<?php
/**
 * Task Management API
 */

header('Content-Type: application/json');
require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/PermissionChecker.php';
require_once __DIR__ . '/Mailer.php';
Config::load();

// Require authentication
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $db = Config::get('db');
    $pdo = new PDO("mysql:host={$db['host']};port={$db['port']};dbname=dashboard_auth", $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Lightweight schema check — DDL lives in migrate.php, run via CLI
    $schemaVersion = '20260614';
    $cacheFile = __DIR__ . '/logs/task_schema.cache';
    if (!file_exists($cacheFile) || file_get_contents($cacheFile) !== $schemaVersion) {
        $check = $pdo->query("SHOW TABLES LIKE 'tasks'");
        if ($check->rowCount() === 0) {
            http_response_code(503);
            echo json_encode(['error' => 'Database schema not initialized. Run api/migrate.php via CLI.']);
            exit;
        }
        if (!is_dir(dirname($cacheFile))) mkdir(dirname($cacheFile), 0755, true);
        file_put_contents($cacheFile, $schemaVersion);
    }

    $currentUser = $_SESSION['username'] ?? 'system';

    $VALID_PRIORITIES = ['low', 'medium', 'high'];
    $VALID_STATUSES = ['pending', 'in-progress', 'completed', 'cancelled'];
    $VALID_CATEGORIES = ['general', 'development', 'design', 'testing', 'documentation', 'maintenance'];

    // Rate limiter for mutations (30/min per user)
    $rateLimiter = null;
    $checkRateLimit = function() use (&$rateLimiter, $currentUser) {
        if ($rateLimiter === null) {
            require_once __DIR__ . '/RateLimiter.php';
            $rateLimiter = new RateLimiter('/tmp/rate_limits', 30, 60);
        }
        return $rateLimiter->checkOrReject('tasks_' . $currentUser);
    };

    // Helper function to get user email and name
    $getUserInfo = function($username) use ($pdo) {
        if (empty($username)) return null;
        $stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        return ($user && !empty($user['email'])) ? $user : null;
    };

    switch ($action) {
        case 'list':
            // Support server-side filtering, sorting, and pagination
            $where = [];
            $params = [];
            
            // Filter by status
            if (!empty($_GET['status'])) {
                $where[] = "status = ?";
                $params[] = $_GET['status'];
            }
            
            // Filter by priority
            if (!empty($_GET['priority'])) {
                $where[] = "priority = ?";
                $params[] = $_GET['priority'];
            }
            
            // Filter by category
            if (!empty($_GET['category'])) {
                $where[] = "category = ?";
                $params[] = $_GET['category'];
            }
            
            // Filter by assigned_to
            if (!empty($_GET['assigned_to'])) {
                $where[] = "assigned_to = ?";
                $params[] = $_GET['assigned_to'];
            }

            // Filter by department (match assigned user's role)
            if (!empty($_GET['department'])) {
                // Uses users.role as department — no schema changes required
                $where[] = "assigned_to IN (SELECT username FROM users WHERE role = ?)";
                $params[] = $_GET['department'];
            }
            
            // Search in title and assigned_to
            if (!empty($_GET['search'])) {
                $search = '%' . $_GET['search'] . '%';
                $where[] = "(title LIKE ? OR assigned_to LIKE ?)";
                $params[] = $search;
                $params[] = $search;
            }
            
            // Filter by overdue only
            if (!empty($_GET['overdue']) && $_GET['overdue'] === '1') {
                $where[] = "due_date < CURDATE() AND status NOT IN ('completed', 'cancelled')";
            }
            
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            
            // Sorting
            $allowedSortFields = ['title', 'status', 'priority', 'assigned_to', 'due_date', 'category', 'created_at', 'updated_at'];
            $sortField = in_array($_GET['sort_field'] ?? 'created_at', $allowedSortFields) ? $_GET['sort_field'] : 'created_at';
            $sortDirection = (isset($_GET['sort_direction']) && strtoupper($_GET['sort_direction']) === 'ASC') ? 'ASC' : 'DESC';
            
            // Pagination
            $page = max(1, intval($_GET['page'] ?? 1));
            $perPage = min(100, max(10, intval($_GET['per_page'] ?? 50))); // Clamp between 10 and 100
            $offset = ($page - 1) * $perPage;
            
            // Get total count for pagination
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks $whereClause");
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();
            
            // Get filtered tasks
            $stmt = $pdo->prepare("SELECT * FROM tasks $whereClause ORDER BY $sortField $sortDirection LIMIT $perPage OFFSET $offset");
            $stmt->execute($params);
            $tasks = $stmt->fetchAll();
            
            echo json_encode([
                'tasks' => $tasks,
                'total' => (int)$total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage),
            ]);
            break;

        case 'get':
            $id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmt->execute([$id]);
            $task = $stmt->fetch();
            if ($task) {
                echo json_encode($task);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Task not found']);
            }
            break;

        case 'create':
            if (!PermissionChecker::hasPermission('can_create_tasks')) {
                http_response_code(403);
                echo json_encode(['error' => 'You do not have permission to create tasks']);
                break;
            }
            if (!$checkRateLimit()) break;

            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true) ?? [];

            $title = trim($input['title'] ?? '');
            if (empty($title) || strlen($title) > 255) {
                http_response_code(400);
                echo json_encode(['error' => 'Title is required (max 255 characters)']);
                break;
            }
            $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

            $priority = $input['priority'] ?? 'medium';
            if (!in_array($priority, $VALID_PRIORITIES)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid priority. Valid: ' . implode(', ', $VALID_PRIORITIES)]);
                break;
            }

            $status = $input['status'] ?? 'pending';
            if (!in_array($status, $VALID_STATUSES)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid status. Valid: ' . implode(', ', $VALID_STATUSES)]);
                break;
            }

            $category = $input['category'] ?? 'general';
            if (!in_array($category, $VALID_CATEGORIES)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid category. Valid: ' . implode(', ', $VALID_CATEGORIES)]);
                break;
            }

            // Duplicate detection: same title by same creator within 24 hours
            $dupStmt = $pdo->prepare("SELECT id FROM tasks WHERE LOWER(title) = LOWER(?) AND created_by = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) LIMIT 1");
            $dupStmt->execute([$title, $currentUser]);
            $existingTask = $dupStmt->fetch();
            if ($existingTask && empty($input['force_create'])) {
                echo json_encode(['duplicate_warning' => true, 'existing_task_id' => (int)$existingTask['id'], 'message' => 'A task with this title was created in the last 24 hours. Send force_create=true to proceed.']);
                break;
            }

            $stmt = $pdo->prepare("INSERT INTO tasks (title, description, priority, status, assigned_to, due_date, category, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $title,
                htmlspecialchars($input['description'] ?? '', ENT_QUOTES, 'UTF-8'),
                $priority,
                $status,
                $input['assigned_to'] ?? '',
                $input['due_date'] ?? null,
                $category,
                $currentUser
            ]);
            $taskId = $pdo->lastInsertId();

            // Log activity
            $pdo->prepare("INSERT INTO task_activity (task_id, action, actor, details) VALUES (?, 'created', ?, ?)")
                ->execute([$taskId, $currentUser, "Task created: $title"]);

            // Send email notification if task is assigned
            $assignedTo = $input['assigned_to'] ?? '';
            if (!empty($assignedTo)) {
                try {
                    $assignedUser = $getUserInfo($assignedTo);
                    if ($assignedUser) {
                        // Send assignment notification to the assigned user
                        Mailer::sendTaskAssignment(
                            $assignedUser['email'],
                            $assignedUser['full_name'] ?: $assignedTo,
                            $title,
                            $input['description'] ?? '',
                            $input['priority'] ?? 'medium',
                            $currentUser,
                            $input['due_date'] ?? null,
                            $taskId
                        );
                        
                        // If creator is different from assignee, notify the creator
                        if ($currentUser !== $assignedTo) {
                            $creatorUser = $getUserInfo($currentUser);
                            if ($creatorUser) {
                                Mailer::sendTaskCreatedNotification(
                                    $creatorUser['email'],
                                    $creatorUser['full_name'] ?: $currentUser,
                                    $title,
                                    $input['description'] ?? '',
                                    $input['priority'] ?? 'medium',
                                    $currentUser,
                                    $assignedTo,
                                    $input['due_date'] ?? null,
                                    $taskId
                                );
                            }
                        }
                    }
                } catch (\Exception $e) {
                    error_log("[tasks.php] Task creation email failed: " . $e->getMessage());
                }
            }
            
            // Send admin notification for high priority tasks
            if (($input['priority'] ?? 'medium') === 'high') {
                try {
                    $priorityLabels = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'];
                    $priorityLabel = $priorityLabels[$input['priority'] ?? 'medium'] ?? 'High';
                    $dueDateText = !empty($input['due_date']) ? "Due: " . date('F j, Y', strtotime($input['due_date'])) : "No due date";
                    
                    $reportContent = "
<p><strong>New High Priority Task Created</strong></p>
<table style=\"background:#f3f4f6;padding:12px;border-radius:6px;margin:16px 0;\">
<tr><td style=\"color:#6b7280;font-size:12px;\">Task</td><td style=\"font-size:14px;font-weight:600;\">$title</td></tr>
<tr><td style=\"color:#6b7280;font-size:12px;\">Priority</td><td style=\"font-size:14px;\">$priorityLabel</td></tr>
<tr><td style=\"color:#6b7280;font-size:12px;\">Due Date</td><td style=\"font-size:14px;\">$dueDateText</td></tr>
<tr><td style=\"color:#6b7280;font-size:12px;\">Created by</td><td style=\"font-size:14px;\">$currentUser</td></tr>
<tr><td style=\"color:#6b7280;font-size:12px;\">Assigned to</td><td style=\"font-size:14px;\">$assignedTo</td></tr>
</table>
";
                    if (!empty($input['description'])) {
                        $reportContent .= "<p style=\"background:#f9fafb;padding:12px;border-radius:4px;font-size:13px;\">" . nl2br(htmlspecialchars(mb_substr($input['description'], 0, 300))) . "</p>";
                    }
                    
                    Mailer::sendAdminNotification(
                        "High Priority Task: $title",
                        'High Priority Task Alert',
                        $reportContent
                    );
                } catch (\Exception $e) {
                    error_log("[tasks.php] Admin notification failed: " . $e->getMessage());
                }
            }

            echo json_encode(['success' => true, 'id' => $taskId]);
            break;

        case 'update':
            if (!$checkRateLimit()) break;

            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true) ?? [];

            $id = $input['id'] ?? 0;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Task ID is required']);
                break;
            }

            // Get old task for ownership and status checks
            $oldStmt = $pdo->prepare("SELECT title, status, assigned_to, created_by FROM tasks WHERE id = ?");
            $oldStmt->execute([$id]);
            $oldTask = $oldStmt->fetch();

            // Permission check: only the task owner may update (no edit by others)
            $isOwner = ($currentUser === $oldTask['created_by']);
            if (!$isOwner) {
                http_response_code(403);
                echo json_encode(['error' => 'Only the task owner can update this task']);
                break;
            }
            // Owner-level permission required
            if (!PermissionChecker::hasPermission('can_update_own_tasks')) {
                http_response_code(403);
                echo json_encode(['error' => 'You do not have permission to update your own tasks']);
                break;
            }

            // Validate enum fields before building query
            if (isset($input['priority']) && !in_array($input['priority'], $VALID_PRIORITIES)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid priority. Valid: ' . implode(', ', $VALID_PRIORITIES)]);
                break;
            }
            if (isset($input['status']) && !in_array($input['status'], $VALID_STATUSES)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid status. Valid: ' . implode(', ', $VALID_STATUSES)]);
                break;
            }
            if (isset($input['category']) && !in_array($input['category'], $VALID_CATEGORIES)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid category. Valid: ' . implode(', ', $VALID_CATEGORIES)]);
                break;
            }
            if (isset($input['title'])) {
                $input['title'] = htmlspecialchars(trim($input['title']), ENT_QUOTES, 'UTF-8');
                if (empty($input['title']) || strlen($input['title']) > 255) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Title is required (max 255 characters)']);
                    break;
                }
            }

            $fields = [];
            $values = [];
            $allowedFields = ['title', 'description', 'priority', 'status', 'assigned_to', 'due_date', 'category'];
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $fields[] = "$field = ?";
                    $values[] = $input[$field];
                }
            }

            if (empty($fields)) {
                http_response_code(400);
                echo json_encode(['error' => 'No fields to update']);
                break;
            }

            $fields[] = "updated_at = NOW()";
            $values[] = $id;

            $stmt = $pdo->prepare("UPDATE tasks SET " . implode(', ', $fields) . " WHERE id = ?");
            $stmt->execute($values);

            // Log activity
            $newStatus = $input['status'] ?? $oldTask['status'];
            if ($newStatus !== $oldTask['status']) {
                $pdo->prepare("INSERT INTO task_activity (task_id, action, actor, details) VALUES (?, 'status_changed', ?, ?)")
                    ->execute([$id, $currentUser, "Status: {$oldTask['status']} → $newStatus"]);

                // Send email notification for status change
                $assignedTo = $oldTask['assigned_to'] ?? '';
                if (!empty($assignedTo)) {
                    try {
                        $assignedUser = $getUserInfo($assignedTo);
                        if ($assignedUser) {
                            Mailer::sendTaskStatusChange(
                                $assignedUser['email'],
                                $assignedUser['full_name'] ?: $assignedTo,
                                $oldTask['title'],
                                $oldTask['status'],
                                $newStatus,
                                $currentUser,
                                $id
                            );
                            
                            // If task is completed, send additional completion notification
                            if ($newStatus === 'completed') {
                                Mailer::sendTaskCompleted(
                                    $assignedUser['email'],
                                    $assignedUser['full_name'] ?: $assignedTo,
                                    $oldTask['title'],
                                    $currentUser,
                                    $id
                                );
                            }
                        }
                    } catch (\Exception $e) {
                        error_log("[tasks.php] Status change email failed: " . $e->getMessage());
                    }
                }
                
                // Send admin notification for task completion
                if ($newStatus === 'completed') {
                    try {
                        $reportContent = "
<p><strong>Task Completed</strong></p>
<table style=\"background:#f3f4f6;padding:12px;border-radius:6px;margin:16px 0;\">
<tr><td style=\"color:#6b7280;font-size:12px;\">Task</td><td style=\"font-size:14px;font-weight:600;\">{$oldTask['title']}</td></tr>
<tr><td style=\"color:#6b7280;font-size:12px;\">Completed by</td><td style=\"font-size:14px;\">$currentUser</td></tr>
<tr><td style=\"color:#6b7280;font-size:12px;\">Assigned to</td><td style=\"font-size:14px;\">$assignedTo</td></tr>
</table>
";
                        Mailer::sendAdminNotification(
                            "Task Completed: {$oldTask['title']}",
                            'Task Completion Report',
                            $reportContent
                        );
                    } catch (\Exception $e) {
                        error_log("[tasks.php] Completion admin notification failed: " . $e->getMessage());
                    }
                }
            } else {
                $pdo->prepare("INSERT INTO task_activity (task_id, action, actor, details) VALUES (?, 'updated', ?, ?)")
                    ->execute([$id, $currentUser, "Task updated"]);
            }

            echo json_encode(['success' => true]);
            break;

        case 'delete':
            if (!$checkRateLimit()) break;

            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true) ?? [];

            $id = $input['id'] ?? 0;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Task ID is required']);
                break;
            }

            if (!PermissionChecker::hasPermission('can_delete_tasks')) {
                http_response_code(403);
                echo json_encode(['error' => 'You do not have permission to delete tasks']);
                break;
            }

            $stmt = $pdo->prepare("SELECT title FROM tasks WHERE id = ?");
            $stmt->execute([$id]);
            $task = $stmt->fetch();

            $pdo->prepare("DELETE FROM tasks WHERE id = ?")->execute([$id]);

            // Log activity
            $pdo->prepare("INSERT INTO task_activity (task_id, action, actor, details) VALUES (?, 'deleted', ?, ?)")
                ->execute([$id, $currentUser, "Task deleted: {$task['title']}"]);

            echo json_encode(['success' => true]);
            break;

        case 'bulk_update':
            if (!PermissionChecker::hasPermission('can_update_any_task')) {
                http_response_code(403);
                echo json_encode(['error' => 'You do not have permission to bulk update tasks']);
                break;
            }
            if (!$checkRateLimit()) break;

            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true) ?? [];

            $ids = $input['ids'] ?? [];
            if (empty($ids) || !is_array($ids)) {
                http_response_code(400);
                echo json_encode(['error' => 'Task IDs array is required']);
                break;
            }

            // Validate enum fields
            if (isset($input['status']) && !in_array($input['status'], $VALID_STATUSES)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid status. Valid: ' . implode(', ', $VALID_STATUSES)]);
                break;
            }
            if (isset($input['priority']) && !in_array($input['priority'], $VALID_PRIORITIES)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid priority. Valid: ' . implode(', ', $VALID_PRIORITIES)]);
                break;
            }
            if (isset($input['category']) && !in_array($input['category'], $VALID_CATEGORIES)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid category. Valid: ' . implode(', ', $VALID_CATEGORIES)]);
                break;
            }

            $fields = [];
            $values = [];
            $allowedFields = ['status', 'priority', 'assigned_to', 'due_date', 'category'];
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $fields[] = "$field = ?";
                    $values[] = $input[$field];
                }
            }

            if (empty($fields)) {
                http_response_code(400);
                echo json_encode(['error' => 'No fields to update']);
                break;
            }

            $fields[] = "updated_at = NOW()";
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $values = array_merge($values, $ids);

            $stmt = $pdo->prepare("UPDATE tasks SET " . implode(', ', $fields) . " WHERE id IN ($placeholders)");
            $stmt->execute($values);
            $updated = $stmt->rowCount();

            // Fetch all task titles in a single query (fix N+1 problem)
            $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
            $titleStmt = $pdo->prepare("SELECT id, title FROM tasks WHERE id IN ($idPlaceholders)");
            $titleStmt->execute($ids);
            $taskTitles = $titleStmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Log activity for each updated task
            $completedTasks = [];
            foreach ($ids as $taskId) {
                $taskTitle = $taskTitles[$taskId] ?? 'Unknown Task';
                if (isset($input['status'])) {
                    $pdo->prepare("INSERT INTO task_activity (task_id, action, actor, details) VALUES (?, 'bulk_status_changed', ?, ?)")
                        ->execute([$taskId, $currentUser, "Bulk update: status → {$input['status']}"]);
                    
                    // Track completed tasks for admin notification
                    if ($input['status'] === 'completed') {
                        $completedTasks[] = $taskTitle;
                    }
                } else {
                    $pdo->prepare("INSERT INTO task_activity (task_id, action, actor, details) VALUES (?, 'bulk_updated', ?, ?)")
                        ->execute([$taskId, $currentUser, "Bulk updated: $taskTitle"]);
                }
            }
            
            // Send admin notification for bulk completion
            if (!empty($completedTasks)) {
                try {
                    $taskCount = count($completedTasks);
                    $taskListItems = implode('', array_map(function($t) {
                        return "<li style=\"margin:4px 0;\">$t</li>";
                    }, $completedTasks));
                    
                    $reportContent = "
<p><strong>Bulk Task Completion</strong></p>
<p>The following <strong>$taskCount task" . ($taskCount > 1 ? 's have' : 'has') . "</strong> been marked as completed:</p>
<ul style=\"background:#f3f4f6;padding:16px 16px 16px 32px;border-radius:6px;margin:16px 0;\">
$taskListItems
</ul>
<p><strong>Updated by:</strong> $currentUser<br>
<strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>
";
                    Mailer::sendAdminNotification(
                        "Bulk Task Completion: $taskCount task" . ($taskCount > 1 ? 's' : ''),
                        'Bulk Task Completion Report',
                        $reportContent
                    );
                } catch (\Exception $e) {
                    error_log("[tasks.php] Bulk completion notification failed: " . $e->getMessage());
                }
            }

            echo json_encode(['success' => true, 'updated' => $updated]);
            break;

        case 'link_task':
            if (!PermissionChecker::hasPermission('can_update_any_task')) {
                http_response_code(403);
                echo json_encode(['error' => 'You do not have permission to link tasks']);
                break;
            }

            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true) ?? [];

            $taskId = $input['task_id'] ?? 0;
            $linkedTaskId = $input['linked_task_id'] ?? 0;
            $linkType = $input['link_type'] ?? 'related';

            if (!$taskId || !$linkedTaskId) {
                http_response_code(400);
                echo json_encode(['error' => 'Task ID and linked task ID are required']);
                break;
            }

            if ($taskId === $linkedTaskId) {
                http_response_code(400);
                echo json_encode(['error' => 'Cannot link a task to itself']);
                break;
            }

            $validLinkTypes = ['blocks', 'blocked-by', 'related', 'duplicate-of'];
            if (!in_array($linkType, $validLinkTypes)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid link type. Valid: ' . implode(', ', $validLinkTypes)]);
                break;
            }

            // Check if link already exists
            $checkStmt = $pdo->prepare("SELECT id FROM task_links WHERE task_id = ? AND linked_task_id = ? AND link_type = ?");
            $checkStmt->execute([$taskId, $linkedTaskId, $linkType]);
            if ($checkStmt->fetch()) {
                echo json_encode(['success' => true, 'message' => 'Link already exists']);
                break;
            }

            $stmt = $pdo->prepare("INSERT INTO task_links (task_id, linked_task_id, link_type, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$taskId, $linkedTaskId, $linkType, $currentUser]);

            $pdo->prepare("INSERT INTO task_activity (task_id, action, actor, details) VALUES (?, 'task_linked', ?, ?)")
                ->execute([$taskId, $currentUser, "Linked to task #$linkedTaskId ($linkType)"]);

            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        case 'get_task_links':
            $taskId = $_GET['task_id'] ?? 0;
            $stmt = $pdo->prepare("SELECT tl.*, t.title as linked_title, t.status as linked_status, t.priority as linked_priority 
                FROM task_links tl 
                JOIN tasks t ON tl.linked_task_id = t.id 
                WHERE tl.task_id = ? 
                ORDER BY tl.created_at DESC");
            $stmt->execute([$taskId]);
            echo json_encode($stmt->fetchAll());
            break;

        case 'unlink_task':
            if (!PermissionChecker::hasPermission('can_update_any_task')) {
                http_response_code(403);
                echo json_encode(['error' => 'You do not have permission to unlink tasks']);
                break;
            }

            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true) ?? [];

            $id = $input['id'] ?? 0;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Link ID is required']);
                break;
            }

            $stmt = $pdo->prepare("SELECT task_id FROM task_links WHERE id = ?");
            $stmt->execute([$id]);
            $linkData = $stmt->fetch();

            $pdo->prepare("DELETE FROM task_links WHERE id = ?")->execute([$id]);

            if ($linkData) {
                $pdo->prepare("INSERT INTO task_activity (task_id, action, actor, details) VALUES (?, 'task_unlinked', ?, ?)")
                    ->execute([$linkData['task_id'], $currentUser, "Task link removed"]);
            }

            echo json_encode(['success' => true]);
            break;

        case 'notes':
            $taskId = $_GET['task_id'] ?? 0;
            $stmt = $pdo->prepare("SELECT * FROM task_notes WHERE task_id = ? ORDER BY is_pinned DESC, created_at ASC");
            $stmt->execute([$taskId]);
            echo json_encode($stmt->fetchAll());
            break;

        case 'add_note':
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true) ?? [];

            $taskId = $input['task_id'] ?? 0;
            $content = trim($input['content'] ?? '');
            if (empty($content) || !$taskId) {
                http_response_code(400);
                echo json_encode(['error' => 'Task ID and content are required']);
                break;
            }

            $validCategories = ['tuning', 'fix', 'implementation', 'question', 'general'];
            $category = in_array($input['category'] ?? 'general', $validCategories) ? $input['category'] : 'general';

            $stmt = $pdo->prepare("INSERT INTO task_notes (task_id, author, content, category, is_pinned, parent_id) VALUES (?, ?, ?, ?, 0, ?)");
            $stmt->execute([$taskId, $currentUser, $content, $category, $input['parent_id'] ?? null]);

            $pdo->prepare("INSERT INTO task_activity (task_id, action, actor, details) VALUES (?, 'commented', ?, ?)")
                ->execute([$taskId, $currentUser, "Note added: " . mb_substr($content, 0, 50)]);

            // Send email notification to task assignee
            try {
                $taskStmt = $pdo->prepare("SELECT title, assigned_to FROM tasks WHERE id = ?");
                $taskStmt->execute([$taskId]);
                $task = $taskStmt->fetch();
                
                if ($task && !empty($task['assigned_to'])) {
                    $assignedUser = $getUserInfo($task['assigned_to']);
                    
                    if ($assignedUser && $task['assigned_to'] !== $currentUser) {
                        Mailer::sendTaskNoteAdded(
                            $assignedUser['email'],
                            $assignedUser['full_name'] ?: $task['assigned_to'],
                            $task['title'],
                            $content,
                            $currentUser,
                            $taskId
                        );
                    }
                }
            } catch (\Exception $e) {
                error_log("[tasks.php] Task note email failed: " . $e->getMessage());
            }

            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        case 'edit_note':
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true) ?? [];

            $id = $input['id'] ?? 0;
            $content = trim($input['content'] ?? '');
            if (!$id || empty($content)) {
                http_response_code(400);
                echo json_encode(['error' => 'Note ID and content are required']);
                break;
            }

            // Check if note exists
            $stmt = $pdo->prepare("SELECT author FROM task_notes WHERE id = ?");
            $stmt->execute([$id]);
            $note = $stmt->fetch();
            if (!$note) {
                http_response_code(404);
                echo json_encode(['error' => 'Note not found']);
                break;
            }

            // Allow edit if user is author or has can_edit_any_note permission
            $isAuthor = ($note['author'] === $currentUser);
            $canEditAny = PermissionChecker::hasPermission('can_edit_any_note');
            if (!$isAuthor && !$canEditAny) {
                http_response_code(403);
                echo json_encode(['error' => 'You can only edit your own notes']);
                break;
            }

            $validCategories = ['tuning', 'fix', 'implementation', 'question', 'general'];
            $category = in_array($input['category'] ?? 'general', $validCategories) ? $input['category'] : 'general';

            $stmt = $pdo->prepare("UPDATE task_notes SET content = ?, category = ? WHERE id = ?");
            $stmt->execute([$content, $category, $id]);

            echo json_encode(['success' => true]);
            break;

        case 'pin_note':
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true) ?? [];

            $id = $input['id'] ?? 0;
            $pinned = (int)($input['is_pinned'] ?? 1);
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Note ID is required']);
                break;
            }

            if (!PermissionChecker::hasPermission('can_pin_notes')) {
                http_response_code(403);
                echo json_encode(['error' => 'You do not have permission to pin notes']);
                break;
            }

            $stmt = $pdo->prepare("UPDATE task_notes SET is_pinned = ? WHERE id = ?");
            $stmt->execute([$pinned, $id]);

            echo json_encode(['success' => true]);
            break;

        case 'delete_note':
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true) ?? [];

            $id = $input['id'] ?? 0;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Note ID is required']);
                break;
            }

            // Check ownership: author or can_delete_any_note
            $stmt = $pdo->prepare("SELECT author FROM task_notes WHERE id = ?");
            $stmt->execute([$id]);
            $note = $stmt->fetch();
            if (!$note) {
                http_response_code(404);
                echo json_encode(['error' => 'Note not found']);
                break;
            }

            $isAuthor = ($note['author'] === $currentUser);
            $canDeleteAny = PermissionChecker::hasPermission('can_delete_any_note');
            if (!$isAuthor && !$canDeleteAny) {
                http_response_code(403);
                echo json_encode(['error' => 'You can only delete your own notes']);
                break;
            }

            $pdo->prepare("DELETE FROM task_notes WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'activity':
            $taskId = $_GET['task_id'] ?? 0;
            $stmt = $pdo->prepare("SELECT * FROM task_activity WHERE task_id = ? ORDER BY created_at DESC");
            $stmt->execute([$taskId]);
            echo json_encode($stmt->fetchAll());
            break;

        case 'stats':
            $statsStmt = $pdo->query("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'in-progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
            FROM tasks");
            echo json_encode($statsStmt->fetch());
            break;

        case 'notes_counts':
            // Get note counts for all tasks in a single query (avoids N+1 problem)
            $stmt = $pdo->query("SELECT task_id, COUNT(*) as count FROM task_notes GROUP BY task_id");
            $counts = [];
            foreach ($stmt->fetchAll() as $row) {
                $counts[$row['task_id']] = (int)$row['count'];
            }
            echo json_encode($counts);
            break;

        case 'upload_screenshot':
            if (!PermissionChecker::hasPermission('can_add_task_notes')) {
                http_response_code(403);
                echo json_encode(['error' => 'You do not have permission to add screenshots']);
                break;
            }

            $taskId = $_POST['task_id'] ?? 0;
            $caption = trim($_POST['caption'] ?? '');
            if (!$taskId) {
                http_response_code(400);
                echo json_encode(['error' => 'Task ID is required']);
                break;
            }

            if (!isset($_FILES['screenshot']) || $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(['error' => 'Screenshot file is required']);
                break;
            }

            $file = $_FILES['screenshot'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file['type'], $allowedTypes)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid file type. Only images are allowed']);
                break;
            }

            if ($file['size'] > 5 * 1024 * 1024) {
                http_response_code(400);
                echo json_encode(['error' => 'File size must be less than 5MB']);
                break;
            }

            // Create upload directory
            $uploadDir = __DIR__ . '/uploads/screenshots';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Generate unique filename
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'task_' . $taskId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $filepath = $uploadDir . '/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $relativePath = '/uploads/screenshots/' . $filename;
                $stmt = $pdo->prepare("INSERT INTO task_screenshots (task_id, author, file_path, caption) VALUES (?, ?, ?, ?)");
                $stmt->execute([$taskId, $currentUser, $relativePath, $caption]);

                $pdo->prepare("INSERT INTO task_activity (task_id, action, actor, details) VALUES (?, 'screenshot_added', ?, ?)")
                    ->execute([$taskId, $currentUser, "Screenshot uploaded: $filename"]);

                echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'file_path' => $relativePath]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to upload file']);
            }
            break;

        case 'get_screenshots':
            $taskId = $_GET['task_id'] ?? 0;
            $stmt = $pdo->prepare("SELECT * FROM task_screenshots WHERE task_id = ? ORDER BY created_at DESC");
            $stmt->execute([$taskId]);
            echo json_encode($stmt->fetchAll());
            break;

        case 'delete_screenshot':
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true) ?? [];

            $id = $input['id'] ?? 0;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Screenshot ID is required']);
                break;
            }

            if (!PermissionChecker::hasPermission('can_delete_tasks')) {
                http_response_code(403);
                echo json_encode(['error' => 'You do not have permission to delete screenshots']);
                break;
            }

            // Get file path to delete physical file
            $stmt = $pdo->prepare("SELECT file_path, task_id FROM task_screenshots WHERE id = ?");
            $stmt->execute([$id]);
            $screenshot = $stmt->fetch();

            if ($screenshot) {
                $filePath = __DIR__ . $screenshot['file_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }

                $pdo->prepare("DELETE FROM task_screenshots WHERE id = ?")->execute([$id]);

                $pdo->prepare("INSERT INTO task_activity (task_id, action, actor, details) VALUES (?, 'screenshot_deleted', ?, ?)")
                    ->execute([$screenshot['task_id'], $currentUser, "Screenshot deleted: ID $id"]);
            }

            echo json_encode(['success' => true]);
            break;

        case 'forward_note':
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true) ?? [];

            $noteId = $input['note_id'] ?? 0;
            $targetTaskId = $input['target_task_id'] ?? 0;
            if (!$noteId || !$targetTaskId) {
                http_response_code(400);
                echo json_encode(['error' => 'Note ID and target task ID are required']);
                break;
            }

            // Get original note
            $stmt = $pdo->prepare("SELECT * FROM task_notes WHERE id = ?");
            $stmt->execute([$noteId]);
            $note = $stmt->fetch();
            if (!$note) {
                http_response_code(404);
                echo json_encode(['error' => 'Note not found']);
                break;
            }

            // Check permission: note author or can_edit_any_note
            $isAuthor = ($note['author'] === $currentUser);
            $canEditAny = PermissionChecker::hasPermission('can_edit_any_note');
            if (!$isAuthor && !$canEditAny) {
                http_response_code(403);
                echo json_encode(['error' => 'You can only forward your own notes']);
                break;
            }

            // Create forwarded note on target task
            $forwardedContent = "[Forwarded from Task #{$note['task_id']} by {$currentUser}]\n\n" . $note['content'];
            $stmt = $pdo->prepare("INSERT INTO task_notes (task_id, author, content, category, is_pinned, status, parent_id) VALUES (?, ?, ?, ?, 0, 'active', ?)");
            $stmt->execute([$targetTaskId, $currentUser, $forwardedContent, $note['category'], $noteId]);

            $pdo->prepare("INSERT INTO task_activity (task_id, action, actor, details) VALUES (?, 'note_forwarded', ?, ?)")
                ->execute([$targetTaskId, $currentUser, "Note forwarded from task #{$note['task_id']}"]);

            $pdo->prepare("INSERT INTO task_activity (task_id, action, actor, details) VALUES (?, 'note_forwarded', ?, ?)")
                ->execute([$note['task_id'], $currentUser, "Note forwarded to task #$targetTaskId"]);

            echo json_encode(['success' => true, 'new_note_id' => $pdo->lastInsertId()]);
            break;

        case 'set_note_status':
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true) ?? [];

            $id = $input['note_id'] ?? 0;
            $status = $input['status'] ?? 'active';
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'Note ID is required']);
                break;
            }

            $validStatuses = ['draft', 'active', 'reviewed', 'action-required'];
            if (!in_array($status, $validStatuses)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid status. Valid values: ' . implode(', ', $validStatuses)]);
                break;
            }

            $stmt = $pdo->prepare("SELECT task_id, author FROM task_notes WHERE id = ?");
            $stmt->execute([$id]);
            $noteData = $stmt->fetch();
            if (!$noteData) {
                http_response_code(404);
                echo json_encode(['error' => 'Note not found']);
                break;
            }

            // Check permission: note author or can_edit_any_note
            $isAuthor = ($noteData['author'] === $currentUser);
            $canEditAny = PermissionChecker::hasPermission('can_edit_any_note');
            if (!$isAuthor && !$canEditAny) {
                http_response_code(403);
                echo json_encode(['error' => 'You can only change status of your own notes']);
                break;
            }

            $stmt = $pdo->prepare("UPDATE task_notes SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);

            $pdo->prepare("INSERT INTO task_activity (task_id, action, actor, details) VALUES (?, 'note_status_changed', ?, ?)")
                ->execute([$noteData['task_id'], $currentUser, "Note #$id status set to $status"]);

            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
    }

} catch (Exception $e) {
    error_log("[tasks.php] Unhandled error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An internal error occurred. Please try again later.']);
}

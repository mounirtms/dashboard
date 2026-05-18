<?php
/**
 * Mailer - Email sending utility using PHP mail()
 */

require_once __DIR__ . '/EmailNotificationLogger.php';

class Mailer {
    // Default email settings (can be overridden by database settings)
    private const FROM_EMAIL = 'alerts@dashboard.technostationery.com';
    private const FROM_NAME = 'Techno Dashboard';
    private const ADMIN_EMAILS = [
        'admin@dashboard.technostationery.com',
        'webmaster@techno-dz.com'
    ];
    
    // Cached settings
    private static $settingsCache = null;

    /**
     * Send an HTML email
     */
    private static function send($to, $subject, $htmlBody) {
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log("[Mailer] Invalid email: $to");
            EmailNotificationLogger::log('invalid_email', $to, $subject, false, 'Invalid email address');
            return false;
        }

        // Load settings from database or use defaults
        $settings = self::loadSettings();
        $fromEmail = $settings['from_email'] ?? self::FROM_EMAIL;
        $fromName = $settings['from_name'] ?? self::FROM_NAME;

        $headers = [
            'From: ' . $fromName . ' <' . $fromEmail . '>',
            'Reply-To: ' . $fromEmail,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'X-Mailer: TechnoDashboard/1.0'
        ];

        $result = mail($to, $subject, $htmlBody, implode("\r\n", $headers));
        
        // Log the attempt
        $type = self::extractTypeFromSubject($subject);
        EmailNotificationLogger::log($type, $to, $subject, $result, $result ? null : 'mail() function returned false');
        
        error_log("[Mailer] Sent to $to | Subject: $subject | Result: " . ($result ? 'OK' : 'FAIL'));
        return $result;
    }
    
    /**
     * Extract notification type from subject line for logging
     */
    private static function extractTypeFromSubject($subject) {
        if (strpos($subject, 'New Task Assigned') === 0) return 'task_assignment';
        if (strpos($subject, 'New Task Created') === 0) return 'task_created';
        if (strpos($subject, 'Task Status Updated') === 0) return 'task_status_change';
        if (strpos($subject, 'Task Completed') === 0) return 'task_completed';
        if (strpos($subject, 'New Note on Task') === 0) return 'task_note';
        if (strpos($subject, 'High Priority Task') === 0) return 'admin_high_priority';
        if (strpos($subject, 'Task Completion Report') === 0) return 'admin_completion';
        if (strpos($subject, 'Bulk Task Completion') === 0) return 'admin_bulk_completion';
        if (strpos($subject, 'System Report') === 0) return 'admin_system_report';
        if (strpos($subject, 'Major Update') === 0) return 'admin_major_update';
        return 'other';
    }

    /**
     * Wrap content in the standard HTML email template
     */
    private static function wrapTemplate($title, $content) {
        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:40px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,0.1);">
<tr><td style="background:#1e3a5f;padding:24px 32px;">
<h1 style="margin:0;color:#fff;font-size:20px;font-weight:700;">Techno Dashboard</h1>
</td></tr>
<tr><td style="padding:32px;">
<h2 style="margin:0 0 16px;color:#1e3a5f;font-size:18px;">$title</h2>
<div style="color:#374151;font-size:14px;line-height:1.6;">$content</div>
</td></tr>
<tr><td style="background:#f9fafb;padding:16px 32px;text-align:center;color:#9ca3af;font-size:12px;">
This is an automated message from Techno Dashboard. Please do not reply.
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;
    }

    /**
     * Send password reset email (admin-initiated)
     */
    public static function sendPasswordReset($to, $username, $tempPassword) {
        $content = "
<p>Hello <strong>$username</strong>,</p>
<p>Your dashboard password has been reset by an administrator.</p>
<p><strong>Temporary Password:</strong></p>
<p style=\"background:#f3f4f6;padding:12px;border-radius:6px;font-family:monospace;font-size:16px;letter-spacing:1px;\">$tempPassword</p>
<p>Please log in and change your password immediately.</p>
<p><a href=\"https://dashboard.technostationery.com\" style=\"display:inline-block;background:#1e3a5f;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;margin-top:16px;\">Go to Dashboard</a></p>
";
        return self::send($to, 'Your Dashboard Password Has Been Reset', self::wrapTemplate('Password Reset', $content));
    }

    /**
     * Send welcome / account created email
     */
    public static function sendAccountCreated($to, $username, $tempPassword) {
        $content = "
<p>Hello <strong>$username</strong>,</p>
<p>A new dashboard account has been created for you.</p>
<p><strong>Your Login Credentials:</strong></p>
<table style=\"background:#f3f4f6;padding:12px;border-radius:6px;margin:16px 0;\">
<tr><td style=\"color:#6b7280;font-size:12px;\">Username</td><td style=\"font-family:monospace;font-size:14px;font-weight:600;\">$username</td></tr>
<tr><td style=\"color:#6b7280;font-size:12px;\">Password</td><td style=\"font-family:monospace;font-size:14px;font-weight:600;\">$tempPassword</td></tr>
</table>
<p>Please log in and change your password right away.</p>
<p><a href=\"https://dashboard.technostationery.com\" style=\"display:inline-block;background:#1e3a5f;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;margin-top:16px;\">Go to Dashboard</a></p>
";
        return self::send($to, 'Welcome to Techno Dashboard', self::wrapTemplate('Account Created', $content));
    }

    /**
     * Send login notification email
     */
    public static function sendLoginNotification($to, $username, $ipAddress) {
        $time = date('Y-m-d H:i:s');
        $content = "
<p>Hello <strong>$username</strong>,</p>
<p>A new login to your dashboard account was detected.</p>
<table style=\"background:#f3f4f6;padding:12px;border-radius:6px;margin:16px 0;\">
<tr><td style=\"color:#6b7280;font-size:12px;\">Time</td><td style=\"font-size:14px;\">$time</td></tr>
<tr><td style=\"color:#6b7280;font-size:12px;\">IP Address</td><td style=\"font-family:monospace;font-size:14px;\">$ipAddress</td></tr>
</table>
<p>If this was you, no action is needed. If you don't recognize this login, please contact an administrator.</p>
";
        return self::send($to, 'New Login to Your Dashboard Account', self::wrapTemplate('Login Notification', $content));
    }

    /**
     * Send password changed confirmation email
     */
    public static function sendPasswordChanged($to, $username) {
        $content = "
<p>Hello <strong>$username</strong>,</p>
<p>Your dashboard password has been successfully changed.</p>
<p>If you did not make this change, please contact an administrator immediately.</p>
<p><a href=\"https://dashboard.technostationery.com\" style=\"display:inline-block;background:#1e3a5f;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;margin-top:16px;\">Go to Dashboard</a></p>
";
        return self::send($to, 'Dashboard Password Changed', self::wrapTemplate('Password Changed', $content));
    }

    /**
     * Send forgot password reset link email
     */
    public static function sendForgotPassword($to, $username, $resetToken) {
        $resetUrl = "https://dashboard.technostationery.com/#/reset-password?token=$resetToken";
        $content = "
<p>Hello <strong>$username</strong>,</p>
<p>We received a request to reset your dashboard password.</p>
<p><a href=\"$resetUrl\" style=\"display:inline-block;background:#1e3a5f;color:#fff;padding:12px 32px;border-radius:6px;text-decoration:none;margin:16px 0;\">Reset Your Password</a></p>
<p>This link will expire in <strong>1 hour</strong>. If you did not request a password reset, you can safely ignore this email.</p>
<p style=\"font-size:12px;color:#6b7280;\">If the button doesn't work, copy and paste this URL into your browser:</p>
<p style=\"font-family:monospace;font-size:11px;color:#6b7280;word-break:break-all;\">$resetUrl</p>
";
        return self::send($to, 'Reset Your Dashboard Password', self::wrapTemplate('Password Reset Request', $content));
    }

    /**
     * Send task assignment notification email
     */
    public static function sendTaskAssignment($to, $assigneeName, $taskTitle, $taskDescription, $priority, $assignedBy, $dueDate = null, $taskId = null) {
        $taskUrl = $taskId ? "https://dashboard.technostationery.com/#/tasks/$taskId" : "https://dashboard.technostationery.com/#/tasks";
        $priorityLabels = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'];
        $priorityLabel = $priorityLabels[$priority] ?? $priority;
        $dueDateText = $dueDate ? "Due date: " . date('F j, Y', strtotime($dueDate)) : "No due date set";

        $content = "
<p>Hello <strong>$assigneeName</strong>,</p>
<p>You have been assigned a new task in the Techno Dashboard.</p>
<table style=\"background:#f3f4f6;padding:16px;border-radius:6px;margin:16px 0;\">
<tr><td style=\"color:#6b7280;font-size:12px;\">Task</td><td style=\"font-size:14px;font-weight:600;\">$taskTitle</td></tr>
<tr><td style=\"color:#6b7280;font-size:12px;\">Priority</td><td style=\"font-size:14px;\">$priorityLabel</td></tr>
<tr><td style=\"color:#6b7280;font-size:12px;\">Due</td><td style=\"font-size:14px;\">$dueDateText</td></tr>
<tr><td style=\"color:#6b7280;font-size:12px;\">Assigned by</td><td style=\"font-size:14px;\">$assignedBy</td></tr>
</table>
";
        if (!empty($taskDescription)) {
            $content .= "<p><strong>Description:</strong></p><p style=\"background:#f9fafb;padding:12px;border-radius:4px;font-size:13px;\">" . nl2br(htmlspecialchars($taskDescription)) . "</p>";
        }

        $content .= "
<p><a href=\"$taskUrl\" style=\"display:inline-block;background:#1e3a5f;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;margin-top:16px;\">View Task</a></p>
";
        return self::send($to, "New Task Assigned: $taskTitle", self::wrapTemplate('New Task Assigned', $content));
    }

    /**
     * Send task status change notification
     */
    public static function sendTaskStatusChange($to, $assigneeName, $taskTitle, $oldStatus, $newStatus, $changedBy, $taskId = null) {
        $taskUrl = $taskId ? "https://dashboard.technostationery.com/#/tasks/$taskId" : "https://dashboard.technostationery.com/#/tasks";
        $statusLabels = ['pending' => 'Pending', 'in-progress' => 'In Progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
        $oldLabel = $statusLabels[$oldStatus] ?? $oldStatus;
        $newLabel = $statusLabels[$newStatus] ?? $newStatus;

        $content = "
<p>Hello <strong>$assigneeName</strong>,</p>
<p>The status of task <strong>\"$taskTitle\"</strong> has been changed.</p>
<table style=\"background:#f3f4f6;padding:16px;border-radius:6px;margin:16px 0;\">
<tr><td style=\"color:#6b7280;font-size:12px;\">From</td><td style=\"font-size:14px;\">$oldLabel</td></tr>
<tr><td style=\"color:#6b7280;font-size:12px;\">To</td><td style=\"font-size:14px;font-weight:600;\">$newLabel</td></tr>
<tr><td style=\"color:#6b7280;font-size:12px;\">Changed by</td><td style=\"font-size:14px;\">$changedBy</td></tr>
</table>
<p><a href=\"$taskUrl\" style=\"display:inline-block;background:#1e3a5f;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;margin-top:16px;\">View Task</a></p>
";
        return self::send($to, "Task Status Updated: $taskTitle", self::wrapTemplate('Task Status Updated', $content));
    }

    /**
     * Send admin notification for major updates and reports
     * Sends to both admin addresses
     */
    public static function sendAdminNotification($subject, $title, $content) {
        $adminEmails = [
            'admin@dashboard.technostationery.com',
            'webmaster@techno-dz.com'
        ];
        
        $results = [];
        foreach ($adminEmails as $email) {
            try {
                $results[$email] = self::send($email, $subject, self::wrapTemplate($title, $content));
                error_log("[Mailer] Admin notification sent to $email: $subject");
            } catch (\Exception $e) {
                error_log("[Mailer] Admin notification failed for $email: " . $e->getMessage());
                $results[$email] = false;
            }
        }
        
        return $results;
    }

    /**
     * Send system report notification to admins
     */
    public static function sendSystemReport($reportTitle, $reportContent, $reportType = 'System Report') {
        $time = date('Y-m-d H:i:s');
        $hostname = gethostname();
        
        $content = "
<p>Hello Administrator,</p>
<p>A new <strong>$reportType</strong> has been generated.</p>
<table style=\"background:#f3f4f6;padding:16px;border-radius:6px;margin:16px 0;\">
<tr><td style=\"color:#6b7280;font-size:12px;\">Report</td><td style=\"font-size:14px;font-weight:600;\">$reportTitle</td></tr>
<tr><td style=\"color:#6b7280;font-size:12px;\">Generated</td><td style=\"font-size:14px;\">$time</td></tr>
<tr><td style=\"color:#6b7280;font-size:12px;\">Server</td><td style=\"font-size:14px;\">$hostname</td></tr>
</table>
<div style=\"background:#f9fafb;padding:16px;border-radius:4px;margin:16px 0;\">
$reportContent
</div>
<p><a href=\"https://dashboard.technostationery.com\" style=\"display:inline-block;background:#1e3a5f;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;margin-top:16px;\">View Dashboard</a></p>
";
        
        return self::sendAdminNotification("System Report: $reportTitle", $reportType, $content);
    }

    /**
     * Send major update notification to admins
     */
    public static function sendMajorUpdateNotification($updateTitle, $updateDetails, $timestamp = null) {
        $time = $timestamp ? date('Y-m-d H:i:s', strtotime($timestamp)) : date('Y-m-d H:i:s');
        
        $content = "
<p>Hello Administrator,</p>
<p>A major system update has been detected.</p>
<table style=\"background:#f3f4f6;padding:16px;border-radius:6px;margin:16px 0;\">
<tr><td style=\"color:#6b7280;font-size:12px;\">Update</td><td style=\"font-size:14px;font-weight:600;\">$updateTitle</td></tr>
<tr><td style=\"color:#6b7280;font-size:12px;\">Time</td><td style=\"font-size:14px;\">$time</td></tr>
</table>
<div style=\"background:#f9fafb;padding:16px;border-radius:4px;margin:16px 0;\">
$updateDetails
</div>
<p><a href=\"https://dashboard.technostationery.com\" style=\"display:inline-block;background:#1e3a5f;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;margin-top:16px;\">View Dashboard</a></p>
";
        
        return self::sendAdminNotification("Major Update: $updateTitle", 'Major System Update', $content);
    }

    /**
     * Send task created notification
     * Used when a new task is created (separate from assignment)
     */
    public static function sendTaskCreatedNotification($to, $assigneeName, $taskTitle, $taskDescription, $priority, $createdBy, $assignedTo, $dueDate = null, $taskId = null) {
        $taskUrl = $taskId ? "https://dashboard.technostationery.com/#/tasks/$taskId" : "https://dashboard.technostationery.com/#/tasks";
        $priorityLabels = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'];
        $priorityLabel = $priorityLabels[$priority] ?? $priority;
        $dueDateText = $dueDate ? "Due date: " . date('F j, Y', strtotime($dueDate)) : "No due date set";

        $content = "
<p>Hello <strong>$assigneeName</strong>,</p>
<p>A new task has been created in the Techno Dashboard.</p>
<table style=\"background:#f3f4f6;padding:16px;border-radius:6px;margin:16px 0;\">
<tr><td style=\"color:#6b7280;font-size:12px;\">Task</td><td style=\"font-size:14px;font-weight:600;\">$taskTitle</td></tr>
<tr><td style=\"color:#6b7280;font-size:12px;\">Priority</td><td style=\"font-size:14px;\">$priorityLabel</td></tr>
<tr><td style=\"color:#6b7280;font-size:12px;\">Due</td><td style=\"font-size:14px;\">$dueDateText</td></tr>
<tr><td style=\"color:#6b7280;font-size:12px;\">Created by</td><td style=\"font-size:14px;\">$createdBy</td></tr>
<tr><td style=\"color:#6b7280;font-size:12px;\">Assigned to</td><td style=\"font-size:14px;\">$assignedTo</td></tr>
</table>
";
        if (!empty($taskDescription)) {
            $content .= "<p><strong>Description:</strong></p><p style=\"background:#f9fafb;padding:12px;border-radius:4px;font-size:13px;\">" . nl2br(htmlspecialchars($taskDescription)) . "</p>";
        }

        $content .= "
<p><a href=\"$taskUrl\" style=\"display:inline-block;background:#1e3a5f;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;margin-top:16px;\">View Task</a></p>
";
        return self::send($to, "New Task Created: $taskTitle", self::wrapTemplate('New Task Created', $content));
    }

    /**
     * Send task note added notification
     */
    public static function sendTaskNoteAdded($to, $assigneeName, $taskTitle, $noteContent, $addedBy, $taskId = null) {
        $taskUrl = $taskId ? "https://dashboard.technostationery.com/#/tasks/$taskId" : "https://dashboard.technostationery.com/#/tasks";
        
        $content = "
<p>Hello <strong>$assigneeName</strong>,</p>
<p>A new note has been added to task <strong>\"$taskTitle\"</strong>.</p>
<table style=\"background:#f3f4f6;padding:16px;border-radius:6px;margin:16px 0;\">
<tr><td style=\"color:#6b7280;font-size:12px;\">Added by</td><td style=\"font-size:14px;\">$addedBy</td></tr>
</table>
<p><strong>Note Content:</strong></p>
<p style=\"background:#f9fafb;padding:12px;border-radius:4px;font-size:13px;\">" . nl2br(htmlspecialchars(mb_substr($noteContent, 0, 500))) . "</p>
<p><a href=\"$taskUrl\" style=\"display:inline-block;background:#1e3a5f;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;margin-top:16px;\">View Task</a></p>
";
        return self::send($to, "New Note on Task: $taskTitle", self::wrapTemplate('Task Note Added', $content));
    }

    /**
     * Send task completion notification
     */
    public static function sendTaskCompleted($to, $assigneeName, $taskTitle, $completedBy, $taskId = null) {
        $taskUrl = $taskId ? "https://dashboard.technostationery.com/#/tasks/$taskId" : "https://dashboard.technostationery.com/#/tasks";
        
        $content = "
<p>Hello <strong>$assigneeName</strong>,</p>
<p>Task <strong>\"$taskTitle\"</strong> has been marked as completed.</p>
<table style=\"background:#f3f4f6;padding:16px;border-radius:6px;margin:16px 0;\">
<tr><td style=\"color:#6b7280;font-size:12px;\">Completed by</td><td style=\"font-size:14px;\">$completedBy</td></tr>
</table>
<p><a href=\"$taskUrl\" style=\"display:inline-block;background:#1e3a5f;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;margin-top:16px;\">View Task</a></p>
<p style=\"color:#6b7280;font-size:12px;margin-top:16px;\">Great work on completing this task!</p>
";
        return self::send($to, "Task Completed: $taskTitle", self::wrapTemplate('Task Completed', $content));
    }

    /**
     * Load email settings from database or return defaults
     */
    private static function loadSettings() {
        // Return cached settings if available
        if (self::$settingsCache !== null) {
            return self::$settingsCache;
        }

        try {
            require_once __DIR__ . '/config.php';
            Config::load();
            $db = Config::get('db');
            
            $pdo = new PDO(
                "mysql:host={$db['host']};port={$db['port']};dbname=dashboard_auth",
                $db['user'],
                $db['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Check if settings table exists
            $tableExists = $pdo->query("SHOW TABLES LIKE 'email_settings'")->fetch();
            
            if ($tableExists) {
                $stmt = $pdo->query("SELECT setting_key, setting_value FROM email_settings");
                $settings = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
                
                // Cache settings
                self::$settingsCache = $settings;
                return $settings;
            }
        } catch (\Exception $e) {
            error_log("[Mailer] Failed to load email settings: " . $e->getMessage());
        }

        // Return defaults
        self::$settingsCache = [
            'from_email' => self::FROM_EMAIL,
            'from_name' => self::FROM_NAME,
            'admin_email_1' => self::ADMIN_EMAILS[0],
            'admin_email_2' => self::ADMIN_EMAILS[1],
            'enabled' => 'true'
        ];
        
        return self::$settingsCache;
    }

    /**
     * Save email settings to database
     */
    public static function saveSettings($settings) {
        try {
            require_once __DIR__ . '/config.php';
            Config::load();
            $db = Config::get('db');
            
            $pdo = new PDO(
                "mysql:host={$db['host']};port={$db['port']};dbname=dashboard_auth",
                $db['user'],
                $db['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Create table if it doesn't exist
            $pdo->exec("CREATE TABLE IF NOT EXISTS email_settings (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(50) NOT NULL UNIQUE,
                setting_value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_key (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // Save settings
            $pdo->beginTransaction();
            
            foreach ($settings as $key => $value) {
                $stmt = $pdo->prepare(
                    "INSERT INTO email_settings (setting_key, setting_value) 
                     VALUES (:key, :value) 
                     ON DUPLICATE KEY UPDATE setting_value = :value2"
                );
                $stmt->execute([
                    ':key' => $key,
                    ':value' => $value,
                    ':value2' => $value
                ]);
            }
            
            $pdo->commit();
            
            // Clear cache
            self::$settingsCache = null;
            
            error_log("[Mailer] Email settings saved successfully");
            return ['success' => true, 'message' => 'Email settings saved'];
            
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("[Mailer] Failed to save email settings: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get current email settings (for settings page API)
     */
    public static function getEmailSettings() {
        $settings = self::loadSettings();
        return [
            'from_email' => $settings['from_email'] ?? self::FROM_EMAIL,
            'from_name' => $settings['from_name'] ?? self::FROM_NAME,
            'admin_email_1' => $settings['admin_email_1'] ?? self::ADMIN_EMAILS[0],
            'admin_email_2' => $settings['admin_email_2'] ?? self::ADMIN_EMAILS[1],
            'enabled' => $settings['enabled'] ?? 'true'
        ];
    }
}

<?php
/**
 * TaskNotifier - general automatic task notification dispatcher.
 * All users, all tasks. Email via Mailer, push via WebpushrAlertHelper.
 * Watchers table: task_watchers(task_id, requester_email, requester_name).
 */
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/WebpushrAlertHelper.php';
class TaskNotifier {
    public static function ensureSchema(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS task_watchers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            task_id INT UNSIGNED NOT NULL,
            requester_email VARCHAR(255) NOT NULL,
            requester_name VARCHAR(100) DEFAULT '',
            created_by VARCHAR(100) DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_watcher (task_id, requester_email),
            INDEX idx_task (task_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public static function userByUsername(PDO $pdo, string $username): ?array {
        if ($username === '') return null;
        $st = $pdo->prepare("SELECT email, full_name FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
        $st->execute([$username]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
    /**
     * Extract @mentions from free text and resolve them to active users.
     *
     * Accepts the real username token (salahcs, khaled, mounirAb) and also an
     * email local-part, so "@salah.cs" and "@salahcs" both resolve to the same
     * person. The list of usernames is scanned first (exact token match) and
     * the local-part pass second, so an exact username always wins.
     *
     * @return string[] unique usernames
     */
    public static function parseMentions(PDO $pdo, string $text): array {
        if (trim($text) === '') return [];

        // Collect candidate @tokens: letters, digits, dot, dash, underscore.
        preg_match_all('/@([A-Za-z0-9._-]{2,64})/', $text, $m);
        $tokens = array_values(array_unique($m[1] ?? []));
        if (!$tokens) return [];

        $resolved = [];

        // Pass 1 — exact username match (case-insensitive).
        $st = $pdo->prepare(
            "SELECT username FROM users
              WHERE is_active = 1
                AND LOWER(username) IN (" . implode(',', array_fill(0, count($tokens), '?')) . ")"
        );
        $st->execute(array_map('strtolower', $tokens));
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $resolved[$r['username']] = true;

        // Pass 2 — email local-part (so @salah.cs resolves to user "salahcs").
        $locals = array_values(array_filter($tokens, function ($t) { return strpos($t, '.') !== false; }));
        if ($locals) {
            $st2 = $pdo->prepare(
                "SELECT username, email FROM users
                  WHERE is_active = 1
                    AND SUBSTRING_INDEX(LOWER(email), '@', 1) IN (" .
                    implode(',', array_fill(0, count($locals), '?')) . ")"
            );
            $st2->execute($locals);
            foreach ($st2->fetchAll(PDO::FETCH_ASSOC) as $r) $resolved[$r['username']] = true;
        }

        return array_keys($resolved);
    }

    /**
     * Email a user who was @mentioned, so mentioning someone always reaches
     * their inbox regardless of whether they are the assignee or creator.
     */
    private static function notifyMention(PDO $pdo, string $username, int $taskId, string $title, string $body, string $author): void {
        $u = self::userByUsername($pdo, $username);
        if (!$u || empty($u['email'])) return;

        // Being mentioned opts the user into the task conversation: they are
        // registered as a requester/watcher, so later STATUS CHANGES reach them
        // too. Without this, a mention delivered exactly one email and the
        // person was then dropped from every subsequent update.
        self::addRequester($pdo, $taskId, $u['email'], $u['full_name'] ?: $username, $author);

        try {
            Mailer::sendTaskMention(
                $u['email'],
                $u['full_name'] ?: $username,
                $title,
                $body,
                $author,
                $taskId
            );
        } catch (\Throwable $e) {
            error_log('[TaskNotifier] mention email failed for ' . $username . ': ' . $e->getMessage());
        }
    }

    public static function pushEnabled(PDO $pdo, string $username, string $key): bool {
        try {
            $st = $pdo->prepare("SELECT pref_value FROM notification_preferences WHERE username = ? AND pref_key = ? LIMIT 1");
            $st->execute([$username, $key]);
            $v = $st->fetchColumn();
            if ($v === false) return true;
            return ((int)$v) === 1;
        } catch (\Throwable $e) { return true; }
    }
    public static function push(string $title, string $body, string $targetUrl = '/tasks'): void {
        try { WebpushrAlertHelper::sendToAll($title, $body, $targetUrl); }
        catch (\Throwable $e) { error_log('[TaskNotifier] push failed: ' . $e->getMessage()); }
    }
    public static function addRequester(PDO $pdo, int $taskId, string $email, string $name = '', string $by = ''): void {
        if ($taskId <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL)) return;
        try {
            $st = $pdo->prepare("INSERT IGNORE INTO task_watchers (task_id, requester_email, requester_name, created_by) VALUES (?, ?, ?, ?)");
            $st->execute([$taskId, strtolower($email), $name, $by]);
        } catch (\Throwable $e) { error_log('[TaskNotifier] addRequester failed: ' . $e->getMessage()); }
    }
    public static function requesters(PDO $pdo, int $taskId): array {
        try {
            $st = $pdo->prepare("SELECT requester_email, requester_name FROM task_watchers WHERE task_id = ?");
            $st->execute([$taskId]);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) { return []; }
    }
    public static function taskUrl(?int $taskId): string {
        return $taskId ? "https://dashboard.technostationery.com/#/tasks/$taskId" : "https://dashboard.technostationery.com/#/tasks";
    }
    /**
     * Task created/assigned: assignee + creator email, requester watcher,
     * push, admin alert on high/critical.
     */
    public static function onCreated(PDO $pdo, int $taskId, string $title, string $description, string $priority, string $assignee, string $creator, ?string $dueDate): void {
        self::ensureSchema($pdo);
        $creatorInfo = self::userByUsername($pdo, $creator);
        if ($creator !== '' && $creator !== $assignee && $creatorInfo && !empty($creatorInfo['email'])) {
            self::addRequester($pdo, $taskId, $creatorInfo['email'], $creatorInfo['full_name'] ?: $creator, $creator);
        }
        if ($assignee !== '') {
            $u = self::userByUsername($pdo, $assignee);
            if ($u && !empty($u['email'])) {
                try {
                    Mailer::sendTaskAssignment($u['email'], $u['full_name'] ?: $assignee, $title, $description, $priority, $creator, $dueDate, $taskId);
                } catch (\Throwable $e) { error_log('[TaskNotifier] assign email failed: ' . $e->getMessage()); }
            }
            if (self::pushEnabled($pdo, $assignee, 'task_assigned_push')) {
                self::push("New task assigned: $title", "Assigned by $creator - Priority: $priority", self::taskUrl($taskId));
            }
        }
        if ($creator !== '' && $creator !== $assignee && $creatorInfo && !empty($creatorInfo['email'])) {
            try {
                Mailer::sendTaskCreatedNotification($creatorInfo['email'], $creatorInfo['full_name'] ?: $creator, $title, $description, $priority, $creator, $assignee, $dueDate, $taskId);
            } catch (\Throwable $e) { error_log('[TaskNotifier] creator email failed: ' . $e->getMessage()); }
        }
        if (in_array($priority, ['high', 'critical'], true)) {
            try {
                Mailer::sendAdminNotification("High Priority Task: $title", 'High Priority Task Alert',
                    "<p><strong>New High Priority Task Created</strong></p>"
                    . "<p><strong>Task:</strong> $title</p>"
                    . "<p><strong>Priority:</strong> $priority</p>"
                    . "<p><strong>Assigned to:</strong> $assignee</p>"
                    . "<p><strong>Created by:</strong> $creator</p>");
            } catch (\Throwable $e) { error_log('[TaskNotifier] admin email failed: ' . $e->getMessage()); }
        }

        // People @mentioned in the description are emailed, so a task that
        // "requires input from @x" reaches them without being assigned.
        $alreadyNotified = array_unique(array_filter([$assignee, $creator]));
        foreach (self::parseMentions($pdo, $title . "\n" . (string) $description) as $mentioned) {
            if ($mentioned === $creator || in_array($mentioned, $alreadyNotified, true)) continue;
            self::notifyMention($pdo, $mentioned, $taskId, $title, (string) $description, $creator);
        }
    }
    /**
     * Status change: assignee + creator + requesters email; completion
     * fan-out; realtime push; admin notification on completion.
     */
    public static function onStatusChange(PDO $pdo, int $taskId, string $title, string $oldStatus, string $newStatus, string $actor, string $assignee, string $creator, bool $notifyAdmin = true, bool $push = true): void {
        self::ensureSchema($pdo);
        $recipients = [];
        if ($assignee !== '') {
            $u = self::userByUsername($pdo, $assignee);
            if ($u && !empty($u['email'])) { $recipients[$u['email']] = $u['full_name'] ?: $assignee; }
        }
        if ($creator !== '' && $creator !== $assignee) {
            $c = self::userByUsername($pdo, $creator);
            if ($c && !empty($c['email'])) { $recipients[$c['email']] = $c['full_name'] ?: $creator; }
        }
        foreach (self::requesters($pdo, $taskId) as $r) {
            $recipients[$r['requester_email']] = $r['requester_name'] ?: 'Requester';
        }
        foreach ($recipients as $email => $name) {
            try { Mailer::sendTaskStatusChange($email, $name, $title, $oldStatus, $newStatus, $actor, $taskId); }
            catch (\Throwable $e) { error_log('[TaskNotifier] status email failed: ' . $e->getMessage()); }
            if ($newStatus === 'completed') {
                try { Mailer::sendTaskCompleted($email, $name, $title, $actor, $taskId); }
                catch (\Throwable $e) { error_log('[TaskNotifier] completed email failed: ' . $e->getMessage()); }
            }
        }
        $pushKey = $newStatus === 'completed' ? 'task_completed_push' : 'task_status_push';
        $pushAllowed = false;
        foreach (array_unique(array_filter([$assignee, $creator])) as $who) {
            if (self::pushEnabled($pdo, $who, $pushKey)) { $pushAllowed = true; break; }
        }
        if ($push && $pushAllowed) {
            self::push("Task $newStatus: $title", "Status changed by $actor", self::taskUrl($taskId));
        }
        // Every completion -> admins (includes webmaster@techno-dz.com).
        // $notifyAdmin=false is used by bulk_update, which sends one
        // aggregated admin report instead of one email per task.
        if ($notifyAdmin && $newStatus === 'completed') {
            try {
                Mailer::sendAdminNotification("Task Completed: $title", 'Task Completion Report',
                    "<p><strong>Task Completed</strong></p>"
                    . "<p><strong>Task:</strong> $title</p>"
                    . "<p><strong>Completed by:</strong> $actor</p>"
                    . "<p><strong>Assigned to:</strong> $assignee</p>");
            } catch (\Throwable $e) { error_log('[TaskNotifier] completion admin email failed: ' . $e->getMessage()); }
        }
    }
    /**
     * Note added: email assignee + creator (except the author), push.
     */
    public static function onNote(PDO $pdo, int $taskId, string $title, string $note, string $author, string $assignee, string $creator): void {
        foreach (array_unique(array_filter([$assignee, $creator])) as $who) {
            if ($who === $author) continue;
            $u = self::userByUsername($pdo, $who);
            if ($u && !empty($u['email'])) {
                try { Mailer::sendTaskNoteAdded($u['email'], $u['full_name'] ?: $who, $title, $note, $author, $taskId); }
                catch (\Throwable $e) { error_log('[TaskNotifier] note email failed: ' . $e->getMessage()); }
            }
        }

        // Anyone @mentioned in the note is emailed too, even when they are
        // neither assignee nor creator. De-duplicated against the people
        // already emailed above so nobody receives the same note twice.
        $alreadyMailed = array_unique(array_filter([$assignee, $creator]));
        foreach (self::parseMentions($pdo, $note) as $mentioned) {
            if ($mentioned === $author || in_array($mentioned, $alreadyMailed, true)) continue;
            self::notifyMention($pdo, $mentioned, $taskId, $title, $note, $author);
        }

        $pushAllowed = false;
        foreach (array_unique(array_filter([$assignee, $creator])) as $who) {
            if ($who === $author) continue;
            if (self::pushEnabled($pdo, $who, 'task_note_push')) { $pushAllowed = true; break; }
        }
        if ($pushAllowed) {
            self::push("New note on task: $title", "Note added by $author", self::taskUrl($taskId));
        }
    }
    /**
     * Task dispatched/reassigned to a new assignee: email + push.
     */
    public static function onDispatch(PDO $pdo, int $taskId, string $title, string $description, string $priority, string $newAssignee, string $actor, ?string $dueDate): void {
        $u = self::userByUsername($pdo, $newAssignee);
        if ($u && !empty($u['email'])) {
            try { Mailer::sendTaskAssignment($u['email'], $u['full_name'] ?: $newAssignee, $title, $description, $priority, $actor, $dueDate, $taskId); }
            catch (\Throwable $e) { error_log('[TaskNotifier] dispatch email failed: ' . $e->getMessage()); }
        }
        if (self::pushEnabled($pdo, $newAssignee, 'task_assigned_push')) {
            self::push("Task reassigned to you: $title", "Dispatched by $actor", self::taskUrl($taskId));
        }
    }
}

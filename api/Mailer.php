<?php
/**
 * Mailer - Email sending utility using PHP mail()
 */

class Mailer {
    private const FROM_EMAIL = 'dashboard@techno-dz.com';
    private const FROM_NAME = 'Techno Dashboard';

    /**
     * Send an HTML email
     */
    private static function send($to, $subject, $htmlBody) {
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log("[Mailer] Invalid email: $to");
            return false;
        }

        $headers = [
            'From: ' . self::FROM_NAME . ' <' . self::FROM_EMAIL . '>',
            'Reply-To: ' . self::FROM_EMAIL,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'X-Mailer: TechnoDashboard/1.0'
        ];

        $result = mail($to, $subject, $htmlBody, implode("\r\n", $headers));
        error_log("[Mailer] Sent to $to | Subject: $subject | Result: " . ($result ? 'OK' : 'FAIL'));
        return $result;
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
}

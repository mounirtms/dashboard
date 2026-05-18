<?php
/**
 * Email Settings API
 * Manage email notification settings
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

// Only admins can manage email settings
if (!PermissionChecker::isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'get';

switch ($action) {
    case 'get':
        // Get current email settings
        $settings = Mailer::getEmailSettings();
        echo json_encode(['success' => true, 'settings' => $settings]);
        break;
        
    case 'save':
        // Save email settings
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true) ?? [];
        
        // Validate required fields
        $required = ['from_email', 'from_name', 'admin_email_1'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => "Field '$field' is required"]);
                exit;
            }
        }
        
        // Validate email addresses
        $emails = ['from_email', 'admin_email_1', 'admin_email_2'];
        foreach ($emails as $emailField) {
            if (!empty($input[$emailField]) && !filter_var($input[$emailField], FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => "Invalid email address: $emailField"]);
                exit;
            }
        }
        
        // Save settings
        $result = Mailer::saveSettings([
            'from_email' => $input['from_email'],
            'from_name' => $input['from_name'],
            'admin_email_1' => $input['admin_email_1'],
            'admin_email_2' => $input['admin_email_2'] ?? '',
            'enabled' => $input['enabled'] ?? 'true'
        ]);
        
        if ($result['success']) {
            echo json_encode($result);
        } else {
            http_response_code(500);
            echo json_encode($result);
        }
        break;
        
    case 'test':
        // Test email configuration
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true) ?? [];
        
        $testEmail = $input['email'] ?? $_SESSION['email'] ?? '';
        
        if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Valid test email required']);
            break;
        }
        
        $settings = Mailer::getEmailSettings();
        
        $content = "
<p>Hello,</p>
<p>This is a test email from your Techno Dashboard email configuration.</p>
<table style=\"background:#f3f4f6;padding:16px;border-radius:6px;margin:16px 0;\">
<tr><td style=\"color:#6b7280;font-size:12px;\">From</td><td style=\"font-size:14px;\">{$settings['from_name']} &lt;{$settings['from_email']}&gt;</td></tr>
<tr><td style=\"color:#6b7280;font-size:12px;\">Time</td><td style=\"font-size:14px;\">" . date('Y-m-d H:i:s') . "</td></tr>
<tr><td style=\"color:#6b7280;font-size:12px;\">Server</td><td style=\"font-size:14px;\">{$_SERVER['SERVER_NAME']}</td></tr>
</table>
<p>If you received this email, your email configuration is working correctly!</p>
";
        
        $result = Mailer::send($testEmail, 'Test Email - Techno Dashboard', Mailer::wrapTemplate('Email Configuration Test', $content));
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => "Test email sent to $testEmail"]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to send test email. Check server mail configuration.']);
        }
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}

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
require_once __DIR__ . '/InputValidator.php';
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
        $input = json_decode($rawInput, true);
        
        if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid JSON in request body: ' . json_last_error_msg()]);
            exit;
        }
        
        $input = $input ?? [];
        
        // Validate required fields using InputValidator
        $validationRules = [
            'from_email' => 'required|email',
            'from_name' => 'required|max:100',
            'admin_email_1' => 'required|email',
            'admin_email_2' => 'email',
            'enabled' => 'in:true,false,1,0'
        ];
        
        $errors = InputValidator::validateArray($input, $validationRules);
        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => implode(', ', $errors)]);
            exit;
        }
        
        // Sanitize from_name to prevent header injection
        $input['from_name'] = str_replace(["\r", "\n"], '', $input['from_name']);
        
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
        // Test email configuration with rate limiting
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        
        if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid JSON in request body']);
            break;
        }
        
        $input = $input ?? [];
        $testEmail = $input['email'] ?? $_SESSION['email'] ?? '';
        
        if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Valid test email required']);
            break;
        }
        
        // Rate limit: 1 test email per 60 seconds
        $rateLimitKey = 'email_test_' . ($_SESSION['username'] ?? 'unknown');
        $rateLimitFile = __DIR__ . '/data/email_rate_limit.json';
        
        if (file_exists($rateLimitFile)) {
            $rateData = json_decode(file_get_contents($rateLimitFile), true) ?? [];
            $lastTest = $rateData[$rateLimitKey] ?? 0;
            
            if (time() - $lastTest < 60) {
                $remaining = 60 - (time() - $lastTest);
                echo json_encode(['success' => false, 'error' => "Rate limited. Please wait $remaining seconds before sending another test email."]);
                break;
            }
        }
        
        // Update rate limit
        @mkdir(__DIR__ . '/data', 0755, true);
        $rateData = file_exists($rateLimitFile) ? json_decode(file_get_contents($rateLimitFile), true) ?? [] : [];
        $rateData[$rateLimitKey] = time();
        file_put_contents($rateLimitFile, json_encode($rateData));
        
        $settings = Mailer::getEmailSettings();
        $serverName = htmlspecialchars($_SERVER['SERVER_NAME'] ?? 'unknown', ENT_QUOTES, 'UTF-8');
        
        $content = "
<p>Hello,</p>
<p>This is a test email from your Techno Dashboard email configuration.</p>
<table style=\"background:#f3f4f6;padding:16px;border-radius:6px;margin:16px 0;\">
<tr><td style=\"color:#6b7280;font-size:12px;\">From</td><td style=\"font-size:14px;\">{$settings['from_name']} &lt;{$settings['from_email']}&gt;</td></tr>
<tr><td style=\"color:#6b7280;font-size:12px;\">Time</td><td style=\"font-size:14px;\">" . date('Y-m-d H:i:s') . "</td></tr>
<tr><td style=\"color:#6b7280;font-size:12px;\">Server</td><td style=\"font-size:14px;\">$serverName</td></tr>
</table>
<p>If you received this email, your email configuration is working correctly!</p>
";
        
        $result = Mailer::sendTestEmail($testEmail, 'Test Email - Techno Dashboard', $content);
        
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

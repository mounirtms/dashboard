<?php
require_once __DIR__ . '/../api/session_helper.php';

if (empty($_SESSION['logged_in'])) {
    header('Location: /#/login');
    exit;
}

$file = basename($_GET['file'] ?? '');
$allowed = [
    'security_audit_report.html',
    '2fa_setup_guide.html',
    'ssh_hardening_report.html',
    'MARIADB_LOAD_FIX_SUMMARY.md',
    'OPENCODE_UPDATE_SUMMARY.md',
    'gift_card_status_report.md',
];

if (!in_array($file, $allowed, true)) {
    http_response_code(404);
    echo 'Report not found.';
    exit;
}

$path = __DIR__ . '/' . $file;
if (!is_file($path)) {
    http_response_code(404);
    echo 'Report not found.';
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, private');

if (str_ends_with($file, '.md')) {
    echo '<!DOCTYPE html><html><head><title>'.$file.'</title><style>body{font-family:sans-serif;padding:20px;max-width:800px;margin:auto;line-height:1.6;} pre{background:#f4f4f4;padding:15px;border-radius:5px;overflow-x:auto;}</style></head><body>';
    echo '<h2>' . htmlspecialchars($file) . '</h2>';
    echo '<pre>' . htmlspecialchars(file_get_contents($path)) . '</pre>';
    echo '</body></html>';
} else {
    readfile($path);
}

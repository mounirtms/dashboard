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
readfile($path);

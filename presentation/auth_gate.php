<?php
/**
 * Presentation auth gate — included by .htaccess for ALL HTML requests
 * Checks dashboard session; redirects to login if not authenticated
 */
$doc_root = dirname(dirname(__FILE__)); // /home/dashboard/public_html
require_once $doc_root . '/api/session_helper.php';
start_secure_session();

if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Not authenticated — redirect to dashboard login
    header('Location: https://dashboard.technostationery.com/#/login', true, 302);
    exit;
}
// Authenticated — continue serving the file
?>
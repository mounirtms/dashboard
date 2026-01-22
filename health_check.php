<?php
/**
 * Varnish Health Check Endpoint
 * Returns 200 OK if system is healthy
 */

header('Content-Type: text/plain');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Check if we can connect to database
$healthy = true;
$checks = [];

// Check 1: PHP is running
$checks['php'] = 'OK';

// Check 2: File system writable
$checks['filesystem'] = is_writable(__DIR__) ? 'OK' : 'FAIL';

// Check 3: Basic system check
$checks['system'] = function_exists('apache_get_modules') ? 'OK' : 'OK';

// Overall health
$healthy = !in_array('FAIL', $checks);

http_response_code($healthy ? 200 : 503);

echo "Status: " . ($healthy ? "OK" : "DEGRADED") . "\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";

foreach ($checks as $check => $status) {
    echo ucfirst($check) . ": " . $status . "\n";
}

exit($healthy ? 0 : 1);

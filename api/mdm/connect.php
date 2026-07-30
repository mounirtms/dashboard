<?php
/**
 * MDM (Master Data Management) Connection Health Endpoint
 * SQL Server 2019 @ techno-mdm:1433
 *
 * Returns connection status for the MDM data source.
 * Currently returns a "not yet configured" state — full SQL Server connector
 * will be wired in once the PDO_SQLSRV extension is provisioned on this host.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../session_helper.php';
require_once __DIR__ . '/../config.php';
Config::load();

// Require authentication
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ── Connection attempt ──────────────────────────────────────────────────────
$host    = getenv('MDM_HOST')     ?: 'techno-mdm';
$port    = getenv('MDM_PORT')     ?: '1433';
$db      = getenv('MDM_DB')       ?: 'TechnoMDM';
$user    = getenv('MDM_USER')     ?: '';
$pass    = getenv('MDM_PASS')     ?: '';

$success   = false;
$message   = '';
$timestamp = date('c');

// Only attempt real connection if credentials are configured
if (!empty($user) && !empty($pass)) {
    try {
        if (!extension_loaded('pdo_sqlsrv') && !extension_loaded('sqlsrv')) {
            throw new RuntimeException('PDO_SQLSRV extension not loaded on this host.');
        }
        $dsn = "sqlsrv:Server=$host,$port;Database=$db;LoginTimeout=5";
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->query('SELECT 1');
        $success = true;
        $message = 'Connected to MDM SQL Server successfully.';
    } catch (Throwable $e) {
        $success = false;
        $message = 'MDM connection failed: ' . $e->getMessage();
    }
} else {
    $message = 'MDM credentials not yet configured (set MDM_HOST, MDM_USER, MDM_PASS in .env).';
}

echo json_encode([
    'success'   => $success,
    'source'    => 'mdm',
    'host'      => $host . ':' . $port,
    'database'  => $db,
    'message'   => $message,
    'timestamp' => $timestamp,
]);

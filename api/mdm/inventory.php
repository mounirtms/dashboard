<?php
/**
 * /api/mdm/inventory
 * MDM SQL Server inventory snapshot endpoint.
 * Returns current stock levels from the MDM system.
 *
 * Currently a stub — returns "not configured" gracefully until MDM
 * SQL Server credentials are set via environment variables.
 *
 * Methods: GET
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

require_once __DIR__ . '/../session_helper.php';
require_once __DIR__ . '/../config.php';

if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ── Check MDM credentials ─────────────────────────────────────────────────────
$mdmHost = getenv('MDM_DB_HOST') ?: '';
$mdmUser = getenv('MDM_DB_USER') ?: '';
$mdmPass = getenv('MDM_DB_PASS') ?: '';
$mdmDb   = getenv('MDM_DB_NAME') ?: 'TechnoMDM';

$configured = ($mdmHost && $mdmUser);

if (!$configured) {
    echo json_encode([
        'success'    => false,
        'source'     => 'mdm',
        'message'    => 'MDM SQL Server credentials not configured. '
                      . 'Set MDM_DB_HOST, MDM_DB_USER, MDM_DB_PASS, MDM_DB_NAME environment variables.',
        'configured' => false,
        'items'      => [],
        'total'      => 0,
    ]);
    exit;
}

// ── Stub: credentials present — attempt connection ────────────────────────────
try {
    // Requires php-sqlsrv or pdo_sqlsrv extension
    // When extension is available this will connect and query live stock data
    if (!extension_loaded('sqlsrv') && !extension_loaded('pdo_sqlsrv')) {
        echo json_encode([
            'success'    => false,
            'source'     => 'mdm',
            'message'    => 'MDM credentials are set but php-sqlsrv/pdo_sqlsrv extension is not installed on this server.',
            'configured' => true,
            'items'      => [],
            'total'      => 0,
        ]);
        exit;
    }

    $dsn = "sqlsrv:Server={$mdmHost};Database={$mdmDb};TrustServerCertificate=1";
    $pdo = new PDO($dsn, $mdmUser, $mdmPass, [
        PDO::ATTR_TIMEOUT    => 5,
        PDO::ATTR_ERRMODE    => PDO::ERRMODE_EXCEPTION,
    ]);

    $stmt = $pdo->query("
        SELECT TOP 1000
            sku,
            product_name AS name,
            quantity_on_hand AS qty,
            warehouse_code AS warehouse,
            last_updated AS updated_at
        FROM dbo.StockSnapshot
        WHERE quantity_on_hand >= 0
        ORDER BY last_updated DESC
    ");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'    => true,
        'source'     => 'mdm',
        'configured' => true,
        'items'      => $items,
        'total'      => count($items),
        'fetched_at' => gmdate('c'),
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'success'    => false,
        'source'     => 'mdm',
        'message'    => 'MDM inventory query failed: ' . $e->getMessage(),
        'configured' => true,
        'items'      => [],
        'total'      => 0,
    ]);
}

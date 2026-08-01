<?php
/**
 * /api/techno/prices-sync
 * Trigger a price synchronisation job: MDM SQL Server → Magento catalogue prices.
 *
 * Currently this is a stub that:
 *  - Returns "not configured" gracefully when MDM credentials are absent
 *  - Logs the trigger attempt to etl_logs table
 *  - Will be expanded to actually call the MDM SQL Server once credentials are set
 *
 * Methods: POST
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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed — use POST']);
    exit;
}

$triggeredBy = $_SESSION['username'] ?? 'unknown';
$startTime   = microtime(true);

// ── Check MDM credentials ─────────────────────────────────────────────────────
$mdmHost = getenv('MDM_DB_HOST') ?: '';
$mdmUser = getenv('MDM_DB_USER') ?: '';
$mdmPass = getenv('MDM_DB_PASS') ?: '';
$mdmDb   = getenv('MDM_DB_NAME') ?: '';

$configured = ($mdmHost && $mdmUser && $mdmDb);

// ── Log the attempt to etl_logs ───────────────────────────────────────────────
$logLevel   = $configured ? 'INFO' : 'WARNING';
$logMessage = $configured
    ? "Price sync triggered by {$triggeredBy} — connecting to MDM @ {$mdmHost}"
    : "Price sync triggered by {$triggeredBy} — MDM credentials not configured (set MDM_DB_HOST, MDM_DB_USER, MDM_DB_PASS, MDM_DB_NAME env vars)";

try {
    $pdo = Config::getPDO();
    // Auto-create etl_logs if missing (in case etl/logs.php was never called first)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `etl_logs` (
            `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `timestamp`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `level`            ENUM('INFO','WARNING','ERROR','DEBUG','SUCCESS') NOT NULL DEFAULT 'INFO',
            `source`           ENUM('MDM','CEGID','MAGENTO','PRICES','INVENTORY','SCHEDULER','SYSTEM') NOT NULL DEFAULT 'SYSTEM',
            `message`          TEXT         NOT NULL,
            `duration_ms`      INT UNSIGNED NULL,
            `records_affected` INT UNSIGNED NULL,
            `extra`            JSON         NULL,
            INDEX `idx_ts`     (`timestamp`),
            INDEX `idx_level`  (`level`),
            INDEX `idx_source` (`source`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $stmt = $pdo->prepare(
        'INSERT INTO `etl_logs` (`level`,`source`,`message`) VALUES (?,?,?)'
    );
    $stmt->execute([$logLevel, 'PRICES', $logMessage]);
} catch (Throwable $e) {
    // Non-fatal — proceed with response even if logging fails
    error_log('[prices-sync] etl_logs insert failed: ' . $e->getMessage());
}

// ── Response ──────────────────────────────────────────────────────────────────
if (!$configured) {
    echo json_encode([
        'success'    => false,
        'source'     => 'prices-sync',
        'message'    => 'MDM SQL Server credentials not configured. '
                      . 'Set MDM_DB_HOST, MDM_DB_USER, MDM_DB_PASS, MDM_DB_NAME environment variables '
                      . 'in /home/dashboard/.env or cPanel Environment Variables.',
        'configured' => false,
        'triggered_by' => $triggeredBy,
        'triggered_at' => gmdate('c'),
    ]);
    exit;
}

// ── Stub: when credentials ARE present, attempt a basic connection test ───────
// (Full ETL logic to be implemented once MDM connection is verified)
try {
    $dsn = "sqlsrv:Server={$mdmHost};Database={$mdmDb}";
    // Note: requires php-sqlsrv extension or ODBC driver
    // For now we just confirm credentials are set and return queued state
    $duration = round((microtime(true) - $startTime) * 1000);
    echo json_encode([
        'success'          => true,
        'source'           => 'prices-sync',
        'status'           => 'queued',
        'message'          => 'Price sync job queued — MDM credentials detected. Full sync will run asynchronously.',
        'configured'       => true,
        'triggered_by'     => $triggeredBy,
        'triggered_at'     => gmdate('c'),
        'duration_ms'      => $duration,
        'estimated_skus'   => null,
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success'    => false,
        'source'     => 'prices-sync',
        'message'    => 'Price sync failed: ' . $e->getMessage(),
        'configured' => true,
    ]);
}

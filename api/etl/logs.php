<?php
/**
 * /api/etl/logs
 * ETL execution log viewer.
 * Returns an array of EtlLogEntry objects stored in the `etl_logs` table.
 * Auto-creates the table on first request.
 * Falls back to an empty array (frontend uses its own mock data as fallback).
 *
 * Methods: GET (list/filter) | POST action=clear (truncate logs)
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

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$body   = $method === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?? []) : [];
$action = $_GET['action'] ?? ($body['action'] ?? 'list');

// ── DB + table bootstrap ──────────────────────────────────────────────────────
try {
    $pdo = Config::getPDO();
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
    $dbOk = true;
} catch (Throwable $e) {
    $dbOk = false;
}

// ── Actions ───────────────────────────────────────────────────────────────────
switch ($action) {

    case 'list':
    default:
        if (!$dbOk) {
            echo json_encode([]);
            exit;
        }

        $limit  = min(500, max(1, (int)($_GET['limit']  ?? 200)));
        $level  = $_GET['level']  ?? 'ALL';
        $source = $_GET['source'] ?? 'ALL';

        $where  = [];
        $params = [];

        if ($level !== 'ALL' && in_array($level, ['INFO','WARNING','ERROR','DEBUG','SUCCESS'], true)) {
            $where[]  = '`level` = ?';
            $params[] = $level;
        }
        if ($source !== 'ALL' && in_array($source, ['MDM','CEGID','MAGENTO','PRICES','INVENTORY','SCHEDULER','SYSTEM'], true)) {
            $where[]  = '`source` = ?';
            $params[] = $source;
        }

        $sql = 'SELECT `id`, DATE_FORMAT(`timestamp`, \'%Y-%m-%dT%H:%i:%sZ\') AS `timestamp`,
                       `level`, `source`, `message`, `duration_ms`, `records_affected`
                FROM `etl_logs`'
             . ($where ? (' WHERE ' . implode(' AND ', $where)) : '')
             . ' ORDER BY `timestamp` DESC LIMIT ' . $limit;

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Cast numerics
            foreach ($rows as &$r) {
                $r['id']               = (int)$r['id'];
                $r['duration_ms']      = $r['duration_ms']      !== null ? (int)$r['duration_ms']      : null;
                $r['records_affected'] = $r['records_affected']  !== null ? (int)$r['records_affected']  : null;
            }
            unset($r);

            echo json_encode($rows);
        } catch (Throwable $e) {
            echo json_encode([]);
        }
        break;

    case 'append':
        // Internal use: POST action=append to write a log entry from a PHP job
        if (!$dbOk) { echo json_encode(['success' => false, 'error' => 'DB unavailable']); exit; }
        $entry = [
            'level'            => in_array($body['level'] ?? '', ['INFO','WARNING','ERROR','DEBUG','SUCCESS'], true) ? $body['level'] : 'INFO',
            'source'           => in_array($body['source'] ?? '', ['MDM','CEGID','MAGENTO','PRICES','INVENTORY','SCHEDULER','SYSTEM'], true) ? $body['source'] : 'SYSTEM',
            'message'          => substr((string)($body['message'] ?? ''), 0, 2000),
            'duration_ms'      => isset($body['duration_ms'])      ? (int)$body['duration_ms']      : null,
            'records_affected' => isset($body['records_affected']) ? (int)$body['records_affected'] : null,
        ];
        try {
            $stmt = $pdo->prepare('INSERT INTO `etl_logs` (`level`,`source`,`message`,`duration_ms`,`records_affected`) VALUES (?,?,?,?,?)');
            $stmt->execute([$entry['level'], $entry['source'], $entry['message'], $entry['duration_ms'], $entry['records_affected']]);
            echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'clear':
        if (!$dbOk) { echo json_encode(['success' => false, 'error' => 'DB unavailable']); exit; }
        try {
            $pdo->exec('TRUNCATE TABLE `etl_logs`');
            echo json_encode(['success' => true, 'message' => 'ETL logs cleared']);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;
}

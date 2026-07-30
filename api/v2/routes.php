<?php
/**
 * API v2 Routes
 * 
 * Defines Flight PHP routes for new endpoints.
 * All routes (except /health) require authentication.
 */

// ── Health check (no auth) ──
Flight::route('GET /health', function () {
    Flight::json([
        'status' => 'ok',
        'timestamp' => time(),
        'correlation_id' => Logger::getCorrelationId(),
    ]);
});

// ── Auth middleware for protected routes ──
Flight::map('requireAuth', function () {
    if (empty($_SESSION['logged_in'])) {
        Flight::json([
            'error' => 'Authentication required',
            'correlation_id' => Logger::getCorrelationId(),
        ], 401);
        return false;
    }
    return true;
});

// ── Audit logs ──
Flight::route('GET /audit-logs', function () {
    if (!Flight::requireAuth()) return;

    require_once __DIR__ . '/../AuditLogger.php';
    $limit = (int)(Flight::request()->query->getInt('limit', 100));

    Logger::audit()->info('Audit logs accessed', ['limit' => $limit]);

    $entries = AuditLogger::getEntries($limit);
    Flight::json([
        'entries' => $entries,
        'count' => count($entries),
        'correlation_id' => Logger::getCorrelationId(),
    ]);
});

// ── Application logs (structured JSON) ──
Flight::route('GET /app-logs', function () {
    if (!Flight::requireAuth()) return;

    $date = Flight::request()->query->getString('date', date('Y-m-d'));
    $channel = Flight::request()->query->getString('channel', null);
    $level = Flight::request()->query->getString('level', null);
    $limit = (int)(Flight::request()->query->getInt('limit', 100));

    $logDir = dirname(__DIR__) . '/../logs';
    $logFile = "$logDir/app-{$date}.log";
    $entries = [];

    if (is_file($logFile)) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $lines = array_slice(array_reverse($lines), 0, $limit);
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if ($entry) {
                if ($channel && ($entry['channel'] ?? '') !== $channel) continue;
                if ($level && strtoupper($entry['level'] ?? '') !== strtoupper($level)) continue;
                $entries[] = $entry;
            }
        }
    }

    Logger::api()->info('App logs accessed', [
        'date' => $date,
        'channel' => $channel,
        'level' => $level,
        'returned' => count($entries),
    ]);

    Flight::json([
        'entries' => $entries,
        'count' => count($entries),
        'date' => $date,
        'structured' => true,
        'correlation_id' => Logger::getCorrelationId(),
    ]);
});

// ── Task stats (example of wrapping existing functionality) ──
Flight::route('GET /task-stats', function () {
    if (!Flight::requireAuth()) return;

    $db = Config::get('db');
    try {
        // Use DB_PROD (Magento DB) — dashboard_auth database does not exist
        $pdo = Config::getPDO();

        $stats = $pdo->query("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'in-progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM tasks")->fetch();

        Flight::json([
            'data' => $stats,
            'correlation_id' => Logger::getCorrelationId(),
        ]);
    } catch (Exception $e) {
        Logger::database()->error('Task stats query failed', ['error' => $e->getMessage()]);
        Flight::json(['error' => 'Database error', 'correlation_id' => Logger::getCorrelationId()], 500);
    }
});

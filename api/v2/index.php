<?php
/**
 * API v2 - Flight PHP Entry Point
 * 
 * Lightweight routing for new API endpoints with centralized logging.
 * Access via: /api/v2/*
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session_helper.php';
require_once __DIR__ . '/../Logger.php';

Config::load();

// Set correlation ID from header if provided
if (isset($_SERVER['HTTP_X_CORRELATION_ID'])) {
    Logger::setCorrelationId($_SERVER['HTTP_X_CORRELATION_ID']);
}

// Log the request
Logger::api()->info('API v2 request', [
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
]);

// ── Routes ──
require_once __DIR__ . '/routes.php';

// ── Global error handler ──
Flight::error(function (\Throwable $e) {
    Logger::api()->error('Unhandled exception', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => Config::get('app.debug', false) ? $e->getTraceAsString() : null,
    ]);

    Flight::json([
        'error' => 'Internal server error',
        'correlation_id' => Logger::getCorrelationId(),
    ], 500);
});

Flight::start();

<?php
/**
 * Base API Class
 * Provides common functionality for all API endpoints
 */

require_once __DIR__ . '/config.php';

abstract class BaseApi {
    protected $cache;
    protected $db;

    public function __construct(CacheManager $cache = null) {
        $this->cache = $cache;
    }

    protected function sendResponse($data, $statusCode = 200) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code($statusCode);
        }
        echo json_encode($data);
        exit;
    }

    protected function sendError($message, $statusCode = 500, $code = 'INTERNAL_ERROR') {
        $this->sendResponse([
            'error' => true,
            'message' => $message,
            'code' => $code
        ], $statusCode);
    }

    protected function sendUnauthorized($message = 'Authentication required') {
        $this->sendError($message, 401, 'UNAUTHORIZED');
    }

    protected function sendNotFound($message = 'Resource not found') {
        $this->sendError($message, 404, 'NOT_FOUND');
    }

    protected function sendBadRequest($message = 'Invalid request') {
        $this->sendError($message, 400, 'BAD_REQUEST');
    }

    protected function getDb() {
        if ($this->db === null) {
            $this->db = Config::getDbConnection();
            if (!$this->db) {
                throw new Exception("Database connection failed");
            }
        }
        return $this->db;
    }

    public function __destruct() {
        if ($this->db) {
            $this->db->close();
        }
    }
}

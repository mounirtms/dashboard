<?php
/**
 * Database Connection Handler
 * Provides secure, read-only access to Magento database for stats
 */

if (!defined('DOC_ACCESS')) {
    die('Direct access not permitted');
}

class DatabaseConnection {
    private static $instance = null;
    private $pdo = null;
    private $config = null;
    
    private function __construct($config) {
        $this->config = $config;
        $this->connect();
    }
    
    public static function getInstance($config) {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }
    
    private function connect() {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $this->config['db']['host'],
                $this->config['db']['name'],
                $this->config['db']['charset']
            );
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $this->pdo = new PDO(
                $dsn,
                $this->config['db']['user'],
                $this->config['db']['pass'],
                $options
            );
            
        } catch (PDOException $e) {
            $this->logError('Database connection failed: ' . $e->getMessage());
            throw new Exception('Database connection failed');
        }
    }
    
    public function query($sql, $params = []) {
        try {
            // Security: Only allow SELECT queries
            if (!preg_match('/^\s*SELECT\s+/i', trim($sql))) {
                throw new Exception('Only SELECT queries are allowed');
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            $this->logError('Query failed: ' . $e->getMessage() . ' | SQL: ' . $sql);
            return false;
        }
    }
    
    public function queryOne($sql, $params = []) {
        $result = $this->query($sql, $params);
        return $result ? $result[0] : false;
    }
    
    public function queryValue($sql, $params = []) {
        $result = $this->queryOne($sql, $params);
        return $result ? reset($result) : false;
    }
    
    public function tableExists($tableName) {
        $sql = "SELECT COUNT(*) as count FROM information_schema.tables 
                WHERE table_schema = ? AND table_name = ?";
        $result = $this->queryOne($sql, [$this->config['db']['name'], $tableName]);
        return $result && $result['count'] > 0;
    }
    
    public function getTableRowCount($tableName) {
        if (!$this->tableExists($tableName)) {
            return 0;
        }
        $sql = "SELECT COUNT(*) as count FROM `{$tableName}`";
        $result = $this->queryOne($sql);
        return $result ? (int)$result['count'] : 0;
    }
    
    private function logError($message) {
        if (defined('LOGS_DIR')) {
            $logFile = LOGS_DIR . '/error_' . date('Y-m-d') . '.log';
            $timestamp = date('Y-m-d H:i:s');
            error_log("[{$timestamp}] {$message}\n", 3, $logFile);
        }
    }
    
    public function close() {
        $this->pdo = null;
    }
}

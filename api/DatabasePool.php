<?php
/**
 * Database Connection Pool
 * 
 * Manages persistent database connections to reduce overhead of creating
 * new connections for each request. Uses singleton pattern per database.
 */

class DatabasePool {
    private static $pool = [];

    /**
     * Get or create a cached PDO connection
     */
    public static function getPDO(string $dsn, string $user, string $pass, array $options = []): PDO {
        $key = md5($dsn . $user);
        
        if (!isset(self::$pool[$key])) {
            $defaultOptions = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT => true,
            ];
            
            self::$pool[$key] = new PDO($dsn, $user, $pass, array_merge($defaultOptions, $options));
        }
        
        return self::$pool[$key];
    }

    /**
     * Get or create a cached MySQLi connection
     */
    public static function getMySQLi(string $host, string $user, string $pass, string $dbname, int $port): ?mysqli {
        $key = md5($host . $port . $dbname . $user);
        
        if (!isset(self::$pool[$key])) {
            $conn = @new mysqli($host, $user, $pass, $dbname, $port);
            if ($conn->connect_error) {
                error_log("DatabasePool: Connection failed for $key: " . $conn->connect_error);
                return null;
            }
            $conn->set_charset('utf8mb4');
            self::$pool[$key] = $conn;
        }
        
        return self::$pool[$key];
    }

    /**
     * Clear all connections (useful for testing)
     */
    public static function clear(): void {
        foreach (self::$pool as $conn) {
            if ($conn instanceof mysqli) {
                $conn->close();
            } elseif ($conn instanceof PDO) {
                // PDO connections close automatically
            }
        }
        self::$pool = [];
    }

    /**
     * Get pool stats for monitoring
     */
    public static function getStats(): array {
        return [
            'total_connections' => count(self::$pool),
            'keys' => array_keys(self::$pool),
        ];
    }
}

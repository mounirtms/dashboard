<?php
/**
 * Database configuration file
 * Centralizes database connection settings
 */

class DatabaseConfig {
    private $host = '127.0.0.1';
    private $port = '3307';
    private $dbname = 'technadminy7_dBT8x12y22';
    private $username = 'root';
    private $password = 'YourNewStrongPassword';
    
    public function getConnection() {
        try {
            $pdo = new PDO("mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset=utf8", $this->username, $this->password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (PDOException $e) {
            throw new Exception("Connection failed: " . $e->getMessage());
        }
    }
    
    public function getHost() {
        return $this->host;
    }
    
    public function getPort() {
        return $this->port;
    }
    
    public function getDbname() {
        return $this->dbname;
    }
    
    public function getUsername() {
        return $this->username;
    }
    
    public function getPassword() {
        return $this->password;
    }
}
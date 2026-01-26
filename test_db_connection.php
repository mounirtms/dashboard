<?php
try {
    $host = '127.0.0.1';
    $port = '3307';
    $dbname = 'technadminy7_dBT8x12y22';
    $username = 'technadminy7_ntdbusr24';
    $password = 'the-correct-password';
    
    echo "Attempting connection...\n";
    echo "Host: $host:$port\n";
    echo "Database: $dbname\n";
    echo "Username: $username\n";
    
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, $username, $password, $options);
    echo "Connection successful!\n";
    
    $stmt = $pdo->query('SELECT VERSION() as version');
    $version = $stmt->fetch();
    echo "MySQL Version: " . $version['version'] . "\n";
    
    $stmt = $pdo->query('SELECT 1 as test');
    $result = $stmt->fetch();
    echo "Test query result: " . $result['test'] . "\n";
    
} catch (Exception $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
    echo "Error code: " . $e->getCode() . "\n";
}
?>
<?php
try {
    $host = '127.0.0.1';
    $port = '3307';
    $dbname = 'technadminy7_dBT8x12y22';
    $username = 'technadminy7_ntdbusr24';
    $password = 'the-correct-password';
    
    echo "Checking maintenance mode in database...\n";
    
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // Check for maintenance mode settings
    $stmt = $pdo->prepare("SELECT * FROM core_config_data WHERE path LIKE '%maintenance%'");
    $stmt->execute();
    $results = $stmt->fetchAll();
    
    if ($results) {
        echo "Found maintenance mode settings:\n";
        foreach ($results as $row) {
            echo "Path: {$row['path']}\n";
            echo "Value: {$row['value']}\n";
            echo "---\n";
        }
    } else {
        echo "No maintenance mode settings found in core_config_data\n";
    }
    
    // Check for any custom maintenance flags
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'maintenance%'");
    $stmt->execute();
    $tables = $stmt->fetchAll();
    
    if ($tables) {
        echo "Found maintenance tables:\n";
        foreach ($tables as $table) {
            echo "Table: " . array_values($table)[0] . "\n";
        }
    } else {
        echo "No maintenance tables found\n";
    }
    
} catch (Exception $e) {
    echo "Database check failed: " . $e->getMessage() . "\n";
}
?>
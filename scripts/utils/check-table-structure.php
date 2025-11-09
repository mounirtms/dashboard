<?php
// Load database configuration
$config = include '/home/technadminy7/public_html/app/etc/env.php';
$dbConfig = $config['db']['connection']['default'];

// Database connection
try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8",
        $dbConfig['username'],
        $dbConfig['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check structure of catalog_product_entity_media_gallery_value_to_entity
    $stmt = $pdo->prepare("DESCRIBE catalog_product_entity_media_gallery_value_to_entity");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Table: catalog_product_entity_media_gallery_value_to_entity\n";
    echo "Columns:\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    echo "\n";
    
    // Check structure of catalog_product_entity_media_gallery
    $stmt = $pdo->prepare("DESCRIBE catalog_product_entity_media_gallery");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Table: catalog_product_entity_media_gallery\n";
    echo "Columns:\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?><?php
// Load database configuration
$config = include '/home/technadminy7/public_html/app/etc/env.php';
$dbConfig = $config['db']['connection']['default'];

// Database connection
try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8",
        $dbConfig['username'],
        $dbConfig['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check structure of catalog_product_entity_media_gallery_value_to_entity
    $stmt = $pdo->prepare("DESCRIBE catalog_product_entity_media_gallery_value_to_entity");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Table: catalog_product_entity_media_gallery_value_to_entity\n";
    echo "Columns:\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    echo "\n";
    
    // Check structure of catalog_product_entity_media_gallery
    $stmt = $pdo->prepare("DESCRIBE catalog_product_entity_media_gallery");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Table: catalog_product_entity_media_gallery\n";
    echo "Columns:\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
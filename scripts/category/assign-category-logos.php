<?php
/**
 * Script to assign SVG logos to categories in Magento
 * This script directly updates the database to assign logos to categories
 */

// Database connection details
$host = '127.0.0.1';
$port = '3307';
$dbname = 'technadminy7_dBT8x12y22';
$username = 'root';
$password = 'YourNewStrongPassword';

try {
    // Create PDO connection
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully.\n";
    
    // Define category to logo mappings
    $logoAssignments = [
        1798 => '/category_logos/promos_techno_logo.svg', // Promos category
        // Add more categories here as needed
    ];
    
    // Get the attribute ID for category images
    $stmt = $pdo->prepare("SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'image' AND entity_type_id = 3");
    $stmt->execute();
    $attributeResult = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$attributeResult) {
        die("Could not find 'image' attribute for categories.\n");
    }
    
    $attributeId = $attributeResult['attribute_id'];
    echo "Found image attribute ID: $attributeId\n";
    
    // Process each category assignment
    foreach ($logoAssignments as $categoryId => $logoPath) {
        try {
            // Check if an image entry already exists for this category
            $stmt = $pdo->prepare("SELECT value_id FROM catalog_category_entity_varchar WHERE attribute_id = ? AND entity_id = ?");
            $stmt->execute([$attributeId, $categoryId]);
            $existingResult = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingResult) {
                // Update existing entry
                $stmt = $pdo->prepare("UPDATE catalog_category_entity_varchar SET value = ? WHERE value_id = ?");
                $stmt->execute([$logoPath, $existingResult['value_id']]);
                echo "Updated logo for category ID $categoryId\n";
            } else {
                // Insert new entry
                $stmt = $pdo->prepare("INSERT INTO catalog_category_entity_varchar (attribute_id, entity_id, value) VALUES (?, ?, ?)");
                $stmt->execute([$attributeId, $categoryId, $logoPath]);
                echo "Inserted logo for category ID $categoryId\n";
            }
        } catch (Exception $e) {
            echo "Error assigning logo to category ID $categoryId: " . $e->getMessage() . "\n";
        }
    }
    
    echo "Logo assignment completed!\n";
    echo "Please flush the cache and reindex to see changes in the admin panel:\n";
    echo "php bin/magento cache:flush\n";
    echo "php bin/magento indexer:reindex\n";
    
} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
}
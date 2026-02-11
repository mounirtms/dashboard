<?php
/**
 * Simple Missing Images Audit (No Magento Bootstrap Required)
 */

echo "=== COMPREHENSIVE MISSING IMAGES AUDIT ===\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n\n";

$host = '127.0.0.1';
$port = '3307';
$dbName = 'technadminy7_dBT8x12y22';
$username = 'root';
$password = 'YourNewStrongPassword';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbName", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all products with image paths
    $sql = "
    SELECT 
        e.entity_id,
        e.sku,
        e.type_id,
        v1.value as name,
        v2.value as image,
        v3.value as small_image,
        v4.value as thumbnail,
        v5.value as visibility,
        v6.value as status
    FROM catalog_product_entity e
    LEFT JOIN catalog_product_entity_varchar v1 ON e.entity_id = v1.entity_id 
        AND v1.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'name' AND entity_type_id = 4) 
        AND v1.store_id = 0
    LEFT JOIN catalog_product_entity_varchar v2 ON e.entity_id = v2.entity_id 
        AND v2.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'image' AND entity_type_id = 4) 
        AND v2.store_id = 0
    LEFT JOIN catalog_product_entity_varchar v3 ON e.entity_id = v3.entity_id 
        AND v3.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'small_image' AND entity_type_id = 4) 
        AND v3.store_id = 0
    LEFT JOIN catalog_product_entity_varchar v4 ON e.entity_id = v4.entity_id 
        AND v4.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'thumbnail' AND entity_type_id = 4) 
        AND v4.store_id = 0
    LEFT JOIN catalog_product_entity_int v5 ON e.entity_id = v5.entity_id 
        AND v5.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'visibility' AND entity_type_id = 4) 
        AND v5.store_id = 0
    LEFT JOIN catalog_product_entity_int v6 ON e.entity_id = v6.entity_id 
        AND v6.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'status' AND entity_type_id = 4) 
        AND v6.store_id = 0
    WHERE (v2.value IS NOT NULL AND v2.value != '' AND v2.value != 'no_selection')
       OR (v3.value IS NOT NULL AND v3.value != '' AND v3.value != 'no_selection')
       OR (v4.value IS NOT NULL AND v4.value != '' AND v4.value != 'no_selection')
    ORDER BY e.entity_id
    ";
    
    $stmt = $pdo->query($sql);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total products to check: " . count($products) . "\n";
    echo "Checking image file existence...\n\n";
    
    $missingImages = [];
    $checkedCount = 0;
    $missingCount = 0;
    $mediaPath = __DIR__ . '/pub/media/catalog/product';
    
    foreach ($products as $product) {
        $checkedCount++;
        
        if ($checkedCount % 1000 == 0) {
            echo "Checked: $checkedCount products...\n";
        }
        
        $hasMissingImage = false;
        $missingTypes = [];
        
        // Check each image type
        $imageTypes = [
            'image' => $product['image'],
            'small_image' => $product['small_image'],
            'thumbnail' => $product['thumbnail']
        ];
        
        foreach ($imageTypes as $type => $imagePath) {
            if (!empty($imagePath) && $imagePath != 'no_selection') {
                $fullPath = $mediaPath . $imagePath;
                if (!file_exists($fullPath)) {
                    $hasMissingImage = true;
                    $missingTypes[] = $type;
                }
            }
        }
        
        if ($hasMissingImage) {
            $missingCount++;
            $missingImages[] = [
                'entity_id' => $product['entity_id'],
                'sku' => $product['sku'],
                'type_id' => $product['type_id'],
                'name' => $product['name'],
                'image' => $product['image'],
                'small_image' => $product['small_image'],
                'thumbnail' => $product['thumbnail'],
                'missing_types' => implode(', ', $missingTypes),
                'visibility' => $product['visibility'],
                'status' => $product['status'] == 1 ? 'Enabled' : 'Disabled'
            ];
        }
    }
    
    echo "\n=== AUDIT SUMMARY ===\n";
    echo "Total products checked: $checkedCount\n";
    echo "Products with missing images: $missingCount\n";
    echo "Percentage missing: " . round(($missingCount / $checkedCount) * 100, 2) . "%\n\n";
    
    // Export to CSV
    $csvFile = __DIR__ . '/var/missing_images_report.csv';
    $fp = fopen($csvFile, 'w');
    
    // CSV headers
    fputcsv($fp, [
        'Entity ID',
        'SKU',
        'Type',
        'Name',
        'Image Path',
        'Small Image Path',
        'Thumbnail Path',
        'Missing Types',
        'Visibility',
        'Status'
    ]);
    
    // Write data
    foreach ($missingImages as $item) {
        fputcsv($fp, [
            $item['entity_id'],
            $item['sku'],
            $item['type_id'],
            $item['name'],
            $item['image'],
            $item['small_image'],
            $item['thumbnail'],
            $item['missing_types'],
            $item['visibility'],
            $item['status']
        ]);
    }
    
    fclose($fp);
    
    echo "CSV exported to: $csvFile\n";
    echo "File size: " . round(filesize($csvFile) / 1024, 2) . " KB\n\n";
    
    // Show sample of missing images (first 20)
    echo "=== SAMPLE MISSING IMAGES (First 20) ===\n";
    $sample = array_slice($missingImages, 0, 20);
    foreach ($sample as $item) {
        echo sprintf(
            "ID: %d | SKU: %s | Status: %s | Missing: %s\n",
            $item['entity_id'],
            $item['sku'],
            $item['status'],
            $item['missing_types']
        );
    }
    
    // Priority products (Enabled + Visible)
    $priorityMissing = array_filter($missingImages, function($item) {
        return $item['status'] == 'Enabled' && $item['visibility'] >= 2;
    });
    
    echo "\n=== HIGH PRIORITY (Enabled + Visible) ===\n";
    echo "Count: " . count($priorityMissing) . "\n";
    
    // Show first 10 high priority
    echo "\nFirst 10 High Priority Products:\n";
    $prioritySample = array_slice($priorityMissing, 0, 10);
    foreach ($prioritySample as $item) {
        echo sprintf(
            "ID: %d | SKU: %s | Name: %s\n",
            $item['entity_id'],
            $item['sku'],
            substr($item['name'], 0, 50)
        );
    }
    
    echo "\nCompleted: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

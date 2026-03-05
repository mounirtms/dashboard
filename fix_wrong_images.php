<?php
/**
 * Script to detect and fix wrong product images
 * Identifies cases where image filename doesn't match product SKU or reference
 */

$host = '127.0.0.1';
$port = '3307';
$db = 'technadminy7_dBT8x12y22';
$user = 'root';
$pass = 'YourNewStrongPassword';

$mediaPath = '/home/technadminy7/public_html/pub/media/catalog/product';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

echo "========================================\n";
echo "Product Image Mismatch Detection\n";
echo "========================================\n\n";

// Get all products with their images
$sql = "SELECT cpe.entity_id, cpe.sku, 
               cpev_img.value as image,
               cpev_name.value as name
        FROM catalog_product_entity cpe
        INNER JOIN catalog_product_entity_varchar cpev_img 
            ON cpe.entity_id = cpev_img.entity_id 
            AND cpev_img.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'image' AND entity_type_id = 4)
        INNER JOIN catalog_product_entity_varchar cpev_name 
            ON cpe.entity_id = cpev_name.entity_id 
            AND cpev_name.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'name' AND entity_type_id = 4)
        WHERE cpev_img.value NOT IN ('no_selection', '/no_selection')
        ORDER BY cpe.entity_id";

$stmt = $pdo->query($sql);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$mismatched = 0;
$checked = 0;
$fixReport = [];

foreach ($products as $product) {
    $entityId = $product['entity_id'];
    $sku = $product['sku'];
    $imagePath = $product['image'];
    $name = $product['name'];
    
    // Extract image filename
    $imageFilename = basename($imagePath);
    $imageFilenameNoExt = pathinfo($imageFilename, PATHINFO_FILENAME);
    
    // Check if SKU or reference number is in the image filename
    $skuPart = substr($sku, 0, 8); // First 8 digits of SKU
    $refMatch = [];
    
    // Try to extract REF number from product name
    if (preg_match('/REF[:\s]*([A-Z0-9]+)/i', $name, $refMatch)) {
        $refNumber = strtolower($refMatch[1]);
        
        // Check if image filename matches the REF number
        if (stripos($imageFilenameNoExt, $refNumber) === false) {
            $mismatched++;
            
            // Try to find the correct image
            $imageDir = dirname($imagePath);
            $imageExt = pathinfo($imageFilename, PATHINFO_EXTENSION);
            
            // Search for images that match the REF number
            $searchPattern = $mediaPath . $imageDir . '/*' . $refNumber . '*.' . $imageExt;
            $foundImages = glob($searchPattern);
            
            $correctImage = null;
            if ($foundImages && count($foundImages) > 0) {
                $correctImage = str_replace($mediaPath, '', $foundImages[0]);
            }
            
            echo "MISMATCH: SKU {$sku} (ID: {$entityId})\n";
            echo "  Product: {$name}\n";
            echo "  Current Image: {$imagePath}\n";
            if ($correctImage) {
                echo "  Suggested Image: {$correctImage}\n";
                $fixReport[] = [
                    'entity_id' => $entityId,
                    'sku' => $sku,
                    'current' => $imagePath,
                    'correct' => $correctImage
                ];
            } else {
                echo "  Suggested Image: NOT FOUND\n";
            }
            echo "\n";
        }
    }
    
    $checked++;
}

echo "\n========================================\n";
echo "Summary\n";
echo "========================================\n";
echo "Checked: {$checked} products\n";
echo "Mismatched: {$mismatched} products\n";
echo "\n";

if (count($fixReport) > 0) {
    echo "Would you like to apply fixes for " . count($fixReport) . " products? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim(strtolower($line)) === 'y') {
        echo "\nApplying fixes...\n";
        foreach ($fixReport as $fix) {
            $updateSql = "UPDATE catalog_product_entity_varchar 
                         SET value = ? 
                         WHERE entity_id = ? 
                         AND attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'image' AND entity_type_id = 4)";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([$fix['correct'], $fix['entity_id']]);
            
            // Also update small_image and thumbnail
            $updateSql = "UPDATE catalog_product_entity_varchar 
                         SET value = ? 
                         WHERE entity_id = ? 
                         AND attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'small_image' AND entity_type_id = 4)";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([$fix['correct'], $fix['entity_id']]);
            
            $updateSql = "UPDATE catalog_product_entity_varchar 
                         SET value = ? 
                         WHERE entity_id = ? 
                         AND attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'thumbnail' AND entity_type_id = 4)";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([$fix['correct'], $fix['entity_id']]);
            
            echo "  Fixed: SKU {$fix['sku']} - {$fix['current']} -> {$fix['correct']}\n";
        }
        echo "\nFixes applied successfully!\n";
    }
}

// Save report to file
file_put_contents('/home/technadminy7/public_html/image_mismatch_report.txt', 
    "Product Image Mismatch Report\n" . 
    "Generated: " . date('Y-m-d H:i:s') . "\n" .
    "========================================\n\n" .
    "Checked: {$checked} products\n" .
    "Mismatched: {$mismatched} products\n\n" .
    json_encode($fixReport, JSON_PRETTY_PRINT));

echo "\nReport saved to: /home/technadminy7/public_html/image_mismatch_report.txt\n";

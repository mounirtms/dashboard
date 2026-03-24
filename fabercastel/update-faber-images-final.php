<?php
/**
 * Update Faber-Castel Product Images - Final Version
 * Uses exact Image Name from CSV
 */

if (php_sapi_name() !== 'cli') {
    die('CLI only');
}

require __DIR__ . '/../app/bootstrap.php';

$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
$connection = $resource->getConnection();

$csvFile = $argv[1] ?? null;
$mediaDir = $argv[2] ?? null;

if (!$csvFile || !$mediaDir) {
    echo "Usage: php update-faber-images-final.php <csv_file> <media_dir>\n";
    exit(1);
}

echo "\n╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║         UPDATE FABER-CASTEL IMAGES (FINAL)                                 ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// Parse CSV
echo "Parsing CSV...\n";
$content = file_get_contents($csvFile);
$content = str_replace(["\r\n", "\r"], "\n", $content);
$tempFile = tempnam(sys_get_temp_dir(), 'csv_');
file_put_contents($tempFile, $content);

$products = [];
if (($handle = fopen($tempFile, 'r')) !== false) {
    $header = fgetcsv($handle, 0, ',', '"');
    $skuIndex = array_search('sku', $header);
    $imageNameIndex = array_search('Image Name', $header);
    
    while (($data = fgetcsv($handle, 0, ',', '"')) !== false) {
        if (count($data) >= max($skuIndex, $imageNameIndex) + 1) {
            $sku = trim($data[$skuIndex]);
            $imageName = trim($data[$imageNameIndex]);
            if (!empty($sku) && !empty($imageName)) {
                $products[$sku] = $imageName;
            }
        }
    }
    fclose($handle);
}
unlink($tempFile);

echo "Found " . count($products) . " products\n\n";

// Get attribute IDs
$imageAttrId = $connection->fetchOne(
    "SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'image' AND entity_type_id = 4"
);
$smallImageAttrId = $connection->fetchOne(
    "SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'small_image' AND entity_type_id = 4"
);
$thumbnailAttrId = $connection->fetchOne(
    "SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'thumbnail' AND entity_type_id = 4"
);
$mediaGalleryAttrId = $connection->fetchOne(
    "SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'media_gallery' AND entity_type_id = 4"
);

echo "Updating product images...\n\n";

$updated = 0;
$skipped = 0;
$errors = 0;

foreach ($products as $sku => $imageName) {
    $productId = $connection->fetchOne("SELECT entity_id FROM catalog_product_entity WHERE sku = ?", [$sku]);
    
    if (!$productId) {
        echo "⚠️  SKU $sku: Product not found\n";
        $skipped++;
        continue;
    }
    
    // Get SKU-based directory
    $skuFirst = substr($sku, 0, 2);
    $skuSecond = substr($sku, 2, 2);
    $imagePath = "/$skuFirst/$skuSecond/$imageName.jpg";
    $fullImagePath = $mediaDir . $imagePath;
    
    if (!file_exists($fullImagePath)) {
        echo "⚠️  SKU $sku: Image not found ($imagePath)\n";
        $skipped++;
        continue;
    }
    
    // Check if image already set
    $currentImage = $connection->fetchOne(
        "SELECT value FROM catalog_product_entity_varchar 
         WHERE entity_id = ? AND attribute_id = ? AND store_id = 0",
        [$productId, $imageAttrId]
    );
    
    if ($currentImage === $imagePath) {
        echo "⚠️  SKU $sku: Image already set\n";
        $skipped++;
        continue;
    }
    
    try {
        // Get image info
        $imageInfo = getimagesize($fullImagePath);
        
        // Update base image attributes
        $connection->query(
            "INSERT INTO catalog_product_entity_varchar (attribute_id, store_id, entity_id, value)
             VALUES (?, 0, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)",
            [$imageAttrId, $productId, $imagePath]
        );
        
        $connection->query(
            "INSERT INTO catalog_product_entity_varchar (attribute_id, store_id, entity_id, value)
             VALUES (?, 0, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)",
            [$smallImageAttrId, $productId, $imagePath]
        );
        
        $connection->query(
            "INSERT INTO catalog_product_entity_varchar (attribute_id, store_id, entity_id, value)
             VALUES (?, 0, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)",
            [$thumbnailAttrId, $productId, $imagePath]
        );
        
        // Add to media gallery
        if ($mediaGalleryAttrId) {
            // Check if already in gallery
            $existingGallery = $connection->fetchOne(
                "SELECT value_id FROM catalog_product_entity_media_gallery 
                 WHERE attribute_id = ? AND value = ?",
                [$mediaGalleryAttrId, $imagePath]
            );
            
            if (!$existingGallery) {
                $galleryRecord = [
                    'attribute_id' => $mediaGalleryAttrId,
                    'value' => $imagePath,
                    'media_type' => 'image',
                    'disabled' => 0,
                ];
                
                $connection->insertOnDuplicate(
                    $resource->getTableName('catalog_product_entity_media_gallery'),
                    $galleryRecord,
                    ['value', 'media_type', 'disabled']
                );
                
                $valueId = $connection->lastInsertId();
                
                // Link to product
                $connection->query(
                    "INSERT INTO catalog_product_entity_media_gallery_value_to_entity (value_id, entity_id)
                     VALUES (?, ?)",
                    [$valueId, $productId]
                );
                
                // Add value record
                $connection->query(
                    "INSERT INTO catalog_product_entity_media_gallery_value (value_id, store_id, entity_id, disabled, position, record_id)
                     VALUES (?, 0, ?, 0, 1, ?)
                     ON DUPLICATE KEY UPDATE disabled = 0, position = 1",
                    [$valueId, $productId, $valueId]
                );
            }
        }
        
        echo "✅ $sku: Image updated ($imagePath)\n";
        $updated++;
        
    } catch (\Exception $e) {
        echo "❌ $sku: Error - " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n═══════════════════════════════════════════════════════════════════════════\n";
echo "SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════════════════\n";
echo "✅ Updated: $updated\n";
echo "⚠️  Skipped: $skipped\n";
echo "❌ Errors: $errors\n\n";

echo "✅ Image update complete!\n\n";

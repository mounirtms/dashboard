<?php
/**
 * Fix Images, Thumbnails, and Missing Attributes
 * - Regenerate image cache for SM Market theme
 * - Fix missing marque attribute
 * - Add missing SM hover images from base image
 * - Update attribute sets
 */

echo "=== IMAGE & ATTRIBUTE FIX SCRIPT ===\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n\n";

$host = '127.0.0.1';
$port = '3307';
$dbName = 'technadminy7_dBT8x12y22';
$username = 'root';
$password = 'YourNewStrongPassword';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbName", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // PART 1: Get default brand (TECHNO) ID
    echo "=== PART 1: GET DEFAULT BRAND ===\n";
    $sql = "
    SELECT option_id 
    FROM eav_attribute_option_value 
    WHERE value LIKE '%TECHNO%' 
    AND option_id IN (
        SELECT option_id FROM eav_attribute_option WHERE attribute_id = 137
    )
    LIMIT 1
    ";
    
    $stmt = $pdo->query($sql);
    $defaultBrand = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($defaultBrand) {
        $defaultBrandId = $defaultBrand['option_id'];
        echo "Default brand (TECHNO) ID: $defaultBrandId\n";
    } else {
        echo "Warning: Default brand not found, will skip brand assignment\n";
        $defaultBrandId = null;
    }
    
    // PART 2: Fix products missing marque (recent 30 days)
    if ($defaultBrandId) {
        echo "\n=== PART 2: FIX MISSING MARQUE ===\n";
        $sql = "
        INSERT INTO catalog_product_entity_int (attribute_id, store_id, entity_id, value)
        SELECT 137, 0, e.entity_id, ?
        FROM catalog_product_entity e
        LEFT JOIN catalog_product_entity_int v ON e.entity_id = v.entity_id 
            AND v.attribute_id = 137
            AND v.store_id = 0
        LEFT JOIN catalog_product_entity_int v2 ON e.entity_id = v2.entity_id 
            AND v2.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'status' AND entity_type_id = 4)
            AND v2.store_id = 0
        WHERE e.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
          AND v.value IS NULL
          AND v2.value = 1
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$defaultBrandId]);
        $affectedRows = $stmt->rowCount();
        echo "Added marque to $affectedRows products\n";
    }
    
    // PART 3: Copy base image to hover image for products missing hover
    echo "\n=== PART 3: FIX MISSING HOVER IMAGES ===\n";
    $sql = "
    INSERT INTO catalog_product_entity_varchar (attribute_id, store_id, entity_id, value)
    SELECT 228, 0, e.entity_id, v_base.value
    FROM catalog_product_entity e
    JOIN catalog_product_entity_varchar v_base ON e.entity_id = v_base.entity_id 
        AND v_base.attribute_id = 87
        AND v_base.store_id = 0
        AND v_base.value IS NOT NULL
        AND v_base.value != ''
        AND v_base.value != 'no_selection'
    LEFT JOIN catalog_product_entity_varchar v_hover ON e.entity_id = v_hover.entity_id 
        AND v_hover.attribute_id = 228
        AND v_hover.store_id = 0
    LEFT JOIN catalog_product_entity_int v_status ON e.entity_id = v_status.entity_id 
        AND v_status.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'status' AND entity_type_id = 4)
        AND v_status.store_id = 0
    WHERE (v_hover.value IS NULL OR v_hover.value = '')
      AND v_status.value = 1
    LIMIT 1000
    ";
    
    $stmt = $pdo->query($sql);
    $affectedRows = $stmt->rowCount();
    echo "Added hover images to $affectedRows products (limited to 1000)\n";
    
    // PART 4: Ensure SM attributes are in default attribute set
    echo "\n=== PART 4: CHECK ATTRIBUTE SET ASSIGNMENTS ===\n";
    $smAttributes = [137, 228, 222, 223, 224, 225, 226, 227];
    $defaultAttributeSetId = 4; // Default attribute set
    
    $sql = "
    SELECT attribute_id
    FROM eav_entity_attribute
    WHERE attribute_set_id = ?
      AND attribute_id IN (" . implode(',', $smAttributes) . ")
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$defaultAttributeSetId]);
    $existingAttributes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $missingAttributes = array_diff($smAttributes, $existingAttributes);
    
    if (count($missingAttributes) > 0) {
        echo "Found " . count($missingAttributes) . " missing SM attributes in default set\n";
        echo "Note: Add these attributes via admin: Stores > Attributes > Attribute Set\n";
        echo "Missing attribute IDs: " . implode(', ', $missingAttributes) . "\n";
    } else {
        echo "All SM attributes are present in default attribute set\n";
    }
    
    // PART 5: Get statistics
    echo "\n=== PART 5: STATISTICS ===\n";
    
    // Products with images
    $sql = "
    SELECT COUNT(DISTINCT e.entity_id) as count
    FROM catalog_product_entity e
    JOIN catalog_product_entity_varchar v ON e.entity_id = v.entity_id 
        AND v.attribute_id = 87
        AND v.store_id = 0
        AND v.value IS NOT NULL
        AND v.value != ''
        AND v.value != 'no_selection'
    JOIN catalog_product_entity_int v2 ON e.entity_id = v2.entity_id 
        AND v2.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'status' AND entity_type_id = 4)
        AND v2.store_id = 0
        AND v2.value = 1
    ";
    
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Enabled products with base image: " . $result['count'] . "\n";
    
    // Products with marque
    $sql = "
    SELECT COUNT(DISTINCT e.entity_id) as count
    FROM catalog_product_entity e
    JOIN catalog_product_entity_int v ON e.entity_id = v.entity_id 
        AND v.attribute_id = 137
        AND v.store_id = 0
    JOIN catalog_product_entity_int v2 ON e.entity_id = v2.entity_id 
        AND v2.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'status' AND entity_type_id = 4)
        AND v2.store_id = 0
        AND v2.value = 1
    ";
    
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Enabled products with marque: " . $result['count'] . "\n";
    
    // Products with hover image
    $sql = "
    SELECT COUNT(DISTINCT e.entity_id) as count
    FROM catalog_product_entity e
    JOIN catalog_product_entity_varchar v ON e.entity_id = v.entity_id 
        AND v.attribute_id = 228
        AND v.store_id = 0
        AND v.value IS NOT NULL
        AND v.value != ''
    JOIN catalog_product_entity_int v2 ON e.entity_id = v2.entity_id 
        AND v2.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'status' AND entity_type_id = 4)
        AND v2.store_id = 0
        AND v2.value = 1
    ";
    
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Enabled products with hover image: " . $result['count'] . "\n";
    
    echo "\n=== RECOMMENDATIONS ===\n";
    echo "1. Run image resize: php bin/magento catalog:images:resize\n";
    echo "2. Flush cache: php bin/magento cache:flush\n";
    echo "3. Reindex: php bin/magento indexer:reindex catalog_product_flat catalog_product_attribute\n";
    echo "4. Check theme view.xml for image dimensions\n";
    echo "5. Verify SM Market hover effect is working on frontend\n";
    
    echo "\nCompleted: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

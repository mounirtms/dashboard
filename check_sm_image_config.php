<?php
/**
 * Check SM Market Image Configuration and Attributes
 */

echo "=== SM MARKET IMAGE & ATTRIBUTE AUDIT ===\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n\n";

$host = '127.0.0.1';
$port = '3307';
$dbName = 'technadminy7_dBT8x12y22';
$username = 'root';
$password = 'YourNewStrongPassword';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbName", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 1. Check SM-related attributes
    echo "=== SM MARKET ATTRIBUTES ===\n";
    $sql = "
    SELECT 
        attribute_code,
        attribute_id,
        frontend_label,
        backend_type,
        is_required,
        default_value
    FROM eav_attribute 
    WHERE entity_type_id = 4 
      AND (attribute_code LIKE 'sm_%' OR attribute_code LIKE 'mgs_%')
    ORDER BY attribute_code
    ";
    
    $stmt = $pdo->query($sql);
    $smAttributes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($smAttributes) . " SM/MGS attributes:\n";
    foreach ($smAttributes as $attr) {
        echo sprintf(
            "  %s (ID: %d, Type: %s, Required: %s)\n",
            $attr['attribute_code'],
            $attr['attribute_id'],
            $attr['backend_type'],
            $attr['is_required'] ? 'Yes' : 'No'
        );
    }
    
    // 2. Check products missing mgs_brand (marque)
    echo "\n=== PRODUCTS MISSING MARQUE (Last 30 days) ===\n";
    $sql = "
    SELECT COUNT(*) as count
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
    
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Enabled products missing marque: " . $result['count'] . "\n";
    
    // 3. Check products with missing hover images
    echo "\n=== PRODUCTS MISSING SM HOVER IMAGE ===\n";
    $sql = "
    SELECT COUNT(*) as count
    FROM catalog_product_entity e
    LEFT JOIN catalog_product_entity_varchar v ON e.entity_id = v.entity_id 
        AND v.attribute_id = 228
        AND v.store_id = 0
    LEFT JOIN catalog_product_entity_int v2 ON e.entity_id = v2.entity_id 
        AND v2.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'status' AND entity_type_id = 4)
        AND v2.store_id = 0
    WHERE (v.value IS NULL OR v.value = '')
      AND v2.value = 1
    LIMIT 1
    ";
    
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Enabled products missing hover image: " . $result['count'] . "\n";
    
    // 4. Check image cache configuration
    echo "\n=== IMAGE CACHE STATUS ===\n";
    $sql = "
    SELECT name, value
    FROM core_config_data
    WHERE path LIKE '%image%' 
       OR path LIKE '%thumbnail%'
       OR path LIKE '%sm_%'
       OR path LIKE '%catalog/product%'
    ORDER BY path
    LIMIT 20
    ";
    
    $stmt = $pdo->query($sql);
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($configs) . " image-related configurations\n";
    
    // 5. Check recently created products without images
    echo "\n=== RECENT PRODUCTS WITHOUT BASE IMAGE (Last 7 days) ===\n";
    $sql = "
    SELECT 
        e.entity_id,
        e.sku,
        e.created_at,
        v1.value as name
    FROM catalog_product_entity e
    LEFT JOIN catalog_product_entity_varchar v1 ON e.entity_id = v1.entity_id 
        AND v1.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'name' AND entity_type_id = 4)
        AND v1.store_id = 0
    LEFT JOIN catalog_product_entity_varchar v2 ON e.entity_id = v2.entity_id 
        AND v2.attribute_id = 87
        AND v2.store_id = 0
    LEFT JOIN catalog_product_entity_int v3 ON e.entity_id = v3.entity_id 
        AND v3.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'status' AND entity_type_id = 4)
        AND v3.store_id = 0
    WHERE e.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
      AND (v2.value IS NULL OR v2.value = '' OR v2.value = 'no_selection')
      AND v3.value = 1
    ORDER BY e.created_at DESC
    LIMIT 10
    ";
    
    $stmt = $pdo->query($sql);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($products) > 0) {
        echo "Found " . count($products) . " recent products without images:\n";
        foreach ($products as $p) {
            echo sprintf(
                "  ID: %d | SKU: %s | Created: %s\n",
                $p['entity_id'],
                $p['sku'],
                $p['created_at']
            );
        }
    } else {
        echo "No recent products missing base image\n";
    }
    
    // 6. Check attribute sets usage
    echo "\n=== ATTRIBUTE SETS WITH MISSING SM ATTRIBUTES ===\n";
    $sql = "
    SELECT 
        eas.attribute_set_name,
        COUNT(DISTINCT e.entity_id) as product_count
    FROM catalog_product_entity e
    JOIN eav_attribute_set eas ON e.attribute_set_id = eas.attribute_set_id
    WHERE e.attribute_set_id NOT IN (
        SELECT DISTINCT attribute_set_id 
        FROM eav_entity_attribute 
        WHERE attribute_id IN (137, 228, 222, 223, 224, 225, 226, 227)
    )
    GROUP BY eas.attribute_set_name
    ORDER BY product_count DESC
    LIMIT 10
    ";
    
    $stmt = $pdo->query($sql);
    $attrSets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($attrSets) > 0) {
        echo "Attribute sets missing SM attributes:\n";
        foreach ($attrSets as $as) {
            echo sprintf(
                "  %s: %d products\n",
                $as['attribute_set_name'],
                $as['product_count']
            );
        }
    } else {
        echo "All attribute sets have SM attributes configured\n";
    }
    
    echo "\nCompleted: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

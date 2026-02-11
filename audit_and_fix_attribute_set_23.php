<?php
/**
 * Audit and Fix Attribute Set 23 (Products)
 * Add missing SM Market and other essential attributes
 */

echo "=== ATTRIBUTE SET 23 AUDIT & FIX ===\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n\n";

$host = '127.0.0.1';
$port = '3307';
$dbName = 'technadminy7_dBT8x12y22';
$username = 'root';
$password = 'YourNewStrongPassword';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbName", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $attributeSetId = 23;
    $entityTypeId = 4; // Product
    
    // PART 1: Get current attributes in set
    echo "=== PART 1: CURRENT ATTRIBUTES IN SET 23 ===\n";
    $sql = "
    SELECT 
        a.attribute_id,
        a.attribute_code,
        a.frontend_label,
        a.backend_type,
        eea.attribute_group_id,
        eag.attribute_group_name
    FROM eav_entity_attribute eea
    JOIN eav_attribute a ON eea.attribute_id = a.attribute_id
    LEFT JOIN eav_attribute_group eag ON eea.attribute_group_id = eag.attribute_group_id
    WHERE eea.attribute_set_id = ?
      AND eea.entity_type_id = ?
    ORDER BY eag.sort_order, eea.sort_order
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$attributeSetId, $entityTypeId]);
    $currentAttributes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total attributes in set: " . count($currentAttributes) . "\n";
    
    // Group by attribute group
    $grouped = [];
    foreach ($currentAttributes as $attr) {
        $groupName = $attr['attribute_group_name'] ?: 'No Group';
        if (!isset($grouped[$groupName])) {
            $grouped[$groupName] = [];
        }
        $grouped[$groupName][] = $attr;
    }
    
    echo "\nAttributes by group:\n";
    foreach ($grouped as $groupName => $attrs) {
        echo "  $groupName: " . count($attrs) . " attributes\n";
    }
    
    // PART 2: Check for missing essential attributes
    echo "\n=== PART 2: CHECK MISSING ESSENTIAL ATTRIBUTES ===\n";
    
    $essentialAttributes = [
        137 => 'mgs_brand (Marque)',
        228 => 'sm_hoverimage (Hover Image)',
        222 => 'sm_degree_path (360° Path)',
        223 => 'sm_degree_index (360° Index)',
        224 => 'sm_degree_width (360° Width)',
        225 => 'sm_degree_height (360° Height)',
        226 => 'sm_featured (Featured)',
        227 => 'sm_sizechart (Size Chart)',
        87 => 'image (Base Image)',
        88 => 'small_image (Small Image)',
        89 => 'thumbnail (Thumbnail)',
    ];
    
    $currentAttrIds = array_column($currentAttributes, 'attribute_id');
    $missingAttributes = array_diff_key($essentialAttributes, array_flip($currentAttrIds));
    
    if (count($missingAttributes) > 0) {
        echo "Found " . count($missingAttributes) . " missing attributes:\n";
        foreach ($missingAttributes as $attrId => $attrName) {
            echo "  - ID $attrId: $attrName\n";
        }
    } else {
        echo "All essential attributes are present ✓\n";
    }
    
    // PART 3: Get or create attribute groups
    echo "\n=== PART 3: ATTRIBUTE GROUPS ===\n";
    
    $sql = "
    SELECT 
        attribute_group_id,
        attribute_group_name,
        sort_order
    FROM eav_attribute_group
    WHERE attribute_set_id = ?
    ORDER BY sort_order
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$attributeSetId]);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Existing groups in set 23:\n";
    foreach ($groups as $group) {
        echo sprintf("  - ID %d: %s (Sort: %d)\n", 
            $group['attribute_group_id'],
            $group['attribute_group_name'],
            $group['sort_order']
        );
    }
    
    // Find "General" group or first group
    $generalGroup = null;
    foreach ($groups as $group) {
        if (stripos($group['attribute_group_name'], 'general') !== false) {
            $generalGroup = $group;
            break;
        }
    }
    if (!$generalGroup && count($groups) > 0) {
        $generalGroup = $groups[0];
    }
    
    if ($generalGroup) {
        echo "\nUsing group for new attributes: " . $generalGroup['attribute_group_name'] . " (ID: " . $generalGroup['attribute_group_id'] . ")\n";
    } else {
        echo "\nWarning: No attribute group found!\n";
    }
    
    // PART 4: Add missing attributes
    if (count($missingAttributes) > 0 && $generalGroup) {
        echo "\n=== PART 4: ADDING MISSING ATTRIBUTES ===\n";
        
        $sql = "
        INSERT INTO eav_entity_attribute 
        (entity_type_id, attribute_set_id, attribute_group_id, attribute_id, sort_order)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order)
        ";
        
        $stmt = $pdo->prepare($sql);
        $addedCount = 0;
        $sortOrder = 100; // Start at 100
        
        foreach ($missingAttributes as $attrId => $attrName) {
            try {
                $stmt->execute([
                    $entityTypeId,
                    $attributeSetId,
                    $generalGroup['attribute_group_id'],
                    $attrId,
                    $sortOrder
                ]);
                echo "  ✓ Added: $attrName\n";
                $addedCount++;
                $sortOrder++;
            } catch (Exception $e) {
                echo "  ✗ Failed to add $attrName: " . $e->getMessage() . "\n";
            }
        }
        
        echo "\nAdded $addedCount of " . count($missingAttributes) . " attributes\n";
    }
    
    // PART 5: Verify final state
    echo "\n=== PART 5: VERIFICATION ===\n";
    
    $sql = "
    SELECT COUNT(*) as total
    FROM eav_entity_attribute
    WHERE attribute_set_id = ?
      AND entity_type_id = ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$attributeSetId, $entityTypeId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Total attributes in set 23 now: " . $result['total'] . "\n";
    
    // Check essential attributes
    $sql = "
    SELECT COUNT(*) as found
    FROM eav_entity_attribute
    WHERE attribute_set_id = ?
      AND entity_type_id = ?
      AND attribute_id IN (" . implode(',', array_keys($essentialAttributes)) . ")
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$attributeSetId, $entityTypeId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Essential attributes present: " . $result['found'] . " of " . count($essentialAttributes) . "\n";
    
    echo "\n=== RECOMMENDATIONS ===\n";
    echo "1. Flush Magento cache: php bin/magento cache:flush\n";
    echo "2. Reindex EAV: php bin/magento indexer:reindex catalog_product_attribute\n";
    echo "3. Test product edit in admin with set 23\n";
    echo "4. Verify SM attributes appear in product form\n";
    
    echo "\nCompleted: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

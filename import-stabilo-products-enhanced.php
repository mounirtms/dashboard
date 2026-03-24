<?php
/**
 * Import STABILO Products from CSV - Enhanced Version
 *
 * Imports products from stabilo.csv with:
 * - Products set to DISABLED status
 * - Proper attribute set assignment
 * - Category assignments
 * - Brand and additional attributes
 *
 * Usage: php import-stabilo-products-enhanced.php [--dry-run]
 */

use Magento\Framework\App\Bootstrap;
require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

$state = $objectManager->get(\Magento\Framework\App\State::class);
try {
    $state->setAreaCode('adminhtml');
} catch (\Exception $e) {
    // Area code already set
}

echo "\n╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║              STABILO PRODUCTS IMPORT (ENHANCED)                            ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n";
echo "📅 Date: " . date('Y-m-d H:i:s') . "\n\n";

// Check for dry-run mode
$dryRun = in_array('--dry-run', $argv);

if ($dryRun) {
    echo "🔍 DRY RUN MODE - No changes will be made\n\n";
}

// Get resource connection
$resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
$connection = $resource->getConnection();

// CSV file path
$csvFile = __DIR__ . '/stabilo.csv';

echo "📂 CSV File: $csvFile\n";

if (!file_exists($csvFile)) {
    echo "❌ ERROR: CSV file not found!\n";
    exit(1);
}

// Read and parse CSV properly using fgetcsv
echo "📂 Parsing CSV file...\n";

$csvLines = [];
if (($handle = fopen($csvFile, "r")) !== false) {
    // Handle Windows line endings
    $csvContent = str_replace("\r\n", "\n", file_get_contents($csvFile));
    $csvContent = str_replace("\r", "\n", $csvContent);
    
    // Write to temp file with normalized line endings
    $tempFile = tempnam(sys_get_temp_dir(), 'csv_');
    file_put_contents($tempFile, $csvContent);
    
    if (($handle = fopen($tempFile, "r")) !== false) {
        while (($data = fgetcsv($handle, 0, ',', '"')) !== false) {
            $csvLines[] = $data;
        }
        fclose($handle);
    }
    unlink($tempFile);
}

$lines = $csvLines;
$header = array_shift($lines); // Remove header from data

echo "📊 Total products in CSV: " . count($lines) . "\n\n";

echo "CSV Headers: " . implode(', ', $header) . "\n\n";

// Build attribute ID map
echo "═══════════════════════════════════════════════════════════════════════════\n";
echo "ATTRIBUTE MAPPING\n";
echo "═══════════════════════════════════════════════════════════════════════════\n\n";

$attributeIds = [];
$attributeCodes = [
    'status' => 'int',
    'visibility' => 'int',
    'tax_class_id' => 'int',
    'country_of_manufacture' => 'varchar',
    'price' => 'decimal',
    'weight' => 'decimal',
    'name' => 'varchar',
    'description' => 'text',
    'short_description' => 'text',
    'mgs_brand' => 'int',
    'techno_ref' => 'varchar',
];

foreach ($attributeCodes as $code => $type) {
    $select = $connection->select()
        ->from($resource->getTableName('eav_attribute'), ['attribute_id', 'backend_type'])
        ->where('attribute_code = ?', $code)
        ->where('entity_type_id = ?', 4);
    $result = $connection->fetchRow($select);
    if ($result) {
        $attributeIds[$code] = $result['attribute_id'];
        echo "  ✓ $code: ID={$result['attribute_id']}, type={$result['backend_type']}\n";
    } else {
        echo "  ✗ $code: NOT FOUND\n";
        $attributeIds[$code] = null;
    }
}

// Get attribute set ID
$attributeSetId = $connection->fetchOne(
    "SELECT attribute_set_id FROM eav_attribute_set WHERE attribute_set_name = 'Products' AND entity_type_id = 4"
);
echo "\n  ✓ Attribute Set 'Products': ID=$attributeSetId\n";

// Get brand option ID for "STABILO"
$brandAttributeId = $attributeIds['mgs_brand'];
$brandOptionId = null;
if ($brandAttributeId) {
    // Get option value from eav_attribute_option and eav_attribute_option_value
    $brandOptionId = $connection->fetchOne(
        "SELECT eaov.option_id 
         FROM eav_attribute_option eaoo
         JOIN eav_attribute_option_value eaov ON eaoo.option_id = eaov.option_id
         WHERE eaoo.attribute_id = ? AND LOWER(eaov.value) = 'stabilo' AND eaov.store_id = 0",
        [$brandAttributeId]
    );
    
    echo "  ✓ Brand Option ID (STABILO): " . ($brandOptionId ?: 'Will use text value') . "\n";
}

// Get default category ID
$defaultCategoryId = $connection->fetchOne(
    "SELECT entity_id FROM catalog_category_entity_varchar ccev 
     JOIN eav_attribute ea ON ccev.attribute_id = ea.attribute_id 
     WHERE ea.attribute_code = 'name' AND ccev.value = 'Produits' AND ccev.store_id = 0"
);
if (!$defaultCategoryId) {
    $defaultCategoryId = 3; // Default to "Tous les produits"
}

echo "\n  ✓ Default Category ID: $defaultCategoryId\n";

echo "\n";
echo "═══════════════════════════════════════════════════════════════════════════\n";
echo "IMPORTING PRODUCTS\n";
echo "═══════════════════════════════════════════════════════════════════════════\n\n";

$imported = 0;
$updated = 0;
$skipped = 0;
$errors = 0;
$failedSkus = [];

// Process each line (header already removed)
foreach ($lines as $lineNum => $data) {
    if (!is_array($data) || count($data) < count($header)) {
        echo "⚠️  Line " . ($lineNum + 2) . ": Invalid data (skipped)\n";
        $skipped++;
        continue;
    }

    // Map CSV data to column names
    $rowData = array_combine($header, $data);

    $sku = trim($rowData['sku']);
    $name = trim($rowData['name']);
    $description = trim($rowData['description']);
    $shortDescription = trim($rowData['short_description']);
    $weight = trim($rowData['weight']) ?: 0;
    $price = trim($rowData['price']) ?: 0;
    $qty = trim($rowData['qty']) ?: 0;
    $isInStock = trim($rowData['is_in_stock']) ?: 1;
    $categories = trim($rowData['categories']);
    $productType = trim($rowData['product_type']) ?: 'simple';
    $attributeSetCode = trim($rowData['attribute_set_code']) ?: 'Products';
    $productWebsites = trim($rowData['product_websites']) ?: 'base';
    $visibility = trim($rowData['visibility']) ?: 'catalog_search';
    $taxClass = trim($rowData['tax_class_name']) ?: 'Taxable Goods';
    $countryOfManufacture = trim($rowData['country_of_manufacture']);
    $additionalAttributes = trim($rowData['additional_attributes']);

    // Skip if no SKU
    if (empty($sku)) {
        echo "⚠️  Line " . ($i + 1) . ": No SKU (skipped)\n";
        $skipped++;
        continue;
    }

    // Check if product already exists
    $productExists = $connection->fetchOne(
        "SELECT entity_id FROM catalog_product_entity WHERE sku = ?",
        [$sku]
    );

    if ($productExists) {
        echo "⚠️  $sku: Already exists (ID: $productExists) - skipped\n";
        $skipped++;
        continue;
    }

    if ($dryRun) {
        echo "✅ $sku: Would be imported (Disabled, Attribute Set: $attributeSetId)\n";
        $imported++;
        continue;
    }

    try {
        // Get attribute set ID for this product
        $productAttributeSetId = $connection->fetchOne(
            "SELECT attribute_set_id FROM eav_attribute_set 
             WHERE attribute_set_name = ? AND entity_type_id = 4",
            [$attributeSetCode]
        );
        
        if (!$productAttributeSetId) {
            $productAttributeSetId = $attributeSetId; // Default to Products
        }

        // Get website ID
        $websiteSelect = $connection->select()
            ->from($resource->getTableName('store_website'), ['website_id'])
            ->where('code = ?', $productWebsites);
        $websiteId = $connection->fetchOne($websiteSelect);

        if (!$websiteId) {
            $websiteId = 1; // Default to base website
        }

        // Get tax class ID
        $taxClassSelect = $connection->select()
            ->from($resource->getTableName('tax_class'), ['class_id'])
            ->where('class_name = ?', $taxClass)
            ->where('class_type = ?', 'PRODUCT');
        $taxClassId = $connection->fetchOne($taxClassSelect);

        if (!$taxClassId) {
            $taxClassId = 2; // Default to Taxable Goods
        }

        // Map visibility
        $visibilityMap = [
            'not_visible' => 1,
            'catalog' => 2,
            'search' => 3,
            'catalog_search' => 4,
        ];
        $visibilityId = $visibilityMap[$visibility] ?? 4; // Default to catalog_search

        // Insert product entity
        $productData = [
            'attribute_set_id' => $productAttributeSetId,
            'type_id' => $productType,
            'sku' => $sku,
            'has_options' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $connection->insert($resource->getTableName('catalog_product_entity'), $productData);
        $productId = $connection->lastInsertId();

        // Insert product website association
        $connection->insert(
            $resource->getTableName('catalog_product_website'),
            ['product_id' => $productId, 'website_id' => $websiteId]
        );

        // Insert product name (store_id = 0 for global)
        if ($attributeIds['name']) {
            $connection->insert(
                $resource->getTableName('catalog_product_entity_varchar'),
                [
                    'attribute_id' => $attributeIds['name'],
                    'store_id' => 0,
                    'entity_id' => $productId,
                    'value' => $name
                ]
            );
        }

        // Insert product description
        if ($attributeIds['description']) {
            $connection->insert(
                $resource->getTableName('catalog_product_entity_text'),
                [
                    'attribute_id' => $attributeIds['description'],
                    'store_id' => 0,
                    'entity_id' => $productId,
                    'value' => $description
                ]
            );
        }

        // Insert short description
        if ($attributeIds['short_description']) {
            $connection->insert(
                $resource->getTableName('catalog_product_entity_text'),
                [
                    'attribute_id' => $attributeIds['short_description'],
                    'store_id' => 0,
                    'entity_id' => $productId,
                    'value' => $shortDescription
                ]
            );
        }

        // Insert weight
        if ($attributeIds['weight']) {
            $connection->insert(
                $resource->getTableName('catalog_product_entity_decimal'),
                [
                    'attribute_id' => $attributeIds['weight'],
                    'store_id' => 0,
                    'entity_id' => $productId,
                    'value' => $weight
                ]
            );
        }

        // Insert price
        if ($attributeIds['price']) {
            $connection->insert(
                $resource->getTableName('catalog_product_entity_decimal'),
                [
                    'attribute_id' => $attributeIds['price'],
                    'store_id' => 0,
                    'entity_id' => $productId,
                    'value' => $price
                ]
            );
        }

        // Insert status - DISABLED (2)
        if ($attributeIds['status']) {
            $connection->insert(
                $resource->getTableName('catalog_product_entity_int'),
                [
                    'attribute_id' => $attributeIds['status'],
                    'store_id' => 0,
                    'entity_id' => $productId,
                    'value' => 2 // DISABLED
                ]
            );
        }

        // Insert visibility
        if ($attributeIds['visibility']) {
            $connection->insert(
                $resource->getTableName('catalog_product_entity_int'),
                [
                    'attribute_id' => $attributeIds['visibility'],
                    'store_id' => 0,
                    'entity_id' => $productId,
                    'value' => $visibilityId
                ]
            );
        }

        // Insert tax class ID
        if ($attributeIds['tax_class_id']) {
            $connection->insert(
                $resource->getTableName('catalog_product_entity_int'),
                [
                    'attribute_id' => $attributeIds['tax_class_id'],
                    'store_id' => 0,
                    'entity_id' => $productId,
                    'value' => $taxClassId
                ]
            );
        }

        // Insert country of manufacture
        if (!empty($countryOfManufacture) && $attributeIds['country_of_manufacture']) {
            $connection->insert(
                $resource->getTableName('catalog_product_entity_varchar'),
                [
                    'attribute_id' => $attributeIds['country_of_manufacture'],
                    'store_id' => 0,
                    'entity_id' => $productId,
                    'value' => $countryOfManufacture
                ]
            );
        }

        // Insert stock information
        $stockItemData = [
            'product_id' => $productId,
            'stock_id' => 1,
            'qty' => $qty,
            'is_in_stock' => $isInStock,
        ];
        $connection->insert($resource->getTableName('cataloginventory_stock_item'), $stockItemData);

        // Parse and assign categories
        if (!empty($categories)) {
            $categoryNames = array_map('trim', explode(',', $categories));
            foreach ($categoryNames as $categoryName) {
                // Find category by name
                $categoryId = $connection->fetchOne(
                    "SELECT ccev.entity_id 
                     FROM catalog_category_entity_varchar ccev
                     JOIN eav_attribute ea ON ccev.attribute_id = ea.attribute_id
                     WHERE ea.attribute_code = 'name' AND ccev.value = ? AND ccev.store_id = 0
                     LIMIT 1",
                    [$categoryName]
                );

                if ($categoryId) {
                    // Check if association already exists
                    $existing = $connection->fetchOne(
                        "SELECT COUNT(*) FROM catalog_category_product 
                         WHERE category_id = ? AND product_id = ?",
                        [$categoryId, $productId]
                    );

                    if (!$existing) {
                        $connection->insert(
                            $resource->getTableName('catalog_category_product'),
                            [
                                'category_id' => $categoryId,
                                'product_id' => $productId,
                                'position' => 0
                            ]
                        );
                    }
                }
            }
        }

        // Parse additional attributes (format: key1=value1,key2=value2)
        if (!empty($additionalAttributes)) {
            $attributes = explode(',', $additionalAttributes);
            foreach ($attributes as $attr) {
                $parts = explode('=', $attr, 2);
                if (count($parts) === 2) {
                    $attrCode = trim($parts[0]);
                    $attrValue = trim($parts[1]);

                    $attrId = $connection->fetchOne(
                        "SELECT attribute_id FROM eav_attribute 
                         WHERE attribute_code = ? AND entity_type_id = 4",
                        [$attrCode]
                    );

                    if ($attrId) {
                        // Determine backend type
                        $backendType = $connection->fetchOne(
                            "SELECT backend_type FROM eav_attribute WHERE attribute_id = ?",
                            [$attrId]
                        );

                        // Handle mgs_brand specially if it's a dropdown
                        if ($attrCode === 'mgs_brand' && $brandOptionId) {
                            $tableName = 'catalog_product_entity_int';
                            $valueToInsert = $brandOptionId;
                        } elseif ($backendType === 'int') {
                            $tableName = 'catalog_product_entity_int';
                            $valueToInsert = (int)$attrValue;
                        } elseif ($backendType === 'decimal') {
                            $tableName = 'catalog_product_entity_decimal';
                            $valueToInsert = (float)$attrValue;
                        } elseif ($backendType === 'text') {
                            $tableName = 'catalog_product_entity_text';
                            $valueToInsert = $attrValue;
                        } else {
                            $tableName = 'catalog_product_entity_varchar';
                            $valueToInsert = $attrValue;
                        }

                        try {
                            $connection->insert(
                                $resource->getTableName($tableName),
                                [
                                    'attribute_id' => $attrId,
                                    'store_id' => 0,
                                    'entity_id' => $productId,
                                    'value' => $valueToInsert
                                ]
                            );
                        } catch (\Exception $e) {
                            // Ignore duplicate key errors
                        }
                    }
                }
            }
        }

        echo "✅ $sku: Imported (ID: $productId, Disabled, AS: $productAttributeSetId)\n";
        $imported++;

    } catch (\Exception $e) {
        echo "❌ $sku: Error - " . $e->getMessage() . "\n";
        $errors++;
        $failedSkus[] = $sku;
    }
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════════════════\n";
echo "IMPORT SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════════════════\n";

echo "Total products in CSV: " . count($lines) . "\n";
echo "✅ Imported: $imported\n";
echo "⚠️  Skipped: $skipped\n";
echo "❌ Errors: $errors\n";

if (!empty($failedSkus)) {
    echo "\nFailed SKUs:\n";
    foreach (array_slice($failedSkus, 0, 10) as $failedSku) {
        echo "  - $failedSku\n";
    }
    if (count($failedSkus) > 10) {
        echo "  ... and " . (count($failedSkus) - 10) . " more\n";
    }
}

echo "\n";

if ($imported > 0 && !$dryRun) {
    echo "🎉 Import completed successfully!\n";
    echo "\n⚠️  IMPORTANT: Run the following commands to complete the import:\n\n";
    echo "   cd /home/beta/public_html\n";
    echo "   php bin/magento indexer:reindex\n";
    echo "   php bin/magento cache:clean\n";
    echo "   php bin/magento cache:flush\n\n";
} else {
    echo "⚠️  No new products imported.\n";
}

echo "\n✅ Import process complete!\n\n";

<?php
/**
 * Import Faber-Castel Products from CSV
 *
 * Imports products with proper image handling
 * - Products set to DISABLED by default
 * - Images already processed and in media directory
 * - Proper attribute set assignment
 *
 * Usage: php import-faber-products.php [--dry-run]
 */

use Magento\Framework\App\Bootstrap;

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require __DIR__ . '/../app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

$state = $objectManager->get(\Magento\Framework\App\State::class);
try {
    $state->setAreaCode('adminhtml');
} catch (\Exception $e) {
    // Area code already set
}

ini_set('memory_limit', '512M');
set_time_limit(300);

echo "\n╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║         FABER-CASTEL PRODUCTS IMPORT                                       ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n";
echo "📅 Date: " . date('Y-m-d H:i:s') . "\n\n";

$dryRun = in_array('--dry-run', $argv);

if ($dryRun) {
    echo "🔍 DRY RUN MODE - No changes will be made\n\n";
}

$resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
$connection = $resource->getConnection();

$csvFile = __DIR__ . '/canvas - canva faber castel.csv';
$mediaDir = __DIR__ . '/../../pub/media/catalog/product';

echo "📂 CSV File: $csvFile\n";
echo "🖼️  Media Dir: $mediaDir\n\n";

if (!file_exists($csvFile)) {
    echo "❌ ERROR: CSV file not found!\n";
    exit(1);
}

// Parse CSV
echo "📂 Parsing CSV...\n";
$content = file_get_contents($csvFile);
$content = str_replace(["\r\n", "\r"], "\n", $content);
$tempFile = tempnam(sys_get_temp_dir(), 'csv_');
file_put_contents($tempFile, $content);

$lines = [];
if (($handle = fopen($tempFile, 'r')) !== false) {
    while (($data = fgetcsv($handle, 0, ',', '"')) !== false) {
        $lines[] = $data;
    }
    fclose($handle);
}
unlink($tempFile);

$header = array_shift($lines);
echo "📊 Total products in CSV: " . count($lines) . "\n";
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
    'image' => 'varchar',
    'small_image' => 'varchar',
    'thumbnail' => 'varchar',
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

// Get media gallery attribute ID
$mediaGalleryAttrId = $connection->fetchOne(
    "SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'media_gallery' AND entity_type_id = 4"
);
echo "  ✓ media_gallery: ID=$mediaGalleryAttrId\n";

// Get attribute set ID
$attributeSetId = $connection->fetchOne(
    "SELECT attribute_set_id FROM eav_attribute_set WHERE attribute_set_name = 'Products' AND entity_type_id = 4"
);
echo "\n  ✓ Attribute Set 'Products': ID=$attributeSetId\n";
echo "\n";

echo "═══════════════════════════════════════════════════════════════════════════\n";
echo "IMPORTING PRODUCTS\n";
echo "═══════════════════════════════════════════════════════════════════════════\n\n";

$imported = 0;
$skipped = 0;
$errors = 0;
$failedSkus = [];

foreach ($lines as $lineNum => $data) {
    if (!is_array($data) || count($data) < count($header)) {
        echo "⚠️  Line " . ($lineNum + 2) . ": Invalid data (skipped)\n";
        $skipped++;
        continue;
    }

    $rowData = array_combine($header, $data);

    $sku = trim($rowData['sku']);
    $ref = trim($rowData['ref']);
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
    $imageName = trim($rowData['Image Name']);
    $additionalAttributes = trim($rowData['additional_attributes']);

    if (empty($sku)) {
        echo "⚠️  Line " . ($lineNum + 2) . ": No SKU (skipped)\n";
        $skipped++;
        continue;
    }

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
        $hasImage = false;
        if (!empty($ref)) {
            $skuFirst = substr($sku, 0, 2);
            $skuSecond = substr($sku, 2, 2);
            $imagePath = "$mediaDir/$skuFirst/$skuSecond/$ref.jpg";
            $hasImage = file_exists($imagePath);
        }
        echo "✅ $sku: Would be imported (Disabled, Image: " . ($hasImage ? '✓' : '✗') . ")\n";
        $imported++;
        continue;
    }

    try {
        // Get attribute set ID
        $productAttributeSetId = $connection->fetchOne(
            "SELECT attribute_set_id FROM eav_attribute_set 
             WHERE attribute_set_name = ? AND entity_type_id = 4",
            [$attributeSetCode]
        );
        
        if (!$productAttributeSetId) {
            $productAttributeSetId = $attributeSetId;
        }

        // Get website ID
        $websiteSelect = $connection->select()
            ->from($resource->getTableName('store_website'), ['website_id'])
            ->where('code = ?', $productWebsites);
        $websiteId = $connection->fetchOne($websiteSelect);

        if (!$websiteId) {
            $websiteId = 1;
        }

        // Get tax class ID
        $taxClassSelect = $connection->select()
            ->from($resource->getTableName('tax_class'), ['class_id'])
            ->where('class_name = ?', $taxClass)
            ->where('class_type = ?', 'PRODUCT');
        $taxClassId = $connection->fetchOne($taxClassSelect);

        if (!$taxClassId) {
            $taxClassId = 2;
        }

        // Map visibility
        $visibilityMap = [
            'not_visible' => 1,
            'catalog' => 2,
            'search' => 3,
            'catalog_search' => 4,
        ];
        $visibilityId = $visibilityMap[$visibility] ?? 4;

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

        // Insert product name
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

        // Handle product image
        if (!empty($ref)) {
            $skuFirst = substr($sku, 0, 2);
            $skuSecond = substr($sku, 2, 2);
            $imagePath = "/$skuFirst/$skuSecond/$ref.jpg";
            $fullImagePath = $mediaDir . $imagePath;

            if (file_exists($fullImagePath)) {
                // Set base image attributes
                if ($attributeIds['image']) {
                    $connection->insert(
                        $resource->getTableName('catalog_product_entity_varchar'),
                        [
                            'attribute_id' => $attributeIds['image'],
                            'store_id' => 0,
                            'entity_id' => $productId,
                            'value' => $imagePath
                        ]
                    );
                }

                if ($attributeIds['small_image']) {
                    $connection->insert(
                        $resource->getTableName('catalog_product_entity_varchar'),
                        [
                            'attribute_id' => $attributeIds['small_image'],
                            'store_id' => 0,
                            'entity_id' => $productId,
                            'value' => $imagePath
                        ]
                    );
                }

                if ($attributeIds['thumbnail']) {
                    $connection->insert(
                        $resource->getTableName('catalog_product_entity_varchar'),
                        [
                            'attribute_id' => $attributeIds['thumbnail'],
                            'store_id' => 0,
                            'entity_id' => $productId,
                            'value' => $imagePath
                        ]
                    );
                }

                // Add to media gallery
                if ($mediaGalleryAttrId) {
                    $galleryRecord = [
                        'attribute_id' => $mediaGalleryAttrId,
                        'store_id' => 0,
                        'entity_id' => $productId,
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
                        "INSERT INTO catalog_product_entity_media_gallery_value 
                         (value_id, store_id, entity_id, disabled, position, record_id)
                         VALUES (?, 0, ?, 0, 1, ?)
                         ON DUPLICATE KEY UPDATE disabled = 0, position = 1",
                        [$valueId, $productId, $valueId]
                    );
                }

                echo "✅ $sku: Imported (ID: $productId, Image: ✓)\n";
            } else {
                echo "✅ $sku: Imported (ID: $productId, Image: ✗ not found)\n";
            }
        } else {
            echo "✅ $sku: Imported (ID: $productId, No ref)\n";
        }

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

<?php
/**
 * Assign Imported STABILO Products to Categories
 *
 * Maps CSV category names to actual Magento categories
 */

require __DIR__ . '/app/bootstrap.php';

$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
$connection = $resource->getConnection();

echo "\n╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║           ASSIGN STABILO PRODUCTS TO CATEGORIES                            ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n";
echo "📅 Date: " . date('Y-m-d H:i:s') . "\n\n";

// Category mapping: CSV name => Magento category ID
$categoryMap = [
    'Ecriture, Correction & Coloria' => [770], // ECRITURE & COLORIAGE
];

// Default categories to assign if no match
$defaultCategories = [
    2170, // Produits
    3,    // Tous les produits
];

// Get newly imported STABILO products
$productIds = $connection->fetchCol(
    "SELECT entity_id FROM catalog_product_entity 
     WHERE sku IN ('1140665941', '1140665939', '1140665943', '1140665940', '1140665942', 
                   '1140658097', '1140641680', '1140641679', '1140641675', '1140641674')"
);

echo "Found " . count($productIds) . " STABILO products to categorize\n\n";

$assigned = 0;
$skipped = 0;

foreach ($productIds as $productId) {
    $sku = $connection->fetchOne("SELECT sku FROM catalog_product_entity WHERE entity_id = ?", [$productId]);
    
    // Get current categories for this product
    $existingCategories = $connection->fetchCol(
        "SELECT category_id FROM catalog_category_product WHERE product_id = ?",
        [$productId]
    );
    
    // Categories to assign
    $categoriesToAssign = array_merge($defaultCategories, $categoryMap['Ecriture, Correction & Coloria'] ?? []);
    $categoriesToAssign = array_unique($categoriesToAssign);
    
    foreach ($categoriesToAssign as $categoryId) {
        // Check if already assigned
        if (in_array($categoryId, $existingCategories)) {
            $skipped++;
            continue;
        }
        
        // Check if category exists
        $categoryExists = $connection->fetchOne(
            "SELECT COUNT(*) FROM catalog_category_entity WHERE entity_id = ?",
            [$categoryId]
        );
        
        if (!$categoryExists) {
            continue;
        }
        
        // Assign product to category
        $connection->insert(
            $resource->getTableName('catalog_category_product'),
            [
                'category_id' => $categoryId,
                'product_id' => $productId,
                'position' => 0
            ]
        );
        
        $assigned++;
    }
    
    echo "✅ $sku: Assigned to categories\n";
}

echo "\n═══════════════════════════════════════════════════════════════════════════\n";
echo "SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════════════════\n";
echo "✅ Category assignments created: $assigned\n";
echo "⚠️  Skipped (already assigned): $skipped\n\n";

echo "✅ Category assignment complete!\n\n";

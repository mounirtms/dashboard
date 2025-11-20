<?php
use Magento\Framework\App\Bootstrap;
use Magento\Framework\ObjectManagerInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;

require __DIR__ . '/../../app/bootstrap.php';

$params = $_SERVER;
$bootstrap = Bootstrap::create(BP, $params);
/** @var ObjectManagerInterface $objectManager */
$objectManager = $bootstrap->getObjectManager();

/** @var State $appState */
$appState = $objectManager->get(State::class);
try {
    $appState->setAreaCode(Area::AREA_ADMINHTML);
} catch (\Magento\Framework\App\StateException $e) {
    // Area already set, continue
}

/** @var ProductCollectionFactory $collectionFactory */
$collectionFactory = $objectManager->get(ProductCollectionFactory::class);
/** @var ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->get(ProductRepositoryInterface::class);

$resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
$connection = $resource->getConnection();

// Resolve attribute IDs via Magento EAV config (more reliable than raw SQL)
/** @var \Magento\Eav\Model\Config $eavConfig */
$eavConfig = $objectManager->get(\Magento\Eav\Model\Config::class);
$statusAttr = $eavConfig->getAttribute('catalog_product', 'status');
if (!$statusAttr || !$statusAttr->getAttributeId()) {
    fwrite(STDERR, "Unable to determine status attribute id via EAV config. Aborting.\n");
    exit(1);
}
$statusAttrId = (int)$statusAttr->getAttributeId();

$batchSize = 200;
$totalProcessed = 0;
$totalDisabled = 0;

$startTime = microtime(true);

// Build base collection: products with missing main image attributes or set to 'no_selection'
$collection = $collectionFactory->create();
$collection->addAttributeToSelect(['name','sku','status','image','small_image','thumbnail']);
$collection->addAttributeToFilter([
    ['attribute' => 'image', 'null' => true],
    ['attribute' => 'image', 'eq' => 'no_selection']
], null, 'left');

$collection->setPageSize($batchSize);
$lastPage = $collection->getLastPageNumber();

$mediaGalleryValueToEntity = $connection->getTableName('catalog_product_entity_media_gallery_value_to_entity');

for ($page = 1; $page <= $lastPage; $page++) {
    $collection->setCurPage($page);
    $collection->load();

    $ids = $collection->getAllIds();
    if (!$ids) {
        $collection->clear();
        continue;
    }

    // Filter out products that actually have any media gallery entry
    $select = $connection->select()
        ->from($mediaGalleryValueToEntity, ['entity_id'])
        ->where('entity_id IN (?)', $ids)
        ->group('entity_id');
    $entitiesWithGallery = $connection->fetchCol($select);
    $entitiesWithGallery = array_flip($entitiesWithGallery ?: []);

    foreach ($collection as $product) {
        $totalProcessed++;
        $id = (int)$product->getId();
        if (isset($entitiesWithGallery[$id])) {
            continue; // has gallery, skip
        }

        // Only disable if not already disabled
        if ((int)$product->getStatus() !== Status::STATUS_DISABLED) {
            $product->setStatus(Status::STATUS_DISABLED);
            try {
                $productRepository->save($product);
                $totalDisabled++;
                echo sprintf("Disabled product ID %d (SKU: %s) due to no image.\n", $id, $product->getSku());
            } catch (\Throwable $t) {
                fwrite(STDERR, sprintf("Failed to disable product ID %d: %s\n", $id, $t->getMessage()));
            }
        }
    }

    $collection->clear();
}

$duration = microtime(true) - $startTime;
echo sprintf("Done. Processed: %d, Disabled: %d, Time: %.2fs\n", $totalProcessed, $totalDisabled, $duration);

// Optional: trigger partial reindex programmatically? Prefer CLI after script.

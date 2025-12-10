<?php
/**
 * Script to revert products that were enabled via script
 * 
 * This script identifies and disables products that were likely enabled
 * via a bulk script operation and reindexes the catalog afterward.
 */

use Magento\Framework\App\Bootstrap;
use Magento\Framework\AppInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\State;

require dirname(__DIR__) . '/../app/bootstrap.php';

class RevertEnabledProducts implements AppInterface
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var State
     */
    private $state;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    private $productCollectionFactory;

    /**
     * @var \Magento\Indexer\Model\IndexerFactory
     */
    private $indexerFactory;

    /**
     * Constructor
     *
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     */
    public function __construct($objectManager)
    {
        $this->objectManager = $objectManager;
        $this->productRepository = $objectManager->get(ProductRepositoryInterface::class);
        $this->state = $objectManager->get(State::class);
        $this->productCollectionFactory = $objectManager->get(\Magento\Catalog\Model\ResourceModel\Product\CollectionFactory::class);
        $this->indexerFactory = $objectManager->get(\Magento\Indexer\Model\IndexerFactory::class);
    }

    /**
     * Run the script
     *
     * @return void
     * @throws \Exception
     */
    public function launch()
    {
        try {
            $this->state->setAreaCode('adminhtml');
            
            echo "Starting product status revert process...\n";
            
            // Get products updated on November 16, 2025 that are currently enabled
            $productCollection = $this->productCollectionFactory->create();
            $productCollection->addAttributeToSelect(['sku', 'status'])
                ->addAttributeToFilter('status', ['eq' => 1]) // Enabled products
                ->addFieldToFilter('updated_at', ['like' => '2025-11-16%']);
                
            echo "Found " . $productCollection->count() . " products updated on November 16, 2025 that are currently enabled.\n";
            
            $disabledCount = 0;
            
            foreach ($productCollection as $product) {
                try {
                    $product = $this->productRepository->getById($product->getId());
                    $product->setStatus(2); // Disable product
                    
                    $this->productRepository->save($product);
                    echo "Disabled product SKU: {$product->getSku()} (ID: {$product->getId()})\n";
                    $disabledCount++;
                } catch (\Exception $e) {
                    echo "Error disabling product SKU: {$product->getSku()} - " . $e->getMessage() . "\n";
                }
            }
            
            echo "Successfully disabled {$disabledCount} products.\n";
            
            // Check if SKU 1740504 is still enabled, and if so, disable it as well
            try {
                $targetProduct = $this->productRepository->get('1740504');
                if ($targetProduct->getStatus() == 1) {
                    $targetProduct->setStatus(2); // Disable
                    $this->productRepository->save($targetProduct);
                    echo "Also disabled target product SKU: 1740504\n";
                } else {
                    echo "Target product SKU: 1740504 is already disabled.\n";
                }
            } catch (NoSuchEntityException $e) {
                echo "Target product SKU: 1740504 not found.\n";
            }
            
            // Reindex catalog_product_flat and catalog_category_product
            echo "Reindexing catalog_product_flat and catalog_category_product...\n";
            
            try {
                $indexer = $this->indexerFactory->create();
                $indexer->load('catalog_product_flat');
                $indexer->reindexAll();
                echo "Reindexed catalog_product_flat successfully.\n";
            } catch (\Exception $e) {
                echo "Error reindexing catalog_product_flat: " . $e->getMessage() . "\n";
            }
            
            try {
                $indexer = $this->indexerFactory->create();
                $indexer->load('catalog_category_product');
                $indexer->reindexAll();
                echo "Reindexed catalog_category_product successfully.\n";
            } catch (\Exception $e) {
                echo "Error reindexing catalog_category_product: " . $e->getMessage() . "\n";
            }
            
            echo "Product revert process completed.\n";
            
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
        
        return 0;
    }

    /**
     * Catch all exceptions
     *
     * @param \Magento\Framework\App\Bootstrap $bootstrap
     * @param \Exception $exception
     * @return void
     */
    public function catchException(Bootstrap $bootstrap, \Exception $exception)
    {
        echo "Exception: " . $exception->getMessage() . "\n";
        return false;
    }
}

// Bootstrap and run
$bootstrap = Bootstrap::create(BP, $_SERVER);
$objManager = $bootstrap->getObjectManager();
$app = $objManager->create(RevertEnabledProducts::class, ['objectManager' => $objManager]);
$bootstrap->run($app);
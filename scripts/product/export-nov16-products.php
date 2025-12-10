<?php
/**
 * Script to export products that were updated on November 16th 2025
 * with their current status
 */

use Magento\Framework\App\Bootstrap;
use Magento\Framework\AppInterface;
use Magento\Framework\App\State;

require dirname(__DIR__) . '/../app/bootstrap.php';

class ExportNov16Products implements AppInterface
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var State
     */
    private $state;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    private $productCollectionFactory;

    /**
     * Constructor
     *
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     */
    public function __construct($objectManager)
    {
        $this->objectManager = $objectManager;
        $this->state = $objectManager->get(State::class);
        $this->productCollectionFactory = $objectManager->get(\Magento\Catalog\Model\ResourceModel\Product\CollectionFactory::class);
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
            
            echo "Exporting products updated on November 16th, 2025...\n";
            
            // Get products updated on November 16, 2025
            $productCollection = $this->productCollectionFactory->create();
            $productCollection->addAttributeToSelect(['sku', 'status', 'name'])
                ->addFieldToFilter('updated_at', ['like' => '2025-11-16%']);
                
            echo "Found " . $productCollection->count() . " products updated on November 16, 2025.\n";
            
            // Output CSV header
            echo "SKU,Name,Status,StatusText\n";
            
            foreach ($productCollection as $product) {
                $statusText = ($product->getStatus() == 1) ? 'Enabled' : 'Disabled';
                echo "\"{$product->getSku()}\",\"{$product->getName()}\",{$product->getStatus()},{$statusText}\n";
            }
            
            echo "Export completed.\n";
            
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
$app = $objManager->create(ExportNov16Products::class, ['objectManager' => $objManager]);
$bootstrap->run($app);
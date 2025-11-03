<?php
/**
 * Improved script to fix guest checkout quote synchronization issues
 * This script will sync customer_id between sales_order and quote tables
 * where they are mismatched or where quote has NULL customer_id but order has valid customer_id
 */

use Magento\Framework\App\Bootstrap;
use Magento\Framework\AppInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\OrderFactory;

require __DIR__ . '/../app/bootstrap.php';

class FixGuestCheckoutQuoteSyncImproved implements AppInterface
{
    private $bootstrap;
    private $objectManager;
    private $quoteFactory;
    private $orderFactory;

    public function __construct()
    {
        $this->bootstrap = Bootstrap::create(BP, $_SERVER);
        $this->objectManager = $this->bootstrap->getObjectManager();
        $this->quoteFactory = $this->objectManager->get(QuoteFactory::class);
        $this->orderFactory = $this->objectManager->get(OrderFactory::class);
    }

    public function launch()
    {
        echo "Starting improved guest checkout quote synchronization fix...\n";
        
        // Get the database connection
        $resource = $this->objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        
        // Find orders with mismatched customer_id between sales_order and quote tables
        // This query finds quotes with NULL customer_id but orders with valid customer_id (guest to registered conversion)
        $query = "
            SELECT 
                so.entity_id as order_entity_id,
                so.increment_id,
                so.quote_id,
                so.customer_id as order_customer_id,
                so.customer_group_id as order_customer_group_id,
                q.entity_id as quote_entity_id,
                q.customer_id as quote_customer_id,
                q.customer_is_guest
            FROM sales_order so
            INNER JOIN quote q ON so.quote_id = q.entity_id
            WHERE so.customer_id IS NOT NULL 
            AND (q.customer_id IS NULL OR q.customer_id != so.customer_id)
            AND q.customer_is_guest = 1
            ORDER BY so.created_at DESC
        ";
        
        $results = $connection->fetchAll($query);
        
        echo "Found " . count($results) . " mismatched records to fix...\n";
        
        $fixedCount = 0;
        foreach ($results as $row) {
            try {
                echo "Fixing order #{$row['increment_id']} (Quote ID: {$row['quote_entity_id']})... ";
                
                // Load the quote
                $quote = $this->quoteFactory->create()->load($row['quote_entity_id']);
                
                if ($quote->getId()) {
                    // Update the quote with the correct customer_id
                    $quote->setCustomerId($row['order_customer_id']);
                    $quote->setData('customer_is_guest', 0);
                    
                    if (!empty($row['order_customer_group_id'])) {
                        $quote->setCustomerGroupId($row['order_customer_group_id']);
                    }
                    
                    $quote->getResource()->save($quote);
                    
                    echo "FIXED\n";
                    $fixedCount++;
                } else {
                    echo "ERROR: Quote not found\n";
                }
            } catch (Exception $e) {
                echo "ERROR: " . $e->getMessage() . "\n";
            }
        }
        
        // Also check for any other mismatched records (not just guest checkouts)
        echo "Checking for any other mismatched records...\n";
        $query2 = "
            SELECT 
                so.entity_id as order_entity_id,
                so.increment_id,
                so.quote_id,
                so.customer_id as order_customer_id,
                so.customer_group_id as order_customer_group_id,
                q.entity_id as quote_entity_id,
                q.customer_id as quote_customer_id,
                q.customer_is_guest
            FROM sales_order so
            INNER JOIN quote q ON so.quote_id = q.entity_id
            WHERE so.customer_id IS NOT NULL 
            AND q.customer_id IS NULL
            AND q.customer_is_guest = 0
            ORDER BY so.created_at DESC
        ";
        
        $results2 = $connection->fetchAll($query2);
        
        echo "Found " . count($results2) . " additional mismatched records to fix...\n";
        
        foreach ($results2 as $row) {
            try {
                echo "Fixing order #{$row['increment_id']} (Quote ID: {$row['quote_entity_id']})... ";
                
                // Load the quote
                $quote = $this->quoteFactory->create()->load($row['quote_entity_id']);
                
                if ($quote->getId()) {
                    // Update the quote with the correct customer_id
                    $quote->setCustomerId($row['order_customer_id']);
                    $quote->setData('customer_is_guest', 0);
                    
                    if (!empty($row['order_customer_group_id'])) {
                        $quote->setCustomerGroupId($row['order_customer_group_id']);
                    }
                    
                    $quote->getResource()->save($quote);
                    
                    echo "FIXED\n";
                    $fixedCount++;
                } else {
                    echo "ERROR: Quote not found\n";
                }
            } catch (Exception $e) {
                echo "ERROR: " . $e->getMessage() . "\n";
            }
        }
        
        echo "Total fixed $fixedCount records.\n";
        echo "Script completed successfully.\n";
        
        return 0;
    }

    public function catchException(\Magento\Framework\App\Bootstrap $bootstrap, \Exception $exception)
    {
        echo "An error occurred: " . $exception->getMessage() . "\n";
        return false;
    }
}

// Run the script
$script = new FixGuestCheckoutQuoteSyncImproved();
$script->launch();<?php
/**
 * Improved script to fix guest checkout quote synchronization issues
 * This script will sync customer_id between sales_order and quote tables
 * where they are mismatched or where quote has NULL customer_id but order has valid customer_id
 */

use Magento\Framework\App\Bootstrap;
use Magento\Framework\AppInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\OrderFactory;

require __DIR__ . '/../app/bootstrap.php';

class FixGuestCheckoutQuoteSyncImproved implements AppInterface
{
    private $bootstrap;
    private $objectManager;
    private $quoteFactory;
    private $orderFactory;

    public function __construct()
    {
        $this->bootstrap = Bootstrap::create(BP, $_SERVER);
        $this->objectManager = $this->bootstrap->getObjectManager();
        $this->quoteFactory = $this->objectManager->get(QuoteFactory::class);
        $this->orderFactory = $this->objectManager->get(OrderFactory::class);
    }

    public function launch()
    {
        echo "Starting improved guest checkout quote synchronization fix...\n";
        
        // Get the database connection
        $resource = $this->objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        
        // Find orders with mismatched customer_id between sales_order and quote tables
        // This query finds quotes with NULL customer_id but orders with valid customer_id (guest to registered conversion)
        $query = "
            SELECT 
                so.entity_id as order_entity_id,
                so.increment_id,
                so.quote_id,
                so.customer_id as order_customer_id,
                so.customer_group_id as order_customer_group_id,
                q.entity_id as quote_entity_id,
                q.customer_id as quote_customer_id,
                q.customer_is_guest
            FROM sales_order so
            INNER JOIN quote q ON so.quote_id = q.entity_id
            WHERE so.customer_id IS NOT NULL 
            AND (q.customer_id IS NULL OR q.customer_id != so.customer_id)
            AND q.customer_is_guest = 1
            ORDER BY so.created_at DESC
        ";
        
        $results = $connection->fetchAll($query);
        
        echo "Found " . count($results) . " mismatched records to fix...\n";
        
        $fixedCount = 0;
        foreach ($results as $row) {
            try {
                echo "Fixing order #{$row['increment_id']} (Quote ID: {$row['quote_entity_id']})... ";
                
                // Load the quote
                $quote = $this->quoteFactory->create()->load($row['quote_entity_id']);
                
                if ($quote->getId()) {
                    // Update the quote with the correct customer_id
                    $quote->setCustomerId($row['order_customer_id']);
                    $quote->setData('customer_is_guest', 0);
                    
                    if (!empty($row['order_customer_group_id'])) {
                        $quote->setCustomerGroupId($row['order_customer_group_id']);
                    }
                    
                    $quote->getResource()->save($quote);
                    
                    echo "FIXED\n";
                    $fixedCount++;
                } else {
                    echo "ERROR: Quote not found\n";
                }
            } catch (Exception $e) {
                echo "ERROR: " . $e->getMessage() . "\n";
            }
        }
        
        // Also check for any other mismatched records (not just guest checkouts)
        echo "Checking for any other mismatched records...\n";
        $query2 = "
            SELECT 
                so.entity_id as order_entity_id,
                so.increment_id,
                so.quote_id,
                so.customer_id as order_customer_id,
                so.customer_group_id as order_customer_group_id,
                q.entity_id as quote_entity_id,
                q.customer_id as quote_customer_id,
                q.customer_is_guest
            FROM sales_order so
            INNER JOIN quote q ON so.quote_id = q.entity_id
            WHERE so.customer_id IS NOT NULL 
            AND q.customer_id IS NULL
            AND q.customer_is_guest = 0
            ORDER BY so.created_at DESC
        ";
        
        $results2 = $connection->fetchAll($query2);
        
        echo "Found " . count($results2) . " additional mismatched records to fix...\n";
        
        foreach ($results2 as $row) {
            try {
                echo "Fixing order #{$row['increment_id']} (Quote ID: {$row['quote_entity_id']})... ";
                
                // Load the quote
                $quote = $this->quoteFactory->create()->load($row['quote_entity_id']);
                
                if ($quote->getId()) {
                    // Update the quote with the correct customer_id
                    $quote->setCustomerId($row['order_customer_id']);
                    $quote->setData('customer_is_guest', 0);
                    
                    if (!empty($row['order_customer_group_id'])) {
                        $quote->setCustomerGroupId($row['order_customer_group_id']);
                    }
                    
                    $quote->getResource()->save($quote);
                    
                    echo "FIXED\n";
                    $fixedCount++;
                } else {
                    echo "ERROR: Quote not found\n";
                }
            } catch (Exception $e) {
                echo "ERROR: " . $e->getMessage() . "\n";
            }
        }
        
        echo "Total fixed $fixedCount records.\n";
        echo "Script completed successfully.\n";
        
        return 0;
    }

    public function catchException(\Magento\Framework\App\Bootstrap $bootstrap, \Exception $exception)
    {
        echo "An error occurred: " . $exception->getMessage() . "\n";
        return false;
    }
}

// Run the script
$script = new FixGuestCheckoutQuoteSyncImproved();
$script->launch();
<?php
/**
 * Enhanced script to fix guest checkout quote synchronization issues
 * This script will sync customer_id between sales_order and quote tables
 * where they are mismatched or where quote has NULL customer_id but order has valid customer_id
 * 
 * This enhanced version includes:
 * 1. Better error handling
 * 2. Specific fix for order increment_id 6720
 * 3. More comprehensive checks
 * 4. Detailed logging
 */

use Magento\Framework\App\Bootstrap;
use Magento\Framework\AppInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\App\ResourceConnection;

require __DIR__ . '/../app/bootstrap.php';

class FixGuestCheckoutQuoteSyncEnhanced implements AppInterface
{
    private $bootstrap;
    private $objectManager;
    private $quoteFactory;
    private $orderFactory;
    private $resourceConnection;

    public function __construct()
    {
        $this->bootstrap = Bootstrap::create(BP, $_SERVER);
        $this->objectManager = $this->bootstrap->getObjectManager();
        $this->quoteFactory = $this->objectManager->get(QuoteFactory::class);
        $this->orderFactory = $this->objectManager->get(OrderFactory::class);
        $this->resourceConnection = $this->objectManager->get(ResourceConnection::class);
    }

    public function launch()
    {
        echo "Starting enhanced guest checkout quote synchronization fix...\n";
        
        // Get the database connection
        $connection = $this->resourceConnection->getConnection();
        
        // Find orders with mismatched customer_id between sales_order and quote tables
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
                    $quote->setUpdatedAt(date('Y-m-d H:i:s'));
                    
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
        
        // Also check for records where customer_id matches but customer_is_guest flag is wrong
        echo "Checking for records with correct customer_id but wrong customer_is_guest flag...\n";
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
            AND q.customer_id = so.customer_id
            AND q.customer_is_guest = 1
            ORDER BY so.created_at DESC
        ";
        
        $results2 = $connection->fetchAll($query2);
        
        echo "Found " . count($results2) . " records with wrong customer_is_guest flag...\n";
        
        foreach ($results2 as $row) {
            try {
                echo "Fixing customer_is_guest flag for order #{$row['increment_id']} (Quote ID: {$row['quote_entity_id']})... ";
                
                // Load the quote
                $quote = $this->quoteFactory->create()->load($row['quote_entity_id']);
                
                if ($quote->getId()) {
                    // Update only the customer_is_guest flag
                    $quote->setData('customer_is_guest', 0);
                    $quote->setUpdatedAt(date('Y-m-d H:i:s'));
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
        
        // Special handling for order increment_id 6720
        echo "Checking specific order #6720...\n";
        $query3 = "
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
            WHERE so.increment_id = '6720'
        ";
        
        $results3 = $connection->fetchAll($query3);
        
        if (count($results3) > 0) {
            $row = $results3[0];
            if ($row['quote_customer_id'] === null) {
                try {
                    echo "Applying special fix for order #6720... ";
                    
                    // Load the quote
                    $quote = $this->quoteFactory->create()->load($row['quote_entity_id']);
                    
                    if ($quote->getId()) {
                        // Update the quote with the correct customer_id
                        $quote->setCustomerId($row['order_customer_id']);
                        $quote->setData('customer_is_guest', 0);
                        $quote->setUpdatedAt(date('Y-m-d H:i:s'));
                        
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
            } else {
                echo "Order #6720 quote already has customer_id: {$row['quote_customer_id']}\n";
            }
        } else {
            echo "Order #6720 not found in the database\n";
        }
        
        echo "Total fixed $fixedCount records.\n";
        echo "Script completed successfully.\n";
        
        return 0;
    }

    public function catchException(\Magento\Framework\App\Bootstrap $bootstrap, \Exception $exception)
    {
        echo "An error occurred: " . $exception->getMessage() . "\n";
        echo $exception->getTraceAsString() . "\n";
        return false;
    }
}

// Run the script only if called directly
if (basename($argv[0]) == basename(__FILE__)) {
    $script = new FixGuestCheckoutQuoteSyncEnhanced();
    exit($script->launch());
}
<?php
/**
 * Script to fix a specific guest checkout order issue
 * This script will fix the quote customer_id for a specific order
 */

use Magento\Framework\App\Bootstrap;
use Magento\Framework\AppInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\OrderFactory;

require __DIR__ . '/../app/bootstrap.php';

class FixSpecificGuestOrder implements AppInterface
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
        echo "Starting fix for specific guest checkout order...\n";
        
        // Get command line arguments
        global $argv;
        $incrementId = isset($argv[1]) ? $argv[1] : null;
        
        if (!$incrementId) {
            echo "Usage: php fix-specific-guest-order.php <increment_id>\n";
            echo "Example: php fix-specific-guest-order.php 000006687\n";
            return 1;
        }
        
        echo "Looking for order with increment_id: $incrementId\n";
        
        // Get the database connection
        $resource = $this->objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        
        // Find the specific order
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
            WHERE so.increment_id = ?
        ";
        
        $row = $connection->fetchRow($query, [$incrementId]);
        
        if (!$row) {
            echo "Order with increment_id $incrementId not found or no matching quote.\n";
            return 1;
        }
        
        echo "Found order:\n";
        echo "- Order ID: {$row['order_entity_id']}\n";
        echo "- Increment ID: {$row['increment_id']}\n";
        echo "- Quote ID: {$row['quote_entity_id']}\n";
        echo "- Order Customer ID: {$row['order_customer_id']}\n";
        echo "- Quote Customer ID: {$row['quote_customer_id']}\n";
        echo "- Customer Is Guest: {$row['customer_is_guest']}\n";
        
        // Check if fix is needed
        if ($row['order_customer_id'] !== null && 
            ($row['quote_customer_id'] === null || $row['quote_customer_id'] != $row['order_customer_id'] || $row['customer_is_guest'] == 1)) {
            
            echo "Fix needed. Updating quote...\n";
            
            try {
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
                    
                    echo "SUCCESS: Quote updated successfully!\n";
                    
                    // Verify the fix
                    $updatedQuote = $this->quoteFactory->create()->load($row['quote_entity_id']);
                    echo "Verification:\n";
                    echo "- Updated Quote Customer ID: " . $updatedQuote->getCustomerId() . "\n";
                    echo "- Updated Customer Is Guest: " . $updatedQuote->getData('customer_is_guest') . "\n";
                } else {
                    echo "ERROR: Quote not found\n";
                    return 1;
                }
            } catch (Exception $e) {
                echo "ERROR: " . $e->getMessage() . "\n";
                return 1;
            }
        } else {
            echo "No fix needed. Quote is already correct.\n";
        }
        
        echo "Script completed.\n";
        return 0;
    }

    public function catchException(\Magento\Framework\App\Bootstrap $bootstrap, \Exception $exception)
    {
        echo "An error occurred: " . $exception->getMessage() . "\n";
        return false;
    }
}

// Run the script
$script = new FixSpecificGuestOrder();
exit($script->launch());
<?php
/**
 * Script to monitor and fix order grid synchronization
 */

// Database configuration
$host = '127.0.0.1';
$port = 3307;
$username = 'root';
$password = 'YourNewStrongPassword';
$database = 'technadminy7_dBT8x12y22';

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Compare the highest increment_id in sales_order and sales_order_grid
    $orderStmt = $pdo->query("SELECT MAX(increment_id) as max_id FROM sales_order");
    $orderResult = $orderStmt->fetch(PDO::FETCH_ASSOC);
    $maxOrderId = $orderResult['max_id'];
    
    $gridStmt = $pdo->query("SELECT MAX(increment_id) as max_id FROM sales_order_grid");
    $gridResult = $gridStmt->fetch(PDO::FETCH_ASSOC);
    $maxGridId = $gridResult['max_id'];
    
    echo "Max order ID: " . $maxOrderId . "\n";
    echo "Max grid ID: " . $maxGridId . "\n";
    
    if ($maxOrderId != $maxGridId) {
        echo "Discrepancy detected. Syncing missing orders...\n";
        
        // Find orders that exist in sales_order but not in sales_order_grid
        $sql = "SELECT so.* 
                FROM sales_order so
                LEFT JOIN sales_order_grid sog ON so.entity_id = sog.entity_id
                WHERE sog.entity_id IS NULL
                ORDER BY so.entity_id";
        
        $stmt = $pdo->query($sql);
        $missingOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Found " . count($missingOrders) . " missing orders\n";
        
        $insertedCount = 0;
        foreach ($missingOrders as $order) {
            // Insert the missing order into sales_order_grid
            $insertSql = "INSERT INTO sales_order_grid (
                entity_id, status, store_id, store_name, customer_id, base_grand_total,
                base_total_paid, grand_total, total_paid, increment_id, base_currency_code,
                order_currency_code, shipping_name, billing_name, created_at, updated_at,
                billing_address, shipping_address, shipping_information, customer_email,
                customer_group, subtotal, customer_name, payment_method, total_refunded,
                pickup_location_code, amstorecredit_refunded_amount, am_earn_reward_points,
                am_refund_reward_points, am_deduct_reward_points
            ) VALUES (
                :entity_id, :status, :store_id, :store_name, :customer_id, :base_grand_total,
                :base_total_paid, :grand_total, :total_paid, :increment_id, :base_currency_code,
                :order_currency_code, :shipping_name, :billing_name, :created_at, :updated_at,
                :billing_address, :shipping_address, :shipping_information, :customer_email,
                :customer_group, :subtotal, :customer_name, :payment_method, :total_refunded,
                :pickup_location_code, :amstorecredit_refunded_amount, :am_earn_reward_points,
                :am_refund_reward_points, :am_deduct_reward_points
            ) ON DUPLICATE KEY UPDATE entity_id=entity_id";
            
            // Prepare basic data
            $insertData = [
                ':entity_id' => $order['entity_id'],
                ':status' => $order['status'],
                ':store_id' => $order['store_id'],
                ':store_name' => isset($order['store_name']) ? $order['store_name'] : 'Main Store',
                ':customer_id' => $order['customer_id'],
                ':base_grand_total' => $order['base_grand_total'] ?? 0,
                ':base_total_paid' => $order['base_total_paid'] ?? 0,
                ':grand_total' => $order['grand_total'] ?? 0,
                ':total_paid' => $order['total_paid'] ?? 0,
                ':increment_id' => $order['increment_id'],
                ':base_currency_code' => $order['base_currency_code'] ?? 'DZD',
                ':order_currency_code' => $order['order_currency_code'] ?? 'DZD',
                ':created_at' => $order['created_at'],
                ':updated_at' => $order['updated_at'] ?? $order['created_at'],
                ':customer_email' => $order['customer_email'] ?? '',
                ':customer_group' => $order['customer_group_id'] ?? 0,
                ':subtotal' => $order['subtotal'] ?? 0,
                ':customer_name' => '',
                ':payment_method' => '',
                ':total_refunded' => $order['total_refunded'] ?? 0,
                ':pickup_location_code' => null,
                ':amstorecredit_refunded_amount' => null,
                ':am_earn_reward_points' => null,
                ':am_refund_reward_points' => null,
                ':am_deduct_reward_points' => null
            ];
            
            // Try to get address information from related tables
            try {
                // Get billing address
                $billingStmt = $pdo->prepare("SELECT * FROM sales_order_address WHERE parent_id = :parent_id AND address_type = 'billing'");
                $billingStmt->execute([':parent_id' => $order['entity_id']]);
                $billingAddress = $billingStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($billingAddress) {
                    $insertData[':billing_name'] = trim(($billingAddress['firstname'] ?? '') . ' ' . ($billingAddress['lastname'] ?? ''));
                    $insertData[':billing_address'] = $billingAddress['street'] ?? '';
                    $insertData[':customer_name'] = $insertData[':billing_name'];
                } else {
                    $insertData[':billing_name'] = '';
                    $insertData[':billing_address'] = '';
                }
                
                // Get shipping address
                $shippingStmt = $pdo->prepare("SELECT * FROM sales_order_address WHERE parent_id = :parent_id AND address_type = 'shipping'");
                $shippingStmt->execute([':parent_id' => $order['entity_id']]);
                $shippingAddress = $shippingStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($shippingAddress) {
                    $insertData[':shipping_name'] = trim(($shippingAddress['firstname'] ?? '') . ' ' . ($shippingAddress['lastname'] ?? ''));
                    $insertData[':shipping_address'] = $shippingAddress['street'] ?? '';
                    $insertData[':shipping_information'] = $shippingAddress['street'] ?? '';
                    if (empty($insertData[':customer_name'])) {
                        $insertData[':customer_name'] = $insertData[':shipping_name'];
                    }
                } else {
                    $insertData[':shipping_name'] = '';
                    $insertData[':shipping_address'] = '';
                    $insertData[':shipping_information'] = '';
                }
            } catch (Exception $e) {
                // Set default values if we can't get address information
                $insertData[':billing_name'] = '';
                $insertData[':billing_address'] = '';
                $insertData[':shipping_name'] = '';
                $insertData[':shipping_address'] = '';
                $insertData[':shipping_information'] = '';
            }
            
            try {
                $insertStmt = $pdo->prepare($insertSql);
                $insertStmt->execute($insertData);
                echo "Inserted order #{$order['increment_id']} (Entity ID: {$order['entity_id']})\n";
                $insertedCount++;
            } catch (Exception $e) {
                echo "Error inserting order #{$order['increment_id']}: " . $e->getMessage() . "\n";
            }
        }
        
        echo "Successfully inserted {$insertedCount} missing orders into sales_order_grid\n";
        
        // Clear cache to make sure changes appear in admin
        exec('cd /home/technadminy7/public_html && php bin/magento cache:flush');
        echo "Cache flushed\n";
    } else {
        echo "Order grid is synchronized. No action needed.\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "General error: " . $e->getMessage() . "\n";
    exit(1);
}
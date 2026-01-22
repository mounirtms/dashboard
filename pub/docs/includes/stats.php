<?php
/**
 * Enhanced Statistics Collection with Rich Data & Error Handling
 * Real-time data from Magento database
 */

if (!defined('DOC_ACCESS')) {
    die('Direct access not permitted');
}

class StatsCollector {
    private $db;
    private $cache = [];
    private $cacheEnabled;
    private $cacheDuration;
    
    public function __construct($db, $config) {
        $this->db = $db;
        $this->cacheEnabled = $config['cache']['enabled'];
        $this->cacheDuration = $config['cache']['duration'];
    }
    
    /**
     * Get comprehensive system statistics
     */
    public function getSystemStats() {
        return $this->getCached('system_stats', function() {
            return [
                'yalidine' => $this->getYalidineStats(),
                'orders' => $this->getOrderStats(),
                'products' => $this->getProductStats(),
                'customers' => $this->getCustomerStats(),
                'performance' => $this->getPerformanceStats(),
                'database' => $this->getDatabaseStats(),
                'revenue' => $this->getRevenueStats(),
                'geographic' => $this->getGeographicStats()
            ];
        });
    }
    
    /**
     * Yalidine Integration Statistics (with error handling)
     */
    public function getYalidineStats() {
        return $this->getCached('yalidine_stats', function() {
            $stats = [];
            
            try {
                // Wilayas
                if ($this->db->tableExists('mab_yalidine_wilayas')) {
                    $stats['wilayas'] = [
                        'total' => $this->db->getTableRowCount('mab_yalidine_wilayas'),
                        'active' => $this->db->queryValue("SELECT COUNT(*) FROM mab_yalidine_wilayas WHERE is_active = 1") ?: 0
                    ];
                    
                    // Top 5 wilayas by order count
                    $topWilayas = $this->db->query("
                        SELECT w.name, w.code, COUNT(DISTINCT so.entity_id) as order_count
                        FROM mab_yalidine_wilayas w
                        LEFT JOIN sales_order_address soa ON w.id = soa.wilaya_id
                        LEFT JOIN sales_order so ON soa.parent_id = so.entity_id
                        WHERE w.is_active = 1
                        GROUP BY w.id, w.name, w.code
                        ORDER BY order_count DESC
                        LIMIT 5
                    ");
                    $stats['top_wilayas'] = $topWilayas ?: [];
                } else {
                    $stats['wilayas'] = ['total' => 0, 'active' => 0];
                    $stats['top_wilayas'] = [];
                }
                
                // Communes
                if ($this->db->tableExists('mab_yalidine_communes')) {
                    $stats['communes'] = [
                        'total' => $this->db->getTableRowCount('mab_yalidine_communes'),
                        'active' => $this->db->queryValue("SELECT COUNT(*) FROM mab_yalidine_communes WHERE is_active = 1") ?: 0
                    ];
                } else {
                    $stats['communes'] = ['total' => 0, 'active' => 0];
                }
                
                // Source Mappings
                if ($this->db->tableExists('mab_yalidine_source_mapping')) {
                    $stats['source_mappings'] = $this->db->getTableRowCount('mab_yalidine_source_mapping');
                } else {
                    $stats['source_mappings'] = 0;
                }
                
                // Synced Addresses
                $stats['synced_addresses'] = [
                    'quotes' => $this->db->queryValue("SELECT COUNT(*) FROM quote_address WHERE wilaya_id IS NOT NULL") ?: 0,
                    'orders' => $this->db->queryValue("SELECT COUNT(*) FROM sales_order_address WHERE wilaya_id IS NOT NULL") ?: 0
                ];
                
                // Yalidine shipping orders
                $stats['shipping_orders'] = [
                    'total' => $this->db->queryValue("SELECT COUNT(*) FROM sales_order WHERE shipping_method LIKE '%yalidine%'") ?: 0,
                    'last_30_days' => $this->db->queryValue("
                        SELECT COUNT(*) FROM sales_order 
                        WHERE shipping_method LIKE '%yalidine%' 
                        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    ") ?: 0,
                    'last_7_days' => $this->db->queryValue("
                        SELECT COUNT(*) FROM sales_order 
                        WHERE shipping_method LIKE '%yalidine%' 
                        AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    ") ?: 0
                ];
                
            } catch (Exception $e) {
                $this->logError('Yalidine stats error: ' . $e->getMessage());
                $stats['error'] = 'Some Yalidine statistics unavailable';
            }
            
            return $stats;
        });
    }
    
    /**
     * Enhanced Order Statistics
     */
    public function getOrderStats() {
        return $this->getCached('order_stats', function() {
            $stats = [];
            
            // Total orders
            $stats['total'] = $this->db->getTableRowCount('sales_order');
            
            // Orders by status
            $statusData = $this->db->query("
                SELECT state, status, COUNT(*) as count
                FROM sales_order
                GROUP BY state, status
                ORDER BY count DESC
            ");
            $stats['by_status'] = $statusData ?: [];
            
            // Time-based orders
            $stats['today'] = $this->db->queryValue("
                SELECT COUNT(*) FROM sales_order
                WHERE DATE(created_at) = CURDATE()
            ") ?: 0;
            
            $stats['yesterday'] = $this->db->queryValue("
                SELECT COUNT(*) FROM sales_order
                WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
            ") ?: 0;
            
            $stats['last_7_days'] = $this->db->queryValue("
                SELECT COUNT(*) FROM sales_order
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ") ?: 0;
            
            $stats['last_30_days'] = $this->db->queryValue("
                SELECT COUNT(*) FROM sales_order
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ") ?: 0;
            
            $stats['this_month'] = $this->db->queryValue("
                SELECT COUNT(*) FROM sales_order
                WHERE MONTH(created_at) = MONTH(CURRENT_DATE())
                AND YEAR(created_at) = YEAR(CURRENT_DATE())
            ") ?: 0;
            
            // Order values
            $stats['average_value'] = round($this->db->queryValue("
                SELECT AVG(grand_total) FROM sales_order
                WHERE state != 'canceled'
            ") ?: 0, 2);
            
            $stats['total_value'] = round($this->db->queryValue("
                SELECT SUM(grand_total) FROM sales_order
                WHERE state IN ('complete', 'processing')
            ") ?: 0, 2);
            
            // Pending orders
            $stats['pending'] = $this->db->queryValue("
                SELECT COUNT(*) FROM sales_order
                WHERE state IN ('pending', 'pending_payment', 'processing')
            ") ?: 0;
            
            // Completed orders
            $stats['completed'] = $this->db->queryValue("
                SELECT COUNT(*) FROM sales_order
                WHERE state = 'complete'
            ") ?: 0;
            
            // Canceled orders
            $stats['canceled'] = $this->db->queryValue("
                SELECT COUNT(*) FROM sales_order
                WHERE state = 'canceled'
            ") ?: 0;
            
            // Orders by shipping method
            $shippingMethods = $this->db->query("
                SELECT shipping_method, COUNT(*) as count
                FROM sales_order
                WHERE shipping_method IS NOT NULL
                GROUP BY shipping_method
                ORDER BY count DESC
                LIMIT 10
            ");
            $stats['by_shipping_method'] = $shippingMethods ?: [];
            
            // Orders by payment method
            $paymentMethods = $this->db->query("
                SELECT sop.method, COUNT(DISTINCT so.entity_id) as count
                FROM sales_order so
                LEFT JOIN sales_order_payment sop ON so.entity_id = sop.parent_id
                WHERE sop.method IS NOT NULL
                GROUP BY sop.method
                ORDER BY count DESC
            ");
            $stats['by_payment_method'] = $paymentMethods ?: [];
            
            // Hourly distribution (last 24 hours)
            $hourly = $this->db->query("
                SELECT HOUR(created_at) as hour, COUNT(*) as count
                FROM sales_order
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY HOUR(created_at)
                ORDER BY hour
            ");
            $stats['hourly_distribution'] = $hourly ?: [];
            
            return $stats;
        });
    }
    
    /**
     * Enhanced Product Statistics
     */
    public function getProductStats() {
        return $this->getCached('product_stats', function() {
            $stats = [];
            
            // Total products
            $stats['total'] = $this->db->queryValue("
                SELECT COUNT(DISTINCT entity_id) FROM catalog_product_entity
            ") ?: 0;
            
            // Enabled products
            $stats['enabled'] = $this->db->queryValue("
                SELECT COUNT(DISTINCT cpe.entity_id)
                FROM catalog_product_entity cpe
                JOIN catalog_product_entity_int cpei ON cpe.entity_id = cpei.entity_id
                JOIN eav_attribute ea ON cpei.attribute_id = ea.attribute_id
                WHERE ea.attribute_code = 'status' AND cpei.value = 1
            ") ?: 0;
            
            $stats['disabled'] = $stats['total'] - $stats['enabled'];
            
            // Stock status
            $stats['in_stock'] = $this->db->queryValue("
                SELECT COUNT(DISTINCT product_id)
                FROM cataloginventory_stock_status
                WHERE stock_status = 1
            ") ?: 0;
            
            $stats['out_of_stock'] = $this->db->queryValue("
                SELECT COUNT(DISTINCT product_id)
                FROM cataloginventory_stock_status
                WHERE stock_status = 0
            ") ?: 0;
            
            // Product types
            $productTypes = $this->db->query("
                SELECT type_id, COUNT(*) as count
                FROM catalog_product_entity
                GROUP BY type_id
                ORDER BY count DESC
            ");
            $stats['by_type'] = $productTypes ?: [];
            
            // Products with images
            $stats['with_images'] = $this->db->queryValue("
                SELECT COUNT(DISTINCT entity_id)
                FROM catalog_product_entity_varchar
                WHERE attribute_id IN (
                    SELECT attribute_id FROM eav_attribute 
                    WHERE attribute_code IN ('image', 'small_image', 'thumbnail')
                    AND entity_type_id = 4
                )
                AND value IS NOT NULL 
                AND value != 'no_selection'
                AND value != ''
            ") ?: 0;
            
            // Average price
            $stats['average_price'] = round($this->db->queryValue("
                SELECT AVG(value)
                FROM catalog_product_entity_decimal cpd
                JOIN eav_attribute ea ON cpd.attribute_id = ea.attribute_id
                WHERE ea.attribute_code = 'price'
                AND value > 0
            ") ?: 0, 2);
            
            return $stats;
        });
    }
    
    /**
     * Enhanced Customer Statistics
     */
    public function getCustomerStats() {
        return $this->getCached('customer_stats', function() {
            $stats = [];
            
            // Total customers
            $stats['total'] = $this->db->getTableRowCount('customer_entity');
            
            // Active customers (placed order in last 90 days)
            $stats['active_90_days'] = $this->db->queryValue("
                SELECT COUNT(DISTINCT customer_id)
                FROM sales_order
                WHERE customer_id IS NOT NULL
                AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            ") ?: 0;
            
            // Active customers (last 30 days)
            $stats['active_30_days'] = $this->db->queryValue("
                SELECT COUNT(DISTINCT customer_id)
                FROM sales_order
                WHERE customer_id IS NOT NULL
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ") ?: 0;
            
            // New customers (last 30 days)
            $stats['new_last_30_days'] = $this->db->queryValue("
                SELECT COUNT(*) FROM customer_entity
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ") ?: 0;
            
            // Customers with addresses
            $stats['with_addresses'] = $this->db->queryValue("
                SELECT COUNT(DISTINCT parent_id)
                FROM customer_address_entity
            ") ?: 0;
            
            // Customers with orders
            $stats['with_orders'] = $this->db->queryValue("
                SELECT COUNT(DISTINCT customer_id)
                FROM sales_order
                WHERE customer_id IS NOT NULL
            ") ?: 0;
            
            // Guest orders percentage
            $guestOrders = $this->db->queryValue("
                SELECT COUNT(*) FROM sales_order WHERE customer_id IS NULL
            ") ?: 0;
            $totalOrders = $this->db->getTableRowCount('sales_order');
            $stats['guest_order_percentage'] = $totalOrders > 0 ? round(($guestOrders / $totalOrders) * 100, 2) : 0;
            
            // Customer lifetime value
            $stats['avg_lifetime_value'] = round($this->db->queryValue("
                SELECT AVG(total_spent)
                FROM (
                    SELECT customer_id, SUM(grand_total) as total_spent
                    FROM sales_order
                    WHERE customer_id IS NOT NULL
                    AND state != 'canceled'
                    GROUP BY customer_id
                ) as customer_totals
            ") ?: 0, 2);
            
            return $stats;
        });
    }
    
    /**
     * Revenue Statistics
     */
    public function getRevenueStats() {
        return $this->getCached('revenue_stats', function() {
            $stats = [];
            
            // Today's revenue
            $stats['today'] = round($this->db->queryValue("
                SELECT SUM(grand_total) FROM sales_order
                WHERE DATE(created_at) = CURDATE()
                AND state IN ('complete', 'processing')
            ") ?: 0, 2);
            
            // Yesterday's revenue
            $stats['yesterday'] = round($this->db->queryValue("
                SELECT SUM(grand_total) FROM sales_order
                WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                AND state IN ('complete', 'processing')
            ") ?: 0, 2);
            
            // Last 7 days
            $stats['last_7_days'] = round($this->db->queryValue("
                SELECT SUM(grand_total) FROM sales_order
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                AND state IN ('complete', 'processing')
            ") ?: 0, 2);
            
            // Last 30 days
            $stats['last_30_days'] = round($this->db->queryValue("
                SELECT SUM(grand_total) FROM sales_order
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND state IN ('complete', 'processing')
            ") ?: 0, 2);
            
            // This month
            $stats['this_month'] = round($this->db->queryValue("
                SELECT SUM(grand_total) FROM sales_order
                WHERE MONTH(created_at) = MONTH(CURRENT_DATE())
                AND YEAR(created_at) = YEAR(CURRENT_DATE())
                AND state IN ('complete', 'processing')
            ") ?: 0, 2);
            
            // Last month
            $stats['last_month'] = round($this->db->queryValue("
                SELECT SUM(grand_total) FROM sales_order
                WHERE MONTH(created_at) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
                AND YEAR(created_at) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
                AND state IN ('complete', 'processing')
            ") ?: 0, 2);
            
            // This year
            $stats['this_year'] = round($this->db->queryValue("
                SELECT SUM(grand_total) FROM sales_order
                WHERE YEAR(created_at) = YEAR(CURRENT_DATE())
                AND state IN ('complete', 'processing')
            ") ?: 0, 2);
            
            // Total all time
            $stats['all_time'] = round($this->db->queryValue("
                SELECT SUM(grand_total) FROM sales_order
                WHERE state IN ('complete', 'processing')
            ") ?: 0, 2);
            
            // Monthly revenue (last 12 months)
            $monthlyRevenue = $this->db->query("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    SUM(grand_total) as revenue,
                    COUNT(*) as order_count
                FROM sales_order
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                AND state IN ('complete', 'processing')
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month DESC
            ");
            $stats['monthly_trend'] = $monthlyRevenue ?: [];
            
            return $stats;
        });
    }
    
    /**
     * Geographic Statistics
     */
    public function getGeographicStats() {
        return $this->getCached('geographic_stats', function() {
            $stats = [];
            
            // Top cities by order count
            $topCities = $this->db->query("
                SELECT city, COUNT(*) as order_count
                FROM sales_order_address
                WHERE address_type = 'shipping'
                AND city IS NOT NULL
                AND city != ''
                GROUP BY city
                ORDER BY order_count DESC
                LIMIT 10
            ");
            $stats['top_cities'] = $topCities ?: [];
            
            // Orders by country
            $ordersByCountry = $this->db->query("
                SELECT country_id, COUNT(*) as order_count
                FROM sales_order_address
                WHERE address_type = 'shipping'
                GROUP BY country_id
                ORDER BY order_count DESC
            ");
            $stats['by_country'] = $ordersByCountry ?: [];
            
            return $stats;
        });
    }
    
    /**
     * Performance Metrics
     */
    public function getPerformanceStats() {
        return $this->getCached('performance_stats', function() {
            $stats = [];
            
            // Cache status (handle missing table)
            if ($this->db->tableExists('core_cache_option')) {
                $cacheData = $this->db->query("
                    SELECT cache_type, status
                    FROM core_cache_option
                    ORDER BY cache_type
                ");
                $stats['cache_types'] = $cacheData ?: [];
                $stats['cache_enabled_count'] = count(array_filter($cacheData ?: [], function($c) {
                    return $c['status'] == 1;
                }));
                $stats['cache_total_count'] = count($cacheData ?: []);
            } else {
                $stats['cache_types'] = [];
                $stats['cache_enabled_count'] = 0;
                $stats['cache_total_count'] = 0;
            }
            
            // Index status (handle missing table)
            if ($this->db->tableExists('indexer_state')) {
                $indexData = $this->db->query("
                    SELECT indexer_id, status, updated
                    FROM indexer_state
                    ORDER BY indexer_id
                ");
                $stats['indexers'] = $indexData ?: [];
                $stats['indexers_valid'] = count(array_filter($indexData ?: [], function($i) {
                    return $i['status'] == 'valid';
                }));
                $stats['indexers_total'] = count($indexData ?: []);
            } else {
                $stats['indexers'] = [];
                $stats['indexers_valid'] = 0;
                $stats['indexers_total'] = 0;
            }
            
            return $stats;
        });
    }
    
    /**
     * Database Statistics
     */
    public function getDatabaseStats() {
        return $this->getCached('database_stats', function() {
            $stats = [];
            
            // Database size
            $sizeData = $this->db->queryOne("
                SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb,
                    ROUND(SUM(data_length) / 1024 / 1024, 2) AS data_mb,
                    ROUND(SUM(index_length) / 1024 / 1024, 2) AS index_mb
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
            ");
            $stats['size_mb'] = $sizeData['size_mb'] ?? 0;
            $stats['data_mb'] = $sizeData['data_mb'] ?? 0;
            $stats['index_mb'] = $sizeData['index_mb'] ?? 0;
            
            // Table count
            $stats['table_count'] = $this->db->queryValue("
                SELECT COUNT(*) FROM information_schema.tables
                WHERE table_schema = DATABASE()
            ") ?: 0;
            
            // Key tables row counts
            $stats['key_tables'] = [
                'sales_order' => $this->db->getTableRowCount('sales_order'),
                'sales_order_item' => $this->db->getTableRowCount('sales_order_item'),
                'catalog_product_entity' => $this->db->getTableRowCount('catalog_product_entity'),
                'customer_entity' => $this->db->getTableRowCount('customer_entity'),
                'quote' => $this->db->getTableRowCount('quote'),
                'quote_item' => $this->db->getTableRowCount('quote_item')
            ];
            
            // Largest tables
            $largestTables = $this->db->query("
                SELECT 
                    table_name,
                    ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb,
                    table_rows
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                ORDER BY (data_length + index_length) DESC
                LIMIT 10
            ");
            $stats['largest_tables'] = $largestTables ?: [];
            
            return $stats;
        });
    }
    
    /**
     * Cache helper
     */
    private function getCached($key, $callback) {
        if (!$this->cacheEnabled) {
            return $callback();
        }
        
        $cacheFile = LOGS_DIR . "/cache_{$key}.json";
        
        // Check if cache exists and is valid
        if (file_exists($cacheFile)) {
            $cacheTime = filemtime($cacheFile);
            if (time() - $cacheTime < $this->cacheDuration) {
                $cached = json_decode(file_get_contents($cacheFile), true);
                if ($cached !== null) {
                    return $cached;
                }
            }
        }
        
        // Generate fresh data
        try {
            $data = $callback();
        } catch (Exception $e) {
            $this->logError('Cache generation error for ' . $key . ': ' . $e->getMessage());
            $data = ['error' => 'Failed to generate statistics'];
        }
        
        // Save to cache
        @file_put_contents($cacheFile, json_encode($data));
        
        return $data;
    }
    
    /**
     * Error logging
     */
    private function logError($message) {
        if (defined('LOGS_DIR')) {
            $logFile = LOGS_DIR . '/error_' . date('Y-m-d') . '.log';
            $timestamp = date('Y-m-d H:i:s');
            @error_log("[{$timestamp}] {$message}\n", 3, $logFile);
        }
    }
    
    /**
     * Clear all caches
     */
    public function clearCache() {
        $cacheFiles = glob(LOGS_DIR . "/cache_*.json");
        foreach ($cacheFiles as $file) {
            @unlink($file);
        }
        return count($cacheFiles);
    }
}

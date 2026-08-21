<?php
/**
 * Environment Helper Utility
 * 
 * Provides shared methods to access environment configurations and data.
 * Supports: Production, Beta, Dev (Magento), PIM (Akeneo)
 */

class EnvironmentHelper {
    private $config;
    private $connections = [];
    private static $attrCache = [];

    public function __construct(array $config) {
        $this->config = $config;
    }

    /**
     * Get environment configuration
     */
    public function getEnvConfig(string $env): ?array {
        return $this->config['environments'][$env] ?? null;
    }

    /**
     * Validate environment name
     */
    public function isValidEnv(string $env): bool {
        return isset($this->config['environments'][$env]);
    }

    /**
     * Get list of valid environment names
     */
    public function getValidEnvNames(): array {
        return array_keys($this->config['environments'] ?? []);
    }

    /**
     * Get all environments
     */
    public function getEnvironments(): array {
        return $this->config['environments'] ?? [];
    }

    /**
     * Get database connection for environment
     */
    public function getDb(string $env): ?mysqli {
        if (isset($this->connections[$env])) {
            return $this->connections[$env];
        }

        $envConfig = $this->getEnvConfig($env);
        if (!$envConfig || empty($envConfig['db'])) {
            return null;
        }

        require_once dirname(__DIR__) . '/DatabasePool.php';

        $dbConfig = $this->config['database'];
        $db = DatabasePool::getMySQLi(
            $dbConfig['host'],
            $envConfig['db_user'] ?? $dbConfig['user'],
            $envConfig['db_pass'] ?? $dbConfig['pass'],
            $envConfig['db'],
            $dbConfig['port']
        );

        if (!$db) {
            return null;
        }

        $this->connections[$env] = $db;
        return $db;
    }

    /**
     * Execute MySQL CLI command using MariaDB binary
     */
    public function execMysql(string $env, string $sql): string {
        $envConfig = $this->getEnvConfig($env);
        if (!$envConfig || empty($envConfig['db'])) {
            return '';
        }

        $dbConfig = $this->config['database'];
        $mysqlBin = $dbConfig['mysql_bin'] ?? 'mysql';
        $dbUser = $envConfig['db_user'] ?? $dbConfig['user'];
        $dbPass = $envConfig['db_pass'] ?? $dbConfig['pass'];
        $dbName = $envConfig['db'];
        $dbHost = $dbConfig['host'];
        $dbPort = $dbConfig['port'];

        $cmd = sprintf(
            "%s -u %s -p'%s' -h %s -P %s %s -e %s 2>&1",
            $mysqlBin,
            escapeshellarg($dbUser),
            addslashes($dbPass),
            $dbHost,
            $dbPort,
            $dbName,
            escapeshellarg($sql)
        );

        $handle = popen($cmd, 'r');
        if ($handle === false) {
            return '';
        }
        $output = '';
        while (!feof($handle)) {
            $output .= fread($handle, 4096);
        }
        pclose($handle);
        return trim($output);
    }

    /**
     * Get orders statistics
     */
    public function getOrdersStats(string $env, string $period = 'today'): array {
        $db = $this->getDb($env);
        if (!$db) return ['error' => 'Cannot connect to database'];

        $where = '';
        $now = date('Y-m-d H:i:s');
        
        switch ($period) {
            case 'today':
                $where = "WHERE created_at >= '" . date('Y-m-d') . " 00:00:00'";
                break;
            case 'week':
                $where = "WHERE created_at >= DATE_SUB('$now', INTERVAL 7 DAY)";
                break;
            case 'month':
                $where = "WHERE created_at >= DATE_SUB('$now', INTERVAL 30 DAY)";
                break;
        }

        $result = [];

        // Order count and revenue
        $r = $db->query("SELECT COUNT(*) as count, SUM(grand_total) as revenue FROM sales_order $where AND status != 'canceled'");
        if ($r && $row = $r->fetch_assoc()) {
            $result['count'] = (int)$row['count'];
            $result['revenue'] = (float)$row['revenue'];
        }

        // Orders by status
        $r = $db->query("SELECT status, COUNT(*) as count FROM sales_order $where GROUP BY status");
        $byStatus = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $byStatus[$row['status']] = (int)$row['count'];
            }
        }
        $result['by_status'] = $byStatus;

        return $result;
    }

    /**
     * Get customer statistics
     */
    public function getCustomerStats(string $env): array {
        $db = $this->getDb($env);
        if (!$db) return ['error' => 'Cannot connect to database'];

        $stats = [];

        // Total customers
        $r = $db->query("SELECT COUNT(*) as count FROM customer_entity");
        $stats['total'] = $r ? (int)$r->fetch_assoc()['count'] : 0;

        // New today
        $r = $db->query("SELECT COUNT(*) as count FROM customer_entity WHERE created_at >= '" . date('Y-m-d') . " 00:00:00'");
        $stats['new_today'] = $r ? (int)$r->fetch_assoc()['count'] : 0;

        // New this week
        $r = $db->query("SELECT COUNT(*) as count FROM customer_entity WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['new_week'] = $r ? (int)$r->fetch_assoc()['count'] : 0;

        // Active sessions (last 15 min)
        $r = $db->query("SELECT COUNT(DISTINCT customer_id) as count FROM customer_visitor WHERE last_visit_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND customer_id IS NOT NULL");
        $stats['active_sessions'] = $r ? (int)$r->fetch_assoc()['count'] : 0;

        return $stats;
    }

    /**
     * Get product statistics
     */
    public function getProductStats(string $env): array {
        $db = $this->getDb($env);
        if (!$db) return ['error' => 'Cannot connect to database'];

        $stats = [];

        // Total products
        $r = $db->query("SELECT COUNT(*) as count FROM catalog_product_entity");
        $stats['total'] = $r ? (int)$r->fetch_assoc()['count'] : 0;

        // Enabled products
        $r = $db->query("
            SELECT COUNT(*) as count 
            FROM catalog_product_entity e
            JOIN catalog_product_entity_int attr ON e.entity_id = attr.entity_id 
            JOIN eav_attribute ea ON attr.attribute_id = ea.attribute_id
            WHERE ea.attribute_code = 'status' AND attr.value = 1
        ");
        $stats['enabled'] = $r ? (int)$r->fetch_assoc()['count'] : 0;

        // In stock
        $r = $db->query("SELECT COUNT(*) as count FROM cataloginventory_stock_item WHERE is_in_stock = 1");
        $stats['in_stock'] = $r ? (int)$r->fetch_assoc()['count'] : 0;

        // Low stock (< 5)
        $r = $db->query("SELECT COUNT(*) as count FROM cataloginventory_stock_item WHERE qty < 5 AND is_in_stock = 1");
        $stats['low_stock'] = $r ? (int)$r->fetch_assoc()['count'] : 0;

        // Out of stock
        $r = $db->query("SELECT COUNT(*) as count FROM cataloginventory_stock_item WHERE is_in_stock = 0");
        $stats['out_of_stock'] = $r ? (int)$r->fetch_assoc()['count'] : 0;

        return $stats;
    }

    /**
     * Get database size
     */
    public function getDbSize(string $env): array {
        $db = $this->getDb($env);
        if (!$db) return ['error' => 'Cannot connect'];

        $envConfig = $this->getEnvConfig($env);
        $dbName = $envConfig['db'];

        $r = $db->query("
            SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) as size_mb,
                ROUND(SUM(data_free) / 1024 / 1024, 1) as frag_mb,
                COUNT(*) as table_count
            FROM information_schema.TABLES 
            WHERE table_schema = '$dbName'
        ");

        return $r ? $r->fetch_assoc() : ['size_mb' => 0, 'frag_mb' => 0, 'table_count' => 0];
    }

    /**
     * Get environment system info
     */
    /**
     * Execute shell command using popen (since shell_exec is disabled)
     */
    private function execCommand(string $cmd): string {
        $handle = @popen($cmd, 'r');
        if ($handle === false) {
            return '';
        }
        $output = '';
        while (!feof($handle)) {
            $output .= fread($handle, 4096);
        }
        pclose($handle);
        return trim($output);
    }

    public function getSystemInfo(string $env): array {
        $envConfig = $this->getEnvConfig($env);
        if (!$envConfig) return [];

        $path = $envConfig['path'];

        // Disk usage
        $diskUsage = $this->execCommand("timeout 3 du -sm $path 2>/dev/null | awk '{print \$1}'");
        
        // PHP-FPM workers
        $user = basename($path);
        $phpFpm = $this->execCommand("ps aux | grep 'php-fpm: pool.*$user' | grep -v grep | grep -v master | wc -l");

        // Magento mode
        $mode = 'unknown';
        $envFile = $path . '/app/etc/env.php';
        if (is_file($envFile)) {
            $content = @file_get_contents($envFile);
            if (strpos($content, "'MAGE_MODE'=>'developer'") !== false || strpos($content, "'MAGE_MODE' => 'developer'") !== false) {
                $mode = 'developer';
            } elseif (strpos($content, "'MAGE_MODE'=>'production'") !== false || strpos($content, "'MAGE_MODE' => 'production'") !== false) {
                $mode = 'production';
            } else {
                $mode = 'default';
            }
        }

        return [
            'disk_usage_mb' => (int)$diskUsage,
            'php_fpm_workers' => (int)$phpFpm,
            'mode' => $mode,
        ];
    }

    /**
     * Get MySQLi connection for a given environment
     */
    public function getDbConnection(string $env): ?mysqli {
        $envConfig = $this->getEnvConfig($env);
        if (!$envConfig) return null;

        $dbConfig = $this->config['database'] ?? [];
        $host = $dbConfig['host'] ?? '127.0.0.1';
        $port = $dbConfig['port'] ?? '3307';
        $user = $dbConfig['user'] ?? 'root';
        $pass = $dbConfig['pass'] ?? '';
        $dbName = $envConfig['db'] ?? '';

        if (!$dbName) return null;

        $db = @new mysqli($host, $user, $pass, $dbName, (int)$port);
        return $db->connect_error ? null : $db;
    }

    /**
     * Get PIM (Akeneo) statistics
     */
    public function getPimStats(): array {
        $db = $this->getDb('pim');
        if (!$db) return ['error' => 'Cannot connect to PIM database'];

        $stats = [];

        // Products count
        $r = $db->query("SELECT COUNT(*) as count FROM pim_catalog_product");
        $stats['products'] = $r ? (int)$r->fetch_assoc()['count'] : 0;

        // Product families
        $r = $db->query("SELECT COUNT(*) as count FROM pim_catalog_family");
        $stats['families'] = $r ? (int)$r->fetch_assoc()['count'] : 0;

        // Attributes
        $r = $db->query("SELECT COUNT(*) as count FROM pim_catalog_attribute");
        $stats['attributes'] = $r ? (int)$r->fetch_assoc()['count'] : 0;

        // Jobs (last 10)
        $r = $db->query("SELECT status, COUNT(*) as count FROM akeneo_batch_job_instance GROUP BY status");
        $jobs = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $jobs[$row['status']] = (int)$row['count'];
            }
        }
        $stats['jobs'] = $jobs;

        return $stats;
    }

    /**
     * Close all database connections
     */
    public function close(): void {
        foreach ($this->connections as $db) {
            @$db->close();
        }
        $this->connections = [];
    }

    // ═══════════════════════════════════════════════════════════
    // CUSTOMER-FACING METHODS
    // ═══════════════════════════════════════════════════════════

    /**
     * Get visible category tree (level 1-2 categories with product counts)
     */
    public function getCustomerCategories(string $env, int $parentId = 2): array {
        $db = $this->getDb($env);
        if (!$db) return [];

        // Get attribute IDs
        $r = $db->query("SELECT attribute_id, attribute_code FROM eav_attribute WHERE entity_type_id = 3 AND attribute_code IN ('name', 'is_active', 'include_in_menu')");
        $attrIds = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $attrIds[$row['attribute_code']] = $row['attribute_id'];
            }
        }

        $nameAttr = $attrIds['name'] ?? 45;
        $activeAttr = $attrIds['is_active'] ?? 46;
        $menuAttr = $attrIds['include_in_menu'] ?? 69;

        // Get categories
        $query = "SELECT ce.entity_id, ce.position, ce.level,
                         cv.value as name,
                         (SELECT COUNT(*) FROM catalog_category_product cp WHERE cp.category_id = ce.entity_id) as product_count
                  FROM catalog_category_entity ce
                  LEFT JOIN catalog_category_entity_varchar cv ON cv.entity_id = ce.entity_id AND cv.attribute_id = {$nameAttr} AND cv.store_id = 0
                  LEFT JOIN catalog_category_entity_int ia ON ia.entity_id = ce.entity_id AND ia.attribute_id = {$activeAttr}
                  LEFT JOIN catalog_category_entity_int im ON im.entity_id = ce.entity_id AND im.attribute_id = {$menuAttr}
                  WHERE ce.parent_id = {$parentId}
                  AND (ia.value = 1 OR ia.value IS NULL)
                  AND (im.value = 1 OR im.value IS NULL)
                  AND cv.value IS NOT NULL
                  ORDER BY ce.position";

        $r = $db->query($query);
        $categories = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $categories[] = $row;
            }
        }

        return $categories;
    }

    /**
     * Get products in a category with pagination
     */
    public function getCategoryProducts(string $env, int $categoryId, int $page = 0, int $pageSize = 10): array {
        $db = $this->getDb($env);
        if (!$db) return [];

        $offset = $page * $pageSize;

        // Get attribute IDs
        $r = $db->query("SELECT attribute_id, attribute_code FROM eav_attribute WHERE entity_type_id = 4 AND attribute_code IN ('name', 'status', 'visibility')");
        $attrIds = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $attrIds[$row['attribute_code']] = $row['attribute_id'];
            }
        }

        $nameAttr = $attrIds['name'] ?? 73;
        $statusAttr = $attrIds['status'] ?? 97;
        $visAttr = $attrIds['visibility'] ?? 99;

        $query = "SELECT e.entity_id, e.sku, e.type_id,
                         nv.value as name,
                         p.min_price as price,
                         ss.stock_status,
                         ss.qty
                  FROM catalog_category_product ccp
                  JOIN catalog_product_entity e ON e.entity_id = ccp.product_id
                  JOIN catalog_product_entity_varchar nv ON nv.entity_id = e.entity_id AND nv.attribute_id = {$nameAttr} AND nv.store_id = 0
                  LEFT JOIN catalog_product_index_price p ON p.entity_id = e.entity_id AND p.website_id = 1 AND p.customer_group_id = 0
                  LEFT JOIN cataloginventory_stock_status ss ON ss.product_id = e.entity_id AND ss.website_id = 1
                  LEFT JOIN catalog_product_entity_int ist ON ist.entity_id = e.entity_id AND ist.attribute_id = {$statusAttr} AND ist.store_id = 0
                  LEFT JOIN catalog_product_entity_int iv ON iv.entity_id = e.entity_id AND iv.attribute_id = {$visAttr} AND iv.store_id = 0
                  WHERE ccp.category_id = {$categoryId}
                  AND (ist.value = 1 OR ist.value IS NULL)
                  AND (iv.value = 4 OR iv.value IS NULL)
                  AND nv.value IS NOT NULL
                  ORDER BY e.entity_id
                  LIMIT {$pageSize} OFFSET {$offset}";

        $r = $db->query($query);
        $products = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $products[] = $row;
            }
        }

        return $products;
    }

    /**
     * Get total product count for a category
     */
    public function getCategoryProductCount(string $env, int $categoryId): int {
        $db = $this->getDb($env);
        if (!$db) return 0;

        $r = $db->query("SELECT COUNT(*) as count FROM catalog_category_product WHERE category_id = {$categoryId}");
        return $r ? (int)$r->fetch_assoc()['count'] : 0;
    }

    /**
     * Get cached attribute IDs to avoid N+1 queries
     */
    private function getAttributeIds(string $env, array $codes): array {
        $cacheKey = "$env:" . implode(',', sort($codes));
        
        if (isset(self::$attrCache[$cacheKey])) {
            return self::$attrCache[$cacheKey];
        }

        $db = $this->getDb($env);
        if (!$db) return [];

        $placeholders = implode(',', array_map(fn($x) => "'$x'", $codes));
        $r = $db->query("SELECT attribute_id, attribute_code FROM eav_attribute WHERE entity_type_id = 4 AND attribute_code IN ($placeholders)");
        $attrIds = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $attrIds[$row['attribute_code']] = $row['attribute_id'];
            }
        }

        self::$attrCache[$cacheKey] = $attrIds;
        return $attrIds;
    }

    /**
     * Search products by name or SKU
     */
    public function searchProducts(string $env, string $query, int $limit = 10): array {
        $db = $this->getDb($env);
        if (!$db) return [];

        $searchTerm = $db->real_escape_string($query);

        // Get cached attribute IDs (avoids N+1 query)
        $attrIds = $this->getAttributeIds($env, ['name', 'status', 'visibility']);

        $nameAttr = $attrIds['name'] ?? 73;
        $statusAttr = $attrIds['status'] ?? 97;
        $visAttr = $attrIds['visibility'] ?? 99;

        $sql = "SELECT e.entity_id, e.sku, e.type_id,
                       nv.value as name,
                       p.min_price as price,
                       ss.stock_status,
                       ss.qty
                FROM catalog_product_entity e
                JOIN catalog_product_entity_varchar nv ON nv.entity_id = e.entity_id AND nv.attribute_id = {$nameAttr} AND nv.store_id = 0
                LEFT JOIN catalog_product_index_price p ON p.entity_id = e.entity_id AND p.website_id = 1 AND p.customer_group_id = 0
                LEFT JOIN cataloginventory_stock_status ss ON ss.product_id = e.entity_id AND ss.website_id = 1
                LEFT JOIN catalog_product_entity_int ist ON ist.entity_id = e.entity_id AND ist.attribute_id = {$statusAttr} AND ist.store_id = 0
                LEFT JOIN catalog_product_entity_int iv ON iv.entity_id = e.entity_id AND iv.attribute_id = {$visAttr} AND iv.store_id = 0
                WHERE (nv.value LIKE '%{$searchTerm}%' OR e.sku LIKE '%{$searchTerm}%')
                AND (ist.value = 1 OR ist.value IS NULL)
                AND (iv.value = 4 OR iv.value IS NULL)
                ORDER BY e.entity_id
                LIMIT {$limit}";

        $r = $db->query($sql);
        $products = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $products[] = $row;
            }
        }

        return $products;
    }

    /**
     * Get full product details
     */
    public function getProductDetails(string $env, int $productId): ?array {
        $db = $this->getDb($env);
        if (!$db) return null;

        // Get cached attribute IDs (avoids N+1 query)
        $attrIds = $this->getAttributeIds($env, ['name', 'description', 'short_description', 'status', 'visibility', 'image']);

        $nameAttr = $attrIds['name'] ?? 73;
        $descAttr = $attrIds['description'] ?? 74;
        $shortDescAttr = $attrIds['short_description'] ?? 75;
        $statusAttr = $attrIds['status'] ?? 97;
        $visAttr = $attrIds['visibility'] ?? 99;
        $imageAttr = $attrIds['image'] ?? 87;

        $sql = "SELECT e.entity_id, e.sku, e.type_id,
                       nv.value as name,
                       td.value as description,
                       tsd.value as short_description,
                       img.value as image,
                       p.min_price as price,
                       ss.stock_status,
                       ss.qty,
                       ist.value as status,
                       iv.value as visibility
                FROM catalog_product_entity e
                LEFT JOIN catalog_product_entity_varchar nv ON nv.entity_id = e.entity_id AND nv.attribute_id = {$nameAttr} AND nv.store_id = 0
                LEFT JOIN catalog_product_entity_text td ON td.entity_id = e.entity_id AND td.attribute_id = {$descAttr} AND td.store_id = 0
                LEFT JOIN catalog_product_entity_text tsd ON tsd.entity_id = e.entity_id AND tsd.attribute_id = {$shortDescAttr} AND tsd.store_id = 0
                LEFT JOIN catalog_product_entity_varchar img ON img.entity_id = e.entity_id AND img.attribute_id = {$imageAttr} AND img.store_id = 0
                LEFT JOIN catalog_product_index_price p ON p.entity_id = e.entity_id AND p.website_id = 1 AND p.customer_group_id = 0
                LEFT JOIN cataloginventory_stock_status ss ON ss.product_id = e.entity_id AND ss.website_id = 1
                LEFT JOIN catalog_product_entity_int ist ON ist.entity_id = e.entity_id AND ist.attribute_id = {$statusAttr} AND ist.store_id = 0
                LEFT JOIN catalog_product_entity_int iv ON iv.entity_id = e.entity_id AND iv.attribute_id = {$visAttr} AND iv.store_id = 0
                WHERE e.entity_id = {$productId}
                LIMIT 1";

        $r = $db->query($sql);
        return $r ? $r->fetch_assoc() : null;
    }

    /**
     * Get product image URL
     */
    public function getProductImageUrl(string $env, string $imagePath, string $baseUrl): ?string {
        if (empty($imagePath)) return null;
        return rtrim($baseUrl, '/') . '/pub/media/catalog/product' . $imagePath;
    }

    /**
     * Create order from cart (direct database insertion)
     */
    public function createOrderFromCart(string $env, array $sessionData, array $shippingAddress): array {
        $db = $this->getDb($env);
        if (!$db) {
            throw new Exception("Cannot connect to database");
        }

        // Start transaction
        $db->begin_transaction();

        try {
            // Generate increment_id
            $incrementId = $this->getNextIncrementId($db, $env);

            // Calculate totals
            $subtotal = 0;
            foreach ($sessionData['cart']['items'] as $item) {
                $subtotal += ($item['price'] ?? 0) * ($item['qty'] ?? 0);
            }
            $shippingCost = 0; // Can be configured later
            $grandTotal = $subtotal + $shippingCost;

            // Insert sales_order
            $customerEmail = $db->real_escape_string($sessionData['customer_email'] ?? 'guest@telegram.bot');
            $customerName = $db->real_escape_string($sessionData['customer_name'] ?? 'Telegram Customer');
            $customerPhone = $db->real_escape_string($sessionData['customer_phone'] ?? '');

            $orderSql = "INSERT INTO sales_order (
                increment_id, state, status, customer_id, customer_email, customer_firstname, customer_lastname,
                customer_is_guest, customer_group_id, store_id, website_id,
                subtotal, shipping_amount, grand_total, base_grand_total, base_subtotal, base_shipping_amount,
                shipping_method, shipping_description,
                total_qty_ordered, total_qty_invoiced, total_qty_shipped,
                created_at, updated_at
            ) VALUES (
                '{$incrementId}', 'new', 'pending', NULL, '{$customerEmail}', '{$customerName}', '',
                1, 0, 1, 1,
                {$subtotal}, {$shippingCost}, {$grandTotal}, {$grandTotal}, {$subtotal}, {$shippingCost},
                'flatrate_flatrate', 'Flat Rate',
                {$sessionData['cart']['item_count']}, 0, 0,
                NOW(), NOW()
            )";

            $db->query($orderSql);
            $orderId = $db->insert_id;

            // Insert order items
            foreach ($sessionData['cart']['items'] as $item) {
                $sku = $db->real_escape_string($item['sku']);
                $name = $db->real_escape_string($item['name']);
                $price = $item['price'] ?? 0;
                $qty = $item['qty'] ?? 1;
                $rowTotal = $price * $qty;

                $db->query("INSERT INTO sales_order_item (
                    order_id, product_id, sku, name, product_type,
                    qty_ordered, qty_invoiced, qty_shipped, qty_canceled,
                    price, base_price, row_total, base_row_total,
                    created_at, updated_at
                ) VALUES (
                    {$orderId}, {$item['product_id']}, '{$sku}', '{$name}', 'simple',
                    {$qty}, 0, 0, 0,
                    {$price}, {$price}, {$rowTotal}, {$rowTotal},
                    NOW(), NOW()
                )");

                // Update inventory
                $db->query("UPDATE inventory_source_item SET quantity = quantity - {$qty} WHERE sku = '{$sku}' AND quantity >= {$qty}");
            }

            // Insert shipping address
            $street = $db->real_escape_string($shippingAddress['street'] ?? '');
            $city = $db->real_escape_string($shippingAddress['city'] ?? '');
            $postcode = $db->real_escape_string($shippingAddress['postcode'] ?? '');
            $phone = $db->real_escape_string($shippingAddress['telephone'] ?? '');

            $db->query("INSERT INTO sales_order_address (
                parent_id, address_type, firstname, lastname, street, city, postcode, telephone, region, country_id
            ) VALUES (
                {$orderId}, 'shipping', '{$customerName}', '', '{$street}', '{$city}', '{$postcode}', '{$phone}', '', 'DZ'
            )");

            // Commit transaction
            $db->commit();

            return [
                'success' => true,
                'order_id' => $orderId,
                'increment_id' => $incrementId,
                'grand_total' => $grandTotal,
            ];

        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Get next increment ID
     */
    private function getNextIncrementId(mysqli $db, string $env): string {
        $r = $db->query("SELECT MAX(CAST(increment_id AS UNSIGNED)) as max_id FROM sales_order WHERE increment_id REGEXP '^[0-9]+$'");
        $row = $r ? $r->fetch_assoc() : null;
        $nextId = ($row['max_id'] ?? 0) + 1;
        return str_pad($nextId, 9, '0', STR_PAD_LEFT);
    }

    /**
     * Get orders by customer email
     */
    public function getOrdersByEmail(string $env, string $email, int $limit = 10): array {
        $db = $this->getDb($env);
        if (!$db) return [];

        $email = $db->real_escape_string($email);
        $r = $db->query("SELECT increment_id, status, grand_total, created_at, shipping_method
                         FROM sales_order
                         WHERE customer_email = '{$email}'
                         ORDER BY created_at DESC
                         LIMIT {$limit}");

        $orders = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $orders[] = $row;
            }
        }

        return $orders;
    }

    /**
     * Get order details by increment_id
     */
    public function getOrderDetails(string $env, string $incrementId): ?array {
        $db = $this->getDb($env);
        if (!$db) return null;

        $incId = $db->real_escape_string($incrementId);
        $r = $db->query("SELECT * FROM sales_order WHERE increment_id = '{$incId}' LIMIT 1");
        $order = $r ? $r->fetch_assoc() : null;

        if (!$order) return null;

        // Get order items
        $r = $db->query("SELECT sku, name, qty_ordered, price, row_total FROM sales_order_item WHERE order_id = {$order['entity_id']}");
        $items = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $items[] = $row;
            }
        }

        $order['items'] = $items;
        return $order;
    }

    /**
     * Get recent orders (latest N orders)
     */
    public function getRecentOrders(string $env, int $limit = 10): array {
        $db = $this->getDb($env);
        if (!$db) return [];

        $r = $db->query("SELECT increment_id, status, grand_total, created_at, customer_email 
                         FROM sales_order 
                         WHERE status != 'canceled' 
                         ORDER BY created_at DESC 
                         LIMIT {$limit}");

        $orders = [];
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $orders[] = $row;
            }
        }

        return $orders;
    }

    /**
     * Get revenue statistics (today, last hour, active carts)
     */
    public function getOrdersByRegion(string $env, int $limit = 60): array {
        $db = $this->getDb($env);
        if (!$db) return ['error' => 'Cannot connect to database'];

        $result = [];
        $r = $db->query("
            SELECT oa.region, COUNT(*) AS cnt
            FROM sales_order_address oa
            JOIN sales_order so ON so.entity_id = oa.parent_id
            WHERE oa.address_type = 'shipping'
              AND oa.region IS NOT NULL AND oa.region != ''
              AND so.status NOT IN ('canceled', 'closed')
            GROUP BY oa.region
            ORDER BY cnt DESC
            LIMIT $limit
        ");
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $result[] = [
                    'region' => $row['region'],
                    'orders' => (int)$row['cnt'],
                ];
            }
        }

        return $result;
    }

    public function getMonthlyStats(string $env, int $months = 12): array {
        $db = $this->getDb($env);
        if (!$db) return ['error' => 'Cannot connect to database'];

        $result = [];
        $r = $db->query("
            SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym,
                   COUNT(*) AS cnt,
                   COALESCE(SUM(grand_total), 0) AS rev
            FROM sales_order
            WHERE status != 'canceled'
              AND created_at >= DATE_SUB(CURDATE(), INTERVAL $months MONTH)
            GROUP BY ym
            ORDER BY ym ASC
        ");
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $result[] = [
                    'month' => $row['ym'],
                    'orders' => (int)$row['cnt'],
                    'revenue' => (float)$row['rev'],
                ];
            }
        }

        return $result;
    }

    public function getRevenueStats(string $env): array {
        $db = $this->getDb($env);
        if (!$db) return ['error' => 'Cannot connect to database'];

        $result = [];

        // Today's orders
        $r = $db->query("SELECT COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as rev 
                         FROM sales_order 
                         WHERE created_at >= CURDATE() AND status != 'canceled'");
        if ($r && $row = $r->fetch_assoc()) {
            $result['today'] = [
                'count' => (int)$row['cnt'],
                'revenue' => (float)$row['rev']
            ];
        }

        // Last hour orders
        $r = $db->query("SELECT COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as rev 
                         FROM sales_order 
                         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) AND status != 'canceled'");
        if ($r && $row = $r->fetch_assoc()) {
            $result['last_hour'] = [
                'count' => (int)$row['cnt'],
                'revenue' => (float)$row['rev']
            ];
        }

        // Active carts (last 30 min)
        $r = $db->query("SELECT COUNT(*) as cnt, COALESCE(SUM(base_grand_total),0) as val 
                         FROM quote 
                         WHERE is_active = 1 AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
        if ($r && $row = $r->fetch_assoc()) {
            $result['active_carts'] = [
                'count' => (int)$row['cnt'],
                'value' => (float)$row['val']
            ];
        }

        return $result;
    }
}

<?php
/**
 * Bulk Actions API Endpoint
 * 
 * Handles CSV uploads for bulk actions like disabling products.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/PermissionChecker.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/DatabasePool.php';

Config::load();

if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!PermissionChecker::isAdmin() && !PermissionChecker::hasPermission('can_bulk_products')) {
    http_response_code(403);
    echo json_encode(['error' => 'Insufficient permissions for bulk product actions']);
    exit;
}

$action = $_POST['action'] ?? '';
$env = $_POST['env'] ?? 'prod';

if ($action === 'disable_products') {
    if (!isset($_FILES['csv_file'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No CSV file uploaded']);
        exit;
    }

    $file = $_FILES['csv_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'Upload error']);
        exit;
    }

    $skus = [];
    if (($handle = fopen($file['tmp_name'], "r")) !== FALSE) {
        $header = fgetcsv($handle);
        $skuIndex = 0;
        if ($header) {
            foreach ($header as $i => $col) {
                if (strtolower(trim($col)) === 'sku') {
                    $skuIndex = $i;
                    break;
                }
            }
        }
        
        fseek($handle, 0);
        $first = true;
        while (($data = fgetcsv($handle)) !== FALSE) {
            if ($first) {
                $first = false;
                if (strtolower(trim($data[$skuIndex] ?? '')) === 'sku') {
                    continue; // Skip header
                }
            }
            if (!empty($data[$skuIndex])) {
                $skus[] = trim($data[$skuIndex]);
            }
        }
        fclose($handle);
    }

    if (empty($skus)) {
        http_response_code(400);
        echo json_encode(['error' => 'No SKUs found in CSV']);
        exit;
    }

    try {
        $dbName = Config::get("db.{$env}");
        if (!$dbName) {
            throw new Exception("Invalid environment database");
        }
        
        $pdo = Config::getPDO($dbName);
        
        // Find attribute_id for status
        $stmt = $pdo->prepare("SELECT attribute_id FROM eav_attribute WHERE entity_type_id = 4 AND attribute_code = 'status'");
        $stmt->execute();
        $statusAttrId = $stmt->fetchColumn();

        if (!$statusAttrId) {
            throw new Exception("Status attribute not found");
        }

        // Get product IDs for SKUs
        $placeholders = str_repeat('?,', count($skus) - 1) . '?';
        $stmt = $pdo->prepare("SELECT entity_id, sku FROM catalog_product_entity WHERE sku IN ($placeholders)");
        $stmt->execute($skus);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $updatedCount = 0;
        $failedSkus = array_diff($skus, array_column($products, 'sku'));

        // 2 = Disabled
        $updateStmt = $pdo->prepare("
            INSERT INTO catalog_product_entity_int (entity_id, attribute_id, store_id, value) 
            VALUES (?, ?, 0, 2)
            ON DUPLICATE KEY UPDATE value = 2
        ");

        $pdo->beginTransaction();
        foreach ($products as $product) {
            $updateStmt->execute([$product['entity_id'], $statusAttrId]);
            $updatedCount++;
        }
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Successfully disabled $updatedCount products.",
            'updated_count' => $updatedCount,
            'not_found_skus' => array_values($failedSkus)
        ]);

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
}

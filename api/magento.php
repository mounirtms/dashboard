<?php
/**
 * Magento REST API Proxy - Standardized
 */

header('Content-Type: application/json', true);
require_once __DIR__ . '/session_helper.php';

// Authentication check
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Require configuration
require_once __DIR__ . '/config.php';
Config::load();

// Environment configurations
$environments = [
    'prod' => [
        'name' => 'Production',
        'base_url' => Config::get('paths.prod_url', 'https://technostationery.com'),
        'api_url' => Config::get('paths.prod_url', 'https://technostationery.com') . '/rest/V1',
        'token' => Config::get('magento.prod.token', ''),
    ],
    'beta' => [
        'name' => 'Beta',
        'base_url' => Config::get('paths.beta_url', 'https://beta.technostationery.com'),
        'api_url' => Config::get('paths.beta_url', 'https://beta.technostationery.com') . '/rest/V1',
        'token' => Config::get('magento.beta.token', ''),
    ],
    'tsdnd' => [
        'name' => 'TSDND',
        'base_url' => Config::get('paths.tsdnd_url', 'https://tsdnd.technostationery.com'),
        'api_url' => Config::get('paths.tsdnd_url', 'https://tsdnd.technostationery.com') . '/rest/V1',
        'token' => Config::get('magento.tsdnd.token', ''),
    ],
    'dev' => [
        'name' => 'Development',
        'base_url' => Config::get('paths.dev_url', 'https://dev.technostationery.com'),
        'api_url' => Config::get('paths.dev_url', 'https://dev.technostationery.com') . '/rest/V1',
        'token' => Config::get('magento.dev.token', ''),
    ],
    'pim' => [
        'name' => 'PIM (Akeneo)',
        'base_url' => Config::get('paths.pim_url', 'https://pim.technostationery.com'),
        'api_url' => Config::get('paths.pim_url', 'https://pim.technostationery.com') . '/api/rest/v1',
        'token' => Config::get('magento.pim.token', ''),
        'type' => 'akeneo',
    ],
];

// Get parameters
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$env = $_GET['env'] ?? 'prod';
$method = $_GET['method'] ?? ($_SERVER['REQUEST_METHOD'] === 'POST' ? 'POST' : 'GET');
$endpoint = $_GET['endpoint'] ?? $_GET['path'] ?? '';
$pageSize = $_GET['pageSize'] ?? $_GET['page_size'] ?? 20;
$currentPage = $_GET['currentPage'] ?? $_GET['page'] ?? 1;
$searchCriteria = $_GET['searchCriteria'] ?? null;

// Validate environment
if (!isset($environments[$env])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid environment', 'valid' => array_keys($environments)]);
    exit;
}

$envConfig = $environments[$env];

// Load Magento credentials from config file if exists
$configFile = __DIR__ . '/../config/magento_credentials.json';
if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true);
    if (isset($config[$env])) {
        $envConfig = array_merge($envConfig, $config[$env]);
    }
}

// Use MagentoToken auto-refresh for prod env; fallback to static token otherwise
$tokenEnv = "MAGENTO_TOKEN_" . strtoupper($env);
if ($env === 'prod' && file_exists(__DIR__ . '/magento-token.php')) {
    // Auto-refreshes when near expiry (within 1 h), writes fresh token back to credentials file
    try {
        require_once __DIR__ . '/magento-token.php';
        $token = MagentoToken::get();
        $envConfig['token'] = $token;
    } catch (Throwable $e) {
        // Fallback to whatever is in the credentials file
        $token = getenv($tokenEnv) ?: $envConfig['token'] ?? '';
        error_log('[magento.php] MagentoToken::get() failed: ' . $e->getMessage());
    }
} else {
    $token = getenv($tokenEnv) ?: $envConfig['token'] ?? '';
}

// Handle actions
switch ($action) {
    case 'login':
        handleLogin($envConfig);
        break;
    
    case 'test':
        handleTestConnection($envConfig);
        break;
    
    case 'status':
        handleStatus($envConfig, $env);
        break;
    
    case 'products':
        handleProducts($envConfig, $method, $endpoint, $pageSize, $currentPage);
        break;
    
    case 'orders':
        handleOrders($envConfig, $method, $endpoint, $pageSize, $currentPage);
        break;
    
    case 'customers':
        handleCustomers($envConfig, $method, $endpoint, $pageSize, $currentPage);
        break;
    
    case 'categories':
        handleCategories($envConfig, $method, $endpoint, $pageSize, $currentPage);
        break;
    
    case 'invoices':
        handleInvoices($envConfig, $method, $endpoint, $pageSize, $currentPage);
        break;
    
    case 'stock':
        handleStock($envConfig, $method, $endpoint, $pageSize, $currentPage);
        break;
    
    case 'cms':
        handleCms($envConfig, $method, $endpoint, $pageSize, $currentPage);
        break;
    
    case 'execute':
        handleCustomRequest($envConfig, $method, $endpoint);
        break;
    
    case 'indexer':
        handleIndexer($envConfig);
        break;
    
    case 'cache':
        handleCache($envConfig);
        break;
    
    case 'system':
        handleSystemInfo($envConfig);
        break;

    case 'product_save':
        handleProductSave($envConfig);
        break;

    case 'product_delete':
        handleProductDelete($envConfig);
        break;

    case 'product_bulk':
        handleProductBulk($envConfig);
        break;

    case 'customer_save':
        handleCustomerSave($envConfig);
        break;

    case 'customer_delete':
        handleCustomerDelete($envConfig);
        break;

    case 'order_action':
        handleOrderAction($envConfig);
        break;

    case 'cms_page_save':
        handleCmsPageSave($envConfig);
        break;

    case 'cms_page_delete':
        handleCmsPageDelete($envConfig);
        break;

    case 'cms_blocks':
        handleCmsBlocks($envConfig, $pageSize, $currentPage);
        break;

    case 'cms_block_save':
        handleCmsBlockSave($envConfig);
        break;

    case 'cms_block_delete':
        handleCmsBlockDelete($envConfig);
        break;

    case 'media_upload':
        handleMediaUpload($envConfig);
        break;

    case 'categories_tree':
        handleCategoriesTree($envConfig);
        break;

    case 'store_config':
        handleStoreConfig($envConfig);
        break;

    default:
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid action',
            'valid_actions' => [
                'login', 'test', 'status', 'products', 'orders', 'customers',
                'categories', 'invoices', 'stock', 'cms', 'execute', 'indexer',
                'cache', 'system', 'product_save', 'product_delete', 'product_bulk',
                'customer_save', 'customer_delete', 'order_action',
                'cms_page_save', 'cms_page_delete', 'cms_blocks', 'cms_block_save',
                'cms_block_delete', 'media_upload', 'categories_tree', 'store_config'
            ]
        ]);
        exit;
}

/**
 * Authenticate with Magento and get token
 */
function handleLogin($envConfig) {
    $username = $_POST['username'] ?? $envConfig['username'] ?? '';
    $password = $_POST['password'] ?? $envConfig['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Username and password required']);
        return;
    }
    
    $apiUrl = $envConfig['api_url'];
    $baseUrl = rtrim($apiUrl, '/rest/V1');
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/rest/V1/integration/admin/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'username' => $username,
        'password' => $password
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        http_response_code(500);
        echo json_encode(['error' => 'Connection error: ' . $error]);
        return;
    }
    
    if ($httpCode === 200) {
        echo json_encode([
            'success' => true,
            'token' => trim($response, '"'),
            'message' => 'Authentication successful'
        ]);
    } else {
        http_response_code($httpCode);
        echo json_encode([
            'error' => 'Authentication failed',
            'message' => $response,
            'http_code' => $httpCode
        ]);
    }
}

/**
 * Test connection to Magento
 */
function handleTestConnection($envConfig) {
    $token = $_GET['token'] ?? $envConfig['token'] ?? '';
    
    if (empty($token)) {
        http_response_code(400);
        echo json_encode(['error' => 'Token required for test']);
        return;
    }
    
    $apiUrl = $envConfig['api_url'];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl . '/products?searchCriteria[pageSize]=1');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo json_encode([
        'success' => $httpCode === 200,
        'http_code' => $httpCode,
        'error' => $error,
        'message' => $httpCode === 200 ? 'Connection successful' : 'Connection failed',
        'response_preview' => substr($response, 0, 500)
    ]);
}

/**
 * Get API status and info
 */
function handleStatus($envConfig, $env) {
    $token = $_GET['token'] ?? $envConfig['token'] ?? '';
    $hasToken = !empty($token);
    
    echo json_encode([
        'environment' => $env,
        'name' => $envConfig['name'],
        'api_url' => $envConfig['api_url'],
        'type' => $envConfig['type'] ?? 'magento',
        'authenticated' => $hasToken,
        'endpoints' => [
            'products' => '/api/magento.php?action=products&env=' . $env,
            'orders' => '/api/magento.php?action=orders&env=' . $env,
            'customers' => '/api/magento.php?action=customers&env=' . $env,
            'categories' => '/api/magento.php?action=categories&env=' . $env,
            'invoices' => '/api/magento.php?action=invoices&env=' . $env,
            'stock' => '/api/magento.php?action=stock&env=' . $env,
            'cms' => '/api/magento.php?action=cms&env=' . $env,
        ]
    ]);
}

/**
 * Proxy request to Magento API
 */
function magentoRequest($envConfig, $method, $endpoint, $token = null, $params = [], $body = null) {
    $token = $token ?: ($envConfig['token'] ?? '');
    $apiUrl = rtrim($envConfig['api_url'], '/');

    $url = $apiUrl . $endpoint;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $headers = ['Content-Type: application/json'];
    if (!empty($token)) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    if ($body !== null && in_array($method, ['POST', 'PUT'])) {
        $jsonBody = is_string($body) ? $body : json_encode($body);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'response' => $response,
        'http_code' => $httpCode,
        'error' => $error,
        'success' => $httpCode >= 200 && $httpCode < 300
    ];
}

/**
 * Handle products request
 */
function handleProducts($envConfig, $method, $endpoint, $pageSize, $currentPage) {
    $token = $_GET['token'] ?? $envConfig['token'] ?? '';
    
    if (empty($token)) {
        http_response_code(401);
        echo json_encode(['error' => 'Magento API token not configured']);
        return;
    }
    
    // Build search criteria
    $params = [
        'searchCriteria[pageSize]' => $pageSize,
        'searchCriteria[currentPage]' => $currentPage,
    ];
    
    // Add custom endpoint if provided
    $apiEndpoint = $endpoint ?: '/products';
    
    $result = magentoRequest($envConfig, $method, $apiEndpoint, $token, $params);
    
    if ($result['http_code'] === 401) {
        http_response_code(200);
        echo json_encode([
            'error' => 'Magento API token rejected (401). Please regenerate the token in Magento Admin → System → Integrations.',
            'magento_http_code' => 401,
            'items' => [],
            'total_count' => 0,
        ]);
        return;
    }
    if ($result['http_code'] === 403) {
        http_response_code(200);
        echo json_encode([
            'error' => 'Magento API token lacks permission for products (403). Ensure the integration has Magento_Catalog scope.',
            'magento_http_code' => 403,
            'items' => [],
            'total_count' => 0,
        ]);
        return;
    }
    
    http_response_code($result['http_code']);
    
    if ($result['error']) {
        echo json_encode(['error' => $result['error']]);
        return;
    }
    
    $data = json_decode($result['response'], true);
    
    // Format response
    if (isset($data['items'])) {
        echo json_encode([
            'items' => $data['items'],
            'total_count' => $data['total_count'] ?? count($data['items']),
            'search_criteria' => $data['search_criteria'] ?? []
        ]);
    } else {
        echo $result['response'];
    }
}

/**
 * Handle orders request
 */
function handleOrders($envConfig, $method, $endpoint, $pageSize, $currentPage) {
    $token = $_GET['token'] ?? $envConfig['token'] ?? '';
    
    if (empty($token)) {
        http_response_code(401);
        echo json_encode(['error' => 'Magento API token not configured. Please add a token in Settings → Magento API.']);
        return;
    }
    
    $params = [
        'searchCriteria[pageSize]' => $pageSize,
        'searchCriteria[currentPage]' => $currentPage,
        'searchCriteria[sortOrders][0][field]' => 'created_at',
        'searchCriteria[sortOrders][0][direction]' => 'DESC',
    ];
    
    $apiEndpoint = $endpoint ?: '/orders';
    
    $result = magentoRequest($envConfig, $method, $apiEndpoint, $token, $params);
    
    if ($result['http_code'] === 401) {
        http_response_code(200);
        echo json_encode([
            'error' => 'Magento API token rejected (401). Token may have expired or lacks Magento_Sales::sales_order permission. Please regenerate the token in Magento Admin → System → Integrations.',
            'magento_http_code' => 401,
            'items' => [],
            'total_count' => 0,
        ]);
        return;
    }
    if ($result['http_code'] === 403) {
        http_response_code(200);
        echo json_encode([
            'error' => 'Magento API token lacks permission for orders (403). Please ensure the integration has Magento_Sales scope in Magento Admin → System → Integrations.',
            'magento_http_code' => 403,
            'items' => [],
            'total_count' => 0,
        ]);
        return;
    }
    
    http_response_code($result['http_code']);
    echo $result['response'] ?: json_encode(['error' => $result['error']]);
}

/**
 * Handle customers request
 */
function handleCustomers($envConfig, $method, $endpoint, $pageSize, $currentPage) {
    $token = $_GET['token'] ?? $envConfig['token'] ?? '';
    
    if (empty($token)) {
        http_response_code(401);
        echo json_encode(['error' => 'Magento API token not configured. Please add a token in Settings → Magento API.']);
        return;
    }
    
    $params = [
        'searchCriteria[pageSize]' => $pageSize,
        'searchCriteria[currentPage]' => $currentPage,
        'searchCriteria[sortOrders][0][field]' => 'created_at',
        'searchCriteria[sortOrders][0][direction]' => 'DESC',
    ];
    
    $apiEndpoint = $endpoint ?: '/customers/search';
    
    $result = magentoRequest($envConfig, $method, $apiEndpoint, $token, $params);
    
    if ($result['http_code'] === 401) {
        http_response_code(200);
        echo json_encode([
            'error' => 'Magento API token rejected (401). Token may have expired or lacks Magento_Customer permission. Please regenerate the token in Magento Admin → System → Integrations.',
            'magento_http_code' => 401,
            'items' => [],
            'total_count' => 0,
        ]);
        return;
    }
    if ($result['http_code'] === 403) {
        http_response_code(200);
        echo json_encode([
            'error' => 'Magento API token lacks permission for customers (403). Please ensure the integration has Magento_Customer scope in Magento Admin → System → Integrations.',
            'magento_http_code' => 403,
            'items' => [],
            'total_count' => 0,
        ]);
        return;
    }
    
    http_response_code($result['http_code']);
    echo $result['response'] ?: json_encode(['error' => $result['error']]);
}

/**
 * Handle categories request
 */
function handleCategories($envConfig, $method, $endpoint, $pageSize, $currentPage) {
    $token = $_GET['token'] ?? $envConfig['token'] ?? '';
    
    if (empty($token)) {
        http_response_code(401);
        echo json_encode(['error' => 'Magento API token not configured']);
        return;
    }
    
    $apiEndpoint = $endpoint ?: '/categories';
    
    $result = magentoRequest($envConfig, $method, $apiEndpoint, $token);
    
    http_response_code($result['http_code']);
    echo $result['response'] ?: json_encode(['error' => $result['error']]);
}

/**
 * Handle invoices request
 */
function handleInvoices($envConfig, $method, $endpoint, $pageSize, $currentPage) {
    $token = $_GET['token'] ?? $envConfig['token'] ?? '';
    
    if (empty($token)) {
        http_response_code(401);
        echo json_encode(['error' => 'Magento API token not configured']);
        return;
    }
    
    $params = [
        'searchCriteria[pageSize]' => $pageSize,
        'searchCriteria[currentPage]' => $currentPage,
    ];
    
    $apiEndpoint = $endpoint ?: '/invoices';
    
    $result = magentoRequest($envConfig, $method, $apiEndpoint, $token, $params);
    
    http_response_code($result['http_code']);
    echo $result['response'] ?: json_encode(['error' => $result['error']]);
}

/**
 * Handle stock request
 */
function handleStock($envConfig, $method, $endpoint, $pageSize, $currentPage) {
    $token = $_GET['token'] ?? $envConfig['token'] ?? '';
    
    if (empty($token)) {
        http_response_code(401);
        echo json_encode(['error' => 'Magento API token not configured']);
        return;
    }
    
    $params = [
        'searchCriteria[pageSize]' => $pageSize,
        'searchCriteria[currentPage]' => $currentPage,
    ];
    
    $apiEndpoint = $endpoint ?: '/stockItems';
    
    $result = magentoRequest($envConfig, $method, $apiEndpoint, $token, $params);
    
    http_response_code($result['http_code']);
    echo $result['response'] ?: json_encode(['error' => $result['error']]);
}

/**
 * Handle CMS pages/blocks request
 */
function handleCms($envConfig, $method, $endpoint, $pageSize, $currentPage) {
    $token = $_GET['token'] ?? $envConfig['token'] ?? '';
    
    if (empty($token)) {
        http_response_code(401);
        echo json_encode(['error' => 'Magento API token not configured']);
        return;
    }
    
    $params = [
        'searchCriteria[pageSize]' => $pageSize,
        'searchCriteria[currentPage]' => $currentPage,
    ];
    
    $apiEndpoint = $endpoint ?: '/cmsPage/search';
    
    $result = magentoRequest($envConfig, $method, $apiEndpoint, $token, $params);
    
    http_response_code($result['http_code']);
    echo $result['response'] ?: json_encode(['error' => $result['error']]);
}

/**
 * Handle custom request
 */
function handleCustomRequest($envConfig, $method, $endpoint) {
    $token = $_GET['token'] ?? $envConfig['token'] ?? '';
    
    if (empty($token)) {
        http_response_code(401);
        echo json_encode(['error' => 'Magento API token not configured']);
        return;
    }
    
    if (empty($endpoint)) {
        http_response_code(400);
        echo json_encode(['error' => 'Endpoint required for custom request']);
        return;
    }
    
    // Get POST body if present
    $postData = null;
    if ($method === 'POST' || $method === 'PUT') {
        $rawInput = file_get_contents('php://input');
        if (!empty($rawInput)) {
            $postData = json_decode($rawInput, true);
        }
    }
    
    $result = magentoRequest($envConfig, $method, $endpoint, $token);
    
    http_response_code($result['http_code']);
    echo $result['response'] ?: json_encode(['error' => $result['error']]);
}

/**
 * Handle indexer status
 */
function handleIndexer($envConfig) {
    $token = $_GET['token'] ?? $envConfig['token'] ?? '';
    
    if (empty($token)) {
        http_response_code(401);
        echo json_encode(['error' => 'Magento API token not configured']);
        return;
    }
    
    $result = magentoRequest($envConfig, 'GET', '/indexer/status', $token);
    
    http_response_code($result['http_code']);
    echo $result['response'] ?: json_encode(['error' => $result['error']]);
}

/**
 * Handle cache operations
 */
function handleCache($envConfig) {
    $token = $_GET['token'] ?? $envConfig['token'] ?? '';
    $operation = $_GET['op'] ?? 'status';
    
    if (empty($token)) {
        http_response_code(401);
        echo json_encode(['error' => 'Magento API token not configured']);
        return;
    }
    
    $endpoint = '/cache/status';
    if ($operation === 'flush') {
        $endpoint = '/cache/flush';
    } elseif ($operation === 'clean') {
        $endpoint = '/cache/clean';
    }
    
    $method = ($operation === 'status') ? 'GET' : 'POST';
    
    $result = magentoRequest($envConfig, $method, $endpoint, $token);
    
    http_response_code($result['http_code']);
    echo $result['response'] ?: json_encode(['error' => $result['error']]);
}

/**
 * Handle system info
 */
function handleSystemInfo($envConfig) {
    $token = $_GET['token'] ?? $envConfig['token'] ?? '';

    if (empty($token)) {
        http_response_code(401);
        echo json_encode(['error' => 'Magento API token not configured']);
        return;
    }

    $result = magentoRequest($envConfig, 'GET', '/store/storeConfigs', $token);

    http_response_code($result['http_code']);
    echo $result['response'] ?: json_encode(['error' => $result['error']]);
}

function requireToken($envConfig) {
    $token = $_GET['token'] ?? $envConfig['token'] ?? '';
    if (empty($token)) {
        http_response_code(401);
        echo json_encode(['error' => 'Magento API token not configured']);
        return null;
    }
    return $token;
}

function getRequestBody() {
    $raw = file_get_contents('php://input');
    if (empty($raw)) return null;
    return json_decode($raw, true);
}

function proxyResult($result) {
    http_response_code($result['http_code']);
    echo $result['response'] ?: json_encode(['error' => $result['error']]);
}

function handleProductSave($envConfig) {
    $token = requireToken($envConfig);
    if (!$token) return;

    $body = getRequestBody();
    if (empty($body) || empty($body['product'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Product data required in request body']);
        return;
    }

    $sku = $body['product']['sku'] ?? $_GET['sku'] ?? '';
    if (empty($sku)) {
        http_response_code(400);
        echo json_encode(['error' => 'SKU required']);
        return;
    }

    $method = isset($body['product']['id']) ? 'PUT' : 'POST';
    $result = magentoRequest($envConfig, $method, '/products/' . urlencode($sku), $token, [], $body);
    proxyResult($result);
}

function handleProductDelete($envConfig) {
    $token = requireToken($envConfig);
    if (!$token) return;

    $sku = $_GET['sku'] ?? '';
    if (empty($sku)) {
        http_response_code(400);
        echo json_encode(['error' => 'SKU required']);
        return;
    }

    $result = magentoRequest($envConfig, 'DELETE', '/products/' . urlencode($sku), $token);
    proxyResult($result);
}

function handleProductBulk($envConfig) {
    $token = requireToken($envConfig);
    if (!$token) return;

    $body = getRequestBody();
    if (empty($body)) {
        http_response_code(400);
        echo json_encode(['error' => 'Bulk operation data required']);
        return;
    }

    $result = magentoRequest($envConfig, 'POST', '/products/bulk', $token, [], $body);
    proxyResult($result);
}

function handleCustomerSave($envConfig) {
    $token = requireToken($envConfig);
    if (!$token) return;

    $body = getRequestBody();
    if (empty($body) || empty($body['customer'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Customer data required']);
        return;
    }

    $customerId = $body['customer']['id'] ?? null;
    if ($customerId) {
        $result = magentoRequest($envConfig, 'PUT', '/customers/' . $customerId, $token, [], $body);
    } else {
        $result = magentoRequest($envConfig, 'POST', '/customers', $token, [], $body);
    }
    proxyResult($result);
}

function handleCustomerDelete($envConfig) {
    $token = requireToken($envConfig);
    if (!$token) return;

    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Customer ID required']);
        return;
    }

    $result = magentoRequest($envConfig, 'DELETE', '/customers/' . (int)$id, $token);
    proxyResult($result);
}

function handleOrderAction($envConfig) {
    $token = requireToken($envConfig);
    if (!$token) return;

    $orderId = $_GET['id'] ?? '';
    $op = $_GET['op'] ?? '';

    if (empty($orderId) || empty($op)) {
        http_response_code(400);
        echo json_encode(['error' => 'Order ID and operation required (op: cancel, hold, unhold, ship, invoice, comment)']);
        return;
    }

    $endpoints = [
        'cancel'  => ['POST', "/orders/{$orderId}/cancel"],
        'hold'    => ['POST', "/orders/{$orderId}/hold"],
        'unhold'  => ['POST', "/orders/{$orderId}/unhold"],
        'ship'    => ['POST', "/order/{$orderId}/ship"],
        'invoice' => ['POST', "/order/{$orderId}/invoice"],
    ];

    if ($op === 'comment') {
        $body = getRequestBody();
        $result = magentoRequest($envConfig, 'POST', "/orders/{$orderId}/comments", $token, [], $body);
    } elseif (isset($endpoints[$op])) {
        [$method, $endpoint] = $endpoints[$op];
        $body = ($op === 'ship' || $op === 'invoice') ? getRequestBody() : null;
        $result = magentoRequest($envConfig, $method, $endpoint, $token, [], $body);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid operation', 'valid' => array_keys($endpoints + ['comment' => true])]);
        return;
    }

    proxyResult($result);
}

function handleCmsPageSave($envConfig) {
    $token = requireToken($envConfig);
    if (!$token) return;

    $body = getRequestBody();
    if (empty($body) || empty($body['page'])) {
        http_response_code(400);
        echo json_encode(['error' => 'CMS page data required']);
        return;
    }

    $pageId = $body['page']['id'] ?? null;
    if ($pageId) {
        $result = magentoRequest($envConfig, 'PUT', '/cmsPage/' . $pageId, $token, [], $body);
    } else {
        $result = magentoRequest($envConfig, 'POST', '/cmsPage', $token, [], $body);
    }
    proxyResult($result);
}

function handleCmsPageDelete($envConfig) {
    $token = requireToken($envConfig);
    if (!$token) return;

    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'CMS page ID required']);
        return;
    }

    $result = magentoRequest($envConfig, 'DELETE', '/cmsPage/' . (int)$id, $token);
    proxyResult($result);
}

function handleCmsBlocks($envConfig, $pageSize, $currentPage) {
    $token = requireToken($envConfig);
    if (!$token) return;

    $params = [
        'searchCriteria[pageSize]' => $pageSize,
        'searchCriteria[currentPage]' => $currentPage,
    ];

    $result = magentoRequest($envConfig, 'GET', '/cmsBlock/search', $token, $params);
    proxyResult($result);
}

function handleCmsBlockSave($envConfig) {
    $token = requireToken($envConfig);
    if (!$token) return;

    $body = getRequestBody();
    if (empty($body) || empty($body['block'])) {
        http_response_code(400);
        echo json_encode(['error' => 'CMS block data required']);
        return;
    }

    $blockId = $body['block']['id'] ?? null;
    if ($blockId) {
        $result = magentoRequest($envConfig, 'PUT', '/cmsBlock/' . $blockId, $token, [], $body);
    } else {
        $result = magentoRequest($envConfig, 'POST', '/cmsBlock', $token, [], $body);
    }
    proxyResult($result);
}

function handleCmsBlockDelete($envConfig) {
    $token = requireToken($envConfig);
    if (!$token) return;

    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'CMS block ID required']);
        return;
    }

    $result = magentoRequest($envConfig, 'DELETE', '/cmsBlock/' . (int)$id, $token);
    proxyResult($result);
}

function handleMediaUpload($envConfig) {
    $token = requireToken($envConfig);
    if (!$token) return;

    $sku = $_GET['sku'] ?? '';
    if (empty($sku)) {
        http_response_code(400);
        echo json_encode(['error' => 'Product SKU required']);
        return;
    }

    $body = getRequestBody();
    if (empty($body) || empty($body['entry'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Media entry data required (entry.media_type, entry.label, entry.file with base64)']);
        return;
    }

    $result = magentoRequest($envConfig, 'POST', '/products/' . urlencode($sku) . '/media', $token, [], $body);
    proxyResult($result);
}

function handleCategoriesTree($envConfig) {
    $token = requireToken($envConfig);
    if (!$token) return;

    $result = magentoRequest($envConfig, 'GET', '/categories', $token);
    proxyResult($result);
}

function handleStoreConfig($envConfig) {
    $token = requireToken($envConfig);
    if (!$token) return;

    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'PUT') {
        $body = getRequestBody();
        $result = magentoRequest($envConfig, 'PUT', '/store/storeConfigs', $token, [], $body);
    } else {
        $result = magentoRequest($envConfig, 'GET', '/store/storeConfigs', $token);
    }
    proxyResult($result);
}

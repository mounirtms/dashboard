<?php
/**
 * Magento REST API Proxy
 * 
 * Provides authenticated access to Magento REST API endpoints
 * Supports all 5 environments with configurable credentials
 * 
 * Usage: /api/magento.php?action=products&env=prod&method=GET&endpoint=/V1/products
 */

session_start();
header('Content-Type: application/json');

// Authentication check
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Environment configurations
$environments = [
    'prod' => [
        'name' => 'Production',
        'base_url' => 'https://technostationery.com',
        'api_url' => 'https://technostationery.com/rest/V1',
        'token' => '', // Set via admin or env var
        'username' => '',
        'password' => '',
    ],
    'beta' => [
        'name' => 'Beta',
        'base_url' => 'https://beta.technostationery.com',
        'api_url' => 'https://beta.technostationery.com/rest/V1',
        'token' => '',
        'username' => '',
        'password' => '',
    ],
    'dev' => [
        'name' => 'Development',
        'base_url' => 'https://dev.technostationery.com',
        'api_url' => 'https://dev.technostationery.com/rest/V1',
        'token' => '',
        'username' => '',
        'password' => '',
    ],
    'pim' => [
        'name' => 'PIM (Akeneo)',
        'base_url' => 'https://pim.technostationery.com',
        'api_url' => 'https://pim.technostationery.com/api/rest/v1',
        'token' => '',
        'username' => '',
        'password' => '',
        'type' => 'akeneo',
    ],
    'lms' => [
        'name' => 'LMS',
        'base_url' => 'https://lms.technostationery.com',
        'api_url' => 'https://lms.technostationery.com/api',
        'token' => '',
        'username' => '',
        'password' => '',
        'type' => 'lms',
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

// Also check environment variables
$tokenEnv = "MAGENTO_TOKEN_" . strtoupper($env);
$token = getenv($tokenEnv) ?: $envConfig['token'] ?? '';

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
    
    default:
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid action',
            'valid_actions' => [
                'login', 'test', 'status', 'products', 'orders', 'customers',
                'categories', 'invoices', 'stock', 'cms', 'execute', 'indexer',
                'cache', 'system'
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
function magentoRequest($envConfig, $method, $endpoint, $token = null, $params = []) {
    $token = $token ?: ($envConfig['token'] ?? '');
    $apiUrl = rtrim($envConfig['api_url'], '/');
    
    // Build URL with query params
    $url = $apiUrl . $endpoint;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $headers = [
        'Content-Type: application/json',
    ];
    
    if (!empty($token)) {
        $headers[] = 'Authorization: Bearer ' . $token;
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
        echo json_encode(['error' => 'Magento API token not configured']);
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
        echo json_encode(['error' => 'Magento API token not configured']);
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

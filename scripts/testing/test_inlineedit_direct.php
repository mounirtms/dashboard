<?php
// Test InlineEdit URL accessibility
require __DIR__ . '/app/bootstrap.php';

$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);

// Simulate POST request to InlineEdit
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'items' => [
        '52' => [
            'yalidin_token' => 'test_token_12345'
        ]
    ],
    'isAjax' => true
];
$_GET = ['isAjax' => true];

$app = $bootstrap->createApplication(\Magento\Framework\App\Http::class);

echo "Testing InlineEdit controller accessibility...\n";
echo "POST data: " . json_encode($_POST) . "\n\n";

try {
    $response = $bootstrap->run($app);
    echo "Response received\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n\nCheck var/log/inlineedit_debug.log for logs\n";

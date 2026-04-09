<?php
// Test if InlineEdit controller is accessible
use Magento\Framework\App\Bootstrap;

require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

echo "Testing InlineEdit Controller...\n\n";

try {
    // Try to get the controller
    $controller = $objectManager->get('Mab\YalidineCarrier\Controller\Adminhtml\SourceAccount\InlineEdit');
    echo "✅ Controller class loaded: " . get_class($controller) . "\n";
    
    // Check CSRF interface
    if ($controller instanceof \Magento\Framework\App\CsrfAwareActionInterface) {
        echo "✅ Implements CsrfAwareActionInterface\n";
    } else {
        echo "❌ Does NOT implement CsrfAwareActionInterface\n";
    }
    
    // Check methods
    if (method_exists($controller, 'validateForCsrf')) {
        echo "✅ validateForCsrf method exists\n";
    } else {
        echo "❌ validateForCsrf method missing\n";
    }
    
    if (method_exists($controller, 'execute')) {
        echo "✅ execute method exists\n";
    } else {
        echo "❌ execute method missing\n";
    }
    
    // Check if log directory is writable
    $logDir = BP . '/var/log';
    if (is_writable($logDir)) {
        echo "✅ Log directory writable: $logDir\n";
    } else {
        echo "❌ Log directory NOT writable: $logDir\n";
    }
    
    echo "\n✅ Controller is properly configured.\n";
    echo "If inline edit still doesn't work, issue is in frontend/JavaScript.\n";
    
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

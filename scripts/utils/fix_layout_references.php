<?php
/**
 * Script to diagnose and fix common layout reference issues in Magento
 */
use Magento\Framework\App\Bootstrap;

require __DIR__ . '/../../app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

echo "Starting layout reference diagnostic...\n";

// Check for common layout issues
$layoutDir = BP . '/app/design/frontend/';
$customLayouts = [];

if (is_dir($layoutDir)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($layoutDir)
    );
    
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'xml') {
            $content = file_get_contents($file->getPathname());
            
            // Look for common problematic patterns
            if (strpos($content, 'referenceContainer') !== false || 
                strpos($content, 'referenceBlock') !== false) {
                
                echo "Found potential layout issues in: {$file->getPathname()}\n";
                $customLayouts[] = $file->getPathname();
            }
        }
    }
}

echo "Checking theme configuration...\n";

// Check current theme
$config = $objectManager->get(\Magento\Framework\App\Config\ScopeConfigInterface::class);
$currentTheme = $config->getValue('design/theme/theme_id');

echo "Current theme ID: {$currentTheme}\n";

// Clear layout cache
$cacheTypeList = $objectManager->get(\Magento\Framework\App\Cache\TypeListInterface::class);
$cacheTypeList->cleanType('layout');
echo "Layout cache cleared.\n";

// Re-deploy static content
echo "Re-deploying static content...\n";
exec('php bin/magento setup:static-content:deploy --force', $output, $result);

if ($result === 0) {
    echo "Static content deployed successfully.\n";
} else {
    echo "Error deploying static content.\n";
    print_r($output);
}

echo "Layout reference diagnostic completed.\n";
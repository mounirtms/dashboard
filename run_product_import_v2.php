<?php
// Alternative script to run Magento product import programmatically

use Magento\Framework\App\Bootstrap;

require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

// Set area code
$state = $objectManager->get(\Magento\Framework\App\State::class);
$state->setAreaCode('adminhtml');

// Get the product import model
$productImport = $objectManager->create(\Magento\CatalogImportExport\Model\Import\Product::class);

// Configure import settings
$config = [
    'entity' => 'catalog_product',
    'behavior' => 'add_update', // Options: add_update, replace, delete
    'validation_strategy' => 'stop_on_first_error',
    'allowed_error_count' => 10,
];

try {
    // Set import data
    $productImport->setParameters($config);

    // Set up the source file
    $source = new \Magento\ImportExport\Model\Import\Source\Csv(
        BP . '/var/import/techno_pens_import.csv',
        ',',
        '"',
        \Magento\Framework\Convert\Excel::DEFAULT_LINE_LENGTH
    );
    
    $productImport->setSource($source);
    
    echo "Validating import source...\n";
    
    // Validate the import
    $validationResult = $productImport->validateData();
    
    if ($validationResult) {
        echo "Validation passed. Starting import...\n";
        
        // Perform the import
        $result = $productImport->importData();
        
        if ($result) {
            echo "Import completed successfully!\n";
            
            // Get error aggregator to check for any issues
            $errorAggregator = $productImport->getErrorAggregator();
            if ($errorAggregator->hasToBeDisplayed()) {
                echo "There were some errors during import:\n";
                foreach ($errorAggregator->getAllErrors() as $error) {
                    echo "Row " . $error->getRowNumber() . ": " . $error->getErrorMessage() . "\n";
                }
            } else {
                echo "No errors reported during import.\n";
            }
        } else {
            echo "Import failed.\n";
        }
    } else {
        echo "Validation failed. Import cannot proceed.\n";
        
        $errorAggregator = $productImport->getErrorAggregator();
        if ($errorAggregator->hasToBeDisplayed()) {
            echo "Validation errors:\n";
            foreach ($errorAggregator->getAllErrors() as $error) {
                echo "Row " . $error->getRowNumber() . ": " . $error->getErrorMessage() . "\n";
            }
        }
    }
} catch (Exception $e) {
    echo "Exception during import: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
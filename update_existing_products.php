<?php
// Magento product update script using the cleaned CSV

use Magento\Framework\App\Bootstrap;

require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

// Set area code
$state = $objectManager->get(\Magento\Framework\App\State::class);
$state->setAreaCode('adminhtml');

// Get the product import model
$productImport = $objectManager->create(\Magento\CatalogImportExport\Model\Import\Product::class);

try {
    // Configure import for updating existing products
    $productImport->setParameters([
        'entity' => 'catalog_product',
        'behavior' => 'add_update', // This will update existing products
        'validation_strategy' => 'validation-skip-errors',
        'allowed_error_count' => 10,
        '_import_field_separator' => ',',
        '_import_multiple_value_separator' => ',',
        '_import_empty_attribute_value_constant' => '__EMPTY__VALUE__',
    ]);

    // Set up the source file (using the cleaned CSV)
    $source = $objectManager->create(
        \Magento\ImportExport\Model\Import\Source\Csv::class,
        [
            'file' => BP . '/canvas - Techno pens (1)_cleaned.csv',
            'delimiter' => ',',
            'enclosure' => '"',
            'escape' => '\\'
        ]
    );

    $productImport->setSource($source);

    echo "Starting validation of the CSV data...\n";
    
    // Validate the import data
    $validationResult = $productImport->validateData();
    
    if ($validationResult) {
        echo "Validation passed. Starting product update...\n";
        
        // Perform the import/update
        $result = $productImport->importData();
        
        if ($result) {
            echo "Product update completed successfully!\n";
        } else {
            echo "Product update failed.\n";
        }
        
        // Check for any errors during import
        $errorAggregator = $productImport->getErrorAggregator();
        if ($errorAggregator->hasToBeDisplayed()) {
            echo "Update completed with " . $errorAggregator->getErrorsCount() . " errors/warnings:\n";
            foreach ($errorAggregator->getAllErrors() as $error) {
                $message = $error->getErrorMessage();
                if (is_array($message)) {
                    $message = implode(', ', $message);
                }
                echo "  - Row " . $error->getRowNumber() . ": " . $message . "\n";
            }
        } else {
            echo "No errors reported during update.\n";
        }
    } else {
        echo "Validation failed. Cannot proceed with import.\n";
        
        $errorAggregator = $productImport->getErrorAggregator();
        if ($errorAggregator->hasToBeDisplayed()) {
            echo "Validation errors:\n";
            foreach ($errorAggregator->getAllErrors() as $error) {
                $message = $error->getErrorMessage();
                if (is_array($message)) {
                    $message = implode(', ', $message);
                }
                echo "  - Row " . $error->getRowNumber() . ": " . $message . "\n";
            }
        }
    }
    
    // Print summary statistics
    echo "\nSummary:\n";
    echo "  - Processed rows: " . $productImport->getProcessedRowsCount() . "\n";
    echo "  - Created products: " . $productImport->getCreatedProductsCount() . "\n";
    echo "  - Updated products: " . $productImport->getUpdatedProductsCount() . "\n";
    echo "  - Deleted products: " . $productImport->getDeletedProductsCount() . "\n";
    
} catch (Exception $e) {
    echo "Exception during product update: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "Product update process completed.\n";
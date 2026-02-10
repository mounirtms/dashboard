<?php
// Script to import configurable products with their variants using simplified CSV

use Magento\Framework\App\Bootstrap;

require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

// Set area code
$state = $objectManager->get(\Magento\Framework\App\State::class);
$state->setAreaCode('adminhtml');

// Get the import facade
$importFacade = $objectManager->get(\Magento\ImportExport\Model\Import::class);

try {
    // Set import data for configurable products
    $importFacade->setData([
        'entity' => 'catalog_product',
        'behavior' => 'add_update', // add_update, replace, delete
        'validation_strategy' => 'validation-skip-errors',
        'allowed_error_count' => 10,
        '_import_field_separator' => ',',
        '_import_multiple_value_separator' => ',',
        '_import_empty_attribute_value_constant' => '__EMPTY__VALUE__',
    ]);

    // Create source from the simplified CSV file
    $source = $objectManager->create(
        \Magento\ImportExport\Model\Import\Source\Csv::class,
        [
            'file' => BP . '/var/import/configurable_techno_pens_simple.csv',
            'delimiter' => ',',
            'enclosure' => '"',
            'escape' => '\\'
        ]
    );

    $importFacade->setSource($source);

    echo "Validating configurable product import data...\n";
    
    // Validate the import
    $validationResult = $importFacade->validateSource($source);

    if ($validationResult->getErrorsCount() > 0) {
        echo "Validation failed with " . $validationResult->getErrorsCount() . " errors:\n";
        foreach ($validationResult->getErrors() as $error) {
            echo "  - " . $error->getTitle() . ": " . $error->getErrorMessage() . "\n";
        }
    } else {
        echo "Validation passed. Starting configurable product import...\n";
        
        // Perform import
        $importResult = $importFacade->importSource();
        
        if ($importResult) {
            echo "Configurable product import completed successfully!\n";
        } else {
            echo "Configurable product import failed.\n";
        }
        
        // Check for errors
        $errorAggregator = $importFacade->getErrorAggregator();
        if ($errorAggregator->hasToBeDisplayed()) {
            echo "Import completed with " . $errorAggregator->getErrorsCount() . " errors:\n";
            foreach ($errorAggregator->getAllErrors() as $error) {
                echo "  - Row " . $error->getRowNumber() . ": " . $error->getErrorMessage() . "\n";
            }
        } else {
            echo "No errors reported during import.\n";
        }
    }
    
    // Print summary
    echo "\nSummary:\n";
    echo "  - Processed entities: " . $importFacade->getProcessedEntitiesCount() . "\n";
    echo "  - Processed rows: " . $importFacade->getProcessedRowsCount() . "\n";
    
} catch (\Exception $e) {
    echo "Exception occurred: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "Configurable product import process completed.\n";
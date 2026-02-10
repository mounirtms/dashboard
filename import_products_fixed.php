<?php
// Magento CLI import script

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
    // Set import data
    $importFacade->setData([
        'entity' => 'catalog_product',
        'behavior' => 'add_update', // add_update, replace, delete
        'validation_strategy' => 'validation-skip-errors',
        'allowed_error_count' => 10,
    ]);

    // Create source from CSV file
    $source = $objectManager->create(
        \Magento\ImportExport\Model\Import\Source\Csv::class,
        [
            'file' => BP . '/var/import/techno_pens_fixed_import.csv',
            'delimiter' => ',',
            'enclosure' => '"',
            'escape' => '\\'
        ]
    );

    $importFacade->setSource($source);

    // Validate the import
    echo "Validating import data...\n";
    $validationResult = $importFacade->validateSource($source);

    if ($validationResult->getErrorsCount() > 0) {
        echo "Validation failed with " . $validationResult->getErrorsCount() . " errors:\n";
        foreach ($validationResult->getErrors() as $error) {
            echo "  - " . $error->getTitle() . ": " . $error->getErrorMessage() . "\n";
        }
    } else {
        echo "Validation passed. Starting import...\n";
        
        // Perform import
        $importResult = $importFacade->importSource();
        
        if ($importResult) {
            echo "Import completed successfully!\n";
        } else {
            echo "Import failed.\n";
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
    echo "Processed entities: " . $importFacade->getProcessedEntitiesCount() . "\n";
    echo "Processed rows: " . $importFacade->getProcessedRowsCount() . "\n";
    
} catch (\Exception $e) {
    echo "Exception occurred: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
<?php
// Script to run Magento product import programmatically

use Magento\Framework\App\Bootstrap;
use Magento\ImportExport\Model\Import;

require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

// Set area code
$state = $objectManager->get(\Magento\Framework\App\State::class);
$state->setAreaCode('adminhtml');

// Get the import model
$importModel = $objectManager->create(\Magento\ImportExport\Model\Import::class);

// Configuration for the import
$importData = [
    'entity' => 'catalog_product',
    'behavior' => 'append', // Options: append, replace, delete
    'validation_strategy' => 'validation-stop-on-error',
    'allowed_error_count' => 10,
    '_import_field_separator' => ',',
    '_import_multiple_value_separator' => ',',
    '_import_empty_attribute_value_constant' => '__EMPTY__VALUE__',
];

// Set import data
$importModel->setData($importData);

// Set import source (the CSV file)
$source = $objectManager->create(
    \Magento\ImportExport\Model\Import\Source\Csv::class,
    [
        'file' => BP . '/var/import/techno_pens_import.csv',
        'delimiter' => ','
    ]
);

$importModel->setSource($source);

// Validate the import
$result = $importModel->validateSource($source);

if ($result->getErrorsCount() > 0) {
    echo "Validation errors:\n";
    foreach ($result->getErrors() as $error) {
        echo "- " . $error->getTitle() . ": " . $error->getErrorMessage() . "\n";
    }
} else {
    echo "Validation passed. Starting import...\n";
    
    // Run the import
    $importModel->importSource();
    
    // Check for errors after import
    $errorAggregator = $importModel->getErrorAggregator();
    
    if ($errorAggregator->hasToBeDisplayed()) {
        echo "Import completed with errors:\n";
        foreach ($errorAggregator->getAllErrors() as $error) {
            echo "- Row " . $error->getRowNumber() . ": " . $error->getErrorMessage() . "\n";
        }
    } else {
        echo "Import completed successfully!\n";
    }
    
    // Print statistics
    $stats = $importModel->getProcessedRowsCount();
    echo "Processed rows: " . $stats . "\n";
}

echo "Import process finished.\n";
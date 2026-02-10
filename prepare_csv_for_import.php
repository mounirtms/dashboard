<?php
// Simple script to copy the fixed CSV to the import directory and trigger Magento import

use Magento\Framework\App\Bootstrap;

require __DIR__ . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

// Copy the fixed CSV to the import directory
$sourceFile = BP . '/canvas - Techno pens (1)_cleaned.csv';
$destFile = BP . '/var/import/techno_pens_update.csv';

if (!copy($sourceFile, $destFile)) {
    die("Failed to copy CSV file to import directory\n");
}

echo "CSV file copied to import directory: $destFile\n";
echo "File exists: " . (file_exists($destFile) ? 'YES' : 'NO') . "\n";
echo "File size: " . filesize($destFile) . " bytes\n";

// Now you can use Magento's admin panel to import this file, or use the CLI command:
echo "\nTo import this file, you can now:\n";
echo "1. Go to Magento Admin > System > Import\n";
echo "2. Select Entity Type: Products\n";
echo "3. Import Behavior: Add/Update Complex Data\n";
echo "4. Select the file: techno_pens_update.csv\n";
echo "5. Check 'Field separator' is comma and 'Multiple value separator' is comma\n";
echo "6. Click 'Check Data' then 'Import'\n\n";

echo "Alternatively, you can try running: php bin/magento import:entities --type=catalog_product --behavior=add_update --file=$destFile\n";
echo "(Note: The exact CLI command may vary depending on your Magento version and extensions)\n";
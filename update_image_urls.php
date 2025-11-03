<?php

// Script to update CSV with full URLs for images
// Usage: php update_image_urls.php input.csv output.csv

if ($argc < 3) {
    echo "Usage: php update_image_urls.php input.csv output.csv\n";
    exit(1);
}

$inputFile = $argv[1];
$outputFile = $argv[2];
$baseUrl = 'https://technostationery.com/pub/media/amasty/amcustomform/';

// Check if input file exists
if (!file_exists($inputFile)) {
    echo "Input file does not exist: $inputFile\n";
    exit(1);
}

// Open input file
if (($inputHandle = fopen($inputFile, 'r')) === FALSE) {
    echo "Cannot open input file: $inputFile\n";
    exit(1);
}

// Open output file
if (($outputHandle = fopen($outputFile, 'w')) === FALSE) {
    echo "Cannot create output file: $outputFile\n";
    fclose($inputHandle);
    exit(1);
}

// Read the header
$header = fgetcsv($inputHandle);
if ($header === FALSE) {
    echo "Cannot read header from input file\n";
    fclose($inputHandle);
    fclose($outputHandle);
    exit(1);
}

// Find the column index for "Photo de l'œuvre /صورة العمل الفني"
$imageColumnIndex = -1;
foreach ($header as $index => $columnName) {
    if (trim($columnName) === 'Photo de l\'œuvre /صورة العمل الفني') {
        $imageColumnIndex = $index;
        break;
    }
}

if ($imageColumnIndex === -1) {
    echo "Column 'Photo de l'œuvre /صورة العمل الفني' not found in CSV\n";
    fclose($inputHandle);
    fclose($outputHandle);
    exit(1);
}

// Write the header to output file
fputcsv($outputHandle, $header);

// Process each row
$rowNumber = 1; // Header is row 1
while (($row = fgetcsv($inputHandle)) !== FALSE) {
    $rowNumber++;
    
    // Update the image column if it has a value
    if (isset($row[$imageColumnIndex]) && !empty(trim($row[$imageColumnIndex]))) {
        $imageName = trim($row[$imageColumnIndex]);
        // Only update if it doesn't already have a full URL
        if (strpos($imageName, 'http') !== 0) {
            $row[$imageColumnIndex] = $baseUrl . $imageName;
        }
    }
    
    // Write the updated row to output file
    if (fputcsv($outputHandle, $row) === FALSE) {
        echo "Error writing row $rowNumber to output file\n";
        fclose($inputHandle);
        fclose($outputHandle);
        exit(1);
    }
}

fclose($inputHandle);
fclose($outputHandle);

echo "CSV file updated successfully. Output saved to: $outputFile\n";
?>

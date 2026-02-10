<?php
// More detailed CSV analysis to check for potential parsing issues

$filename = '/home/technadminy7/public_html/canvas - Techno pens (1).csv';

echo "Analyzing original CSV file: $filename\n";

$content = file_get_contents($filename);
if ($content === false) {
    echo "Cannot read file: $filename\n";
    exit(1);
}

$lines = explode("\n", $content);
foreach ($lines as $lineNum => $line) {
    if (trim($line) === '') continue; // Skip empty lines
    
    echo "Line " . ($lineNum + 1) . ": ";
    
    // Count fields using str_getcsv which mimics fgetcsv but without file handling
    $fields = str_getcsv($line);
    $fieldCount = count($fields);
    
    echo "$fieldCount fields";
    
    // Check if field at index 4 exists
    if (isset($fields[4])) {
        echo ", field[4] exists";
    } else {
        echo ", field[4] MISSING - THIS IS THE PROBLEM!";
    }
    
    // Look for potential problematic characters
    $problems = [];
    if (substr_count($line, '"') % 2 !== 0) {
        $problems[] = "odd quote count";
    }
    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $line)) {
        $problems[] = "control chars";
    }
    
    if (!empty($problems)) {
        echo ", PROBLEMS: " . implode(", ", $problems);
    }
    
    echo "\n";
    
    // Show preview of fields 0-4
    for ($i = 0; $i < min(5, $fieldCount); $i++) {
        $fieldPreview = strlen($fields[$i]) > 30 ? substr($fields[$i], 0, 30) . '...' : $fields[$i];
        echo "  [$i]: '$fieldPreview'\n";
    }
    
    if ($lineNum >= 10) { // Limit output
        echo "... (truncated)\n";
        break;
    }
}

echo "\nAnalysis completed.\n";
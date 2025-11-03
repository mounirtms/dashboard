<?php
/**
 * Process Missing Images Script (Corrected Version)
 * 
 * This script processes the CSV file and organizes images according to the requirements:
 * 1. Match images in the folder with the "ref" column
 * 2. Rename images according to the "Image Name" column
 * 3. Handle duplicate images with _2, _3 suffixes
 * 4. Resize images
 * 5. Place images in the correct Magento folder structure
 */

// Configuration
$csvFile = '/home/technadminy7/public_html/Missing Images - missing .csv';
$imagesDir = '/home/technadminy7/public_html/images';
$mediaBaseDir = '/home/technadminy7/public_html/pub/media/catalog/product';
$maxWidth = 1400; // Maximum width for resized images

echo "🚀 Starting image processing script...\n";

// Create media directory if it doesn't exist
if (!is_dir($mediaBaseDir)) {
    mkdir($mediaBaseDir, 0755, true);
    echo "📁 Created media base directory: $mediaBaseDir\n";
}

// Read CSV file
echo "🔍 Reading CSV file: $csvFile\n";
$csvData = [];
if (($handle = fopen($csvFile, 'r')) !== false) {
    // Read header
    $header = fgetcsv($handle);
    echo "📋 CSV Header: " . implode(', ', $header) . "\n";
    
    // Read data
    $rowCount = 0;
    while (($data = fgetcsv($handle)) !== false && $rowCount < 100) { // Limit to first 100 rows for testing
        $csvData[] = [
            'sku' => $data[0],
            'image_name' => $data[1],
            'ref' => $data[2]
        ];
        $rowCount++;
    }
    fclose($handle);
} else {
    die("❌ Could not open CSV file: $csvFile\n");
}

echo "📊 Found " . count($csvData) . " entries in CSV (limited to first 100 for testing)\n";

// Get list of all images in the images directory
echo "🔍 Scanning images directory: $imagesDir\n";
$imageFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($imagesDir));
foreach ($iterator as $file) {
    if ($file->isFile() && in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png'])) {
        $imageFiles[] = $file->getPathname();
    }
}

echo "📊 Found " . count($imageFiles) . " image files\n";

// Show first few image files for verification
echo "📋 First 5 image files:\n";
for ($i = 0; $i < min(5, count($imageFiles)); $i++) {
    echo "  " . basename($imageFiles[$i]) . "\n";
}

// Process each entry in the CSV
$processedCount = 0;
$errorCount = 0;
$matchCount = 0;

foreach ($csvData as $entry) {
    $sku = $entry['sku'];
    $imageName = $entry['image_name'];
    $ref = $entry['ref'];
    
    // Skip if ref is empty
    if (empty($ref)) {
        echo "⚠️ Skipping SKU $sku - empty ref\n";
        continue;
    }
    
    // Find matching images
    $matchingImages = [];
    foreach ($imageFiles as $imageFile) {
        $fileName = basename($imageFile);
        // Check if the filename starts with the ref (with or without extension)
        if (strpos($fileName, $ref) === 0 || strpos($fileName, $ref . '_') === 0) {
            $matchingImages[] = $imageFile;
        }
    }
    
    if (empty($matchingImages)) {
        // Only show this for first few entries to avoid spam
        if ($matchCount < 10) {
            echo "🔍 No matching images found for SKU $sku with ref $ref\n";
        }
        $matchCount++;
        continue;
    }
    
    echo "✅ Found " . count($matchingImages) . " matching image(s) for SKU $sku with ref $ref\n";
    $matchCount++;
    
    // Process each matching image
    foreach ($matchingImages as $index => $sourceImagePath) {
        // Determine the final image name
        $finalImageName = $imageName;
        if ($index > 0) {
            $finalImageName .= '_' . ($index + 1);
        }
        
        // Get file extension
        $extension = pathinfo($sourceImagePath, PATHINFO_EXTENSION);
        $finalImageName .= '.' . $extension;
        
        // Create Magento folder structure (first two letters of filename)
        $firstChar = strtolower(substr($finalImageName, 0, 1));
        $secondChar = strtolower(substr($finalImageName, 1, 1));
        $targetDir = $mediaBaseDir . '/' . $firstChar . '/' . $secondChar;
        
        // Create directory if it doesn't exist
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
            echo "📁 Created directory: $targetDir\n";
        }
        
        // Full target path
        $targetPath = $targetDir . '/' . $finalImageName;
        
        // Check if file already exists and add suffix if needed
        $suffix = 1;
        $originalTargetPath = $targetPath;
        while (file_exists($targetPath)) {
            $suffix++;
            $nameWithoutExt = pathinfo($finalImageName, PATHINFO_FILENAME);
            $extension = pathinfo($finalImageName, PATHINFO_EXTENSION);
            $targetPath = $targetDir . '/' . $nameWithoutExt . '_' . $suffix . '.' . $extension;
        }
        
        // Copy the image to the target location
        if (copy($sourceImagePath, $targetPath)) {
            echo "✅ Copied image for SKU $sku: " . basename($sourceImagePath) . " → " . basename($targetPath) . "\n";
            
            // Try to resize the image
            if (resizeImage($targetPath, $maxWidth)) {
                echo "✅ Resized image: " . basename($targetPath) . "\n";
            } else {
                echo "⚠️ Failed to resize image: " . basename($targetPath) . " (continuing without resize)\n";
            }
            
            $processedCount++;
        } else {
            echo "❌ Failed to copy image for SKU $sku: " . basename($sourceImagePath) . "\n";
            $errorCount++;
        }
    }
    
    // Limit processing for testing
    if ($processedCount >= 20) {
        echo "🛑 Stopping processing after 20 images for testing purposes\n";
        break;
    }
}

echo "\n📊 Processing Summary:\n";
echo "✅ Processed: $processedCount images\n";
echo "❌ Errors: $errorCount\n";
echo "🔍 Matches found: $matchCount entries\n";

/**
 * Resize an image to maximum width while maintaining aspect ratio
 */
function resizeImage($imagePath, $maxWidth) {
    // Check if file exists
    if (!file_exists($imagePath)) {
        return false;
    }
    
    // Get image information
    $imageInfo = getimagesize($imagePath);
    if ($imageInfo === false) {
        return false;
    }
    
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    
    // Check if resizing is needed
    if ($width <= $maxWidth) {
        return true; // No resizing needed
    }
    
    // Calculate new dimensions
    $newWidth = $maxWidth;
    $newHeight = intval($height * ($maxWidth / $width));
    
    // Create image resource based on mime type
    $sourceImage = null;
    switch ($imageInfo['mime']) {
        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($imagePath);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($imagePath);
            break;
        default:
            return false;
    }
    
    if ($sourceImage === false) {
        return false;
    }
    
    // Create resized image
    $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG images
    if ($imageInfo['mime'] === 'image/png') {
        imagealphablending($resizedImage, false);
        imagesavealpha($resizedImage, true);
        $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
        imagefilledrectangle($resizedImage, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    // Resize the image
    imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    // Save the resized image
    $result = false;
    switch ($imageInfo['mime']) {
        case 'image/jpeg':
            $result = imagejpeg($resizedImage, $imagePath, 85);
            break;
        case 'image/png':
            $result = imagepng($resizedImage, $imagePath, 6);
            break;
    }
    
    // Free memory
    imagedestroy($sourceImage);
    imagedestroy($resizedImage);
    
    return $result;
}
?>
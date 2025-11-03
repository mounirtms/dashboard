<?php
// Convert JPG/PNG to WebP
$sourceImage = 'pub/media/wysiwyg/slidershow/techno/CATALOGUE_TECHNO_BUREAU_2023_min_mob_520.webp';
$destinationImage = 'pub/media/amasty/webp/wysiwyg/slidershow/techno/CATALOGUE_TECHNO_BUREAU_2023_min_mob_520.webp';

// Check if source image exists
if (!file_exists($sourceImage)) {
    echo "Source image does not exist: $sourceImage\n";
    exit(1);
}

// Get image info
$imageInfo = getimagesize($sourceImage);
if ($imageInfo === false) {
    echo "Cannot get image information\n";
    exit(1);
}

// Create image resource based on mime type
switch ($imageInfo['mime']) {
    case 'image/webp':
        $image = imagecreatefromwebp($sourceImage);
        break;
    case 'image/jpeg':
        $image = imagecreatefromjpeg($sourceImage);
        break;
    case 'image/png':
        $image = imagecreatefrompng($sourceImage);
        break;
    default:
        echo "Unsupported image type: " . $imageInfo['mime'] . "\n";
        exit(1);
}

if ($image === false) {
    echo "Failed to create image resource\n";
    exit(1);
}

// Save as WebP
$result = imagewebp($image, $destinationImage, 80); // Quality 80%

// Free memory
imagedestroy($image);

if ($result) {
    echo "Successfully converted image to WebP format: $destinationImage\n";
} else {
    echo "Failed to convert image to WebP format\n";
    exit(1);
}
?>
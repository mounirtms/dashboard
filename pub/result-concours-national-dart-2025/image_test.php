<?php
// Test image paths
echo "<h1>Image Path Test</h1>";

// Test if images are accessible
$testImages = [
    'inbound5603197906901684346.jpg',
    'IMG_20251022_164806_282.jpg',
    'home_office.jpg'
];

foreach ($testImages as $image) {
    $path = '/home/technadminy7/public_html/pub/media/amasty/amcustomform/' . $image;
    $url = '/pub/media/amasty/amcustomform/' . $image;
    
    echo "<h2>Testing: $image</h2>";
    echo "<p>File exists: " . (file_exists($path) ? 'Yes' : 'No') . "</p>";
    echo "<p>File size: " . (file_exists($path) ? filesize($path) : 'N/A') . " bytes</p>";
    echo "<p>URL: <a href='$url' target='_blank'>$url</a></p>";
    
    if (file_exists($path)) {
        echo "<img src='$url' style='max-width: 200px; max-height: 200px;' onerror='this.onerror=null;this.src=\"https://via.placeholder.com/200x200/ff0000/ffffff?text=Error\";'>";
    }
    echo "<hr>";
}
?>
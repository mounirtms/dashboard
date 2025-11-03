<?php
// Script to fix Amasty form submission issues

echo "<h1>Amasty Form Submission Fix</h1>";

// Fix permissions for the Amasty custom form directory
$uploadDir = '/home/technadminy7/public_html/pub/media/amasty/amcustomform/';

echo "<h2>Checking and fixing directory permissions...</h2>";

// Check current permissions
echo "<p>Current permissions: " . substr(sprintf('%o', fileperms($uploadDir)), -4) . "</p>";

// Set proper permissions (775 for directories)
if (chmod($uploadDir, 0775)) {
    echo "<p style='color: green;'>Successfully set permissions to 775</p>";
} else {
    echo "<p style='color: red;'>Failed to set permissions</p>";
}

// Check ownership
$fileInfo = posix_getpwuid(fileowner($uploadDir));
$userInfo = posix_getpwuid(posix_geteuid());

echo "<p>Directory owner: " . $fileInfo['name'] . "</p>";
echo "<p>Current user: " . $userInfo['name'] . "</p>";

// Check if we can create a test file
$testFile = $uploadDir . 'test_permission_fix.txt';
if (file_put_contents($testFile, "Test file for permission fix")) {
    echo "<p style='color: green;'>Successfully created test file - permissions are correct</p>";
    unlink($testFile); // Remove the test file
} else {
    echo "<p style='color: red;'>Failed to create test file - permissions issue remains</p>";
}

// Check PHP configuration
echo "<h2>PHP Configuration Check</h2>";
$uploadMaxFilesize = ini_get('upload_max_filesize');
$postMaxSize = ini_get('post_max_size');

echo "<p>upload_max_filesize: " . $uploadMaxFilesize . "</p>";
echo "<p>post_max_size: " . $postMaxSize . "</p>";

// Check if there's a mismatch that could cause issues
$uploadBytes = returnBytes($uploadMaxFilesize);
$postBytes = returnBytes($postMaxSize);

if ($uploadBytes > $postBytes) {
    echo "<p style='color: orange;'>Warning: upload_max_filesize is larger than post_max_size. This may cause upload issues.</p>";
} else {
    echo "<p style='color: green;'>upload_max_filesize is properly configured relative to post_max_size.</p>";
}

// Function to convert PHP ini values to bytes
function returnBytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int) $val;
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

echo "<h2>Recommendations</h2>";
echo "<ul>";
echo "<li>If form submission still fails, check the exception.log and system.log files for specific error messages</li>";
echo "<li>Ensure the web server user has write permissions to the amcustomform directory</li>";
echo "<li>Verify that uploaded files do not exceed the upload_max_filesize limit</li>";
echo "<li>Check that the form configuration in the Magento admin panel is correct</li>";
echo "</ul>";

echo "<p><a href='display.php'>Back to Gallery</a></p>";
?>
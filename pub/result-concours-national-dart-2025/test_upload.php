<?php
// Test script to check file upload permissions

echo "<h1>File Upload Permission Test</h1>";

// Check if the directory is writable
$uploadDir = '/home/technadminy7/public_html/pub/media/amasty/amcustomform/';
echo "<p>Upload directory: " . $uploadDir . "</p>";

if (is_writable($uploadDir)) {
    echo "<p style='color: green;'>Directory is writable</p>";
} else {
    echo "<p style='color: red;'>Directory is NOT writable</p>";
}

// Check PHP upload settings
echo "<h2>PHP Upload Settings</h2>";
echo "<p>upload_max_filesize: " . ini_get('upload_max_filesize') . "</p>";
echo "<p>post_max_size: " . ini_get('post_max_size') . "</p>";
echo "<p>max_file_uploads: " . ini_get('max_file_uploads') . "</p>";

// Check if we can create a test file
$testFile = $uploadDir . 'test_permission.txt';
if (file_put_contents($testFile, "Test file for permission check")) {
    echo "<p style='color: green;'>Successfully created test file</p>";
    unlink($testFile); // Remove the test file
} else {
    echo "<p style='color: red;'>Failed to create test file</p>";
}

echo "<h2>Directory Permissions</h2>";
echo "<pre>";
echo shell_exec('ls -ld ' . escapeshellarg($uploadDir));
echo "</pre>";

echo "<h2>PHP User</h2>";
echo "<pre>";
echo shell_exec('whoami');
echo "</pre>";
?>
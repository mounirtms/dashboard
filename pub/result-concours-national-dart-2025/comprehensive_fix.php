<?php
// Comprehensive solution to fix Amasty form submission issues

echo "<h1>Comprehensive Amasty Form Submission Fix</h1>";

// 1. Fix directory permissions
$uploadDir = '/home/technadminy7/public_html/pub/media/amasty/amcustomform/';
echo "<h2>1. Fixing Directory Permissions</h2>";

// Set proper permissions (775 for directories)
if (chmod($uploadDir, 0775)) {
    echo "<p style='color: green;'>✓ Successfully set permissions to 775 for amcustomform directory</p>";
} else {
    echo "<p style='color: red;'>✗ Failed to set permissions for amcustomform directory</p>";
}

// 2. Fix PHP configuration mismatch
echo "<h2>2. PHP Configuration Issues</h2>";
$uploadMaxFilesize = ini_get('upload_max_filesize');
$postMaxSize = ini_get('post_max_size');

echo "<p>Current upload_max_filesize: " . $uploadMaxFilesize . "</p>";
echo "<p>Current post_max_size: " . $postMaxSize . "</p>";

$uploadBytes = returnBytes($uploadMaxFilesize);
$postBytes = returnBytes($postMaxSize);

if ($uploadBytes > $postBytes) {
    echo "<p style='color: orange;'>⚠ Warning: upload_max_filesize is larger than post_max_size. This can cause upload failures.</p>";
    echo "<p style='color: orange;'>Recommendation: Set post_max_size to be larger than upload_max_filesize in php.ini</p>";
} else {
    echo "<p style='color: green;'>✓ upload_max_filesize is properly configured relative to post_max_size.</p>";
}

// 3. Check and fix ownership issues
echo "<h2>3. Ownership Check</h2>";
$fileInfo = posix_getpwuid(fileowner($uploadDir));
$userInfo = posix_getpwuid(posix_geteuid());

echo "<p>Directory owner: " . $fileInfo['name'] . "</p>";
echo "<p>Web server user: " . $userInfo['name'] . "</p>";

// 4. Test file creation
echo "<h2>4. File Creation Test</h2>";
$testFile = $uploadDir . 'test_permission_fix.txt';
if (file_put_contents($testFile, "Test file for permission fix - " . date('Y-m-d H:i:s'))) {
    echo "<p style='color: green;'>✓ Successfully created test file - permissions are correct</p>";
    unlink($testFile); // Remove the test file
} else {
    echo "<p style='color: red;'>✗ Failed to create test file - permissions issue remains</p>";
}

// 5. Check for existing files and their permissions
echo "<h2>5. Existing Files Check</h2>";
$files = glob($uploadDir . "*.{jpg,jpeg,png,gif,pdf}", GLOB_BRACE);
$fileCount = count($files);
echo "<p>Found $fileCount image/PDF files in the directory</p>";

if ($fileCount > 0) {
    $sampleFile = $files[0];
    $perms = substr(sprintf('%o', fileperms($sampleFile)), -4);
    echo "<p>Sample file permissions: $perms</p>";
    
    // Check if we can read a sample file
    if (is_readable($sampleFile)) {
        echo "<p style='color: green;'>✓ Sample file is readable</p>";
    } else {
        echo "<p style='color: red;'>✗ Sample file is not readable</p>";
    }
}

// 6. Recommendations
echo "<h2>6. Recommendations</h2>";
echo "<ol>";
echo "<li><strong>Check exception.log and system.log</strong> for specific error messages related to form submissions</li>";
echo "<li><strong>Adjust PHP settings</strong> in php.ini: set post_max_size to be larger than upload_max_filesize</li>";
echo "<li><strong>Verify web server user permissions</strong> to ensure it can write to the amcustomform directory</li>";
echo "<li><strong>Test form submission</strong> with a small file to isolate the issue</li>";
echo "<li><strong>Check form configuration</strong> in Magento admin panel for any validation rules that might be failing</li>";
echo "</ol>";

echo "<h2>7. Additional Debugging Steps</h2>";
echo "<ul>";
echo "<li>Enable developer mode in Magento to get more detailed error messages</li>";
echo "<li>Check browser console for JavaScript errors during form submission</li>";
echo "<li>Verify that the form key is being properly generated and submitted</li>";
echo "<li>Check if there are any custom validation rules that might be causing the failure</li>";
echo "</ul>";

echo "<p><a href='display.php'>← Back to Gallery</a></p>";

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
?>
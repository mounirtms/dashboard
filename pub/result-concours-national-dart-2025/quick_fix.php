<?php
// Quick fix for Amasty form submission error

echo "<h1>Quick Fix for Amasty Form ID 9 Submission Error</h1>";

// 1. Ensure the amasty custom form directory exists
$uploadDir = '/home/technadminy7/public_html/pub/media/amasty/amcustomform/';

echo "<h2>1. Checking Directory Structure</h2>";

// Create directory if it doesn't exist
if (!file_exists($uploadDir)) {
    if (mkdir($uploadDir, 0775, true)) {
        echo "<p style='color: green;'>✓ Created directory: $uploadDir</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to create directory: $uploadDir</p>";
        exit;
    }
} else {
    echo "<p style='color: green;'>✓ Directory already exists: $uploadDir</p>";
}

// 2. Set proper permissions
echo "<h2>2. Setting Permissions</h2>";
if (chmod($uploadDir, 0775)) {
    echo "<p style='color: green;'>✓ Set permissions to 775 for amcustomform directory</p>";
} else {
    echo "<p style='color: red;'>✗ Failed to set permissions for amcustomform directory</p>";
}

// 3. Set proper ownership
echo "<h2>3. Setting Ownership</h2>";
// We'll use chown in the terminal, but we can check the current owner
$fileInfo = posix_getpwuid(fileowner($uploadDir));
$userInfo = posix_getpwuid(posix_geteuid());

echo "<p>Current directory owner: " . $fileInfo['name'] . "</p>";
echo "<p>Current user: " . $userInfo['name'] . "</p>";

// 4. Test file creation
echo "<h2>4. Testing File Creation</h2>";
$testFile = $uploadDir . 'quick_fix_test.txt';
if (file_put_contents($testFile, "Test file created at " . date('Y-m-d H:i:s'))) {
    echo "<p style='color: green;'>✓ Successfully created test file</p>";
    unlink($testFile); // Remove the test file
} else {
    echo "<p style='color: red;'>✗ Failed to create test file</p>";
}

// 5. Check PHP configuration
echo "<h2>5. PHP Configuration</h2>";
$uploadMaxFilesize = ini_get('upload_max_filesize');
$postMaxSize = ini_get('post_max_size');

echo "<p>upload_max_filesize: " . $uploadMaxFilesize . "</p>";
echo "<p>post_max_size: " . $postMaxSize . "</p>";

// 6. Recommendations
echo "<h2>6. Additional Recommendations</h2>";
echo "<ul>";
echo "<li>Clear Magento cache after this fix</li>";
echo "<li>Check browser console for JavaScript errors during form submission</li>";
echo "<li>If the issue persists, check Magento logs (var/log/) for specific error messages</li>";
echo "<li>Verify form configuration in Magento admin panel</li>";
echo "</ul>";

echo "<h2>7. Next Steps</h2>";
echo "<p>You can now try submitting the form again. If you still encounter issues:</p>";
echo "<ol>";
echo "<li>Clear Magento cache by running: php bin/magento cache:flush</li>";
echo "<li>Check the browser's developer console for any JavaScript errors</li>";
echo "<li>Review the form configuration in the Magento admin panel</li>";
echo "</ol>";

echo "<p><a href='display.php'>← Back to Gallery</a> | <a href='index.html'>← Back to Form</a></p>";

echo "<h2>8. Technical Details</h2>";
echo "<p>The error 'Server error occurred while saving form data. Please try again later or use Contact Us link in the menu.' was likely caused by:</p>";
echo "<ul>";
echo "<li>Missing amasty/amcustomform directory for file uploads</li>";
echo "<li>Incorrect directory permissions preventing file writes</li>";
echo "<li>PHP configuration issues with upload limits</li>";
echo "</ul>";
?>
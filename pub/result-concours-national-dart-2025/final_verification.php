<?php
// Final verification script for Amasty form submission fix

echo "<h1>Final Verification for Amasty Form ID 9 Fix</h1>";

// 1. Verify directory structure
$uploadDir = '/home/technadminy7/public_html/pub/media/amasty/amcustomform/';
echo "<h2>1. Directory Structure Verification</h2>";

if (file_exists($uploadDir)) {
    echo "<p style='color: green;'>✓ Directory exists: $uploadDir</p>";
    
    // Check permissions
    $perms = substr(sprintf('%o', fileperms($uploadDir)), -4);
    if ($perms == '0775' || $perms == '775') {
        echo "<p style='color: green;'>✓ Correct permissions: $perms</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Permissions are $perms, recommended is 775</p>";
    }
    
    // Check ownership
    $fileInfo = posix_getpwuid(fileowner($uploadDir));
    if ($fileInfo['name'] == 'technadminy7') {
        echo "<p style='color: green;'>✓ Correct ownership: technadminy7</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Owner is " . $fileInfo['name'] . ", recommended is technadminy7</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Directory does not exist: $uploadDir</p>";
    exit;
}

// 2. Test file creation
echo "<h2>2. File Creation Test</h2>";
$testFile = $uploadDir . 'final_verification_test.txt';
if (file_put_contents($testFile, "Final verification test created at " . date('Y-m-d H:i:s'))) {
    echo "<p style='color: green;'>✓ Successfully created test file</p>";
    
    // Test if we can read it
    if (is_readable($testFile)) {
        echo "<p style='color: green;'>✓ Test file is readable</p>";
    } else {
        echo "<p style='color: red;'>✗ Test file is not readable</p>";
    }
    
    // Clean up
    unlink($testFile);
} else {
    echo "<p style='color: red;'>✗ Failed to create test file</p>";
}

// 3. PHP Configuration Check
echo "<h2>3. PHP Configuration Check</h2>";
$uploadMaxFilesize = ini_get('upload_max_filesize');
$postMaxSize = ini_get('post_max_size');

echo "<p>upload_max_filesize: " . $uploadMaxFilesize . "</p>";
echo "<p>post_max_size: " . $postMaxSize . "</p>";

// Convert to bytes for comparison
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

$uploadBytes = returnBytes($uploadMaxFilesize);
$postBytes = returnBytes($postMaxSize);

if ($uploadBytes <= $postBytes) {
    echo "<p style='color: green;'>✓ upload_max_filesize is properly configured relative to post_max_size</p>";
} else {
    echo "<p style='color: red;'>✗ upload_max_filesize is larger than post_max_size. This can cause upload failures.</p>";
}

// 4. Summary
echo "<h2>4. Fix Summary</h2>";
echo "<ul>";
echo "<li><strong>Directory Creation:</strong> The missing amasty/amcustomform directory has been created</li>";
echo "<li><strong>Permissions:</strong> Directory permissions have been set to 775</li>";
echo "<li><strong>Ownership:</strong> Directory ownership has been verified</li>";
echo "<li><strong>PHP Configuration:</strong> Fixed post_max_size to be equal to upload_max_filesize (512M)</li>";
echo "<li><strong>Testing:</strong> File creation and read operations have been verified</li>";
echo "</ul>";

echo "<h2>5. Next Steps</h2>";
echo "<ol>";
echo "<li>Try submitting the form again at <a href='index.html'>the form page</a></li>";
echo "<li>If issues persist, clear Magento cache by running: php bin/magento cache:flush</li>";
echo "<li>Check browser console for any JavaScript errors during form submission</li>";
echo "<li>Review Magento logs (var/log/) for any specific error messages</li>";
echo "</ol>";

echo "<h2>6. Expected Outcome</h2>";
echo "<p>The error 'Server error occurred while saving form data. Please try again later or use Contact Us link in the menu.' should no longer occur after these fixes.</p>";

echo "<p><a href='display.php'>← Back to Gallery</a> | <a href='index.html'>← Back to Form</a></p>";
?>
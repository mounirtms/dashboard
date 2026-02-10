<?php
// OneSignal Configuration Verification Script
// This script verifies that the OneSignal configuration has been properly added

echo "<h1>OneSignal Configuration Verification</h1>";

// Check if the configuration exists in config.php
$configFile = '/home/technadminy7/public_html/app/etc/config.php';
if (file_exists($configFile)) {
    $configContent = file_get_contents($configFile);
    if (strpos($configContent, 'OneSignalSDK.page.js') !== false) {
        echo "<p style='color: green;'>✅ OneSignal script found in config.php</p>";
    } else {
        echo "<p style='color: red;'>❌ OneSignal script NOT found in config.php</p>";
    }
    
    if (strpos($configContent, 'ea60f1be-864c-4710-9437-3288e8e06cc4') !== false) {
        echo "<p style='color: green;'>✅ OneSignal App ID found in config.php</p>";
    } else {
        echo "<p style='color: red;'>❌ OneSignal App ID NOT found in config.php</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Config file not found</p>";
}

// Check if static content was deployed
$staticDir = '/home/technadminy7/public_html/pub/static/frontend/Sm/market/fr_FR';
if (is_dir($staticDir)) {
    echo "<p style='color: green;'>✅ Static content directory exists</p>";
    
    // Check for recently modified files
    $recentFiles = glob($staticDir . '/*');
    if (!empty($recentFiles)) {
        $latestFile = array_reduce($recentFiles, function($carry, $item) {
            return (is_file($item) && (!$carry || filemtime($item) > filemtime($carry))) ? $item : $carry;
        });
        if ($latestFile) {
            $modTime = date('Y-m-d H:i:s', filemtime($latestFile));
            echo "<p style='color: green;'>✅ Latest static file modified: $modTime</p>";
        }
    }
} else {
    echo "<p style='color: red;'>❌ Static content directory not found</p>";
}

// Check cache status
echo "<h2>Cache Status</h2>";
exec('cd /home/technadminy7/public_html && php bin/magento cache:status', $cacheStatus);
foreach ($cacheStatus as $line) {
    echo "<p>$line</p>";
}

echo "<h2>Next Steps:</h2>";
echo "<ol>";
echo "<li>Visit your website and check browser console for OneSignal initialization</li>";
echo "<li>Look for the OneSignal notification button on the bottom right</li>";
echo "<li>Test on both desktop and mobile browsers</li>";
echo "<li>Check OneSignal dashboard for new subscribers</li>";
echo "</ol>";

echo "<p><strong>Note:</strong> The OneSignal configuration has been added to your base configuration and will be applied to all themes including mobile.</p>";
?>
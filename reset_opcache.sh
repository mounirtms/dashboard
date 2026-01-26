#!/bin/bash
# Quick OPcache Reset for Production - v6.0.1
# This will reset opcache to clear old cached code

echo "Resetting OPcache for PHP 8.2..."

# Method 1: Create opcache_reset.php file
cat > /home/technadminy7/public_html/pub/opcache_reset.php << 'EOF'
<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache has been reset successfully!";
} else {
    echo "OPcache is not enabled.";
}
?>
EOF

chmod 644 /home/technadminy7/public_html/pub/opcache_reset.php

# Method 2: Call it via curl
echo "Calling opcache reset via web..."
curl -s "https://technostationery.com/opcache_reset.php"
echo ""

# Method 3: Try to restart PHP-FPM if possible
echo "Attempting to restart PHP-FPM..."
systemctl restart ea-php82-php-fpm 2>/dev/null && echo "✓ PHP-FPM restarted" || echo "  (May require manual restart)"

echo ""
echo "OPcache reset complete!"
echo "Please test: https://technostationery.com/"

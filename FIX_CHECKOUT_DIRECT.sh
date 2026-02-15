#!/bin/bash

echo "====================================="
echo "DIRECT CHECKOUT FIX (Skip Problematic Config)"
echo "====================================="
echo ""

BASE_DIR="/home/technadminy7/public_html"
cd "$BASE_DIR"

# Get DB credentials from env.php
DB_NAME=$(php -r 'include "app/etc/env.php"; echo $config["db"]["connection"]["default"]["dbname"];')
DB_USER=$(php -r 'include "app/etc/env.php"; echo $config["db"]["connection"]["default"]["username"];')
DB_PASS=$(php -r 'include "app/etc/env.php"; echo $config["db"]["connection"]["default"]["password"];')
DB_HOST=$(php -r 'include "app/etc/env.php"; echo $config["db"]["connection"]["default"]["host"];')

echo "[1] Inserting Amasty configuration directly into database..."
mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" << 'EOSQL'
-- Enable Amasty Checkout
INSERT INTO core_config_data (scope, scope_id, path, value) 
VALUES ('default', 0, 'amasty_checkout/general/enabled', '1')
ON DUPLICATE KEY UPDATE value='1';

-- Set layout (using layout_modern path which works)
INSERT INTO core_config_data (scope, scope_id, path, value) 
VALUES ('default', 0, 'amasty_checkout/design/layout_modern', '3columns')
ON DUPLICATE KEY UPDATE value='3columns';

-- Enable discount
INSERT INTO core_config_data (scope, scope_id, path, value) 
VALUES ('default', 0, 'amasty_checkout/additional_options/discount', '1')
ON DUPLICATE KEY UPDATE value='1';

-- Enable comment
INSERT INTO core_config_data (scope, scope_id, path, value) 
VALUES ('default', 0, 'amasty_checkout/additional_options/comment', '1')
ON DUPLICATE KEY UPDATE value='1';

-- Enable guest checkout
INSERT INTO core_config_data (scope, scope_id, path, value) 
VALUES ('default', 0, 'checkout/options/guest_checkout', '1')
ON DUPLICATE KEY UPDATE value='1';

-- Show telephone as required
INSERT INTO core_config_data (scope, scope_id, path, value) 
VALUES ('default', 0, 'customer/address/telephone_show', 'req')
ON DUPLICATE KEY UPDATE value='req';

EOSQL

echo "✓ Configuration inserted"

echo ""
echo "[2] Clearing config cache..."
php bin/magento cache:clean config full_page
echo "✓ Cache cleared"

echo ""
echo "[3] Verifying settings..."
echo "Guest checkout enabled:" $(php bin/magento config:show checkout/options/guest_checkout)
echo "Amasty enabled:" $(php bin/magento config:show amasty_checkout/general/enabled)
echo "Amasty layout:" $(php bin/magento config:show amasty_checkout/design/layout_modern)
echo "Discount enabled:" $(php bin/magento config:show amasty_checkout/additional_options/discount)

echo ""
echo "====================================="
echo "✓ FIX COMPLETE!"
echo "====================================="
echo ""
echo "🎯 TEST CHECKOUT NOW:"
echo "1. https://technostationery.com/ - Add product"
echo "2. https://technostationery.com/checkout/cart/ - View cart"
echo "3. Click 'Procéder au paiement'"
echo "4. Fields should now appear!"
echo ""


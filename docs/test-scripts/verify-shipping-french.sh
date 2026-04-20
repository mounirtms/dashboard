#!/bin/bash
echo "=========================================="
echo "🔍 SHIPPING METHOD FRENCH LOCALE VERIFICATION"
echo "=========================================="
echo ""

echo "1. Checking shipping-method-cards.js for French translations..."
if grep -q "Retrait immédiat" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js; then
    echo "✅ 'Retrait immédiat' found"
else
    echo "❌ 'Retrait immédiat' NOT found"
fi

if grep -q "Livraison gratuite" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js; then
    echo "✅ 'Livraison gratuite' found"
else
    echo "❌ 'Livraison gratuite' NOT found"
fi

if grep -q "jours ouvrables" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js; then
    echo "✅ 'jours ouvrables' found"
else
    echo "❌ 'jours ouvrables' NOT found"
fi

echo ""
echo "2. Checking identifyCarrier function for all carriers..."
grep -A 15 "identifyCarrier = function" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js | grep -E "(yalidine|ecotrak|techno|retrait|pickup)"

echo ""
echo "3. Checking delivery time estimates..."
grep -A 30 "estimateDeliveryTime = function" app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js | grep -E "(case 'yalidine'|case 'ecotrak'|case 'store-pickup'|case 'free')"

echo ""
echo "4. Checking MagePlaza shipping methods in database..."
cd /home/dev/public_html
php -r "
require 'app/bootstrap.php';
\$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, \$_SERVER);
\$obj = \$bootstrap->getObjectManager();
\$state = \$obj->get('Magento\Framework\App\State');
\$state->setAreaCode('frontend');

\$connection = \$obj->get('Magento\Framework\App\ResourceConnection')->getConnection();
\$tableName = \$connection->getTableName('mageplaza_tablerate_rule');

\$query = \"SELECT rule_id, name FROM {\$tableName} WHERE status = 1 LIMIT 10\";
\$results = \$connection->fetchAll(\$query);

echo \"Active Shipping Rules:\n\";
foreach (\$results as \$row) {
    echo \"  - [{row['rule_id']}] {row['name']}\n\";
}
"

echo ""
echo "=========================================="
echo "✅ Verification Complete"
echo "=========================================="

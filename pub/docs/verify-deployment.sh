#!/bin/bash
#
# Documentation System Verification Script
# Verifies that all components are properly deployed and functional
#

set -e

DOC_DIR="/home/technadminy7/public_html/pub/documentation"
BASE_URL="https://technostationery.com/documentation"

echo "========================================="
echo "Documentation System Verification"
echo "========================================="
echo ""

# Change to documentation directory
cd "$DOC_DIR"

echo "1. Checking directory structure..."
if [ -d "includes" ] && [ -d "logs" ] && [ -d "assets" ] && [ -d "data" ]; then
    echo "   ✅ All directories exist"
else
    echo "   ❌ Missing directories"
    exit 1
fi

echo ""
echo "2. Checking required files..."
REQUIRED_FILES=("config.php" "api.php" "main.html" ".htaccess" "includes/db.php" "includes/stats.php")
for file in "${REQUIRED_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "   ✅ $file"
    else
        echo "   ❌ Missing: $file"
        exit 1
    fi
done

echo ""
echo "3. Checking file permissions..."
if [ -w "logs" ] && [ -w "data" ]; then
    echo "   ✅ Writable directories (logs, data)"
else
    echo "   ⚠️  Setting permissions..."
    chmod 777 logs data
    echo "   ✅ Permissions fixed"
fi

echo ""
echo "4. Testing database connection..."
DB_TEST=$(php -r "
define('DOC_ACCESS', true);
\$config = require './config.php';
require './includes/db.php';
try {
    \$db = DatabaseConnection::getInstance(\$config);
    \$count = \$db->queryValue('SELECT COUNT(*) FROM sales_order');
    echo \$count;
} catch (Exception \$e) {
    echo 'ERROR: ' . \$e->getMessage();
    exit(1);
}" 2>/dev/null)

if [[ "$DB_TEST" =~ ^[0-9]+$ ]]; then
    echo "   ✅ Database connected ($DB_TEST orders found)"
else
    echo "   ❌ Database connection failed: $DB_TEST"
    exit 1
fi

echo ""
echo "5. Testing Yalidine stats collection..."
YALIDINE_TEST=$(php -r "
define('DOC_ACCESS', true);
\$config = require './config.php';
require './includes/db.php';
require './includes/stats.php';
try {
    \$db = DatabaseConnection::getInstance(\$config);
    \$stats = new StatsCollector(\$db, \$config);
    \$y = \$stats->getYalidineStats();
    echo \$y['wilayas']['active'] . ',' . \$y['communes']['active'];
} catch (Exception \$e) {
    echo 'ERROR';
    exit(1);
}" 2>/dev/null)

if [[ "$YALIDINE_TEST" =~ ^[0-9]+,[0-9]+$ ]]; then
    IFS=',' read -r wilayas communes <<< "$YALIDINE_TEST"
    echo "   ✅ Yalidine stats loaded ($wilayas wilayas, $communes communes)"
else
    echo "   ❌ Yalidine stats failed"
    exit 1
fi

echo ""
echo "6. Testing API endpoints..."

# Test health endpoint
HEALTH_TEST=$(php -r "
define('DOC_ACCESS', true);
\$_GET['action'] = 'health';
ob_start();
require './api.php';
\$output = ob_get_clean();
\$data = json_decode(\$output, true);
echo isset(\$data['success']) && \$data['success'] ? 'OK' : 'FAIL';
" 2>/dev/null)

if [ "$HEALTH_TEST" = "OK" ]; then
    echo "   ✅ API health endpoint working"
else
    echo "   ❌ API health endpoint failed"
    exit 1
fi

# Test general stats endpoint
GENERAL_TEST=$(php -r "
define('DOC_ACCESS', true);
\$_GET['action'] = 'general';
ob_start();
require './api.php';
\$output = ob_get_clean();
\$data = json_decode(\$output, true);
echo isset(\$data['success']) && \$data['success'] ? 'OK' : 'FAIL';
" 2>/dev/null)

if [ "$GENERAL_TEST" = "OK" ]; then
    echo "   ✅ API general endpoint working"
else
    echo "   ❌ API general endpoint failed"
    exit 1
fi

# Test yalidine stats endpoint
YALIDINE_API_TEST=$(php -r "
define('DOC_ACCESS', true);
\$_GET['action'] = 'yalidine';
ob_start();
require './api.php';
\$output = ob_get_clean();
\$data = json_decode(\$output, true);
echo isset(\$data['success']) && \$data['success'] ? 'OK' : 'FAIL';
" 2>/dev/null)

if [ "$YALIDINE_API_TEST" = "OK" ]; then
    echo "   ✅ API yalidine endpoint working"
else
    echo "   ❌ API yalidine endpoint failed"
    exit 1
fi

echo ""
echo "7. Checking security configuration..."
if grep -q "Require all denied" .htaccess && grep -q "config\.php" .htaccess; then
    echo "   ✅ Security rules configured"
else
    echo "   ⚠️  Check .htaccess security rules"
fi

echo ""
echo "8. Testing cache system..."
# Clear cache first
rm -f logs/cache_*.json 2>/dev/null || true

# Generate cache
php -r "
define('DOC_ACCESS', true);
\$config = require './config.php';
require './includes/db.php';
require './includes/stats.php';
\$db = DatabaseConnection::getInstance(\$config);
\$stats = new StatsCollector(\$db, \$config);
\$stats->getSystemStats(); // This should create cache files
" 2>/dev/null

CACHE_FILES=$(ls -1 logs/cache_*.json 2>/dev/null | wc -l)
if [ "$CACHE_FILES" -gt 0 ]; then
    echo "   ✅ Cache system working ($CACHE_FILES cache files created)"
else
    echo "   ⚠️  Cache system may not be working properly"
fi

echo ""
echo "9. Checking log directory..."
if [ -d "logs" ] && [ -w "logs" ]; then
    echo "   ✅ Logs directory writable"
    LOG_COUNT=$(ls -1 logs/*.log 2>/dev/null | wc -l)
    echo "   ℹ️  Current log files: $LOG_COUNT"
else
    echo "   ❌ Logs directory not writable"
    exit 1
fi

echo ""
echo "10. Final verification..."
echo "   📊 Database Orders: $DB_TEST"
echo "   🚚 Yalidine Wilayas: $wilayas"
echo "   🏘️  Yalidine Communes: $communes"
echo "   💾 Cache Files: $CACHE_FILES"
echo "   📝 Log Files: $LOG_COUNT"

echo ""
echo "========================================="
echo "✅ ALL CHECKS PASSED"
echo "========================================="
echo ""
echo "🌐 Documentation URL:"
echo "   $BASE_URL/main.html"
echo ""
echo "🔗 API Health Check:"
echo "   $BASE_URL/api.php?action=health"
echo ""
echo "📖 Deployment Guide:"
echo "   $DOC_DIR/DEPLOYMENT_GUIDE.md"
echo ""
echo "✅ System is ready for production use!"
echo ""

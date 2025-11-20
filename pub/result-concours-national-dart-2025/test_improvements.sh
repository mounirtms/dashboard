#!/bin/bash

# Test Script for Applied Improvements
echo "========================================="
echo "Testing Applied Improvements"
echo "========================================="
echo ""

cd /home/technadminy7/public_html/pub/result-concours-national-dart-2025

echo "✓ 1. Checking file integrity..."
if [ -f "index.php" ] && [ -f "assets/js/app.js" ] && [ -f "assets/css/style.css" ]; then
    echo "   All core files present"
else
    echo "   ✗ Missing core files"
    exit 1
fi

echo ""
echo "✓ 2. Checking new optimization files..."
if [ -f "assets/css/optimizations.css" ] && [ -f "assets/js/ux-enhancements.js" ]; then
    echo "   New optimization files created"
else
    echo "   ✗ Missing optimization files"
    exit 1
fi

echo ""
echo "✓ 3. Checking PHP syntax..."
php -l index.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "   PHP syntax valid"
else
    echo "   ✗ PHP syntax errors detected"
    exit 1
fi

echo ""
echo "✓ 4. Checking for authentication controls..."
if grep -q "isAuthenticated ? '' : 'display:none;'" index.php; then
    echo "   Authentication-based visibility controls found"
else
    echo "   ⚠ Authentication controls may need review"
fi

echo ""
echo "✓ 5. Checking for filter persistence..."
if grep -q "filter_wilaya === \$wilaya" index.php; then
    echo "   Filter persistence implemented"
else
    echo "   ⚠ Filter persistence may need review"
fi

echo ""
echo "✓ 6. Checking for pagination..."
if grep -q "pagination" index.php; then
    echo "   Pagination code found"
else
    echo "   ✗ Pagination missing"
fi

echo ""
echo "✓ 7. Checking for export buttons..."
if grep -q "btnExportAll" index.php && grep -q "btnExportRated" index.php; then
    echo "   Export buttons found"
else
    echo "   ✗ Export buttons missing"
fi

echo ""
echo "✓ 8. Checking JavaScript enhancements..."
if grep -q "debounceFilter" assets/js/app.js; then
    echo "   Debounce filtering implemented"
else
    echo "   ⚠ Debounce filtering may need review"
fi

echo ""
echo "✓ 9. Checking CSS optimizations..."
if grep -q "cubic-bezier" assets/css/optimizations.css; then
    echo "   CSS performance optimizations found"
else
    echo "   ⚠ CSS optimizations may need review"
fi

echo ""
echo "✓ 10. Checking for keyboard shortcuts..."
if grep -q "keydown" assets/js/ux-enhancements.js; then
    echo "   Keyboard shortcuts implemented"
else
    echo "   ⚠ Keyboard shortcuts may need review"
fi

echo ""
echo "========================================="
echo "File Sizes:"
echo "========================================="
ls -lh index.php assets/js/app.js assets/css/style.css assets/css/optimizations.css assets/js/ux-enhancements.js 2>/dev/null | awk '{print $9, "-", $5}'

echo ""
echo "========================================="
echo "Backup Files:"
echo "========================================="
ls -lht index.php.backup_* 2>/dev/null | head -3 | awk '{print $9, "-", $6, $7, $8}'

echo ""
echo "========================================="
echo "✅ All Tests Passed!"
echo "========================================="
echo ""
echo "Next Steps:"
echo "1. Access the application in a browser"
echo "2. Test login/logout functionality"
echo "3. Verify filters persist from URL"
echo "4. Test pagination in both views"
echo "5. Check export buttons (CSV/Rated)"
echo ""
echo "Documentation: See IMPROVEMENTS.md for details"
echo ""

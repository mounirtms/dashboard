#!/bin/bash
#
# HTML Block Performance Optimization
# Analyzes and optimizes CMS blocks for better performance
#

echo "=== HTML BLOCK PERFORMANCE OPTIMIZATION ==="
echo "Started: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

DB_CMD="/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22"

# Part 1: Block statistics
echo "=== PART 1: BLOCK STATISTICS ==="
echo "Total active blocks:"
echo "SELECT COUNT(*) as total FROM cms_block WHERE is_active = 1;" | $DB_CMD -N

echo ""
echo "Largest blocks (top 10):"
echo "SELECT identifier, LENGTH(content) as size FROM cms_block WHERE is_active = 1 ORDER BY LENGTH(content) DESC LIMIT 10;" | $DB_CMD -t

# Part 2: Performance issues
echo ""
echo "=== PART 2: PERFORMANCE ISSUES ==="
echo ""
echo "Blocks with inline styles:"
$DB_CMD -e "
SELECT 
    identifier,
    title,
    (LENGTH(content) - LENGTH(REPLACE(content, 'style=', ''))) / 6 as inline_styles
FROM cms_block
WHERE is_active = 1
  AND content LIKE '%style=%'
ORDER BY inline_styles DESC
LIMIT 10;
"

echo ""
echo "Blocks with images (potential optimization):"
$DB_CMD -e "
SELECT 
    identifier,
    title,
    (LENGTH(content) - LENGTH(REPLACE(content, '<img', ''))) / 4 as image_count
FROM cms_block
WHERE is_active = 1
  AND content LIKE '%<img%'
ORDER BY image_count DESC
LIMIT 10;
"

# Part 3: Optimization recommendations
echo ""
echo "=== PART 3: OPTIMIZATION RECOMMENDATIONS ==="
echo ""
echo "1. FOOTER BLOCKS (Largest: 7.8KB)"
echo "   - footer-1-content: 7.8KB"
echo "   - footer-mobile: 5.3KB"
echo "   - footer-28-content: 5.1KB"
echo "   Recommendation: Move inline styles to CSS file"
echo ""
echo "2. INLINE STYLES"
echo "   Found blocks with inline style= attributes"
echo "   Recommendation: Extract to external CSS or <style> block"
echo ""
echo "3. IMAGES"
echo "   Blocks contain <img> tags"
echo "   Recommendation: Use lazy loading, optimize image sizes"
echo ""
echo "4. CACHING"
echo "   Check: System > Cache Management"
echo "   Enable: Block HTML cache"
echo ""

# Part 4: Block cache check
echo "=== PART 4: CACHE STATUS ==="
php bin/magento cache:status | grep -E "(block_html|layout|full_page)"

echo ""
echo "=== RECOMMENDATIONS SUMMARY ==="
echo ""
echo "HIGH PRIORITY:"
echo "  1. Extract inline styles from footer blocks to CSS"
echo "  2. Enable block_html cache if disabled"
echo "  3. Add lazy loading to images in CMS blocks"
echo ""
echo "MEDIUM PRIORITY:"
echo "  4. Minify HTML in large blocks (footer-*)"
echo "  5. Consider splitting large blocks into smaller components"
echo "  6. Use page builder for complex layouts instead of raw HTML"
echo ""
echo "LOW PRIORITY:"
echo "  7. Review and remove unused CMS blocks"
echo "  8. Audit block content for deprecated HTML tags"
echo ""

echo "Completed: $(date '+%Y-%m-%d %H:%M:%S')"

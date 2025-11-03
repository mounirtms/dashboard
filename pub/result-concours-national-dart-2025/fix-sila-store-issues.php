<?php
// Script to fix sila store view issues
echo "<h1>Fixing SILA Store View Issues</h1>";

echo "<h2>Issues Identified:</h2>";
echo "<ol>";
echo "<li>Products not visible in sila store view</li>";
echo "<li>CMS pages not assigned to sila store</li>";
echo "<li>Search index needs reindexing</li>";
echo "<li>Store view name should be 'techno' not 'default'</li>";
echo "</ol>";

echo "<h2>Solutions to Apply:</h2>";
echo "<ol>";
echo "<li>Assign CMS pages to sila store (store_id = 9)</li>";
echo "<li>Ensure products are properly indexed for sila store</li>";
echo "<li>Reindex catalog search data</li>";
echo "<li>Update store view name if needed</li>";
echo "</ol>";

echo "<h2>SQL Commands to Run:</h2>";
echo "<pre>";
echo "-- 1. Assign home page to sila store\n";
echo "INSERT INTO cms_page_store (page_id, store_id) SELECT page_id, 9 FROM cms_page WHERE identifier = 'home' AND page_id NOT IN (SELECT page_id FROM cms_page_store WHERE store_id = 9);\n\n";

echo "-- 2. Assign other important CMS pages to sila store\n";
echo "INSERT INTO cms_page_store (page_id, store_id) SELECT page_id, 9 FROM cms_page WHERE identifier IN ('no-route', 'home-demo-01') AND page_id NOT IN (SELECT page_id FROM cms_page_store WHERE store_id = 9);\n\n";

echo "-- 3. Set sila store home page configuration\n";
echo "INSERT INTO core_config_data (scope, scope_id, path, value) VALUES ('stores', 9, 'web/default/cms_home_page', 'home-demo-01') ON DUPLICATE KEY UPDATE value = 'home-demo-01';\n\n";

echo "-- 4. Check if products are assigned to website (should already be)\n";
echo "SELECT COUNT(*) as product_count FROM catalog_product_website WHERE website_id = 1;\n\n";

echo "-- 5. Update store view name if needed\n";
echo "UPDATE store SET name = 'Techno Stationery' WHERE store_id = 1;\n";
echo "</pre>";

echo "<h2>Commands to Run in Magento CLI:</h2>";
echo "<pre>";
echo "# Reindex catalog search\n";
echo "php bin/magento indexer:reindex catalogsearch_fulltext\n\n";

echo "# Reindex other related indexes\n";
echo "php bin/magento indexer:reindex catalog_category_product catalog_product_category\n\n";

echo "# Clear cache\n";
echo "php bin/magento cache:flush\n";
echo "</pre>";

echo "<h2>Elasticsearch Configuration:</h2>";
echo "<p>Elasticsearch is already configured correctly for multi-store use with index prefix 'techno_stationery'. No changes needed.</p>";

echo "<h2>Verification Steps:</h2>";
echo "<ol>";
echo "<li>Check that products appear in sila store view</li>";
echo "<li>Verify that home page loads correctly for sila store</li>";
echo "<li>Confirm search functionality works in sila store</li>";
echo "<li>Ensure default store view is named 'techno'</li>";
echo "</ol>";
?>
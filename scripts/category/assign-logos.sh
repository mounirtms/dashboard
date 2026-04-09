#!/bin/bash
# Script to assign logos to categories via direct SQL

echo "Assigning logos to categories..."

# Assign promo logo to category 1798
echo "Assigning promo logo to category 1798..."
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "INSERT INTO catalog_category_entity_varchar (attribute_id, entity_id, value) SELECT attribute_id, 1798, '/category_logos/promos_techno_logo.svg' FROM eav_attribute WHERE attribute_code = 'image' AND entity_type_id = 3 ON DUPLICATE KEY UPDATE value = '/category_logos/promos_techno_logo.svg';"

echo "Logo assignment completed!"
echo "Now flushing cache and reindexing..."
cd /home/betapublic_html
php bin/magento cache:flush
php bin/magento indexer:reindex

echo "Done! Please check the admin panel to see the logos."
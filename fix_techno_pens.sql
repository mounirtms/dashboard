-- TECHNO PENS COMPLETE FIX - Direct SQL
-- Fix product 1140665419 and related products to be searchable

-- ============================================
-- PART 1: FIX PRODUCT 1140665419 (BLEU - REF 9798)
-- ============================================

SET @product_id = 9769;
SET @sku = '1140665419';

-- Get attribute IDs
SET @status_attr = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'status' AND entity_type_id = 4);
SET @visibility_attr = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'visibility' AND entity_type_id = 4);
SET @name_attr = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'name' AND entity_type_id = 4);
SET @tax_class_attr = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'tax_class_id' AND entity_type_id = 4);

-- Step 1: Clean conflicting attribute values
DELETE FROM catalog_product_entity_int 
WHERE entity_id = @product_id 
  AND attribute_id IN (@status_attr, @visibility_attr, @tax_class_attr)
  AND store_id != 0;

-- Step 2: Set correct attribute values for ALL stores
-- Status = 1 (Enabled) for all stores
DELETE FROM catalog_product_entity_int 
WHERE entity_id = @product_id AND attribute_id = @status_attr;

INSERT INTO catalog_product_entity_int (entity_id, attribute_id, store_id, value) VALUES
(@product_id, @status_attr, 0, 1),  -- Global
(@product_id, @status_attr, 1, 1),  -- Store 1
(@product_id, @status_attr, 6, 1),  -- Store 6
(@product_id, @status_attr, 8, 1),  -- Store 8
(@product_id, @status_attr, 9, 1),  -- Store 9
(@product_id, @status_attr, 10, 1); -- Store 10

-- Visibility = 4 (Catalog, Search) for all stores
DELETE FROM catalog_product_entity_int 
WHERE entity_id = @product_id AND attribute_id = @visibility_attr;

INSERT INTO catalog_product_entity_int (entity_id, attribute_id, store_id, value) VALUES
(@product_id, @visibility_attr, 0, 4),  -- Global
(@product_id, @visibility_attr, 1, 4),  -- Store 1
(@product_id, @visibility_attr, 6, 4),  -- Store 6
(@product_id, @visibility_attr, 8, 4),  -- Store 8
(@product_id, @visibility_attr, 9, 4),  -- Store 9
(@product_id, @visibility_attr, 10, 4); -- Store 10

-- Tax class = 2 (Taxable Goods)
INSERT INTO catalog_product_entity_int (entity_id, attribute_id, store_id, value)
VALUES (@product_id, @tax_class_attr, 0, 2)
ON DUPLICATE KEY UPDATE value = 2;

-- Step 3: Fix stock (both legacy and MSI)
-- Legacy stock
UPDATE cataloginventory_stock_item 
SET qty = 9999, 
    is_in_stock = 1,
    manage_stock = 1,
    use_config_manage_stock = 0,
    stock_status_changed_auto = 0
WHERE product_id = @product_id;

-- If not exists, insert
INSERT IGNORE INTO cataloginventory_stock_item 
(product_id, stock_id, qty, is_in_stock, manage_stock, use_config_manage_stock)
VALUES (@product_id, 1, 9999, 1, 1, 0);

-- MSI stock (default source)
INSERT INTO inventory_source_item (source_code, sku, quantity, status)
VALUES ('default', @sku, 9999, 1)
ON DUPLICATE KEY UPDATE quantity = 9999, status = 1;

-- Step 4: Assign to categories
DELETE FROM catalog_category_product WHERE product_id = @product_id;

INSERT INTO catalog_category_product (category_id, product_id, position) VALUES
(2, @product_id, 0),     -- Default Category
(3, @product_id, 1),     -- Tous les produits
(8, @product_id, 2),     -- SCOLAIRE
(38, @product_id, 3),    -- ECRITURE & CORRECTION
(112, @product_id, 4),   -- STYLOS ENCRE VISQUEUSE
(770, @product_id, 5),   -- ECRITURE & COLORIAGE
(773, @product_id, 6),   -- ECRITURE & CORRECTION (sub)
(775, @product_id, 7),   -- STYLOS ENCRE VISQUEUSE (sub)
(2224, @product_id, 8);  -- BUREAUTIQUE & INFORMATIQUE

-- Step 5: Clean indexes (will be regenerated)
DELETE FROM catalog_product_index_price WHERE entity_id = @product_id;
DELETE FROM catalog_product_index_eav WHERE entity_id = @product_id;
DELETE FROM catalog_category_product_index WHERE product_id = @product_id;
DELETE FROM catalogsearch_fulltext_scope1 WHERE entity_id = @product_id;

-- Step 6: Clean URL rewrites (will be regenerated)
DELETE FROM url_rewrite 
WHERE entity_type = 'product' 
  AND entity_id = @product_id;

SELECT 'Product 1140665419 fixed!' AS result;

-- ============================================
-- PART 2: CHECK CMS PAGES/BLOCKS FOR ENGLISH TEXT
-- ============================================

SELECT '=== CMS PAGES WITH ENGLISH CONTENT ===' AS section;

SELECT 
    page_id,
    title,
    identifier,
    is_active,
    LENGTH(content) as content_length,
    CASE 
        WHEN content LIKE '%english%' OR content LIKE '%English%' THEN 'HAS ENGLISH'
        WHEN content LIKE '%Add to Cart%' THEN 'HAS "Add to Cart"'
        WHEN content LIKE '%Price%' AND content NOT LIKE '%Prix%' THEN 'HAS "Price"'
        WHEN content LIKE '%Buy%' THEN 'HAS "Buy"'
        WHEN content LIKE '%Shop%' AND content NOT LIKE '%Boutique%' THEN 'HAS "Shop"'
        WHEN content LIKE '%Home%' AND content NOT LIKE '%Accueil%' THEN 'HAS "Home"'
        ELSE 'CHECK MANUALLY'
    END as english_keywords
FROM cms_page
WHERE is_active = 1
  AND (
    content LIKE '%Add to Cart%' 
    OR content LIKE '%Price%'
    OR content LIKE '%Buy%'
    OR content LIKE '%Shop%'
    OR content LIKE '%Home%'
    OR content LIKE '%english%'
    OR content LIKE '%English%'
  )
ORDER BY page_id;

SELECT '=== CMS BLOCKS WITH ENGLISH CONTENT ===' AS section;

SELECT 
    block_id,
    title,
    identifier,
    is_active,
    LENGTH(content) as content_length,
    CASE 
        WHEN content LIKE '%Add to Cart%' THEN 'HAS "Add to Cart"'
        WHEN content LIKE '%Price%' AND content NOT LIKE '%Prix%' THEN 'HAS "Price"'
        WHEN content LIKE '%Buy%' THEN 'HAS "Buy"'
        WHEN content LIKE '%Shop%' AND content NOT LIKE '%Boutique%' THEN 'HAS "Shop"'
        WHEN content LIKE '%Home%' AND content NOT LIKE '%Accueil%' THEN 'HAS "Home"'
        WHEN content LIKE '%Contact us%' THEN 'HAS "Contact us"'
        ELSE 'CHECK MANUALLY'
    END as english_keywords
FROM cms_block
WHERE is_active = 1
  AND (
    content LIKE '%Add to Cart%' 
    OR content LIKE '%Price%'
    OR content LIKE '%Buy%'
    OR content LIKE '%Shop%'
    OR content LIKE '%Home%'
    OR content LIKE '%Contact%'
  )
ORDER BY block_id
LIMIT 20;

SELECT '=== SUMMARY ===' AS section;
SELECT CONCAT('Product 1140665419 has been fixed with status=1, visibility=4, stock=9999') AS message;
SELECT CONCAT('Run: php bin/magento indexer:reindex catalogsearch_fulltext') AS next_step_1;
SELECT CONCAT('Run: php bin/magento indexer:reindex catalog_product_category') AS next_step_2;
SELECT CONCAT('Run: php bin/magento cache:flush') AS next_step_3;

-- Update À LA UNE category to show only specific products
-- Category ID: 2121
-- Products: 495, 606, 1140621565, 1140632138, 1140637505, 1140658840

USE technadminy7_dBT8x12y22;

-- Backup current category products
CREATE TABLE IF NOT EXISTS catalog_category_product_alune_backup_20260211 AS
SELECT * FROM catalog_category_product WHERE category_id = 2121;

-- Remove all current products from À LA UNE category
DELETE FROM catalog_category_product WHERE category_id = 2121;

-- Add only the 6 specified products
INSERT INTO catalog_category_product (category_id, product_id, position)
SELECT 2121, entity_id, 0
FROM catalog_product_entity
WHERE entity_id IN (495, 606)
   OR sku IN ('1140621565', '1140632138', '1140637505', '1140658840');

-- Verify
SELECT 
    ccp.product_id,
    cpe.sku,
    v.value as product_name
FROM catalog_category_product ccp
JOIN catalog_product_entity cpe ON ccp.product_id = cpe.entity_id
LEFT JOIN catalog_product_entity_varchar v ON cpe.entity_id = v.entity_id 
    AND v.attribute_id = (SELECT attribute_id FROM eav_attribute WHERE attribute_code = 'name' AND entity_type_id = 4)
    AND v.store_id = 0
WHERE ccp.category_id = 2121
ORDER BY ccp.product_id;

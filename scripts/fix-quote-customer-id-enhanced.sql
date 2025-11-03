-- Enhanced SQL script to fix guest checkout quote synchronization issues
-- This script will sync customer_id between sales_order and quote tables
-- where they are mismatched or where quote has NULL customer_id but order has valid customer_id

-- First, let's check what records need to be fixed, including the specific order 6720
SELECT 
    so.entity_id as order_entity_id,
    so.increment_id,
    so.quote_id,
    so.customer_id as order_customer_id,
    so.customer_group_id as order_customer_group_id,
    q.entity_id as quote_entity_id,
    q.customer_id as quote_customer_id,
    q.customer_is_guest
FROM sales_order so
INNER JOIN quote q ON so.quote_id = q.entity_id
WHERE (so.customer_id IS NOT NULL 
AND (q.customer_id IS NULL OR q.customer_id != so.customer_id))
OR so.increment_id = '6720'
ORDER BY so.created_at DESC;

-- Count how many records need to be fixed
SELECT COUNT(*) as records_to_fix
FROM sales_order so
INNER JOIN quote q ON so.quote_id = q.entity_id
WHERE so.customer_id IS NOT NULL 
AND (q.customer_id IS NULL OR q.customer_id != so.customer_id);

-- Fix all mismatched records - guest checkouts where customer_is_guest = 1
UPDATE quote q
INNER JOIN sales_order so ON q.entity_id = so.quote_id
SET 
    q.customer_id = so.customer_id,
    q.customer_is_guest = 0,
    q.customer_group_id = so.customer_group_id,
    q.updated_at = NOW()
WHERE 
    so.customer_id IS NOT NULL 
    AND (q.customer_id IS NULL OR q.customer_id != so.customer_id)
    AND q.customer_is_guest = 1;

-- Fix any remaining mismatched records where customer_is_guest = 0 but customer_id is NULL
UPDATE quote q
INNER JOIN sales_order so ON q.entity_id = so.quote_id
SET 
    q.customer_id = so.customer_id,
    q.customer_is_guest = 0,
    q.customer_group_id = so.customer_group_id,
    q.updated_at = NOW()
WHERE 
    so.customer_id IS NOT NULL 
    AND q.customer_id IS NULL
    AND q.customer_is_guest = 0;

-- Fix specific edge case: customer_id matches but customer_is_guest flag is wrong
UPDATE quote q
INNER JOIN sales_order so ON q.entity_id = so.quote_id
SET 
    q.customer_is_guest = 0,
    q.updated_at = NOW()
WHERE 
    so.customer_id IS NOT NULL 
    AND q.customer_id = so.customer_id
    AND q.customer_is_guest = 1;

-- Special fix for order increment_id 6720 if it still has issues
UPDATE quote q
INNER JOIN sales_order so ON q.entity_id = so.quote_id
SET 
    q.customer_id = so.customer_id,
    q.customer_is_guest = 0,
    q.customer_group_id = so.customer_group_id,
    q.updated_at = NOW()
WHERE 
    so.increment_id = '6720'
    AND q.customer_id IS NULL;

-- Verify the fixes for recent orders
SELECT 
    so.entity_id as order_entity_id,
    so.increment_id,
    so.quote_id,
    so.customer_id as order_customer_id,
    q.entity_id as quote_entity_id,
    q.customer_id as quote_customer_id,
    q.customer_is_guest
FROM sales_order so
INNER JOIN quote q ON so.quote_id = q.entity_id
WHERE so.increment_id = '6720'
   OR so.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY so.created_at DESC;
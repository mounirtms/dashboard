-- SQL script to fix guest checkout quote synchronization issues
-- This script will sync customer_id between sales_order and quote tables
-- where they are mismatched or where quote has NULL customer_id but order has valid customer_id

-- First, let's check what records need to be fixed
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
WHERE so.customer_id IS NOT NULL 
AND (q.customer_id IS NULL OR q.customer_id != so.customer_id)
AND q.customer_is_guest = 1
ORDER BY so.created_at DESC;

-- Now let's fix the specific order mentioned in the issue (increment_id 6564)
UPDATE quote q
INNER JOIN sales_order so ON q.entity_id = so.quote_id
SET 
    q.customer_id = so.customer_id,
    q.customer_is_guest = 0,
    q.customer_group_id = so.customer_group_id
WHERE 
    so.increment_id = '000006564'
    AND so.customer_id IS NOT NULL 
    AND (q.customer_id IS NULL OR q.customer_id != so.customer_id)
    AND q.customer_is_guest = 1;

-- Fix all similar records
UPDATE quote q
INNER JOIN sales_order so ON q.entity_id = so.quote_id
SET 
    q.customer_id = so.customer_id,
    q.customer_is_guest = 0,
    q.customer_group_id = so.customer_group_id
WHERE 
    so.customer_id IS NOT NULL 
    AND (q.customer_id IS NULL OR q.customer_id != so.customer_id)
    AND q.customer_is_guest = 1;

-- Verify the fix for the specific order
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
WHERE so.increment_id = '000006564';-- SQL script to fix guest checkout quote synchronization issues
-- This script will sync customer_id between sales_order and quote tables
-- where they are mismatched or where quote has NULL customer_id but order has valid customer_id

-- First, let's check what records need to be fixed
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
WHERE so.customer_id IS NOT NULL 
AND (q.customer_id IS NULL OR q.customer_id != so.customer_id)
AND q.customer_is_guest = 1
ORDER BY so.created_at DESC;

-- Now let's fix the specific order mentioned in the issue (increment_id 6564)
UPDATE quote q
INNER JOIN sales_order so ON q.entity_id = so.quote_id
SET 
    q.customer_id = so.customer_id,
    q.customer_is_guest = 0,
    q.customer_group_id = so.customer_group_id
WHERE 
    so.increment_id = '000006564'
    AND so.customer_id IS NOT NULL 
    AND (q.customer_id IS NULL OR q.customer_id != so.customer_id)
    AND q.customer_is_guest = 1;

-- Fix all similar records
UPDATE quote q
INNER JOIN sales_order so ON q.entity_id = so.quote_id
SET 
    q.customer_id = so.customer_id,
    q.customer_is_guest = 0,
    q.customer_group_id = so.customer_group_id
WHERE 
    so.customer_id IS NOT NULL 
    AND (q.customer_id IS NULL OR q.customer_id != so.customer_id)
    AND q.customer_is_guest = 1;

-- Verify the fix for the specific order
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
WHERE so.increment_id = '000006564';
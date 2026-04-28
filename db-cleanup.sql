-- ================================================================
-- MariaDB Cleanup Script for Magento 2 Database
-- Generated: 2026-04-28
-- ================================================================
-- IMPORTANT: Run this during low-traffic hours
-- IMPORTANT: Take a full backup before running
-- ================================================================

-- Step 1: Check current data sizes
SELECT 
  'sales_bestsellers_aggregated_monthly' AS table_name,
  COUNT(*) AS total_rows,
  MIN(period) AS oldest_period,
  MAX(period) AS newest_period
FROM sales_bestsellers_aggregated_monthly
UNION ALL
SELECT 
  'sales_bestsellers_aggregated_yearly' AS table_name,
  COUNT(*) AS total_rows,
  MIN(period) AS oldest_period,
  MAX(period) AS newest_period
FROM sales_bestsellers_aggregated_yearly;

-- Step 2: Check how much data will be deleted
SELECT 
  'sales_bestsellers_aggregated_monthly' AS table_name,
  COUNT(*) AS rows_to_delete
FROM sales_bestsellers_aggregated_monthly
WHERE period < '2025-04-01'
UNION ALL
SELECT 
  'sales_bestsellers_aggregated_yearly' AS table_name,
  COUNT(*) AS rows_to_delete
FROM sales_bestsellers_aggregated_yearly
WHERE period < '2025-04-01';

-- ================================================================
-- UNCOMMENT THE FOLLOWING LINES TO ACTUALLY DELETE DATA
-- ================================================================

-- Step 3: Delete old data (older than 12 months)
-- DELETE FROM sales_bestsellers_aggregated_monthly 
-- WHERE period < '2025-04-01';

-- DELETE FROM sales_bestsellers_aggregated_yearly 
-- WHERE period < '2025-04-01';

-- Step 4: Optimize tables to reclaim space
-- OPTIMIZE TABLE sales_bestsellers_aggregated_monthly;
-- OPTIMIZE TABLE sales_bestsellers_aggregated_yearly;

-- ================================================================
-- ALTERNATIVE: Truncate tables completely (if you don't need the data)
-- ================================================================
-- TRUNCATE TABLE sales_bestsellers_aggregated_monthly;
-- TRUNCATE TABLE sales_bestsellers_aggregated_yearly;

-- Step 5: Verify cleanup
SELECT 
  table_name,
  table_rows,
  ROUND(data_length / 1024 / 1024, 2) AS size_mb,
  ROUND(index_length / 1024 / 1024, 2) AS index_size_mb
FROM information_schema.tables
WHERE table_schema = 'technadminy7_dBT8x12y22'
  AND table_name IN (
    'sales_bestsellers_aggregated_monthly',
    'sales_bestsellers_aggregated_yearly'
  );

-- ================================================================
-- Additional cleanup: Check for orphaned records
-- ================================================================

-- Check magento_operation table size
SELECT 
  COUNT(*) AS total_operations,
  ROUND(SUM(LENGTH(serialized_data)) / 1024 / 1024, 2) AS estimated_size_mb
FROM magento_operation;

-- Clean old operations (older than 30 days) - UNCOMMENT IF NEEDED
-- DELETE FROM magento_operation 
-- WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Clean search_query table (old search terms)
SELECT 
  COUNT(*) AS total_searches,
  MIN(query_date) AS oldest_search
FROM search_query;

-- Clean searches older than 90 days - UNCOMMENT IF NEEDED
-- DELETE FROM search_query 
-- WHERE query_date < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- ================================================================
-- Monitor connection issues
-- ================================================================

-- Check current connections
SHOW PROCESSLIST;

-- Check aborted connections
SHOW GLOBAL STATUS LIKE 'Aborted_connects';
SHOW GLOBAL STATUS LIKE 'Aborted_clients';

-- Check connection limits
SHOW VARIABLES LIKE '%max_connections%';
SHOW VARIABLES LIKE '%wait_timeout%';

-- ================================================================
-- Performance monitoring
-- ================================================================

-- Check buffer pool usage
SELECT 
  ROUND((innodb_buffer_pool_pages_data * @@innodb_page_size) / 1024 / 1024, 2) AS buffer_pool_used_mb,
  ROUND((innodb_buffer_pool_pages_total * @@innodb_page_size) / 1024 / 1024, 2) AS buffer_pool_total_mb,
  ROUND((innodb_buffer_pool_pages_data / innodb_buffer_pool_pages_total) * 100, 2) AS utilization_pct
FROM (
  SELECT 
    MAX(IF(variable_name = 'Innodb_buffer_pool_pages_data', variable_value, 0)) AS innodb_buffer_pool_pages_data,
    MAX(IF(variable_name = 'Innodb_buffer_pool_pages_total', variable_value, 0)) AS innodb_buffer_pool_pages_total
  FROM information_schema.GLOBAL_STATUS
  WHERE variable_name IN ('Innodb_buffer_pool_pages_data', 'Innodb_buffer_pool_pages_total')
) AS t;

-- Check temporary table usage
SHOW GLOBAL STATUS LIKE 'Created_tmp%';

-- Check slow queries
SHOW GLOBAL STATUS LIKE 'Slow_queries';

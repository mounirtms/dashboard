-- H1 2025 (2025-01-01 to 2025-06-30) vs H1 2026 (2026-01-01 to 2026-06-30)
-- 1. CMD_Done (Completed orders count)
SELECT 'CMD_Done H1 2025' AS metric, COUNT(*) AS value FROM sales_order WHERE created_at >= '2025-01-01 00:00:00' AND created_at <= '2025-06-30 23:59:59' AND status = 'complete'
UNION ALL
SELECT 'CMD_Done H1 2026', COUNT(*) FROM sales_order WHERE created_at >= '2026-01-01 00:00:00' AND created_at <= '2026-06-30 23:59:59' AND status = 'complete'
UNION ALL
-- 2. Revenue H1 2025 and 2026
SELECT 'Revenue H1 2025', SUM(base_grand_total) FROM sales_order WHERE created_at >= '2025-01-01 00:00:00' AND created_at <= '2025-06-30 23:59:59' AND status = 'complete'
UNION ALL
SELECT 'Revenue H1 2026', SUM(base_grand_total) FROM sales_order WHERE created_at >= '2026-01-01 00:00:00' AND created_at <= '2026-06-30 23:59:59' AND status = 'complete'
UNION ALL
-- 3. Cancelled orders H1 2025 and 2026
SELECT 'Cancelled H1 2025', COUNT(*) FROM sales_order WHERE created_at >= '2025-01-01 00:00:00' AND created_at <= '2025-06-30 23:59:59' AND status = 'canceled'
UNION ALL
SELECT 'Cancelled H1 2026', COUNT(*) FROM sales_order WHERE created_at >= '2026-01-01 00:00:00' AND created_at <= '2026-06-30 23:59:59' AND status = 'canceled'
UNION ALL
-- Total Orders H1 2025 and H1 2026
SELECT 'Total Orders H1 2025', COUNT(*) FROM sales_order WHERE created_at >= '2025-01-01 00:00:00' AND created_at <= '2025-06-30 23:59:59'
UNION ALL
SELECT 'Total Orders H1 2026', COUNT(*) FROM sales_order WHERE created_at >= '2026-01-01 00:00:00' AND created_at <= '2026-06-30 23:59:59'
UNION ALL
-- 4. Total Customers
SELECT 'Total Customers', COUNT(*) FROM customer_entity;

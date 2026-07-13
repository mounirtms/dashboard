SELECT 'H1 2025 CMD_Done', COUNT(*), SUM(base_grand_total) FROM sales_order WHERE created_at >= '2025-01-01' AND created_at <= '2025-06-30 23:59:59' AND status = 'CMD_Done'
UNION ALL
SELECT 'H1 2026 CMD_Done', COUNT(*), SUM(base_grand_total) FROM sales_order WHERE created_at >= '2026-01-01' AND created_at <= '2026-06-30 23:59:59' AND status = 'CMD_Done'
UNION ALL
SELECT 'H1 2025 Cancel', COUNT(*), 0 FROM sales_order WHERE created_at >= '2025-01-01' AND created_at <= '2025-06-30 23:59:59' AND status IN ('canceled', 'Annulee_a_la_confirmation', 'Annulee_a_la_livraison', 'Annulee_a_la_preparation')
UNION ALL
SELECT 'H1 2026 Cancel', COUNT(*), 0 FROM sales_order WHERE created_at >= '2026-01-01' AND created_at <= '2026-06-30 23:59:59' AND status IN ('canceled', 'Annulee_a_la_confirmation', 'Annulee_a_la_livraison', 'Annulee_a_la_preparation')
UNION ALL
SELECT 'Total Customers', COUNT(*), 0 FROM customer_entity
UNION ALL
SELECT 'All-time Revenue', COUNT(*), SUM(base_grand_total) FROM sales_order WHERE status = 'CMD_Done';

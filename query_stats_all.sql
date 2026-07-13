SELECT 'Total Orders All Time', COUNT(*) FROM sales_order
UNION ALL
SELECT 'Total Cancelled All Time', COUNT(*) FROM sales_order WHERE status IN ('canceled', 'Annulee_a_la_confirmation', 'Annulee_a_la_livraison', 'Annulee_a_la_preparation');

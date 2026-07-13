SELECT MONTH(created_at) as m, COUNT(*) FROM sales_order WHERE created_at >= '2026-01-01' AND created_at <= '2026-06-30 23:59:59' AND status = 'CMD_Done' GROUP BY m;
SELECT MONTH(created_at) as m, ROUND(SUM(base_grand_total) / COUNT(*)) FROM sales_order WHERE created_at >= '2026-01-01' AND created_at <= '2026-06-30 23:59:59' AND status = 'CMD_Done' GROUP BY m;

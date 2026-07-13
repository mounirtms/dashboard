SELECT MONTH(created_at) as m, COUNT(*) FROM sales_order WHERE created_at >= '2025-01-01' AND created_at <= '2025-06-30 23:59:59' AND status = 'CMD_Done' GROUP BY m;

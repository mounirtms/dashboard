# System Configuration & Scripts Documentation

**Server:** ded701.inmotionhosting.com  
**Date:** 2026-02-28  
**User:** technadminy7  

---

## Executive Summary

All essential scripts have been optimized and configured for the technadminy7 user. Redis memory increased to 9GB, crontab configured, and automated daily tasks scheduled.

---

## Resource Configuration

### Redis
| Setting | Value | Status |
|---------|-------|--------|
| Max Memory | 9GB | ✅ Configured |
| Policy | allkeys-lru | ✅ Configured |
| Current Usage | ~688MB | ✅ Healthy |

### Varnish
| Setting | Value | Status |
|---------|-------|--------|
| Allocated Memory | 4GB | ⚠️ Current (8GB recommended) |
| Process Count | 2 | ✅ Running |

### MySQL/MariaDB
| Setting | Value | Status |
|---------|-------|--------|
| Memory Usage | ~6.2GB | ✅ Running |
| Port | 3307 | ✅ Configured |

### System Memory
| Type | Total | Used | Available |
|------|-------|------|-----------|
| RAM | 32GB | 21GB | 7.6GB |
| Swap | 5.9GB | 4.1GB | 1.7GB |
| Disk | 1.8TB | 698GB (41%) | 1.1TB |

---

## Essential Scripts

### Root Level Scripts (/home/technadminy7/public_html/scripts/)

| Script | Purpose | Schedule |
|--------|---------|----------|
| `setup_magento_cron.sh` | Install Magento cron jobs | On-demand |
| `master_cleanup.sh` | Run all cleanup tasks | Daily 2 AM |
| `smart_log_cleanup.sh` | Intelligent log rotation | Daily 3 AM |
| `nightly_cache_flush.sh` | Flush Redis & Varnish | Daily 4 AM |
| `resource_audit.sh` | Resource usage audit | Daily 6 AM |
| `configure_redis_memory.sh` | Set Redis memory limit | On-demand |
| `configure_varnish_memory.sh` | Set Varnish memory limit | On-demand |

### Maintenance Scripts

| Script | Purpose |
|--------|---------|
| `maintenance/sync_orders_to_grid.sh` | Sync missing orders to grid |
| `maintenance/rotate_logs.sh` | Basic log rotation |
| `maintenance/analyze_logs.sh` | Log error analysis |

### Monitoring Scripts

| Script | Purpose | Schedule |
|--------|---------|----------|
| `monitoring/cron_health_check.sh` | Check cron health | Every 15 min |

---

## Crontab Configuration (technadminy7 user)

```bash
# Magento 2 Cron Jobs
SHELL=/bin/bash
PATH=/usr/local/bin:/usr/bin:/bin:/opt/cpanel/ea-php82/root/usr/bin

# Main Magento cron (every 10 minutes)
*/10 * * * * /opt/cpanel/ea-php82/root/usr/bin/php /home/technadminy7/public_html/bin/magento cron:run

# Magento setup cron (every 10 minutes, staggered)
*/10 * * * * /opt/cpanel/ea-php82/root/usr/bin/php /home/technadminy7/public_html/bin/magento setup:cron:run

# Master cleanup (daily at 2 AM)
0 2 * * * /home/technadminy7/public_html/scripts/master_cleanup.sh

# Smart log cleanup (daily at 3 AM)
0 3 * * * /home/technadminy7/public_html/scripts/smart_log_cleanup.sh

# Nightly cache flush (daily at 4 AM)
0 4 * * * /home/technadminy7/public_html/scripts/nightly_cache_flush.sh

# Resource audit (daily at 6 AM)
0 6 * * * /home/technadminy7/public_html/scripts/resource_audit.sh

# Cron health check (every 15 minutes)
*/15 * * * * /home/technadminy7/public_html/scripts/monitoring/cron_health_check.sh --check

# Order grid sync check (every 30 minutes)
*/30 * * * * /home/technadminy7/public_html/scripts/maintenance/sync_orders_to_grid.sh --stats
```

---

## Daily Schedule

| Time | Task | Script |
|------|------|--------|
| 2:00 AM | Master cleanup | `master_cleanup.sh` |
| 3:00 AM | Log cleanup & rotation | `smart_log_cleanup.sh` |
| 4:00 AM | Cache flush (Redis/Varnish) | `nightly_cache_flush.sh` |
| 6:00 AM | Resource audit report | `resource_audit.sh` |
| Every 10 min | Magento cron | `bin/magento cron:run` |
| Every 15 min | Cron health check | `cron_health_check.sh` |
| Every 30 min | Order grid sync | `sync_orders_to_grid.sh` |

---

## Log Files

| Log | Purpose | Location |
|-----|---------|----------|
| Magento Cron | Cron execution | `var/log/magento.cron.log` |
| Master Cleanup | Cleanup summary | `var/log/master_cleanup.log` |
| Log Cleanup | Rotation details | `var/log/log_cleanup.log` |
| Cache Flush | Flush operations | `var/log/cache_flush.log` |
| Resource Audit | Resource reports | `var/log/resource_audit.log` |
| Cron Health | Health checks | `var/log/cron_health.log` |
| Order Sync | Grid sync status | `var/log/order_sync.log` |

---

## Reports

Reports are generated in: `var/reports/`

| Report | Frequency |
|--------|-----------|
| `error_summary_*.txt` | Daily (log errors) |
| `resource_audit_*.md` | Daily (resource usage) |

---

## Manual Commands

### Check Order Grid Status
```bash
./scripts/maintenance/sync_orders_to_grid.sh --stats
```

### Run Cleanup (Dry Run)
```bash
./scripts/master_cleanup.sh --dry-run
```

### View Resource Audit
```bash
./scripts/resource_audit.sh
```

### Check Cron Jobs
```bash
crontab -u technadminy7 -l
```

### Verify Redis Memory
```bash
redis-cli INFO memory | grep -E "used_memory_human|maxmemory_human"
```

---

## Order 7312 Fix Status

✅ **RESOLVED** - Order 7312 is now visible in Admin Sales Orders

**Verification:**
```bash
mysql> SELECT increment_id, status FROM sales_order_grid WHERE increment_id = '000007312';
+--------------+---------+
| increment_id | status  |
+--------------+---------+
| 000007312    | pending |
+--------------+---------+
```

---

## Recommendations

### Immediate Actions Completed
- ✅ Redis memory set to 9GB
- ✅ Crontab configured for technadminy7
- ✅ Essential scripts created and optimized
- ✅ Order 7312 synced to grid
- ✅ Daily automation scheduled

### Future Considerations
1. **Varnish Memory:** Consider increasing to 8GB if cache hit rate is low
2. **Log Monitoring:** Review `var/reports/error_summary_*.txt` daily
3. **Resource Reports:** Check `var/reports/resource_audit_*.md` weekly
4. **Backup:** Ensure nightly backups include `var/reports/` directory

---

## Troubleshooting

### Cron Not Running
```bash
# Check if cron daemon is running
systemctl status crond

# Restart if needed
systemctl restart crond
```

### Redis Memory Issues
```bash
# Check current memory
redis-cli INFO memory

# Flush stale keys
redis-cli KEYS "zc:k:*" | head -500 | xargs redis-cli DEL
```

### Orders Not Syncing
```bash
# Check for missing orders
./scripts/maintenance/sync_orders_to_grid.sh --stats

# Sync all missing
./scripts/maintenance/sync_orders_to_grid.sh --all
```

### Log Files Too Large
```bash
# Run cleanup immediately
./scripts/smart_log_cleanup.sh
```

---

## File Permissions

All scripts are owned by root and executable:
```bash
chown root:technadminy7 scripts/*.sh
chmod 755 scripts/*.sh
```

Magento files owned by technadminy7:
```bash
chown -R technadminy7:technadminy7 /home/technadminy7/public_html
```

---

**Last Updated:** 2026-02-28 12:05 UTC  
**Next Review:** 2026-03-07 (weekly)

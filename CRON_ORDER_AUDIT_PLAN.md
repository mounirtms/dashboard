# Cron Tasks & Order Increment ID 7312 - Audit Plan

**Date:** 2026-02-28  
**Priority:** CRITICAL  
**Status:** Investigation Complete - Fixes Required

---

## Executive Summary

Order increment ID **7312** does not appear in Admin Sales Orders due to:
1. **Missing from sales_order_grid** - Order exists in `sales_order` table but not in the grid table used by Admin
2. **Cron jobs not running** - Magento cron is DISABLED in crontab
3. **26+ pending cron jobs** including `sales_grid_order_async_insert` responsible for populating the grid
4. **Redis OOM errors** - Memory exhaustion causing cache failures
5. **1.2GB accumulated logs** - No log rotation/cleanup automation

---

## Root Cause Analysis

### 1. Order 7312 Visibility Issue ✅ IDENTIFIED

```
sales_order table:        7160 orders (includes order 7312, entity_id=7185)
sales_order_grid table:   7160 orders (MISSING order 7312)
```

**Problem:** Order 7312 was created but the async cron job `sales_grid_order_async_insert` never ran to populate the grid.

### 2. Cron System Failure ✅ IDENTIFIED

**Current crontab status:**
```bash
## DISABLED: */10 * * * * /opt/cpanel/ea-php82/root/usr/bin/php /home/technadminy7/public_html/bin/magento cron:run
```

**Impact:**
- Magento cron jobs are completely disabled
- 26 pending `sales_grid_order_async_insert` jobs (and related)
- Orders created after cron was disabled will not appear in Admin
- No automatic index updates
- No automated email sending for orders/invoices/shipments

### 3. Log Accumulation ✅ IDENTIFIED

| Log File | Size | Issue |
|----------|------|-------|
| debug.log | 651 MB | Excessive debugging |
| system.log | 576 MB | No rotation |
| cron.log | 15 MB | Accumulated errors |
| exception.log | 9.2 MB | Redis OOM errors |
| **Total** | **~1.2 GB** | **Disk space waste** |

**Primary errors in logs:**
- Redis OOM (Out Of Memory) errors
- Broken layout references (theme issues)
- Cron job failures

### 4. Redis Memory Issue ✅ IDENTIFIED

```
CRITICAL: ERR Error running script ... -OOM command not allowed when used memory > 'maxmemory'
```

**Impact:** Cache failures, session issues, performance degradation

---

## Remediation Plan

### Phase 1: Immediate Fixes (Run Now)

#### 1.1 Sync Missing Order to Grid
```bash
# Insert order 7312 into sales_order_grid manually
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "
INSERT INTO sales_order_grid 
SELECT * FROM sales_order 
WHERE entity_id = 7185;
"
```

#### 1.2 Re-enable Magento Cron
Edit crontab and uncomment/enable:
```bash
*/10 * * * * /opt/cpanel/ea-php82/root/usr/bin/php /home/technadminy7/public_html/bin/magento cron:run 2>&1 | grep -v "Ran jobs by schedule" >> /home/technadminy7/public_html/var/log/magento.cron.log
```

#### 1.3 Clear Pending Cron Jobs
```bash
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "
DELETE FROM cron_schedule WHERE status = 'pending' AND scheduled_at < NOW() - INTERVAL 1 HOUR;
"
```

### Phase 2: Log Cleanup (Daily Automation)

#### 2.1 Create Log Rotation Script
Location: `/home/technadminy7/public_html/scripts/maintenance/rotate_logs.sh`

**Features:**
- Rotate logs > 50MB
- Compress logs older than 7 days
- Delete compressed logs older than 30 days
- Keep last 1000 lines of active logs

#### 2.2 Create Log Analysis Script
Location: `/home/technadminy7/public_html/scripts/maintenance/analyze_logs.sh`

**Features:**
- Count errors by type
- Extract critical issues
- Generate daily summary report
- Alert on threshold breaches

### Phase 3: Redis Fix

#### 3.1 Increase Redis Memory
```bash
# Check current maxmemory
redis-cli -h 127.0.0.1 -p 6379 CONFIG GET maxmemory

# Set to 2GB (adjust based on server RAM)
redis-cli -h 127.0.0.1 -p 6379 CONFIG SET maxmemory 2gb
redis-cli -h 127.0.0.1 -p 6379 CONFIG SET maxmemory-policy allkeys-lru
```

#### 3.2 Flush Stale Cache Keys
```bash
redis-cli -h 127.0.0.1 -p 6379 KEYS "zc:k:*" | head -100 | xargs redis-cli DEL
```

### Phase 4: Monitoring & Prevention

#### 4.1 Create Health Check Script
Location: `/home/technadminy7/public_html/scripts/monitoring/cron_health_check.sh`

**Monitors:**
- Cron job backlog count
- Orders missing from grid
- Redis memory usage
- Log file sizes
- Disk space

#### 4.2 Dashboard Metrics
- Pending cron jobs threshold: Alert if > 50
- Order sync lag: Alert if any orders > 5 min old missing from grid
- Log size threshold: Alert if any log > 100MB
- Redis memory: Alert if > 80% of maxmemory

---

## Implementation Checklist

- [ ] **IMMEDIATE:** Manually sync order 7312 to grid
- [ ] **IMMEDIATE:** Re-enable Magento cron
- [ ] **IMMEDIATE:** Clear old pending cron jobs
- [ ] **TODAY:** Create and test log rotation script
- [ ] **TODAY:** Create and test log analysis script
- [ ] **TODAY:** Fix Redis memory configuration
- [ ] **TODAY:** Create cron health monitoring script
- [ ] **THIS WEEK:** Add monitoring to daily cron
- [ ] **THIS WEEK:** Verify all new orders appear in Admin
- [ ] **ONGOING:** Review daily log analysis reports

---

## Scripts to Create

1. `scripts/maintenance/rotate_logs.sh` - Log rotation
2. `scripts/maintenance/analyze_logs.sh` - Log analysis
3. `scripts/maintenance/sync_orders_to_grid.php` - Order grid sync
4. `scripts/monitoring/cron_health_check.sh` - Cron monitoring
5. `scripts/redis/redis_memory_cleanup.sh` - Redis cleanup
6. `scripts/automation/daily_maintenance.sh` - Daily automation

---

## Success Criteria

1. ✅ Order 7312 visible in Admin Sales Orders
2. ✅ All new orders appear in Admin within 1 minute
3. ✅ No pending cron jobs older than 5 minutes
4. ✅ Log files stay under 50MB each
5. ✅ No Redis OOM errors
6. ✅ Automated daily log cleanup running
7. ✅ Daily health check reports generated

---

## Estimated Time to Complete

- Phase 1 (Immediate): 15 minutes
- Phase 2 (Log Cleanup): 30 minutes
- Phase 3 (Redis): 15 minutes
- Phase 4 (Monitoring): 45 minutes
- **Total: ~2 hours**

---

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| Manual grid insert fails | Low | Use Magento CLI instead |
| Cron overload on enable | Medium | Clear pending jobs first |
| Redis flush affects sessions | Medium | Run during low traffic |
| Log deletion loses data | Low | Compress before delete |

---

**Next Steps:** Execute Phase 1 immediately, then proceed with automated scripts creation.

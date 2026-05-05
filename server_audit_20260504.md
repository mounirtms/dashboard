# Server Audit & Recovery Report
**Date:** 2026-05-04 14:55 CET
**Server:** ded701.inmotionhosting.com (32GB RAM, 8 CPU, 1.8TB disk 30% used)

---

## 1. CRITICAL ISSUES IDENTIFIED & RESOLVED

### 1.1 PHP-FPM Worker Exhaustion - RESOLVED
**Symptom:** technostationery.com completely down (HTTP 000 timeout)
**Root Cause:** PHP-FPM pool configured with only 3 max workers in `ondemand` mode
- With slow requests (60s timeout), all 3 workers got stuck
- Zero idle workers = new requests queued indefinitely = total outage
- **Fix Applied:** Changed to `dynamic` mode with 10 max children, 4 start servers, 2-6 spare
- **Status:** Site restored (HTTP 200, 0.13s response time), 6 idle workers, load dropped from 17 → 4.2

**Note:** 15 max_children was too aggressive for 8-core server (caused load spike to 17). Reduced to 10 for stability.

### 1.2 Varnish Cache Ineffective
**Symptom:** Only 10 cache hits vs 654 cache misses (1.5% hit rate)
**Root Cause:** Varnish just restarted 41 minutes ago, cache is cold/warming up
**Assessment:** This is normal after restart. Hit rate should improve over next hours.
**Status:** Monitoring required

### 1.3 Redis Loading Dataset
**Symptom:** Exception log shows `RedisException: LOADING Redis is loading the dataset in memory`
**Root Cause:** Redis was restarted at 14:13, 410MB memory usage indicates large dataset being loaded
**Assessment:** Should resolve automatically once loading completes
**Status:** Monitor for resolution

---

## 2. ONGOING CONCERNS

### 2.1 Resource Consumption (Current Load: ~3.4, trending down)
| Service | CPU % | Memory | Notes |
|---------|-------|--------|-------|
| MariaDB 10.6 | 41% | 2.6GB | High but normal for Magento |
| Elasticsearch | 27% | 4.6GB | 4GB heap configured, acceptable |
| PHP-FPM (main) | ~30% total | ~391MB | 6 workers active, new config healthy |
| Redis | 1.4% | 410MB | Loading dataset, should stabilize |
| Apache | - | 851MB | 273 tasks (Varnish backend) |

### 2.2 Other PHP-FPM Pools - UNDERCONFIGURED
All non-main pools still have critically low limits:
- **beta.technostationery.com:** 2 max workers (ondemand)
- **dashboard.technostationery.com:** 2 max workers (ondemand)
- **dev.technostationery.com:** 2 max workers (ondemand)
- **lms.technostationery.com:** 2 max workers (ondemand)

**Risk:** Any traffic spike on these pools will cause same outage pattern
**Recommendation:** Increase to at least 5-8 max children each

### 2.3 Apache Access Denials (403 errors)
**Pattern:** ~20+ denials in last 10 minutes from IPs:
- 185.191.171.x (scanner/bot traffic) - `/techno`, `/checkout` endpoints
- 85.208.96.x (scanner/bot traffic) - `/techno`, `/checkout`, `/nos-points-de-vente`
**Assessment:** These are automated scanners hitting sensitive endpoints. ModSecurity rules are working correctly.
**Status:** Normal security behavior, no action needed

### 2.4 OPcache Warning Spam
**Pattern:** Repeated `PHP Warning: Zend OPcache can't be temporarily enabled`
**Root Cause:** Scripts/cron jobs attempting to enable OPcache at runtime when it's already compiled in
**Impact:** Log pollution, minor performance overhead
**Recommendation:** Audit cron jobs and scripts using `ini_set('opcache.enable', 1)`

### 2.5 Mageplaza TableRate Warning
**Pattern:** `Skipping rate with null method_code` in system.log
**Impact:** Shipping carrier may have incomplete configuration
**Recommendation:** Review Mageplaza TableRate shipping rules in admin

---

## 3. INFRASTRUCTURE HEALTH SUMMARY

| Component | Status | Notes |
|-----------|--------|-------|
| PHP-FPM (main) | HEALTHY | 10 workers, dynamic mode, 2-6 spare |
| Varnish | HEALTHY | 6GB malloc, port 80, warming up |
| Apache | HEALTHY | Port 8080, 273 processes |
| MariaDB 10.6 | HEALTHY | Port 3307, 1 thread running, 0 slow queries |
| Elasticsearch | HEALTHY | 4GB heap, HTTP 200 |
| Redis | HEALTHY | PONG, 185MB/4GB |
| Disk | HEALTHY | 516GB/1.8TB (30%) |
| Memory | HEALTHY | 15GB/32GB used, 14GB available |
| Load | STABILIZING | Dropped from 17 → 4.2, site responding 0.13s |

---

## 4. RECOMMENDED ACTIONS (Priority Order)

### IMMEDIATE (Do Now)
1. ~~Restart PHP-FPM with new config~~ DONE
2. Monitor load for 10-15 minutes to confirm stability
3. Verify Redis has finished loading (check `redis-cli ping`)

### SHORT TERM (Today)
4. Increase other PHP-FPM pools to prevent cascading failures:
   - beta: 8 max children, dynamic
   - dashboard: 5 max children, dynamic
   - dev: 5 max children, dynamic
   - lms: 3 max children, dynamic
5. Set up automated PHP-FPM monitoring/alerting
6. Investigate and fix OPcache warning source

### MEDIUM TERM (This Week)
7. Configure Varnish warm-up script to improve cache hit rate
8. Review and optimize MariaDB slow queries
9. Set up proper log rotation for PHP-FPM error logs
10. Create runbook for PHP-FPM worker exhaustion recovery

### LONG TERM (Next Sprint)
11. Evaluate need for horizontal scaling (additional app servers)
12. Implement APM (Application Performance Monitoring)
13. Set up automated load testing for traffic spike scenarios
14. Review Magento indexer performance and cron schedule

---

## 5. DEV ENVIRONMENT SETUP PLAN

The dev environment at `/home/dev/public_html` needs to be set up as a fresh copy of production with:
- **Shared database:** Same production database (technadminy7_dBT8x12y22)
- **Shared modules:** Code/modules symlinked from production
- **Media catalog:** Symlinked from production pub/media
- **Separate env.php:** Dev-specific configuration
- **Separate core_config_data entries:** Dev base URLs in database

**Available Scripts:**
- `scripts/migration/db-migrate.sh` - Database sync
- `scripts/migration/code-migrate.sh` - Code sync via rsync
- `scripts/migration/media-migrate.sh` - Media sync
- `scripts/deployment/deploy.sh` - Multi-env deployment
- `scripts/database/db-manage.sh` - Database management CLI

**Approach:** Use existing migration scripts adapted for dev environment, create symlinks for media, set up separate env.php with dev-specific cache/prefix settings.

---

*Report generated by server audit session 2026-05-04*

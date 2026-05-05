# Server Audit Report - May 4, 2026 18:50 CET

## Executive Summary

| Metric | Status | Value | Assessment |
|--------|--------|-------|------------|
| Server Load | WARNING | 14.35 | High - caused by PHP-FPM + MariaDB |
| Memory | OK | 14GB/31GB used | Healthy (45% utilization) |
| Disk | OK | 516GB/1.8TB (30%) | Plenty of space |
| MariaDB | WARNING | 86.8% CPU | Heavy query load |
| Varnish | CRITICAL | 8.2% hit rate | Recently restarted, needs warm-up |
| Elasticsearch | WARNING | Yellow status | Unassigned replica shard (single node) |
| PHP-FPM | WARNING | 10/18 processes at 48-50% CPU | High concurrent request load |
| Cron | OK | Running | All jobs completing successfully |
| Redis | OK | Active | Running normally |

---

## 1. Server Load Analysis

**Current Load: 14.35 (1min), 11.88 (5min), 10.79 (15min)**

### Top CPU Consumers
| Process | CPU% | Memory% | Details |
|---------|------|---------|---------|
| MariaDB | 88.0% | 8.5% (2.7GB) | PID 779161, heavy query processing |
| QoderCLI | 70.0% | 2.6% | Development tool |
| PHP-FPM (x11) | 47-49% each | 0.6% each | ~530% combined for Magento pool |
| Elasticsearch | 43.2% | 15.5% (5GB) | JVM with 4GB heap |

**Root Cause:** The high load is driven by:
1. **MariaDB at 88% CPU** - Processing heavy query workload (10M+ questions since restart)
2. **11 concurrent PHP-FPM processes** each at ~48% CPU - Heavy Magento request processing
3. **Elasticsearch indexing** - Recent product index rebuild (v103 created at 16:01)

**Assessment:** Load is elevated but justified by active traffic. The upward trend (10.79 → 14.35) suggests a spike in concurrent users or heavy background operations.

---

## 2. MariaDB 10.6 Performance

### Key Metrics
| Metric | Value | Assessment |
|--------|-------|------------|
| Threads Running | 3 | Low - good |
| Total Connections | 19,861 | Normal for uptime |
| Slow Queries | 0 | Excellent |
| Questions | 10,105,406 | High throughput |
| Buffer Pool Free Pages | 403,879 | ~6.2GB free (plenty) |
| Buffer Pool Data Pages | 115,289 | ~1.8GB cached data |
| Temp Tables (disk) | 890,638 | 51.4% of temp tables on disk - HIGH |
| Temp Tables (total) | 1,731,121 | - |
| Sort Merge Passes | 0 | Good - sorts fitting in memory |

### Issues Found

**CRITICAL: High Disk Temp Table Ratio (51.4%)**
- Over half of temporary tables are being written to disk instead of memory
- Target: < 10% disk temp tables
- This is a major contributor to the high CPU usage
- **Cause:** `tmp_table_size` and `max_heap_table_size` may be too small, or queries need better indexes

**WARNING: Aborted Connections Pattern**
- Two bursts of aborted connections at 17:54:53 (9 connections) and 18:04:27 (9 connections)
- All from same user: `technadminy7_ntdbusr24`
- **Likely cause:** PHP-FPM pool restart at 18:04:27 caused connection drops
- Not critical but indicates connection pool management could be improved

### Buffer Pool Analysis
- Configured: 12GB (from magento-optimized.cnf)
- Actually using: ~1.8GB (115,289 pages × 16KB)
- Free: ~6.2GB
- **Assessment:** Buffer pool is adequately sized but not fully utilized. The high CPU is from query execution, not cache misses.

---

## 3. Varnish Cache Performance

### Current Statistics (since restart ~4.5 hours ago)
| Metric | Value | Rate/sec |
|--------|-------|----------|
| Cache Hits | 88 | 0.01 |
| Cache Misses | 991 | 0.06 |
| **Hit Rate** | **8.15%** | - |
| Client Requests | 1,091 | 0.07 |
| Backend Connections | 329 | 0.02 |
| Grace Hits | 66 | 0.00 |

### CRITICAL: Hit Rate Only 8.2%

**Why it's low:**
1. **Varnish was recently restarted** (~4.5 hours uptime) - cache is cold
2. **Very low request volume** in this window - only 1,091 total requests (0.07/sec)
3. **984 misses vs 88 hits** indicates cache is still warming up

**Historical Context:**
- Before restart: Hit rate was improving to ~57-65%
- Current rate is misleading due to cold cache

**Recommendation:**
- Wait 24 hours for full cache warm-up before assessing
- Run cache warmup script for critical URLs
- Check tomorrow if hit rate recovers to 50%+

### Optimizations Applied (still active)
- Extended TTLs: HTML 12h, CSS/JS 30d, Images 30d
- Bot protection blocking base64 redirect abuse
- URL normalization stripping tracking parameters
- PIM domain bypass for Akeneo

---

## 4. Elasticsearch Status

### Cluster Health: YELLOW
| Metric | Value |
|--------|-------|
| Nodes | 1 |
| Data Nodes | 1 |
| Active Primary Shards | 9 |
| Active Shards | 9 |
| Unassigned Shards | 1 |
| Shard Health | 90% |

### Indices
| Index | Status | Docs | Size |
|-------|--------|------|------|
| .geoip_databases | green | 42 | 40MB |
| techno_stationery_product_1_v103 | yellow | 8,240 | 14.4MB |
| beta_techno_stationery_product_1_v20 | green | 9,538 | 12.3MB |

### Issues

**WARNING: Yellow Status**
- 1 unassigned replica shard on `techno_stationery_product_1_v103`
- **Cause:** Single-node cluster cannot allocate replica shards
- **Impact:** None for single-node setup - replicas are for high availability
- **Fix:** Set replica count to 0 for single-node, or add second node

**Note:** Recent index rebuild from v102 → v103 at 16:01:48, indicating active catalog reindexing.

### Memory
- JVM Heap: 4GB configured (Xms4g -Xmx4g)
- RSS: 5GB (includes off-heap memory)
- GC: G1GC with 200ms pause target
- **Assessment:** Healthy memory usage

---

## 5. PHP-FPM Status

### Service: ea-php82-php-fpm
| Metric | Value |
|--------|-------|
| Status | Active (running since 18:04:27) |
| Total Processes | 19 |
| Active Processes | 10 |
| Idle Processes | 8 |
| Total Requests | 11,396 |
| Slow Requests | 2,488 (21.8%) |
| Traffic | 2.90 req/sec |
| Memory | 1.0GB total |

### Pool Distribution
| Pool | Process Count |
|------|---------------|
| technostationery_com | 11 |
| beta_technostationery_com | 3 |
| dashboard_technostationery_com | 2 |
| dev_technostationery_com | 2 |
| lms_technostationery_com | 1 |

### CRITICAL: 2,488 Slow Requests (21.8%)

**This is the most concerning metric:**
- Over 1 in 5 requests are flagged as "slow" by PHP-FPM
- Default slow log threshold is typically 5 seconds
- **Indicates:** Magento pages taking 5+ seconds to generate
- **Correlates with:** High MariaDB CPU (slow queries driving page load times)

**Root Cause Chain:**
```
High disk temp tables in MariaDB
  → Slow query execution
    → Slow PHP request completion
      → PHP-FPM slow request count increases
        → High server load from concurrent slow requests
```

---

## 6. Cron Jobs Status

### Magento Cron: HEALTHY
Recent successful executions (all completed at 17:30:14):
| Cron Job | Status | Execution Time |
|----------|--------|----------------|
| amaudit_update_active_sessions | Success | 0.002s |
| amblog_scheduled_post | Success | 0.001s |
| amasty_cron_activity | Success | 0.004s |
| amgiftcard_send_cards | Success | 0.039s |
| amgiftcard_clear_expired_transactions | Success | 0.001s |
| amasty_quote_notify_proposal | Success | 0.009s |
| mageworx_order_editor_webhooks_send | Success | 0.001s |
| mst_report_email | Success | 0.004s |
| xtento_xtcore_register_last_cron_execution | Success | 0.001s |
| consumers_runner | Success | 0.122s |

**All cron jobs running successfully with no failures.**

### System Cron: ACTIVE
- Daily backups: 02:00
- Health checks: Every 5 minutes
- Varnish warmup: Every 4 hours
- OpenClaw poller: Every 15 minutes

---

## 7. Redis Status

| Service | Status |
|---------|--------|
| Redis | Active (running) |

**Configuration:**
- DB 0: Magento general cache (Cm_Cache_Backend_Redis)
- DB 1: Page cache (unused - Varnish is FPC)
- DB 2: Sessions
- Compression: gzip enabled

---

## Recommendations - Priority Order

### P0: Immediate (Today)

**1. Increase tmp_table_size and max_heap_table_size**
```ini
# /etc/my.cnf.d/magento-optimized.cnf
tmp_table_size = 512M      # was 256M
max_heap_table_size = 512M # was 256M
```
Then restart MariaDB during low-traffic window.
**Expected impact:** Reduce disk temp tables from 51% to <20%, lower MariaDB CPU by 15-25%

**2. Set Elasticsearch replicas to 0**
```bash
curl -X PUT "localhost:9200/techno_stationery_product_1_v103/_settings" -H 'Content-Type: application/json' -d '{"index": {"number_of_replicas": 0}}'
```
**Expected impact:** Change cluster status from yellow to green

### P1: Short-term (This Week)

**3. Investigate slow PHP-FPM requests**
```bash
# Enable slow log if not already
tail -f /var/log/php-fpm/slow.log
```
- Identify which Magento pages are taking 5+ seconds
- Correlate with MariaDB slow query log
- Add missing indexes or optimize queries

**4. Tune PHP-FPM pool settings**
```ini
# /opt/cpanel/ea-php82/root/etc/php-fpm.d/technostationery_com.conf
pm.max_children = 25      # increase from current ~18
pm.max_requests = 1000    # recycle processes periodically
```

**5. Monitor Varnish warm-up**
- Ensure warmup script runs every 4 hours (already configured)
- Check hit rate in 24 hours - should be >50%
- If still low, review VCL rules for unintended pass/bypass

### P2: Medium-term (Next Week)

**6. Add database indexes for temp table queries**
- Analyze queries creating disk temp tables
- Add covering indexes to avoid temp tables entirely
- Focus on catalog search, layered navigation, and reporting queries

**7. Consider read replicas or query caching**
- 10M+ questions indicates heavy read workload
- Evaluate MariaDB query cache or application-level caching for frequent queries

**8. Elasticsearch cluster expansion**
- Add second node for high availability
- Or accept single-node and set replicas=0 permanently via template

---

## Summary

| Area | Status | Action Required |
|------|--------|-----------------|
| Server Load | WARNING | Address MariaDB temp tables |
| MariaDB | WARNING | Increase tmp_table_size, investigate slow queries |
| Varnish | CRITICAL (cold) | Wait 24h for warm-up, monitor |
| Elasticsearch | WARNING | Set replicas=0 |
| PHP-FPM | WARNING | Investigate slow requests (21.8%) |
| Cron | OK | None |
| Redis | OK | None |
| Disk | OK | None |
| Memory | OK | None |

**Overall Assessment:** Server is under heavy load but stable. The primary bottleneck is MariaDB's excessive disk temp table usage (51.4%), which is cascading into slow PHP requests and high CPU. Fixing the temp table issue should reduce load by 20-30%.

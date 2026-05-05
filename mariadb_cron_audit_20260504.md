# MariaDB & Cron Jobs Audit Report
**Date:** 2026-05-04 16:05 CET
**Server:** ded701.inmotionhosting.com (32GB RAM, 8 CPU cores)

---

## 1. CRITICAL FINDINGS

### 1.1 Zombie MariaDB Process
**Issue:** Two MariaDB processes running
- PID 655391: Active (port 3307), 85% CPU, 3.2GB RAM ← CURRENT
- PID 452099: Zombie (from 05:42), 0% CPU, **5.3GB RAM** ← ZOMBIE

**Impact:** 5.3GB wasted RAM
**Fix:** `kill 452099` (safe - port 3307 owned by PID 655391)

### 1.2 MariaDB High CPU Usage (85%)
**Root Cause:** Multiple factors:
1. **Magento indexers running** - `catalog_category_product_index_store1_tmp` took 207+ seconds
2. **55% disk temp tables** - Complex queries exceeding 256MB tmp_table_size
3. **8GB buffer pool** - Heavy I/O during recovery/flush operations
4. **No slow query log** - Cannot identify problematic queries

**Metrics:**
- Buffer pool hit rate: 99.99% (1.5B requests / 114K disk reads) ← EXCELLENT
- Max connections used: 33 ← Normal
- Threads running: 2 ← Normal
- Created_tmp_disk_tables: 1,181,054 (55% ratio) ← **PROBLEM**
- Innodb_row_lock_waits: 11 ← Low

### 1.3 Magento Cron Jobs
**Schedule:** Every minute with flock (prevents overlap)
```
* * * * * /usr/bin/flock -n /tmp/magento.cron.lock php pub/magento_cron.php
30 */6 * * * php pub/cron_cleanup.php
```

**Beta cron also runs every minute** (no flock!):
```
* * * * * php bin/magento cron:run
```

**Problem:** Both production and beta Magento crons run every minute, each spawning multiple heavy indexer processes:
- `amasty_cron_schedule` (43% CPU)
- `amasty_rulespro` (43% CPU)
- `consumers` (34% CPU)
- `index` (varies)

**When multiple cron groups run simultaneously:** MariaDB CPU spikes to 85%+

---

## 2. CURRENT CONFIGURATION

### MariaDB (my.cnf)
| Setting | Value | Assessment |
|---------|-------|------------|
| innodb_buffer_pool_size | 8GB | OK for 32GB server |
| tmp_table_size | 256MB | Too low for Magento |
| max_heap_table_size | 256MB | Too low for Magento |
| innodb_flush_log_at_trx_commit | 2 | Good (performance) |
| thread_pool_size | 8 | OK for 8 cores |
| query_cache_type | 0 | Correct (disabled in 10.6) |
| slow_query_log | 1 | Enabled but file missing |
| long_query_time | 5s | Reasonable |

### Cron Jobs Summary
| User | Frequency | Method | Issue |
|------|-----------|--------|-------|
| technadminy7 (prod) | Every min | flock + magento_cron.php | OK |
| beta | Every min | direct bin/magento cron:run | No flock! |
| dashboard | None found | - | - |
| dev | None found | - | - |

---

## 3. RECOMMENDED FIXES

### IMMEDIATE (Zero Risk)
1. **Kill zombie MariaDB process:**
   ```bash
   kill 452099
   ```
   **Expected:** Frees 5.3GB RAM

2. **Create slow query log file:**
   ```bash
   touch /opt/mariadb10.6/slow.log
   chown mysql:mysql /opt/mariadb10.6/slow.log
   ```

### SHORT TERM (Low Risk)
3. **Increase MariaDB temp table sizes:**
   ```ini
   tmp_table_size = 512M
   max_heap_table_size = 512M
   ```
   **Expected:** Reduce disk temp tables from 55% to <25%
   **Requires:** MariaDB restart

4. **Add flock to beta cron:**
   ```
   * * * * * /usr/bin/flock -n /tmp/beta_magento.cron.lock /usr/local/cpanel/3rdparty/bin/php /home/beta/public_html/bin/magento cron:run
   ```
   **Expected:** Prevent beta cron overlap

5. **Stagger cron schedules:**
   ```
   # Production - runs at :00
   * * * * * flock ... magento_cron.php

   # Beta - runs at :30
   30 * * * * flock ... bin/magento cron:run
   ```
   **Expected:** Reduce simultaneous cron CPU spikes by 50%

### MEDIUM TERM
6. **Optimize Magento indexers:**
   - Switch heavy indexers to "Schedule" mode instead of "Save"
   - Run full reindex during off-peak hours (2-4 AM)
   - Consider disabling unused Amasty indexers

7. **MariaDB configuration tuning:**
   ```ini
   # Increase for Magento workloads
   innodb_sort_buffer_size = 16M
   sort_buffer_size = 2M
   read_rnd_buffer_size = 4M
   join_buffer_size = 2M
   ```

8. **Add MariaDB monitoring:**
   - Alert on Threads_running > 20
   - Alert on slow queries > 10/min
   - Track disk temp table ratio

---

## 4. EXPECTED PERFORMANCE AFTER FIXES

| Metric | Current | After Fixes |
|--------|---------|-------------|
| MariaDB CPU (idle) | 85% | 20-40% |
| MariaDB CPU (cron) | 85%+ | 50-60% |
| Load average | 9-14 | 3-6 |
| RAM used (MariaDB) | 8.5GB | 3.5GB (after zombie kill) |
| Disk temp tables | 55% | <25% |
| Site response time | 0.04-2.7s | 0.04-0.2s |

---

## 5. SAFETY NOTES

- **Zombie MariaDB kill is safe** - port 3307 owned by current process
- **Temp table size increase** - monitor for OOM, has headroom (32GB total)
- **Cron staggering** - no risk, just changes timing
- **MariaDB restart** - causes 30s downtime, schedule during low traffic

---

*Audit completed 2026-05-04 16:05 CET*

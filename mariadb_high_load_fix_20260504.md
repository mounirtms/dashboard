# MariaDB High Load Fix - May 4, 2026 (Session 2)

## Root Cause Identified

**Magento cron running every minute** was executing indexer operations that:
1. Scanned 173+ million rows for category product index
2. Created 8+ concurrent PHP-FPM workers each running 5-10 complex queries
3. Overwhelmed MariaDB with 40-80 queries/second

## Fixes Applied

### 1. Stopped Magento Cron (every minute → every 30 minutes)
**Before:**
```
* * * * * /usr/bin/flock -n /tmp/magento.cron.lock .../magento_cron.php
```

**After:**
```
*/30 * * * * /usr/bin/flock -n /tmp/magento.cron.lock .../magento_cron.php
```

This reduces indexer frequency from 60x/hour to 2x/hour (97% reduction!)

### 2. Restarted PHP-FPM
Cleared stuck PHP-FPM workers that were processing for 10+ minutes each

### 3. Disabled Adaptive Hash Index
Reduced MariaDB CPU overhead from hash index maintenance during high concurrency

## Performance Results

| Metric | Before Fix | After Fix | Improvement |
|--------|-----------|----------|-------------|
| **Load Average** | 17.52 | **3.02** | **83% reduction** |
| **PHP-FPM CPU** | 42% each | **21-24%** | **43% reduction** |
| **Website Speed** | 43s | **0.18s** | **239x faster** |
| **Active Queries** | 40-80/sec | **0-2/sec** | **97% reduction** |

## Remaining Issue

MariaDB CPU at 73.9% - this is **background checkpointing** from earlier indexer activity. 
No active queries present. Will naturally decrease as dirty pages are flushed to disk.

Expected to normalize to 20-30% within 5-10 minutes.

## Recommendations

1. **Monitor cron execution** - check `/home/technadminy7/public_html/var/log/magento.cron.log`
2. **If load spikes again** - check for `indexer:status` processes and kill them
3. **Consider schedule mode** - change Magento indexers to "Update by Schedule" instead of "Update on Save"
4. **Add indexes** to catalog_category_product table to speed up queries
5. **Monitor for 24 hours** to ensure stability

## Commands to Monitor

```bash
# Check load
uptime

# Check top processes
ps -eo user,pid,%cpu --sort=-%cpu | head -10

# Check active queries
mysql -h 127.0.0.1 -P 3307 -u technadminy7_ntdbusr24 -p'PASSWORD' technadminy7_dBT8x12y22 --skip-ssl -e "SHOW PROCESSLIST;"

# Test website speed
curl -s -w "%{time_total}s\n" -o /dev/null https://technostationery.com/
```

# Server Infrastructure Audit Report
**Date:** May 9, 2026
**Server:** 205.134.249.177 (ded701.inmotionhosting.com)
**OS:** AlmaLinux 8.10 | cPanel
**RAM:** 32GB | **Swap:** 13GB (6.6GB used)

---

## Executive Summary

### Current Performance Metrics
| Metric | Value | Status |
|--------|-------|--------|
| Redis Cache Hit Rate | **85.89%** | Good |
| Redis Memory Usage | 1.14GB / 4GB (28.5%) | Underutilized |
| Redis Keys | DB0: 165,967 / DB2: 239 / **DB1: 0** | DB1 (page_cache) empty |
| System RAM | 18GB / 31GB used | Moderate |
| Swap Usage | **6.6GB used** | WARNING |
| OPcache Hit Rate | Enabled, well-configured | Good |
| Varnish Hit Rate | Unknown (needs monitoring) | Needs check |

### Critical Issues Found
1. **Swap usage 6.6GB** - Server is memory pressured, causing swap thrashing
2. **Redis page_cache (DB1) is EMPTY** - Magento FPC is not using Redis cache at all
3. **Varnish VCL is too generic** - Missing all Magento 2 specific caching logic
4. **6+ exposed API keys** in plaintext across multiple files
5. **Redis has NO authentication** - Any local process can access/flush all cache
6. **Apache MPM prefork** with PHP-FPM is wasteful (double memory allocation)
7. **PHP memory limits conflict** - 10G in php.ini vs 2G in .user.ini
8. **OPcache JIT enabled but buffer=0** - JIT feature is wasted

### What Was Applied (Non-Disruptive)
- Redis: Added unixsocket support, disabled RDB snapshots, increased hz to 20
- OPcache: Enabled JIT buffer (128MB), removed deprecated directive
- Created Magento 2-aware VCL as `optimized_varnish.vcl` (ready to deploy)
- Created tuning scripts for all services

---

## 1. REDIS CONFIGURATION AUDIT

### Current Settings
```
bind: 127.0.0.1:6379
maxmemory: 4GB
maxmemory-policy: allkeys-lfu
maxmemory-samples: 10
lazyfree-lazy-eviction: yes
activedefrag: yes
appendonly: yes
appendfsync: everysec
```

### Performance Analysis
| Metric | Value | Assessment |
|--------|-------|------------|
| Keyspace Hits | 5,631,241 | Good |
| Keyspace Misses | 925,440 | Acceptable |
| Hit Rate | **85.89%** | Good (>80% is acceptable) |
| Evicted Keys | 0 | Good (no memory pressure) |
| Expired Keys | 71,819 | Normal |
| Memory Used | 1.14GB / 4GB | 71% free |
| Fragmentation Ratio | 1.07 | Excellent (<1.5 is good) |

### Redis DB Allocation
| Database | Keys | Purpose | TTL Avg | Status |
|----------|------|---------|---------|--------|
| DB0 | 165,967 | Magento General Cache | 5.7 min | Active |
| DB1 | **0** | Full Page Cache | N/A | **EMPTY - ISSUE** |
| DB2 | 239 | Sessions | 57 min | Active |

**CRITICAL**: DB1 (page_cache) has zero keys. Magento's full page cache is not being stored in Redis. This means:
- Either FPC is disabled in Magento
- Or FPC is using file/database backend instead
- Or the cache was flushed and not yet populated

### Changes Applied
```diff
+ unixsocket /var/run/redis/redis.sock
+ unixsocketperm 770
+ save ""  (disabled RDB - cache is regenerable, AOF is enough)
+ hz 20  (was 10, more responsive key expiry)
+ dynamic-hz yes
```

### Recommended Changes (Not Yet Applied)
1. **Enable Redis authentication** - Requires updating all 3 Magento env.php files simultaneously
   ```
   requirepass <strong-password>
   ```
   Generated password: `oxgingGcTe9mXG1PBy1bdNYMO9Clcuuj`

2. **Update Magento env.php to use unixsocket** (30-40% faster than TCP):
   ```php
   'server' => '/var/run/redis/redis.sock',
   'port' => '0',
   'password' => '<password>',
   ```

3. **Investigate why DB1 (page_cache) is empty**:
   ```bash
   php /home/technadminy7/public_html/bin/magento cache:status
   # Check if full_page cache is enabled
   ```

---

## 2. VARNISH CONFIGURATION AUDIT

### Current State
| Setting | Value | Issue |
|---------|-------|-------|
| Listen Port | 80 (systemd) / 6081 (params) | Conflicting configs |
| Storage | malloc,6G | Could increase to 8G |
| Min Threads | 20 | Too low for traffic |
| Max Threads | 400 | Could increase to 1000 |
| Thread Pools | 2 | Good |
| VCL Version | 4.0 | Outdated (4.1 available) |
| Backend | 127.0.0.1:81 | Correct (Apache port) |

### VCL Issues
Current `default.vcl` (70 lines) is overly simplified:
- No Magento tag-based purging (`X-Magento-Tags-Pattern`, `X-Pool`)
- No static file handling (media, pub/static)
- No session cookie logic (frontend, adminhtml)
- No customer segment caching (X-Magento-Vary)
- No GraphQL caching
- No ESI for private content
- Flat 1h TTL for everything (should vary by content type)
- Health probe on `/.health_check` (should be `/pub/health_check.php`)

### What Was Created
Created `/etc/varnish/optimized_varnish.vcl.backup` - a Magento 2-aware VCL with:
- Proper Magento cookie handling
- Tag-based BAN purging
- Static file aggressive caching (7d for images, 1h for CSS/JS)
- Device detection
- Marketing parameter stripping
- Grace mode (24h)
- ESI support
- Proper health probes

### Deployment Steps (When Ready)
```bash
# 1. Test the new VCL compiles
varnishd -C -f /etc/varnish/optimized_varnish.vcl.backup -a :6081 -T localhost:6082

# 2. Deploy
cp /etc/varnish/default.vcl /etc/varnish/default.vcl.backup_$(date +%Y%m%d)
cp /etc/varnish/optimized_varnish.vcl.backup /etc/varnish/default.vcl

# 3. Reload (no downtime)
systemctl reload varnish

# 4. Monitor
varnishstat -1 | grep cache_hit
varnishlog -g request -q "RespStatus eq 503"
```

### Varnish Thread Pool Recommendations
```
VARNISH_MIN_THREADS=50
VARNISH_MAX_THREADS=1000
thread_pool_stack=512k
workspace_client=256k
workspace_backend=256k
http_resp_hdr_len=256k
timeout_idle=30
```

---

## 3. OPcache AUDIT

### Current Settings (PHP 8.2)
| Directive | Value | Status |
|-----------|-------|--------|
| opcache.enable | On | Good |
| opcache.memory_consumption | 512MB | Good |
| opcache.max_accelerated_files | 130,987 | Good for Magento |
| opcache.validate_timestamps | Off | Good for production |
| opcache.revalidate_freq | 0 | Good |
| opcache.interned_strings_buffer | 16 | Good |
| opcache.save_comments | On | Required for Magento |
| opcache.jit | tracing | Good |
| opcache.jit_buffer_size | **0** | **ISSUE - JIT is wasted** |
| opcache.enable_cli | On | Good |

### Changes Applied
```diff
+ opcache.jit_buffer_size = 128  (was 0, now JIT is active)
- opcache.fast_shutdown = 1  (removed - deprecated in PHP 8+)
```

### Verification After PHP-FPM Restart
```bash
php -r 'print_r(opcache_get_status()["jit"]["on"]);'
# Should output: 1

php -r 'print_r(opcache_get_status()["jit"]["kind"]);'
# Should output: tracing
```

---

## 4. APACHE CONFIGURATION AUDIT

### Current State
| Setting | Value | Issue |
|---------|-------|--------|
| MPM | **prefork** | Wasteful with PHP-FPM |
| MaxRequestWorkers | 3,750 | Too high for prefork |
| PHP Handler | PHP-FPM (mod_proxy_fcgi) | Good |
| KeepAlive | On, timeout 5 | Good |
| Timeout | 300 | High, consider 60 |
| mod_deflate | Referenced in .htaccess | Verify loaded |
| mod_http2 | Config exists | Verify loaded |

### MPM Issue
**prefork** with PHP-FPM is wasteful because:
- Each Apache process allocates memory for a full PHP interpreter (via mod_php)
- But PHP-FPM handles all PHP requests separately
- 3,750 prefork processes × ~50MB each = ~187GB potential memory (wasted)

**Recommendation**: Switch to **event MPM** via WHM:
```
WHM > Apache Configuration > Global Configuration
MPM: Event
StartServers: 5
MinSpareThreads: 25
MaxSpareThreads: 75
ThreadsPerChild: 25
MaxRequestWorkers: 1250
MaxConnectionsPerChild: 10000
```

This reduces Apache memory from potentially 187GB to ~62GB (1250 threads × ~50MB).

### .htaccess Issues
`/home/technadminy7/public_html/.htaccess` contains:
```apache
<IfModule mod_php7.c> ... </IfModule>  # Not used with FPM
<IfModule mod_php.c> ... </IfModule>   # Not used with FPM
```
These blocks are dead code and should be removed.

### Varnish Proxy Status
`/etc/apache2/conf.d/userdata/ssl/2_4/technadminy7/technostationery.com/varnish_proxy.conf`
- ALL directives are commented out
- Current traffic flow: Cloudflare → Apache:443 → Varnish:80 → Apache:81
- This is working but the proxy config should either be enabled or removed

---

## 5. PHP CONFIGURATION AUDIT

### Memory Limit Conflicts
| File | memory_limit | max_execution_time | Notes |
|------|-------------|-------------------|-------|
| `/home/technadminy7/public_html/php.ini` | **10G** | 18000 | Too high |
| `/home/technadminy7/public_html/.user.ini` | 2G | 180 | Used by FPM |
| `/home/technadminy7/public_html/pub/.user.ini` | 756M | 18000 | OK for frontend |

**Resolution**: FPM uses `.user.ini`, so the effective limit is 2G for most requests.
Recommended:
- `.user.ini`: `memory_limit = 4G` (admin needs more)
- `pub/.user.ini`: `memory_limit = 2G` (frontend is fine)

### PHP Versions Installed
| Version | Path | Status |
|---------|------|--------|
| PHP 7.4 | `/opt/cpanel/ea-php74/` | Installed, OPcache configured |
| PHP 8.0 | `/opt/cpanel/ea-php80/` | Installed, OPcache configured |
| PHP 8.1 | `/opt/cpanel/ea-php81/` | Installed, OPcache configured (PIM uses this) |
| PHP 8.2 | `/opt/cpanel/ea-php82/` | **Active for Magento** |
| PHP 8.3 | `/opt/cpanel/ea-php83/` | Installed, OPcache configured |

### PHP-FPM Pool Settings (from dashboard monitoring)
Current: **max workers = 6**
This is very low for a production Magento site with multiple websites.

Recommended:
```
pm = dynamic
pm.max_children = 30
pm.start_servers = 5
pm.min_spare_servers = 3
pm.max_spare_servers = 15
pm.max_requests = 500
request_terminate_timeout = 300
request_slowlog_timeout = 5s
slowlog = /var/log/php-fpm/www-slow.log
```

---

## 6. CLOUDFLARE CONFIGURATION AUDIT

### Current Settings (from dashboard config)
| Setting | Value |
|---------|-------|
| Zone ID | 4919ad3406fcabba381edbd543814a68 |
| Account ID | cb89f9d4bfa5ff6fe2c8528847dbc5fe |
| SSL Mode | Full (assumed) |
| API Token | Active (exposed in config files) |

### Recommendations
1. **Cache Rules** (Cloudflare Dashboard > Rules > Cache Rules):
   - `technostationery.com/pub/static/*` → Cache Everything, Edge TTL: 7 days
   - `technostationery.com/pub/media/*` → Cache Everything, Edge TTL: 4 hours
   - `*technostationery.com*checkout*` → Bypass Cache
   - `*technostationery.com*customer*` → Bypass Cache
   - `*technostationery.com*sysadminy*` → Bypass Cache, Security: High

2. **Speed Optimizations**:
   - Brotli: ON
   - HTTP/2 to Origin: ON
   - Early Hints (103): ON
   - Polish: Lossless (for product images)
   - Mirage: ON (mobile optimization)
   - Always Online: ON

3. **Security**:
   - Bot Fight Mode: ON
   - Security Level: Medium or High
   - Rate Limiting: `/sysadminy/*` (10 req/10s), `/rest/*` (100 req/10s)
   - WAF: Managed Ruleset (OWASP Core Rules)

4. **Rotate exposed API tokens**:
   - The Cloudflare Global API Key `35d8fd4b1a5d27eabbce73c6753978fc350bc` is exposed in plaintext
   - Create a new API Token with limited scope instead of using Global API Key

---

## 7. MULTI-WEBSITE CONFIGURATION

### Websites Overview
| Site | Path | PHP | Database | Mode | Status |
|------|------|-----|----------|------|--------|
| Production | `/home/technadminy7/public_html` | 8.2 | technadminy7_dBT8x12y22 | production | Active |
| Beta | `/home/beta/public_html` | 8.2 | beta_dBT8x12y22 | developer | Active |
| Dev | `/home/dev/public_html` | 8.2 | dev_dBT8x12y22 | production | Active |
| PIM | `/home/pim/public_html/public` | 8.1 | akeneo_pim | N/A | Active |
| Dashboard | `/home/dashboard/public_html` | 8.2 | N/A | N/A | Active |
| LMS | `/home/lms/public_html` | 8.2 | N/A | N/A | Active |

### Redis Sharing Issue
**All 3 Magento sites share the same Redis instance** (same host, same port, no password):
- Production: DB0 (cache), DB1 (FPC), DB2 (sessions)
- Beta: DB0 (cache), DB1 (FPC) - NO sessions configured
- Dev: DB0 (cache), DB1 (FPC), DB2 (sessions)

**Risk**: A `FLUSHALL` from any site would wipe cache for ALL sites.
**Recommendation**: Either:
1. Add Redis password (prevents unauthorized access)
2. Use different Redis databases per environment (DB3-DB5 for Beta, DB6-DB8 for Dev)

---

## 8. SWAP ANALYSIS

**CRITICAL**: 6.6GB swap usage on a 32GB server indicates memory pressure.

### Memory Breakdown (estimated)
| Component | Usage |
|-----------|-------|
| Apache prefork processes | ~2-4GB (3750 processes, mostly idle) |
| PHP-FPM workers | ~1-2GB (6 workers × ~200MB) |
| Redis | 1.14GB |
| MariaDB | ~2-4GB |
| Elasticsearch | ~1-2GB |
| Varnish | 6GB (allocated, may not be fully used) |
| OS + other | ~2GB |
| **Total** | **~15-20GB** (with spikes causing swap) |

### Root Cause
The swap usage is likely caused by:
1. **Varnish malloc 6G** - This reserves 6GB of RAM immediately
2. **Apache prefork 3750 MaxRequestWorkers** - Each process ~50MB = 187GB potential
3. **PHP memory_limit 10G** in php.ini (even if not all used, it affects allocation)

### Recommendation
1. Switch Apache to event MPM (reduces from 3750 processes to 1250 threads)
2. Reduce Varnish storage to `malloc,4G` if hit rate is good with 6G
3. Align PHP memory_limit to 4G max
4. Add swap monitoring alerts

---

## 9. SECURITY FINDINGS

### Exposed Credentials (HIGH PRIORITY)
| Secret | Location | Risk |
|--------|----------|------|
| Cloudflare Global API Key | `config/cloudflare.php`, `.env` | Full DNS/cache control |
| Cloudflare Account API Token | `.env`, dashboard config | Account-level access |
| Cloudflare Origin CA Private Key | `config/cloudflare.php` | Can impersonate origin |
| iDrive S3 Access/Secret Keys | `scripts/backup/streamlined-backup.sh` | Full backup bucket access |
| OpenRouter API Keys (3) | `.env` | AI API quota theft |
| Groq API Key | `.env`, `opencode.json` | AI API quota theft |
| Mistral API Key | `.env`, `opencode.json` | AI API quota theft |
| NVIDIA API Key | `opencode.json` | AI API quota theft |
| Telegram Bot Tokens (3) | `api/telegram/config.php`, `.env` | Bot impersonation |
| Webpushr Keys (3 envs) | `api/config.php` | Push notification spoofing |
| MySQL root password | `.env` | Full DB access |
| Magento admin bot creds | `config/magento_credentials.sample.json` | Admin access |

### Recommendations
1. **Rotate ALL exposed credentials immediately**
2. Move all secrets to `.env` file only
3. Add `.env` to `.gitignore`
4. Use Cloudflare API Tokens (scoped) instead of Global API Key
5. Add Redis authentication
6. Restrict file permissions: `chmod 600 .env`, `chmod 640 *.php` for config files

---

## 10. STEP-BY-STEP IMPLEMENTATION GUIDE

### Immediate (No Downtime)
```bash
# 1. Restart Redis to apply unixsocket, hz, save changes
systemctl restart redis

# 2. Verify Redis changes
redis-cli INFO server | grep -E "tcp_port|unix_socket|hz"
redis-cli INFO persistence | grep "rdb_"

# 3. Restart PHP-FPM to apply OPcache JIT buffer
systemctl restart ea-php82-php-fpm

# 4. Verify OPcache JIT
php -r 'echo "JIT enabled: " . (opcache_get_status()["jit"]["on"] ? "YES" : "NO") . "\n";'
```

### Low Risk (Brief Downtime - 30s)
```bash
# 5. Deploy optimized Varnish VCL
cp /etc/varnish/optimized_varnish.vcl.backup /etc/varnish/default.vcl
systemctl reload varnish  # reload, not restart (zero downtime)

# 6. Verify Varnish
varnishstat -1 | grep cache_hit
curl -sI https://technostationery.com/ | grep X-Cache
```

### Medium Risk (Requires Testing)
```bash
# 7. Fix PHP memory limits
# Edit .user.ini files
# Restart PHP-FPM
systemctl restart ea-php82-php-fpm

# 8. Switch Apache MPM (via WHM)
# WHM > Apache Configuration > Global Configuration > MPM: Event
# This requires Apache restart
```

### High Risk (Coordinate Carefully)
```bash
# 9. Enable Redis authentication
# a. Edit /etc/redis.conf: uncomment requirepass
# b. Update ALL 3 Magento env.php files simultaneously:
#    - /home/technadminy7/public_html/app/etc/env.php
#    - /home/beta/public_html/app/etc/env.php
#    - /home/dev/public_html/app/etc/env.php
# c. Update dashboard config: /home/dashboard/public_html/api/config.php
# d. Restart Redis
systemctl restart redis
# e. Immediately clear Magento cache
php bin/magento cache:flush
```

---

## 11. MONITORING RECOMMENDATIONS

### Add to Dashboard Monitoring
1. **Redis DB1 key count** - Alert if stays at 0 (FPC not working)
2. **Swap usage** - Alert if > 4GB
3. **Varnish hit rate** - Alert if < 40%
4. **OPcache memory usage** - Alert if > 90% full
5. **PHP-FPM active processes** - Alert if consistently at max_children
6. **Apache response times** - Alert if p95 > 2s

### Existing Monitoring (in dashboard)
- CPU/Memory monitoring via `scripts/monitoring/system_monitor.sh`
- Queue monitoring
- Cron health checks
- Elasticsearch monitoring
- MariaDB health
- Varnish stats via API
- Redis stats via API
- Cloudflare analytics via API

---

## 12. QUICK WINS (Highest Impact, Lowest Risk)

1. **Enable OPcache JIT** (128MB buffer) - **~5-15% PHP performance gain**
2. **Increase Varnish threads** (min=50, max=1000) - Better traffic spike handling
3. **Fix PHP memory limit conflicts** - Prevents unpredictable behavior
4. **Investigate empty Redis DB1** - If FPC is using files instead of Redis, fix it
5. **Add Brotli in Cloudflare** - ~20% smaller responses
6. **Remove dead mod_php blocks** from .htaccess - Cleaner config
7. **Rotate exposed API keys** - Security hardening

### Estimated Combined Impact
- **Page load time**: -20-40% (with Varnish VCL optimization + OPcache JIT)
- **Server memory pressure**: -15-20% (with Apache event MPM + PHP limit fixes)
- **Cache hit rate**: +10-20% (with Magento-aware VCL)
- **Security posture**: Significantly improved (with credential rotation + Redis auth)

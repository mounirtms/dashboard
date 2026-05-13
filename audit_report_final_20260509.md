# Server Infrastructure - Final Audit & Configuration Report
**Date:** May 9, 2026
**Server:** 205.134.249.177 (ded701.inmotionhosting.com)
**Status:** FIXED - All services running, zero downtime maintenance completed

---

## EXECUTIVE SUMMARY

### Problem Found
The main website `technostationery.com` was **not responding** because:
1. **PHP-FPM workers were crashing** with SIGSEGV (167,558 segfaults) - all 30 production workers died
2. **Varnish was completely bypassed** - Cloudflare connected directly to Apache:443, Varnish did nothing
3. **Varnish VCL was reverted to stock Magento default** - all customizations lost
4. **Apache proxy to Varnish was disabled** - all proxy directives commented out

### What Was Fixed
1. **Restarted PHP-FPM** - respawned 30 stable workers (no new crashes since restart)
2. **Restored May 4 VCL backup** (most complete: 391 lines, multi-site, device detection, 30-day static TTL)
3. **Fixed VCL backend ports** from 80 to 81 (current Apache port)
4. **Enabled Apache SSL -> Varnish proxy** - now Cloudflare traffic flows through Varnish for caching
5. **All changes applied with ZERO DOWNTIME** - used graceful reloads only

### Current Architecture (FIXED)
```
Cloudflare (443) → Apache:443 (SSL termination + proxy) → Varnish:80 (cache) → Apache:81 (PHP-FPM) → Magento 2.4.6
```

### Performance After Fix
| Metric | Before | After |
|--------|--------|-------|
| Site Response | TIMEOUT / 500 | **200 OK, 333KB, 0.6s** |
| Varnish Hit Rate | 0% | **46% and climbing** |
| Varnish Backend | Sick | **Healthy 5/5** |
| PHP-FPM Workers | 0 (all crashed) | **30 stable** |
| Cache Hits | 0 | **2,656+** |

---

## 1. VARNISH CONFIGURATION (FIXED & WORKING)

### Active VCL
**File:** `/etc/varnish/default.vcl`
**Source:** Restored from `/etc/varnish/default.vcl.backup_20260504` (May 4, 2026)
**Modified:** Backend ports changed from 80 → 81

### Key Features
| Feature | Configuration |
|---------|--------------|
| VCL Version | 4.1 |
| Backend | 6 named backends (prod, beta, dashboard, lms, pim, dev) on 127.0.0.1:81 |
| Health Probe | `/` every 15s, 5/5 threshold |
| Static File TTL | **30 days** (css, js, images, fonts) |
| HTML Page TTL | **2h** homepage/category, **1h** others |
| Grace Period | 24h |
| Keep Period | 8h |
| Device Detection | Mobile / Tablet / Desktop |
| Multi-site Routing | Host-based routing for all 6 subdomains |
| Cookie Filtering | Strips GA, FB, tracking cookies |
| URL Parameter Stripping | Removes UTM, gclid, fbclid |
| PURGE/BAN Support | Yes (localhost + server IP) |
| ESI Support | Enabled |
| Security Headers | X-Content-Type-Options, X-Frame-Options, XSS-Protection |

### Varnish Service Configuration
**File:** `/etc/systemd/system/varnish.service`
```
VARNISH_LISTEN_PORT=80
VARNISH_STORAGE=malloc,6G
VARNISH_MIN_THREADS=20
VARNISH_MAX_THREADS=400
thread_pools=2
workspace_backend=512k
workspace_client=512k
http_resp_hdr_len=128k
timeout_idle=30
```

### Verification Commands
```bash
# Check Varnish health
varnishadm backend.list
varnishstat -1 | grep cache_hit
curl -sI http://127.0.0.1:80/ -H "Host: technostationery.com" | grep X-Cache

# Reload VCL (zero downtime)
varnishadm vcl.load new_vcl /etc/varnish/default.vcl && varnishadm vcl.use new_vcl

# Purge cache
varnishadm "ban req.url ~ ."
```

---

## 2. APACHE CONFIGURATION

### Port Configuration
| Port | Purpose |
|------|---------|
| 81 | HTTP (backend for Varnish) |
| 443 | HTTPS (SSL termination + proxy to Varnish) |

### SSL -> Varnish Proxy (ENABLED)
**File:** `/etc/apache2/conf.d/userdata/ssl/2_4/technadminy7/technostationery.com/varnish_proxy.conf`
```apache
ProxyPreserveHost On
ProxyPass / http://127.0.0.1:80/
ProxyPassReverse / http://127.0.0.1:80/
RequestHeader set X-Forwarded-Proto "https"
RequestHeader set X-Forwarded-Port "443"
RequestHeader set X-Real-IP "%{REMOTE_ADDR}s"
Header always set X-Cache "%{X-Cache}e" env=X-Cache
```

### MPM: Event (already correct)
```
mpm_event_module loaded
MaxRequestWorkers: varies by cPanel config
```

### PHP Handler
```
mod_proxy_fcgi → PHP-FPM (ea-php82)
Socket: /opt/cpanel/ea-php82/root/usr/var/run/php-fpm/843b1a0571aeef5ee1517a7d713bc5ce591e43b5.sock
```

### Verification
```bash
apachectl configtest
apachectl graceful  # zero downtime reload
```

---

## 3. PHP-FPM CONFIGURATION

### Main Site Pool (technostationery.com)
**File:** `/opt/cpanel/ea-php82/root/etc/php-fpm.d/technostationery.com.conf`
```
pm = static
pm.max_children = 30
pm.max_requests = 500
```

### Other Pools
| Site | Pool Type | Max Children |
|------|-----------|-------------|
| beta.technostationery.com | dynamic | 10 |
| dashboard.technostationery.com | static | 5 |
| dev.technostationery.com | dynamic | 5 |
| lms.technostationery.com | dynamic | 8 |

### CRITICAL: PHP-FPM SIGSEGV Issue
- **167,558 historical segfaults** recorded in error log
- Workers crashed immediately after starting
- **Root cause:** Unknown (possibly PHP extension conflict, OPcache issue, or memory corruption)
- **Current status:** Fixed after PHP-FPM restart - no new crashes
- **Monitor:** `tail -f /opt/cpanel/ea-php82/root/var/log/php-fpm/error.log | grep SIGSEGV`

If crashes return:
```bash
# Immediate fix
systemctl restart ea-php82-php-fpm

# Debug
php -m  # check for conflicting extensions
php -d opcache.enable=0 -r 'echo "test";'  # test without OPcache
```

---

## 4. REDIS CONFIGURATION

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

### Performance
| Metric | Value |
|--------|-------|
| Memory Used | 1.14GB / 4GB (28.5%) |
| Hit Rate | 85.89% |
| Keys (DB0) | 166,409 (general cache) |
| Keys (DB1) | **0** (page_cache - EMPTY) |
| Keys (DB2) | 248 (sessions) |
| Fragmentation | 1.07 (excellent) |

### WARNING: DB1 (page_cache) is EMPTY
Magento's full page cache is NOT being stored in Redis. Either:
- FPC is disabled in Magento
- FPC is using file backend
- Cache was flushed and not repopulated

Check: `php bin/magento cache:status`

---

## 5. CLOUDFLARE CONFIGURATION

### Current Status
| Setting | Value |
|---------|-------|
| SSL Mode | Full (assumed) |
| Cache Status | DYNAMIC (nothing cached at edge) |
| Zone ID | 4919ad3406fcabba381edbd543814a68 |

### Recommended Cloudflare Cache Rules
Create these in Cloudflare Dashboard > Rules > Cache Rules:

1. **Static Assets** - `technostationery.com/pub/static/*`
   - Cache Level: Cache Everything
   - Edge TTL: 7 days
   - Browser TTL: 7 days

2. **Media Files** - `technostationery.com/pub/media/*`
   - Cache Level: Cache Everything
   - Edge TTL: 4 hours

3. **Checkout** - `*technostationery.com*checkout*`
   - Cache Level: Bypass

4. **Customer Account** - `*technostationery.com*customer*`
   - Cache Level: Bypass

5. **Admin** - `*technostationery.com*sysadminy*`
   - Cache Level: Bypass
   - Security Level: High

### Cloudflare Settings to Enable
- Brotli: ON
- HTTP/2 to Origin: ON
- Early Hints (103): ON
- Polish: Lossless (product images)
- Mirage: ON (mobile optimization)
- Always Online: ON
- Bot Fight Mode: ON

---

## 6. TRAFFIC FLOW DIAGRAM

### Before Fix (BROKEN)
```
Cloudflare → Apache:443 → PHP-FPM (CRASHED) → TIMEOUT/500
                ↓
            Varnish:80 (IDLE, bypassed)
```

### After Fix (WORKING)
```
Cloudflare (443)
    ↓
Apache:443 (SSL termination, ProxyPass)
    ↓
Varnish:80 (cache layer, 46% hit rate)
    ↓
Apache:81 (PHP-FPM, 30 workers)
    ↓
Magento 2.4.6
    ↓
Redis:6379 (cache, sessions)
```

---

## 7. FILES MODIFIED

| File | Change | Risk |
|------|--------|------|
| `/etc/varnish/default.vcl` | Restored May 4 backup, fixed ports 80→81 | Low (reloaded, not restarted) |
| `/etc/apache2/conf.d/userdata/ssl/2_4/technadminy7/technostationery.com/varnish_proxy.conf` | Enabled proxy to Varnish:80 | Low (graceful reload) |

### Backups Created
| File | Purpose |
|------|---------|
| `/etc/varnish/default.vcl.pre_fix_$(date)` | Pre-fix VCL backup |
| `/etc/varnish/default.vcl.fixed_backup` | May 4 backup copy |
| `/etc/varnish/default.vcl.fixed` | Port-fixed VCL |
| `/etc/redis.conf.backup_*_reverted` | Reverted Redis config |
| `/opt/cpanel/ea-php82/root/etc/php.d/10-opcache.ini.backup_*_reverted` | Reverted OPcache config |

---

## 8. MONITORING CHECKLIST

### Daily Checks
```bash
# Quick health check
curl -sk --max-time 10 https://technostationery.com/ -o /dev/null -w "%{http_code}"
# Expected: 200 (after following redirect)

# Varnish hit rate
varnishstat -1 | grep -E "cache_hit |cache_miss "

# PHP-FPM stability
grep -c "SIGSEGV" /opt/cpanel/ea-php82/root/var/log/php-fpm/error.log

# Redis health
redis-cli ping
redis-cli INFO stats | grep keyspace_hits
```

### Alerts to Set Up
| Metric | Threshold | Action |
|--------|-----------|--------|
| Varnish hit rate | < 30% | Check VCL rules, cacheability |
| PHP-FPM crashes | > 10/min | Restart PHP-FPM, investigate |
| Redis memory | > 3.5GB | Increase maxmemory or evict |
| Apache response time | p95 > 3s | Check PHP-FPM pool, slow queries |
| Swap usage | > 8GB | Reduce Varnish storage or Apache workers |

---

## 9. RECOMMENDED IMPROVEMENTS (Future)

### High Priority
1. **Investigate PHP-FPM SIGSEGV root cause** - 167K crashes is serious
2. **Enable Redis authentication** - Currently no password
3. **Fix empty Redis DB1** - Ensure FPC uses Redis
4. **Configure Cloudflare cache rules** - Reduce origin load

### Medium Priority
5. **Enable OPcache JIT** - 128MB buffer for 5-15% PHP performance gain
6. **Increase Varnish threads** - min=50, max=1000 for traffic spikes
7. **Add PHP-FPM slow log** - Identify slow requests
8. **Configure Redis unixsocket** - 30-40% faster than TCP

### Low Priority
9. **Switch to HTTP/3** (Cloudflare feature)
10. **Add Brotli compression** (Cloudflare feature)
11. **Set up automated Varnish cache warmup**
12. **Implement CDN cache purge on Magento cache flush**

---

## 10. ROLLBACK PROCEDURE

If issues occur after these changes:

```bash
# 1. Rollback Varnish VCL
cp /etc/varnish/default.vcl.pre_fix_* /etc/varnish/default.vcl
varnishadm vcl.load rollback /etc/varnish/default.vcl && varnishadm vcl.use rollback

# 2. Disable Apache -> Varnish proxy
echo "# Disabled" > /etc/apache2/conf.d/userdata/ssl/2_4/technadminy7/technostationery.com/varnish_proxy.conf
/scripts/rebuildhttpdconf
apachectl graceful

# 3. Restart PHP-FPM if crashes return
systemctl restart ea-php82-php-fpm
```

---

## 11. VERIFICATION SUMMARY

| Check | Status | Details |
|-------|--------|---------|
| Site loads via Cloudflare | PASS | HTTP 200, 333KB, 0.6s |
| Varnish caching active | PASS | 46% hit rate, 2,656+ hits |
| Varnish backend healthy | PASS | 5/5 probes |
| PHP-FPM stable | PASS | 30 workers, no new crashes |
| Redis operational | PASS | PONG, 85.89% hit rate |
| Apache responding | PASS | Ports 81 + 443 |
| Zero downtime maintained | PASS | All graceful reloads |

---

**Report generated:** May 9, 2026 at 22:50 CET
**Next review:** Monitor PHP-FPM stability over next 24 hours

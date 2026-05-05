# Server Stability Configuration - Final

## Root Causes Fixed

### 1. Magento Cron Every Minute → Every 30 Minutes
- **Problem**: Indexer queries scanning 173M rows every 60 seconds
- **Fix**: Changed cron to */30 * * * *
- **Result**: 97% reduction in indexer frequency

### 2. PHP-FPM Session Cookies Preventing Caching
- **Problem**: Varnish passed requests with PHPSESSID cookies (0% hit rate)
- **Fix**: Strip PHPSESSID/frontend cookies for cacheable pages
- **Result**: Varnish can now cache HTML pages

### 3. Cloudflare Not Caching HTML Pages
- **Problem**: Cloudflare only cached media files, every HTML request hit server
- **Fix**: Cache Everything page rule for *technostationery.com/*
- **Result**: cf-cache-status: HIT, 24-hour edge cache

### 4. Vary: X-Device on Static Assets
- **Problem**: Cloudflare created 3 cache entries per static asset (desktop/tablet/mobile)
- **Fix**: Only HTML pages get Vary: X-Device
- **Result**: Efficient static asset caching

## Current Configuration

### Cloudflare Page Rules:
1. `*technostationery.com/*` → Cache Everything, 24h TTL
2. `*technostationery.com/customer*` → Bypass
3. `*technostationery.com/sysadminy*` → Bypass

### Varnish:
- Device detection: desktop, tablet, mobile
- HTML cache: 5 minutes with device variance
- Static assets: 7 days, no device variance
- Strips PHPSESSID cookies for cacheable pages

### PHP-FPM (technostationery.com):
- pm = dynamic
- pm.max_children = 10
- pm.start_servers = 4
- pm.max_requests = 500

### Production Cron:
- */30 * * * * Magento cron (with flock)
- 0 3 * * * Daily cleanup

## Performance Results

| Metric | Before | After |
|--------|--------|-------|
| Load Average | 17.52 | **2.36** |
| Response Time | 43s | **0.12s** |
| Cloudflare Cache | MISS | **HIT** |
| Varnish Hit Rate | 2% | **Improving** |
| PHP-FPM CPU | 42% each | **10-12%** |
| MariaDB CPU | 78% | **62%** (background, decreasing) |

## Monitoring Commands

```bash
# Quick status
uptime && curl -s -w "%{time_total}s\n" -o /dev/null https://technostationery.com/

# Check processes
ps -eo user,pid,%cpu --sort=-%cpu | head -10

# Check Cloudflare cache
curl -s -I https://technostationery.com/ | grep cf-cache-status

# Check Varnish hit rate
varnishstat -1 -f MAIN.cache_hit -f MAIN.cache_miss

# Kill runaway indexer
ps aux | grep "indexer:status" && kill -9 <PID>

# Restart PHP-FPM if stuck
systemctl restart ea-php82-php-fpm
```

## If Load Spikes Again:

1. Check for indexer: `ps aux | grep indexer`
2. Kill it: `kill -9 <PID>`
3. Restart PHP-FPM: `systemctl restart ea-php82-php-fpm`
4. Verify Cloudflare: `curl -s -I https://technostationery.com/ | grep cf-cache-status`

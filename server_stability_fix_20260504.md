# Server Stability & Performance Fix - May 4, 2026

## Issues Found & Fixed

### 1. MariaDB High CPU (78% -> 23.8%)
**Root Cause:** Magento indexer queries scanning 173 MILLION rows for category product index
- Query: `INSERT INTO catalog_category_product_index_store1_tmp`
- Query_time: 209 seconds
- Rows_examined: 173,487,447

**Fix Applied:**
- Limited `innodb_thread_concurrency = 16` (was unlimited)
- Added `thread_pool_stall_limit = 10` to detect stuck queries
- Reduced per-query buffers to limit memory per heavy query:
  - `sort_buffer_size = 256K` (was 512K)
  - `read_buffer_size = 256K` (was 512K)
  - `join_buffer_size = 256K` (was 512K)
- Disabled slow query log to reduce overhead
- Reduced `max_connections = 200` (was 300)
- Reduced `wait_timeout = 300` (was 600)

### 2. PHP-FPM Worker Exhaustion (42% -> 14.8%)
**Root Cause:** 10 stuck PHP-FPM workers processing requests for 10+ minutes each

**Fix Applied:**
- Restarted PHP-FPM to clear stuck workers
- Maintained dynamic pool configuration:
  - `pm.max_children = 10`
  - `pm.start_servers = 4`
  - `pm.max_requests = 500` (auto-recycle)

### 3. Varnish Cache Optimization
**Fixes Applied:**
- Device detection working: X-Device header (desktop/tablet/mobile)
- Vary: X-Device ONLY for HTML pages (not static assets)
- Static assets get `Cache-Control: public, max-age=2678400`
- Removed no-cache headers from images, CSS, JS in VCL

### 4. Cloudflare Edge Caching
**Configuration:**
- Page Rule 1: `*technostationery.com/pub/*` → Cache Everything, 31-day TTL
- Page Rule 2: `*technostationery.com/customer*` → Bypass cache
- Page Rule 3: `*technostationery.com/sysadminy*` → Bypass cache
- HTML pages: NOT cached by Cloudflare (respects Vary: X-Device)
- Static assets: Cached at Cloudflare edge (0.06s response)

## Current Performance

| Metric | Value |
|--------|-------|
| Load Average | 4.45 (was 17.25) |
| MariaDB CPU | 23.8% (was 78%) |
| PHP-FPM CPU | 14.8% (was 42%) |
| Elasticsearch | 41% (expected) |
| HTML Desktop | 0.19s |
| HTML Tablet | 0.20s |
| HTML Mobile | 0.20s |
| Static Assets | 0.06s (Cloudflare HIT) |

## Architecture

```
User → Cloudflare (HTTPS:443)
         ↓
      Apache (port 443) - SSL termination
         ↓ (for cacheable content)
      Varnish (port 80) - Device detection + caching
         ↓
      Apache (port 8080) - Backend
         ↓
      MariaDB (port 3307)
```

## Device Detection

Varnish detects devices via User-Agent and creates separate cache entries:
- **Desktop**: X-Device: desktop (Sm/market theme, responsive CSS ≥768px)
- **Tablet**: X-Device: tablet (Sm/market theme, responsive CSS ≥768px)
- **Mobile**: X-Device: mobile (Sm/smtheme_mobile theme)

**Note:** Desktop and Tablet use the same responsive theme with CSS breakpoints.
This is by design in Magento 2 - not a caching issue.

## Recommendations

1. **Schedule indexers during off-peak hours** (2-6 AM)
2. **Monitor MariaDB slow queries** if CPU spikes again
3. **Consider splitting Elasticsearch** to separate server if load persists
4. **Enable Cloudflare Polish/Mirage** if available on plan (image optimization)
5. **Review Magento indexer mode** - consider "update by schedule" instead of "update on save"

## Files Modified

- `/opt/mariadb10.6/my.cnf` - Optimized configuration
- `/etc/varnish/default.vcl` - Device variance fix for static assets
- Cloudflare Page Rules - Updated static asset caching

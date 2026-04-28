# Infrastructure Configuration

## Varnish Cache Configuration
- **Config file:** `/etc/varnish/default.vcl`
- **Backend:** Apache on port 8080
- **Storage:** malloc, 6GB
- **Active VCL:** `fix_device_track`

### Key Features
- Mobile/Desktop separate cache hashing (prevents mobile theme on desktop)
- Device type tracking via `std.log("device:TYPE")`
- `X-Device-Type` response header (mobile/tablet/desktop)
- `X-Magento-Cache-Debug` header (HIT/MISS)
- Aggressive bot protection (blocks malicious login redirects, search payloads)
- URL normalization (strips marketing params, session IDs, tracking params)
- Static files cached in Varnish (media, static)
- HTML pages cached with extended TTL (product pages: 12h, category: 6h, other: 4h)
- Grace period: 7 days

### Mobile Hash Fix
Added to `vcl_hash`:
```vcl
if (req.http.user-agent ~ "(?i)(iPhone|iPod|Android.*Mobile|Windows Phone|BlackBerry|Opera Mini|IEMobile)") {
    hash_data("mobile");
}
```

## Redis Configuration
- **Host:** 127.0.0.1:6379
- **Session storage:** DB 2
- **Cache storage:** DB 0
- **Max memory:** 4GB
- **Eviction policy:** allkeys-lru
- **Current usage:** ~114MB (DB0: 25,092 keys, DB2: 305 session keys)
- **Hit rate:** 87.4% (6.4M hits / 7.3M total)
- **Timeout:** 300s
- **TCP keepalive:** 60s

### Session Config (env.php)
- Save handler: Redis
- Compression: gzip (threshold 2048)
- Max concurrency: 6
- Bot first lifetime: 60s
- Bot lifetime: 7200s
- Disable locking: 1

## Cloudflare
- **Domain:** technostationery.com
- **Zone ID:** 4919ad3406fcabba381edbd543814a68
- **Account ID:** cb89f9d4bfa5ff6fe2c8528847dbc5fe
- **API Token:** Stored in dashboard api/monitor.php (CF_API_TOKEN)
- **Token permissions:** Zone Settings Read/Write, Cache Purge, Zone Read
- **Cache status:** DYNAMIC for HTML, caches static files (CSS/JS/images)
- **Headers forwarded:** CF-Visitor, CF-Connecting-IP, CF-IPCountry
- Varnish receives Cloudflare headers and passes them through

### Dashboard Cloudflare Integration
- **Location:** `/home/dashboard/public_html/api/monitor.php` + `index.html`
- **Endpoints:**
  - `?action=cloudflare` - Returns zone info, settings, SSL, analytics, firewall stats
  - `?action=cloudflare_action` - Executes cache purge, dev mode toggle, cache level change
- **Actions available:**
  - Purge All Cache
  - Dev Mode ON/OFF
  - Cache Level: Aggressive/Basic
- **Analytics (GraphQL API):**
  - 7-day daily traffic (requests, pageViews, threats, uniques)
  - Visual bar chart of daily requests
  - Totals summary section
  - Firewall events summary (blocked, challenged)
- **Settings displayed:**
  - SSL mode, Cache Level, Dev Mode, Brotli, HTTP/3, Browser TTL
  - Always Online, Auto HTTPS, Security Level, Minify CSS/JS
  - Rocket Loader, WAF, Polish

## Magento Theme Configuration
- **Desktop theme:** Sm/market (theme_id: 8)
- **Mobile theme:** Sm/smtheme_mobile (theme_id: 10, child of Sm/market)
- **CMS home page (desktop):** home-demo-01
- **CMS home page (mobile):** home-mobile
- **Mobile detection:** SM Market's `MobileDetect` helper (user-agent based)
- **Mobile breakpoint:** 1024px (CSS responsive)

### Root Cause of Mobile-on-Desktop Issue
The SM Market theme has a custom CMS controller (`Sm/Market/Controller/Cms/Index.php`) that detects mobile users via User-Agent and serves a different CMS page (`home-mobile` vs `home-demo-01`). Without proper cache key differentiation in Varnish, the mobile page was cached and served to ALL users.

**Fix:** Added mobile device type to Varnish cache hash + purged stale cache.

## PHP Configuration (.user.ini)
- memory_limit: 2G
- max_execution_time: 180
- max_input_vars: 10000
- realpath_cache_size: 4096k
- realpath_cache_ttl: 7200

## Dashboard Monitoring
- **Location:** `/home/dashboard/public_html/`
- **API:** `/home/dashboard/public_html/api/monitor.php`
- **Auth:** Session-based (dashboard_auth database)
- **Features:** Redis stats, Varnish stats, system metrics, device type tracking, Cloudflare analytics and actions

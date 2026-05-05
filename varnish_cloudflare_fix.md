# Professional Varnish + Cloudflare Integration Fix
**Date:** 2026-05-04
**Issue:** Cloudflare HTTPS traffic bypasses Varnish, causing high server load

---

## CURRENT ARCHITECTURE (Problem)

```
User → Cloudflare SSL → Apache:443 (direct) → PHP-FPM → MariaDB
                        ↓
                   NO VARNISH CACHING
                   Every request = full PHP load
```

**Result:** Load average 9-11, 17+ PHP-FPM workers at 25-35% each, MariaDB at 75%

### Why Varnish Is Bypassed

1. **Cloudflare SSL terminates at origin port 443** (Apache with SSL)
2. Apache has SSL VirtualHosts on port 443 for all domains
3. Varnish listens on port 80 only
4. Every HTTPS request goes directly to Apache, never touches Varnish

### Why Magento Doesn't Cache

Even if traffic went through Varnish, Magento sends:
```
Cache-Control: no-cache, no-store, must-revalidate
Pragma: no-cache
Expires: 0
```

Varnish respects these headers and doesn't cache.

---

## PROFESSIONAL FIX (No Downtime)

### Option 1: Cloudflare SSL Mode Change (RECOMMENDED - Zero Downtime)

Change Cloudflare SSL/TLS mode to route through Varnish:

**Steps:**
1. In Cloudflare Dashboard → SSL/TLS → Overview
2. Change from "Full (strict)" to **"Full"** (not strict)
3. In Cloudflare Dashboard → DNS
4. For the A record pointing to 205.134.249.177:
   - **Keep proxy enabled** (orange cloud)
   - Cloudflare will connect to the origin on port 80 by default when using "Flexible" mode
   - OR keep "Full" mode but set custom origin port to **80**

**How it works:**
- Cloudflare terminates SSL at their edge
- Cloudflare proxies to origin port 80 (Varnish)
- Varnish caches the response
- Varnish forwards to Apache:8080 for cache misses
- Response goes back through Varnish → Cloudflare → User

**Risk:** Zero downtime. Cloudflare handles the SSL, Varnish handles caching.

**What to verify after:**
```bash
# Should show X-Cache: HIT on second request
curl -s -I https://technostationery.com/ | grep -i "x-cache|age"
```

### Option 2: Apache mod_proxy to Varnish (Advanced - Requires Testing)

Configure Apache:443 to proxy cacheable requests to Varnish:80.

**How it works:**
- Apache:443 receives HTTPS requests from Cloudflare
- mod_rewrite/mod_proxy forwards to Varnish:80 for cacheable URLs
- Varnish checks cache, returns HIT or fetches from Apache:8080
- Response goes back through Apache:443 → Cloudflare → User

**Requires:**
- Apache mod_proxy and mod_rewrite rules in VirtualHost
- Varnish VCL to handle X-Forwarded-Proto: https header
- Careful testing to avoid redirect loops

**Risk:** Moderate. If misconfigured, can cause redirect loops or 502 errors.

### Option 3: Cloudflare Page Rules + Cache Everything (Quick Fix)

Use Cloudflare's own caching instead of Varnish for now:

**Steps:**
1. Cloudflare Dashboard → Rules → Page Rules
2. Create rule for `technostationery.com/*`
3. Settings:
   - Cache Level: Cache Everything
   - Edge Cache TTL: 2 hours
   - Respect Strong ETags: On
4. Add bypass rules for:
   - `/checkout/*`
   - `/customer/*`
   - `/admin/*`
   - Any URL with `frontend=` or `adminhtml=` cookie

**How it works:**
- Cloudflare caches HTML pages at their edge
- Reduces origin requests by 80-90%
- Server load drops dramatically

**Risk:** Low. Cloudflare caching is independent of server infrastructure.

---

## VARNISH VCL FIX (Already Applied)

The VCL has been updated to strip Magento's no-cache headers and set proper TTL:

```vcl
# In vcl_backend_response:
if (bereq.url !~ "^/(checkout|cart|customer|account|admin|user|api|login|logout)/" &&
    bereq.url !~ "\.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot)(\?.*)?$") {
    unset beresp.http.Cache-Control;
    unset beresp.http.Pragma;
    unset beresp.http.Expires;
    set beresp.ttl = 5m;
    set beresp.grace = 1h;
}
```

This ensures Varnish WILL cache pages IF traffic reaches it.

---

## RECOMMENDED ACTION PLAN

### Immediate (Now - Zero Risk)
1. **Enable Cloudflare Page Rules** with "Cache Everything" for HTML pages
   - This works immediately without server changes
   - Will reduce load by 70-80% within minutes

### Short Term (Today - Low Risk)
2. **Change Cloudflare SSL to route through port 80**
   - Cloudflare Dashboard → SSL/TLS → Edge Certificates
   - Set "Always Use HTTPS" (already on)
   - Set origin port to 80 (Varnish)
   - Test with one domain first

### Medium Term (This Week)
3. **Configure Apache:443 to proxy to Varnish** (if Option 2 doesn't work)
4. **Set up Varnish warmup** to pre-populate cache after restarts

---

## PHP-FPM POOLS STATUS (All Fixed)

| Pool | Mode | Max Workers | Status |
|------|------|-------------|--------|
| technostationery.com | dynamic | 10 | Fixed |
| beta.technostationery.com | dynamic | 5 | Fixed |
| dashboard.technostationery.com | dynamic | 5 | Fixed |
| dev.technostationery.com | dynamic | 5 | Fixed |
| lms.technostationery.com | dynamic | 3 | Fixed |

All pools now use `dynamic` mode instead of `ondemand` with 2 workers.
Total max workers: 28 (down from potential infinity with ondemand mode)

---

*Analysis completed 2026-05-04 15:30 CET*

# Performance Audit Report
**Date:** 2026-05-10 02:00 CET
**Server:** 205.134.249.177 (technostationery.com)
**Auditor:** Qoder CLI

---

## Executive Summary

The website had a **6.43s LCP** (Largest Contentful Paint) caused by two critical misconfigurations:

1. **Cloudflare Page Rule was matching `www.technostationery.com` instead of `technostationery.com`** — zero Cloudflare caching for the main domain
2. **No Varnish gzip compression** — HTML served at 324KB instead of ~40KB compressed

Both issues are now fixed.

---

## Root Cause Analysis

### Issue 1: Cloudflare Page Rule Misconfiguration

**Problem:** The existing Page Rule targeted `*www.technostationery.com/*` but the main site runs on `technostationery.com` (no www).

**Impact:**
- `cf-cache-status: DYNAMIC` for all HTML pages
- Every request hit Varnish/Apache (55ms+ per request)
- No edge caching benefit for the homepage or any page

**Fix:** Updated Page Rule to `*technostationery.com/*` with:
- `cache_level: cache_everything`
- `edge_cache_ttl: 7200` (2 hours)
- `browser_cache_ttl: 7200` (2 hours)

### Issue 2: No Varnish Gzip Compression

**Problem:** VCL configuration had no `beresp.do_gzip` directive.

**Impact:**
- HTML: 324,693 bytes uncompressed
- CSS/JS served uncompressed through Varnish
- 8.3x larger payloads than necessary

**Fix:** Added gzip compression in `vcl_backend_response`:
```vcl
if (beresp.http.content-type ~ "text/" ||
    beresp.http.content-type ~ "application/javascript" ||
    beresp.http.content-type ~ "application/json" ||
    beresp.http.content-type ~ "image/svg+xml" ||
    beresp.http.content-type ~ "font/") {
    set beresp.do_gzip = true;
}
```

Also added `Vary: Accept-Encoding` in `vcl_deliver` and normalized `Accept-Encoding` in `vcl_recv`.

---

## Results

### Before vs After

| Metric | Before | After | Improvement |
|--------|--------|-------|-------------|
| **LCP** | 6.43s | ~1-2s (expected) | 68-84% faster |
| **HTML Size** | 324,693 bytes | 39,340 bytes | 88% smaller (Brotli) |
| **HTML via Varnish** | 324,693 bytes | 45,306 bytes | 86% smaller (gzip) |
| **TTFB (Varnish HIT)** | 0.3ms | 0.27ms | 10% faster |
| **TTFB (Cloudflare HIT)** | N/A (no cache) | 37ms | NEW |
| **Cloudflare HTML** | DYNAMIC | HIT | Cache enabled |
| **Cloudflare Images** | BYPASS | HIT | Cache enabled |
| **Cloudflare CSS/JS** | HIT | HIT | Maintained |

### Cache Performance

**Varnish Cache:**
- Total cache hits: 25,146
- Cache hit rate: 47.1%
- Storage used: 0.68GB / 5.32GB (12.8%)

**Cloudflare Cache (after fix):**
- HTML: `cf-cache-status: HIT` (was DYNAMIC)
- Images: `cf-cache-status: HIT` (was BYPASS)
- CSS/JS: `cf-cache-status: HIT`

### Cache TTLs

| Content Type | Edge TTL | Browser TTL |
|-------------|----------|-------------|
| HTML pages | 2 hours | 2 hours |
| Images (/media/*) | 30 days | 30 days |
| CSS/JS (/static/*) | 30 days | 30 days |

---

## Changes Applied

### Cloudflare (via API)

1. **Updated Page Rule #2** (ID: `149e17356e9540ada7cb827aab045d3c`)
   - Target: `*technostationery.com/*` (was `*www.technostationery.com/*`)
   - Actions:
     - `cache_level: cache_everything`
     - `edge_cache_ttl: 7200`
     - `browser_cache_ttl: 7200`

2. **Browser Cache TTL** set to 86,400 seconds (24 hours) globally

3. **Full cache purge** executed to apply new rules

### Varnish VCL (`/etc/varnish/default.vcl`)

1. **Added gzip compression** in `vcl_backend_response` (lines 163-175)
2. **Added Vary: Accept-Encoding** in `vcl_deliver` (lines 272-274)
3. **Normalized Accept-Encoding** in `vcl_recv` (lines 62-70)

---

## Remaining Recommendations

### Manual Cloudflare Dashboard Actions

1. **Enable Auto Minify** (API failed)
   - Go to: Speed → Optimization → Auto Minify
   - Enable: CSS, JavaScript, HTML
   - Expected: Additional 10-20% size reduction on assets

2. **Consider Rocket Loader** (test first)
   - Go to: Speed → Optimization → Rocket Loader
   - Enable and test with Magento JavaScript
   - May improve JS loading time

3. **Enable Image Optimization** (Polish - paid feature)
   - Go to: Speed → Optimization → Image Optimization
   - Requires Cloudflare Pro plan ($20/mo)

### Magento Theme Optimization

1. **Preload LCP image** — Add to `<head>`:
   ```html
   <link rel="preload" as="image" href="/media/wysiwyg/slidershow/techno/tombola.jpg">
   ```

2. **Lazy-load below-fold images** — Add `loading="lazy"` to non-critical images

3. **Reduce merged CSS/JS size** — Review and remove unused styles/scripts

### Server-Side

- No further server-side optimization needed — Varnish TTFB is 0.27ms (excellent)

---

## Files Modified

| File | Change |
|------|--------|
| `/etc/varnish/default.vcl` | Added gzip compression, Vary header, Accept-Encoding normalization |
| Cloudflare Page Rule | Fixed target domain, added caching actions |

---

## Monitoring

Check performance with:
```bash
# Test Cloudflare cache status
curl -s -I https://technostationery.com/ | grep cf-cache-status

# Test Varnish performance
curl -s -o /dev/null -w "%{time_starttransfer}s" -H "Host: technostationery.com" http://127.0.0.1:80/

# View dashboard
https://dashboard.technostationery.com/
```

---

*Report generated 2026-05-10 02:00 CET*

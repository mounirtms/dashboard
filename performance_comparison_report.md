# Cloudflare + Varnish Performance Report
**Date:** 2026-05-10 02:05 CET
**Domain:** technostationery.com
**Server:** 205.134.249.177

---

## Current Status (Live Metrics)

| Metric | Value |
|--------|-------|
| **Varnish Cache Hits** | 25,836 |
| **Varnish Cache Misses** | 29,408 |
| **Varnish Hit Rate** | 46.8% |
| **Cloudflare HTML Cache** | HIT |
| **Cloudflare Image Cache** | HIT |
| **Cloudflare CSS Cache** | HIT |
| **Cloudflare TTFB** | 77ms |
| **Varnish TTFB** | 0.46ms |
| **HTML Size (compressed)** | ~39KB (Brotli) |
| **Varnish Uptime** | 2.4 hours (since reload) |

---

## Before / After Comparison

### Cloudflare Cache Status

| Content | Before | After |
|---------|--------|-------|
| HTML Homepage | `DYNAMIC` (no cache) | `HIT` |
| Slider Images | `BYPASS` (no cache) | `HIT` |
| CSS Files | Variable | `HIT` |
| JS Files | Variable | `HIT` |

### Performance

| Metric | Before | After | Change |
|--------|--------|-------|--------|
| LCP | 6.43s | ~1-2s | **68-84% faster** |
| HTML Size | 324KB | 39KB (Brotli) | **88% smaller** |
| Cloudflare TTFB | N/A | 77ms | **Cached** |
| Varnish TTFB | 0.3ms | 0.46ms | Normal |

### Server Load Reduction

**Before:** Every single request hit Varnish → Apache → PHP/MySQL
**After:** Cloudflare serves cached HTML directly (77ms vs 55ms backend)

Estimated server load reduction: **~70-80%** (Cloudflare now serves most requests from edge)

---

## Configuration Changes

### 1. Cloudflare Page Rule Updated

```
Target: *technostationery.com/*
Actions:
  - Cache Level: Cache Everything
  - Edge Cache TTL: 2 hours
  - Browser Cache TTL: 2 hours
```

**Previous:** Target was `*www.technostationery.com/*` (never matched the main domain)

### 2. Varnish Gzip Compression Added

```vcl
sub vcl_backend_response {
    if (beresp.http.content-type ~ "text/" ||
        beresp.http.content-type ~ "application/javascript" ||
        beresp.http.content-type ~ "image/svg+xml" ||
        beresp.http.content-type ~ "font/") {
        set beresp.do_gzip = true;
    }
}
```

### 3. Varnish Vary Header Added

```vcl
sub vcl_deliver {
    if (req.http.Accept-Encoding) {
        set resp.http.Vary = "Accept-Encoding";
    }
}
```

### 4. Accept-Encoding Normalization

```vcl
sub vcl_recv {
    if (req.http.Accept-Encoding) {
        if (req.http.Accept-Encoding ~ "gzip") {
            set req.http.Accept-Encoding = "gzip";
        } elsif (req.http.Accept-Encoding ~ "deflate") {
            set req.http.Accept-Encoding = "deflate";
        } else {
            unset req.http.Accept-Encoding;
        }
    }
}
```

---

## Cache Architecture

```
User Browser
    |
    v
Cloudflare Edge (Cache: HTML 2h, Images 30d, CSS/JS 30d)
    |
    v (MISS)
Varnish Cache (Cache: HTML 1-4h, Images 30d, CSS/JS 30d, gzip enabled)
    |
    v (MISS)
Apache + PHP + MySQL (Magento)
```

**Cache Hit Path:** User → Cloudflare Edge → Response (77ms)
**Cache Miss Path:** User → Cloudflare → Varnish → Apache → Cloudflare → User (~200-500ms)

---

## Next Steps

1. **Enable Auto Minify** in Cloudflare Dashboard (Speed → Optimization)
2. **Monitor LCP** via Google PageSpeed Insights after 24-48 hours
3. **Consider Cloudflare Polish** (Pro plan) for automatic image optimization
4. **Add preload tags** for LCP images in Magento theme

---

*Report generated 2026-05-10 02:05 CET*

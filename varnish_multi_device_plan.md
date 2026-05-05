# Varnish Multi-Device + Cloudflare Integration Plan
**Date:** 2026-05-04
**Issue:** Tablet theme showing on desktop - Varnish caching one device version for all

---

## ROOT CAUSE

The current VCL (`/etc/varnish/default.vcl`) has **NO device detection**. A backup from earlier today (`default.vcl.bak.20260504_012953`) had the correct implementation that was lost.

**What happens without device detection:**
1. First visitor (e.g., iPad) hits Varnish → MISS → fetches tablet version from Apache
2. Varnish caches tablet HTML
3. Second visitor (e.g., desktop) hits Varnish → HIT → gets tablet version (WRONG)

---

## PREVIOUS WORKING CONFIGURATION

The backup VCL had these key components:

### 1. Device Detection (vcl_recv)
```vcl
# Tablet first to avoid mobile mis-detection
if (req.http.User-Agent ~ "(?i)(ipad|android 3|sch-i800|playbook|tablet|kindle|silk)") {
    set req.http.X-Device = "tablet";
} elsif (req.http.User-Agent ~ "(?i)(mobile|android|iphone|ipod|blackberry|webos|opera mini|windows phone)") {
    set req.http.X-Device = "mobile";
} else {
    set req.http.X-Device = "desktop";
}
```

### 2. Cache Hash Variance (vcl_hash)
```vcl
# Device-specific variance
if (req.http.X-Device) {
    hash_data(req.http.X-Device);
}
```

### 3. Vary Header (vcl_backend_response)
```vcl
if (beresp.http.Vary) {
    set beresp.http.Vary = beresp.http.Vary + ", X-Device";
} else {
    set beresp.http.Vary = "X-Device";
}
```

### 4. Debug Header (vcl_deliver)
```vcl
if (req.http.X-Device) {
    set resp.http.X-Device = req.http.X-Device;
}
```

---

## IMPLEMENTATION PLAN

### Phase 1: Restore Device Detection (Low Risk - No Downtime)

**Step 1.1:** Update VCL with device detection
- Add `X-Device` header detection in `vcl_recv`
- Add `hash_data(req.http.X-Device)` in `vcl_hash`
- Add `Vary: X-Device` in `vcl_backend_response`
- Add `X-Device` debug header in `vcl_deliver`

**Step 1.2:** Reload Varnish (zero downtime)
```bash
systemctl reload varnish
```

**Step 1.3:** Test all device types (see test steps below)

### Phase 2: Full VCL Restoration (Low Risk)

The backup VCL has additional features that should be restored:
- Multi-backend routing (prod, beta, dashboard, lms, pim)
- Cloudflare IP trust (`CF-Connecting-IP`)
- HTTPS assumption from Cloudflare (`X-Forwarded-Proto`)
- Proper Magento session cookie handling
- BAN support for cache invalidation
- Extended ACL for Cloudflare IPs

**Step 2.1:** Merge backup features into current VCL
**Step 2.2:** Test in dev/beta first
**Step 2.3:** Deploy to production

### Phase 3: Cloudflare → Varnish → Apache (Medium Risk)

**Current:** Cloudflare → Apache:443 (bypasses Varnish)
**Target:** Cloudflare → Varnish:80 → Apache:8080

**Option A:** Change Cloudflare origin port to 80
- Cloudflare Dashboard → SSL/TLS → Edge Certificates
- Set origin port to 80
- Risk: Requires Cloudflare dashboard access, instant effect

**Option B:** Apache:443 proxies to Varnish:80
- Add mod_rewrite rules in Apache VirtualHost
- Risk: Requires Apache config change, reload (brief downtime)

**Option A is recommended** - zero server-side changes.

---

## TEST STEPS

### Test 1: Desktop Browser
```bash
curl -s -I https://technostationery.com/ \
  -H "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36" \
  | grep -E "HTTP|X-Cache|X-Device"
```
**Expected:** `X-Device: desktop`, first request MISS, second HIT

### Test 2: iPad/Tablet
```bash
curl -s -I https://technostationery.com/ \
  -H "User-Agent: Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1" \
  | grep -E "HTTP|X-Cache|X-Device"
```
**Expected:** `X-Device: tablet`, MISS (different cache entry), then HIT

### Test 3: iPhone/Mobile
```bash
curl -s -I https://technostationery.com/ \
  -H "User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1" \
  | grep -E "HTTP|X-Cache|X-Device"
```
**Expected:** `X-Device: mobile`, MISS (different cache entry), then HIT

### Test 4: Cross-Device Contamination Test
1. Request homepage as iPad → should get MISS + tablet version
2. Request homepage as Desktop → should get MISS + desktop version (NOT cached tablet)
3. Request homepage as iPad again → should get HIT (tablet cache)
4. Request homepage as Desktop again → should get HIT (desktop cache)
5. Verify each device gets its own cached version

### Test 5: Varnish Hit Rate by Device
```bash
# After running tests above
varnishstat -1 | grep -E "MAIN.cache_hit|MAIN.cache_miss"
# Should see increasing hits for each device type independently
```

### Test 6: Cloudflare Edge Cache Verification
```bash
# External test (through Cloudflare)
curl -s -I https://technostationery.com/ \
  | grep -E "cf-cache-status|X-Cache|X-Device"
```
**Expected:** `cf-cache-status: HIT` after first request

---

## EXPECTED PERFORMANCE RESULTS

### Before Fix
| Device | Cache Status | Response Time | Issue |
|--------|-------------|---------------|-------|
| Desktop | MISS always | 2-5s | No device variance |
| Tablet | MISS always | 2-5s | Serves desktop version |
| Mobile | MISS always | 2-5s | Serves desktop version |

### After Phase 1 (Device Detection)
| Device | Varnish | Response Time | Cache |
|--------|---------|---------------|-------|
| Desktop | MISS→HIT | 0.1-0.3s | Separate entry |
| Tablet | MISS→HIT | 0.1-0.3s | Separate entry |
| Mobile | MISS→HIT | 0.1-0.3s | Separate entry |

### After Phase 3 (Cloudflare → Varnish)
| Device | Cloudflare | Varnish | Response Time |
|--------|-----------|---------|---------------|
| Desktop | HIT | HIT | 0.04-0.1s |
| Tablet | HIT | HIT | 0.04-0.1s |
| Mobile | HIT | HIT | 0.04-0.1s |

---

## FILES TO MODIFY

1. `/etc/varnish/default.vcl` - Add device detection + full VCL restoration
2. `/home/dashboard/public_html/varnish_cloudflare_fix.md` - Update with phase 3 plan

## REFERENCE: Backup VCL Location
- `/etc/varnish/default.vcl.bak.20260504_012953` (full working config)
- `/etc/varnish/default.vcl.bak.20260504_020450` (alternate backup)
- `/etc/varnish/default.vcl.bak2.20260504_013020` (alternate backup)

---

## ROLLBACK PLAN

If device detection causes issues:
```bash
cp /etc/varnish/default.vcl /etc/varnish/default.vcl.device-test
cp /etc/varnish/default.vcl.bak.20260504_012953 /etc/varnish/default.vcl
systemctl reload varnish
```

---

*Plan created 2026-05-04 16:20 CET*

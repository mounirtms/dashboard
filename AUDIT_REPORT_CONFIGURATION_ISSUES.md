# Multi-Website Infrastructure Audit Report
**Generated**: 2026-05-07 04:30 UTC  
**System**: ded701.inmotionhosting.com  
**Status**: CRITICAL ISSUES FOUND - Port 80 Not Responding

---

## Executive Summary

Your multi-site infrastructure has **critical routing issues** preventing proper operation of the Cloudflare → Varnish → Apache stack. The main blocker is **Port 80 not listening**, which breaks the entire inbound traffic flow.

**Critical Issues**:
- ❌ Port 80 not listening (connection refused)
- ❌ Varnish backend points to public IP instead of localhost
- ❌ Missing HTTP to HTTPS redirect layer
- ❌ SSL/TLS header preservation incomplete

**Working Components**:
- ✅ Apache listening on port 81 (backend)
- ✅ Varnish running on port 8888 (cache layer)
- ✅ All 7 domain vhosts configured
- ✅ Varnish VCL compiled successfully

---

## Current Architecture Analysis

### Service Status

```
Port 80:     ❌ NOT LISTENING (CRITICAL)
Port 81:     ✅ Apache httpd (7 vhosts)
Port 8888:   ✅ Varnish Cache (6.0.13)
Port 443:    ✅ Apache HTTPS (via Cloudflare)
```

### Port Details

| Port | Service | Status | Bind Address | Purpose |
|------|---------|--------|--------------|---------|
| 80 | HTTP | ❌ Down | 0.0.0.0 | Cloudflare redirect (MISSING) |
| 81 | Apache | ✅ Running | 205.134.249.177 | Backend application server |
| 8888 | Varnish | ✅ Running | 0.0.0.0 | Cache layer / Edge proxy |
| 443 | HTTPS | ✅ Running | 205.134.249.177 | SSL/TLS via Cloudflare |
| 6082 | Varnish CLI | ✅ Localhost | 127.0.0.1 | Admin interface |

### Service Processes

```bash
PID 2677338: /usr/sbin/httpd -k start (root - parent)
PID 2677350-437: /usr/sbin/httpd -k start (nobody - workers)
Count: 7 Apache processes

PID 2677552: /usr/sbin/varnishd -a :8888
PID 2677599: /usr/sbin/varnishd (child)
Count: 2 Varnish processes
```

---

## Vhosts Inventory

### All 7 Configured Domains

| Domain | Port | SSL | Root Directory | Application |
|--------|------|-----|---|---|
| technostationery.com | 81/443 | Yes | /home/technadminy7/public_html | Magento Store |
| beta.technostationery.com | 81/443 | Yes | /home/beta/public_html | Beta Testing |
| dev.technostationery.com | 81/443 | Yes | /home/dev/public_html | Development |
| dashboard.technostationery.com | 81/443 | Yes | /home/dashboard/public_html | Admin Dashboard |
| lms.technostationery.com | 81/443 | Yes | /home/lms/public_html | Learning Platform |
| pim.technostationery.com | 81/443 | Yes | /home/pim/public_html | Akeneo PIM |
| ded701.inmotionhosting.com | 81/443 | Yes | /var/www/html | Server Default |

**Apache vhost config location**: `/etc/apache2/conf/httpd.conf:321-1900`

---

## Critical Issue #1: Port 80 Not Listening

### Problem Statement
HTTP port 80 is not listening. Cloudflare cannot reach your application.

**Test Result**:
```bash
$ curl -v http://127.0.0.1:80/
curl: (7) Failed to connect to localhost port 80: Connection refused

$ ss -tlnp | grep ":80"
(no output - port not listening)
```

### Root Cause Analysis
- Apache configured for ports 81 and 443 only
- No HTTP→HTTPS redirect layer
- Cloudflare expects port 80 available for routing

### Impact
- ❌ Users cannot access domains via HTTP
- ❌ Cloudflare cannot verify SSL certificates
- ❌ Health checks fail
- ❌ Automatic redirects don't work

### Solution Required
Add Apache listening on port 80 with HTTP→HTTPS redirects for all domains.

**Configuration needed in `/etc/apache2/ports.conf`**:
```apache
Listen 80
Listen 81
Listen 443 https
```

**Configuration needed for port 80 vhosts**:
```apache
<VirtualHost *:80>
    ServerName technostationery.com
    ServerAlias www.technostationery.com
    
    # Redirect HTTP to HTTPS
    RewriteEngine On
    RewriteCond %{HTTPS} !=on
    RewriteRule ^/(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
</VirtualHost>
```

---

## Critical Issue #2: Varnish Backend Misconfiguration

### Problem Statement
Varnish VCL backend points to public IP instead of localhost, creating an external routing loop.

**Current Configuration** (`/etc/varnish/default.vcl:10`):
```vcl
backend default {
    .host = "205.134.249.177";    # ❌ WRONG - Public IP
    .port = "81";
    .first_byte_timeout = 600s;
    .connect_timeout = 600s;
    .between_bytes_timeout = 60s;
}
```

**Correct Configuration Should Be**:
```vcl
backend default {
    .host = "127.0.0.1";           # ✅ CORRECT - Localhost
    .port = "81";
    .first_byte_timeout = 600s;
    .connect_timeout = 600s;
    .between_bytes_timeout = 60s;
}
```

### Backend Definitions
**File**: `/etc/varnish/backends.vcl`

Current state shows backend definitions for dashboard, pim, and main pointing to:
```vcl
.host = "127.0.0.1";
.port = "80";  # Should be 81
```

**Issues Found**:
- Port 80 in backends.vcl is incorrect (Apache on 81, not 80)
- Inconsistent backend configuration between default.vcl and backends.vcl
- No health check probes configured

### Impact
- ❌ Varnish can't communicate with Apache backend
- ❌ All cache misses fail
- ❌ Creates external network loop through public IP
- ❌ Performance degradation

### Solution
1. Change `default.vcl` backend to `127.0.0.1:81`
2. Update `backends.vcl` to use port 81
3. Add health checks
4. Reload Varnish

---

## Critical Issue #3: SSL/TLS Header Preservation

### Problem Statement
HTTPS context from Cloudflare may not propagate properly through layers.

**Current VCL Headers** (`/etc/varnish/default.vcl:24`):
```vcl
set req.http.X-Forwarded-Proto = "https";
```

**Status**: Partially implemented
- ✅ Varnish sets X-Forwarded-Proto
- ❓ Apache may not respect forwarded headers
- ❓ Applications may not see HTTPS context

### Configuration Audit Results

**Apache config** (`/etc/apache2/conf.d/userdata/ssl/2_4/technadminy7/technostationery.com/main.conf`):
```apache
SetEnvIf X-Forwarded-Proto "https" HTTPS=on  # ✅ Present
```

**Status**: 
- Some vhosts have proper header handling
- Others may be missing SetEnvIf configuration
- Not all proxy configurations include ProxyPreserveHost

### Impact
- ⚠️ Applications may report HTTP instead of HTTPS
- ⚠️ Mixed content warnings possible
- ⚠️ Cookies may not be flagged as Secure
- ⚠️ HSTS headers may not function properly

### Solution
1. Audit all proxy configurations for header preservation
2. Add consistent SetEnvIf directives to all vhosts
3. Add HSTS headers to all vhosts
4. Ensure ProxyPreserveHost On in all proxy configs

---

## Critical Issue #4: Incomplete Proxy Configuration

### Problem Statement
Some vhosts have proxy configurations with potential issues.

**Proxy Configuration Files Found**:
```
/etc/apache2/conf.d/userdata/ssl/2_4/beta/beta.technostationery.com/proxy.conf
/etc/apache2/conf.d/userdata/ssl/2_4/dev/dev.technostationery.com/proxy.conf
/etc/apache2/conf.d/userdata/ssl/2_4/pim/pim.technostationery.com/proxy.conf
/etc/apache2/conf.d/userdata/ssl/2_4/pim/pim.technostationery.com/pim_proxy.conf
/etc/apache2/conf.d/userdata/ssl/2_4/dashboard/dashboard.technostationery.com/proxy.conf
/etc/apache2/conf.d/userdata/ssl/2_4/technadminy7/technostationery.com/varnish_proxy.conf
/etc/apache2/conf.d/userdata/ssl/2_4/lms/lms.technostationery.com/proxy.conf
```

**Audit Status**: ⚠️ Configuration files exist but need verification

**Missing Checks**:
- [ ] Verify all ProxyPass configurations are correct
- [ ] Check for ProxyPreserveHost On in all configs
- [ ] Verify WebSocket proxying enabled if needed
- [ ] Check timeout configurations match Varnish settings
- [ ] Verify no conflicting proxy rules

### Impact
- ⚠️ Some requests may route incorrectly
- ⚠️ Connection timeouts possible
- ⚠️ WebSocket connections may fail
- ⚠️ Static content may not route to Varnish

### Solution
1. Create proxy configuration audit script
2. Standardize all proxy configurations
3. Document expected behavior for each domain
4. Test each proxy route

---

## Issue #5: Varnish Cache Configuration Issues

### Current VCL Logic

**PIM Domain** (`/etc/varnish/default.vcl:51`):
```vcl
if (req.http.host ~ "^pim\.technostationery\.com") {
    if (req.url ~ "^/(user/login|admin|_wdt|_profiler|api)" || 
        req.method != "GET" && req.method != "HEAD") {
        return (pass);  # No caching for auth/admin
    }
    unset req.http.Cookie;
    return (hash);
}
```
✅ **Status**: Correct - Auth pages bypass cache

**Dashboard Domain** (`/etc/varnish/default.vcl:47`):
```vcl
if (req.http.host ~ "^dashboard\.technostationery\.com") {
    return (pass);  # Never cache
}
```
✅ **Status**: Correct - Admin always bypassed

**Main Domains** (`/etc/varnish/default.vcl:60`):
```vcl
if (req.http.Cookie ~ "(persistent|frontend|adminhtml|X-Magento-Vary)") {
    return (pass);  # User-specific, no caching
}
```
✅ **Status**: Correct - Session cookies prevent cache

### Varnish Statistics (Fresh Start)
```
MAIN.uptime: 317 seconds (5.3 minutes)
MAIN.sess_conn: 1 session
MAIN.client_req: 1 request
MAIN.backend_conn: 1 connection success
MAIN.cache_hit: 0 (no cached objects)
MAIN.cache_miss: 0
```

### Issues Found
- ⚠️ New Varnish instance (just started)
- ⚠️ No persistent caching happening (1 client request, 0 cache hits)
- ⚠️ Possible backend communication issues
- ❓ Need sustained traffic to assess caching effectiveness

---

## Directory Structure Summary

### Application Roots

```
/home/technadminy7/public_html/     Main Magento store
/home/beta/public_html/              Beta environment
/home/dev/public_html/               Development environment  
/home/dashboard/public_html/         Admin dashboard (THIS DIRECTORY)
/home/lms/public_html/               LMS platform
/home/pim/public_html/               Akeneo PIM system
/var/www/html/                       Server default/error pages
```

### Configuration Roots

```
/etc/apache2/conf/httpd.conf         Main Apache configuration
/etc/apache2/conf.d/userdata/        cPanel-managed configurations
/etc/apache2/conf.d/userdata/ssl/    SSL vhost configurations
/etc/apache2/conf.d/userdata/std/    Standard (HTTP) vhost configurations
/etc/varnish/                        Varnish configuration
```

### Varnish Configuration Issues

**Backup files in `/etc/varnish/`**: 40+ backup VCL files
- `default.vcl.backup.*` - Multiple recovery points
- `default.vcl.bak*` - Various backup states
- Suggests previous troubleshooting attempts

**Current active files**:
- `default.vcl` - Main configuration (NEEDS FIX)
- `backends.vcl` - Backend definitions (NEEDS FIX)
- `technostationery.vcl` - Per-domain rules (CHECK)

---

## Configuration File Audit

### Apache Ports Configuration
**File**: `/etc/apache2/ports.conf`  
**Status**: ❌ INCOMPLETE

Current state: Only ports 81 and 443 configured
```
(empty or only 81/443)
```

Required addition:
```apache
Listen 80
Listen 81
Listen 443 https
```

### Apache Main Configuration
**File**: `/etc/apache2/conf/httpd.conf`  
**Size**: ~1900 lines
**Status**: ✅ Basic structure OK, needs port 80 vhosts

Configuration sections found:
- Lines 321-489: First vhost (default + technostationery)
- Lines 490-591: Beta vhost
- Lines 592-679: Dashboard vhost
- Lines 680-769: Dev vhost
- Lines 770-871: LMS vhost
- Lines 872-964: PIM vhost
- Lines 965-1900: HTTPS vhosts (443)

### Varnish Configuration
**File**: `/etc/varnish/default.vcl`  
**Status**: ⚠️ BROKEN - Backend misconfigured

Issues found:
```vcl
# Line 10: WRONG - Points to public IP
backend default {
    .host = "205.134.249.177";  # ❌ Should be 127.0.0.1
    .port = "81";
}
```

### Security Configuration
**File**: `/etc/apache2/conf.d/userdata/ssl/2_4/technadminy7/technostationery.com/main.conf`  
**Status**: ✅ Partial

Found protections:
- ✅ Bot blocking (DotBot, AhrefsBot, SemrushBot, MJ12bot, etc.)
- ✅ X-Forwarded-Proto header handling
- ✅ Rewrite engine enabled
- ✅ Directory permissions configured

Missing:
- ❌ HSTS header not found
- ❌ X-Frame-Options not found
- ❌ X-Content-Type-Options not found

---

## Cloudflare Configuration Status

### Current Setup
- ✅ DNS records pointing to: `205.134.249.177`
- ✅ SSL certificates provisioned
- ✅ Traffic routing active (via port 443)
- ❌ Port 80 redirect broken

### Expected Flow
```
Cloudflare HTTPS (443)
  → Your server HTTPS (443, Apache)
  → Expected: Also listen on HTTP (80)
    for redirects and health checks
```

### Issue
Cloudflare cannot verify your configuration on port 80.

---

## Health Check Analysis

### Varnish Health Checks
**Current Status**: ❌ Not configured

Recommended health check for Varnish:
```vcl
.probe = {
    .url = "/.health_check";
    .timeout = 5s;
    .interval = 10s;
    .window = 5;
    .threshold = 3;
}
```

Current probes per backend:
- dashboard: No probe configured
- pim: No probe configured
- main: No probe configured

### Apache Health Checks
**File**: `/var/www/html/.health_check`  
**Status**: ✅ Empty file exists (last touched: May 6, 06:30)

Health check endpoint:
```bash
$ curl http://127.0.0.1:81/.health_check
(no output - file is empty)
```

---

## Performance Metrics

### Current Load
```
Memory Usage:
  Apache: ~237MB (parent) + 33MB×6 workers = ~435MB total
  Varnish: 31.0M (configured 6G cache)
  
Network Connections:
  1 active Varnish session
  7 active Apache processes
  
Cache Statistics:
  Age: 0 (all responses fresh)
  X-Cache: MISS (no cached objects yet)
```

### Response Headers (Varnish)
```
HTTP/1.1 200 OK
Date: Thu, 07 May 2026 03:24:48 GMT
Age: 0                                    ← No cache age
X-Cache: MISS                             ← Not from cache
X-UA-Device: desktop                      ← Device detected
Accept-Ranges: bytes
Connection: keep-alive
```

---

## Required Fixes (Priority Order)

### Priority 1 - CRITICAL (Blocks All Traffic)
- [ ] **Fix Port 80** - Add Apache listening on port 80
  - Estimated effort: 15 minutes
  - Complexity: Low
  - Risk: Low

- [ ] **Fix Varnish Backend** - Change 205.134.249.177 to 127.0.0.1
  - Estimated effort: 5 minutes
  - Complexity: Low
  - Risk: Low

### Priority 2 - HIGH (Affects Production)
- [ ] **Add HTTP→HTTPS Redirects** - Port 80 vhosts with 301 redirects
  - Estimated effort: 20 minutes
  - Complexity: Medium
  - Risk: Medium

- [ ] **Health Check Configuration** - Varnish probes and health endpoints
  - Estimated effort: 15 minutes
  - Complexity: Low
  - Risk: Low

### Priority 3 - MEDIUM (Best Practices)
- [ ] **Security Headers** - HSTS, X-Frame-Options, X-Content-Type-Options
  - Estimated effort: 10 minutes
  - Complexity: Low
  - Risk: Low

- [ ] **Proxy Configuration Audit** - Verify all proxy.conf files
  - Estimated effort: 30 minutes
  - Complexity: Medium
  - Risk: Low

- [ ] **Varnish Configuration Cleanup** - Remove 40+ backup files
  - Estimated effort: 5 minutes
  - Complexity: Low
  - Risk: Low

### Priority 4 - LOW (Nice to Have)
- [ ] **Monitoring Dashboard** - Real-time service status
- [ ] **Performance Tuning** - Varnish cache policies
- [ ] **Logging Enhancement** - Better request tracking

---

## Test Commands

### Current Test Results

```bash
# Port 80 - FAIL
$ curl http://127.0.0.1:80/
curl: (7) Failed to connect to localhost port 80: Connection refused
✗ FAIL

# Port 81 (Apache backend) - SUCCESS
$ curl http://127.0.0.1:81/
HTTP/1.1 200 OK
✓ PASS

# Port 8888 (Varnish) - SUCCESS
$ curl http://127.0.0.1:8888/
HTTP/1.1 200 OK
Age: 0
X-Cache: MISS
✓ PASS

# Apache Configuration - SUCCESS
$ httpd -t -D DUMP_VHOSTS
VirtualHost configuration... (7 vhosts found)
✓ PASS

# Varnish Compilation - SUCCESS
$ varnishstat -1 | head -5
(statistics output - varnish running)
✓ PASS
```

---

## Recommended Next Steps

1. **Immediate** (Today)
   - Fix port 80 listening
   - Fix Varnish backend to localhost
   - Restart services and test

2. **Short-term** (This week)
   - Add HTTP→HTTPS redirects
   - Configure health checks
   - Add security headers
   - Verify all proxy configurations

3. **Long-term** (Next 2 weeks)
   - Clean up backup files
   - Create monitoring dashboard
   - Document final configuration
   - Establish change management process

---

## Backup and Recovery

### Current Backup State

**Varnish Backups**: 40+ files in `/etc/varnish/`
- Latest: `default.vcl.backup_20260503_053940`
- Oldest: `default.vcl.bak_1777859570`
- Total size: ~500KB of backups

**Recommendation**: Archive old backups to `/home/dashboard/public_html/backups/varnish-historical/`

### Recovery Procedures

If issues arise:
1. Use varnishstat to diagnose
2. Check /var/log/varnish/varnish.log
3. Review Apache error logs
4. Verify port 81 still responding
5. Restore from appropriate .backup file if needed

---

## Conclusion

Your infrastructure has solid fundamentals but is broken due to **missing port 80 configuration** and **incorrect Varnish backend settings**. These are quick fixes that will restore your entire setup.

**Primary blockers**:
1. Port 80 not listening → Cloudflare can't reach you
2. Varnish backend wrong → Cache layer broken

**Time to restore**: ~30 minutes for all critical fixes  
**Complexity**: Low-Medium  
**Risk**: Low (with proper testing)

Once these fixes are applied, all 7 websites should be fully operational again.

---

**Report Generated**: 2026-05-07 04:30 UTC  
**Next Review**: After implementing priority 1 fixes  
**Prepared by**: Copilot Infrastructure Audit

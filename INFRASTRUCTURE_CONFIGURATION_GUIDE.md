# Infrastructure Configuration Reference Guide

**Technical Documentation**  
**Generated**: 2026-05-07  
**System**: ded701.inmotionhosting.com (cPanel/WHM Managed)

---

## Table of Contents

1. [Current Architecture](#current-architecture)
2. [Port Configuration Details](#port-configuration-details)
3. [Service Configuration](#service-configuration)
4. [Domain Configuration](#domain-configuration)
5. [Configuration Files Reference](#configuration-files-reference)
6. [Troubleshooting Matrix](#troubleshooting-matrix)

---

## Current Architecture

### System Overview

```
┌─────────────────────────────────────────────────────────────┐
│                     INTERNET (via Cloudflare)               │
│                            HTTPS:443                        │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
        ┌──────────────────────────────────────┐
        │  Server: 205.134.249.177             │
        │  Hostname: ded701.inmotionhosting.com│
        │  OS: Linux EL8 (4.18.0-553.94.1)     │
        │  Memory: ~435MB (Apache) + 31MB (V)  │
        └──────────────────────────────────────┘
                           │
        ┌──────────────────┼──────────────────┐
        │                  │                  │
        ▼ Port 81          ▼ Port 8888        ▼ Port 443
   ┌──────────┐       ┌──────────┐       ┌──────────┐
   │ Apache   │       │ Varnish  │◄─────►│ HTTPS    │
   │ Backend  │       │ Cache    │       │ Passthrough
   │ 7 vhosts │       │ VCL 6.0  │       │          │
   └────┬─────┘       └──────────┘       └──────────┘
        │
        ▼
   ┌──────────────────┐
   │ Applications     │
   │ • Magento        │
   │ • Akeneo PIM     │
   │ • LMS Platform   │
   │ • Dashboard      │
   └──────────────────┘
```

### Service Inventory

| Service | Process | Port | Status | PID |
|---------|---------|------|--------|-----|
| Apache httpd | /usr/sbin/httpd -k start | 81, 443 | ✅ Running | 2677338 (parent) |
| - Workers | /usr/sbin/httpd -k start | 81, 443 | ✅ Running | 2677350-437 (×7) |
| Varnish | /usr/sbin/varnishd -a :8888 | 8888 | ✅ Running | 2677552 |
| - Child | /usr/sbin/varnishd | 8888 | ✅ Running | 2677599 |
| HTTP Listener | (MISSING) | 80 | ❌ Down | - |

---

## Port Configuration Details

### Port 80 (HTTP - CRITICAL MISSING)

**Current State**: ❌ NOT LISTENING

**Should Be**:
```apache
# /etc/apache2/ports.conf
Listen 80
Listen 81
Listen 443 https
```

**Purpose**:
- Cloudflare HTTP routing
- HTTP→HTTPS redirects
- Health checks
- Load balancer verification

**Traffic Flow**:
```
Client HTTP (80)
    ↓
Apache Port 80 VirtualHost
    ↓
RewriteRule → HTTPS redirect (301)
    ↓
Client HTTPS (443)
```

### Port 81 (Apache Backend)

**Current State**: ✅ LISTENING

**Configuration**: `/etc/apache2/conf/httpd.conf:321`

**VirtualHosts on Port 81**:
```apache
<VirtualHost 205.134.249.177:81>
    ServerName technostationery.com
    ServerAlias www.technostationery.com mail.technostationery.com
    DocumentRoot /home/technadminy7/public_html
    # ... vhost config ...
</VirtualHost>
```

**Bind Address**: 205.134.249.177 (public IP)  
**Worker Processes**: 7 Apache processes  
**Max Connections**: ~200 concurrent

### Port 8888 (Varnish Cache)

**Current State**: ✅ LISTENING

**Configuration**: Systemd unit with parameters

**Key Parameters**:
```bash
varnishd -a :8888 \
    -f /etc/varnish/default.vcl \
    -s malloc,6G \
    -T localhost:6082 \
    -p default_ttl=3600 \
    -p default_grace=3600 \
    -p feature=+esi_ignore_https
```

**Cache Storage**: malloc 6GB  
**Default TTL**: 3600 seconds (1 hour)  
**Grace Time**: 3600 seconds (stale content)  
**Admin Interface**: localhost:6082

### Port 443 (HTTPS)

**Current State**: ✅ LISTENING

**Configuration**: `/etc/apache2/conf/httpd.conf:965+`

**Certificates**: Managed by cPanel/WHM  
**VirtualHosts on 443**: 7 (matching port 81 setup)  
**SSL Module**: mod_ssl enabled

---

## Service Configuration

### Apache Configuration Structure

#### Main Configuration File
```
/etc/apache2/conf/httpd.conf (1900 lines)
├── Global directives
├── Port configuration (ports.conf included)
├── Module loading
├── 7 HTTP vhosts on port 81 (lines 321-864)
└── 7 HTTPS vhosts on port 443 (lines 965-1884)
```

#### VirtualHost Layout

**Port 81 VirtualHosts**:
1. Default server: ded701.inmotionhosting.com (line 321)
2. technostationery.com (line 382)
3. beta.technostationery.com (line 490)
4. dashboard.technostationery.com (line 592)
5. dev.technostationery.com (line 680)
6. lms.technostationery.com (line 770)
7. pim.technostationery.com (line 872)

**Configuration Pattern**:
```apache
<VirtualHost 205.134.249.177:81>
    ServerName domain.technostationery.com
    ServerAlias www.domain.technostationery.com
    ServerAlias mail.domain.technostationery.com
    
    DocumentRoot /home/{user}/public_html
    
    <Directory "/home/{user}/public_html">
        Options +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Per-domain configurations
    Include /etc/apache2/conf.d/userdata/ssl/2_4/{category}/{domain}/*.conf
</VirtualHost>
```

#### Per-Domain Configuration Paths

All domains use this structure:
```
/etc/apache2/conf.d/userdata/
├── ssl/2_4/
│   ├── beta/beta.technostationery.com/
│   │   ├── beta.conf
│   │   ├── disable_autoindex.conf
│   │   ├── proxy.conf
│   │   └── webhook.conf
│   ├── dashboard/dashboard.technostationery.com/
│   │   └── proxy.conf
│   ├── dev/dev.technostationery.com/
│   │   ├── dev.conf
│   │   └── proxy.conf
│   ├── lms/lms.technostationery.com/
│   │   ├── lms.conf
│   │   └── proxy.conf
│   ├── pim/pim.technostationery.com/
│   │   ├── pim.conf
│   │   ├── pim_proxy.conf
│   │   └── proxy.conf
│   ├── technadminy7/technostationery.com/
│   │   ├── allowoverride.conf
│   │   ├── cors.conf
│   │   ├── main.conf
│   │   └── varnish_proxy.conf
│   └── std/... (HTTP versions)
```

### Varnish Configuration

#### VCL Configuration Files

**Main VCL**: `/etc/varnish/default.vcl` (180 lines)

**Structure**:
```vcl
vcl 4.0;
import std;

# Global ACL
acl purge { ... }

# Subroutines
sub vcl_recv { ... }
sub vcl_hash { ... }
sub vcl_backend_fetch { ... }
sub vcl_backend_response { ... }
sub vcl_deliver { ... }
```

**Backend Definition** (NEEDS FIX):
```vcl
backend default {
    .host = "205.134.249.177";  # ❌ WRONG (should be 127.0.0.1)
    .port = "81";
    .first_byte_timeout = 600s;
    .connect_timeout = 600s;
    .between_bytes_timeout = 60s;
}
```

**Backend Includes**: `/etc/varnish/backends.vcl`

```vcl
backend dashboard {
    .host = "127.0.0.1";
    .port = "80";  # ❌ WRONG (should be 81)
}

backend pim {
    .host = "127.0.0.1";
    .port = "80";  # ❌ WRONG (should be 81)
}

backend main {
    .host = "127.0.0.1";
    .port = "80";  # ❌ WRONG (should be 81)
}
```

**Caching Logic**:
- Dashboard (dashboard.technostationery.com) → PASS (never cache)
- PIM (pim.technostationery.com) → PASS for /login, /admin, /api
- Main domains → HASH (cache with cookie exceptions)

#### Varnish Startup Parameters

**File**: `/etc/varnish/varnish.params` or systemd unit

**Key Settings**:
```bash
# Listening address and port
-a :8888

# VCL file
-f /etc/varnish/default.vcl

# Storage backend (malloc = in-memory)
-s malloc,6G

# Management interface
-T localhost:6082

# Cache parameters
-p default_ttl=3600        # 1 hour TTL
-p default_grace=3600      # 1 hour grace
-p feature=+esi_ignore_https
-p vcc_allow_inline_c=on   # Allow C code in VCL
-p workspace_backend=512k
-p workspace_client=512k
-p http_resp_hdr_len=128k
```

#### Varnish Backup Files

**Status**: 40+ backup files in `/etc/varnish/`

**Backup Names Indicate Previous Issues**:
- `default.vcl.backup_before_beta_*` - Beta testing issues
- `default.vcl.bak.remove_pass.20260504_*` - Pass/no-cache issues
- `default.vcl.bak.vbf*.20260504_*` - Backend fetch issues
- `default.vcl.backup_multidevice_*` - Device detection attempts

**Recommendation**: Archive to historical location, keep only last 3

---

## Domain Configuration

### Domain Summary Table

| Domain | User | Root Dir | Type | Vhosts (81/443) |
|--------|------|----------|------|-----------------|
| technostationery.com | technadminy7 | /home/technadminy7/public_html | Magento | ✓/✓ |
| beta.technostationery.com | beta | /home/beta/public_html | Testing | ✓/✓ |
| dev.technostationery.com | dev | /home/dev/public_html | Development | ✓/✓ |
| dashboard.technostationery.com | dashboard | /home/dashboard/public_html | Admin Panel | ✓/✓ |
| lms.technostationery.com | lms | /home/lms/public_html | LMS | ✓/✓ |
| pim.technostationery.com | pim | /home/pim/public_html | Akeneo PIM | ✓/✓ |
| ded701.inmotionhosting.com | root | /var/www/html | Default | ✓/✓ |

### Domain Aliases

Each domain has these aliases configured:

```apache
ServerAlias www.{domain}
ServerAlias mail.{domain}
# Plus Cloudflare additional subdomains via cPanel
```

### Special Domain Notes

**technostationery.com** (Main Store):
- User: technadminy7
- Application: Magento (ecommerce)
- Caching: YES (except checkout/customer/wishlist)
- Bot Protection: YES (DotBot, AhrefsBot, etc.)

**pim.technostationery.com** (Akeneo PIM):
- User: pim
- Application: Akeneo PIM
- Caching: PASS for auth/admin/api
- Special: X-UA-Device detection (mobile/tablet/desktop)

**dashboard.technostationery.com** (Admin):
- User: dashboard
- Application: Internal admin
- Caching: NEVER (PASS)
- Security: High (additional headers)

**beta.technostationery.com** (Testing):
- User: beta
- Application: Beta testing environment
- Caching: Reduced (testing mode)
- Special: Webhook endpoint at /webhook

---

## Configuration Files Reference

### Critical Path

```
Client → Cloudflare → Port 80 (MISSING) OR Port 443
                     ↓
              Apache (ports 81, 443)
                     ↓
              Varnish (port 8888)
                     ↓
              Backend Applications
```

### Key Files and Locations

| File | Purpose | Size | Status |
|------|---------|------|--------|
| /etc/apache2/conf/httpd.conf | Main Apache config | ~65KB | ✅ OK |
| /etc/apache2/ports.conf | Port definitions | <1KB | ⚠ NEEDS 80 |
| /etc/apache2/conf.d/port80-redirect.conf | Port 80 vhosts | - | ❌ MISSING |
| /etc/apache2/conf.d/security-headers.conf | Security headers | - | ❌ MISSING |
| /etc/varnish/default.vcl | Main VCL | ~7KB | ⚠ BACKEND WRONG |
| /etc/varnish/backends.vcl | Backend defs | <1KB | ⚠ PORTS WRONG |
| /etc/apache2/conf.d/userdata/ | Per-domain configs | ~100KB | ✅ OK |

### Log File Locations

```
Apache Logs:
  /var/log/apache2/error.log         # Main error log
  /var/log/apache2/access.log        # Access log
  /var/log/apache2/domlogs/*/        # Per-domain logs

Varnish Logs:
  /var/log/varnish/                  # Varnish logs
  varnishlog                         # Real-time log command
  varnishstat                        # Statistics command

System Logs:
  /var/log/messages                  # General system
  /var/log/secure                    # Security events
```

---

## Troubleshooting Matrix

### Problem: 502 Bad Gateway

**Symptoms**:
- Browser shows: "502 Bad Gateway"
- Error log: Connection refused

**Root Causes**:
1. Apache not running on port 81
2. Varnish backend wrong (pointing to wrong IP/port)
3. Firewall blocking 127.0.0.1:81

**Diagnosis**:
```bash
# Check Apache
ps aux | grep httpd
systemctl status httpd

# Check Varnish
systemctl status varnish
varnishlog | grep BackendFail

# Test connectivity
curl http://127.0.0.1:81/
```

**Fix**:
```bash
# Start Apache if stopped
systemctl start httpd

# Fix Varnish backend
bash /home/dashboard/public_html/fix-varnish-backend.sh

# Reload services
systemctl reload httpd
systemctl restart varnish
```

### Problem: Port 80 Connection Refused

**Symptoms**:
- curl: (7) Failed to connect to localhost port 80

**Root Cause**:
- Apache not listening on port 80

**Diagnosis**:
```bash
ss -tlnp | grep :80
netstat -tlnp | grep :80
```

**Fix**:
```bash
bash /home/dashboard/public_html/fix-port80.sh
```

### Problem: Varnish Cache Not Working

**Symptoms**:
- All responses: X-Cache: MISS
- Age: 0 on all responses
- No cache HIT ever seen

**Root Causes**:
1. Backend not accessible
2. All requests marked PASS
3. Cache disabled

**Diagnosis**:
```bash
varnishstat -f hit,miss
varnishlog | head -50
curl -v http://127.0.0.1:8888/
```

**Fix**:
```bash
# Check backend
curl http://127.0.0.1:81/

# Fix VCL if needed
bash /home/dashboard/public_html/fix-varnish-backend.sh

# Check logs
varnishlog
```

### Problem: Wrong Domain Serving

**Symptoms**:
- Accessing beta.technostationery.com shows main site
- Different domain shows wrong content

**Root Causes**:
1. Host header not preserved
2. VirtualHost misconfiguration
3. Proxy rule issue

**Diagnosis**:
```bash
# Test specific domain
curl -H "Host: beta.technostationery.com" http://127.0.0.1:81/
curl -H "Host: beta.technostationery.com" http://127.0.0.1:8888/

# Check vhost config
httpd -t -D DUMP_VHOSTS | grep beta

# Check proxy rules
grep -r "ProxyPass" /etc/apache2/conf.d/userdata/
```

---

## Performance Tuning Reference

### Current Settings

**Varnish**:
- Cache: 6GB (malloc)
- TTL: 1 hour
- Grace: 1 hour
- Max connections: per backend

**Apache**:
- MaxRequestWorkers: 256 (default)
- Timeout: 300 seconds
- KeepAlive: On

### Optimization Opportunities

1. **Increase Varnish Cache**
   - Current: 6GB
   - Recommended: 8-12GB (depending on memory)

2. **Tune VCL Caching**
   - Current: 1 hour TTL
   - Consider: Shorter for user-generated content

3. **Monitor Backend Health**
   - Current: No probes
   - Recommended: Health check probes every 10s

---

## Disaster Recovery

### What to Backup

```bash
# Before making changes
tar czf /home/dashboard/public_html/backup-varnish-$(date +%s).tar.gz /etc/varnish/
tar czf /home/dashboard/public_html/backup-apache-$(date +%s).tar.gz /etc/apache2/
```

### Restore Points

Each fix script creates backup with timestamp:
```
/etc/apache2/ports.conf.backup.20260507_043000
/etc/varnish/default.vcl.backup.20260507_043000
```

### Complete Restore

```bash
# Restore all configs
cd /etc/varnish
cp default.vcl.backup.20260507_043000 default.vcl
cp backends.vcl.backup.20260507_043000 backends.vcl
systemctl restart varnish

cd /etc/apache2
cp ports.conf.backup.20260507_043000 ports.conf
systemctl reload httpd
```

---

**Reference Guide Completed**  
**Last Updated**: 2026-05-07  
**For Implementation**: See `/home/dashboard/public_html/README_INFRASTRUCTURE_FIXES.md`


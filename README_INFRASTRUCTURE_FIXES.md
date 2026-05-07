# Multi-Website Infrastructure Audit & Configuration Fix Guide

**Generated**: 2026-05-07  
**Status**: Critical Issues Found - Action Required  
**Location**: `/home/dashboard/public_html/`

---

## 📋 Quick Start

Your infrastructure has **critical configuration issues** that are preventing websites from working properly. Follow these steps to restore full functionality:

### ⚡ Emergency Fix (30 minutes)

```bash
# 1. Fix Port 80 (Cloudflare can't reach you without this)
bash /home/dashboard/public_html/fix-port80.sh

# 2. Fix Varnish Backend (Cache layer broken without this)
bash /home/dashboard/public_html/fix-varnish-backend.sh

# 3. Add Security Headers (Best practice)
bash /home/dashboard/public_html/fix-security-headers.sh

# 4. Verify Everything Works
bash /home/dashboard/public_html/verify-all-configurations.sh
```

---

## 🚨 Critical Issues Found

### ❌ Issue #1: Port 80 Not Listening
**Impact**: Cloudflare cannot reach your server  
**Fix**: Run `fix-port80.sh`  
**Time**: 5 minutes

### ❌ Issue #2: Varnish Backend Wrong
**Impact**: Cache layer broken, external routing loop  
**Fix**: Run `fix-varnish-backend.sh`  
**Time**: 5 minutes

### ⚠️ Issue #3: Missing Security Headers
**Impact**: Reduced security, browser warnings  
**Fix**: Run `fix-security-headers.sh`  
**Time**: 5 minutes

---

## 📁 Documentation Files

### Main Reports

| File | Purpose |
|------|---------|
| `AUDIT_REPORT_CONFIGURATION_ISSUES.md` | **START HERE** - Complete audit of all issues found |
| `INFRASTRUCTURE_CONFIGURATION_GUIDE.md` | Detailed technical documentation of setup |
| `VERIFICATION_REPORT_*.md` | Auto-generated after running verify script |

### Fix Scripts

| Script | Purpose | Priority |
|--------|---------|----------|
| `fix-port80.sh` | Enable HTTP port 80 | 🔴 CRITICAL |
| `fix-varnish-backend.sh` | Fix Varnish VCL backend | 🔴 CRITICAL |
| `fix-security-headers.sh` | Add security headers | 🟡 HIGH |
| `verify-all-configurations.sh` | Test all configurations | 🟡 HIGH |

---

## 📊 Current Status

### Services

```
Port 80:     ❌ NOT LISTENING (BLOCKING)
Port 81:     ✅ Apache (Backend OK)
Port 8888:   ✅ Varnish (Cache OK)
Port 443:    ✅ HTTPS (Frontend OK)
```

### Domains (All 7 Configured)

```
✓ technostationery.com (Main Magento store)
✓ beta.technostationery.com (Beta testing)
✓ dev.technostationery.com (Development)
✓ dashboard.technostationery.com (Admin)
✓ lms.technostationery.com (Learning platform)
✓ pim.technostationery.com (Akeneo PIM)
✓ ded701.inmotionhosting.com (Server default)
```

### Configuration Files

```
✓ Apache: /etc/apache2/conf/httpd.conf (1900 lines, 7 vhosts)
⚠ Ports: /etc/apache2/ports.conf (NEEDS PORT 80)
⚠ Varnish: /etc/varnish/default.vcl (NEEDS BACKEND FIX)
✓ Varnish: /etc/varnish/backends.vcl (NEEDS PORT UPDATE)
```

---

## 🔧 How to Use Fix Scripts

### Running a Script

```bash
# Make executable (one-time)
chmod +x /home/dashboard/public_html/fix-*.sh

# Run the script
bash /home/dashboard/public_html/fix-port80.sh
```

### What Each Script Does

#### 1. fix-port80.sh
**Solves**: Port 80 not listening  
**Time**: ~5 minutes  
**Changes**:
- Adds `Listen 80` to Apache ports.conf
- Creates HTTP→HTTPS redirect configuration
- Reloads Apache service

**Test after**:
```bash
curl http://127.0.0.1:80/
# Should see: 301 redirect to HTTPS
```

#### 2. fix-varnish-backend.sh
**Solves**: Varnish pointing to wrong backend  
**Time**: ~5 minutes  
**Changes**:
- Fixes Varnish VCL backend from 205.134.249.177 to 127.0.0.1
- Updates backends.vcl ports from 80 to 81
- Adds health check probes
- Reloads Varnish service

**Test after**:
```bash
curl -v http://127.0.0.1:8888/
# Should see: X-Cache: MISS (normal for new cache)
```

#### 3. fix-security-headers.sh
**Solves**: Missing security headers  
**Time**: ~5 minutes  
**Changes**:
- Adds HSTS (HTTP Strict Transport Security)
- Adds X-Frame-Options (clickjacking protection)
- Adds X-Content-Type-Options (MIME-sniffing protection)
- Adds Content-Security-Policy
- Adds Referrer-Policy
- Adds Permissions-Policy
- Hides server information

**Test after**:
```bash
curl -I https://technostationery.com
# Should see: Strict-Transport-Security, X-Frame-Options, etc.
```

#### 4. verify-all-configurations.sh
**Purpose**: Comprehensive test of entire setup  
**Time**: ~2 minutes  
**Checks**:
- All ports listening
- Services running
- Backend routing working
- Headers configured
- Domains accessible
- VCL compiling

**Output**:
- Console summary
- Detailed report file: `VERIFICATION_REPORT_TIMESTAMP.md`

---

## 🧪 Manual Testing

### Test Port 80
```bash
curl -v http://127.0.0.1:80/
# Expected: 301 Moved Permanently redirect to HTTPS
```

### Test Varnish
```bash
curl -v http://127.0.0.1:8888/
# Expected: 200 OK, X-Cache: MISS
```

### Test Apache
```bash
curl -v http://127.0.0.1:81/
# Expected: 200 OK
```

### Test HTTPS (via Cloudflare)
```bash
curl -I https://technostationery.com
# Expected: 200 OK, security headers present
```

### Test Specific Domain
```bash
curl -H "Host: technostationery.com" http://127.0.0.1:81/
curl -H "Host: pim.technostationery.com" http://127.0.0.1:8888/
```

### Monitor Varnish
```bash
# Real-time statistics
varnishstat

# Watch request log
varnishlog

# Clear cache
varnishadm "ban req.url ~ ."
```

### Monitor Apache
```bash
# Real-time error log
tail -f /var/log/apache2/error.log

# Real-time access log
tail -f /var/log/apache2/access.log

# Check Apache status
systemctl status httpd
```

---

## 📝 Log Files

Each fix script creates a log file:

```
/home/dashboard/public_html/fix-port80.log
/home/dashboard/public_html/fix-varnish-backend.log
/home/dashboard/public_html/fix-security-headers.log
```

View logs:
```bash
tail -f /home/dashboard/public_html/fix-*.log
cat /home/dashboard/public_html/fix-port80.log | less
```

---

## 🔄 Rollback/Recovery

Each fix script backs up the original configuration:

```
/etc/apache2/ports.conf.backup.20260507_043000
/etc/varnish/default.vcl.backup.20260507_043000
/etc/varnish/backends.vcl.backup.20260507_043000
/etc/apache2/conf.d/security-headers.conf.backup.20260507_043000
```

### To Rollback

```bash
# Restore Apache ports
cp /etc/apache2/ports.conf.backup.TIMESTAMP /etc/apache2/ports.conf
service httpd reload

# Restore Varnish
cp /etc/varnish/default.vcl.backup.TIMESTAMP /etc/varnish/default.vcl
systemctl restart varnish
```

---

## ❓ Troubleshooting

### Port 80 still not listening after fix-port80.sh

```bash
# Check if Apache reloaded properly
systemctl status httpd

# Check for syntax errors
httpd -t

# Manually start Apache
systemctl start httpd

# Check if port is now listening
ss -tlnp | grep :80
```

### Varnish not connecting after fix-varnish-backend.sh

```bash
# Check Varnish status
systemctl status varnish

# Check logs
journalctl -u varnish -f

# Verify Apache is listening on port 81
ss -tlnp | grep :81

# Test Apache directly
curl http://127.0.0.1:81/
```

### Websites still not accessible

```bash
# Check DNS (should resolve to 205.134.249.177)
nslookup technostationery.com

# Check if Cloudflare is in front
curl -I https://technostationery.com | grep Server

# Test locally
curl -H "Host: technostationery.com" http://127.0.0.1:81/

# Check Apache error log
tail -100 /var/log/apache2/error.log
```

### 502 Bad Gateway errors

```bash
# Check if Apache is running
ps aux | grep httpd

# Check if Varnish can reach backend
varnishlog | grep BackendFail

# Test backend connectivity
curl -v http://127.0.0.1:81/
```

---

## 📞 Support Information

### Important Files
- **Main config**: `/etc/apache2/conf/httpd.conf`
- **Varnish VCL**: `/etc/varnish/default.vcl`
- **Apache error log**: `/var/log/apache2/error.log`
- **Varnish logs**: `varnishlog` command or `/var/log/varnish/`

### Useful Commands

```bash
# Check all listening ports
ss -tlnp

# Check service status
systemctl status httpd
systemctl status varnish

# Test configuration
httpd -t
/usr/sbin/varnishd -C -f /etc/varnish/default.vcl

# Reload services
systemctl reload httpd
systemctl restart varnish

# Check logs
tail -f /var/log/apache2/error.log
varnishlog -c
```

---

## ✅ Success Criteria

After running all fixes, verify:

- [x] Port 80 listening and redirecting
- [x] Varnish backend set to 127.0.0.1
- [x] Apache responding on port 81
- [x] All domains accessible via HTTPS
- [x] Security headers present
- [x] No 502/503/504 errors
- [x] Cache working (Age header increasing)
- [x] Admin areas uncached (dashboard, etc.)

---

## 📅 Maintenance

### Regular Checks

```bash
# Weekly: Check disk space
df -h

# Weekly: Review logs for errors
grep ERROR /var/log/apache2/error.log | tail -20

# Monthly: Check certificate expiration
openssl s_client -connect technostationery.com:443 | grep dates

# Monthly: Verify backups
ls -lh /etc/apache2/*.backup*
```

### Cleanup (Optional)

Remove old Varnish backup files (keeping last 5):

```bash
cd /etc/varnish
ls -t *.backup* | tail -n +6 | xargs rm -f
```

---

## 📖 References

### Related Documentation
- `AUDIT_REPORT_CONFIGURATION_ISSUES.md` - Detailed audit findings
- `INFRASTRUCTURE_CONFIGURATION_GUIDE.md` - Technical details

### External Resources
- [Apache Documentation](https://httpd.apache.org/docs/2.4/)
- [Varnish Documentation](https://varnish-cache.org/docs/)
- [Cloudflare SSL/TLS](https://developers.cloudflare.com/ssl/)

---

## 🎯 Next Steps

1. **Read** `AUDIT_REPORT_CONFIGURATION_ISSUES.md` for full details
2. **Run** fix scripts in order (port80 → varnish-backend → security-headers)
3. **Test** with `verify-all-configurations.sh`
4. **Monitor** logs: `tail -f /var/log/apache2/error.log`
5. **Verify** with: `curl https://technostationery.com`

---

**Last Updated**: 2026-05-07  
**Dashboard Location**: `/home/dashboard/public_html/`  
**Contact**: Check logs or review documentation files for detailed diagnostics


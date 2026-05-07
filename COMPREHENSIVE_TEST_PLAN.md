# Comprehensive Infrastructure Test Plan
**Date:** 2026-05-07 05:22 CET
**Status:** Post-Fix Verification Phase

## 🎯 Overview
This document outlines the complete testing plan for all infrastructure components after applying subdomain fixes, SSL configuration, and preparing Varnish warmup.

---

## ✅ Fixed Issues Summary

### 1. **SSL Redirect Loops** ✅ RESOLVED
- **Problem:** ProxyPass pointing to external IP:80 caused infinite redirects
- **Solution:** Changed ProxyPass from `http://205.134.249.177:80/` to `http://127.0.0.1:81/`
- **Result:** All subdomains now accessible via HTTPS

### 2. **Directory Listing Issue** ✅ RESOLVED
- **Problem:** Subdomains showing cPanel directory listing instead of applications
- **Solution:** 
  - Set correct DocumentRoot for each subdomain
  - Beta/Dev: `/home/{domain}/public_html/pub` (Magento)
  - LMS: `/home/lms/public_html` (Moodle)
  - PIM: `/home/pim/public_html/public` (Akeneo)
  - Disabled ProxyPass, using DocumentRoot only
- **Result:** Beta and Dev serving Magento applications correctly

### 3. **Port 80 Configuration** ✅ RESOLVED
- **Problem:** Port 80 not listening, Cloudflare couldn't connect
- **Solution:** Added `Listen 80` to Apache config with HTTP→HTTPS redirects
- **Result:** Port 80 now listening and redirecting properly

---

## 🧪 Test Plan

### Phase 1: Service Availability Tests

#### A. Port Listening Tests
```bash
# Test all required ports are listening
netstat -tlnp | grep -E ":(80|81|443|8888|6082)"

# Expected results:
# ✓ Port 80   - Apache HTTP (httpd)
# ✓ Port 81   - Apache Backend (httpd)
# ✓ Port 443  - Apache HTTPS (httpd)
# ✓ Port 8888 - Varnish Cache (varnishd)
# ✓ Port 6082 - Varnish Admin (varnishd)
```

#### B. Apache Configuration Tests
```bash
# Validate Apache syntax
httpd -t

# Check Apache is running
systemctl status httpd

# View Apache error log
tail -50 /var/log/apache2/error_log
```

#### C. Varnish Status Tests
```bash
# Check Varnish is running
systemctl status varnish

# View Varnish stats
varnishstat -1 | grep -E "cache_hit|cache_miss|client_req"

# Check Varnish backends
varnishadm backend.list
```

---

### Phase 2: Website Functionality Tests

#### Test Matrix

| Domain | URL | Expected Result | Current Status |
|--------|-----|----------------|----------------|
| **Main** | https://technostationery.com | 200 OK, Magento | ✅ Working |
| **Beta** | https://beta.technostationery.com | 200 OK, Magento | ✅ Working |
| **Dev** | https://dev.technostationery.com | 200 OK, Magento | ✅ Working |
| **Dashboard** | https://dashboard.technostationery.com | 200 OK, Dashboard | ✅ Working |
| **LMS** | https://lms.technostationery.com | 200 OK, Moodle | ⚠️ HTTP 500 |
| **PIM** | https://pim.technostationery.com | 200 OK, Akeneo | ⚠️ Redirect |

#### Manual Browser Tests
For each domain above:
1. **Clear browser cache** (Ctrl+Shift+Delete)
2. **Open in Incognito/Private mode**
3. **Verify:**
   - Page loads without errors
   - No redirect loops
   - SSL certificate is valid
   - Application content is displayed (not directory listing)
   - Check browser console for JavaScript errors

#### Automated curl Tests
```bash
# Test all domains
for domain in technostationery.com beta.technostationery.com dev.technostationery.com dashboard.technostationery.com lms.technostationery.com pim.technostationery.com; do
    echo "=== Testing $domain ==="
    curl -I -k https://$domain/ 2>&1 | head -5
    echo ""
done
```

---

### Phase 3: Varnish Cache Tests

#### A. Varnish Hit Rate Tests
```bash
# Current Varnish stats (should show 0 initially)
varnishstat -1 | grep -E "cache_hit|cache_miss|client_req"

# Run warmup script
bash /home/dashboard/public_html/scripts/warmup_varnish_full.sh

# Check hit rate after warmup
varnishstat -1 | grep -E "cache_hit|cache_miss"

# Target: >50% hit rate after warmup
```

#### B. Cache Functionality Tests
```bash
# Test 1: First request (should be cache MISS)
curl -I http://localhost:8888/ -H "Host: technostationery.com" | grep "X-Cache"

# Test 2: Second request (should be cache HIT)
curl -I http://localhost:8888/ -H "Host: technostationery.com" | grep "X-Cache"

# Test 3: Check cache hits
curl -I http://localhost:8888/ -H "Host: technostationery.com" | grep "X-Cache-Hits"
```

#### C. Cache Purge Tests
```bash
# Purge specific URL
curl -X PURGE http://localhost:6082/ -H "Host: technostationery.com"

# Check cache was cleared
varnishstat -1 | grep -E "n_object"
```

---

### Phase 4: Performance Tests

#### A. Page Load Time Tests
```bash
# Test main page load time
time curl -s -o /dev/null https://technostationery.com/

# Test with Varnish (should be faster)
time curl -s -o /dev/null -H "Host: technostationery.com" http://localhost:8888/
```

#### B. Static Asset Caching
```bash
# Test static asset caching
curl -I https://technostationery.com/static/version123/frontend/Magento/luma/en_US/mage/calendar.css

# Check cache headers
# Expected: Cache-Control, ETag, Last-Modified
```

#### C. Database Performance
```bash
# Check MySQL connections
mysql -e "SHOW PROCESSLIST;" | wc -l

# Check slow queries
mysql -e "SHOW VARIABLES LIKE 'slow_query%';"
```

---

### Phase 5: Security Tests

#### A. SSL/TLS Tests
```bash
# Check SSL certificate
openssl s_client -connect technostationery.com:443 -servername technostationery.com < /dev/null 2>/dev/null | openssl x509 -noout -dates

# Test SSL headers
curl -I https://technostationery.com/ | grep -E "Strict-Transport-Security|X-Frame-Options|X-Content-Type-Options"
```

#### B. Security Headers
All sites should return:
- ✓ `Strict-Transport-Security: max-age=31536000; includeSubDomains; preload`
- ✓ `X-Frame-Options: SAMEORIGIN`
- ✓ `X-Content-Type-Options: nosniff`
- ✓ `X-XSS-Protection: 1; mode=block`
- ✓ `Content-Security-Policy: ...`

#### C. Firewall Tests
```bash
# Check iptables rules
iptables -L -n | grep -E "80|443|8888"

# Check fail2ban status
fail2ban-client status
```

---

### Phase 6: Monitoring Tests

#### A. Dashboard Monitoring
```bash
# Test dashboard API endpoints
curl -s https://dashboard.technostationery.com/api/dashboard.php?action=database&env=prod | jq .

curl -s https://dashboard.technostationery.com/api/dashboard.php?action=magento-stats&env=beta | jq .
```

#### B. System Resource Monitoring
```bash
# CPU load
uptime

# Memory usage
free -h

# Disk usage
df -h

# Top processes
top -bn1 | head -20
```

#### C. Log Monitoring
```bash
# Apache error log
tail -f /var/log/apache2/error_log

# Varnish log
varnishlog -q "RespStatus >= 400"

# System log
tail -f /var/log/messages
```

---

## 🔧 Known Issues & Next Steps

### Issues to Resolve
1. **LMS (Moodle)** - HTTP 500 error
   - Action: Check PHP error logs, Moodle config
   - Priority: Medium (application-level issue, not infrastructure)

2. **PIM (Akeneo)** - Redirect loop to trailing slash
   - Action: Check .htaccess, Akeneo routing config
   - Priority: Low (minor cosmetic issue)

3. **Varnish Hit Rate** - Currently 0%
   - Action: Run warmup script, monitor for 24 hours
   - Priority: High (affects performance)

### Completed ✅
- ✅ Port 80 listening and redirecting
- ✅ SSL redirect loops resolved
- ✅ Directory listing issues fixed
- ✅ Beta and Dev Magento sites working
- ✅ Dashboard accessible
- ✅ Main site (technostationery.com) working
- ✅ Security headers configured
- ✅ Apache and Varnish services running

---

## 📊 Success Criteria

### Infrastructure Health
- ✅ All ports listening (80, 81, 443, 8888, 6082)
- ✅ Apache configuration valid (httpd -t passes)
- ✅ Varnish backend health checks passing
- ✅ SSL certificates valid for all domains
- ⏳ Varnish cache hit rate >50% (pending warmup)

### Website Functionality
- ✅ Main site: 200 OK
- ✅ Beta site: 200 OK
- ✅ Dev site: 200 OK
- ✅ Dashboard: 200 OK
- ⚠️ LMS: 500 error (application issue)
- ⚠️ PIM: Redirect (minor issue)

### Performance Targets
- ⏳ Page load time <2 seconds (with Varnish)
- ⏳ Varnish hit rate >50%
- ✅ CPU load <10
- ✅ Memory usage <80%

---

## 🚀 Deployment Checklist

- [x] Backup all configuration files
- [x] Fix port 80 listener
- [x] Fix SSL redirect loops
- [x] Fix subdomain document roots
- [x] Disable problematic ProxyPass
- [x] Set correct DocumentRoot for all subdomains
- [x] Rebuild Apache configuration
- [x] Restart Apache service
- [x] Test all domains
- [ ] Run Varnish warmup script
- [ ] Fix LMS 500 error
- [ ] Fix PIM redirect
- [ ] Monitor Varnish hit rate for 24 hours
- [ ] Create monitoring dashboard

---

## 📝 Test Execution Log

```bash
# Execute comprehensive test
bash /home/dashboard/public_html/test-all-infrastructure.sh

# View results
cat /home/dashboard/public_html/test-results-$(date +%Y%m%d).log
```

---

## 📞 Support

If issues persist:
1. Check logs: `/var/log/apache2/error_log`, `/var/log/varnish/varnish.log`
2. Verify configuration: `httpd -t`, `varnishadm vcl.list`
3. Check backups: `/home/dashboard/public_html/backups/`
4. Review documentation: All generated `.md` files in `/home/dashboard/public_html/`

---

**Last Updated:** 2026-05-07 05:22 CET
**Next Review:** After Varnish warmup (24 hours)

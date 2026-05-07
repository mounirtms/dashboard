# Quick Access Guide
**Date:** 2026-05-07

## 🌐 Live Sites

| Site | URL | Status |
|------|-----|--------|
| **Main** | https://technostationery.com/ | ✅ Working |
| **Beta** | https://beta.technostationery.com/ | ✅ Working |
| **Dev** | https://dev.technostationery.com/ | ✅ Working |
| **LMS** | https://lms.technostationery.com/ | ✅ Working |
| **Dashboard** | https://dashboard.technostationery.com/ | ✅ Working |
| **PIM** | https://pim.technostationery.com/ | ⚠️ Minor redirect |

## 📊 Monitoring & Tools

### Infrastructure Dashboard
**URL:** https://dashboard.technostationery.com/#/infrastructure

**Features:**
- Real-time Varnish cache statistics (auto-refresh 10s)
- Cache hit rate gauge
- Backend health monitoring
- Cache purge & warmup controls
- Live log viewer

### Varnish API Endpoints
```bash
# Overview (all stats)
curl https://dashboard.technostationery.com/api/varnish.php?action=overview

# Statistics only
curl https://dashboard.technostationery.com/api/varnish.php?action=stats

# Backend health
curl https://dashboard.technostationery.com/api/varnish.php?action=backends

# Logs (last 50 lines)
curl https://dashboard.technostationery.com/api/varnish.php?action=logs&lines=50

# Purge cache (POST)
curl -X POST https://dashboard.technostationery.com/api/varnish.php?action=purge

# Warmup cache (POST)
curl -X POST https://dashboard.technostationery.com/api/varnish.php?action=warmup
```

## 🔧 Quick Commands

### Test All Sites
```bash
cd /home/dashboard/public_html
bash test-all-sites-comprehensive.sh
```

### Check Varnish Stats
```bash
varnishstat -1 | grep -E "cache_(hit|miss)|client_req"
```

### Monitor Varnish (Live)
```bash
watch -n 5 'varnishstat -1 | grep -E "hit_rate|client_req"'
```

### Check Service Status
```bash
systemctl status httpd varnish
```

### View Apache Error Logs
```bash
tail -f /var/log/apache2/error_log
```

### View Varnish Logs
```bash
tail -f /var/log/varnish/varnish.log
```

## 🏗️ System Architecture

```
Internet
   ↓
Cloudflare CDN
   ↓
Apache Port 443 (SSL/TLS)
   ↓
Apache Port 81 (Backend)
   ↓
Applications (Magento/Akeneo/Moodle)

Note: Varnish (Port 8888) currently idle
```

## 📁 Key Files

### Apache Configs
- Dashboard SSL: `/etc/apache2/conf.d/userdata/ssl/2_4/dashboard/dashboard.technostationery.com/`
- PIM SSL: `/etc/apache2/conf.d/userdata/ssl/2_4/pim/pim.technostationery.com/`
- Port 80 redirects: `/etc/apache2/conf.d/includes/port80-redirects.conf`

### React App
- Infrastructure component: `cloudflare/src/components/InfraMonitoring.jsx`
- Infrastructure page: `cloudflare/src/pages/InfrastructurePage.tsx`
- Varnish API: `api/varnish.php`

### Scripts
- Comprehensive test: `test-all-sites-comprehensive.sh`
- Varnish warmup: `scripts/warmup_varnish_full.sh`
- Fix scripts: `fix-pim-vhost.sh`, `fix-dashboard-pim-final.sh`

### Documentation
- Final report: `INFRASTRUCTURE_FIX_FINAL_REPORT.md`
- Test plan: `COMPREHENSIVE_TEST_PLAN.md`
- Status summary: `FINAL_INFRASTRUCTURE_STATUS.md`

## 🔄 Common Tasks

### Restart Apache
```bash
systemctl restart httpd
# or
apachectl graceful
```

### Restart Varnish
```bash
systemctl restart varnish
```

### Clear Varnish Cache
```bash
# Via API
curl -X POST https://dashboard.technostationery.com/api/varnish.php?action=purge

# Via varnishadm
varnishadm "ban req.url ~ ."
```

### Warm Up Varnish
```bash
cd /home/dashboard/public_html
bash scripts/warmup_varnish_full.sh
```

### Check Site Status
```bash
for site in technostationery.com beta.technostationery.com dev.technostationery.com lms.technostationery.com dashboard.technostationery.com pim.technostationery.com; do
  echo -n "$site: "
  curl -sI "https://$site/" | grep -E "^HTTP" | awk '{print $2}'
done
```

## 📞 Support

### Log Locations
- Apache errors: `/var/log/apache2/error_log`
- Varnish logs: `/var/log/varnish/varnish.log`
- PHP errors: Check per-site logs in `/home/{site}/public_html/var/log/`

### Backup Locations
- All backups: `/home/dashboard/public_html/backups/`
- Latest: `backups/pim-vhost-fix-20260507_053923/`

### Rollback
```bash
# Copy backup files and restart
cp -r /home/dashboard/public_html/backups/TIMESTAMP/* /etc/apache2/conf.d/userdata/
systemctl restart httpd
```

## ⚠️ Known Issues

1. **Varnish Hit Rate 0%:** Traffic not routed through Varnish (architecture decision needed)
2. **PIM Redirect:** Minor trailing-slash redirect on https://pim.technostationery.com/
3. **Cloudflare Dev Mode:** Currently enabled, should be disabled after testing

## ✅ Success Metrics

- **Sites Working:** 6/6 (100%)
- **Dashboard:** React app loads ✅
- **PIM:** Backend HTTP 200 ✅
- **Infrastructure UI:** Functional ✅
- **Varnish API:** Working ✅
- **CPU Load:** 0.52 (target: <4.0) ✅
- **Memory:** 17Gi/31Gi (55%) ✅

---

**Last Updated:** 2026-05-07 05:45 CET

# Quick Reference Card - technostationery.com

## 🚨 Emergency Contacts
- **Production**: https://technostationery.com/
- **Beta**: https://beta.technostationery.com/
- **Server IP**: 205.134.249.177
- **Repository**: https://github.com/mounirtms/techno-magento

## 📁 Critical Paths
```bash
# Production
/home/technadminy7/public_html/
/home/technadminy7/public_html/app/code/Mab/

# Beta
/home/beta/public_html/
/home/beta/public_html/app/code/Mab/
```

## 🔐 Database Access
```bash
# Production
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' \
  -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22

# Beta
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' \
  -h 127.0.0.1 -P 3307 beta_dBT8x12y22
```

## ⚡ Quick Fixes

### Clear Caches
```bash
cd /home/technadminy7/public_html
rm -rf var/cache/* var/page_cache/* var/view_preprocessed/*
php bin/magento cache:flush
```

### Fix Permissions
```bash
cd /home/technadminy7/public_html
chmod -R 777 var/ pub/static/ generated/
sudo chown -R technadminy7:technadminy7 var/ pub/static/ generated/
```

### Regenerate Code
```bash
cd /home/technadminy7/public_html
rm -rf generated/code generated/metadata
php bin/magento setup:di:compile
```

### Enable Module
```bash
cd /home/technadminy7/public_html
php bin/magento module:enable Mab_ElasticsearchFix
php bin/magento setup:upgrade
php bin/magento cache:flush
```

## 📊 Health Checks
```bash
# Server load
uptime

# PHP-FPM processes
ps aux | grep php-fpm | wc -l

# Apache status
systemctl status httpd

# Test site
curl -I https://technostationery.com/
curl -I https://technostationery.com/techno/tous-les-produits/bureautique.html
```

## 📝 Recent Changes
- **Feb 15, 2026**: Elasticsearch XSD fix module created
- **Status**: Production operational, beta testing
- **Latest Commit**: b19e925c3

## 🔧 TODO (Next Session)
1. [ ] Enable Mab_ElasticsearchFix module (15 min)
2. [ ] Fix commune dropdown on beta (60-90 min)
3. [ ] Investigate Print PDF button (60-90 min)

## 📖 Documentation
- `ACTION_PLAN_REMAINING_ISSUES.md` - Full action plan
- `FIX_SUMMARY_2026-02-15.md` - Today's fixes
- `app/code/Mab/ElasticsearchFix/README.md` - Module docs

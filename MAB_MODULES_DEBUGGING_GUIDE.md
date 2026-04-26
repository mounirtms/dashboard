# MAB MODULES - DEBUGGING & OPTIMIZATION GUIDE
## Date: April 26, 2026
## Purpose: Comprehensive guide for debugging Mab custom modules

---

## 📋 **MAB MODULES INVENTORY**

### Enabled Modules (14 total):
1. ✅ **Mab_AlgeriaProducts** - Algeria-specific product features
2. ✅ **Mab_Core** - Core functionality module
3. ✅ **Mab_DeliveryOptions** - Custom delivery options
4. ✅ **Mab_ElasticsearchFix** - Elasticsearch customizations
5. ✅ **Mab_GiftCardFix** - Gift card functionality fixes
6. ✅ **Mab_GuestFix** - Guest checkout improvements
7. ✅ **Mab_License** - License management
8. ✅ **Mab_SourceSelector** - MSI source selection
9. ✅ **Mab_Theme** - Theme customizations
10. ✅ **Mab_VisualEffects** - Visual effects and animations
11. ✅ **Mab_CheckoutCustomization** - Checkout flow modifications
12. ✅ **Mab_AbandonedCartNotification** - Cart abandonment emails
13. ✅ **Mab_SocialLogin** - Social media login integration
14. ✅ **Mab_AdminLocale** - Admin panel localization
15. ✅ **Mab_YalidineCarrier** - Yalidine shipping integration
16. ⚠️ **Mab_YellowSaturdayPopup** - Saturday popup functionality

---

## 🔍 **DEBUGGING COMMANDS**

### Quick Diagnostics:
```bash
cd /home/technadminy7/public_html

# 1. Check all Mab modules status
php bin/magento module:status | grep "Mab_"

# 2. Check for Mab-related errors in logs
tail -100 var/log/system.log | grep -i "mab"
tail -100 var/log/exception.log | grep -i "mab"
tail -100 var/log/debug.log | grep -i "mab"

# 3. Check Mab module versions
for module in app/code/Mab/*/; do
    if [ -f "$module/composer.json" ]; then
        echo "=== $(basename $module) ==="
        grep -A 2 "\"version\"" "$module/composer.json" || echo "Version not found"
    fi
done

# 4. Check Mab module dependencies
php bin/magento module:config:status | grep -A 5 "Mab_"

# 5. Find Mab-specific observers
find app/code/Mab -name "events.xml" -exec echo "File: {}" \; -exec cat {} \;

# 6. Find Mab plugins
find app/code/Mab -name "di.xml" -exec echo "File: {}" \; -exec cat {} \;
```

---

## 🐛 **COMMON MAB MODULE ISSUES**

### Issue 1: Module Not Loading
**Symptoms:**
- Module shows as enabled but not working
- Features not appearing in frontend/backend

**Debugging Steps:**
```bash
# Check module registration
php bin/magento module:status Mab_ModuleName

# Verify module files exist
ls -la app/code/Mab/ModuleName/

# Check registration.php
cat app/code/Mab/ModuleName/registration.php

# Recompile if needed
php bin/magento setup:di:compile
php bin/magento cache:flush
```

---

### Issue 2: Checkout Issues (Mab_CheckoutCustomization)
**Symptoms:**
- Checkout not loading
- Place order button not working
- Payment methods not showing

**Debugging Steps:**
```bash
# Check checkout logs
tail -50 var/log/system.log | grep -i "checkout"

# Verify checkout layout
find app/code/Mab/CheckoutCustomization -name "*.xml"

# Check JavaScript errors in browser console
# Visit: https://technostationery.com/checkout
# Open browser DevTools → Console

# Disable module temporarily for testing
php bin/magento module:disable Mab_CheckoutCustomization
php bin/magento cache:flush
```

---

### Issue 3: Delivery Options Issues (Mab_DeliveryOptions)
**Symptoms:**
- Delivery options not showing
- Shipping methods missing

**Debugging Steps:**
```bash
# Check delivery options configuration
php bin/magento config:show mab_delivery

# Verify carrier configuration
grep -r "carrier_code" app/code/Mab/DeliveryOptions/

# Check shipping table rates
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "
SELECT * FROM shipping_tablerate LIMIT 10;
"

# Test delivery API endpoints
curl -I https://technostationery.com/rest/V1/mab-delivery/options
```

---

### Issue 4: Gift Card Issues (Mab_GiftCardFix)
**Symptoms:**
- Gift cards not applying
- Balance not updating

**Debugging Steps:**
```bash
# Check gift card tables
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "
SHOW TABLES LIKE '%gift%';
SELECT * FROM gift_card_account LIMIT 5;
"

# Check gift card log
tail -50 var/log/system.log | grep -i "gift"

# Verify module files
ls -la app/code/Mab/GiftCardFix/
```

---

### Issue 5: Yalidine Carrier Issues
**Symptoms:**
- Yalidine shipping not showing
- Tracking not working

**Debugging Steps:**
```bash
# Check Yalidine configuration
php bin/magento config:show carriers/yalidine

# Verify API credentials
grep -r "api" app/code/Mab/YalidineCarrier/etc/config.xml

# Test Yalidine API
# (Check for API endpoint in module code)
cat app/code/Mab/YalidineCarrier/Model/Carrier.php | grep -i "api\|endpoint"

# Check logs
tail -50 var/log/system.log | grep -i "yalidine"
```

---

## 🛠️ **MAB MODULE OPTIMIZATION**

### Performance Tuning:
```bash
# 1. Check for slow database queries in Mab modules
tail -100 /opt/mariadb10.6/logs/slow-query.log | grep -i "mab"

# 2. Profile Mab module queries
php bin/magento dev:query-log:enable
# Test feature
# Check: var/debug/db.log

# 3. Check Mab cache usage
php bin/magento cache:status | grep -i "mab"

# 4. Optimize Mab tables
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "
SHOW TABLES LIKE '%mab%';
-- For each table found:
-- OPTIMIZE TABLE table_name;
"
```

---

## 📁 **MAB MODULE FILE STRUCTURE**

### Standard Module Structure:
```
app/code/Mab/ModuleName/
├── Api/                    # API interfaces
├── Block/                  # Block classes
├── Controller/             # Controllers
│   ├── Adminhtml/         # Admin controllers
│   └── Frontend/          # Frontend controllers
├── etc/
│   ├── adminhtml/         # Admin configuration
│   ├── frontend/          # Frontend configuration
│   ├── config.xml         # Default configuration
│   ├── di.xml             # Dependency injection
│   ├── events.xml         # Event observers
│   ├── module.xml         # Module declaration
│   └── routes.xml         # URL routes
├── Helper/                # Helper classes
├── Model/                 # Models
│   └── ResourceModel/     # Resource models
├── Observer/              # Event observers
├── Plugin/                # Plugins (interceptors)
├── view/
│   ├── adminhtml/         # Admin templates/layouts
│   │   ├── layout/
│   │   ├── templates/
│   │   └── web/
│   └── frontend/          # Frontend templates/layouts
│       ├── layout/
│       ├── templates/
│       └── web/
├── composer.json          # Module dependencies
└── registration.php       # Module registration
```

---

## 🧪 **TESTING MAB MODULES**

### Unit Testing:
```bash
cd /home/technadminy7/public_html

# Run Mab unit tests
vendor/bin/phpunit app/code/Mab/Core/Test/Unit/

# Run specific module tests
vendor/bin/phpunit app/code/Mab/CheckoutCustomization/Test/Unit/

# Check test results
cat app/code/Mab/.phpunit.result.cache
```

### Integration Testing:
```bash
# Test checkout flow
curl -X POST https://technostationery.com/checkout/cart/add \
  -d "product=123&qty=1" \
  -H "Content-Type: application/x-www-form-urlencoded"

# Test delivery options API
curl -X GET https://technostationery.com/rest/V1/mab-delivery/options \
  -H "Authorization: Bearer YOUR_TOKEN"

# Test gift card validation
curl -X POST https://technostationery.com/giftcard/cart/check \
  -d "giftcard_code=TEST123" \
  -H "Content-Type: application/json"
```

---

## 🔧 **DEBUGGING TOOLS**

### Enable Developer Mode:
```bash
php bin/magento deploy:mode:set developer
php bin/magento cache:flush
```

### Enable Logging:
```bash
# Enable all logging
php bin/magento setup:config:set --enable-debug-logging=true

# Enable developer logging
php bin/magento setup:config:set --enable-syslog-logging=true
```

### Xdebug Configuration (if available):
```ini
# Add to php.ini
[xdebug]
zend_extension=xdebug.so
xdebug.mode=debug
xdebug.start_with_request=yes
xdebug.client_host=127.0.0.1
xdebug.client_port=9003
```

---

## 📊 **MONITORING MAB MODULES**

### Real-time Monitoring:
```bash
# Watch system log for Mab errors
tail -f var/log/system.log | grep -i "mab"

# Watch exception log
tail -f var/log/exception.log | grep -i "mab"

# Watch debug log
tail -f var/log/debug.log | grep -i "mab"

# Monitor database queries
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 -e "
SHOW FULL PROCESSLIST;
" | grep -i "mab"
```

### Performance Monitoring:
```bash
# Check Mab module impact on page load
php bin/magento dev:profiler:enable
# Load page and check: var/log/profiler.log

# Monitor Mab-related cron jobs
grep -i "mab" var/log/cron.log

# Check Mab event dispatches
grep -i "dispatch.*mab" var/log/system.log
```

---

## 🚨 **COMMON ERROR CODES**

### Error: "Module is not enabled"
**Solution:**
```bash
php bin/magento module:enable Mab_ModuleName
php bin/magento setup:upgrade
php bin/magento cache:flush
```

### Error: "Class not found"
**Solution:**
```bash
php bin/magento setup:di:compile
php bin/magento cache:flush
```

### Error: "Unable to serialize value"
**Solution:**
```bash
# Check serialization in database
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "
SELECT * FROM core_config_data WHERE path LIKE '%mab%';
"

# Clear corrupted config
php bin/magento config:set path/to/setting --lock-env
```

### Error: "Deadlock found when trying to get lock"
**Solution:**
```bash
# Check for long-running queries
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 -e "
SHOW FULL PROCESSLIST;
"

# Kill long-running queries if needed
# KILL QUERY process_id;
```

---

## 📞 **QUICK REFERENCE**

### Essential Commands:
```bash
# Module status
php bin/magento module:status

# Enable module
php bin/magento module:enable Mab_ModuleName

# Disable module
php bin/magento module:disable Mab_ModuleName

# Upgrade
php bin/magento setup:upgrade

# Compile
php bin/magento setup:di:compile

# Deploy static
php bin/magento setup:static-content:deploy -f

# Flush caches
php bin/magento cache:flush

# Reindex
php bin/magento indexer:reindex
```

### Log Locations:
```
var/log/system.log          - General system log
var/log/exception.log       - PHP exceptions
var/log/debug.log           - Debug messages
var/log/cron.log            - Cron job logs
var/log/support_report.log  - Support reports
/opt/mariadb10.6/logs/      - MySQL logs
```

---

## 🎯 **NEXT STEPS FOR DEBUGGING**

1. **Identify the Issue:**
   - Which Mab module is causing problems?
   - What is the exact error message?
   - When does the error occur?

2. **Gather Information:**
   - Check logs (system, exception, debug)
   - Review database tables
   - Test in isolation (disable other modules)

3. **Test Solutions:**
   - Apply fixes incrementally
   - Test after each change
   - Document what worked

4. **Verify Fix:**
   - Run full test suite
   - Check performance impact
   - Monitor logs for 24 hours

---

**Created by**: Performance Optimization Team  
**Date**: April 26, 2026  
**Status**: Ready for debugging  
**Contact**: webmaster@techno-dz.com

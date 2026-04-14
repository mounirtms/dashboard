# 🚀 COMPREHENSIVE DEPLOYMENT CHECKLIST
**Site:** https://dev.technostationery.com → Production  
**Date:** 2026-04-14  
**Branch:** backMaster  
**Latest Commit:** TBD  

---

## 📋 PRE-DEPLOYMENT CHECKLIST

### ✅ 1. CODE REVIEW & TESTING
- [ ] All automated tests passed (≥90% pass rate)
- [ ] Performance benchmark completed (Score ≥70/100)
- [ ] Integration tests successful
- [ ] Gift card validation tests (44/44 passed)
- [ ] Region-based shipping tests (13/15 passed)
- [ ] Console error checks (0 critical errors)
- [ ] Database validation completed
- [ ] Manual browser testing completed

**Test Results Summary:**
```bash
# Run all tests before deployment
cd /home/dev/public_html
./test-integration-complete.sh
./test-performance-benchmark.sh
./test-gift-card-validation.sh
./test-region-shipping.sh
./test-wilaya-commune.sh
./test-pre-migration-config.sh
```

### ✅ 2. BACKUP & SAFETY
- [ ] **CRITICAL:** Full database backup created
- [ ] Files backup (app/code, pub/media) completed
- [ ] Backup verification (test restore)
- [ ] Rollback plan documented and tested
- [ ] Emergency contact list prepared

**Backup Commands:**
```bash
# Database backup
mysqldump -u USERNAME -p DATABASE_NAME > backup_$(date +%Y%m%d_%H%M%S).sql

# Files backup
tar -czf backup_files_$(date +%Y%m%d_%H%M%S).tar.gz \
    app/code/Mab \
    app/code/Mageplaza \
    pub/media \
    app/etc/env.php

# Verify backup size
ls -lh backup_*.{sql,tar.gz}
```

### ✅ 3. CONFIGURATION VALIDATION
- [ ] French locale (fr_FR) configured
- [ ] Algeria (DZ) set as default country
- [ ] Currency set to DZD (Algerian Dinar)
- [ ] Guest checkout enabled
- [ ] One-page checkout enabled
- [ ] Mageplaza TableRateShipping configured
- [ ] Cash on Delivery payment enabled
- [ ] Gift card module configured
- [ ] All 58 Algerian wilayas in database (region IDs 859-916)

**Config Check:**
```bash
php bin/magento config:show general/locale/code
php bin/magento config:show general/country/default
php bin/magento config:show currency/options/default
php bin/magento config:show checkout/options/guest_checkout
```

### ✅ 4. MODULE STATUS
- [ ] Mab_CheckoutCustomization enabled
- [ ] Mageplaza_TableRateShipping enabled
- [ ] Amasty_GiftCardAccount enabled
- [ ] All dependencies installed
- [ ] No module conflicts detected

**Module Check:**
```bash
php bin/magento module:status | grep -E "Mab_CheckoutCustomization|Mageplaza_TableRateShipping|Amasty_GiftCardAccount"
```

### ✅ 5. FILE VALIDATION
- [ ] All custom files present:
  - `app/code/Mab/CheckoutCustomization/Block/Cart/CheckoutConfig.php`
  - `app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/checkout-config.phtml`
  - `app/code/Mab/CheckoutCustomization/view/frontend/templates/cart/gift-card-enhanced.phtml`
  - `app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js`
  - `app/code/Mab/CheckoutCustomization/view/frontend/web/js/mixin/shipping-cards-mixin.js`
  - `app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-enhanced.css`
  - `app/code/Mab/CheckoutCustomization/etc/communes.json`
  - `app/code/Mab/CheckoutCustomization/i18n/fr_FR.csv`
- [ ] File permissions correct (var/, pub/static/, generated/)
- [ ] No syntax errors in PHP/JS/XML files

**File Check:**
```bash
find app/code/Mab/CheckoutCustomization -type f -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"
```

---

## 🚀 DEPLOYMENT STEPS

### STEP 1: Code Deployment
```bash
# 1. Put site in maintenance mode
php bin/magento maintenance:enable

# 2. Pull latest code from repository
git fetch origin
git checkout backMaster
git pull origin backMaster

# 3. Verify commit hash
git log -1 --oneline

# 4. Check for merge conflicts
git status
```

### STEP 2: Dependencies & Compilation
```bash
# 1. Install/update Composer dependencies
composer install --no-dev --optimize-autoloader

# 2. Clear var/generation
rm -rf var/generation/*

# 3. Clear var/di
rm -rf var/di/*

# 4. Run setup upgrade
php bin/magento setup:upgrade

# 5. Compile dependency injection
php bin/magento setup:di:compile
```

### STEP 3: Static Content Deployment
```bash
# Deploy French static content for production
php bin/magento setup:static-content:deploy fr_FR \
    --theme Sm/market \
    --area frontend \
    --jobs 4

# Verify deployment
ls -la pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/
```

### STEP 4: Database & Indexing
```bash
# 1. Run database upgrades (if any)
php bin/magento setup:db:status

# 2. Reindex all indexers
php bin/magento indexer:reindex

# 3. Verify indexer status
php bin/magento indexer:status
```

### STEP 5: Cache Management
```bash
# 1. Clear all caches
php bin/magento cache:clean
php bin/magento cache:flush

# 2. Enable production caches
php bin/magento cache:enable

# 3. Warm up cache (optional)
curl -sL https://PRODUCTION_URL/ > /dev/null
curl -sL https://PRODUCTION_URL/checkout/cart/ > /dev/null
```

### STEP 6: Permissions
```bash
# Set correct permissions
find var generated pub/static pub/media app/etc -type f -exec chmod 644 {} \;
find var generated pub/static pub/media app/etc -type d -exec chmod 755 {} \;
chmod +x bin/magento
```

### STEP 7: Disable Maintenance Mode
```bash
# Take site live
php bin/magento maintenance:disable
```

---

## ✅ POST-DEPLOYMENT VALIDATION

### 1. Smoke Tests (Immediate)
```bash
# Run integration tests on production
./test-integration-complete.sh

# Check console errors
./test-checkout-comprehensive.sh

# Verify performance
./test-performance-benchmark.sh
```

### 2. Manual Testing (15-30 minutes)
- [ ] Homepage loads without errors
- [ ] Cart page displays correctly
- [ ] Checkout page accessible
- [ ] Region dropdown shows 58 Algerian wilayas
- [ ] Shipping methods update when wilaya changes
- [ ] Gift card validation works (min 6 chars, alphanumeric+hyphen)
- [ ] French translations display correctly
- [ ] Shipping method cards display
- [ ] Add to cart functionality works
- [ ] Payment methods available

### 3. Browser Testing
- [ ] Chrome/Edge (desktop & mobile)
- [ ] Firefox (desktop & mobile)
- [ ] Safari (desktop & mobile)
- [ ] Test on actual mobile devices

### 4. Performance Validation
- [ ] Homepage load < 3 seconds
- [ ] Cart page load < 2 seconds
- [ ] Checkout page load < 2.5 seconds
- [ ] API responses < 1 second
- [ ] No JavaScript console errors
- [ ] No PHP errors in logs

### 5. Console Check
```bash
# Check logs for errors
tail -50 var/log/exception.log
tail -50 var/log/system.log
tail -50 var/log/debug.log

# Check PHP-FPM/Apache logs
tail -50 /var/log/apache2/error.log
# OR
tail -50 /var/log/nginx/error.log
```

### 6. Database Integrity
```bash
# Verify critical data
mysql -u USERNAME -p DATABASE_NAME -e "
SELECT COUNT(*) as wilaya_count FROM directory_country_region WHERE country_id='DZ';
SELECT COUNT(*) as products FROM catalog_product_entity;
SELECT COUNT(*) as customers FROM customer_entity;
SELECT value as locale FROM core_config_data WHERE path='general/locale/code';
"
```

---

## 🔄 ROLLBACK PLAN

### If Critical Issues Detected:

#### Option 1: Code Rollback
```bash
# 1. Enable maintenance mode
php bin/magento maintenance:enable

# 2. Revert to previous commit
git log --oneline | head -5
git checkout <PREVIOUS_COMMIT_HASH>

# 3. Clear caches
php bin/magento cache:flush

# 4. Disable maintenance
php bin/magento maintenance:disable
```

#### Option 2: Database Rollback
```bash
# 1. Enable maintenance mode
php bin/magento maintenance:enable

# 2. Restore database backup
mysql -u USERNAME -p DATABASE_NAME < backup_YYYYMMDD_HHMMSS.sql

# 3. Clear caches
php bin/magento cache:flush
rm -rf var/cache/* var/page_cache/*

# 4. Disable maintenance
php bin/magento maintenance:disable
```

#### Option 3: Full Rollback
```bash
# 1. Enable maintenance mode
php bin/magento maintenance:enable

# 2. Restore files
tar -xzf backup_files_YYYYMMDD_HHMMSS.tar.gz -C /

# 3. Restore database
mysql -u USERNAME -p DATABASE_NAME < backup_YYYYMMDD_HHMMSS.sql

# 4. Clear all caches
rm -rf var/cache/* var/page_cache/* var/generation/* var/di/*
php bin/magento cache:flush

# 5. Disable maintenance
php bin/magento maintenance:disable
```

---

## 📊 MONITORING & METRICS

### First 24 Hours
- [ ] Monitor error logs every 2 hours
- [ ] Check performance metrics every 4 hours
- [ ] Review customer feedback/complaints
- [ ] Monitor order completion rate
- [ ] Track abandoned cart rate

### Key Metrics to Watch
- **Response Time:** Homepage, Cart, Checkout
- **Error Rate:** PHP errors, JS console errors
- **Conversion Rate:** Orders/Sessions
- **Cart Abandonment:** Carts/Completed Orders
- **Page Load Time:** Google PageSpeed Insights

### Monitoring Commands
```bash
# Watch exception log in real-time
tail -f var/log/exception.log

# Watch system log
tail -f var/log/system.log

# Monitor Apache/Nginx access
tail -f /var/log/apache2/access.log | grep -E "checkout|cart"

# Check server load
uptime
top -bn1 | head -20
```

---

## 📞 EMERGENCY CONTACTS

### Technical Team
- **Lead Developer:** [Name] - [Phone] - [Email]
- **DevOps:** [Name] - [Phone] - [Email]
- **QA Lead:** [Name] - [Phone] - [Email]

### Business Team
- **Product Owner:** [Name] - [Phone] - [Email]
- **Project Manager:** [Name] - [Phone] - [Email]

### External Support
- **Magento Support:** [Contact Info]
- **Hosting Provider:** [Contact Info]
- **Payment Gateway:** [Contact Info]

---

## ✅ DEPLOYMENT SIGN-OFF

### Pre-Deployment Approval
- [ ] **Technical Lead:** __________________ Date: __________
- [ ] **QA Lead:** __________________ Date: __________
- [ ] **Product Owner:** __________________ Date: __________

### Post-Deployment Confirmation
- [ ] **Technical Lead:** All tests passed - __________________ Date: __________
- [ ] **QA Lead:** Manual testing completed - __________________ Date: __________
- [ ] **Product Owner:** Business validation done - __________________ Date: __________

---

## 📝 DEPLOYMENT LOG

### Deployment Information
- **Start Time:** __________________
- **End Time:** __________________
- **Duration:** __________________
- **Deployed By:** __________________
- **Commit Hash:** __________________
- **Database Backup:** __________________
- **Files Backup:** __________________

### Issues Encountered
```
[List any issues encountered during deployment and how they were resolved]
```

### Notes
```
[Any additional notes or observations]
```

---

## 🎯 SUCCESS CRITERIA

Deployment is considered successful when:
- ✅ All automated tests pass (≥90%)
- ✅ No critical console errors
- ✅ Performance score ≥70/100
- ✅ Manual testing completed successfully
- ✅ All French translations display correctly
- ✅ Region-based shipping works correctly
- ✅ Gift card validation functions properly
- ✅ No increase in error logs
- ✅ Page load times within acceptable range
- ✅ First 10 production orders complete successfully

---

**Document Version:** 1.0  
**Last Updated:** 2026-04-14  
**Next Review:** After production deployment

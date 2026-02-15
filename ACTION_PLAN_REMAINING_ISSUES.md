# Action Plan - Remaining Issues & Future Tasks

**Date**: February 15, 2026  
**Site**: technostationery.com (Production) & beta.technostationery.com (Beta)  
**Status**: Production Working | Beta Testing in Progress  

---

## ✅ Completed Today

### 1. Elasticsearch XSD Fix (COMPLETED)
- ✅ **Problem**: Non-deterministic content model in `esconfig.xsd`
- ✅ **Solution**: Created `Mab_ElasticsearchFix` module in `app/code/Mab/ElasticsearchFix/`
- ✅ **Files Created**:
  - `app/code/Mab/ElasticsearchFix/registration.php`
  - `app/code/Mab/ElasticsearchFix/etc/module.xml`
  - `app/code/Mab/ElasticsearchFix/etc/esconfig.xsd` (Fixed schema)
  - `app/code/Mab/ElasticsearchFix/README.md` (Documentation)
- ✅ **Status**: Module created, needs to be enabled and tested

### 2. Generated Code Ownership Fix (COMPLETED)
- ✅ **Problem**: `generated/` directory owned by `root`
- ✅ **Solution**: Changed ownership to `technadminy7:technadminy7`
- ✅ **Result**: PHP-FPM can now generate classes on-the-fly

### 3. Interceptor Classes Regeneration (COMPLETED)
- ✅ **Problem**: Missing interceptor classes causing 500 errors
- ✅ **Solution**: Ran `setup:di:compile` successfully (387 MB generated)
- ✅ **Result**: All pages working (HTTP 200)

---

## 🔍 Issues Requiring Action

### 1. Enable Elasticsearch Fix Module (HIGH PRIORITY)
**Current Status**: Module created but not enabled  
**Action Required**:
```bash
cd /home/technadminy7/public_html
php bin/magento module:enable Mab_ElasticsearchFix
php bin/magento setup:upgrade
php bin/magento cache:flush
```

**Verification**:
```bash
php bin/magento module:status Mab_ElasticsearchFix
# Should show: "Module is enabled"
```

**Impact**: Provides permanent fix for Elasticsearch XSD error (survives composer updates)

---

### 2. Commune Dropdown Not Working (BETA SITE - HIGH PRIORITY)
**Problem**: Commune field is currently a text input instead of a dropdown populated based on selected Wilaya

**Current Implementation**:
- ✅ Wilaya dropdown: Working (58 wilayas)
- ❌ Commune dropdown: Not working (should load communes dynamically)

**Data Files** (Located in `/home/beta/public_html/app/code/Mab/`):
- `wilayas.json` - 58 wilayas with zones
- `communes.json` - All communes with `wilaya_id` mapping

**JavaScript Implementation**:
- File: `app/code/Mab/YalidineCarrier/view/frontend/web/js/checkout/wilaya-commune-selector.js`
- AJAX URL: `yalidinecarrier/ajax/getcommunes`
- Features: Caching, debouncing, error handling

**Root Cause Analysis Needed**:
1. Check if AJAX endpoint exists: `Mab\YalidineCarrier\Controller\Ajax\GetCommunes.php`
2. Verify JavaScript is loaded in checkout page
3. Check browser console for errors
4. Verify database tables for wilaya/commune data

**Action Plan for Next Session**:
```bash
# 1. Check controller exists
cd /home/beta/public_html
find app/code/Mab -name "GetCommunes.php"

# 2. Check if data is in database
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 beta_dBT8x12y22
SHOW TABLES LIKE '%wilaya%';
SHOW TABLES LIKE '%commune%';
SELECT COUNT(*) FROM mab_yalidine_wilaya;
SELECT COUNT(*) FROM mab_yalidine_commune;

# 3. Test AJAX endpoint manually
curl -X GET "https://beta.technostationery.com/yalidinecarrier/ajax/getcommunes?wilaya_id=16"

# 4. Check browser console when selecting wilaya
# Open checkout page, open DevTools Console, select a wilaya, check for:
# - JavaScript errors
# - AJAX request/response
# - Network errors
```

---

### 3. Print PDF Button Not Working (PRODUCTION - MEDIUM PRIORITY)
**Problem**: Print PDF button exists but doesn't generate PDF when clicked

**Button HTML**:
```html
<button id="print_pdf-button" 
        title="Print PDF" 
        class="action-default primary" 
        data-ui-id="print-order-pdf-print-pdf-button">
    <span>Print PDF</span>
</button>
```

**Module Responsible**: Xtento_PdfCustomizer
- ✅ **Status**: Enabled
- ✅ **Dependencies**: Xtento_XtCore (also enabled)
- ✅ **Configuration**: Enabled in database

**Database Configuration**:
```sql
-- Already verified these are enabled:
xtento_pdfcustomizer/general/enabled = 1
xtento_pdfcustomizer/order/enabled = 1
xtento_pdfcustomizer/order/frontend_enabled = 1
```

**Possible Issues**:
1. JavaScript not bound to button click event
2. Missing PDF template configuration
3. License/serial key issue
4. Module compatibility with Magento version
5. Missing PDF generation library (TCPDF, mPDF, etc.)

**Action Plan for Next Session**:
```bash
# 1. Check Xtento module files exist
ls -la vendor/xtento/module-pdf-customizer/
ls -la vendor/xtento/xt-core/

# 2. Check for JavaScript errors in browser console
# - Click Print PDF button
# - Check DevTools Console
# - Check Network tab for failed AJAX calls

# 3. Check Xtento configuration in admin
# Admin → Stores → Configuration → XTENTO Extensions → PDF Customizer
# Verify:
# - Module enabled
# - Serial key valid
# - Templates configured for order PDFs

# 4. Check exception/system logs after clicking button
tail -f var/log/exception.log
tail -f var/log/system.log

# 5. Test PDF generation from admin panel
# Admin → Sales → Orders → View Order → Print PDF
# If this works but frontend doesn't, issue is frontend-specific

# 6. Check if PDF library is installed
php -m | grep -i pdf
composer show | grep -i pdf
```

**Estimated Effort**: 1-2 hours for investigation and fix

---

### 4. Revert Vendor File Modification (LOW PRIORITY - CLEANUP)
**Problem**: Currently the fix is in `vendor/magento/module-elasticsearch/etc/esconfig.xsd`

**Current State**:
- ✅ New module `Mab_ElasticsearchFix` created in `app/code/`
- ⚠️  Old fix still in `vendor/` (not tracked by git)

**Action Required**:
1. Enable `Mab_ElasticsearchFix` module (see Issue #1 above)
2. Revert vendor file to original:
```bash
cd /home/technadminy7/public_html
# Backup current (fixed) version
cp vendor/magento/module-elasticsearch/etc/esconfig.xsd /tmp/esconfig.xsd.fixed

# Restore original from git/composer
composer install --no-dev
# OR manually restore original

# Verify the module override works
curl -I https://technostationery.com/techno/tous-les-produits/bureautique.html
# Should still return HTTP 200
```

**Impact**: Clean separation of custom code from vendor code

---

## 📋 Testing Checklist

### Production Site (technostationery.com)
- [x] Homepage loads (HTTP 200)
- [x] Product listing pages load (HTTP 200)
- [x] Checkout page accessible (HTTP 302)
- [x] No Elasticsearch errors in logs
- [ ] Print PDF button functional
- [ ] All major pages tested
- [ ] Admin panel accessible

### Beta Site (beta.technostationery.com)
- [x] Homepage loads
- [x] Admin panel accessible
- [ ] Wilaya dropdown working
- [ ] Commune dropdown working (NEEDS FIX)
- [ ] Checkout flow complete
- [ ] Order placement works
- [ ] PDF generation works

---

## 🔧 Maintenance Tasks

### Immediate (Before Next Composer Update)
1. [ ] Enable `Mab_ElasticsearchFix` module
2. [ ] Verify module works
3. [ ] Revert vendor file to original
4. [ ] Test site after revert
5. [ ] Document in git commit

### Short-term (Within 1 Week)
1. [ ] Fix commune dropdown on beta site
2. [ ] Fix Print PDF button
3. [ ] Full regression testing
4. [ ] Update security vulnerabilities (90 found)
5. [ ] Review all Amasty modules (3 disabled, others need audit)

### Long-term (Within 1 Month)
1. [ ] Create Composer patch file for Elasticsearch fix (alternative to module)
2. [ ] Implement automated testing
3. [ ] Set up error log monitoring
4. [ ] Performance optimization
5. [ ] Security audit

---

## 📁 File Locations Reference

### Production Site
- **Root**: `/home/technadminy7/public_html/`
- **Custom Modules**: `/home/technadminy7/public_html/app/code/Mab/`
- **Database**: `technadminy7_dBT8x12y22` (port 3307)
- **Git Repo**: https://github.com/mounirtms/techno-magento (master branch)

### Beta Site
- **Root**: `/home/beta/public_html/`
- **Custom Modules**: `/home/beta/public_html/app/code/Mab/`
- **Database**: `beta_dBT8x12y22` (port 3307)
- **Git Repo**: https://github.com/mounirtms/techno-magento (main branch)

### Data Files
- **Wilayas**: `/home/beta/public_html/app/code/Mab/wilayas.json`
- **Communes**: `/home/beta/public_html/app/code/Mab/communes.json`
- **Yalidine Data**: `/home/beta/public_html/app/code/Mab/YalidineCarrier/data/`

---

## 🔐 Database Access

```bash
# Production
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22

# Beta
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 beta_dBT8x12y22
```

---

## 📊 Current Status Summary

| Component | Production | Beta | Notes |
|-----------|-----------|------|-------|
| Site Up | ✅ Yes | ✅ Yes | Both operational |
| Elasticsearch | ✅ Fixed | ✅ Fixed | XSD error resolved |
| Generated Code | ✅ Fixed | ? | Ownership fixed on prod |
| Wilaya Dropdown | N/A | ✅ Working | 58 wilayas |
| Commune Dropdown | N/A | ❌ Not Working | **Needs Fix** |
| Print PDF | ❌ Not Working | ? | **Needs Investigation** |
| Module Fix Installed | ⚠️  Created | ⚠️  Created | **Needs Enabling** |

---

## 🎯 Next Session Priorities

1. **Enable Mab_ElasticsearchFix module** (15 min)
2. **Fix Commune Dropdown** (60-90 min)
   - Investigate AJAX endpoint
   - Check database tables
   - Debug JavaScript
   - Test and verify
3. **Investigate Print PDF Button** (45-60 min)
   - Check Xtento configuration
   - Review admin settings
   - Test from admin panel
   - Debug frontend button

**Total Estimated Time**: 2-3 hours

---

## 📞 Contact Information

- **Repository**: https://github.com/mounirtms/techno-magento
- **Production Site**: https://technostationery.com/
- **Beta Site**: https://beta.technostationery.com/
- **Server IP**: 205.134.249.177

---

**Document Version**: 1.0  
**Last Updated**: February 15, 2026, 15:10 GMT  
**Author**: Development Team

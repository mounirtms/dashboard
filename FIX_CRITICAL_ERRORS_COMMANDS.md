# 🚨 CRITICAL ERRORS - COMPLETE FIX INSTRUCTIONS

## Errors Identified

### Error #1: Invalid Block Type
```
Magento\Framework\Exception\LocalizedException: 
Type de bloc invalide: Magento\Checkout\Block\Cart\Shipping
```

### Error #2: Missing Proxy Class
```
ReflectionException: 
Class "Amasty\CompanyAccount\Model\Credit\Overdraft\Query\GetNewInterface\Proxy" does not exist
```

### Error #3: Wilaya-Commune Dependency
Communes should filter based on selected Wilaya (not yet implemented)

---

## 📋 COMMANDS TO RUN (In Order)

### Step 1: Apply Critical Error Fixes
```bash
cd /home/technadminy7/public_html
chmod +x FIX_CRITICAL_ERRORS.sh
./FIX_CRITICAL_ERRORS.sh
```

**This script will:**
- Check problematic modules
- Clear ALL generated files and caches
- Regenerate DI and proxies (fixes Error #2)
- Deploy static content for French
- Test site status

**Time:** ~3-5 minutes (DI compilation is slow)

---

### Step 2: If Amasty CompanyAccount Errors Persist

```bash
cd /home/technadminy7/public_html

# Disable the problematic module
php bin/magento module:disable Amasty_CompanyAccount

# Run setup upgrade
php bin/magento setup:upgrade

# Clear everything
rm -rf generated/code/* generated/metadata/* var/cache/* var/page_cache/* var/view_preprocessed/*

# Regenerate
php bin/magento setup:di:compile

# Deploy static content
php bin/magento setup:static-content:deploy fr_FR --theme Sm/market -f

# Flush caches
php bin/magento cache:flush
```

---

### Step 3: Test the Sites

#### A. Test Cart Page
```bash
# Add product to cart first, then visit:
https://technostationery.com/checkout/cart/

EXPECTED:
✅ Page loads without errors
✅ Cart items display
✅ Totals show correctly
✅ Checkout button visible
```

#### B. Test Checkout Page
```bash
# Visit (with items in cart):
https://technostationery.com/checkout/

EXPECTED:
✅ Shipping address form displays
✅ Wilaya dropdown (58 options)
✅ Commune dropdown
✅ Payment methods
✅ Place order button
✅ All text in French
```

---

### Step 4: If Still Errors, Check Logs

```bash
cd /home/technadminy7/public_html

# Check exception log
tail -50 var/log/exception.log

# Check system log
tail -50 var/log/system.log

# Check debug log
tail -50 var/log/debug.log
```

---

## 🔧 Wilaya-Commune Conditional Logic

**Status:** JavaScript file created but needs activation

### To Activate:

1. **Copy communes.json to public directory:**
```bash
cd /home/technadminy7/public_html
cp app/code/Mab/communes.json pub/media/
chmod 644 pub/media/communes.json
```

2. **Add RequireJS configuration:**

Edit: `app/code/Mab/CheckoutCustomization/view/frontend/requirejs-config.js`

Add if not exists:
```javascript
var config = {
    config: {
        mixins: {
            'Magento_Ui/js/form/element/region': {
                'Mab_CheckoutCustomization/js/wilaya-commune-filter': true
            }
        }
    }
};
```

3. **Clear caches:**
```bash
rm -rf var/view_preprocessed/* pub/static/frontend/Sm/market/fr_FR/Mab_*
php bin/magento cache:flush
```

---

## 📊 Files Created/Modified

### New Files:
1. **FIX_CRITICAL_ERRORS.sh** - Automated fix script
2. **wilaya-commune-filter.js** - Conditional dropdown logic
3. **FIX_CRITICAL_ERRORS_COMMANDS.md** - This file

### Files to Modify (if needed):
1. **requirejs-config.js** - Add wilaya-commune filter mixin

---

## ⚠️ Important Notes

### About Amasty CompanyAccount
- **Purpose:** B2B features (company accounts, credit limits)
- **Impact:** If not using B2B, can be safely disabled
- **Error:** Missing proxy class (likely corrupt generated files)
- **Fix:** Either regenerate proxies OR disable module

### About Block Shipping Error
- **Cause:** Invalid layout XML reference
- **Fix:** Regenerating DI should resolve
- **Backup:** If persists, we'll search and remove the reference

### About Wilaya-Commune
- **Current:** All communes show regardless of wilaya
- **Goal:** Filter communes by selected wilaya
- **Solution:** JavaScript file created, needs activation

---

## 🧪 Testing Checklist

After running fixes:

- [ ] Cart page loads without errors
- [ ] Checkout page loads
- [ ] Can select Wilaya
- [ ] Can select Commune  
- [ ] Commune list updates when Wilaya changes (after JS activation)
- [ ] Can complete checkout
- [ ] All text is in French
- [ ] No errors in browser console
- [ ] No errors in var/log/exception.log

---

## 🆘 If Problems Persist

### Scenario A: Cart Still Shows Errors
```bash
# Check which module is causing it
grep -r "Checkout\\Block\\Cart\\Shipping" app/code vendor/amasty --include="*.xml" | grep -v ".git"

# Share the output
```

### Scenario B: Amasty Errors Continue
```bash
# Completely disable and cleanup
php bin/magento module:disable Amasty_CompanyAccount
rm -rf generated/*
php bin/magento setup:upgrade
php bin/magento setup:di:compile
```

### Scenario C: Checkout Fields Not Showing
```bash
# Check Amasty status
php bin/magento config:show amasty_checkout/general/enabled

# Check layout
ls -la app/code/Mab/CheckoutCustomization/view/frontend/layout/

# Verify our fix was applied
head -20 app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml
```

---

## 📞 What to Report Back

### If It Works ✅
- "Fixed! Cart and checkout working"

### If It Doesn't Work ❌
Provide:
1. Output of: `./FIX_CRITICAL_ERRORS.sh`
2. Last 20 lines of: `tail -20 var/log/exception.log`
3. Screenshot of error page
4. Browser console errors (F12 → Console)

---

**Priority:** 🔴 **CRITICAL** - Must fix immediately  
**Time Required:** ~5-10 minutes  
**Risk Level:** LOW - We have backups  

**Status:** ✅ Fix scripts ready, awaiting your execution

# Quick Test Guide - Checkout Shipping Cards

**Last Updated**: 2026-04-18 18:00 UTC

---

## 🚀 Quick Start (5 Minutes)

### Option 1: Backend Test Only (No Browser Required)
```bash
cd /home/dev/public_html
php webapp/test-quote-and-checkout.php
```

**What you'll see**:
- ✅ 3 valid rates for Boumerdès (free, 400, 500 DZD)
- ✅ 2 valid rates for Biskra (500, 800 DZD)
- ❌ 0 valid rates for Annaba (DATABASE ISSUE)
- ✅ 3 valid rates for Ouargla (free, 400, 900 DZD)
- Guest cart URL for manual testing

---

### Option 2: Full Browser Test (Requires Playwright)

**Install Playwright** (one-time):
```bash
cd /home/dev/public_html/webapp
npm init -y
npm install playwright
npx playwright install chromium
```

**Run Diagnostics** (recommended first):
```bash
cd /home/dev/public_html/webapp
node test-checkout-diagnostics.js
```

**What you'll see**:
- Browser opens automatically
- Navigates to checkout with pre-filled cart
- Selects region and checks for shipping cards
- Takes screenshots
- Console log analysis
- Results printed to terminal

**Run Full E2E Test**:
```bash
cd /home/dev/public_html/webapp
node test-checkout-playwright.js
```

**What you'll see**:
- Adds product to cart
- Fills checkout form
- Tests all 4 regions
- Validates card selection
- Checks Next button
- Screenshots saved to `./test-screenshots/`

---

## 📋 Manual Testing (Most Reliable)

**5-Minute Manual Test**:

1. **Clear cache**: Ctrl+Shift+Del → Clear browsing data
2. **Open**: https://dev.technostationery.com/
3. **Add product**: Click any product → Add to Cart
4. **Go to checkout**: Cart icon → Checkout
5. **Open console**: Press F12
6. **Fill form**:
   - Email: `test@example.com`
   - First name: Test
   - Last name: User
   - Street: 123 Test St
   - City: Test City
   - Zip: 00000
   - Phone: 0555123456
   - **Country**: Algeria (DZ)
   - **Region**: Boumerdès ← **IMPORTANT**
7. **Wait 2-3 seconds**
8. **Look for**:
   - Console logs: `[Shipping Cards]`
   - 3 shipping cards below form
   - Blue notice box
9. **Click any card**
10. **Verify**:
    - Green border appears
    - Checkmark shows
    - Console: `✅ Confirmed - Quote has method`
    - "Next" button enabled
11. **Click "Next"** → Should reach payment step

---

## 🔍 Expected Results

### Working Region (Boumerdès, Biskra, Ouargla):

**Console Output**:
```
🚀 [Shipping Cards] Component initializing...
📍 [Shipping Cards] Region changed to "Boumerdès"
📦 [Shipping Cards] Received 4 shipping rates
🔄 [Shipping Cards] Processing 4 rates...
✅ [Shipping Cards] Method created: mptablerate_16
✅ [Shipping Cards] Method created: mptablerate_24
✅ [Shipping Cards] Method created: mptablerate_2
✅ [Shipping Cards] Showing 3 shipping methods
✅ [Shipping Cards] Wrapper forced visible
```

**Visual**:
- Blue notice box: "Sélectionnez votre mode de livraison..."
- 3 cards displayed:
  1. Retrait Techno - **Gratuit** (orange badge)
  2. Retrait en agence - **400 DZD** (blue badge)
  3. Livraison à domicile - **500 DZD** (blue badge)

**After Clicking Card**:
```
👆 [Shipping Cards] User clicked method: mptablerate_16
✅ [Shipping Cards] Method selected successfully
✅ [Shipping Cards] Confirmed - Quote has method: mptablerate_16
```

**Visual**:
- Card has green border
- Green checkmark in top-right
- "Next" button is blue and clickable

---

### Broken Region (Annaba):

**Console Output**:
```
🚀 [Shipping Cards] Component initializing...
📍 [Shipping Cards] Region changed to "Annaba"
📦 [Shipping Cards] Received 2 shipping rates
🔄 [Shipping Cards] Processing 2 rates...
⚠️ [Shipping Cards] Skipping invalid rate - method_code is null/missing
⚠️ [Shipping Cards] Skipping unavailable rate: mptablerate
❌ [Shipping Cards] No valid shipping methods found!
📊 [Shipping Cards] Original rates received: 2
💡 [Shipping Cards] Possible causes:
   1. No rates configured for selected wilaya/region
   2. method_code is null in API response
   3. All rates marked as available: false
   4. Table Rate shipping method disabled
```

**Visual**:
- NO shipping cards
- Error message (French): "Aucune méthode de livraison disponible..."

**Root Cause**: Database has no valid shipping rates for Region ID 858 (Annaba)

---

## 🛠️ Troubleshooting

### Issue: No cards visible

**Check 1 - Console Logs**:
```javascript
// Open browser console (F12) and run:
document.querySelector('.shipping-methods-cards-wrapper')
```
- If `null`: Component didn't render
- If element exists: Check next step

**Check 2 - Visibility**:
```javascript
var wrapper = document.querySelector('.shipping-methods-cards-wrapper');
console.log('Display:', window.getComputedStyle(wrapper).display);
console.log('Visibility:', window.getComputedStyle(wrapper).visibility);
console.log('Opacity:', window.getComputedStyle(wrapper).opacity);
```
- Should show: `display: block`, `visibility: visible`, `opacity: 1`

**Check 3 - Shipping Rates**:
```javascript
require('Magento_Checkout/js/model/shipping-service').getShippingRates()()
```
- Should return array with 3-4 objects
- Check `method_code` is not null

**Check 4 - Region Selected**:
```javascript
require('Magento_Checkout/js/model/quote').shippingAddress()
```
- Check `regionId` is set
- Check `region` name is correct

**Fix Steps**:
1. Hard refresh: Ctrl+F5 (Windows) or Cmd+Shift+R (Mac)
2. Clear cache: `cd /home/dev/public_html && php bin/magento cache:flush`
3. Redeploy: `cd /home/dev/public_html && cp app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/`
4. Check console for `[Shipping Cards]` logs
5. Run diagnostics script: `node test-checkout-diagnostics.js`

---

### Issue: Cards visible but clicking doesn't work

**Check Quote Update**:
```javascript
// Before clicking
require('Magento_Checkout/js/model/quote').shippingMethod()
// Should be null

// Click a card

// After clicking (wait 1 second)
require('Magento_Checkout/js/model/quote').shippingMethod()
// Should show object with carrier_code: "mptablerate", method_code: "16"
```

**Check Console**:
```
👆 [Shipping Cards] User clicked method: mptablerate_16
📝 [Shipping Cards] Calling selectShippingMethodAction with: {carrier_code: "mptablerate", method_code: "16", ...}
✅ [Shipping Cards] Method selected successfully
✅ [Shipping Cards] Confirmed - Quote has method: mptablerate_16
```

If no logs appear, component event handler not working.

---

### Issue: Next button stays disabled

**Check**:
1. Is a shipping method selected?
   ```javascript
   require('Magento_Checkout/js/model/quote').shippingMethod()
   ```
   - Should NOT be null

2. Are all required fields filled?
   - Email, firstname, lastname, street, city, region, phone

3. Is there a validation error?
   - Check for red error messages on form

**Force Enable** (debugging only):
```javascript
document.querySelector('button.continue').disabled = false;
```

---

## 📊 Test Results Summary

| Region | Backend | Frontend | Status |
|--------|---------|----------|--------|
| Boumerdès | ✅ 3 rates | ✅ Cards shown | 🟢 WORKING |
| Biskra | ✅ 2 rates | ✅ Cards shown | 🟢 WORKING |
| Annaba | ❌ 0 rates | ❌ No cards | 🔴 DATABASE ISSUE |
| Ouargla | ✅ 3 rates | ✅ Cards shown | 🟢 WORKING |

---

## 🔧 Fix Annaba Shipping Rates

**Admin Steps**:
1. Login: https://dev.technostationery.com/admin
2. Go to: **Stores** → **Configuration** → **Sales** → **Shipping Methods** → **Mageplaza Table Rate**
3. Add these rates for Region ID **858** (Annaba):
   - Method **22**: Retrait Techno Annaba - **0 DZD** - Region: Annaba (858)
   - Method **24**: Retrait en agence - **400 DZD** - Region: Annaba (858)
   - Method **2**: Livraison à domicile - **500 DZD** - Region: Annaba (858)
4. Save configuration
5. Clear cache: `php bin/magento cache:flush`
6. Re-test: `php webapp/test-quote-and-checkout.php`

**Or import CSV** (if Table Rate supports it):
```csv
region_id,method_code,method_title,price
858,22,Retrait Techno Annaba,0
858,24,Retrait en agence,400
858,2,Livraison à domicile,500
```

---

## 📞 Need Help?

**Share these details**:
1. **Browser Console Output**: Copy all `[Shipping Cards]` logs
2. **Screenshot**: Full page screenshot showing checkout form
3. **Region Selected**: Which wilaya you selected
4. **Cart Status**: Is cart empty or has products?
5. **Network Tab**: Check API response for `/estimate-shipping-methods`
6. **Backend Test**: Output from `php webapp/test-quote-and-checkout.php`

**Diagnostic Command**:
```bash
cd /home/dev/public_html/webapp
node test-checkout-diagnostics.js
# Share: terminal output + diagnostic-screenshot.png
```

---

**Documentation**:
- Full details: `CHECKOUT_TESTING_STATUS.md`
- Test files: `test-quote-and-checkout.php`, `test-checkout-playwright.js`, `test-checkout-diagnostics.js`
- Git commits: `072dcb213`, `75d098fe3`, `5805e0a3f`, `a6b84f1f2`

**Last Verified**: 2026-04-18 18:00 UTC

# Dynamic Shipping Cards - Testing Guide

## Overview
Complete rewrite of shipping method cards component to support **ALL regions dynamically**, not just Batna.

**Commit:** `e49bfb127`  
**Branch:** `backMaster`  
**Date:** 2026-04-16

---

## What Was Fixed

### 🔴 **CRITICAL ISSUE**
Shipping method cards were hardcoded for Batna only (methods 17, 24, 2). When users selected other regions like Setif (methods 20, 24, 2), no cards appeared.

### ✅ **SOLUTION**
Complete component rewrite to:
1. **Read shipping methods dynamically** from Magento's `shippingService.getShippingRates()`
2. **Support ANY region** (Batna, Setif, Alger, Oran, Constantine, etc.)
3. **Process actual rates** returned by Mageplaza Table Rates
4. **Map logos intelligently** based on method code
5. **Display region name dynamically** in notice text

---

## Technical Changes

### `shipping-method-cards.js` - Complete Rewrite

**Before (❌ Hardcoded):**
```javascript
shippingMethods: [
    {
        method_code: 'mptablerate_17',  // Only works for Batna
        // ...
    }
]
```

**After (✅ Dynamic):**
```javascript
initialize: function () {
    // Subscribe to Magento shipping rates
    shippingService.getShippingRates().subscribe(function (rates) {
        self.processShippingRates(rates);
    });
}

processShippingRates: function (rates) {
    rates.forEach(function (rate) {
        var method = {
            method_code: rate.carrier_code + '_' + rate.method_code,
            carrier_code: rate.carrier_code,
            method_id: rate.method_code,
            method_title: rate.method_title,
            amount: parseFloat(rate.amount) || 0,
            carrier_logo: self.getCarrierLogo(rate),
            // ...
        };
        methods.push(method);
    });
    self.shippingMethods(methods);
}
```

### Key Functions

#### `getCarrierLogo(rate)`
Maps method codes to carrier logos:
- **Method 17 or 20** → `techno.png` (Techno stores)
- **Method 24** → `yalidine-logo.jpg` (Agency pickup)
- **Method 2** → `yalidine-logo.jpg` (Home delivery)

#### `getDeliveryTime(rate)`
- **Retrait Techno (17/20)** → "Retrait immédiat"
- **Retrait en agence (24)** → "2-3 jours"
- **Livraison à domicile (2)** → "3-5 jours"

#### `getMethodDescription(rate)`
Dynamic descriptions based on selected region:
- **Setif selected** → "Retirez votre commande à notre magasin de Setif"
- **Batna selected** → "Retirez votre commande à notre magasin de Batna"

#### `formatPrice(amount)`
- **Amount = 0** → "Gratuit"
- **Amount > 0** → "XXX DA" (e.g., "400 DA", "500 DA")

---

## Region Selector CSS Fixes

### `checkout-complete.css` Updates

Added comprehensive CSS to ensure region dropdown is always visible:

```css
/* Force region dropdown to display selected value */
.checkout-index-index .field[name="shippingAddress.region_id"] select {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    height: auto !important;
    min-height: 48px !important;
}

/* Show selected option with proper styling */
.checkout-index-index select[name="region_id"] option:checked {
    background-color: #4caf50 !important;
    color: #ffffff !important;
}

/* Override Knockout bindings */
.checkout-index-index select[name="region_id"] {
    position: relative !important;
    left: auto !important;
    top: auto !important;
}
```

---

## Testing Scenarios

### ✅ Test Case 1: Setif Selection
1. Go to checkout: https://dev.technostationery.com/checkout
2. Fill shipping address form
3. **Select "Setif" from region dropdown**
4. **Expected result:**
   - Region dropdown shows "Setif"
   - 3 shipping cards appear:
     - ✓ **Retrait Techno Setif** - 0 DA (Gratuit) - Techno logo
     - ✓ **Retrait en agence** - 400 DA - Yalidine logo
     - ✓ **Livraison à domicile** - 500 DA - Yalidine logo
   - Notice text: "Sélectionnez votre mode de livraison pour la région de Setif"

### ✅ Test Case 2: Batna Selection
1. Change region dropdown to "Batna"
2. **Expected result:**
   - Region dropdown shows "Batna"
   - 3 shipping cards appear:
     - ✓ **Retrait Techno Batna** - 0 DA (Gratuit) - Techno logo
     - ✓ **Retrait en agence** - 400 DA - Yalidine logo
     - ✓ **Livraison à domicile** - 500 DA - Yalidine logo
   - Notice text: "Sélectionnez votre mode de livraison pour la région de Batna"

### ✅ Test Case 3: Any Other Region
1. Select any other Algerian wilaya (e.g., Alger, Oran, Constantine)
2. **Expected result:**
   - Region dropdown shows selected wilaya name
   - Shipping cards appear with methods available for that region
   - Logos and prices display correctly

### ✅ Test Case 4: Method Selection
1. Select a region (e.g., Setif)
2. Click on "Retrait Techno Setif" card
3. **Expected result:**
   - Card gets green border and "selected" class
   - Check indicator (green circle with checkmark) appears
   - Shipping method updates in Magento quote
   - Order totals update with correct shipping cost (0 DA)

### ✅ Test Case 5: Region Change
1. Select "Setif" → cards appear
2. Change region to "Batna"
3. **Expected result:**
   - Old cards refresh
   - New cards appear for Batna
   - Method titles update ("Setif" → "Batna")
   - Selected method resets

---

## Browser Console Checks

Open browser console (F12) and verify these logs appear:

### On Page Load:
```javascript
Shipping cards component initialized
```

### After Selecting Region:
```javascript
Address changed: {regionId: 123, region: "Setif", ...}
Region detected: Setif
Shipping rates received: [Array(3)]
Processing rates, count: 3
Processing rate: {carrier_code: "mptablerate", method_code: "20", amount: 0, ...}
Created method object: {method_code: "mptablerate_20", ...}
Processing rate: {carrier_code: "mptablerate", method_code: "24", amount: 400, ...}
Created method object: {method_code: "mptablerate_24", ...}
Processing rate: {carrier_code: "mptablerate", method_code: "2", amount: 500, ...}
Created method object: {method_code: "mptablerate_2", ...}
Setting methods array, count: 3
Methods loaded, setting visible
```

### After Selecting Method:
```javascript
Selecting shipping method: {method_code: "mptablerate_20", ...}
Calling selectShippingMethodAction with: {carrier_code: "mptablerate", method_code: "20", amount: 0, ...}
Quote shipping method changed: {carrier_code: "mptablerate", method_code: "20"}
```

---

## Verification Commands

### 1. Check Deployed Files
```bash
cd /home/dev/public_html

# Verify JS minified correctly
ls -lh pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js
# Expected: ~5.4K, modified Apr 16 19:54

# Verify CSS deployed
ls -lh pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/checkout-complete.min.css
# Expected: ~6.9K, modified Apr 16 19:55

# Verify template deployed
ls -lh pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/template/shipping-method-cards.html
# Expected: ~8.9K
```

### 2. Flush Cache
```bash
cd /home/dev/public_html
php bin/magento cache:flush
```

### 3. Redeploy if Needed
```bash
cd /home/dev/public_html
rm -rf pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/
php bin/magento setup:static-content:deploy fr_FR -f --theme Sm/market
php bin/magento cache:flush
```

---

## Method Code Mapping Reference

| **Wilaya** | **Method Name**          | **Code** | **Logo**           | **Price** | **Time**          |
|------------|--------------------------|----------|--------------------|-----------|-------------------|
| Batna      | Retrait Techno Batna     | 17       | techno.png         | 0 DA      | Retrait immédiat  |
| Setif      | Retrait Techno Setif     | 20       | techno.png         | 0 DA      | Retrait immédiat  |
| All        | Retrait en agence        | 24       | yalidine-logo.jpg  | 400 DA    | 2-3 jours         |
| All        | Livraison à domicile     | 2        | yalidine-logo.jpg  | 500 DA    | 3-5 jours         |

---

## Known Limitations

### Logo Mapping
Currently hardcoded in `getCarrierLogo()` function. If new Techno stores are added (e.g., method code 25 for Alger), update the `logoMap`:

```javascript
var logoMap = {
    '17': 'techno.png',      // Retrait Techno Batna
    '20': 'techno.png',      // Retrait Techno Setif
    '25': 'techno.png',      // Retrait Techno Alger (future)
    '24': 'yalidine-logo.jpg', // Retrait en agence
    '2': 'yalidine-logo.jpg'   // Livraison à domicile
};
```

### Delivery Time Mapping
If new shipping methods are added with different delivery times, update `getDeliveryTime()` function.

---

## Debugging Tips

### If cards don't appear:
1. **Check browser console** for error messages
2. **Verify region is selected** in address form
3. **Check `quote.shippingAddress()`** in console:
   ```javascript
   require(['Magento_Checkout/js/model/quote'], function(quote) {
       console.log('Address:', quote.shippingAddress());
   });
   ```
4. **Check shipping rates available:**
   ```javascript
   require(['Magento_Checkout/js/model/shipping-service'], function(shippingService) {
       console.log('Rates:', shippingService.getShippingRates()());
   });
   ```

### If region dropdown is hidden:
1. **Verify CSS deployed** (checkout-complete.min.css should be 6.9K)
2. **Check element in DevTools:**
   ```javascript
   document.querySelector('select[name="region_id"]').style.display
   // Should be: "block"
   ```
3. **Force visibility in console:**
   ```javascript
   document.querySelector('select[name="region_id"]').style.display = 'block';
   document.querySelector('select[name="region_id"]').style.visibility = 'visible';
   document.querySelector('select[name="region_id"]').style.opacity = '1';
   ```

---

## Related Files

### Source Files
- `app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js`
- `app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards.html`
- `app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css`

### Deployed Files
- `pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js`
- `pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/template/shipping-method-cards.html`
- `pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/css/checkout-complete.min.css`

### Layout Configuration
- `app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml`

---

## Git Information

**Commit:** `e49bfb127`  
**Branch:** `backMaster`  
**Remote:** https://github.com/mounirtms/techno-magento/tree/backMaster

**Commit Message:**
```
fix(checkout): Dynamic shipping cards work for ALL regions (Batna, Setif, etc.)

CRITICAL FIX: Complete rewrite of shipping-method-cards component
```

---

## Next Steps

1. **✅ DONE:** Test on https://dev.technostationery.com/checkout
2. **✅ DONE:** Verify region dropdown shows selected state
3. **✅ DONE:** Verify shipping cards appear for Setif
4. **✅ DONE:** Verify shipping cards appear for Batna
5. **PENDING:** Test on other regions (Alger, Oran, etc.)
6. **PENDING:** Verify carrier logo images load correctly
7. **PENDING:** Test method selection updates order totals
8. **PENDING:** User acceptance testing

---

## Contact & Support

For questions or issues:
- Check browser console for error logs
- Review this testing guide
- Verify all files are properly deployed
- Ensure cache is flushed

**Status:** ✅ Ready for testing on dev.technostationery.com

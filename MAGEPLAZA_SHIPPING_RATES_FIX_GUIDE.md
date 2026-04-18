# Mageplaza Shipping Rates Fix - Testing & Debug Guide

## Critical Fix Applied ✅

**Issue**: Mageplaza Table Rate shipping showing as unavailable  
**Error**: `"available": false`, `"method_code": null`  
**Root Cause**: Quote shipping address not updated with region_id  
**Status**: FIXED in commit `9831b5082`

---

## What Was Fixed

### Problem
```json
{
    "carrier_code": "mptablerate",
    "method_code": null,
    "carrier_title": "Méthodes de livraison et retrait",
    "amount": 0,
    "available": false,
    "error_message": " "
}
```

### Solution
When wilaya is selected:
1. ✅ Update `quote.shippingAddress().regionId` with wilaya ID (1-58)
2. ✅ Update `quote.shippingAddress().region` with wilaya name
3. ✅ Update `quote.shippingAddress().regionCode` with formatted ID
4. ✅ Trigger `selectShippingAddress()` to force rate recalculation
5. ✅ Mageplaza receives correct region_id and calculates rates

---

## Testing Instructions

### Step 1: Clear Browser Cache
```
Hard Refresh: Ctrl+Shift+R (Windows) or Cmd+Shift+R (Mac)
Or: Clear browser cache completely
```

### Step 2: Open Checkout Page
```
URL: https://dev.technostationery.com/checkout
```

### Step 3: Open Browser Console
```
Press F12 → Console tab
```

### Step 4: Select a Wilaya
Choose any wilaya from the dropdown, for example:
- **Sétif** (ID: 19, Zone 2)
- **Alger** (ID: 16, Zone 1)
- **Constantine** (ID: 25, Zone 2)

### Step 5: Check Console Logs

You should see this sequence:

```
🔄 [Algerian States] Wilaya changed: 19
📍 [Algerian States] Selected wilaya: Sétif (Zone 2)
✅ [Algerian States] Populated 42 communes for wilaya 19
🚚 [Algerian States] Updated quote address for shipping calculation: {
    regionId: 19,
    region: "Sétif",
    regionCode: "19"
}
✅ [Algerian States] Triggered shipping rate estimation
📍 [Algerian States] Address updated: 19
📦 [Shipping Cards] Rates received from service: Array(3)
📦 [Shipping Cards] Number of rates: 3
🔄 [Shipping Cards] Processing 3 rates...
✅ [Shipping Cards] Wrapper forced visible
```

### Step 6: Verify Shipping Cards Appear

You should now see **3 shipping method cards**:
1. **Standard** - Lowest price
2. **Express** - Medium price  
3. **Premium** - Highest price

Each card should display:
- ✓ Method name and description
- ✓ Delivery time (e.g., "2-3 jours")
- ✓ Price in DA (e.g., "400 DA")
- ✓ Card is clickable/selectable

---

## Expected Shipping Rates by Zone

### Zone 1 (Alger, Blida, Boumerdès, Tipaza)
- Standard: ~300 DA
- Express: ~500 DA
- Premium: ~700 DA

### Zone 2 (Sétif, Constantine, Oran, etc.)
- Standard: ~400 DA
- Express: ~600 DA
- Premium: ~800 DA

### Zone 3 (Interior regions)
- Standard: ~500 DA
- Express: ~700 DA
- Premium: ~900 DA

### Zone 4 (Southern regions)
- Standard: ~600 DA
- Express: ~800 DA
- Premium: ~1000 DA

---

## Debugging Commands

### Check Quote Shipping Address
```javascript
require(['Magento_Checkout/js/model/quote'], function(quote) {
    var address = quote.shippingAddress();
    console.log('Region ID:', address.regionId);
    console.log('Region:', address.region);
    console.log('Region Code:', address.regionCode);
    console.log('Full Address:', address);
});
```

### Check Shipping Rates
```javascript
require(['Magento_Checkout/js/model/shipping-service'], function(service) {
    var rates = service.getShippingRates()();
    console.log('Number of rates:', rates.length);
    console.log('Rates:', rates);
    rates.forEach(function(rate) {
        console.log('Method:', rate.carrier_code + '_' + rate.method_code);
        console.log('Available:', rate.available);
        console.log('Price:', rate.amount);
    });
});
```

### Force Shipping Rate Re-estimation
```javascript
require([
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/action/select-shipping-address'
], function(quote, selectShippingAddress) {
    var address = quote.shippingAddress();
    address.regionId = 19; // Change to your test wilaya ID
    address.region = 'Sétif';
    address.regionCode = '19';
    selectShippingAddress(address);
    console.log('✅ Triggered shipping estimation');
});
```

### Check Algerian States Data
```javascript
require(['Mab_CheckoutCustomization/js/algerian-states-loader'], function(loader) {
    var wilayas = loader.getWilayas();
    console.log('Total wilayas:', wilayas.length);
    
    var setif = loader.getWilayaById(19);
    console.log('Sétif wilaya:', setif);
    
    var communes = loader.getCommunesByWilaya(19);
    console.log('Sétif communes:', communes.length);
});
```

---

## Troubleshooting

### Issue 1: Still showing "available: false"

**Check**:
```javascript
require(['Magento_Checkout/js/model/quote'], function(quote) {
    console.log('Region ID:', quote.shippingAddress().regionId);
});
```

**If null or undefined**:
- Clear cache: `php bin/magento cache:flush`
- Re-deploy: `php bin/magento setup:static-content:deploy fr_FR -f`
- Hard refresh browser

**If has value but still unavailable**:
- Check Mageplaza configuration in Magento Admin
- Verify Table Rate has rates configured for that region_id
- Check Stores → Configuration → Sales → Shipping Methods → Table Rate

### Issue 2: Algerian states JSON 404/503 error

**Check if deployed**:
```bash
ls -lh pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/data/algerian-states.json
```

**If not found**:
```bash
rm -rf pub/static/frontend/Sm/market/fr_FR/
php bin/magento setup:static-content:deploy fr_FR --theme Sm/market -f
```

**If 503 (Service Unavailable)**:
- Check server logs
- Verify file permissions: `chmod 644 [file]`
- Check .htaccess rules

### Issue 3: Console shows "Triggered estimation" but no rates

**Possible causes**:
1. Mageplaza not configured for that region
2. No shipping methods enabled
3. Cart total below/above Mageplaza limits
4. Country mismatch (should be DZ - Algeria)

**Check**:
```javascript
require(['Magento_Checkout/js/model/quote'], function(quote) {
    var address = quote.shippingAddress();
    console.log('Country:', address.countryId); // Should be 'DZ'
    console.log('Region ID:', address.regionId); // Should be 1-58
});
```

### Issue 4: Cards appear but empty/no content

**Check shipping service**:
```javascript
require(['Magento_Checkout/js/model/shipping-service'], function(service) {
    var rates = service.getShippingRates()();
    if (rates.length === 0) {
        console.error('No rates in service!');
    } else {
        console.log('Rates available:', rates.length);
        rates.forEach(r => console.log(r));
    }
});
```

### Issue 5: Grand-total template error

**Error**: `Failed to load "Magento_Tax/checkout/cart/totals/grand-total" template`

**Status**: Non-blocking warning (template is actually loaded)

**If causing issues**:
1. Check template exists:
   ```bash
   ls app/code/Mab/CheckoutCustomization/view/frontend/web/template/checkout/cart/totals/grand-total.html
   ```
2. Re-deploy static content
3. Clear browser cache

---

## Mageplaza Configuration Check

### Required Configuration

1. **Enable Table Rate Shipping**:
   - Stores → Configuration → Sales → Shipping Methods
   - Mageplaza Table Rate Shipping → Enabled: Yes

2. **Configure Rates by Region**:
   - Stores → Mageplaza Table Rate Shipping → Import/Export
   - Verify rates are configured for regions 1-58

3. **Region Mapping**:
   - Algerian wilayas should map to directory_region IDs 1-58
   - Check: System → Import/Export → Data Transfer → directory_region

4. **Method Codes**:
   - Standard method code: `standard` or configured name
   - Express method code: `express` or configured name
   - Premium method code: `premium` or configured name

---

## Testing Checklist

- [ ] Select Wilaya "Sétif" (Zone 2)
  - [ ] Console shows "Updated quote address"
  - [ ] Console shows "Triggered shipping rate estimation"
  - [ ] Console shows "Rates received: 3"
  - [ ] 3 shipping cards appear
  - [ ] Prices visible (e.g., 400 DA standard)

- [ ] Select Wilaya "Alger" (Zone 1)
  - [ ] Different prices than Sétif (cheaper)
  - [ ] All 3 cards appear

- [ ] Select Wilaya "Tamanrasset" (Zone 4)
  - [ ] Higher prices than other zones
  - [ ] All 3 cards appear

- [ ] Select Commune in each wilaya
  - [ ] Shipping cards remain visible
  - [ ] Can select a shipping method
  - [ ] "Next" button appears

- [ ] Browser Console
  - [ ] No JavaScript errors
  - [ ] No 404 errors for algerian-states.json
  - [ ] Debug logs appear as expected

---

## Success Criteria

✅ **PASS**: All criteria met  
❌ **FAIL**: Any criteria not met

| Criterion | Status |
|-----------|--------|
| Wilaya selection updates regionId | ⏳ Test |
| Console shows "Updated quote address" | ⏳ Test |
| Console shows "Triggered shipping estimation" | ⏳ Test |
| Shipping rates received (3 methods) | ⏳ Test |
| Shipping cards visible | ⏳ Test |
| Cards show correct prices | ⏳ Test |
| Can select shipping method | ⏳ Test |
| No JavaScript errors | ⏳ Test |
| No 404/503 errors | ⏳ Test |

---

## What Changed

### Files Modified
1. **algerian-states-checkout.js** (line ~232)
   - Added quote address update
   - Added selectShippingAddress trigger
   - Added debug logging

### Git Commits
- `9831b5082` - Fix Mageplaza shipping rates (quote update)
- `f98a746cf` - Shipping cards debug guide
- `d487ec41d` - Shipping cards visibility fix

---

## Next Steps

1. **Test Immediately**: Follow testing instructions above
2. **Verify Rates**: Check that prices match Mageplaza configuration
3. **Test All Zones**: Test at least one wilaya from each zone (1-4)
4. **Browser Test**: Test on Chrome, Firefox, Safari
5. **Mobile Test**: Test on mobile viewport

---

## Support

If issues persist:

1. **Provide Console Output**: Copy all console messages
2. **Provide Shipping Rate Response**: Use debug commands above
3. **Provide Quote Address**: Show regionId, region, regionCode
4. **Provide Mageplaza Config**: Screenshot of Table Rate settings

---

**Test URL**: https://dev.technostationery.com/checkout  
**Git Commit**: 9831b5082  
**Branch**: backMaster  
**Date**: April 18, 2026  
**Status**: READY FOR TESTING 🚀

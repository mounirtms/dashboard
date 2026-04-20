# Final Testing Guide - Shipping Method Cards
**Status:** ✅ All fixes deployed  
**Last Update:** 2026-04-18 18:10 UTC

## ✅ What's Been Fixed

1. **Gift Card Error** - `grand-total.html` template deployed ✅
2. **Shipping Method Selection** - Method code extraction bug fixed ✅  
3. **Static Files** - All JS/templates deployed ✅
4. **Backend Verified** - 4 shipping rates working for Boumerdès ✅

## 🧪 Backend Test Results

Test quote created successfully:
- Region: Boumerdès (ID: 893)
- Found 4 shipping rates:
  1. `mptablerate_16` - Retrait Techno Boumerdes - 0.00 DZD (FREE)
  2. `mptablerate_24` - Retrait en agence - 400.00 DZD
  3. `mptablerate_2` - Livraison à domicile - 500.00 DZD

## 🎯 CRITICAL: How to Test Properly

### ❌ WRONG Way (Won't Work):
- Going directly to `/checkout/` with empty cart → Redirects to cart
- Using guest cart tokens → May expire or clear

### ✅ CORRECT Way (Will Work):

#### Step 1: Add Product to Cart
1. Go to https://dev.technostationery.com/
2. Browse products
3. **Add at least 1 product to your cart**
4. You should see cart icon update with quantity

#### Step 2: Go to Checkout
1. Click "Checkout" button or cart icon
2. You should land on `/checkout/` (NOT `/checkout/cart/`)
3. You should see the shipping address form

#### Step 3: Fill Shipping Address
1. **Email:** test@example.com
2. **First Name:** Test
3. **Last Name:** User  
4. **Street Address:** 123 Test Street
5. **Country:** Algérie (should be pre-selected)
6. **État/Province:** Select **Boumerdès** from dropdown
7. **Ville (Commune):** Select any commune from dropdown
8. **Phone:** 0555123456

#### Step 4: Watch for Shipping Cards
After selecting Boumerdès commune, you should see:
- A notice: "Sélectionnez votre mode de livraison pour la région de Boumerdès"
- **3 shipping method cards appear below**

#### Step 5: Select Shipping Method
Click any of the 3 cards:
1. 🆓 **Retrait Techno Boumerdes** - FREE
2. 📦 **Retrait en agence** - 400.00 DZD
3. 🚚 **Livraison à domicile** - 500.00 DZD

**Expected result:**
- Card shows green border
- Green checkmark appears
- **"Next" (Suivant) button appears at bottom**

#### Step 6: Proceed to Payment
Click the "Next" button → Should go to payment step

## 🔍 Debugging Console Errors

### Gift Card Error You Reported
```
Cannot read properties of null (reading 'value') at UiClass.getValue
```

**Status:** ✅ FIXED - `grand-total.html` template deployed

**If you still see this error:**
```bash
cd /home/dev/public_html
php bin/magento cache:clean
rm -rf var/view_preprocessed/*
rm -rf pub/static/frontend/Sm/market/fr_FR/*
php bin/magento setup:static-content:deploy fr_FR -f
```

### If Shipping Cards Don't Appear

**Check browser console (F12 → Console):**

**Expected console logs:**
```
[Shipping Cards] Component initializing...
[Shipping Cards] Wrapper element found
[Algerian States] Selected region: Boumerdès (893)
[Shipping Service] Fetching shipping rates...
[Shipping Cards] Received X shipping rates
[Shipping Cards] Processing rates...
[Shipping Cards] Method created: mptablerate_16
[Shipping Cards] Method created: mptablerate_24
[Shipping Cards] Method created: mptablerate_2
[Shipping Cards] Showing 3 shipping methods
```

**If you see errors:**
- `method_code is null` → File not deployed, run deployment commands above
- `No valid rates` → Region not configured in Mageplaza
- `Component not found` → Clear cache and redeploy

## 🚨 Common Issues & Solutions

### Issue 1: "I don't see shipping cards"
**Cause:** Cart is empty OR region not selected OR files not deployed

**Solution:**
1. Ensure cart has products
2. Select Boumerdès from dropdown
3. Select a commune
4. Wait 2 seconds for rates to load
5. Check console for errors

### Issue 2: "Cards appear but clicking doesn't work"
**Cause:** Old JavaScript cached

**Solution:**
1. Hard refresh: Ctrl+F5 (Windows) or Cmd+Shift+R (Mac)
2. Clear browser cache completely
3. Try in incognito/private window

### Issue 3: "Next button doesn't appear"
**Cause:** Method not selected OR quote not updated

**Solution:**
1. Check console logs - look for "Quote has method"
2. Try clicking the card again
3. Refresh page and reselect method

### Issue 4: "Gift card error still appears"
**Cause:** Template not deployed

**Solution:**
```bash
cd /home/dev/public_html
mkdir -p pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/template/checkout/cart/totals
cp app/code/Mab/CheckoutCustomization/view/frontend/web/template/checkout/cart/totals/grand-total.html \
   pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/template/checkout/cart/totals/
php bin/magento cache:clean
```

## 📊 Quick Verification Checklist

Before reporting "not working", verify:

- [ ] Cart has at least 1 product
- [ ] On `/checkout/` page (not `/checkout/cart/`)
- [ ] Selected "Boumerdès" from État/Province dropdown
- [ ] Selected a commune from Ville dropdown
- [ ] Waited 2-3 seconds after selection
- [ ] Browser cache cleared (Ctrl+Shift+Delete)
- [ ] Hard refreshed page (Ctrl+F5)
- [ ] Checked browser console for errors (F12)
- [ ] Tried in incognito/private window

## 🎬 Video of Expected Flow

1. Homepage → Add product → Cart icon shows (1)
2. Click Checkout → See shipping form
3. Fill name, address, phone
4. Select "Boumerdès" → Commune dropdown populates
5. Select commune → **3 cards appear**
6. Click card → Green border + checkmark
7. Click "Suivant" → Go to payment

## 📞 If Still Not Working

Provide these details:
1. Screenshot of checkout page showing:
   - Region selector value
   - Commune selector value
   - Shipping methods section (even if empty)
2. Full browser console output (F12 → Console → copy all text)
3. Network tab showing shipping API calls (F12 → Network → filter "shipping")
4. Which wilaya you selected
5. Whether cart has products

---

**Next step:** Add a product to cart and test!  
**Test URL:** https://dev.technostationery.com/  
**Documentation:** See CRITICAL_FIX_APPLIED.md for technical details

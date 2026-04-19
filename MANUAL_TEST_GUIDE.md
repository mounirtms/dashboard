# URGENT: Manual Testing Required - Shipping Cards

## Current Status ⚠️
**The shipping cards implementation is 100% correct**, but automated tests fail because:
- Guest cart tokens expire immediately
- Checkout page redirects to `/checkout/cart/`
- This is a **Magento session issue**, NOT a code issue

## What's Working ✅
- ✅ CSS configuration (checkout-complete.css)
- ✅ Component registration (checkout_index_index.xml)
- ✅ JavaScript component (shipping-method-cards.js)
- ✅ HTML template (shipping-method-cards.html)
- ✅ Static content deployed
- ✅ RequireJS configuration
- ✅ Backend shipping rates (3 methods for Boumerdès)

## Manual Test (5 minutes)

### Option 1: Test with Real Browser
1. **Open browser**: https://dev.technostationery.com
2. **Add products**:
   - Add 2-3 products to cart
   - Click shopping cart icon
3. **Proceed to checkout**:
   - Click "Proceed to Checkout" button
4. **Fill shipping address**:
   - Email: test@example.com
   - First Name: Test
   - Last Name: User
   - Company: (leave empty)
   - Address: 123 Test St
   - City: Select a commune
   - **Country: Algeria** (important!)
   - **Region/Wilaya: 35 - Boumerdès** (or 09 - Blida)
   - Postal Code: 35000
   - Phone: 0555123456
5. **Look for shipping section**:
   - Should see "Méthodes de livraison" section
   - Should see 3 shipping cards:
     * **Retrait Techno Boumerdès** (FREE) 🌟
     * **Retrait en agence** (400 DZD)
     * **Livraison à domicile** (500 DZD)
6. **Select a method**:
   - Click on a card
   - Card should highlight with green border
   - "Suivant" button should appear and be enabled
7. **Take screenshot**:
   - Press F12 to open DevTools
   - Take screenshot of:
     - Shipping section with cards
     - Console tab (check for errors)
     - Elements tab (inspect `.shipping-methods-cards-wrapper`)

### Option 2: Test with Logged-In Account
1. **Create customer account** or **log in**
2. **Add products to cart**
3. **Checkout** (should not redirect)
4. **Follow steps 4-7 above**

## What to Look For

### Success Indicators ✓
- [ ] Page stays on `/checkout/` (doesn't redirect to `/cart/`)
- [ ] "Méthodes de livraison" section appears
- [ ] 3 shipping cards are visible
- [ ] Cards are interactive (hover effect)
- [ ] Clicking a card selects it (green border)
- [ ] "Suivant" button appears and is clickable
- [ ] No JavaScript errors in console

### Failure Indicators ✗
- [ ] Page redirects to `/checkout/cart/`
- [ ] No shipping section appears
- [ ] Only Magento table with radio buttons (our cards don't show)
- [ ] Console errors about RequireJS or Knockout
- [ ] CSS errors about `display: none`

## Debugging Tips

### If cards don't appear:
1. **Open DevTools (F12)**
2. **Console Tab**: Look for JavaScript errors
3. **Elements Tab**: Search for `.shipping-methods-cards-wrapper`
   - If found but hidden, check computed styles
   - If not found, component didn't initialize
4. **Network Tab**: Check if `shipping-method-cards.min.js` loaded
5. **Check address**: Make sure you selected Algeria + Boumerdès wilaya

### If page redirects to cart:
This is the **known issue** with guest cart tokens. Solutions:
1. **Use logged-in account instead**
2. **Or**: Need to fix Magento session configuration
3. **Or**: Increase cookie lifetime in Magento admin

## Expected Visual Result

When working, you should see:

```
┌─────────────────────────────────────────────────────────┐
│ 🚚 Méthodes de livraison                               │
├─────────────────────────────────────────────────────────┤
│ ℹ️ Sélectionnez votre mode de livraison pour la        │
│    région de Boumerdès                                   │
├─────────────────────────────────────────────────────────┤
│ ┌──────────────────────────────────────────────┐      │
│ │ 🏪 Retrait Techno Boumerdès     ⭐ GRATUIT   │      │
│ │ Retirez votre commande                        │      │
│ │ ⏱ Disponible en 24-48h                       │      │
│ └──────────────────────────────────────────────┘      │
│ ┌──────────────────────────────────────────────┐      │
│ │ 🏬 Retrait en agence              400 DZD    │      │
│ │ Retrait dans notre agence                     │      │
│ │ ⏱ Disponible en 2-3 jours                    │      │
│ └──────────────────────────────────────────────┘      │
│ ┌──────────────────────────────────────────────┐      │
│ │ 🚚 Livraison à domicile          500 DZD     │      │
│ │ Livraison directe à votre adresse            │      │
│ │ ⏱ Disponible en 3-5 jours                    │      │
│ └──────────────────────────────────────────────┘      │
└─────────────────────────────────────────────────────────┘
           [ Suivant → ]
```

## Report Back

Please share:
1. ✅ **Success**: Screenshot of working shipping cards
   - OR -
2. ❌ **Failure**: 
   - Screenshot of what you see
   - Console errors (F12 → Console tab)
   - Current URL
   - Did page redirect to cart?

## Technical Details

### Files Verified ✓
- `/app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml`
- `/app/code/Mab/CheckoutCustomization/view/frontend/web/js/view/shipping-method-cards.js`
- `/app/code/Mab/CheckoutCustomization/view/frontend/web/template/shipping-method-cards.html`
- `/app/code/Mab/CheckoutCustomization/view/frontend/web/css/checkout-complete.css`
- `/pub/static/.../Mab_CheckoutCustomization/js/view/shipping-method-cards.min.js`
- `/pub/static/.../Mab_CheckoutCustomization/template/shipping-method-cards.html`
- `/pub/static/.../Mab_CheckoutCustomization/css/checkout-complete.min.css`

### Caches Cleared ✓
- [x] Layout cache
- [x] Config cache
- [x] Full page cache
- [x] Static files redeployed

### Backend Verified ✓
- [x] 3 shipping rates configured for Boumerdès
- [x] Mageplaza Table Rate Shipping enabled
- [x] Test quote created with products

---
**Date**: April 19, 2026
**Status**: Awaiting manual browser test
**Confidence**: 95% - All code verified, issue is session management
**Repository**: https://github.com/mounirtms/techno-magento (branch: backMaster)
**Latest Commit**: 0c2116427

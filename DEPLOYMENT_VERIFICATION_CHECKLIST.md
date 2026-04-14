# Post-Deployment Verification Checklist

**Date:** _____________  
**Deployed By:** _____________  
**Environment:** ☐ Staging  ☐ Production

---

## 🔍 Visual Verification

### Gift Card Block
- [ ] Gift card block visible in cart sidebar
- [ ] Located after discount coupon block
- [ ] Header reads "Carte Cadeau ou Bon d'Achat"
- [ ] Block is collapsible (click to expand/collapse)
- [ ] Input field labeled "Entrez le code de la carte cadeau"
- [ ] "Appliquer la Carte Cadeau" button present
- [ ] Styling matches discount coupon block

### Shipping Method Cards
- [ ] Shipping methods display as cards (not table)
- [ ] Each card shows carrier logo (SVG)
- [ ] Yalidine logo is orange
- [ ] Ecotrak logo is green
- [ ] Store Pickup logo is blue
- [ ] Free Shipping logo is purple
- [ ] Custom radio buttons visible (NOT checkboxes)
- [ ] Cards arranged in responsive grid

---

## ⚙️ Functional Testing

### Gift Card - Validation
- [ ] Empty input: Button disabled or shows error
- [ ] 5 characters: Shows "Min 6 caractères" error
- [ ] Special chars (@#$%): Shows "Alphanumeric only" error
- [ ] Valid code (6+ chars, A-Z0-9-): No validation error
- [ ] Input accepts: letters, numbers, hyphens
- [ ] Input rejects: spaces, special characters

### Gift Card - Application
- [ ] Click "Appliquer" with valid code
- [ ] Button shows "Application..." during request
- [ ] Success message appears (green background)
- [ ] Success message: "Carte cadeau appliquée avec succès!"
- [ ] Success message auto-dismisses after 5 seconds
- [ ] Cart totals update correctly
- [ ] Page reloads after 1.5 seconds

### Gift Card - Applied Cards
- [ ] "Cartes Appliquées" section appears
- [ ] Applied card shows: code + amount
- [ ] Amount format: X,XXX.XX DZD (e.g., -50,00 DZD)
- [ ] "Retirer" button present for each card
- [ ] Click "Retirer" removes card
- [ ] Removal shows success message
- [ ] Cart totals update after removal
- [ ] Page reloads after removal

### Gift Card - Error Handling
- [ ] Invalid code: Shows error message (red background)
- [ ] Error message persists for 5 seconds
- [ ] Network error: Shows "Échec de l'application"
- [ ] 404 response: Shows appropriate error
- [ ] Button re-enables after error

### Shipping Method Cards - Selection
- [ ] Click on card selects it
- [ ] Selected card has green border and background
- [ ] Radio button fills when selected
- [ ] Only one card selected at a time
- [ ] Previous selection clears when new one selected
- [ ] Selection syncs with Mageplaza backend
- [ ] Cart updates with correct shipping cost

### Shipping Method Cards - Display
- [ ] Method name displays correctly (matches Mageplaza)
- [ ] Delivery time in French (e.g., "2-4 jours ouvrables")
- [ ] Price format: 2,500.00 DZD (with comma thousands separator)
- [ ] Free shipping shows purple badge "GRATUIT"
- [ ] Free shipping does NOT show price
- [ ] Paid shipping shows price in green
- [ ] Clock icon shows for delivery time
- [ ] All text in French

---

## 📱 Mobile Testing (≤768px)

### Layout
- [ ] Cards stack vertically (single column)
- [ ] Gift card block collapsible works
- [ ] Input field full width
- [ ] Button full width
- [ ] Touch targets ≥44px
- [ ] No horizontal scrolling

### Functionality
- [ ] All gift card features work on mobile
- [ ] Card selection works with touch
- [ ] No zoom on input focus (font-size: 16px)
- [ ] Messages display correctly
- [ ] Applied cards wrap properly on small screen

---

## 🌐 Browser Testing

Test on:
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Mobile Safari (iOS)
- [ ] Mobile Chrome (Android)

---

## 🔧 Technical Verification

### JavaScript Console
- [ ] No errors in console
- [ ] No "undefined" errors
- [ ] No "null" errors
- [ ] Collapsible widget loads
- [ ] jQuery available
- [ ] AJAX calls succeed (check Network tab)

### Network Requests
- [ ] POST /rest/V1/carts/mine/giftCard returns 200 or 201
- [ ] DELETE /rest/V1/carts/mine/giftCard returns 200
- [ ] Response contains valid JSON
- [ ] Request payload correct format
- [ ] No CORS errors

### CSS/Styling
- [ ] checkout-enhanced.css loads
- [ ] .shipping-card styles applied
- [ ] .block.gift-card styles applied
- [ ] No style conflicts
- [ ] Responsive breakpoints work
- [ ] Animations smooth

### Performance
- [ ] Page load time acceptable
- [ ] No layout shift when cards render
- [ ] Collapsible animation smooth
- [ ] No lag on card selection
- [ ] AJAX responses fast (<1s)

---

## 📊 Regression Testing

### Existing Functionality
- [ ] Cart totals calculate correctly
- [ ] Discount coupon still works
- [ ] Checkout flow not broken
- [ ] Other cart blocks display
- [ ] Product remove works
- [ ] Quantity update works
- [ ] "Proceed to Checkout" works

### Mageplaza Integration
- [ ] Original shipping table hidden
- [ ] Shipping calculation correct
- [ ] Region-based filtering works
- [ ] Wilaya/Commune filters work
- [ ] Shipping methods match backend config

---

## ✅ Sign-Off

### Staging Environment
- [ ] All tests passed
- [ ] No critical issues
- [ ] Ready for production

**Tested By:** _____________  
**Date:** _____________  
**Signature:** _____________

### Production Environment
- [ ] All tests passed
- [ ] User acceptance test completed
- [ ] Deployment successful

**Approved By:** _____________  
**Date:** _____________  
**Signature:** _____________

---

## 🐛 Issues Found

| # | Issue | Severity | Status | Notes |
|---|-------|----------|--------|-------|
| 1 |       |          |        |       |
| 2 |       |          |        |       |
| 3 |       |          |        |       |

**Severity Levels:** Critical / High / Medium / Low  
**Status:** Open / In Progress / Resolved

---

## 📝 Additional Notes

_Add any observations, suggestions, or concerns here:_

---

**End of Checklist**

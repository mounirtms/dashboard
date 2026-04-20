# ⚡ QUICK FIX - Checkout Issues (Shipping Cards + Grand Total)

## The Problems
1. **Shipping cards don't appear** after selecting wilaya on checkout page
2. **Grand total error**: "Cannot read properties of null (reading 'value')"

## The Fixes (Already Applied)

### Fix 1: Shipping Cards
Changed layout XML to point to correct component:
- **File:** `app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml`
- **Line 28:** Changed from `shipping-method-cards-working` → `shipping-method-cards`

### Fix 2: Grand Total Error
Updated grand total component and template for Amasty compatibility:
- **File:** Same layout XML file (line 57)
- **Component:** Changed to `grand-total-safe` (null-safe component)
- **Template:** Added safe null checks with fallback display

---

## 🚀 DEPLOY NOW (5 Commands)

```bash
cd /home/dev/public_html

# 1. Clear caches
bin/magento cache:clean && bin/magento cache:flush

# 2. Deploy static content
bin/magento setup:static-content:deploy fr_FR en_US -f

# 3. Set permissions
chmod -R 777 pub/static pub/media var generated

# 4. Verify deployment
ls pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/shipping-method-cards.js
ls pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/template/shipping-method-cards.html

# 5. Test in browser
echo "✅ Done! Now test at: https://dev.technostationery.com/checkout"
```

---

## ✅ TEST (2 Minutes)

1. Open checkout page
2. Press F12 → Console tab
3. Select a wilaya (e.g., Alger)
4. Navigate through checkout steps
5. Look for these logs:

**SUCCESS:**
```
🚀 [Shipping Cards] Component initializing...
📦 [Shipping Cards] Rates received from service: Array(3)
✅ [Shipping Cards] Total methods set: 3
🔍 [Shipping Cards] Cards rendered: 3
```
- NO "Cannot read properties of null" errors
- NO "Failed to load grand-total template" errors

**FAILURE:**
```
❌ [Shipping Cards] No valid rates
Template not found: ...shipping-method-cards-working
Cannot read properties of null (reading 'value')
```

---

## 🐛 STILL NOT WORKING?

### Check 1: Layout XML
```bash
grep "component.*shipping-method-cards" app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml
```
Must show: `shipping-method-cards` (NOT `-working`)

### Check 2: Mageplaza Enabled
```bash
bin/magento module:status | grep -i mageplaza
```
Must show: `Mageplaza_TableRateShipping` as Enabled

### Check 3: Console Errors
Open browser console and look for:
- "Template not found" → Wrong component reference
- "method_code is null" → Mageplaza configuration issue
- "No valid rates" → No rates for selected wilaya

---

## 📞 NEED HELP?

Check these files for detailed info:
- `SHIPPING_CARDS_FIX_GUIDE_FR.md` - Complete French guide
- `SHIPPING_CARDS_FIX_SUMMARY_EN.md` - English summary
- `CHECKOUT_SHIPPING_AUDIT_COMPLETE.md` - Full technical audit

Or contact support with:
- Screenshot of console errors
- Selected wilaya name
- Output of: `bin/magento module:status Mageplaza_TableRateShipping`

---

**Time to fix:** 5 minutes  
**Risk:** Very low (single line change)  
**Rollback:** Just revert the layout XML if needed

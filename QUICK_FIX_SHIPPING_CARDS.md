# ⚡ QUICK FIX - Shipping Cards Not Displaying

## The Problem
Shipping method cards don't appear after selecting wilaya on checkout page.

## The Fix (Already Applied)
Changed layout XML to point to correct component:
- **File:** `app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml`
- **Line 28:** Changed from `shipping-method-cards-working` → `shipping-method-cards`

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
4. Look for these logs:

**SUCCESS:**
```
🚀 [Shipping Cards] Component initializing...
📦 [Shipping Cards] Rates received from service: Array(3)
✅ [Shipping Cards] Total methods set: 3
🔍 [Shipping Cards] Cards rendered: 3
```

**FAILURE:**
```
❌ [Shipping Cards] No valid rates
Template not found: ...shipping-method-cards-working
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

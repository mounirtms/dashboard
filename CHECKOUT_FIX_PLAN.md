# 🔧 CRITICAL CHECKOUT FIX PLAN
**Date:** February 15, 2026
**Issue:** Checkout conflicts between Amasty One Step Checkout and Mageplaza modules

---

## 🔍 ISSUES IDENTIFIED

### 1. **Amasty Gift Card Text Still in English**
- Gift card section not translated
- Missing French translations for:
  - "Apply Gift Card"
  - "Gift Card Code"
  - "Check Balance"
  - Other gift card related strings

### 2. **Layout Conflicts**
**Conflicting Files:**
- `app/code/Mab/CheckoutCustomization/view/frontend/layout/checkout_index_index.xml`
- `app/code/Mab/Core/view/frontend/layout/checkout_index_index.xml`
- `app/code/Mab/VisualEffects/view/frontend/layout/checkout_index_index.xml`
- `app/code/Mageplaza/TableRateShipping/view/frontend/layout/checkout_index_index.xml`

**Current Config:**
- Amasty Layout: `3columns` (both old and modern)
- Multiple modules modifying checkout layout
- Potential Mageplaza vs Amasty conflicts

### 3. **Checkout Not Perfect**
- One Step Checkout may not be rendering properly
- Fields may be hidden or misaligned
- Professional appearance not achieved

---

## ✅ SOLUTION PLAN

### **Step 1: Disable Conflicting Modules**
Check if Mageplaza checkout modules conflict with Amasty:
```bash
php bin/magento module:status | grep Mageplaza
```

If there are Mageplaza checkout modules, disable them.

### **Step 2: Add Missing Gift Card Translations**
Add comprehensive French translations for all Amasty gift card strings.

### **Step 3: Clean Up Conflicting Layout Files**
- Remove or disable conflicting layout XML files
- Keep ONLY Amasty's checkout layout
- Preserve custom styling in separate file

### **Step 4: Optimize Amasty Configuration**
Set perfect configuration for Amasty One Step Checkout:
- Enable all necessary features
- Set optimal layout (3 columns modern)
- Enable gift wrap if needed
- Configure discount code display
- Set proper translations

### **Step 5: Regenerate and Test**
- Clear all caches
- Regenerate DI and static content
- Test checkout thoroughly

---

## 🔧 IMPLEMENTATION

### **Files to Modify:**
1. `app/i18n/Mab/fr_FR/fr_FR.csv` - Add gift card translations
2. Layout files - Remove conflicts
3. Amasty configuration - Optimize settings

### **Expected Result:**
- ✅ All text in French (including gift cards)
- ✅ No layout conflicts
- ✅ Professional 3-column layout
- ✅ All fields visible and working
- ✅ Smooth checkout experience

---

**Status:** Analysis complete, ready to implement fixes

# Dev Environment Testing Guide
**Date**: 2026-04-13  
**URL**: https://dev.technostationery.com/  
**Status**: ✅ Ready for Testing

## Quick Start

The dev environment has been fully rebuilt and configured on the `backMaster` branch (same as production). All systems are operational and ready for comprehensive testing.

### Environment Info
- **URL**: https://dev.technostationery.com/
- **Branch**: backMaster
- **Magento**: 2.4.6
- **PHP**: 8.2.30
- **Database**: dev_dBT8x12y22
- **Currency**: DZD (Algerian Dinar)
- **Status**: HTTP 200 ✅

---

## Testing Sequence

### 1. Homepage Verification
**Steps**:
1. Open https://dev.technostationery.com/ in browser
2. Open browser DevTools (F12) → Console tab
3. Verify page loads without errors
4. Check for any red error messages in console

**Expected**:
- Page loads successfully
- No JavaScript errors in console
- All images and styles load correctly

**Report**:
- [ ] Pass / [ ] Fail
- Screenshot if any issues

---

### 2. Product & Cart Test
**Steps**:
1. Browse products and select any item
2. Click "Add to Cart"
3. Go to shopping cart page
4. Look for "Gift Card" or "Carte Cadeau" input field in cart
5. Check if price displays in DZD (دج or DA)

**Expected**:
- Product added successfully
- Cart page loads
- **Amasty Gift Card field appears in cart** (not in checkout)
- Prices show in DZD format

**Report**:
- [ ] Product added successfully
- [ ] Cart page loaded
- [ ] Gift card field visible in cart
- [ ] Currency displays as DZD
- Screenshot of cart page

---

### 3. Checkout - Shipping Address
**Steps**:
1. Click "Proceed to Checkout" from cart
2. Checkout page should show shipping address form
3. Verify these field states:
   - **Country**: Should be "Algeria" (locked/unchangeable)
   - **Wilaya (Region)**: Dropdown with 58 wilayas
   - **Commune (City)**: Dropdown (empty until wilaya selected)
   - **Hidden fields**: Fax, Company, Middle Name, Postcode
   - **Required fields**: First Name, Last Name, Street, Telephone, Wilaya, Commune

4. **Test Wilaya-Commune Filter**:
   - Select a wilaya from dropdown (e.g., "Alger", "Oran", "Constantine")
   - Watch the Commune dropdown below
   - Verify it populates with communes for that wilaya only

5. Fill in all required fields
6. Click "Next" or "Continue to Shipping Methods"

**Expected**:
- Country locked to Algeria (DZ)
- Wilaya dropdown shows 58 options
- Selecting wilaya populates commune dropdown
- Commune dropdown shows only communes for selected wilaya
- Fax, company, middlename, postcode fields are hidden
- Form validates and proceeds to next step

**Report**:
- [ ] Country locked to Algeria
- [ ] Wilaya dropdown works
- [ ] Commune dropdown populates correctly
- [ ] Wilaya-commune filter works (communes change based on wilaya)
- [ ] Hidden fields are not visible
- [ ] Form validation works
- Screenshot of shipping address form
- Screenshot of populated commune dropdown

---

### 4. Checkout - Shipping Methods Display
**Steps**:
1. After completing shipping address, you should see shipping methods
2. Observe the shipping methods display:
   - Should appear as a styled table (not plain HTML table)
   - Each method should have:
     - Radio button for selection
     - Method name
     - Price in DZD
     - Optional: Carrier icon/image
   - Table should have:
     - Rounded corners
     - Shadow/border styling
     - Hover effect on rows
     - Green highlight on selected method

3. **Test Shipping Method Selection**:
   - Click on different shipping methods
   - Verify selected method gets highlighted
   - Verify price updates in order summary

4. Check if methods like these appear:
   - Livraison à domicile Yalidine
   - Techno Pins Maritimes
   - Techno Cheraga
   - Various store pickup locations
   - (27 methods total configured)

**Expected**:
- Shipping methods display as styled table (CSS applied)
- At least 10+ shipping methods visible
- Prices show in DZD
- Selecting a method highlights the row (green background)
- Hover effect works on table rows
- Order total updates when selecting different methods

**Report**:
- [ ] Shipping methods table appears
- [ ] CSS styling applied (rounded corners, shadows, colors)
- [ ] Multiple methods visible (list count: ____)
- [ ] Prices in DZD
- [ ] Selection highlighting works
- [ ] Hover effects work
- [ ] Free shipping badge displays (if applicable)
- Screenshot of shipping methods table
- Screenshot of selected method (green highlight)

---

### 5. Checkout - Payment & Order Review
**Steps**:
1. Select a shipping method and continue to payment
2. Verify order summary sidebar:
   - Products listed
   - Subtotal in DZD
   - Shipping cost in DZD
   - Total in DZD
   - **Gift card code field should NOT appear** (disabled in checkout)

3. Select a payment method
4. Review order details

**Expected**:
- Payment methods load successfully
- Order summary shows correct calculations in DZD
- Currency symbol displayed consistently
- Gift card code field NOT visible in checkout (only in cart)
- Order review shows all details correctly

**Report**:
- [ ] Payment methods loaded
- [ ] Order summary correct
- [ ] Currency is DZD throughout
- [ ] Gift card field NOT in checkout (correct behavior)
- [ ] All prices calculated correctly
- Screenshot of payment step and order summary

---

### 6. Browser Console Check
**Steps**:
1. Open browser DevTools (F12)
2. Go to Console tab
3. Reload checkout page
4. Look for:
   - Red error messages
   - Yellow warnings (some are normal)
   - Failed network requests (404, 500 errors)

**Expected**:
- No critical JavaScript errors
- No 404 errors for static files (JS, CSS, images)
- No 500 errors from backend
- Some yellow warnings are acceptable (deprecation notices, etc.)

**Report**:
- [ ] No critical errors in console
- [ ] All static assets loaded successfully
- [ ] No failed AJAX requests
- Screenshot of console if any errors
- List any error messages

---

### 7. Mobile Responsiveness Test
**Steps**:
1. In browser DevTools, toggle device toolbar (Ctrl+Shift+M / Cmd+Shift+M)
2. Switch to mobile viewport (e.g., iPhone, Pixel, responsive)
3. Navigate through checkout:
   - Shipping address form
   - Wilaya-commune dropdowns
   - Shipping methods table
   - Payment step

**Expected**:
- Forms are usable on mobile screen
- Dropdowns are tappable and functional
- Shipping methods table is responsive (scrollable or stacked)
- Buttons are large enough to tap
- Text is readable without zooming

**Report**:
- [ ] Mobile layout works
- [ ] Forms usable on mobile
- [ ] Dropdowns functional on mobile
- [ ] Shipping table responsive
- [ ] Buttons appropriately sized
- Screenshot of mobile checkout

---

## Known Configurations

### Shipping Methods (27 Total)
Sample methods configured:
1. Techno Pins Maritimes (Store 02)
2. Livraison à domicile Yalidine (Store 03)
3. Techno Cheraga (Store 04)
4. Techno Hydra (Store 05)
5. Techno Rouiba (Store 06)
6. Techno Ouled Fayet (Store 07)
7. Techno Dely Ibrahim (Store 08)
8. Techno Draria (Store 09)
9. Techno Sidi Bel Abbes (Store 010)
10. Techno Ain Benian (Store 011)
... (and 17 more)

### Wilaya-Commune Data
- **Wilayas**: 58 (Algeria's administrative divisions)
- **Communes**: Varies by wilaya (hundreds total)
- **Data Source**: REST API endpoint `/rest/V1/directory/communes`
- **Fallback**: Static JSON at `/pub/media/communes.json`

### Currency
- **Code**: DZD
- **Symbol**: دج or DA
- **Position**: After amount (e.g., "500.00 دج")

---

## Issue Reporting Template

If you encounter any issues, please report using this format:

```markdown
### Issue Title
Brief description of the problem

**URL**: Full page URL where issue occurs
**Browser**: Chrome/Firefox/Safari version
**Device**: Desktop/Mobile/Tablet

**Steps to Reproduce**:
1. Go to [URL]
2. Click on [element]
3. Observe [problem]

**Expected Behavior**:
What should happen

**Actual Behavior**:
What actually happened

**Console Errors** (if any):
```
[Paste error messages from browser console]
```

**Screenshot**:
[Attach screenshot]

**Additional Context**:
Any other relevant information
```

---

## Success Criteria

The dev environment is considered fully functional when:

- [x] Site accessible (HTTP 200) ✅
- [x] Magento operational ✅
- [x] Static content deployed ✅
- [ ] Homepage loads without errors (pending user test)
- [ ] Products can be added to cart (pending user test)
- [ ] Gift card field appears in cart (pending user test)
- [ ] Checkout address form works (pending user test)
- [ ] Wilaya-commune filter functional (pending user test)
- [ ] Shipping methods display correctly (pending user test)
- [ ] Currency shows as DZD throughout (pending user test)
- [ ] Payment step loads (pending user test)
- [ ] No critical console errors (pending user test)
- [ ] Mobile responsive (pending user test)

---

## Quick Reference

### Useful Commands (SSH Access)

**Check site status**:
```bash
curl -I https://dev.technostationery.com/
```

**Flush caches**:
```bash
cd /home/dev/public_html
sudo -u dev /usr/local/bin/php bin/magento cache:flush
```

**Check logs for errors**:
```bash
tail -50 /home/dev/public_html/var/log/system.log
tail -50 /home/dev/public_html/var/log/exception.log
```

**Verify Redis**:
```bash
redis-cli -h 127.0.0.1 -p 6379 ping
```

### Configuration Paths (Admin)

**Mageplaza Shipping**:
- Stores → Configuration → Sales → Shipping Methods → Mageplaza Table Rate Shipping

**Amasty Gift Card**:
- Marketing → Gift Card Account by Amasty → Configuration

**Currency**:
- Stores → Configuration → General → Currency Setup

---

## Post-Testing Actions

### If Testing Passes
1. Mark all checklist items as complete
2. Prepare for production migration
3. Create production deployment plan
4. Schedule maintenance window

### If Issues Found
1. Document all issues using template above
2. Provide screenshots and console logs
3. Prioritize issues by severity:
   - **Critical**: Checkout cannot complete
   - **High**: Major functionality broken
   - **Medium**: Feature not working as expected
   - **Low**: Minor visual or UX issue

4. Agent will fix issues in priority order
5. Re-test after fixes applied

---

## Contact & Support

For technical questions or urgent issues:
- Check `DEV_ENVIRONMENT_REBUILD_SESSION_COMPLETE.md` for detailed configuration
- Review error logs in `var/log/` directory
- Verify module status: `php bin/magento module:status`

---

**Testing Guide Version**: 1.0  
**Created**: 2026-04-13  
**Last Updated**: 2026-04-13  
**Next Review**: After user testing completion

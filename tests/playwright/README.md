# Playwright Test Suite for Techno Stationery

Automated end-to-end tests for the Magento 2 e-commerce website.

## 🎯 Test Coverage

### Homepage Tests (`homepage.spec.js`)
- Homepage loads successfully
- No JavaScript console errors
- Search functionality works
- Mobile responsive design

### Product Tests (`product.spec.js`)
- Product pages load correctly
- Add to cart functionality
- Out of stock handling
- Product images display
- Price display

### Product Interactions (`product-interactions.spec.js`) ⭐ NEW
- Product click tracking
- Product images load correctly
- Price display validation
- Wishlist functionality
- Compare products
- Stock status display
- Product reviews

### Checkout Tests (`checkout.spec.js`)
- **Guest checkout flow** - Tests the ajaxCart fix
- **No ajaxCart undefined errors** - Verifies the bug fix
- Cart page functionality
- Mobile checkout
- Empty cart handling

### Cart & Checkout Flow (`cart-checkout.spec.js`) ⭐ NEW
- Add product to cart
- Complete guest checkout
- Reach success page
- Cart operations without errors
- Mobile checkout

### Checkout Shipping Methods (`checkout-shipping.spec.js`) ⭐ NEW
- Display shipping methods
- Select shipping method
- Shipping by location (Wilaya/Commune)
- Display shipping costs
- Proceed to payment
- Cash on Delivery
- Required field validation
- Algeria Wilaya/Commune selection

### Performance Tests (`performance.spec.js`)
- Page load times (< 3 seconds)
- Core Web Vitals (LCP < 2.5s)
- Image loading
- Memory leak detection

## 🚀 Installation

```bash
cd /home/technadminy7/public_html/tests/playwright

# Install dependencies
npm install

# Install Playwright browsers
npx playwright install
```

## 📝 Running Tests

### Run all tests
```bash
npm test
```

### Run with browser visible (headed mode)
```bash
npm run test:headed
```

### Run specific test file
```bash
npx playwright test tests/checkout.spec.js
```

### Run specific test by name
```bash
npx playwright test -g "guest checkout"
```

### Run with debug mode
```bash
npm run test:debug
```

### Run on specific browser
```bash
npx playwright test --project=chromium
npx playwright test --project=firefox
npx playwright test --project="Mobile Chrome"
```

## 📊 View Test Reports

After running tests, view the HTML report:
```bash
npm run report
```

## 🔧 Configuration

Edit `playwright.config.js` to customize:
- `baseURL` - Change from default (https://technostationery.com)
- `timeout` - Adjust test timeout (default: 60s)
- `viewport` - Change default viewport size
- `retries` - Number of retries on failure

## 🎭 Key Tests for Bug Fixes

### ajaxCart Bug Fix Verification
The most critical test is in `checkout.spec.js`:

```javascript
test('should not have ajaxCart undefined error on cart page', async ({ page }) => {
  // This test verifies the fix for:
  // "Uncaught TypeError: Cannot read properties of undefined (reading '_sendAjax')"
  
  // Test adds product to cart and checks for ajaxCart errors
  // Should pass with 0 ajaxCart errors after the fix
});
```

### What This Tests
1. ✅ Add to cart functionality works
2. ✅ No `window.parent.ajaxCart` undefined errors
3. ✅ Guest checkout is accessible
4. ✅ Checkout page loads without JS errors

## 📈 CI/CD Integration

Add to your CI pipeline:

```yaml
# Example GitHub Actions
- name: Install dependencies
  run: |
    cd tests/playwright
    npm ci
    npx playwright install --with-deps

- name: Run Playwright tests
  run: |
    cd tests/playwright
    npm test
    
- name: Upload test results
  uses: actions/upload-artifact@v3
  with:
    name: playwright-report
    path: tests/playwright/playwright-report/
```

## 🐛 Troubleshooting

### Tests failing with timeout
- Increase timeout in `playwright.config.js`
- Check website is accessible
- Verify network connection

### Browser not installing
```bash
npx playwright install --with-deps
```

### Tests pass locally but fail on CI
- Check BASE_URL environment variable
- Ensure CI has sufficient resources
- Add more wait time for slow networks

## 📞 Support

For issues with tests, check:
1. Website is accessible
2. Products exist in catalog (Pilot, Casio)
3. Guest checkout is enabled in admin

## 📄 License

MIT License - Techno Stationery

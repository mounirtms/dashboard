# Quick Reference - Playwright Tests

## Installation (One-time)

```bash
cd /home/dev/public_html
npm install
npx playwright install chromium firefox
```

## Running Tests

### All Tests
```bash
npm run test:e2e
```

### Specific Test Suites
```bash
npm run test:checkout      # Checkout flow tests
npm run test:cart          # Cart & gift card tests
npm run test:orders        # Order management tests
npm run test:auth          # Authentication tests
npm run test:catalog       # Catalog & product tests
npm run test:responsive    # Responsive & accessibility tests
```

### Interactive Mode
```bash
npm run test:ui            # Playwright UI mode
npm run test:headed        # See browser while testing
```

### View Reports
```bash
npm run test:report        # Open HTML test report
```

## Test Files Structure

```
tests/playwright/
├── checkout/
│   └── checkout-flow.spec.js          # TC001-TC006
├── cart/
│   └── gift-card-cart.spec.js         # TC101-TC303
├── orders/
│   └── order-management.spec.js       # TC401-TC602
├── auth/
│   └── authentication.spec.js         # TC701-TC904
├── catalog/
│   └── products.spec.js               # TC1001-TC1302
└── responsive/
    └── accessibility.spec.js          # TC1401-TC1704
```

## Test Summary

| Suite | Tests | Focus |
|-------|-------|-------|
| Checkout | 6 | Complete checkout flow, gift card, validation |
| Cart | 13 | Add/remove items, gift card, coupons |
| Orders | 6 | History, details, tracking, guest orders |
| Auth | 11 | Login, register, password, account |
| Catalog | 13 | Browse, search, product details, wishlist |
| Responsive | 9 | Mobile/tablet/desktop, accessibility |
| **Total** | **58** | **Full e-commerce coverage** |

## Common Issues

### Test fails with "Element not found"
- Add `await page.waitForTimeout(2000)` before the action
- Check if element selector is correct
- Verify page has loaded: `await page.waitForLoadState('networkidle')`

### Login fails
- Verify credentials in test match actual test account
- Check if account is active in Magento admin

### Gift card test fails
- Verify gift card code `TECHB25000183` exists and is active
- Check if customer is logged in (gift cards require login)

### Checkout times out
- Use `test.slow()` at start of test
- Increase timeout in config: `timeout: 120000`
- Check if shipping methods are configured

## Customizing Tests

### Change Base URL
```bash
export BASE_URL=https://your-domain.com
npm run test:e2e
```

### Run Single Test
```bash
npx playwright test -g "TC001"
```

### Run with Specific Browser
```bash
npx playwright test --project=chromium
```

### Run with Retries
```bash
npx playwright test --retries=2
```

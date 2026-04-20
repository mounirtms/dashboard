# TechnoStationery Magento 2 - Playwright Test Suite

Comprehensive end-to-end test suite for the TechnoStationery e-commerce platform built with Playwright.

## Test Coverage

### Checkout Flow Tests (`tests/playwright/checkout/`)
- Complete checkout process from cart to order confirmation
- Guest and logged-in user checkout
- Multiple products in checkout
- Gift card application during checkout
- Form validation and required fields
- Shipping method selection and pricing

### Cart & Gift Card Tests (`tests/playwright/cart/`)
- Add to cart functionality
- Cart quantity updates
- Item removal from cart
- Cart totals display
- Gift card apply/check/remove operations
- Coupon code operations
- Guest vs logged-in user behavior

### Order Management Tests (`tests/playwright/orders/`)
- Order history viewing
- Order details verification
- Reorder functionality
- Order status labels in French
- Guest order tracking

### Authentication Tests (`tests/playwright/auth/`)
- Login with valid/invalid credentials
- Customer registration
- Password recovery
- Account management
- Address book operations
- Logout functionality

### Catalog & Product Tests (`tests/playwright/catalog/`)
- Homepage and category browsing
- Product sorting and pagination
- Product details page
- Search functionality
- Wishlist operations

### Responsive & Accessibility Tests (`tests/playwright/responsive/`)
- Mobile, tablet, desktop layouts
- Checkout field readability on mobile
- Button tap target sizes
- Keyboard navigation
- Color contrast verification

## Prerequisites

```bash
# Install Node.js (v16 or higher)
# Install Playwright and browsers
cd /home/dev/public_html
npm install
npx playwright install chromium firefox
```

## Quick Start

```bash
# Run all tests
npx playwright test

# Run specific test suite
npx playwright test checkout
npx playwright test cart
npx playwright test auth

# Run with UI
npx playwright test --ui

# Run in headed mode (see browser)
npx playwright test --headed

# Run specific test file
npx playwright test checkout-flow.spec.js

# Run with specific browser
npx playwright test --project=chromium

# Run with retries
npx playwright test --retries=2
```

## Test Configuration

### Environment Variables

```bash
# Set base URL (default: https://dev.technostationery.com)
export BASE_URL=https://dev.technostationery.com

# Run tests
npx playwright test
```

### Credentials Setup

Create a `.env.test` file:

```bash
# Test account credentials
TEST_EMAIL=test@example.com
TEST_PASSWORD=Test123456

# Base URL
BASE_URL=https://dev.technostationery.com
```

## Test Cases Summary

| ID | Description | Status |
|----|-------------|--------|
| TC001 | Guest user complete checkout | Ready |
| TC002 | Logged-in user checkout | Ready |
| TC003 | Multiple products checkout | Ready |
| TC004 | Checkout with gift card | Ready |
| TC005 | Checkout validation | Ready |
| TC006 | Shipping method selection | Ready |
| TC101 | Add product to cart | Ready |
| TC102 | View cart page | Ready |
| TC103 | Update cart quantity | Ready |
| TC104 | Remove from cart | Ready |
| TC105 | Cart totals display | Ready |
| TC201 | Apply valid gift card | Ready |
| TC202 | Check gift card balance | Ready |
| TC203 | Invalid gift card error | Ready |
| TC204 | Duplicate gift card handling | Ready |
| TC205 | Guest gift card hint | Ready |
| TC301-303 | Coupon operations | Ready |
| TC401-403 | Order history | Ready |
| TC501-502 | Order status | Ready |
| TC601-602 | Guest tracking | Ready |
| TC701-707 | Authentication | Ready |
| TC801-803 | Password recovery | Ready |
| TC901-904 | Account management | Ready |
| TC1001-1004 | Product browsing | Ready |
| TC1101-1105 | Product details | Ready |
| TC1201-1204 | Search functionality | Ready |
| TC1301-1302 | Wishlist | Ready |
| TC1401-1402 | Responsive checkout | Ready |
| TC1501-1503 | Mobile-specific | Ready |
| TC1601 | Tablet layout | Ready |
| TC1701-1704 | Accessibility | Ready |

## Running Tests

### Full Test Suite

```bash
npx playwright test
```

### Parallel Execution

```bash
# Run in parallel (default)
npx playwright test --workers=4

# Run sequentially
npx playwright test --workers=1
```

### Generate Reports

```bash
# HTML report
npx playwright show-report tests/playwright-report

# JSON results
cat tests/playwright-results.json
```

### Debug Mode

```bash
# Slow down execution
npx playwright test --headed --timeout=120000

# Debug specific test
npx playwright test --debug checkout-flow.spec.js
```

## CI/CD Integration

### GitHub Actions Example

```yaml
name: E2E Tests
on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-node@v3
        with:
          node-version: '18'
      - run: npm install
      - run: npx playwright install --with-deps
      - run: npx playwright test
      - uses: actions/upload-artifact@v3
        if: always()
        with:
          name: playwright-report
          path: tests/playwright-report/
```

## Adding New Tests

1. Create a new `.spec.js` file in the appropriate directory
2. Use the `test.describe` and `test` pattern
3. Follow naming convention: `TC{XXX}: Description`
4. Add test case to the summary table above

## Notes

- Tests use the French locale (fr-FR)
- Timezone set to Africa/Algiers
- Screenshots captured on failure
- Videos retained on failure
- Traces collected on retry

## Troubleshooting

### Tests timeout
- Increase timeout in config: `timeout: 120000`
- Use `test.slow()` for longer tests

### Elements not found
- Add `await page.waitForTimeout(2000)` for AJAX calls
- Use `waitForSelector` with timeout
- Check if login credentials are valid

### Network issues
- Use `await page.waitForLoadState('networkidle')`
- Add retries: `npx playwright test --retries=2`

## Support

For issues or questions about the test suite, check the test output and reports.

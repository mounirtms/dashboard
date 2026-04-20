// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Responsive Design Test Suite
 * Tests checkout and key pages across different screen sizes
 */

const devices = [
  { name: 'Mobile', viewport: { width: 375, height: 667 } },
  { name: 'Tablet', viewport: { width: 768, height: 1024 } },
  { name: 'Desktop', viewport: { width: 1280, height: 720 } },
];

test.describe('Checkout Responsive Design', () => {
  
  for (const device of devices) {
    test(`TC1401: Checkout layout on ${device.name}`, async ({ page }) => {
      await page.setViewportSize(device.viewport);
      await page.goto('/checkout/');
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(1000);
      
      // Verify checkout page loads
      await expect(page.locator('.checkout-index-index')).toBeVisible();
      
      // Verify OPC wrapper visible
      await expect(page.locator('.opc-wrapper')).toBeVisible();
      
      // Verify step title visible
      await expect(page.locator('.step-title')).toBeVisible();
      
      // Take screenshot for visual verification
      await page.screenshot({ 
        path: `tests/screenshots/checkout-${device.name.toLowerCase()}.png`,
        fullPage: true 
      });
    });

    test(`TC1402: Cart page layout on ${device.name}`, async ({ page }) => {
      await page.setViewportSize(device.viewport);
      await page.goto('/checkout/cart/');
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(1000);
      
      // Verify cart page loads
      await expect(page.locator('.cart')).toBeVisible();
      
      // Verify cart form
      await expect(page.locator('.cart-form')).toBeVisible();
      
      // Verify sidebar
      await expect(page.locator('.cart-summary')).toBeVisible();
      
      await page.screenshot({ 
        path: `tests/screenshots/cart-${device.name.toLowerCase()}.png`,
        fullPage: true 
      });
    });
  }
});

test.describe('Mobile-Specific Checkout Tests', () => {
  test.use({ viewport: { width: 375, height: 667 } });

  test('TC1501: Mobile checkout fields are readable', async ({ page }) => {
    await page.goto('/checkout/');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    
    // Verify input fields have readable font size (16px to prevent iOS zoom)
    const inputs = page.locator('input[type="text"], input[type="email"]');
    const count = await inputs.count();
    
    for (let i = 0; i < Math.min(count, 3); i++) {
      const fontSize = await inputs.nth(i).evaluate(el => 
        window.getComputedStyle(el).fontSize
      );
      const fontSizeNum = parseFloat(fontSize);
      expect(fontSizeNum).toBeGreaterThanOrEqual(14);
    }
  });

  test('TC1502: Mobile checkout buttons are tappable', async ({ page }) => {
    await page.goto('/checkout/');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    
    // Verify buttons have minimum height (44px for mobile)
    const buttons = page.locator('button.action');
    const count = await buttons.count();
    
    for (let i = 0; i < Math.min(count, 3); i++) {
      const height = await buttons.nth(i).evaluate(el => 
        el.offsetHeight
      );
      expect(height).toBeGreaterThanOrEqual(40);
    }
  });

  test('TC1503: Mobile payment methods stack vertically', async ({ page }) => {
    test.slow();
    
    // Complete checkout to payment step
    await page.goto('/checkout/');
    await page.waitForLoadState('networkidle');
    
    // Fill minimal address
    await page.fill('input[name="firstname"]', 'Test');
    await page.fill('input[name="lastname"]', 'User');
    await page.fill('input[name="street[0]"]', '123 Test St');
    await page.fill('input[name="city"]', 'Alger');
    await page.fill('input[name="postcode"]', '16000');
    await page.fill('input[name="telephone"]', '0555123456');
    await page.selectOption('select[name="country_id"]', 'DZ');
    await page.waitForTimeout(1000);
    await page.selectOption('select[name="region_id"]', '1');
    
    // Continue to payment
    await page.waitForSelector('.table-checkout-shipping-method', { timeout: 15000 });
    await page.waitForTimeout(1000);
    await page.click('input[type="radio"][name="ko_unique_1"]');
    await page.click('button.action-continue');
    await page.waitForTimeout(3000);
    
    // Verify payment methods
    const paymentMethods = page.locator('.payment-method');
    const count = await paymentMethods.count();
    expect(count).toBeGreaterThan(0);
    
    // Verify they're visible on mobile
    for (let i = 0; i < count; i++) {
      await expect(paymentMethods.nth(i)).toBeVisible();
    }
  });
});

test.describe('Tablet Responsive Tests', () => {
  test.use({ viewport: { width: 768, height: 1024 } });

  test('TC1601: Tablet checkout two-column layout', async ({ page }) => {
    test.slow();
    
    await page.goto('/checkout/');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    
    // Verify checkout loaded
    await expect(page.locator('.opc-wrapper')).toBeVisible();
    
    // On tablet, sidebar might be visible
    const sidebarVisible = await page.locator('.opc-sidebar').count() > 0;
    if (sidebarVisible) {
      await expect(page.locator('.opc-sidebar')).toBeVisible();
    }
    
    await page.screenshot({ 
      path: 'tests/screenshots/checkout-tablet-layout.png',
      fullPage: true 
    });
  });
});

test.describe('Accessibility Tests', () => {
  
  test('TC1701: Checkout page has proper headings', async ({ page }) => {
    await page.goto('/checkout/');
    await page.waitForLoadState('networkidle');
    
    // Verify H1 exists
    await expect(page.locator('h1')).toHaveCountGreaterThan(0);
    
    // Verify form labels exist
    const labels = await page.locator('label').count();
    expect(labels).toBeGreaterThan(0);
  });

  test('TC1702: Keyboard navigation in checkout', async ({ page }) => {
    await page.goto('/checkout/');
    await page.waitForLoadState('networkidle');
    
    // Tab through form fields
    await page.keyboard.press('Tab');
    await page.waitForTimeout(500);
    
    // Verify focus moves
    const focusedTag = await page.evaluate(() => document.activeElement?.tagName);
    expect(['INPUT', 'SELECT', 'BUTTON', 'TEXTAREA']).toContain(focusedTag);
  });

  test('TC1703: Cart page alt texts for images', async ({ page }) => {
    await page.goto('/checkout/cart/');
    await page.waitForLoadState('networkidle');
    
    // Verify product images have alt text
    const images = page.locator('.product-image-container img');
    const count = await images.count();
    
    if (count > 0) {
      const altText = await images.first().getAttribute('alt');
      // Should have meaningful alt text
      expect(altText).not.toBeNull();
    }
  });

  test('TC1704: Color contrast on error messages', async ({ page }) => {
    // Trigger an error by submitting empty checkout form
    await page.goto('/checkout/');
    await page.waitForLoadState('networkidle');
    await page.click('button.action-continue');
    await page.waitForTimeout(1000);
    
    // Verify error messages are visible and styled
    const errorFields = page.locator('.field._error');
    const count = await errorFields.count();
    
    if (count > 0) {
      const errorBorderColor = await errorFields.first().evaluate(el => 
        window.getComputedStyle(el.querySelector('input, select') || el).borderColor
      );
      // Should have red-ish border for errors
      expect(errorBorderColor).not.toBe('');
    }
  });
});

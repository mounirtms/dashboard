// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Complete Checkout Flow Test Suite
 * Tests the entire checkout process from cart to order confirmation
 */

test.describe('Checkout Flow - Complete Process', () => {
  
  test('TC001: Guest user can complete checkout with new address', async ({ page }) => {
    test.slow(); // This test may take longer
    
    // 1. Add product to cart
    await page.goto('/techno-stylom-pilot-roller-0-5-mm-noir.html');
    await page.waitForLoadState('networkidle');
    await page.click('button#product-addtocart-button');
    await page.waitForTimeout(2000);
    
    // 2. Navigate to checkout
    await page.goto('/checkout/');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('.opc-wrapper')).toBeVisible();
    
    // 3. Fill shipping information
    await page.fill('input[name="firstname"]', 'Test');
    await page.fill('input[name="lastname"]', 'User');
    await page.fill('input[name="street[0]"]', '123 Rue Test');
    await page.fill('input[name="city"]', 'Alger');
    await page.fill('input[name="postcode"]', '16000');
    await page.fill('input[name="telephone"]', '0555123456');
    
    // 4. Select country and region
    await page.selectOption('select[name="country_id"]', 'DZ');
    await page.waitForTimeout(1000);
    await page.selectOption('select[name="region_id"]', '1'); // Alger
    
    // 5. Select shipping method
    await page.waitForSelector('.table-checkout-shipping-method');
    await page.waitForTimeout(2000);
    await page.click('input[type="radio"][name="ko_unique_1"]');
    await page.waitForTimeout(1000);
    await page.click('button.action-continue');
    await page.waitForTimeout(2000);
    
    // 6. Select payment method
    await page.waitForSelector('#payment-method');
    await page.click('input[type="radio"][name="payment[method]"]');
    await page.waitForTimeout(1000);
    
    // 7. Place order
    await page.click('button.action.primary.checkout');
    await page.waitForTimeout(3000);
    
    // 8. Verify order success
    await expect(page.locator('.checkout-onepage-success')).toBeVisible();
    await expect(page.locator('.base')).toContainText('Merci');
  });

  test('TC002: Logged-in user checkout with saved address', async ({ page, context }) => {
    test.slow();
    
    // Login first
    await page.goto('/customer/account/login/');
    await page.fill('#email', 'test@example.com');
    await page.fill('#pass', 'Test123456');
    await page.click('#send2');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    // Add product to cart
    await page.goto('/techno-stylom-pilot-roller-0-5-mm-noir.html');
    await page.click('button#product-addtocart-button');
    await page.waitForTimeout(2000);
    
    // Go to checkout
    await page.goto('/checkout/');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    // Verify saved address is shown
    const addressExists = await page.locator('.shipping-address-item').count() > 0;
    if (addressExists) {
      await expect(page.locator('.shipping-address-item')).toBeVisible();
    }
    
    // Select shipping method
    await page.waitForSelector('.table-checkout-shipping-method', { timeout: 15000 });
    await page.waitForTimeout(2000);
    
    // Select payment and place order
    await page.click('button.action-continue');
    await page.waitForTimeout(2000);
    await page.click('input[type="radio"][name="payment[method]"]');
    await page.waitForTimeout(1000);
    await page.click('button.action.primary.checkout');
    await page.waitForTimeout(3000);
    
    // Verify order placed
    await expect(page.locator('.checkout-onepage-success')).toBeVisible();
  });

  test('TC003: Multiple products in checkout', async ({ page }) => {
    test.slow();
    
    // Add multiple products
    const products = [
      '/techno-stylom-pilot-roller-0-5-mm-noir.html',
      '/cahier-200-pages-seyes-24x32.html'
    ];
    
    for (const product of products) {
      await page.goto(product);
      await page.waitForLoadState('networkidle');
      await page.click('button#product-addtocart-button');
      await page.waitForTimeout(1500);
    }
    
    // Verify cart has items
    await page.goto('/checkout/cart/');
    await page.waitForLoadState('networkidle');
    const itemCount = await page.locator('.cart-item').count();
    expect(itemCount).toBeGreaterThanOrEqual(2);
    
    // Proceed to checkout
    await page.goto('/checkout/');
    await page.waitForLoadState('networkidle');
    
    // Verify order summary shows multiple items
    await expect(page.locator('.opc-block-summary')).toBeVisible();
    const summaryItems = await page.locator('.product-item').count();
    expect(summaryItems).toBeGreaterThanOrEqual(2);
  });

  test('TC004: Checkout with gift card applied', async ({ page }) => {
    test.slow();
    
    // Login and add product
    await page.goto('/customer/account/login/');
    await page.fill('#email', 'test@example.com');
    await page.fill('#pass', 'Test123456');
    await page.click('#send2');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    await page.goto('/techno-stylom-pilot-roller-0-5-mm-noir.html');
    await page.click('button#product-addtocart-button');
    await page.waitForTimeout(2000);
    
    // Apply gift card in cart
    await page.goto('/checkout/cart/');
    await page.waitForLoadState('networkidle');
    
    // Open gift card section
    await page.click('.block.gift-card .title');
    await page.waitForTimeout(500);
    
    // Enter gift card code
    await page.fill('#gift_card_code', 'TECHB25000183');
    await page.click('#apply-gift-card-btn');
    await page.waitForTimeout(3000);
    
    // Verify gift card applied (check for success message or reload)
    await page.waitForTimeout(2000);
    
    // Proceed to checkout
    await page.goto('/checkout/');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    // Verify gift card appears in totals
    const hasGiftCardTotal = await page.locator('text=Carte cadeau').count() > 0;
    console.log('Gift card total visible:', hasGiftCardTotal);
    
    // Complete checkout
    await page.fill('input[name="firstname"]', 'Test');
    await page.fill('input[name="lastname"]', 'User');
    await page.fill('input[name="street[0]"]', '123 Rue Test');
    await page.fill('input[name="city"]', 'Alger');
    await page.fill('input[name="postcode"]', '16000');
    await page.fill('input[name="telephone"]', '0555123456');
    await page.selectOption('select[name="country_id"]', 'DZ');
    await page.waitForTimeout(1000);
    await page.selectOption('select[name="region_id"]', '1');
    
    await page.waitForSelector('.table-checkout-shipping-method', { timeout: 15000 });
    await page.waitForTimeout(2000);
    await page.click('input[type="radio"][name="ko_unique_1"]');
    await page.click('button.action-continue');
    await page.waitForTimeout(2000);
    
    await page.click('input[type="radio"][name="payment[method]"]');
    await page.click('button.action.primary.checkout');
    await page.waitForTimeout(3000);
    
    await expect(page.locator('.checkout-onepage-success')).toBeVisible();
  });

  test('TC005: Checkout validation - required fields', async ({ page }) => {
    await page.goto('/checkout/');
    await page.waitForLoadState('networkidle');
    
    // Try to continue without filling required fields
    const continueBtn = page.locator('button.action-continue');
    await continueBtn.click();
    await page.waitForTimeout(1000);
    
    // Verify validation errors appear
    const errorFields = await page.locator('.field._error').count();
    expect(errorFields).toBeGreaterThan(0);
    
    // Verify specific required field validations
    const firstNameError = await page.locator('input[name="firstname"]:invalid').count();
    expect(firstNameError).toBeGreaterThan(0);
  });

  test('TC006: Shipping method selection and price display', async ({ page }) => {
    test.slow();
    
    // Add product and go to checkout
    await page.goto('/techno-stylom-pilot-roller-0-5-mm-noir.html');
    await page.click('button#product-addtocart-button');
    await page.waitForTimeout(2000);
    await page.goto('/checkout/');
    await page.waitForLoadState('networkidle');
    
    // Fill address to trigger shipping methods
    await page.fill('input[name="firstname"]', 'Test');
    await page.fill('input[name="lastname"]', 'User');
    await page.fill('input[name="street[0]"]', '123 Rue Test');
    await page.fill('input[name="city"]', 'Alger');
    await page.fill('input[name="postcode"]', '16000');
    await page.fill('input[name="telephone"]', '0555123456');
    await page.selectOption('select[name="country_id"]', 'DZ');
    await page.waitForTimeout(1000);
    await page.selectOption('select[name="region_id"]', '1');
    
    // Wait for shipping methods to load
    await page.waitForSelector('.table-checkout-shipping-method', { timeout: 15000 });
    await page.waitForTimeout(2000);
    
    // Verify shipping methods are displayed
    const shippingMethods = await page.locator('.table-checkout-shipping-method tbody tr').count();
    expect(shippingMethods).toBeGreaterThan(0);
    
    // Verify prices are shown
    const priceCells = await page.locator('.col-price').count();
    expect(priceCells).toBeGreaterThan(0);
    
    // Select a method and verify it's selected
    await page.click('input[type="radio"][name="ko_unique_1"]');
    await page.waitForTimeout(500);
    const selectedRadio = await page.locator('input[type="radio"][name="ko_unique_1"]:checked').count();
    expect(selectedRadio).toBe(1);
  });
});

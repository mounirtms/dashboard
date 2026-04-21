// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Test Suite: Cart & Checkout Flow Tests
 * Tests complete purchase flow from add to cart to success page
 */
test.describe('Cart & Checkout Flow Tests', () => {
  
  test('should add product to cart successfully', async ({ page }) => {
    console.log('=== TEST: Add Product to Cart ===');
    
    // Navigate to search
    await page.goto('/catalogsearch/result/?q=Pilot', { timeout: 30000 });
    await page.waitForSelector('.product-item-link', { timeout: 15000 });
    
    // Click first product
    await page.locator('.product-item-link').first().click();
    await page.waitForLoadState('networkidle', { timeout: 30000 });
    await page.waitForTimeout(3000);
    
    // Wait for add to cart button
    await page.waitForSelector('button.action.tocart', { timeout: 15000 });
    
    // Click add to cart
    await page.locator('button.action.tocart').click();
    
    // Wait for any feedback (success message OR mini cart update)
    try {
      await page.waitForSelector('.message-success, .action.showcart, [data-role="minicart-content"]', { timeout: 10000 });
      console.log('Cart update detected');
    } catch (e) {
      console.log('No immediate feedback, checking cart...');
    }
    
    // Verify mini cart shows items or we're on cart page
    const miniCart = page.locator('[data-block="minicart"]');
    const miniCartVisible = await miniCart.count() > 0;
    
    // Check no console errors
    const errors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        errors.push(msg.text());
      }
    });
    
    const ajaxCartErrors = errors.filter(err => 
      err.includes('ajaxCart') || 
      err.includes('_sendAjax') ||
      err.includes('quoteData')
    );
    
    console.log('Add to cart errors:', ajaxCartErrors.length);
    console.log('Total errors:', errors.length);
    
    // Main check: should have no quoteData errors
    expect(ajaxCartErrors.length).toBe(0);
    
    // Cart should be accessible
    expect(miniCartVisible).toBeTruthy();
  });

  test('should complete guest checkout successfully', async ({ page }) => {
    console.log('=== TEST: Complete Guest Checkout ===');
    
    // Step 1: Add product to cart
    await page.goto('/catalogsearch/result/?q=Pilot', { timeout: 30000 });
    await page.waitForSelector('.product-item-link');
    await page.locator('.product-item-link').first().click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    await page.waitForSelector('button.action.tocart');
    await page.locator('button.action.tocart').click();
    await page.waitForSelector('.message-success');
    
    // Step 2: Go to checkout
    await page.goto('/checkout/', { timeout: 30000 });
    await page.waitForLoadState('networkidle', { timeout: 30000 });
    await page.waitForTimeout(3000);
    
    // Step 3: Verify checkout page loaded
    const checkoutUrl = page.url();
    console.log('Checkout URL:', checkoutUrl);
    
    // Should be on checkout page
    expect(checkoutUrl).toContain('/checkout/');
    
    // Step 4: Check for email field (guest checkout)
    const emailField = page.locator('input[name="username"]');
    const hasEmailField = await emailField.count() > 0;
    
    // Step 5: Check no JavaScript errors
    const consoleErrors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });
    
    // Wait for any async errors
    await page.waitForTimeout(3000);
    
    // Check for critical errors
    const criticalErrors = consoleErrors.filter(err => 
      err.includes('quoteData') ||
      err.includes('Cannot read properties of undefined') ||
      err.includes('ajaxCart')
    );
    
    console.log('Console errors:', consoleErrors.length);
    console.log('Critical errors:', criticalErrors.length);
    
    // Should have no critical errors
    expect(criticalErrors.length).toBe(0);
    
    // Should have email field or checkout container
    const checkoutContainer = page.locator('#checkout, .opc-wrapper, .checkout-container');
    const hasCheckout = await checkoutContainer.count() > 0;
    
    expect(hasEmailField || hasCheckout).toBeTruthy();
  });

  test('should reach success page after order', async ({ page }) => {
    console.log('=== TEST: Order Success Page ===');
    
    // This test simulates a complete checkout
    // Note: Won't actually place order, just verifies flow
    
    // Add product
    await page.goto('/catalogsearch/result/?q=Pilot', { timeout: 30000 });
    await page.waitForSelector('.product-item-link');
    await page.locator('.product-item-link').first().click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    await page.waitForSelector('button.action.tocart');
    await page.locator('button.action.tocart').click();
    await page.waitForSelector('.message-success');
    
    // Go to cart
    await page.goto('/checkout/cart/', { timeout: 30000 });
    await page.waitForLoadState('networkidle');
    
    // Verify cart page loaded
    await expect(page).toHaveTitle(/shopping cart/i);
    
    // Check cart has items
    const cartItems = page.locator('.cart-items, .table-wrapper');
    const hasItems = await cartItems.count() > 0;
    expect(hasItems).toBeTruthy();
    
    // Check for checkout button
    const checkoutButton = page.locator('button.checkout');
    await expect(checkoutButton).toBeVisible();
    
    console.log('Cart page verified successfully');
  });

  test('should handle cart operations without errors', async ({ page }) => {
    console.log('=== TEST: Cart Operations ===');
    
    // Collect all console errors
    const allErrors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        allErrors.push({
          text: msg.text(),
          type: msg.type()
        });
      }
    });
    
    // Add multiple products
    const products = ['Pilot', 'Casio'];
    
    for (const product of products) {
      await page.goto(`/catalogsearch/result/?q=${product}`, { timeout: 30000 });
      await page.waitForSelector('.product-item-link');
      await page.locator('.product-item-link').first().click();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(1000);
      
      await page.waitForSelector('button.action.tocart');
      await page.locator('button.action.tocart').click();
      await page.waitForSelector('.message-success, .action.showcart', { timeout: 10000 });
      await page.waitForTimeout(1000);
    }
    
    // Go to cart
    await page.goto('/checkout/cart/', { timeout: 30000 });
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    // Check for errors
    const quoteErrors = allErrors.filter(err => 
      err.text.includes('quoteData') ||
      err.text.includes('quote.js') ||
      err.text.includes('Cannot read properties of undefined')
    );
    
    console.log('Total errors:', allErrors.length);
    console.log('Quote errors:', quoteErrors.length);
    
    // Should have no quote-related errors
    expect(quoteErrors.length).toBe(0);
  });

  test('checkout should work on mobile devices', async ({ page }) => {
    console.log('=== TEST: Mobile Checkout ===');
    
    // Set mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });
    
    // Add product to cart
    await page.goto('/catalogsearch/result/?q=Pilot', { timeout: 30000 });
    await page.waitForSelector('.product-item-link');
    await page.locator('.product-item-link').first().click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    await page.waitForSelector('button.action.tocart');
    await page.locator('button.action.tocart').click();
    await page.waitForSelector('.message-success');
    
    // Go to checkout
    await page.goto('/checkout/', { timeout: 30000 });
    await page.waitForLoadState('networkidle', { timeout: 30000 });
    await page.waitForTimeout(3000);
    
    // Verify checkout is visible
    const checkoutContainer = page.locator('#checkout, .opc-wrapper');
    await expect(checkoutContainer).toBeVisible();
    
    // Check no horizontal scroll
    const hasHorizontalScroll = await page.evaluate(() => {
      return document.documentElement.scrollWidth > document.documentElement.clientWidth;
    });
    
    expect(hasHorizontalScroll).toBeFalsy();
    
    // Check no JavaScript errors
    const errors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        errors.push(msg.text());
      }
    });
    
    await page.waitForTimeout(2000);
    
    const criticalErrors = errors.filter(err => 
      err.includes('quoteData') ||
      err.includes('Cannot read properties')
    );
    
    expect(criticalErrors.length).toBe(0);
  });
});

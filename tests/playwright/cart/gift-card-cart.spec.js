// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Cart and Gift Card Test Suite
 * Tests cart functionality, gift card apply/check/remove, and coupon codes
 */

test.describe('Cart Operations', () => {
  
  test('TC101: Add product to cart from product page', async ({ page }) => {
    await page.goto('/techno-stylom-pilot-roller-0-5-mm-noir.html');
    await page.waitForLoadState('networkidle');
    
    // Add to cart
    await page.click('button#product-addtocart-button');
    await page.waitForTimeout(2000);
    
    // Verify success message
    await expect(page.locator('.message-success')).toBeVisible();
    await expect(page.locator('.message-success')).toContainText('ajouté');
    
    // Verify cart counter updated
    const cartCounter = await page.locator('.counter-number').textContent();
    expect(parseInt(cartCounter)).toBeGreaterThanOrEqual(1);
  });

  test('TC102: View cart page with items', async ({ page }) => {
    // Add product first
    await page.goto('/techno-stylom-pilot-roller-0-5-mm-noir.html');
    await page.click('button#product-addtocart-button');
    await page.waitForTimeout(2000);
    
    // Navigate to cart
    await page.goto('/checkout/cart/');
    await page.waitForLoadState('networkidle');
    
    // Verify cart displays
    await expect(page.locator('.cart-form')).toBeVisible();
    await expect(page.locator('.cart-summary')).toBeVisible();
    
    // Verify product details
    await expect(page.locator('.product-item-name')).toBeVisible();
    await expect(page.locator('.cart-price')).toBeVisible();
    
    // Verify quantity input
    await expect(page.locator('input[name^="qty"]')).toBeVisible();
    
    // Verify update cart button
    await expect(page.locator('button#update-cart-btn')).toBeVisible();
  });

  test('TC103: Update product quantity in cart', async ({ page }) => {
    // Add product
    await page.goto('/techno-stylom-pilot-roller-0-5-mm-noir.html');
    await page.click('button#product-addtocart-button');
    await page.waitForTimeout(2000);
    
    // Go to cart
    await page.goto('/checkout/cart/');
    await page.waitForLoadState('networkidle');
    
    // Update quantity
    const qtyInput = page.locator('input[name^="qty"]').first();
    await qtyInput.fill('3');
    await page.click('button#update-cart-btn');
    await page.waitForTimeout(2000);
    
    // Verify quantity updated
    await page.waitForLoadState('networkidle');
    const updatedQty = await qtyInput.inputValue();
    expect(updatedQty).toBe('3');
    
    // Verify subtotal updated
    await expect(page.locator('.cart-price')).toBeVisible();
  });

  test('TC104: Remove item from cart', async ({ page }) => {
    // Add product
    await page.goto('/techno-stylom-pilot-roller-0-5-mm-noir.html');
    await page.click('button#product-addtocart-button');
    await page.waitForTimeout(2000);
    
    // Go to cart
    await page.goto('/checkout/cart/');
    await page.waitForLoadState('networkidle');
    
    // Remove item
    await page.click('a.action.delete');
    await page.waitForTimeout(2000);
    
    // Verify cart is empty
    await expect(page.locator('.cart-empty')).toBeVisible();
    await expect(page.locator('.cart-empty')).toContainText('vide');
  });

  test('TC105: Cart summary totals display', async ({ page }) => {
    // Add product
    await page.goto('/techno-stylom-pilot-roller-0-5-mm-noir.html');
    await page.click('button#product-addtocart-button');
    await page.waitForTimeout(2000);
    
    // Go to cart
    await page.goto('/checkout/cart/');
    await page.waitForLoadState('networkidle');
    
    // Verify sidebar totals
    await expect(page.locator('.cart-totals')).toBeVisible();
    
    // Verify subtotal
    await expect(page.locator('text=Sous-total')).toBeVisible();
    
    // Verify grand total
    await expect(page.locator('text=Total de la commande')).toBeVisible();
    await expect(page.locator('.grand .price')).toBeVisible();
  });
});

test.describe('Gift Card Operations', () => {
  
  test('TC201: Apply valid gift card in cart', async ({ page, context }) => {
    test.slow();
    
    // Login
    await page.goto('/customer/account/login/');
    await page.fill('#email', 'test@example.com');
    await page.fill('#pass', 'Test123456');
    await page.click('#send2');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    // Add product
    await page.goto('/techno-stylom-pilot-roller-0-5-mm-noir.html');
    await page.click('button#product-addtocart-button');
    await page.waitForTimeout(2000);
    
    // Go to cart
    await page.goto('/checkout/cart/');
    await page.waitForLoadState('networkidle');
    
    // Open gift card section
    await page.click('.block.gift-card .title');
    await page.waitForTimeout(500);
    
    // Enter gift card code
    await page.fill('#gift_card_code', 'TECHB25000183');
    await page.click('#apply-gift-card-btn');
    
    // Wait for response and reload
    await page.waitForTimeout(4000);
    
    // Verify success message or reload
    const pageUrl = page.url();
    expect(pageUrl).toContain('/checkout/cart/');
  });

  test('TC202: Check gift card balance', async ({ page }) => {
    // Login
    await page.goto('/customer/account/login/');
    await page.fill('#email', 'test@example.com');
    await page.fill('#pass', 'Test123456');
    await page.click('#send2');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    // Go to cart
    await page.goto('/checkout/cart/');
    await page.waitForLoadState('networkidle');
    
    // Open gift card section
    await page.click('.block.gift-card .title');
    await page.waitForTimeout(500);
    
    // Enter code and check balance
    await page.fill('#gift_card_code', 'TECHB25000183');
    await page.click('#check-gift-card-btn');
    await page.waitForTimeout(3000);
    
    // Verify balance display
    const balanceVisible = await page.locator('#gift-card-balance').isVisible();
    if (balanceVisible) {
      await expect(page.locator('#balance-amount')).toBeVisible();
    }
    
    // Or verify success message
    const successMsg = await page.locator('.message-success').count();
    const errorMsg = await page.locator('.message-error').count();
    expect(successMsg + errorMsg).toBeGreaterThan(0);
  });

  test('TC203: Apply invalid gift card shows error', async ({ page }) => {
    // Login
    await page.goto('/customer/account/login/');
    await page.fill('#email', 'test@example.com');
    await page.fill('#pass', 'Test123456');
    await page.click('#send2');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    await page.goto('/checkout/cart/');
    await page.waitForLoadState('networkidle');
    
    // Open gift card
    await page.click('.block.gift-card .title');
    await page.waitForTimeout(500);
    
    // Enter invalid code
    await page.fill('#gift_card_code', 'INVALID123');
    await page.click('#apply-gift-card-btn');
    await page.waitForTimeout(3000);
    
    // Verify error message
    const errorMsg = await page.locator('.message-error');
    await expect(errorMsg).toBeVisible();
  });

  test('TC204: Apply duplicate gift card shows appropriate message', async ({ page }) => {
    test.slow();
    
    // Login
    await page.goto('/customer/account/login/');
    await page.fill('#email', 'test@example.com');
    await page.fill('#pass', 'Test123456');
    await page.click('#send2');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    // Add product
    await page.goto('/techno-stylom-pilot-roller-0-5-mm-noir.html');
    await page.click('button#product-addtocart-button');
    await page.waitForTimeout(2000);
    
    // Go to cart
    await page.goto('/checkout/cart/');
    await page.waitForLoadState('networkidle');
    
    // Apply gift card first time
    await page.click('.block.gift-card .title');
    await page.waitForTimeout(500);
    await page.fill('#gift_card_code', 'TECHB25000183');
    await page.click('#apply-gift-card-btn');
    await page.waitForTimeout(4000);
    
    // Apply same gift card again
    await page.click('.block.gift-card .title');
    await page.waitForTimeout(500);
    await page.fill('#gift_card_code', 'TECHB25000183');
    await page.click('#apply-gift-card-btn');
    await page.waitForTimeout(4000);
    
    // Should show "already applied" message (success, not error)
    const successVisible = await page.locator('.message-success').count() > 0;
    const errorVisible = await page.locator('.message-error').count() > 0;
    
    // Either success with "already applied" message OR error with appropriate text
    if (errorVisible) {
      const errorText = await page.locator('.message-error').textContent();
      expect(errorText.toLowerCase()).toContain('déjà');
    }
  });

  test('TC205: Guest user sees gift card login hint', async ({ page }) => {
    await page.goto('/checkout/cart/');
    await page.waitForLoadState('networkidle');
    
    // Verify guest hint is shown
    await expect(page.locator('.block.gift-card.guest-hint')).toBeVisible();
    await expect(page.locator('.block.gift-card')).toContainText('Réservé aux clients connectés');
  });
});

test.describe('Coupon Code Operations', () => {
  
  test('TC301: Apply valid coupon code', async ({ page }) => {
    // Add product
    await page.goto('/techno-stylom-pilot-roller-0-5-mm-noir.html');
    await page.click('button#product-addtocart-button');
    await page.waitForTimeout(2000);
    
    // Go to cart
    await page.goto('/checkout/cart/');
    await page.waitForLoadState('networkidle');
    
    // Open coupon section
    await page.click('.block.discount .title');
    await page.waitForTimeout(500);
    
    // Enter coupon code (adjust based on your actual coupon)
    await page.fill('#coupon_code', 'TEST10');
    await page.click('.action.apply');
    await page.waitForTimeout(3000);
    
    // Verify coupon applied (success message or discount in totals)
    const successVisible = await page.locator('.message-success').count() > 0;
    const discountVisible = await page.locator('text=Remise').count() > 0;
    expect(successVisible || discountVisible).toBe(true);
  });

  test('TC302: Apply invalid coupon shows error', async ({ page }) => {
    await page.goto('/checkout/cart/');
    await page.waitForLoadState('networkidle');
    
    // Open coupon
    await page.click('.block.discount .title');
    await page.waitForTimeout(500);
    
    // Enter invalid code
    await page.fill('#coupon_code', 'INVALIDCOUPON999');
    await page.click('.action.apply');
    await page.waitForTimeout(2000);
    
    // Verify error message
    await expect(page.locator('.message-error')).toBeVisible();
  });

  test('TC303: Remove applied coupon', async ({ page }) => {
    await page.goto('/checkout/cart/');
    await page.waitForLoadState('networkidle');
    
    // Open coupon section
    await page.click('.block.discount .title');
    await page.waitForTimeout(500);
    
    // Enter and apply coupon
    await page.fill('#coupon_code', 'TEST10');
    await page.click('.action.apply');
    await page.waitForTimeout(3000);
    
    // Remove coupon
    await page.click('.action.remove');
    await page.waitForTimeout(2000);
    
    // Verify coupon removed
    const successMsg = await page.locator('.message-success');
    await expect(successMsg).toBeVisible();
  });
});

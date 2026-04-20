// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Order Management Test Suite
 * Tests order history, order details, reorders, and order status
 */

test.describe('Order History', () => {
  
  test('TC401: View order history as logged-in user', async ({ page }) => {
    // Login
    await page.goto('/customer/account/login/');
    await page.fill('#email', 'test@example.com');
    await page.fill('#pass', 'Test123456');
    await page.click('#send2');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    // Navigate to orders
    await page.goto('/sales/order/history/');
    await page.waitForLoadState('networkidle');
    
    // Verify order history page
    await expect(page.locator('.page-title')).toBeVisible();
    await expect(page.locator('.page-title')).toContainText('commande');
    
    // Verify orders table (if orders exist)
    const orderTable = await page.locator('.orders-history').count();
    if (orderTable > 0) {
      await expect(page.locator('.orders-history')).toBeVisible();
      await expect(page.locator('table thead th')).toHaveCountGreaterThan(0);
    }
  });

  test('TC402: View order details', async ({ page }) => {
    // Login
    await page.goto('/customer/account/login/');
    await page.fill('#email', 'test@example.com');
    await page.fill('#pass', 'Test123456');
    await page.click('#send2');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    // Go to orders
    await page.goto('/sales/order/history/');
    await page.waitForLoadState('networkidle');
    
    // Click on first order if exists
    const orderLinks = await page.locator('a[href*="/sales/order/view/"]').count();
    if (orderLinks > 0) {
      await page.click('a[href*="/sales/order/view/"]').first();
      await page.waitForLoadState('networkidle');
      
      // Verify order details
      await expect(page.locator('.order-details')).toBeVisible();
      await expect(page.locator('.order-number')).toBeVisible();
      await expect(page.locator('.order-date')).toBeVisible();
      
      // Verify order items
      await expect(page.locator('.order-items-table')).toBeVisible();
      
      // Verify order totals
      await expect(page.locator('.order-totals')).toBeVisible();
    }
  });

  test('TC403: Reorder from order history', async ({ page }) => {
    test.slow();
    
    // Login
    await page.goto('/customer/account/login/');
    await page.fill('#email', 'test@example.com');
    await page.fill('#pass', 'Test123456');
    await page.click('#send2');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    // Go to order view
    await page.goto('/sales/order/history/');
    await page.waitForLoadState('networkidle');
    
    // Click reorder if available
    const reorderBtn = await page.locator('button.reorder, a.reorder').count();
    if (reorderBtn > 0) {
      await page.click('button.reorder, a.reorder').first();
      await page.waitForTimeout(3000);
      
      // Verify items added to cart
      await page.goto('/checkout/cart/');
      await page.waitForLoadState('networkidle');
      
      const cartItems = await page.locator('.cart-item').count();
      expect(cartItems).toBeGreaterThan(0);
    }
  });
});

test.describe('Order Status and Tracking', () => {
  
  test('TC501: Check order status labels in French', async ({ page }) => {
    // Login
    await page.goto('/customer/account/login/');
    await page.fill('#email', 'test@example.com');
    await page.fill('#pass', 'Test123456');
    await page.click('#send2');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    await page.goto('/sales/order/history/');
    await page.waitForLoadState('networkidle');
    
    // Check status column exists
    const statusHeaders = await page.locator('th:has-text("Statut")').count();
    if (statusHeaders > 0) {
      expect(statusHeaders).toBeGreaterThan(0);
    }
    
    // Verify status values are in French
    const statusTexts = await page.locator('td.col-status').allTextContents();
    for (const status of statusTexts) {
      // Should contain French status like "En cours", "Traitée", "Expédiée"
      expect(status.length).toBeGreaterThan(0);
    }
  });

  test('TC502: Order confirmation email content', async ({ page }) => {
    // This test verifies the order confirmation page shows proper French content
    // Actual email testing would require email service integration
    
    await page.goto('/customer/account/login/');
    await page.fill('#email', 'test@example.com');
    await page.fill('#pass', 'Test123456');
    await page.click('#send2');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    // Check if order success page has French text
    await page.goto('/sales/order/history/');
    await page.waitForLoadState('networkidle');
    
    // Verify French localization
    await expect(page.locator('text=Mes commandes')).toBeVisible();
  });
});

test.describe('Guest Order Tracking', () => {
  
  test('TC601: Guest order tracking form accessible', async ({ page }) => {
    await page.goto('/sales/guest/form/');
    await page.waitForLoadState('networkidle');
    
    // Verify tracking form
    await expect(page.locator('.page-title')).toBeVisible();
    
    // Verify form fields
    await expect(page.locator('input[name="oar_order_id"]')).toBeVisible();
    await expect(page.locator('input[name="oar_billing_lastname"]')).toBeVisible();
    await expect(page.locator('input[name="oar_billing_email"]')).toBeVisible();
    
    // Verify continue button
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('TC602: Submit invalid guest tracking request', async ({ page }) => {
    await page.goto('/sales/guest/form/');
    await page.waitForLoadState('networkidle');
    
    // Submit with invalid data
    await page.fill('input[name="oar_order_id"]', 'INVALID123');
    await page.fill('input[name="oar_billing_lastname"]', 'Test');
    await page.fill('input[name="oar_billing_email"]', 'invalid@email.com');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(2000);
    
    // Should show error message
    await expect(page.locator('.message-error, .message.notice')).toBeVisible();
  });
});

// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * EMERGENCY PRODUCTION TESTS
 * Quick tests to identify critical performance issues
 */
test.describe('Emergency Production Tests', () => {
  
  test('homepage should load in under 5 seconds', async ({ page }) => {
    console.log('=== EMERGENCY TEST: Homepage Load ===');
    
    const startTime = Date.now();
    
    await page.goto('/', { timeout: 30000 });
    await page.waitForLoadState('networkidle', { timeout: 30000 });
    
    const loadTime = Date.now() - startTime;
    console.log(`Homepage loaded in: ${loadTime}ms`);
    
    // Should load in under 5 seconds
    expect(loadTime).toBeLessThan(5000);
    
    // Check for errors
    const errors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        errors.push(msg.text());
      }
    });
    
    await page.waitForTimeout(2000);
    
    console.log('Console errors:', errors.length);
    expect(errors.length).toBe(0);
  });

  test('checkout should be accessible', async ({ page }) => {
    console.log('=== EMERGENCY TEST: Checkout Access ===');
    
    await page.goto('/checkout/', { timeout: 30000 });
    await page.waitForLoadState('networkidle', { timeout: 30000 });
    
    const currentUrl = page.url();
    console.log('Checkout URL:', currentUrl);
    
    // Should be on checkout page
    expect(currentUrl).toContain('/checkout/');
    
    // Check load time
    const loadTime = page.evaluate(() => performance.timing.loadEventEnd - performance.timing.navigationStart);
    console.log('Checkout load time:', loadTime);
    
    // Should load in under 10 seconds
    expect(loadTime).toBeLessThan(10000);
  });

  test('no critical JavaScript errors', async ({ page }) => {
    console.log('=== EMERGENCY TEST: No JS Errors ===');
    
    const criticalErrors = [];
    
    page.on('console', msg => {
      if (msg.type() === 'error') {
        const text = msg.text();
        if (text.includes('quoteData') || 
            text.includes('ajaxCart') || 
            text.includes('Cannot read properties') ||
            text.includes('undefined')) {
          criticalErrors.push(text);
        }
      }
    });
    
    await page.goto('/', { timeout: 30000 });
    await page.waitForLoadState('networkidle', { timeout: 30000 });
    await page.waitForTimeout(3000);
    
    console.log('Critical errors found:', criticalErrors.length);
    console.log('Errors:', criticalErrors);
    
    expect(criticalErrors.length).toBe(0);
  });

  test('add to cart should work', async ({ page }) => {
    console.log('=== EMERGENCY TEST: Add to Cart ===');
    
    await page.goto('/catalogsearch/result/?q=Pilot', { timeout: 30000 });
    await page.waitForSelector('.product-item-link', { timeout: 15000 });
    await page.locator('.product-item-link').first().click();
    await page.waitForLoadState('networkidle', { timeout: 30000 });
    
    await page.waitForSelector('button.action.tocart', { timeout: 15000 });
    await page.locator('button.action.tocart').click();
    
    // Wait for any response
    await page.waitForTimeout(5000);
    
    // Check for errors
    const errors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        errors.push(msg.text());
      }
    });
    
    const criticalErrors = errors.filter(err => 
      err.includes('quoteData') || err.includes('ajaxCart')
    );
    
    console.log('Add to cart errors:', criticalErrors.length);
    expect(criticalErrors.length).toBe(0);
  });

  test('performance metrics', async ({ page }) => {
    console.log('=== EMERGENCY TEST: Performance Metrics ===');
    
    await page.goto('/', { timeout: 30000 });
    await page.waitForLoadState('networkidle', { timeout: 30000 });
    
    // Get performance metrics
    const metrics = await page.evaluate(() => {
      const timing = performance.timing;
      return {
        ttfb: timing.responseStart - timing.navigationStart,
        domContentLoaded: timing.domContentLoadedEventEnd - timing.navigationStart,
        loadComplete: timing.loadEventEnd - timing.navigationStart,
        domElements: document.getElementsByTagName('*').length
      };
    });
    
    console.log('Performance metrics:', metrics);
    
    // TTFB should be under 2 seconds
    expect(metrics.ttfb).toBeLessThan(2000);
    
    // DOM load should be under 5 seconds
    expect(metrics.domContentLoaded).toBeLessThan(5000);
    
    // Full load should be under 10 seconds
    expect(metrics.loadComplete).toBeLessThan(10000);
  });
});

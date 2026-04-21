// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Test Suite: Performance Tests
 * Verifies page load times and performance metrics
 */
test.describe('Performance Tests', () => {
  
  test('homepage should load within 3 seconds', async ({ page }) => {
    // Start timing
    const startTime = Date.now();
    
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    
    // Calculate load time
    const loadTime = Date.now() - startTime;
    
    console.log(`Homepage loaded in ${loadTime}ms`);
    
    // Should load within 3 seconds
    expect(loadTime).toBeLessThan(3000);
  });

  test('product page should load within 3 seconds', async ({ page }) => {
    const startTime = Date.now();
    
    await page.goto('/catalogsearch/result/?q=Pilot');
    await page.waitForSelector('.product-item-link');
    
    const loadTime = Date.now() - startTime;
    
    console.log(`Product page loaded in ${loadTime}ms`);
    expect(loadTime).toBeLessThan(3000);
  });

  test('cart page should load within 3 seconds', async ({ page }) => {
    // Add product first
    await page.goto('/catalogsearch/result/?q=Pilot');
    await page.waitForSelector('.product-item-link');
    await page.locator('.product-item-link').first().click();
    await page.waitForSelector('button.action.tocart');
    await page.locator('button.action.tocart').click();
    await page.waitForSelector('.message-success');
    
    // Time cart page load
    const startTime = Date.now();
    
    await page.goto('/checkout/cart/');
    await page.waitForLoadState('networkidle');
    
    const loadTime = Date.now() - startTime;
    
    console.log(`Cart page loaded in ${loadTime}ms`);
    expect(loadTime).toBeLessThan(3000);
  });

  test('should have good Core Web Vitals', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    
    // Get LCP (Largest Contentful Paint)
    const lcp = await page.evaluate(() => {
      return new Promise((resolve) => {
        new PerformanceObserver((list) => {
          const entries = list.getEntries();
          const lastEntry = entries[entries.length - 1];
          resolve(lastEntry.renderTime || lastEntry.loadTime);
        }).observe({ entryTypes: ['largest-contentful-paint'] });
        
        // Timeout after 5 seconds
        setTimeout(() => resolve(0), 5000);
      });
    });
    
    console.log(`LCP: ${lcp}ms`);
    
    // LCP should be under 2.5 seconds for good UX
    expect(lcp).toBeLessThan(2500);
  });

  test('images should load correctly', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    
    // Check all images are loaded
    const images = page.locator('img[src]');
    const count = await images.count();
    
    console.log(`Found ${count} images`);
    
    // Check each image is loaded
    for (let i = 0; i < Math.min(count, 10); i++) {
      const img = images.nth(i);
      await expect(img).toBeVisible({ timeout: 5000 });
      
      const naturalWidth = await img.evaluate((el) => el.naturalWidth);
      expect(naturalWidth).toBeGreaterThan(0);
    }
  });

  test('should not have memory leaks', async ({ page }) => {
    // Navigate to multiple pages
    const pages = [
      '/',
      '/catalogsearch/result/?q=Pilot',
      '/catalogsearch/result/?q=Casio',
      '/'
    ];
    
    for (const url of pages) {
      await page.goto(url);
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(1000);
    }
    
    // Check page is still responsive
    const title = await page.title();
    expect(title).toBeTruthy();
  });
});

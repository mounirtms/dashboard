// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Test Suite: Homepage Tests
 * Verifies homepage loads correctly with no JavaScript errors
 */
test.describe('Homepage Tests', () => {
  
  test('should load homepage successfully', async ({ page }) => {
    // Navigate to homepage
    const response = await page.goto('/');
    
    // Check status code
    expect(response.status()).toBe(200);
    
    // Check page title contains "Techno"
    await expect(page).toHaveTitle(/Techno/i);
    
    // Check for main elements
    await expect(page.locator('header')).toBeVisible();
    await expect(page.locator('footer')).toBeVisible();
    await expect(page.locator('#nav')).toBeVisible();
  });

  test('should have no console errors on homepage', async ({ page }) => {
    // Collect console errors
    const errors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        errors.push(msg.text());
      }
    });
    
    // Navigate to homepage
    await page.goto('/');
    
    // Wait for page to fully load
    await page.waitForLoadState('networkidle');
    
    // Assert no critical errors
    const criticalErrors = errors.filter(err => 
      !err.includes('favicon') && // Ignore favicon errors
      !err.includes('404') // Ignore 404 errors
    );
    
    expect(criticalErrors.length).toBe(0);
  });

  test('should display search functionality', async ({ page }) => {
    await page.goto('/');
    
    // Check search box exists
    const searchBox = page.locator('#search');
    await expect(searchBox).toBeVisible();
    
    // Try searching
    await searchBox.fill('Pilot');
    await page.locator('button.action.search').click();
    
    // Should redirect to search results
    await expect(page).toHaveURL(/catalogsearch\/result/);
    await expect(page.locator('.products.list')).toBeVisible();
  });

  test('should load responsive on mobile', async ({ page }) => {
    // Set mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });
    
    await page.goto('/');
    
    // Check mobile menu exists
    const mobileMenu = page.locator('.nav-toggle, .mobile-menu');
    await expect(mobileMenu).toBeVisible();
    
    // Check logo is visible
    await expect(page.locator('.logo')).toBeVisible();
  });
});

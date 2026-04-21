// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Test Suite: Product Page Tests
 * Verifies product pages load correctly and add to cart works
 */
test.describe('Product Page Tests', () => {
  
  test('should load product page successfully', async ({ page }) => {
    // Navigate to a product (using search to find one)
    await page.goto('/catalogsearch/result/?q=Pilot');
    
    // Wait for products to load
    await page.waitForSelector('.product-item-link', { timeout: 10000 });
    
    // Click first product
    const firstProduct = page.locator('.product-item-link').first();
    await firstProduct.click();
    
    // Should be on product page
    await expect(page).toHaveURL(/catalog\/product\/view\/id\/\d+/);
    
    // Check product info exists
    await expect(page.locator('.product-info-main')).toBeVisible();
    await expect(page.locator('h1.product.name')).toBeVisible();
    await expect(page.locator('.price-wrapper')).toBeVisible();
  });

  test('should add product to cart successfully', async ({ page }) => {
    // Navigate to product
    await page.goto('/catalogsearch/result/?q=Pilot');
    await page.waitForSelector('.product-item-link');
    await page.locator('.product-item-link').first().click();
    
    // Wait for add to cart button
    await page.waitForSelector('button.action.tocart.primary', { timeout: 10000 });
    
    // Click add to cart
    await page.locator('button.action.tocart.primary').click();
    
    // Wait for success message
    await page.waitForSelector('.message-success', { timeout: 10000 });
    await expect(page.locator('.message-success')).toBeVisible();
    
    // Check mini cart shows items
    await expect(page.locator('[data-block="minicart"]')).toBeVisible();
  });

  test('should handle out of stock products correctly', async ({ page }) => {
    // Search for product
    await page.goto('/catalogsearch/result/?q=Pilot');
    await page.waitForSelector('.product-item-link');
    await page.locator('.product-item-link').first().click();
    
    // Check if product is in stock
    const availability = page.locator('.stock.available, .stock.unavailable');
    
    if (await availability.count() > 0) {
      const isAvailable = await availability.first().hasClass('stock available');
      
      if (isAvailable) {
        // Add to cart button should be enabled
        const addToCartBtn = page.locator('button.action.tocart');
        await expect(addToCartBtn).toBeEnabled();
      } else {
        // Add to cart button should be disabled or hidden
        const addToCartBtn = page.locator('button.action.tocart');
        await expect(addToCartBtn).toBeDisabled();
      }
    }
  });

  test('should display product images correctly', async ({ page }) => {
    await page.goto('/catalogsearch/result/?q=Pilot');
    await page.waitForSelector('.product-item-link');
    await page.locator('.product-item-link').first().click();
    
    // Check product image exists
    await expect(page.locator('.product-image-main, .fotorama__img')).toBeVisible();
    
    // Check image has src attribute
    const image = page.locator('.product-image-main img, .fotorama__img').first();
    const src = await image.getAttribute('src');
    expect(src).toBeTruthy();
    expect(src).toMatch(/\.jpg|\.png|\.webp/i);
  });

  test('should display product price correctly', async ({ page }) => {
    await page.goto('/catalogsearch/result/?q=Pilot');
    await page.waitForSelector('.product-item-link');
    await page.locator('.product-item-link').first().click();
    
    // Check price exists and is valid
    const price = page.locator('.price-wrapper .price').first();
    await expect(price).toBeVisible();
    
    const priceText = await price.textContent();
    expect(priceText).toMatch(/\d+/); // Should contain numbers
    expect(priceText).toMatch(/DA|DZD|دج/i); // Should contain currency
  });
});

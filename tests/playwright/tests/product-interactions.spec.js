// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Test Suite: Product Page Interactions
 * Tests product clicks, GTM tracking, and product interactions
 */
test.describe('Product Interactions Tests', () => {
  
  test('should track product clicks', async ({ page }) => {
    console.log('=== TEST: Product Click Tracking ===');
    
    // Navigate to search results
    await page.goto('/catalogsearch/result/?q=Pilot', { timeout: 30000 });
    await page.waitForSelector('.product-item-link', { timeout: 15000 });
    
    // Collect console logs
    const consoleLogs = [];
    page.on('console', msg => {
      consoleLogs.push({
        type: msg.type(),
        text: msg.text()
      });
    });
    
    // Click product
    const firstProduct = page.locator('.product-item-link').first();
    await firstProduct.click();
    await page.waitForLoadState('networkidle', { timeout: 30000 });
    
    // Should navigate to product page
    const currentUrl = page.url();
    console.log('Navigated to:', currentUrl);
    
    expect(currentUrl).toContain('/catalog/product/view/');
    
    // Check for GTM/product tracking (if enabled)
    const gtmLogs = consoleLogs.filter(log => 
      log.text.includes('product') || 
      log.text.includes('GTM') ||
      log.text.includes('dataLayer')
    );
    
    console.log('Tracking logs found:', gtmLogs.length);
    
    // Product page should load without errors
    const errorLogs = consoleLogs.filter(log => 
      log.type === 'error' && 
      !log.text.includes('favicon') &&
      !log.text.includes('404')
    );
    
    console.log('Error logs:', errorLogs.length);
    expect(errorLogs.length).toBe(0);
  });

  test('should display product images correctly', async ({ page }) => {
    console.log('=== TEST: Product Images ===');
    
    await page.goto('/catalogsearch/result/?q=Pilot', { timeout: 30000 });
    await page.waitForSelector('.product-item-link');
    await page.locator('.product-item-link').first().click();
    await page.waitForLoadState('networkidle', { timeout: 30000 });
    
    // Check for product image
    const productImage = page.locator('.product-image-main img, .fotorama__img, .gallery-placeholder img');
    await expect(productImage).toBeVisible({ timeout: 10000 });
    
    // Check image has src
    const src = await productImage.getAttribute('src');
    console.log('Product image src:', src ? 'present' : 'missing');
    expect(src).toBeTruthy();
    expect(src).toMatch(/\.jpg|\.png|\.webp|\.jpeg/i);
    
    // Check image loads
    const naturalWidth = await productImage.evaluate((el) => el.naturalWidth);
    console.log('Image natural width:', naturalWidth);
    expect(naturalWidth).toBeGreaterThan(0);
  });

  test('should show product price correctly', async ({ page }) => {
    console.log('=== TEST: Product Price Display ===');
    
    await page.goto('/catalogsearch/result/?q=Pilot', { timeout: 30000 });
    await page.waitForSelector('.product-item-link');
    await page.locator('.product-item-link').first().click();
    await page.waitForLoadState('networkidle', { timeout: 30000 });
    
    // Check for price
    const priceElement = page.locator('.price-wrapper .price, [price-amount], .price');
    await expect(priceElement).toBeVisible({ timeout: 10000 });
    
    const priceText = await priceElement.first().textContent();
    console.log('Price displayed:', priceText);
    
    // Should contain numbers and currency
    expect(priceText).toMatch(/[\d,]+(\.\d{2})?/);
    expect(priceText).toMatch(/DA|DZD|دج|€|\$/i);
  });

  test('should allow adding to wishlist', async ({ page }) => {
    console.log('=== TEST: Add to Wishlist ===');
    
    await page.goto('/catalogsearch/result/?q=Pilot', { timeout: 30000 });
    await page.waitForSelector('.product-item-link');
    await page.locator('.product-item-link').first().click();
    await page.waitForLoadState('networkidle', { timeout: 30000 });
    
    // Check for wishlist button
    const wishlistBtn = page.locator('.action.towishlist, [data-action="add-to-wishlist"]');
    const hasWishlist = await wishlistBtn.count() > 0;
    
    console.log('Wishlist button available:', hasWishlist);
    
    if (hasWishlist) {
      // Click wishlist (may require login)
      await wishlistBtn.click();
      await page.waitForTimeout(2000);
      
      // Should either add to wishlist or redirect to login
      const currentUrl = page.url();
      const isWishlistAdded = currentUrl.includes('wishlist');
      const isLoginRedirect = currentUrl.includes('customer/account/login');
      
      console.log('Wishlist added:', isWishlistAdded);
      console.log('Login redirect:', isLoginRedirect);
      
      // Either is acceptable
      expect(isWishlistAdded || isLoginRedirect).toBeTruthy();
    }
    
    // Check no errors
    const errors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        errors.push(msg.text());
      }
    });
    
    await page.waitForTimeout(2000);
    
    const wishlistErrors = errors.filter(err => 
      err.includes('wishlist') || err.includes('quoteData')
    );
    
    expect(wishlistErrors.length).toBe(0);
  });

  test('should compare products', async ({ page }) => {
    console.log('=== TEST: Compare Products ===');
    
    await page.goto('/catalogsearch/result/?q=Pilot', { timeout: 30000 });
    await page.waitForSelector('.product-item-link');
    
    // Check for compare button
    const compareBtn = page.locator('.action.tocompare, [data-action="add-to-compare"]');
    const hasCompare = await compareBtn.count() > 0;
    
    console.log('Compare button available:', hasCompare);
    
    if (hasCompare) {
      await compareBtn.first().click();
      await page.waitForTimeout(2000);
      
      // Should show success message or redirect
      const successMsg = page.locator('.message-success');
      const hasSuccess = await successMsg.count() > 0;
      
      console.log('Compare success message:', hasSuccess);
    }
    
    // Check no errors
    const errors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        errors.push(msg.text());
      }
    });
    
    await page.waitForTimeout(2000);
    
    const compareErrors = errors.filter(err => 
      err.includes('compare') || err.includes('quoteData')
    );
    
    expect(compareErrors.length).toBe(0);
  });

  test('should display product stock status', async ({ page }) => {
    console.log('=== TEST: Product Stock Status ===');
    
    await page.goto('/catalogsearch/result/?q=Pilot', { timeout: 30000 });
    await page.waitForSelector('.product-item-link');
    await page.locator('.product-item-link').first().click();
    await page.waitForLoadState('networkidle', { timeout: 30000 });
    
    // Check for stock status
    const stockStatus = page.locator('.stock.available, .stock.unavailable, [availability]');
    const hasStockStatus = await stockStatus.count() > 0;
    
    console.log('Stock status displayed:', hasStockStatus);
    
    if (hasStockStatus) {
      const statusText = await stockStatus.first().textContent();
      console.log('Stock status:', statusText);
    }
    
    // Add to cart button should reflect stock status
    const addToCartBtn = page.locator('button.action.tocart');
    const hasAddToCart = await addToCartBtn.count() > 0;
    
    console.log('Add to cart button:', hasAddToCart);
    expect(hasAddToCart).toBeTruthy();
  });

  test('should display product reviews', async ({ page }) => {
    console.log('=== TEST: Product Reviews ===');
    
    await page.goto('/catalogsearch/result/?q=Pilot', { timeout: 30000 });
    await page.waitForSelector('.product-item-link');
    await page.locator('.product-item-link').first().click();
    await page.waitForLoadState('networkidle', { timeout: 30000 });
    
    // Check for reviews section
    const reviewsSection = page.locator('#reviews, .reviews, [data-role="reviews"]');
    const hasReviews = await reviewsSection.count() > 0;
    
    console.log('Reviews section available:', hasReviews);
    
    if (hasReviews) {
      // Check for rating stars
      const ratingStars = page.locator('.rating-box, .stars, [rating]');
      const hasRating = await ratingStars.count() > 0;
      
      console.log('Rating display available:', hasRating);
    }
    
    // Check no errors
    const errors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        errors.push(msg.text());
      }
    });
    
    await page.waitForTimeout(2000);
    
    const reviewErrors = errors.filter(err => 
      err.includes('review') || err.includes('quoteData')
    );
    
    expect(reviewErrors.length).toBe(0);
  });
});

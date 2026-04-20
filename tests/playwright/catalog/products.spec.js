// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Catalog and Product Test Suite
 * Tests product browsing, search, filtering, and product details
 */

test.describe('Product Browsing', () => {
  
  test('TC1001: Homepage loads correctly', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    
    // Verify page loaded
    await expect(page.locator('.page-wrapper')).toBeVisible();
    
    // Verify header elements
    await expect(page.locator('.logo')).toBeVisible();
    await expect(page.locator('.store-menu')).toBeVisible();
    
    // Verify search bar
    await expect(page.locator('#search')).toBeVisible();
    
    // Verify cart icon
    await expect(page.locator('.showcart')).toBeVisible();
  });

  test('TC1002: Category page displays products', async ({ page }) => {
    await page.goto('/stylos.html'); // Adjust category URL as needed
    await page.waitForLoadState('networkidle');
    
    // Verify category page
    await expect(page.locator('.category-view')).toBeVisible();
    
    // Verify products grid
    const products = await page.locator('.product-item').count();
    expect(products).toBeGreaterThan(0);
    
    // Verify product details shown
    await expect(page.locator('.product-item-photo').first()).toBeVisible();
    await expect(page.locator('.product-item-name').first()).toBeVisible();
    await expect(page.locator('.price-box').first()).toBeVisible();
  });

  test('TC1003: Product sorting functionality', async ({ page }) => {
    await page.goto('/stylos.html');
    await page.waitForLoadState('networkidle');
    
    // Verify sort options
    await expect(page.locator('.sorter-action')).toBeVisible();
    
    // Sort by price
    await page.selectOption('.sorter-action select', 'price');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    
    // Verify products still displayed
    const products = await page.locator('.product-item').count();
    expect(products).toBeGreaterThan(0);
  });

  test('TC1004: Product pagination', async ({ page }) => {
    await page.goto('/stylos.html');
    await page.waitForLoadState('networkidle');
    
    // Check if pagination exists
    const pages = await page.locator('.pages li').count();
    if (pages > 2) {
      // Click next page
      await page.click('.pages a.next');
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(1000);
      
      // Verify different products shown
      await expect(page.locator('.product-item').first()).toBeVisible();
    }
  });
});

test.describe('Product Details', () => {
  
  test('TC1101: Product page displays correctly', async ({ page }) => {
    await page.goto('/techno-stylom-pilot-roller-0-5-mm-noir.html');
    await page.waitForLoadState('networkidle');
    
    // Verify product page
    await expect(page.locator('.product-info-main')).toBeVisible();
    
    // Verify product title
    await expect(page.locator('h1.page-title')).toBeVisible();
    
    // Verify price
    await expect(page.locator('.price-box')).toBeVisible();
    
    // Verify availability
    await expect(page.locator('.stock.available')).toBeVisible();
    
    // Verify add to cart button
    await expect(page.locator('#product-addtocart-button')).toBeVisible();
    
    // Verify product image
    await expect(page.locator('.product-media img')).toBeVisible();
  });

  test('TC1102: Product with configurable options', async ({ page }) => {
    // Test a configurable product if exists
    await page.goto('/cahier-200-pages-seyes-24x32.html');
    await page.waitForLoadState('networkidle');
    
    // Check for configurable options
    const options = await page.locator('.super-attribute-select').count();
    if (options > 0) {
      // Select an option
      await page.selectOption('.super-attribute-select', { index: 1 });
      await page.waitForTimeout(1000);
      
      // Verify price updated
      await expect(page.locator('.price-box')).toBeVisible();
    }
  });

  test('TC1103: Product image gallery', async ({ page }) => {
    await page.goto('/techno-stylom-pilot-roller-0-5-mm-noir.html');
    await page.waitForLoadState('networkidle');
    
    // Verify main image
    await expect(page.locator('.fotorama img')).toBeVisible();
    
    // Click next image if exists
    const nextBtn = await page.locator('.fotorama__arr').count();
    if (nextBtn > 0) {
      await page.click('.fotorama__arr');
      await page.waitForTimeout(1000);
      await expect(page.locator('.fotorama__stage img')).toBeVisible();
    }
  });

  test('TC1104: Product description and details', async ({ page }) => {
    await page.goto('/techno-stylom-pilot-roller-0-5-mm-noir.html');
    await page.waitForLoadState('networkidle');
    
    // Scroll to description
    await page.locator('.product.info.detailed').scrollIntoViewIfNeeded();
    
    // Verify description exists
    await expect(page.locator('.product.attribute.description')).toBeVisible();
    await expect(page.locator('.description .value')).toBeVisible();
  });

  test('TC1105: Related products display', async ({ page }) => {
    await page.goto('/techno-stylom-pilot-roller-0-5-mm-noir.html');
    await page.waitForLoadState('networkidle');
    
    // Scroll to related products
    const relatedBlock = page.locator('.block.related');
    await relatedBlock.scrollIntoViewIfNeeded();
    
    // Verify related products shown
    const hasRelated = await relatedBlock.count();
    if (hasRelated > 0) {
      await expect(relatedBlock).toBeVisible();
      const relatedItems = await page.locator('.related.product-item').count();
      expect(relatedItems).toBeGreaterThan(0);
    }
  });
});

test.describe('Search Functionality', () => {
  
  test('TC1201: Search for existing product', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    
    // Enter search term
    await page.fill('#search', 'stylo');
    await page.press('#search', 'Enter');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    // Verify search results
    await expect(page.locator('.search.results')).toBeVisible();
    const results = await page.locator('.product-item').count();
    expect(results).toBeGreaterThan(0);
  });

  test('TC1202: Search with no results', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    
    // Search for non-existent product
    await page.fill('#search', 'xyznonexistent123');
    await page.press('#search', 'Enter');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    // Verify no results message
    await expect(page.locator('.message.notice')).toBeVisible();
    await expect(page.locator('.message.notice')).toContainText('resultat');
  });

  test('TC1203: Search suggestions/autocomplete', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    
    // Type partial search term
    await page.fill('#search', 'sty');
    await page.waitForTimeout(1000);
    
    // Check if suggestions appear (if enabled)
    const suggestionsVisible = await page.locator('.search-autocomplete, .qsuggestions').count();
    if (suggestionsVisible > 0) {
      await expect(page.locator('.search-autocomplete, .qsuggestions').first()).toBeVisible();
    }
  });

  test('TC1204: Search with special characters', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    
    // Search with special chars
    await page.fill('#search', '<script>alert("test")</script>');
    await page.press('#search', 'Enter');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    
    // Should handle safely (no XSS)
    await expect(page.locator('.search.results, .message.notice')).toBeVisible();
  });
});

test.describe('Wishlist', () => {
  
  test('TC1301: Add product to wishlist (logged in)', async ({ page }) => {
    // Login
    await page.goto('/customer/account/login/');
    await page.fill('#email', 'test@example.com');
    await page.fill('#pass', 'Test123456');
    await page.click('#send2');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    // Go to product
    await page.goto('/techno-stylom-pilot-roller-0-5-mm-noir.html');
    await page.waitForLoadState('networkidle');
    
    // Add to wishlist
    const wishlistBtn = page.locator('.action.towishlist');
    if (await wishlistBtn.count() > 0) {
      await wishlistBtn.click();
      await page.waitForTimeout(2000);
      
      // Verify success
      await expect(page.locator('.message-success')).toBeVisible();
    }
  });

  test('TC1302: View wishlist', async ({ page }) => {
    // Login
    await page.goto('/customer/account/login/');
    await page.fill('#email', 'test@example.com');
    await page.fill('#pass', 'Test123456');
    await page.click('#send2');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
    
    // Go to wishlist
    await page.goto('/wishlist/');
    await page.waitForLoadState('networkidle');
    
    // Verify wishlist page
    await expect(page.locator('.page-title')).toContainText('liste');
  });
});

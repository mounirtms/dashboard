/**
 * Comprehensive Checkout Flow Test with Playwright
 * Tests shipping method cards, field validation, and checkout completion
 */

const { chromium } = require('playwright');
const fs = require('fs');

// Test configuration
const TEST_CONFIG = {
    baseUrl: 'https://dev.technostationery.com',
    timeout: 60000,
    headless: false, // Set to true for CI/CD
    slowMo: 500,     // Slow down for visibility
    screenshotDir: './test-screenshots',
    
    // Test data
    testRegions: [
        { name: 'Boumerdès', value: '893', expectedMethods: 3 },
        { name: 'Biskra', value: '865', expectedMethods: 3 },
        { name: 'Annaba', value: '858', expectedMethods: 3 },
        { name: 'Ouargla', value: '888', expectedMethods: 3 }
    ],
    
    testProduct: {
        url: '/catalogue/fournitures-scolaires/cahiers-carnets-notes/cahiers/cahier-clairefontaine-96-pages-24x32.html',
        name: 'Cahier'
    }
};

// Utility functions
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));

async function takeScreenshot(page, name) {
    const dir = TEST_CONFIG.screenshotDir;
    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }
    
    const filename = `${dir}/${Date.now()}-${name}.png`;
    await page.screenshot({ path: filename, fullPage: true });
    console.log(`📸 Screenshot saved: ${filename}`);
    return filename;
}

async function checkConsoleErrors(page) {
    const errors = [];
    const warnings = [];
    
    page.on('console', msg => {
        const type = msg.type();
        const text = msg.text();
        
        if (type === 'error') {
            errors.push(text);
            console.log('❌ Console Error:', text);
        } else if (type === 'warning' && !text.includes('Permissions-Policy')) {
            warnings.push(text);
            console.log('⚠️  Console Warning:', text);
        } else if (text.includes('[Shipping Cards]')) {
            console.log('📦 ' + text);
        } else if (text.includes('[Algerian States]')) {
            console.log('🗺️  ' + text);
        }
    });
    
    page.on('pageerror', error => {
        errors.push(error.message);
        console.log('❌ Page Error:', error.message);
    });
    
    return { errors, warnings };
}

async function runCheckoutTest() {
    console.log('\n' + '='.repeat(60));
    console.log('COMPREHENSIVE CHECKOUT FLOW TEST');
    console.log('='.repeat(60) + '\n');
    
    const browser = await chromium.launch({
        headless: TEST_CONFIG.headless,
        slowMo: TEST_CONFIG.slowMo
    });
    
    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 },
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    });
    
    const page = await context.newPage();
    const { errors, warnings } = await checkConsoleErrors(page);
    
    try {
        // Test 1: Add product to cart
        console.log('\n=== Test 1: Add Product to Cart ===');
        await page.goto(TEST_CONFIG.baseUrl + TEST_CONFIG.testProduct.url, {
            waitUntil: 'networkidle',
            timeout: TEST_CONFIG.timeout
        });
        
        await takeScreenshot(page, '01-product-page');
        
        // Wait for add to cart button
        const addToCartBtn = await page.waitForSelector('#product-addtocart-button, button.tocart', {
            timeout: 10000
        });
        
        console.log('✅ Product page loaded');
        console.log('🛒 Clicking "Add to Cart"...');
        
        await addToCartBtn.click();
        await delay(3000); // Wait for AJAX
        
        // Verify cart counter updated
        const cartCounter = await page.locator('.counter-number, .counter.qty').textContent().catch(() => '0');
        console.log(`✅ Cart counter: ${cartCounter}`);
        
        await takeScreenshot(page, '02-added-to-cart');
        
        // Test 2: Navigate to checkout
        console.log('\n=== Test 2: Navigate to Checkout ===');
        await page.goto(TEST_CONFIG.baseUrl + '/checkout/', {
            waitUntil: 'networkidle',
            timeout: TEST_CONFIG.timeout
        });
        
        await delay(2000);
        
        const currentUrl = page.url();
        console.log(`Current URL: ${currentUrl}`);
        
        if (currentUrl.includes('/checkout/cart')) {
            console.log('⚠️  Redirected to cart - cart may be empty');
            await takeScreenshot(page, '03-cart-redirect');
        } else {
            console.log('✅ On checkout page');
        }
        
        await takeScreenshot(page, '03-checkout-loaded');
        
        // Test 3: Fill shipping address
        console.log('\n=== Test 3: Fill Shipping Address ===');
        
        // Wait for shipping step
        await page.waitForSelector('#shipping', { timeout: 10000 }).catch(() => null);
        
        // Fill customer email
        const emailField = await page.locator('input[name="username"], #customer-email').first();
        if (await emailField.isVisible()) {
            await emailField.fill(`test${Date.now()}@example.com`);
            console.log('✅ Email filled');
        }
        
        // Fill address fields
        const fields = {
            'firstname': 'Test',
            'lastname': 'User',
            'street[0]': '123 Test Street',
            'city': 'Test City',
            'postcode': '00000',
            'telephone': '0555123456'
        };
        
        for (const [name, value] of Object.entries(fields)) {
            const field = await page.locator(`input[name="${name}"], input[name="shippingAddress.${name}"]`).first();
            if (await field.isVisible()) {
                await field.fill(value);
                console.log(`✅ Filled ${name}: ${value}`);
            }
        }
        
        // Select country (should be Algeria by default)
        const countrySelect = await page.locator('select[name="country_id"], select[name="shippingAddress.country_id"]').first();
        if (await countrySelect.isVisible()) {
            await countrySelect.selectOption('DZ');
            console.log('✅ Country: Algeria (DZ)');
            await delay(1000);
        }
        
        await takeScreenshot(page, '04-address-basic-filled');
        
        // Test 4: Test each region
        for (const regionData of TEST_CONFIG.testRegions) {
            console.log(`\n=== Test 4.${TEST_CONFIG.testRegions.indexOf(regionData) + 1}: Region ${regionData.name} ===`);
            
            // Select region
            const regionSelect = await page.locator('select[name="region_id"], select[name="shippingAddress.region_id"]').first();
            if (await regionSelect.isVisible()) {
                await regionSelect.selectOption(regionData.value);
                console.log(`✅ Selected region: ${regionData.name} (${regionData.value})`);
                
                // Wait for shipping rates to load
                console.log('⏳ Waiting for shipping rates...');
                await delay(3000);
                
                // Check for shipping method cards
                const cardsWrapper = await page.locator('.shipping-methods-cards-wrapper').first();
                const isVisible = await cardsWrapper.isVisible().catch(() => false);
                
                console.log(`Shipping cards wrapper visible: ${isVisible}`);
                
                if (isVisible) {
                    // Count shipping cards
                    const cards = await page.locator('.shipping-card').all();
                    console.log(`✅ Found ${cards.length} shipping method cards`);
                    
                    if (cards.length !== regionData.expectedMethods) {
                        console.log(`⚠️  Expected ${regionData.expectedMethods} methods, got ${cards.length}`);
                    }
                    
                    // Test each card
                    for (let i = 0; i < cards.length; i++) {
                        const card = cards[i];
                        const methodCode = await card.getAttribute('data-method-code');
                        const methodName = await card.locator('.method-name').textContent();
                        const price = await card.locator('.price-amount, .free-badge').textContent();
                        
                        console.log(`  Card ${i + 1}: ${methodName} - ${price} (${methodCode})`);
                    }
                    
                    // Click first card
                    console.log('\n🖱️  Clicking first shipping card...');
                    await cards[0].click();
                    await delay(1000);
                    
                    // Check if card is selected
                    const hasSelectedClass = await cards[0].evaluate(el => el.classList.contains('selected'));
                    console.log(`Card selected state: ${hasSelectedClass}`);
                    
                    // Check for Next button
                    const nextButton = await page.locator('button.continue, button[data-role="opc-continue"], .button.action.continue.primary').first();
                    const isNextVisible = await nextButton.isVisible().catch(() => false);
                    const isNextEnabled = await nextButton.isEnabled().catch(() => false);
                    
                    console.log(`Next button visible: ${isNextVisible}`);
                    console.log(`Next button enabled: ${isNextEnabled}`);
                    
                    if (hasSelectedClass && isNextVisible && isNextEnabled) {
                        console.log('✅ Shipping method selection working correctly!');
                    } else {
                        console.log('❌ Shipping method selection has issues');
                        if (!hasSelectedClass) console.log('   - Card not marked as selected');
                        if (!isNextVisible) console.log('   - Next button not visible');
                        if (!isNextEnabled) console.log('   - Next button not enabled');
                    }
                    
                } else {
                    console.log('❌ Shipping cards wrapper not visible');
                    
                    // Check for error messages
                    const errorMsg = await page.locator('.message-error, .error-message').textContent().catch(() => '');
                    if (errorMsg) {
                        console.log(`Error message: ${errorMsg}`);
                    }
                }
                
                await takeScreenshot(page, `05-region-${regionData.name.toLowerCase()}`);
                
            } else {
                console.log('❌ Region select not found');
            }
        }
        
        // Test 5: Complete checkout (if Next button is available)
        console.log('\n=== Test 5: Attempt to Complete Checkout ===');
        
        const nextButton = await page.locator('button.continue, button[data-role="opc-continue"]').first();
        const isNextEnabled = await nextButton.isEnabled().catch(() => false);
        
        if (isNextEnabled) {
            console.log('🖱️  Clicking Next to proceed to payment...');
            await nextButton.click();
            await delay(2000);
            
            // Check if we reached payment step
            const paymentStep = await page.locator('#payment, .payment-method').isVisible().catch(() => false);
            
            if (paymentStep) {
                console.log('✅ Reached payment step!');
                await takeScreenshot(page, '06-payment-step');
            } else {
                console.log('⚠️  Did not reach payment step');
            }
        } else {
            console.log('⚠️  Next button not enabled, cannot proceed');
        }
        
        // Final summary
        console.log('\n' + '='.repeat(60));
        console.log('TEST SUMMARY');
        console.log('='.repeat(60));
        console.log(`Console Errors: ${errors.length}`);
        console.log(`Console Warnings: ${warnings.length}`);
        
        if (errors.length > 0) {
            console.log('\nErrors encountered:');
            errors.forEach((err, i) => {
                console.log(`  ${i + 1}. ${err.substring(0, 150)}`);
            });
        }
        
        console.log('\n✅ Test completed successfully');
        
    } catch (error) {
        console.error('\n❌ TEST FAILED:', error.message);
        await takeScreenshot(page, 'ERROR-final-state');
        throw error;
        
    } finally {
        await delay(2000);
        await browser.close();
    }
}

// Run the test
runCheckoutTest().catch(error => {
    console.error('Fatal error:', error);
    process.exit(1);
});

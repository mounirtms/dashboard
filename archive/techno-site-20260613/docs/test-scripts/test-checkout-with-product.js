/**
 * Playwright Test - Add product and navigate to checkout
 */

const { chromium } = require('playwright');

async function runTest() {
    console.log('🚀 Starting checkout test with product...\n');
    
    const browser = await chromium.launch({
        headless: true,
        args: ['--disable-blink-features=AutomationControlled']
    });
    
    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 },
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    });
    
    const page = await context.newPage();
    
    // Collection arrays
    const consoleLogs = [];
    const shippingLogs = [];
    
    // Capture console messages
    page.on('console', msg => {
        const text = msg.text();
        const type = msg.type();
        
        consoleLogs.push({ type, text, timestamp: Date.now() });
        
        if (text.includes('shipping') || text.includes('Batna') || text.includes('region') || 
            text.includes('isVisible') || text.includes('cards') || text.includes('method')) {
            shippingLogs.push(text);
            console.log(`📋 [SHIPPING LOG] ${text}`);
        }
        
        if (type === 'error') {
            console.log(`❌ [ERROR] ${text}`);
        }
    });
    
    page.on('pageerror', error => {
        console.log(`💥 [JS ERROR] ${error.message}`);
    });
    
    try {
        // Step 1: Go to homepage
        console.log('🏠 Navigating to homepage...');
        await page.goto('https://dev.technostationery.com/', {
            waitUntil: 'networkidle',
            timeout: 60000
        });
        
        console.log('✅ Homepage loaded\n');
        await page.waitForTimeout(2000);
        
        // Step 2: Find and add a product
        console.log('🔍 Looking for a product to add...');
        
        // Try to find "Add to Cart" button
        const addToCartButton = await page.locator('button[title*="Ajouter"], button:has-text("Ajouter au panier"), .action.tocart').first();
        
        if (await addToCartButton.count() > 0) {
            console.log('🛒 Found "Add to Cart" button, clicking...');
            await addToCartButton.click();
            await page.waitForTimeout(3000);
            console.log('✅ Product added to cart\n');
        } else {
            console.log('⚠️  No "Add to Cart" button found on homepage');
            console.log('📍 Trying to navigate to a product page...');
            
            // Try to find a product link
            const productLink = await page.locator('.product-item-link, .product-name a').first();
            if (await productLink.count() > 0) {
                await productLink.click();
                await page.waitForTimeout(2000);
                
                // Now try to add to cart
                const pdpAddToCart = await page.locator('#product-addtocart-button, button[type="submit"][title*="Ajouter"]').first();
                if (await pdpAddToCart.count() > 0) {
                    console.log('🛒 Adding product from product page...');
                    await pdpAddToCart.click();
                    await page.waitForTimeout(3000);
                    console.log('✅ Product added\n');
                }
            }
        }
        
        // Step 3: Navigate to checkout
        console.log('🚶 Navigating to checkout...');
        await page.goto('https://dev.technostationery.com/checkout', {
            waitUntil: 'networkidle',
            timeout: 60000
        });
        
        console.log('✅ Checkout page loaded\n');
        console.log('⏳ Waiting for checkout initialization (10 seconds)...');
        await page.waitForTimeout(10000);
        
        // Get page URL
        const currentUrl = page.url();
        console.log(`📍 Current URL: ${currentUrl}\n`);
        
        // Check if we're on checkout
        if (!currentUrl.includes('/checkout')) {
            console.log('⚠️  Not on checkout page! Current URL:', currentUrl);
            console.log('Trying to click "Proceed to Checkout" button...');
            
            const checkoutButton = await page.locator('button:has-text("Commander"), button:has-text("Checkout"), .checkout-methods-items button').first();
            if (await checkoutButton.count() > 0) {
                await checkoutButton.click();
                await page.waitForTimeout(5000);
                console.log('Navigated to:', page.url());
            }
        }
        
        // Check for checkout components
        console.log('\n🔍 Checking checkout page structure...');
        
        const pageStructure = await page.evaluate(() => {
            return {
                hasCheckoutRoot: !!document.querySelector('#checkout'),
                hasShippingStep: !!document.querySelector('#opc-shipping_method, [name="checkout.steps.shipping-step"]'),
                hasRegionField: !!document.querySelector('select[name="region_id"]'),
                hasShippingWrapper: !!document.querySelector('.shipping-methods-cards-wrapper'),
                hasOpcWrapper: !!document.querySelector('.opc-wrapper'),
                bodyClasses: document.body.className,
                checkoutSteps: Array.from(document.querySelectorAll('.opc-progress-bar-item')).map(el => ({
                    text: el.textContent.trim(),
                    classes: el.className
                }))
            };
        });
        
        console.log('Page structure:', JSON.stringify(pageStructure, null, 2));
        
        // If region field exists, select Batna
        if (pageStructure.hasRegionField) {
            console.log('\n📍 Region field found! Getting regions...');
            
            const regions = await page.evaluate(() => {
                const select = document.querySelector('select[name="region_id"]');
                if (!select) return [];
                return Array.from(select.options).map(opt => ({
                    value: opt.value,
                    text: opt.text,
                    selected: opt.selected
                }));
            });
            
            console.log(`Found ${regions.length} regions`);
            const batnaOption = regions.find(r => r.text.toLowerCase().includes('batna'));
            
            if (batnaOption) {
                console.log(`\n🎯 Selecting Batna (value: ${batnaOption.value})...`);
                
                await page.selectOption('select[name="region_id"]', batnaOption.value);
                await page.evaluate(() => {
                    const select = document.querySelector('select[name="region_id"]');
                    if (select) {
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                        select.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                });
                
                console.log('✅ Batna selected, waiting for shipping methods...');
                await page.waitForTimeout(8000);
                
                // Check shipping cards state
                console.log('\n🔍 Checking shipping cards...');
                
                const cardsState = await page.evaluate(() => {
                    const wrapper = document.querySelector('.shipping-methods-cards-wrapper');
                    const cards = document.querySelectorAll('.shipping-card');
                    const notice = document.querySelector('.shipping-notice');
                    
                    if (!wrapper) {
                        return { 
                            error: 'Wrapper not found',
                            allDivs: Array.from(document.querySelectorAll('div[class*="shipping"]')).map(d => ({
                                class: d.className,
                                visible: window.getComputedStyle(d).display !== 'none'
                            }))
                        };
                    }
                    
                    const style = window.getComputedStyle(wrapper);
                    
                    return {
                        wrapper: {
                            display: style.display,
                            visibility: style.visibility,
                            opacity: style.opacity,
                            width: style.width,
                            height: style.height,
                            innerHTML: wrapper.innerHTML.substring(0, 500)
                        },
                        notice: notice ? {
                            exists: true,
                            text: notice.textContent.trim(),
                            display: window.getComputedStyle(notice).display
                        } : { exists: false },
                        cards: {
                            total: cards.length,
                            details: Array.from(cards).map(card => ({
                                title: card.querySelector('.shipping-card-title')?.textContent.trim(),
                                price: card.querySelector('.shipping-price')?.textContent.trim(),
                                display: window.getComputedStyle(card).display,
                                visibility: window.getComputedStyle(card).visibility
                            }))
                        }
                    };
                });
                
                console.log('\n📊 Shipping Cards State:');
                console.log(JSON.stringify(cardsState, null, 2));
                
                // Check Knockout context
                console.log('\n🔧 Checking Knockout bindings...');
                
                const koState = await page.evaluate(() => {
                    const wrapper = document.querySelector('.shipping-methods-cards-wrapper');
                    if (!wrapper || !window.ko) {
                        return { error: 'Wrapper or KnockoutJS not found' };
                    }
                    
                    try {
                        const data = ko.dataFor(wrapper);
                        if (!data) {
                            return { error: 'No Knockout data bound to wrapper' };
                        }
                        
                        return {
                            hasIsVisible: typeof data.isVisible !== 'undefined',
                            isVisibleValue: data.isVisible ? data.isVisible() : 'N/A',
                            hasCurrentRegion: typeof data.currentRegion !== 'undefined',
                            currentRegionValue: data.currentRegion ? data.currentRegion() : 'N/A',
                            hasShippingMethods: typeof data.shippingMethods !== 'undefined',
                            shippingMethodsCount: data.shippingMethods ? data.shippingMethods().length : 0,
                            allProperties: Object.keys(data).filter(k => typeof data[k] !== 'function')
                        };
                    } catch (e) {
                        return { error: 'Error reading Knockout data: ' + e.message };
                    }
                });
                
                console.log('Knockout state:', JSON.stringify(koState, null, 2));
            } else {
                console.log('\n❌ Batna not found in regions!');
                console.log('Available regions:', regions.map(r => r.text).join(', '));
            }
        } else {
            console.log('\n❌ Region field not found on page!');
        }
        
        // Take screenshot
        await page.screenshot({ 
            path: '/home/dev/public_html/checkout-with-product.png',
            fullPage: true 
        });
        console.log('\n📸 Screenshot saved to checkout-with-product.png');
        
        // Print shipping-related console logs
        console.log('\n' + '='.repeat(80));
        console.log('📋 SHIPPING-RELATED CONSOLE LOGS');
        console.log('='.repeat(80));
        if (shippingLogs.length > 0) {
            shippingLogs.forEach((log, idx) => console.log(`${idx + 1}. ${log}`));
        } else {
            console.log('No shipping-related logs found');
        }
        
    } catch (error) {
        console.error('\n💥 Test failed:', error.message);
        console.error(error.stack);
    } finally {
        await browser.close();
        console.log('\n✅ Test complete');
    }
}

runTest().catch(console.error);

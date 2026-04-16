/**
 * Comprehensive Playwright Test for Shipping Cards
 * Tests wilaya selection and shipping method cards rendering
 */

const { chromium } = require('playwright');
const fs = require('fs');

async function runComprehensiveTest() {
    console.log('🚀 Starting Comprehensive Shipping Cards Test\n');
    console.log('='.repeat(80));
    
    const browser = await chromium.launch({
        headless: true,
        args: ['--disable-blink-features=AutomationControlled', '--no-sandbox']
    });
    
    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 },
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0'
    });
    
    const page = await context.newPage();
    
    // Log collectors
    const consoleLogs = [];
    const shippingLogs = [];
    const errors = [];
    
    // Console listener
    page.on('console', msg => {
        const text = msg.text();
        const type = msg.type();
        
        consoleLogs.push({ type, text, timestamp: new Date().toISOString() });
        
        if (text.includes('[Shipping Cards]') || text.includes('shipping') || 
            text.includes('Batna') || text.includes('region') || text.includes('rate')) {
            shippingLogs.push({ type, text, timestamp: new Date().toISOString() });
            console.log(`📋 ${type.toUpperCase()}: ${text}`);
        }
        
        if (type === 'error') {
            errors.push(text);
        }
    });
    
    page.on('pageerror', err => {
        errors.push(err.message);
        console.log(`💥 JS ERROR: ${err.message}`);
    });
    
    try {
        // Step 1: Navigate to homepage and add product
        console.log('\n📍 Step 1: Adding product to cart...');
        await page.goto('https://dev.technostationery.com/', { 
            waitUntil: 'domcontentloaded',
            timeout: 60000 
        });
        
        await page.waitForTimeout(3000);
        
        // Try to find and click first "Add to Cart" button
        const addToCartSelectors = [
            'button.action.tocart.primary',
            'button[title*="panier"]',
            '.product-item-actions button.tocart'
        ];
        
        let productAdded = false;
        for (const selector of addToCartSelectors) {
            try {
                const button = page.locator(selector).first();
                if (await button.count() > 0 && await button.isVisible()) {
                    await button.click();
                    await page.waitForTimeout(3000);
                    productAdded = true;
                    console.log('✅ Product added to cart');
                    break;
                }
            } catch (e) {
                // Try next selector
            }
        }
        
        if (!productAdded) {
            console.log('⚠️  Could not add product, navigating directly to checkout...');
        }
        
        // Step 2: Navigate to checkout
        console.log('\n📍 Step 2: Navigating to checkout...');
        await page.goto('https://dev.technostationery.com/checkout', {
            waitUntil: 'domcontentloaded',
            timeout: 60000
        });
        
        console.log('✅ Checkout page loaded');
        console.log('⏳ Waiting for checkout to initialize (8 seconds)...\n');
        await page.waitForTimeout(8000);
        
        // Step 3: Check page structure
        console.log('📍 Step 3: Analyzing page structure...');
        
        const pageInfo = await page.evaluate(() => {
            return {
                url: window.location.href,
                title: document.title,
                hasCheckoutRoot: !!document.querySelector('#checkout'),
                hasShippingStep: !!document.querySelector('#shipping'),
                hasRegionField: !!document.querySelector('select[name="region_id"]'),
                hasShippingWrapper: !!document.querySelector('.shipping-methods-cards-wrapper'),
                bodyClass: document.body.className,
                checkoutElements: {
                    shippingAddress: !!document.querySelector('[name="shippingAddress"]'),
                    shippingMethod: !!document.querySelector('[name="shippingAddress"] .shipping-methods-cards-wrapper'),
                    opcWrapper: !!document.querySelector('.opc-wrapper')
                }
            };
        });
        
        console.log('Page Info:', JSON.stringify(pageInfo, null, 2));
        
        if (!pageInfo.hasRegionField) {
            console.log('\n⚠️  WARNING: No region field found! May not be on actual checkout page.');
            console.log('Current URL:', pageInfo.url);
            
            // Try to proceed to checkout if on cart page
            if (pageInfo.url.includes('/cart')) {
                console.log('📍 Attempting to click "Proceed to Checkout" button...');
                const checkoutBtn = page.locator('button.checkout, button:has-text("Commander")').first();
                if (await checkoutBtn.count() > 0) {
                    await checkoutBtn.click();
                    await page.waitForTimeout(5000);
                    console.log('✅ Navigated to checkout');
                }
            }
        }
        
        // Step 4: Get wilaya/region options
        console.log('\n📍 Step 4: Fetching wilaya options...');
        
        const regionData = await page.evaluate(() => {
            const select = document.querySelector('select[name="region_id"]');
            if (!select) return null;
            
            return {
                exists: true,
                currentValue: select.value,
                options: Array.from(select.options).map(opt => ({
                    value: opt.value,
                    text: opt.text.trim(),
                    selected: opt.selected
                })).filter(opt => opt.value !== '' && opt.value !== '0')
            };
        });
        
        if (!regionData || !regionData.exists) {
            throw new Error('Region dropdown not found on page!');
        }
        
        console.log(`✅ Found ${regionData.options.length} wilaya options`);
        console.log('First 5 wilayas:', regionData.options.slice(0, 5).map(r => r.text).join(', '));
        
        // Step 5: Find Batna
        const batnaOption = regionData.options.find(opt => 
            opt.text.toLowerCase().includes('batna')
        );
        
        if (!batnaOption) {
            console.log('\n⚠️  Batna not found! Available options:', regionData.options.map(r => r.text).join(', '));
            throw new Error('Batna region not found in dropdown');
        }
        
        console.log(`\n✅ Found Batna: value="${batnaOption.value}", text="${batnaOption.text}"`);
        
        // Step 6: Check initial shipping cards state
        console.log('\n📍 Step 5: Checking initial shipping cards state...');
        
        const initialCardsState = await page.evaluate(() => {
            const wrapper = document.querySelector('.shipping-methods-cards-wrapper');
            if (!wrapper) return { exists: false };
            
            const style = window.getComputedStyle(wrapper);
            const cards = document.querySelectorAll('.shipping-card');
            
            return {
                exists: true,
                display: style.display,
                visibility: style.visibility,
                opacity: style.opacity,
                cardsCount: cards.length,
                innerHTML: wrapper.innerHTML.substring(0, 300)
            };
        });
        
        console.log('Initial cards state:', JSON.stringify(initialCardsState, null, 2));
        
        // Step 7: Select Batna
        console.log('\n📍 Step 6: Selecting Batna wilaya...');
        console.log('='.repeat(80));
        
        await page.selectOption('select[name="region_id"]', batnaOption.value);
        
        // Trigger change events
        await page.evaluate(() => {
            const select = document.querySelector('select[name="region_id"]');
            if (select) {
                select.dispatchEvent(new Event('change', { bubbles: true }));
                select.dispatchEvent(new Event('input', { bubbles: true }));
                
                // Trigger Knockout change
                const changeEvent = document.createEvent('HTMLEvents');
                changeEvent.initEvent('change', true, true);
                select.dispatchEvent(changeEvent);
            }
        });
        
        console.log('✅ Batna selected, waiting for shipping rates (10 seconds)...');
        await page.waitForTimeout(10000);
        
        // Step 8: Check shipping cards after selection
        console.log('\n📍 Step 7: Analyzing shipping cards after Batna selection...');
        console.log('='.repeat(80));
        
        const finalCardsState = await page.evaluate(() => {
            const wrapper = document.querySelector('.shipping-methods-cards-wrapper');
            if (!wrapper) {
                return { 
                    error: 'Wrapper not found',
                    allShippingDivs: Array.from(document.querySelectorAll('div[class*="shipping"]')).map(d => ({
                        className: d.className,
                        display: window.getComputedStyle(d).display
                    }))
                };
            }
            
            const style = window.getComputedStyle(wrapper);
            const cards = document.querySelectorAll('.shipping-card');
            const notice = document.querySelector('.shipping-notice');
            const loading = document.querySelector('.shipping-cards-loading');
            const error = document.querySelector('.shipping-cards-error');
            
            // Get Knockout context
            let koData = null;
            if (window.ko && window.ko.dataFor) {
                try {
                    koData = ko.dataFor(wrapper);
                    if (koData) {
                        koData = {
                            isVisible: koData.isVisible ? koData.isVisible() : 'N/A',
                            isLoading: koData.isLoading ? koData.isLoading() : 'N/A',
                            hasMethods: koData.hasMethods ? koData.hasMethods() : 'N/A',
                            methodsCount: koData.shippingMethods ? koData.shippingMethods().length : 0,
                            currentRegion: koData.currentRegion ? koData.currentRegion() : 'N/A',
                            errorMessage: koData.errorMessage ? koData.errorMessage() : ''
                        };
                    }
                } catch (e) {
                    koData = { error: e.message };
                }
            }
            
            return {
                wrapper: {
                    display: style.display,
                    visibility: style.visibility,
                    opacity: style.opacity,
                    width: style.width,
                    height: style.height
                },
                notice: notice ? {
                    exists: true,
                    text: notice.textContent.trim(),
                    display: window.getComputedStyle(notice).display
                } : { exists: false },
                loading: loading ? {
                    exists: true,
                    display: window.getComputedStyle(loading).display
                } : { exists: false },
                error: error ? {
                    exists: true,
                    text: error.textContent.trim()
                } : { exists: false },
                cards: {
                    total: cards.length,
                    details: Array.from(cards).slice(0, 5).map(card => {
                        const cardStyle = window.getComputedStyle(card);
                        return {
                            methodCode: card.getAttribute('data-method-code'),
                            title: card.querySelector('.method-name')?.textContent.trim(),
                            price: card.querySelector('.price-amount, .free-badge')?.textContent.trim(),
                            display: cardStyle.display,
                            visibility: cardStyle.visibility,
                            isSelected: card.classList.contains('selected')
                        };
                    })
                },
                knockout: koData
            };
        });
        
        console.log('\n📊 FINAL SHIPPING CARDS STATE:');
        console.log(JSON.stringify(finalCardsState, null, 2));
        
        // Step 9: Take screenshot
        await page.screenshot({ 
            path: '/home/dev/public_html/checkout-batna-final.png',
            fullPage: true 
        });
        console.log('\n📸 Screenshot saved: checkout-batna-final.png');
        
        // Step 10: Verify success
        console.log('\n' + '='.repeat(80));
        console.log('📊 TEST RESULTS');
        console.log('='.repeat(80));
        
        const success = finalCardsState.cards && finalCardsState.cards.total > 0;
        
        if (success) {
            console.log('✅ SUCCESS! Shipping cards are displayed!');
            console.log(`   - Total cards: ${finalCardsState.cards.total}`);
            console.log(`   - Wrapper visible: ${finalCardsState.wrapper.display === 'block' || finalCardsState.wrapper.display === 'grid'}`);
            
            if (finalCardsState.cards.details.length > 0) {
                console.log('\n📦 SHIPPING OPTIONS:');
                finalCardsState.cards.details.forEach((card, idx) => {
                    console.log(`   ${idx + 1}. ${card.title} - ${card.price}`);
                });
            }
        } else {
            console.log('❌ FAILED! Shipping cards are NOT displayed');
            if (finalCardsState.error) {
                console.log(`   Error: ${finalCardsState.error}`);
            }
            if (finalCardsState.knockout) {
                console.log('   Knockout state:', JSON.stringify(finalCardsState.knockout, null, 2));
            }
        }
        
        console.log(`\n📋 Total console logs: ${consoleLogs.length}`);
        console.log(`📋 Shipping-related logs: ${shippingLogs.length}`);
        console.log(`❌ Errors: ${errors.length}`);
        
        if (shippingLogs.length > 0) {
            console.log('\n🔍 SHIPPING-RELATED CONSOLE LOGS:');
            shippingLogs.slice(0, 20).forEach((log, idx) => {
                console.log(`   ${idx + 1}. [${log.type}] ${log.text}`);
            });
            if (shippingLogs.length > 20) {
                console.log(`   ... and ${shippingLogs.length - 20} more`);
            }
        }
        
        // Save full log
        fs.writeFileSync(
            '/home/dev/public_html/playwright-shipping-test-log.json',
            JSON.stringify({
                timestamp: new Date().toISOString(),
                success,
                pageInfo,
                regionData: regionData.options.length,
                batnaOption,
                initialCardsState,
                finalCardsState,
                shippingLogs,
                consoleLogs,
                errors
            }, null, 2)
        );
        
        console.log('\n💾 Full log saved: playwright-shipping-test-log.json');
        
        return success ? 0 : 1;
        
    } catch (error) {
        console.error('\n💥 TEST FAILED WITH ERROR:');
        console.error(error.message);
        console.error(error.stack);
        return 1;
    } finally {
        await browser.close();
        console.log('\n✅ Browser closed\n');
    }
}

// Run test
runComprehensiveTest()
    .then(exitCode => {
        process.exit(exitCode);
    })
    .catch(err => {
        console.error('Fatal error:', err);
        process.exit(1);
    });

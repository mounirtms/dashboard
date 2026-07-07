/**
 * Enhanced Playwright Test for Shipping Cards Visibility
 * Captures console logs, network requests, and DOM state
 */

const { chromium } = require('playwright');

async function runTest() {
    console.log('🚀 Starting enhanced Playwright checkout test...\n');
    
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
    const consoleErrors = [];
    const networkErrors = [];
    const jsErrors = [];
    
    // Capture all console messages
    page.on('console', msg => {
        const text = msg.text();
        const type = msg.type();
        
        consoleLogs.push({ type, text, timestamp: Date.now() });
        
        if (type === 'error') {
            consoleErrors.push(text);
            console.log(`❌ [CONSOLE ERROR] ${text}`);
        } else if (type === 'warning') {
            console.log(`⚠️  [CONSOLE WARNING] ${text}`);
        } else if (text.includes('shipping') || text.includes('Batna') || text.includes('region')) {
            console.log(`📋 [CONSOLE LOG] ${text}`);
        }
    });
    
    // Capture JavaScript errors
    page.on('pageerror', error => {
        jsErrors.push(error.message);
        console.log(`💥 [JS ERROR] ${error.message}`);
    });
    
    // Capture network failures
    page.on('requestfailed', request => {
        networkErrors.push({
            url: request.url(),
            failure: request.failure()
        });
        console.log(`🌐 [NETWORK ERROR] ${request.url()} - ${request.failure().errorText}`);
    });
    
    try {
        console.log('🌍 Navigating to checkout page...');
        await page.goto('https://dev.technostationery.com/checkout', {
            waitUntil: 'networkidle',
            timeout: 60000
        });
        
        console.log('✅ Page loaded\n');
        
        // Wait for checkout to initialize
        console.log('⏳ Waiting for checkout initialization...');
        await page.waitForTimeout(3000);
        
        // Check if shipping method cards component exists
        console.log('\n🔍 Checking for shipping-method-cards component...');
        const componentExists = await page.evaluate(() => {
            return !!document.querySelector('[data-bind*="shipping-method-cards"]') || 
                   !!document.querySelector('.shipping-methods-cards-wrapper');
        });
        console.log(`Component exists: ${componentExists}`);
        
        // Get current region value
        const regionInfo = await page.evaluate(() => {
            const regionSelect = document.querySelector('select[name="region_id"]');
            return {
                exists: !!regionSelect,
                value: regionSelect ? regionSelect.value : null,
                options: regionSelect ? Array.from(regionSelect.options).map(o => ({
                    value: o.value,
                    text: o.text,
                    selected: o.selected
                })) : []
            };
        });
        
        console.log('\n📍 Region field info:');
        console.log(`  - Exists: ${regionInfo.exists}`);
        console.log(`  - Current value: ${regionInfo.value}`);
        console.log(`  - Available options: ${regionInfo.options.length}`);
        
        // Find Batna option
        const batnaOption = regionInfo.options.find(opt => 
            opt.text.toLowerCase().includes('batna')
        );
        
        if (batnaOption) {
            console.log(`\n🎯 Found Batna option: value="${batnaOption.value}", text="${batnaOption.text}"`);
            
            // Select Batna region
            console.log('\n🔄 Selecting Batna region...');
            await page.selectOption('select[name="region_id"]', batnaOption.value);
            
            // Trigger change event
            await page.evaluate(() => {
                const regionSelect = document.querySelector('select[name="region_id"]');
                if (regionSelect) {
                    regionSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    // Also trigger Knockout events
                    const event = new Event('input', { bubbles: true });
                    regionSelect.dispatchEvent(event);
                }
            });
            
            console.log('✅ Region selected, waiting for updates...');
            await page.waitForTimeout(5000);
            
            // Check shipping cards visibility
            console.log('\n🔍 Checking shipping cards state...');
            const cardsState = await page.evaluate(() => {
                const wrapper = document.querySelector('.shipping-methods-cards-wrapper');
                const cards = document.querySelectorAll('.shipping-card');
                
                if (!wrapper) {
                    return { error: 'Wrapper not found' };
                }
                
                const computedStyle = window.getComputedStyle(wrapper);
                const knockoutData = ko && ko.dataFor ? ko.dataFor(wrapper) : null;
                
                return {
                    wrapper: {
                        exists: true,
                        display: computedStyle.display,
                        visibility: computedStyle.visibility,
                        opacity: computedStyle.opacity,
                        innerHTML: wrapper.innerHTML.substring(0, 200),
                        attributes: Array.from(wrapper.attributes).map(a => ({
                            name: a.name,
                            value: a.value
                        }))
                    },
                    cards: {
                        count: cards.length,
                        visible: Array.from(cards).filter(card => {
                            const style = window.getComputedStyle(card);
                            return style.display !== 'none' && style.visibility !== 'hidden';
                        }).length
                    },
                    knockout: knockoutData ? {
                        isVisible: knockoutData.isVisible ? knockoutData.isVisible() : 'N/A',
                        currentRegion: knockoutData.currentRegion ? knockoutData.currentRegion() : 'N/A',
                        shippingMethodsCount: knockoutData.shippingMethods ? knockoutData.shippingMethods().length : 0
                    } : { error: 'Knockout data not available' }
                };
            });
            
            console.log('\n📊 Shipping Cards State:');
            console.log(JSON.stringify(cardsState, null, 2));
            
            // Check for RequireJS modules
            console.log('\n🔧 Checking RequireJS modules...');
            const moduleState = await page.evaluate(() => {
                if (typeof require === 'undefined') {
                    return { error: 'RequireJS not loaded' };
                }
                
                const modules = {};
                try {
                    require(['Mab_CheckoutCustomization/js/view/shipping-method-cards'], (component) => {
                        modules.component = 'loaded';
                    }, () => {
                        modules.component = 'failed';
                    });
                } catch (e) {
                    modules.component = 'error: ' + e.message;
                }
                
                return modules;
            });
            
            console.log('RequireJS modules:', moduleState);
            
            // Take screenshot
            await page.screenshot({ 
                path: '/home/dev/public_html/checkout-batna-test.png',
                fullPage: true 
            });
            console.log('\n📸 Screenshot saved to checkout-batna-test.png');
            
        } else {
            console.log('\n❌ Batna option not found in region dropdown!');
            console.log('Available regions:', regionInfo.options.map(o => o.text).join(', '));
        }
        
        // Print summary
        console.log('\n' + '='.repeat(80));
        console.log('📊 TEST SUMMARY');
        console.log('='.repeat(80));
        console.log(`Total console logs: ${consoleLogs.length}`);
        console.log(`Console errors: ${consoleErrors.length}`);
        console.log(`JavaScript errors: ${jsErrors.length}`);
        console.log(`Network errors: ${networkErrors.length}`);
        
        if (consoleErrors.length > 0) {
            console.log('\n❌ Console Errors:');
            consoleErrors.forEach((err, idx) => console.log(`  ${idx + 1}. ${err}`));
        }
        
        if (jsErrors.length > 0) {
            console.log('\n💥 JavaScript Errors:');
            jsErrors.forEach((err, idx) => console.log(`  ${idx + 1}. ${err}`));
        }
        
        // Save detailed log to file
        const fs = require('fs');
        const logData = {
            timestamp: new Date().toISOString(),
            summary: {
                consoleLogs: consoleLogs.length,
                consoleErrors: consoleErrors.length,
                jsErrors: jsErrors.length,
                networkErrors: networkErrors.length
            },
            consoleLogs,
            consoleErrors,
            jsErrors,
            networkErrors
        };
        
        fs.writeFileSync(
            '/home/dev/public_html/playwright-checkout-log.json',
            JSON.stringify(logData, null, 2)
        );
        console.log('\n💾 Detailed log saved to playwright-checkout-log.json');
        
    } catch (error) {
        console.error('\n💥 Test failed with error:', error.message);
        console.error(error.stack);
    } finally {
        await browser.close();
        console.log('\n✅ Browser closed');
    }
}

// Run the test
runTest().catch(console.error);

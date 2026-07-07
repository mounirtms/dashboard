/**
 * Comprehensive Shipping Cards Diagnosis Test
 * Tests all aspects of the shipping cards display
 */

const { chromium } = require('playwright');

async function runDiagnostics() {
    console.log('='.repeat(80));
    console.log('SHIPPING CARDS COMPREHENSIVE DIAGNOSIS');
    console.log('='.repeat(80));

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 },
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    });
    const page = await context.newPage();

    // Capture console logs
    const consoleLogs = [];
    page.on('console', msg => {
        consoleLogs.push(`[${msg.type()}] ${msg.text()}`);
    });

    try {
        // Step 1: Load checkout page
        console.log('\n1. Loading checkout page...');
        const checkoutUrl = 'http://dev.technostationery.com/checkout/?cartId=U1c2sKEerK6y0Q4RaheCXyJoQ0rA7w32';
        console.log(`   URL: ${checkoutUrl}`);
        await page.goto(checkoutUrl, { 
            waitUntil: 'domcontentloaded',
            timeout: 60000 
        });
        
        const pageTitle = await page.title();
        console.log(`   Page Title: ${pageTitle}`);
        console.log(`   URL: ${page.url()}`);

        // Wait for checkout to initialize
        await page.waitForTimeout(3000);

        // Step 2: Check if shipping section exists
        console.log('\n2. Checking shipping section...');
        
        const shippingSection = await page.$('#opc-shipping_method');
        const shippingVisible = shippingSection ? await shippingSection.isVisible() : false;
        console.log(`   #opc-shipping_method exists: ${!!shippingSection}`);
        console.log(`   #opc-shipping_method visible: ${shippingVisible}`);

        if (shippingSection) {
            const styles = await page.evaluate(() => {
                const el = document.querySelector('#opc-shipping_method');
                if (!el) return null;
                const computed = window.getComputedStyle(el);
                return {
                    display: computed.display,
                    visibility: computed.visibility,
                    opacity: computed.opacity,
                    height: computed.height
                };
            });
            console.log(`   Computed styles:`, styles);
        }

        // Step 3: Check shipping method form
        console.log('\n3. Checking shipping method form...');
        
        const form = await page.$('#co-shipping-method-form');
        const formVisible = form ? await form.isVisible() : false;
        console.log(`   #co-shipping-method-form exists: ${!!form}`);
        console.log(`   #co-shipping-method-form visible: ${formVisible}`);

        if (form) {
            const formStyles = await page.evaluate(() => {
                const el = document.querySelector('#co-shipping-method-form');
                if (!el) return null;
                const computed = window.getComputedStyle(el);
                return {
                    display: computed.display,
                    visibility: computed.visibility,
                    opacity: computed.opacity
                };
            });
            console.log(`   Form styles:`, formStyles);
        }

        // Step 4: Check for before-shipping-method-form region
        console.log('\n4. Checking before-shipping-method-form region...');
        
        const hasRegion = await page.evaluate(() => {
            const scripts = Array.from(document.querySelectorAll('script[type="text/x-magento-init"]'));
            const checkoutConfig = scripts.find(s => s.textContent.includes('checkout') && s.textContent.includes('before-shipping-method-form'));
            return !!checkoutConfig;
        });
        console.log(`   before-shipping-method-form region in config: ${hasRegion}`);

        // Step 5: Check for shipping cards wrapper
        console.log('\n5. Checking shipping cards wrapper...');
        
        const wrapper = await page.$('.shipping-methods-cards-wrapper');
        const wrapperVisible = wrapper ? await wrapper.isVisible() : false;
        console.log(`   .shipping-methods-cards-wrapper exists: ${!!wrapper}`);
        console.log(`   .shipping-methods-cards-wrapper visible: ${wrapperVisible}`);

        if (wrapper) {
            const wrapperStyles = await page.evaluate(() => {
                const el = document.querySelector('.shipping-methods-cards-wrapper');
                if (!el) return null;
                const computed = window.getComputedStyle(el);
                return {
                    display: computed.display,
                    visibility: computed.visibility,
                    opacity: computed.opacity,
                    position: computed.position,
                    zIndex: computed.zIndex
                };
            });
            console.log(`   Wrapper styles:`, wrapperStyles);

            const wrapperHTML = await page.evaluate(() => {
                const el = document.querySelector('.shipping-methods-cards-wrapper');
                return el ? el.outerHTML.substring(0, 500) : null;
            });
            console.log(`   Wrapper HTML (first 500 chars):`, wrapperHTML);
        }

        // Step 6: Check for shipping cards
        console.log('\n6. Checking shipping cards...');
        
        const cards = await page.$$('.shipping-card');
        console.log(`   Shipping cards count: ${cards.length}`);

        if (cards.length > 0) {
            for (let i = 0; i < cards.length; i++) {
                const cardInfo = await page.evaluate((idx) => {
                    const card = document.querySelectorAll('.shipping-card')[idx];
                    if (!card) return null;
                    
                    const title = card.querySelector('.method-name');
                    const price = card.querySelector('.price-amount, .free-badge');
                    const computed = window.getComputedStyle(card);
                    
                    return {
                        title: title ? title.textContent : 'N/A',
                        price: price ? price.textContent : 'N/A',
                        display: computed.display,
                        visibility: computed.visibility,
                        opacity: computed.opacity
                    };
                }, i);
                console.log(`   Card ${i + 1}:`, cardInfo);
            }
        }

        // Step 7: Check RequireJS component loading
        console.log('\n7. Checking RequireJS component...');
        
        const componentStatus = await page.evaluate(() => {
            if (typeof require === 'undefined') {
                return { status: 'RequireJS not loaded' };
            }
            
            try {
                const loaded = require.s.contexts._.defined;
                const hasComponent = 'Mab_CheckoutCustomization/js/view/shipping-method-cards' in loaded;
                return {
                    status: 'RequireJS loaded',
                    componentLoaded: hasComponent,
                    loadedModules: Object.keys(loaded).filter(k => k.includes('shipping')).length
                };
            } catch (e) {
                return { status: 'Error checking RequireJS', error: e.message };
            }
        });
        console.log(`   Component status:`, componentStatus);

        // Step 8: Check Knockout bindings
        console.log('\n8. Checking Knockout bindings...');
        
        const koStatus = await page.evaluate(() => {
            if (typeof ko === 'undefined') {
                return { status: 'Knockout not loaded' };
            }
            
            const wrapper = document.querySelector('.shipping-methods-cards-wrapper');
            if (!wrapper) {
                return { status: 'Wrapper not found' };
            }
            
            const bindingContext = ko.dataFor(wrapper);
            return {
                status: 'Knockout loaded',
                hasBinding: !!bindingContext,
                contextType: bindingContext ? typeof bindingContext : 'N/A'
            };
        });
        console.log(`   Knockout status:`, koStatus);

        // Step 9: Check CSS rules
        console.log('\n9. Checking CSS rules affecting shipping methods...');
        
        const cssRules = await page.evaluate(() => {
            const results = [];
            const selectors = [
                '.checkout-shipping-method',
                '.table-checkout-shipping-method',
                '#checkout-shipping-method-load',
                '#opc-shipping_method',
                '.methods-shipping',
                '.shipping-methods-cards-wrapper'
            ];
            
            selectors.forEach(selector => {
                const el = document.querySelector(selector);
                if (el) {
                    const computed = window.getComputedStyle(el);
                    results.push({
                        selector,
                        display: computed.display,
                        visibility: computed.visibility,
                        opacity: computed.opacity
                    });
                }
            });
            
            return results;
        });
        
        cssRules.forEach(rule => {
            console.log(`   ${rule.selector}:`, rule);
        });

        // Step 10: Check console errors
        console.log('\n10. Console messages:');
        const errors = consoleLogs.filter(log => log.includes('[error]'));
        const warnings = consoleLogs.filter(log => log.includes('[warning]'));
        console.log(`   Errors: ${errors.length}`);
        console.log(`   Warnings: ${warnings.length}`);
        
        if (errors.length > 0) {
            console.log('\n   Recent errors:');
            errors.slice(-5).forEach(err => console.log(`     ${err}`));
        }

        // Screenshot
        console.log('\n11. Taking screenshot...');
        await page.screenshot({ path: './screenshots/diagnosis-full-page.png', fullPage: true });
        console.log('   Screenshot saved: ./screenshots/diagnosis-full-page.png');

        console.log('\n' + '='.repeat(80));
        console.log('DIAGNOSIS COMPLETE');
        console.log('='.repeat(80));

    } catch (error) {
        console.error('\nERROR during diagnosis:', error.message);
        await page.screenshot({ path: './screenshots/diagnosis-error.png' });
    } finally {
        await browser.close();
    }
}

runDiagnostics().catch(console.error);

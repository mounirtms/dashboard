const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
    console.log('🔍 Starting Comprehensive Checkout Diagnostics...\n');
    
    const browser = await chromium.launch({ 
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    
    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 },
        locale: 'fr-FR'
    });
    
    const page = await context.newPage();
    
    // Capture console logs
    const consoleLogs = [];
    const errors = [];
    
    page.on('console', msg => {
        const log = {
            type: msg.type(),
            text: msg.text(),
            timestamp: new Date().toISOString()
        };
        consoleLogs.push(log);
        
        // Print important logs
        if (msg.text().includes('[Shipping') || msg.type() === 'error') {
            console.log(`[${msg.type().toUpperCase()}] ${msg.text()}`);
        }
    });
    
    page.on('pageerror', error => {
        errors.push({
            message: error.message,
            stack: error.stack,
            timestamp: new Date().toISOString()
        });
        console.error(`❌ PAGE ERROR: ${error.message}`);
    });
    
    try {
        // Navigate to checkout
        console.log('📍 Step 1: Loading checkout page...');
        await page.goto('https://dev.technostationery.com/checkout', { 
            waitUntil: 'networkidle',
            timeout: 60000 
        });
        
        await page.waitForTimeout(5000);
        console.log('✅ Checkout page loaded\n');
        
        // Check page structure
        console.log('📍 Step 2: Analyzing page structure...');
        const pageStructure = await page.evaluate(() => {
            return {
                hasCheckout: !!document.querySelector('#checkout'),
                hasShippingStep: !!document.querySelector('#shipping'),
                hasRegionField: !!document.querySelector('select[name="region_id"]'),
                hasShippingWrapper: !!document.querySelector('.shipping-methods-cards-wrapper'),
                url: window.location.href
            };
        });
        
        console.log('Page Structure:', JSON.stringify(pageStructure, null, 2));
        
        if (!pageStructure.hasRegionField) {
            console.log('\n⚠️  No region field found - may need to add product to cart first');
            
            // Try to navigate to a product and add to cart
            console.log('📍 Attempting to add product to cart...');
            await page.goto('https://dev.technostationery.com/', { waitUntil: 'networkidle' });
            await page.waitForTimeout(3000);
            
            // Click first product
            const firstProduct = await page.$('.product-item a.product-photo').first();
            if (firstProduct) {
                await firstProduct.click();
                await page.waitForTimeout(3000);
                
                // Add to cart
                const addToCartBtn = await page.$('#product-addtocart-button');
                if (addToCartBtn) {
                    await addToCartBtn.click();
                    await page.waitForTimeout(3000);
                    
                    // Go to checkout
                    await page.goto('https://dev.technostationery.com/checkout', { 
                        waitUntil: 'networkidle',
                        timeout: 60000 
                    });
                    await page.waitForTimeout(5000);
                    console.log('✅ Navigated to checkout with product\n');
                }
            }
        }
        
        // Fill address form
        console.log('📍 Step 3: Filling address form...');
        
        const addressFields = {
            'firstname': 'Test',
            'lastname': 'User',
            'street[0]': '123 Rue Test',
            'city': 'Test City',
            'telephone': '0555123456'
        };
        
        for (const [field, value] of Object.entries(addressFields)) {
            const input = await page.$(`input[name="${field}"], input[name="shippingAddress.${field}"]`).catch(() => null);
            if (input) {
                await input.fill(value);
                console.log(`  ✅ Filled ${field}`);
            }
        }
        
        // Select country (Algeria)
        const countrySelect = await page.$('select[name="country_id"], select[name="shippingAddress.country_id"]').catch(() => null);
        if (countrySelect) {
            await countrySelect.selectOption('DZ');
            console.log('  ✅ Selected Algeria (DZ)');
            await page.waitForTimeout(1000);
        }
        
        // Select wilaya (Alger - 859)
        console.log('\n📍 Step 4: Selecting wilaya (Alger - 859)...');
        const regionSelect = await page.$('select[name="region_id"], select[name="shippingAddress.region_id"]').catch(() => null);
        
        if (regionSelect) {
            await regionSelect.selectOption('859');
            console.log('  ✅ Selected Alger (859)');
            
            // Wait for shipping methods to load
            console.log('  ⏳ Waiting for shipping methods to load (5 seconds)...');
            await page.waitForTimeout(5000);
            
            // Check shipping cards
            console.log('\n📍 Step 5: Checking shipping cards...');
            const shippingInfo = await page.evaluate(() => {
                const wrapper = document.querySelector('.shipping-methods-cards-wrapper');
                const cards = document.querySelectorAll('.shipping-card');
                
                return {
                    wrapperExists: !!wrapper,
                    wrapperVisible: wrapper ? window.getComputedStyle(wrapper).display !== 'none' : false,
                    wrapperStyles: wrapper ? {
                        display: window.getComputedStyle(wrapper).display,
                        visibility: window.getComputedStyle(wrapper).visibility,
                        opacity: window.getComputedStyle(wrapper).opacity,
                        height: window.getComputedStyle(wrapper).height
                    } : null,
                    cardsCount: cards.length,
                    cards: Array.from(cards).map(card => ({
                        methodCode: card.getAttribute('data-method-code'),
                        title: card.querySelector('.method-name, .method-title')?.textContent.trim(),
                        price: card.querySelector('.price-amount, .free-badge')?.textContent.trim(),
                        isVisible: window.getComputedStyle(card).display !== 'none',
                        classes: card.className
                    }))
                };
            });
            
            console.log('Shipping Info:', JSON.stringify(shippingInfo, null, 2));
            
            if (shippingInfo.cardsCount > 0) {
                console.log(`\n✅ Found ${shippingInfo.cardsCount} shipping cards`);
                
                // Take screenshot of shipping cards
                await page.screenshot({ 
                    path: '/home/dev/public_html/checkout-shipping-cards.png',
                    fullPage: true 
                });
                console.log('📸 Screenshot saved: checkout-shipping-cards.png\n');
                
                // Click first card
                console.log('📍 Step 6: Clicking first shipping card...');
                const firstCard = await page.$('.shipping-card').first();
                if (firstCard) {
                    await firstCard.click();
                    await page.waitForTimeout(2000);
                    
                    // Check if card is selected
                    const cardSelected = await page.evaluate(() => {
                        const card = document.querySelector('.shipping-card.selected');
                        return !!card;
                    });
                    
                    console.log(`  Card selected: ${cardSelected ? '✅ YES' : '❌ NO'}`);
                    
                    // Check for Next/Continue button
                    console.log('\n📍 Step 7: Checking Next/Continue button...');
                    const buttonInfo = await page.evaluate(() => {
                        const buttons = [
                            'button.button.action.continue.primary',
                            'button[data-role="opc-continue"]',
                            '.actions-toolbar button.primary',
                            '#shipping-method-buttons-container button',
                            '.checkout-shipping-method .actions-toolbar button'
                        ];
                        
                        let foundButton = null;
                        for (const selector of buttons) {
                            const btn = document.querySelector(selector);
                            if (btn) {
                                foundButton = {
                                    selector: selector,
                                    exists: true,
                                    visible: window.getComputedStyle(btn).display !== 'none',
                                    enabled: !btn.disabled,
                                    text: btn.textContent.trim(),
                                    styles: {
                                        display: window.getComputedStyle(btn).display,
                                        visibility: window.getComputedStyle(btn).visibility,
                                        opacity: window.getComputedStyle(btn).opacity,
                                        position: window.getComputedStyle(btn).position
                                    }
                                };
                                break;
                            }
                        }
                        
                        return foundButton || { exists: false };
                    });
                    
                    console.log('Button Info:', JSON.stringify(buttonInfo, null, 2));
                    
                    if (buttonInfo.exists) {
                        if (buttonInfo.visible && buttonInfo.enabled) {
                            console.log('\n🎉 SUCCESS: Next button is visible and enabled!');
                        } else {
                            console.log('\n❌ ISSUE: Next button exists but:');
                            if (!buttonInfo.visible) console.log('   - Button is NOT visible');
                            if (!buttonInfo.enabled) console.log('   - Button is DISABLED');
                        }
                    } else {
                        console.log('\n❌ CRITICAL: Next/Continue button NOT FOUND in DOM!');
                        console.log('   This means Magento\'s checkout validation is not passing');
                    }
                    
                    // Check quote state
                    console.log('\n📍 Step 8: Checking Magento quote state...');
                    const quoteState = await page.evaluate(() => {
                        if (typeof require !== 'undefined') {
                            try {
                                return new Promise((resolve) => {
                                    require(['Magento_Checkout/js/model/quote'], function(quote) {
                                        const shippingMethod = quote.shippingMethod();
                                        resolve({
                                            hasShippingMethod: !!shippingMethod,
                                            shippingMethod: shippingMethod ? {
                                                carrier_code: shippingMethod.carrier_code,
                                                method_code: shippingMethod.method_code,
                                                carrier_title: shippingMethod.carrier_title,
                                                method_title: shippingMethod.method_title
                                            } : null,
                                            stepNavigator: typeof window.checkoutStepNavigator !== 'undefined'
                                        });
                                    });
                                });
                            } catch (e) {
                                return { error: e.message };
                            }
                        }
                        return { requireJS: 'not available' };
                    });
                    
                    console.log('Quote State:', JSON.stringify(quoteState, null, 2));
                    
                    // Take final screenshot
                    await page.screenshot({ 
                        path: '/home/dev/public_html/checkout-after-selection.png',
                        fullPage: true 
                    });
                    console.log('\n📸 Final screenshot saved: checkout-after-selection.png');
                    
                } else {
                    console.log('\n❌ No shipping cards found to click');
                }
                
            } else {
                console.log('\n❌ NO SHIPPING CARDS FOUND!');
                console.log('   Wrapper exists:', shippingInfo.wrapperExists);
                console.log('   Wrapper visible:', shippingInfo.wrapperVisible);
                
                // Take screenshot showing the issue
                await page.screenshot({ 
                    path: '/home/dev/public_html/checkout-no-shipping-cards.png',
                    fullPage: true 
                });
                console.log('📸 Screenshot saved: checkout-no-shipping-cards.png');
            }
            
        } else {
            console.log('\n❌ Region select not found!');
        }
        
        // Summary
        console.log('\n' + '='.repeat(80));
        console.log('DIAGNOSTICS SUMMARY');
        console.log('='.repeat(80));
        console.log(`Total console logs: ${consoleLogs.length}`);
        console.log(`Total errors: ${errors.length}`);
        
        if (errors.length > 0) {
            console.log('\n❌ Errors encountered:');
            errors.forEach((err, idx) => {
                console.log(`  ${idx + 1}. ${err.message}`);
            });
        }
        
        // Check for specific issues
        const shippingErrors = consoleLogs.filter(log => 
            log.text.includes('Shipping') && log.type === 'error'
        );
        
        if (shippingErrors.length > 0) {
            console.log('\n⚠️  Shipping-related errors:');
            shippingErrors.forEach(err => console.log(`  - ${err.text}`));
        }
        
        console.log('\n' + '='.repeat(80));
        
    } catch (error) {
        console.error(`\n❌ Fatal error: ${error.message}`);
        console.error(error.stack);
        
        await page.screenshot({ 
            path: '/home/dev/public_html/checkout-diagnostic-error.png',
            fullPage: true 
        });
    } finally {
        await browser.close();
    }
})();

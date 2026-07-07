/**
 * Enhanced Full Order Test - Blida with Better Form Handling
 * Includes waits, retries, and comprehensive logging
 */

const { chromium } = require('playwright');
const fs = require('fs');

async function enhancedFullOrderTest() {
    console.log('\n' + '='.repeat(80));
    console.log('ENHANCED FULL ORDER TEST - BLIDA TECHNO PICKUP');
    console.log('With improved form handling and comprehensive logging');
    console.log('='.repeat(80) + '\n');
    
    const browser = await chromium.launch({ 
        headless: true,
        slowMo: 50  // Slow down actions slightly
    });
    
    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 },
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    });
    
    const page = await context.newPage();
    
    // Comprehensive console tracking
    const consoleLogs = {
        shippingCards: [],
        errors: [],
        warnings: [],
        ajax: [],
        all: []
    };
    
    page.on('console', msg => {
        const text = msg.text();
        const type = msg.type();
        
        consoleLogs.all.push({ type, text, timestamp: new Date().toISOString() });
        
        if (text.includes('[Shipping Cards]')) {
            consoleLogs.shippingCards.push(text);
            console.log(`📦 ${text}`);
        }
        
        if (type === 'error' && !text.includes('CORS') && !text.includes('webpushr')) {
            consoleLogs.errors.push(text);
            console.log(`❌ ERROR: ${text}`);
        }
        
        if (type === 'warning') {
            consoleLogs.warnings.push(text);
        }
        
        if (text.includes('estimate-shipping-methods') || text.includes('XHR')) {
            consoleLogs.ajax.push(text);
        }
    });
    
    page.on('pageerror', error => {
        if (!error.message.includes('webpushr')) {
            console.log(`🔴 Page Error: ${error.message}`);
            consoleLogs.errors.push(`Page Error: ${error.message}`);
        }
    });
    
    // Track network requests for shipping API
    const apiCalls = [];
    page.on('response', async response => {
        const url = response.url();
        if (url.includes('estimate-shipping-methods') || url.includes('shipping-information')) {
            const status = response.status();
            console.log(`\n🌐 API Call: ${url.split('/').pop()}`);
            console.log(`   Status: ${status}`);
            
            try {
                const body = await response.json();
                apiCalls.push({ url, status, body, timestamp: new Date().toISOString() });
                console.log(`   Response: ${JSON.stringify(body).substring(0, 150)}...`);
            } catch (e) {
                apiCalls.push({ url, status, error: 'Could not parse response' });
            }
        }
    });
    
    const screenshotDir = './screenshots/blida-enhanced';
    if (!fs.existsSync(screenshotDir)) {
        fs.mkdirSync(screenshotDir, { recursive: true });
    }
    
    let orderSuccess = false;
    let orderNumber = null;
    
    try {
        console.log('═══════════════════════════════════════════════════════════');
        console.log('STEP 1: ADD PRODUCTS TO CART');
        console.log('═══════════════════════════════════════════════════════════\n');
        
        // Add Product 1
        console.log('Adding Product 1...');
        await page.goto('https://dev.technostationery.com/palette-de-peinture-aquarelel-rouge-ark-ref-313.html', {
            waitUntil: 'domcontentloaded',
            timeout: 30000
        });
        await page.waitForTimeout(2000);
        
        const addBtn1 = page.locator('button#product-addtocart-button').first();
        if (await addBtn1.count() > 0) {
            await addBtn1.click();
            console.log('✅ Product 1 added');
            await page.waitForTimeout(3000);
        }
        
        // Add Product 2
        console.log('\nAdding Product 2...');
        await page.goto('https://dev.technostationery.com/stylo-a-plume-classic-rouge-blanc-maped-ref-222212.html', {
            waitUntil: 'domcontentloaded',
            timeout: 30000
        });
        await page.waitForTimeout(2000);
        
        const qtyInput = page.locator('input#qty').first();
        if (await qtyInput.count() > 0) {
            await qtyInput.fill('2');
        }
        
        const addBtn2 = page.locator('button#product-addtocart-button').first();
        if (await addBtn2.count() > 0) {
            await addBtn2.click();
            console.log('✅ Product 2 added (x2)');
            await page.waitForTimeout(3000);
        }
        
        await page.screenshot({ path: `${screenshotDir}/01-cart-ready.png`, timeout: 10000 });
        
        console.log('\n═══════════════════════════════════════════════════════════');
        console.log('STEP 2: NAVIGATE TO CHECKOUT');
        console.log('═══════════════════════════════════════════════════════════\n');
        
        await page.goto('https://dev.technostationery.com/checkout/', {
            waitUntil: 'domcontentloaded',
            timeout: 30000
        });
        
        console.log('⏳ Waiting for checkout to initialize (10 seconds)...');
        await page.waitForTimeout(10000);  // Longer wait for Magento
        
        await page.screenshot({ path: `${screenshotDir}/02-checkout-loaded.png`, fullPage: true, timeout: 10000 });
        
        console.log('\n═══════════════════════════════════════════════════════════');
        console.log('STEP 3: FILL SHIPPING FORM');
        console.log('═══════════════════════════════════════════════════════════\n');
        
        // Helper function with better error handling
        const fillFieldSafe = async (selector, value, label) => {
            try {
                await page.waitForSelector(selector, { state: 'attached', timeout: 5000 });
                const field = page.locator(selector).first();
                
                if (await field.count() > 0) {
                    // Try to make field visible by clicking nearby
                    await field.scrollIntoViewIfNeeded();
                    await page.waitForTimeout(500);
                    
                    await field.fill(value);
                    console.log(`   ✅ ${label}: ${value}`);
                    return true;
                }
            } catch (e) {
                console.log(`   ⚠️  ${label}: Field not accessible (${e.message})`);
            }
            return false;
        };
        
        // Check if guest checkout
        const emailField = page.locator('input#customer-email').first();
        if (await emailField.count() > 0) {
            console.log('🔓 Guest Checkout Mode\n');
            
            try {
                // Wait for email field specifically
                await page.waitForSelector('input#customer-email', { state: 'visible', timeout: 10000 });
                await emailField.fill('test.blida@technostationery.com');
                console.log('   ✅ Email: test.blida@technostationery.com');
                await page.waitForTimeout(2000);
            } catch (e) {
                console.log('   ⚠️  Email field not visible yet');
            }
        }
        
        // Wait for form to be ready
        await page.waitForTimeout(3000);
        
        console.log('\n📝 Filling address fields...\n');
        
        // Use more flexible selectors and try multiple approaches
        await fillFieldSafe('input[name="firstname"]', 'Ahmed', 'First Name');
        await fillFieldSafe('input[name="lastname"]', 'Benali', 'Last Name');
        await fillFieldSafe('input[name="company"]', 'Techno Stationery', 'Company');
        await fillFieldSafe('input[name="street[0]"]', '123 Rue Larbi Ben Mhidi', 'Street');
        await fillFieldSafe('input[name="city"]', 'Blida', 'City');
        await fillFieldSafe('input[name="postcode"]', '09000', 'Postcode');
        await fillFieldSafe('input[name="telephone"]', '0550123456', 'Telephone');
        
        await page.waitForTimeout(2000);
        await page.screenshot({ path: `${screenshotDir}/03-fields-filled.png`, fullPage: true, timeout: 10000 });
        
        console.log('\n📍 Selecting Country and Region...\n');
        
        // Try to select country with better handling
        try {
            const countrySelect = page.locator('select[name="country_id"]').first();
            const countryExists = await countrySelect.count() > 0;
            
            if (countryExists) {
                // Check if country is already set
                const currentCountry = await countrySelect.evaluate(el => el.value);
                console.log(`   Current country: ${currentCountry}`);
                
                if (currentCountry !== 'DZ') {
                    // Try to interact with the select
                    await countrySelect.scrollIntoViewIfNeeded();
                    await page.waitForTimeout(500);
                    
                    // Try using JavaScript to set value
                    await page.evaluate(() => {
                        const select = document.querySelector('select[name="country_id"]');
                        if (select) {
                            select.value = 'DZ';
                            select.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    });
                    
                    console.log('   ✅ Country: Algeria (DZ) - set via JavaScript');
                    await page.waitForTimeout(3000);  // Wait for regions to load
                } else {
                    console.log('   ✅ Country: Already set to Algeria');
                }
            }
        } catch (e) {
            console.log(`   ⚠️  Country select issue: ${e.message}`);
            console.log('   Attempting to proceed anyway...');
        }
        
        await page.screenshot({ path: `${screenshotDir}/04-country-set.png`, fullPage: true, timeout: 10000 });
        
        // Select Blida region
        console.log('\n🗺️  Selecting Blida region...\n');
        
        try {
            await page.waitForSelector('select[name="region_id"]', { state: 'attached', timeout: 10000 });
            
            const regionSelect = page.locator('select[name="region_id"]').first();
            const regionExists = await regionSelect.count() > 0;
            
            if (regionExists) {
                // Check available options
                const options = await page.evaluate(() => {
                    const select = document.querySelector('select[name="region_id"]');
                    if (!select) return [];
                    return Array.from(select.options).map(opt => ({
                        value: opt.value,
                        text: opt.text
                    }));
                });
                
                console.log(`   Found ${options.length} regions in dropdown`);
                const blidaOption = options.find(opt => opt.text.toLowerCase().includes('blida'));
                if (blidaOption) {
                    console.log(`   Blida option: ${blidaOption.text} (ID: ${blidaOption.value})`);
                }
                
                // Set Blida using JavaScript
                await page.evaluate(() => {
                    const select = document.querySelector('select[name="region_id"]');
                    if (select) {
                        select.value = '867';  // Blida Magento ID
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
                
                console.log('   ✅ Region: Blida (867) - set via JavaScript');
                
                console.log('\n⏳ Waiting 8 seconds for shipping rates API call...\n');
                await page.waitForTimeout(8000);
            }
        } catch (e) {
            console.log(`   ⚠️  Region select issue: ${e.message}`);
        }
        
        await page.screenshot({ path: `${screenshotDir}/05-region-selected.png`, fullPage: true, timeout: 10000 });
        
        console.log('═══════════════════════════════════════════════════════════');
        console.log('STEP 4: CHECK SHIPPING METHODS');
        console.log('═══════════════════════════════════════════════════════════\n');
        
        // Detailed shipping state check
        const shippingState = await page.evaluate(() => {
            const wrapper = document.querySelector('.shipping-methods-cards-wrapper');
            const cards = document.querySelectorAll('.shipping-card');
            const errorMsg = document.querySelector('.shipping-error-message, .shipping-methods-error');
            
            const result = {
                wrapperExists: !!wrapper,
                wrapperHTML: wrapper ? wrapper.outerHTML.substring(0, 500) : null,
                wrapperStyles: null,
                cardsCount: cards.length,
                cards: [],
                errorMessage: errorMsg ? errorMsg.textContent.trim() : null
            };
            
            if (wrapper) {
                const styles = window.getComputedStyle(wrapper);
                result.wrapperStyles = {
                    display: styles.display,
                    visibility: styles.visibility,
                    opacity: styles.opacity,
                    height: styles.height,
                    position: styles.position,
                    zIndex: styles.zIndex
                };
            }
            
            cards.forEach((card, index) => {
                const methodTitle = card.querySelector('.method-title, .method-name');
                const priceElement = card.querySelector('.price-amount, .free-badge');
                const deliveryTime = card.querySelector('.delivery-time');
                const cardStyles = window.getComputedStyle(card);
                
                result.cards.push({
                    index: index + 1,
                    methodCode: card.getAttribute('data-method-code'),
                    title: methodTitle ? methodTitle.textContent.trim() : 'N/A',
                    price: priceElement ? priceElement.textContent.trim() : 'N/A',
                    deliveryTime: deliveryTime ? deliveryTime.textContent.trim() : 'N/A',
                    isFree: card.classList.contains('free-shipping'),
                    isSelected: card.classList.contains('selected'),
                    classes: card.className,
                    visible: cardStyles.display !== 'none' && cardStyles.visibility !== 'hidden',
                    display: cardStyles.display,
                    position: cardStyles.position
                });
            });
            
            return result;
        });
        
        console.log('📦 Shipping Methods State:\n');
        console.log(`   Wrapper Exists: ${shippingState.wrapperExists ? '✅ Yes' : '❌ No'}`);
        
        if (shippingState.wrapperExists && shippingState.wrapperStyles) {
            console.log('   Wrapper Styles:');
            console.log(`     Display: ${shippingState.wrapperStyles.display}`);
            console.log(`     Visibility: ${shippingState.wrapperStyles.visibility}`);
            console.log(`     Opacity: ${shippingState.wrapperStyles.opacity}`);
            console.log(`     Height: ${shippingState.wrapperStyles.height}`);
            console.log(`     Position: ${shippingState.wrapperStyles.position}`);
            console.log(`     Z-Index: ${shippingState.wrapperStyles.zIndex}`);
        }
        
        console.log(`\n   Shipping Cards: ${shippingState.cardsCount} rendered\n`);
        
        if (shippingState.cardsCount > 0) {
            console.log('   📋 Available Shipping Methods:\n');
            
            shippingState.cards.forEach(card => {
                const freeLabel = card.isFree ? ' 🎉 FREE' : '';
                const selectedLabel = card.isSelected ? ' ✓ SELECTED' : '';
                console.log(`   ${card.index}. ${card.title}${freeLabel}${selectedLabel}`);
                console.log(`      Code: ${card.methodCode}`);
                console.log(`      Price: ${card.price}`);
                console.log(`      Delivery: ${card.deliveryTime}`);
                console.log(`      Visible: ${card.visible ? '✅' : '❌'}`);
                console.log(`      Display: ${card.display}\n`);
            });
            
            console.log('═══════════════════════════════════════════════════════════');
            console.log('STEP 5: SELECT SHIPPING METHOD');
            console.log('═══════════════════════════════════════════════════════════\n');
            
            // Find and click free shipping (Retrait Techno Blida)
            const cards = await page.locator('.shipping-card').all();
            let selectedMethod = null;
            
            for (let i = 0; i < cards.length; i++) {
                const card = cards[i];
                const isFree = await card.evaluate(el => el.classList.contains('free-shipping'));
                const title = await card.locator('.method-title, .method-name').first().textContent();
                
                if (isFree) {
                    console.log(`🎯 Selecting FREE method: ${title.trim()}`);
                    await card.click();
                    selectedMethod = title.trim();
                    await page.waitForTimeout(2000);
                    break;
                }
            }
            
            if (!selectedMethod) {
                console.log('⚠️  No free method found, selecting first available...');
                await cards[0].click();
                selectedMethod = await cards[0].locator('.method-title, .method-name').first().textContent();
                await page.waitForTimeout(2000);
            }
            
            console.log(`✅ Selected: ${selectedMethod}`);
            
            await page.screenshot({ path: `${screenshotDir}/06-method-selected.png`, fullPage: true, timeout: 10000 });
            
            // Check continue button
            const continueBtn = page.locator('button.continue, button[data-role="opc-continue"]').first();
            const isBtnEnabled = await continueBtn.evaluate(btn => !btn.disabled);
            
            console.log(`\n📍 Continue Button: ${isBtnEnabled ? '✅ Enabled' : '❌ Disabled'}`);
            
            if (isBtnEnabled) {
                console.log('\n═══════════════════════════════════════════════════════════');
                console.log('STEP 6: PROCEED TO PAYMENT');
                console.log('═══════════════════════════════════════════════════════════\n');
                
                console.log('🖱️  Clicking "Next" button...');
                await continueBtn.click();
                
                console.log('⏳ Waiting for payment step (5 seconds)...');
                await page.waitForTimeout(5000);
                
                await page.screenshot({ path: `${screenshotDir}/07-payment-step.png`, fullPage: true, timeout: 10000 });
                
                console.log('✅ Reached payment step\n');
                
                console.log('═══════════════════════════════════════════════════════════');
                console.log('STEP 7: SELECT PAYMENT METHOD');
                console.log('═══════════════════════════════════════════════════════════\n');
                
                // Wait for payment methods to load
                await page.waitForTimeout(2000);
                
                // Try to select Cash on Delivery
                try {
                    const cashRadio = page.locator('input[value="cashondelivery"]').first();
                    if (await cashRadio.count() > 0) {
                        await cashRadio.check();
                        console.log('✅ Payment: Cash on Delivery selected');
                        await page.waitForTimeout(1500);
                    } else {
                        // Fallback: select first payment method
                        const firstPayment = page.locator('.payment-method input[type="radio"]').first();
                        if (await firstPayment.count() > 0) {
                            await firstPayment.check();
                            console.log('✅ Payment: First available method selected');
                            await page.waitForTimeout(1500);
                        }
                    }
                } catch (e) {
                    console.log(`⚠️  Payment selection: ${e.message}`);
                }
                
                await page.screenshot({ path: `${screenshotDir}/08-payment-selected.png`, fullPage: true, timeout: 10000 });
                
                // Get order summary
                const orderSummary = await page.evaluate(() => {
                    const getPrice = (selector) => {
                        const el = document.querySelector(selector);
                        return el ? el.textContent.trim() : 'N/A';
                    };
                    
                    return {
                        subtotal: getPrice('.totals .sub .price, .sub .amount .price'),
                        shipping: getPrice('.totals .shipping .price, .shipping .amount .price'),
                        grandTotal: getPrice('.totals .grand .price, .grand.totals .amount .price')
                    };
                });
                
                console.log('\n💰 Order Summary:');
                console.log(`   Subtotal: ${orderSummary.subtotal}`);
                console.log(`   Shipping: ${orderSummary.shipping}`);
                console.log(`   Grand Total: ${orderSummary.grandTotal}`);
                
                console.log('\n═══════════════════════════════════════════════════════════');
                console.log('STEP 8: PLACE ORDER');
                console.log('═══════════════════════════════════════════════════════════\n');
                
                const placeOrderBtn = page.locator('button.checkout, button[title="Place Order"]').first();
                
                if (await placeOrderBtn.count() > 0) {
                    console.log('🖱️  Clicking "Place Order" button...');
                    await placeOrderBtn.click();
                    
                    console.log('⏳ Processing order (waiting 10 seconds)...\n');
                    await page.waitForTimeout(10000);
                    
                    const currentUrl = page.url();
                    console.log(`Current URL: ${currentUrl}`);
                    
                    if (currentUrl.includes('/success')) {
                        orderSuccess = true;
                        
                        console.log('\n🎉 ═══════════════════════════════════════════════════════════');
                        console.log('🎉 ORDER PLACED SUCCESSFULLY!');
                        console.log('🎉 ═══════════════════════════════════════════════════════════\n');
                        
                        await page.screenshot({ path: `${screenshotDir}/09-order-success.png`, fullPage: true, timeout: 10000 });
                        
                        // Extract order number
                        const orderInfo = await page.evaluate(() => {
                            const orderText = document.body.textContent;
                            const match = orderText.match(/order\s+number\s*:?\s*#?(\d+)/i) || 
                                         orderText.match(/#(\d{6,})/);
                            return {
                                orderNumber: match ? match[1] : null,
                                pageText: orderText.substring(0, 500)
                            };
                        });
                        
                        if (orderInfo.orderNumber) {
                            orderNumber = orderInfo.orderNumber;
                            console.log(`📋 Order Number: #${orderNumber}`);
                        }
                        
                        console.log('\n✅ Order Confirmation:');
                        console.log(`   Customer: Ahmed Benali`);
                        console.log(`   Email: test.blida@technostationery.com`);
                        console.log(`   Phone: 0550123456`);
                        console.log(`   Address: 123 Rue Larbi Ben Mhidi, Blida 09000`);
                        console.log(`   Shipping: ${selectedMethod || 'Selected method'}`);
                        console.log(`   Payment: Cash on Delivery`);
                        console.log(`   Total: ${orderSummary.grandTotal}`);
                        
                    } else {
                        console.log('\n⚠️  Order may not have completed');
                        console.log(`   Expected: /success`);
                        console.log(`   Got: ${currentUrl}`);
                        
                        await page.screenshot({ path: `${screenshotDir}/09-order-status.png`, fullPage: true, timeout: 10000 });
                    }
                } else {
                    console.log('❌ Place Order button not found');
                }
                
            } else {
                console.log('❌ Cannot proceed: Continue button is disabled');
                console.log('   This may indicate:');
                console.log('   - Shipping method not properly selected');
                console.log('   - Form validation errors');
                console.log('   - Required fields missing');
            }
            
        } else {
            console.log('❌ NO SHIPPING CARDS RENDERED!\n');
            
            if (shippingState.errorMessage) {
                console.log(`   Error Message: "${shippingState.errorMessage}"`);
            }
            
            console.log('   Possible causes:');
            console.log('   1. No shipping rates configured for Blida in Mageplaza Table Rate');
            console.log('   2. Region was not properly selected');
            console.log('   3. API call failed or returned error rates');
            console.log('   4. JavaScript error prevented rendering\n');
            
            console.log('   Check console logs below for more details.');
        }
        
        // Comprehensive logging summary
        console.log('\n═══════════════════════════════════════════════════════════');
        console.log('CONSOLE LOGS SUMMARY');
        console.log('═══════════════════════════════════════════════════════════\n');
        
        console.log(`📊 Statistics:`);
        console.log(`   Total messages: ${consoleLogs.all.length}`);
        console.log(`   Shipping Cards logs: ${consoleLogs.shippingCards.length}`);
        console.log(`   Errors: ${consoleLogs.errors.length}`);
        console.log(`   Warnings: ${consoleLogs.warnings.length}`);
        console.log(`   AJAX logs: ${consoleLogs.ajax.length}`);
        
        if (consoleLogs.shippingCards.length > 0) {
            console.log(`\n📦 Shipping Cards Console Logs:\n`);
            consoleLogs.shippingCards.forEach(log => {
                console.log(`   ${log}`);
            });
        }
        
        if (consoleLogs.errors.length > 0) {
            console.log(`\n❌ JavaScript Errors:\n`);
            consoleLogs.errors.forEach(err => {
                console.log(`   ${err}`);
            });
        }
        
        if (apiCalls.length > 0) {
            console.log(`\n🌐 API Calls (${apiCalls.length} total):\n`);
            apiCalls.forEach((call, i) => {
                console.log(`   ${i + 1}. ${call.url.split('/').pop()}`);
                console.log(`      Status: ${call.status}`);
                console.log(`      Time: ${call.timestamp}`);
                if (call.body) {
                    const ratesCount = Array.isArray(call.body) ? call.body.length : 'N/A';
                    console.log(`      Rates: ${ratesCount}`);
                }
            });
        }
        
        // Save comprehensive report
        const fullReport = {
            timestamp: new Date().toISOString(),
            testName: 'Enhanced Full Order - Blida with Techno Pickup',
            success: orderSuccess,
            orderNumber: orderNumber,
            region: {
                name: 'Blida',
                magentoId: 867,
                wilayaId: 9,
                zone: 1
            },
            shippingState,
            apiCalls,
            consoleLogs: {
                shippingCards: consoleLogs.shippingCards,
                errors: consoleLogs.errors,
                warnings: consoleLogs.warnings.slice(0, 20),
                totalMessages: consoleLogs.all.length
            }
        };
        
        fs.writeFileSync('./blida-enhanced-test-report.json', JSON.stringify(fullReport, null, 2));
        console.log(`\n📄 Full report saved: blida-enhanced-test-report.json`);
        
        console.log('\n' + '='.repeat(80));
        console.log('TEST COMPLETE');
        console.log(`Screenshots: ${screenshotDir}/`);
        if (orderSuccess) {
            console.log(`✅ SUCCESS: Order #${orderNumber} placed!`);
        } else {
            console.log(`⚠️  Test completed but order may not have been placed`);
        }
        console.log('='.repeat(80) + '\n');
        
    } catch (error) {
        console.error(`\n❌ Test failed: ${error.message}`);
        console.error(error.stack);
        
        try {
            await page.screenshot({ 
                path: `${screenshotDir}/error-final.png`, 
                fullPage: true,
                timeout: 10000
            });
            console.log(`📸 Error screenshot: ${screenshotDir}/error-final.png`);
        } catch (e) {
            console.log('Could not capture error screenshot');
        }
        
    } finally {
        await page.waitForTimeout(2000);
        await browser.close();
    }
}

enhancedFullOrderTest().catch(console.error);

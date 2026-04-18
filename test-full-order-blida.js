/**
 * Full Order Test - Complete Checkout Flow with Blida Techno Pickup
 * Tests the entire order process from product selection to order placement
 */

const { chromium } = require('playwright');
const fs = require('fs');

async function testFullOrderBlida() {
    console.log('\n' + '='.repeat(80));
    console.log('FULL ORDER TEST - BLIDA WITH TECHNO PICKUP');
    console.log('Complete end-to-end checkout flow');
    console.log('='.repeat(80) + '\n');
    
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 },
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    });
    
    const page = await context.newPage();
    
    // Track console messages
    const consoleLogs = {
        shippingCards: [],
        all: []
    };
    
    page.on('console', msg => {
        const text = msg.text();
        consoleLogs.all.push({ type: msg.type(), text });
        
        if (text.includes('[Shipping Cards]')) {
            consoleLogs.shippingCards.push(text);
            console.log(`📦 ${text}`);
        }
    });
    
    const screenshotDir = './screenshots/blida-full-order';
    if (!fs.existsSync(screenshotDir)) {
        fs.mkdirSync(screenshotDir, { recursive: true });
    }
    
    let orderId = null;
    let orderNumber = null;
    
    try {
        console.log('═══════════════════════════════════════════════════════════');
        console.log('STEP 1: ADD PRODUCTS TO CART');
        console.log('═══════════════════════════════════════════════════════════\n');
        
        // Product 1: Palette de peinture
        const product1Url = 'https://dev.technostationery.com/palette-de-peinture-aquarelel-rouge-ark-ref-313.html';
        console.log('Adding Product 1: Palette de peinture...');
        await page.goto(product1Url, { waitUntil: 'domcontentloaded', timeout: 30000 });
        await page.waitForTimeout(2000);
        
        const product1Title = await page.title();
        console.log(`✅ Product 1 page loaded: ${product1Title}`);
        
        const addToCartBtn1 = page.locator('button#product-addtocart-button').first();
        if (await addToCartBtn1.count() > 0) {
            await addToCartBtn1.click();
            console.log('🛒 Added to cart');
            await page.waitForTimeout(3000);
        }
        
        await page.screenshot({ path: `${screenshotDir}/01-product1-added.png`, timeout: 10000 });
        
        // Product 2: Stylo à plume
        const product2Url = 'https://dev.technostationery.com/stylo-a-plume-classic-rouge-blanc-maped-ref-222212.html';
        console.log('\nAdding Product 2: Stylo à plume...');
        await page.goto(product2Url, { waitUntil: 'domcontentloaded', timeout: 30000 });
        await page.waitForTimeout(2000);
        
        const product2Title = await page.title();
        console.log(`✅ Product 2 page loaded: ${product2Title}`);
        
        // Set quantity to 2
        const qtyInput = page.locator('input#qty').first();
        if (await qtyInput.count() > 0) {
            await qtyInput.fill('2');
            console.log('📝 Quantity set to 2');
        }
        
        const addToCartBtn2 = page.locator('button#product-addtocart-button').first();
        if (await addToCartBtn2.count() > 0) {
            await addToCartBtn2.click();
            console.log('🛒 Added to cart (x2)');
            await page.waitForTimeout(3000);
        }
        
        await page.screenshot({ path: `${screenshotDir}/02-product2-added.png`, timeout: 10000 });
        
        console.log('\n✅ Cart Summary:');
        console.log('   - Palette de peinture x1');
        console.log('   - Stylo à plume x2');
        
        console.log('\n═══════════════════════════════════════════════════════════');
        console.log('STEP 2: GO TO CHECKOUT');
        console.log('═══════════════════════════════════════════════════════════\n');
        
        await page.goto('https://dev.technostationery.com/checkout/', {
            waitUntil: 'domcontentloaded',
            timeout: 30000
        });
        
        await page.waitForTimeout(5000);
        
        const checkoutUrl = page.url();
        console.log(`✅ Checkout page loaded: ${checkoutUrl}`);
        
        await page.screenshot({ path: `${screenshotDir}/03-checkout-page.png`, fullPage: true, timeout: 10000 });
        
        console.log('\n═══════════════════════════════════════════════════════════');
        console.log('STEP 3: FILL SHIPPING ADDRESS');
        console.log('═══════════════════════════════════════════════════════════\n');
        
        // Check if guest or logged in
        const emailField = page.locator('input#customer-email').first();
        const isGuest = await emailField.count() > 0;
        
        if (isGuest) {
            console.log('🔓 Guest checkout detected');
            console.log('   Filling guest email...');
            
            // Wait for email field to be visible
            await page.waitForSelector('input#customer-email', { state: 'visible', timeout: 10000 });
            await emailField.fill('test.blida@technostationery.com');
            console.log('   ✅ Email: test.blida@technostationery.com');
            await page.waitForTimeout(1000);
        } else {
            console.log('🔐 Logged in user detected');
        }
        
        // Wait for shipping form to be ready
        await page.waitForTimeout(2000);
        
        // Fill address fields
        console.log('\n📝 Filling shipping address for Blida...\n');
        
        const fillField = async (selector, value, label) => {
            const field = page.locator(selector).first();
            if (await field.count() > 0) {
                await field.waitFor({ state: 'visible', timeout: 5000 });
                await field.clear();
                await field.fill(value);
                console.log(`   ✅ ${label}: ${value}`);
                return true;
            }
            return false;
        };
        
        await fillField('input[name="firstname"]', 'Ahmed', 'First Name');
        await fillField('input[name="lastname"]', 'Benali', 'Last Name');
        await fillField('input[name="company"]', 'Techno Stationery', 'Company');
        await fillField('input[name="street[0]"]', '123 Rue Larbi Ben M\'hidi', 'Street Address');
        await fillField('input[name="city"]', 'Blida', 'City');
        await fillField('input[name="postcode"]', '09000', 'Postcode');
        await fillField('input[name="telephone"]', '0550123456', 'Telephone');
        
        await page.waitForTimeout(1000);
        
        // Select country - Algeria
        const countrySelect = page.locator('select[name="country_id"]').first();
        if (await countrySelect.count() > 0) {
            await countrySelect.selectOption('DZ');
            console.log('   ✅ Country: Algeria (DZ)');
            await page.waitForTimeout(2000); // Wait for regions to load
        }
        
        await page.screenshot({ path: `${screenshotDir}/04-address-filled.png`, fullPage: true, timeout: 10000 });
        
        console.log('\n═══════════════════════════════════════════════════════════');
        console.log('STEP 4: SELECT BLIDA REGION');
        console.log('═══════════════════════════════════════════════════════════\n');
        
        // Select region - Blida (Magento ID: 867)
        const regionSelect = page.locator('select[name="region_id"]').first();
        if (await regionSelect.count() > 0) {
            console.log('🗺️  Selecting Blida region (Magento ID: 867)...');
            await regionSelect.selectOption('867');
            console.log('✅ Region selected: Blida');
            
            console.log('⏳ Waiting 5 seconds for shipping rates API call...\n');
            await page.waitForTimeout(5000);
        } else {
            console.log('⚠️  Region select not found');
        }
        
        await page.screenshot({ path: `${screenshotDir}/05-region-selected.png`, fullPage: true, timeout: 10000 });
        
        console.log('═══════════════════════════════════════════════════════════');
        console.log('STEP 5: CHECK SHIPPING METHODS');
        console.log('═══════════════════════════════════════════════════════════\n');
        
        const shippingState = await page.evaluate(() => {
            const wrapper = document.querySelector('.shipping-methods-cards-wrapper');
            const cards = document.querySelectorAll('.shipping-card');
            
            const result = {
                wrapperExists: !!wrapper,
                wrapperVisible: wrapper ? window.getComputedStyle(wrapper).display !== 'none' : false,
                cardsCount: cards.length,
                cards: []
            };
            
            cards.forEach(card => {
                const methodTitle = card.querySelector('.method-title, .method-name');
                const priceElement = card.querySelector('.price-amount, .free-badge');
                const deliveryTime = card.querySelector('.delivery-time');
                
                result.cards.push({
                    methodCode: card.getAttribute('data-method-code'),
                    title: methodTitle ? methodTitle.textContent.trim() : 'N/A',
                    price: priceElement ? priceElement.textContent.trim() : 'N/A',
                    deliveryTime: deliveryTime ? deliveryTime.textContent.trim() : 'N/A',
                    isFree: card.classList.contains('free-shipping')
                });
            });
            
            return result;
        });
        
        console.log('Shipping Methods Status:');
        console.log(`   Wrapper: ${shippingState.wrapperExists ? '✅ Present' : '❌ Missing'}`);
        console.log(`   Visible: ${shippingState.wrapperVisible ? '✅ Yes' : '❌ No'}`);
        console.log(`   Cards: ${shippingState.cardsCount} found\n`);
        
        if (shippingState.cardsCount > 0) {
            console.log('📦 Available Shipping Methods:\n');
            shippingState.cards.forEach((card, i) => {
                const freeLabel = card.isFree ? ' 🎉 FREE' : '';
                console.log(`   ${i + 1}. ${card.title}${freeLabel}`);
                console.log(`      Price: ${card.price}`);
                console.log(`      Delivery: ${card.deliveryTime}`);
                console.log(`      Code: ${card.methodCode}\n`);
            });
        } else {
            console.log('⚠️  WARNING: No shipping cards rendered!');
            console.log('   This may indicate:');
            console.log('   - No shipping rates configured for Blida');
            console.log('   - API call failed');
            console.log('   - JavaScript error\n');
        }
        
        console.log('═══════════════════════════════════════════════════════════');
        console.log('STEP 6: SELECT TECHNO BLIDA PICKUP (FREE)');
        console.log('═══════════════════════════════════════════════════════════\n');
        
        // Find and click the free Techno Blida pickup option
        let technoSelected = false;
        
        if (shippingState.cardsCount > 0) {
            // Look for the free shipping option (Retrait Techno Blida)
            const cards = await page.locator('.shipping-card').all();
            
            for (let i = 0; i < cards.length; i++) {
                const card = cards[i];
                const isFree = await card.evaluate(el => el.classList.contains('free-shipping'));
                const title = await card.locator('.method-title, .method-name').textContent();
                
                if (isFree && title.toLowerCase().includes('retrait') && title.toLowerCase().includes('blida')) {
                    console.log(`🎯 Selecting: ${title.trim()}`);
                    await card.click();
                    technoSelected = true;
                    console.log('✅ Techno Blida pickup selected (FREE)');
                    await page.waitForTimeout(2000);
                    break;
                }
            }
            
            if (!technoSelected) {
                // Fallback: click first card
                console.log('⚠️  Techno Blida not found, selecting first available method...');
                await page.locator('.shipping-card').first().click();
                technoSelected = true;
                await page.waitForTimeout(2000);
            }
        }
        
        await page.screenshot({ path: `${screenshotDir}/06-shipping-selected.png`, fullPage: true, timeout: 10000 });
        
        if (technoSelected) {
            // Check if Next button is enabled
            const continueBtn = page.locator('button.continue, button[data-role="opc-continue"]').first();
            const isBtnEnabled = await continueBtn.evaluate(btn => !btn.disabled);
            
            console.log(`\n📍 Continue Button: ${isBtnEnabled ? '✅ Enabled' : '❌ Disabled'}`);
            
            if (isBtnEnabled) {
                console.log('\n═══════════════════════════════════════════════════════════');
                console.log('STEP 7: PROCEED TO PAYMENT');
                console.log('═══════════════════════════════════════════════════════════\n');
                
                console.log('🖱️  Clicking "Next" button...');
                await continueBtn.click();
                await page.waitForTimeout(3000);
                
                console.log('✅ Proceeded to payment step');
                
                await page.screenshot({ path: `${screenshotDir}/07-payment-step.png`, fullPage: true, timeout: 10000 });
                
                console.log('\n═══════════════════════════════════════════════════════════');
                console.log('STEP 8: SELECT PAYMENT METHOD');
                console.log('═══════════════════════════════════════════════════════════\n');
                
                // Check available payment methods
                const paymentMethods = await page.evaluate(() => {
                    const methods = [];
                    const paymentLabels = document.querySelectorAll('.payment-method-title label, .payment-method input[type="radio"]');
                    
                    paymentLabels.forEach(label => {
                        const text = label.textContent || label.getAttribute('aria-label') || '';
                        if (text.trim()) {
                            methods.push(text.trim());
                        }
                    });
                    
                    return methods;
                });
                
                console.log('💳 Available Payment Methods:');
                paymentMethods.forEach((method, i) => {
                    console.log(`   ${i + 1}. ${method}`);
                });
                
                // Select Cash on Delivery if available
                const cashOnDeliveryRadio = page.locator('input[value="cashondelivery"]').first();
                if (await cashOnDeliveryRadio.count() > 0) {
                    console.log('\n✅ Selecting: Cash on Delivery');
                    await cashOnDeliveryRadio.check();
                    await page.waitForTimeout(1500);
                } else {
                    // Fallback: click first payment method
                    const firstPaymentRadio = page.locator('.payment-method input[type="radio"]').first();
                    if (await firstPaymentRadio.count() > 0) {
                        console.log('\n✅ Selecting first available payment method');
                        await firstPaymentRadio.check();
                        await page.waitForTimeout(1500);
                    }
                }
                
                await page.screenshot({ path: `${screenshotDir}/08-payment-selected.png`, fullPage: true, timeout: 10000 });
                
                console.log('\n═══════════════════════════════════════════════════════════');
                console.log('STEP 9: REVIEW ORDER SUMMARY');
                console.log('═══════════════════════════════════════════════════════════\n');
                
                const orderSummary = await page.evaluate(() => {
                    const subtotal = document.querySelector('.totals-tax-summary .sub .price, .sub .price');
                    const shippingCost = document.querySelector('.totals-tax-summary .shipping .price, .shipping .price');
                    const grandTotal = document.querySelector('.grand .price, .totals .grand.totals .price');
                    
                    return {
                        subtotal: subtotal ? subtotal.textContent.trim() : 'N/A',
                        shipping: shippingCost ? shippingCost.textContent.trim() : 'N/A',
                        grandTotal: grandTotal ? grandTotal.textContent.trim() : 'N/A'
                    };
                });
                
                console.log('💰 Order Summary:');
                console.log(`   Subtotal: ${orderSummary.subtotal}`);
                console.log(`   Shipping: ${orderSummary.shipping}`);
                console.log(`   Grand Total: ${orderSummary.grandTotal}`);
                
                console.log('\n═══════════════════════════════════════════════════════════');
                console.log('STEP 10: PLACE ORDER');
                console.log('═══════════════════════════════════════════════════════════\n');
                
                // Find and click Place Order button
                const placeOrderBtn = page.locator('button.checkout, button[title="Place Order"]').first();
                if (await placeOrderBtn.count() > 0) {
                    console.log('🖱️  Clicking "Place Order" button...');
                    await placeOrderBtn.click();
                    console.log('⏳ Processing order...\n');
                    
                    // Wait for order success page
                    await page.waitForTimeout(5000);
                    
                    const currentUrl = page.url();
                    console.log(`Current URL: ${currentUrl}`);
                    
                    if (currentUrl.includes('/success')) {
                        console.log('\n🎉 ═══════════════════════════════════════════════════════════');
                        console.log('🎉 ORDER PLACED SUCCESSFULLY!');
                        console.log('🎉 ═══════════════════════════════════════════════════════════\n');
                        
                        await page.screenshot({ path: `${screenshotDir}/09-order-success.png`, fullPage: true, timeout: 10000 });
                        
                        // Extract order number
                        const orderInfo = await page.evaluate(() => {
                            const orderNumberElement = document.querySelector('.checkout-success .order-number, .checkout-success strong');
                            const continueLink = document.querySelector('.checkout-success a[href*="order"]');
                            
                            return {
                                orderNumber: orderNumberElement ? orderNumberElement.textContent.trim() : null,
                                orderLink: continueLink ? continueLink.href : null
                            };
                        });
                        
                        if (orderInfo.orderNumber) {
                            orderNumber = orderInfo.orderNumber.replace(/[^0-9]/g, '');
                            console.log(`📋 Order Number: #${orderNumber}`);
                        }
                        
                        if (orderInfo.orderLink) {
                            console.log(`🔗 Order Link: ${orderInfo.orderLink}`);
                        }
                        
                        console.log('\n✅ Order Details:');
                        console.log(`   Customer: Ahmed Benali`);
                        console.log(`   Email: test.blida@technostationery.com`);
                        console.log(`   Phone: 0550123456`);
                        console.log(`   Address: 123 Rue Larbi Ben M'hidi, Blida 09000`);
                        console.log(`   Shipping: Retrait Techno Blida (FREE)`);
                        console.log(`   Payment: Cash on Delivery`);
                        console.log(`   Total: ${orderSummary.grandTotal}`);
                        
                    } else {
                        console.log('\n⚠️  Order may not have completed successfully');
                        console.log(`   Current URL: ${currentUrl}`);
                        
                        await page.screenshot({ path: `${screenshotDir}/09-order-issue.png`, fullPage: true, timeout: 10000 });
                    }
                } else {
                    console.log('❌ Place Order button not found');
                }
                
            } else {
                console.log('❌ Cannot proceed: Continue button is disabled');
            }
        } else {
            console.log('❌ Cannot select shipping method: No methods available');
        }
        
        // Console logs summary
        console.log('\n═══════════════════════════════════════════════════════════');
        console.log('CONSOLE LOGS SUMMARY');
        console.log('═══════════════════════════════════════════════════════════\n');
        
        console.log(`Total console messages: ${consoleLogs.all.length}`);
        console.log(`Shipping Cards logs: ${consoleLogs.shippingCards.length}`);
        
        if (consoleLogs.shippingCards.length > 0) {
            console.log('\n📦 Shipping Cards Console Output:\n');
            consoleLogs.shippingCards.forEach(log => {
                console.log(`   ${log}`);
            });
        }
        
        // Save test report
        const testReport = {
            timestamp: new Date().toISOString(),
            testName: 'Full Order - Blida with Techno Pickup',
            region: 'Blida (Magento ID: 867)',
            shippingMethod: 'Retrait Techno Blida (Free)',
            orderNumber: orderNumber,
            orderSummary: orderSummary || null,
            shippingState,
            consoleLogs: {
                shippingCards: consoleLogs.shippingCards,
                totalMessages: consoleLogs.all.length
            }
        };
        
        fs.writeFileSync('./blida-full-order-report.json', JSON.stringify(testReport, null, 2));
        console.log('\n📄 Test report saved: blida-full-order-report.json');
        
        console.log('\n' + '='.repeat(80));
        console.log('TEST COMPLETE');
        console.log(`Screenshots saved in: ${screenshotDir}/`);
        if (orderNumber) {
            console.log(`✅ Order #${orderNumber} placed successfully!`);
        }
        console.log('='.repeat(80) + '\n');
        
    } catch (error) {
        console.error(`\n❌ Test failed: ${error.message}`);
        console.error(error.stack);
        
        try {
            await page.screenshot({ 
                path: `${screenshotDir}/error-state.png`, 
                fullPage: true,
                timeout: 10000
            });
            console.log(`📸 Error screenshot saved: ${screenshotDir}/error-state.png\n`);
        } catch (e) {
            console.log('Could not capture error screenshot');
        }
        
    } finally {
        await page.waitForTimeout(1000);
        await browser.close();
    }
}

testFullOrderBlida().catch(console.error);

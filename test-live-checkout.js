/**
 * Live Checkout Test - Tests actual checkout with products in cart
 * Tests a region that has valid shipping rates configured
 */

const { chromium } = require('playwright');
const fs = require('fs');

async function testLiveCheckout() {
    console.log('\n' + '='.repeat(80));
    console.log('LIVE CHECKOUT SHIPPING CARDS TEST');
    console.log('Testing with Boumerdès region (has 3 valid rates configured)');
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
        errors: [],
        all: []
    };
    
    page.on('console', msg => {
        const text = msg.text();
        const type = msg.type();
        
        consoleLogs.all.push({ type, text });
        
        if (text.includes('[Shipping Cards]')) {
            consoleLogs.shippingCards.push(text);
            console.log(`📦 ${text}`);
        }
        
        if (type === 'error' && !text.includes('CORS') && !text.includes('webpushr')) {
            consoleLogs.errors.push(text);
            console.log(`❌ ${text}`);
        }
    });
    
    page.on('pageerror', error => {
        if (!error.message.includes('webpushr')) {
            console.log(`🔴 Page Error: ${error.message}`);
        }
    });
    
    const screenshotDir = './screenshots';
    if (!fs.existsSync(screenshotDir)) {
        fs.mkdirSync(screenshotDir, { recursive: true });
    }
    
    try {
        console.log('Step 1: Adding products to cart...\n');
        
        // Add a product to cart
        const productUrl = 'https://dev.technostationery.com/palette-de-peinture-aquarelel-rouge-ark-ref-313.html';
        await page.goto(productUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });
        await page.waitForTimeout(2000);
        
        console.log(`✅ Product page loaded: ${await page.title()}\n`);
        
        // Click add to cart
        const addToCartBtn = page.locator('button#product-addtocart-button, button.tocart');
        if (await addToCartBtn.count() > 0) {
            await addToCartBtn.first().click();
            console.log('🛒 Clicked "Add to Cart" button');
            await page.waitForTimeout(3000);
        }
        
        console.log('\nStep 2: Going to checkout...\n');
        
        // Go to checkout
        await page.goto('https://dev.technostationery.com/checkout/', {
            waitUntil: 'domcontentloaded',
            timeout: 30000
        });
        
        await page.waitForTimeout(5000);
        
        console.log(`✅ Checkout page loaded: ${page.url()}\n`);
        
        await page.screenshot({ 
            path: './screenshots/01-checkout-loaded.png', 
            fullPage: true,
            timeout: 10000
        });
        console.log('📸 Screenshot: 01-checkout-loaded.png\n');
        
        // Check initial state
        console.log('=== Step 3: Initial Checkout State ===\n');
        
        const initialState = await page.evaluate(() => {
            return {
                url: window.location.href,
                hasShippingStep: !!document.querySelector('#shipping'),
                hasEmailField: !!document.querySelector('input[name="username"], input#customer-email'),
                hasAddressForm: !!document.querySelector('form[id*="shipping"], .shipping-address-items'),
                hasWrapper: !!document.querySelector('.shipping-methods-cards-wrapper'),
                wrapperVisible: (() => {
                    const wrapper = document.querySelector('.shipping-methods-cards-wrapper');
                    return wrapper ? window.getComputedStyle(wrapper).display !== 'none' : false;
                })(),
                cardsCount: document.querySelectorAll('.shipping-card').length,
                hasRegionSelect: !!document.querySelector('select[name="region_id"]')
            };
        });
        
        console.log('Checkout State:');
        console.log('  URL:', initialState.url);
        console.log('  Shipping Step:', initialState.hasShippingStep ? '✅ Present' : '❌ Missing');
        console.log('  Email Field:', initialState.hasEmailField ? '✅ Present' : '❌ Missing');
        console.log('  Address Form:', initialState.hasAddressForm ? '✅ Present' : '❌ Missing');
        console.log('  Shipping Cards Wrapper:', initialState.hasWrapper ? '✅ Present' : '❌ Missing');
        console.log('  Wrapper Visible:', initialState.wrapperVisible ? '✅ Yes' : '❌ No');
        console.log('  Region Select:', initialState.hasRegionSelect ? '✅ Present' : '❌ Missing');
        console.log('  Cards Rendered:', initialState.cardsCount);
        
        // If guest checkout, fill email
        if (initialState.hasEmailField) {
            console.log('\n=== Step 4: Filling Guest Email ===\n');
            
            const emailInput = page.locator('input[name="username"], input#customer-email').first();
            await emailInput.fill('test@example.com');
            console.log('✅ Email filled: test@example.com');
            await page.waitForTimeout(1000);
        }
        
        // Fill shipping address
        console.log('\n=== Step 5: Filling Shipping Address ===\n');
        
        // Check if we have country select
        const countrySelect = page.locator('select[name="country_id"]').first();
        if (await countrySelect.count() > 0) {
            await countrySelect.selectOption('DZ'); // Algeria
            console.log('✅ Country: Algeria (DZ)');
            await page.waitForTimeout(2000);
        }
        
        // Fill first name
        const firstNameInput = page.locator('input[name="firstname"]').first();
        if (await firstNameInput.count() > 0) {
            await firstNameInput.fill('Test');
            console.log('✅ First Name: Test');
        }
        
        // Fill last name
        const lastNameInput = page.locator('input[name="lastname"]').first();
        if (await lastNameInput.count() > 0) {
            await lastNameInput.fill('User');
            console.log('✅ Last Name: User');
        }
        
        // Fill street
        const streetInput = page.locator('input[name="street[0]"]').first();
        if (await streetInput.count() > 0) {
            await streetInput.fill('123 Test Street');
            console.log('✅ Street: 123 Test Street');
        }
        
        // Fill city
        const cityInput = page.locator('input[name="city"]').first();
        if (await cityInput.count() > 0) {
            await cityInput.fill('Boumerdès');
            console.log('✅ City: Boumerdès');
        }
        
        // Fill postcode
        const postcodeInput = page.locator('input[name="postcode"]').first();
        if (await postcodeInput.count() > 0) {
            await postcodeInput.fill('35000');
            console.log('✅ Postcode: 35000');
        }
        
        // Fill telephone
        const phoneInput = page.locator('input[name="telephone"]').first();
        if (await phoneInput.count() > 0) {
            await phoneInput.fill('0550123456');
            console.log('✅ Phone: 0550123456');
        }
        
        console.log('\n=== Step 6: Selecting Region (Boumerdès - ID 893) ===\n');
        
        // Select region
        const regionSelect = page.locator('select[name="region_id"]').first();
        if (await regionSelect.count() > 0) {
            await regionSelect.selectOption('893'); // Boumerdès
            console.log('✅ Region selected: Boumerdès (ID: 893)');
            console.log('⏳ Waiting 5 seconds for shipping rates API call...\n');
            await page.waitForTimeout(5000);
            
            await page.screenshot({ 
                path: './screenshots/02-after-region-select.png', 
                fullPage: true,
                timeout: 10000
            });
            console.log('📸 Screenshot: 02-after-region-select.png\n');
        } else {
            console.log('⚠️  Region select not found - may already be filled');
        }
        
        // Check state after region selection
        console.log('=== Step 7: Checking Shipping Cards After Region Selection ===\n');
        
        const afterSelection = await page.evaluate(() => {
            const wrapper = document.querySelector('.shipping-methods-cards-wrapper');
            const cards = document.querySelectorAll('.shipping-card');
            const errorMsg = document.querySelector('.shipping-error-message, .shipping-methods-error');
            
            const result = {
                wrapperExists: !!wrapper,
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
                    width: styles.width
                };
            }
            
            cards.forEach(card => {
                const methodTitle = card.querySelector('.method-title, .method-name');
                const priceElement = card.querySelector('.price-amount, .free-badge');
                const deliveryTime = card.querySelector('.delivery-time');
                const cardStyles = window.getComputedStyle(card);
                
                result.cards.push({
                    methodCode: card.getAttribute('data-method-code'),
                    title: methodTitle ? methodTitle.textContent.trim() : 'N/A',
                    price: priceElement ? priceElement.textContent.trim() : 'N/A',
                    deliveryTime: deliveryTime ? deliveryTime.textContent.trim() : 'N/A',
                    classes: card.className,
                    visible: cardStyles.display !== 'none' && cardStyles.visibility !== 'hidden',
                    display: cardStyles.display,
                    position: cardStyles.position
                });
            });
            
            return result;
        });
        
        console.log('Shipping Cards Status:');
        console.log('  Wrapper Exists:', afterSelection.wrapperExists ? '✅' : '❌');
        
        if (afterSelection.wrapperExists && afterSelection.wrapperStyles) {
            console.log('  Wrapper Styles:');
            console.log('    Display:', afterSelection.wrapperStyles.display);
            console.log('    Visibility:', afterSelection.wrapperStyles.visibility);
            console.log('    Opacity:', afterSelection.wrapperStyles.opacity);
            console.log('    Height:', afterSelection.wrapperStyles.height);
            console.log('    Width:', afterSelection.wrapperStyles.width);
        }
        
        console.log('\n  Cards Rendered:', afterSelection.cardsCount);
        
        if (afterSelection.errorMessage) {
            console.log('  ⚠️  Error Message:', afterSelection.errorMessage);
        }
        
        if (afterSelection.cardsCount > 0) {
            console.log('\n  📋 Shipping Method Cards:\n');
            
            afterSelection.cards.forEach((card, i) => {
                console.log(`    Card ${i + 1}:`);
                console.log(`      Method Code: ${card.methodCode}`);
                console.log(`      Title: ${card.title}`);
                console.log(`      Price: ${card.price}`);
                console.log(`      Delivery Time: ${card.deliveryTime}`);
                console.log(`      Visible: ${card.visible ? '✅ Yes' : '❌ No'}`);
                console.log(`      Display: ${card.display}`);
                console.log(`      Classes: ${card.classes}\n`);
            });
            
            // Test clicking first card
            console.log('=== Step 8: Testing Card Selection ===\n');
            
            console.log('🖱️  Clicking first shipping card...');
            await page.locator('.shipping-card').first().click();
            await page.waitForTimeout(2000);
            
            await page.screenshot({ 
                path: './screenshots/03-after-card-click.png', 
                fullPage: true,
                timeout: 10000
            });
            console.log('📸 Screenshot: 03-after-card-click.png\n');
            
            const afterClick = await page.evaluate(() => {
                const selectedCard = document.querySelector('.shipping-card.selected');
                const continueBtn = document.querySelector('button.continue, button[data-role="opc-continue"]');
                
                return {
                    hasSelectedCard: !!selectedCard,
                    selectedMethodCode: selectedCard ? selectedCard.getAttribute('data-method-code') : null,
                    selectedTitle: selectedCard ? selectedCard.querySelector('.method-title, .method-name')?.textContent.trim() : null,
                    continueBtnExists: !!continueBtn,
                    continueBtnDisabled: continueBtn ? continueBtn.disabled : null,
                    continueBtnText: continueBtn ? continueBtn.textContent.trim() : null
                };
            });
            
            console.log('After Clicking Card:');
            console.log('  Selected Card:', afterClick.hasSelectedCard ? '✅ Yes' : '❌ No');
            if (afterClick.hasSelectedCard) {
                console.log('  Selected Method:', afterClick.selectedMethodCode);
                console.log('  Selected Title:', afterClick.selectedTitle);
            }
            console.log('  Continue Button:', afterClick.continueBtnExists ? '✅ Found' : '❌ Not found');
            console.log('  Button Disabled:', afterClick.continueBtnDisabled ? '❌ Yes (blocked)' : '✅ No (enabled)');
            console.log('  Button Text:', afterClick.continueBtnText);
            
            // Final verdict
            if (afterSelection.cardsCount > 0 && afterClick.hasSelectedCard && !afterClick.continueBtnDisabled) {
                console.log('\n' + '='.repeat(80));
                console.log('✅ SUCCESS: Shipping method cards are working correctly!');
                console.log('   - Cards rendered: ' + afterSelection.cardsCount);
                console.log('   - Card selection: Working');
                console.log('   - Continue button: Enabled');
                console.log('='.repeat(80) + '\n');
            } else {
                console.log('\n' + '='.repeat(80));
                console.log('⚠️  PARTIAL SUCCESS or ISSUE:');
                if (afterSelection.cardsCount === 0) {
                    console.log('   ❌ No cards rendered');
                }
                if (!afterClick.hasSelectedCard) {
                    console.log('   ❌ Card selection not working');
                }
                if (afterClick.continueBtnDisabled) {
                    console.log('   ❌ Continue button still disabled');
                }
                console.log('='.repeat(80) + '\n');
            }
            
        } else {
            console.log('\n❌ CRITICAL: No shipping cards rendered!\n');
            if (afterSelection.errorMessage) {
                console.log('   Error Message:', afterSelection.errorMessage);
            }
            console.log('   This may indicate:');
            console.log('   - No shipping rates configured for Boumerdès');
            console.log('   - API call failed');
            console.log('   - JavaScript error preventing rendering\n');
        }
        
        // Console logs summary
        console.log('=== Step 9: Console Logs Summary ===\n');
        
        console.log(`Total console messages: ${consoleLogs.all.length}`);
        console.log(`Shipping Cards logs: ${consoleLogs.shippingCards.length}`);
        console.log(`Errors: ${consoleLogs.errors.length}`);
        
        if (consoleLogs.shippingCards.length > 0) {
            console.log('\n📦 Shipping Cards Console Logs:\n');
            consoleLogs.shippingCards.forEach(log => {
                console.log(`   ${log}`);
            });
        }
        
        if (consoleLogs.errors.length > 0) {
            console.log('\n❌ JavaScript Errors:\n');
            consoleLogs.errors.slice(0, 10).forEach(err => {
                console.log(`   ${err.substring(0, 200)}`);
            });
        }
        
        // Save detailed report
        const report = {
            timestamp: new Date().toISOString(),
            testUrl: page.url(),
            initialState,
            afterSelection,
            consoleLogs: {
                shippingCards: consoleLogs.shippingCards,
                errors: consoleLogs.errors,
                totalMessages: consoleLogs.all.length
            }
        };
        
        fs.writeFileSync('./live-checkout-report.json', JSON.stringify(report, null, 2));
        console.log('\n📄 Detailed report saved: live-checkout-report.json');
        
        console.log('\n' + '='.repeat(80));
        console.log('TEST COMPLETE');
        console.log('Screenshots saved in ./screenshots/');
        console.log('='.repeat(80) + '\n');
        
    } catch (error) {
        console.error(`\n❌ Test failed: ${error.message}`);
        console.error(error.stack);
        
        try {
            await page.screenshot({ 
                path: './screenshots/error-state.png', 
                fullPage: true,
                timeout: 10000
            });
            console.log('📸 Error screenshot saved: error-state.png\n');
        } catch (e) {
            console.log('Could not capture error screenshot');
        }
        
    } finally {
        await page.waitForTimeout(1000);
        await browser.close();
    }
}

testLiveCheckout().catch(console.error);

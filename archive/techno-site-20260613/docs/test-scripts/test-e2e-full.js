/**
 * Full E2E Test - Add products via frontend and test shipping cards
 */
const { chromium } = require('playwright');
const fs = require('fs');

async function fullE2ETest() {
    console.log('🔍 FULL E2E SHIPPING CARDS TEST\n');
    console.log('═'.repeat(80));
    
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
    });
    const page = await context.newPage();
    
    const results = [];
    
    // Capture console
    page.on('console', msg => {
        const text = msg.text();
        if (text.includes('[Shipping Cards]') || text.includes('Shipping') || text.includes('method')) {
            console.log('📝', text);
        }
    });
    
    try {
        console.log('\n📍 Step 1: Loading homepage...');
        await page.goto('https://dev.technostationery.com/', { 
            waitUntil: 'networkidle',
            timeout: 60000 
        });
        
        await page.screenshot({ 
            path: './screenshots/e2e-01-homepage.png',
            fullPage: false
        });
        console.log('   ✅ Homepage loaded');
        
        // Step 2: Search for a product
        console.log('\n📍 Step 2: Searching for products...');
        await page.fill('input[name="q"]', 'stylo');
        await page.click('button[title="RECHERCHER"]');
        await page.waitForTimeout(3000);
        
        await page.screenshot({ 
            path: './screenshots/e2e-02-search-results.png',
            fullPage: false
        });
        console.log('   ✅ Search completed');
        
        // Step 3: Add first product
        console.log('\n📍 Step 3: Adding first product to cart...');
        const firstAddButton = await page.locator('.action.tocart.primary').first();
        if (await firstAddButton.isVisible()) {
            await firstAddButton.click();
            await page.waitForTimeout(3000);
            console.log('   ✅ First product added');
        }
        
        // Step 4: Add second product
        console.log('\n📍 Step 4: Adding second product to cart...');
        const secondAddButton = await page.locator('.action.tocart.primary').nth(1);
        if (await secondAddButton.isVisible()) {
            await secondAddButton.click();
            await page.waitForTimeout(3000);
            console.log('   ✅ Second product added');
        }
        
        await page.screenshot({ 
            path: './screenshots/e2e-03-products-added.png',
            fullPage: false
        });
        
        // Step 5: Go to checkout
        console.log('\n📍 Step 5: Going to checkout...');
        await page.goto('https://dev.technostationery.com/checkout/', {
            waitUntil: 'networkidle',
            timeout: 60000
        });
        await page.waitForTimeout(5000);
        
        const checkoutUrl = page.url();
        console.log('   🔗 Checkout URL:', checkoutUrl);
        
        await page.screenshot({ 
            path: './screenshots/e2e-04-checkout-initial.png',
            fullPage: true
        });
        
        // Check if we're on checkout or redirected to cart
        if (checkoutUrl.includes('/checkout/cart/')) {
            console.log('   ⚠️  Redirected to cart - cart might be empty');
            await page.screenshot({ 
                path: './screenshots/e2e-05-cart-redirect.png',
                fullPage: true
            });
        } else {
            console.log('   ✅ On checkout page');
            
            // Step 6: Check for shipping cards
            console.log('\n📍 Step 6: Checking for shipping elements...');
            
            const shippingCheck = await page.evaluate(() => {
                return {
                    shippingStep: !!document.querySelector('#opc-shipping_method'),
                    cardsWrapper: !!document.querySelector('.shipping-methods-cards-wrapper'),
                    cardCount: document.querySelectorAll('.shipping-card').length,
                    shippingTable: !!document.querySelector('.table-checkout-shipping-method'),
                    shippingForm: !!document.querySelector('#co-shipping-method-form')
                };
            });
            
            console.log('\n   📊 Shipping Elements Found:');
            console.log('      Shipping Step:', shippingCheck.shippingStep ? '✅' : '❌');
            console.log('      Cards Wrapper:', shippingCheck.cardsWrapper ? '✅' : '❌');
            console.log('      Card Count:', shippingCheck.cardCount);
            console.log('      Shipping Table:', shippingCheck.shippingTable ? '✅' : '❌');
            console.log('      Shipping Form:', shippingCheck.shippingForm ? '✅' : '❌');
            
            await page.screenshot({ 
                path: './screenshots/e2e-06-shipping-check.png',
                fullPage: true
            });
            
            // Try to fill address if form is visible
            console.log('\n📍 Step 7: Attempting to fill address...');
            try {
                // Check if email field exists
                const emailField = page.locator('input[name="username"]');
                if (await emailField.isVisible({ timeout: 5000 })) {
                    await emailField.fill('test@example.com');
                    await page.waitForTimeout(2000);
                    console.log('   ✅ Email filled');
                }
                
                // Try to select country
                const countrySelect = page.locator('select[name="country_id"]');
                if (await countrySelect.isVisible({ timeout: 5000 })) {
                    await countrySelect.selectOption('DZ');
                    await page.waitForTimeout(2000);
                    console.log('   ✅ Country selected: Algeria');
                }
                
                await page.screenshot({ 
                    path: './screenshots/e2e-07-address-filling.png',
                    fullPage: true
                });
                
                // Wait for region field to load
                await page.waitForTimeout(3000);
                
                // Try to select Blida region
                const regionSelect = page.locator('select[name="region_id"]');
                if (await regionSelect.isVisible({ timeout: 5000 })) {
                    await regionSelect.selectOption('867'); // Blida
                    await page.waitForTimeout(3000);
                    console.log('   ✅ Region selected: Blida');
                    
                    await page.screenshot({ 
                        path: './screenshots/e2e-08-region-selected.png',
                        fullPage: true
                    });
                    
                    // Wait for shipping rates to load
                    await page.waitForTimeout(5000);
                    
                    // Check again for shipping cards
                    const finalCheck = await page.evaluate(() => {
                        const cards = document.querySelectorAll('.shipping-card');
                        return {
                            cardsWrapper: !!document.querySelector('.shipping-methods-cards-wrapper'),
                            cardCount: cards.length,
                            cards: Array.from(cards).map(card => ({
                                title: card.querySelector('.shipping-card-title')?.textContent.trim(),
                                price: card.querySelector('.shipping-card-price')?.textContent.trim(),
                                visible: window.getComputedStyle(card).display !== 'none'
                            })),
                            shippingTable: !!document.querySelector('.table-checkout-shipping-method'),
                            tableRows: document.querySelectorAll('.table-checkout-shipping-method tbody tr').length
                        };
                    });
                    
                    console.log('\n   📊 FINAL Shipping Check (After Blida Selection):');
                    console.log('      Cards Wrapper:', finalCheck.cardsWrapper ? '✅' : '❌');
                    console.log('      Card Count:', finalCheck.cardCount);
                    if (finalCheck.cardCount > 0) {
                        console.log('\n      🎉 SHIPPING CARDS FOUND:');
                        finalCheck.cards.forEach((card, i) => {
                            console.log(`         ${i+1}. ${card.title} - ${card.price}`);
                            console.log(`            Visible: ${card.visible ? '✅' : '❌'}`);
                        });
                    }
                    console.log('\n      Shipping Table:', finalCheck.shippingTable ? '✅' : '❌');
                    console.log('      Table Rows:', finalCheck.tableRows);
                    
                    await page.screenshot({ 
                        path: './screenshots/e2e-09-final-with-rates.png',
                        fullPage: true
                    });
                    
                    results.push({
                        test: 'Shipping Cards After Region Selection',
                        status: finalCheck.cardCount > 0 ? 'PASS' : 'FAIL',
                        details: finalCheck
                    });
                }
                
            } catch (formError) {
                console.log('   ⚠️  Form filling error:', formError.message);
            }
        }
        
        // Save results
        fs.writeFileSync(
            './test-results-e2e.json',
            JSON.stringify(results, null, 2)
        );
        
        console.log('\n' + '═'.repeat(80));
        console.log('📊 TEST COMPLETE');
        console.log('═'.repeat(80));
        console.log('Screenshots saved in: ./screenshots/');
        console.log('Results saved to: test-results-e2e.json');
        console.log('═'.repeat(80));
        
    } catch (error) {
        console.error('\n❌ Test Error:', error.message);
        try {
            await page.screenshot({ 
                path: './screenshots/e2e-error.png',
                fullPage: true
            });
        } catch (e) {}
    } finally {
        await browser.close();
    }
}

fullE2ETest();

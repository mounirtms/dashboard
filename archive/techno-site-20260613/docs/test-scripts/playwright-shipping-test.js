const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    
    console.log('🚀 Starting Playwright shipping cards test...\n');
    
    // Navigate to checkout page
    const checkoutUrl = 'https://dev.technostationery.com/checkout/';
    console.log('📍 Navigating to:', checkoutUrl);
    
    try {
        await page.goto(checkoutUrl, { waitUntil: 'networkidle', timeout: 60000 });
        console.log('✅ Page loaded successfully\n');
        
        // Wait for checkout to initialize
        await page.waitForTimeout(3000);
        
        // Check if we're on cart page (redirected from checkout)
        const currentUrl = page.url();
        console.log('📍 Current URL:', currentUrl);
        
        if (currentUrl.includes('/cart/')) {
            console.log('⚠️  Redirected to cart - cart is empty');
            console.log('💡 Adding product to cart first...\n');
            
            // Add product to cart
            await page.goto('https://dev.technostationery.com/index.php/catalogue/produits/fournitures-de-bureau/classement/chemise-a-rabat-a4-avec-fermeture-elastique-papier-grain-300g-couleur-ass-3-206.html');
            await page.waitForTimeout(2000);
            
            // Click add to cart button
            const addToCartBtn = await page.$('#product-addtocart-button');
            if (addToCartBtn) {
                await addToCartBtn.click();
                console.log('✅ Product added to cart');
                await page.waitForTimeout(3000);
            }
            
            // Go back to checkout
            await page.goto(checkoutUrl, { waitUntil: 'networkidle', timeout: 60000 });
            await page.waitForTimeout(3000);
        }
        
        // Check for shipping step
        const shippingStep = await page.$('.checkout-shipping-address');
        console.log('🔍 Shipping step found:', shippingStep !== null);
        
        // Look for region/wilaya selector
        const regionSelector = await page.$('select[name="region_id"]');
        console.log('🔍 Region selector found:', regionSelector !== null);
        
        if (regionSelector) {
            // Select Biskra (region 865)
            console.log('\n📍 Selecting Biskra (region 865)...');
            await page.selectOption('select[name="region_id"]', '865');
            await page.waitForTimeout(2000);
            
            console.log('✅ Region selected\n');
            
            // Wait for shipping methods to load
            await page.waitForTimeout(3000);
            
            // Check for shipping method cards
            const cardsWrapper = await page.$('.shipping-methods-cards-wrapper');
            console.log('🔍 Shipping cards wrapper found:', cardsWrapper !== null);
            
            if (cardsWrapper) {
                const isVisible = await page.evaluate(() => {
                    const wrapper = document.querySelector('.shipping-methods-cards-wrapper');
                    if (!wrapper) return false;
                    const styles = window.getComputedStyle(wrapper);
                    return styles.display !== 'none' && styles.visibility !== 'hidden' && styles.opacity !== '0';
                });
                
                console.log('🔍 Cards wrapper visible:', isVisible);
                
                // Count shipping cards
                const cards = await page.$$('.shipping-card');
                console.log('🔍 Number of shipping cards:', cards.length);
                
                if (cards.length > 0) {
                    console.log('\n✅ SUCCESS: Shipping method cards are displaying!\n');
                    
                    // Get details of each card
                    for (let i = 0; i < cards.length; i++) {
                        const title = await cards[i].$eval('.card-title', el => el.textContent.trim()).catch(() => 'N/A');
                        const price = await cards[i].$eval('.card-price', el => el.textContent.trim()).catch(() => 'N/A');
                        console.log(`   ${i + 1}. ${title} - ${price}`);
                    }
                } else {
                    console.log('\n❌ FAIL: No shipping cards found');
                    
                    // Check console logs
                    console.log('\n📋 Checking browser console logs...');
                    page.on('console', msg => console.log('   CONSOLE:', msg.text()));
                    await page.waitForTimeout(2000);
                }
            } else {
                console.log('\n❌ FAIL: Shipping cards wrapper not found in DOM');
            }
            
            // Get shipping rates from shipping service
            const shippingRates = await page.evaluate(() => {
                try {
                    const shippingService = require('Magento_Checkout/js/model/shipping-service');
                    const rates = shippingService.getShippingRates()();
                    return rates;
                } catch (e) {
                    return { error: e.message };
                }
            }).catch(err => ({ error: err.message }));
            
            console.log('\n📦 Shipping rates from service:');
            console.log(JSON.stringify(shippingRates, null, 2));
            
        } else {
            console.log('❌ Region selector not found - cannot test shipping cards');
        }
        
    } catch (error) {
        console.error('❌ Error during test:', error.message);
    }
    
    await browser.close();
    console.log('\n✅ Test completed');
})();

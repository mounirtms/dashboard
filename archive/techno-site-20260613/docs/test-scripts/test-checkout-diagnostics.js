/**
 * Checkout Diagnostics - Deep inspection of shipping cards rendering
 * Checks DOM, styles, and component state
 */

const { chromium } = require('playwright');
const fs = require('fs');

async function runDiagnostics() {
    console.log('\n' + '='.repeat(70));
    console.log('CHECKOUT SHIPPING CARDS DIAGNOSTICS');
    console.log('='.repeat(70) + '\n');
    
    // Read checkout URL from file
    const checkoutUrl = fs.existsSync('./test-checkout-url.txt')
        ? fs.readFileSync('./test-checkout-url.txt', 'utf8').trim()
        : 'https://dev.technostationery.com/checkout/';
    
    console.log(`Test URL: ${checkoutUrl}\n`);
    
    const browser = await chromium.launch({ headless: false });
    const page = await browser.newPage({
        viewport: { width: 1920, height: 1080 }
    });
    
    // Collect console logs
    const consoleLogs = [];
    page.on('console', msg => {
        const text = msg.text();
        consoleLogs.push({ type: msg.type(), text });
        
        if (text.includes('[Shipping Cards]') || text.includes('[Algerian States]')) {
            console.log(`📋 ${text}`);
        }
    });
    
    page.on('pageerror', error => {
        console.log(`❌ Page Error: ${error.message}`);
    });
    
    try {
        console.log('🌐 Loading checkout page...');
        await page.goto(checkoutUrl, {
            waitUntil: 'networkidle',
            timeout: 60000
        });
        
        await page.waitForTimeout(2000);
        
        console.log(`\n✅ Page loaded: ${page.url()}`);
        console.log(`   Title: ${await page.title()}\n`);
        
        // Diagnostic 1: Check if shipping step is present
        console.log('=== Diagnostic 1: Shipping Step ===');
        const shippingStep = await page.locator('#shipping').count();
        console.log(`Shipping step element: ${shippingStep > 0 ? '✅ Found' : '❌ Not found'}`);
        
        // Diagnostic 2: Check component initialization
        console.log('\n=== Diagnostic 2: Component Files ===');
        
        const componentCheck = await page.evaluate(() => {
            return {
                requireJsDefined: typeof require !== 'undefined',
                jQueryLoaded: typeof jQuery !== 'undefined',
                knockoutLoaded: typeof ko !== 'undefined',
                magentoCheckoutLoaded: typeof window.checkout !== 'undefined'
            };
        });
        
        console.log(`RequireJS: ${componentCheck.requireJsDefined ? '✅' : '❌'}`);
        console.log(`jQuery: ${componentCheck.jQueryLoaded ? '✅' : '❌'}`);
        console.log(`Knockout: ${componentCheck.knockoutLoaded ? '✅' : '❌'}`);
        console.log(`Magento Checkout: ${componentCheck.magentoCheckoutLoaded ? '✅' : '❌'}`);
        
        // Diagnostic 3: Check DOM elements
        console.log('\n=== Diagnostic 3: DOM Elements ===');
        
        const domCheck = await page.evaluate(() => {
            const wrapper = document.querySelector('.shipping-methods-cards-wrapper');
            const cards = document.querySelectorAll('.shipping-card');
            const regionSelect = document.querySelector('select[name="region_id"], select[name="shippingAddress.region_id"]');
            const continueBtn = document.querySelector('button.continue, button[data-role="opc-continue"]');
            
            return {
                wrapper: {
                    exists: !!wrapper,
                    display: wrapper ? window.getComputedStyle(wrapper).display : null,
                    visibility: wrapper ? window.getComputedStyle(wrapper).visibility : null,
                    opacity: wrapper ? window.getComputedStyle(wrapper).opacity : null,
                    innerHTML: wrapper ? wrapper.innerHTML.substring(0, 200) : null
                },
                cards: {
                    count: cards.length,
                    details: Array.from(cards).map(card => ({
                        methodCode: card.getAttribute('data-method-code'),
                        classes: card.className,
                        visible: window.getComputedStyle(card).display !== 'none'
                    }))
                },
                regionSelect: {
                    exists: !!regionSelect,
                    value: regionSelect ? regionSelect.value : null,
                    options: regionSelect ? regionSelect.options.length : 0
                },
                continueBtn: {
                    exists: !!continueBtn,
                    disabled: continueBtn ? continueBtn.disabled : null,
                    display: continueBtn ? window.getComputedStyle(continueBtn).display : null
                }
            };
        });
        
        console.log('\nShipping Cards Wrapper:');
        console.log(`  Exists: ${domCheck.wrapper.exists ? '✅' : '❌'}`);
        if (domCheck.wrapper.exists) {
            console.log(`  Display: ${domCheck.wrapper.display}`);
            console.log(`  Visibility: ${domCheck.wrapper.visibility}`);
            console.log(`  Opacity: ${domCheck.wrapper.opacity}`);
        }
        
        console.log(`\nShipping Cards: ${domCheck.cards.count} found`);
        domCheck.cards.details.forEach((card, i) => {
            console.log(`  Card ${i + 1}:`);
            console.log(`    Method Code: ${card.methodCode || 'N/A'}`);
            console.log(`    Classes: ${card.classes}`);
            console.log(`    Visible: ${card.visible ? '✅' : '❌'}`);
        });
        
        console.log(`\nRegion Select:`);
        console.log(`  Exists: ${domCheck.regionSelect.exists ? '✅' : '❌'}`);
        console.log(`  Options: ${domCheck.regionSelect.options}`);
        console.log(`  Selected: ${domCheck.regionSelect.value || 'None'}`);
        
        console.log(`\nContinue Button:`);
        console.log(`  Exists: ${domCheck.continueBtn.exists ? '✅' : '❌'}`);
        console.log(`  Disabled: ${domCheck.continueBtn.disabled}`);
        console.log(`  Display: ${domCheck.continueBtn.display}`);
        
        // Diagnostic 4: Select a region and watch what happens
        console.log('\n=== Diagnostic 4: Region Selection Test ===');
        
        const regionSelect = await page.locator('select[name="region_id"], select[name="shippingAddress.region_id"]').first();
        const hasRegionSelect = await regionSelect.count() > 0;
        
        if (hasRegionSelect) {
            console.log('🖱️  Selecting region: Boumerdès (893)...');
            await regionSelect.selectOption('893');
            
            console.log('⏳ Waiting 3 seconds for shipping rates...');
            await page.waitForTimeout(3000);
            
            const afterSelection = await page.evaluate(() => {
                const wrapper = document.querySelector('.shipping-methods-cards-wrapper');
                const cards = document.querySelectorAll('.shipping-card');
                
                return {
                    wrapperVisible: wrapper && window.getComputedStyle(wrapper).display !== 'none',
                    cardCount: cards.length,
                    cards: Array.from(cards).map(card => ({
                        method: card.querySelector('.method-name')?.textContent || 'Unknown',
                        price: card.querySelector('.price-amount, .free-badge')?.textContent || 'Unknown',
                        code: card.getAttribute('data-method-code')
                    }))
                };
            });
            
            console.log(`\nAfter region selection:`);
            console.log(`  Wrapper visible: ${afterSelection.wrapperVisible ? '✅' : '❌'}`);
            console.log(`  Cards rendered: ${afterSelection.cardCount}`);
            
            if (afterSelection.cardCount > 0) {
                console.log(`\n  Available shipping methods:`);
                afterSelection.cards.forEach((card, i) => {
                    console.log(`    ${i + 1}. ${card.method} - ${card.price} (${card.code})`);
                });
                
                // Try clicking a card
                console.log(`\n🖱️  Clicking first shipping card...`);
                await page.locator('.shipping-card').first().click();
                await page.waitForTimeout(1000);
                
                const afterClick = await page.evaluate(() => {
                    const selectedCard = document.querySelector('.shipping-card.selected');
                    const continueBtn = document.querySelector('button.continue, button[data-role="opc-continue"]');
                    
                    return {
                        hasSelectedCard: !!selectedCard,
                        selectedMethod: selectedCard?.getAttribute('data-method-code'),
                        continueBtnEnabled: continueBtn ? !continueBtn.disabled : false
                    };
                });
                
                console.log(`\nAfter clicking card:`);
                console.log(`  Card marked as selected: ${afterClick.hasSelectedCard ? '✅' : '❌'}`);
                console.log(`  Selected method: ${afterClick.selectedMethod || 'None'}`);
                console.log(`  Continue button enabled: ${afterClick.continueBtnEnabled ? '✅' : '❌'}`);
                
                if (afterClick.hasSelectedCard && afterClick.continueBtnEnabled) {
                    console.log('\n🎉 SUCCESS: Shipping cards are working correctly!');
                } else {
                    console.log('\n⚠️  ISSUE: Shipping cards visible but selection may not be working');
                }
                
            } else {
                console.log('\n❌ PROBLEM: No shipping cards rendered after region selection');
            }
            
        } else {
            console.log('❌ Region select not found - may not be on shipping step');
        }
        
        // Diagnostic 5: Console log analysis
        console.log('\n=== Diagnostic 5: Console Log Analysis ===');
        
        const shippingCardLogs = consoleLogs.filter(log => log.text.includes('[Shipping Cards]'));
        const errorLogs = consoleLogs.filter(log => log.type === 'error');
        
        console.log(`Total console logs: ${consoleLogs.length}`);
        console.log(`Shipping card logs: ${shippingCardLogs.length}`);
        console.log(`Error logs: ${errorLogs.length}`);
        
        if (shippingCardLogs.length > 0) {
            console.log('\nKey shipping card logs:');
            shippingCardLogs.slice(0, 10).forEach(log => {
                console.log(`  ${log.text}`);
            });
        }
        
        if (errorLogs.length > 0) {
            console.log('\nErrors found:');
            errorLogs.forEach(log => {
                console.log(`  ❌ ${log.text}`);
            });
        }
        
        // Take final screenshot
        await page.screenshot({ 
            path: './diagnostic-screenshot.png', 
            fullPage: true 
        });
        console.log('\n📸 Screenshot saved: diagnostic-screenshot.png');
        
        console.log('\n' + '='.repeat(70));
        console.log('DIAGNOSTICS COMPLETE');
        console.log('='.repeat(70) + '\n');
        
    } catch (error) {
        console.error(`\n❌ Error during diagnostics: ${error.message}`);
        await page.screenshot({ path: './diagnostic-error.png', fullPage: true });
        
    } finally {
        await page.waitForTimeout(2000);
        await browser.close();
    }
}

runDiagnostics().catch(console.error);

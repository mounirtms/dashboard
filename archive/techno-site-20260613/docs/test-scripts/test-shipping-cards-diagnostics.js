/**
 * Enhanced Shipping Cards Diagnostics
 * Captures screenshots, queries missing elements, and fixes console output
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

async function runEnhancedDiagnostics() {
    console.log('\n' + '='.repeat(80));
    console.log('ENHANCED SHIPPING CARDS DIAGNOSTICS');
    console.log('Captures screenshots, queries DOM, analyzes console output');
    console.log('='.repeat(80) + '\n');
    
    // Read checkout URL
    const checkoutUrl = fs.existsSync('./test-checkout-url.txt')
        ? fs.readFileSync('./test-checkout-url.txt', 'utf8').trim()
        : 'https://dev.technostationery.com/checkout/';
    
    console.log(`Test URL: ${checkoutUrl}\n`);
    
    const browser = await chromium.launch({ 
        headless: true,
        slowMo: 100
    });
    
    const page = await browser.newPage({
        viewport: { width: 1920, height: 1080 }
    });
    
    // Enhanced console tracking
    const consoleLogs = {
        all: [],
        shippingCards: [],
        errors: [],
        warnings: [],
        knockout: [],
        ajax: []
    };
    
    page.on('console', msg => {
        const text = msg.text();
        const type = msg.type();
        
        consoleLogs.all.push({ type, text, timestamp: new Date().toISOString() });
        
        // Categorize logs
        if (text.includes('[Shipping Cards]') || text.includes('shipping-method-cards')) {
            consoleLogs.shippingCards.push({ type, text });
            console.log(`📦 ${text}`);
        }
        
        if (text.includes('[Algerian States]')) {
            console.log(`🗺️  ${text}`);
        }
        
        if (type === 'error') {
            consoleLogs.errors.push(text);
            console.log(`❌ ${text}`);
        }
        
        if (type === 'warning') {
            consoleLogs.warnings.push(text);
        }
        
        if (text.includes('ko.') || text.includes('knockout') || text.toLowerCase().includes('binding')) {
            consoleLogs.knockout.push(text);
        }
        
        if (text.includes('XHR') || text.includes('estimate-shipping-methods') || text.includes('rest/default/V1')) {
            consoleLogs.ajax.push(text);
            console.log(`🌐 ${text}`);
        }
    });
    
    page.on('pageerror', error => {
        console.log(`🔴 Page Error: ${error.message}`);
        consoleLogs.errors.push(`Page Error: ${error.message}\n${error.stack}`);
    });
    
    // Track network requests for shipping rates
    const shippingRequests = [];
    page.on('response', async response => {
        const url = response.url();
        if (url.includes('estimate-shipping-methods') || url.includes('shipping-information')) {
            const status = response.status();
            try {
                const body = await response.text();
                shippingRequests.push({
                    url,
                    status,
                    body: body.substring(0, 500),
                    timestamp: new Date().toISOString()
                });
                console.log(`🚚 Shipping API call: ${url} → Status ${status}`);
            } catch (e) {
                shippingRequests.push({ url, status, error: e.message });
            }
        }
    });
    
    try {
        console.log('🌐 Loading checkout page...\n');
        await page.goto(checkoutUrl, {
            waitUntil: 'domcontentloaded',
            timeout: 60000
        });
        
        // Wait for checkout to initialize
        await page.waitForTimeout(5000);
        
        console.log(`\n✅ Page loaded: ${page.url()}`);
        console.log(`   Title: ${await page.title()}\n`);
        
        // === STEP 1: Initial DOM State ===
        console.log('=== Step 1: Initial DOM State ===\n');
        
        await takeAnnotatedScreenshot(page, './screenshots/01-initial-state.png', 'Initial page load');
        
        const initialState = await page.evaluate(() => {
            const wrapper = document.querySelector('.shipping-methods-cards-wrapper');
            const cards = document.querySelectorAll('.shipping-card');
            const shippingStep = document.querySelector('#shipping');
            const regionSelect = document.querySelector('select[name="region_id"], select[name="shippingAddress.region_id"]');
            
            return {
                shippingStepExists: !!shippingStep,
                shippingStepVisible: shippingStep ? window.getComputedStyle(shippingStep).display !== 'none' : false,
                wrapperExists: !!wrapper,
                wrapperStyles: wrapper ? {
                    display: window.getComputedStyle(wrapper).display,
                    visibility: window.getComputedStyle(wrapper).visibility,
                    opacity: window.getComputedStyle(wrapper).opacity,
                    height: window.getComputedStyle(wrapper).height
                } : null,
                cardsCount: cards.length,
                regionSelectExists: !!regionSelect,
                regionSelectValue: regionSelect ? regionSelect.value : null
            };
        });
        
        console.log('Shipping Step:', initialState.shippingStepExists ? '✅ Exists' : '❌ Missing');
        console.log('  Visible:', initialState.shippingStepVisible ? '✅ Yes' : '❌ No');
        
        console.log('\nShipping Cards Wrapper:', initialState.wrapperExists ? '✅ Exists' : '❌ Missing');
        if (initialState.wrapperExists) {
            console.log('  Display:', initialState.wrapperStyles.display);
            console.log('  Visibility:', initialState.wrapperStyles.visibility);
            console.log('  Opacity:', initialState.wrapperStyles.opacity);
            console.log('  Height:', initialState.wrapperStyles.height);
        }
        
        console.log('\nShipping Cards:', initialState.cardsCount, 'found');
        console.log('Region Select:', initialState.regionSelectExists ? '✅ Exists' : '❌ Missing');
        console.log('  Selected Region ID:', initialState.regionSelectValue || 'None');
        
        // === STEP 2: Check Component Initialization ===
        console.log('\n=== Step 2: Component Initialization ===\n');
        
        const componentState = await page.evaluate(() => {
            const results = {
                requireJs: typeof require !== 'undefined',
                jQuery: typeof jQuery !== 'undefined',
                knockout: typeof ko !== 'undefined',
                uiRegistry: typeof window.uiRegistry !== 'undefined',
                checkoutData: typeof window.checkoutData !== 'undefined'
            };
            
            // Try to access the shipping-cards component via requireJS
            if (typeof require !== 'undefined') {
                try {
                    require(['uiRegistry'], function(registry) {
                        const component = registry.get('checkout.steps.shipping-step.shipping-method-cards');
                        results.componentRegistered = !!component;
                        if (component) {
                            results.componentData = {
                                visible: component.visible ? component.visible() : 'N/A',
                                loading: component.loading ? component.loading() : 'N/A',
                                hasShippingMethods: component.shippingMethods ? component.shippingMethods().length : 0,
                                currentRegion: component.currentRegion ? component.currentRegion() : 'N/A'
                            };
                        }
                    });
                } catch (e) {
                    results.componentError = e.message;
                }
            }
            
            return results;
        });
        
        console.log('RequireJS:', componentState.requireJs ? '✅' : '❌');
        console.log('jQuery:', componentState.jQuery ? '✅' : '❌');
        console.log('Knockout:', componentState.knockout ? '✅' : '❌');
        console.log('UI Registry:', componentState.uiRegistry ? '✅' : '❌');
        console.log('Checkout Data:', componentState.checkoutData ? '✅' : '❌');
        
        if (componentState.componentRegistered) {
            console.log('\nShipping Cards Component:');
            console.log('  Registered:', '✅');
            console.log('  Visible:', componentState.componentData.visible);
            console.log('  Loading:', componentState.componentData.loading);
            console.log('  Shipping Methods Count:', componentState.componentData.hasShippingMethods);
            console.log('  Current Region:', componentState.componentData.currentRegion);
        }
        
        // === STEP 3: Region Selection Test ===
        console.log('\n=== Step 3: Region Selection Test ===\n');
        
        const regionSelect = page.locator('select[name="region_id"], select[name="shippingAddress.region_id"]').first();
        const hasRegionSelect = await regionSelect.count() > 0;
        
        if (hasRegionSelect) {
            // Get available regions
            const regions = await page.evaluate(() => {
                const select = document.querySelector('select[name="region_id"], select[name="shippingAddress.region_id"]');
                if (!select) return [];
                return Array.from(select.options).map(opt => ({
                    value: opt.value,
                    text: opt.text
                })).filter(opt => opt.value && opt.value !== '');
            });
            
            console.log(`Found ${regions.length} regions in dropdown:`);
            regions.slice(0, 5).forEach(r => {
                console.log(`  - ${r.text} (ID: ${r.value})`);
            });
            if (regions.length > 5) {
                console.log(`  ... and ${regions.length - 5} more`);
            }
            
            // Test with Boumerdès (893)
            console.log('\n🖱️  Selecting region: Boumerdès (ID: 893)...');
            await regionSelect.selectOption('893');
            
            await takeAnnotatedScreenshot(page, './screenshots/02-after-region-select.png', 'After selecting Boumerdès');
            
            console.log('⏳ Waiting 5 seconds for shipping rates API call...\n');
            await page.waitForTimeout(5000);
            
            // Check DOM after selection
            const afterSelection = await page.evaluate(() => {
                const wrapper = document.querySelector('.shipping-methods-cards-wrapper');
                const cards = document.querySelectorAll('.shipping-card');
                const errorMsg = document.querySelector('.shipping-methods-error');
                
                const result = {
                    wrapperExists: !!wrapper,
                    wrapperStyles: wrapper ? {
                        display: window.getComputedStyle(wrapper).display,
                        visibility: window.getComputedStyle(wrapper).visibility,
                        opacity: window.getComputedStyle(wrapper).opacity,
                        position: window.getComputedStyle(wrapper).position,
                        zIndex: window.getComputedStyle(wrapper).zIndex
                    } : null,
                    cardsCount: cards.length,
                    cards: [],
                    errorMessage: errorMsg ? errorMsg.textContent : null
                };
                
                cards.forEach(card => {
                    const methodTitle = card.querySelector('.method-title, .method-name');
                    const priceElement = card.querySelector('.price-amount, .free-badge');
                    const cardStyles = window.getComputedStyle(card);
                    
                    result.cards.push({
                        methodCode: card.getAttribute('data-method-code'),
                        title: methodTitle ? methodTitle.textContent.trim() : 'N/A',
                        price: priceElement ? priceElement.textContent.trim() : 'N/A',
                        classes: card.className,
                        visible: cardStyles.display !== 'none' && cardStyles.visibility !== 'hidden',
                        display: cardStyles.display,
                        position: cardStyles.position
                    });
                });
                
                return result;
            });
            
            console.log('After Region Selection:');
            console.log('  Wrapper exists:', afterSelection.wrapperExists ? '✅' : '❌');
            
            if (afterSelection.wrapperExists) {
                console.log('  Wrapper styles:');
                console.log('    Display:', afterSelection.wrapperStyles.display);
                console.log('    Visibility:', afterSelection.wrapperStyles.visibility);
                console.log('    Opacity:', afterSelection.wrapperStyles.opacity);
                console.log('    Position:', afterSelection.wrapperStyles.position);
                console.log('    Z-Index:', afterSelection.wrapperStyles.zIndex);
            }
            
            console.log('\n  Shipping Cards:', afterSelection.cardsCount, 'rendered');
            
            if (afterSelection.cardsCount > 0) {
                console.log('\n  📋 Card Details:');
                afterSelection.cards.forEach((card, i) => {
                    console.log(`\n    Card ${i + 1}:`);
                    console.log(`      Method Code: ${card.methodCode || 'N/A'}`);
                    console.log(`      Title: ${card.title}`);
                    console.log(`      Price: ${card.price}`);
                    console.log(`      Classes: ${card.classes}`);
                    console.log(`      Visible: ${card.visible ? '✅ Yes' : '❌ No'}`);
                    console.log(`      Display: ${card.display}`);
                    console.log(`      Position: ${card.position}`);
                });
                
                // === STEP 4: Test Card Selection ===
                console.log('\n=== Step 4: Test Card Selection ===\n');
                
                console.log('🖱️  Clicking first shipping card...');
                await page.locator('.shipping-card').first().click();
                await page.waitForTimeout(1500);
                
                await takeAnnotatedScreenshot(page, './screenshots/03-after-card-click.png', 'After clicking first card');
                
                const afterClick = await page.evaluate(() => {
                    const cards = document.querySelectorAll('.shipping-card');
                    const selectedCard = document.querySelector('.shipping-card.selected');
                    const continueBtn = document.querySelector('button.continue, button[data-role="opc-continue"]');
                    
                    return {
                        totalCards: cards.length,
                        hasSelectedCard: !!selectedCard,
                        selectedMethodCode: selectedCard ? selectedCard.getAttribute('data-method-code') : null,
                        selectedClasses: selectedCard ? selectedCard.className : null,
                        continueBtnExists: !!continueBtn,
                        continueBtnDisabled: continueBtn ? continueBtn.disabled : null,
                        continueBtnClasses: continueBtn ? continueBtn.className : null,
                        allCardsState: Array.from(cards).map(card => ({
                            code: card.getAttribute('data-method-code'),
                            hasSelected: card.classList.contains('selected'),
                            ariaChecked: card.querySelector('input[type="radio"]')?.checked
                        }))
                    };
                });
                
                console.log('After Clicking Card:');
                console.log('  Selected card exists:', afterClick.hasSelectedCard ? '✅ Yes' : '❌ No');
                
                if (afterClick.hasSelectedCard) {
                    console.log('  Selected method code:', afterClick.selectedMethodCode);
                    console.log('  Selected card classes:', afterClick.selectedClasses);
                }
                
                console.log('\n  All Cards State:');
                afterClick.allCardsState.forEach((state, i) => {
                    console.log(`    Card ${i + 1} (${state.code}): ${state.hasSelected ? '✅ Selected' : '⚪ Not selected'} | Radio: ${state.ariaChecked ? 'Checked' : 'Unchecked'}`);
                });
                
                console.log('\n  Continue Button:');
                console.log('    Exists:', afterClick.continueBtnExists ? '✅' : '❌');
                console.log('    Disabled:', afterClick.continueBtnDisabled ? '❌ Yes (BLOCKED)' : '✅ No (Enabled)');
                console.log('    Classes:', afterClick.continueBtnClasses);
                
                // === STEP 5: Test Contrast & Accessibility ===
                console.log('\n=== Step 5: Accessibility & Contrast Check ===\n');
                
                const accessibilityCheck = await page.evaluate(() => {
                    const selectedCard = document.querySelector('.shipping-card.selected');
                    if (!selectedCard) return { error: 'No selected card to test' };
                    
                    const hintElements = selectedCard.querySelectorAll('.hint, .message-hint, .selected-indicator');
                    const styles = [];
                    
                    hintElements.forEach(el => {
                        const computed = window.getComputedStyle(el);
                        styles.push({
                            element: el.className,
                            color: computed.color,
                            backgroundColor: computed.backgroundColor,
                            fontSize: computed.fontSize,
                            fontWeight: computed.fontWeight,
                            opacity: computed.opacity
                        });
                    });
                    
                    return { hintCount: hintElements.length, styles };
                });
                
                if (accessibilityCheck.hintCount > 0) {
                    console.log(`Found ${accessibilityCheck.hintCount} hint/message elements:`);
                    accessibilityCheck.styles.forEach((style, i) => {
                        console.log(`\n  Element ${i + 1} (.${style.element}):`);
                        console.log(`    Color: ${style.color}`);
                        console.log(`    Background: ${style.backgroundColor}`);
                        console.log(`    Font Size: ${style.fontSize}`);
                        console.log(`    Font Weight: ${style.fontWeight}`);
                        console.log(`    Opacity: ${style.opacity}`);
                    });
                } else {
                    console.log('No hint elements found (or no selected card)');
                }
                
                // Final success/failure verdict
                if (afterSelection.cardsCount > 0 && afterClick.hasSelectedCard && !afterClick.continueBtnDisabled) {
                    console.log('\n✅ ========================================');
                    console.log('✅ SUCCESS: Shipping cards are working!');
                    console.log('✅ ========================================\n');
                } else {
                    console.log('\n❌ ========================================');
                    console.log('❌ ISSUE DETECTED:');
                    if (afterSelection.cardsCount === 0) {
                        console.log('   - No shipping cards rendered');
                    }
                    if (!afterClick.hasSelectedCard) {
                        console.log('   - Card selection not working');
                    }
                    if (afterClick.continueBtnDisabled) {
                        console.log('   - Continue button still disabled');
                    }
                    console.log('❌ ========================================\n');
                }
                
            } else {
                console.log('\n❌ CRITICAL: No shipping cards rendered after region selection!');
                
                if (afterSelection.errorMessage) {
                    console.log(`   Error message shown: "${afterSelection.errorMessage}"`);
                }
                
                await takeAnnotatedScreenshot(page, './screenshots/03-no-cards-error.png', 'ERROR: No cards rendered');
            }
            
        } else {
            console.log('❌ Region select dropdown not found!');
        }
        
        // === STEP 6: Console Log Summary ===
        console.log('\n=== Step 6: Console Log Summary ===\n');
        
        console.log(`Total console messages: ${consoleLogs.all.length}`);
        console.log(`  - Shipping Cards logs: ${consoleLogs.shippingCards.length}`);
        console.log(`  - Errors: ${consoleLogs.errors.length}`);
        console.log(`  - Warnings: ${consoleLogs.warnings.length}`);
        console.log(`  - Knockout/Binding logs: ${consoleLogs.knockout.length}`);
        console.log(`  - AJAX/API logs: ${consoleLogs.ajax.length}`);
        
        if (consoleLogs.shippingCards.length > 0) {
            console.log('\n📦 Shipping Cards Logs (last 15):');
            consoleLogs.shippingCards.slice(-15).forEach(log => {
                console.log(`   ${log.text}`);
            });
        }
        
        if (consoleLogs.errors.length > 0) {
            console.log('\n❌ Errors Found:');
            consoleLogs.errors.forEach(err => {
                console.log(`   ${err}`);
            });
        }
        
        if (shippingRequests.length > 0) {
            console.log('\n🚚 Shipping API Requests:');
            shippingRequests.forEach(req => {
                console.log(`   ${req.url}`);
                console.log(`   Status: ${req.status}`);
                if (req.body) {
                    console.log(`   Response preview: ${req.body.substring(0, 200)}...`);
                }
            });
        }
        
        // Save detailed report
        const report = {
            testUrl: checkoutUrl,
            timestamp: new Date().toISOString(),
            initialState,
            componentState,
            afterSelection,
            consoleLogs,
            shippingRequests
        };
        
        fs.writeFileSync('./diagnostic-report.json', JSON.stringify(report, null, 2));
        console.log('\n📄 Detailed report saved: diagnostic-report.json');
        
        await takeAnnotatedScreenshot(page, './screenshots/04-final-state.png', 'Final state');
        
        console.log('\n' + '='.repeat(80));
        console.log('DIAGNOSTICS COMPLETE');
        console.log('Screenshots saved in ./screenshots/');
        console.log('='.repeat(80) + '\n');
        
    } catch (error) {
        console.error(`\n❌ Error during diagnostics: ${error.message}`);
        console.error(error.stack);
        try {
            await page.screenshot({ path: './screenshots/error-state.png', fullPage: true, timeout: 10000 });
        } catch (e) {
            console.log('Could not take error screenshot:', e.message);
        }
        
    } finally {
        await page.waitForTimeout(2000);
        await browser.close();
    }
}

async function takeAnnotatedScreenshot(page, filename, annotation) {
    // Create screenshots directory if it doesn't exist
    const dir = path.dirname(filename);
    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }
    
    try {
        await page.screenshot({ path: filename, fullPage: true, timeout: 10000 });
        console.log(`📸 Screenshot: ${filename} (${annotation})`);
    } catch (e) {
        console.log(`⚠️  Could not take screenshot: ${e.message}`);
    }
}

runEnhancedDiagnostics().catch(console.error);

/**
 * Comprehensive Shipping Cards Test with Screenshots
 * Tests the actual checkout page and captures detailed screenshots
 */
const { chromium } = require('playwright');
const fs = require('fs');

async function comprehensiveTest() {
    console.log('🔍 COMPREHENSIVE SHIPPING CARDS TEST\n');
    console.log('═'.repeat(80));
    
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    
    // Set viewport
    await page.setViewportSize({ width: 1920, height: 1080 });
    
    const results = {
        timestamp: new Date().toISOString(),
        tests: [],
        screenshots: [],
        consoleLogs: []
    };
    
    // Capture console logs
    page.on('console', msg => {
        const text = msg.text();
        results.consoleLogs.push(text);
        if (text.includes('[Shipping Cards]') || text.includes('shipping') || text.includes('method')) {
            console.log('📝', text);
        }
    });
    
    try {
        // Read checkout URL
        const url = fs.readFileSync('test-checkout-url.txt', 'utf8').trim();
        console.log('\n📍 Testing URL:', url);
        console.log('\n⏳ Loading checkout page...\n');
        
        // Navigate to checkout
        await page.goto(url, { waitUntil: 'networkidle', timeout: 45000 });
        
        // Wait for page to stabilize
        await page.waitForTimeout(3000);
        
        // Screenshot 1: Initial page load
        await page.screenshot({ 
            path: './screenshots/test-01-initial-load.png', 
            fullPage: true 
        });
        results.screenshots.push('test-01-initial-load.png');
        console.log('📸 Screenshot 1: Initial page load');
        
        // Check page title and URL
        const pageTitle = await page.title();
        const finalUrl = page.url();
        console.log('\n📄 Page Title:', pageTitle);
        console.log('🔗 Final URL:', finalUrl);
        
        results.tests.push({
            name: 'Page Load',
            status: finalUrl.includes('/checkout/') ? 'PASS' : 'FAIL',
            details: { pageTitle, finalUrl }
        });
        
        // Wait a bit more for JavaScript to execute
        await page.waitForTimeout(2000);
        
        // Test 1: Check for shipping step
        const shippingStep = await page.evaluate(() => {
            const step = document.querySelector('#opc-shipping_method, .checkout-shipping-method');
            return {
                exists: !!step,
                visible: step ? window.getComputedStyle(step).display !== 'none' : false,
                innerHTML: step ? step.innerHTML.substring(0, 500) : null
            };
        });
        
        console.log('\n✓ Test 1: Shipping Step');
        console.log('  Exists:', shippingStep.exists ? '✅' : '❌');
        console.log('  Visible:', shippingStep.visible ? '✅' : '❌');
        
        results.tests.push({
            name: 'Shipping Step Present',
            status: shippingStep.exists && shippingStep.visible ? 'PASS' : 'FAIL',
            details: shippingStep
        });
        
        // Test 2: Check for shipping cards wrapper
        const cardsWrapper = await page.evaluate(() => {
            const wrapper = document.querySelector('.shipping-methods-cards-wrapper');
            if (!wrapper) return { exists: false };
            
            const styles = window.getComputedStyle(wrapper);
            return {
                exists: true,
                display: styles.display,
                visibility: styles.visibility,
                opacity: styles.opacity,
                position: styles.position,
                width: styles.width,
                height: styles.height,
                classes: wrapper.className,
                innerHTML: wrapper.innerHTML.substring(0, 300)
            };
        });
        
        console.log('\n✓ Test 2: Shipping Cards Wrapper');
        console.log('  Exists:', cardsWrapper.exists ? '✅' : '❌');
        if (cardsWrapper.exists) {
            console.log('  Display:', cardsWrapper.display);
            console.log('  Visibility:', cardsWrapper.visibility);
            console.log('  Opacity:', cardsWrapper.opacity);
        }
        
        results.tests.push({
            name: 'Cards Wrapper Present',
            status: cardsWrapper.exists ? 'PASS' : 'FAIL',
            details: cardsWrapper
        });
        
        // Test 3: Check for individual shipping cards
        const shippingCards = await page.evaluate(() => {
            const cards = document.querySelectorAll('.shipping-card');
            return Array.from(cards).map(card => {
                const styles = window.getComputedStyle(card);
                const radio = card.querySelector('input[type="radio"]');
                const title = card.querySelector('.shipping-card-title, .method-title');
                const price = card.querySelector('.shipping-card-price, .price-badge');
                
                return {
                    exists: true,
                    display: styles.display,
                    visibility: styles.visibility,
                    opacity: styles.opacity,
                    methodCode: radio ? radio.value : null,
                    title: title ? title.textContent.trim() : null,
                    price: price ? price.textContent.trim() : null,
                    classes: card.className
                };
            });
        });
        
        console.log('\n✓ Test 3: Shipping Cards');
        console.log('  Count:', shippingCards.length);
        
        if (shippingCards.length > 0) {
            console.log('\n  📋 Card Details:');
            shippingCards.forEach((card, i) => {
                console.log(`    ${i + 1}. ${card.title || 'No title'}`);
                console.log(`       Price: ${card.price || 'No price'}`);
                console.log(`       Code: ${card.methodCode || 'No code'}`);
                console.log(`       Visible: ${card.visibility !== 'hidden' && card.display !== 'none' ? '✅' : '❌'}`);
            });
        }
        
        results.tests.push({
            name: 'Shipping Cards Rendered',
            status: shippingCards.length > 0 ? 'PASS' : 'FAIL',
            details: { count: shippingCards.length, cards: shippingCards }
        });
        
        // Screenshot 2: After cards check
        await page.screenshot({ 
            path: './screenshots/test-02-cards-check.png', 
            fullPage: true 
        });
        results.screenshots.push('test-02-cards-check.png');
        console.log('\n📸 Screenshot 2: After cards check');
        
        // Test 4: Check default Magento shipping methods
        const defaultShippingMethods = await page.evaluate(() => {
            const table = document.querySelector('.table-checkout-shipping-method');
            const rows = table ? table.querySelectorAll('tbody tr') : [];
            return {
                tableExists: !!table,
                rowCount: rows.length,
                visible: table ? window.getComputedStyle(table).display !== 'none' : false
            };
        });
        
        console.log('\n✓ Test 4: Default Shipping Methods Table');
        console.log('  Table Exists:', defaultShippingMethods.tableExists ? '✅' : '❌');
        console.log('  Rows:', defaultShippingMethods.rowCount);
        console.log('  Visible:', defaultShippingMethods.visible ? '✅' : '❌');
        
        results.tests.push({
            name: 'Default Shipping Table',
            status: defaultShippingMethods.tableExists ? 'PASS' : 'FAIL',
            details: defaultShippingMethods
        });
        
        // Test 5: Check for any shipping-related elements
        const shippingElements = await page.evaluate(() => {
            return {
                shippingMethodsForm: !!document.querySelector('#co-shipping-method-form'),
                shippingRatesTable: !!document.querySelector('.table-checkout-shipping-method'),
                noQuotesBlock: !!document.querySelector('.no-quotes-block'),
                beforeShippingRegion: document.querySelectorAll('[data-bind*="before-shipping"]').length,
                anyShippingDiv: document.querySelectorAll('[class*="shipping"], [id*="shipping"]').length
            };
        });
        
        console.log('\n✓ Test 5: Shipping Elements Survey');
        console.log('  Shipping Form:', shippingElements.shippingMethodsForm ? '✅' : '❌');
        console.log('  Rates Table:', shippingElements.shippingRatesTable ? '✅' : '❌');
        console.log('  No Quotes Block:', shippingElements.noQuotesBlock ? '✅' : '❌');
        console.log('  Before-shipping regions:', shippingElements.beforeShippingRegion);
        console.log('  Total shipping elements:', shippingElements.anyShippingDiv);
        
        results.tests.push({
            name: 'Shipping Elements Present',
            status: shippingElements.anyShippingDiv > 0 ? 'PASS' : 'FAIL',
            details: shippingElements
        });
        
        // Screenshot 3: Final state
        await page.screenshot({ 
            path: './screenshots/test-03-final-state.png', 
            fullPage: true 
        });
        results.screenshots.push('test-03-final-state.png');
        console.log('\n📸 Screenshot 3: Final state');
        
        // Analyze console logs
        const relevantLogs = results.consoleLogs.filter(log => 
            log.toLowerCase().includes('shipping') || 
            log.toLowerCase().includes('card') ||
            log.toLowerCase().includes('method') ||
            log.toLowerCase().includes('error')
        );
        
        console.log('\n✓ Test 6: Console Logs Analysis');
        console.log('  Total logs:', results.consoleLogs.length);
        console.log('  Relevant logs:', relevantLogs.length);
        
        if (relevantLogs.length > 0 && relevantLogs.length <= 30) {
            console.log('\n  📝 Key Log Messages:');
            relevantLogs.forEach(log => console.log('    -', log.substring(0, 120)));
        }
        
        // Calculate pass rate
        const passCount = results.tests.filter(t => t.status === 'PASS').length;
        const totalTests = results.tests.length;
        const passRate = ((passCount / totalTests) * 100).toFixed(1);
        
        console.log('\n' + '═'.repeat(80));
        console.log('📊 TEST SUMMARY');
        console.log('═'.repeat(80));
        console.log(`Tests Passed: ${passCount}/${totalTests} (${passRate}%)`);
        console.log(`Screenshots: ${results.screenshots.length} saved in ./screenshots/`);
        console.log(`Console Logs: ${relevantLogs.length} relevant messages`);
        
        console.log('\n📋 Individual Test Results:');
        results.tests.forEach((test, i) => {
            const icon = test.status === 'PASS' ? '✅' : '❌';
            console.log(`  ${i + 1}. ${icon} ${test.name}: ${test.status}`);
        });
        
        // Save detailed results
        fs.writeFileSync(
            './test-results-comprehensive.json',
            JSON.stringify(results, null, 2)
        );
        console.log('\n💾 Detailed results saved to: test-results-comprehensive.json');
        
        // Overall verdict
        console.log('\n' + '═'.repeat(80));
        if (shippingCards.length > 0) {
            console.log('✅ SUCCESS: SHIPPING CARDS ARE RENDERING!');
            console.log(`   Found ${shippingCards.length} shipping method cards`);
        } else if (defaultShippingMethods.tableExists && defaultShippingMethods.rowCount > 0) {
            console.log('⚠️  PARTIAL: Default shipping table is showing');
            console.log('   Cards not rendering, but standard methods available');
        } else {
            console.log('❌ FAILED: No shipping methods visible');
            console.log('   Neither cards nor default table found');
        }
        console.log('═'.repeat(80));
        
    } catch (error) {
        console.error('\n❌ Test Error:', error.message);
        
        // Try to capture error screenshot
        try {
            await page.screenshot({ 
                path: './screenshots/test-error.png', 
                fullPage: true 
            });
            console.log('📸 Error screenshot saved');
        } catch (e) {}
        
        results.tests.push({
            name: 'Test Execution',
            status: 'FAIL',
            error: error.message
        });
    } finally {
        await browser.close();
    }
}

comprehensiveTest();

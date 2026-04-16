const { chromium } = require('playwright');

(async () => {
    console.log('=== Starting Playwright Checkout Test ===\n');
    
    const browser = await chromium.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    
    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 },
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    });
    
    const page = await context.newPage();
    
    // Collect console messages
    const consoleLogs = [];
    page.on('console', msg => {
        const text = msg.text();
        consoleLogs.push({
            type: msg.type(),
            text: text,
            timestamp: new Date().toISOString()
        });
        console.log(`[${msg.type().toUpperCase()}] ${text}`);
    });
    
    // Collect errors
    const errors = [];
    page.on('pageerror', error => {
        errors.push({
            message: error.message,
            stack: error.stack,
            timestamp: new Date().toISOString()
        });
        console.error('[PAGE ERROR]', error.message);
    });
    
    try {
        console.log('\n1. Navigating to checkout...');
        await page.goto('https://dev.technostationery.com/checkout', {
            waitUntil: 'networkidle',
            timeout: 60000
        });
        
        console.log('✓ Page loaded\n');
        
        // Wait for checkout to load
        await page.waitForTimeout(3000);
        
        console.log('2. Checking for shipping cards wrapper...');
        const wrapperExists = await page.locator('.shipping-methods-cards-wrapper').count() > 0;
        console.log(`Wrapper exists: ${wrapperExists}`);
        
        if (wrapperExists) {
            // Get wrapper styles
            const wrapperStyle = await page.evaluate(() => {
                const wrapper = document.querySelector('.shipping-methods-cards-wrapper');
                if (!wrapper) return null;
                
                const computed = window.getComputedStyle(wrapper);
                return {
                    display: computed.display,
                    visibility: computed.visibility,
                    opacity: computed.opacity,
                    inlineStyle: wrapper.getAttribute('style'),
                    dataRegion: wrapper.getAttribute('data-region-selected')
                };
            });
            
            console.log('Wrapper styles:', JSON.stringify(wrapperStyle, null, 2));
        }
        
        console.log('\n3. Checking for shipping cards...');
        const cardsCount = await page.locator('.shipping-card').count();
        console.log(`Shipping cards found: ${cardsCount}`);
        
        if (cardsCount > 0) {
            console.log('✓ Shipping cards are visible!\n');
            
            // Get card details
            const cards = await page.evaluate(() => {
                const cardElements = document.querySelectorAll('.shipping-card');
                return Array.from(cardElements).map((card, index) => {
                    const title = card.querySelector('.method-name')?.textContent || 'No title';
                    const price = card.querySelector('.price-amount, .free-badge')?.textContent || 'No price';
                    const desc = card.querySelector('.method-description')?.textContent || 'No description';
                    const computed = window.getComputedStyle(card);
                    
                    return {
                        index,
                        title,
                        price,
                        description: desc,
                        display: computed.display,
                        visibility: computed.visibility
                    };
                });
            });
            
            console.log('Shipping cards details:');
            cards.forEach(card => {
                console.log(`  [${card.index}] ${card.title} - ${card.price}`);
                console.log(`      Display: ${card.display}, Visibility: ${card.visibility}`);
            });
        } else {
            console.log('✗ No shipping cards found!\n');
        }
        
        console.log('\n4. Checking region dropdown...');
        const regionExists = await page.locator('select[name="region_id"]').count() > 0;
        console.log(`Region dropdown exists: ${regionExists}`);
        
        if (regionExists) {
            const regionValue = await page.locator('select[name="region_id"]').inputValue();
            const regionVisible = await page.locator('select[name="region_id"]').isVisible();
            console.log(`Region value: ${regionValue || 'empty'}`);
            console.log(`Region visible: ${regionVisible}`);
            
            // Get region options
            const options = await page.evaluate(() => {
                const select = document.querySelector('select[name="region_id"]');
                if (!select) return [];
                return Array.from(select.options).map(opt => ({
                    value: opt.value,
                    text: opt.text,
                    selected: opt.selected
                }));
            });
            
            console.log(`Region options count: ${options.length}`);
            if (options.length > 0) {
                console.log('First 5 options:', options.slice(0, 5).map(o => o.text));
            }
            
            // Try to select Batna if it exists
            const batnaOption = options.find(opt => 
                opt.text.toLowerCase().includes('batna')
            );
            
            if (batnaOption) {
                console.log(`\n5. Selecting Batna region (value: ${batnaOption.value})...`);
                await page.selectOption('select[name="region_id"]', batnaOption.value);
                console.log('✓ Batna selected');
                
                // Wait for shipping methods to load
                await page.waitForTimeout(2000);
                
                // Check if cards appeared/updated
                const cardsAfterSelect = await page.locator('.shipping-card').count();
                console.log(`Shipping cards after region select: ${cardsAfterSelect}`);
                
                if (cardsAfterSelect > 0) {
                    console.log('✓ Cards visible after region selection!');
                    
                    // Check if cards are for Batna
                    const cardTitles = await page.evaluate(() => {
                        return Array.from(document.querySelectorAll('.method-name'))
                            .map(el => el.textContent);
                    });
                    console.log('Card titles:', cardTitles);
                } else {
                    console.log('✗ Cards NOT visible after region selection');
                }
            } else {
                console.log('✗ Batna option not found in dropdown');
            }
        }
        
        console.log('\n6. Checking for JavaScript errors...');
        if (errors.length > 0) {
            console.log(`Found ${errors.length} JavaScript errors:`);
            errors.forEach((err, i) => {
                console.log(`  Error ${i + 1}:`, err.message);
            });
        } else {
            console.log('✓ No JavaScript errors detected');
        }
        
        console.log('\n7. Checking KnockoutJS binding...');
        const koBinding = await page.evaluate(() => {
            try {
                const wrapper = document.querySelector('.shipping-methods-cards-wrapper');
                if (!wrapper) return { error: 'Wrapper not found' };
                
                const koData = ko.dataFor(wrapper);
                if (!koData) return { error: 'No KO binding' };
                
                return {
                    hasShippingMethods: typeof koData.shippingMethods === 'function',
                    methodsCount: koData.shippingMethods ? koData.shippingMethods().length : 0,
                    isVisible: typeof koData.isVisible === 'function' ? koData.isVisible() : 'not found',
                    currentRegion: typeof koData.currentRegion === 'function' ? koData.currentRegion() : 'not found'
                };
            } catch (e) {
                return { error: e.message };
            }
        });
        
        console.log('KnockoutJS binding data:', JSON.stringify(koBinding, null, 2));
        
        console.log('\n8. Taking screenshot...');
        await page.screenshot({ 
            path: 'checkout-test-screenshot.png', 
            fullPage: true 
        });
        console.log('✓ Screenshot saved: checkout-test-screenshot.png');
        
        // Summary report
        console.log('\n' + '='.repeat(60));
        console.log('SUMMARY REPORT');
        console.log('='.repeat(60));
        console.log(`Wrapper exists: ${wrapperExists ? '✓' : '✗'}`);
        console.log(`Shipping cards visible: ${cardsCount > 0 ? '✓' : '✗'} (${cardsCount} cards)`);
        console.log(`Region dropdown exists: ${regionExists ? '✓' : '✗'}`);
        console.log(`JavaScript errors: ${errors.length === 0 ? '✓' : '✗'} (${errors.length} errors)`);
        console.log(`Console logs collected: ${consoleLogs.length}`);
        
        // Filter important console logs
        const importantLogs = consoleLogs.filter(log => 
            log.text.includes('Shipping') || 
            log.text.includes('Region') || 
            log.text.includes('Address') ||
            log.text.includes('visible') ||
            log.text.includes('cards')
        );
        
        if (importantLogs.length > 0) {
            console.log('\n' + '='.repeat(60));
            console.log('IMPORTANT CONSOLE LOGS');
            console.log('='.repeat(60));
            importantLogs.forEach(log => {
                console.log(`[${log.type}] ${log.text}`);
            });
        }
        
        // Export full report
        const report = {
            timestamp: new Date().toISOString(),
            url: 'https://dev.technostationery.com/checkout',
            results: {
                wrapperExists,
                cardsCount,
                regionExists,
                errorsCount: errors.length,
                consoleLogsCount: consoleLogs.length
            },
            errors,
            consoleLogs: importantLogs,
            koBinding
        };
        
        const fs = require('fs');
        fs.writeFileSync('checkout-test-report.json', JSON.stringify(report, null, 2));
        console.log('\n✓ Full report saved: checkout-test-report.json');
        
    } catch (error) {
        console.error('\n✗ Test failed with error:', error.message);
        console.error(error.stack);
    } finally {
        await browser.close();
        console.log('\n=== Test completed ===');
    }
})();

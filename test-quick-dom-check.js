/**
 * Quick DOM Check - Verify Shipping Cards Wrapper Exists
 * Simple test to see if template is rendering
 */
const { chromium } = require('playwright');

async function quickDOMCheck() {
    console.log('🔍 QUICK DOM CHECK - Shipping Cards Wrapper\n');
    console.log('═'.repeat(60));
    
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    
    try {
        // Get checkout URL
        const fs = require('fs');
        const checkoutUrl = fs.readFileSync('test-checkout-url.txt', 'utf8').trim();
        
        console.log('📍 URL:', checkoutUrl);
        console.log('⏳ Loading page...\n');
        
        // Navigate to checkout
        await page.goto(checkoutUrl, { waitUntil: 'networkidle', timeout: 30000 });
        
        // Wait a bit for JavaScript to execute
        await page.waitForTimeout(3000);
        
        // Check for wrapper element
        const wrapperExists = await page.evaluate(() => {
            const wrapper = document.querySelector('.shipping-methods-cards-wrapper');
            if (wrapper) {
                const styles = window.getComputedStyle(wrapper);
                return {
                    exists: true,
                    display: styles.display,
                    visibility: styles.visibility,
                    opacity: styles.opacity,
                    innerHTML: wrapper.innerHTML.substring(0, 200) + '...',
                    classes: wrapper.className,
                    parentTag: wrapper.parentElement?.tagName,
                    parentClasses: wrapper.parentElement?.className
                };
            }
            return { exists: false };
        });
        
        // Check for cards
        const cardCount = await page.evaluate(() => {
            return document.querySelectorAll('.shipping-card').length;
        });
        
        // Check for before-shipping-method-form region
        const regionCheck = await page.evaluate(() => {
            // Look for any element with data-bind containing before-shipping-method-form
            const elements = document.querySelectorAll('[data-bind]');
            for (let elem of elements) {
                if (elem.getAttribute('data-bind').includes('before-shipping-method-form')) {
                    return {
                        found: true,
                        tag: elem.tagName,
                        bind: elem.getAttribute('data-bind'),
                        html: elem.innerHTML.substring(0, 100)
                    };
                }
            }
            return { found: false };
        });
        
        // Results
        console.log('📊 RESULTS:');
        console.log('═'.repeat(60));
        
        if (wrapperExists.exists) {
            console.log('✅ WRAPPER FOUND!');
            console.log('   Display:', wrapperExists.display);
            console.log('   Visibility:', wrapperExists.visibility);
            console.log('   Opacity:', wrapperExists.opacity);
            console.log('   Classes:', wrapperExists.classes);
            console.log('   Parent:', wrapperExists.parentTag, '-', wrapperExists.parentClasses);
            console.log('   Has Content:', wrapperExists.innerHTML.length > 10);
        } else {
            console.log('❌ WRAPPER NOT FOUND!');
            console.log('   Template did not render in DOM');
        }
        
        console.log('\n📋 Shipping Cards:', cardCount);
        
        if (regionCheck.found) {
            console.log('✅ before-shipping-method-form region found');
            console.log('   Tag:', regionCheck.tag);
            console.log('   Bind:', regionCheck.bind);
        } else {
            console.log('⚠️  before-shipping-method-form region not found');
        }
        
        // Console logs
        const logs = [];
        page.on('console', msg => {
            const text = msg.text();
            if (text.includes('[Shipping Cards]') || text.includes('[Cards Injector]') || text.includes('[Shipping Visibility]')) {
                logs.push(text);
            }
        });
        
        // Wait for logs to accumulate
        await page.waitForTimeout(2000);
        
        if (logs.length > 0) {
            console.log('\n📝 Relevant Console Logs:');
            console.log('═'.repeat(60));
            logs.slice(0, 10).forEach(log => console.log('  ', log));
        }
        
        // Screenshot
        await page.screenshot({ path: './screenshots/quick-dom-check.png', fullPage: true });
        console.log('\n📸 Screenshot saved: ./screenshots/quick-dom-check.png');
        
        console.log('\n' + '═'.repeat(60));
        console.log(wrapperExists.exists ? '✅ SUCCESS - Template is rendering!' : '❌ FAILURE - Template not rendering');
        console.log('═'.repeat(60));
        
    } catch (error) {
        console.error('❌ Test failed:', error.message);
    } finally {
        await browser.close();
    }
}

quickDOMCheck();

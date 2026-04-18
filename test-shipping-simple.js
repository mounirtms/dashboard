/**
 * Simple Shipping Cards Test
 * Quick visual test of shipping cards rendering
 */

const { chromium } = require('playwright');
const fs = require('fs');

async function runSimpleTest() {
    console.log('\n=================================');
    console.log('SIMPLE SHIPPING CARDS TEST');
    console.log('=================================\n');
    
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({
        viewport: { width: 1920, height: 1080 }
    });
    
    // Track console
    const logs = [];
    page.on('console', msg => {
        const text = msg.text();
        logs.push({ type: msg.type(), text });
        
        if (text.includes('[Shipping Cards]') || text.includes('shipping')) {
            console.log(`📋 ${text}`);
        }
    });
    
    page.on('pageerror', error => {
        console.log(`❌ Error: ${error.message}`);
    });
    
    try {
        // Go directly to checkout
        const url = 'https://dev.technostationery.com/checkout/';
        console.log(`🌐 Loading: ${url}\n`);
        
        await page.goto(url, {
            waitUntil: 'domcontentloaded',
            timeout: 30000
        });
        
        await page.waitForTimeout(3000);
        
        console.log(`✅ Page loaded: ${page.url()}\n`);
        
        // Check DOM
        const result = await page.evaluate(() => {
            return {
                url: window.location.href,
                title: document.title,
                hasShippingStep: !!document.querySelector('#shipping'),
                hasWrapper: !!document.querySelector('.shipping-methods-cards-wrapper'),
                hasRegionSelect: !!document.querySelector('select[name="region_id"]'),
                cardCount: document.querySelectorAll('.shipping-card').length
            };
        });
        
        console.log('Page Info:');
        console.log('  URL:', result.url);
        console.log('  Title:', result.title);
        console.log('  Shipping Step:', result.hasShippingStep ? '✅' : '❌');
        console.log('  Cards Wrapper:', result.hasWrapper ? '✅' : '❌');
        console.log('  Region Select:', result.hasRegionSelect ? '✅' : '❌');
        console.log('  Shipping Cards:', result.cardCount);
        
        // If we're on cart page, it's empty
        if (result.url.includes('/cart')) {
            console.log('\n⚠️  Redirected to cart - cart is empty');
            console.log('💡 Add products to cart first, then go to checkout\n');
        }
        
        // Take screenshot
        const dir = './screenshots';
        if (!fs.existsSync(dir)) {
            fs.mkdirSync(dir, { recursive: true });
        }
        
        try {
            await page.screenshot({ 
                path: './screenshots/simple-test.png', 
                fullPage: true,
                timeout: 10000
            });
            console.log('\n📸 Screenshot saved: ./screenshots/simple-test.png');
        } catch (e) {
            console.log('Could not take screenshot');
        }
        
        // Show relevant console logs
        const shippingLogs = logs.filter(l => 
            l.text.includes('[Shipping Cards]') || 
            l.text.includes('shipping-method-cards')
        );
        
        if (shippingLogs.length > 0) {
            console.log('\n📦 Shipping Cards Logs:');
            shippingLogs.forEach(log => {
                console.log(`   ${log.text}`);
            });
        }
        
        const errors = logs.filter(l => l.type === 'error');
        if (errors.length > 0) {
            console.log('\n❌ Console Errors:');
            errors.slice(0, 5).forEach(err => {
                console.log(`   ${err.text.substring(0, 150)}`);
            });
        }
        
        console.log('\n=================================\n');
        
    } catch (error) {
        console.error(`\n❌ Test failed: ${error.message}\n`);
        
    } finally {
        await browser.close();
    }
}

runSimpleTest().catch(console.error);

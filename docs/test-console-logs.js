/**
 * Test with console logging to see what's happening
 */
const { chromium } = require('playwright');
const fs = require('fs');

async function testWithConsole() {
    console.log('🔍 Shipping Cards Test with Console Logs\n');
    
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    
    // Capture console logs
    const consoleLogs = [];
    page.on('console', msg => {
        const text = msg.text();
        consoleLogs.push(text);
        if (text.includes('[Shipping Cards]') || text.includes('shipping')) {
            console.log('📝', text);
        }
    });
    
    try {
        const url = fs.readFileSync('test-checkout-url.txt', 'utf8').trim();
        console.log('📍 URL:', url, '\n');
        
        await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
        
        console.log('\n⏳ Waiting 5 seconds for components to load...\n');
        await page.waitForTimeout(5000);
        
        // Check DOM
        const result = await page.evaluate(() => {
            return {
                wrapper: !!document.querySelector('.shipping-methods-cards-wrapper'),
                shippingStep: !!document.querySelector('#opc-shipping_method'),
                beforeRegion: !!document.querySelector('[data-bind*="before-shipping-method-form"]'),
                hasShippingCards: !!window.shippingMethodCards
            };
        });
        
        console.log('\n📊 DOM Elements:');
        console.log('  Wrapper:', result.wrapper ? '✅' : '❌');
        console.log('  Shipping step:', result.shippingStep ? '✅' : '❌');
        console.log('  Before region:', result.beforeRegion ? '✅' : '❌');
        console.log('  Component loaded:', result.hasShippingCards ? '✅' : '❌');
        
        console.log('\n📝 Relevant Console Logs:');
        const relevant = consoleLogs.filter(log => 
            log.toLowerCase().includes('shipping') || 
            log.toLowerCase().includes('card') ||
            log.includes('Mab_Checkout')
        );
        
        if (relevant.length > 0) {
            relevant.slice(0, 20).forEach(log => console.log('  ', log));
        } else {
            console.log('  (No relevant logs found)');
        }
        
        await page.screenshot({ path: './screenshots/console-test.png', fullPage: true });
        
    } catch (error) {
        console.error('❌ Error:', error.message);
    } finally {
        await browser.close();
    }
}

testWithConsole();

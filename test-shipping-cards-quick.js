/**
 * Quick test to check if shipping cards render
 */
const { chromium } = require('playwright');
const fs = require('fs');

async function quickTest() {
    console.log('🔍 Quick Shipping Cards Test\n');
    
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();
    
    try {
        const url = fs.readFileSync('test-checkout-url.txt', 'utf8').trim();
        console.log('📍 URL:', url);
        
        await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
        await page.waitForTimeout(3000);
        
        // Check for wrapper
        const result = await page.evaluate(() => {
            const wrapper = document.querySelector('.shipping-methods-cards-wrapper');
            const cards = document.querySelectorAll('.shipping-card');
            
            return {
                wrapperExists: !!wrapper,
                wrapperVisible: wrapper ? window.getComputedStyle(wrapper).display !== 'none' : false,
                cardCount: cards.length,
                cardDetails: Array.from(cards).map(card => ({
                    methodCode: card.querySelector('input[type="radio"]')?.value,
                    title: card.querySelector('.shipping-card-title')?.textContent.trim(),
                    visible: window.getComputedStyle(card).display !== 'none'
                }))
            };
        });
        
        console.log('\n📊 RESULTS:');
        console.log('═'.repeat(60));
        console.log('Wrapper exists:', result.wrapperExists ? '✅ YES' : '❌ NO');
        console.log('Wrapper visible:', result.wrapperVisible ? '✅ YES' : '❌ NO');
        console.log('Cards found:', result.cardCount);
        
        if (result.cardCount > 0) {
            console.log('\n📋 Card Details:');
            result.cardDetails.forEach((card, i) => {
                console.log(`  ${i+1}. ${card.title} (${card.methodCode}) - ${card.visible ? 'Visible' : 'Hidden'}`);
            });
        }
        
        await page.screenshot({ path: './screenshots/quick-test-restore.png', fullPage: true });
        console.log('\n📸 Screenshot: ./screenshots/quick-test-restore.png');
        
        console.log('\n' + (result.wrapperExists && result.cardCount > 0 ? '✅ SUCCESS!' : '❌ FAILED'));
        
    } catch (error) {
        console.error('❌ Error:', error.message);
    } finally {
        await browser.close();
    }
}

quickTest();

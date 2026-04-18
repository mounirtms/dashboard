const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  
  // Use the test cart from above
  const checkoutUrl = 'https://dev.technostationery.com/checkout/?cartId=LrIZo8RvtYXqU0acMmJGd2UfxOcb8rOJ';
  
  console.log('🌐 Navigating to checkout...');
  await page.goto(checkoutUrl, { waitUntil: 'networkidle' });
  
  console.log('📄 Page title:', await page.title());
  console.log('📍 Current URL:', page.url());
  
  // Check if redirected to cart (empty)
  if (page.url().includes('/cart/')) {
    console.log('❌ Redirected to cart - quote may be empty');
  }
  
  // Wait for shipping step
  await page.waitForSelector('#shipping', { timeout: 5000 }).catch(() => {
    console.log('⚠️ Shipping step not found');
  });
  
  // Check for region selector
  const regionSelector = await page.$('select[name="region_id"]');
  if (regionSelector) {
    console.log('✅ Region selector found');
    
    // Select Boumerdès
    await regionSelector.selectOption({ value: '893' });
    console.log('✅ Selected Boumerdès (893)');
    
    // Wait for shipping methods to load
    await page.waitForTimeout(2000);
    
    // Check for shipping method cards
    const cards = await page.$$('.shipping-card');
    console.log(`📦 Found ${cards.length} shipping method cards`);
    
    if (cards.length > 0) {
      for (let i = 0; i < cards.length; i++) {
        const card = cards[i];
        const methodCode = await card.getAttribute('data-method-code');
        const methodName = await card.$eval('.method-name', el => el.textContent);
        const price = await card.$eval('.price-amount', el => el.textContent).catch(() => 'Gratuit');
        console.log(`  Card ${i + 1}: ${methodName} - ${price} (${methodCode})`);
      }
      
      // Click first card
      console.log('\n👆 Clicking first shipping card...');
      await cards[0].click();
      await page.waitForTimeout(1000);
      
      // Check if card is selected
      const isSelected = await cards[0].evaluate(el => el.classList.contains('selected'));
      console.log(isSelected ? '✅ Card selected' : '❌ Card not selected');
      
      // Check for Next button
      const nextButton = await page.$('button[data-role="opc-continue"]');
      if (nextButton) {
        const isVisible = await nextButton.isVisible();
        console.log(isVisible ? '✅ Next button visible' : '❌ Next button hidden');
      } else {
        console.log('❌ Next button not found');
      }
    } else {
      console.log('❌ No shipping method cards found');
      
      // Check for error messages
      const errorMsg = await page.$('.shipping-error-message');
      if (errorMsg) {
        const text = await errorMsg.textContent();
        console.log('❌ Error:', text);
      }
    }
  } else {
    console.log('❌ Region selector not found');
  }
  
  // Capture console errors
  page.on('console', msg => {
    if (msg.type() === 'error') {
      console.log('Console Error:', msg.text());
    }
  });
  
  await browser.close();
})();

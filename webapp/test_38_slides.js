const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  
  const errors = [];
  const warnings = [];
  
  page.on('console', msg => {
    if (msg.type() === 'error') errors.push(msg.text());
    else if (msg.type() === 'warning') warnings.push(msg.text());
  });
  
  page.on('pageerror', err => {
    errors.push(`PAGE ERROR: ${err.message}`);
  });
  
  const url = 'https://dashboard.technostationery.com/presentation/test_view.html';
  console.log(`Loading: ${url}`);
  
  try {
    await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
  } catch (e) {
    console.log(`Load note: ${e.message}`);
  }
  
  await page.waitForTimeout(2000);
  
  // Check initial console errors
  if (errors.length > 0) {
    console.log(`\n🚨 INITIAL ERRORS (${errors.length}):`);
    for (const e of errors) console.log(`  • ${e}`);
  } else {
    console.log('\n✅ No JS errors on load');
  }
  
  // Get slide info
  const slideInfo = await page.evaluate(() => {
    const slides = document.querySelectorAll('.slide');
    return {
      count: slides.length,
      ids: Array.from(slides).map(s => s.id),
      showSlideExists: typeof showSlide === 'function',
      initChartsExists: typeof initChartsForSlide === 'function',
      currentVar: typeof current !== 'undefined' ? current : 'undefined'
    };
  });
  
  console.log(`\n📊 Slide info:`);
  console.log(`  Total slides: ${slideInfo.count}`);
  console.log(`  showSlide function: ${slideInfo.showSlideExists ? '✓' : '✗'}`);
  console.log(`  initChartsForSlide function: ${slideInfo.initChartsExists ? '✓' : '✗'}`);
  console.log(`  current variable: ${slideInfo.currentVar}`);
  console.log(`  IDs: ${slideInfo.ids.join(', ')}`);
  
  // Navigate through all slides
  console.log('\n🧭 Testing navigation through all slides...');
  const navResults = [];
  
  for (let i = 0; i < slideInfo.count; i++) {
    const result = await page.evaluate((idx) => {
      try {
        showSlide(idx);
        const slides = document.querySelectorAll('.slide');
        const slide = slides[idx];
        if (!slide) return { idx, error: 'slide element not found' };
        
        const style = window.getComputedStyle(slide);
        return {
          idx,
          id: slide.id,
          contentLen: slide.innerHTML.length,
          display: style.display,
          opacity: style.opacity,
          hasCanvases: slide.querySelectorAll('canvas').length
        };
      } catch(e) {
        return { idx, error: e.toString() };
      }
    }, i);
    
    navResults.push(result);
    
    // Small delay for charts
    if (i < 5 || i > slideInfo.count - 5) {
      await page.waitForTimeout(200);
    } else {
      await page.waitForTimeout(50);
    }
  }
  
  // Report navigation results
  let passCount = 0, failCount = 0;
  const failures = [];
  
  for (const r of navResults) {
    if (r.error) {
      failCount++;
      failures.push(`Slide ${r.idx}: ${r.error}`);
    } else if (r.contentLen < 50) {
      failCount++;
      failures.push(`Slide ${r.idx} (${r.id}): Content too short (${r.contentLen} chars)`);
    } else {
      passCount++;
    }
  }
  
  console.log(`\n📋 Navigation results: ${passCount}/${slideInfo.count} PASS, ${failCount} FAIL`);
  
  if (failures.length > 0) {
    console.log('Failures:');
    for (const f of failures) console.log(`  ✗ ${f}`);
  }
  
  // Show individual slide results
  for (const r of navResults) {
    if (!r.error && r.contentLen >= 50) {
      console.log(`  ✓ [${r.idx}] ${r.id} — content=${r.contentLen}chars, display=${r.display}`);
    }
  }
  
  // Test goNext / goPrev
  console.log('\n🔄 Testing goNext/goPrev...');
  await page.evaluate(() => showSlide(0));
  await page.waitForTimeout(200);
  const startSlide = await page.evaluate(() => current);
  
  await page.evaluate(() => goNext());
  await page.waitForTimeout(200);
  const afterNext = await page.evaluate(() => current);
  
  await page.evaluate(() => goPrev());
  await page.waitForTimeout(200);
  const afterPrev = await page.evaluate(() => current);
  
  console.log(`  Start: ${startSlide}, after goNext: ${afterNext}, after goPrev: ${afterPrev}`);
  const navWorks = startSlide === 0 && afterNext === 1 && afterPrev === 0;
  console.log(`  Navigation: ${navWorks ? '✓ WORKS' : '✗ BROKEN'}`);
  
  // Check canvases after visiting chart slides
  console.log('\n🎨 Checking chart canvases...');
  // Visit all slides once more to init charts
  for (let i = 0; i < slideInfo.count; i++) {
    await page.evaluate((idx) => showSlide(idx), i);
    await page.waitForTimeout(100);
  }
  
  const canvasInfo = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('canvas')).map(c => ({
      id: c.id,
      w: c.width,
      h: c.height
    }));
  });
  
  console.log(`  Total canvas elements: ${canvasInfo.length}`);
  for (const c of canvasInfo) {
    console.log(`    canvas#${c.id}: ${c.w}x${c.h} ${c.w > 0 && c.h > 0 ? '✓' : '✗ zero-size!'}`);
  }
  
  // Final errors check
  const finalErrors = errors.filter(e => !e.includes('favicon') && !e.includes('404'));
  
  // Summary
  console.log('\n' + '='.repeat(50));
  const allGood = finalErrors.length === 0 && failCount === 0 && slideInfo.count === 38 && navWorks;
  console.log(`RESULT: ${allGood ? '✅ ALL TESTS PASS' : '❌ ISSUES FOUND'}`);
  console.log(`  • Slides: ${slideInfo.count}/38`);
  console.log(`  • Nav errors: ${finalErrors.length}`);
  console.log(`  • Slide nav fails: ${failCount}`);
  console.log(`  • goNext/goPrev: ${navWorks ? '✓' : '✗'}`);
  console.log(`  • Canvas elements: ${canvasInfo.length}`);
  console.log('='.repeat(50));
  
  await browser.close();
  process.exit(allGood ? 0 : 1);
})().catch(err => {
  console.error('Fatal error:', err);
  process.exit(1);
});

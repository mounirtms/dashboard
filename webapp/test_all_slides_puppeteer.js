/**
 * TechnoStationery Presentation — Full 38-Slide Puppeteer Test
 * Uses puppeteer with the system Chrome binary
 */
const puppeteer = require('/usr/lib/node_modules/puppeteer');

const URL = 'https://dashboard.technostationery.com/presentation/test_view.html';
const CHROME_PATH = '/home/dashboard/public_html/webapp/chromium-dir/chrome';

async function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

async function main() {
  console.log('='.repeat(60));
  console.log('TechnoStationery — Full 38-Slide Navigation Test');
  console.log('='.repeat(60));
  console.log(`URL: ${URL}`);
  console.log(`Browser: ${CHROME_PATH}`);
  console.log('');

  let browser;
  try {
    browser = await puppeteer.launch({
      executablePath: CHROME_PATH,
      headless: true,
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--disable-gpu',
        '--no-first-run',
        '--disable-extensions',
      ]
    });
  } catch (e) {
    console.error('Failed to launch browser:', e.message);
    process.exit(1);
  }

  const page = await browser.newPage();
  await page.setViewport({ width: 1920, height: 1080 });

  // Collect all console messages and errors
  const consoleErrors = [];
  const consoleWarnings = [];
  const jsErrors = [];

  page.on('console', msg => {
    const type = msg.type();
    const text = msg.text();
    if (type === 'error') consoleErrors.push(text);
    else if (type === 'warning' || type === 'warn') consoleWarnings.push(text);
  });
  page.on('pageerror', err => jsErrors.push(err.message));

  console.log('Loading page...');
  const t0 = Date.now();
  try {
    await page.goto(URL, { waitUntil: 'networkidle0', timeout: 60000 });
  } catch (e) {
    console.warn(`Warning during load: ${e.message}`);
  }
  const loadTime = ((Date.now() - t0) / 1000).toFixed(2);
  console.log(`Page loaded in ${loadTime}s`);
  console.log('');

  // ── STEP 1: Basic JS function availability ──
  console.log('── STEP 1: Checking JS functions ──');
  const jsFunctions = await page.evaluate(() => {
    return {
      showSlide:      typeof showSlide === 'function',
      goNext:         typeof goNext === 'function',
      goPrev:         typeof goPrev === 'function',
      initChartsForSlide: typeof initChartsForSlide === 'function',
      totalSlides:    typeof slides !== 'undefined' ? slides.length : -1,
      currentSlide:   typeof current !== 'undefined' ? current : -1,
    };
  });
  console.log('  showSlide():', jsFunctions.showSlide ? '✅' : '❌ MISSING');
  console.log('  goNext():', jsFunctions.goNext ? '✅' : '❌ MISSING');
  console.log('  goPrev():', jsFunctions.goPrev ? '✅' : '❌ MISSING');
  console.log('  initChartsForSlide():', jsFunctions.initChartsForSlide ? '✅' : '❌ MISSING');
  console.log(`  slides[] count: ${jsFunctions.totalSlides}`);
  console.log(`  current index: ${jsFunctions.currentSlide}`);
  console.log('');

  if (!jsFunctions.showSlide) {
    console.error('❌ CRITICAL: showSlide() not defined — aborting tests');
    await browser.close();
    process.exit(1);
  }

  // ── STEP 2: Check all slide DOM elements ──
  console.log('── STEP 2: Checking all slide DOM elements ──');
  const slideInfo = await page.evaluate(() => {
    const allSlides = document.querySelectorAll('.slide');
    const ids = Array.from(allSlides).map(s => s.id);
    // Check for expected IDs s1..s38 (s17b is the extra one)
    const expected = [];
    for (let i = 1; i <= 38; i++) expected.push(`s${i}`);
    expected.splice(17, 0, 's17b'); // s17b after s17
    const found = new Set(ids);
    const missing = expected.filter(id => !found.has(id));
    const extra = ids.filter(id => !expected.includes(id));
    return { count: allSlides.length, ids, missing, extra };
  });
  console.log(`  Total .slide elements: ${slideInfo.count} ${slideInfo.count >= 38 ? '✅' : '❌ (expected 38+)'}`);
  if (slideInfo.missing.length > 0) console.log(`  ❌ Missing IDs: ${slideInfo.missing.join(', ')}`);
  if (slideInfo.extra.length > 0) console.log(`  ℹ Extra IDs: ${slideInfo.extra.join(', ')}`);
  console.log('');

  // ── STEP 3: Navigate through all slides ──
  console.log(`── STEP 3: Navigating all ${jsFunctions.totalSlides} slides ──`);
  const slideResults = [];
  const total = jsFunctions.totalSlides;

  for (let i = 0; i < total; i++) {
    const result = await page.evaluate((idx) => {
      try {
        showSlide(idx);
        const slide = slides[idx];
        if (!slide) return { ok: false, error: `slides[${idx}] is undefined` };
        const id = slide.id || '(no id)';
        const display = window.getComputedStyle(slide).display;
        const visible = display !== 'none';
        const hasContent = slide.innerHTML.trim().length > 0;
        const canvases = slide.querySelectorAll('canvas').length;
        const title = (slide.querySelector('h1,h2,h3,.slide-title') || {}).textContent || '';
        return { ok: true, id, visible, hasContent, canvases, display, title: title.trim().slice(0, 60) };
      } catch (e) {
        return { ok: false, error: e.message };
      }
    }, i);

    const icon = result.ok && result.visible && result.hasContent ? '✅' : '❌';
    const canvasNote = result.canvases > 0 ? ` [${result.canvases} canvas]` : '';
    const titleNote = result.title ? ` — "${result.title}"` : '';
    console.log(`  [${String(i + 1).padStart(2)}] ${icon} ${result.id || 'ERR'}${canvasNote}${titleNote}`);
    if (!result.ok) console.log(`       ERROR: ${result.error}`);
    if (result.ok && !result.visible) console.log(`       ⚠ display: ${result.display}`);
    if (result.ok && !result.hasContent) console.log(`       ⚠ empty content`);

    slideResults.push(result);
    await sleep(50); // small delay between slides
  }
  console.log('');

  // ── STEP 4: Test goNext / goPrev navigation ──
  console.log('── STEP 4: Testing goNext() / goPrev() navigation ──');
  const navTest = await page.evaluate(() => {
    showSlide(0);
    const startIdx = current;
    goNext();
    const afterNext = current;
    goNext();
    const afterNext2 = current;
    goPrev();
    const afterPrev = current;
    return { startIdx, afterNext, afterNext2, afterPrev };
  });
  console.log(`  Start: slide ${navTest.startIdx} → goNext → ${navTest.afterNext} → goNext → ${navTest.afterNext2} → goPrev → ${navTest.afterPrev}`);
  const navOk = navTest.afterNext === 1 && navTest.afterNext2 === 2 && navTest.afterPrev === 1;
  console.log(`  Navigation logic: ${navOk ? '✅ CORRECT' : '❌ WRONG'}`);
  console.log('');

  // ── STEP 5: Test TOC links ──
  console.log('── STEP 5: Checking TOC links (slide 1) ──');
  await page.evaluate(() => showSlide(0));
  const tocLinks = await page.evaluate(() => {
    const links = document.querySelectorAll('#s1 a[onclick*="showSlide"]');
    return Array.from(links).map(a => {
      const match = a.getAttribute('onclick').match(/showSlide\((\d+)\)/);
      return {
        text: a.textContent.trim().slice(0, 50),
        target: match ? parseInt(match[1]) : -1,
        onclick: a.getAttribute('onclick')
      };
    });
  });
  if (tocLinks.length === 0) {
    console.log('  ℹ No TOC links found in slide 1 (may be on a different slide)');
  } else {
    tocLinks.forEach((l, i) => {
      const valid = l.target >= 0 && l.target < total;
      console.log(`  TOC[${i + 1}] → showSlide(${l.target}) ${valid ? '✅' : '❌'} — "${l.text}"`);
    });
  }
  console.log('');

  // ── STEP 6: Check charts on chart slides ──
  console.log('── STEP 6: Chart canvas detection ──');
  const chartSlides = slideResults.filter(r => r.ok && r.canvases > 0);
  if (chartSlides.length === 0) {
    console.log('  ℹ No canvas elements found — charts may render lazily');
  } else {
    console.log(`  Found ${chartSlides.length} slides with canvas elements:`);
    chartSlides.forEach(r => console.log(`    ${r.id}: ${r.canvases} canvas(es)`));
  }
  console.log('');

  // ── STEP 7: Check Algeria map slide (s18) ──
  console.log('── STEP 7: Algeria Map slide (s18) ──');
  const mapSlideIdx = slideResults.findIndex(r => r.id === 's18');
  if (mapSlideIdx >= 0) {
    await page.evaluate((idx) => showSlide(idx), mapSlideIdx);
    await sleep(200);
    const mapCheck = await page.evaluate(() => {
      const slide = document.getElementById('s18');
      if (!slide) return { found: false };
      const svgEl = slide.querySelector('svg');
      const paths = svgEl ? svgEl.querySelectorAll('path').length : 0;
      const mapFunc = typeof colorizeAlgeriaMap === 'function';
      return { found: true, hasSvg: !!svgEl, pathCount: paths, hasMapFunc: mapFunc };
    });
    console.log(`  s18 found: ${mapCheck.found ? '✅' : '❌'}`);
    console.log(`  SVG present: ${mapCheck.hasSvg ? '✅' : '❌'} (${mapCheck.pathCount} paths)`);
    console.log(`  colorizeAlgeriaMap(): ${mapCheck.hasMapFunc ? '✅' : '❌'}`);
  } else {
    console.log('  ℹ s18 not in slide list');
  }
  console.log('');

  // ── STEP 8: JS errors summary ──
  console.log('── STEP 8: Console errors & JS errors ──');
  if (jsErrors.length === 0 && consoleErrors.length === 0) {
    console.log('  ✅ Zero JS errors');
  } else {
    if (jsErrors.length > 0) {
      console.log(`  ❌ ${jsErrors.length} page error(s):`);
      jsErrors.forEach(e => console.log(`    • ${e.slice(0, 200)}`));
    }
    if (consoleErrors.length > 0) {
      console.log(`  ❌ ${consoleErrors.length} console error(s):`);
      consoleErrors.forEach(e => console.log(`    • ${e.slice(0, 200)}`));
    }
  }
  if (consoleWarnings.length > 0) {
    console.log(`  ⚠ ${consoleWarnings.length} warning(s):`);
    consoleWarnings.slice(0, 5).forEach(w => console.log(`    • ${w.slice(0, 150)}`));
  }
  console.log('');

  // ── FINAL SUMMARY ──
  console.log('='.repeat(60));
  const failedSlides = slideResults.filter(r => !r.ok || !r.visible || !r.hasContent);
  const passedSlides = slideResults.filter(r => r.ok && r.visible && r.hasContent);
  console.log(`RESULTS: ${passedSlides.length}/${total} slides passed`);
  if (failedSlides.length > 0) {
    console.log(`FAILED slides (${failedSlides.length}):`);
    failedSlides.forEach(r => {
      const reason = !r.ok ? `error: ${r.error}` : !r.visible ? `not visible (${r.display})` : 'no content';
      console.log(`  ❌ ${r.id}: ${reason}`);
    });
  }
  const allOk = failedSlides.length === 0 && navOk && jsErrors.length === 0 && consoleErrors.length === 0;
  console.log(`OVERALL: ${allOk ? '✅ ALL TESTS PASSED' : '❌ ISSUES FOUND — see above'}`);
  console.log('='.repeat(60));

  await browser.close();
  process.exit(allOk ? 0 : 1);
}

main().catch(e => {
  console.error('Fatal:', e);
  process.exit(1);
});

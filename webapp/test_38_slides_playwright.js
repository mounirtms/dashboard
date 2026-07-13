/**
 * TechnoStationery Executive Audit — Full 38-Slide Playwright Test
 * Tests ALL slides for: JS functions, navigation, content, charts, TOC links
 */
process.env.PLAYWRIGHT_BROWSERS_PATH = '/home/dashboard/public_html/webapp/pw-browsers';
const { chromium } = require('/home/dashboard/public_html/node_modules/playwright');

const URL = 'https://dashboard.technostationery.com/presentation/test_view.html';

async function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

async function main() {
  console.log('='.repeat(65));
  console.log('  TechnoStationery — Full 38-Slide Playwright Navigation Test');
  console.log('='.repeat(65));
  console.log(`  URL: ${URL}`);
  console.log('');

  const browser = await chromium.launch({
    headless: true,
    args: ['--no-sandbox','--disable-setuid-sandbox','--disable-dev-shm-usage',
           '--disable-gpu','--no-first-run','--disable-extensions'],
  });

  const context = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
  const page = await context.newPage();

  // Collect errors
  const consoleErrors = [];
  const jsErrors = [];
  page.on('console', msg => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
  });
  page.on('pageerror', err => jsErrors.push(err.message));

  console.log('  Loading page...');
  const t0 = Date.now();
  try {
    await page.goto(URL, { waitUntil: 'networkidle', timeout: 60000 });
  } catch(e) {
    console.warn(`  Warning during load: ${e.message.slice(0,100)}`);
  }
  const loadTime = ((Date.now() - t0) / 1000).toFixed(2);
  const pageTitle = await page.title();
  console.log(`  Load time: ${loadTime}s`);
  console.log(`  Page title: ${pageTitle}`);
  console.log('');

  // ── STEP 1: JS Functions Check ──
  console.log('── Step 1: JS Functions ──────────────────────────────────────');
  const jsFns = await page.evaluate(() => ({
    showSlide:          typeof showSlide === 'function',
    goNext:             typeof goNext === 'function',
    goPrev:             typeof goPrev === 'function',
    initChartsForSlide: typeof initChartsForSlide === 'function',
    slideCount:         typeof slides !== 'undefined' ? slides.length : -1,
    current:            typeof current !== 'undefined' ? current : -1,
  }));
  const fnStatus = (ok) => ok ? '✅' : '❌ MISSING';
  console.log(`  showSlide():          ${fnStatus(jsFns.showSlide)}`);
  console.log(`  goNext():             ${fnStatus(jsFns.goNext)}`);
  console.log(`  goPrev():             ${fnStatus(jsFns.goPrev)}`);
  console.log(`  initChartsForSlide(): ${fnStatus(jsFns.initChartsForSlide)}`);
  console.log(`  slides[] count:       ${jsFns.slideCount} ${jsFns.slideCount >= 38 ? '✅' : '❌'}`);
  console.log(`  current index:        ${jsFns.current}`);
  console.log('');

  if (!jsFns.showSlide) {
    console.error('❌ CRITICAL: showSlide() not defined — aborting');
    await browser.close();
    process.exit(1);
  }

  const total = jsFns.slideCount;

  // ── STEP 2: DOM Slide Elements ──
  console.log('── Step 2: Slide DOM Elements ────────────────────────────────');
  const domInfo = await page.evaluate(() => {
    const allSlides = document.querySelectorAll('.slide');
    const ids = Array.from(allSlides).map(s => s.id);
    return { count: allSlides.length, ids };
  });
  console.log(`  .slide elements found: ${domInfo.count} ${domInfo.count >= 38 ? '✅' : '❌ expected 38+'}`);
  // Check for duplicates
  const seen = new Set(); const dups = [];
  domInfo.ids.forEach(id => { if (seen.has(id)) dups.push(id); else seen.add(id); });
  if (dups.length) console.log(`  ❌ Duplicate IDs: ${dups.join(', ')}`);
  else console.log(`  No duplicate IDs ✅`);
  console.log('');

  // ── STEP 3: Navigate All Slides ──
  console.log(`── Step 3: Navigate All ${total} Slides ──────────────────────────`);
  const results = [];
  const issues = [];

  for (let i = 0; i < total; i++) {
    const r = await page.evaluate((idx) => {
      try {
        showSlide(idx);
        const slide = slides[idx];
        if (!slide) return { ok: false, error: `slides[${idx}] undefined` };
        const id = slide.id || '?';
        const style = window.getComputedStyle(slide);
        const visible = style.display !== 'none' && style.visibility !== 'hidden';
        const content = slide.innerHTML.trim();
        const hasContent = content.length > 50;
        const canvases = slide.querySelectorAll('canvas').length;
        const headings = slide.querySelectorAll('h1,h2,h3,.slide-title,.section-title');
        const title = headings.length > 0 ? headings[0].textContent.trim().replace(/\s+/g,' ') : '';
        // Check for placeholder/broken content
        const hasLoremIpsum = content.includes('Lorem ipsum');
        const hasTODO = content.includes('TODO') || content.includes('[PLACEHOLDER]');
        return {
          ok: true, id, visible, hasContent, canvases,
          title: title.slice(0,55), display: style.display,
          hasLoremIpsum, hasTODO, contentLen: content.length,
        };
      } catch(e) {
        return { ok: false, error: e.message };
      }
    }, i);

    const num = String(i+1).padStart(2);
    const icon = (r.ok && r.visible && r.hasContent) ? '✅' : '❌';
    const canvas = r.canvases > 0 ? ` [${r.canvases}📊]` : '';
    const titleStr = r.title ? ` "${r.title}"` : '';
    console.log(`  [${num}] ${icon} ${(r.id||'ERR').padEnd(6)}${canvas}${titleStr}`);

    if (!r.ok) {
      console.log(`       ❌ Error: ${r.error}`);
      issues.push({ slide: i+1, id: r.id||'?', problem: `JS error: ${r.error}` });
    } else {
      if (!r.visible) {
        console.log(`       ❌ Not visible (display: ${r.display})`);
        issues.push({ slide: i+1, id: r.id, problem: `not visible (display:${r.display})` });
      }
      if (!r.hasContent) {
        console.log(`       ❌ Empty/minimal content (${r.contentLen} chars)`);
        issues.push({ slide: i+1, id: r.id, problem: `empty content (${r.contentLen} chars)` });
      }
      if (r.hasLoremIpsum) {
        console.log(`       ⚠  Contains placeholder "Lorem ipsum" text`);
        issues.push({ slide: i+1, id: r.id, problem: 'lorem ipsum placeholder text' });
      }
      if (r.hasTODO) {
        console.log(`       ⚠  Contains TODO/PLACEHOLDER marker`);
        issues.push({ slide: i+1, id: r.id, problem: 'TODO/PLACEHOLDER marker' });
      }
    }
    results.push(r);
    await sleep(30);
  }
  console.log('');

  // ── STEP 4: Navigation (goNext / goPrev) ──
  console.log('── Step 4: goNext() / goPrev() Navigation ────────────────────');
  const navTest = await page.evaluate(() => {
    showSlide(0);
    const s0 = current;
    goNext(); const s1 = current;
    goNext(); const s2 = current;
    goPrev(); const s3 = current;
    showSlide(0); // reset
    // Test boundary: can't go before 0
    goPrev(); const sBoundaryMin = current;
    showSlide(slides.length - 1);
    goNext(); const sBoundaryMax = current; // should stay at last
    return { s0, s1, s2, s3, sBoundaryMin, sBoundaryMax, total: slides.length };
  });
  const navOk = navTest.s1 === 1 && navTest.s2 === 2 && navTest.s3 === 1;
  console.log(`  start→next→next→prev: ${navTest.s0}→${navTest.s1}→${navTest.s2}→${navTest.s3} ${navOk ? '✅' : '❌'}`);
  console.log(`  min boundary (goPrev at 0): ${navTest.sBoundaryMin} ${navTest.sBoundaryMin === 0 ? '✅ stays at 0' : '⚠ moved to '+navTest.sBoundaryMin}`);
  console.log(`  max boundary (goNext at ${navTest.total-1}): ${navTest.sBoundaryMax} ${navTest.sBoundaryMax === navTest.total-1 ? '✅ stays at end' : '⚠ moved to '+navTest.sBoundaryMax}`);
  if (!navOk) issues.push({ slide: 'nav', id: 'nav', problem: 'goNext/goPrev logic wrong' });
  console.log('');

  // ── STEP 5: TOC Links ──
  console.log('── Step 5: TOC Links ─────────────────────────────────────────');
  await page.evaluate(() => showSlide(0));
  const tocData = await page.evaluate((total) => {
    // Look for TOC links across slide 1 (index 0) and slide 2 (index 1, may have TOC)
    const tocSlides = [document.getElementById('s1'), document.getElementById('s2')].filter(Boolean);
    const allLinks = [];
    for (const s of tocSlides) {
      const links = s.querySelectorAll('a[onclick*="showSlide"]');
      links.forEach(a => {
        const m = a.getAttribute('onclick').match(/showSlide\((\d+)\)/);
        allLinks.push({
          text: a.textContent.trim().replace(/\s+/g,' ').slice(0,45),
          target: m ? parseInt(m[1]) : -1,
          valid: m ? (parseInt(m[1]) >= 0 && parseInt(m[1]) < total) : false,
        });
      });
    }
    return allLinks;
  }, total);

  if (tocData.length === 0) {
    console.log('  ℹ No TOC onclick links found in s1/s2');
  } else {
    let tocIssues = 0;
    tocData.forEach((l,i) => {
      const icon = l.valid ? '✅' : '❌';
      console.log(`  TOC[${String(i+1).padStart(2)}] → showSlide(${String(l.target).padStart(2)}) ${icon} "${l.text}"`);
      if (!l.valid) { tocIssues++; issues.push({ slide:'TOC', id:'toc', problem:`link "${l.text}" → invalid index ${l.target}` }); }
    });
    console.log(`  ${tocData.length} TOC links, ${tocIssues} invalid ${tocIssues===0?'✅':'❌'}`);
  }
  console.log('');

  // ── STEP 6: Specific Slide Checks ──
  console.log('── Step 6: Specific Slide Verification ───────────────────────');

  // s18 Algeria map
  const mapIdx = results.findIndex(r => r.id === 's18');
  if (mapIdx >= 0) {
    await page.evaluate((idx) => showSlide(idx), mapIdx);
    await sleep(500);
    const mapCheck = await page.evaluate(() => {
      const s = document.getElementById('s18');
      const svg = s ? s.querySelector('svg') : null;
      return {
        found: !!s,
        hasSvg: !!svg,
        pathCount: svg ? svg.querySelectorAll('path').length : 0,
        colorizeExists: typeof colorizeAlgeriaMap === 'function',
      };
    });
    const mapOk = mapCheck.found && mapCheck.hasSvg && mapCheck.pathCount > 40;
    console.log(`  s18 Algeria Map: SVG=${mapCheck.hasSvg} paths=${mapCheck.pathCount} colorize=${mapCheck.colorizeExists} ${mapOk?'✅':'❌'}`);
    if (!mapOk) issues.push({ slide:18, id:'s18', problem:`Algeria map: svg=${mapCheck.hasSvg}, paths=${mapCheck.pathCount}` });
  }

  // s5 commits slide
  const s5idx = results.findIndex(r => r.id === 's5');
  if (s5idx >= 0) {
    await page.evaluate((idx) => showSlide(idx), s5idx);
    const s5Check = await page.evaluate(() => {
      const s = document.getElementById('s5');
      const text = s ? s.textContent : '';
      return {
        found: !!s,
        hasTechnoData: text.includes('techno') || text.includes('Techno') || text.includes('TECHNO'),
        charCount: text.trim().length,
      };
    });
    console.log(`  s5 Commits slide: found=${s5Check.found} hasData=${s5Check.hasTechnoData} len=${s5Check.charCount} ${s5Check.found?'✅':'❌'}`);
  }

  // s35 — the large Thank You/KPI slide
  const s35idx = results.findIndex(r => r.id === 's35');
  if (s35idx >= 0) {
    await page.evaluate((idx) => showSlide(idx), s35idx);
    const s35Check = await page.evaluate(() => {
      const s = document.getElementById('s35');
      return { found: !!s, len: s ? s.innerHTML.length : 0 };
    });
    const s35Ok = s35Check.found && s35Check.len > 5000;
    console.log(`  s35 KPI slide: found=${s35Check.found} contentLen=${s35Check.len} ${s35Ok?'✅':'❌ may be truncated'}`);
    if (!s35Ok) issues.push({ slide:35, id:'s35', problem:`s35 may be truncated: ${s35Check.len} chars` });
  }

  // Chart slides: try to init charts and see if they render
  console.log('');
  console.log('── Step 7: Chart Initialization ──────────────────────────────');
  const chartSlideIds = ['s4','s6','s8','s10','s12','s14','s15','s17','s17b','s19','s20','s21','s22','s23','s24','s25','s26'];
  let chartOk = 0, chartFail = 0;
  for (const sid of chartSlideIds) {
    const idx = results.findIndex(r => r.id === sid);
    if (idx < 0) { console.log(`  ${sid}: ℹ not found in slides[]`); continue; }
    await page.evaluate((i) => { showSlide(i); }, idx);
    await sleep(300);
    const check = await page.evaluate((sid) => {
      try {
        if (typeof initChartsForSlide === 'function') initChartsForSlide(sid);
        const s = document.getElementById(sid);
        const canvases = s ? s.querySelectorAll('canvas') : [];
        let rendered = 0;
        canvases.forEach(c => { if (c.width > 0 && c.height > 0) rendered++; });
        return { canvasCount: canvases.length, rendered, error: null };
      } catch(e) {
        return { canvasCount: 0, rendered: 0, error: e.message };
      }
    }, sid);
    if (check.error) {
      console.log(`  ${sid}: ❌ Chart init error: ${check.error.slice(0,80)}`);
      chartFail++;
      issues.push({ slide: sid, id: sid, problem: `chart init error: ${check.error.slice(0,80)}` });
    } else {
      const ok = check.canvasCount > 0;
      console.log(`  ${sid}: ${ok?'✅':'⚠'} ${check.canvasCount} canvas, ${check.rendered} rendered`);
      if (ok) chartOk++; else chartFail++;
    }
  }
  console.log(`  Charts: ${chartOk} ok, ${chartFail} with issues`);
  console.log('');

  // ── STEP 8: Console & JS Errors ──
  console.log('── Step 8: Console & JS Errors ───────────────────────────────');
  if (jsErrors.length === 0 && consoleErrors.length === 0) {
    console.log('  ✅ Zero JS errors throughout test');
  } else {
    if (jsErrors.length > 0) {
      console.log(`  ❌ ${jsErrors.length} page JS error(s):`);
      jsErrors.forEach(e => console.log(`    • ${e.slice(0,200)}`));
    }
    if (consoleErrors.length > 0) {
      console.log(`  ❌ ${consoleErrors.length} console error(s):`);
      consoleErrors.slice(0,10).forEach(e => console.log(`    • ${e.slice(0,150)}`));
    }
  }
  console.log('');

  // ── FINAL SUMMARY ──
  console.log('='.repeat(65));
  const passed = results.filter(r => r.ok && r.visible && r.hasContent).length;
  const failed = results.filter(r => !r.ok || !r.visible || !r.hasContent).length;
  console.log(`  Slides: ${passed}/${total} passed | ${failed} failed`);
  console.log(`  JS errors: ${jsErrors.length + consoleErrors.length}`);
  console.log(`  Navigation: ${navOk ? 'OK' : 'BROKEN'}`);
  console.log(`  TOC: ${tocData.length} links checked`);
  console.log('');

  if (issues.length > 0) {
    console.log(`  ❌ ISSUES FOUND (${issues.length} total):`);
    issues.forEach(iss => console.log(`    • [${iss.id}] ${iss.problem}`));
  } else {
    console.log('  ✅ ALL TESTS PASSED — No issues found');
  }
  console.log('='.repeat(65));

  await browser.close();

  // Return issue list for fix script
  if (issues.length > 0) {
    process.exitCode = 1;
  }
  return issues;
}

main().catch(e => {
  console.error('\n❌ Fatal test error:', e.message);
  process.exit(1);
});

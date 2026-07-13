#!/usr/bin/env python3
"""
test_all_slides.py — Playwright test for all 38 slides
Uses subprocess to run playwright via Node.js
"""
import subprocess
import json
import sys
import os

# Write the playwright test script
JS_TEST = r"""
const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  
  const errors = [];
  const warnings = [];
  const logs = [];
  
  page.on('console', msg => {
    const text = msg.text();
    if (msg.type() === 'error') errors.push(text);
    else if (msg.type() === 'warning') warnings.push(text);
    else logs.push(`[${msg.type()}] ${text}`);
  });
  
  page.on('pageerror', err => {
    errors.push(`PAGE ERROR: ${err.message}`);
  });
  
  const url = 'https://dashboard.technostationery.com/presentation/test_view.html';
  console.log(`Loading: ${url}`);
  
  try {
    await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
  } catch (e) {
    console.log(`Load warning: ${e.message}`);
  }
  
  await page.waitForTimeout(2000);
  
  // Get slide count
  const slideCount = await page.evaluate(() => {
    const slides = document.querySelectorAll('.slide');
    return slides.length;
  });
  console.log(`Total slides found: ${slideCount}`);
  
  // Get slide IDs
  const slideIds = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('.slide')).map(s => s.id);
  });
  console.log(`Slide IDs: ${slideIds.join(', ')}`);
  
  // Test navigation through all slides
  const navResults = [];
  let navErrors = [];
  
  for (let i = 0; i < slideCount; i++) {
    // Call showSlide(i)
    const result = await page.evaluate((idx) => {
      try {
        showSlide(idx);
        const slides = document.querySelectorAll('.slide');
        const activeSlide = slides[idx];
        const isVisible = activeSlide && (
          getComputedStyle(activeSlide).display !== 'none' &&
          getComputedStyle(activeSlide).opacity !== '0' &&
          activeSlide.style.display !== 'none'
        );
        return {
          idx: idx,
          slideId: activeSlide ? activeSlide.id : 'missing',
          hasContent: activeSlide ? activeSlide.innerHTML.length > 50 : false,
          isVisible: isVisible,
          display: activeSlide ? getComputedStyle(activeSlide).display : 'N/A',
          opacity: activeSlide ? getComputedStyle(activeSlide).opacity : 'N/A'
        };
      } catch(e) {
        return { idx: idx, error: e.message };
      }
    }, i);
    
    navResults.push(result);
    if (result.error) {
      navErrors.push(`Slide ${i}: ${result.error}`);
    }
    
    // Wait a bit for charts to init
    await page.waitForTimeout(100);
  }
  
  // Check canvases rendered
  const canvasInfo = await page.evaluate(() => {
    const canvases = document.querySelectorAll('canvas');
    return Array.from(canvases).map(c => ({
      id: c.id,
      width: c.width,
      height: c.height,
      hasContext: !!c.getContext('2d')
    }));
  });
  
  // Summary
  console.log('\n=== NAVIGATION TEST RESULTS ===');
  let passCount = 0;
  let failCount = 0;
  
  for (const r of navResults) {
    if (r.error) {
      console.log(`  ✗ Slide ${r.idx}: ERROR - ${r.error}`);
      failCount++;
    } else if (!r.hasContent) {
      console.log(`  ✗ Slide ${r.idx} (${r.slideId}): No content (empty)`);
      failCount++;
    } else {
      console.log(`  ✓ Slide ${r.idx} (${r.slideId}): OK - display=${r.display}, opacity=${r.opacity}`);
      passCount++;
    }
  }
  
  console.log(`\n  PASS: ${passCount}/${slideCount}, FAIL: ${failCount}/${slideCount}`);
  
  console.log('\n=== CANVAS ELEMENTS ===');
  console.log(`  Total canvases: ${canvasInfo.length}`);
  for (const c of canvasInfo) {
    console.log(`  canvas#${c.id}: ${c.width}x${c.height}`);
  }
  
  console.log('\n=== CONSOLE ERRORS ===');
  if (errors.length === 0) {
    console.log('  No errors! ✓');
  } else {
    for (const e of errors) {
      console.log(`  ✗ ${e}`);
    }
  }
  
  if (warnings.length > 0) {
    console.log('\n=== CONSOLE WARNINGS ===');
    for (const w of warnings.slice(0, 5)) {
      console.log(`  ⚠ ${w}`);
    }
  }
  
  // Final verdict
  const success = errors.length === 0 && failCount === 0 && slideCount === 38;
  console.log(`\n=== FINAL VERDICT: ${success ? '✓ ALL SLIDES PASS' : '✗ ISSUES FOUND'} ===`);
  console.log(`  Slides: ${slideCount}/38, Errors: ${errors.length}, Nav fails: ${failCount}`);
  
  await browser.close();
  process.exit(success ? 0 : 1);
})();
"""

def run_playwright_test():
    # Write JS test to temp file
    js_file = '/tmp/test_slides_playwright.js'
    with open(js_file, 'w') as f:
        f.write(JS_TEST)
    
    print("Running Playwright navigation test...")
    print("This tests all 38 slides by calling showSlide(i) for each...")
    print()
    
    result = subprocess.run(
        ['node', js_file],
        stdout=subprocess.PIPE, stderr=subprocess.PIPE,
        timeout=120,
        cwd='/home/dashboard/public_html'
    )
    
    stdout = result.stdout.decode('utf-8', errors='replace')
    stderr = result.stderr.decode('utf-8', errors='replace')
    
    print(stdout)
    if stderr:
        print("STDERR:", stderr[:500])
    
    return result.returncode == 0


if __name__ == '__main__':
    # Check if playwright is installed
    check = subprocess.run(['node', '-e', "require('playwright'); console.log('OK')"],
                          stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    if check.returncode != 0:
        print("Installing playwright...")
        subprocess.run(['npm', 'install', 'playwright', '--prefix', '/tmp/pw'],
                      stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        os.environ['NODE_PATH'] = '/tmp/pw/node_modules'
    
    success = run_playwright_test()
    sys.exit(0 if success else 1)

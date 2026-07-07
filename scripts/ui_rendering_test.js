#!/usr/bin/env node
/**
 * UI Rendering and Loading Screen Test for Akeneo PIM
 * Tests UI rendering with and without custom CSS
 */

const { chromium } = require('playwright');

const BASE_URL = 'https://pim.technostationery.com';
const USERNAME = 'admin';
const PASSWORD = 'PimAdmin2026!';

async function testUIRendering() {
    console.log('\n=== UI RENDERING AND LOADING SCREEN TEST ===\n');
    
    // Test 1: With custom CSS (default)
    console.log('TEST 1: UI Rendering WITH custom CSS');
    await testWithCSS(true);
    
    // Test 2: Without custom CSS (disabled)
    console.log('\nTEST 2: UI Rendering WITHOUT custom CSS');
    await testWithCSS(false);
    
    console.log('\n=== TEST COMPLETE ===\n');
}

async function testWithCSS(useCustomCSS) {
    const browser = await chromium.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    
    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
    });
    
    const page = await context.newPage();
    
    // Capture console messages
    const consoleLogs = [];
    page.on('console', msg => consoleLogs.push(`${msg.type()}: ${msg.text()}`));
    
    // Capture page errors
    const pageErrors = [];
    page.on('pageerror', err => pageErrors.push(`ERROR: ${err.message}`));
    
    try {
        // Navigate to login page
        console.log('  Loading login page...');
        const response = await page.goto(`${BASE_URL}/user/login`, { 
            waitUntil: 'networkidle', 
            timeout: 30000 
        });
        console.log(`  Status: ${response.status()}`);
        
        // Wait for initial load
        await page.waitForTimeout(2000);
        
        // Check if custom CSS is loaded
        const cssLoaded = await page.evaluate(() => {
            const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'));
            return links.some(link => link.href.includes('pim.css'));
        });
        console.log(`  Custom CSS loaded: ${cssLoaded}`);
        
        // If we're testing without CSS, try to disable it
        if (!useCustomCSS) {
            await page.addStyleTag({ content: 'link[href*="pim.css"] { display: none !important; }' });
            // Alternative: remove the CSS link
            await page.evaluate(() => {
                const links = document.querySelectorAll('link[rel="stylesheet"]');
                links.forEach(link => {
                    if (link.href.includes('pim.css')) {
                        link.disabled = true;
                    }
                });
            });
        }
        
        // Check body classes and styles
        const bodyInfo = await page.evaluate(() => {
            const body = document.body;
            const computed = window.getComputedStyle(body);
            return {
                className: body.className,
                backgroundColor: computed.backgroundColor,
                minHeight: computed.minHeight,
                visibility: computed.visibility,
                opacity: computed.opacity
            };
        });
        console.log(`  Body class: ${bodyInfo.className}`);
        console.log(`  Background: ${bodyInfo.backgroundColor}`);
        console.log(`  Min height: ${bodyInfo.minHeight}`);
        console.log(`  Visibility: ${bodyInfo.visibility}`);
        console.log(`  Opacity: ${bodyInfo.opacity}`);
        
        // Check for loading indicators/spinners
        const loadingElements = await page.evaluate(() => {
            const selectors = [
                '.loading',
                '.spinner',
                '.ajax-loading',
                '[class*="load"]',
                '[class*="spinner"]'
            ];
            const found = [];
            selectors.forEach(selector => {
                const elements = document.querySelectorAll(selector);
                elements.forEach(el => {
                    if (el.offsetParent !== null) { // visible element
                        found.push({
                            selector: selector,
                            className: el.className,
                            visible: true
                        });
                    }
                });
            });
            return found;
        });
        console.log(`  Loading elements found: ${loadingElements.length}`);
        loadingElements.forEach(el => {
            console.log(`    - ${el.selector}: ${el.className}`);
        });
        
        // Check for main content visibility
        const mainContent = await page.evaluate(() => {
            // Common selectors for main content in PIM/Akeneo
            const selectors = [
                '#main-content',
                '.main-content',
                '#content',
                '.content',
                'main',
                '.container',
                '#pim-container'
            ];
            
            for (const selector of selectors) {
                const el = document.querySelector(selector);
                if (el && el.offsetParent !== null) {
                    const computed = window.getComputedStyle(el);
                    return {
                        found: true,
                        selector: selector,
                        visible: computed.visibility !== 'hidden',
                        opacity: parseFloat(computed.opacity),
                        display: computed.display
                    };
                }
            }
            return { found: false };
        });
        
        if (mainContent.found) {
            console.log(`  Main content found: ${mainContent.selector}`);
            console.log(`    Visible: ${mainContent.visible}`);
            console.log(`    Opacity: ${mainContent.opacity}`);
            console.log(`    Display: ${mainContent.display}`);
        } else {
            console.log('  Main content: NOT FOUND with common selectors');
        }
        
        // Take screenshot
        const screenshotPath = `/tmp/ui_test_${useCustomCSS ? 'with' : 'without'}_css_${Date.now()}.png`;
        await page.screenshot({ path: screenshotPath, fullPage: true });
        console.log(`  Screenshot saved: ${screenshotPath}`);
        
        // Try to login if we're testing UI rendering beyond login
        if (useCustomCSS) { // Only try login with CSS enabled for now
            console.log('  Attempting login...');
            
            // Get CSRF token
            const csrfToken = await page.evaluate(() => {
                const input = document.querySelector('input[name="_csrf_token"]');
                return input ? input.value : null;
            });
            
            if (csrfToken) {
                await page.fill('input[name="_username"]', USERNAME);
                await page.fill('input[name="_password"]', PASSWORD);
                await page.click('button[type="submit"]');
                
                // Wait for navigation or timeout
                try {
                    await page.waitForNavigation({ timeout: 15000 });
                    console.log(`  Login successful, redirected to: ${page.url()}`);
                } catch (e) {
                    console.log(`  Login navigation timeout: ${e.message}`);
                    // Check if we're still on login page
                    const currentUrl = page.url();
                    if (currentUrl.includes('/user/login')) {
                        console.log('  Still on login page after submit');
                    }
                }
                
                // Wait a bit for potential dashboard load
                await page.waitForTimeout(3000);
                
                // Check dashboard/UI state
                const dashboardState = await page.evaluate(() => {
                    const html = document.body.innerHTML.toLowerCase();
                    return {
                        hasDashboard: html.includes('dashboard') || html.includes('enrich') || html.includes('catalog'),
                        hasMenu: html.includes('menu') || html.includes('nav'),
                        title: document.title
                    };
                });
                console.log(`  Dashboard indicators: ${dashboardState.hasDashboard}`);
                console.log(`  Menu/nav indicators: ${dashboardState.hasMenu}`);
                console.log(`  Page title: ${dashboardState.title}`);
            } else {
                console.log('  CSRF token not found');
            }
        }
        
        // Report issues
        const errors = consoleLogs.filter(log => log.startsWith('error:'));
        if (errors.length > 0) {
            console.log(`  Console errors: ${errors.length}`);
            errors.slice(0, 5).forEach(error => console.log(`    ${error}`));
        }
        
        if (pageErrors.length > 0) {
            console.log(`  Page errors: ${pageErrors.length}`);
            pageErrors.slice(0, 5).forEach(error => console.log(`    ${error}`));
        }
        
    } catch (error) {
        console.error(`  TEST FAILED: ${error.message}`);
    } finally {
        await browser.close();
    }
}

// Run the test
testUIRendering().catch(e => {
    console.error(e);
    process.exit(1);
});

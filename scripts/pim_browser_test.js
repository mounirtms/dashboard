#!/usr/bin/env node
/**
 * PIM Playwright Test with full logging
 * Run: node /home/dashboard/public_html/scripts/pim_browser_test.js
 */

const { chromium } = require('playwright');

const BASE_URL = 'https://pim.technostationery.com';
const USERNAME = 'admin';
const PASSWORD = 'PimAdmin2026!';

const fs = require('fs');

async function runBrowserTest() {
    console.log('\n=== PIM BROWSER TEST WITH LOGGING ===\n');
    
    const browser = await chromium.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    
    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 },
        ignoreHTTPSErrors: true,
        userAgent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36'
    });
    
    const page = await context.newPage();

    // Abort noisy analytics/CDN requests to avoid networkidle stalls and reduce noise
    await page.route('**/*', route => {
        const url = route.request().url();
        if (url.includes('google-analytics') || url.includes('clarity.ms') || url.includes('cdn-cgi') || url.includes('d.clarity.ms') || url.includes('/465q/') ) {
            return route.abort();
        }
        return route.continue();
    });
    
    // Capture ALL console messages
    const allLogs = [];
    page.on('console', msg => {
        const log = `[${msg.type()}] ${msg.text()}`;
        allLogs.push(log);
    });
    
    // Capture page errors
    const pageErrors = [];
    page.on('pageerror', err => {
        pageErrors.push(`ERROR: ${err.message}`);
    });
    
    // Capture network failures
    const failedRequests = [];
    page.on('requestfailed', req => {
        failedRequests.push(`${req.url()} - ${req.failure()?.errorText}`);
    });
    
    // Capture responses with errors
    const errorResponses = [];
    page.on('response', res => {
        if (res.status() >= 400) {
            errorResponses.push(`${res.url()} - ${res.status()}`);
        }
    });
    
    try {
        // Test 1: Homepage
        console.log('TEST 1: Loading homepage...');
        const r1 = await page.goto(BASE_URL + '/', { waitUntil: 'networkidle', timeout: 30000 });
        console.log(`  Status: ${r1.status()}`);
        
        // Test 2: Login Page
        console.log('\nTEST 2: Loading login page...');
        await page.goto(BASE_URL + '/user/login', { waitUntil: 'networkidle', timeout: 30000 });
        
        // Check login form
        const formExists = await page.$('form[action*="login-check"]');
        console.log(`  Form exists: ${!!formExists}`);
        
        // Check for styles
        const styles = await page.evaluate(() => {
            const body = document.body;
            const computed = window.getComputedStyle(body);
            return {
                backgroundColor: computed.backgroundColor,
                minHeight: computed.minHeight,
                className: body.className
            };
        });
        console.log(`  Body class: ${styles.className}`);
        console.log(`  Background: ${styles.backgroundColor}`);
        
        // Check CSS loading
        const cssLinks = await page.evaluate(() => {
            const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'));
            return links.map(l => l.href).filter(h => h && h.includes('pim'));
        });
        console.log(`  CSS files: ${cssLinks.length}`);
        cssLinks.forEach(c => console.log(`    - ${c}`));
        
        // Test 3: Try Login
        console.log('\nTEST 3: Attempting login...');
        
        // Get CSRF token
        const csrfToken = await page.evaluate(() => {
            const input = document.querySelector('input[name="_csrf_token"]');
            return input ? input.value : null;
        });
        console.log(`  CSRF: ${csrfToken ? 'Found' : 'NOT FOUND'}`);
        
        // Fill login form
        await page.fill('input[name="_username"]', USERNAME);
        await page.fill('input[name="_password"]', PASSWORD);
        
        // Click submit
        await page.click('button[type="submit"]');
        
        // Wait for navigation and robustly confirm login/dashboard
        let currentUrl = page.url();
        console.log(`  After login URL: ${currentUrl}`);

        // Retry loop: wait up to 30s for dashboard/menu to appear, tolerate transient 500s by reloading
        const start = Date.now();
        let dashboardCheck = { hasDashboard: false, hasLogin: true, title: '' };
        const maxWait = 30000;
        while ((Date.now() - start) < maxWait) {
            // small wait to allow SPA boot
            await page.waitForTimeout(1500);
            try {
                // reload if page shows a 500-ish body or fails to render
                const bodyHtml = await page.evaluate(() => document.body ? document.body.innerHTML : '');
                if (bodyHtml && bodyHtml.match(/\b500\b|Fatal error|Exception|Connection refused/i)) {
                    console.log('  Detected server error in page body, reloading...');
                    await page.reload({ waitUntil: 'networkidle', timeout: 15000 }).catch(() => null);
                    continue;
                }
            } catch (e) {
                // ignore
            }

            // Check for dashboard/menu indicators
            dashboardCheck = await page.evaluate(() => {
                const html = document.body ? document.body.innerHTML : '';
                return {
                    hasDashboard: html.includes('dashboard') || html.includes('enrich/product') || !!document.querySelector('nav') || !!document.querySelector('.pim-menu'),
                    hasLogin: html.includes('login') && html.includes('password'),
                    title: document.title
                };
            });
            currentUrl = page.url();
            if (dashboardCheck.hasDashboard) break;
        }

        console.log(`  Logged in: ${dashboardCheck.hasDashboard}`);
        console.log(`  Still on login: ${dashboardCheck.hasLogin}`);

        // Save post-login HTML and anchors for debugging
        try {
            const postHtml = await page.content();
            fs.writeFileSync('test-results/pim_after_login.html', postHtml);
            console.log('  Saved post-login HTML to test-results/pim_after_login.html');

            const anchors = await page.$$eval('a', as => as.map(a => ({href: a.getAttribute('href'), hrefAbs: a.href, text: a.innerText.trim()})));
            console.log(`  Anchor count: ${anchors.length}`);
            anchors.slice(0, 50).forEach(a => console.log(`    - ${a.href || a.hrefAbs} | ${a.text}`));
        } catch (e) {
            console.log('  Failed saving post-login HTML: ' + e.message);
        }

        // Try to execute deferred scripts (Cloudflare Rocket Loader) by injecting original scripts
        try {
            await page.evaluate(() => {
                Array.from(document.querySelectorAll('script[type*="-text/javascript"]')).forEach(s => {
                    try {
                        const ns = document.createElement('script');
                        if (s.src) ns.src = s.src; else ns.textContent = s.textContent;
                        ns.async = false;
                        document.head.appendChild(ns);
                    } catch (err) { /* ignore */ }
                });
            });
            await page.waitForTimeout(3000);
            console.log('  Injected deferred scripts to force execution');
        } catch (err) {
            console.log('  Script injection failed: ' + err.message);
        }

        // Check for menu/navigation elements and exercise menu links
        const menuSelectors = ['nav', '.menu', '.main-nav', '#main-menu', '.nav', '.side-menu', '.pim-menu'];
        let menuFound = null;
        for (const sel of menuSelectors) {
            const el = await page.$(sel);
            if (el) { menuFound = sel; break; }
        }
        console.log(`  Menu selector found: ${menuFound}`);

        let menuLinks = [];
        if (menuFound) {
            menuLinks = await page.$$eval(`${menuFound} a`, links => links.map(a => ({href: a.getAttribute('href'), text: a.innerText.trim()})).filter(l => l.href));
        } else {
            // fallback to common Akeneo links
            menuLinks = await page.$$eval('a[href*="enrich"], a[href*="catalog"], a[href*="settings"], a[href*="system"], a[href*="configuration"]', links => links.map(a => ({href: a.getAttribute('href'), text: a.innerText.trim()})).filter(l => l.href));
        }

        console.log(`  Menu links found: ${menuLinks.length}`);

        // Iterate a reasonable subset of menu links and verify pages load and main content is present
        for (const link of menuLinks.slice(0, 12)) {
            try {
                const safeText = (link.text || 'link').replace(/[^a-z0-9]+/gi, '_').toLowerCase();
                console.log(`  -> Clicking menu: ${link.text} (${link.href})`);

                // Click by href if it is an in-site link
                if (link.href && link.href.startsWith('/')) {
                    await Promise.all([
                        page.waitForNavigation({ waitUntil: 'load', timeout: 15000 }).catch(() => null),
                        page.click(`a[href='${link.href}']`).catch(() => null)
                    ]);
                } else {
                    // try clicking by link text if href is absolute or not matching
                    const anchors = await page.$$("a");
                    for (const a of anchors) {
                        const txt = (await a.innerText()).trim();
                        if (txt === link.text) {
                            await Promise.all([
                                page.waitForNavigation({ waitUntil: 'load', timeout: 15000 }).catch(() => null),
                                a.click().catch(() => null)
                            ]);
                            break;
                        }
                    }
                }

                const landed = page.url();
                console.log(`    Landed: ${landed}`);

                const mainPresent = !!(await page.$('main, #content, .main, .container, #main-content'));
                console.log(`    Main present: ${mainPresent}`);

                const screenshotPath = `/tmp/pim_menu_${safeText}_${Date.now()}.png`;
                await page.screenshot({ path: screenshotPath, fullPage: true });
                console.log(`    Screenshot: ${screenshotPath}`);

                // Return to dashboard/home before next link
                await page.goto(BASE_URL + '/', { waitUntil: 'load', timeout: 15000 }).catch(() => null);
                await page.waitForTimeout(800);

            } catch (err) {
                console.log(`    Click error: ${err.message}`);
            }
        }
        
        // Test 4: Try accessing a protected page
        console.log('\nTEST 4: Accessing products page...');
        try {
            const r4 = await page.goto(BASE_URL + '/enrich/product/', { 
                waitUntil: 'networkidle', 
                timeout: 15000 
            });
            console.log(`  Products page status: ${r4.status()}`);
        } catch (e) {
            console.log(`  Products page error: ${e.message}`);
        }
        
        // Screenshot
        await page.screenshot({ path: '/tmp/pim_test_screenshot.png', fullPage: true });
        console.log('\n  Screenshot saved: /tmp/pim_test_screenshot.png');
        
        // Summary of issues
        console.log('\n=== ISSUES FOUND ===');
        console.log(`Console errors: ${allLogs.filter(l => l.includes('[error]')).length}`);
        console.log(`Page errors: ${pageErrors.length}`);
        console.log(`Failed requests: ${failedRequests.length}`);
        console.log(`Error responses: ${errorResponses.length}`);
        
        if (pageErrors.length > 0) {
            console.log('\nPage Errors:');
            pageErrors.forEach(e => console.log(`  ${e}`));
        }
        
        if (failedRequests.length > 0) {
            console.log('\nFailed Requests:');
            failedRequests.forEach(r => console.log(`  ${r}`));
        }
        
        if (errorResponses.length > 0) {
            console.log('\nError Responses:');
            errorResponses.forEach(r => console.log(`  ${r}`));
        }
        
        console.log('\n=== TEST COMPLETE ===\n');
        
    } catch (error) {
        console.error('TEST FAILED:', error.message);
    } finally {
        await browser.close();
    }
    
    process.exit(0);
}

runBrowserTest().catch(e => {
    console.error(e);
    process.exit(1);
});
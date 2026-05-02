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
        viewport: { width: 1920, height: 1080 }
    });
    
    const page = await context.newPage();
    
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
        
        // Wait for navigation
        await page.waitForTimeout(3000);
        
        // Check where we ended up
        const currentUrl = page.url();
        console.log(`  After login URL: ${currentUrl}`);
        
        // Check if logged in (look for dashboard elements)
        const dashboardCheck = await page.evaluate(() => {
            const body = document.body;
            const html = document.body.innerHTML;
            return {
                hasDashboard: html.includes('dashboard') || html.includes('enrich/product'),
                hasLogin: html.includes('login') && html.includes('password'),
                title: document.title
            };
        });
        console.log(`  Logged in: ${dashboardCheck.hasDashboard}`);
        console.log(`  Still on login: ${dashboardCheck.hasLogin}`);
        
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
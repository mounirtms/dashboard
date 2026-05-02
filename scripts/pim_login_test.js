#!/usr/bin/env node
/**
 * PIM Comprehensive Login Test
 * Tests all login scenarios and captures detailed debug info
 */

const { chromium } = require('playwright');

const BASE_URL = 'https://pim.technostationery.com';
const USERS = [
    { username: 'admin', password: 'PimAdmin2026!' },
    { username: 'apiconnector', password: 'ApiConnector@2026!Secure' }
];

const fs = require('fs');

async function runTest() {
    console.log('\n=== PIM COMPREHENSIVE LOGIN TEST ===\n');
    
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();
    
    const issues = [];
    const debug = [];
    
    page.on('console', msg => {
        if (msg.type() === 'error') {
            issues.push(`Console Error: ${msg.text()}`);
        }
    });
    
    page.on('pageerror', err => {
        issues.push(`Page Error: ${err.message}`);
    });
    
    page.on('response', async res => {
        if (res.status() >= 400 && res.url().includes('login')) {
            debug.push(`HTTP ${res.status()}: ${res.url()}`);
        }
    });
    
    for (const user of USERS) {
        console.log(`\n=== Testing: ${user.username} ===`);
        
        await page.goto(BASE_URL + '/user/login', { waitUntil: 'networkidle', timeout: 30000 });
        debug.push(`Loaded login page`);
        
        await page.fill('input[name="_username"]', user.username);
        await page.fill('input[name="_password"]', user.password);
        
        debug.push(`Filling form: ${user.username} / *****`);
        
        await page.click('button[type="submit"]');
        await page.waitForTimeout(5000);
        
        const finalUrl = page.url();
        const success = !finalUrl.includes('/user/login');
        
        console.log(`  Final URL: ${finalUrl}`);
        console.log(`  Success: ${success ? 'YES' : 'NO'}`);
        
        if (!success) {
            const html = await page.content();
            if (html.includes('error') || html.includes('invalid')) {
                issues.push(`Login failed - error in page for ${user.username}`);
            }
        }
        
        debug.push(`Result for ${user.username}: ${success ? 'SUCCESS' : 'FAILED'}`);
    }
    
    console.log('\n=== DEBUG INFO ===');
    debug.forEach(d => console.log(`  ${d}`));
    
    console.log('\n=== ISSUES ===');
    if (issues.length === 0) {
        console.log('  None');
    } else {
        issues.forEach(i => console.log(`  ${i}`));
    }
    
    await page.screenshot({ path: '/tmp/pim_login_test.png', fullPage: true });
    console.log('\n  Screenshot: /tmp/pim_login_test.png');
    
    await browser.close();
    process.exit(0);
}

runTest().catch(e => {
    console.error('Test failed:', e.message);
    process.exit(1);
});
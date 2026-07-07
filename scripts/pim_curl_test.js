#!/usr/bin/env node
/**
 * PIM Curl Test - No Playwright needed
 * Run: node pim_curl_test.js
 */

const { execSync } = require('child_process');
const https = require('https');
const http = require('http');

// Colors
const colors = { red: '\x1b[31m', green: '\x1b[32m', yellow: '\x1b[33m', cyan: '\x1b[36m', reset: '\x1b[0m' };

function log(msg, c = 'cyan') {
    console.log(`${colors[c]}${msg}${colors.reset}`);
}

function curl(url, options = {}) {
    return new Promise((resolve, reject) => {
        const u = new URL(url);
        const h = u.protocol === 'https:' ? https : http;
        
        const req = h.request(url, {
            method: 'GET',
            headers: {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept': 'text/html,application/xhtml+xml',
                ...options.headers
            },
            timeout: 30000
        }, res => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => {
                resolve({
                    status: res.statusCode,
                    headers: res.headers,
                    data: data,
                    size: Buffer.byteLength(data, 'utf8')
                });
            });
        });
        
        req.on('error', reject);
        req.on('timeout', () => reject(new Error('Timeout')));
        req.end();
    });
}

async function runTests() {
    const BASE_URL = process.argv[2] || 'https://pim.technostationery.com';
    log(`\n=== PIM CURL TEST SUITE ===`, 'cyan');
    log(`Target: ${BASE_URL}\n`);
    
    const results = {
        homepage: { status: 0, time: 0, size: 0 },
        login: { status: 0, time: 0, size: 0, hasForm: false },
        css: { status: 0, time: 0, size: 0 },
        api: { status: 0, time: 0, size: 0 }
    };
    
    try {
        // TEST 1: Homepage
        log('TEST 1: Homepage');
        let start = Date.now();
        let r = await curl(BASE_URL + '/');
        results.homepage.status = r.status;
        results.homepage.time = Date.now() - start;
        results.homepage.size = r.size;
        
        log(`  Status: ${r.status} (${results.homepage.time}ms)`);
        log(`  Size: ${r.size} bytes`);
        
        // Extract redirect info
        if (r.headers.location) {
            log(`  Redirects to: ${r.headers.location}`, 'yellow');
        }
        
        // Check for errors in HTML
        if (r.data.includes('error') || r.data.includes('Error')) {
            log('  Found error text!', 'red');
        }
        
        // =========================================
        // TEST 2: Login Page
        // =========================================
        log('\nTEST 2: Login Page');
        start = Date.now();
        r = await curl(BASE_URL + '/user/login');
        results.login.status = r.status;
        results.login.time = Date.now() - start;
        results.login.size = r.size;
        
        log(`  Status: ${r.status} (${results.login.time}ms)`);
        log(`  Size: ${r.size} bytes`);
        
        // Check form elements in HTML
        results.login.hasForm = r.data.includes('pim_user_security_login') || 
                               r.data.includes('_username') ||
                               r.data.includes('login');
        
        log(`  Has login form: ${results.login.hasForm ? 'YES' : 'NO'}`);
        
        // Look for input fields
        const hasUsername = r.data.includes('username') || r.data.includes('name="_username"');
        const hasPassword = r.data.includes('password') || r.data.includes('name="_password"');
        const hasSubmit = r.data.includes('type="submit"') || r.data.includes('button');
        
        log(`  Username field: ${hasUsername ? 'YES' : 'NO'}`);
        log(`  Password field: ${hasPassword ? 'YES' : 'NO'}`);
        log(`  Submit button: ${hasSubmit ? 'YES' : 'NO'}`);
        
        // Check loading issues
        const errors = ['error', 'fatal', 'exception', 'failed'].filter(e => r.data.toLowerCase().includes(e));
        if (errors.length > 0) {
            log(`  WARNING: Found ${errors.length} error keywords!`, 'red');
        }
        
        // Extract JavaScript files
        const jsMatches = r.data.match(/src="([^"]*\.js)"/g) || [];
        log(`  JS files: ${jsMatches.length}`);
        
        // Extract CSS files  
        const cssMatches = r.data.match(/href="([^"]*\.css)"/g) || [];
        log(`  CSS files: ${cssMatches.length}`);
        cssMatches.forEach(c => {
            if (c.includes('pim')) {
                log(`    PIM CSS: ${c}`, 'green');
            }
        });
        
        // =========================================
        // TEST 3: CSS File
        // =========================================
        log('\nTEST 3: CSS File');
        start = Date.now();
        r = await curl(BASE_URL + '/css/pim.css');
        results.css.status = r.status;
        results.css.time = Date.now() - start;
        results.css.size = r.size;
        
        log(`  Status: ${r.status} (${results.css.time}ms)`);
        log(`  Size: ${r.size} bytes`);
        
        // Check CSS content
        if (r.data.includes('Akeneo') || r.data.includes('pim')) {
            log(`  Contains PIM styles: YES`, 'green');
        }
        
        // =========================================
        // TEST 4: API
        // =========================================
        log('\nTEST 4: API Products');
        start = Date.now();
        r = await curl(BASE_URL + '/api/rest/v1/products?limit=1');
        results.api.status = r.status;
        results.api.time = Date.now() - start;
        
        log(`  Status: ${r.status} (${results.api.time}ms)`);
        
        // Try with auth
        if (r.status >= 400) {
            log('  Trying with basic auth...', 'yellow');
            const authR = await curl(BASE_URL + '/api/rest/v1/products?limit=1', {
                headers: { 'Authorization': 'Basic ' + Buffer.from('apiconnector:ApiConnector@2026!Secure').toString('base64') }
            });
            log(`  Auth Status: ${authR.status}`, authR.status === 200 ? 'green' : 'yellow');
        }
        
        // =========================================
        // SUMMARY
        // =========================================
        log('\n=== SUMMARY ===', 'cyan');
        
        const totalIssues = [];
        if (results.homepage.status !== 200) totalIssues.push(`Homepage: ${results.homepage.status}`);
        if (results.login.status !== 200) totalIssues.push(`Login: ${results.login.status}`);
        if (!results.login.hasForm) totalIssues.push('No login form found');
        if (results.css.status !== 200) totalIssues.push(`CSS: ${results.css.status}`);
        
        if (totalIssues.length === 0) {
            log('All tests PASSED!', 'green');
        } else {
            log('Issues found:', 'red');
            totalIssues.forEach(i => log(`  - ${i}`, 'yellow'));
        }
        
        log('\n=== COMPLETE ===\n', 'green');
        
    } catch (error) {
        log(`ERROR: ${error.message}`, 'red');
    }
    
    process.exit(0);
}

runTests();
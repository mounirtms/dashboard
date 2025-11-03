const { exec } = require('child_process');
const fs = require('fs-extra');
const path = require('path');

/**
 * MAB Project Optimization Script (Remaining Tasks)
 * Runs optimization tasks except image resizing
 */

const PROJECT_ROOT = '/home/technadminy7/public_html';

async function runCommand(command) {
    return new Promise((resolve, reject) => {
        console.log(`Executing: ${command}`);
        exec(command, { cwd: PROJECT_ROOT }, (error, stdout, stderr) => {
            if (error) {
                console.error(`Error: ${error.message}`);
                reject(error);
                return;
            }
            if (stderr) {
                console.error(`Stderr: ${stderr}`);
            }
            console.log(`Output: ${stdout}`);
            resolve(stdout);
        });
    });
}

async function optimizeRemaining() {
    try {
        console.log('🚀 Starting MAB Project Optimization (Remaining Tasks)...');
        
        // 1. Clear Magento caches
        console.log('\n1. Clearing Magento caches...');
        await runCommand('php bin/magento cache:clean');
        
        // 2. Minify static assets
        console.log('\n2. Minifying static assets...');
        await runCommand('npm run minify-static');
        
        // 3. Reindex
        console.log('\n3. Reindexing...');
        await runCommand('php bin/magento indexer:reindex');
        
        // 4. Clear cache again
        console.log('\n4. Final cache clear...');
        await runCommand('php bin/magento cache:flush');
        
        console.log('\n✅ All remaining optimization tasks completed successfully!');
        
    } catch (error) {
        console.error('❌ Optimization failed:', error.message);
        process.exit(1);
    }
}

// Run optimization if script is called directly
if (require.main === module) {
    optimizeRemaining();
}

module.exports = { optimizeRemaining };

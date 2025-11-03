const fs = require('fs-extra');
const path = require('path');
const glob = require('glob');

/**
 * Module Performance Check Script
 * Analyzes MAB modules for potential performance improvements
 */

const PROJECT_ROOT = '/home/technadminy7/public_html';
const MAB_MODULES_DIR = path.join(PROJECT_ROOT, 'app', 'code', 'Mab');

async function checkModulePerformance() {
    try {
        console.log('🔍 Analyzing MAB modules for performance improvements...\n');
        
        // Check if MAB modules directory exists
        if (!(await fs.pathExists(MAB_MODULES_DIR))) {
            console.error('❌ MAB modules directory not found');
            process.exit(1);
        }
        
        // Get all MAB modules
        const modules = await fs.readdir(MAB_MODULES_DIR);
        const moduleDirs = modules.filter(item => 
            !item.startsWith('.') && 
            fs.statSync(path.join(MAB_MODULES_DIR, item)).isDirectory()
        );
        
        console.log(`Found ${moduleDirs.length} MAB modules:\n`);
        
        let totalIssues = 0;
        
        // Analyze each module
        for (const module of moduleDirs) {
            const modulePath = path.join(MAB_MODULES_DIR, module);
            console.log(`📁 ${module}`);
            
            let moduleIssues = 0;
            
            // 1. Check for large configuration files
            const configFiles = glob.sync(`${modulePath}/etc/**/*.xml`, {
                ignore: ['**/node_modules/**']
            });
            
            for (const configFile of configFiles) {
                try {
                    const stats = await fs.stat(configFile);
                    // Flag files larger than 100KB
                    if (stats.size > 100 * 1024) {
                        const sizeKB = (stats.size / 1024).toFixed(2);
                        console.log(`  ⚠️ Large config file (${sizeKB}KB): ${path.relative(modulePath, configFile)}`);
                        moduleIssues++;
                    }
                } catch (err) {
                    // Skip errors
                }
            }
            
            // 2. Check for missing registration files
            const registrationFile = path.join(modulePath, 'registration.php');
            if (!(await fs.pathExists(registrationFile))) {
                console.log(`  ❌ Missing registration.php`);
                moduleIssues++;
            }
            
            // 3. Check for composer.json (not required but good to have)
            const composerFile = path.join(modulePath, 'composer.json');
            if (!(await fs.pathExists(composerFile))) {
                // This is not necessarily an issue, just informational
                // console.log(`  ℹ️ No composer.json found`);
            }
            
            // 4. Check for excessive nested directories
            const allDirs = glob.sync(`${modulePath}/**/*`, {
                ignore: ['**/node_modules/**'],
                nodir: false
            }).filter(item => fs.statSync(item).isDirectory());
            
            if (allDirs.length > 50) {
                console.log(`  ⚠️ Module has ${allDirs.length} directories (might be overly complex)`);
                moduleIssues++;
            }
            
            // 5. Check for large PHP files
            const phpFiles = glob.sync(`${modulePath}/**/*.php`, {
                ignore: ['**/node_modules/**']
            });
            
            for (const phpFile of phpFiles) {
                try {
                    const stats = await fs.stat(phpFile);
                    // Flag files larger than 50KB
                    if (stats.size > 50 * 1024) {
                        const sizeKB = (stats.size / 1024).toFixed(2);
                        console.log(`  ⚠️ Large PHP file (${sizeKB}KB): ${path.relative(modulePath, phpFile)}`);
                        moduleIssues++;
                    }
                } catch (err) {
                    // Skip errors
                }
            }
            
            if (moduleIssues === 0) {
                console.log(`  ✅ No issues found`);
            } else {
                totalIssues += moduleIssues;
            }
            
            console.log(''); // Empty line between modules
        }
        
        console.log(`\n📊 Analysis complete:`);
        console.log(`   ${moduleDirs.length} modules analyzed`);
        console.log(`   ${totalIssues} potential issues found`);
        
        if (totalIssues > 0) {
            console.log(`\n💡 Recommendations:`);
            console.log(`   1. Break down large configuration files into smaller, more manageable pieces`);
            console.log(`   2. Review large PHP files for possible refactoring`);
            console.log(`   3. Consider if all directories are necessary for module functionality`);
        } else {
            console.log(`\n✅ All modules appear to be in good condition!`);
        }
        
    } catch (error) {
        console.error('❌ Module performance check failed:', error.message);
        process.exit(1);
    }
}

// Run check if script is called directly
if (require.main === module) {
    checkModulePerformance();
}

module.exports = { checkModulePerformance };
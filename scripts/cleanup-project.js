const fs = require('fs-extra');
const path = require('path');
const glob = require('glob');

/**
 * Project Cleanup Script
 * Organizes files and removes unnecessary items
 */

const PROJECT_ROOT = '/home/technadminy7/public_html';
const BACKUP_DIR = path.join(PROJECT_ROOT, 'var', 'backups');

async function cleanupProject() {
    try {
        console.log('🧹 Starting project cleanup...');
        
        // Create backup directory if it doesn't exist
        await fs.ensureDir(BACKUP_DIR);
        console.log(`✅ Backup directory ensured: ${BACKUP_DIR}`);
        
        // 1. Clean up log files older than 30 days
        console.log('\n1. Cleaning up old log files...');
        const logFiles = glob.sync(`${PROJECT_ROOT}/var/log/**/*.log`, { 
            ignore: ['**/node_modules/**'] 
        });
        
        const thirtyDaysAgo = Date.now() - (30 * 24 * 60 * 60 * 1000);
        let cleanedLogs = 0;
        
        for (const logFile of logFiles) {
            try {
                const stats = await fs.stat(logFile);
                if (stats.mtime < thirtyDaysAgo) {
                    // Move to backup instead of deleting
                    const backupPath = path.join(BACKUP_DIR, 'logs', path.basename(logFile));
                    await fs.ensureDir(path.dirname(backupPath));
                    await fs.move(logFile, backupPath, { overwrite: true });
                    cleanedLogs++;
                }
            } catch (err) {
                console.warn(`⚠️ Could not process log file ${logFile}: ${err.message}`);
            }
        }
        console.log(`✅ Cleaned up ${cleanedLogs} old log files`);
        
        // 2. Clean up session files older than 7 days
        console.log('\n2. Cleaning up old session files...');
        const sessionFiles = glob.sync(`${PROJECT_ROOT}/var/session/**/*`, {
            ignore: ['**/node_modules/**']
        });
        
        const sevenDaysAgo = Date.now() - (7 * 24 * 60 * 60 * 1000);
        let cleanedSessions = 0;
        
        for (const sessionFile of sessionFiles) {
            try {
                const stats = await fs.stat(sessionFile);
                if (stats.mtime < sevenDaysAgo) {
                    await fs.remove(sessionFile);
                    cleanedSessions++;
                }
            } catch (err) {
                // Skip directories and errors
            }
        }
        console.log(`✅ Cleaned up ${cleanedSessions} old session files`);
        
        // 3. Report on large files that could be optimized
        console.log('\n3. Analyzing large files...');
        const largeFiles = [];
        const allFiles = glob.sync(`${PROJECT_ROOT}/{pub/media,var}/**/*`, {
            ignore: ['**/node_modules/**', '**/.git/**'],
            nodir: true
        });
        
        for (const file of allFiles) {
            try {
                const stats = await fs.stat(file);
                // Files larger than 5MB
                if (stats.size > 5 * 1024 * 1024) {
                    largeFiles.push({
                        path: file,
                        size: stats.size
                    });
                }
            } catch (err) {
                // Skip errors
            }
        }
        
        if (largeFiles.length > 0) {
            console.log('⚠️ Large files found (consider optimizing):');
            largeFiles
                .sort((a, b) => b.size - a.size)
                .slice(0, 10)
                .forEach(file => {
                    const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
                    console.log(`   ${sizeMB}MB - ${file.path}`);
                });
        } else {
            console.log('✅ No large files found');
        }
        
        console.log('\n✅ Project cleanup completed successfully!');
        console.log(`📝 Backup of old logs available at: ${BACKUP_DIR}/logs/`);
        
    } catch (error) {
        console.error('❌ Cleanup failed:', error.message);
        process.exit(1);
    }
}

// Run cleanup if script is called directly
if (require.main === module) {
    cleanupProject();
}

module.exports = { cleanupProject };
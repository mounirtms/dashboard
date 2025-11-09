# Manual Script Execution Guide

This document provides instructions for manually running the optimization and maintenance scripts that were previously scheduled in cron jobs.

## npm Scripts (Node.js)

These scripts can be run using npm from the project root directory:

```bash
cd /home/technadminy7/public_html
```

### Asset Optimization
```bash
# Minify CSS and JavaScript files
npm run minify-static

# Resize and optimize images
npm run resize-images

# Run all optimization scripts
npm run optimize-all

# Clean up old log and session files
npm run cleanup

# Check module performance
npm run check-modules
```

## Shell Scripts

These scripts can be run directly from the scripts directory:

```bash
cd /home/technadminy7/public_html/scripts
```

### Manual Execution Commands

```bash
# Clean up session files
./clean_sessions.sh

# Frequent cache cleanup (runs every hour)
./frequent_cache_clean.sh

# Database table optimization (weekly)
./optimize_tables.sh

# Log rotation (daily)
./log_rotation.sh

# Database monitoring (daily)
./db_monitor.sh

# Cleanup duplicate files (weekly)
./cleanup_duplicates.sh

# Optimize README files (monthly)
./optimize_readme.sh

# Daily optimization tasks
./daily_optimization.sh

# Comprehensive cleanup
./comprehensive-cleanup.sh

# Remove unused files (weekly)
./remove-unused-files.sh

# Remove unused markdown files
./remove-unused-md-files.sh
```

## iDrive Backup Scripts

```bash
# Test iDrive connection
./test-idrive-connection.sh

# Upload latest backup to iDrive
./upload-to-idrive.sh

# Cleanup old iDrive backups (keeps last 3)
./cleanup-idrive-backups.sh
```

## Scheduling

The only automated script remaining is:
- Backup runs daily at midnight (00:00)

All other scripts must be run manually as needed.
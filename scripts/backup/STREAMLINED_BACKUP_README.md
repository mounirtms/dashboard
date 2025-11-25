# Streamlined Backup Solution for iDrive

## Overview

This document describes the streamlined backup solution designed to simplify and improve the backup process for the Magento installation. It consolidates functionality from multiple scripts into two core scripts for better maintainability and reliability.

## New Scripts

### 1. `streamlined-backup.sh`

A comprehensive backup script that performs all essential backup operations in a single execution:

1. Creates timestamped database backup
2. Creates selective file backups focusing on:
   - Essential system configuration files
   - Media files from technadminy7 (catalog, wysiwyg, promobanners, lookbook)
   - Specific directories from pim account (public_html/app, public_html/etc)
   - Specific directories from beta account (.ssh, .cpanel, .htpasswds, public_html/etc)
   - Important log files (system.log, exception.log, cron.log)
3. Uploads backups directly to iDrive (no intermediate storage)
4. Cleans up local backup files older than 7 days
5. Implements intelligent retention policies for iDrive backups:
   - Keeps daily backups for 7 days
   - Keeps weekly backups (Sundays) for 30 days
   - Keeps monthly backups (1st of month) for 90 days
   - Deletes everything older than 90 days

#### Features:
- Enhanced logging with timestamps and colored output
- More robust error handling
- Direct upload to iDrive (avoids disk space issues)
- Comprehensive retention policy enforcement
- Single point of failure for easier troubleshooting
- Optimized backup size by focusing on essential files only

### 2. `simple-cleanup.sh`

A focused script for cleaning up various types of extra files to maintain healthy disk usage:

1. Temporary files older than 1 day
2. Log files older than 30 days
3. Session files older than 7 days
4. Cache directories (var/cache and var/page_cache)
5. Local backup directories older than 7 days
6. Disk usage reporting

## Improvements Over Previous Solutions

### Optimized Storage Usage
- Only backs up essential files and directories
- Focuses on critical data: database, configuration, media files, and important logs
- Selectively includes only specific user accounts and directories (pim, beta)
- Reduces backup size significantly compared to full system backups

### Simplified Architecture
- Consolidated multiple scripts into two core scripts
- Eliminated separate backup creation and upload steps
- Reduced complexity and points of failure

### Direct Upload Strategy
- Uploads directly to iDrive without creating large temporary files
- Avoids issues with limited disk space in /tmp directory
- Uses `sudo` with absolute path to AWS CLI for reliable execution

### Better Error Handling
- More comprehensive error checking and reporting
- Improved logging with timestamps and status indicators
- Graceful handling of missing directories or files

### Streamlined Cron Management
- Single setup script manages all cron jobs
- Automatically removes old/obsolete cron entries
- Clear documentation of scheduled tasks

## Cron Jobs

The streamlined backup solution uses two cron jobs:

1. **Daily Backup Job** - Runs every day at 2:00 AM
   ```
   0 2 * * * /home/technadminy7/public_html/scripts/backup/streamlined-backup.sh
   ```

2. **Weekly Cleanup Job** - Runs every Sunday at 3:00 AM
   ```
   0 3 * * 0 /home/technadminy7/public_html/scripts/backup/simple-cleanup.sh
   ```

## Manual Execution

To manually run the backup process:
```bash
cd /home/technadminy7/public_html
./scripts/backup/streamlined-backup.sh
```

To manually run the cleanup process:
```bash
cd /home/technadminy7/public_html
./scripts/backup/simple-cleanup.sh
```

## Setup Instructions

To install the cron jobs:
```bash
cd /home/technadminy7/public_html
./scripts/backup/setup-streamlined-backup-cron.sh
```

To verify the cron jobs are installed:
```bash
crontab -l
```

To verify the entire backup system is working correctly:
```bash
cd /home/technadminy7/public_html
./scripts/backup/verify-streamlined-backup.sh
```

## Troubleshooting

### Checking Logs
Logs are written to:
- `/home/technadminy7/public_html/var/log/streamlined-backup.log`
- `/home/technadminy7/public_html/var/log/simple-cleanup.log`

### Verifying iDrive Contents
To list the contents of the iDrive bucket:
```bash
export AWS_ACCESS_KEY_ID="prQjrOCZTP1yTOPfYvRl"
export AWS_SECRET_ACCESS_KEY="41VJfqqxpbGse3G1UUTtdCLWUNGhtxnuf5DssWpT"
export AWS_DEFAULT_REGION="us-west-1"
/usr/local/bin/aws s3 ls s3://weektechno/ --endpoint-url https://l0y0.la.idrivee2-27.com
```

## Benefits

With this streamlined approach:
1. Backups run consistently every night without manual intervention
2. Disk space is managed efficiently with automatic cleanup
3. Fewer scripts to maintain and monitor
4. Direct upload avoids temporary disk space issues
5. Better logging and error reporting for easier troubleshooting
6. Simplified cron job management
7. Easy verification of system health with the verification script
8. Optimized backup size focuses on essential data only
# Monthly Beta Environment Backup Schedule

## Overview
This document describes the monthly backup system for the beta environment, which runs on the 1st of every month and cleans up old backups to maintain efficient storage usage.

## Backup Schedule

### Monthly Beta Backup
- **Frequency**: 1st of every month
- **Time**: 4:00 AM
- **Script**: `scripts/monthly-beta-backup.sh`
- **Contents**:
  - Database backup (`beta_database.sql.gz`)
  - Codebase backup including:
    - `app/` directory
    - `bin/` directory
    - `dev/` directory
    - `generated/` directory
    - `lib/` directory
    - `pub/static/` directory
    - `setup/` directory
    - `vendor/` directory
    - `pub/errors/` directory
    - `pub/media/.htaccess` file (not the full media directory)
  - Upload to iDrive: `s3://weektechno/beta-YYYY-MM-DD/`

### Beta Cleanup
- **Frequency**: 2nd of every month (day after backup)
- **Time**: 5:00 AM
- **Script**: `scripts/cleanup-beta-backups.sh`
- **Policy**: Deletes beta backups older than 90 days (keeps approximately 3 months of backups)

## Scripts

### 1. `monthly-beta-backup.sh`
- Creates a backup of the beta environment codebase and database
- Only runs on the 1st of each month
- Uploads backup to iDrive cloud storage
- Generates detailed logs

### 2. `cleanup-beta-backups.sh`
- Removes old beta backups from iDrive to save storage space
- Keeps only the last 90 days of beta backups
- Runs every 2nd of the month

### 3. `setup-monthly-beta-backup-cron.sh`
- Adds the monthly beta backup and cleanup jobs to the system crontab
- Can be run to install or update the cron jobs

### 4. `test-beta-backup.sh`
- Simulates the backup process without actually running it
- Useful for verifying the setup without performing a full backup

## Cron Job Entries

```
# Monthly beta backup on the 1st of every month at 4 AM
0 4 1 * * /home/technadminy7/public_html/scripts/monthly-beta-backup.sh

# Cleanup old beta backups on the 2nd of every month at 5 AM
0 5 2 * * /home/technadminy7/public_html/scripts/cleanup-beta-backups.sh
```

## Manual Execution

To manually run the beta backup (will only execute on the 1st of the month):
```bash
cd /home/technadminy7/public_html
./scripts/monthly-beta-backup.sh
```

To manually run the beta cleanup:
```bash
cd /home/technadminy7/public_html
./scripts/cleanup-beta-backups.sh
```

To test the backup process:
```bash
cd /home/technadminy7/public_html
./scripts/test-beta-backup.sh
```

To setup the cron jobs:
```bash
cd /home/technadminy7/public_html
./scripts/setup-monthly-beta-backup-cron.sh
```

## Logs

All scripts generate detailed logs in the `var/log/` directory:
- `monthly-beta-backup.log` - Monthly beta backup logs
- `cleanup-beta-backups.log` - Beta cleanup logs
- `setup-monthly-beta-backup-cron.log` - Cron setup logs
- `test-beta-backup.log` - Test logs

## Storage Management

### Retention Policy
- Keep beta backups for 90 days
- Approximately 3 months of beta backups maintained
- Automatic deletion of older beta backups

### Storage Requirements
- Each beta backup is typically 1-2 GB (codebase only, no media files)
- Total storage needed: ~6-12 GB for 3 months
- Efficient use of iDrive storage space

## Verification

To verify the cron jobs are installed:
```bash
crontab -l
```

To verify the backup was uploaded to iDrive:
```bash
aws s3 ls s3://weektechno/ --endpoint-url https://l0y0.la.idrivee2-27.com
```

## Troubleshooting

### Backup Not Running
1. Check if today is the 1st of the month
2. Verify cron jobs are installed (`crontab -l`)
3. Check logs for errors

### Upload Issues
1. Verify iDrive credentials in the script
2. Check network connectivity
3. Review logs for specific error messages

### Cleanup Not Working
1. Verify the date format of backup directories
2. Check the cutoff date calculation
3. Review logs for errors

## Security Notes

- iDrive credentials are stored in the scripts
- Ensure proper file permissions on backup scripts
- Regularly audit access to backup systems
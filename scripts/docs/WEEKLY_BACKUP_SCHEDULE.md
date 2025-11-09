# Weekly iDrive Backup Schedule

## Overview
This document describes the new weekly backup system for the Magento installation, which runs every Thursday and cleans up old backups to maintain efficient storage usage.

## Backup Schedule

### Weekly Backup
- **Frequency**: Every Thursday
- **Time**: 2:00 AM
- **Script**: `scripts/weekly-idrive-backup.sh`
- **Contents**:
  - Database backup (`magento_backup.sql.gz`)
  - File backup including:
    - `app/` directory
    - `pub/media/` directory
    - `pub/static/` directory
  - Upload to iDrive: `s3://weektechno/YYYY-MM-DD/`

### Cleanup
- **Frequency**: Every Friday (day after backup)
- **Time**: 3:00 AM
- **Script**: `scripts/cleanup-idrive-backups.sh`
- **Policy**: Deletes backups older than 30 days (keeps approximately 4 weeks of backups)

## Scripts

### 1. `weekly-idrive-backup.sh`
- Creates a complete backup of the Magento installation
- Only runs on Thursdays
- Uploads backup to iDrive cloud storage
- Generates detailed logs

### 2. `cleanup-idrive-backups.sh`
- Removes old backups from iDrive to save storage space
- Keeps only the last 30 days of backups
- Runs every Friday

### 3. `setup-weekly-backup-cron.sh`
- Adds the weekly backup and cleanup jobs to the system crontab
- Can be run to install or update the cron jobs

### 4. `test-weekly-backup.sh`
- Simulates the backup process without actually running it
- Useful for verifying the setup without performing a full backup

## Cron Job Entries

```
# Weekly backup every Thursday at 2 AM
0 2 * * 4 /home/technadminy7/public_html/scripts/weekly-idrive-backup.sh

# Cleanup old backups every Friday at 3 AM
0 3 * * 5 /home/technadminy7/public_html/scripts/cleanup-idrive-backups.sh
```

## Manual Execution

To manually run the weekly backup (will only execute on Thursdays):
```bash
cd /home/technadminy7/public_html
./scripts/weekly-idrive-backup.sh
```

To manually run the cleanup:
```bash
cd /home/technadminy7/public_html
./scripts/cleanup-idrive-backups.sh
```

To test the backup process:
```bash
cd /home/technadminy7/public_html
./scripts/test-weekly-backup.sh
```

To setup the cron jobs:
```bash
cd /home/technadminy7/public_html
./scripts/setup-weekly-backup-cron.sh
```

## Logs

All scripts generate detailed logs in the `var/log/` directory:
- `weekly-idrive-backup.log` - Weekly backup logs
- `cleanup-idrive-backups.log` - Cleanup logs
- `setup-weekly-backup-cron.log` - Cron setup logs
- `test-weekly-backup.log` - Test logs

## Storage Management

The system maintains approximately 4 weeks of backups:
- Each full backup is typically 4-5 GB
- 4 weeks of backups require approximately 16-20 GB of storage
- Old backups are automatically deleted to maintain this limit

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
1. Check if today is Thursday
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
# Database Backup Scripts

## Overview
Scripts for backing up production databases and uploading to iDrive S3 storage.

## Available Databases
- **technadminy7_dBT8x12y22** - Production Magento database
- **beta_dBT8x12y22** - Beta/staging database
- **akeneo_pim** - PIM (Product Information Management) database

## Scripts

### 1. `backup-databases.sh` - Full Backup Script
Complete backup solution with logging and iDrive upload.

**Usage:**
```bash
# Backup all databases and upload to iDrive
./backup-databases.sh --all --upload

# Backup all databases locally only
./backup-databases.sh --all

# Backup specific database
./backup-databases.sh technadminy7_dBT8x12y22

# Backup multiple specific databases
./backup-databases.sh technadminy7_dBT8x12y22 beta_dBT8x12y22

# Show help
./backup-databases.sh --help
```

**Features:**
- Compressed backups using pigz (multi-threaded)
- Includes routines, triggers, and events
- Comprehensive logging to `/home/dashboard/public_html/var/log/`
- Automatic upload to iDrive S3
- Backup location: `/backup/YYYY-MM-DD/databases/`

---

### 2. `quick-db-backup.sh` - Interactive Quick Backup
Simple script for manual backups with interactive prompts.

**Usage:**
```bash
# Interactive mode (prompts for database)
./quick-db-backup.sh

# Backup specific database
./quick-db-backup.sh technadminy7_dBT8x12y22

# Backup all databases
./quick-db-backup.sh all
```

---

### 3. `upload-to-idrive.sh` - Manual Upload Script
Upload existing backups to iDrive S3 storage.

**Usage:**
```bash
# Interactive mode (shows recent backups)
./upload-to-idrive.sh

# Upload specific path
./upload-to-idrive.sh /backup/2026-04-28/databases
./upload-to-idrive.sh /backup/2026-04-28/accounts/technadminy7.tar.gz
```

---

## Backup Storage

### Local Backups
- Location: `/backup/YYYY-MM-DD/databases/`
- Format: `database_name.sql.gz` (compressed with pigz)

### iDrive S3 Storage
- Bucket: `s3://weektechno/`
- Path: `YYYY-MM-DD/databases/`
- Endpoint: `https://l0y0.la.idrivee2-27.com`

---

## Automation

### Cron Job Example
```bash
# Daily backup at 2 AM with iDrive upload
0 2 * * * /home/dashboard/public_html/scripts/backup/backup-databases.sh --all --upload >> /home/dashboard/public_html/var/log/db-backup-cron.log 2>&1
```

### Setup Automated Backups
```bash
./setup-streamlined-backup-cron.sh
```

---

## MariaDB Configuration
- Binary: `/opt/mariadb10.6/mariadb/bin/mysqldump`
- Host: `127.0.0.1`
- Port: `3307`
- User: `root`

---

## Troubleshooting

### Check Backup Logs
```bash
ls -lth /home/dashboard/public_html/var/log/db-backup-*.log
tail -100 /home/dashboard/public_html/var/log/db-backup-*.log
```

### Verify iDrive Upload
```bash
export AWS_ACCESS_KEY_ID="prQjrOCZTP1yTOPfYvRl"
export AWS_SECRET_ACCESS_KEY="41VJfqqxpbGse3G1UUTtdCLWUNGhtxnuf5DssWpT"
/usr/local/bin/aws s3 ls s3://weektechno/$(date +%F)/databases/ \
    --endpoint-url https://l0y0.la.idrivee2-27.com
```

### Test Database Connection
```bash
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' \
    -h 127.0.0.1 -P 3307 -e "SHOW DATABASES;"
```

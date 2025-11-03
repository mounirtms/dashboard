#!/bin/bash

# iDrive Backup Script for Magento
# This script creates a database backup and uploads it to iDrive using AWS CLI

set -e

# === Configuration ===
PROJECT_ROOT="/home/technadminy7/public_html"
DATE=$(date +%F)
BACKUP_DIR="/backup/$DATE"
LOG_FILE="${PROJECT_ROOT}/var/log/idrive-backup.log"

# === iDrive S3 Configuration (from working awsbackup.sh) ===
export AWS_ACCESS_KEY_ID="prQjrOCZTP1yTOPfYvRl"
export AWS_SECRET_ACCESS_KEY="41VJfqqxpbGse3G1UUTtdCLWUNGhtxnuf5DssWpT"
export AWS_DEFAULT_REGION="us-west-1"

S3_BUCKET="s3://weektechno"
S3_ENDPOINT="https://l0y0.la.idrivee2-27.com"

# === MySQL Configuration ===
MYSQL_PASS='YourNewStrongPassword'
MYSQL_DB='technadminy7_dBT8x12y22'
MYSQL_USER='root'
MYSQL_HOST='127.0.0.1'
MYSQL_PORT='3307'
MYSQL_BIN='/opt/mariadb10.6/mariadb/bin/mysqldump'

# === Colors for output ===
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# === Functions ===
die() {
    echo -e "${RED}[$(date '+%Y-%m-%d %H:%M:%S')] ❌ ERROR: $1${NC}" | tee -a "$LOG_FILE"
    exit 1
}

log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')] $1${NC}" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')] ✅ SUCCESS: $1${NC}" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[$(date '+%Y-%m-%d %H:%M:%S')] ⚠️ WARNING: $1${NC}" | tee -a "$LOG_FILE"
}

# === Create backup directory ===
create_backup_dir() {
    log "Creating backup directory: $BACKUP_DIR"
    mkdir -p "$BACKUP_DIR"
    
    if [ ! -d "$BACKUP_DIR" ]; then
        die "Failed to create backup directory: $BACKUP_DIR"
    fi
    
    success "Backup directory created"
}

# === Backup Magento database ===
backup_database() {
    log "Starting database backup..."
    
    # Check if MariaDB is running
    if netstat -tlnp 2>/dev/null | grep -q ":$MYSQL_PORT "; then
        log "MariaDB is running on port $MYSQL_PORT, proceeding with database backup"
        
        # Create database dump
        log "Dumping Magento database to $BACKUP_DIR/magento_backup.sql.gz..."
        if $MYSQL_BIN --skip-lock-tables -u "$MYSQL_USER" -p"$MYSQL_PASS" -h "$MYSQL_HOST" -P "$MYSQL_PORT" "$MYSQL_DB" 2>> "$LOG_FILE" | gzip > "$BACKUP_DIR/magento_backup.sql.gz"; then
            success "Database backup completed"
        else
            die "Magento database backup failed"
        fi
    else
        warning "MariaDB is not running on port $MYSQL_PORT, skipping database backup"
    fi
}

# === Backup Magento files ===
backup_files() {
    log "Starting file backup..."
    
    # Create a tar.gz of important Magento directories
    log "Creating archive of Magento files..."
    
    # Create temporary list of files to backup
    TEMP_LIST="/tmp/magento_files_$(date +%s).txt"
    
    # Add important directories to backup list
    echo "${PROJECT_ROOT}/app" >> "$TEMP_LIST"
    echo "${PROJECT_ROOT}/pub/media" >> "$TEMP_LIST"
    echo "${PROJECT_ROOT}/pub/static" >> "$TEMP_LIST"
    
    # Create tar.gz archive
    if tar -czf "$BACKUP_DIR/magento_files.tar.gz" -T "$TEMP_LIST" 2>> "$LOG_FILE"; then
        success "File backup completed"
        rm -f "$TEMP_LIST"
    else
        rm -f "$TEMP_LIST"
        die "File backup failed"
    fi
}

# === Clean up empty files ===
cleanup_empty_files() {
    log "Cleaning up empty files that cause upload errors..."
    find "$BACKUP_DIR" -type f -size 0 -delete 2>> "$LOG_FILE"
    success "Empty file cleanup completed"
}

# === Upload to iDrive ===
upload_to_idrive() {
    log "Uploading backup to iDrive: $S3_BUCKET/$DATE/"
    
    # Upload with AWS CLI
    log "Starting upload process..."
    if aws s3 cp "$BACKUP_DIR" "$S3_BUCKET/$DATE/" \
        --recursive \
        --endpoint-url "$S3_ENDPOINT" \
        --no-progress 2>> "$LOG_FILE"; then
        success "Upload completed successfully"
    else
        warning "Some files may have failed to upload, checking what was uploaded..."
        
        # List what was uploaded successfully
        aws s3 ls "$S3_BUCKET/$DATE/" --recursive --endpoint-url "$S3_ENDPOINT" >> "$LOG_FILE" 2>&1
    fi
}

# === Verify upload ===
verify_upload() {
    log "Verifying upload..."
    
    # List files in the backup directory on iDrive
    if aws s3 ls "$S3_BUCKET/$DATE/" --endpoint-url "$S3_ENDPOINT" >> "$LOG_FILE" 2>&1; then
        success "Upload verification completed"
    else
        warning "Could not list files in iDrive backup directory"
    fi
}

# === Create completion marker ===
create_completion_marker() {
    log "Creating completion marker..."
    touch "$BACKUP_DIR/.complete"
    success "Completion marker created: $BACKUP_DIR/.complete"
}

# === Main function ===
main() {
    log "Starting iDrive backup process..."
    
    START_TIME=$(date +%s)
    
    # Create backup directory
    create_backup_dir
    
    # Backup database
    backup_database
    
    # Backup files
    backup_files
    
    # Clean up empty files
    cleanup_empty_files
    
    # Upload to iDrive
    upload_to_idrive
    
    # Verify upload
    verify_upload
    
    # Create completion marker
    create_completion_marker
    
    END_TIME=$(date +%s)
    DURATION=$((END_TIME - START_TIME))
    
    success "iDrive backup completed successfully in ${DURATION} seconds!"
    log "Backup location: $BACKUP_DIR"
    log "iDrive location: $S3_BUCKET/$DATE/"
    log "Log file: $LOG_FILE"
}

# === Run main function ===
main "$@"
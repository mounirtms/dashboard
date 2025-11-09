#!/bin/bash

# Monthly Beta Environment Backup Script
# This script creates a backup of the beta environment codebase and database
# Scheduled to run on the 1st of every month

set -e

# === Configuration ===
PROJECT_ROOT="/home/technadminy7/public_html"
BETA_ROOT="/home/beta/public_html"
DATE=$(date +%F)
DAY_OF_MONTH=$(date +%d)
BACKUP_DIR="/backup/beta-$DATE"
LOG_FILE="${PROJECT_ROOT}/var/log/monthly-beta-backup.log"

# === Beta Database Configuration ===
BETA_DB_HOST="127.0.0.1"
BETA_DB_PORT="3307"
BETA_DB_NAME="beta_dBT8x12y22"
BETA_DB_USER="beta_ntdbusr24"
BETA_DB_PASS="the-correct-password"
MYSQL_BIN="/opt/mariadb10.6/mariadb/bin/mysqldump"

# === iDrive S3 Configuration ===
export AWS_ACCESS_KEY_ID="prQjrOCZTP1yTOPfYvRl"
export AWS_SECRET_ACCESS_KEY="41VJfqqxpbGse3G1UUTtdCLWUNGhtxnuf5DssWpT"
export AWS_DEFAULT_REGION="us-west-1"

S3_BUCKET="s3://weektechno"
S3_ENDPOINT="https://l0y0.la.idrivee2-27.com"

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

# === Check if today is the 1st of the month ===
check_day() {
    if [ "$DAY_OF_MONTH" -ne 1 ]; then
        log "Today is not the 1st of the month (day $DAY_OF_MONTH). This script is designed to run only on the 1st."
        log "Exiting without performing backup."
        exit 0
    fi
    log "Today is the 1st of the month. Proceeding with monthly beta backup."
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

# === Backup Beta database ===
backup_beta_database() {
    log "Starting beta database backup..."
    
    # Check if MariaDB is running
    if netstat -tlnp 2>/dev/null | grep -q ":$BETA_DB_PORT "; then
        log "MariaDB is running on port $BETA_DB_PORT, proceeding with beta database backup"
        
        # Create database dump
        log "Dumping beta database to $BACKUP_DIR/beta_database.sql.gz..."
        if $MYSQL_BIN --skip-lock-tables -u "$BETA_DB_USER" -p"$BETA_DB_PASS" -h "$BETA_DB_HOST" -P "$BETA_DB_PORT" "$BETA_DB_NAME" 2>> "$LOG_FILE" | gzip > "$BACKUP_DIR/beta_database.sql.gz"; then
            success "Beta database backup completed"
        else
            die "Beta database backup failed"
        fi
    else
        warning "MariaDB is not running on port $BETA_DB_PORT, skipping database backup"
    fi
}

# === Backup Beta codebase ===
backup_beta_codebase() {
    log "Starting beta codebase backup..."
    
    # Create a tar.gz of important beta directories
    log "Creating archive of beta codebase..."
    
    # Create temporary list of files to backup
    TEMP_LIST="/tmp/beta_files_$(date +%s).txt"
    
    # Add important directories to backup list (only codebase, not media)
    echo "$BETA_ROOT/app" >> "$TEMP_LIST"
    echo "$BETA_ROOT/bin" >> "$TEMP_LIST"
    echo "$BETA_ROOT/dev" >> "$TEMP_LIST"
    echo "$BETA_ROOT/generated" >> "$TEMP_LIST"
    echo "$BETA_ROOT/lib" >> "$TEMP_LIST"
    echo "$BETA_ROOT/pub/static" >> "$TEMP_LIST"
    echo "$BETA_ROOT/setup" >> "$TEMP_LIST"
    echo "$BETA_ROOT/vendor" >> "$TEMP_LIST"
    echo "$BETA_ROOT/pub/errors" >> "$TEMP_LIST"
    echo "$BETA_ROOT/pub/media/.htaccess" >> "$TEMP_LIST"  # Include .htaccess but not media files
    
    # Exclude large unnecessary files
    echo "$BETA_ROOT/node_modules" >> "$TEMP_LIST.exclude"
    echo "$BETA_ROOT/var" >> "$TEMP_LIST.exclude"
    
    # Create tar.gz archive
    if tar -czf "$BACKUP_DIR/beta_codebase.tar.gz" -T "$TEMP_LIST" --exclude-from="$TEMP_LIST.exclude" 2>> "$LOG_FILE"; then
        success "Beta codebase backup completed"
        rm -f "$TEMP_LIST" "$TEMP_LIST.exclude"
    else
        rm -f "$TEMP_LIST" "$TEMP_LIST.exclude"
        die "Beta codebase backup failed"
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
    log "Uploading beta backup to iDrive: $S3_BUCKET/beta-$DATE/"
    
    # Upload with AWS CLI
    log "Starting upload process..."
    if aws s3 cp "$BACKUP_DIR" "$S3_BUCKET/beta-$DATE/" \
        --recursive \
        --endpoint-url "$S3_ENDPOINT" \
        --no-progress 2>> "$LOG_FILE"; then
        success "Upload completed successfully"
    else
        warning "Some files may have failed to upload, checking what was uploaded..."
        
        # List what was uploaded successfully
        aws s3 ls "$S3_BUCKET/beta-$DATE/" --recursive --endpoint-url "$S3_ENDPOINT" >> "$LOG_FILE" 2>&1
    fi
}

# === Verify upload ===
verify_upload() {
    log "Verifying upload..."
    
    # List files in the backup directory on iDrive
    if aws s3 ls "$S3_BUCKET/beta-$DATE/" --endpoint-url "$S3_ENDPOINT" >> "$LOG_FILE" 2>&1; then
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
    log "Starting monthly beta environment backup process..."
    
    START_TIME=$(date +%s)
    
    # Check if today is the 1st of the month
    check_day
    
    # Create backup directory
    create_backup_dir
    
    # Backup beta database
    backup_beta_database
    
    # Backup beta codebase
    backup_beta_codebase
    
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
    
    success "Monthly beta environment backup completed successfully in ${DURATION} seconds!"
    log "Backup location: $BACKUP_DIR"
    log "iDrive location: $S3_BUCKET/beta-$DATE/"
    log "Log file: $LOG_FILE"
}

# === Run main function ===
main "$@"
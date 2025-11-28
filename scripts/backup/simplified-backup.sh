#!/bin/bash

# Simplified Backup Script for Magento with Integrated Upload
# This script creates essential backups and uploads directly to iDrive

set -e

# === Configuration ===
PROJECT_ROOT="/home/technadminy7/public_html"
DATE=$(date +%F)
DATETIME=$(date +%F-%H-%M-%S)
BACKUP_DIR="/backup/$DATE"
LOG_FILE="${PROJECT_ROOT}/var/log/backup.log"

# Create log directory if it doesn't exist
mkdir -p "${PROJECT_ROOT}/var/log"

# === iDrive S3 Configuration ===
AWS_CMD="/usr/local/bin/aws"
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

# === Retention Policy ===
DAILY_RETENTION=7      # Keep daily backups for 7 days

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
    echo -e "${YELLOW}[$(date '+%Y-%m-%d %H:%M:%S')] ⚠️  WARNING: $1${NC}" | tee -a "$LOG_FILE"
}

# Create backup directories
create_backup_dirs() {
    log "Creating backup directories..."
    
    # Create backup directory with proper permissions
    sudo mkdir -p "$BACKUP_DIR"
    sudo chown technadminy7. "$BACKUP_DIR"
    sudo chmod 755 "$BACKUP_DIR"
    
    # Create subdirectories
    mkdir -p "$BACKUP_DIR"/{database,config,source,media,logs}
    
    success "Backup directories created"
}

# Create database backup
create_database_backup() {
    log "Creating database backup..."
    
    # Database backup
    "$MYSQL_BIN" \
        --host="$MYSQL_HOST" \
        --port="$MYSQL_PORT" \
        --user="$MYSQL_USER" \
        --password="$MYSQL_PASS" \
        --single-transaction \
        --routines \
        --triggers \
        "$MYSQL_DB" > "$BACKUP_DIR/database/database-$DATETIME.sql" || die "Failed to create database backup"
    
    success "Database backup created: database-$DATETIME.sql"
}

# Create file backups
create_file_backups() {
    log "Creating file backups..."
    
    # Configuration files backup
    cd "$PROJECT_ROOT"
    tar -czf "$BACKUP_DIR/config/config-$DATETIME.tar.gz" \
        --exclude="var/cache/*" \
        --exclude="var/page_cache/*" \
        --exclude="var/session/*" \
        --exclude="pub/media/*" \
        --exclude="var/log/*" \
        --exclude="var/report/*" \
        --exclude="var/tmp/*" \
        --exclude="var/backups/*" \
        --exclude="node_modules/*" \
        --exclude=".git/*" \
        app/etc/* 2>/dev/null || die "Failed to create config backup"
        
    success "Config backup created: config-$DATETIME.tar.gz"
    
    # Essential source code backup
    log "Creating essential source code backup..."
    tar -czf "$BACKUP_DIR/source/source-$DATETIME.tar.gz" \
        --exclude="var/cache/*" \
        --exclude="var/page_cache/*" \
        --exclude="var/session/*" \
        --exclude="pub/media/*" \
        --exclude="var/log/*" \
        --exclude="var/report/*" \
        --exclude="var/tmp/*" \
        --exclude="var/backups/*" \
        --exclude="node_modules/*" \
        --exclude=".git/*" \
        --exclude="pub/static/*" \
        app/code/Mab/ \
        app/code/Amasty/ \
        app/design/ \
        pub/errors/ 2>/dev/null || warning "Failed to create essential source code backup"
        
    success "Essential source code backup created: source-$DATETIME.tar.gz"
    
    # PIMCORE backup
    if [ -d "/home/pim/public_html" ]; then
        log "Creating PIMCORE backup..."
        tar -czf "$BACKUP_DIR/source/pimcore-$DATETIME.tar.gz" \
            -C "/home/pim" public_html 2>/dev/null || warning "Failed to create PIMCORE backup"
        success "PIMCORE backup created: pimcore-$DATETIME.tar.gz"
    else
        warning "PIMCORE directory not found, skipping PIMCORE backup"
    fi
    
    # BETA backup
    if [ -d "/home/beta/public_html" ]; then
        log "Creating BETA backup..."
        tar -czf "$BACKUP_DIR/source/beta-$DATETIME.tar.gz" \
            -C "/home/beta" public_html 2>/dev/null || warning "Failed to create BETA backup"
        success "BETA backup created: beta-$DATETIME.tar.gz"
    else
        warning "BETA directory not found, skipping BETA backup"
    fi
    
    # Media files backup - only important directories
    if [ -d "$PROJECT_ROOT/pub/media" ]; then
        log "Creating media backup..."
        
        # Catalog images are the most important
        if [ -d "$PROJECT_ROOT/pub/media/catalog" ]; then
            tar -czf "$BACKUP_DIR/media/catalog-$DATETIME.tar.gz" \
                -C "$PROJECT_ROOT/pub/media" catalog 2>/dev/null || warning "Failed to create catalog backup"
        fi
        
        # Other important media directories
        for dir in wysiwyg promobanners lookbook; do
            if [ -d "$PROJECT_ROOT/pub/media/$dir" ]; then
                tar -czf "$BACKUP_DIR/media/$dir-$DATETIME.tar.gz" \
                    -C "$PROJECT_ROOT/pub/media" "$dir" 2>/dev/null || warning "Failed to create $dir backup"
            fi
        done
        
        success "Media backup created"
    else
        warning "Media directory not found, skipping media backup"
    fi
    
    # Logs backup
    if [ -d "$PROJECT_ROOT/var/log" ]; then
        log "Creating logs backup..."
        
        # Only backup important logs
        mkdir -p "/tmp/logs-backup-$DATETIME"
        
        # Copy important logs
        for log_file in system.log exception.log cron.log debug.log; do
            if [ -f "$PROJECT_ROOT/var/log/$log_file" ]; then
                cp "$PROJECT_ROOT/var/log/$log_file" "/tmp/logs-backup-$DATETIME/" 2>/dev/null || true
            fi
        done
        
        # Create archive of logs
        tar -czf "$BACKUP_DIR/logs/logs-$DATETIME.tar.gz" -C "/tmp" "logs-backup-$DATETIME" 2>/dev/null || warning "Failed to create logs backup"
        
        # Clean up temporary directory
        rm -rf "/tmp/logs-backup-$DATETIME"
        
        success "Logs backup created: logs-$DATETIME.tar.gz"
    else
        warning "Logs directory not found, skipping logs backup"
    fi
}

# Upload backup directly to iDrive
upload_to_idrive() {
    log "Uploading backup to iDrive..."
    
    # Upload database directory
    if [ -d "$BACKUP_DIR/database" ] && [ "$(ls -A "$BACKUP_DIR/database")" ]; then
        log "Uploading database directory..."
        sudo "$AWS_CMD" s3 sync "$BACKUP_DIR/database" "$S3_BUCKET/$DATE/database/" \
            --endpoint-url "$S3_ENDPOINT" \
            --delete || die "Failed to upload database directory to iDrive"
        success "Database directory uploaded to iDrive"
    fi
    
    # Upload config directory
    if [ -d "$BACKUP_DIR/config" ] && [ "$(ls -A "$BACKUP_DIR/config")" ]; then
        log "Uploading config directory..."
        sudo "$AWS_CMD" s3 sync "$BACKUP_DIR/config" "$S3_BUCKET/$DATE/config/" \
            --endpoint-url "$S3_ENDPOINT" \
            --delete || die "Failed to upload config directory to iDrive"
        success "Config directory uploaded to iDrive"
    fi
    
    # Upload source directory
    if [ -d "$BACKUP_DIR/source" ] && [ "$(ls -A "$BACKUP_DIR/source")" ]; then
        log "Uploading source directory..."
        sudo "$AWS_CMD" s3 sync "$BACKUP_DIR/source" "$S3_BUCKET/$DATE/source/" \
            --endpoint-url "$S3_ENDPOINT" \
            --delete || die "Failed to upload source directory to iDrive"
        success "Source directory uploaded to iDrive"
    fi
    
    # Upload media directory
    if [ -d "$BACKUP_DIR/media" ] && [ "$(ls -A "$BACKUP_DIR/media")" ]; then
        log "Uploading media directory..."
        sudo "$AWS_CMD" s3 sync "$BACKUP_DIR/media" "$S3_BUCKET/$DATE/media/" \
            --endpoint-url "$S3_ENDPOINT" \
            --delete || die "Failed to upload media directory to iDrive"
        success "Media directory uploaded to iDrive"
    fi
    
    # Upload logs directory
    if [ -d "$BACKUP_DIR/logs" ] && [ "$(ls -A "$BACKUP_DIR/logs")" ]; then
        log "Uploading logs directory..."
        sudo "$AWS_CMD" s3 sync "$BACKUP_DIR/logs" "$S3_BUCKET/$DATE/logs/" \
            --endpoint-url "$S3_ENDPOINT" \
            --delete || die "Failed to upload logs directory to iDrive"
        success "Logs directory uploaded to iDrive"
    fi
    
    success "Backup uploaded to iDrive"
}

# Cleanup old local backups
cleanup_local_backups() {
    log "Cleaning up local backups older than $DAILY_RETENTION days..."
    
    # Clean up backups with standard date format (YYYY-MM-DD)
    find /backup -maxdepth 1 -type d -name "????-??-??" -mtime +$DAILY_RETENTION -exec sudo rm -rf {} + 2>/dev/null || true
    
    # Clean up backups with extended format (YYYY-MM-DD-*)
    find /backup -maxdepth 1 -type d -name "????-??-??-*" -mtime +$DAILY_RETENTION -exec sudo rm -rf {} + 2>/dev/null || true
    
    success "Local backups cleanup completed"
}

# Main function
main() {
    log "=== Starting Simplified Backup Process ==="
    
    # Create backup directories
    create_backup_dirs
    
    # Create database backup
    create_database_backup
    
    # Create file backups
    create_file_backups
    
    # Upload to iDrive
    upload_to_idrive
    
    # Cleanup local backups
    cleanup_local_backups
    
    success "Simplified backup process completed successfully"
    log "=== Backup Process Finished ==="
}

# Run main function
main "$@"
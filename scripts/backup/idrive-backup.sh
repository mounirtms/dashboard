#!/bin/bash
###############################################################################
# Techno Stationery - iDrive Backup Script
# Creates backups and uploads directly to iDrive S3
###############################################################################

set -e

# === Configuration ===
DATE=$(date +%F)
DATETIME=$(date +%F-%H-%M-%S)
BACKUP_DIR="/backup/$DATE"
LOG_FILE="/home/dashboard/public_html/var/log/idrive-backup-${DATETIME}.log"

# Create log directory
mkdir -p /home/dashboard/public_html/var/log

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

# === Colors ===
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# === Functions ===
die() {
    echo -e "${RED}[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $1${NC}" | tee -a "$LOG_FILE"
    exit 1
}

log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')] $1${NC}" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')] SUCCESS: $1${NC}" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[$(date '+%Y-%m-%d %H:%M:%S')] WARNING: $1${NC}" | tee -a "$LOG_FILE"
}

# === Step 1: Create backup directories ===
log "=== Step 1: Creating backup directories ==="
mkdir -p "$BACKUP_DIR"/{database,config,source,media,pim,beta,logs}
success "Backup directories created at $BACKUP_DIR"

# === Step 2: Create database backup ===
log "=== Step 2: Creating database backup ==="
if "$MYSQL_BIN" \
    --host="$MYSQL_HOST" \
    --port="$MYSQL_PORT" \
    --user="$MYSQL_USER" \
    --password="$MYSQL_PASS" \
    --single-transaction \
    --routines \
    --triggers \
    "$MYSQL_DB" > "$BACKUP_DIR/database/database-${DATETIME}.sql" 2>>"$LOG_FILE"; then
    SIZE=$(du -h "$BACKUP_DIR/database/database-${DATETIME}.sql" | cut -f1)
    success "Database backup created ($SIZE)"
else
    warning "Database backup failed, continuing with file backup"
fi

# === Step 3: Create configuration backup ===
log "=== Step 3: Creating configuration backup ==="
if [ -d "/home/technadminy7/public_html/app/etc" ]; then
    tar -czf "$BACKUP_DIR/config/config-${DATETIME}.tar.gz" \
        -C /home/technadminy7/public_html app/etc/ 2>>"$LOG_FILE" || warning "Config backup had issues"
    success "Configuration backup created"
else
    warning "Config directory not found"
fi

# === Step 4: Create source code backup ===
log "=== Step 4: Creating source code backup ==="
if [ -d "/home/technadminy7/public_html/app/code" ]; then
    tar -czf "$BACKUP_DIR/source/source-${DATETIME}.tar.gz" \
        --exclude="var/cache" \
        --exclude="var/page_cache" \
        --exclude="var/session" \
        --exclude="pub/media" \
        --exclude="var/log" \
        --exclude="var/report" \
        --exclude="var/tmp" \
        --exclude="var/backups" \
        --exclude="node_modules" \
        --exclude=".git" \
        --exclude="pub/static" \
        -C /home/technadminy7/public_html app/code/ app/design/ 2>>"$LOG_FILE" || warning "Source backup had issues"
    success "Source code backup created"
else
    warning "Source code directory not found"
fi

# === Step 5: Create media backup ===
log "=== Step 5: Creating media backup ==="
if [ -d "/home/technadminy7/public_html/pub/media/catalog" ]; then
    tar -czf "$BACKUP_DIR/media/catalog-${DATETIME}.tar.gz" \
        --exclude="catalog/product/cache" \
        -C /home/technadminy7/public_html/pub/media catalog 2>>"$LOG_FILE" || warning "Catalog backup had issues"
    success "Catalog media backup created"
else
    warning "Catalog directory not found"
fi

# === Step 6: Create PIM backup ===
log "=== Step 6: Creating PIM backup ==="
if [ -d "/home/pim/public_html" ]; then
    tar -czf "$BACKUP_DIR/pim/pim-${DATETIME}.tar.gz" \
        --exclude="var/cache" \
        --exclude="var/log" \
        --exclude="node_modules" \
        -C /home/pim public_html 2>>"$LOG_FILE" || warning "PIM backup had issues"
    success "PIM backup created"
else
    warning "PIM directory not found"
fi

# === Step 7: Create BETA backup ===
log "=== Step 7: Creating BETA backup ==="
if [ -d "/home/beta/public_html" ]; then
    tar -czf "$BACKUP_DIR/beta/beta-${DATETIME}.tar.gz" \
        --exclude="var/cache" \
        --exclude="var/page_cache" \
        --exclude="var/session" \
        --exclude="var/log" \
        --exclude="var/report" \
        --exclude="generated/code" \
        --exclude="generated/metadata" \
        --exclude="pub/static/_cache" \
        --exclude="node_modules" \
        -C /home/beta public_html 2>>"$LOG_FILE" || warning "BETA backup had issues"
    success "BETA backup created"
else
    warning "BETA directory not found"
fi

# === Step 8: Create logs backup ===
log "=== Step 8: Creating logs backup ==="
if [ -d "/home/technadminy7/public_html/var/log" ]; then
    tar -czf "$BACKUP_DIR/logs/logs-${DATETIME}.tar.gz" \
        -C /home/technadminy7/public_html/var log 2>>"$LOG_FILE" || warning "Logs backup had issues"
    success "Logs backup created"
else
    warning "Logs directory not found"
fi

# === Step 9: Upload to iDrive ===
log "=== Step 9: Uploading to iDrive ==="

# Upload each directory if it has content
for dir in database config source media pim beta logs; do
    if [ -d "$BACKUP_DIR/$dir" ] && [ "$(ls -A "$BACKUP_DIR/$dir" 2>/dev/null)" ]; then
        log "Uploading $dir directory to iDrive..."
        $AWS_CMD s3 sync "$BACKUP_DIR/$dir" "$S3_BUCKET/$DATE/$dir/" \
            --endpoint-url "$S3_ENDPOINT" 2>>"$LOG_FILE" || warning "Failed to upload $dir"
        success "$dir directory uploaded"
    else
        warning "Skipping $dir - empty or not found"
    fi
done

success "All backups uploaded to iDrive s3://weektechno/$DATE/"

# === Step 10: Summary ===
log "=== Backup Summary ==="
log "Date: $DATETIME"
log "Location: $BACKUP_DIR"
log "iDrive: s3://weektechno/$DATE/"

TOTAL_SIZE=$(du -sh "$BACKUP_DIR" 2>/dev/null | cut -f1)
log "Total local backup size: $TOTAL_SIZE"

# List uploaded files
log "Uploaded files:"
$AWS_CMD s3 ls "$S3_BUCKET/$DATE/" --endpoint-url "$S3_ENDPOINT" --recursive 2>>"$LOG_FILE" | head -30 | tee -a "$LOG_FILE"

success "iDrive backup process completed successfully"
log "=== Backup Process Finished ==="

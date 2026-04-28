#!/bin/bash
###############################################################################
# Techno Stationery - Database Backup Script
# Backs up all production databases and uploads to iDrive
###############################################################################

set -euo pipefail

# === Configuration ===
DATE=$(date +%F)
DATETIME=$(date +%F-%H-%M-%S)
BACKUP_DIR="/backup/$DATE/databases"
LOG_FILE="/home/dashboard/public_html/var/log/db-backup-${DATETIME}.log"

# Create directories
mkdir -p "$BACKUP_DIR"
mkdir -p /home/dashboard/public_html/var/log

# === iDrive S3 Configuration ===
export AWS_ACCESS_KEY_ID="prQjrOCZTP1yTOPfYvRl"
export AWS_SECRET_ACCESS_KEY="41VJfqqxpbGse3G1UUTtdCLWUNGhtxnuf5DssWpT"
export AWS_DEFAULT_REGION="us-west-1"

S3_BUCKET="s3://weektechno"
S3_ENDPOINT="https://l0y0.la.idrivee2-27.com"
AWS_CMD="/usr/local/bin/aws"

# === MariaDB Configuration ===
MYSQL_BIN='/opt/mariadb10.6/mariadb/bin/mysqldump'
MYSQL_USER='root'
MYSQL_PASS='YourNewStrongPassword'
MYSQL_HOST='127.0.0.1'
MYSQL_PORT='3307'

# === Colors ===
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# === Functions ===
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')] $1${NC}" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')] SUCCESS: $1${NC}" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[$(date '+%Y-%m-%d %H:%M:%S')] WARNING: $1${NC}" | tee -a "$LOG_FILE"
}

die() {
    echo -e "${RED}[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $1${NC}" | tee -a "$LOG_FILE"
    exit 1
}

# === Parse Arguments ===
UPLOAD_TO_IDRIVE=false
DATABASES=()

usage() {
    echo "Usage: $0 [OPTIONS] [DATABASE_NAMES...]"
    echo ""
    echo "Options:"
    echo "  --upload          Upload to iDrive after backup"
    echo "  --all             Backup all production databases"
    echo "  --help            Show this help message"
    echo ""
    echo "Available databases:"
    echo "  technadminy7_dBT8x12y22  (Production)"
    echo "  beta_dBT8x12y22          (Beta)"
    echo "  akeneo_pim               (PIM)"
    echo ""
    echo "Examples:"
    echo "  $0 --all --upload                    # Backup all and upload"
    echo "  $0 technadminy7_dBT8x12y22           # Backup specific database"
    echo "  $0 --all                             # Backup all locally"
    exit 0
}

while [[ $# -gt 0 ]]; do
    case $1 in
        --all)
            DATABASES=("technadminy7_dBT8x12y22" "beta_dBT8x12y22" "akeneo_pim")
            shift
            ;;
        --upload)
            UPLOAD_TO_IDRIVE=true
            shift
            ;;
        --help)
            usage
            ;;
        *)
            DATABASES+=("$1")
            shift
            ;;
    esac
done

# Default to all databases if none specified
if [[ ${#DATABASES[@]} -eq 0 ]]; then
    DATABASES=("technadminy7_dBT8x12y22" "beta_dBT8x12y22" "akeneo_pim")
    log "No databases specified, backing up all production databases"
fi

# === Step 1: Backup Databases ===
log "=== Database Backup Started ==="
log "Date: $DATETIME"
log "Backup directory: $BACKUP_DIR"
log "Databases: ${DATABASES[*]}"

BACKED_UP=()
FAILED=()

for DB_NAME in "${DATABASES[@]}"; do
    log "Backing up database: $DB_NAME..."
    
    OUTPUT_FILE="$BACKUP_DIR/${DB_NAME}.sql.gz"
    
    if $MYSQL_BIN \
        --host="$MYSQL_HOST" \
        --port="$MYSQL_PORT" \
        --user="$MYSQL_USER" \
        --password="$MYSQL_PASS" \
        --single-transaction \
        --routines \
        --triggers \
        --events \
        "$DB_NAME" 2>>"$LOG_FILE" | pigz -6 --processes 8 > "$OUTPUT_FILE"; then
        
        SIZE=$(du -h "$OUTPUT_FILE" | cut -f1)
        success "Backed up $DB_NAME ($SIZE)"
        BACKED_UP+=("$DB_NAME")
    else
        warning "Failed to backup $DB_NAME"
        FAILED+=("$DB_NAME")
        rm -f "$OUTPUT_FILE"
    fi
done

# === Step 2: Upload to iDrive ===
if [[ "$UPLOAD_TO_IDRIVE" == true ]]; then
    log "=== Uploading to iDrive ==="
    
    if [[ ${#BACKED_UP[@]} -gt 0 ]]; then
        log "Uploading databases to s3://weektechno/$DATE/databases/"
        
        if $AWS_CMD s3 sync "$BACKUP_DIR" "$S3_BUCKET/$DATE/databases/" \
            --endpoint-url "$S3_ENDPOINT" 2>>"$LOG_FILE"; then
            success "All databases uploaded to iDrive"
        else
            warning "Some uploads to iDrive failed"
        fi
    else
        warning "No databases to upload"
    fi
fi

# === Step 3: Summary ===
log "=== Backup Summary ==="
log "Total backed up: ${#BACKED_UP[@]}/${#DATABASES[@]}"
log "Location: $BACKUP_DIR"

if [[ ${#BACKED_UP[@]} -gt 0 ]]; then
    log "Backed up databases:"
    for db in "${BACKED_UP[@]}"; do
        SIZE=$(du -h "$BACKUP_DIR/${db}.sql.gz" 2>/dev/null | cut -f1)
        log "  - $db ($SIZE)"
    done
fi

if [[ ${#FAILED[@]} -gt 0 ]]; then
    warning "Failed databases:"
    for db in "${FAILED[@]}"; do
        warning "  - $db"
    done
fi

if [[ "$UPLOAD_TO_IDRIVE" == true ]]; then
    log "iDrive location: s3://weektechno/$DATE/databases/"
fi

if [[ ${#FAILED[@]} -eq 0 ]]; then
    success "Database backup completed successfully"
else
    die "Database backup completed with errors"
fi

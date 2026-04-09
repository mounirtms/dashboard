#!/bin/bash

# Full Migration Script
# Runs all migration scripts in the correct order
# 1. Database migration
# 2. Source code migration
# 3. Media files migration

# Configuration
LOG_FILE="/home/betapublic_html/scripts/migration/full-migration.log"
SCRIPTS_DIR="/home/betapublic_html/scripts/migration"

# Function to log messages
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

# Function to confirm before proceeding
confirm_proceed() {
    read -p "Do you want to proceed with $1? (yes/no): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log_message "Migration cancelled by user"
        exit 1
    fi
}

# Start migration process
log_message "========================================="
log_message "Starting full migration process"
log_message "========================================="

# Confirm before starting
echo "This script will perform a full migration:"
echo "1. Database migration (technadminy7_dBT8x12y22 -> beta_dBT8x12y22)"
echo "2. Source code migration (excluding core config and specified files)"
echo "3. Media files migration"
echo ""
echo "IMPORTANT: Ensure you have backups before proceeding!"
echo ""

# Set executable permissions for all scripts
chmod +x "$SCRIPTS_DIR"/*.sh

# Run database migration
log_message "Starting database migration..."
if [ -f "$SCRIPTS_DIR/db-migrate.sh" ]; then
    "$SCRIPTS_DIR/db-migrate.sh"
    if [ $? -eq 0 ]; then
        log_message "Database migration completed successfully"
    else
        log_message "ERROR: Database migration failed"
        exit 1
    fi
else
    log_message "ERROR: Database migration script not found"
    exit 1
fi

# Run source code migration
log_message "Starting source code migration..."
if [ -f "$SCRIPTS_DIR/code-migrate.sh" ]; then
    "$SCRIPTS_DIR/code-migrate.sh"
    if [ $? -eq 0 ]; then
        log_message "Source code migration completed successfully"
    else
        log_message "ERROR: Source code migration failed"
        exit 1
    fi
else
    log_message "ERROR: Source code migration script not found"
    exit 1
fi

# Run media files migration
log_message "Starting media files migration..."
if [ -f "$SCRIPTS_DIR/media-migrate.sh" ]; then
    "$SCRIPTS_DIR/media-migrate.sh"
    if [ $? -eq 0 ]; then
        log_message "Media files migration completed successfully"
    else
        log_message "ERROR: Media files migration failed"
        exit 1
    fi
else
    log_message "ERROR: Media files migration script not found"
    exit 1
fi

log_message "========================================="
log_message "Full migration process completed"
log_message "========================================="

echo ""
echo "Migration completed successfully!"
echo "Please verify the migrated data and test your beta environment."
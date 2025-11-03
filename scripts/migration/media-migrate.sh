#!/bin/bash

# Media Files Migration Script
# Migrates media files from technadminy7/public_html/pub/media to beta installation
# Excludes certain files and directories as specified

# Configuration
SOURCE_MEDIA_DIR="/home/technadminy7/public_html/pub/media"
TARGET_MEDIA_DIR="/home/technadminy7/beta/pub/media"
LOG_FILE="/home/technadminy7/public_html/scripts/migration/media-migration.log"

# Directories and files to exclude
EXCLUDE_PATTERNS=(
    ".git/*"
    ".idea/*"
    ".vscode/*"
    "*.log"
    "*.bak"
    "*.swp"
    ".DS_Store"
    "Thumbs.db"
)

# Function to log messages
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

# Start migration
log_message "Starting media files migration from $SOURCE_MEDIA_DIR to $TARGET_MEDIA_DIR"

# Create target directory if it doesn't exist
if [ ! -d "$TARGET_MEDIA_DIR" ]; then
    log_message "Creating target media directory: $TARGET_MEDIA_DIR"
    mkdir -p "$TARGET_MEDIA_DIR"
fi

# Execute migration with rsync
log_message "Executing media files migration..."

# First, copy the main .htaccess file
if [ -f "$SOURCE_MEDIA_DIR/.htaccess" ]; then
    log_message "Copying .htaccess file..."
    cp "$SOURCE_MEDIA_DIR/.htaccess" "$TARGET_MEDIA_DIR/.htaccess"
    chmod 644 "$TARGET_MEDIA_DIR/.htaccess"
fi

# Use rsync to migrate media files with exclusions
rsync -avz --delete \
    --exclude='.git/' \
    --exclude='.idea/' \
    --exclude='.vscode/' \
    --exclude='*.log' \
    --exclude='*.bak' \
    --exclude='*.swp' \
    --exclude='.DS_Store' \
    --exclude='Thumbs.db' \
    "$SOURCE_MEDIA_DIR/" "$TARGET_MEDIA_DIR/" | while read line; do
        log_message "RSYNC: $line"
    done

if [ $? -eq 0 ]; then
    log_message "Media files migration completed successfully"
else
    log_message "ERROR: Media files migration failed"
    exit 1
fi

# Set proper permissions
log_message "Setting proper permissions..."
find "$TARGET_MEDIA_DIR" -type f -exec chmod 644 {} \;
find "$TARGET_MEDIA_DIR" -type d -exec chmod 755 {} \;

log_message "Media files migration process completed"#!/bin/bash

# Media Files Migration Script
# Migrates media files from technadminy7/public_html/pub/media to beta installation
# Excludes certain files and directories as specified

# Configuration
SOURCE_MEDIA_DIR="/home/technadminy7/public_html/pub/media"
TARGET_MEDIA_DIR="/home/technadminy7/beta/pub/media"
LOG_FILE="/home/technadminy7/public_html/scripts/migration/media-migration.log"

# Directories and files to exclude
EXCLUDE_PATTERNS=(
    ".git/*"
    ".idea/*"
    ".vscode/*"
    "*.log"
    "*.bak"
    "*.swp"
    ".DS_Store"
    "Thumbs.db"
)

# Function to log messages
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

# Start migration
log_message "Starting media files migration from $SOURCE_MEDIA_DIR to $TARGET_MEDIA_DIR"

# Create target directory if it doesn't exist
if [ ! -d "$TARGET_MEDIA_DIR" ]; then
    log_message "Creating target media directory: $TARGET_MEDIA_DIR"
    mkdir -p "$TARGET_MEDIA_DIR"
fi

# Execute migration with rsync
log_message "Executing media files migration..."

# First, copy the main .htaccess file
if [ -f "$SOURCE_MEDIA_DIR/.htaccess" ]; then
    log_message "Copying .htaccess file..."
    cp "$SOURCE_MEDIA_DIR/.htaccess" "$TARGET_MEDIA_DIR/.htaccess"
    chmod 644 "$TARGET_MEDIA_DIR/.htaccess"
fi

# Use rsync to migrate media files with exclusions
rsync -avz --delete \
    --exclude='.git/' \
    --exclude='.idea/' \
    --exclude='.vscode/' \
    --exclude='*.log' \
    --exclude='*.bak' \
    --exclude='*.swp' \
    --exclude='.DS_Store' \
    --exclude='Thumbs.db' \
    "$SOURCE_MEDIA_DIR/" "$TARGET_MEDIA_DIR/" | while read line; do
        log_message "RSYNC: $line"
    done

if [ $? -eq 0 ]; then
    log_message "Media files migration completed successfully"
else
    log_message "ERROR: Media files migration failed"
    exit 1
fi

# Set proper permissions
log_message "Setting proper permissions..."
find "$TARGET_MEDIA_DIR" -type f -exec chmod 644 {} \;
find "$TARGET_MEDIA_DIR" -type d -exec chmod 755 {} \;

log_message "Media files migration process completed"
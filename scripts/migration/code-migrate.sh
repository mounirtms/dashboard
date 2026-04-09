#!/bin/bash

# Source Code Migration Script
# Migrates source code from betapublic_html to beta installation
# Excludes certain files and directories as specified

# Configuration
SOURCE_DIR="/home/betapublic_html"
TARGET_DIR="/home/betabeta"
LOG_FILE="/home/betapublic_html/scripts/migration/code-migration.log"

# Directories and files to exclude
EXCLUDE_PATTERNS=(
    "app/etc/env.php"
    "var/*"
    "pub/static/*"
    "generated/*"
    "vendor/*"
    "node_modules/*"
    ".git/*"
    ".idea/*"
    ".vscode/*"
    "pub/media/*"
    "*.log"
    "*.bak"
    "*.swp"
    ".DS_Store"
    "Thumbs.db"
    "sitemap*"
    "robots.txt"
    "pub/robots.txt"
    "pub/sitemap*"
    "auth*"
    "key*"
    "composer.lock"
    "/app/code/Magento/TestModule*"
    "en.php"
)

# Function to log messages
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

# Start migration
log_message "Starting source code migration from $SOURCE_DIR to $TARGET_DIR"

# Create target directory if it doesn't exist
if [ ! -d "$TARGET_DIR" ]; then
    log_message "Creating target directory: $TARGET_DIR"
    mkdir -p "$TARGET_DIR"
fi

# Build rsync exclude options
EXCLUDE_OPTS=""
for pattern in "${EXCLUDE_PATTERNS[@]}"; do
    EXCLUDE_OPTS="$EXCLUDE_OPTS --exclude='$pattern'"
done

# Execute migration with rsync
log_message "Executing source code migration..."
rsync -avz --delete \
    --exclude='app/etc/env.php' \
    --exclude='var/' \
    --exclude='pub/static/' \
    --exclude='generated/' \
    --exclude='vendor/' \
    --exclude='node_modules/' \
    --exclude='.git/' \
    --exclude='.idea/' \
    --exclude='.vscode/' \
    --exclude='*.log' \
    --exclude='*.bak' \
    --exclude='*.swp' \
    --exclude='.DS_Store' \
    --exclude='Thumbs.db' \
    --exclude='sitemap*' \
    --exclude='robots.txt' \
    --exclude='pub/robots.txt' \
    --exclude='pub/sitemap*' \
    --exclude='auth*' \
    --exclude='key*' \
    --exclude='composer.lock' \
    --exclude='/app/code/Magento/TestModule*' \
    --exclude='en.php' \
    "$SOURCE_DIR/" "$TARGET_DIR/" | while read line; do
        log_message "RSYNC: $line"
    done

if [ $? -eq 0 ]; then
    log_message "Source code migration completed successfully"
else
    log_message "ERROR: Source code migration failed"
    exit 1
fi

# Set proper permissions
log_message "Setting proper permissions..."
find "$TARGET_DIR" -type f -exec chmod 644 {} \;
find "$TARGET_DIR" -type d -exec chmod 755 {} \;
chmod +x "$TARGET_DIR/scripts/migration/"*.sh 2>/dev/null || true

log_message "Source code migration process completed"
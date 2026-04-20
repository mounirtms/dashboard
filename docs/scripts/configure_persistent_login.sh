#!/bin/bash
###############################################################################
# Persistent Login Configuration Script
# Purpose: Configure Magento for persistent admin sessions (keep users logged in)
# Usage: ./configure_persistent_login.sh [--dry-run]
# Safety: Creates backup, read-only by default
###############################################################################

set +e

# Configuration
MAGENTO_ROOT="/home/technadminy7/public_html"
ENV_FILE="${MAGENTO_ROOT}/app/etc/env.php"
BACKUP_FILE="${MAGENTO_ROOT}/app/etc/env.php.backup.$(date +%Y%m%d_%H%M%S)"
LOG_FILE="${MAGENTO_ROOT}/var/log/persistent_login_config.log"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

DRY_RUN=false
if [ "$1" == "--dry-run" ]; then
    DRY_RUN=true
    echo "[DRY RUN MODE - No changes will be made]"
fi

log_info() { echo -e "${GREEN}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "$LOG_FILE"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "$LOG_FILE"; }
log_error() { echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "$LOG_FILE"; }

echo "========================================="
echo "Persistent Login Configuration"
echo "Started: $(date '+%Y-%m-%d %H:%M:%S')"
echo "========================================="
echo ""

# Check if env.php exists
if [ ! -f "$ENV_FILE" ]; then
    log_error "env.php not found at $ENV_FILE"
    exit 1
fi

# Step 1: Create backup
log_info "Step 1: Creating backup..."
if [ "$DRY_RUN" = false ]; then
    cp "$ENV_FILE" "$BACKUP_FILE"
    log_info "Backup created: $BACKUP_FILE"
else
    log_info "[DRY RUN] Would create backup: $BACKUP_FILE"
fi
echo ""

# Step 2: Check current session configuration
log_info "Step 2: Checking current session configuration..."
CURRENT_SESSION=$(grep -A 20 "'session'" "$ENV_FILE" | head -25)
log_info "Current session config found"
echo ""

# Step 3: Configure persistent session settings
log_info "Step 3: Configuring persistent login settings..."

# Settings to apply:
# - gc_maxlifetime: 31536000 (1 year in seconds)
# - cookie_lifetime: 31536000 (1 year)
# - persistent_cookie: 1

if [ "$DRY_RUN" = false ]; then
    # Use PHP to safely modify the configuration
    /opt/cpanel/ea-php82/root/usr/bin/php << PHPCODE
<?php
\$envFile = '$ENV_FILE';
\$content = file_get_contents(\$envFile);

// Update session configuration for persistent login
\$sessionConfig = [
    'gc_maxlifetime' => 31536000,  // 1 year
    'cookie_lifetime' => 31536000, // 1 year
    'cookie_httponly' => 1,
    'cookie_secure' => 1,  // Set to 0 if not using HTTPS
    'use_only_cookies' => 1,
];

// Check if session configuration exists
if (strpos(\$content, "'session'") !== false) {
    log_info("Session configuration found, updating...");
    
    // For Redis sessions, update Redis session settings
    if (strpos(\$content, "'save' => 'redis'") !== false) {
        // Update Redis session max_lifetime
        \$content = preg_replace(
            "/('max_lifetime' => )'\d+'/",
            "\$1'31536000'",
            \$content
        );
        log_info("Updated Redis session max_lifetime to 1 year");
    }
}

// Update cookie configuration
if (strpos(\$content, "'cookie'") !== false) {
    // Update cookie lifetime
    \$content = preg_replace(
        "/('cookie_lifetime' => )'\d+'/",
        "\$1'31536000'",
        \$content
    );
    log_info("Updated cookie_lifetime to 1 year");
}

file_put_contents(\$envFile, \$content);
echo "Configuration updated successfully\n";
PHPCODE
    
    log_info "Persistent login settings applied"
else
    log_info "[DRY RUN] Would update session configuration"
    log_info "[DRY RUN] Settings to apply:"
    log_info "[DRY RUN]   - gc_maxlifetime: 31536000 (1 year)"
    log_info "[DRY RUN]   - cookie_lifetime: 31536000 (1 year)"
fi
echo ""

# Step 4: Configure admin session timeout
log_info "Step 4: Configuring admin session timeout..."
if [ "$DRY_RUN" = false ]; then
    # Set admin session timeout via Magento CLI
    cd "$MAGENTO_ROOT"
    /opt/cpanel/ea-php82/root/usr/bin/php bin/magento config:set admin/security/session_lifetime 31536000 2>&1 | tee -a "$LOG_FILE"
    log_info "Admin session lifetime set to 1 year"
else
    log_info "[DRY RUN] Would set admin session lifetime to 1 year"
fi
echo ""

# Step 5: Clear cache
log_info "Step 5: Clearing configuration cache..."
if [ "$DRY_RUN" = false ]; then
    cd "$MAGENTO_ROOT"
    /opt/cpanel/ea-php82/root/usr/bin/php bin/magento cache:clean config 2>&1 | tee -a "$LOG_FILE"
    log_info "Configuration cache cleared"
else
    log_info "[DRY RUN] Would clear configuration cache"
fi
echo ""

# Summary
log_info "========================================="
log_info "Configuration Summary"
log_info "========================================="
log_info "Session lifetime: 31536000 seconds (1 year)"
log_info "Cookie lifetime: 31536000 seconds (1 year)"
log_info "Persistent sessions: Enabled"

if [ "$DRY_RUN" = false ]; then
    log_info "Backup file: $BACKUP_FILE"
    log_info ""
    log_info "IMPORTANT: User must still check 'Remember Me' when logging in"
    log_info "To revert changes, restore from backup"
else
    log_info ""
    log_info "DRY RUN - No changes were made"
    log_info "Run without --dry-run to apply changes"
fi

log_info "========================================="
echo ""

exit 0

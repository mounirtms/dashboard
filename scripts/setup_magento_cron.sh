#!/bin/bash
###############################################################################
# Magento Cron Setup Script
# Purpose: Install and configure Magento cron jobs for technadminy7 user
# Usage: sudo ./setup_magento_cron.sh
###############################################################################

set -e

# Configuration
MAGENTO_USER="technadminy7"
MAGENTO_ROOT="/home/technadminy7/public_html"
PHP_PATH="/opt/cpanel/ea-php82/root/usr/bin/php"
LOG_DIR="/home/technadminy7/public_html/var/log"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

echo "========================================="
echo "Magento Cron Setup for ${MAGENTO_USER}"
echo "========================================="
echo ""

# Check if running as root
if [ "$(id -u)" -ne 0 ]; then
    log_error "This script must be run as root"
    exit 1
fi

# Clear existing Magento cron jobs for this user
log_info "Clearing existing Magento cron entries..."
crontab -u ${MAGENTO_USER} -l 2>/dev/null | grep -v "bin/magento cron:run" | crontab -u ${MAGENTO_USER} - || true

# Get current crontab
CURRENT_CRON=$(crontab -u ${MAGENTO_USER} -l 2>/dev/null || echo "")

# Create new crontab with Magento cron
cat > /tmp/magento_cron.$$ << EOF
# Magento 2 Cron Jobs
# Generated: $(date '+%Y-%m-%d %H:%M:%S')

# Main Magento cron (runs every 10 minutes)
*/10 * * * * ${PHP_PATH} ${MAGENTO_ROOT}/bin/magento cron:run 2>&1 | grep -v "Ran jobs by schedule" >> ${LOG_DIR}/magento.cron.log

# Magento cron for specific groups (staggered)
*/10 * * * * ${PHP_PATH} ${MAGENTO_ROOT}/bin/magento setup:cron:run >> ${LOG_DIR}/magento.setup.log 2>&1

# EOF
EOF

# Install the crontab
crontab -u ${MAGENTO_USER} /tmp/magento_cron.$$
rm -f /tmp/magento_cron.$$

log_info "Magento cron jobs installed successfully!"
echo ""
log_info "Installed cron jobs:"
crontab -u ${MAGENTO_USER} -l | grep -E "magento|cron"
echo ""

# Verify cron is running on the system
if pgrep -x "crond" > /dev/null; then
    log_info "Cron daemon is running"
else
    log_warn "Cron daemon is NOT running - starting it..."
    systemctl start crond || service cron start || true
fi

log_info "Setup complete!"
log_info "Verify with: crontab -u ${MAGENTO_USER} -l"

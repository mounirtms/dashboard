#!/bin/bash
###############################################################################
# Varnish Memory Configuration Script
# Purpose: Configure Varnish storage to use 8GB RAM
# Usage: sudo ./configure_varnish_memory.sh [SIZE_GB]
# Example: sudo ./configure_varnish_memory.sh 8
###############################################################################

set -e

# Configuration
TARGET_GB=${1:-8}  # Default 8GB
VARNISH_SECRET="/etc/varnish/secret"
VARNISH_ADMIN="127.0.0.1:6082"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

echo "========================================="
echo "Varnish Memory Configuration"
echo "========================================="
echo ""

# Check if running as root
if [ "$(id -u)" -ne 0 ]; then
    log_error "This script must be run as root"
    exit 1
fi

# Get current Varnish process info
log_info "Current Varnish configuration:"
ps aux | grep varnishd | grep -v grep | head -2
echo ""

# Check current storage
CURRENT_STORAGE=$(ps aux | grep varnishd | grep -oP 'malloc,\K[0-9]+[GM]' | head -1 || echo "4G")
log_info "Current storage: ${CURRENT_STORAGE}"
echo ""

# Calculate storage size
STORAGE_SIZE="${TARGET_GB}G"

log_info "Target storage: ${STORAGE_SIZE}"
echo ""

# Find Varnish configuration file
VARNISH_CONFIG=""
for config in /etc/varnish/default.vcl /etc/default/varnish /etc/sysconfig/varnish; do
    if [ -f "$config" ]; then
        VARNISH_CONFIG="$config"
        break
    fi
done

# Update systemd service if it exists
if [ -f "/etc/systemd/system/varnish.service" ]; then
    log_info "Updating systemd service..."
    sed -i "s/-s malloc,[0-9]*[GM]/-s malloc,${STORAGE_SIZE}/g" /etc/systemd/system/varnish.service
    log_info "Systemd service updated"
elif [ -f "/lib/systemd/system/varnish.service" ]; then
    log_info "Updating lib systemd service..."
    cp /lib/systemd/system/varnish.service /etc/systemd/system/varnish.service
    sed -i "s/-s malloc,[0-9]*[GM]/-s malloc,${STORAGE_SIZE}/g" /etc/systemd/system/varnish.service
    log_info "Systemd service updated"
else
    log_warn "No systemd service found - Varnish may be managed differently"
fi

# Update /etc/default/varnish if it exists
if [ -f "/etc/default/varnish" ]; then
    log_info "Updating /etc/default/varnish..."
    sed -i "s/-s malloc,[0-9]*[GM]/-s malloc,${STORAGE_SIZE}/g" /etc/default/varnish
    log_info "/etc/default/varnish updated"
fi

# Update /etc/sysconfig/varnish if it exists
if [ -f "/etc/sysconfig/varnish" ]; then
    log_info "Updating /etc/sysconfig/varnish..."
    sed -i "s/-s malloc,[0-9]*[GM]/-s malloc,${STORAGE_SIZE}/g" /etc/sysconfig/varnish
    log_info "/etc/sysconfig/varnish updated"
fi

echo ""
log_info "Configuration files updated"
echo ""

# Restart Varnish
log_info "Restarting Varnish service..."
if systemctl restart varnish 2>/dev/null; then
    log_info "Varnish restarted successfully"
else
    log_warn "Could not restart Varnish via systemctl"
    log_info "Please restart Varnish manually:"
    log_info "  systemctl restart varnish"
fi

echo ""

# Verify new configuration
sleep 2
log_info "Verifying new configuration:"
ps aux | grep varnishd | grep -v grep | head -2
echo ""

log_info "Varnish memory configuration complete!"
log_info "New storage size: ${STORAGE_SIZE}"
echo ""
echo "========================================="

#!/bin/bash
###############################################################################
# Redis Memory Configuration Script
# Purpose: Set Redis maxmemory to 8-9GB for multi-site setup
# Usage: sudo ./configure_redis_memory.sh [SIZE_GB]
# Example: sudo ./configure_redis_memory.sh 9
###############################################################################

set -e

# Configuration
REDIS_HOST="127.0.0.1"
REDIS_PORT="6379"
REDIS_CONF="/etc/redis.conf"
TARGET_GB=${1:-9}  # Default 9GB

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

echo "========================================="
echo "Redis Memory Configuration"
echo "========================================="
echo ""

# Check if running as root
if [ "$(id -u)" -ne 0 ]; then
    log_error "This script must be run as root"
    exit 1
fi

# Calculate bytes
TARGET_BYTES=$((TARGET_GB * 1024 * 1024 * 1024))

log_info "Configuring Redis maxmemory to ${TARGET_GB}GB (${TARGET_BYTES} bytes)"
echo ""

# Get current settings
log_info "Current Redis memory settings:"
redis-cli CONFIG GET maxmemory
redis-cli CONFIG GET maxmemory-policy
echo ""

# Set runtime configuration
log_info "Setting runtime configuration..."
redis-cli CONFIG SET maxmemory ${TARGET_BYTES}
redis-cli CONFIG SET maxmemory-policy allkeys-lru
log_info "Runtime configuration updated"
echo ""

# Verify new settings
log_info "Verifying new settings:"
redis-cli CONFIG GET maxmemory
redis-cli CONFIG GET maxmemory-policy
echo ""

# Update configuration file
if [ -f "${REDIS_CONF}" ]; then
    log_info "Updating Redis configuration file: ${REDIS_CONF}"
    
    # Remove old maxmemory settings
    sed -i '/^maxmemory /d' "${REDIS_CONF}" 2>/dev/null || true
    
    # Add new settings
    echo "" >> "${REDIS_CONF}"
    echo "# Auto-configured by configure_redis_memory.sh" >> "${REDIS_CONF}"
    echo "# Date: $(date '+%Y-%m-%d %H:%M:%S')" >> "${REDIS_CONF}"
    echo "maxmemory ${TARGET_BYTES}" >> "${REDIS_CONF}"
    echo "maxmemory-policy allkeys-lru" >> "${REDIS_CONF}"
    
    log_info "Configuration file updated"
else
    log_warn "Redis config file not found at ${REDIS_CONF}"
    log_warn "Settings will be lost on Redis restart"
fi

echo ""
log_info "Redis memory configuration complete!"
log_info "Max memory: ${TARGET_GB}GB"
log_info "Eviction policy: allkeys-lru"
echo ""
echo "========================================="

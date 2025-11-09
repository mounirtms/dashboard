#!/bin/bash

# Maintenance Mode Wrapper Script
# Automatically enables maintenance mode before running commands and disables it after
# Usage: ./maintenance-wrapper.sh "command to run"

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if we're in the right directory
if [ ! -f "bin/magento" ]; then
    error "This script must be run from the Magento root directory"
    exit 1
fi

# Check if command is provided
if [ $# -eq 0 ]; then
    error "Usage: $0 \"command to run\""
    echo "Example: $0 \"php bin/magento setup:upgrade\""
    exit 1
fi

COMMAND="$1"

log "Starting maintenance mode wrapper for command: $COMMAND"

# Enable maintenance mode
log "Enabling maintenance mode..."
php bin/magento maintenance:enable

if [ $? -eq 0 ]; then
    success "Maintenance mode enabled successfully"
else
    error "Failed to enable maintenance mode"
    exit 1
fi

# Wait a moment for the maintenance mode to take effect
sleep 2

# Run the provided command
log "Running command: $COMMAND"
echo "----------------------------------------"
eval $COMMAND
COMMAND_RESULT=$?
echo "----------------------------------------"

if [ $COMMAND_RESULT -eq 0 ]; then
    success "Command executed successfully"
else
    error "Command failed with exit code: $COMMAND_RESULT"
fi

# Disable maintenance mode
log "Disabling maintenance mode..."
php bin/magento maintenance:disable

if [ $? -eq 0 ]; then
    success "Maintenance mode disabled successfully"
else
    error "Failed to disable maintenance mode"
fi

# Final result
if [ $COMMAND_RESULT -eq 0 ]; then
    success "Maintenance wrapper completed successfully!"
else
    error "Maintenance wrapper completed with errors in the command execution"
    exit $COMMAND_RESULT
fi
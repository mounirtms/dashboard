#!/bin/bash

###############################################################################
# Fix Permissions Script
# Fixes file and directory permissions for web application
###############################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}🔒 Fixing File Permissions${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

BASE_DIR="/home/dashboard/public_html"

# React app directory
if [ -d "$BASE_DIR/app" ]; then
    echo -e "${YELLOW}Fixing /app permissions...${NC}"
    find "$BASE_DIR/app" -type d -exec chmod 755 {} \; 2>/dev/null || true
    find "$BASE_DIR/app" -type f -exec chmod 644 {} \; 2>/dev/null || true
    echo -e "${GREEN}✅ /app permissions fixed${NC}"
fi

# Backend directory
if [ -d "$BASE_DIR/backend" ]; then
    echo -e "${YELLOW}Fixing /backend permissions...${NC}"
    find "$BASE_DIR/backend" -type d -exec chmod 755 {} \; 2>/dev/null || true
    find "$BASE_DIR/backend" -type f -exec chmod 644 {} \; 2>/dev/null || true
    
    # Scripts should be executable
    if [ -d "$BASE_DIR/backend/scripts" ]; then
        find "$BASE_DIR/backend/scripts" -type f \( -name "*.js" -o -name "*.sh" \) -exec chmod 755 {} \; 2>/dev/null || true
        echo -e "${GREEN}✅ Backend scripts made executable${NC}"
    fi
    echo -e "${GREEN}✅ /backend permissions fixed${NC}"
fi

# Scripts directory
if [ -d "$BASE_DIR/scripts" ]; then
    echo -e "${YELLOW}Fixing /scripts permissions...${NC}"
    find "$BASE_DIR/scripts" -type f -name "*.sh" -exec chmod 755 {} \; 2>/dev/null || true
    find "$BASE_DIR/scripts" -type f -name "*.php" -exec chmod 755 {} \; 2>/dev/null || true
    echo -e "${GREEN}✅ /scripts permissions fixed${NC}"
fi

# Logs directory
if [ -d "$BASE_DIR/logs" ]; then
    echo -e "${YELLOW}Fixing /logs permissions...${NC}"
    chmod -R 755 "$BASE_DIR/logs" 2>/dev/null || true
    echo -e "${GREEN}✅ /logs permissions fixed${NC}"
fi

# Webapp directory (development)
if [ -d "$BASE_DIR/webapp" ]; then
    echo -e "${YELLOW}Fixing /webapp permissions...${NC}"
    
    # Source files
    find "$BASE_DIR/webapp/src" -type d -exec chmod 755 {} \; 2>/dev/null || true
    find "$BASE_DIR/webapp/src" -type f -exec chmod 644 {} \; 2>/dev/null || true
    
    # Scripts
    if [ -d "$BASE_DIR/webapp/scripts" ]; then
        find "$BASE_DIR/webapp/scripts" -type f -name "*.sh" -exec chmod 755 {} \; 2>/dev/null || true
    fi
    
    # Config files
    [ -f "$BASE_DIR/webapp/package.json" ] && chmod 644 "$BASE_DIR/webapp/package.json"
    [ -f "$BASE_DIR/webapp/vite.config.js" ] && chmod 644 "$BASE_DIR/webapp/vite.config.js"
    
    echo -e "${GREEN}✅ /webapp permissions fixed${NC}"
fi

# Set ownership (if running as root)
echo ""
if [ "$EUID" -eq 0 ]; then
    echo -e "${YELLOW}Setting ownership to dashboard:dashboard...${NC}"
    chown -R dashboard:dashboard "$BASE_DIR"
    echo -e "${GREEN}✅ Ownership set${NC}"
else
    echo -e "${YELLOW}⚠️  Run as root to set ownership:${NC}"
    echo -e "   sudo bash $(readlink -f "$0")"
fi

echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}✅ Permissions Fixed Successfully!${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

exit 0

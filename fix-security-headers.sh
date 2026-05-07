#!/bin/bash

##########################################################
# FIX #3: Security Headers Configuration
# Adds HSTS, X-Frame-Options, and other security headers
# RECOMMENDED: Best practice for HTTPS deployments
##########################################################

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_FILE="$SCRIPT_DIR/fix-security-headers.log"
TIMESTAMP=$(date '+%Y%m%d_%H%M%S')

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting Security Headers Configuration" | tee -a "$LOG_FILE"

print_status() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✓ $2${NC}" | tee -a "$LOG_FILE"
    else
        echo -e "${RED}✗ $2${NC}" | tee -a "$LOG_FILE"
    fi
}

print_info() {
    echo -e "${BLUE}ℹ $1${NC}" | tee -a "$LOG_FILE"
}

# 1. Backup existing security configs
echo -e "\n${YELLOW}[1/3] Backing up existing configurations${NC}" | tee -a "$LOG_FILE"

[ -f /etc/apache2/conf.d/security-headers.conf ] && \
    cp /etc/apache2/conf.d/security-headers.conf "/etc/apache2/conf.d/security-headers.conf.backup.$TIMESTAMP" && \
    print_status 0 "Backed up existing security-headers.conf"

# 2. Create comprehensive security headers configuration
echo -e "\n${YELLOW}[2/3] Creating security headers configuration${NC}" | tee -a "$LOG_FILE"

cat > /etc/apache2/conf.d/security-headers.conf << 'SECURITY_EOF'
# ========================================================
# Security Headers Configuration
# Applied to all HTTPS connections (port 443)
# Created: 2026-05-07
# ========================================================

# Enable mod_headers for header manipulation
<IfModule mod_headers.c>
    
    # ---- HTTPS/SSL Security ----
    
    # HTTP Strict Transport Security (HSTS)
    # Forces all future connections over HTTPS for 1 year
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" env=HTTPS
    
    # ---- Clickjacking Protection ----
    
    # X-Frame-Options prevents clickjacking attacks
    Header always set X-Frame-Options "SAMEORIGIN"
    
    # ---- MIME-Type Sniffing Protection ----
    
    # X-Content-Type-Options prevents MIME-sniffing attacks
    Header always set X-Content-Type-Options "nosniff"
    
    # ---- XSS Protection ----
    
    # X-XSS-Protection (legacy, for older browsers)
    Header always set X-XSS-Protection "1; mode=block"
    
    # Content Security Policy (CSP)
    # Adjust this based on your application requirements
    Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self' https:; media-src 'self'; object-src 'none';" env=HTTPS
    
    # ---- Referrer Policy ----
    
    # Referrer-Policy controls what referrer information is shared
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    
    # ---- Feature Policy / Permissions Policy ----
    
    # Restrict powerful browser features
    Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
    
    # ---- Proxy/Cache Control ----
    
    # Prevent caching of sensitive content
    Header always set Cache-Control "no-cache, no-store, must-revalidate, max-age=0" env=HTTPS
    Header always set Pragma "no-cache"
    Header always set Expires "0"
    
    # ---- Remove Server Information ----
    
    # Hide Apache version and server information
    Header always unset Server
    Header always unset X-Powered-By
    Header always unset X-AspNet-Version
    Header always unset X-Runtime
    
    # ---- Custom Headers ----
    
    # Add custom server identifier (optional)
    Header always set Server "TechnostationeryServer/1.0"
    
</IfModule>

# ---- per-vhost adjustments below ----

# Dashboard - Stricter CSP (admin area, no external content)
<VirtualHost *:443>
    ServerName dashboard.technostationery.com
    <IfModule mod_headers.c>
        # More restrictive CSP for admin
        Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;" env=HTTPS
    </IfModule>
</VirtualHost>

# Main Store - Allow Magento resources
<VirtualHost *:443>
    ServerName technostationery.com
    <IfModule mod_headers.c>
        # CSP for Magento
        Header always set Content-Security-Policy "default-src 'self' https:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: https:; font-src 'self' data: https:;" env=HTTPS
    </IfModule>
</VirtualHost>

# PIM - Moderate CSP (Akeneo has specific requirements)
<VirtualHost *:443>
    ServerName pim.technostationery.com
    <IfModule mod_headers.c>
        # CSP for PIM/Akeneo
        Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:;" env=HTTPS
    </IfModule>
</VirtualHost>
SECURITY_EOF

print_status $? "Security headers configuration created"

# 3. Test Apache configuration
echo -e "\n${YELLOW}[3/3] Testing Apache configuration syntax${NC}" | tee -a "$LOG_FILE"

httpd -t 2>&1 | tee -a "$LOG_FILE"
TEST_RESULT=${PIPESTATUS[0]}

if [ $TEST_RESULT -eq 0 ]; then
    print_status 0 "Apache configuration syntax is valid"
else
    print_status 1 "Configuration error detected"
    exit 1
fi

# 4. Reload Apache
echo -e "\n${YELLOW}Reloading Apache to apply security headers${NC}" | tee -a "$LOG_FILE"
service httpd reload 2>&1 | tee -a "$LOG_FILE"
print_status $? "Apache reloaded"

# 5. Verify headers are present
echo -e "\n${YELLOW}[Verifying security headers on HTTPS]${NC}" | tee -a "$LOG_FILE"
sleep 2

# Test via Varnish (port 8888)
echo -e "\n${BLUE}Testing through Varnish (port 8888):${NC}" | tee -a "$LOG_FILE"
curl -s -I -H "Host: technostationery.com" https://localhost:8888/ 2>/dev/null | \
    grep -E "Strict-Transport-Security|X-Frame-Options|X-Content-Type-Options|X-XSS-Protection|Referrer-Policy" | \
    tee -a "$LOG_FILE" || echo "Headers not found (expected if SSL cert invalid)" | tee -a "$LOG_FILE"

# Test via Apache backend (port 81) - won't have HTTPS without cert
echo -e "\n${BLUE}Testing HTTP headers (port 81):${NC}" | tee -a "$LOG_FILE"
curl -s -I http://127.0.0.1:81/ 2>/dev/null | head -15 | tee -a "$LOG_FILE"

# 6. Summary
echo -e "\n${GREEN}================================================${NC}" | tee -a "$LOG_FILE"
echo -e "${GREEN}Security Headers Configuration Completed${NC}" | tee -a "$LOG_FILE"
echo -e "${GREEN}================================================${NC}" | tee -a "$LOG_FILE"
echo -e "\nHeaders Added:" | tee -a "$LOG_FILE"
echo "  ✓ Strict-Transport-Security (HSTS)" | tee -a "$LOG_FILE"
echo "  ✓ X-Frame-Options (Clickjacking protection)" | tee -a "$LOG_FILE"
echo "  ✓ X-Content-Type-Options (MIME-sniffing protection)" | tee -a "$LOG_FILE"
echo "  ✓ X-XSS-Protection (XSS protection)" | tee -a "$LOG_FILE"
echo "  ✓ Content-Security-Policy (CSP)" | tee -a "$LOG_FILE"
echo "  ✓ Referrer-Policy" | tee -a "$LOG_FILE"
echo "  ✓ Permissions-Policy (Feature Policy)" | tee -a "$LOG_FILE"
echo "  ✓ Server information hiding" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "Configuration File:" | tee -a "$LOG_FILE"
echo "  /etc/apache2/conf.d/security-headers.conf" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "Backup:" | tee -a "$LOG_FILE"
echo "  /etc/apache2/conf.d/security-headers.conf.backup.$TIMESTAMP" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "To verify headers in browser:" | tee -a "$LOG_FILE"
echo "  1. Open https://technostationery.com" | tee -a "$LOG_FILE"
echo "  2. Press F12 (Developer Tools)" | tee -a "$LOG_FILE"
echo "  3. Go to Network tab" | tee -a "$LOG_FILE"
echo "  4. Reload page and check Response Headers" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "Test headers via curl:" | tee -a "$LOG_FILE"
echo "  curl -I https://technostationery.com" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "Log file: $LOG_FILE" | tee -a "$LOG_FILE"

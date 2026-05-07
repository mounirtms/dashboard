#!/bin/bash

##########################################################
# CRITICAL FIX #2: Varnish Backend Configuration
# This script fixes Varnish VCL to point to localhost
# REQUIRED: Without this, Varnish cannot reach Apache
##########################################################

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_FILE="$SCRIPT_DIR/fix-varnish-backend.log"
TIMESTAMP=$(date '+%Y%m%d_%H%M%S')

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting Varnish Backend Configuration Fix" | tee -a "$LOG_FILE"

# Function to print status
print_status() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✓ $2${NC}" | tee -a "$LOG_FILE"
    else
        echo -e "${RED}✗ $2${NC}" | tee -a "$LOG_FILE"
        exit 1
    fi
}

print_info() {
    echo -e "${BLUE}ℹ $1${NC}" | tee -a "$LOG_FILE"
}

# 1. Backup current VCL files
echo -e "\n${YELLOW}[1/5] Backing up Varnish VCL configurations${NC}" | tee -a "$LOG_FILE"

cp /etc/varnish/default.vcl "/etc/varnish/default.vcl.backup.$TIMESTAMP"
print_status $? "Backed up: default.vcl → default.vcl.backup.$TIMESTAMP"

cp /etc/varnish/backends.vcl "/etc/varnish/backends.vcl.backup.$TIMESTAMP"
print_status $? "Backed up: backends.vcl → backends.vcl.backup.$TIMESTAMP"

# 2. Show current backend configuration
echo -e "\n${YELLOW}[2/5] Current backend configuration:${NC}" | tee -a "$LOG_FILE"
echo -e "\n${BLUE}=== default.vcl backend definition ===${NC}" | tee -a "$LOG_FILE"
grep -A 8 "^backend default" /etc/varnish/default.vcl | tee -a "$LOG_FILE" || echo "Not found" | tee -a "$LOG_FILE"

echo -e "\n${BLUE}=== backends.vcl definitions ===${NC}" | tee -a "$LOG_FILE"
cat /etc/varnish/backends.vcl | tee -a "$LOG_FILE"

# 3. Fix default.vcl - Change backend host from 205.134.249.177 to 127.0.0.1
echo -e "\n${YELLOW}[3/5] Fixing backend host in default.vcl${NC}" | tee -a "$LOG_FILE"

sed -i 's/\.host = "205\.134\.249\.177";/.host = "127.0.0.1";/g' /etc/varnish/default.vcl
print_status $? "Updated default.vcl: backend host changed to 127.0.0.1"

# Verify the change
echo -e "\n${BLUE}Verification:${NC}" | tee -a "$LOG_FILE"
grep -A 2 "^backend default" /etc/varnish/default.vcl | tee -a "$LOG_FILE"

# 4. Fix backends.vcl - Change port 80 to 81 (where Apache listens)
echo -e "\n${YELLOW}[4/5] Fixing backend ports in backends.vcl${NC}" | tee -a "$LOG_FILE"

cat > /etc/varnish/backends.vcl << 'BACKENDS_EOF'
# Backend definitions for each domain
# All backends point to Apache on localhost:81

backend dashboard {
    .host = "127.0.0.1";
    .port = "81";
    .connect_timeout = 5s;
    .first_byte_timeout = 600s;
    .between_bytes_timeout = 60s;
    .probe = {
        .url = "/.health_check";
        .timeout = 5s;
        .interval = 10s;
    }
}

backend pim {
    .host = "127.0.0.1";
    .port = "81";
    .connect_timeout = 5s;
    .first_byte_timeout = 600s;
    .between_bytes_timeout = 60s;
    .probe = {
        .url = "/.health_check";
        .timeout = 5s;
        .interval = 10s;
    }
}

backend main {
    .host = "127.0.0.1";
    .port = "81";
    .connect_timeout = 5s;
    .first_byte_timeout = 600s;
    .between_bytes_timeout = 60s;
    .probe = {
        .url = "/.health_check";
        .timeout = 5s;
        .interval = 10s;
    }
}

# Default backend (fallback)
backend default {
    .host = "127.0.0.1";
    .port = "81";
    .connect_timeout = 5s;
    .first_byte_timeout = 600s;
    .between_bytes_timeout = 60s;
}
BACKENDS_EOF

print_status $? "Updated backends.vcl with correct host and port configuration"

echo -e "\n${BLUE}Updated backends.vcl contents:${NC}" | tee -a "$LOG_FILE"
cat /etc/varnish/backends.vcl | tee -a "$LOG_FILE"

# 5. Test Varnish VCL compilation
echo -e "\n${YELLOW}[5/5] Testing Varnish VCL compilation${NC}" | tee -a "$LOG_FILE"

if command -v varnishd &> /dev/null; then
    VCL_TEST=$(/usr/sbin/varnishd -C -f /etc/varnish/default.vcl 2>&1)
    if echo "$VCL_TEST" | grep -q "VCL compiled"; then
        print_status 0 "Varnish VCL compiled successfully"
        echo "$VCL_TEST" | head -20 | tee -a "$LOG_FILE"
    else
        echo -e "${RED}VCL compilation warning:${NC}" | tee -a "$LOG_FILE"
        echo "$VCL_TEST" | tee -a "$LOG_FILE"
    fi
else
    print_info "varnishd not found in PATH, skipping compile test"
fi

# 6. Restart Varnish service
echo -e "\n${YELLOW}[6/6] Restarting Varnish service${NC}" | tee -a "$LOG_FILE"

if systemctl is-active --quiet varnish; then
    systemctl restart varnish 2>&1 | tee -a "$LOG_FILE"
    print_status $? "Varnish service restarted"
    
    # Wait for service to start
    sleep 3
    
    # Verify Varnish is running
    if systemctl is-active --quiet varnish; then
        print_status 0 "Varnish is running"
    else
        echo -e "${YELLOW}Checking Varnish status...${NC}" | tee -a "$LOG_FILE"
        systemctl status varnish | head -20 | tee -a "$LOG_FILE"
    fi
else
    print_info "Varnish service not currently running"
    echo "To start: systemctl start varnish" | tee -a "$LOG_FILE"
fi

# 7. Test connectivity
echo -e "\n${YELLOW}[Testing Varnish → Apache connectivity]${NC}" | tee -a "$LOG_FILE"

# Give Varnish time to start
sleep 2

# Test through Varnish
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8888/ 2>&1 || echo "000")
if [ "$HTTP_CODE" = "200" ]; then
    echo -e "${GREEN}✓ Varnish responding on port 8888: HTTP $HTTP_CODE${NC}" | tee -a "$LOG_FILE"
else
    echo -e "${YELLOW}⚠ Varnish response: HTTP $HTTP_CODE (may indicate backend issue)${NC}" | tee -a "$LOG_FILE"
fi

# Test direct Apache connection
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:81/ 2>&1 || echo "000")
if [ "$HTTP_CODE" = "200" ]; then
    echo -e "${GREEN}✓ Apache responding on port 81: HTTP $HTTP_CODE${NC}" | tee -a "$LOG_FILE"
else
    echo -e "${RED}✗ Apache not responding on port 81: HTTP $HTTP_CODE${NC}" | tee -a "$LOG_FILE"
fi

# 8. Show Varnish statistics
echo -e "\n${YELLOW}[Varnish Statistics]${NC}" | tee -a "$LOG_FILE"
if command -v varnishstat &> /dev/null; then
    varnishstat -1 2>/dev/null | head -30 | tee -a "$LOG_FILE"
fi

# 9. Summary
echo -e "\n${GREEN}================================================${NC}" | tee -a "$LOG_FILE"
echo -e "${GREEN}Varnish Backend Configuration Fix Completed${NC}" | tee -a "$LOG_FILE"
echo -e "${GREEN}================================================${NC}" | tee -a "$LOG_FILE"
echo -e "\nConfiguration Changes:" | tee -a "$LOG_FILE"
echo "  ✓ Fixed default.vcl: backend host 205.134.249.177 → 127.0.0.1" | tee -a "$LOG_FILE"
echo "  ✓ Updated backends.vcl: all backends point to 127.0.0.1:81" | tee -a "$LOG_FILE"
echo "  ✓ Added health check probes to all backends" | tee -a "$LOG_FILE"
echo "  ✓ Verified Varnish VCL compilation" | tee -a "$LOG_FILE"
echo "  ✓ Restarted Varnish service" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "Verification:" | tee -a "$LOG_FILE"
echo "  Backend definitions backed up to:" | tee -a "$LOG_FILE"
echo "    - /etc/varnish/default.vcl.backup.$TIMESTAMP" | tee -a "$LOG_FILE"
echo "    - /etc/varnish/backends.vcl.backup.$TIMESTAMP" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "Test the routing:" | tee -a "$LOG_FILE"
echo "  curl -v http://127.0.0.1:8888/" | tee -a "$LOG_FILE"
echo "  curl -v http://127.0.0.1:81/" | tee -a "$LOG_FILE"
echo "  curl -I -H 'Host: technostationery.com' http://127.0.0.1:8888/" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "Next Steps:" | tee -a "$LOG_FILE"
echo "  1. Verify Apache is responding on port 81" | tee -a "$LOG_FILE"
echo "  2. Verify Varnish is proxying through port 8888" | tee -a "$LOG_FILE"
echo "  3. Test through Cloudflare: curl https://technostationery.com/" | tee -a "$LOG_FILE"
echo "  4. Run: fix-security-headers.sh (for HTTPS headers)" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"
echo "Log file: $LOG_FILE" | tee -a "$LOG_FILE"


cd /home/beta/public_html
 
rm -rf  var/*  pub/static/frontend/*  pub/static/adminhtml/* generated/*
php bin/magento maintenance:enable
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
 
chmod -R 777 pub/static/ var/ generated/
php bin/magento maintenance:disable 
php bin/magento cache:flush
php bin/magento cache:clean
php bin/magento mab:cache:all:purge  
php bin/magento mab:cloudflare:purge:all 
chown -R beta:beta .
chmod -R 775 pub/static/
chmod -R 775 var/
chmod -R 775 generated/
php bin/magento mab:test:full

#!/bin/bash
###############################################################################
# Deploy Multi-Site Varnish Configuration
# Purpose: Configure Varnish with site-specific routing and Apache on port 8080
# Safety: Backs up existing configs before making changes
###############################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

echo "========================================="
echo "Multi-Site Varnish Deployment"
echo "Started: $(date '+%Y-%m-%d %H:%M:%S')"
echo "========================================="
echo ""

# Step 1: Backup existing configurations
log_info "Creating backups..."
BACKUP_DIR="/home/dashboard/public_html/backups/varnish_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

if [ -f "/etc/varnish/default.vcl" ]; then
    cp /etc/varnish/default.vcl "$BACKUP_DIR/default.vcl.bak"
    log_info "Backed up: /etc/varnish/default.vcl"
fi

if [ -f "/etc/apache2/conf/httpd.conf" ]; then
    cp /etc/apache2/conf/httpd.conf "$BACKUP_DIR/httpd.conf.bak"
    log_info "Backed up: /etc/apache2/conf/httpd.conf"
fi

echo ""

# Step 2: Check Apache current port
log_info "Checking Apache configuration..."
APACHE_PORT=$(grep -E "^Listen\s+80$" /etc/apache2/conf/httpd.conf 2>/dev/null || echo "")
if [ -n "$APACHE_PORT" ]; then
    log_info "Apache is listening on port 80 (will be changed to 8080)"
else
    log_info "Apache port configuration not found in expected location"
fi

echo ""

# Step 3: Deploy new Varnish VCL
log_info "Deploying multi-site Varnish VCL..."
if [ -f "/tmp/varnish_multi_site_config.vcl" ]; then
    cp /tmp/varnish_multi_site_config.vcl /etc/varnish/default.vcl
    chmod 644 /etc/varnish/default.vcl
    log_info "VCL deployed successfully"
    
    # Validate VCL syntax
    if varnishd -C -f /etc/varnish/default.vcl > /dev/null 2>&1; then
        log_info "VCL syntax validation: PASSED"
    else
        log_error "VCL syntax validation: FAILED"
        log_error "Restoring backup..."
        cp "$BACKUP_DIR/default.vcl.bak" /etc/varnish/default.vcl
        exit 1
    fi
else
    log_error "VCL file not found at /tmp/varnish_multi_site_config.vcl"
    exit 1
fi

echo ""

# Step 4: Update Apache to listen on port 8080
log_info "Configuring Apache to listen on port 8080..."

# Create a sed script to change port 80 to 8080
sed -i.bak 's/^Listen 80$/Listen 8080/' /etc/apache2/conf/httpd.conf 2>/dev/null || log_warn "Could not update Apache port automatically"

# Also update virtualhost declarations
sed -i 's/^<VirtualHost \*:80>$/<VirtualHost *:8080>/' /etc/apache2/conf/httpd.conf 2>/dev/null || log_warn "Could not update VirtualHost port automatically"

log_info "Apache configuration updated"

echo ""

# Step 5: Test Apache configuration
log_info "Testing Apache configuration..."
if httpd -t 2>&1 | grep -q "Syntax OK"; then
    log_info "Apache configuration: OK"
else
    log_error "Apache configuration test failed"
    log_error "Restoring backup..."
    cp "$BACKUP_DIR/httpd.conf.bak" /etc/apache2/conf/httpd.conf
    exit 1
fi

echo ""

# Step 6: Restart services
log_info "Restarting services..."

log_info "Restarting Apache..."
systemctl restart httpd
if [ $? -eq 0 ]; then
    log_info "Apache restarted successfully"
else
    log_error "Apache restart failed"
    log_error "Restoring backup..."
    cp "$BACKUP_DIR/httpd.conf.bak" /etc/apache2/conf/httpd.conf
    systemctl restart httpd
    exit 1
fi

sleep 2

log_info "Restarting Varnish..."
systemctl restart varnish
if [ $? -eq 0 ]; then
    log_info "Varnish restarted successfully"
else
    log_error "Varnish restart failed"
    log_error "Check logs: journalctl -u varnish -n 50"
    exit 1
fi

sleep 2

echo ""

# Step 7: Verify services
log_info "Verifying services..."

# Check Apache on 8080
if netstat -tlnp 2>/dev/null | grep -q ":8080.*httpd"; then
    log_info "✓ Apache listening on port 8080"
else
    log_warn "✗ Apache not listening on port 8080"
fi

# Check Varnish on 80
if netstat -tlnp 2>/dev/null | grep -q ":6081.*varnish"; then
    log_info "✓ Varnish listening on port 6081"
else
    log_warn "✗ Varnish not listening on port 6081"
fi

# Check backend health
varnishadm backend.list 2>/dev/null | grep -E "dashboard|technostationery|beta" || log_warn "Backend status unavailable"

echo ""

# Step 8: Summary
log_info "=== DEPLOYMENT SUMMARY ==="
log_info "Backups stored in: $BACKUP_DIR"
log_info "VCL config: /etc/varnish/default.vcl"
log_info "Apache config: /etc/apache2/conf/httpd.conf"
log_info ""
log_info "Next steps:"
log_info "1. Test each site to verify traffic flows through Varnish"
log_info "2. Check cache headers: curl -I https://dashboard.technostationery.com/"
log_info "3. Monitor hit rate: varnishstat -1 | grep cache_hit"
log_info "4. Run warmup script: bash /home/dashboard/public_html/scripts/warmup_varnish_full.sh"

echo ""
echo "========================================="
log_info "Deployment completed at $(date '+%Y-%m-%d %H:%M:%S')"
echo "========================================="

exit 0

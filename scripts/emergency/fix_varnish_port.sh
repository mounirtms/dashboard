#!/bin/bash
# ==========================================
# Varnish Port Fixer
# Purpose: Resolves port 80 conflict by moving Apache to 8080
# ==========================================

CONF="/etc/apache2/conf/httpd.conf"

if [[ ! -f "$CONF" ]]; then
    CONF="/etc/httpd/conf/httpd.conf"
fi

if [[ ! -f "$CONF" ]]; then
    echo "Apache configuration file not found."
    exit 1
fi

echo "Moving Apache to port 8080..."
sed -i 's/Listen 0.0.0.0:80/Listen 0.0.0.0:8080/g' "$CONF"
sed -i 's/Listen \[::\]:80/Listen [::]:8080/g' "$CONF"
sed -i 's/Listen 80/Listen 8080/g' "$CONF"

echo "Restarting Apache..."
systemctl restart httpd || systemctl restart apache2

echo "Starting Varnish..."
systemctl start varnish

echo "Checking status..."
sleep 2
systemctl is-active varnish && echo "Varnish is ACTIVE" || echo "Varnish is STILL DOWN"
ss -tlnp | grep -E ':80|:8080'

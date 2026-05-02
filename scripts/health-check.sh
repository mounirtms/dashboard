#!/bin/bash
# Quick System Health Check
# Run: ./scripts/health-check.sh

echo "=== System Health Check ==="
echo ""

# Dashboard
echo -n "Dashboard: "
if curl -sf -o /dev/null -w "%{http_code}" https://dashboard.technostationery.com; then
    echo " OK"
else
    echo " FAILED"
fi

# Akeneo PIM
echo -n "PIM Login: "
if curl -sf -o /dev/null -w "%{http_code}" https://pim.technostationery.com/user/login; then
    echo " OK"
else
    echo " FAILED"
fi

# MySQL
echo -n "MySQL: "
if mysql --socket=/opt/mariadb10.6/mariadb.sock -u root -p'YourNewStrongPassword' --skip-ssl -e "SELECT 1" 2>/dev/null; then
    echo "OK"
else
    echo "FAILED"
fi

# Elasticsearch
echo -n "Elasticsearch: "
if curl -sf localhost:9200/_cluster/health 2>/dev/null | grep -q "green\|yellow"; then
    echo "OK"
else
    echo "FAILED"
fi

# Server Load
echo -n "Load: "
uptime | awk -F'load average:' '{print $2}'

# Memory
echo -n "Memory: "
free -h | awk '/^Mem:/{print $3"/"$2}'

echo ""
echo "=== Dashboard Credentials ==="
echo "User: admin"
echo "Pass: admin123"
echo ""
echo "=== PIM Credentials ==="  
echo "User: adminreset (temporary)"
echo "Pass: PassWord1234"
echo ""
#!/bin/bash
# Comprehensive System Audit Script
# Date: $(date)

REPORT_FILE="/home/technadminy7/public_html/SYSTEM_AUDIT_REPORT_$(date +%Y%m%d_%H%M%S).md"

echo "# Comprehensive System Audit Report" > $REPORT_FILE
echo "Generated: $(date)" >> $REPORT_FILE
echo "" >> $REPORT_FILE

echo "## 1. System Overview" >> $REPORT_FILE
echo "\`\`\`" >> $REPORT_FILE
uptime >> $REPORT_FILE
echo "" >> $REPORT_FILE
echo "CPU Info:" >> $REPORT_FILE
lscpu | grep -E "^CPU\(s\)|Thread|Core|Socket|Model name" >> $REPORT_FILE
echo "" >> $REPORT_FILE
echo "Memory:" >> $REPORT_FILE
free -h >> $REPORT_FILE
echo "" >> $REPORT_FILE
echo "Disk Usage:" >> $REPORT_FILE
df -h / >> $REPORT_FILE
echo "\`\`\`" >> $REPORT_FILE
echo "" >> $REPORT_FILE

echo "## 2. Current Load Analysis (2-minute sample)" >> $REPORT_FILE
echo "Collecting 24 samples at 5-second intervals..." >> $REPORT_FILE
echo "\`\`\`" >> $REPORT_FILE
for i in {1..24}; do
  echo "Sample $i at $(date +%H:%M:%S):" >> $REPORT_FILE
  uptime | awk '{print "Load: " $(NF-2) " " $(NF-1) " " $NF}' >> $REPORT_FILE
  ps aux --sort=-%cpu | head -6 | awk '{printf "%-10s %5s %5s %s\n", $1, $3, $4, $11}' >> $REPORT_FILE
  echo "" >> $REPORT_FILE
  sleep 5
done
echo "\`\`\`" >> $REPORT_FILE
echo "" >> $REPORT_FILE

echo "## 3. Top Resource Consumers" >> $REPORT_FILE
echo "### CPU Top 20" >> $REPORT_FILE
echo "\`\`\`" >> $REPORT_FILE
ps aux --sort=-%cpu | head -21 >> $REPORT_FILE
echo "\`\`\`" >> $REPORT_FILE
echo "" >> $REPORT_FILE

echo "### Memory Top 20" >> $REPORT_FILE
echo "\`\`\`" >> $REPORT_FILE
ps aux --sort=-%mem | head -21 >> $REPORT_FILE
echo "\`\`\`" >> $REPORT_FILE
echo "" >> $REPORT_FILE

echo "## 4. Service Status Check" >> $REPORT_FILE
echo "\`\`\`" >> $REPORT_FILE
systemctl status mariadb10.6 --no-pager -l 2>&1 | head -20 >> $REPORT_FILE 2>&1 || echo "MariaDB 10.6 service check failed" >> $REPORT_FILE
echo "" >> $REPORT_FILE
systemctl status ea-php82-php-fpm --no-pager -l 2>&1 | head -15 >> $REPORT_FILE 2>&1 || echo "PHP-FPM service check failed" >> $REPORT_FILE
echo "" >> $REPORT_FILE
systemctl status redis --no-pager -l 2>&1 | head -15 >> $REPORT_FILE 2>&1 || echo "Redis service check failed" >> $REPORT_FILE
echo "" >> $REPORT_FILE
systemctl status varnish --no-pager -l 2>&1 | head -15 >> $REPORT_FILE 2>&1 || echo "Varnish service check failed" >> $REPORT_FILE
echo "" >> $REPORT_FILE
systemctl status elasticsearch --no-pager -l 2>&1 | head -15 >> $REPORT_FILE 2>&1 || echo "Elasticsearch service check failed" >> $REPORT_FILE
echo "\`\`\`" >> $REPORT_FILE
echo "" >> $REPORT_FILE

echo "Report saved to: $REPORT_FILE"

# Database Outage Emergency Response Guide

## Immediate Actions When Database Goes Down

### 1. Quick Diagnosis
```bash
# Check if database is running
systemctl status mariadb

# Check database logs
tail -50 /opt/mariadb10.6/mariadb-error.log

# Check system resources
top -bn1 | head -20
free -h
df -h
```

### 2. Manual Database Restart
```bash
# Graceful restart
sudo systemctl restart mariadb

# If that fails, force restart
sudo systemctl stop mariadb
sudo systemctl start mariadb

# Check status
systemctl status mariadb
```

### 3. Verify Recovery
```bash
# Test database connection
/usr/bin/mariadb -u technadminy7_ntdbusr24 -p -h localhost -P 3307 technadminy7_dBT8x12y22 -e "SHOW STATUS LIKE 'Uptime';"

# Clear Magento cache
cd /home/technadminy7/public_html
php bin/magento cache:flush

# Restart web services
sudo systemctl restart ea-php82-php-fpm
sudo systemctl restart httpd
```

## Prevention Measures

### 1. Regular Monitoring Setup
- Database health checks every 5 minutes
- Automated recovery scripts
- Resource monitoring alerts

### 2. Configuration Optimizations Applied
- Reduced max_connections from conflicting values to stable 200
- Disabled query cache for stability
- Optimized timeouts and buffer sizes
- Balanced InnoDB buffer pool size

### 3. Cron Job Management
- Spread heavy operations throughout the day
- Monitor for deadlocks and connection issues
- Automated cleanup of stuck processes

## Key Log Locations
- `/opt/mariadb10.6/mariadb-error.log` - Database errors
- `/home/technadminy7/public_html/var/log/system.log` - Application errors
- `/home/technadminy7/public_html/var/log/database_health.log` - Health monitoring
- `/home/technadminy7/public_html/var/log/database_recovery.log` - Recovery attempts

## Contact Information
For critical issues, contact system administrator immediately.
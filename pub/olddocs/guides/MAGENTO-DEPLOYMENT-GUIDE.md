# Magento 2.4.6 Production Optimization Deployment Guide

## Overview
This guide provides step-by-step instructions for deploying optimized production resources for your Magento 2.4.6 installation.

## Prerequisites
- Root or sudo access to the server
- Magento 2.4.6 Community Edition
- PHP 8.2.x
- MariaDB 10.6+
- Redis server
- Varnish cache (optional but recommended)

## Files Created
1. `magento-php-fpm-pool.conf` - Optimized PHP-FPM configuration
2. `magento-mysql-config.cnf` - MariaDB optimization settings
3. `magento-redis-config.conf` - Redis cache configuration
4. `magento-varnish.vcl` - Varnish HTTP accelerator configuration
5. `php-fpm-systemd-override.conf` - Systemd service overrides
6. `magento-monitor.sh` - System monitoring script
7. `magento-crontab.txt` - Production cron jobs

## Deployment Steps

### 1. PHP-FPM Configuration
```bash
# Copy the PHP-FPM pool configuration
sudo cp /home/technadminy7/public_html/magento-php-fpm-pool.conf /opt/cpanel/ea-php82/root/etc/php-fpm.d/technostationery_com.conf

# Restart PHP-FPM service
sudo systemctl restart ea-php82-php-fpm
```

### 2. MySQL/MariaDB Optimization
```bash
# Copy MySQL configuration
sudo cp /home/technadminy7/public_html/magento-mysql-config.cnf /etc/my.cnf.d/magento-optimizations.cnf

# Restart MySQL service
sudo systemctl restart mariadb

# Verify the configuration
mysql -u root -p -e "SHOW VARIABLES LIKE 'innodb_buffer_pool_size';"
```

### 3. Redis Configuration
```bash
# Copy Redis configuration (adjust path based on your Redis installation)
sudo cp /home/technadminy7/public_html/magento-redis-config.conf /etc/redis.conf

# Or for Debian/Ubuntu systems:
sudo cp /home/technadminy7/public_html/magento-redis-config.conf /etc/redis/redis.conf

# Restart Redis service
sudo systemctl restart redis

# Verify Redis is running
redis-cli ping
```

### 4. Varnish Configuration (Optional)
```bash
# Install Varnish if not already installed
sudo yum install varnish  # CentOS/RHEL
# OR
sudo apt-get install varnish  # Ubuntu/Debian

# Copy Varnish configuration
sudo cp /home/technadminy7/public_html/magento-varnish.vcl /etc/varnish/default.vcl

# Configure Varnish to listen on port 80 and backend on 8080
# Edit /etc/varnish/varnish.params or /etc/sysconfig/varnish

# Restart Varnish service
sudo systemctl restart varnish
```

### 5. Systemd Service Overrides
```bash
# Create override directory
sudo mkdir -p /etc/systemd/system/php-fpm.service.d/

# Copy override configuration
sudo cp /home/technadminy7/public_html/php-fpm-systemd-override.conf /etc/systemd/system/php-fpm.service.d/override.conf

# Reload systemd and restart services
sudo systemctl daemon-reload
sudo systemctl restart php-fpm
```

### 6. Monitoring Setup
```bash
# Make monitoring script executable
chmod +x /home/technadminy7/public_html/magento-monitor.sh

# Test the monitoring script
bash /home/technadminy7/public_html/magento-monitor.sh

# Add to crontab
(crontab -l 2>/dev/null; cat /home/technadminy7/public_html/magento-crontab.txt) | crontab -
```

### 7. Magento Configuration Updates
```bash
# Clear Magento cache
php bin/magento cache:clean

# Reindex all indexes
php bin/magento indexer:reindex

# Deploy static content
php bin/magento setup:static-content:deploy -f

# Compile dependency injection
php bin/magento setup:di:compile

# Enable production mode
php bin/magento deploy:mode:set production
```

## Performance Testing

After deployment, run these tests to verify optimization:

```bash
# Test page load time
curl -w "@curl-format.txt" -o /dev/null -s "https://technostationery.com/"

# Check PHP-FPM status
sudo systemctl status ea-php82-php-fpm

# Monitor system resources
htop
iotop
```

## Monitoring Commands

```bash
# Check PHP-FPM pool status
sudo systemctl status ea-php82-php-fpm

# Check MySQL performance
mysqladmin -r -i 1 ext | grep -e Queries -e Threads_connected

# Check Redis performance
redis-cli info stats

# Check Varnish hit rate
varnishstat -1 | grep hitrate

# Monitor Magento logs
tail -f var/log/system.log
tail -f var/log/exception.log
```

## Troubleshooting

### Common Issues:

1. **PHP-FPM not starting**: Check configuration syntax and permissions
2. **MySQL performance issues**: Adjust buffer pool size based on available RAM
3. **Redis connection errors**: Verify Redis is running and firewall settings
4. **Varnish cache misses**: Check VCL configuration and cache headers

### Log Locations:
- PHP-FPM: `/opt/cpanel/ea-php82/root/var/log/`
- MySQL: `/var/log/mysqld.log`
- Redis: `/var/log/redis/redis-server.log`
- Varnish: `/var/log/varnish/`
- Magento: `/home/technadminy7/public_html/var/log/`

## Security Considerations

1. Set strong passwords for Redis
2. Configure firewall rules
3. Regular security updates
4. Monitor access logs
5. Implement proper backup strategy

## Backup Recommendations

Create automated backup scripts for:
- Database dumps
- Media files
- Configuration files
- Custom code

## Maintenance Schedule

Weekly:
- Check system logs
- Monitor resource usage
- Review security alerts

Monthly:
- Update extensions
- Review performance metrics
- Test backup restoration

Quarterly:
- Security audit
- Performance optimization review
- Infrastructure assessment
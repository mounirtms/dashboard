# Production Environment Documentation

## System Overview
This document provides comprehensive documentation for the Production Magento environment at `/home/technadminy7/public_html`.

## Environment Details
- **Domain:** www.technostationery.com
- **Environment:** Production (Live)
- **Magento Version:** 2.4.x
- **PHP Version:** 8.2
- **Database:** MariaDB 10.6
- **Server:** Apache 2.4 with PHP-FPM

## Directory Structure
```
/home/technadminy7/public_html/
├── app/                 # Application code
├── bin/                 # Magento CLI tools
├── dev/                 # Development tools
├── docs/               # Documentation (NEW)
├── generated/          # Generated code
├── lib/                # Libraries
├── pub/                # Public web root
├── setup/              # Setup tools
├── var/                # Variable data (logs, cache)
├── vendor/             # Composer dependencies
└── scripts/            # Custom scripts
```

## Critical Operations Guide

### Daily Monitoring
```bash
# Check system health
./scripts/database_health_monitor.sh

# Monitor logs
tail -f var/log/system.log

# Check resource usage
top -bn1 | head -20
```

### Weekly Maintenance
```bash
# Database optimization
php bin/magento indexer:reindex

# Cache management
php bin/magento cache:flush

# Log rotation
logrotate /etc/logrotate.d/magento
```

### Monthly Tasks
```bash
# Security audit
./scripts/security_audit.php

# Performance review
./scripts/performance_monitor.sh

# Backup verification
./scripts/backup/verify-backup.sh
```

## Emergency Procedures

### Database Issues
1. Check database connectivity
2. Review error logs in `var/log/`
3. Run automated recovery script
4. Contact database administrator if needed

### Performance Problems
1. Check system resources (CPU, memory, disk)
2. Review slow query logs
3. Clear Magento cache
4. Restart web services if necessary

### Security Incidents
1. Isolate affected systems
2. Review access logs
3. Change compromised credentials
4. Run security scan
5. Document incident

## Configuration Files

### Key Configuration Locations
- **Main Config:** `app/etc/env.php`
- **Database Config:** `app/etc/config.php`
- **Apache Config:** `/etc/httpd/conf.d/magento.conf`
- **PHP Config:** `/opt/cpanel/ea-php82/root/etc/php.ini`

### Environment Variables
```bash
MAGE_MODE=production
MAGE_RUN_TYPE=website
MAGE_RUN_CODE=base
```

## Monitoring and Alerts

### Active Monitoring Scripts
- `database_health_monitor.sh` - Every 15 minutes
- `automated_database_recovery.sh` - Weekly
- Custom log monitors in `var/log/`

### Key Metrics Tracked
- Database connection status
- System resource utilization
- Magento cron job execution
- Error rate and frequency

## Backup and Recovery

### Backup Schedule
- **Daily:** Database and code backup at 2 AM
- **Weekly:** Full system backup on Sundays
- **Monthly:** Offsite backup archive

### Recovery Procedures
1. Identify backup point
2. Restore database from backup
3. Deploy code from backup
4. Verify system functionality
5. Update DNS if necessary

## Security Configuration

### Current Security Measures
- SSL/TLS encryption enforced
- Firewall rules configured
- Regular security updates
- Access logging enabled
- Intrusion detection system

### Recent Security Enhancements
- Removed password hints from login pages
- Enhanced session management
- Improved input validation
- Regular vulnerability scanning

## Performance Optimization

### Current Optimizations
- Redis caching configured
- Varnish HTTP accelerator
- Database query optimization
- Asset minification enabled
- CDN integration

### Performance Baselines
- Page load time: < 2 seconds
- Database query time: < 100ms average
- Server response time: < 500ms
- Uptime target: 99.9%

## Contact Information

### Support Contacts
- **Primary Administrator:** [admin contact]
- **Development Team:** [dev team contact]
- **Hosting Provider:** [hosting support]

### Emergency Contacts
- **24/7 Support:** [emergency number]
- **Database Admin:** [dba contact]
- **Security Team:** [security contact]

---
*Last Updated: January 30, 2026*  
*Document Owner: System Administration Team*
# CPU Optimization & Monitoring - Complete Setup

## Problem Analysis

### Root Causes of High CPU (63-90%)

1. **PHP-FPM Workers Overload**
   - 13+ PHP-FPM workers running simultaneously
   - Each using 50-60% CPU, 250-300MB RAM
   - `memory_limit = 2G` per process (too high)
   - `pm.max_children = 8` but ondemand pools can spawn more

2. **Elasticsearch Memory Issues**
   - Conflicting JVM heap settings: `-Xms12g -Xmx12g -Xms6g -Xmx6g -Xms2g -Xmx2g`
   - Using 2.9GB RAM (8.9% of system)
   - Running since Mar24 without restart

3. **MariaDB Configuration**
   - `innodb_buffer_pool_size = 8G`
   - `query_cache_size = 512M`
   - Total using 4.8GB RAM (15% of system)

4. **Magento Queue Consumers**
   - Running with `--max-messages=10000` (never stop)
   - Some consumers running since Mar28 without restart
   - Accumulated 10,084 messages before cleanup

5. **Cache Buildup**
   - Magento cache: variable size
   - PIM cache: 67MB
   - No automatic cleanup

---

## Solutions Implemented

### Scripts Created

| Script | Purpose | Location |
|--------|---------|----------|
| `cpu_optimize.sh` | Optimize CPU, clean caches, restart stuck processes | `/home/pim/public_html/` |
| `emergency_cpu_throttle.sh` | Emergency CPU reduction when >90% | `/home/pim/public_html/` |
| `system_monitor.sh` | Comprehensive monitoring with auto-remediation | `scripts/monitoring/` |
| `queue_optimize.sh` | Daily queue table optimization | `/home/pim/public_html/` |

### Cron Jobs Added (technadminy7 user)

```cron
# Queue Optimization (Daily at 3:15 AM)
15 3 * * * /bin/bash /home/pim/public_html/queue_optimize.sh

# CPU Optimization (Every 10 minutes)
*/10 * * * * /bin/bash /home/pim/public_html/cpu_optimize.sh >> /home/technadminy7/public_html/var/log/cpu_optimize.log 2>&1

# System Monitor (Every 2 minutes)
*/2 * * * * /bin/bash /home/pim/public_html/scripts/monitoring/system_monitor.sh
```

---

## Thresholds Configured

### CPU
- **WARNING**: 60%
- **CRITICAL**: 80% (auto-run optimization)
- **EMERGENCY**: 90% (aggressive throttle)

### Memory
- **WARNING**: 70%
- **CRITICAL**: 85%

### Queue
- **WARNING**: 1,000 messages
- **CRITICAL**: 5,000 messages

### PHP-FPM
- **MAX WORKERS**: 6 (kill excess)

### Load Average
- **WARNING**: 10
- **CRITICAL**: 15

---

## Manual Commands

### Check Current Status
```bash
# Quick status
/home/pim/public_html/scripts/quick_status.sh

# System monitor
/home/pim/public_html/scripts/monitoring/system_monitor.sh

# CPU optimization
/home/pim/public_html/cpu_optimize.sh
```

### Emergency CPU Reduction
```bash
# When CPU > 90%
/home/pim/public_html/emergency_cpu_throttle.sh
```

### View Logs
```bash
# Real-time monitoring
tail -f /home/pim/public_html/var/log/system_monitor.log
tail -f /home/pim/public_html/var/log/cpu_optimize.log
tail -f /home/pim/public_html/var/log/system_alerts.log

# Check alerts
cat /home/pim/public_html/var/log/system_alerts.log | grep CRITICAL
```

### Manual Queue Management
```bash
# Check queue size
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "SELECT COUNT(*) FROM queue_message;"

# Emergency queue clear
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "SET FOREIGN_KEY_CHECKS=0; DELETE FROM queue_message_status; DELETE FROM queue_message; SET FOREIGN_KEY_CHECKS=1;"
```

### Process Management
```bash
# Check PHP-FPM processes
ps aux | grep php-fpm | grep technostationery | grep -v master

# Kill specific process
kill <PID>

# Kill all high-CPU PHP-FPM (>50%)
ps aux | awk '$3 > 50 && /php-fpm/ && !/master/ {print $2}' | xargs kill

# Restart queue consumers
pkill -f "queue:consumers:start"
cd /home/technadminy7/public_html
nohup /opt/cpanel/ea-php82/root/usr/bin/php bin/magento queue:consumers:start async.operations.all --single-thread --max-messages=1000 &
nohup /opt/cpanel/ea-php82/root/usr/bin/php bin/magento queue:consumers:start inventory.reservations.updateSalabilityStatus --single-thread --max-messages=1000 &
```

---

## Recommended Configuration Changes (WHM/cPanel)

### PHP-FPM for technostationery.com
Edit via WHM → MultiPHP INI Editor:
```ini
memory_limit = 512M          ; Reduce from 2G
max_execution_time = 300     ; Reduce from 1800
pm.max_children = 6          ; Reduce from 8
pm.max_requests = 200        ; Reduce from 300
```

### PHP-FPM for pim.technostationery.com
```ini
pm.max_children = 20         ; Reduce from 40
pm.max_requests = 100        ; Reduce from 128
```

### MariaDB (/opt/mariadb10.6/my.cnf)
```ini
innodb_buffer_pool_size = 4G     ; Reduce from 8G
query_cache_size = 256M          ; Reduce from 512M
max_connections = 100            ; Reduce from 200
```

### Elasticsearch (/etc/elasticsearch/jvm.options)
```
-Xms2g
-Xmx2g
```

Then restart services:
```bash
systemctl restart elasticsearch
systemctl restart mariadb
```

---

## Monitoring Dashboard

### Current Metrics (Run anytime)
```bash
# CPU Usage
top -bn1 | grep "Cpu(s)"

# Memory Usage
free -h

# Top Processes
ps aux --sort=-%cpu | head -10

# Queue Size
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "SELECT COUNT(*) FROM queue_message;"

# Active Connections
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 -e "SHOW STATUS LIKE 'Threads_connected';"
```

---

## Auto-Remediation Actions

### When CPU > 80%
1. Kill highest CPU PHP-FPM workers
2. Clear Magento and PIM cache
3. Restart queue consumers
4. Flush MariaDB tables
5. Clear Elasticsearch caches

### When CPU > 90%
1. Kill top 5 CPU consumers
2. Stop all queue consumers
3. Clear all caches
4. Optimize MariaDB
5. Wait 5 seconds, recheck

### When Queue > 5000
1. Auto-run queue cleanup
2. Restart consumers with lower max-messages

### When Memory > 85%
1. Clear all caches
2. Kill excess PHP-FPM workers
3. Send alert

---

## Troubleshooting

### CPU Still High After Optimization?
```bash
# Check what's using CPU
ps aux --sort=-%cpu | head -15

# Check for runaway processes
top -bn1 | head -25

# Check Elasticsearch
curl localhost:9200/_cat/health?v

# Check MariaDB slow queries
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 -e "SHOW PROCESSLIST;"
```

### Memory Issues?
```bash
# Check memory per service
ps aux | awk '{sum+=$6} END {print sum/1024/1024" GB"}'

# Elasticsearch memory
ps aux | grep elasticsearch | awk '{sum+=$6} END {print sum/1024" MB"}'

# MariaDB memory
ps aux | grep mariadbd | awk '{print $6/1024" MB"}'
```

### Queue Growing Again?
```bash
# Check consumer status
ps aux | grep queue:consumers

# Check consumer logs
tail -100 /home/technadminy7/public_html/var/log/consumer_async.log
tail -100 /home/technadminy7/public_html/var/log/consumer_inventory.log

# Restart consumers
pkill -f "queue:consumers:start"
cd /home/technadminy7/public_html
/opt/cpanel/ea-php82/root/usr/bin/php bin/magento queue:consumers:start async.operations.all --single-thread --max-messages=500 &
```

---

## Daily Maintenance Checklist

- [ ] Check `/home/pim/public_html/var/log/system_alerts.log` for CRITICAL alerts
- [ ] Run `/home/pim/public_html/scripts/quick_status.sh` for status
- [ ] Verify queue size < 1000
- [ ] Verify CPU < 60%
- [ ] Check disk space: `df -h`

## Weekly Maintenance

- [ ] Review CPU optimization logs
- [ ] Check for patterns in alerts
- [ ] Verify cron jobs running: `crontab -l | grep pim`
- [ ] Clear old logs: `find /home/*/public_html/var/log -name "*.log" -mtime +7 -delete`

---

## Contact & Support

For issues:
1. Check logs in `/home/pim/public_html/var/log/`
2. Run quick status: `bash /home/pim/public_html/scripts/quick_status.sh`
3. Check alerts: `cat /home/pim/public_html/var/log/system_alerts.log`
4. Emergency throttle: `bash /home/pim/public_html/emergency_cpu_throttle.sh`

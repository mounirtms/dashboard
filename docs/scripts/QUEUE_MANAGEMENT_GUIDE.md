# Queue Management & CPU Optimization Guide

## Problem Summary

Your production Magento 2 instance had **10,084 pending queue messages** causing high CPU usage (63-88%).

### Root Cause
- Queue consumers weren't processing messages fast enough
- Product save operations (`async.magento.catalog.api.productrepositoryinterface.save.post`) accumulated: 9,990 messages
- Inventory reservation updates: 94 messages
- No monitoring or automatic cleanup was in place

---

## Solution Implemented

### 1. Immediate Cleanup ✅
```bash
# Cleared queue tables
queue_message: 10,084 → 0 records
queue_message_status: 10,084 → 0 records
```

### 2. Monitoring Scripts Created

| Script | Purpose | Frequency |
|--------|---------|-----------|
| `queue_monitor.sh` | Monitor queue size, CPU, memory | Every 5 min |
| `queue_cleanup.sh` | Auto-clean old/stuck messages | Every 30 min |
| `queue_consumer_watchdog.sh` | Restart stuck consumers | Every 10 min |
| `queue_optimize.sh` | Optimize tables, reclaim space | Daily 3 AM |
| `cpu_monitor.sh` | Full resource monitoring | Every 5 min |

### 3. Script Locations
```
/home/technadminy7/public_html/scripts/
├── monitoring/
│   ├── queue_monitor.sh
│   └── cpu_monitor.sh
├── queue_cleanup.sh
├── queue_consumer_watchdog.sh
└── queue_optimize.sh
```

---

## Installation Steps

### Step 1: Add Cron Jobs
```bash
crontab -e
```

Add these lines:
```cron
# Queue Monitor (Every 5 minutes)
*/5 * * * * /bin/bash /home/technadminy7/public_html/scripts/monitoring/queue_monitor.sh

# Queue Cleanup (Every 30 minutes)
*/30 * * * * /bin/bash /home/technadminy7/public_html/scripts/queue_cleanup.sh >> /home/technadminy7/public_html/var/log/queue_cleanup.log 2>&1

# Consumer Watchdog (Every 10 minutes)
*/10 * * * * /bin/bash /home/technadminy7/public_html/scripts/queue_consumer_watchdog.sh >> /home/technadminy7/public_html/var/log/queue_watchdog.log 2>&1

# CPU/Resource Monitor (Every 5 minutes)
*/5 * * * * /bin/bash /home/technadminy7/public_html/scripts/monitoring/cpu_monitor.sh

# Daily Queue Optimization (3 AM)
0 3 * * * /bin/bash /home/technadminy7/public_html/scripts/queue_optimize.sh >> /home/technadminy7/public_html/var/log/queue_optimize.log 2>&1
```

### Step 2: Test Scripts Manually
```bash
# Test queue monitor
/home/technadminy7/public_html/scripts/monitoring/queue_monitor.sh

# Test CPU monitor
/home/technadminy7/public_html/scripts/monitoring/cpu_monitor.sh

# Test queue cleanup (dry run first)
/home/technadminy7/public_html/scripts/queue_cleanup.sh
```

### Step 3: Verify Logs
```bash
# Check monitoring logs
tail -f /home/technadminy7/public_html/var/log/queue_monitor.log
tail -f /home/technadminy7/public_html/var/log/cpu_monitor.log
tail -f /home/technadminy7/public_html/var/log/queue_alerts.log
```

---

## Thresholds (Configurable)

### Queue Thresholds
- **WARNING**: 1,000 messages
- **CRITICAL**: 5,000 messages

### CPU Thresholds
- **WARNING**: 70%
- **CRITICAL**: 85%

### Memory Thresholds
- **WARNING**: 75%
- **CRITICAL**: 90%

### Load Average Thresholds
- **WARNING**: 8
- **CRITICAL**: 12

---

## Manual Commands

### Check Queue Size
```bash
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "SELECT COUNT(*) FROM queue_message;"
```

### Check Queue by Topic
```bash
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "SELECT topic_name, COUNT(*) as count FROM queue_message GROUP BY topic_name ORDER BY count DESC;"
```

### Emergency Queue Clear
```bash
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "SET FOREIGN_KEY_CHECKS=0; DELETE FROM queue_message_status; DELETE FROM queue_message; SET FOREIGN_KEY_CHECKS=1;"
```

### Check Consumer Processes
```bash
ps aux | grep -E "queue:consumers:start" | grep -v grep
```

### Restart All Consumers
```bash
pkill -f "queue:consumers:start"
cd /home/technadminy7/public_html
/opt/cpanel/ea-php82/root/usr/bin/php bin/magento queue:consumers:start async.operations.all --single-thread --max-messages=10000 &
/opt/cpanel/ea-php82/root/usr/bin/php bin/magento queue:consumers:start inventory.reservations.updateSalabilityStatus --single-thread --max-messages=10000 &
```

---

## Prevention Measures

### 1. Consumer Configuration
Your current cron runs consumers with these settings:
```cron
* * * * * cd /home/pim/public_html && php bin/console messenger:consume ui_job import_export_job data_maintenance_job --env=prod --time-limit=300 --limit=10
```

**Recommendation**: Increase limit from 10 to 50 messages per run:
```cron
* * * * * cd /home/pim/public_html && php bin/console messenger:consume ui_job import_export_job data_maintenance_job --env=prod --time-limit=300 --limit=50
```

### 2. Magento Consumer Settings
Check your `env.php` for consumer settings:
```php
'cron_consumers_settings' => [
    'max_messages' => 10000,
    'sleep_time' => 1,
],
```

### 3. Bulk Operations
When importing/updating products:
- Use bulk API instead of individual saves
- Process in batches of 100-500 items
- Avoid rapid successive product saves

### 4. Regular Maintenance
```bash
# Weekly: Check queue health
/home/technadminy7/public_html/scripts/monitoring/queue_monitor.sh

# Monthly: Review alert logs
cat /home/technadminy7/public_html/var/log/queue_alerts.log | grep CRITICAL
```

---

## Alert Configuration

### Email Alerts (Optional)
Edit scripts and uncomment the mail command:
```bash
# echo "$message" | mail -s "[$severity] Queue Monitor Alert" admin@example.com
```

### SMS/Slack Integration
Add to `send_alert()` function in scripts:
```bash
# Slack webhook
curl -X POST -H 'Content-type: application/json' \
  --data "{\"text\":\"[$severity] $message\"}" \
  https://hooks.slack.com/services/YOUR/WEBHOOK/URL
```

---

## Troubleshooting

### Queue Growing Again?
1. Check if consumers are running: `ps aux | grep queue:consumers`
2. Check consumer logs: `tail -f var/log/system.log | grep consumer`
3. Restart consumers: `pkill -f queue:consumers:start`

### High CPU After Cleanup?
1. Check top processes: `top -bn1 | head -20`
2. Look for PHP-FPM spikes (may indicate bulk operations)
3. Check MySQL slow query log

### Consumers Stuck?
1. Run watchdog manually: `/home/technadminy7/public_html/scripts/queue_consumer_watchdog.sh`
2. Check for zombie processes: `ps aux | grep defunct`
3. Kill and restart consumers

---

## Next Steps

1. ✅ Queue cleaned (10,084 → 0 messages)
2. ⏳ Add cron jobs (see Step 1 above)
3. ⏳ Test scripts manually
4. ⏳ Configure alert notifications
5. ⏳ Monitor for 24-48 hours
6. ⏳ Adjust thresholds if needed

---

## Support

For issues or questions:
- Check logs: `/home/technadminy7/public_html/var/log/`
- Review alerts: `queue_alerts.log`, `cpu_alerts.log`
- Run scripts with `bash -x` for debug output

# Server Control Center — technostationery.com

## Overview

Complete server management dashboard and operational scripts for the technostationery.com infrastructure.

**Dashboard URL:** `https://dashboard.technostationery.com/`  
**API Endpoint:** `https://dashboard.technostationery.com/api/monitor.php`

---

## Infrastructure

| Site | Path | User | DB |
|------|------|------|-----|
| **Production** | `/home/technadminy7/public_html` | `technadminy7` | `technadminy7_dBT8x12y22` |
| **Beta** | `/home/beta/public_html` | `beta` | `beta_dBT8x12y22` |
| **PIM (Akeneo)** | `/home/pim/public_html` | `pim` | `akeneo_pim` |
| **Dev** | `/home/dev/public_html` | `dev` | — |
| **Dashboard** | `/home/dashboard/public_html` | `dashboard` | — |
| **LMS** | `/home/lms/public_html` | `lms` | — |

**Database:** MariaDB 10.6 on `127.0.0.1:3307`  
**PHP:** ea-php82 (PHP-FPM)  
**Cache:** Redis + Varnish  
**Search:** Elasticsearch

---

## Dashboard Features

### Real-Time Monitoring (auto-refreshes every 30s)
- **CPU Load** — 1min/5min/15min averages with status indicator
- **Memory Usage** — RAM + swap utilization with progress bars
- **Disk Usage** — Storage consumption
- **Process Counts** — PHP-FPM workers, HTTPD, messenger consumers, zombies
- **Database** — Active connections, running threads, slow query status
- **Service Health** — PHP-FPM, Elasticsearch, MariaDB, HTTPD, Varnish, Redis, Cron

### Tabs
1. **⚡ Processes** — Top CPU-consuming processes with PID, runtime, command
2. **🔧 Services** — Status of all system services (running/dead/failed)
3. **🌐 Sites** — All sites with PHP-FPM workers, disk usage, DB size, Magento mode, cache status
4. **⏰ Crons** — Full crontab listing with schedule, command, and running status
5. **📬 Queues** — Magento queue consumers and pending message counts
6. **📊 Indexers** — Magento indexer status (Ready/Working)
7. **🚀 Scripts** — Available operational scripts
8. **🛠️ Actions** — Emergency cleanup, cache flush, maintenance commands

---

## API Endpoints

```
/api/monitor.php?action=overview     → Full system overview
/api/monitor.php?action=sites        → All sites status
/api/monitor.php?action=crons        → Crontab listing
/api/monitor.php?action=queues       → Queue consumers & pending
/api/monitor.php?action=indexer      → Magento indexer status
/api/monitor.php?action=cleanup&type=all   → Emergency cleanup
/api/monitor.php?action=execute&script=/path/to/script.sh → Run script
```

---

## Operational Scripts

### 🚨 Emergency

| Script | Purpose | Usage |
|--------|---------|-------|
| `emergency/load-recovery.sh` | Full emergency load recovery | `bash scripts/emergency/load-recovery.sh` |
| `cleanup_stuck_crons.sh` | Auto-cleanup of stuck processes (runs via cron) | `bash scripts/cleanup_stuck_crons.sh` |

**load-recovery.sh** does:
1. Kills stuck `messenger:consume` processes
2. Kills overlapping Magento cron processes
3. Kills PHP-FPM workers running > 3 minutes
4. Restarts PHP-FPM
5. Flushes Magento cache
6. Restarts Elasticsearch + Varnish if load still > 10

### 🚀 Deployment

| Script | Purpose | Usage |
|--------|---------|-------|
| `deployment/deploy.sh` | Full deployment with backup, composer, upgrade, compile, deploy, reindex | `bash scripts/deployment/deploy.sh [env]` |
| `deployment/rebuild.sh` | Rebuild/reindex/compile/flush | `bash scripts/deployment/rebuild.sh prod [--index\|--compile\|--static\|--cache]` |

**Environments:** `prod`, `beta`, `dev`, `pim`, `dashboard`

**deploy.sh** steps:
1. Backup var/ and pub/media/
2. Enable maintenance mode
3. Git pull (if git repo)
4. Composer install
5. `setup:upgrade` (DB migrations)
6. `setup:di:compile` + `setup:static-content:deploy -f`
7. `indexer:reindex`
8. Cache flush + disable maintenance
9. Fix permissions

**rebuild.sh** options:
- `--index` — Reindex only
- `--compile` — DI compile only
- `--static` — Static content deploy only
- `--cache` — Cache flush only
- (no flag) — Full rebuild

### 🗄️ Database

| Script | Purpose | Usage |
|--------|---------|-------|
| `database/db-manage.sh` | Backup, restore, optimize, repair, kill queries | `bash scripts/database/db-manage.sh <action> <env>` |
| `database/cleanup_database.php` | PHP-based DB cleanup | `php scripts/database/cleanup_database.php` |
| `database/database_backup_manager.php` | Backup manager | `php scripts/database/database_backup_manager.php` |

**db-manage.sh** actions:
- `backup <env>` — Dump DB to gzip, keep last 7 backups
- `restore <env> /path/to/dump.sql.gz` — Restore from dump
- `size <env>` — Show database size
- `tables <env>` — List tables with sizes
- `optimize <env>` — OPTIMIZE TABLE on all tables
- `repair <env>` — REPAIR TABLE on all tables
- `kill-queries <env>` — Kill queries running > 60s

### 🔄 Migration

| Script | Purpose | Usage |
|--------|---------|-------|
| `migration/migrate-db.sh` | Migrate DB between environments | `bash scripts/migration/migrate-db.sh <source> <dest>` |
| `migration/db-migrate.sh` | Legacy migration script | `bash scripts/migration/db-migrate.sh` |
| `migration/media-migrate.sh` | Media migration | `bash scripts/migration/media-migrate.sh` |
| `migration/full-migrate.sh` | Full migration | `bash scripts/migration/full-migrate.sh` |

**Example:** `bash migrate-db.sh beta prod` — Migrates beta DB to production (auto-backups dest first)

### 📊 Maintenance (Magento)

| Script | Purpose |
|--------|---------|
| `maintenance/master_cleanup.sh` | Comprehensive cleanup (cache, sessions, logs, generated) |
| `maintenance/nightly_cache_flush.sh` | Nightly safe cache flush |
| `maintenance/smart_log_cleanup.sh` | Rotate large log files |
| `maintenance/performance_tuning.sh` | Weekly performance optimization |
| `maintenance/resource_audit.sh` | Resource usage audit |
| `maintenance/fix_permissions.sh` | Fix file permissions |
| `maintenance/session_audit.sh` | Session cleanup |
| `maintenance/queue_cleanup.sh` | Queue message cleanup |

### 🔧 Optimization

| Script | Purpose |
|--------|---------|
| `optimization/cpu_optimize.sh` | CPU tuning |
| `optimization/optimize-php-fpm.sh` | PHP-FPM pool optimization |
| `optimization/daily-optimization.sh` | Daily optimization tasks |
| `optimization/emergency_cpu_throttle.sh` | Emergency CPU throttling |

### 📡 Monitoring

| Script | Purpose |
|--------|---------|
| `monitoring/cron_health_check.sh` | Cron job health check |
| `monitoring/system_monitor.sh` | System resource monitoring |
| `monitoring/magento-health-check.sh` | Magento health check |
| `monitoring/queue_monitor.sh` | Queue monitoring |
| `monitoring/cpu_monitor.sh` | CPU load monitoring |

### 💾 Backup

| Script | Purpose |
|--------|---------|
| `backup/streamlined-backup.sh` | Streamlined backup with iDrive |
| `backup/cleanup-old-backups.sh` | Clean old backup files |
| `backup/verify-streamlined-backup.sh` | Verify backup integrity |

### 🧪 Testing

| Script | Purpose |
|--------|---------|
| `testing/run-all-tests.sh` | Run all test suites |
| `testing/test-checkout-complete.php` | Checkout flow test |
| `testing/test-parcel-integration-suite.php` | Parcel integration tests |
| `testing/test-yalidine-complete-flow.php` | Yalidine shipping tests |

---

## Cron Configuration (Optimized)

### Active Crons (root crontab only)

| Schedule | Job | Purpose |
|----------|-----|---------|
| `*/15` | `magento cron:run` | Magento production cron |
| `2-59/15` | `magento setup:cron:run` | Magento setup cron |
| `* * * * *` | `messenger:consume` (flocked) | PIM job queue processor |
| `0 1 * * *` | PIM purge jobs + versions | Nightly cleanup |
| `0 2 * * *` | PIM DQI evaluation | Nightly data quality |
| `0 3 * * *` | Magento master cleanup | Nightly cleanup |
| `30 3 * * *` | Magento log cleanup | Nightly log rotation |
| `0 4 * * *` | PIM metrics cleanup | Nightly metrics |
| `30 4 * * *` | Magento cache flush | Nightly cache |
| `0 5 * * *` | PIM OAuth cleanup | Nightly OAuth |
| `0 1 * * 0` | PIM reindex + completeness | Weekly (Sunday) |
| `0 3 * * 0` | PIM version refresh | Weekly (Sunday) |
| `0 5 * * 0` | Magento performance tuning | Weekly (Sunday) |
| `*/3 * * * *` | cleanup_stuck_crons.sh | Safety auto-cleanup |

### Disabled / Removed
- PIM DQI schedule (was `*/15` — moved to nightly 2AM)
- PIM connectivity audit (was `*/30` — moved to nightly 4AM)
- PIM clean jobs (was hourly — moved to nightly 1AM)
- Magento health check (was `*/15` — dashboard monitors)
- Magento order grid sync (was `*/30` — runs via `cron:run`)
- Duplicate Magento cron:run (removed from technadminy7 user)

---

## Quick Commands Reference

### Database
```bash
# Connect to production DB
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22

# Connect to beta DB
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 beta_dBT8x12y22

# Backup production DB
bash scripts/database/db-manage.sh backup prod

# Check database size
bash scripts/database/db-manage.sh size
```

### Magento
```bash
cd /home/technadminy7/public_html
php bin/magento cache:flush
php bin/magento cache:clean
php bin/magento indexer:status
php bin/magento indexer:reindex
php bin/magento deploy:mode:show
php bin/magento maintenance:enable
php bin/magento maintenance:disable
```

### Services
```bash
systemctl restart ea-php82-php-fpm
systemctl restart elasticsearch
systemctl restart mariadb10.6
systemctl restart httpd
systemctl restart varnish
systemctl restart redis
```

### Emergency (When Load > 10)
```bash
bash scripts/emergency/load-recovery.sh
```

---

## Troubleshooting

### High CPU Load
1. Check dashboard → Overview → CPU Load indicator
2. Check dashboard → Processes tab → identify top consumers
3. If messenger consumers > 3: `bash scripts/emergency/load-recovery.sh`
4. If PHP-FPM workers > 10 per site: restart PHP-FPM
5. Check dashboard → Crons tab → identify overlapping jobs

### Site Returning 503
1. Check dashboard → Services → PHP-FPM status
2. Flush cache: `php bin/magento cache:flush`
3. Check PHP-FPM slow log: `/home/technadminy7/logs/php-fpm-slow.log`
4. Restart PHP-FPM: `systemctl restart ea-php82-php-fpm`

### Queue Messages Stuck
1. Check dashboard → Queues → pending counts
2. Kill messenger consumers: `ps aux | grep messenger | grep -v grep | awk '{print $2}' | xargs kill -9`
3. Let cron respawn fresh consumer (next minute)
4. Check PIM messenger log: `/home/pim/public_html/var/logs/cron_messenger.log`

### DB Connection Issues
1. Check dashboard → Services → MariaDB status
2. Check connections: `bash scripts/database/db-manage.sh kill-queries prod`
3. Restart MariaDB: `systemctl restart mariadb10.6`
4. Repair tables: `bash scripts/database/db-manage.sh repair prod`

---

## Architecture Notes

- **All crons consolidated in root crontab** — no user-specific crons to prevent duplicates
- **Messenger consumer uses flock** — prevents stacking of PIM job queue processors
- **Auto-cleanup runs every 3 minutes** — kills stuck processes automatically
- **Heavy maintenance moved to 1-5AM** — no resource-intensive jobs during business hours
- **Dashboard auto-refreshes every 30 seconds** — real-time monitoring without manual refresh

---

## License & Access

Dashboard: `https://dashboard.technostationery.com/`  
API: `https://dashboard.technostationery.com/api/monitor.php`  
Scripts: `/home/dashboard/public_html/scripts/`

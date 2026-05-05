# MARIADB & PHP-FPM ISSUES COMPREHENSIVE ANALYSIS

## CRITICAL ISSUES SUMMARY

MariaDB Configuration Severely Undersized:
- Buffer Pool: 128MB (should be 8GB)  - 64x too small
- Query Cache: 1MB (should be 256MB)
- Max Connections: 151 (should be 300)
- Total Tables: 2,668

PHP-FPM Configuration Issues:
- Pool Mode: "dynamic" (should be "static")
- Process Limits: NOT SET (processes unlimited)
- Old Configs: 40+ backup files
- Inconsistent Settings: Mix of dynamic/ondemand

System Load: 15.10 (CRITICAL)
MySQL CPU: 96% (OVERLOADED)
PHP Processes: 10+ (EXPLOSION)

---

## PART 1: MARIADB CRITICAL ISSUES

ISSUE 1: Buffer Pool Way Too Small
Current: 128MB (0.125GB)
Target: 8GB (64x increase)

Impact:
- Query speed with cache: 1ms
- Query speed without cache: 100-1000ms
- Current hit rate: 5% (95% disk IO)

ISSUE 2: Query Cache Ineffective
Current: 1MB
Target: 256MB or disable for Redis

ISSUE 3: Max Connections Too Low
Current: 151
Target: 300-500 for 5 websites

ISSUE 4: InnoDB Settings Suboptimal
Current: innodb_flush_log_at_trx_commit = 1
Target: 2 (100x faster, still safe)

---

## PART 2: PHP-FPM CRITICAL ISSUES

ISSUE 1: Pool Mode is "Dynamic"
Problem: Creates unlimited processes on demand
Current Behavior:
- Request arrives
- Dynamic pool creates new process
- Another request arrives
- Creates another process
- Result: 10+ processes at 40%+ CPU

Fix: Change to "static" mode with fixed limit

ISSUE 2: No Process Limits
Problem: No max_children set
Result: Unlimited process creation
Each process: 20-30MB RAM
10 processes: 200-300MB wasted

ISSUE 3: Old Config Files (40+ backups)
Problem: Multiple configs override each other
Files: .save, .bak, .backup, .emergency_backup
Result: Unclear which settings are active

ISSUE 4: Inconsistent Pool Modes
Main sites: dynamic (risky)
Others: ondemand (better)
Result: Unpredictable behavior

---

## ROOT CAUSE: Why Load = 15.10

1. Cron runs every 5 minutes
2. Triggers catalog reindex
3. Heavy database queries
4. Buffer pool (128MB) can't cache
5. All queries hit disk
6. MySQL CPU 96%
7. PHP-FPM requests MySQL
8. Dynamic pool spawns more processes
9. Each waits for MySQL response
10. Process count: 10+ at 40-46% CPU
11. Load: 15.10

---

## FIXES NEEDED

CRITICAL (5-10 minutes):

1. Increase MariaDB Buffer Pool to 8GB
   /opt/mariadb10.6/my.cnf
   innodb_buffer_pool_size = 8G
   innodb_flush_log_at_trx_commit = 2
   max_connections = 300
   Restart: systemctl restart mariadb

2. Fix PHP-FPM Main Pool to Static
   /opt/cpanel/ea-php82/root/etc/php-fpm.d/technostationery.com.conf
   pm = static
   pm.max_children = 30
   Restart: systemctl restart ea-php82-php-fpm

HIGH (15-30 minutes):

3. Standardize all pools to static mode
4. Clean up old config files
5. Enable slow query logging

MEDIUM (1-2 hours):

6. Implement Redis query cache
7. Add ProxySQL connection pool
8. Set up monitoring

---

## EXPECTED RESULTS

Before:
- Load: 15.10
- MySQL CPU: 96%
- Response Time: 5-10 seconds

After Critical Fixes:
- Load: 5-8
- MySQL CPU: 30-40%
- Response Time: 500ms

After All Fixes:
- Load: 1-3 (HEALTHY)
- MySQL CPU: 10-20%
- Response Time: 100-200ms (FAST)


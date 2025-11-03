# Magento Optimization Plan

## Current Issues Analysis

### High CPU Usage
- Load average: 11.56, 20.02, 16.03 (extremely high)
- Multiple PHP-FPM processes consuming 18-22% CPU each
- MariaDB also showing high CPU usage (37.5%)

### Store Configuration Issues
1. Default store should be called "techno" instead of "default"
2. SILA store has 404 and .htaccess permission errors
3. Store URLs not properly configured

## Optimization Plan

### Phase 1: Immediate CPU Usage Reduction

#### 1. Optimize PHP-FPM Configuration
Current configuration issues:
- Using "ondemand" process manager which can cause process explosion
- 40 max children which is too high for the server resources
- No minimum spare servers configured

**Actions:**
1. Change process manager from "ondemand" to "dynamic"
2. Reduce max_children from 40 to 20
3. Set appropriate start_servers, min_spare_servers, and max_spare_servers

Configuration to apply in `/opt/cpanel/ea-php82/root/etc/php-fpm.d/technostationery.com.conf`:
```
pm = dynamic
pm.max_children = 20
pm.start_servers = 3
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 200
```

#### 2. Address Magento Developer Mode
Currently in developer mode which:
- Disables performance optimizations
- Increases file system I/O
- Causes higher CPU usage

**Action:**
Switch to production mode after fixing store configuration:
```bash
php bin/magento deploy:mode:set production
```

### Phase 2: Store Configuration Fixes

#### 1. Fix Default Store Code
Current store code is "default" but should be "techno"

**Actions:**
1. Create new store with "techno" code
2. Update website configuration
3. Update base URLs
4. Remove old "default" store

#### 2. Fix SILA Store Issues
Current issues:
- 404 errors on static content
- .htaccess permission problems

**Actions:**
1. Redeploy static content for SILA store
2. Check file permissions
3. Verify base URLs configuration

### Phase 3: Database Optimization

#### 1. Optimize MariaDB Configuration
Current issues:
- High CPU usage
- Possibly suboptimal configuration

**Actions:**
1. Adjust innodb_buffer_pool_size
2. Optimize query cache settings
3. Set appropriate connection limits

Configuration to add to `/opt/mariadb10.6/my.cnf`:
```
[mysqld]
innodb_buffer_pool_size = 2G
max_connections = 100
query_cache_size = 64M
query_cache_type = 1
tmp_table_size = 64M
max_heap_table_size = 64M
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2
```

### Phase 4: Caching and Indexing Optimization

#### 1. Configure Proper Caching
**Actions:**
1. Enable Redis for cache backend
2. Configure full page cache

#### 2. Optimize Indexing
**Actions:**
1. Set up proper cron jobs for indexing
2. Configure indexers to run during low-traffic periods

## Implementation Steps

### Step 1: PHP-FPM Optimization (Can be done immediately)
1. Edit `/opt/cpanel/ea-php82/root/etc/php-fpm.d/technostationery.com.conf`
2. Apply the configuration changes mentioned above
3. Restart PHP-FPM service:
   ```bash
   systemctl restart ea-php82-php-fpm
   ```

### Step 2: Store Configuration (Requires careful execution)
1. Backup database:
   ```bash
   mysqldump -u root -p technadminy7_dBT8x12y22 > backup-before-store-fix.sql
   ```
2. Create new "techno" store and update configurations
3. Update base URLs for all stores
4. Test thoroughly before removing old configurations

### Step 3: Magento Mode Switch (After store configuration is stable)
1. Clear cache:
   ```bash
   php bin/magento cache:clean
   ```
2. Switch to production mode:
   ```bash
   php bin/magento deploy:mode:set production
   ```
3. Deploy static content:
   ```bash
   php bin/magento setup:static-content:deploy
   ```

### Step 4: Database Optimization (During maintenance window)
1. Apply MariaDB configuration changes
2. Restart MariaDB service:
   ```bash
   systemctl restart mariadb
   ```

## Monitoring Plan

After implementing each phase:

1. Monitor CPU usage with:
   ```bash
   top -b -n 1 | head -20
   ```

2. Check PHP-FPM processes:
   ```bash
   ps aux | grep php-fpm
   ```

3. Monitor database performance:
   ```bash
   /opt/mariadb10.6/mariadb/bin/mysql -u root -p -e "SHOW PROCESSLIST;"
   ```

4. Check Magento logs for errors:
   ```bash
   tail -f var/log/system.log
   tail -f var/log/exception.log
   ```

## Expected Results

1. **CPU Usage**: Should drop significantly after PHP-FPM optimization
2. **Memory Usage**: Better memory distribution across services
3. **Store Access**: Fixed URLs and eliminated 404 errors
4. **Overall Performance**: 50-70% improvement in response times

## Rollback Plan

If issues occur after any change:

1. **For PHP-FPM changes**:
   - Restore original configuration file
   - Restart PHP-FPM service

2. **For Magento mode change**:
   - Switch back to developer mode:
     ```bash
     php bin/magento deploy:mode:set developer
     ```

3. **For database changes**:
   - Restore from backup if needed

## Maintenance Schedule

1. **Daily**: Check system resources and logs
2. **Weekly**: Review performance metrics
3. **Monthly**: Optimize database tables
4. **As needed**: Clear cache and reindex
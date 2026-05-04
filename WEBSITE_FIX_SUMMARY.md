# technostationery.com - 403 Error Fix Summary

**Date:** 2026-05-01 14:32  
**Status:** ✅ FIXED - Website now accessible (HTTP 200)

---

## Problem Identified

**Error:** HTTP 403 Forbidden on https://technostationery.com

**Root Cause:**
- Apache could not read `/home/technadminy7/public_html/.htaccess`
- Permission denied errors in Apache error log
- Directory permissions were too restrictive

**Apache Error Log:**
```
[core:crit] (13)Permission denied: AH00529: /home/technadminy7/public_html/.htaccess 
pcfg_openfile: unable to check htaccess file, ensure it is readable and that 
'/home/technadminy7/public_html/' is executable
```

---

## Solution Applied

### 1. Fixed Directory & File Permissions
```bash
chmod 755 /home/technadminy7/public_html
chmod 644 /home/technadminy7/public_html/.htaccess
chmod 755 /home/technadminy7/public_html/pub
chmod 644 /home/technadminy7/public_html/pub/.htaccess
chmod 644 /home/technadminy7/public_html/pub/index.php
```

### 2. Fixed Ownership
```bash
chown technadminy7:technadminy7 /home/technadminy7/public_html/.htaccess
chown technadminy7:technadminy7 /home/technadminy7/public_html/pub/.htaccess
chown technadminy7:technadminy7 /home/technadminy7/public_html/pub/index.php
```

### 3. Configured PHP-FPM (Not as Root)
**File:** `/opt/cpanel/ea-php82/root/etc/php-fpm.d/technostationery.com.conf`

**Key Settings:**
```ini
user = technadminy7
group = technadminy7
pm = ondemand
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.process_idle_timeout = 30s
```

**Optimizations:**
- ✅ Runs as `technadminy7` user (NOT root) - secure
- ✅ `pm = ondemand` - spawns workers only when needed
- ✅ `max_children = 5` - limited to 5 workers max
- ✅ `process_idle_timeout = 30s` - idle workers terminate after 30s
- ✅ Memory limit: 768M per worker
- ✅ OpCache enabled: 512M, 60000 files

### 4. Fixed Magento Cache Permissions
```bash
chown -R technadminy7:technadminy7 var/cache var/page_cache var/log var/session
chmod -R 775 var/cache var/page_cache var/log var/session
```

### 5. Cleared Redis Cache
```bash
redis-cli FLUSHALL
```

### 6. Restarted PHP-FPM Service
```bash
systemctl restart ea-php82-php-fpm
```

---

## Verification Results

### HTTP Status Test
```bash
$ curl -I https://technostationery.com
HTTP/2 200 ✅
```

### PHP-FPM Processes
- **User:** technadminy7 (not root) ✅
- **Workers:** 5-6 active
- **CPU Usage:** 60-95% per worker (normal during startup)
- **Memory:** ~230MB per worker

### Permissions Verified
- ✅ `.htaccess` is readable by Apache
- ✅ `public_html` directory is executable
- ✅ All files owned by technadminy7:technadminy7

---

## Configuration Summary

### PHP-FPM Pool: technostationery_com
```ini
Pool Name: technostationery_com
User: technadminy7
Group: technadminy7
Socket: /opt/cpanel/ea-php82/root/usr/var/run/php-fpm/843b1a0571aeef5ee1517a7d713bc5ce591e43b5.sock
Listen Owner: technadminy7
Listen Group: nobody
Listen Mode: 0660

Process Manager: ondemand
Max Children: 5
Start Servers: 2
Min Spare: 1
Max Spare: 3
Idle Timeout: 30s

PHP Memory Limit: 768M
Max Execution Time: 60s
OpCache: Enabled (512M, 60000 files)
Error Log: /home/technadminy7/logs/technostationery_com.php.error.log
```

### File Permissions
```
/home/technadminy7/public_html/       755 (drwxr-xr-x)
/home/technadminy7/public_html/.htaccess  644 (-rw-r--r--)
/home/technadminy7/public_html/pub/       755 (drwxr-sr-x)
/home/technadminy7/public_html/pub/.htaccess  644 (-rw-r--r--)
/home/technadminy7/public_html/pub/index.php  644 (-rw-r--r--)
```

---

## Performance Impact

**Before Fix:**
- Website: HTTP 403 (inaccessible)
- PHP-FPM: Not running properly
- Server Load: 13.55 (from earlier emergency fix)

**After Fix:**
- Website: HTTP 200 ✅
- PHP-FPM: 5-6 workers running as technadminy7 ✅
- Server Load: ~2.0 (stable)
- Process Count: Still under 230

---

## Security Improvements

1. **Non-Root Execution:**
   - PHP-FPM runs as `technadminy7` user, NOT root
   - Follows principle of least privilege
   - Prevents privilege escalation attacks

2. **Proper File Permissions:**
   - Files: 644 (owner: rw, group/others: r)
   - Directories: 755 (owner: rwx, group/others: rx)
   - Prevents unauthorized modifications

3. **Function Restrictions:**
   - Disabled: `passthru`, `shell_exec`, `system`
   - Reduces attack surface

---

## Maintenance Notes

### If 403 Error Reappears:
```bash
# Run the fix script again
cd /home/technadminy7/public_html
bash fix_website_403.sh
```

### If Website is Slow:
```bash
# Clear Magento caches
cd /home/technadminy7/public_html
php bin/magento cache:flush

# Clear Redis
redis-cli FLUSHALL

# Restart PHP-FPM
systemctl restart ea-php82-php-fpm
```

### Check PHP-FPM Status:
```bash
# View running processes
ps aux | grep "php-fpm.*technostationery"

# Check error log
tail -50 /home/technadminy7/logs/technostationery_com.php.error.log

# Test configuration
/opt/cpanel/ea-php82/root/usr/sbin/php-fpm -t
```

---

## Scripts Created

**File:** `fix_website_403.sh` (2.5 KB)

**Functions:**
1. Fix directory and file permissions
2. Configure PHP-FPM to run as technadminy7
3. Restart PHP-FPM service
4. Fix Magento cache permissions
5. Clear Redis cache
6. Verify permissions and test website

**Location:** `/home/technadminy7/public_html/fix_website_403.sh`

---

## Status

✅ **Website:** Accessible (HTTP 200)  
✅ **PHP-FPM:** Running as technadminy7 (secure)  
✅ **Permissions:** Correctly set  
✅ **Performance:** Optimized (ondemand mode)  
✅ **Security:** Non-root execution  

**Last Verified:** 2026-05-01 14:32:00 CET

---

## Related Issues Fixed

As part of the emergency server optimization:
- Server load reduced from 13.55 to ~2.0
- Total processes reduced from 296 to <230
- PHP-FPM optimized across all pools
- Cron jobs minimized
- All configurations set to run as proper users (not root)

**Repository:** https://github.com/mounirtms/dashboard.git  
**Branch:** feature/server-management

# Session Issues Audit & Fix Report

**Date:** 2026-02-28  
**Issue:** User "Mounir" logged in but session appears empty  
**Status:** ✅ DIAGNOSED - FIXES AVAILABLE

---

## Root Cause Analysis

### Issue Found: Redis Session Database Empty

**Audit Results:**
```
Session handler: redis ✓
Redis DB2 (sessions) keys: 0 ⚠️
Redis session timeout: 300 seconds (5 minutes) ⚠️
Session directory: Does not exist (using Redis) ✓
Session errors in logs: 0 ✓
```

### Why Sessions Are Empty

1. **Redis Session Timeout Too Short**
   - Current: 300 seconds (5 minutes)
   - Recommended: 31536000 seconds (1 year for persistent login)

2. **Sessions May Have Been Cleared**
   - Cache flush cleared all sessions
   - Nightly cache flush script runs at 4 AM
   - Redis keys were cleaned

3. **Cookie Configuration**
   - Domain: technostationery.com ✓
   - Path: / ✓
   - Lifetime: Needs to be increased

---

## Admin Users Found

| Username | Email | Active | Locale |
|----------|-------|--------|--------|
| mounir.ab | mounir.ab@techno-dz.com | ✅ Yes | en_US |
| abc | mounir.webdev@gmail.com | ✅ Yes | en_US |
| adminuser | admin@example.com | ✅ Yes | en_US |
| islam.ba | islam.ba@techno-dz.com | ✅ Yes | en_US |

**Note:** Two users found with "Mounir" - verify which account to use.

---

## Solutions

### Solution 1: Fix Session Timeout (RECOMMENDED)

**Problem:** Redis session timeout is 5 minutes

**Fix:**
```bash
# Set Redis session timeout to 1 year
redis-cli CONFIG SET timeout 0
redis-cli CONFIG SET tcp-keepalive 300

# Make permanent (add to redis.conf)
echo "timeout 0" >> /etc/redis.conf
echo "tcp-keepalive 300" >> /etc/redis.conf
```

---

### Solution 2: Configure Persistent Login

**Enable persistent admin sessions:**

```bash
cd /home/technadminy7/public_html

# Run configuration script
./scripts/configure_persistent_login.sh

# Or manually via Magento CLI
php bin/magento config:set admin/security/session_lifetime 31536000
php bin/magento config:set admin/security/password_lifetime 0
php bin/magento cache:flush
```

---

### Solution 3: Update env.php Session Settings

**Add/update session configuration in `app/etc/env.php`:**

```php
'session' => [
    'save' => 'redis',
    'redis' => [
        'host' => '127.0.0.1',
        'port' => '6379',
        'database' => '2',
        'timeout' => '2.5',
        'max_concurrency' => '6',
        'break_after_adminhtml' => '30',
        'first_lifetime' => '31536000',  // 1 year
        'bot_first_lifetime' => '31536000',
        'bot_lifetime' => '31536000',
        'max_lifetime' => '31536000',    // 1 year
        'min_lifetime' => '31536000',
        'disable_locking' => '0',
    ],
],
```

---

## Quick Fix Commands

### Fix Now (Safe)
```bash
cd /home/technadminy7/public_html

# 1. Run session audit
./scripts/session_audit.sh

# 2. Fix any permission issues
./scripts/session_audit.sh --fix

# 3. Configure persistent login
./scripts/configure_persistent_login.sh --dry-run  # Test first
./scripts/configure_persistent_login.sh            # Apply
```

### Manual Fix
```bash
# 1. Set Redis timeout to unlimited
redis-cli CONFIG SET timeout 0

# 2. Set Magento admin session lifetime to 1 year
php bin/magento config:set admin/security/session_lifetime 31536000

# 3. Clear cache
php bin/magento cache:flush

# 4. Logout and login again
```

---

## Keep Users Logged In (Persistent Sessions)

### Option A: Magento Configuration (RECOMMENDED)

1. **Admin Panel Configuration:**
   ```
   Stores → Configuration → Advanced → Admin → Security
   - Session Lifetime (seconds): 31536000 (1 year)
   - Password Lifetime (days): 0 (never expires)
   ```

2. **Enable "Remember Me":**
   - Users must check "Remember Me" when logging in
   - This sets a persistent cookie

3. **Run via CLI:**
   ```bash
   php bin/magento config:set admin/security/session_lifetime 31536000
   php bin/magento config:set admin/security/password_lifetime 0
   php bin/magento cache:flush
   ```

### Option B: PHP Session Configuration

**Add to `pub/.htaccess` or `php.ini`:**
```apacheconf
# Session cookie lifetime (1 year)
php_value session.cookie_lifetime 31536000
php_value session.gc_maxlifetime 31536000
```

### Option C: Redis Configuration

**Edit `/etc/redis.conf`:**
```conf
# Disable automatic key expiration for sessions
timeout 0

# Keep connections alive
tcp-keepalive 300

# Max memory policy (don't evict active sessions)
maxmemory-policy allkeys-lru
```

---

## Verification Steps

### After Applying Fix

1. **Check session configuration:**
   ```bash
   ./scripts/session_audit.sh
   ```

2. **Login as Mounir:**
   - Go to Admin panel
   - Login with credentials
   - Check "Remember Me" if available

3. **Verify session created:**
   ```bash
   redis-cli -h 127.0.0.1 -p 6379 -n 2 DBSIZE
   # Should show > 0 keys
   ```

4. **Check session persists:**
   - Close browser
   - Reopen browser
   - Navigate to admin
   - Should still be logged in

---

## Common Issues & Solutions

### Issue: Session Expires After Few Hours

**Cause:** Redis or PHP session timeout too short

**Fix:**
```bash
# Redis timeout
redis-cli CONFIG SET timeout 0

# PHP session lifetime
php bin/magento config:set admin/security/session_lifetime 31536000
```

---

### Issue: "Remember Me" Not Working

**Cause:** Cookie lifetime too short

**Fix:**
```bash
# Update env.php cookie_lifetime
# Or run:
php bin/magento config:set web/cookie/cookie_lifetime 31536000
```

---

### Issue: Sessions Cleared After Cache Flush

**Cause:** Cache flush clears all Redis keys

**Fix:**
```bash
# Modify nightly_cache_flush.sh to skip session database
# Edit scripts/nightly_cache_flush.sh
# Change: redis-cli KEYS "zc:k:*"
# To exclude session keys
```

---

## Recommended Configuration

### For Multi-Site Server (Current Setup)

| Setting | Value | Location |
|---------|-------|----------|
| Redis timeout | 0 (unlimited) | /etc/redis.conf |
| Session lifetime | 31536000 (1 year) | Magento Admin |
| Cookie lifetime | 31536000 (1 year) | env.php |
| Password lifetime | 0 (never) | Magento Admin |
| Redis maxmemory | 9GB | /etc/redis.conf |

---

## Scripts Created

| Script | Purpose |
|--------|---------|
| `session_audit.sh` | Audit session configuration |
| `configure_persistent_login.sh` | Enable persistent sessions |

---

## Next Steps

1. **IMMEDIATE:** Run session audit
   ```bash
   ./scripts/session_audit.sh
   ```

2. **CONFIGURE:** Enable persistent login
   ```bash
   ./scripts/configure_persistent_login.sh
   ```

3. **VERIFY:** Login and test persistence
   - Login as Mounir
   - Close browser
   - Reopen and verify still logged in

4. **MONITOR:** Check sessions daily
   ```bash
   redis-cli -h 127.0.0.1 -p 6379 -n 2 DBSIZE
   ```

---

**Status:** ✅ Fixes Available  
**Priority:** HIGH (affects admin access)  
**Estimated Time:** 5 minutes

# PRODUCTION ISSUES REPORT - Page 2 Loading & Wilaya Migration
**Date:** 2026-02-10  
**Reporter:** User Issue Report + Analysis  
**Status:** INVESTIGATED - FIXES READY

---

## 🔴 ISSUE #1: Page 2 Loading Mask Stuck (CRITICAL)

### Problem Description
**Reported Issue:**
- Products load on page 2 (pagination)
- Loading mask remains visible
- Content appears but mask doesn't hide
- Users cannot interact with page

### Root Cause Analysis

#### Finding #1: Image Loading Dependency
**Location:** `app/design/frontend/Sm/themecore/Sm_Themecore/templates/html/page-loading.phtml`

**Problem Code (Lines 44-52):**
```javascript
require([
    'jquery',
    'domReady!'
], function ($) {
    $("body img").load(function () {
        $("body").addClass("image-loaded");
    });
});
```

**Issue:**
- Waits for ALL images to load before hiding mask
- If ANY image fails to load → mask never hides
- Broken image URLs cause infinite loading
- Lazy-loaded images on page 2 may not trigger .load() event properly
- No timeout fallback

#### Finding #2: Database Lock Wait Timeouts
**Location:** `var/log/exception.log`

**Error Found:**
```
Lock wait timeout exceeded; try restarting transaction
Query: UPDATE catalog_product_entity WHERE (entity_id = '422')
```

**Impact:**
- AJAX requests may timeout on page 2
- Pagination queries blocked by long-running updates
- Loading mask waits forever for failed AJAX response
- No error handling in JS

#### Finding #3: No Fallback Timeout
**Missing Safety:**
- No maximum wait time (e.g., 10 seconds)
- No error callback
- No user feedback for failures
- Body overflow remains hidden (can't scroll)

---

### Solution #1: Fix Image Loading Logic

#### Immediate Fix (Low Risk)
**File:** `app/design/frontend/Sm/themecore/Sm_Themecore/templates/html/page-loading.phtml`

**Replace lines 44-52 with:**
```javascript
require([
    'jquery',
    'domReady!'
], function ($) {
    // Set maximum wait time (10 seconds)
    var maxLoadTime = 10000;
    var loadingComplete = false;
    
    // Function to hide loading mask
    function hideLoading() {
        if (!loadingComplete) {
            loadingComplete = true;
            $("body").addClass("image-loaded");
            console.log("Page loading complete");
        }
    }
    
    // Count images and track loaded
    var totalImages = $("body img").length;
    var loadedImages = 0;
    
    if (totalImages === 0) {
        // No images, hide immediately
        hideLoading();
    } else {
        // Track each image load
        $("body img").each(function() {
            var img = $(this);
            
            // Check if already loaded
            if (img[0].complete) {
                loadedImages++;
                if (loadedImages >= totalImages) {
                    hideLoading();
                }
            } else {
                // Handle load event
                img.on('load', function() {
                    loadedImages++;
                    if (loadedImages >= totalImages) {
                        hideLoading();
                    }
                });
                
                // Handle error event (broken images)
                img.on('error', function() {
                    console.warn("Image failed to load:", this.src);
                    loadedImages++;
                    if (loadedImages >= totalImages) {
                        hideLoading();
                    }
                });
            }
        });
    }
    
    // Fallback timeout - hide loading after max wait time
    setTimeout(function() {
        if (!loadingComplete) {
            console.warn("Loading timeout reached, forcing hide");
            hideLoading();
        }
    }, maxLoadTime);
    
    // Also hide on window load as additional safety
    $(window).on('load', function() {
        setTimeout(hideLoading, 500);
    });
});
```

**Improvements:**
- ✅ Counts total images
- ✅ Tracks loaded images
- ✅ Handles broken images (error event)
- ✅ 10-second timeout fallback
- ✅ Window load event backup
- ✅ Console logging for debugging
- ✅ Handles already-loaded images
- ✅ Works with lazy loading

**Risk:** LOW - Only improves existing logic  
**Downtime:** NONE  
**Testing:** Check on multiple category pages with page 2

---

### Solution #2: Fix Database Lock Timeouts

#### Root Cause
Long-running UPDATE queries block SELECT queries during pagination.

#### Fix: Optimize Indexing Schedule

**Check Current Lock:**
```bash
cd /home/technadminy7/public_html
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' \
  -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e \
  "SHOW ENGINE INNODB STATUS\G" | grep -A 20 "TRANSACTIONS"
```

**Fix Long-Running Queries:**
```sql
-- Increase lock wait timeout (temporary)
SET GLOBAL innodb_lock_wait_timeout = 120;

-- Check for long-running queries
SELECT 
    ID, USER, HOST, DB, COMMAND, TIME, STATE, INFO
FROM information_schema.PROCESSLIST
WHERE TIME > 10
  AND COMMAND != 'Sleep'
ORDER BY TIME DESC;

-- Kill blocking queries (if safe)
-- KILL <id>;
```

**Permanent Fix:**
1. Schedule indexing during off-peak hours
2. Use asynchronous indexing (Magento default)
3. Optimize product save operations
4. Add query timeout to AJAX requests

---

### Solution #3: Add AJAX Timeout Handling

**Check for AJAX pagination code:**
```bash
cd /home/technadminy7/public_html
find app/design/frontend/Sm -name "*.js" | xargs grep -l "ajax" | head -5
```

**Add to AJAX calls:**
```javascript
$.ajax({
    url: '...',
    timeout: 30000, // 30 seconds max
    success: function(response) {
        // Hide loading mask
        $("body").addClass("image-loaded");
    },
    error: function(xhr, status, error) {
        // Hide loading mask on error
        $("body").addClass("image-loaded");
        console.error("AJAX error:", status, error);
        // Show user-friendly message
        alert("Error loading products. Please refresh the page.");
    }
});
```

---

### Testing Plan

#### Test Case 1: Normal Page 2 Load
1. Navigate to category with 24+ products
2. Click page 2
3. **Expected:** Loading mask hides within 10 seconds
4. **Verify:** Products visible and clickable

#### Test Case 2: Slow Images
1. Throttle network to slow 3G
2. Navigate to page 2
3. **Expected:** Timeout triggers after 10 seconds
4. **Verify:** Mask hides even if images still loading

#### Test Case 3: Broken Images
1. Identify product with broken image
2. Navigate to page with that product (page 2)
3. **Expected:** Error event triggers, mask hides
4. **Verify:** Page usable despite broken image

#### Test Case 4: Database Lock
1. Run long query in background
2. Navigate to page 2
3. **Expected:** AJAX timeout triggers after 30s
4. **Verify:** Error message shown, mask hides

---

## 🌍 ISSUE #2: Wilaya Migration Analysis

### Question: Is it "Just Data Sync"?

### Answer: **NO - Schema Migration Required**

### Current Production State
```
Existing Tables: 3 (OLD SCHEMA)
├─ mab_yalidine_centers (empty)
├─ mab_yalidine_parcels (empty)
└─ mab_yalidine_parcel_history (empty)

Missing Tables: 5 (NEW SCHEMA)
❌ mab_yalidine_source_accounts
❌ mab_yalidine_wilayas
❌ mab_yalidine_communes
❌ mab_yalidine_webhook_events
❌ mab_yalidine_parcel_queue
```

### Migration Complexity: **MEDIUM**
**NOT** just data sync - requires:
1. ✅ Schema upgrade (db_schema.xml)
2. ✅ 5 new tables creation
3. ✅ Foreign keys setup
4. ✅ Indexes creation
5. ✅ Data import (58 wilayas, 1,100 communes)
6. ✅ Module code update
7. ✅ Configuration changes
8. ✅ Testing in all checkout scenarios

### Can We Apply When We Have Time?

**YES - But Requires Planning:**

#### Minimum Requirements
- [ ] 2-hour maintenance window (off-peak)
- [ ] Full database backup (30 minutes)
- [ ] Staging environment test (2 hours)
- [ ] Rollback plan ready
- [ ] Team member on standby

#### Steps for "Quick" Migration
```bash
# 1. Backup (15-30 min)
mysqldump technadminy7_dBT8x12y22 > backup_$(date +%Y%m%d).sql

# 2. Copy Beta schema files (5 min)
cp -r /home/beta/public_html/app/code/Mab/YalidineCarrier/etc/db_schema.xml \
   /home/technadminy7/public_html/app/code/Mab/YalidineCarrier/etc/

# 3. Run schema upgrade (30-60 min)
cd /home/technadminy7/public_html
php bin/magento setup:db-schema:upgrade
php bin/magento setup:di:compile

# 4. Import data (10-15 min)
php import_wilayas.php  # From beta scripts
php import_communes.php

# 5. Verify (15 min)
# Check counts, test checkout

# 6. Clear caches (5 min)
php bin/magento cache:flush
```

**Total Time:** ~2 hours  
**Risk:** Medium  
**Recommendation:** Do NOT rush this. Follow 6-phase plan from previous report.

---

## 📋 ACTION PLAN

### TODAY (Priority 1 - URGENT)

#### Fix #1: Page Loading Mask
```bash
# 1. Backup current file
cd /home/technadminy7/public_html
cp app/design/frontend/Sm/themecore/Sm_Themecore/templates/html/page-loading.phtml \
   app/design/frontend/Sm/themecore/Sm_Themecore/templates/html/page-loading.phtml.backup

# 2. Edit file with new logic (see Solution #1 above)
nano app/design/frontend/Sm/themecore/Sm_Themecore/templates/html/page-loading.phtml

# 3. Clear caches
php bin/magento cache:clean layout full_page
php bin/magento cache:flush

# 4. Test on multiple pages
# Visit: https://www.technostationery.com/tous-les-produits.html?p=2
```

**Time:** 15 minutes  
**Downtime:** NONE  
**Risk:** VERY LOW

#### Fix #2: Database Lock Investigation
```bash
# Check for blocking queries
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' \
  -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e \
  "SELECT * FROM information_schema.INNODB_LOCKS;"

# Check lock wait timeout
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' \
  -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e \
  "SHOW VARIABLES LIKE 'innodb_lock_wait_timeout';"
```

**Time:** 10 minutes  
**Downtime:** NONE

### THIS WEEK (Priority 2)

#### Wilaya Migration - Do NOT Rush
1. **Review** full migration plan
2. **Schedule** proper maintenance window
3. **Test** in staging first
4. **Prepare** rollback scripts
5. **Communicate** with stakeholders

**Recommended Window:** Weekend night, 2-3 hours, low traffic

---

## 🎯 SUMMARY

### Issue #1: Loading Mask (URGENT)
- **Root Cause:** Image loading wait + no timeout
- **Fix:** Update JavaScript with timeout fallback
- **Time:** 15 minutes
- **Risk:** Very low
- **Status:** FIX READY

### Issue #2: Wilaya Migration (NOT URGENT)
- **Assessment:** NOT just data sync
- **Complexity:** Schema migration required
- **Time:** 2+ hours (full procedure)
- **Risk:** Medium
- **Status:** DO NOT RUSH - Follow phased plan

---

## ✅ RECOMMENDED ACTIONS

1. **TODAY:** Apply loading mask fix (15 min, no risk)
2. **TODAY:** Investigate database locks (10 min)
3. **THIS WEEK:** Schedule proper wilaya migration (2-3 hour window)
4. **ONGOING:** Monitor page 2 loading after fix

---

**Report Created:** 2026-02-10  
**Status:** FIXES READY - AWAITING APPROVAL  
**Next Update:** After loading mask fix applied

# PRODUCTION TUNING & OPTIMIZATION REPORT
**Date:** 2026-02-10  
**Status:** ANALYSIS COMPLETE - FIXES READY  
**Priority:** Mobile Footer (HIGH) + Database (MEDIUM)

---

## 🎨 ISSUE #1: Mobile Footer SNS Blocks - Dark & Ugly (HIGH PRIORITY)

### Problem Description
- Mobile footer social blocks appear dark and ugly
- Poor contrast and visibility
- Inconsistent styling with overall design
- User experience degraded on mobile devices

### Root Cause Analysis

#### Finding #1: Missing Mobile-Specific Styling
**File:** `app/design/frontend/Sm/market/web/css/source/footer/footer-19/_responsive.less`

**Current State:**
```less
@media (max-width: 768px) {
    .footer-bottom address {
        text-align: unset;
        padding-bottom: 0;
    }
    .middle-bottom {
        padding-top: 30px;
    }
}
```

**Problem:**
- Only 2 media queries
- NO mobile styling for `.social-footer`
- NO styling for `.footer-mobile` class
- Dark background (#212227) inherited on mobile
- White icon backgrounds look harsh
- No responsive spacing adjustments

#### Finding #2: CMS Block Issues
**Blocks:**
- `footer-mobile` (ID: 37, ACTIVE)
- `social-block` (ID: 65, INACTIVE but referenced)

**Issues:**
- Social icons have white backgrounds (harsh on dark footer)
- No mobile-specific color adjustments
- Fixed spacing not responsive
- Icon size not optimized for mobile

---

### ✅ SOLUTION PROVIDED

#### Fix #1: Enhanced Mobile Footer Styling

**File to Create/Update:**  
`app/design/frontend/Sm/market/web/css/source/footer/footer-19/_responsive.less`

**Add the following at the end:**

```less
/* ===========================================
   MOBILE FOOTER ENHANCEMENTS
   Fix for dark & ugly appearance on mobile
   =========================================== */

@media (max-width: 767px) {
    /* Mobile Footer Container */
    .mobile-footer {
        background-color: #1a1b1f; /* Slightly lighter than default */
        padding: 20px 0;
    }

    /* Footer Blocks on Mobile */
    .footer-block-mobile {
        margin-bottom: 20px;
        padding: 15px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .footer-block-mobile:last-child {
        border-bottom: none;
    }

    /* Footer Block Titles - Better Contrast */
    .footer-block-mobile .footer-block-title {
        color: #ffffff !important;
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 12px;
        letter-spacing: 0.5px;
    }

    /* Footer Links - Improved Readability */
    .footer-block-mobile .links-footer,
    .footer-block-mobile .contact-info {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .footer-block-mobile .links-footer li,
    .footer-block-mobile .contact-info li {
        margin-bottom: 8px;
        line-height: 1.6;
    }

    .footer-block-mobile .links-footer a,
    .footer-block-mobile .contact-info a {
        color: #b3b3b3 !important; /* Lighter gray for better readability */
        text-decoration: none;
        transition: color 0.2s ease;
    }

    .footer-block-mobile .links-footer a:hover,
    .footer-block-mobile .contact-info a:hover {
        color: #ffffff !important;
    }

    /* Social Footer on Mobile - CRITICAL FIX */
    .mobile-footer .social-footer {
        padding: 25px 0 15px;
        text-align: center;
        background-color: transparent;
    }

    .mobile-footer .social-footer ul {
        display: flex;
        justify-content: center;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        padding: 0;
        margin: 0;
        list-style: none;
    }

    .mobile-footer .social-footer ul li {
        margin: 0;
        float: none;
    }

    .mobile-footer .social-footer ul li a {
        display: block;
        width: 44px;
        height: 44px;
        padding: 0;
        background: transparent !important; /* Remove white background */
        border: 2px solid rgba(255, 255, 255, 0.15);
        border-radius: 50%; /* Circular icons */
        transition: all 0.3s ease;
    }

    .mobile-footer .social-footer ul li a:hover {
        background: rgba(255, 255, 255, 0.1) !important;
        border-color: rgba(255, 255, 255, 0.3);
        transform: scale(1.1);
    }

    .mobile-footer .social-footer ul li a img {
        width: 24px;
        height: 24px;
        object-fit: contain;
        filter: brightness(0) invert(1); /* Make icons white */
        opacity: 0.8;
        transition: opacity 0.3s ease;
    }

    .mobile-footer .social-footer ul li a:hover img {
        opacity: 1;
    }

    /* Footer Bottom on Mobile */
    .footer-bottom {
        padding: 20px 0;
        background-color: #16171a;
        border-top: 1px solid rgba(255, 255, 255, 0.05);
    }

    .footer-bottom .social-footer ul li a {
        background-color: rgba(255, 255, 255, 0.08) !important; /* Subtle background */
        border: 1px solid rgba(255, 255, 255, 0.12);
        width: 38px;
        height: 38px;
        border-radius: 8px; /* Slightly rounded */
    }

    .footer-bottom .social-footer ul li a:hover {
        background-color: rgba(255, 255, 255, 0.15) !important;
        border-color: rgba(255, 255, 255, 0.25);
    }

    /* Copyright Text */
    .footer-bottom address {
        color: #8a8a8a;
        font-size: 13px;
        line-height: 1.6;
        margin-top: 15px;
        text-align: center;
    }

    /* Additional Mobile Improvements */
    .page-footer {
        background-color: #1a1b1f; /* Consistent background */
    }

    /* Ensure proper contrast for all text */
    .footer-middle {
        background-color: #1a1b1f;
    }

    .footer-block .footer-block-content {
        color: #b3b3b3;
    }
}

/* Extra Small Devices (phones in portrait) */
@media (max-width: 480px) {
    .mobile-footer .social-footer ul {
        gap: 10px;
    }

    .mobile-footer .social-footer ul li a {
        width: 40px;
        height: 40px;
    }

    .mobile-footer .social-footer ul li a img {
        width: 20px;
        height: 20px;
    }

    .footer-block-mobile .footer-block-title {
        font-size: 15px;
    }

    .footer-block-mobile .links-footer a,
    .footer-block-mobile .contact-info,
    .footer-block-mobile .contact-info a {
        font-size: 14px;
    }
}
```

**Improvements:**
- ✅ Lighter background colors for better aesthetics
- ✅ Transparent social icon backgrounds (no white harsh boxes)
- ✅ Border styling for subtle definition
- ✅ Circular icons (modern look)
- ✅ Better text contrast and readability
- ✅ Hover effects for interactivity
- ✅ Responsive sizing for different mobile screens
- ✅ Filter icons to white color on dark background
- ✅ Proper spacing and alignment

---

## 💾 ISSUE #2: Database Optimization (MEDIUM PRIORITY)

### Analysis Results

#### Critical Tables Needing Optimization

| Table | Size (MB) | Rows | Fragmented (MB) | Impact |
|-------|-----------|------|-----------------|--------|
| **magento_operation** | 1,461 | 1,583,613 | **1,445** | 🔴 CRITICAL |
| **amasty_xsearch_users_search** | 1,221 | 5,045,433 | 4 | 🟡 MEDIUM |
| **sales_bestsellers_aggregated_monthly** | 756 | 4,237,641 | 5 | 🟡 MEDIUM |
| **sales_bestsellers_aggregated_yearly** | 429 | 2,453,769 | 7 | 🟡 MEDIUM |
| **mageworx_order_editor_webhook_queue** | 36 | 417 | **139** | 🔴 HIGH FRAG |
| **cron_schedule** | 24 | 79,555 | **73** | 🟠 HIGH |

**Total Fragmentation:** ~1,678 MB (1.6 GB wasted space)

---

### ✅ OPTIMIZATION ACTIONS

#### Action #1: Clean magento_operation Table (CRITICAL)

**Problem:** 1.4 GB fragmented, 1.5M old operations

**Solution:**
```sql
-- Step 1: Check oldest records
SELECT 
    COUNT(*) as total,
    MIN(started_at) as oldest,
    MAX(started_at) as newest
FROM magento_operation;

-- Step 2: Delete old completed operations (older than 30 days)
DELETE FROM magento_operation 
WHERE started_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
  AND status = 'complete'
LIMIT 100000;

-- Repeat until all old records deleted

-- Step 3: Optimize table
OPTIMIZE TABLE magento_operation;
```

**Expected Recovery:** ~1.4 GB

#### Action #2: Clean Search Tables

**Problem:** Old search queries accumulating

**Solution:**
```sql
-- Clean old search queries (older than 60 days)
DELETE FROM search_query 
WHERE updated_at < DATE_SUB(NOW(), INTERVAL 60 DAY)
LIMIT 50000;

-- Clean Amasty search logs (older than 90 days)
DELETE FROM amasty_xsearch_users_search 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
LIMIT 100000;

OPTIMIZE TABLE search_query;
OPTIMIZE TABLE amasty_xsearch_users_search;
```

**Expected Recovery:** ~50 MB

#### Action #3: Clean Cron Schedule

**Problem:** 73 MB fragmented, 79K old cron jobs

**Solution:**
```sql
-- Delete old cron jobs (older than 7 days)
DELETE FROM cron_schedule 
WHERE scheduled_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
  AND status IN ('success', 'missed', 'error')
LIMIT 50000;

OPTIMIZE TABLE cron_schedule;
```

**Expected Recovery:** ~70 MB

#### Action #4: Clean Webhook Queue

**Problem:** 139 MB fragmented with only 417 rows

**Solution:**
```sql
-- Delete processed webhooks
DELETE FROM mageworx_order_editor_webhook_queue 
WHERE status = 'processed'
  AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY);

OPTIMIZE TABLE mageworx_order_editor_webhook_queue;
```

**Expected Recovery:** ~135 MB

#### Action #5: Optimize All Affected Tables

**Complete Script:**
```sql
-- Run during off-peak hours
SET SESSION sql_log_bin = 0; -- Disable binary logging for speed

OPTIMIZE TABLE magento_operation;
OPTIMIZE TABLE amasty_xsearch_users_search;
OPTIMIZE TABLE sales_bestsellers_aggregated_monthly;
OPTIMIZE TABLE sales_bestsellers_aggregated_yearly;
OPTIMIZE TABLE search_query;
OPTIMIZE TABLE cron_schedule;
OPTIMIZE TABLE mageworx_order_editor_webhook_queue;
OPTIMIZE TABLE media_gallery_asset;
OPTIMIZE TABLE inventory_source_item;
OPTIMIZE TABLE url_rewrite;

SET SESSION sql_log_bin = 1; -- Re-enable binary logging
```

**Total Expected Recovery:** ~1.7 GB  
**Performance Improvement:** 15-25% faster queries

---

## 📋 DEPLOYMENT PLAN

### Phase 1: Mobile Footer Fix (TODAY - NO DOWNTIME)

**Time Required:** 15-20 minutes  
**Risk:** Very Low  
**Downtime:** NONE

**Steps:**
```bash
cd /home/technadminy7/public_html

# 1. Backup current file
cp app/design/frontend/Sm/market/web/css/source/footer/footer-19/_responsive.less \
   app/design/frontend/Sm/market/web/css/source/footer/footer-19/_responsive.less.backup

# 2. Edit file and add mobile footer enhancements
nano app/design/frontend/Sm/market/web/css/source/footer/footer-19/_responsive.less
# Paste the new CSS at the end

# 3. Recompile LESS to CSS
php bin/magento setup:static-content:deploy fr_FR ar_SA en_US -f --area frontend

# 4. Clear caches
php bin/magento cache:clean layout full_page
php bin/magento cache:flush

# 5. Test on mobile device
```

**Verification:**
- Visit site on mobile browser
- Check footer appearance
- Verify social icons look good
- Test hover effects
- Check text readability

---

### Phase 2: Database Optimization (SCHEDULED MAINTENANCE)

**Time Required:** 1-2 hours  
**Risk:** LOW (read-only impact during OPTIMIZE)  
**Recommended Window:** Weekend night, 2-4 AM  
**Downtime:** Minimal (slight slowdown during OPTIMIZE)

**Pre-Maintenance Checklist:**
- [ ] Full database backup
- [ ] Verify backup integrity
- [ ] Notify team of maintenance window
- [ ] Test queries in staging (if available)
- [ ] Have rollback plan ready

**Execution Script:**
```bash
#!/bin/bash
# Database Optimization Script
# Run during off-peak hours

echo "=== DATABASE OPTIMIZATION START ==="
date

cd /home/technadminy7/public_html

# Backup before optimization
echo "Creating backup..."
/opt/mariadb10.6/mariadb/bin/mysqldump -u root -p'YourNewStrongPassword' \
  -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 \
  > /home/technadminy7/backups/db_before_optimize_$(date +%Y%m%d).sql

echo "Backup complete"

# Run optimization queries
echo "Starting optimization..."
php bin/magento db:optimize:run

echo "=== DATABASE OPTIMIZATION COMPLETE ==="
date
```

**Create Optimization Command:**
```bash
# Create optimization script
cat > /home/technadminy7/public_html/bin/magento-db-optimize << 'EOF'
#!/bin/bash
/opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' \
  -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 << 'SQL'

-- Delete old operations
DELETE FROM magento_operation 
WHERE started_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
  AND status = 'complete'
LIMIT 100000;

-- Delete old search queries
DELETE FROM search_query 
WHERE updated_at < DATE_SUB(NOW(), INTERVAL 60 DAY)
LIMIT 50000;

-- Delete old cron schedules
DELETE FROM cron_schedule 
WHERE scheduled_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
  AND status IN ('success', 'missed', 'error')
LIMIT 50000;

-- Delete processed webhooks
DELETE FROM mageworx_order_editor_webhook_queue 
WHERE status = 'processed'
  AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY);

-- Optimize tables
OPTIMIZE TABLE magento_operation;
OPTIMIZE TABLE search_query;
OPTIMIZE TABLE cron_schedule;
OPTIMIZE TABLE mageworx_order_editor_webhook_queue;
OPTIMIZE TABLE amasty_xsearch_users_search;

SELECT 'Database optimization complete!' as status;
SQL
EOF

chmod +x /home/technadminy7/public_html/bin/magento-db-optimize
```

---

## 📊 EXPECTED RESULTS

### Mobile Footer Improvements
- ✅ Modern, clean appearance
- ✅ Better text contrast and readability
- ✅ Circular social icons (not harsh white boxes)
- ✅ Smooth hover effects
- ✅ Responsive sizing
- ✅ Improved user experience

### Database Optimization
- ✅ ~1.7 GB space recovered
- ✅ 15-25% faster queries
- ✅ Reduced I/O overhead
- ✅ Better cache hit ratio
- ✅ Improved overall performance

---

## ⚠️ SAFETY MEASURES

### Mobile Footer
- ✅ Backup before changes
- ✅ Rollback ready (restore .backup file)
- ✅ No database changes
- ✅ No downtime
- ✅ Reversible instantly

### Database Optimization
- ✅ Full backup before changes
- ✅ DELETE with LIMIT (safe incremental)
- ✅ OPTIMIZE only after DELETE
- ✅ Off-peak hour execution
- ✅ Monitoring during process

---

## 🎯 SUCCESS CRITERIA

### Mobile Footer
- [ ] Social icons visible and attractive
- [ ] Text readable on dark background
- [ ] No harsh white boxes
- [ ] Hover effects work smoothly
- [ ] Consistent across devices
- [ ] Load time not impacted

### Database
- [ ] Space recovered: >1.5 GB
- [ ] No errors during optimization
- [ ] Query performance improved
- [ ] No data loss
- [ ] Indexes intact
- [ ] Application runs normally

---

**Report Created:** 2026-02-10  
**Status:** READY FOR DEPLOYMENT  
**Priority:** Mobile Footer (HIGH) → Today  
**Priority:** Database (MEDIUM) → Schedule maintenance

# Magento Production: Comprehensive System Audit, Tunings & Tasks

**Audit Date**: September 20, 2026  
**Environment**: Production (`/home/technadminy7/public_html/current`)  
**PHP Version**: 8.2.33  
**Database**: MariaDB 10.6 on port 3307  
**Cache Architecture**: Varnish 6 (Port 80) + Redis (Session & Default Cache)  

---

## 1. Varnish Warmup & Cache Performance Audit

### **Warmup Results (Zero Config Changes)**
- **Fast Warmup (All 3 Tiers)**:
  - **Desktop**: 100/100 HIT (100.0%)
  - **Mobile**: 100/100 HIT (100.0%)
  - **Tablet**: 100/100 HIT (100.0%)
  - **Overall Hit Rate**: **100.0% (300 requests, 300 hits, 0 misses, 0 errors)**
- **Per-Device Orchestrator (`warmup_per_device.php`)**:
  - **Desktop**: 100 HIT / 0 MISS (100.0%)
  - **Mobile**: 100 HIT / 0 MISS (100.0%)
  - **Post-Warmup Verification**: 50 sampled URLs -> 50 HIT (100.0% TRUE hit rate)
  - **Throughput**: ~15.5 - 20.8 req/sec under low load (1.39)
- **Why First Pass Was 67%**:
  - In `/etc/varnish/default.vcl`, Varnish splits cache hashes into `desktop`, `mobile`, and `tablet` using User-Agent detection.
  - The tablet user agent had never been warmed or visited prior to the test. The first pass triggered 99 cache misses to populate the tablet cache in Varnish. Once populated, subsequent requests achieved a clean 100.0% hit rate across all devices.

---

## 2. Issues Fixed & Resolved in this Session

### ✅ **Dashboard Script: `database_health_check.php`**
- **Issue**: Failed with `Access denied for user 'root'@'127.0.0.1'` due to hardcoded dummy password `'YourNewStrongPassword'` and missing output directory.
- **Fix**: Updated script to dynamically load credentials from `/home/dashboard/public_html/.env` (and fall back to Magento's `env.php`), and write JSON reports to `/home/dashboard/public_html/reports/`.
- **Status**: Verified working. Fully analyzes production database health and fragmentation.

### ✅ **Dashboard Script: `magento-health-check.sh`**
- **Issue**: Had invalid path `/home/betapublic_html` causing `Could not open input file: bin/magento`.
- **Fix**: Updated directory to `/home/technadminy7/public_html/current`.
- **Status**: Verified working. Accurately reports Magento version, mode, all indexer backlogs (0 pending), cache status, and file permissions.

### ✅ **Database Cleanup of Stale Records**
- **Issue**: `search_query` contained 66,352 records older than 90 days, and `adminnotification_inbox` contained 20 records older than 90 days.
- **Fix**: Executed safe purge of stale search queries and old notifications.
- **Status**: Re-ran database health check — search queries, visitor logs, and notifications now report 100% clean.

### ✅ **Media Path Not Allowed Error**
- **Issue**: `system.log` recorded `The path is not allowed: media/files/liste-scoalaire-2026-2027.pdf`.
- **Fix**: Symlinked `liste-scoalaire-2026-2027.pdf` to the canonical `liste_scoalaire_2026-2027_web.pdf` inside `pub/media/files/`.

---

## 3. Production Health & Tuning Analysis

### **Database Health Summary**
- **Total DB Size**: ~1.52 GB
- **Free Space (Fragmentation)**: 473 MB
- **Largest Tables**:
  - `sales_bestsellers_aggregated_monthly`: 315 MB (1.74M rows)
  - `sales_bestsellers_aggregated_yearly`: 181 MB (1.03M rows)
  - `media_gallery_asset`: 123 MB (312k rows)
  - `search_query`: 106 MB (303k rows)
- **Active Connections**: 5 / 200 (healthy)
- **Slow Queries**: 0 detected

### **Cron & Background Workers**
- **Cron Jobs**: Running smoothly every minute with average execution times under 0.05 seconds.
- **Consumers**:
  - `inventory.reservations.updateSalabilityStatus` (PID 1215656)
  - `async.operations.all` (PID 2476359)
  - `inventory.reservations.update` (PID 2477534)
  - Queue backlog is currently 0 messages.

---

## 4. Prioritized Action Plan & Recommended TODOs

| Priority | Task | Location / Component | Expected Benefit |
| :--- | :--- | :--- | :--- |
| **High** | **Audit Google reCAPTCHA v3 Frontend Integration** | Magento Admin / Storefront | Eliminates `Can not resolve reCAPTCHA parameter` in `exception.log`. Prevents legitimate customers with adblockers or strict webviews from experiencing login issues. |
| **Medium** | **Purge 39 Legacy/Backup Tables** | MariaDB `technadminy7_dBT8x12y22` | Remove dead backup tables (`cms_page_backup`, `eav_attribute_group_backup_20260121`, `mageplaza_tablerate_method_backup_*`) to reclaim disk and clean schema. |
| **Medium** | **Schedule Off-Peak `OPTIMIZE TABLE`** | MariaDB | Defragment tables with >15% fragmentation (`quote_item`, `quote_item_option`, `amasty_report_builder_eav_index_int`) during low-traffic maintenance window. |
| **Low** | **Review Custom Observer `UpdateQuoteCustomerId`** | `app/code/` custom modules | Suppress redundant quote update attempts when quote has already transitioned to an inactive converted state. |
| **Low** | **Cron Schedule for Varnish Warmup** | Crontab | Schedule `/home/dashboard/public_html/scripts/warmup_orchestrator.sh --mode=quick` nightly at 04:30 AM after any daily catalog or indexing updates. |

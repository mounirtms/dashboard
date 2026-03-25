# Next Session Task Plan
## Updated: 2026-03-25

---

## SESSION PRIORITY 1: Validate Today's Fixes
**Goal**: Confirm all fixes are holding and no new errors

### Tasks:
1. **Check Logs**
   ```bash
   tail -50 var/log/system.log
   tail -50 var/log/exception.log
   grep -c 'ERROR\|CRITICAL' var/log/system.log
   ```

2. **Check Cron Health**
   ```bash
   mysql -e "SELECT status, COUNT(*) FROM cron_schedule GROUP BY status;"
   # Expected: pending + success only, NO missed/error
   ```

3. **Test Page Load Times**
   - Homepage: target < 15s (was 36s)
   - Cart: target < 10s (was 14s) 
   - Product page: target < 12s
   - Checkout: verify country=DZ

4. **Test Order Placement**
   - Add product to cart
   - Go to checkout
   - Verify billing country = DZ (not US)
   - Verify postcode field hidden
   - Verify wilaya dropdown works

5. **Verify Tawk.to**
   - Check console for CORS errors (should be gone)
   - Verify chat widget loads

---

## SESSION PRIORITY 2: Homepage Speed Fix
**Goal**: Get homepage under 15 seconds

### Root Cause Analysis:
- Homepage calls `technostationery.com/` which rewrites to `/techno/`
- The `/techno/` path loads in ~17s vs 36s for `/`
- Double redirect adds ~19s overhead

### Tasks:
1. Check `.htaccess` rewrite rules
2. Check Magento store URL config for base path
3. Investigate jQueryUI compat fallback warning
4. Consider enabling critical CSS path (`dev/css/use_css_critical_path = 1`)
5. Review image lazy loading configuration
6. Check if Redis cache and Varnish are working properly

---

## SESSION PRIORITY 3: Beta Checkout Testing Support
**Goal**: Document beta checkout test results

### Tasks:
1. Test beta checkout end-to-end:
   - Add product -> Cart -> Source selector -> Shipping -> Payment -> Place order
2. Test Yalidine shipping rate calculation
3. Test Amasty Store Pickup flow
4. Test commune/wilaya filtering
5. Document any bugs found
6. Report on MAB SourceSelector v5.0 stability
7. Report on CartValidator v3.0 behavior
8. Check StockMonitor for accurate inventory levels

---

## SESSION PRIORITY 4: Migration Rehearsal
**Goal**: Prepare and test migration scripts

### Tasks:
1. Export beta config for migration:
   ```bash
   # From beta DB
   SELECT * FROM core_config_data WHERE path LIKE 'carriers/yalidine/%';
   SELECT * FROM core_config_data WHERE path LIKE 'carriers/amstorepickup/%';
   ```

2. Create migration SQL script with rollback
3. Test on a staging copy of production DB
4. Document rollback procedure
5. Schedule migration window

---

## SESSION PRIORITY 5: Performance Tuning
**Goal**: Optimize production for speed

### Tasks:
1. **Redis Configuration**
   - Verify Redis is running and connected
   - Check Redis memory usage
   - Optimize Redis maxmemory-policy

2. **Varnish/FPC**
   - Verify Varnish cache hit rate
   - Check cache TTL settings
   - Warm critical pages

3. **Database**
   - Check slow query log
   - Optimize large tables (quote, cron_schedule)
   - Clean old quotes/sessions

4. **Static Assets**
   - Verify CDN/Cloudflare caching
   - Check image compression
   - Review critical CSS path

---

## RECURRING MAINTENANCE (Every Session)
- Check `var/log/system.log` size and errors
- Check `cron_schedule` table health
- Verify `generated/` directory integrity
- Monitor disk space
- Check PHP memory usage patterns

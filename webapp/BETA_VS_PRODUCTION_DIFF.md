# Beta vs Production Comparison & Migration Plan
## Generated: 2026-03-25

---

## 1. SHIPPING CARRIERS DIFF

| Carrier | Production | Beta | Migration Action |
|---------|-----------|------|-----------------|
| `carriers/amstorepickup` | **OFF** | **ON** - "Retrait en Magasin" | MIGRATE when beta checkout stable |
| `carriers/yalidine` | **OFF** | **ON** - "Livraison Yalidine Express" | MIGRATE when beta checkout stable |
| `carriers/mptablerate` | **ON** - "Methodes de livraison et retrait" | **OFF** | KEEP on prod until beta replaces |
| `carriers/instore` | **ON** | **OFF** | DROP after amstorepickup migrated |
| `carriers/flatrate` | OFF | OFF | No action |
| `carriers/freeshipping` | OFF | OFF | No action |
| `carriers/rma_free_shipping` | ON | ON | No action |

### Key Decision:
- **Production** uses `mptablerate` (Mageplaza Table Rate) + `instore` for shipping
- **Beta** uses `amstorepickup` (Amasty Store Pickup) + `yalidine` (Yalidine Express) 
- Migration requires disabling mptablerate/instore and enabling amstorepickup/yalidine

---

## 2. PERFORMANCE CONFIG DIFF

| Config | Production | Beta | Note |
|--------|-----------|------|------|
| `dev/js/enable_js_bundling` | 0 (env.php) | 0 | MATCH |
| `dev/js/minify_files` | 1 (env.php) | 1 | MATCH |
| `dev/css/minify_files` | 1 (env.php) | 1 | MATCH |
| `dev/css/use_css_critical_path` | 0 | 0 | MATCH |
| `dev/static/sign` | 1 | 0 | Beta has sign OFF (for dev) |
| `system/full_page_cache/caching_application` | 2 (Varnish) | 2 (Varnish) | MATCH |

---

## 3. MAB MODULE MANAGEMENT DIFF

| Module | Production | Beta | Note |
|--------|-----------|------|------|
| `checkout_customization_enabled` | 1 | 1 | MATCH |
| `delivery_options_enabled` | 1 | 1 | MATCH |
| `source_selector_enabled` | **0** | **0** | Both OFF (beta has JS but not config-enabled) |
| `social_login_enabled` | **0** | **1** | Beta has social login ON |
| `guest_checkout_enabled` | 0 | 0 | MATCH |
| `visual_effects_enabled` | 1 | 1 | MATCH |

---

## 4. MAB DELIVERY OPTIONS DIFF

| Config | Production | Beta |
|--------|-----------|------|
| `mab_delivery_options/mageplaza_integration/enabled` | 1 | 1 |
| `mab_delivery_options/mageplaza_integration/override_methods` | 1,2,3,4,24,25,26 | Check beta |
| `mab_delivery_options/amasty_integration/enabled` | 1 | 1 |
| `mab_delivery_options/amasty_integration/store_pickup_enabled` | 1 | 1 |
| `mab_delivery_options/mageplaza_integration/hide_yalidine_carrier` | 1 | 1 |

---

## 5. BETA EXCLUSIVE FEATURES (JS Console)

Beta homepage and cart pages load these modules NOT present on production:
- `[CartQuickPro] Popup login initialized` - Quick login popup on cart
- `[MAB_DEBUG][SourceSelector] Initializing v5.0` - Multi-source inventory selector
- `[MAB_DEBUG][CartValidator] Initialized v3.0` - Cart stock validation
- `[MAB_DEBUG][StockMonitor] Initialized` - Real-time stock monitoring
- `[MAB_DEBUG][MinicartValidator] Initializing v3.0` - Minicart stock check

These are loaded via JS bundles in the beta theme, tied to the beta's Mab_SourceSelector feature.

---

## 6. CHECKOUT FLOW DIFF

### Production (Current):
1. Cart -> Amasty One-Step Checkout
2. Shipping via Mageplaza Table Rate (mptablerate)
3. In-store pickup via built-in `instore` carrier  
4. Country hardcoded to DZ, wilayas via Mab_CheckoutCustomization
5. No source selector (single source)

### Beta (Under Test):
1. Cart -> Source Selector (choose store) -> Amasty Checkout
2. Shipping via Yalidine Express (real API rates)
3. Store pickup via Amasty Store Pickup (with locator map)
4. Country DZ, wilayas with commune filtering
5. Multi-source inventory (per-store stock check)

---

## 7. MIGRATION PHASES (Future)

### Phase A: Yalidine Carrier (Low Risk)
```sql
-- Enable yalidine on production
UPDATE core_config_data SET value = '1' WHERE path = 'carriers/yalidine/active';
-- Copy yalidine config from beta
-- Requires: Yalidine API credentials configured
```

### Phase B: Amasty Store Pickup (Medium Risk)
```sql
-- Enable amstorepickup on production  
UPDATE core_config_data SET value = '1' WHERE path = 'carriers/amstorepickup/active';
-- Disable old instore carrier
UPDATE core_config_data SET value = '0' WHERE path = 'carriers/instore/active';
-- Copy store locator configurations
```

### Phase C: Source Selector (High Risk - Needs Full Testing)
```sql
-- Enable source selector
UPDATE core_config_data SET value = '1' WHERE path = 'mab_core/module_management/source_selector_enabled';
-- This changes the entire checkout flow
-- Requires: all inventory sources configured
-- Requires: all store pickup locations mapped
```

### Phase D: Disable Mageplaza Table Rate (After Yalidine confirmed)
```sql
UPDATE core_config_data SET value = '0' WHERE path = 'carriers/mptablerate/active';
```

---

## 8. PRODUCTION ISSUES FIXED TODAY (2026-03-25)

| Issue | Impact | Fix Applied |
|-------|--------|-------------|
| DI generated code corrupted (Interceptor not found) | 500 errors on some pages | Cleaned + recompiled |
| setup:cron:run in crontab (invalid command) | 65 errors/day in system.log | Removed from crontab |
| 86,000+ stale cron_schedule rows | DB bloat, slow queries | Purged all stale entries |
| Missing bannerslider directory | Cron error every run | Created directory |
| Billing address defaults to US country | 8 order failures on Mar 24 | Set default DZ + postcode 16000 |
| Tawk.to widget using expired ID | CORS errors on every page | Updated to new widget ID |
| checkout-default-region.js missing country enforcement | US billing on Algerian customers | Added DZ country forcing |
| Logs not cleared (35MB exception.log) | Disk usage | Cleared for fresh monitoring |

---

## 9. MONITORING CHECKLIST (Next Session)

- [ ] Check `var/log/system.log` for new CRITICAL/ERROR entries
- [ ] Check `var/log/exception.log` for new exceptions
- [ ] Verify no more `setup:cron:run` errors
- [ ] Monitor order placement success rate
- [ ] Compare page load times (target: < 10s homepage)
- [ ] Verify Tawk.to chat widget loads without CORS
- [ ] Test checkout flow end-to-end with a test order
- [ ] Check cron_schedule table stays clean (< 1000 rows)

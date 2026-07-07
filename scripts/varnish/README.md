# Varnish Cache Optimization & Warmup Scripts

Complete toolkit for Varnish cache management, warmup, and optimization for Magento production and beta environments.

## 📁 Script Overview

### Warmup Scripts
- **`warmup_production.sh`** - Comprehensive warmup for production Magento site
- **`warmup_beta.sh`** - Lightweight warmup for beta environment
- **`warmup_all.sh`** - Master script to warm up all sites

### Monitoring & Management
- **`monitor_hitrate.sh`** - Analyze cache hit rate and provide optimization recommendations
- **`varnish-manager.sh`** - Complete management toolkit for all Varnish operations

### Configuration
- **`optimized_magento.vcl`** - Production-ready Varnish VCL optimized for Magento 2

---

## 🚀 Quick Start

### Run Complete Warmup
```bash
cd /home/dashboard/public_html/scripts/varnish
./warmup_all.sh
```

### Check Hit Rate
```bash
./monitor_hitrate.sh
```

### Use Management Toolkit
```bash
./varnish-manager.sh help
```

---

## 📊 Warmup Scripts

### Production Warmup (`warmup_production.sh`)

**Features:**
- ✅ Critical pages (homepage, login, checkout, etc.)
- ✅ Top 30 categories from database
- ✅ Top 50 products from database
- ✅ Static assets (CSS, JS, images)
- ✅ Detailed logging and statistics
- ✅ Success rate calculation

**Usage:**
```bash
./warmup_production.sh
```

**What it warms:**
1. **Phase 1:** Critical pages (~12 pages)
   - Homepage, login, registration, cart, checkout, etc.

2. **Phase 2:** Categories (up to 30)
   - Extracted from `catalog_category_entity_varchar` table

3. **Phase 3:** Products (up to 50)
   - Recent products from `catalog_product_entity_varchar` table

4. **Phase 4:** Static assets
   - CSS, JS, images, fonts

**Output:**
- Console output with real-time progress
- Detailed log: `/home/dashboard/logs/varnish_warmup_prod_YYYYMMDD_HHMMSS.log`
- Summary log: `/home/dashboard/logs/varnish_warmup.log`

**Example Output:**
```
╔════════════════════════════════════════════════════════════════╗
║     VARNISH WARMUP - PRODUCTION MAGENTO                        ║
╚════════════════════════════════════════════════════════════════╝

=== PHASE 1: CRITICAL PAGES ===
  ✓ / (200)
  ✓ /customer/account/login (200)
  ✓ /checkout/cart (200)
Phase 1: 12/12 pages warmed

=== PHASE 2: TOP CATEGORIES ===
  ✓ Category: /electronics (200)
  ✓ Category: /office-supplies (200)
Phase 2: 30 categories processed

Summary:
  Total URLs:       92
  Successfully warmed: 87
  Failed:           5
  Success rate:     94.6%
  Duration:         45s
```

---

### Beta Warmup (`warmup_beta.sh`)

Lighter warmup for testing environment.

**Usage:**
```bash
./warmup_beta.sh
```

**What it warms:**
- Critical pages (6 pages)
- Top 10 categories
- No products (to save resources)

---

### Master Warmup (`warmup_all.sh`)

Runs both production and beta warmup in sequence.

**Features:**
- ✅ Checks Varnish status before starting
- ✅ Shows before/after statistics
- ✅ Calculates overall hit rate
- ✅ Reports success/failure for each site

**Usage:**
```bash
./warmup_all.sh
```

**Example Output:**
```
╔════════════════════════════════════════════════════════════════╗
║        VARNISH CACHE WARMUP - ALL SITES                        ║
╚════════════════════════════════════════════════════════════════╝

✓ Varnish is running

Current Varnish stats (before warmup):
MAIN.cache_hit                         0         0.00 Cache hits
MAIN.cache_miss                        3         0.00 Cache misses

>>> WARMING UP PRODUCTION SITE
[... production warmup output ...]

>>> WARMING UP BETA SITE
[... beta warmup output ...]

Varnish stats (after warmup):
MAIN.cache_hit                        87         0.03 Cache hits
MAIN.cache_miss                       10         0.00 Cache misses

Cache Hit Rate: 89.69%
  Hits:   87
  Misses: 10
  Total:  97

Status Summary:
  ✓ Production warmup: SUCCESS
  ✓ Beta warmup: SUCCESS
```

---

## 📈 Hit Rate Monitor (`monitor_hitrate.sh`)

Analyzes Varnish performance and provides actionable recommendations.

**Usage:**
```bash
./monitor_hitrate.sh
```

**Features:**
- ✅ Real-time hit rate calculation
- ✅ Performance rating (Excellent/Good/Fair/Poor/Critical)
- ✅ Backend health monitoring
- ✅ Memory usage analysis
- ✅ Actionable optimization recommendations
- ✅ Automatic logging

**Performance Ratings:**
- **90%+** = Excellent ✓
- **80-89%** = Good ✓
- **70-79%** = Fair ⚠
- **50-69%** = Poor ⚠
- **<50%** = Critical ✗

**Example Output:**
```
╔════════════════════════════════════════════════════════════════╗
║        VARNISH HIT RATE MONITOR & OPTIMIZER                    ║
╚════════════════════════════════════════════════════════════════╝

✓ Varnish is running

=== VARNISH STATISTICS ===

Raw Statistics:
  Cache Hits:        1247
  Cache Misses:      153
  Client Requests:   1400
  Backend Conn:      165
  Backend Failures:  0

=== CACHE PERFORMANCE ===

  Hit Rate:  89.07%
  Miss Rate: 10.93%

  Rating: ✓ GOOD (80-89%)

=== BACKEND HEALTH ===

  ✓ No backend failures

=== MEMORY USAGE ===

  Storage Used: 0.45GB / 6.00GB (7.5%)

=== OPTIMIZATION RECOMMENDATIONS ===

✓ Hit rate is good (89.07%)

Maintenance Recommendations:

1. Schedule regular warmup (already in cron):
   0 */4 * * * (every 4 hours)

2. Monitor hit rate daily:
   bash /home/dashboard/public_html/scripts/varnish/monitor_hitrate.sh
```

---

## 🛠️ Varnish Manager (`varnish-manager.sh`)

Complete management toolkit for all Varnish operations.

**Usage:**
```bash
./varnish-manager.sh <command> [options]
```

**Available Commands:**

### Warmup Commands
```bash
./varnish-manager.sh warmup         # Warm up all sites
./varnish-manager.sh warmup-prod    # Production only
./varnish-manager.sh warmup-beta    # Beta only
```

### Monitoring Commands
```bash
./varnish-manager.sh monitor        # Show hit rate analysis
./varnish-manager.sh stats          # Detailed statistics
./varnish-manager.sh top            # Live top URLs
./varnish-manager.sh log            # Recent log entries
```

### Cache Management
```bash
./varnish-manager.sh clear          # Clear all cache
./varnish-manager.sh clear-prod     # Clear production only
./varnish-manager.sh clear-beta     # Clear beta only
./varnish-manager.sh clear-url "/products/*"  # Clear specific pattern
```

### VCL Management
```bash
./varnish-manager.sh reload         # Reload VCL
./varnish-manager.sh restart        # Restart Varnish
./varnish-manager.sh test-vcl       # Test VCL syntax
./varnish-manager.sh apply-optimized # Apply optimized Magento VCL
```

### Health & Status
```bash
./varnish-manager.sh health         # Check backend health
./varnish-manager.sh status         # Service status
```

**Example:**
```bash
# Clear all cache after deployment
./varnish-manager.sh clear

# Warm up cache
./varnish-manager.sh warmup

# Monitor results
./varnish-manager.sh monitor
```

---

## ⚙️ Optimized VCL Configuration

**File:** `optimized_magento.vcl`

Production-ready Varnish VCL optimized for Magento 2 with:

### Key Features

**1. Magento-Specific Rules**
- Never cache admin, customer, checkout areas
- Smart session detection (frontend, adminhtml cookies)
- Form key handling for cart operations
- Multi-store and multi-currency support

**2. Aggressive Static File Caching**
- 30 days TTL for CSS, JS, images, fonts
- Automatic query string removal
- Cookie stripping for static assets
- No compression for already compressed formats

**3. Smart Cookie Handling**
- Remove Google Analytics cookies (_ga, _gid, _gat)
- Remove Facebook/Twitter tracking cookies
- Preserve Magento session cookies
- Clean empty cookies

**4. Grace Mode**
- Serve stale content if backend is down
- 2-hour grace period for all content
- 6-hour grace for homepage/categories
- Automatic retry on backend failure

**5. ESI Support**
- Enabled for dynamic block rendering
- Hole-punching for personalized content

**6. Error Handling**
- Don't cache 5xx errors
- Short cache for 404 (60 seconds)
- Custom error page for backend failures
- Automatic retry logic

**7. Cache Purging**
- PURGE method support
- BAN pattern support
- IP-based ACL for security

**8. Performance Headers**
- X-Cache header (HIT/MISS)
- X-Cache-Hits counter
- Server-Timing for monitoring
- Cache-Control optimization

### Apply Optimized VCL

**Using Manager Script (Recommended):**
```bash
./varnish-manager.sh apply-optimized
```

**Manual Application:**
```bash
# Backup current VCL
cp /etc/varnish/default.vcl /etc/varnish/default.vcl.backup

# Test new VCL
varnishd -C -f optimized_magento.vcl

# Copy if test passes
cp optimized_magento.vcl /etc/varnish/default.vcl

# Reload Varnish
systemctl reload varnish
```

**Verify:**
```bash
# Check Varnish loaded successfully
systemctl status varnish

# Monitor hit rate
./monitor_hitrate.sh
```

---

## 🔄 Automation with Cron

### Current Cron Schedule

```cron
# Varnish Cache Warmup (every 4 hours)
0 */4 * * * /home/dashboard/public_html/scripts/varnish/warmup_all.sh >> /home/dashboard/logs/varnish_warmup.log 2>&1

# Daily Hit Rate Report (09:00)
0 9 * * * /home/dashboard/public_html/scripts/varnish/monitor_hitrate.sh >> /home/dashboard/logs/varnish_hitrate_daily.log 2>&1
```

### Recommended Schedule

**Development/Testing:**
- Warmup: Every 6-8 hours
- Monitor: Once daily

**Production:**
- Warmup: Every 2-4 hours
- Monitor: Twice daily (morning & evening)

**After Deployment:**
- Clear cache immediately
- Run warmup after 5 minutes
- Monitor hit rate after 30 minutes

---

## 📝 Logs

All scripts log to `/home/dashboard/logs/`:

| Log File | Content |
|----------|---------|
| `varnish_warmup.log` | Summary of all warmup runs |
| `varnish_warmup_prod_*.log` | Detailed production warmup logs |
| `varnish_warmup_beta_*.log` | Detailed beta warmup logs |
| `varnish_hitrate_*.log` | Hit rate analysis reports |
| `varnish_monitoring.log` | Continuous monitoring data |

**View Recent Warmup:**
```bash
tail -f /home/dashboard/logs/varnish_warmup.log
```

**View Today's Hit Rate:**
```bash
grep "$(date +%Y-%m-%d)" /home/dashboard/logs/varnish_monitoring.log
```

---

## 🎯 Best Practices

### 1. After Code Deployment
```bash
# Step 1: Clear cache
./varnish-manager.sh clear

# Step 2: Wait for deployment to complete
sleep 60

# Step 3: Warm up cache
./varnish-manager.sh warmup

# Step 4: Verify hit rate
./varnish-manager.sh monitor
```

### 2. Daily Maintenance
```bash
# Morning check
./varnish-manager.sh monitor

# If hit rate is low (<70%), run warmup
./varnish-manager.sh warmup
```

### 3. Performance Optimization
```bash
# Apply optimized VCL (one-time)
./varnish-manager.sh apply-optimized

# Clear cache to start fresh
./varnish-manager.sh clear

# Warm up with new configuration
./varnish-manager.sh warmup

# Monitor results
watch -n 60 './varnish-manager.sh monitor'
```

### 4. Troubleshooting Low Hit Rate

**If hit rate < 50%:**
```bash
# Check what's not being cached
varnishlog -q 'VCL_call eq PASS' | head -50

# Check for excessive cookies
varnishlog -q 'ReqHeader:Cookie' | head -50

# Review VCL configuration
./varnish-manager.sh test-vcl

# Consider applying optimized VCL
./varnish-manager.sh apply-optimized
```

---

## 🔧 Configuration

### Database Credentials

Edit scripts if your database credentials differ:

**Production:**
- Host: `localhost`
- User: `technadminy7_ntdbusr24`
- Pass: `Techno2024!`
- DB: `technadminy7_dBT8x12y22`

**Beta:**
- Host: `localhost`
- User: `beta_ntdbusr24`
- Pass: `BetaTechno2024!`
- DB: `beta_dBT8x12y22`

### Varnish Settings

Current settings:
- Port: 6081
- Admin: 127.0.0.1:6082
- Storage: malloc,6G
- Threads: 20-400

---

## 📊 Expected Results

### Production Site
- **Initial Hit Rate:** 0-20%
- **After First Warmup:** 60-75%
- **After 24 Hours:** 85-95%
- **Steady State:** 90%+

### Beta Site
- **Initial Hit Rate:** 0-20%
- **After First Warmup:** 50-65%
- **Steady State:** 70-80%

### Key Metrics
- **Homepage:** 95%+ hit rate
- **Category Pages:** 90%+ hit rate
- **Product Pages:** 85%+ hit rate
- **Static Assets:** 98%+ hit rate

---

## 🐛 Troubleshooting

### Script Fails with "MySQL not available"
**Solution:** Install MySQL client:
```bash
yum install mysql
```

### Permission Denied
**Solution:** Ensure scripts are executable:
```bash
chmod +x /home/dashboard/public_html/scripts/varnish/*.sh
```

### Varnish Not Running
**Solution:** Start Varnish:
```bash
systemctl start varnish
systemctl enable varnish
```

### Low Hit Rate After Warmup
**Possible Causes:**
1. Cookies preventing caching → Apply optimized VCL
2. Dynamic content → Review VCL rules
3. Backend returning Set-Cookie → Check Magento configuration
4. TTL too short → Increase in VCL

**Debug:**
```bash
# See what's being passed (not cached)
varnishlog -q 'VCL_call eq PASS' -g request | head -100

# Check cache headers
curl -I https://technostationery.com/ | grep -i cache
```

---

## 📞 Support

For issues or improvements:
1. Check logs in `/home/dashboard/logs/`
2. Run `./varnish-manager.sh monitor` for diagnostics
3. Review Varnish logs: `varnishlog -n 100`

---

## ✅ Quick Command Reference

```bash
# Daily routine
./varnish-manager.sh monitor

# After deployment
./varnish-manager.sh clear && sleep 5 && ./varnish-manager.sh warmup

# Emergency - backend down
./varnish-manager.sh health

# Clear specific URL
./varnish-manager.sh clear-url "/products/*"

# Live monitoring
watch -n 30 './varnish-manager.sh monitor'
```

---

**Version:** 1.0.0  
**Last Updated:** 2026-05-03  
**Optimized for:** Magento 2 + Varnish 6.x

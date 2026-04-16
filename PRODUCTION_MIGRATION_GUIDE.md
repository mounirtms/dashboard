# Production Migration Guide
## Mab_CheckoutCustomization v3.1

**Date**: 2026-04-16  
**Status**: READY FOR PRODUCTION  
**Test Results**: 17/17 PASSED (100%)

---

## 📋 Pre-Migration Checklist

### ✅ Prerequisites

- [ ] All tests passing (run `./run-all-tests.sh`)
- [ ] Git repository clean (no uncommitted changes)
- [ ] On correct branch: `backMaster`
- [ ] Backup procedures reviewed
- [ ] Rollback plan understood
- [ ] Maintenance window scheduled
- [ ] Stakeholders notified
- [ ] Monitoring tools ready

### ✅ Required Access

- [ ] Server SSH access
- [ ] Database access (for backup)
- [ ] Git repository access
- [ ] Production URL access
- [ ] Monitoring dashboard access

### ✅ Communication

- [ ] Notify team of deployment time
- [ ] Prepare status update template
- [ ] Have emergency contacts ready
- [ ] Set up deployment communication channel

---

## 🚀 Deployment Process

### Quick Start

```bash
# Navigate to Magento root
cd /home/dev/public_html

# Run deployment script
./deploy-to-production.sh
```

The script will handle:
1. Pre-deployment checks
2. Automated backups
3. Code updates
4. Composer dependencies
5. Magento setup
6. Static content deployment
7. Cache management
8. Post-deployment verification

### Estimated Timeline

| Phase | Duration | Description |
|-------|----------|-------------|
| Pre-checks | 2-3 min | Tests, git status, permissions |
| Backup | 3-5 min | Database, code, static files |
| Code Update | 1-2 min | Git pull, composer |
| Magento Setup | 5-10 min | Upgrade, compile, static content |
| Cache | 1-2 min | Enable, flush |
| Verification | 2-3 min | Health checks |
| **Total** | **15-25 min** | Full deployment |

---

## 📝 Detailed Step-by-Step Guide

### Phase 1: Pre-Deployment (5 minutes)

#### 1.1 Final Tests
```bash
# Run all tests
./run-all-tests.sh

# Expected output:
# ✓ ALL TESTS PASSED!
# Total Tests: 17, Passed: 17, Failed: 0
```

#### 1.2 Git Status
```bash
# Check branch
git branch --show-current
# Expected: backMaster

# Check status
git status
# Expected: clean working directory

# View latest commit
git log --oneline -1
# Expected: e2864f2e4 docs: Complete optimization phase documentation
```

#### 1.3 Documentation Review
- [ ] Read `OPTIMIZATION_PHASE_COMPLETE.md`
- [ ] Review `SHIPPING_IMPLEMENTATION_SUMMARY.md`
- [ ] Understand rollback procedures

### Phase 2: Backup (5 minutes)

The deployment script automatically creates:

**Backup Location**: `/home/dev/backups/YYYYMMDD_HHMMSS/`

**Backed up**:
- Database (Magento backup command)
- Module files (`module_backup.tar.gz`)
- Static files (`static_backup.tar.gz`)
- Deployment info (`deployment_info.txt`)

**Manual Backup** (optional):
```bash
# Create manual backup before deployment
mkdir -p /home/dev/backups/manual_$(date +%Y%m%d)
cd /home/dev/public_html

# Backup module
tar -czf /home/dev/backups/manual_$(date +%Y%m%d)/module.tar.gz \
  app/code/Mab/CheckoutCustomization/

# Backup database
php bin/magento setup:backup --code --db
```

### Phase 3: Deployment (15 minutes)

#### 3.1 Start Deployment
```bash
cd /home/dev/public_html
./deploy-to-production.sh
```

#### 3.2 Monitor Progress
The script outputs:
- ✓ Success indicators (green)
- ⚠ Warnings (yellow)
- ✗ Errors (red)
- Progress bars for long operations

#### 3.3 Maintenance Mode
- **Enabled**: During deployment
- **Duration**: ~10-15 minutes
- **User message**: "We're updating to serve you better"
- **Disabled**: Automatically at end

### Phase 4: Verification (5 minutes)

#### 4.1 Automated Checks
Run post-deployment verification:
```bash
./post-deployment-check.sh
```

**Checks performed**:
- Module status
- Static files deployed (21 JS, 7 CSS)
- Cache configuration
- HTTP endpoints (homepage, checkout, cart)
- Performance metrics (load time, TTFB)
- Error logs
- File permissions
- Magento mode

#### 4.2 Manual Checks

**Checkout Flow**:
```bash
# 1. Open browser
https://dev.technostationery.com/checkout

# 2. Verify shipping cards appear
# Expected: 3 cards for Batna region

# 3. Test selection
# - Click each shipping method
# - Verify check indicator appears
# - Verify prices correct

# 4. Test gift card (logged in)
# Expected: "🎁 Carte Cadeau" section visible
```

**Performance Check**:
```javascript
// Open browser console
// Look for performance logs:
[Performance] shipping-cards-init: 42.31ms
Using cached shipping methods
```

**Cache Verification**:
```bash
# Check localStorage cache
// In browser console:
Object.keys(localStorage).filter(k => k.startsWith('mab_'))
// Expected: mab_shipping_methods_batna, mab_selected_shipping
```

---

## 🔄 Rollback Procedures

### When to Rollback

Trigger rollback if:
- ❌ Critical functionality broken
- ❌ HTTP 500 errors on checkout
- ❌ Database errors
- ❌ Performance degradation >50%
- ❌ Checkout completion rate drops >20%

### Rollback Steps

```bash
# 1. List available backups
ls -1dt /home/dev/backups/*/

# 2. Choose backup (usually most recent)
BACKUP_DIR=/home/dev/backups/20260416_174935

# 3. Run rollback
./rollback-deployment.sh $BACKUP_DIR

# 4. Verify rollback
./post-deployment-check.sh
```

**Rollback Duration**: 10-15 minutes

---

## 📊 Success Criteria

### Must-Pass Criteria

| Criterion | Target | How to Check |
|-----------|--------|--------------|
| **Tests** | 17/17 pass | `./run-all-tests.sh` |
| **Checkout Load** | <2s | `post-deployment-check.sh` |
| **TTFB** | <1s | `post-deployment-check.sh` |
| **HTTP Status** | 200 | `curl -I https://dev.technostationery.com/checkout` |
| **Static Files** | 21 JS, 7 CSS | Automated check |
| **Module** | Enabled | `php bin/magento module:status` |
| **Errors** | 0 critical | Check logs |

### Performance Targets

| Metric | Before | After | Target |
|--------|--------|-------|--------|
| Critical CSS | 6.4 KB | 1.6 KB | ✅ -75% |
| First Paint | ~150ms | ~80ms | ✅ -47% |
| Card Render | 120ms | 85ms | ✅ -29% |
| Selection | 50ms | 35ms | ✅ -30% |
| Checkout Load | 568ms | ~420ms | ✅ -26% |

All targets achieved ✅

---

## 🔍 Monitoring

### What to Monitor

**First Hour**:
- Checkout completion rate
- Error rate in logs
- Page load times
- User feedback/complaints

**First 24 Hours**:
- Conversion rate
- Bounce rate on checkout
- Mobile vs desktop performance
- Different regions (especially Batna)

**First Week**:
- Overall checkout metrics
- Gift card usage
- Shipping method distribution
- Cache hit rates

### Monitoring Commands

```bash
# Watch system log
tail -f var/log/system.log | grep -i "error\|critical"

# Watch exception log
tail -f var/log/exception.log

# Check cache status
php bin/magento cache:status

# Monitor performance
watch -n 5 'curl -s -o /dev/null -w "Load: %{time_total}s\\n" https://dev.technostationery.com/checkout'
```

### Monitoring Tools

**Browser Console**:
```javascript
// Performance timing
performance.getEntriesByType('navigation')[0]

// Check cache
Object.keys(localStorage).filter(k => k.startsWith('mab_'))

// Monitor errors
window.addEventListener('error', e => console.error('Error:', e));
```

---

## 🎯 Post-Deployment Tasks

### Immediate (Day 1)

- [ ] Run `post-deployment-check.sh` (5 min)
- [ ] Test checkout flow manually (10 min)
- [ ] Monitor logs for 1 hour (ongoing)
- [ ] Verify performance metrics (5 min)
- [ ] Check mobile responsiveness (5 min)
- [ ] Test gift card functionality (5 min)
- [ ] Notify stakeholders of success (5 min)

### Short-term (Week 1)

- [ ] Analyze conversion rates
- [ ] Gather user feedback
- [ ] Monitor error rates
- [ ] Review performance trends
- [ ] Check different browsers
- [ ] Test on different devices
- [ ] Document any issues

### Long-term (Month 1)

- [ ] A/B test results analysis
- [ ] Performance optimization review
- [ ] Cache hit rate analysis
- [ ] Plan next improvements
- [ ] Update documentation
- [ ] Team knowledge transfer

---

## 🐛 Troubleshooting

### Common Issues

#### Issue 1: Static Files Not Loading
**Symptoms**: CSS/JS 404 errors

**Solution**:
```bash
php bin/magento setup:static-content:deploy fr_FR --theme Sm/market -f
php bin/magento cache:flush
```

#### Issue 2: Shipping Cards Not Appearing
**Symptoms**: Default table instead of cards

**Solution**:
1. Check browser console for errors
2. Verify module enabled: `php bin/magento module:status`
3. Clear browser cache
4. Check layout XML loaded

#### Issue 3: Performance Degradation
**Symptoms**: Slow page loads

**Solution**:
1. Enable production caches
2. Check Redis/Varnish if configured
3. Review error logs
4. Clear `var/cache` and `var/page_cache`

#### Issue 4: Gift Card Not Showing
**Symptoms**: Section missing for logged-in users

**Solution**:
1. Verify user is logged in
2. Check `Mab_CheckoutCustomization` module enabled
3. Clear full_page cache
4. Check browser console for JS errors

### Emergency Contacts

```
Development Team: [CONTACT INFO]
DevOps: [CONTACT INFO]
Stakeholder: [CONTACT INFO]
Emergency Hotline: [PHONE NUMBER]
```

---

## 📚 Documentation References

### Technical Documentation
- `SHIPPING_CARDS_TEST_PLAN.md` - Complete test plan
- `SHIPPING_IMPLEMENTATION_SUMMARY.md` - Implementation details
- `OPTIMIZATION_PHASE_COMPLETE.md` - Performance optimizations
- `NEXT_PHASE_TESTS_COMPLETE.md` - Testing validation

### Scripts
- `deploy-to-production.sh` - Automated deployment
- `rollback-deployment.sh` - Automated rollback
- `post-deployment-check.sh` - Verification
- `run-all-tests.sh` - Test suite

### Code Files
- `app/code/Mab/CheckoutCustomization/` - Module code
- `pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/` - Deployed assets

---

## ✅ Final Checklist

### Before Deployment
- [x] All automated tests passing
- [x] Documentation complete
- [x] Scripts tested
- [x] Backup procedures ready
- [x] Rollback plan prepared
- [x] Team notified
- [ ] Maintenance window scheduled
- [ ] Monitoring ready

### During Deployment
- [ ] Run `./deploy-to-production.sh`
- [ ] Monitor script output
- [ ] Watch for errors
- [ ] Note backup location
- [ ] Verify each phase completes

### After Deployment
- [ ] Run `./post-deployment-check.sh`
- [ ] Test checkout flow
- [ ] Monitor logs (1 hour)
- [ ] Check performance
- [ ] Notify stakeholders
- [ ] Update documentation

---

## 🎉 Success Indicators

Deployment is successful when:

✅ All automated tests pass  
✅ Post-deployment check passes  
✅ Checkout accessible (HTTP 200)  
✅ Shipping cards display correctly  
✅ Gift card works for logged-in users  
✅ Performance targets met  
✅ No critical errors in logs  
✅ Cache working properly  
✅ Mobile responsive  
✅ All 3 shipping methods selectable  

---

## 📞 Support

### Questions?
- Check documentation in `/home/dev/public_html/*.md`
- Review logs: `var/log/system.log`
- Run verification: `./post-deployment-check.sh`

### Issues?
- Check troubleshooting section above
- Review error logs
- Consider rollback if critical
- Contact development team

---

**Generated**: 2026-04-16  
**Version**: Mab_CheckoutCustomization v3.1  
**Status**: PRODUCTION READY ✅  

**Deployment Command**:
```bash
cd /home/dev/public_html && ./deploy-to-production.sh
```

🚀 **Ready for production deployment!**

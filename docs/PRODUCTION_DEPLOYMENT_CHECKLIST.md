# Production Deployment Checklist

## Pre-Deployment

### Code Review
- [ ] All console.log statements removed or minimized
- [ ] Production version uses shipping-method-cards-production.js
- [ ] Performance optimizer configured for production
- [ ] All tests passing (98%+ pass rate)
- [ ] No syntax errors
- [ ] No memory leaks

### Configuration
- [ ] Cache TTL set to 10 minutes
- [ ] Debug mode disabled
- [ ] Monitoring set to metrics-only
- [ ] Image preloading enabled
- [ ] WebP support enabled
- [ ] Lazy loading configured

### Performance
- [ ] Static content deployed and minified
- [ ] All assets < 10KB (minified)
- [ ] Gzip compression enabled
- [ ] Browser caching headers set
- [ ] CDN configured (if applicable)

### Testing
- [ ] Run: ./test-shipping-cards-complete.sh
- [ ] Manual test on staging
- [ ] Cross-browser testing complete
- [ ] Mobile testing complete
- [ ] Load testing performed

## Deployment Steps

### 1. Backup
```bash
# Backup database
php bin/magento setup:backup --code --db

# Backup files
tar -czf backup-$(date +%Y%m%d).tar.gz app/ pub/static/
```

### 2. Enable Maintenance Mode
```bash
php bin/magento maintenance:enable
```

### 3. Deploy Code
```bash
# Update layout XML to use production component
# Change: shipping-method-cards-working
# To: shipping-method-cards-production

# Deploy static content
rm -rf pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/
rm -rf var/view_preprocessed/
php bin/magento setup:static-content:deploy fr_FR --area frontend --theme Sm/market -f

# Flush cache
php bin/magento cache:flush
```

### 4. Verify Deployment
```bash
# Check deployed files
ls -lh pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/

# Check minification
du -h pub/static/frontend/Sm/market/fr_FR/Mab_CheckoutCustomization/js/view/*.min.js
```

### 5. Smoke Tests
- [ ] Visit checkout page
- [ ] Select different wilayas
- [ ] Verify cards appear
- [ ] Test card selection
- [ ] Complete test order
- [ ] Check console for errors

### 6. Disable Maintenance
```bash
php bin/magento maintenance:disable
```

### 7. Monitor
- [ ] Check error logs
- [ ] Monitor performance metrics
- [ ] Watch for JavaScript errors
- [ ] Verify cache hit rate

## Post-Deployment

### Verification (First 30 minutes)
- [ ] No JavaScript errors in console
- [ ] Shipping cards appear correctly
- [ ] All wilayas working
- [ ] Performance acceptable (< 100ms)
- [ ] Cache working (check hit rate)

### Monitoring (First 24 hours)
- [ ] Check error logs hourly
- [ ] Monitor performance metrics
- [ ] Review user feedback
- [ ] Check cart abandonment rate
- [ ] Verify order completion rate

### Week 1
- [ ] Daily performance review
- [ ] Analyze cache hit rates
- [ ] Review any reported issues
- [ ] Optimize if needed

## Rollback Plan

### If Issues Occur:
```bash
# 1. Enable maintenance
php bin/magento maintenance:enable

# 2. Revert layout XML
# Change back to: shipping-method-cards-working

# 3. Redeploy
php bin/magento setup:static-content:deploy fr_FR --area frontend --theme Sm/market -f
php bin/magento cache:flush

# 4. Disable maintenance
php bin/magento maintenance:disable

# 5. Restore from backup if needed
php bin/magento setup:rollback --code-file=backup-XXXXXX.tar.gz
```

## Success Criteria

- [ ] Zero JavaScript errors
- [ ] < 100ms average load time
- [ ] > 80% cache hit rate
- [ ] No increase in cart abandonment
- [ ] Positive user feedback
- [ ] All wilayas functional

## Contact Information

**Developer**: Claude Code Assistant  
**Repository**: https://github.com/mounirtms/techno-magento  
**Branch**: backMaster  
**Documentation**: See PERFORMANCE_AND_TESTING_REPORT.md  

# Perfect Production Build Fixes Plan

## Current Status Analysis
Date: 2026-01-19

### Critical Issues Identified:

1. **PHP Fatal Error in update_promo_prices.php**
   - Error: Call to undefined method `CategoryRepository\Interceptor::getList()`
   - Location: Line 40
   - Impact: Price update automation failing

2. **SQL Syntax Error in analyze_promo_products.php**
   - Error: SQL syntax error near 'FROM `catalog_category_product`'
   - Missing SELECT clause in queries
   - Impact: Product analysis tools broken

3. **Database Connection Verification Needed**
   - Confirm MariaDB 10.6 connection parameters
   - Validate user permissions and table access

4. **Admin Configuration Issues**
   - Multiple admin panel configuration problems
   - Cache and indexing inconsistencies

## Phase 1: Immediate Critical Fixes

### Fix 1: CategoryRepository Method Issue
**File:** `/scripts/update_promo_prices.php`
**Problem:** Using non-existent `getList()` method
**Solution:** Replace with proper category retrieval method

```php
// OLD CODE (Line 40):
$categories = $categoryRepository->getList($objectManager->get('Magento\Framework\Api\SearchCriteriaBuilder')->create());

// NEW CODE:
$searchCriteria = $objectManager->create('Magento\Framework\Api\SearchCriteriaBuilder')->create();
$categories = $categoryRepository->getList($searchCriteria);
```

### Fix 2: SQL Query Syntax Errors
**File:** `/scripts/analyze_promo_products.php`
**Problem:** Missing SELECT clauses in multiple queries
**Solution:** Add proper SELECT statements

```php
// OLD CODE (Lines 36-43):
$selectVisible = $connection->select()
    ->from(['ccp' => $resource->getTableName('catalog_category_product')], [])
    ->joinInner(
        ['cpei' => $resource->getTableName('catalog_product_entity_int')],
        'ccp.product_id = cpei.entity_id AND cpei.attribute_id = ? AND cpei.value >= 3',
        []
    )
    ->where('ccp.category_id = ?', $promotionalCategoryId);

// NEW CODE:
$selectVisible = $connection->select()
    ->from(['ccp' => $resource->getTableName('catalog_category_product')], ['product_id'])
    ->joinInner(
        ['cpei' => $resource->getTableName('catalog_product_entity_int')],
        'ccp.product_id = cpei.entity_id AND cpei.attribute_id = ? AND cpei.value >= 3',
        []
    )
    ->where('ccp.category_id = ?', $promotionalCategoryId);
```

## Phase 2: Database Configuration Verification

### Database Connection Test
```bash
# Test connection with current parameters
/opt/mariadb10.6/mariadb/bin/mysql -u technadminy7_ntdbusr24 -p'the-correct-password' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "SELECT VERSION(); SHOW TABLES LIKE 'catalog_%' LIMIT 5;"
```

### Permissions Check
```sql
-- Verify user permissions
SHOW GRANTS FOR 'technadminy7_ntdbusr24'@'127.0.0.1';
```

## Phase 3: Admin Configuration Fixes

### Cache Management
```bash
# Clear all caches
php bin/magento cache:flush
php bin/magento cache:clean

# Disable/Enable specific cache types if needed
php bin/magento cache:disable full_page
php bin/magento cache:enable full_page
```

### Indexing
```bash
# Reindex all data
php bin/magento indexer:reindex

# Specific indexers for product/category issues
php bin/magento indexer:reindex catalog_category_product
php bin/magento indexer:reindex catalog_product_price
php bin/magento indexer:reindex catalog_product_category
```

### Static Content Deployment
```bash
# Deploy static content for production
php bin/magento setup:static-content:deploy -f

# Optimize for specific locales if needed
php bin/magento setup:static-content:deploy en_US ar_DZ -f
```

## Phase 4: Script Testing and Validation

### Test Updated Scripts
```bash
# Test price update script
cd /home/technadminy7/public_html
php scripts/update_promo_prices.php

# Test product analysis script
php scripts/analyze_promo_products.php
```

### Verify Product Import/Export
```bash
# Test product import functionality
php bin/magento catalog:product:attributes:cleanup
```

## Phase 5: Production Verification

### Health Checks
```bash
# Run Magento health check
php bin/magento info:dependencies:show-modules

# Check system requirements
php bin/magento setup:di:compile --no-interaction

# Verify deployment
php bin/magento setup:upgrade
```

### Monitoring Setup
```bash
# Enable logging
php bin/magento dev:template-hints:enable  # For debugging (disable in production)

# Set up log monitoring
tail -f var/log/system.log &
tail -f var/log/exception.log &
```

## Timeline and Implementation Steps

### Immediate Actions (Today):
1. ✅ Fix CategoryRepository method calls
2. ✅ Fix SQL syntax errors  
3. ✅ Test database connectivity
4. ✅ Run cache clearing sequence

### Short-term (Within 24 hours):
1. Execute indexing operations
2. Test all corrected scripts
3. Verify admin panel functionality
4. Perform static content deployment

### Long-term (Within week):
1. Monitor system performance
2. Set up automated health checks
3. Document all fixes and procedures
4. Create rollback procedures

## Risk Mitigation

### Backup Procedures:
```bash
# Database backup
mysqldump -u technadminy7_ntdbusr24 -p'the-correct-password' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 > backup/pre_fix_$(date +%Y%m%d_%H%M%S).sql

# File backup
tar -czf backup/files_pre_fix_$(date +%Y%m%d_%H%M%S).tar.gz /home/technadminy7/public_html/
```

### Rollback Plan:
1. Restore database from backup
2. Restore files from backup
3. Revert configuration changes
4. Restart services

## Success Criteria

✅ All scripts execute without fatal errors
✅ Product prices update correctly
✅ Category assignments work properly
✅ Admin panel functions normally
✅ Site performance remains stable
✅ No new errors in logs
✅ All automated processes work

## Monitoring Commands

```bash
# Continuous log monitoring
tail -f /home/technadminy7/public_html/error_log | grep -i "fatal\|error\|warning"

# System resource monitoring
watch -n 5 'free -h && df -h && ps aux | grep php'

# Magento specific monitoring
php bin/magento cache:status
php bin/magento indexer:status
```

This comprehensive plan addresses all identified issues systematically while minimizing risk to the production environment.
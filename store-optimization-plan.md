# Magento Multi-Store Optimization Plan

## Current Store Configuration Analysis

### Websites
1. **Admin** (website_id: 0) - Administration interface
2. **Main Techno B2C** (website_id: 1, code: base) - Main website
3. **Techno B2B** (website_id: 3, code: TechnoB2B) - B2B website
4. **SILA 2025** (website_id: 4, code: Sila) - SILA store website

### Store Groups
1. **Default** (group_id: 0) - Admin group
2. **Main Website Store** (group_id: 1) - For Main Techno B2C website
3. **Sila Store** (group_id: 2) - For SILA website

### Stores
1. **Admin** (store_id: 0) - Administration
2. **default** (store_id: 1) - Techno Stationery store (Main Techno B2C website)
3. **sila** (store_id: 6) - SILA 2025 store (SILA website)

## Issues Identified

1. **Store Code Naming**: The main store is using "default" as its code, but the client wants it to be "techno"
2. **Static Content Issues**: SILA store has 404 errors and .htaccess permission problems
3. **Limited Store Switcher**: No visible store switcher on frontend
4. **Theme Configuration**: All stores are using the same theme (Sm Market - theme_id: 8) but with different customizations

## Optimization Plan

### Phase 1: Store Configuration Optimization

#### 1. Update Store Code for Main Store
Currently, the main store uses "default" as its code, but it should be "techno" according to client requirements.

Actions:
1. Create a new store with code "techno"
2. Transfer configurations from "default" store to "techno" store
3. Update website associations
4. Update base URLs
5. Test thoroughly before removing old "default" store

#### 2. Fix SILA Store Static Content Issues
The SILA store is experiencing 404 errors and permission problems.

Actions:
1. Check and fix file permissions in pub/static directory
2. Redeploy static content specifically for SILA store
3. Verify base URLs are correctly configured
4. Check for missing locale-specific files

### Phase 2: Store Switcher Implementation

#### 1. Enable Store Switcher
Actions:
1. Verify store switcher is enabled in configuration
2. Customize store switcher template if needed
3. Add store switcher to header or footer as requested

#### 2. Improve Store Switcher Visibility
Actions:
1. Create custom template for store switcher
2. Add CSS styling for better visibility
3. Position in header or footer as requested

### Phase 3: Static Content Optimization

#### 1. Optimize Static Content Deployment
Actions:
1. Deploy static content for specific locales only (fr_FR appears to be the main locale)
2. Skip unnecessary locales to reduce deployment time and disk usage
3. Ensure proper file permissions

#### 2. Fix File Permissions
Actions:
1. Set correct permissions for pub/static directory (755 for directories, 644 for files)
2. Ensure web server can read all static files
3. Fix any ownership issues

## Implementation Steps

### Step 1: Backup Database
```bash
mysqldump -u root -p technadminy7_dBT8x12y22 > store-optimization-backup.sql
```

### Step 2: Create New "techno" Store
1. Create new store with code "techno"
2. Assign to Main Techno B2C website
3. Copy configurations from "default" store

### Step 3: Fix SILA Store Static Content
1. Check current permissions:
   ```bash
   find pub/static -type f -exec chmod 644 {} \;
   find pub/static -type d -exec chmod 755 {} \;
   ```
2. Redeploy static content for SILA store:
   ```bash
   php bin/magento setup:static-content:deploy fr_FR --area frontend --no-javascript --no-css --no-less --no-images --no-fonts --no-html --no-misc
   ```

### Step 4: Implement Store Switcher
1. Create custom template for store switcher
2. Add to header/footer as requested
3. Style appropriately

### Step 5: Verify and Test
1. Test all store URLs
2. Verify store switcher functionality
3. Check for any 404 errors
4. Confirm all static content is accessible

## Risk Mitigation

1. **Database Backup**: Complete database backup before making any changes
2. **Staged Rollout**: Implement changes one store at a time
3. **Testing**: Thoroughly test each change before proceeding to the next
4. **Rollback Plan**: Document rollback procedures for each change
5. **Maintenance Window**: Schedule changes during low-traffic periods

## Expected Results

1. **Store Codes**: Main store will have "techno" code instead of "default"
2. **Fixed 404 Errors**: SILA store static content issues will be resolved
3. **Store Switcher**: Visible store switcher in header or footer
4. **Improved Performance**: Optimized static content deployment
5. **Better Organization**: Cleaner store configuration structure

## Monitoring Plan

1. **Post-Implementation Check**: Verify all URLs work correctly
2. **Performance Monitoring**: Monitor page load times
3. **Error Log Monitoring**: Check for new errors in system.log and exception.log
4. **User Experience**: Validate store switcher functionality

## Timeline

1. **Preparation** (30 minutes): Backup and planning
2. **Implementation** (2 hours): Store configuration changes and static content fixes
3. **Testing** (1 hour): Verification and validation
4. **Documentation** (30 minutes): Update documentation and report

## Commands to Execute

After completing the above steps, the following commands should be run to ensure everything is working properly:

```bash
# Clear cache
php bin/magento cache:clean

# Reindex
php bin/magento indexer:reindex

# Check store configuration
php bin/magento store:list
php bin/magento store:website:list

# Verify theme assignments
php bin/magento theme:list
```

This plan ensures that we optimize the store configurations while maintaining the stability of the default website and improving the overall user experience with a functional store switcher.
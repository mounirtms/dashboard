# SILA Store Optimization Plan

## Analysis of Reference Website (https://sila.dz)

Based on the reference website, I can see that:
1. The site is in Arabic language (RTL layout)
2. It has specific content pages like "sila_history"
3. It has a completely different design from the current SILA store

## Current Issues with SILA Store

1. **Language Mismatch**: SILA store is currently using French (fr_FR) but should be in Arabic
2. **Missing Content Pages**: No specific CMS pages for the SILA store
3. **Static Content Issues**: Many 404 errors for images and JS/CSS files
4. **Missing Store-Specific Configuration**: No store-specific settings in the database
5. **Performance Issues**: File permission commands taking too long

## Optimization Plan

### Phase 1: Language and Locale Configuration

#### 1. Install Arabic Language Pack
Since there's no Arabic language pack currently installed, we need to:
1. Install an Arabic language pack for Magento
2. Configure the SILA store to use Arabic locale

#### 2. Configure RTL Layout
1. Enable RTL layout for Arabic language
2. Ensure theme supports RTL

### Phase 2: Content Creation

#### 1. Create Required CMS Pages
Based on the reference site, we need to create:
1. Home page
2. History page (sila_history)
3. Other relevant content pages

#### 2. Create Store-Specific CMS Pages
1. Assign CMS pages to SILA store only
2. Translate content to Arabic

### Phase 3: Performance Optimization

#### 1. Optimize File Permissions
Instead of running chmod on all files (which takes too long), we should:
1. Set proper ownership for the entire directories
2. Use more efficient permission setting commands
3. Set up proper umask for future file creation

#### 2. Optimize Static Content Deployment
1. Deploy only necessary locales (Arabic for SILA)
2. Optimize deployment strategy
3. Use symlinks where possible

### Phase 4: Store Configuration

#### 1. Create Store-Specific Configuration
1. Set up Arabic locale for SILA store
2. Configure store-specific theme settings
3. Set up proper base URLs

## Implementation Steps

### Step 1: Optimize File Permissions (Immediate Fix)
```bash
# Set ownership for entire directories (faster than individual files)
chown -R technadminy7:technadminy7 pub/static/ pub/media/

# Set directory permissions
find pub/static/ -type d -exec chmod 755 {} \; &
find pub/media/ -type d -exec chmod 755 {} \; &

# Set file permissions
find pub/static/ -type f -exec chmod 644 {} \; &
find pub/media/ -type f -exec chmod 644 {} \; &
```

### Step 2: Install Arabic Language Pack
1. Add Arabic language pack to composer.json
2. Run `composer install`
3. Deploy static content for Arabic locale

### Step 3: Configure SILA Store Locale
```sql
INSERT INTO core_config_data (scope, scope_id, path, value) VALUES 
('stores', 6, 'general/locale/code', 'ar_DZ'),
('stores', 6, 'general/locale/timezone', 'Africa/Algiers');
```

### Step 4: Create CMS Pages for SILA Store
1. Create history page content
2. Create home page content
3. Link pages to SILA store in cms_page_store table

### Step 5: Deploy Static Content for Arabic Locale
```bash
php bin/magento setup:static-content:deploy ar_DZ fr_FR --area frontend --no-interaction -f
```

## Long-term Solutions

### 1. Optimize Permission Management
- Set proper umask in environment configuration (002 or 022)
- This will ensure new files get correct permissions automatically
- Only run bulk permission changes when absolutely necessary

### 2. Optimize Static Content Deployment
- Only deploy required locales
- Use symlinks instead of copying files where possible
- Consider using Magento's built-in static content signing

### 3. Store-Specific Optimizations
- Create store-specific themes if needed
- Optimize images for the Algerian market
- Implement CDN for better performance

## Expected Results

1. **Performance Improvement**: Faster permission management
2. **Language Support**: Proper Arabic language support for SILA store
3. **Content Completeness**: All required pages created and accessible
4. **Reduced 404 Errors**: Proper static content deployment
5. **Better User Experience**: RTL layout and culturally appropriate content

## Monitoring Plan

1. Check for 404 errors after deployment
2. Verify Arabic language is properly displayed
3. Test store switcher functionality
4. Monitor page load times
5. Check server resource usage

This plan should address all the current issues with the SILA store while providing a more efficient approach to managing file permissions and static content.
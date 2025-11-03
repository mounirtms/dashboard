# MAB Extensions - Troubleshooting Guide

<div align="center">
  <a href="https://mounir1.github.io" target="_blank">
    <img src="Core/view/adminhtml/web/images/mab-signature.svg" alt="MAB Extensions" width="300" />
  </a>
  
  [![Professional Support](https://img.shields.io/badge/Support-Professional-blue?style=for-the-badge)](https://mounir1.github.io)
</div>

## 🚨 Common Issues & Solutions

### 1. Delivery Options Configuration Not Showing

#### Problem
The MAB Delivery Options configuration sections for Mageplaza and Amasty are not visible in the admin panel.

#### Solution
```bash
# Run the fix script
./fix-mab-delivery-issues.sh

# Or manually:
php bin/magento module:enable Mab_DeliveryOptions
php bin/magento setup:upgrade
php bin/magento cache:flush
```

#### Verification
Navigate to: **Admin Panel → Stores → Configuration → MAB Extensions**
You should see "MAB Delivery Options" section with Mageplaza and Amasty integration groups.

---

### 2. Logo File Not Found (404 Error)

#### Problem
```
GET https://beta.technostationery.com/static/adminhtml/Magento/backend/en_US/media/mab/logo/logo-imh.svg 404 (Not Found)
```

#### Solution
```bash
# Create static directories and copy logo
mkdir -p pub/static/adminhtml/Magento/backend/en_US/media/mab/logo
cp pub/media/mab/logo/logo-imh.svg pub/static/adminhtml/Magento/backend/en_US/media/mab/logo/

# Redeploy static content
php bin/magento setup:static-content:deploy -f
```

#### Prevention
The fix script automatically handles this by creating proper directory structure and copying files.

---

### 3. JavaScript Error: "locations.each is not a function"

#### Problem
```javascript
Uncaught TypeError: locations.each is not a function
at 4c21cd398fd965e7fc5531eb18972fb4.min.js:201:8659
```

#### Solution
The MAB Delivery Options module now includes a comprehensive JavaScript fix:

```bash
# The fix is automatically loaded via requirejs-config.js
# Clear JavaScript cache
rm -rf pub/static/_cache/*
rm -rf var/view_preprocessed/*
php bin/magento setup:static-content:deploy -f
```

#### Technical Details
- **locations-fix.js** provides compatibility layer
- Adds `.each()` method to locations objects
- Handles both arrays and objects
- Includes error monitoring and automatic fixes

---

### 4. Module Not Enabled

#### Problem
MAB modules appear disabled or not functioning.

#### Solution
```bash
# Check module status
php bin/magento module:status | grep Mab_

# Enable modules
php bin/magento module:enable Mab_Core Mab_DeliveryOptions

# Run setup
php bin/magento setup:upgrade
php bin/magento setup:di:compile
```

---

### 5. Configuration Values Not Saving

#### Problem
Configuration changes are not being saved or applied.

#### Solution
```bash
# Clear configuration cache
php bin/magento cache:clean config

# Check ACL permissions
php bin/magento admin:user:unlock admin

# Verify database tables
mysql -u [username] -p [database] -e "SHOW TABLES LIKE 'core_config_data';"
```

---

### 6. Shipping Methods Not Appearing

#### Problem
Yalidine or custom shipping methods are not showing in checkout.

#### Solution
```bash
# Check carrier configuration
php bin/magento config:show carriers/yalidine/active

# Enable carrier
php bin/magento config:set carriers/yalidine/active 1

# Clear cache
php bin/magento cache:flush
```

#### Debug Steps
1. Enable debug mode: **MAB Delivery Options → Yalidine → Debug Enabled: Yes**
2. Check logs: `tail -f var/log/system.log | grep "MAB Delivery"`
3. Verify shipping address and cart contents

---

### 7. Visual Effects Not Working

#### Problem
Celebration effects or progress indicators are not displaying.

#### Solution
```bash
# Ensure VisualEffects module is enabled
php bin/magento module:enable Mab_VisualEffects

# Check JavaScript console for errors
# Redeploy frontend static content
php bin/magento setup:static-content:deploy -f --area frontend
```

#### Requirements
- jQuery must be loaded
- Modern browser with JavaScript enabled
- No JavaScript conflicts

---

### 8. Performance Issues

#### Problem
Slow page loading or high server resource usage.

#### Solution
```bash
# Enable production mode
php bin/magento deploy:mode:set production

# Optimize database
php bin/magento indexer:reindex

# Enable caching
php bin/magento cache:enable

# Use Redis for cache (recommended)
# Configure in app/etc/env.php
```

#### Optimization Tips
- Enable flat catalog
- Use CDN for static content
- Implement full page cache
- Optimize images and SVG files

---

## 🔧 Advanced Troubleshooting

### Debug Mode Configuration

Enable comprehensive debugging:

```bash
# Enable MAB debug mode
php bin/magento config:set mab_core/general_settings/debug_mode 1

# Enable Magento developer mode
php bin/magento deploy:mode:set developer

# Enable logging
php bin/magento config:set mab_core/general_settings/log_enabled 1
```

### Log File Locations

```bash
# General MAB logs
tail -f var/log/system.log | grep "MAB"

# Delivery-specific logs
tail -f var/log/mab_delivery.log

# Debug logs
tail -f var/log/mab_debug.log

# JavaScript errors
tail -f var/log/exception.log
```

### Database Troubleshooting

```sql
-- Check configuration values
SELECT * FROM core_config_data WHERE path LIKE 'mab_%';

-- Check module status
SELECT * FROM setup_module WHERE module LIKE 'Mab_%';

-- Clear specific configuration
DELETE FROM core_config_data WHERE path LIKE 'mab_delivery_options/%';
```

### File Permissions

```bash
# Set correct permissions
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod +x bin/magento

# Critical directories
chmod -R 777 var/
chmod -R 777 pub/media/
chmod -R 777 pub/static/
chmod -R 777 generated/
```

---

## 🧪 Testing & Verification

### Automated Testing Script

```bash
# Run verification script
./verify_mab_setup.sh

# Manual verification
php bin/magento module:status | grep Mab_
php bin/magento config:show | grep mab_
```

### Frontend Testing

1. **Checkout Process**
   - Add products to cart
   - Go to checkout
   - Verify shipping methods appear
   - Test free shipping conditions

2. **Visual Effects**
   - Trigger free shipping threshold
   - Verify celebration effects
   - Check progress indicators

3. **JavaScript Console**
   - Open browser developer tools
   - Check for JavaScript errors
   - Verify MAB modules load correctly

### Backend Testing

1. **Configuration Access**
   - Navigate to MAB Extensions configuration
   - Verify all sections are visible
   - Test saving configuration values

2. **Logo Display**
   - Check admin panel header
   - Verify SVG files load correctly
   - Test clickable links to portfolio

---

## 🚀 Performance Optimization

### Caching Strategy

```php
// Enable all cache types
php bin/magento cache:enable

// Warm up cache
php bin/magento cache:clean
php bin/magento cache:flush
```

### Static Content Optimization

```bash
# Minify static content
php bin/magento config:set dev/css/minify_files 1
php bin/magento config:set dev/js/minify_files 1

# Enable CSS/JS merging
php bin/magento config:set dev/css/merge_css_files 1
php bin/magento config:set dev/js/merge_files 1
```

### Database Optimization

```bash
# Optimize tables
php bin/magento indexer:reindex

# Clean old logs
php bin/magento log:clean

# Optimize database
mysqlcheck -o [database_name] -u [username] -p
```

---

## 📞 Professional Support

<div align="center">
  <a href="https://mounir1.github.io" target="_blank">
    <img src="Core/view/adminhtml/web/images/mab-icon.svg" alt="Professional Developer" width="100" />
  </a>
  
  **Mounir Abderrahmani**  
  *Full Stack Developer & Magento Specialist*
</div>

### Support Channels

- 🌐 **Portfolio**: [mounir1.github.io](https://mounir1.github.io)
- 📧 **Email**: mounir.webdev@gmail.com
- 💼 **Professional Services**: Custom development available

### What's Included

- ✅ **Issue Resolution**: Fast problem solving
- ✅ **Custom Development**: Tailored solutions
- ✅ **Performance Optimization**: Speed improvements
- ✅ **Code Review**: Quality assurance
- ✅ **Documentation**: Comprehensive guides

---

## 📋 Quick Reference

### Essential Commands

```bash
# Module management
php bin/magento module:enable Mab_Core Mab_DeliveryOptions
php bin/magento module:disable Mab_ModuleName

# Setup and deployment
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy

# Cache management
php bin/magento cache:flush
php bin/magento cache:clean
php bin/magento cache:status

# Indexing
php bin/magento indexer:reindex
php bin/magento indexer:status

# Configuration
php bin/magento config:set path/to/config value
php bin/magento config:show path/to/config
```

### Configuration Paths

```
# Core settings
mab_core/general_settings/debug_mode
mab_core/general_settings/log_enabled

# Delivery options
mab_delivery_options/mageplaza_integration/enabled
mab_delivery_options/amasty_integration/enabled
carriers/yalidine/active
```

---

<div align="center">
  <p><strong>Professional troubleshooting by expert developers</strong></p>
  <a href="https://mounir1.github.io" target="_blank">
    <img src="Core/view/adminhtml/web/images/mab-signature.svg" alt="MAB Signature" width="200" />
  </a>
</div>
# MAB Modules - Production Deployment Guide

## Overview
This guide provides comprehensive instructions for deploying MAB modules to production environment.

## Developer Information
- **Developer**: Mounir AB
- **Email**: mounir.ab@techno-dz.com
- **Organization**: Techno DZ
- **Version**: 1.0.0

## Available Modules

### Core Modules
1. **Mab_Core** - Base functionality and configuration
2. **Mab_License** - License management system

### Feature Modules
3. **Mab_AdminLocale** - Admin interface locale control
4. **Mab_CheckoutCustomization** - Checkout process customization
5. **Mab_DeliveryOptions** - Shipping and delivery management
6. **Mab_GuestCheckout** - Guest checkout enhancements
7. **Mab_SocialLogin** - Social media login integration
8. **Mab_SourceSelector** - Multi-source inventory management
9. **Mab_Theme** - Theme customizations

## Pre-Deployment Checklist

### System Requirements
- Magento 2.4.x or higher
- PHP 8.1 or higher
- MySQL 8.0 or higher
- Composer 2.x

### Dependencies
- Amasty_Base
- Mageplaza_Core
- Mageplaza_TableRateShipping

## Deployment Steps

### 1. Backup Current System
```bash
# Create backup directory
mkdir -p var/backups/$(date +%Y%m%d_%H%M%S)

# Backup database
mysqldump -u [username] -p [database_name] > var/backups/$(date +%Y%m%d_%H%M%S)/database_backup.sql

# Backup MAB modules
cp -r app/code/Mab var/backups/$(date +%Y%m%d_%H%M%S)/
```

### 2. Enable Maintenance Mode
```bash
php bin/magento maintenance:enable
```

### 3. Clear Cache
```bash
php bin/magento cache:clean
php bin/magento cache:flush
```

### 4. Enable MAB Modules
```bash
php bin/magento module:enable Mab_Core
php bin/magento module:enable Mab_License
php bin/magento module:enable Mab_AdminLocale
php bin/magento module:enable Mab_CheckoutCustomization
php bin/magento module:enable Mab_DeliveryOptions
php bin/magento module:enable Mab_GuestCheckout
php bin/magento module:enable Mab_SocialLogin
php bin/magento module:enable Mab_SourceSelector
php bin/magento module:enable Mab_Theme
```

### 5. Run Setup Upgrade
```bash
php bin/magento setup:upgrade
```

### 6. Compile Dependency Injection
```bash
php bin/magento setup:di:compile
```

### 7. Deploy Static Content
```bash
php bin/magento setup:static-content:deploy -f
```

### 8. Reindex Data
```bash
php bin/magento indexer:reindex
```

### 9. Set File Permissions
```bash
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
find ./var -type d -exec chmod 777 {} \;
find ./pub/media -type d -exec chmod 777 {} \;
find ./pub/static -type d -exec chmod 777 {} \;
chmod +x bin/magento
```

### 10. Final Cache Clear
```bash
php bin/magento cache:clean
php bin/magento cache:flush
```

### 11. Disable Maintenance Mode
```bash
php bin/magento maintenance:disable
```

## Post-Deployment Verification

### 1. Check Module Status
```bash
php bin/magento module:status | grep Mab
```

### 2. Verify Admin Configuration
- Navigate to **Stores > Configuration > MAB Extensions**
- Verify all configuration sections are accessible
- Test license validation (if applicable)

### 3. Test Frontend Functionality
- Test checkout process
- Verify delivery options
- Check social login functionality
- Test guest checkout features

### 4. Check Error Logs
```bash
tail -f var/log/system.log
tail -f var/log/exception.log
```

## Configuration Guide

### Core Settings
Navigate to **Stores > Configuration > MAB Extensions > MAB Core Settings**

#### License Settings
- Enter your license key
- Verify license status

#### General Settings
- Enable/disable debug mode
- Configure logging preferences
- Set up Firebase integration (if needed)

### Module-Specific Configuration

#### Delivery Options
- Configure Yalidine shipping
- Set up free shipping rules
- Enable debug logging

#### Checkout Customization
- Customize checkout steps
- Configure discount code settings
- Set up Amasty integration

#### Admin Locale
- Force English in admin
- Hide locale selector

#### Source Selector
- Configure default succursale
- Enable stock filtering
- Set up frontend dropdown

## Troubleshooting

### Common Issues

#### 1. "Undefined array key 'id'" Error
This error was fixed in the system.xml files. If it persists:
- Clear cache: `php bin/magento cache:clean`
- Recompile: `php bin/magento setup:di:compile`

#### 2. Module Not Showing in Admin
- Verify module is enabled: `php bin/magento module:status`
- Check ACL permissions
- Clear cache and recompile

#### 3. Configuration Not Accessible
- Verify ACL resources are properly defined
- Check admin user permissions
- Clear configuration cache

### Debug Mode
Enable debug mode in each module's configuration to get detailed error information.

## Testing

### Pre-Deployment Testing

Before deploying to production, run the comprehensive test suite:

```bash
# Run syntax checks
./test-mab-syntax.sh

# Run comprehensive PHP tests
php test-mab-modules.php
```

### Unit Testing

To run unit tests for MAB modules:

```bash
# Run all MAB unit tests
cd app/code/Mab
../../../vendor/bin/phpunit --configuration phpunit.xml
```

### Integration Testing

Integration tests should be run in a staging environment that mirrors production.

## Performance Optimization

### Production Settings
1. Disable debug mode in all modules
2. Enable production mode: `php bin/magento deploy:mode:set production`
3. Enable full-page cache
4. Configure Redis for cache and sessions
5. Enable CSS/JS minification

### Caching Strategy
- Enable all cache types
- Use Redis for cache backend
- Configure Varnish for full-page cache

## Security Considerations

### File Permissions
- Ensure proper file permissions are set
- Restrict access to sensitive configuration files
- Use HTTPS for all admin and checkout pages

### License Validation
- Ensure license keys are properly configured
- Monitor license status regularly
- Keep Firebase configuration secure

## Monitoring and Maintenance

### Regular Tasks
1. Monitor error logs daily
2. Check license status weekly
3. Update modules as needed
4. Backup configuration regularly

### Performance Monitoring
- Monitor page load times
- Check database performance
- Monitor cache hit rates

## Support and Contact

For technical support or custom development:
- **Email**: mounir.ab@techno-dz.com
- **Developer**: Mounir AB
- **Organization**: Techno DZ

## Version History

### v1.0.0 (Current)
- Initial release
- All core modules implemented
- Production-ready configuration
- Comprehensive error handling
- Full documentation

---

**Note**: Always test in a staging environment before deploying to production.
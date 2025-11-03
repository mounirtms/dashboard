# MAB Extensions Configuration Guide

## Module Configuration Guide

### 1. DeliveryOptions Module (Mab_DeliveryOptions)

#### Yalidine Integration
1. Navigate to Stores > Configuration > MAB Extensions > Delivery Options
2. Configure shipping methods:
   ```
   Free Shipping Rules:
   - Minimum Order: 5000 DZD (default)
   - Method 2 (Home): 1600 DZD
   - Method 24 (Agency): 1300 DZD
   ```

3. Customize messages:
   ```
   Free shipping message:
   🎁 Plus que {amount} DZD d'achats pour bénéficier de la livraison gratuite! 🚚
   ```

4. Set up pickup locations:
   - Enable/disable pickup sources
   - Choose source type:
     * Magento sources only
     * Amasty sources only
     * Both source types

#### Advanced Configuration
1. Product-specific rules:
   - Enable specific SKU rules
   - Add SKU list for eligible products
   - Set custom conditions

2. Date and time slots:
   - Configure available days
   - Set excluded dates
   - Define time slots

### 2. Core Module (Mab_Core)

#### Base Configuration
1. System logging:
   - Enable/disable debug logging
   - Configure log rotation
   - Set log levels

2. Performance settings:
   - Cache configuration
   - Index management
   - Static content deployment

#### Integration Settings
1. Firebase setup:
   - API configuration
   - Authentication settings
   - Real-time updates

2. Third-party services:
   - API endpoints
   - Authentication tokens
   - Service configuration

### 3. Optimization & Maintenance

#### Cache Management
```bash
# Clear specific caches
php bin/magento cache:clean config layout block_html

# Flush all caches
php bin/magento cache:flush

# Deploy static content
php bin/magento setup:static-content:deploy fr_FR -f
```

#### Database Optimization
```bash
# Reindex data
php bin/magento indexer:reindex

# Clean logs
php bin/magento mab:logs:clean
```

### 4. Troubleshooting

#### Common Issues
1. Shipping rates not updating:
   - Clear shipping cache
   - Verify rate configuration
   - Check carrier availability

2. Free shipping not applying:
   - Verify minimum amount settings
   - Check SKU restrictions
   - Validate cart contents

3. Pickup locations not showing:
   - Verify source configuration
   - Check inventory settings
   - Validate API access

#### Logging & Debugging
1. Check logs at:
   ```
   var/log/system.log
   var/log/debug.log
   var/log/shipping.log
   ```

2. Enable debug mode:
   - Set developer mode
   - Enable debug logging
   - Monitor shipping calculations

### 5. Security Best Practices

1. Access Control:
   - Use proper ACL resources
   - Implement role restrictions
   - Validate user permissions

2. Data Validation:
   - Sanitize inputs
   - Validate configurations
   - Implement error handling

3. Regular Updates:
   - Keep modules updated
   - Monitor security patches
   - Backup configurations

### 6. Performance Optimization

1. Caching Strategy:
   - Configure proper cache types
   - Implement result caching
   - Use efficient queries

2. Code Efficiency:
   - Optimize database queries
   - Use proper indexing
   - Implement lazy loading

For additional support or custom development:
- Contact: mounir.ab@techno-dz.com
- Technical Support: Available Monday-Friday

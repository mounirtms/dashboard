# Magento 2 Multi-Environment Deployment Guide
## Techno Stationery - Dev → Beta → Production

---

## 📋 Table of Contents
1. [Environment Overview](#environment-overview)
2. [Directory Structure](#directory-structure)
3. [Database Configuration](#database-configuration)
4. [Deployment Workflow](#deployment-workflow)
5. [CI/CD Pipeline](#cicd-pipeline)
6. [Module Management](#module-management)
7. [Build Commands](#build-commands)
8. [Troubleshooting](#troubleshooting)

---

## 🌍 Environment Overview

### Production (technostationery.com)
- **Location**: `/home/technadminy7/public_html`
- **Database**: `technadminy7_dBT8x12y22`
- **Redis DBs**: 0 (cache), 1 (page_cache), 2 (session)
- **Size**: ~58 GB (with full media)
- **Purpose**: Live customer-facing website
- **Mode**: Developer (MAGE_MODE=developer)
- **Store Code**: `techno` (store_id=1)

### Beta (beta.technostationery.com)
- **Location**: `/home/beta/public_html`
- **Database**: `beta_dBT8x12y22`
- **Redis DBs**: 0 (cache), 1 (page_cache), 2 (session)
- **Size**: ~5 GB (media symlinked to production)
- **Purpose**: Pre-production testing and QA
- **Mode**: Developer (MAGE_MODE=developer)
- **Store Code**: `beta_store` (store_id=1)

### Dev (dev.technostationery.com)
- **Location**: `/home/dev/public_html`
- **Database**: `dev_dBT8x12y22`
- **Redis DBs**: 5 (cache), 6 (page_cache), 7 (session)
- **Size**: ~3.2 GB (originally planned with symlinks)
- **Purpose**: Development and module testing
- **Mode**: Developer (MAGE_MODE=developer)
- **Store Code**: `dev_store` (store_id=1)

---

## 📁 Directory Structure

### Shared Across Environments
```
pub/media/        # Shared product images, cache
vendor/           # Composer dependencies (1.3 GB)
lib/              # Magento libraries (32 MB)
```

### Environment-Specific
```
app/etc/          # Configuration files (env.php, config.php)
var/log/          # Environment-specific logs
var/cache/        # Runtime cache
var/session/      # Session data
generated/        # Generated code (172 MB)
pub/static/       # Compiled CSS/JS (335-349 MB)
```

### Current Setup Status

#### Beta Environment
- ✅ All files: Full copy from production
- ✅ Media: Symlinked to production (`pub/media -> /home/technadminy7/public_html/pub/media`)
- ✅ Vendor: Full copy (1.3 GB)
- ✅ Generated: Full copy (172 MB)
- ✅ Static: Full copy (349 MB)

#### Dev Environment
- ⚠️ All files: Full copy (symlinks were overwritten)
- ❌ Media: Full copy (4 KB - needs symlink)
- ❌ Vendor: Full copy (1.3 GB - could be symlinked)
- ❌ Generated: Full copy (172 MB - could be symlinked for read-only)
- ❌ Static: Full copy (335 MB - needs regeneration per environment)

---

## 🗄️ Database Configuration

### Connection Credentials

#### Production
```bash
/opt/mariadb10.6/mariadb/bin/mysql \
  -u root -p'YourNewStrongPassword' \
  -h 127.0.0.1 -P 3307 \
  technadminy7_dBT8x12y22
```

#### Beta
```bash
/opt/mariadb10.6/mariadb/bin/mysql \
  -u root -p'YourNewStrongPassword' \
  -h 127.0.0.1 -P 3307 \
  beta_dBT8x12y22
```

#### Dev
```bash
/opt/mariadb10.6/mariadb/bin/mysql \
  -u root -p'YourNewStrongPassword' \
  -h 127.0.0.1 -P 3307 \
  dev_dBT8x12y22
```

### Critical Database Settings

Each environment must have correct URLs configured:

```sql
-- Update base URLs
UPDATE core_config_data 
SET value='https://ENVIRONMENT.technostationery.com/' 
WHERE path IN ('web/unsecure/base_url', 'web/secure/base_url');

-- Disable store code in URL (to prevent /techno/ redirect)
UPDATE core_config_data 
SET value='0' 
WHERE path='web/url/use_store';

-- Verify settings
SELECT path, value 
FROM core_config_data 
WHERE path LIKE 'web/%/base%' OR path='web/url/use_store';
```

---

## 🚀 Deployment Workflow

### Phase 1: Development (Dev Environment)

1. **Enable Module for Testing**
   ```bash
   cd /home/dev/public_html
   php bin/magento module:enable Vendor_ModuleName
   php bin/magento setup:upgrade
   php bin/magento setup:di:compile
   php bin/magento cache:flush
   ```

2. **Test Functionality**
   - Verify frontend: https://dev.technostationery.com/
   - Verify admin: https://dev.technostationery.com/sysadminy
   - Check logs: `tail -f var/log/*.log`

3. **Document Changes**
   - List configuration changes
   - Note any database schema changes
   - Document new dependencies

### Phase 2: Beta Testing (Beta Environment)

1. **Sync Code from Dev**
   ```bash
   cd /home/beta/public_html
   # Copy only changed files
   rsync -av --exclude='app/etc/' --exclude='var/' \
     /home/dev/public_html/app/code/Vendor/ModuleName \
     app/code/Vendor/
   ```

2. **Enable Module**
   ```bash
   php bin/magento module:enable Vendor_ModuleName
   php bin/magento setup:upgrade
   php bin/magento setup:di:compile
   php bin/magento setup:static-content:deploy -f
   php bin/magento cache:flush
   ```

3. **QA Testing**
   - Full regression testing
   - Performance testing
   - Security review

### Phase 3: Production Deployment

1. **Backup Production**
   ```bash
   cd /home/technadminy7/public_html
   
   # Backup database
   /opt/mariadb10.6/mariadb/bin/mysqldump \
     -u root -p'YourNewStrongPassword' \
     -h 127.0.0.1 -P 3307 \
     --single-transaction --quick \
     technadminy7_dBT8x12y22 > /tmp/prod_backup_$(date +%Y%m%d_%H%M%S).sql
   
   # Backup files
   tar -czf /tmp/prod_files_$(date +%Y%m%d_%H%M%S).tar.gz \
     app/code/Vendor/ModuleName
   ```

2. **Deploy Module**
   ```bash
   cd /home/technadminy7/public_html
   php bin/magento maintenance:enable
   
   # Copy module
   rsync -av /home/beta/public_html/app/code/Vendor/ModuleName \
     app/code/Vendor/
   
   # Enable and compile
   php bin/magento module:enable Vendor_ModuleName
   php bin/magento setup:upgrade
   php bin/magento setup:di:compile
   php bin/magento setup:static-content:deploy -f
   
   php bin/magento maintenance:disable
   php bin/magento cache:flush
   ```

3. **Verify Deployment**
   - Test critical paths
   - Monitor logs
   - Check error rates

---

## 🔄 CI/CD Pipeline

### Proposed Workflow

```
Developer → Git Push → Dev Environment
                ↓
         Automated Tests
                ↓
         Code Review + Approval
                ↓
         Beta Environment
                ↓
         QA Testing
                ↓
         Approval + Merge
                ↓
         Production Deployment
```

### Git Workflow

1. **Development Branch**: `develop` or feature branches
2. **Beta Branch**: `beta` or `staging`
3. **Production Branch**: `main` or `production`

### Deployment Triggers

- **Dev**: Auto-deploy on push to `develop`
- **Beta**: Auto-deploy on merge to `beta` + manual approval
- **Production**: Manual deployment after beta QA sign-off

---

## 📦 Module Management

### Gradual Module Enablement Strategy

#### Phase 1: Core Modules (Week 1)
- `Magento_Catalog`
- `Magento_Customer`
- `Magento_Checkout`
- `Magento_Sales`

#### Phase 2: Payment & Shipping (Week 2)
- `Magento_Payment`
- `Magento_Shipping`
- Custom payment modules
- Custom shipping modules

#### Phase 3: Marketing & Promotions (Week 3)
- `Magento_CatalogRule`
- `Magento_SalesRule`
- `Magento_Newsletter`
- Amasty extensions (if needed)

#### Phase 4: Custom Modules (Week 4+)
- `Mab_*` modules (one by one)
- `Sm_*` theme modules
- Third-party extensions

### Module Testing Checklist

For each module:
- [ ] Enable in dev
- [ ] Run setup:upgrade
- [ ] Check for conflicts
- [ ] Test frontend functionality
- [ ] Test admin functionality
- [ ] Verify no performance degradation
- [ ] Check error logs
- [ ] Document configuration needs
- [ ] Promote to beta
- [ ] Full QA testing
- [ ] Deploy to production

---

## 🛠️ Build Commands

### Standard Deployment Build

```bash
#!/bin/bash
# Standard build process for all environments

cd /home/USER/public_html

# Clean caches and generated files
rm -rf var/cache/* var/page_cache/* var/view_preprocessed/* \
       var/log/* pub/static/frontend* generated/*

# Enable maintenance mode
php bin/magento maintenance:enable

# Upgrade database schema
php bin/magento setup:upgrade

# Compile dependency injection
php bin/magento setup:di:compile

# Deploy static content (all locales)
php bin/magento setup:static-content:deploy  -f

# Disable maintenance mode
php bin/magento maintenance:disable

# Flush all caches
php bin/magento cache:clean
php bin/magento cache:flush

# Clear Redis cache for this environment
redis-cli -n <DB_NUMBER> flushdb
```

### Environment-Specific Build Commands

#### Production Build
```bash
cd /home/technadminy7/public_html
chown -R technadminy7:technadminy7 .
chmod -R 777 pub/static/ var/ generated/

# Run standard build
# ... (commands above)

# Clear Redis
redis-cli -n 0 flushdb  # cache
redis-cli -n 1 flushdb  # page_cache
redis-cli -n 2 flushdb  # session
```

#### Beta Build
```bash
cd /home/beta/public_html
chown -R beta:beta .
chmod -R 777 pub/static/ var/ generated/

# Run standard build
# ... (commands above)

# Clear Redis
redis-cli -n 0 flushdb  # cache
redis-cli -n 1 flushdb  # page_cache
redis-cli -n 2 flushdb  # session
```

#### Dev Build
```bash
cd /home/dev/public_html
chown -R dev:dev .
chmod -R 777 pub/static/ var/ generated/

# Run standard build
# ... (commands above)

# Clear Redis
redis-cli -n 5 flushdb  # cache
redis-cli -n 6 flushdb  # page_cache
redis-cli -n 7 flushdb  # session
```

---

## 🐛 Troubleshooting

### Common Issues

#### Issue: "Class not found" errors
**Solution**: Run `php bin/magento setup:di:compile`

#### Issue: Static files not loading
**Solution**: 
```bash
rm -rf pub/static/frontend* generated/*
php bin/magento setup:static-content:deploy -f
```

#### Issue: Database schema outdated
**Solution**: `php bin/magento setup:upgrade`

#### Issue: Redirect to production from dev/beta
**Solution**: 
```sql
UPDATE core_config_data SET value='0' WHERE path='web/url/use_store';
```

#### Issue: Permission denied errors
**Solution**:
```bash
cd /home/USER/public_html
chown -R USER:USER .
chmod -R 777 pub/static/ var/ generated/
```

### Log Locations

- **System Log**: `var/log/system.log`
- **Exception Log**: `var/log/exception.log`
- **Debug Log**: `var/log/debug.log`
- **Apache Error Log**: `/etc/apache2/logs/error_log`
- **PHP Error Log**: Check `php.ini` for location

### Health Check Commands

```bash
# Check Magento status
php bin/magento --version
php bin/magento module:status

# Check database connection
php bin/magento setup:db:status

# Check Redis connection
redis-cli ping

# Check file permissions
find var/ pub/static/ generated/ ! -perm -666 -type f

# Check disk usage
du -sh /home/*/public_html
```

---

## 📊 Environment Comparison Table

| Aspect | Production | Beta | Dev |
|--------|-----------|------|-----|
| URL | technostationery.com | beta.technostationery.com | dev.technostationery.com |
| Database | technadminy7_dBT8x12y22 | beta_dBT8x12y22 | dev_dBT8x12y22 |
| Redis DBs | 0, 1, 2 | 0, 1, 2 | 5, 6, 7 |
| Size | 58 GB | 5 GB | 3.2 GB |
| Mode | Developer | Developer | Developer |
| Media | Full copy | Symlinked | Full copy |
| Purpose | Live site | QA/Testing | Development |
| Deployment | Manual | Semi-auto | Auto |

---

## 📝 Notes

- **Always test in Dev first, then Beta, then Production**
- **Never skip Beta testing**
- **Always backup before production deployment**
- **Monitor logs after every deployment**
- **Document all configuration changes**
- **Keep environments in sync (code-wise)**
- **Use separate databases for each environment**
- **Beta should mirror production as closely as possible**

---

## 📞 Support

For issues or questions, check:
1. This documentation
2. Magento DevDocs: https://devdocs.magento.com/
3. var/log files for errors
4. System administrator

---

**Last Updated**: March 3, 2026
**Version**: 1.0
**Maintainer**: System Administrator

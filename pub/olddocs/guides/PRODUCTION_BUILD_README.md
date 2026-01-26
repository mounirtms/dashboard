# Magento Production Build Scripts

This directory contains scripts to fix production build issues in Magento.

## Scripts

### 1. production-build-fix.sh
A comprehensive script that handles the entire production build process with detailed logging and error handling.

Features:
- Fixes file and directory permissions
- Clears all cache directories
- Runs Magento maintenance mode operations
- Executes setup upgrade, DI compilation, and static content deployment
- Sets production mode
- Performs indexing
- Checks website accessibility
- Reviews logs for errors

### 2. simple-production-build.sh
A simplified script focusing on the core build commands that were failing.

Features:
- Fixes critical permissions
- Executes the exact sequence of commands that were failing
- Includes error handling for each step
- Verifies website status after completion

## Usage

### For comprehensive build process:
```bash
./production-build-fix.sh
```

### For quick build process:
```bash
./simple-production-build.sh
```

## Troubleshooting Common Issues

### Permission Errors
Both scripts fix permissions automatically, but if you encounter permission errors:
```bash
sudo chown -R technadminy7:technadminy7 /home/technadminy7/public_html
find /home/technadminy7/public_html -type d -exec chmod 755 {} \;
find /home/technadminy7/public_html -type f -exec chmod 644 {} \;
chmod -R 777 /home/technadminy7/public_html/var /home/technadminy7/public_html/generated /home/technadminy7/public_html/pub/static /home/technadminy7/public_html/pub/media
```

### Cache Issues
If experiencing cache-related problems:
```bash
rm -rf var/cache/* var/page_cache/* var/view_preprocessed/* var/log/* pub/static/frontend* generated/*
php bin/magento cache:clean
php bin/magento cache:flush
```

### Maintenance Mode
If the site gets stuck in maintenance mode:
```bash
php bin/magento maintenance:disable
```

## Original Problem Commands That Were Failing

The scripts address these commands that were failing:
```bash
rm -rf var/cache/* var/page_cache/* var/view_preprocessed/* var/log/* pub/static/frontend* generated/* 
php bin/magento maintenance:enable 
php bin/magento setup:upgrade 
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f 
php bin/magento maintenance:disable 
php bin/magento cache:clean
php bin/magento cache:flush
php bin/magento indexer:reindex
php bin/magento deploy:mode:set production
```

## Important Notes

- Both scripts should be run from the Magento root directory
- The scripts include error handling and will exit if critical steps fail
- Always backup your site before running these scripts in production
- Check the logs after running the scripts to ensure everything worked correctly

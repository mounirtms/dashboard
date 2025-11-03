# Magento Migration Scripts

This directory contains professional scripts for migrating data from the production environment (`technadminy7_dBT8x12y22`) to the beta environment (`beta_dBT8x12y22`).

## Scripts Overview

1. **[db-migrate.sh](file:///home/technadminy7/public_html/scripts/migration/db-migrate.sh)** - Migrates database tables excluding sensitive/core configuration data
2. **[code-migrate.sh](file:///home/technadminy7/public_html/scripts/migration/code-migrate.sh)** - Migrates source code excluding environment-specific files
3. **[media-migrate.sh](file:///home/technadminy7/public_html/scripts/migration/media-migrate.sh)** - Migrates media files
4. **[full-migrate.sh](file:///home/technadminy7/public_html/scripts/migration/full-migrate.sh)** - Runs all migration scripts in the correct order

## Prerequisites

1. Ensure both databases (`technadminy7_dBT8x12y22` and `beta_dBT8x12y22`) exist
2. Ensure the target directory `/home/technadminy7/beta` exists or can be created
3. Verify database credentials in [db-migrate.sh](file:///home/technadminy7/public_html/scripts/migration/db-migrate.sh)
4. **Create backups before running any migration scripts**

## Usage

### Individual Script Execution

```bash
# Navigate to the migration directory
cd /home/technadminy7/public_html/scripts/migration

# Run database migration
./db-migrate.sh

# Run source code migration
./code-migrate.sh

# Run media files migration
./media-migrate.sh
```

### Full Migration

```bash
# Run all migrations in order
./full-migrate.sh
```

## Exclusions

### Database Tables Excluded:
- `core_config_data` (core configuration)
- All temporary/session tables
- All order-related tables
- All customer-related tables
- All log/reporting tables
- And many more sensitive tables

### Files/Directories Excluded from Source Code Migration:
- `app/etc/env.php` (environment configuration)
- `var/` directory (temporary files)
- `pub/static/` directory (generated static files)
- `generated/` directory (generated code)
- `vendor/` directory (composer dependencies)
- `.git/` directory
- Log files (`*.log`)
- Backup files (`*.bak`, `*.swp`)
- OS-specific files (`.DS_Store`, `Thumbs.db`)
- Sitemap files
- Authentication files (`auth*`, `key*`)
- `composer.lock`
- Test modules
- `en.php` files

### Files/Directories Excluded from Media Migration:
- Version control directories (`.git/`)
- IDE configuration directories
- Log files
- Backup files
- OS-specific files

## Logging

All scripts generate detailed logs in the same directory:
- `db-migration.log`
- `code-migration.log`
- `media-migration.log`
- `full-migration.log`

## Important Notes

1. **Always run these scripts during low-traffic periods**
2. **Always create backups before migration**
3. **Test the beta environment thoroughly after migration**
4. **These scripts are designed for one-way migration (production → beta)**
5. **Do not run these scripts on production during peak hours**

## Troubleshooting

If you encounter issues:

1. Check the log files for error messages
2. Verify database connectivity and permissions
3. Ensure sufficient disk space in target directories
4. Check file permissions on source and target directories

## Customization

You can modify the exclusion lists in each script to add or remove files/tables as needed for your specific migration requirements.# Magento Migration Scripts

This directory contains professional scripts for migrating data from the production environment (`technadminy7_dBT8x12y22`) to the beta environment (`beta_dBT8x12y22`).

## Scripts Overview

1. **[db-migrate.sh](file:///home/technadminy7/public_html/scripts/migration/db-migrate.sh)** - Migrates database tables excluding sensitive/core configuration data
2. **[code-migrate.sh](file:///home/technadminy7/public_html/scripts/migration/code-migrate.sh)** - Migrates source code excluding environment-specific files
3. **[media-migrate.sh](file:///home/technadminy7/public_html/scripts/migration/media-migrate.sh)** - Migrates media files
4. **[full-migrate.sh](file:///home/technadminy7/public_html/scripts/migration/full-migrate.sh)** - Runs all migration scripts in the correct order

## Prerequisites

1. Ensure both databases (`technadminy7_dBT8x12y22` and `beta_dBT8x12y22`) exist
2. Ensure the target directory `/home/technadminy7/beta` exists or can be created
3. Verify database credentials in [db-migrate.sh](file:///home/technadminy7/public_html/scripts/migration/db-migrate.sh)
4. **Create backups before running any migration scripts**

## Usage

### Individual Script Execution

```bash
# Navigate to the migration directory
cd /home/technadminy7/public_html/scripts/migration

# Run database migration
./db-migrate.sh

# Run source code migration
./code-migrate.sh

# Run media files migration
./media-migrate.sh
```

### Full Migration

```bash
# Run all migrations in order
./full-migrate.sh
```

## Exclusions

### Database Tables Excluded:
- `core_config_data` (core configuration)
- All temporary/session tables
- All order-related tables
- All customer-related tables
- All log/reporting tables
- And many more sensitive tables

### Files/Directories Excluded from Source Code Migration:
- `app/etc/env.php` (environment configuration)
- `var/` directory (temporary files)
- `pub/static/` directory (generated static files)
- `generated/` directory (generated code)
- `vendor/` directory (composer dependencies)
- `.git/` directory
- Log files (`*.log`)
- Backup files (`*.bak`, `*.swp`)
- OS-specific files (`.DS_Store`, `Thumbs.db`)
- Sitemap files
- Authentication files (`auth*`, `key*`)
- `composer.lock`
- Test modules
- `en.php` files

### Files/Directories Excluded from Media Migration:
- Version control directories (`.git/`)
- IDE configuration directories
- Log files
- Backup files
- OS-specific files

## Logging

All scripts generate detailed logs in the same directory:
- `db-migration.log`
- `code-migration.log`
- `media-migration.log`
- `full-migration.log`

## Important Notes

1. **Always run these scripts during low-traffic periods**
2. **Always create backups before migration**
3. **Test the beta environment thoroughly after migration**
4. **These scripts are designed for one-way migration (production → beta)**
5. **Do not run these scripts on production during peak hours**

## Troubleshooting

If you encounter issues:

1. Check the log files for error messages
2. Verify database connectivity and permissions
3. Ensure sufficient disk space in target directories
4. Check file permissions on source and target directories

## Customization

You can modify the exclusion lists in each script to add or remove files/tables as needed for your specific migration requirements.
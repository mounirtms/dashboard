#!/bin/bash

# Streamlined Backup Script for Magento with Integrated Upload
# This script creates a database and file backup, and uploads directly to iDrive

set -e

# === Configuration ===
PROJECT_ROOT="/home/betapublic_html"
DATE=$(date +%F)
DATETIME=$(date +%F-%H-%M-%S)
BACKUP_DIR="/backup/$DATE"
LOG_FILE="${PROJECT_ROOT}/var/log/streamlined-backup.log"

# Create log directory if it doesn't exist
mkdir -p "${PROJECT_ROOT}/var/log"

# === iDrive S3 Configuration ===
AWS_CMD="/usr/local/bin/aws"
export AWS_ACCESS_KEY_ID="prQjrOCZTP1yTOPfYvRl"
export AWS_SECRET_ACCESS_KEY="41VJfqqxpbGse3G1UUTtdCLWUNGhtxnuf5DssWpT"
export AWS_DEFAULT_REGION="us-west-1"

S3_BUCKET="s3://weektechno"
S3_ENDPOINT="https://l0y0.la.idrivee2-27.com"

# === MySQL Configuration ===
MYSQL_PASS='YourNewStrongPassword'
MYSQL_DB='technadminy7_dBT8x12y22'
MYSQL_USER='root'
MYSQL_HOST='127.0.0.1'
MYSQL_PORT='3307'
MYSQL_BIN='/opt/mariadb10.6/mariadb/bin/mysqldump'

# === Retention Policy ===
DAILY_RETENTION=7      # Keep daily backups for 7 days
WEEKLY_RETENTION=30    # Keep weekly backups for 30 days
MONTHLY_RETENTION=90   # Keep monthly backups for 90 days

# === Colors for output ===
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# === Functions ===
die() {
    echo -e "${RED}[$(date '+%Y-%m-%d %H:%M:%S')] ❌ ERROR: $1${NC}" | tee -a "$LOG_FILE"
    exit 1
}

log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')] $1${NC}" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')] ✅ SUCCESS: $1${NC}" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[$(date '+%Y-%m-%d %H:%M:%S')] ⚠️  WARNING: $1${NC}" | tee -a "$LOG_FILE"
}

# Create backup directories
create_backup_dirs() {
    log "Creating backup directories..."
    
    # Create backup directory with proper permissions
    sudo mkdir -p "$BACKUP_DIR"
    sudo chown technadminy7. "$BACKUP_DIR"
    sudo chmod 755 "$BACKUP_DIR"
    
    # Create subdirectories
    mkdir -p "$BACKUP_DIR"/{system,accounts,products,pim,beta,logs,source,configs}
    
    success "Backup directories created"
}

# Create database backup
create_database_backup() {
    log "Creating database backup..."
    
    # Database backup
    "$MYSQL_BIN" \
        --host="$MYSQL_HOST" \
        --port="$MYSQL_PORT" \
        --user="$MYSQL_USER" \
        --password="$MYSQL_PASS" \
        --single-transaction \
        --routines \
        --triggers \
        "$MYSQL_DB" > "$BACKUP_DIR/database-$DATETIME.sql" || die "Failed to create database backup"
    
    success "Database backup created: database-$DATETIME.sql"
}

# Create file backups
create_file_backups() {
    log "Creating file backups..."
    
    # System files backup - essential configuration and source code files
    cd "$PROJECT_ROOT"
    
    # Backup essential configuration files
    tar -czf "$BACKUP_DIR/system/system-config-$DATETIME.tar.gz" \
        --exclude="var/cache/*" \
        --exclude="var/page_cache/*" \
        --exclude="var/session/*" \
        --exclude="pub/media/*" \
        --exclude="var/log/*" \
        --exclude="var/report/*" \
        --exclude="var/tmp/*" \
        --exclude="var/backups/*" \
        --exclude="node_modules/*" \
        --exclude=".git/*" \
        app/etc/* 2>/dev/null || die "Failed to create system config backup"
        
    success "System config backup created: system-config-$DATETIME.tar.gz"
    
    # Source code backup - Magento core and custom modules
    log "Creating source code backup..."
    tar -czf "$BACKUP_DIR/source/magento-source-$DATETIME.tar.gz" \
        --exclude="var/cache/*" \
        --exclude="var/page_cache/*" \
        --exclude="var/session/*" \
        --exclude="pub/media/*" \
        --exclude="var/log/*" \
        --exclude="var/report/*" \
        --exclude="var/tmp/*" \
        --exclude="var/backups/*" \
        --exclude="node_modules/*" \
        --exclude=".git/*" \
        --exclude="pub/static/*" \
        app/ \
        lib/ \
        pub/index.php \
        pub/errors/ \
        vendor/ 2>/dev/null || warning "Failed to create full source code backup"
        
    success "Source code backup created: magento-source-$DATETIME.tar.gz"
    
    # Accounts backup - only specific accounts and folders
    # PIM account backup - complete public_html directory
    if [ -d "/home/pim" ]; then
        log "Creating pim account backup..."
        tar -czf "$BACKUP_DIR/pim/pim-full-$DATETIME.tar.gz" \
            -C "/home/pim" public_html 2>/dev/null || warning "Failed to create full pim backup"
        success "Pim account backup created: pim-full-$DATETIME.tar.gz"
    else
        warning "Pim account not found, skipping pim backup"
    fi
    
    # Beta account backup - complete public_html directory
    if [ -d "/home/beta" ]; then
        log "Creating beta account backup..."
        tar -czf "$BACKUP_DIR/beta/beta-full-$DATETIME.tar.gz" \
            -C "/home/beta" public_html 2>/dev/null || warning "Failed to create full beta backup"
        success "Beta account backup created: beta-full-$DATETIME.tar.gz"
    else
        warning "Beta account not found, skipping beta backup"
    fi
    
    # Technadminy7 account - media files and source code
    if [ -d "$PROJECT_ROOT/pub/media" ]; then
        log "Creating technadminy7 media backup..."
        
        # Create archive of important media directories
        # Catalog images are the most important
        if [ -d "$PROJECT_ROOT/pub/media/catalog" ]; then
            tar -czf "$BACKUP_DIR/products/catalog-$DATETIME.tar.gz" \
                -C "$PROJECT_ROOT/pub/media" catalog 2>/dev/null || warning "Failed to create catalog backup"
        fi
        
        # Product images organized by SKU (if exists)
        if [ -d "$PROJECT_ROOT/product_images_by_sku" ]; then
            log "Creating product images by SKU backup..."
            tar -czf "$BACKUP_DIR/products/product-images-by-sku-$DATETIME.tar.gz" \
                -C "$PROJECT_ROOT" product_images_by_sku 2>/dev/null || warning "Failed to create product images by SKU backup"
            success "Product images by SKU backup created"
        fi
        
        # Other important media directories
        for dir in wysiwyg promobanners lookbook; do
            if [ -d "$PROJECT_ROOT/pub/media/$dir" ]; then
                tar -czf "$BACKUP_DIR/products/$dir-$DATETIME.tar.gz" \
                    -C "$PROJECT_ROOT/pub/media" "$dir" 2>/dev/null || warning "Failed to create $dir backup"
            fi
        done
        
        success "Technadminy7 media backup created"
    else
        warning "Technadminy7 media directory not found, skipping media backup"
    fi
    
    # Important logs backup
    if [ -d "$PROJECT_ROOT/var/log" ]; then
        log "Creating logs backup..."
        
        # Only backup important logs
        mkdir -p "/tmp/logs-backup-$DATETIME"
        
        # Copy important logs
        for log_file in system.log exception.log cron.log debug.log; do
            if [ -f "$PROJECT_ROOT/var/log/$log_file" ]; then
                cp "$PROJECT_ROOT/var/log/$log_file" "/tmp/logs-backup-$DATETIME/" 2>/dev/null || true
            fi
        done
        
        # Create archive of logs
        tar -czf "$BACKUP_DIR/logs/logs-$DATETIME.tar.gz" -C "/tmp" "logs-backup-$DATETIME" 2>/dev/null || warning "Failed to create logs backup"
        
        # Clean up temporary directory
        rm -rf "/tmp/logs-backup-$DATETIME"
        
        success "Logs backup created: logs-$DATETIME.tar.gz"
    else
        warning "Logs directory not found, skipping logs backup"
    fi
}

# Backup system configurations (server-level configs not covered by WHM backups)
backup_system_configurations() {
    log "Creating system configurations backup..."

    local CFG_DIR="$BACKUP_DIR/configs"
    mkdir -p "$CFG_DIR"/{varnish,apache,redis,mariadb,systemd,projects,cron,csf,sysctl,limits,php,php-fpm,cpanel,ssh,logrotate,imunify360}

    # === Varnish ===
    if [ -d "/etc/varnish" ]; then
        log "Backing up Varnish configurations..."
        for f in default.vcl backends.vcl technostationery.vcl varnish.params varnish.params.optimized secret; do
            if [ -f "/etc/varnish/$f" ]; then
                cp "/etc/varnish/$f" "$CFG_DIR/varnish/" 2>/dev/null || true
            fi
        done
        success "Varnish configs backed up"
    else
        warning "Varnish config directory not found, skipping"
    fi

    # === Apache ===
    log "Backing up Apache configurations..."
    local APACHE_CONF="/usr/local/apache/conf"

    for f in httpd.conf php.conf mime.types; do
        if [ -f "$APACHE_CONF/$f" ]; then
            cp "$APACHE_CONF/$f" "$CFG_DIR/apache/" 2>/dev/null || true
        fi
    done
    for d in includes userdata modsec conf.d; do
        if [ -d "$APACHE_CONF/$d" ]; then
            cp -a "$APACHE_CONF/$d" "$CFG_DIR/apache/$d" 2>/dev/null || true
        fi
    done
    success "Apache configs backed up"

    # === Redis ===
    log "Backing up Redis configurations..."
    for f in /etc/redis.conf /etc/redis_optimization.conf /etc/redis-sentinel.conf; do
        if [ -f "$f" ]; then
            cp "$f" "$CFG_DIR/redis/" 2>/dev/null || true
        fi
    done
    success "Redis configs backed up"

    # === MariaDB 10.6 ===
    log "Backing up MariaDB configurations..."
    if [ -f "/etc/my.cnf" ]; then
        cp /etc/my.cnf "$CFG_DIR/mariadb/" 2>/dev/null || true
    fi
    if [ -d "/etc/my.cnf.d" ]; then
        for f in magento-optimized.cnf client.cnf server.cnf mysql-clients.cnf spider.cnf enable_encryption.preset server-cert.pem server-key.pem; do
            if [ -f "/etc/my.cnf.d/$f" ]; then
                cp "/etc/my.cnf.d/$f" "$CFG_DIR/mariadb/" 2>/dev/null || true
            fi
        done
    fi
    if [ -f "/opt/mariadb10.6/my.cnf" ]; then
        cp /opt/mariadb10.6/my.cnf "$CFG_DIR/mariadb/my.cnf.mariadb10.6" 2>/dev/null || true
    fi
    success "MariaDB configs backed up"

    # === Systemd service overrides ===
    log "Backing up systemd service overrides..."
    local SYSTEMD_DIR="/etc/systemd/system"
    for f in varnish.service varnish.service.backup mariadb10.6.service; do
        if [ -f "$SYSTEMD_DIR/$f" ]; then
            cp "$SYSTEMD_DIR/$f" "$CFG_DIR/systemd/" 2>/dev/null || true
        fi
    done
    for d in varnish.service.d varnishncsa.service.d redis.service.d redis-sentinel.service.d mariadb.service.d; do
        if [ -d "$SYSTEMD_DIR/$d" ]; then
            cp -a "$SYSTEMD_DIR/$d" "$CFG_DIR/systemd/$d" 2>/dev/null || true
        fi
    done
    success "Systemd overrides backed up"

    # === CSF Firewall ===
    if [ -d "/etc/csf" ]; then
        log "Backing up CSF firewall configurations..."
        for f in csf.conf csf.allow csf.deny csf.ignore csf.blocklists csf.cloudflare \
                 csf.dirwatch csf.fignore csf.pignore csf.rignore csf.signore \
                 csf.logignore csf.syslogs csf.smtpauth csf.resellers csf.redirect \
                 csf.rblconf regex.custom.pm imh.allow imh.ignore cpanel.allow cpanel.ignore; do
            if [ -f "/etc/csf/$f" ]; then
                cp "/etc/csf/$f" "$CFG_DIR/csf/" 2>/dev/null || true
            fi
        done
        success "CSF firewall configs backed up"
    else
        warning "CSF config directory not found, skipping"
    fi

    # === Sysctl kernel tuning ===
    log "Backing up sysctl kernel tuning..."
    if [ -f "/etc/sysctl.conf" ]; then
        cp /etc/sysctl.conf "$CFG_DIR/sysctl/" 2>/dev/null || true
    fi
    if [ -d "/etc/sysctl.d" ]; then
        for f in /etc/sysctl.d/*.conf; do
            if [ -f "$f" ]; then
                cp "$f" "$CFG_DIR/sysctl/" 2>/dev/null || true
            fi
        done
    fi
    success "Sysctl tuning configs backed up"

    # === Security limits (ulimits) ===
    log "Backing up security limits..."
    if [ -f "/etc/security/limits.conf" ]; then
        cp /etc/security/limits.conf "$CFG_DIR/limits/" 2>/dev/null || true
    fi
    if [ -d "/etc/security/limits.d" ]; then
        for f in /etc/security/limits.d/*.conf; do
            if [ -f "$f" ]; then
                cp "$f" "$CFG_DIR/limits/" 2>/dev/null || true
            fi
        done
    fi
    success "Security limits backed up"

    # === EA-PHP (EasyApache PHP 8.2 and 8.3) ===
    log "Backing up PHP configurations..."
    for php_ver in ea-php82 ea-php83; do
        local PHP_ROOT="/opt/cpanel/$php_ver/root/etc"
        if [ -d "$PHP_ROOT" ]; then
            mkdir -p "$CFG_DIR/php/$php_ver/php.d"
            if [ -f "$PHP_ROOT/php.ini" ]; then
                cp "$PHP_ROOT/php.ini" "$CFG_DIR/php/$php_ver/" 2>/dev/null || true
            fi
            for ini in 10-opcache.ini 50-redis.ini imagick.ini 20-intl.ini; do
                if [ -f "$PHP_ROOT/php.d/$ini" ]; then
                    cp "$PHP_ROOT/php.d/$ini" "$CFG_DIR/php/$php_ver/php.d/" 2>/dev/null || true
                fi
            done
        fi
    done
    success "PHP configs backed up"

    # === PHP-FPM pool configs (per domain) ===
    log "Backing up PHP-FPM pool configurations..."
    for php_ver in ea-php81 ea-php82 ea-php83; do
        local FPM_DIR="/opt/cpanel/$php_ver/root/etc/php-fpm.d"
        if [ -d "$FPM_DIR" ]; then
            mkdir -p "$CFG_DIR/php-fpm/$php_ver"
            for f in "$FPM_DIR"/*.conf; do
                if [ -f "$f" ]; then
                    cp "$f" "$CFG_DIR/php-fpm/$php_ver/" 2>/dev/null || true
                fi
            done
        fi
    done
    if [ -d "/var/cpanel/ApachePHPFPM" ]; then
        for f in system_pool_defaults.yaml default_accounts_to_fpm; do
            if [ -f "/var/cpanel/ApachePHPFPM/$f" ]; then
                cp "/var/cpanel/ApachePHPFPM/$f" "$CFG_DIR/php-fpm/" 2>/dev/null || true
            fi
        done
    fi
    success "PHP-FPM pool configs backed up"

    # === cPanel user account definitions ===
    log "Backing up cPanel user definitions..."
    if [ -d "/var/cpanel/users" ]; then
        for f in /var/cpanel/users/*; do
            if [ -f "$f" ]; then
                cp "$f" "$CFG_DIR/cpanel/" 2>/dev/null || true
            fi
        done
    fi
    success "cPanel user definitions backed up"

    # === SSH ===
    log "Backing up SSH configuration..."
    if [ -f "/etc/ssh/sshd_config" ]; then
        cp /etc/ssh/sshd_config "$CFG_DIR/ssh/" 2>/dev/null || true
    fi
    success "SSH config backed up"

    # === Logrotate (custom configs for services) ===
    log "Backing up logrotate configurations..."
    if [ -d "/etc/logrotate.d" ]; then
        for f in varnish redis magento akeneo-pim \
                 ea-php82-php-fpm ea-php83-php-fpm \
                 apache backups.conf imunify360-antivirus imunify-core; do
            if [ -f "/etc/logrotate.d/$f" ]; then
                cp "/etc/logrotate.d/$f" "$CFG_DIR/logrotate/" 2>/dev/null || true
            fi
        done
    fi
    success "Logrotate configs backed up"

    # === Imunify360 ===
    if [ -d "/etc/imunify360/user_config" ]; then
        log "Backing up Imunify360 configuration..."
        cp -a /etc/imunify360/user_config "$CFG_DIR/imunify360/" 2>/dev/null || true
        success "Imunify360 config backed up"
    else
        warning "Imunify360 config not found, skipping"
    fi

    # === Project .env and .htaccess files ===
    log "Backing up project configuration files..."

    if [ -d "/home/dashboard/public_html" ]; then
        mkdir -p "$CFG_DIR/projects/dashboard"
        if [ -f "/home/dashboard/public_html/.env" ]; then
            cp "/home/dashboard/public_html/.env" "$CFG_DIR/projects/dashboard/" 2>/dev/null || true
        fi
        find /home/dashboard/public_html -name ".htaccess" -exec bash -c '
            rel="${1#/home/dashboard/public_html/}"
            dest_dir="$2/$(dirname "$rel")"
            mkdir -p "$dest_dir"
            cp "$1" "$dest_dir/"
        ' _ {} "$CFG_DIR/projects/dashboard" \; 2>/dev/null || true
    fi

    if [ -d "/home/pim/public_html" ] && [ -f "/home/pim/public_html/.env" ]; then
        mkdir -p "$CFG_DIR/projects/pim"
        cp "/home/pim/public_html/.env" "$CFG_DIR/projects/pim/" 2>/dev/null || true
    fi

    if [ -d "/home/betapublic_html" ]; then
        mkdir -p "$CFG_DIR/projects/beta"
        if [ -f "/home/betapublic_html/.htaccess" ]; then
            cp "/home/betapublic_html/.htaccess" "$CFG_DIR/projects/beta/" 2>/dev/null || true
        fi
        if [ -f "/home/betapublic_html/app/etc/env.php" ]; then
            mkdir -p "$CFG_DIR/projects/beta/app/etc"
            cp "/home/betapublic_html/app/etc/env.php" "$CFG_DIR/projects/beta/app/etc/" 2>/dev/null || true
        fi
        if [ -f "/home/betapublic_html/app/etc/config.php" ]; then
            mkdir -p "$CFG_DIR/projects/beta/app/etc"
            cp "/home/betapublic_html/app/etc/config.php" "$CFG_DIR/projects/beta/app/etc/" 2>/dev/null || true
        fi
    fi

    if [ -d "/home/dev/public_html" ]; then
        mkdir -p "$CFG_DIR/projects/dev"
        if [ -f "/home/dev/public_html/.htaccess" ]; then
            cp "/home/dev/public_html/.htaccess" "$CFG_DIR/projects/dev/" 2>/dev/null || true
        fi
        if [ -f "/home/dev/public_html/app/etc/env.php" ]; then
            mkdir -p "$CFG_DIR/projects/dev/app/etc"
            cp "/home/dev/public_html/app/etc/env.php" "$CFG_DIR/projects/dev/app/etc/" 2>/dev/null || true
        fi
    fi

    success "Project configs backed up"

    # === Crontabs ===
    log "Backing up crontabs..."
    crontab -l > "$CFG_DIR/cron/root_crontab.txt" 2>/dev/null || true
    for user in technadminy7 beta pim dev dashboard; do
        crontab -u "$user" -l > "$CFG_DIR/cron/${user}_crontab.txt" 2>/dev/null || true
    done
    success "Crontabs backed up"

    # === Create combined archive ===
    log "Creating system configs archive..."
    tar -czf "$CFG_DIR/server-configs-$DATETIME.tar.gz" -C "$CFG_DIR" \
        varnish apache redis mariadb systemd projects cron \
        csf sysctl limits php php-fpm cpanel ssh logrotate imunify360 \
        2>/dev/null || warning "Failed to create combined configs archive"

    success "System configurations backup completed"
}

# Upload backup directly to iDrive
upload_to_idrive() {
    log "Uploading backup to iDrive..."
    
    # Upload system directory
    if [ -d "$BACKUP_DIR/system" ] && [ "$(ls -A "$BACKUP_DIR/system")" ]; then
        log "Uploading system directory..."
        sudo "$AWS_CMD" s3 sync "$BACKUP_DIR/system" "$S3_BUCKET/$DATE/system/" \
            --endpoint-url "$S3_ENDPOINT" \
            --delete || die "Failed to upload system directory to iDrive"
        success "System directory uploaded to iDrive"
    fi
    
    # Upload source directory
    if [ -d "$BACKUP_DIR/source" ] && [ "$(ls -A "$BACKUP_DIR/source")" ]; then
        log "Uploading source directory..."
        sudo "$AWS_CMD" s3 sync "$BACKUP_DIR/source" "$S3_BUCKET/$DATE/source/" \
            --endpoint-url "$S3_ENDPOINT" \
            --delete || die "Failed to upload source directory to iDrive"
        success "Source directory uploaded to iDrive"
    fi
    
    # Upload accounts directory
    if [ -d "$BACKUP_DIR/accounts" ] && [ "$(ls -A "$BACKUP_DIR/accounts")" ]; then
        log "Uploading accounts directory..."
        sudo "$AWS_CMD" s3 sync "$BACKUP_DIR/accounts" "$S3_BUCKET/$DATE/accounts/" \
            --endpoint-url "$S3_ENDPOINT" \
            --delete || die "Failed to upload accounts directory to iDrive"
        success "Accounts directory uploaded to iDrive"
    fi
    
    # Upload products directory
    if [ -d "$BACKUP_DIR/products" ] && [ "$(ls -A "$BACKUP_DIR/products")" ]; then
        log "Uploading products directory..."
        sudo "$AWS_CMD" s3 sync "$BACKUP_DIR/products" "$S3_BUCKET/$DATE/products/" \
            --endpoint-url "$S3_ENDPOINT" \
            --delete || die "Failed to upload products directory to iDrive"
        success "Products directory uploaded to iDrive"
    fi
    
    # Upload pim directory
    if [ -d "$BACKUP_DIR/pim" ] && [ "$(ls -A "$BACKUP_DIR/pim")" ]; then
        log "Uploading pim directory..."
        sudo "$AWS_CMD" s3 sync "$BACKUP_DIR/pim" "$S3_BUCKET/$DATE/pim/" \
            --endpoint-url "$S3_ENDPOINT" \
            --delete || die "Failed to upload pim directory to iDrive"
        success "Pim directory uploaded to iDrive"
    fi
    
    # Upload beta directory
    if [ -d "$BACKUP_DIR/beta" ] && [ "$(ls -A "$BACKUP_DIR/beta")" ]; then
        log "Uploading beta directory..."
        sudo "$AWS_CMD" s3 sync "$BACKUP_DIR/beta" "$S3_BUCKET/$DATE/beta/" \
            --endpoint-url "$S3_ENDPOINT" \
            --delete || die "Failed to upload beta directory to iDrive"
        success "Beta directory uploaded to iDrive"
    fi
    
    # Upload logs directory
    if [ -d "$BACKUP_DIR/logs" ] && [ "$(ls -A "$BACKUP_DIR/logs")" ]; then
        log "Uploading logs directory..."
        sudo "$AWS_CMD" s3 sync "$BACKUP_DIR/logs" "$S3_BUCKET/$DATE/logs/" \
            --endpoint-url "$S3_ENDPOINT" \
            --delete || die "Failed to upload logs directory to iDrive"
        success "Logs directory uploaded to iDrive"
    fi

    # Upload configs directory
    if [ -d "$BACKUP_DIR/configs" ] && [ "$(ls -A "$BACKUP_DIR/configs")" ]; then
        log "Uploading configs directory..."
        sudo "$AWS_CMD" s3 sync "$BACKUP_DIR/configs" "$S3_BUCKET/$DATE/configs/" \
            --endpoint-url "$S3_ENDPOINT" \
            --delete || die "Failed to upload configs directory to iDrive"
        success "Configs directory uploaded to iDrive"
    fi

    success "Backup uploaded to iDrive"
}

# Cleanup old local backups
cleanup_local_backups() {
    log "Cleaning up local backups older than $DAILY_RETENTION days..."
    
    # Clean up backups with standard date format (YYYY-MM-DD)
    find /backup -maxdepth 1 -type d -name "????-??-??" -mtime +$DAILY_RETENTION -exec sudo rm -rf {} + 2>/dev/null || true
    
    # Clean up backups with extended format (YYYY-MM-DD-*)
    find /backup -maxdepth 1 -type d -name "????-??-??-*" -mtime +$DAILY_RETENTION -exec sudo rm -rf {} + 2>/dev/null || true
    
    success "Local backups cleanup completed"
}

# Implement retention policy on iDrive
implement_retention_policy() {
    log "Implementing retention policy on iDrive..."
    
    # Get list of backup dates from iDrive
    sudo "$AWS_CMD" s3 ls "$S3_BUCKET/" --endpoint-url "$S3_ENDPOINT" | \
        awk '{print $2}' | sed 's/\/$//' > /tmp/backup_dates.txt
    
    # Process each backup date
    while read -r backup_date; do
        if [ -n "$backup_date" ]; then
            # Convert date string to seconds
            backup_seconds=$(date -d "$backup_date" +%s 2>/dev/null || echo 0)
            current_seconds=$(date +%s)
            
            if [ "$backup_seconds" -gt 0 ]; then
                # Calculate age in days
                age_days=$(( (current_seconds - backup_seconds) / 86400 ))
                
                # Apply retention policy
                day_of_week=$(date -d "$backup_date" +%u 2>/dev/null || echo "")
                day_of_month=$(date -d "$backup_date" +%d 2>/dev/null || echo "")
                
                # Delete based on retention policy
                if [ "$age_days" -gt "$MONTHLY_RETENTION" ]; then
                    # Older than 90 days - delete
                    log "Deleting backup $backup_date (older than $MONTHLY_RETENTION days)"
                    sudo "$AWS_CMD" s3 rm "$S3_BUCKET/$backup_date" --recursive \
                        --endpoint-url "$S3_ENDPOINT" 2>/dev/null || true
                elif [ "$age_days" -gt "$WEEKLY_RETENTION" ]; then
                    # Between 30-90 days - keep only first of month
                    if [ "$day_of_month" != "01" ]; then
                        log "Deleting backup $backup_date (not 1st of month, between 30-90 days old)"
                        sudo "$AWS_CMD" s3 rm "$S3_BUCKET/$backup_date" --recursive \
                            --endpoint-url "$S3_ENDPOINT" 2>/dev/null || true
                    fi
                elif [ "$age_days" -gt "$DAILY_RETENTION" ]; then
                    # Between 7-30 days - keep only Sundays
                    if [ "$day_of_week" != "7" ]; then
                        log "Deleting backup $backup_date (not Sunday, between 7-30 days old)"
                        sudo "$AWS_CMD" s3 rm "$S3_BUCKET/$backup_date" --recursive \
                            --endpoint-url "$S3_ENDPOINT" 2>/dev/null || true
                    fi
                fi
            fi
        fi
    done < /tmp/backup_dates.txt
    
    # Clean up temp file
    rm -f /tmp/backup_dates.txt
    
    success "Retention policy implemented on iDrive"
}

# Main function
main() {
    log "=== Starting Streamlined Backup Process ==="
    
    # Create backup directories
    create_backup_dirs
    
    # Create database backup
    create_database_backup
    
    # Create file backups
    create_file_backups

    # Backup system configurations
    backup_system_configurations

    # Upload to iDrive
    upload_to_idrive
    
    # Cleanup local backups
    cleanup_local_backups
    
    # Implement retention policy
    implement_retention_policy
    
    success "Streamlined backup process completed successfully"
    log "=== Backup Process Finished ==="
}

# Run main function
main "$@"
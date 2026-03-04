#!/bin/bash
################################################################################
# Magento 2 Deployment Script
# Techno Stationery - Multi-Environment Deployment
#
# Usage:
#   ./deploy.sh dev module-name
#   ./deploy.sh beta module-name
#   ./deploy.sh production module-name
#
# Author: System Administrator
# Date: March 3, 2026
################################################################################

set -e  # Exit on error

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PRODUCTION_DIR="/home/technadminy7/public_html"
BETA_DIR="/home/beta/public_html"
DEV_DIR="/home/dev/public_html"

PRODUCTION_REDIS_DBS="0 1 2"
BETA_REDIS_DBS="0 1 2"
DEV_REDIS_DBS="5 6 7"

# Functions
function print_header() {
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}"
}

function print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

function print_error() {
    echo -e "${RED}✗ $1${NC}"
}

function print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

function print_info() {
    echo -e "${BLUE}ℹ $1${NC}"
}

function get_environment_dir() {
    case "$1" in
        production|prod)
            echo "$PRODUCTION_DIR"
            ;;
        beta|staging)
            echo "$BETA_DIR"
            ;;
        dev|development)
            echo "$DEV_DIR"
            ;;
        *)
            print_error "Invalid environment: $1"
            echo "Valid environments: production, beta, dev"
            exit 1
            ;;
    esac
}

function get_redis_dbs() {
    case "$1" in
        production|prod)
            echo "$PRODUCTION_REDIS_DBS"
            ;;
        beta|staging)
            echo "$BETA_REDIS_DBS"
            ;;
        dev|development)
            echo "$DEV_REDIS_DBS"
            ;;
    esac
}

function backup_database() {
    local env=$1
    local dir=$(get_environment_dir "$env")
    local db_name=""
    
    case "$env" in
        production|prod)
            db_name="technadminy7_dBT8x12y22"
            ;;
        beta|staging)
            db_name="beta_dBT8x12y22"
            ;;
        dev|development)
            db_name="dev_dBT8x12y22"
            ;;
    esac
    
    local backup_file="/tmp/${env}_db_backup_$(date +%Y%m%d_%H%M%S).sql"
    
    print_info "Backing up database: $db_name"
    /opt/mariadb10.6/mariadb/bin/mysqldump \
        -u root -p'YourNewStrongPassword' \
        -h 127.0.0.1 -P 3307 \
        --single-transaction --quick \
        "$db_name" > "$backup_file"
    
    print_success "Database backed up to: $backup_file"
    echo "$backup_file"
}

function clear_caches() {
    local env=$1
    local dir=$(get_environment_dir "$env")
    local redis_dbs=$(get_redis_dbs "$env")
    
    print_info "Clearing caches for $env environment"
    
    cd "$dir"
    
    # Clear file caches
    rm -rf var/cache/* var/page_cache/* var/view_preprocessed/* 2>/dev/null || true
    
    # Clear Redis
    for db in $redis_dbs; do
        redis-cli -n $db flushdb > /dev/null
        print_success "Cleared Redis DB $db"
    done
    
    # Flush Magento cache
    php bin/magento cache:flush > /dev/null
    
    print_success "All caches cleared"
}

function fix_permissions() {
    local env=$1
    local dir=$(get_environment_dir "$env")
    local user=""
    
    case "$env" in
        production|prod)
            user="technadminy7"
            ;;
        beta|staging)
            user="beta"
            ;;
        dev|development)
            user="dev"
            ;;
    esac
    
    print_info "Fixing permissions for $env environment"
    
    cd "$dir"
    chown -R "$user:$user" .
    chmod -R 777 pub/static/ var/ generated/ 2>/dev/null || true
    
    print_success "Permissions fixed"
}

function deploy_full_build() {
    local env=$1
    local dir=$(get_environment_dir "$env")
    
    print_header "Full Build Deployment: ${env^^}"
    
    cd "$dir"
    
    # Step 1: Clean
    print_info "Step 1/7: Cleaning old files"
    rm -rf var/cache/* var/page_cache/* var/view_preprocessed/* \
           var/log/* pub/static/frontend* generated/* 2>/dev/null || true
    print_success "Cleaned"
    
    # Step 2: Maintenance mode
    print_info "Step 2/7: Enabling maintenance mode"
    php bin/magento maintenance:enable
    print_success "Maintenance mode enabled"
    
    # Step 3: Setup upgrade
    print_info "Step 3/7: Running setup:upgrade"
    php bin/magento setup:upgrade
    print_success "Database upgraded"
    
    # Step 4: DI Compile
    print_info "Step 4/7: Compiling dependency injection"
    php bin/magento setup:di:compile
    print_success "DI compiled"
    
    # Step 5: Static content deploy
    print_info "Step 5/7: Deploying static content"
    php bin/magento setup:static-content:deploy fr_FR en_US ar_SA -f
    print_success "Static content deployed"
    
    # Step 6: Disable maintenance
    print_info "Step 6/7: Disabling maintenance mode"
    php bin/magento maintenance:disable
    print_success "Maintenance mode disabled"
    
    # Step 7: Clear caches
    print_info "Step 7/7: Flushing caches"
    php bin/magento cache:clean
    php bin/magento cache:flush
    clear_caches "$env"
    print_success "Caches flushed"
    
    # Fix permissions
    fix_permissions "$env"
    
    print_success "Full build deployment completed successfully!"
}

function enable_module() {
    local env=$1
    local module=$2
    local dir=$(get_environment_dir "$env")
    
    print_header "Enabling Module: $module in ${env^^}"
    
    if [ -z "$module" ]; then
        print_error "Module name is required"
        exit 1
    fi
    
    cd "$dir"
    
    # Backup database
    if [ "$env" = "production" ] || [ "$env" = "prod" ]; then
        backup_database "$env"
    fi
    
    # Enable module
    print_info "Enabling module: $module"
    php bin/magento module:enable "$module"
    print_success "Module enabled"
    
    # Run full build
    deploy_full_build "$env"
    
    print_success "Module $module enabled successfully!"
}

function disable_module() {
    local env=$1
    local module=$2
    local dir=$(get_environment_dir "$env")
    
    print_header "Disabling Module: $module in ${env^^}"
    
    if [ -z "$module" ]; then
        print_error "Module name is required"
        exit 1
    fi
    
    cd "$dir"
    
    # Disable module
    print_info "Disabling module: $module"
    php bin/magento module:disable "$module"
    print_success "Module disabled"
    
    # Run full build
    deploy_full_build "$env"
    
    print_success "Module $module disabled successfully!"
}

function list_modules() {
    local env=$1
    local dir=$(get_environment_dir "$env")
    
    print_header "Module Status: ${env^^}"
    
    cd "$dir"
    php bin/magento module:status
}

function sync_code() {
    local source_env=$1
    local target_env=$2
    local source_dir=$(get_environment_dir "$source_env")
    local target_dir=$(get_environment_dir "$target_env")
    
    print_header "Syncing Code: $source_env → $target_env"
    
    print_warning "This will copy app/code from $source_env to $target_env"
    read -p "Continue? (y/n) " -n 1 -r
    echo
    
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_info "Cancelled"
        exit 0
    fi
    
    # Sync app/code
    print_info "Syncing app/code directory"
    rsync -av --exclude='.git' "$source_dir/app/code/" "$target_dir/app/code/"
    print_success "Code synced"
    
    # Run build on target
    deploy_full_build "$target_env"
}

function check_health() {
    local env=$1
    local dir=$(get_environment_dir "$env")
    
    print_header "Health Check: ${env^^}"
    
    cd "$dir"
    
    # Magento version
    print_info "Magento Version:"
    php bin/magento --version
    
    echo ""
    
    # Database status
    print_info "Database Status:"
    php bin/magento setup:db:status
    
    echo ""
    
    # Cache status
    print_info "Cache Status:"
    php bin/magento cache:status
    
    echo ""
    
    # Redis connectivity
    print_info "Redis Connectivity:"
    redis-cli ping
    
    echo ""
    
    # Disk usage
    print_info "Disk Usage:"
    du -sh "$dir"
    
    print_success "Health check completed"
}

function show_usage() {
    echo "Usage: $0 <environment> <command> [options]"
    echo ""
    echo "Environments:"
    echo "  production, prod     Production environment"
    echo "  beta, staging        Beta/staging environment"
    echo "  dev, development     Development environment"
    echo ""
    echo "Commands:"
    echo "  build                Full build deployment"
    echo "  enable <module>      Enable a module"
    echo "  disable <module>     Disable a module"
    echo "  list                 List all modules"
    echo "  sync <target>        Sync code to another environment"
    echo "  clear                Clear all caches"
    echo "  permissions          Fix file permissions"
    echo "  health               Run health checks"
    echo "  backup               Backup database"
    echo ""
    echo "Examples:"
    echo "  $0 dev build"
    echo "  $0 beta enable Mab_CustomModule"
    echo "  $0 production disable Mab_TestModule"
    echo "  $0 dev sync beta"
    echo "  $0 production health"
}

# Main script
if [ $# -lt 2 ]; then
    show_usage
    exit 1
fi

ENVIRONMENT=$1
COMMAND=$2
OPTION=$3

case "$COMMAND" in
    build)
        deploy_full_build "$ENVIRONMENT"
        ;;
    enable)
        enable_module "$ENVIRONMENT" "$OPTION"
        ;;
    disable)
        disable_module "$ENVIRONMENT" "$OPTION"
        ;;
    list)
        list_modules "$ENVIRONMENT"
        ;;
    sync)
        sync_code "$ENVIRONMENT" "$OPTION"
        ;;
    clear)
        clear_caches "$ENVIRONMENT"
        ;;
    permissions)
        fix_permissions "$ENVIRONMENT"
        ;;
    health)
        check_health "$ENVIRONMENT"
        ;;
    backup)
        backup_database "$ENVIRONMENT"
        ;;
    *)
        print_error "Unknown command: $COMMAND"
        show_usage
        exit 1
        ;;
esac

print_success "Operation completed successfully!"
exit 0

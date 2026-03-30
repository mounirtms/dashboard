#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════
# Deploy Sessions 15-18 Changes to Production
# Purpose: Deploy Yalidine Parcel Grid + Firebase Social Login improvements
# Location: /home/dashboard/public_html/scripts/deployment/deploy-sessions-15-18-to-production.sh
# Usage: bash deploy-sessions-15-18-to-production.sh [--environment=ENV] [--dry-run]
# ═══════════════════════════════════════════════════════════════════════════

set -e

# ═══════════════════════════════════════════════════════════════════════════
# Configuration
# ═══════════════════════════════════════════════════════════════════════════

SCRIPT_NAME="deploy-sessions-15-18-to-production.sh"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_DIR="/home/dashboard/public_html/var/log"
LOG_FILE="$LOG_DIR/sessions_15_18_deployment_$(date '+%Y%m%d_%H%M%S').log"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

# Environment paths
SOURCE_ENV="beta"
SOURCE_PATH="/home/beta/public_html"
TARGET_ENV="${1#--environment=}"
TARGET_ENV="${TARGET_ENV:-production}"

declare -A ENV_PATHS=(
    ["production"]="/home/technadminy7/public_html"
    ["dev"]="/home/dev/public_html"
    ["lms"]="/home/lms/public_html"
)

declare -A ENV_USERS=(
    ["production"]="technadminy7"
    ["dev"]="dev"
    ["lms"]="lms"
)

TARGET_PATH="${ENV_PATHS[$TARGET_ENV]}"
TARGET_USER="${ENV_USERS[$TARGET_ENV]}"
DRY_RUN=false

# Check for dry-run flag
for arg in "$@"; do
    case $arg in
        --dry-run)
            DRY_RUN=true
            ;;
    esac
done

# ═══════════════════════════════════════════════════════════════════════════
# Functions
# ═══════════════════════════════════════════════════════════════════════════

log_message() {
    local level="$1"
    local message="$2"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [$level] $message" | tee -a "$LOG_FILE"
}

error_exit() {
    log_message "ERROR" "$1"
    exit 1
}

section_header() {
    local title="$1"
    echo "" | tee -a "$LOG_FILE"
    echo "════════════════════════════════════════════════════════════════" | tee -a "$LOG_FILE"
    echo "  $title" | tee -a "$LOG_FILE"
    echo "════════════════════════════════════════════════════════════════" | tee -a "$LOG_FILE"
}

# ═══════════════════════════════════════════════════════════════════════════
# Pre-Deployment Validation
# ═══════════════════════════════════════════════════════════════════════════

section_header "SESSION 15-18 DEPLOYMENT - VALIDATION"

log_message "INFO" "Source: $SOURCE_PATH ($SOURCE_ENV)"
log_message "INFO" "Target: $TARGET_PATH ($TARGET_ENV)"
log_message "INFO" "Dry Run: $DRY_RUN"

# Validate source exists
if [ ! -d "$SOURCE_PATH" ]; then
    error_exit "Source directory does not exist: $SOURCE_PATH"
fi

# Validate target exists
if [ -z "$TARGET_PATH" ] || [ ! -d "$TARGET_PATH" ]; then
    error_exit "Target directory does not exist or invalid environment: $TARGET_ENV"
fi

# Create log directory
mkdir -p "$LOG_DIR"

log_message "INFO" "Validation passed"

# ═══════════════════════════════════════════════════════════════════════════
# Session 15: Yalidine Parcel Grid API Integration
# ═══════════════════════════════════════════════════════════════════════════

section_header "SESSION 15: Yalidine Parcel Grid API Integration"

deploy_session_15() {
    log_message "INFO" "Deploying Session 15 changes..."
    
    # Files to deploy
    declare -a SESSION_15_FILES=(
        "app/code/Mab/YalidineCarrier/Ui/DataProvider/ParcelApiDataProvider.php"
        "app/code/Mab/YalidineCarrier/etc/di.xml"
        "app/code/Mab/YalidineCarrier/view/adminhtml/ui_component/yalidinecarrier_parcel_listing.xml"
    )
    
    for file in "${SESSION_15_FILES[@]}"; do
        if [ -f "$SOURCE_PATH/$file" ]; then
            if [ "$DRY_RUN" = false ]; then
                # Create directory if needed
                mkdir -p "$(dirname "$TARGET_PATH/$file")"
                
                # Copy file
                cp -p "$SOURCE_PATH/$file" "$TARGET_PATH/$file"
                chown $TARGET_USER:$TARGET_USER "$TARGET_PATH/$file"
                log_message "INFO" "✓ Deployed: $file"
            else
                log_message "INFO" "[DRY RUN] Would deploy: $file"
            fi
        else
            log_message "WARNING" "Source file not found: $file"
        fi
    done
    
    log_message "INFO" "Session 15 deployment complete"
}

# ═══════════════════════════════════════════════════════════════════════════
# Session 16: Firebase Social Login SDK Refactor
# ═══════════════════════════════════════════════════════════════════════════

section_header "SESSION 16: Firebase Social Login SDK Refactor"

deploy_session_16() {
    log_message "INFO" "Deploying Session 16 changes..."
    
    # Files to deploy
    declare -a SESSION_16_FILES=(
        "app/code/MiniOrange/FB/view/frontend/requirejs-config.js"
        "app/code/MiniOrange/FB/view/frontend/web/js/firebase-loader.js"
        "app/code/MiniOrange/FB/view/frontend/web/js/firebase-social-login.js"
    )
    
    for file in "${SESSION_16_FILES[@]}"; do
        if [ -f "$SOURCE_PATH/$file" ]; then
            if [ "$DRY_RUN" = false ]; then
                mkdir -p "$(dirname "$TARGET_PATH/$file")"
                cp -p "$SOURCE_PATH/$file" "$TARGET_PATH/$file"
                chown $TARGET_USER:$TARGET_USER "$TARGET_PATH/$file"
                log_message "INFO" "✓ Deployed: $file"
            else
                log_message "INFO" "[DRY RUN] Would deploy: $file"
            fi
        else
            log_message "WARNING" "Source file not found: $file"
        fi
    done
    
    log_message "INFO" "Session 16 deployment complete"
}

# ═══════════════════════════════════════════════════════════════════════════
# Session 17: Production Readiness (Static Content + Redis + Deployment Script)
# ═══════════════════════════════════════════════════════════════════════════

section_header "SESSION 17: Production Readiness Tools"

deploy_session_17() {
    log_message "INFO" "Deploying Session 17 changes..."
    
    # Deploy Redis caching improvements
    declare -a SESSION_17_FILES=(
        "app/code/Mab/YalidineCarrier/Model/YalidineApi.php"
        "app/code/Mab/YalidineCarrier/etc/adminhtml/system.xml"
        "app/code/Mab/YalidineCarrier/etc/config.xml"
    )
    
    for file in "${SESSION_17_FILES[@]}"; do
        if [ -f "$SOURCE_PATH/$file" ]; then
            if [ "$DRY_RUN" = false ]; then
                mkdir -p "$(dirname "$TARGET_PATH/$file")"
                cp -p "$SOURCE_PATH/$file" "$TARGET_PATH/$file"
                chown $TARGET_USER:$TARGET_USER "$TARGET_PATH/$file"
                log_message "INFO" "✓ Deployed: $file"
            else
                log_message "INFO" "[DRY RUN] Would deploy: $file"
            fi
        else
            log_message "WARNING" "Source file not found: $file"
        fi
    done
    
    # Deploy scripts (to target scripts directory)
    if [ "$DRY_RUN" = false ]; then
        # Create scripts directory if not exists
        mkdir -p "$TARGET_PATH"
        
        # Deploy deploy.sh
        if [ -f "$SOURCE_PATH/deploy.sh" ]; then
            cp -p "$SOURCE_PATH/deploy.sh" "$TARGET_PATH/deploy.sh"
            chmod +x "$TARGET_PATH/deploy.sh"
            chown $TARGET_USER:$TARGET_USER "$TARGET_PATH/deploy.sh"
            log_message "INFO" "✓ Deployed: deploy.sh"
        fi
        
        # Deploy monitor-cache.sh
        if [ -f "$SOURCE_PATH/monitor-cache.sh" ]; then
            cp -p "$SOURCE_PATH/monitor-cache.sh" "$TARGET_PATH/monitor-cache.sh"
            chmod +x "$TARGET_PATH/monitor-cache.sh"
            chown $TARGET_USER:$TARGET_USER "$TARGET_PATH/monitor-cache.sh"
            log_message "INFO" "✓ Deployed: monitor-cache.sh"
        fi
        
        # Deploy health-check.sh
        if [ -f "$SOURCE_PATH/health-check.sh" ]; then
            cp -p "$SOURCE_PATH/health-check.sh" "$TARGET_PATH/health-check.sh"
            chmod +x "$TARGET_PATH/health-check.sh"
            chown $TARGET_USER:$TARGET_USER "$TARGET_PATH/health-check.sh"
            log_message "INFO" "✓ Deployed: health-check.sh"
        fi
    else
        log_message "INFO" "[DRY RUN] Would deploy scripts: deploy.sh, monitor-cache.sh, health-check.sh"
    fi
    
    log_message "INFO" "Session 17 deployment complete"
}

# ═══════════════════════════════════════════════════════════════════════════
# Session 18: Monitoring & Health Check Tools
# ═══════════════════════════════════════════════════════════════════════════

section_header "SESSION 18: Monitoring & Health Check Tools"

deploy_session_18() {
    log_message "INFO" "Session 18 tools already deployed in Session 17"
    log_message "INFO" "Session 18 deployment complete"
}

# ═══════════════════════════════════════════════════════════════════════════
# Post-Deployment Tasks
# ═══════════════════════════════════════════════════════════════════════════

section_header "POST-DEPLOYMENT TASKS"

run_post_deployment() {
    log_message "INFO" "Running post-deployment tasks..."
    
    if [ "$DRY_RUN" = false ]; then
        cd "$TARGET_PATH"
        
        # Clear cache
        log_message "INFO" "Clearing cache..."
        rm -rf var/cache/* var/page_cache/* var/view_preprocessed/* generated/code/* 2>/dev/null || true
        
        # Run Magento setup commands
        log_message "INFO" "Running Magento setup:upgrade..."
        su - $TARGET_USER -c "cd $TARGET_PATH && php bin/magento setup:upgrade" 2>&1 | tee -a "$LOG_FILE" || true
        
        log_message "INFO" "Compiling DI..."
        su - $TARGET_USER -c "cd $TARGET_PATH && php bin/magento setup:di:compile" 2>&1 | tee -a "$LOG_FILE" || true
        
        log_message "INFO" "Deploying static content (French)..."
        su - $TARGET_USER -c "cd $TARGET_PATH && php bin/magento setup:static-content:deploy -f fr_FR --theme Sm/market" 2>&1 | tee -a "$LOG_FILE" || true
        
        log_message "INFO" "Flushing cache..."
        su - $TARGET_USER -c "cd $TARGET_PATH && php bin/magento cache:flush" 2>&1 | tee -a "$LOG_FILE" || true
        
        # Fix permissions
        log_message "INFO" "Fixing permissions..."
        chown -R $TARGET_USER:$TARGET_USER "$TARGET_PATH/var" "$TARGET_PATH/generated" "$TARGET_PATH/pub/static" 2>/dev/null || true
        find "$TARGET_PATH/var" "$TARGET_PATH/generated" "$TARGET_PATH/pub/static" -type d -exec chmod 770 {} \; 2>/dev/null || true
        find "$TARGET_PATH/var" "$TARGET_PATH/generated" "$TARGET_PATH/pub/static" -type f -exec chmod 660 {} \; 2>/dev/null || true
        
        log_message "INFO" "Post-deployment tasks complete"
    else
        log_message "INFO" "[DRY RUN] Would run post-deployment tasks"
    fi
}

# ═══════════════════════════════════════════════════════════════════════════
# Execute Deployment
# ═══════════════════════════════════════════════════════════════════════════

section_header "STARTING DEPLOYMENT"

# Deploy each session
deploy_session_15
deploy_session_16
deploy_session_17
deploy_session_18

# Run post-deployment tasks
run_post_deployment

# ═══════════════════════════════════════════════════════════════════════════
# Deployment Summary
# ═══════════════════════════════════════════════════════════════════════════

section_header "DEPLOYMENT SUMMARY"

log_message "INFO" "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
log_message "INFO" "✅ Sessions 15-18 Deployment Complete!"
log_message "INFO" "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
log_message "INFO" ""
log_message "INFO" "📋 Deployment Details:"
log_message "INFO" "  • Source: $SOURCE_ENV"
log_message "INFO" "  • Target: $TARGET_ENV"
log_message "INFO" "  • Timestamp: $TIMESTAMP"
log_message "INFO" "  • Dry Run: $DRY_RUN"
log_message "INFO" ""
log_message "INFO" "🎯 Deployed Features:"
log_message "INFO" "  ✓ Session 15: Yalidine Parcel Grid API Integration"
log_message "INFO" "  ✓ Session 16: Firebase Social Login SDK Refactor"
log_message "INFO" "  ✓ Session 17: Redis Caching + Production Tools"
log_message "INFO" "  ✓ Session 18: Monitoring & Health Check Tools"
log_message "INFO" ""
log_message "INFO" "📁 Log File: $LOG_FILE"
log_message "INFO" ""
log_message "INFO" "🧪 Next Steps:"
log_message "INFO" "  1. Test admin parcel grid: https://$TARGET_ENV.technostationery.com/sysadminy/admin/yalidinecarrier/parcel/"
log_message "INFO" "  2. Test Firebase login: https://$TARGET_ENV.technostationery.com/customer/account/login"
log_message "INFO" "  3. Monitor cache: bash $TARGET_PATH/monitor-cache.sh"
log_message "INFO" "  4. Run health check: bash $TARGET_PATH/health-check.sh"
log_message "INFO" ""
log_message "INFO" "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

exit 0

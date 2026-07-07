#!/bin/bash
# ============================================
# Multi-Environment Deployment Script
# Supports: dev, beta, prod, dashboard
# ============================================

set -e

# ── Configuration ──
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
LOG_DIR="/home/dashboard/public_html/logs/deployments"
BACKUP_DIR="/home/dashboard/public_html/backups"
REPO_DIR="/home/dashboard/public_html/webapp"

# Environment paths
declare -A ENV_PATHS=(
  ["dev"]="/home/dev/public_html"
  ["beta"]="/home/beta/public_html"
  ["prod"]="/home/technadminy7/public_html"
  ["dashboard"]="/home/dashboard/public_html"
)

declare -A ENV_USERS=(
  ["dev"]="dev"
  ["beta"]="beta"
  ["prod"]="technadminy7"
  ["dashboard"]="dashboard"
)

declare -A ENV_DOMAINS=(
  ["dev"]="dev.technostationery.com"
  ["beta"]="beta.technostationery.com"
  ["prod"]="technostationery.com"
  ["dashboard"]="dashboard.technostationery.com"
)

# ── Colors ──
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# ── Logging ──
log() { echo -e "${BLUE}[$(date +'%H:%M:%S')]${NC} $1"; }
success() { echo -e "${GREEN}✓${NC} $1"; }
warn() { echo -e "${YELLOW}⚠${NC} $1"; }
error() { echo -e "${RED}✗${NC} $1"; }

# ── Functions ──
usage() {
    echo "Usage: $0 <environment> [options]"
    echo ""
    echo "Environments: dev, beta, prod, dashboard"
    echo ""
    echo "Options:"
    echo "  --branch <branch>    Git branch to deploy (default: release)"
    echo "  --skip-backup        Skip backup (not recommended)"
    echo "  --skip-tests         Skip pre-deployment checks"
    echo "  --force              Force deploy without confirmation"
    echo "  --dry-run            Show what would be done without executing"
    echo "  --rollback           Rollback last deployment"
    echo ""
    echo "Examples:"
    echo "  $0 dev                          # Deploy to dev"
    echo "  $0 beta --branch main           # Deploy main to beta"
    echo "  $0 prod --force                 # Force deploy to production"
    echo "  $0 beta --rollback              # Rollback beta"
    exit 1
}

create_backup() {
    local env=$1
    local target_path=${ENV_PATHS[$env]}
    local user=${ENV_USERS[$env]}
    local backup_file="${BACKUP_DIR}/${env}_backup_${TIMESTAMP}.tar.gz"

    log "Creating backup of ${env}..."
    mkdir -p "$BACKUP_DIR"

    if tar -czf "$backup_file" -C "$(dirname "$target_path")" "$(basename "$target_path")" 2>/dev/null; then
        success "Backup created: $backup_file"
        echo "$backup_file"
        return 0
    else
        error "Backup failed!"
        return 1
    fi
}

run_preflight_checks() {
    local env=$1
    local branch=$2
    local target_path=${ENV_PATHS[$env]}
    local domain=${ENV_DOMAINS[$env]}

    log "Running pre-flight checks for ${env}..."

    # Check disk space
    local disk_pct=$(df "$target_path" 2>/dev/null | tail -1 | awk '{print $5}' | tr -d '%')
    if [ -n "$disk_pct" ] && [ "$disk_pct" -gt 90 ]; then
        error "Disk usage is ${disk_pct}% - must be below 90%"
        return 1
    fi
    success "Disk space OK (${disk_pct}% used)"

    # Check git branch exists
    cd "$REPO_DIR"
    if ! git ls-remote --heads origin "$branch" | grep -q "$branch"; then
        warn "Branch '$branch' not found on remote, checking locally..."
        if ! git branch --list "$branch" | grep -q "$branch"; then
            error "Branch '$branch' does not exist"
            return 1
        fi
    fi
    success "Branch '$branch' exists"

    # Check if target directory exists
    if [ ! -d "$target_path" ]; then
        error "Target directory does not exist: $target_path"
        return 1
    fi
    success "Target directory exists"

    # Check PHP syntax (if Magento)
    if [ -f "${target_path}/bin/magento" ]; then
        log "Checking Magento compilation..."
        # Quick syntax check on key files
        local php_errors=$(find "$REPO_DIR/backend" -name "*.js" -exec node --check {} \; 2>&1 | grep -c "error" || true)
        if [ "$php_errors" -gt 0 ]; then
            warn "Found $php_errors potential JS syntax issues"
        else
            success "Backend syntax check passed"
        fi
    fi

    # Check if site is reachable
    local http_code=$(curl -sS -o /dev/null -w "%{http_code}" "https://${domain}/" 2>/dev/null || echo "000")
    if [ "$http_code" != "000" ]; then
        success "Site reachable (HTTP ${http_code})"
    else
        warn "Site not reachable (HTTP ${http_code})"
    fi

    success "Pre-flight checks passed for ${env}"
    return 0
}

deploy_environment() {
    local env=$1
    local branch=$2
    local target_path=${ENV_PATHS[$env]}
    local user=${ENV_USERS[$env]}
    local domain=${ENV_DOMAINS[$env]}
    local log_file="${LOG_DIR}/deploy_${env}_${TIMESTAMP}.log"

    mkdir -p "$LOG_DIR"

    log "============================================"
    log "  DEPLOYMENT: ${env^^}"
    log "  Branch: ${branch}"
    log "  Target: ${target_path}"
    log "  Domain: ${domain}"
    log "============================================"

    # Build frontend
    log "Building frontend..."
    cd "$REPO_DIR"
    
    # Build Vite app
    if [ -f "package.json" ]; then
        if npm run build 2>&1 | tee -a "$log_file"; then
            success "Frontend build successful"
        else
            error "Frontend build failed!"
            return 1
        fi
    fi

    # Deploy files
    log "Deploying files to ${env}..."
    
    # Copy built assets to target
    if [ -d "dist" ]; then
        rsync -avz --delete dist/ "${target_path}/webapp/" 2>&1 | tee -a "$log_file"
        success "Files deployed to ${target_path}/webapp/"
    fi

    # Copy API files if deploying to dashboard
    if [ "$env" = "dashboard" ]; then
        rsync -avz api/ "${target_path}/api/" 2>&1 | tee -a "$log_file"
        success "API files deployed"
    fi

    # Set permissions
    log "Setting permissions..."
    chown -R "${user}:${user}" "${target_path}/" 2>/dev/null || true
    find "${target_path}/" -type d -exec chmod 755 {} \; 2>/dev/null || true
    find "${target_path}/" -type f -exec chmod 644 {} \; 2>/dev/null || true
    success "Permissions set"

    # Restart PHP-FPM
    log "Restarting PHP-FPM..."
    if systemctl restart ea-php82-php-fpm 2>&1 | tee -a "$log_file"; then
        success "PHP-FPM restarted"
    else
        warn "PHP-FPM restart failed (may still be running)"
    fi

    # Final health check
    sleep 2
    local http_code=$(curl -sS -o /dev/null -w "%{http_code}" "https://${domain}/" 2>/dev/null || echo "000")
    if [ "$http_code" = "200" ] || [ "$http_code" = "302" ]; then
        success "Deployment complete! ${domain} is responding (HTTP ${http_code})"
    else
        warn "Site responding with HTTP ${http_code} - verify manually"
    fi

    log "Deployment log: ${log_file}"
    return 0
}

rollback_environment() {
    local env=$1
    local target_path=${ENV_PATHS[$env]}

    # Find latest backup
    local latest_backup=$(ls -t "${BACKUP_DIR}/${env}_backup_"*.tar.gz 2>/dev/null | head -1)
    
    if [ -z "$latest_backup" ]; then
        error "No backup found for ${env}"
        return 1
    fi

    log "Rolling back ${env} using: ${latest_backup}"
    
    # Extract backup
    if tar -xzf "$latest_backup" -C "/" 2>&1; then
        success "Rollback complete for ${env}"
        return 0
    else
        error "Rollback failed!"
        return 1
    fi
}

# ── Main ──
ENV=""
BRANCH="release"
SKIP_BACKUP=false
SKIP_TESTS=false
FORCE=false
DRY_RUN=false
ROLLBACK=false

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        dev|beta|prod|dashboard)
            ENV=$1
            shift
            ;;
        --branch)
            BRANCH="$2"
            shift 2
            ;;
        --skip-backup)
            SKIP_BACKUP=true
            shift
            ;;
        --skip-tests)
            SKIP_TESTS=true
            shift
            ;;
        --force)
            FORCE=true
            shift
            ;;
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --rollback)
            ROLLBACK=true
            shift
            ;;
        -h|--help)
            usage
            ;;
        *)
            error "Unknown option: $1"
            usage
            ;;
    esac
done

# Validate environment
if [ -z "$ENV" ]; then
    error "Environment not specified"
    usage
fi

if [ -z "${ENV_PATHS[$ENV]+x}" ]; then
    error "Unknown environment: $ENV"
    usage
fi

# ── Execute ──
if [ "$ROLLBACK" = true ]; then
    rollback_environment "$ENV"
    exit $?
fi

# Confirmation for production
if [ "$ENV" = "prod" ] && [ "$FORCE" = false ] && [ "$DRY_RUN" = false ]; then
    warn "You are about to deploy to PRODUCTION!"
    echo "Domain: ${ENV_DOMAINS[$ENV]}"
    echo "Branch: $BRANCH"
    read -p "Type 'DEPLOY PROD' to confirm: " confirm
    if [ "$confirm" != "DEPLOY PROD" ]; then
        error "Deployment cancelled"
        exit 1
    fi
fi

if [ "$DRY_RUN" = true ]; then
    log "[DRY RUN] Would deploy branch '$BRANCH' to ${ENV} (${ENV_PATHS[$ENV]})"
    log "[DRY RUN] Would backup to ${BACKUP_DIR}/${ENV}_backup_${TIMESTAMP}.tar.gz"
    exit 0
fi

# Pre-flight checks
if [ "$SKIP_TESTS" = false ]; then
    if ! run_preflight_checks "$ENV" "$BRANCH"; then
        error "Pre-flight checks failed!"
        exit 1
    fi
fi

# Backup
if [ "$SKIP_BACKUP" = false ]; then
    if ! create_backup "$ENV"; then
        error "Backup failed! Aborting deployment."
        exit 1
    fi
fi

# Deploy
if deploy_environment "$ENV" "$BRANCH"; then
    success "🚀 Deployment to ${ENV^^} completed successfully!"
    exit 0
else
    error "Deployment failed! Check log for details."
    exit 1
fi

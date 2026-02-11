#!/bin/bash
# Quick Command Reference - Techno Magento Optimization
# Run: ./quick-commands.sh [command]

MAGENTO_ROOT="/home/technadminy7/public_html"
cd "$MAGENTO_ROOT" || exit 1

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_header() {
    echo -e "\n${BLUE}========================================${NC}"
    echo -e "${GREEN}$1${NC}"
    echo -e "${BLUE}========================================${NC}\n"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

# Main menu
if [ -z "$1" ]; then
    print_header "TECHNO MAGENTO - QUICK COMMANDS"
    echo "Usage: ./quick-commands.sh [command]"
    echo ""
    echo "Available commands:"
    echo ""
    echo "  ${GREEN}status${NC}           - System status overview"
    echo "  ${GREEN}health${NC}           - Quick health check"
    echo "  ${GREEN}audit${NC}            - Comprehensive performance audit"
    echo "  ${GREEN}optimize${NC}         - Run all optimizations"
    echo "  ${GREEN}cache${NC}            - Flush all caches"
    echo "  ${GREEN}reindex${NC}          - Reindex all indexers"
    echo "  ${GREEN}reindex-amasty${NC}   - Reindex Amasty only"
    echo "  ${GREEN}images${NC}           - Fix images and attributes"
    echo "  ${GREEN}cleanup${NC}          - Database cleanup"
    echo "  ${GREEN}verify${NC}           - Verify all optimizations"
    echo "  ${GREEN}amasty${NC}           - Amasty modules optimization"
    echo "  ${GREEN}cpu${NC}              - CPU and performance check"
    echo "  ${GREEN}logs${NC}             - View recent logs"
    echo "  ${GREEN}products${NC}         - Product statistics"
    echo "  ${GREEN}categories${NC}       - Category statistics"
    echo "  ${GREEN}help${NC}             - Show this help message"
    echo ""
    exit 0
fi

case "$1" in
    status)
        print_header "SYSTEM STATUS"
        
        # CPU
        CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | awk '{print $2+$4}')
        echo -e "CPU Usage: ${YELLOW}${CPU_USAGE}%${NC}"
        
        # Memory
        MEMORY=$(free -h | grep Mem | awk '{print $3 "/" $2}')
        echo -e "Memory: ${YELLOW}${MEMORY}${NC}"
        
        # Disk
        DISK=$(df -h /home/technadminy7 | tail -1 | awk '{print $3 "/" $2 " (" $5 " used)"}')
        echo -e "Disk: ${YELLOW}${DISK}${NC}"
        
        # PHP-FPM
        PHP_FPM=$(ps aux | grep php-fpm | grep -v grep | wc -l)
        echo -e "PHP-FPM Workers: ${YELLOW}${PHP_FPM}${NC}"
        
        # Indexers
        READY_INDEXERS=$(php bin/magento indexer:status | grep -c "Ready")
        echo -e "Ready Indexers: ${GREEN}${READY_INDEXERS}${NC}"
        
        # Cache
        ENABLED_CACHES=$(php bin/magento cache:status | grep -c "Enabled")
        echo -e "Enabled Caches: ${GREEN}${ENABLED_CACHES}${NC}"
        
        print_success "Status check complete"
        ;;
        
    health)
        print_header "HEALTH CHECK"
        
        # Indexers
        echo "Checking indexers..."
        INVALID=$(php bin/magento indexer:status | grep -c "Reindex required")
        if [ "$INVALID" -eq 0 ]; then
            print_success "All indexers are Ready"
        else
            print_warning "$INVALID indexers need reindexing"
        fi
        
        # Cache
        echo "Checking cache..."
        DISABLED=$(php bin/magento cache:status | grep -c "Disabled")
        if [ "$DISABLED" -eq 0 ]; then
            print_success "All caches are enabled"
        else
            print_warning "$DISABLED caches are disabled"
        fi
        
        # Disk space
        echo "Checking disk space..."
        DISK_PERCENT=$(df -h /home/technadminy7 | tail -1 | awk '{print $5}' | tr -d '%')
        if [ "$DISK_PERCENT" -lt 80 ]; then
            print_success "Disk space OK ($DISK_PERCENT%)"
        else
            print_warning "Disk space high ($DISK_PERCENT%)"
        fi
        
        print_success "Health check complete"
        ;;
        
    audit)
        print_header "COMPREHENSIVE AUDIT"
        php comprehensive_performance_audit.php
        ;;
        
    optimize)
        print_header "RUNNING ALL OPTIMIZATIONS"
        ./advanced_performance_tuning.sh
        ;;
        
    cache)
        print_header "FLUSHING CACHES"
        php bin/magento cache:flush
        print_success "All caches flushed"
        ;;
        
    reindex)
        print_header "REINDEXING ALL INDEXERS"
        php bin/magento indexer:reindex
        print_success "All indexers reindexed"
        ;;
        
    reindex-amasty)
        print_header "REINDEXING AMASTY INDEXERS"
        php bin/magento indexer:reindex $(php bin/magento indexer:status | grep amasty | awk '{print $1}' | tr '\n' ' ')
        print_success "Amasty indexers reindexed"
        ;;
        
    images)
        print_header "FIXING IMAGES & ATTRIBUTES"
        php fix_images_and_attributes.php
        ;;
        
    cleanup)
        print_header "DATABASE CLEANUP"
        ./database_cleanup.sh
        ;;
        
    verify)
        print_header "VERIFYING OPTIMIZATIONS"
        ./verify_optimizations.sh
        ;;
        
    amasty)
        print_header "AMASTY OPTIMIZATION"
        ./optimize_amasty_modules.sh
        ;;
        
    cpu)
        print_header "CPU & PERFORMANCE CHECK"
        ./optimize_cpu_and_images.sh
        ;;
        
    logs)
        print_header "RECENT LOGS (Last 20 lines)"
        echo -e "\n${YELLOW}System Log:${NC}"
        tail -20 var/log/system.log 2>/dev/null || echo "No system.log"
        echo -e "\n${YELLOW}Exception Log:${NC}"
        tail -20 var/log/exception.log 2>/dev/null || echo "No exception.log"
        ;;
        
    products)
        print_header "PRODUCT STATISTICS"
        /opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "
            SELECT 'Total Products' as Metric, COUNT(*) as Count FROM catalog_product_entity
            UNION ALL
            SELECT 'Enabled Products', COUNT(*)
            FROM catalog_product_entity cpe
            JOIN catalog_product_entity_int status ON cpe.entity_id = status.entity_id
            WHERE status.attribute_id = 97 AND status.value = 1 AND status.store_id = 0
            UNION ALL
            SELECT 'Simple Products', COUNT(*) FROM catalog_product_entity WHERE type_id = 'simple'
            UNION ALL
            SELECT 'Configurable Products', COUNT(*) FROM catalog_product_entity WHERE type_id = 'configurable';
        "
        ;;
        
    categories)
        print_header "CATEGORY STATISTICS"
        /opt/mariadb10.6/mariadb/bin/mysql -u root -p'YourNewStrongPassword' -h 127.0.0.1 -P 3307 technadminy7_dBT8x12y22 -e "
            SELECT 'Total Categories' as Metric, COUNT(*) as Count FROM catalog_category_entity
            UNION ALL
            SELECT 'Top Category (ID 2)', COUNT(*) FROM catalog_category_product WHERE category_id = 2
            UNION ALL
            SELECT 'Top Category (ID 3)', COUNT(*) FROM catalog_category_product WHERE category_id = 3;
        "
        ;;
        
    help|*)
        $0
        ;;
esac

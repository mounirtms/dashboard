#!/bin/bash
###############################################################################
# Sync Missing Orders to Sales Grid
# Purpose: Fix orders that exist in sales_order but missing from sales_order_grid
# Usage: ./sync_orders_to_grid.sh [--all] [--order-id XXX]
###############################################################################

set -e

# Configuration
DB_HOST="127.0.0.1"
DB_PORT="3307"
DB_USER="root"
DB_PASS="YourNewStrongPassword"
DB_NAME="technadminy7_dBT8x12y22"
MYSQL_CMD="/opt/mariadb10.6/mariadb/bin/mysql"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to sync specific order
sync_order() {
    local order_id=$1
    log_info "Syncing order ID: $order_id"
    
    $MYSQL_CMD -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT $DB_NAME -e "
        INSERT IGNORE INTO sales_order_grid 
        SELECT * FROM sales_order 
        WHERE entity_id = $order_id;
    "
    
    # Verify
    local count=$($MYSQL_CMD -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT $DB_NAME -N -e "
        SELECT COUNT(*) FROM sales_order_grid WHERE entity_id = $order_id;
    ")
    
    if [ "$count" -gt 0 ]; then
        log_info "✓ Order $order_id successfully synced to grid"
        return 0
    else
        log_error "✗ Failed to sync order $order_id"
        return 1
    fi
}

# Function to sync all missing orders
sync_all_missing() {
    log_info "Finding orders missing from grid..."
    
    # Get missing orders
    local missing_orders=$($MYSQL_CMD -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT $DB_NAME -N -e "
        SELECT so.entity_id, so.increment_id 
        FROM sales_order so 
        LEFT JOIN sales_order_grid sog ON so.entity_id = sog.entity_id 
        WHERE sog.entity_id IS NULL 
        ORDER BY so.entity_id DESC;
    ")
    
    if [ -z "$missing_orders" ]; then
        log_info "✓ No missing orders found. All orders are synced."
        return 0
    fi
    
    local count=0
    
    echo "$missing_orders" | while read line; do
        local entity_id=$(echo $line | awk '{print $1}')
        local increment_id=$(echo $line | awk '{print $2}')
        count=$((count + 1))
        
        log_info "[$count] Syncing order $increment_id (entity_id: $entity_id)"
        
        # Use proper column mapping since tables have different schemas
        $MYSQL_CMD -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT $DB_NAME -e "
            INSERT INTO sales_order_grid (
                entity_id, status, store_id, store_name, customer_id,
                base_grand_total, base_total_paid, grand_total, total_paid,
                increment_id, base_currency_code, order_currency_code,
                shipping_name, billing_name, created_at, updated_at,
                customer_email, customer_group, customer_name, subtotal
            )
            SELECT 
                so.entity_id, so.status, so.store_id, IFNULL(so.store_name, 'Main Store'), so.customer_id,
                so.base_grand_total, IFNULL(so.base_total_paid, 0), so.grand_total, IFNULL(so.total_paid, 0),
                so.increment_id, IFNULL(so.base_currency_code, 'DZD'), IFNULL(so.order_currency_code, 'DZD'),
                CONCAT(IFNULL(so.customer_firstname, ''), ' ', IFNULL(so.customer_lastname, '')),
                CONCAT(IFNULL(so.customer_firstname, ''), ' ', IFNULL(so.customer_lastname, '')),
                so.created_at, so.updated_at,
                so.customer_email, 'General',
                CONCAT(IFNULL(so.customer_firstname, ''), ' ', IFNULL(so.customer_lastname, '')),
                so.subtotal
            FROM sales_order so
            WHERE so.entity_id = $entity_id;
        "
        
        log_info "✓ Synced order $increment_id"
    done
    
    log_info "Sync complete. Processed: $count orders"
}

# Function to show statistics
show_stats() {
    log_info "=== Order Grid Statistics ==="
    
    local total_orders=$($MYSQL_CMD -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT $DB_NAME -N -e "
        SELECT COUNT(*) FROM sales_order;
    ")
    
    local grid_orders=$($MYSQL_CMD -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT $DB_NAME -N -e "
        SELECT COUNT(*) FROM sales_order_grid;
    ")
    
    local missing=$($MYSQL_CMD -u $DB_USER -p$DB_PASS -h $DB_HOST -P $DB_PORT $DB_NAME -N -e "
        SELECT COUNT(*) 
        FROM sales_order so 
        LEFT JOIN sales_order_grid sog ON so.entity_id = sog.entity_id 
        WHERE sog.entity_id IS NULL;
    ")
    
    echo "Total orders in sales_order: $total_orders"
    echo "Total orders in sales_order_grid: $grid_orders"
    echo "Missing orders: $missing"
    
    if [ "$missing" -gt 0 ]; then
        log_warn "There are $missing orders missing from the grid!"
    else
        log_info "✓ All orders are synced to the grid"
    fi
}

# Main
case "${1:-}" in
    --all)
        sync_all_missing
        ;;
    --order-id)
        if [ -z "${2:-}" ]; then
            log_error "Please provide an order ID"
            exit 1
        fi
        sync_order "$2"
        ;;
    --stats)
        show_stats
        ;;
    --fix-7312)
        log_info "Fixing order 7312 specifically..."
        # Order 7312 has entity_id 7185
        sync_order 7185
        ;;
    *)
        echo "Usage: $0 [--all|--order-id XXX|--stats|--fix-7312]"
        echo ""
        echo "Options:"
        echo "  --all        Sync all missing orders to grid"
        echo "  --order-id   Sync specific order by entity_id"
        echo "  --stats      Show order grid statistics"
        echo "  --fix-7312   Fix order 7312 specifically"
        echo ""
        show_stats
        ;;
esac

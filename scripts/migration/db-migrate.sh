#!/bin/bash

# Database Migration Script
# Migrates data from technadminy7_dBT8x12y22 to beta_dBT8x12y22
# Excludes core_config_data table and other specified tables

# Configuration
SOURCE_DB="technadminy7_dBT8x12y22"
TARGET_DB="beta_dBT8x12y22"
DB_HOST="127.0.0.1"
DB_PORT="3307"
DB_USER="technadminy7_ntdbusr24"
DB_PASSWORD="the-correct-password"

# Tables to exclude
EXCLUDE_TABLES=("core_config_data" "admin_user" "admin_passwords" "admin_system_messages" "adminnotification_inbox" "flag" "cron_schedule" "report_event" "report_compared_product_index" "report_viewed_product_index" "customer_visitor" "customer_log" "customer_visitor" "quote" "quote_address" "quote_address_item" "quote_item" "quote_item_option" "quote_payment" "quote_shipping_rate" "session" "oauth_token" "oauth_nonce" "oauth_consumer" "oauth_token_request_log" "password_reset_request_event" "paypal_payment_transaction" "paypal_settlement_report" "paypal_settlement_report_row" "review_detail" "review_entity_summary" "review_status_history" "sales_bestsellers_aggregated_daily" "sales_bestsellers_aggregated_monthly" "sales_bestsellers_aggregated_yearly" "sales_creditmemo" "sales_creditmemo_comment" "sales_creditmemo_grid" "sales_creditmemo_item" "sales_invoice" "sales_invoice_comment" "sales_invoice_grid" "sales_invoice_item" "sales_order" "sales_order_address" "sales_order_aggregated_created" "sales_order_aggregated_updated" "sales_order_grid" "sales_order_item" "sales_order_payment" "sales_order_status_history" "sales_order_tax" "sales_order_tax_item" "sales_payment_transaction" "sales_refunded_aggregated" "sales_refunded_aggregated_order" "sales_shipment" "sales_shipment_comment" "sales_shipment_grid" "sales_shipment_item" "sales_shipment_track" "sales_invoiced_aggregated" "sales_invoiced_aggregated_order" "sales_shipping_aggregated" "sales_shipping_aggregated_order" "sales_refunded_aggregated" "sales_refunded_aggregated_order" "search_query" "search_synonyms" "sendfriend_log" "tax_calculation" "tax_calculation_rate" "tax_calculation_rate_title" "tax_calculation_rule" "tax_class" "tax_order_aggregated_created" "tax_order_aggregated_updated" "weee_tax" "wishlist" "wishlist_item" "wishlist_item_option" "catalog_compare_item" "catalog_product_frontend_action" "catalogsearch_fulltext_scope0" "catalogsearch_fulltext_scope1" "catalogsearch_recommendations" "url_rewrite" "search_tmp_5e4b4a8c6d8a1" "catalogsearch_search_cl" "catalogsearch_fulltext_cl" "customer_visitor" "customer_log" "admin_user_session")

# Log file
LOG_FILE="/home/betapublic_html/scripts/migration/db-migration.log"

# Function to log messages
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a "$LOG_FILE"
}

# Function to check if table should be excluded
is_excluded_table() {
    local table=$1
    for excluded in "${EXCLUDE_TABLES[@]}"; do
        if [[ "$table" == "$excluded" ]]; then
            return 0
        fi
    done
    return 1
}

# Start migration
log_message "Starting database migration from $SOURCE_DB to $TARGET_DB"

# Get list of tables from source database
log_message "Fetching table list from source database..."
TABLES=$(/opt/mariadb10.6/mariadb/bin/mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASSWORD" -e "SHOW TABLES;" "$SOURCE_DB" 2>/dev/null | grep -v Tables_in)

if [ -z "$TABLES" ]; then
    log_message "ERROR: Could not fetch table list from source database"
    exit 1
fi

# Process each table
for table in $TABLES; do
    if is_excluded_table "$table"; then
        log_message "Skipping excluded table: $table"
        continue
    fi
    
    log_message "Migrating table: $table"
    
    # Export table structure and data
    /opt/mariadb10.6/mariadb/bin/mysqldump -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASSWORD" "$SOURCE_DB" "$table" | \
    /opt/mariadb10.6/mariadb/bin/mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASSWORD" "$TARGET_DB"
    
    if [ $? -eq 0 ]; then
        log_message "Successfully migrated table: $table"
    else
        log_message "ERROR: Failed to migrate table: $table"
    fi
done

log_message "Database migration completed"
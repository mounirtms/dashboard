#!/bin/bash

# Script to manually trigger order synchronization

echo "Running order synchronization check..."

# Run the monitoring script
/opt/cpanel/ea-php82/root/usr/bin/php /home/technadminy7/public_html/scripts/monitor_order_sync.php

echo "Order synchronization check completed."
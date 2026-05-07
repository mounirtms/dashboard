#!/bin/bash
# ==========================================
# Service Management Tool
# Purpose: Start, Stop, or Restart services
# Usage: ./service_manager.sh [service] [action]
# ==========================================

SERVICE=$1
ACTION=$2

if [[ -z "$SERVICE" || -z "$ACTION" ]]; then
    echo "Usage: ./service_manager.sh [service] [action]"
    echo "Actions: start, stop, restart, status"
    exit 1
fi

case $ACTION in
    start|stop|restart|status)
        systemctl $ACTION $SERVICE
        ;;
    *)
        echo "Invalid action: $ACTION"
        exit 1
        ;;
esac

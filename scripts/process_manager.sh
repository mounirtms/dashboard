#!/bin/bash
# ==========================================
# Process Management Tool
# Usage: ./process_manager.sh kill [user|name] [target]
# ==========================================

ACTION=$1
TYPE=$2
TARGET=$3

if [[ -z "$ACTION" || -z "$TYPE" || -z "$TARGET" ]]; then
    echo "Usage: ./process_manager.sh kill [user|name] [target]"
    exit 1
fi

if [[ "$ACTION" == "kill" ]]; then
    if [[ "$TYPE" == "user" ]]; then
        echo "Killing all processes for user: $TARGET"
        pkill -u "$TARGET"
    elif [[ "$TYPE" == "name" ]]; then
        echo "Killing all processes with name: $TARGET"
        pkill -f "$TARGET"
    else
        echo "Invalid type: $TYPE. Use 'user' or 'name'."
        exit 1
    fi
else
    echo "Invalid action: $ACTION"
    exit 1
fi

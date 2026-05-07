#!/bin/bash
# ==========================================
# Emergency Process Killer
# Purpose: Kill processes by name or user
# Usage: ./kill_process.sh [name|user] [target]
# ==========================================

TYPE=$1
TARGET=$2

if [[ -z "$TYPE" || -z "$TARGET" ]]; then
    echo "Usage: ./kill_process.sh [name|user] [target]"
    exit 1
fi

case $TYPE in
    name)
        echo "Attempting to kill processes matching: $TARGET"
        pkill -9 -f "$TARGET"
        ;;
    user)
        echo "Attempting to kill all processes for user: $TARGET"
        pkill -9 -u "$TARGET"
        ;;
    *)
        echo "Invalid type: $TYPE. Use 'name' or 'user'."
        exit 1
        ;;
esac

echo "Done."

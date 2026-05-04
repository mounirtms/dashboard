#!/bin/bash

echo "=========================================="
echo "SSH SESSION CLEANUP"
echo "=========================================="
echo "Started: $(date)"
echo ""

# Get current TTY to avoid killing our own session
CURRENT_TTY=$(tty)
CURRENT_PID=$$
echo "Current session: TTY=$CURRENT_TTY PID=$CURRENT_PID"
echo ""

# Count SSH sessions
TOTAL_SSH=$(ps aux | grep "sshd: " | grep -v grep | wc -l)
echo "Total SSH sessions found: $TOTAL_SSH"
echo ""

# Kill other SSH sessions (not current)
echo "Closing other SSH sessions..."
KILLED=0
ps aux | grep "sshd: " | grep -v grep | while read line; do
    PID=$(echo $line | awk '{print $2}')
    USER=$(echo $line | awk '{print $1}')
    TTY=$(echo $line | awk '{print $7}')
    
    # Don't kill current session or parent
    if [ "$PID" != "$CURRENT_PID" ] && [ "$TTY" != "$CURRENT_TTY" ] && [ "$TTY" != "?" ]; then
        echo "Terminating: PID=$PID User=$USER TTY=$TTY"
        kill -9 $PID 2>/dev/null
        ((KILLED++))
    fi
done

echo ""
echo "Sessions terminated: $KILLED"
echo "Completed: $(date)"
echo "=========================================="

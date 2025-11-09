#!/bin/bash
# Script to monitor and prevent lingma malware recurrence

# Check for the malicious process
MALICIOUS_PROCESS=$(ps aux | grep "/root/.lingma/bin" | grep -v grep)

if [ ! -z "$MALICIOUS_PROCESS" ]; then
    echo "[$(date)] Malicious lingma process detected:"
    echo "$MALICIOUS_PROCESS"
    
    # Extract PID and kill it
    PID=$(echo "$MALICIOUS_PROCESS" | awk '{print $2}')
    echo "[$(date)] Killing process PID: $PID"
    kill -9 $PID
    
    # Remove the malicious directory
    if [ -d "/root/.lingma" ]; then
        echo "[$(date)] Removing malicious directory: /root/.lingma"
        rm -rf /root/.lingma
    fi
    
    # Send alert email (if mail command is available)
    if command -v mail &> /dev/null; then
        echo "Malicious lingma process detected and terminated on $(hostname) at $(date)" | \
        mail -s "Security Alert: Malicious Process Terminated" root@localhost
    fi
else
    echo "[$(date)] No malicious lingma process detected"
fi
#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════
# Quick Status Check - Queue & CPU Health
# Run: bash /home/technadminy7/public_html/scripts/quick_status.sh
# ═══════════════════════════════════════════════════════════════════════════

MYSQL_BIN="/opt/mariadb10.6/mariadb/bin/mysql"
MYSQL_USER="root"
MYSQL_PASS="YourNewStrongPassword"
MYSQL_HOST="127.0.0.1"
MYSQL_PORT="3307"
DB_NAME="technadminy7_dBT8x12y22"

echo "╔════════════════════════════════════════════════════════════════════════════╗"
echo "║              PRODUCTION QUEUE & CPU STATUS CHECK                           ║"
echo "╚════════════════════════════════════════════════════════════════════════════╝"
echo ""

# Queue Status
echo "📊 QUEUE STATUS"
echo "─────────────────────────────────────────────────────────────────────────────"
QUEUE_COUNT=$($MYSQL_BIN -u "$MYSQL_USER" -p"$MYSQL_PASS" -h "$MYSQL_HOST" -P "$MYSQL_PORT" "$DB_NAME" -N -e "SELECT COUNT(*) FROM queue_message;" 2>/dev/null || echo "0")
STATUS_COUNT=$($MYSQL_BIN -u "$MYSQL_USER" -p"$MYSQL_PASS" -h "$MYSQL_HOST" -P "$MYSQL_PORT" "$DB_NAME" -N -e "SELECT COUNT(*) FROM queue_message_status;" 2>/dev/null || echo "0")

if [ "$QUEUE_COUNT" -eq 0 ]; then
    echo "  ✅ queue_message: $QUEUE_COUNT (OK)"
elif [ "$QUEUE_COUNT" -lt 1000 ]; then
    echo "  ⚠️  queue_message: $QUEUE_COUNT (WARNING)"
else
    echo "  🚨 queue_message: $QUEUE_COUNT (CRITICAL)"
fi

if [ "$STATUS_COUNT" -eq 0 ]; then
    echo "  ✅ queue_message_status: $STATUS_COUNT (OK)"
else
    echo "  ⚠️  queue_message_status: $STATUS_COUNT"
fi

echo ""
echo "📦 QUEUE BY TOPIC"
echo "─────────────────────────────────────────────────────────────────────────────"
$MYSQL_BIN -u "$MYSQL_USER" -p"$MYSQL_PASS" -h "$MYSQL_HOST" -P "$MYSQL_PORT" "$DB_NAME" -e "SELECT topic_name, COUNT(*) as count FROM queue_message GROUP BY topic_name ORDER BY count DESC LIMIT 5;" 2>/dev/null || echo "  No messages in queue"

echo ""
echo "🔄 CONSUMER PROCESSES"
echo "─────────────────────────────────────────────────────────────────────────────"
CONSUMER_COUNT=$(ps aux | grep -E "queue:consumers:start" | grep -v grep | wc -l)
echo "  Active consumers: $CONSUMER_COUNT"
ps aux | grep -E "queue:consumers:start" | grep -v grep | awk '{print "    PID: "$2" CPU: "$3"% MEM: "$4"%"}'

echo ""
echo "💻 CPU & MEMORY"
echo "─────────────────────────────────────────────────────────────────────────────"
CPU_LINE=$(top -bn1 | grep "Cpu(s)")
CPU_USER=$(echo "$CPU_LINE" | awk '{print $2}' | cut -d'%' -f1)
CPU_SYS=$(echo "$CPU_LINE" | awk '{print $4}' | cut -d'%' -f1)
CPU_IDLE=$(echo "$CPU_LINE" | awk '{print $8}' | cut -d'%' -f1)

MEM_INFO=$(free | grep Mem)
MEM_PERCENT=$(( $(echo $MEM_INFO | awk '{print $3}') * 100 / $(echo $MEM_INFO | awk '{print $2}') ))

LOAD=$(uptime | awk -F'load average:' '{print $2}' | awk -F',' '{print $1}' | tr -d ' ')

if (( $(echo "$CPU_USER + $CPU_SYS < 50" | bc -l 2>/dev/null || echo 0) )); then
    CPU_STATUS="✅"
elif (( $(echo "$CPU_USER + $CPU_SYS < 80" | bc -l 2>/dev/null || echo 0) )); then
    CPU_STATUS="⚠️"
else
    CPU_STATUS="🚨"
fi

echo "  $CPU_STATUS CPU: User ${CPU_USER}%, System ${CPU_SYS}%, Idle ${CPU_IDLE}%"
echo "  Memory: ${MEM_PERCENT}% used"
echo "  Load Average: $LOAD"

echo ""
echo "📈 TOP PROCESSES"
echo "─────────────────────────────────────────────────────────────────────────────"
ps aux --sort=-%cpu | head -4 | tail -3 | while read line; do
    PID=$(echo $line | awk '{print $2}')
    CPU=$(echo $line | awk '{print $3}')
    MEM=$(echo $line | awk '{print $4}')
    CMD=$(echo $line | awk '{for(i=11;i<=NF;i++) printf $i" "; print ""}' | cut -c1-50)
    echo "  PID $PID | CPU: ${CPU}% | MEM: ${MEM}% | $CMD"
done

echo ""
echo "📋 RECENT ALERTS"
echo "─────────────────────────────────────────────────────────────────────────────"
if [ -f /home/technadminy7/public_html/var/log/queue_alerts.log ]; then
    tail -5 /home/technadminy7/public_html/var/log/queue_alerts.log 2>/dev/null | while read line; do
        echo "  $line"
    done
else
    echo "  No alerts found"
fi

echo ""
echo "╔════════════════════════════════════════════════════════════════════════════╗"
echo "║  Quick Commands:                                                           ║"
echo "║  - Clear queue:    bash /home/technadminy7/public_html/scripts/queue_cleanup.sh        ║"
echo "║  - Monitor:        bash /home/technadminy7/public_html/scripts/monitoring/queue_monitor.sh   ║"
echo "║  - Watch consumers: bash /home/technadminy7/public_html/scripts/queue_consumer_watchdog.sh ║"
echo "║  - View logs:      tail -f /home/technadminy7/public_html/var/log/queue_monitor.log        ║"
echo "╚════════════════════════════════════════════════════════════════════════════╝"

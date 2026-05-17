#!/bin/bash
# ═══════════════════════════════════════════════════════════════════════════
# Email Alert System
# Purpose: Send email notifications for various events
# Location: /home/dashboard/public_html/scripts/notifications/email_alert.sh
# Usage: bash email_alert.sh --to=email --subject="Alert" --body="Message"
# ═══════════════════════════════════════════════════════════════════════════

set -e

# Configuration
SMTP_HOST="${SMTP_HOST:-localhost}"
SMTP_PORT="${SMTP_PORT:-25}"
SMTP_USER="${SMTP_USER:-}"
SMTP_PASS="${SMTP_PASS:-}"
FROM_EMAIL="${FROM_EMAIL:-alerts@technostationery.com}"
FROM_NAME="${FROM_NAME:-"Server Alerts"}"

# Log file
LOG_FILE="/home/dashboard/public_html/var/log/alerts.log"
mkdir -p "$(dirname "$LOG_FILE")"

log_alert() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

usage() {
    echo "Usage: $0 --to=EMAIL --subject=SUBJECT --body=BODY [OPTIONS]"
    echo ""
    echo "Required:"
    echo "  --to=EMAIL          Recipient email address"
    echo "  --subject=SUBJECT   Email subject"
    echo "  --body=BODY         Email body"
    echo ""
    echo "Optional:"
    echo "  --severity=LEVEL    Severity level (info, warning, critical, fatal)"
    echo "  --log-file=FILE     Attach log file"
    echo "  --html              Send as HTML email"
    echo "  --cc=EMAIL          CC recipient"
    echo "  --bcc=EMAIL         BCC recipient"
    exit 1
}

# Parse arguments
TO=""
SUBJECT=""
BODY=""
SEVERITY="info"
LOG_FILE_ATTACH=""
HTML=false
CC=""
BCC=""

while [[ $# -gt 0 ]]; do
    case $1 in
        --to=*)
            TO="${1#*=}"
            shift
            ;;
        --subject=*)
            SUBJECT="${1#*=}"
            shift
            ;;
        --body=*)
            BODY="${1#*=}"
            shift
            ;;
        --severity=*)
            SEVERITY="${1#*=}"
            shift
            ;;
        --log-file=*)
            LOG_FILE_ATTACH="${1#*=}"
            shift
            ;;
        --html)
            HTML=true
            shift
            ;;
        --cc=*)
            CC="${1#*=}"
            shift
            ;;
        --bcc=*)
            BCC="${1#*=}"
            shift
            ;;
        *)
            usage
            ;;
    esac
done

# Validate required arguments
if [ -z "$TO" ] || [ -z "$SUBJECT" ] || [ -z "$BODY" ]; then
    echo "Error: Missing required arguments"
    usage
fi

# Add severity prefix to subject
SUBJECT="[$(echo "$SEVERITY" | tr '[:lower:]' '[:upper:]')] $SUBJECT"

# Create email content
if [ "$HTML" = true ]; then
    # HTML email
    CONTENT_TYPE="text/html"
    EMAIL_BODY="<html><body>
<h2>Server Alert</h2>
<p><strong>Severity:</strong> $(echo "$SEVERITY" | tr '[:lower:]' '[:upper:]')</p>
<p><strong>Time:</strong> $(date '+%Y-%m-%d %H:%M:%S')</p>
<p><strong>Message:</strong></p>
<p>$BODY</p>
<hr>
<p><em>This is an automated alert from the Server Management System.</em></p>
</body></html>"
else
    # Plain text email
    CONTENT_TYPE="text/plain"
    EMAIL_BODY="Server Alert
============

Severity: $(echo "$SEVERITY" | tr '[:lower:]' '[:upper:]')
Time: $(date '+%Y-%m-%d %H:%M:%S')

Message:
$BODY

---
This is an automated alert from the Server Management System."
fi

# Send email safely without eval (prevents command injection)
log_alert "Sending email to $TO: $SUBJECT"

# Build mail arguments as an array (no eval needed)
MAIL_ARGS=()
MAIL_ARGS+=("-s" "$SUBJECT")
MAIL_ARGS+=("-a" "Content-Type: $CONTENT_TYPE; charset=UTF-8")
MAIL_ARGS+=("-a" "From: $FROM_NAME <$FROM_EMAIL>")

if [ -n "$CC" ]; then
    MAIL_ARGS+=("-c" "$CC")
fi

if [ -n "$BCC" ]; then
    MAIL_ARGS+=("-b" "$BCC")
fi

if [ -n "$LOG_FILE_ATTACH" ] && [ -f "$LOG_FILE_ATTACH" ]; then
    MAIL_ARGS+=("-a" "$LOG_FILE_ATTACH")
fi

MAIL_ARGS+=("$TO")

# Send email using array expansion (safe from injection)
if echo "$EMAIL_BODY" | mail "${MAIL_ARGS[@]}"; then
    log_alert "Email sent successfully to $TO"
    echo "Email sent successfully"
    exit 0
else
    log_alert "Failed to send email to $TO"
    echo "Failed to send email"
    exit 1
fi

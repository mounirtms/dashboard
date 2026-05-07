# Environment Manager - Troubleshooting Guide

## ❓ Common Issues

### ❌ Dashboard shows "Authentication Required"
- **Cause:** Your admin session has expired or your IP has changed.
- **Solution:** Refresh the page or log back into the Magento Admin panel.

### ❌ Action buttons are disabled or missing
- **Cause:** Lack of ACL permissions or trying to perform a restricted action on Production.
- **Solution:** Check your user role permissions. Note that `Suspend` and `Kill` are intentionally disabled for the Production environment for safety.

### ❌ AJAX Errors when clicking buttons
- **Cause:** Network timeout or CSRF token mismatch.
- **Solution:**
    1. Refresh the dashboard to generate a new CSRF token.
    2. Check `var/log/exception.log` for backend errors.
    3. Ensure the server's firewall isn't blocking the AJAX requests.

### ❌ Environment status is stuck on "Running..."
- **Cause:** A long-running background process (like a deployment) is still active.
- **Solution:** Wait for the operation to complete. You can check the progress in the `var/log/mab_deploy.log` file.

### ❌ Varnish Cache not clearing
- **Cause:** Varnish terminal (varnishadm) permission issue or incorrect host header.
- **Solution:** Verify that the `dashboard` user has permission to run `varnishadm ban`. Check `api/monitor.php` for specific Varnish errors.

## 📁 Critical Log Locations
- **Module Logs:** `var/log/mab_env_manager.log`
- **Magento Logs:** `var/log/system.log`, `var/log/exception.log`
- **Web Server Logs:** `/home/<user>/logs/<domain>.php.error.log`

## 📞 Escalation
If an issue persists:
1. Capture a screenshot of the error.
2. Download the last 100 lines of `var/log/exception.log`.
3. Contact the infrastructure team via the "Terminal AI" section in the main dashboard.

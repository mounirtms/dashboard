# Environment Manager - User Manual

## 📊 Dashboard Overview
The Environment Manager dashboard provides a "Cockpit" view of your entire server infrastructure. Each environment is represented by a card showing its current status and vital statistics.

## 🎴 Environment Cards
Each card displays:
- **Status Badge:**
    - 🔵 **Active:** Environment is running normally.
    - 🟡 **Suspended:** Environment is temporarily offline (suspended via cPanel/WHM).
    - ⚪ **Minimized:** Resources are reduced for cost/performance saving.
- **PHP-FPM Workers:** Number of active PHP processes for this environment.
- **Disk Usage:** Total space occupied by files and logs.
- **Database Size:** Current size of the environment's database.
- **Magento Mode:** Whether the site is in `production` or `developer` mode.

## ⚡ Available Operations

### 🚀 Deploy
- **Target:** Dev, Beta
- **Action:** Triggers the CI/CD pipeline to deploy the latest code to the selected environment.
- **Safety:** Deployment to Production is restricted to senior administrators and requires a manual confirmation.

### ⏸️ Suspend / Resume
- **Action:** Temporarily suspends or resumes the environment's web access.
- **Usage:** Used during maintenance windows or for inactive dev environments.
- **Note:** Production cannot be suspended through this interface.

### 📉 Minimize / Restore
- **Action:** Adjusts PHP-FPM and resource limits for the environment.
- **Usage:** Ideal for reducing the footprint of dev environments when not in active use.

### 🧹 Cleanup
- **Action:** Clears caches, restarts consumers, and cleans up temporary files.
- **Best Practice:** Run after minor configuration changes or if an environment feels sluggish.

## 📜 Viewing Logs
Click on the **"View Logs"** button on any environment card to see real-time log output, including:
- Magento System Logs
- PHP Error Logs
- Deployment Logs

## ⌨️ Console Commands
For power users, the following CLI commands are available:
- `bin/magento mab:env:status` - Show status of all environments.
- `bin/magento mab:env:manage <env_key> <action>` - Perform actions via CLI.

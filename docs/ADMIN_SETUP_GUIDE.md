# Environment Manager - Admin Setup Guide

## 🛠️ Overview
The MAB Environment Manager is a specialized Magento 2 module designed to manage and monitor multiple environments (Production, Beta, Dev, etc.) from a single dashboard.

## 📋 Prerequisites
- Magento 2.4.x
- PHP 8.1+
- MariaDB 10.4+
- `Mab_Core` module installed and enabled

## 🚀 Installation Steps

### 1. File Installation
Ensure the module files are located in:
`app/code/Mab/EnvironmentManager/`

### 2. Enable the Module
Run the following commands in your Magento root:
```bash
bin/magento module:enable Mab_EnvironmentManager
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy
```

### 3. Database Verification
Verify that the `mab_environments` and `mab_env_logs` tables have been created in your database.

### 4. Seeding Environments
The module includes a command to seed the initial environment data:
```bash
bin/magento mab:env:seed
```
This will populate the dashboard with Production, Beta, and Dev environment entries.

### 5. Create Admin User
If you need to create a dedicated admin user for the manager:
```bash
bin/magento mab:env:create-user <username> <email> <password>
```

## 🔐 Configuration
Access the configuration at:
**Stores > Configuration > MAB Extensions > Environment Manager**

Here you can configure:
- API endpoints for environment operations
- Security tokens
- Notification settings

## 🚦 Troubleshooting Setup
If the dashboard doesn't appear in the sidebar:
1. Flush Magento cache: `bin/magento cache:flush`
2. Check `var/log/system.log` for any DI errors.
3. Ensure your admin user has the correct ACL permissions (`Mab_EnvironmentManager::all`).

# Centralized Scripts Repository - Architecture

## Overview

This repository contains **shared/cross-environment scripts** that work across multiple domains.
**Environment-specific scripts remain in their local directories.**

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        Dashboard                                 │
│         /home/dashboard/public_html/scripts/                     │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │  SHARED SCRIPTS (Centralized)                            │   │
│  │  ├── optimization/cpu_optimize.sh                        │   │
│  │  ├── optimization/emergency_cpu_throttle.sh              │   │
│  │  ├── maintenance/queue_optimize.sh                       │   │
│  │  └── monitoring/{system,cpu,queue}_monitor.sh            │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
                              │
         ┌────────────────────┼────────────────────┐
         ▼                    ▼                    ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│  technadminy7   ││  pim (Akeneo)   ││   beta/dev/lms  │
│  (Magento 2)    ││                 ││                 │
│                 ││                 ││                 │
│ LOCAL SCRIPTS:  ││ LOCAL SCRIPTS:  ││ LOCAL SCRIPTS:  │
│ ├── master_     ││ ├── fix_akeneo  ││ ├── (various)   │
│ │   cleanup.sh  ││ └── (console    ││ └── ...         │
│ ├── smart_log_  ││     commands)   ││                 │
│ │   cleanup.sh  ││                 ││                 │
│ ├── nightly_    ││                 ││                 │
│ │   cache_flush ││                 ││                 │
│ └── monitoring/ ││                 ││                 │
│     └── ...     ││                 ││                 │
└─────────────────┘ └─────────────────┘ └─────────────────┘
```

---

## Script Locations

### Shared Scripts (Dashboard)
These scripts work across ALL environments:

| Script | Location | Purpose |
|--------|----------|---------|
| `cpu_optimize.sh` | `optimization/` | CPU optimization, cache cleanup |
| `emergency_cpu_throttle.sh` | `optimization/` | Emergency CPU reduction |
| `queue_optimize.sh` | `maintenance/` | Magento queue optimization |
| `system_monitor.sh` | `monitoring/` | Comprehensive monitoring |
| `cpu_monitor.sh` | `monitoring/` | CPU/memory monitoring |
| `queue_monitor.sh` | `monitoring/` | Queue monitoring |

### Environment-Specific Scripts (Local)
These scripts stay in their respective environments:

**technadminy7 (Magento 2)**
```
/home/technadminy7/public_html/scripts/
├── master_cleanup.sh
├── smart_log_cleanup.sh
├── nightly_cache_flush.sh
├── resource_audit.sh
├── performance_tuning.sh
├── sync_orders_to_grid.sh
└── monitoring/cron_health_check.sh
```

**pim (Akeneo PIM)**
```
/home/pim/public_html/
└── fix_akeneo.sh
```

**beta/dev/lms**
```
/home/{beta,dev,lms}/public_html/scripts/
└── (environment-specific scripts)
```

---

## Cron Configuration

### technadminy7 Crontab
```cron
# Magento cron (local)
*/10 * * * * /opt/cpanel/ea-php82/root/usr/bin/php /home/technadminy7/public_html/bin/magento cron:run

# Local scripts
0 2 * * * /home/technadminy7/public_html/scripts/master_cleanup.sh
0 3 * * * /home/technadminy7/public_html/scripts/smart_log_cleanup.sh
0 4 * * * /home/technadminy7/public_html/scripts/nightly_cache_flush.sh
0 6 * * * /home/technadminy7/public_html/scripts/resource_audit.sh
*/15 * * * * /home/technadminy7/public_html/scripts/monitoring/cron_health_check.sh
*/30 * * * * /home/technadminy7/public_html/scripts/maintenance/sync_orders_to_grid.sh
0 5 * * 0 /home/technadminy7/public_html/scripts/performance_tuning.sh

# Shared scripts (from dashboard)
15 3 * * * /bin/bash /home/dashboard/public_html/scripts/maintenance/queue_optimize.sh
*/10 * * * * /bin/bash /home/dashboard/public_html/scripts/optimization/cpu_optimize.sh
*/2 * * * * /bin/bash /home/dashboard/public_html/scripts/monitoring/system_monitor.sh
```

### pim Crontab (Akeneo)
```cron
# All Akeneo console commands (inline, no external scripts)
* * * * * cd /home/pim/public_html && php bin/console messenger:consume ...
*/15 * * * * cd /home/pim/public_html && php bin/console akeneo:batch:clean-job-executions ...
```

---

## Usage

### Running Scripts Manually

**Shared scripts (from dashboard):**
```bash
# CPU optimization
bash /home/dashboard/public_html/scripts/optimization/cpu_optimize.sh

# Emergency throttle
bash /home/dashboard/public_html/scripts/optimization/emergency_cpu_throttle.sh

# Queue optimization
bash /home/dashboard/public_html/scripts/maintenance/queue_optimize.sh

# System status
bash /home/dashboard/public_html/scripts/quick_status.sh
```

**Local scripts:**
```bash
# technadminy7
bash /home/technadminy7/public_html/scripts/master_cleanup.sh

# pim
bash /home/pim/public_html/fix_akeneo.sh
```

### Viewing Logs

```bash
# Shared script logs
tail -f /home/technadminy7/public_html/var/log/cpu_optimize.log
tail -f /home/technadminy7/public_html/var/log/system_monitor.log
tail -f /home/technadminy7/public_html/var/log/system_alerts.log

# Local script logs
tail -f /home/technadminy7/public_html/var/log/master_cleanup.log
tail -f /home/technadminy7/public_html/var/log/magento.cron.log
```

---

## Dashboard Integration (Planned)

A React-based web interface is planned to provide:
- One-click script execution
- Real-time system monitoring
- Cron job management
- Log viewer
- Alert notifications

**Documentation:** See `docs/DASHBOARD_INTERFACE_PLAN.md`

**Key Principle:** The dashboard is an **interface layer** that calls existing scripts. It does NOT replace or relocate them.

---

## Adding New Scripts

### Shared Scripts (go to dashboard)
If a script:
- Works across multiple environments
- Doesn't depend on environment-specific paths
- Provides general utility (monitoring, optimization)

Then add to: `/home/dashboard/public_html/scripts/{category}/`

### Local Scripts (stay in environment)
If a script:
- Depends on environment-specific paths
- Uses environment-specific database credentials
- Handles environment-specific tasks (Magento, Akeneo)

Then keep in: `/home/{user}/public_html/scripts/`

---

## Security

### Permissions
- Shared scripts: `dashboard:nobody` with 755
- Local scripts: `{user}:{group}` with 755

### Execution
- Scripts run as the environment owner
- Dashboard API uses sudo for cross-environment operations
- All executions are logged

---

## Statistics

| Category | Count |
|----------|-------|
| Shared scripts (dashboard) | 55+ |
| Local scripts (technadminy7) | 20+ |
| Local scripts (pim) | 5+ |
| Documentation files | 15+ |
| Cron jobs | 15+ |

---

## Quick Reference

```bash
# Check system status
bash /home/dashboard/public_html/scripts/quick_status.sh

# View all shared scripts
ls -la /home/dashboard/public_html/scripts/*/

# View technadminy7 local scripts
ls -la /home/technadminy7/public_html/scripts/

# Read dashboard plan
cat /home/dashboard/public_html/scripts/docs/DASHBOARD_INTERFACE_PLAN.md
```

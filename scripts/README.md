# Server Management Scripts

Centralized repository for all server management, monitoring, and maintenance scripts.

**Location**: `/home/dashboard/public_html/scripts/`

---

## Directory Structure

```
scripts/
├── monitoring/          # System monitoring scripts
│   ├── system_monitor.sh
│   ├── cpu_monitor.sh
│   ├── queue_monitor.sh
│   └── ...
├── backup/             # Backup scripts
├── deployment/         # Deployment scripts
├── migration/          # Database migration scripts
├── maintenance/        # Maintenance & cleanup scripts
│   ├── queue_optimize.sh
│   ├── master_cleanup.sh
│   ├── smart_log_cleanup.sh
│   └── ...
├── optimization/       # CPU/Performance optimization
│   ├── cpu_optimize.sh
│   ├── emergency_cpu_throttle.sh
│   └── ...
├── category/           # Category management scripts
├── product/            # Product management scripts
├── image/              # Image processing scripts
├── automation/         # Automated task scripts
└── docs/               # Documentation
    ├── DASHBOARD_IMPLEMENTATION_PLAN.md
    ├── CPU_OPTIMIZATION_GUIDE.md
    └── ...
```

---

## Quick Start

### Run Script Manually
```bash
# List available scripts
ls -la /home/dashboard/public_html/scripts/maintenance/

# Execute script
bash /home/dashboard/public_html/scripts/maintenance/queue_optimize.sh

# Check status
bash /home/dashboard/public_html/scripts/quick_status.sh
```

### View Logs
```bash
# System monitor logs
tail -f /home/technadminy7/public_html/var/log/system_monitor.log

# CPU optimization logs
tail -f /home/technadminy7/public_html/var/log/cpu_optimize.log

# Alert logs
tail -f /home/technadminy7/public_html/var/log/system_alerts.log
```

---

## Available Scripts by Category

### Monitoring
| Script | Purpose | Frequency |
|--------|---------|-----------|
| `system_monitor.sh` | Comprehensive system monitoring | Every 2 min |
| `cpu_monitor.sh` | CPU and memory monitoring | Every 5 min |
| `queue_monitor.sh` | Magento queue monitoring | Every 5 min |

### Optimization
| Script | Purpose | Frequency |
|--------|---------|-----------|
| `cpu_optimize.sh` | CPU optimization and cache cleanup | Every 10 min |
| `emergency_cpu_throttle.sh` | Emergency CPU reduction | Manual |
| `performance_tuning.sh` | Performance optimization | Weekly |

### Maintenance
| Script | Purpose | Frequency |
|--------|---------|-----------|
| `queue_optimize.sh` | Queue table optimization | Daily 3:15 AM |
| `master_cleanup.sh` | General cleanup | Daily 2 AM |
| `smart_log_cleanup.sh` | Log rotation | Daily 3 AM |
| `nightly_cache_flush.sh` | Cache cleanup | Daily 4 AM |
| `resource_audit.sh` | Resource audit | Daily 6 AM |

### Backup
| Script | Purpose | Frequency |
|--------|---------|-----------|
| *(backup scripts)* | Database and file backups | Configurable |

### Migration
| Script | Purpose | Frequency |
|--------|---------|-----------|
| *(migration scripts)* | Database migrations | Manual |

---

## Cron Configuration

All cron jobs reference scripts from this directory:

```cron
# System Monitor (Every 2 minutes)
*/2 * * * * /bin/bash /home/dashboard/public_html/scripts/monitoring/system_monitor.sh

# CPU Optimization (Every 10 minutes)
*/10 * * * * /bin/bash /home/dashboard/public_html/scripts/optimization/cpu_optimize.sh >> /home/technadminy7/public_html/var/log/cpu_optimize.log 2>&1

# Queue Optimization (Daily at 3:15 AM)
15 3 * * * /bin/bash /home/dashboard/public_html/scripts/maintenance/queue_optimize.sh

# Master Cleanup (Daily at 2 AM)
0 2 * * * /home/dashboard/public_html/scripts/maintenance/master_cleanup.sh >> /home/technadminy7/public_html/var/log/master_cleanup.log 2>&1

# Smart Log Cleanup (Daily at 3 AM)
0 3 * * * /home/dashboard/public_html/scripts/maintenance/smart_log_cleanup.sh >> /home/technadminy7/public_html/var/log/log_cleanup.log 2>&1

# Nightly Cache Flush (Daily at 4 AM)
0 4 * * * /home/dashboard/public_html/scripts/maintenance/nightly_cache_flush.sh >> /home/technadminy7/public_html/var/log/cache_flush.log 2>&1

# Resource Audit (Daily at 6 AM)
0 6 * * * /home/dashboard/public_html/scripts/maintenance/resource_audit.sh >> /home/technadminy7/public_html/var/log/resource_audit.log 2>&1

# Performance Tuning (Weekly on Sunday at 5 AM)
0 5 * * 0 /home/dashboard/public_html/scripts/optimization/performance_tuning.sh >> /home/technadminy7/public_html/var/log/performance_tuning.log 2>&1
```

---

## Emergency Commands

### High CPU (>90%)
```bash
# Emergency CPU throttle
bash /home/dashboard/public_html/scripts/optimization/emergency_cpu_throttle.sh
```

### Queue Backup (>5000 messages)
```bash
# Clear queue immediately
bash /home/dashboard/public_html/scripts/maintenance/queue_optimize.sh
```

### Check System Status
```bash
# Quick status check
bash /home/dashboard/public_html/scripts/quick_status.sh
```

---

## Dashboard Integration (Planned)

A React-based dashboard is planned to provide:
- Web UI for script execution
- Real-time system monitoring
- Scheduled task management
- Alert notifications
- Log viewer
- Database management

See: `docs/DASHBOARD_IMPLEMENTATION_PLAN.md`

---

## Security

### Permissions
- All scripts owned by `dashboard:nobody`
- Execute permission: 755
- Sensitive scripts require admin role

### Execution
- Scripts run as the domain owner user
- All executions are logged
- Timeout limits prevent runaway processes

---

## Adding New Scripts

1. Place script in appropriate category directory
2. Make executable: `chmod +x script.sh`
3. Set ownership: `chown dashboard:nobody script.sh`
4. Update this README
5. Add to dashboard API whitelist (when implemented)

---

## Support

For issues or questions:
- Check logs in `/home/*/public_html/var/log/`
- Run `quick_status.sh` for system overview
- Review `docs/` for detailed guides

---

## Statistics

- **Total Scripts**: 55+
- **Documentation Files**: 12+
- **Domains Managed**: 5 (technostationery, pim, beta, dev, lms)
- **Automated Tasks**: 10+ cron jobs

# Main Dashboard Tuning Plan
**Date:** 2026-05-07  
**Focus:** Optimize and enhance the main dashboard monitoring system

---

## Current Infrastructure Monitoring Features ✅

Already Implemented:
- ✅ Varnish cache monitoring with real-time stats
- ✅ Cache hit rate gauge (color-coded)
- ✅ Backend server health monitoring
- ✅ Cache purge and warmup actions
- ✅ Live log viewer (50 lines)
- ✅ Auto-refresh every 10 seconds
- ✅ System architecture diagram

---

## Proposed Dashboard Enhancements

### 1. System Resource Monitoring
**Priority:** High  
**Features:**
- CPU load graph (real-time)
- Memory usage gauge
- Disk usage per mount point
- Network traffic (in/out)
- Process list with resource usage
- Service status indicators (Apache, Varnish, MySQL, Redis, Elasticsearch)

### 2. Website Health Dashboard
**Priority:** High  
**Features:**
- All 6 sites status (technostationery.com, beta, dev, lms, dashboard, pim)
- Response time monitoring
- SSL certificate expiry dates
- Uptime percentage
- Recent errors/warnings
- Quick actions (restart services, clear cache)

### 3. Performance Metrics
**Priority:** Medium  
**Features:**
- Page load time trends
- Database query performance
- API response times
- Cache effectiveness metrics
- Slow query log viewer

### 4. Apache & PHP-FPM Monitoring
**Priority:** Medium  
**Features:**
- Apache worker status
- Active connections
- Requests per second
- PHP-FPM pool status
- FPM worker utilization
- OPcache statistics

### 5. Database Monitoring
**Priority:** Medium  
**Features:**
- MySQL connection pool
- Active queries
- Slow query log
- Table sizes
- Index usage
- Replication status (if applicable)

### 6. Alerts & Notifications
**Priority:** Low  
**Features:**
- Threshold-based alerts
- Email/Telegram notifications
- Alert history
- Custom alert rules

### 7. Log Aggregation
**Priority:** Medium  
**Features:**
- Combined log viewer (Apache, PHP, MySQL, Varnish)
- Log filtering and search
- Error pattern detection
- Real-time log streaming

### 8. Quick Actions Panel
**Priority:** High  
**Features:**
- Restart services (Apache, Varnish, PHP-FPM)
- Clear all caches (Varnish, Redis, Magento)
- Run warm-up scripts
- Flush DNS
- Trigger maintenance mode
- Deploy code updates

---

## Implementation Priority

### Phase 1 (Immediate - High Priority)
1. System resource monitoring (CPU, Memory, Disk)
2. Website health dashboard (all 6 sites)
3. Quick actions panel

### Phase 2 (Short-term - Medium Priority)
4. Apache & PHP-FPM monitoring
5. Performance metrics
6. Log aggregation

### Phase 3 (Long-term - Low Priority)
7. Database monitoring
8. Alerts & notifications

---

## Technical Stack

### Backend APIs
- System metrics: `/api/system.php`
- Website health: `/api/sites.php`
- Apache status: `/api/apache.php`
- PHP-FPM status: `/api/php-fpm.php`
- Database: `/api/database.php`
- Logs: `/api/logs.php`
- Actions: `/api/actions.php`

### Frontend Components
- `SystemMonitoring.jsx` - Resource monitoring
- `WebsiteHealth.jsx` - Site status dashboard
- `ApacheMonitoring.jsx` - Apache metrics
- `PhpFpmMonitoring.jsx` - PHP-FPM metrics
- `DatabaseMonitoring.jsx` - Database metrics
- `LogViewer.jsx` - Log aggregation
- `QuickActions.jsx` - Action buttons

### Dashboard Layout
```
┌─────────────────────────────────────────────────────────────┐
│  Main Dashboard - Real-time Infrastructure Monitoring       │
├─────────────────────────────────────────────────────────────┤
│  System Resources        │  Website Health                  │
│  ┌─────────────────┐     │  ┌────────────────────────┐     │
│  │ CPU: 0.52       │     │  │ ✅ technostationery.com │     │
│  │ Memory: 55%     │     │  │ ✅ beta                 │     │
│  │ Disk: 44%       │     │  │ ✅ dev                  │     │
│  └─────────────────┘     │  │ ✅ lms                  │     │
│                          │  │ ✅ dashboard            │     │
│  Varnish Cache          │  │ ⚠️  pim (redirect)      │     │
│  ┌─────────────────┐     │  └────────────────────────┘     │
│  │ Hit Rate: 0%    │     │                                  │
│  │ Requests: 1     │     │  Quick Actions                   │
│  │ Hits: 0         │     │  [Restart Apache] [Clear Cache]  │
│  └─────────────────┘     │  [Warm Up] [Maintenance Mode]    │
├─────────────────────────────────────────────────────────────┤
│  Apache Status          │  PHP-FPM Status                  │
│  ┌─────────────────┐     │  ┌────────────────────────┐     │
│  │ Workers: 10/25  │     │  │ Active: 5/20           │     │
│  │ Requests/s: 15  │     │  │ Queue: 0               │     │
│  └─────────────────┘     │  └────────────────────────┘     │
├─────────────────────────────────────────────────────────────┤
│  Recent Logs                                                │
│  [2026-05-07 05:55:00] Apache: OK                          │
│  [2026-05-07 05:54:45] Varnish: Cache miss                 │
│  [2026-05-07 05:54:30] PHP-FPM: Pool www ready             │
└─────────────────────────────────────────────────────────────┘
```

---

## Next Steps

1. Create system metrics API endpoint
2. Create website health API endpoint
3. Build SystemMonitoring React component
4. Build WebsiteHealth React component
5. Build QuickActions React component
6. Integrate into main dashboard page
7. Test and refine

---

**Status:** Ready to implement Phase 1  
**Estimated Time:** 30-45 minutes

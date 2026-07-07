# Dashboard Server Management Interface

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        REACT DASHBOARD (Web UI)                              │
│                    https://dashboard.technostationery.com                    │
│                                                                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐    │
│  │   Overview   │  │   Scripts    │  │  Monitoring  │  │  Databases   │    │
│  │   Dashboard  │  │   Runner     │  │   Charts     │  │   Manager    │    │
│  └──────────────┘  └──────────────┘  └──────────────┘  └──────────────┘    │
│                                                                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐    │
│  │    Backup    │  │    Cron      │  │    Logs      │  │   Settings   │    │
│  │   Manager    │  │  Scheduler   │  │   Viewer     │  │              │    │
│  └──────────────┘  └──────────────┘  └──────────────┘  └──────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         NODE.JS API LAYER                                    │
│                    /home/dashboard/public_html/webapp/backend                │
│                                                                              │
│  ┌────────────────┐  ┌────────────────┐  ┌────────────────┐                │
│  │   /api/scripts │  │ /api/monitoring│  │  /api/database │                │
│  │   /api/cron    │  │   /api/alerts  │  │   /api/backup  │                │
│  └────────────────┘  └────────────────┘  └────────────────┘                │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                    ┌───────────────┼───────────────┐
                    ▼               ▼               ▼
┌──────────────────────┐ ┌──────────────────┐ ┌──────────────────────┐
│  Environment Scripts │ │  Shared Scripts  │ │   Database Access    │
│  (Local to each env) │ │   (Dashboard)    │ │                      │
│                      │ │                  │ │  ┌────────────────┐  │
│ /home/technadminy7/  │ │ /home/dashboard/ │ │  │ technadminy7_  │  │
│   public_html/       │ │   public_html/   │ │  │     DB         │  │
│   scripts/           │ │   scripts/       │ │  ├────────────────┤  │
│   ├── master_cleanup │ │   ├── cpu_       │ │  │ akeneo_pim     │  │
│   ├── smart_log_     │ │   │   optimize   │ │  │     DB         │  │
│   ├── nightly_       │ │   ├── system_    │ │  ├────────────────┤  │
│   │   cache_flush    │ │   │   monitor    │ │  │ beta_db        │  │
│   └── performance_   │ │   ├── queue_     │ │  │                │  │
│       tuning         │ │   │   optimize   │ │  └────────────────┘  │
│                      │ │   └── emergency_ │                      │
│ /home/pim/           │ │       throttle   │                      │
│   public_html/       │ │                  │                      │
│   (Akeneo scripts)   │ │                  │                      │
└──────────────────────┘ └──────────────────┘ └──────────────────────┘
```

---

## Script Categories

### 1. Environment-Specific Scripts (Keep Local)
These scripts are tied to specific environments and should NOT be moved:

**technadminy7 (Magento 2)**
```
/home/technadminy7/public_html/scripts/
├── master_cleanup.sh          # Magento-specific cleanup
├── smart_log_cleanup.sh       # Magento log rotation
├── nightly_cache_flush.sh     # Magento cache flush
├── resource_audit.sh          # Magento resource check
├── performance_tuning.sh      # Magento optimization
├── sync_orders_to_grid.sh     # Magento order sync
└── monitoring/
    └── cron_health_check.sh   # Magento cron monitor
```

**pim (Akeneo PIM)**
```
/home/pim/public_html/
├── fix_akeneo.sh              # PIM-specific fix
└── (Akeneo console commands in crontab)
```

**beta/dev/lms**
```
# Each has their own environment-specific scripts
```

### 2. Shared Scripts (Centralized in Dashboard)
These scripts work across all environments:

```
/home/dashboard/public_html/scripts/
├── optimization/
│   ├── cpu_optimize.sh        # Universal CPU optimization
│   └── emergency_cpu_throttle.sh  # Emergency response
├── maintenance/
│   └── queue_optimize.sh      # Magento queue optimization
├── monitoring/
│   ├── system_monitor.sh      # Universal system monitor
│   ├── cpu_monitor.sh         # CPU monitoring
│   └── queue_monitor.sh       # Queue monitoring
└── docs/
    └── (documentation)
```

---

## Dashboard Features

### Feature 1: Script Runner with One-Click Execution

```jsx
// src/pages/ScriptRunner.jsx
function ScriptRunner() {
  const [scripts, setScripts] = useState({
    environment: [
      { 
        id: 'master_cleanup', 
        name: 'Master Cleanup', 
        path: '/home/technadminy7/public_html/scripts/master_cleanup.sh',
        environment: 'technadminy7',
        schedule: '0 2 * * *',
        lastRun: '2026-03-29 02:00:00',
        status: 'success'
      },
      // ... more scripts
    ],
    shared: [
      {
        id: 'cpu_optimize',
        name: 'CPU Optimization',
        path: '/home/dashboard/public_html/scripts/optimization/cpu_optimize.sh',
        schedule: '*/10 * * * *',
        lastRun: '2026-03-29 16:50:00',
        status: 'success'
      },
      // ... more scripts
    ]
  });

  const executeScript = async (scriptId) => {
    // Call API to execute script
    const response = await api.post('/scripts/execute', { scriptId });
    // Show real-time output
  };

  return (
    <div className="script-runner">
      <ScriptGrid scripts={scripts.environment} title="Environment Scripts" />
      <ScriptGrid scripts={scripts.shared} title="Shared Scripts" />
      <ExecutionLog />
    </div>
  );
}
```

**UI Components:**
- Script cards with status indicators (green=success, red=failed, yellow=running)
- One-click "Run Now" button for each script
- Schedule display (cron expression in human-readable format)
- Last execution time and duration
- Real-time output console

---

### Feature 2: System Monitoring Dashboard

```jsx
// src/pages/Monitoring.jsx
function Monitoring() {
  const { cpu, memory, disk, queue, services } = useSystemStats();

  return (
    <Dashboard>
      <Grid container spacing={3}>
        {/* CPU Card */}
        <CPUCard 
          value={cpu.percent} 
          history={cpu.history}
          alert={cpu.percent > 80}
          onOptimize={() => executeScript('cpu_optimize')}
        />
        
        {/* Memory Card */}
        <MemoryCard 
          used={memory.used} 
          total={memory.total}
          alert={memory.percent > 85}
        />
        
        {/* Queue Status Card */}
        <QueueCard
          count={queue.messages}
          alert={queue.messages > 1000}
          onClear={() => executeScript('queue_optimize')}
        />
        
        {/* Services Status */}
        <ServicesCard services={services} />
      </Grid>
    </Dashboard>
  );
}
```

**Real-time Metrics:**
- CPU usage (line chart, 5-second updates)
- Memory usage (gauge + history)
- Disk usage (per-mountpoint)
- Magento queue size
- Service health (MariaDB, Elasticsearch, Redis, PHP-FPM)

**Quick Actions:**
- "Optimize CPU" button → triggers `cpu_optimize.sh`
- "Clear Queue" button → triggers `queue_optimize.sh`
- "Emergency Throttle" button → triggers `emergency_cpu_throttle.sh`

---

### Feature 3: Cron Job Manager

```jsx
// src/pages/CronManager.jsx
function CronManager() {
  const [jobs, setJobs] = useState([
    {
      id: 1,
      command: '/home/technadminy7/public_html/scripts/master_cleanup.sh',
      schedule: '0 2 * * *',
      readable: 'Daily at 2:00 AM',
      environment: 'technadminy7',
      lastRun: '2026-03-29 02:00:00',
      nextRun: '2026-03-30 02:00:00',
      status: 'active'
    },
    // ... more jobs
  ]);

  return (
    <CronManager>
      <CronTable jobs={jobs} />
      <CronEditor onSave={handleSave} />
      <ExecutionHistory />
    </CronManager>
  );
}
```

**Features:**
- List all cron jobs per environment
- Visual cron editor (GUI for building cron expressions)
- Enable/disable toggle
- Run now button
- View execution history
- Edit schedule

---

### Feature 4: Log Viewer

```jsx
// src/pages/LogViewer.jsx
function LogViewer() {
  const [logs, setLogs] = useState([]);
  const [selectedLog, setSelectedLog] = useState('system_monitor.log');

  const logFiles = [
    'system_monitor.log',
    'cpu_optimize.log',
    'queue_monitor.log',
    'master_cleanup.log',
    'magento.cron.log'
  ];

  return (
    <LogViewer>
      <LogSelector logs={logFiles} selected={selectedLog} onChange={setSelectedLog} />
      <LogContent file={selectedLog} realtime={true} />
      <LogFilters />
    </LogViewer>
  );
}
```

**Features:**
- Select log file from dropdown
- Real-time tail (-f equivalent)
- Search/filter logs
- Download log file
- Clear old logs button

---

### Feature 5: Alert Center

```jsx
// src/pages/Alerts.jsx
function Alerts() {
  const [alerts, setAlerts] = useState([
    {
      id: 1,
      severity: 'critical',
      title: 'CPU Usage Critical',
      message: 'CPU usage at 92% (threshold: 80%)',
      timestamp: '2026-03-29 16:45:00',
      acknowledged: false,
      autoAction: 'cpu_optimize.sh executed'
    },
    // ... more alerts
  ]);

  return (
    <AlertCenter>
      <AlertList alerts={alerts} />
      <AlertSettings />
    </AlertCenter>
  );
}
```

**Alert Types:**
- CPU > 80% (warning), > 90% (critical)
- Memory > 85%
- Queue > 1000 (warning), > 5000 (critical)
- Disk > 90%
- Service down

**Actions:**
- Auto-execute remediation scripts
- Send notifications (email, Slack)
- Acknowledge alerts
- Configure thresholds

---

## API Endpoints

### Scripts API
```javascript
// GET /api/scripts/list
// Returns all available scripts
{
  "environment": [
    {
      "id": "master_cleanup",
      "name": "Master Cleanup",
      "path": "/home/technadminy7/public_html/scripts/master_cleanup.sh",
      "environment": "technadminy7",
      "schedule": "0 2 * * *",
      "lastRun": "2026-03-29 02:00:00",
      "status": "success",
      "duration": 45
    }
  ],
  "shared": [...]
}

// POST /api/scripts/execute
// Execute a script
{
  "scriptId": "cpu_optimize",
  "environment": "technadminy7"
}

// Response (WebSocket stream)
{
  "executionId": "exec_123",
  "output": "[2026-03-29 16:50:00] Starting CPU optimization...",
  "status": "running"
}

// GET /api/scripts/:id/logs
// Get execution history
```

### Monitoring API
```javascript
// GET /api/monitoring/stats
{
  "cpu": { "percent": 45.2, "history": [...] },
  "memory": { "used": 24000, "total": 32000, "percent": 75 },
  "disk": [{ "mount": "/", "used": 60, "total": 100 }],
  "queue": { "messages": 150 },
  "services": {
    "mariadb": "up",
    "elasticsearch": "up",
    "redis": "up",
    "php-fpm": "up"
  }
}

// WebSocket /ws/monitoring
// Real-time metrics stream
```

---

## Implementation Phases

### Phase 1: Core Infrastructure (Week 1-2)
- [ ] Setup Express API server in `/home/dashboard/public_html/webapp/backend/`
- [ ] Create script execution service (with timeout, logging)
- [ ] Implement JWT authentication
- [ ] Create system monitoring service
- [ ] Setup WebSocket for real-time updates
- [ ] Database schema for execution history

### Phase 2: Basic UI (Week 2-3)
- [ ] Overview dashboard with metrics cards
- [ ] Script runner page with execution console
- [ ] Log viewer page
- [ ] Basic styling with existing design system

### Phase 3: Advanced Features (Week 3-4)
- [ ] Cron job manager
- [ ] Alert center with notifications
- [ ] Database manager
- [ ] Backup scheduler

### Phase 4: Polish (Week 4-5)
- [ ] Mobile responsive design
- [ ] Performance optimization
- [ ] Error handling improvements
- [ ] Documentation

---

## Security Model

### Authentication
- JWT tokens stored in httpOnly cookies
- Session timeout: 8 hours
- Role-based access control

### Authorization
```javascript
const roles = {
  admin: {
    scripts: ['execute', 'view', 'edit', 'delete'],
    cron: ['create', 'edit', 'delete', 'execute'],
    monitoring: ['view', 'configure'],
    database: ['view', 'backup', 'restore', 'migrate']
  },
  operator: {
    scripts: ['execute', 'view'],
    cron: ['view', 'execute'],
    monitoring: ['view'],
    database: ['view', 'backup']
  },
  viewer: {
    scripts: ['view'],
    cron: ['view'],
    monitoring: ['view'],
    database: ['view']
  }
};
```

### Script Execution Security
- Whitelist of allowed scripts
- Parameter validation
- Execution timeout (default: 300s)
- All executions logged with user ID
- Rate limiting (max 5 executions per minute per user)

---

## File Structure

```
/home/dashboard/public_html/webapp/
├── src/
│   ├── components/
│   │   ├── dashboard/
│   │   │   ├── CPUCard.jsx
│   │   │   ├── MemoryCard.jsx
│   │   │   ├── QueueCard.jsx
│   │   │   └── ServicesCard.jsx
│   │   ├── scripts/
│   │   │   ├── ScriptGrid.jsx
│   │   │   ├── ScriptCard.jsx
│   │   │   ├── ExecutionConsole.jsx
│   │   │   └── ScriptScheduler.jsx
│   │   ├── monitoring/
│   │   │   ├── MetricsChart.jsx
│   │   │   ├── AlertBadge.jsx
│   │   │   └── ServiceStatus.jsx
│   │   └── common/
│   │       ├── Card.jsx
│   │       ├── Button.jsx
│   │       └── Modal.jsx
│   ├── pages/
│   │   ├── Overview.jsx
│   │   ├── ScriptRunner.jsx
│   │   ├── Monitoring.jsx
│   │   ├── CronManager.jsx
│   │   ├── LogViewer.jsx
│   │   ├── Alerts.jsx
│   │   └── Settings.jsx
│   ├── services/
│   │   ├── api.js
│   │   ├── scripts.js
│   │   ├── monitoring.js
│   │   └── auth.js
│   ├── hooks/
│   │   ├── useSystemStats.js
│   │   ├── useScriptExecution.js
│   │   └── useWebSocket.js
│   └── App.jsx
├── backend/
│   ├── routes/
│   │   ├── scripts.js
│   │   ├── monitoring.js
│   │   ├── cron.js
│   │   ├── logs.js
│   │   └── alerts.js
│   ├── services/
│   │   ├── scriptRunner.js
│   │   ├── systemMonitor.js
│   │   ├── cronManager.js
│   │   └── alertService.js
│   ├── middleware/
│   │   ├── auth.js
│   │   └── rateLimiter.js
│   ├── models/
│   │   ├── Execution.js
│   │   ├── Alert.js
│   │   └── Schedule.js
│   └── server.js
└── package.json
```

---

## Quick Start Commands

### Development
```bash
cd /home/dashboard/public_html/webapp
npm run dev  # Starts frontend + backend
```

### Production
```bash
cd /home/dashboard/public_html/webapp
npm run build
pm2 start ecosystem.config.js
```

### Test Script Execution
```bash
# Via API
curl -X POST http://localhost:3001/api/scripts/execute \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"scriptId": "cpu_optimize"}'
```

---

## Success Criteria

- [ ] All environment-specific scripts remain local and functional
- [ ] Shared scripts accessible from dashboard
- [ ] One-click execution for all scripts
- [ ] Real-time monitoring with < 5 second latency
- [ ] Execution history retained for 90 days
- [ ] Alert notifications within 30 seconds
- [ ] Mobile-responsive UI
- [ ] Role-based access control working

---

## Notes

1. **Do NOT move environment-specific scripts** - Keep them in their respective `/home/*/public_html/scripts/` directories
2. **Dashboard is an interface layer** - It calls existing scripts, doesn't replace them
3. **Cron jobs remain in crontab** - Dashboard provides UI to manage them
4. **Logs stay in place** - Dashboard provides viewer, doesn't relocate them
5. **Respect file permissions** - Dashboard API runs as dashboard user, uses sudo for privileged operations

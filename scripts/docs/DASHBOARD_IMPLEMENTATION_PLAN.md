# Server Management Dashboard - Implementation Plan

## Overview

Centralized React-based dashboard for managing all server operations, scripts, monitoring, and maintenance tasks across multiple domains (technostationery.com, pim.technostationery.com, beta, dev, lms).

**Location**: `/home/dashboard/public_html/webapp/`

---

## Current State

### Scripts Consolidated
- **55 shell scripts** moved to `/home/dashboard/public_html/scripts/`
- **12 markdown documents** in `/home/dashboard/public_html/scripts/docs/`
- **Directory structure**:
  ```
  /home/dashboard/public_html/scripts/
  ├── monitoring/       # System monitoring scripts
  ├── backup/          # Backup scripts
  ├── deployment/      # Deployment scripts
  ├── migration/       # Database migration scripts
  ├── maintenance/     # Maintenance & cleanup scripts
  ├── optimization/    # CPU/Performance optimization
  ├── category/        # Category management
  ├── product/         # Product management
  ├── image/           # Image processing
  ├── automation/      # Automated tasks
  └── docs/            # Documentation
  ```

### Cron Jobs Updated
All cron jobs now reference `/home/dashboard/public_html/scripts/`

---

## Architecture

### Frontend (React + Vite)
```
/home/dashboard/public_html/webapp/
├── src/
│   ├── components/
│   │   ├── dashboard/
│   │   ├── monitoring/
│   │   ├── scripts/
│   │   ├── database/
│   │   └── common/
│   ├── pages/
│   │   ├── Overview.jsx
│   │   ├── ScriptRunner.jsx
│   │   ├── SystemMonitor.jsx
│   │   ├── DatabaseManager.jsx
│   │   ├── BackupManager.jsx
│   │   └── Settings.jsx
│   ├── services/
│   │   ├── api.js
│   │   ├── scripts.js
│   │   └── monitoring.js
│   └── hooks/
│       ├── useSystemStats.js
│       └── useScriptExecution.js
├── backend/
│   ├── routes/
│   │   ├── scripts.js
│   │   ├── monitoring.js
│   │   └── database.js
│   └── services/
│       ├── scriptRunner.js
│       └── systemMonitor.js
└── docs/              # Built documentation
```

### Backend API (Node.js/Express)
Located in: `/home/dashboard/public_html/webapp/backend/`

---

## Features Implementation

### Phase 1: Core Infrastructure (Week 1-2)

#### 1.1 Backend API Setup
```javascript
// backend/routes/scripts.js
router.get('/list', authenticate, async (req, res) => {
  // List all available scripts
});

router.post('/execute', authenticate, async (req, res) => {
  // Execute script with parameters
});

router.get('/logs/:scriptId', authenticate, async (req, res) => {
  // Get script execution logs
});

router.post('/schedule', authenticate, async (req, res) => {
  // Schedule script execution
});
```

#### 1.2 Script Execution Service
```javascript
// backend/services/scriptRunner.js
class ScriptRunner {
  async execute(scriptPath, params, user) {
    // Validate permissions
    // Log execution
    // Run script with timeout
    // Capture output
    // Handle errors
  }
  
  async stop(executionId) {
    // Stop running script
  }
  
  getLogs(scriptId, limit = 100) {
    // Get execution logs
  }
}
```

#### 1.3 System Monitoring Service
```javascript
// backend/services/systemMonitor.js
class SystemMonitor {
  async getCPUUsage() {
    // Get CPU stats from /proc/stat or top
  }
  
  async getMemoryUsage() {
    // Get memory stats from free
  }
  
  async getDiskUsage() {
    // Get disk stats from df
  }
  
  async getQueueStatus() {
    // Get Magento queue status
  }
  
  async getServiceStatus() {
    // Check MariaDB, Elasticsearch, Redis, PHP-FPM
  }
}
```

---

### Phase 2: UI Components (Week 2-3)

#### 2.1 Dashboard Overview Page
```jsx
// src/pages/Overview.jsx
function Overview() {
  const { cpu, memory, disk, queue } = useSystemStats();
  
  return (
    <Dashboard>
      <CPUChart data={cpu} />
      <MemoryChart data={memory} />
      <DiskUsage data={disk} />
      <QueueStatus data={queue} />
      <AlertsList />
      <QuickActions />
    </Dashboard>
  );
}
```

#### 2.2 Script Runner Page
```jsx
// src/pages/ScriptRunner.jsx
function ScriptRunner() {
  const [scripts, setScripts] = useState([]);
  const [selectedScript, setSelectedScript] = useState(null);
  const [execution, setExecution] = useState(null);
  
  return (
    <ScriptRunner>
      <ScriptList 
        scripts={scripts}
        onSelect={setSelectedScript}
        categories={['monitoring', 'backup', 'deployment', 'maintenance']}
      />
      <ScriptDetails 
        script={selectedScript}
        onExecute={handleExecute}
      />
      <ExecutionLog execution={execution} />
    </ScriptRunner>
  );
}
```

#### 2.3 System Monitor Page
```jsx
// src/pages/SystemMonitor.jsx
function SystemMonitor() {
  const [realtime, setRealtime] = useState(true);
  const { services, processes, logs } = useSystemMonitor();
  
  return (
    <SystemMonitor>
      <ServiceStatus services={services} />
      <ProcessList processes={processes} />
      <LogViewer logs={logs} realtime={realtime} />
      <ResourceCharts />
    </SystemMonitor>
  );
}
```

#### 2.4 Database Manager Page
```jsx
// src/pages/DatabaseManager.jsx
function DatabaseManager() {
  const [databases, setDatabases] = useState([]);
  const [migrations, setMigrations] = useState([]);
  
  return (
    <DatabaseManager>
      <DatabaseList databases={databases} />
      <MigrationRunner 
        migrations={migrations}
        onRun={handleRunMigration}
      />
      <BackupScheduler />
      <QueryEditor />
    </DatabaseManager>
  );
}
```

---

### Phase 3: Advanced Features (Week 3-4)

#### 3.1 Scheduled Tasks Manager
```jsx
// src/components/ScheduledTasks.jsx
function ScheduledTasks() {
  const [schedules, setSchedules] = useState([]);
  
  return (
    <ScheduledTasks>
      <CronEditor onSave={handleSaveSchedule} />
      <ScheduleList schedules={schedules} />
      <ExecutionHistory />
    </ScheduledTasks>
  );
}
```

#### 3.2 Alert System
```javascript
// backend/services/alerts.js
class AlertService {
  async checkThresholds() {
    // CPU > 80%
    // Memory > 85%
    // Queue > 5000
    // Disk > 90%
  }
  
  async sendAlert(severity, message, channel) {
    // Email, Slack, SMS notifications
  }
}
```

#### 3.3 Backup Management
```jsx
// src/pages/BackupManager.jsx
function BackupManager() {
  const [backups, setBackups] = useState([]);
  
  return (
    <BackupManager>
      <BackupSchedule />
      <BackupList backups={backups} />
      <RestoreWizard />
      <BackupVerification />
    </BackupManager>
  );
}
```

---

## API Endpoints

### Authentication
```
POST   /api/auth/login
POST   /api/auth/logout
GET    /api/auth/me
```

### Scripts
```
GET    /api/scripts                    # List all scripts
GET    /api/scripts/:category          # List scripts by category
GET    /api/scripts/:id                # Get script details
POST   /api/scripts/:id/execute        # Execute script
GET    /api/scripts/:id/logs           # Get execution logs
POST   /api/scripts/:id/schedule       # Schedule execution
DELETE /api/scripts/:id/schedule/:scheduleId
```

### Monitoring
```
GET    /api/monitoring/cpu             # CPU usage history
GET    /api/monitoring/memory          # Memory usage
GET    /api/monitoring/disk            # Disk usage
GET    /api/monitoring/queue           # Magento queue status
GET    /api/monitoring/services        # Service status
GET    /api/monitoring/processes       # Running processes
GET    /api/monitoring/alerts          # Active alerts
```

### Database
```
GET    /api/databases                  # List databases
GET    /api/databases/:name/stats      # Database statistics
POST   /api/databases/:name/backup     # Create backup
GET    /api/databases/:name/backups    # List backups
POST   /api/databases/:name/restore    # Restore backup
GET    /api/migrations                 # List migrations
POST   /api/migrations/:id/run         # Run migration
```

### Cron/Scheduling
```
GET    /api/cron/jobs                  # List cron jobs
POST   /api/cron/jobs                  # Create cron job
PUT    /api/cron/jobs/:id              # Update cron job
DELETE /api/cron/jobs/:id              # Delete cron job
GET    /api/cron/logs                  # Cron execution logs
```

---

## Security Considerations

### Authentication & Authorization
```javascript
// Middleware for role-based access
const requireRole = (role) => (req, res, next) => {
  if (req.user.role !== role && req.user.role !== 'admin') {
    return res.status(403).json({ error: 'Insufficient permissions' });
  }
  next();
};

// Usage
router.post('/execute', requireRole('admin'), scriptController.execute);
```

### Script Execution Security
- Whitelist allowed scripts
- Validate all parameters
- Set execution timeouts
- Log all executions
- Rate limiting per user

### API Security
- JWT authentication
- HTTPS only
- CORS configuration
- Rate limiting
- Input sanitization

---

## Database Schema

```sql
-- Script executions log
CREATE TABLE script_executions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  script_name VARCHAR(255),
  executed_by INT,
  status ENUM('running', 'completed', 'failed', 'cancelled'),
  exit_code INT,
  output TEXT,
  started_at DATETIME,
  completed_at DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Scheduled tasks
CREATE TABLE scheduled_tasks (
  id INT PRIMARY KEY AUTO_INCREMENT,
  script_name VARCHAR(255),
  cron_expression VARCHAR(100),
  parameters JSON,
  enabled BOOLEAN DEFAULT TRUE,
  last_run DATETIME,
  next_run DATETIME,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- System metrics
CREATE TABLE system_metrics (
  id INT PRIMARY KEY AUTO_INCREMENT,
  metric_type VARCHAR(50),
  metric_value DECIMAL(10,2),
  metadata JSON,
  recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Alerts
CREATE TABLE alerts (
  id INT PRIMARY KEY AUTO_INCREMENT,
  severity VARCHAR(20),
  title VARCHAR(255),
  message TEXT,
  acknowledged BOOLEAN DEFAULT FALSE,
  acknowledged_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## Implementation Timeline

### Week 1: Backend Foundation
- [ ] Setup Express API server
- [ ] Implement authentication
- [ ] Create script execution service
- [ ] Create system monitoring service
- [ ] Setup database schema

### Week 2: Core UI
- [ ] Dashboard overview page
- [ ] Script runner page
- [ ] System monitor page
- [ ] Real-time updates (WebSocket)

### Week 3: Advanced Features
- [ ] Database manager
- [ ] Backup manager
- [ ] Scheduled tasks
- [ ] Alert system

### Week 4: Polish & Testing
- [ ] UI/UX improvements
- [ ] Error handling
- [ ] Performance optimization
- [ ] Documentation
- [ ] User testing

---

## Integration Points

### Existing Scripts Integration
All scripts in `/home/dashboard/public_html/scripts/` are accessible:
```javascript
const SCRIPTS_BASE_PATH = '/home/dashboard/public_html/scripts';

const AVAILABLE_SCRIPTS = {
  monitoring: [
    { id: 'system_monitor', name: 'System Monitor', path: 'monitoring/system_monitor.sh' },
    { id: 'cpu_monitor', name: 'CPU Monitor', path: 'monitoring/cpu_monitor.sh' },
    { id: 'queue_monitor', name: 'Queue Monitor', path: 'monitoring/queue_monitor.sh' }
  ],
  optimization: [
    { id: 'cpu_optimize', name: 'CPU Optimize', path: 'optimization/cpu_optimize.sh' },
    { id: 'emergency_throttle', name: 'Emergency Throttle', path: 'optimization/emergency_cpu_throttle.sh' }
  ],
  maintenance: [
    { id: 'queue_optimize', name: 'Queue Optimize', path: 'maintenance/queue_optimize.sh' },
    { id: 'master_cleanup', name: 'Master Cleanup', path: 'maintenance/master_cleanup.sh' }
  ],
  backup: [
    // Backup scripts
  ],
  migration: [
    // Migration scripts
  ]
};
```

### WebSocket for Real-time Updates
```javascript
// backend/websocket.js
const WebSocket = require('ws');
const wss = new WebSocket.Server({ port: 8080 });

wss.on('connection', (ws) => {
  // Send real-time metrics
  const interval = setInterval(() => {
    getSystemMetrics().then(metrics => {
      ws.send(JSON.stringify({ type: 'metrics', data: metrics }));
    });
  }, 5000);
  
  ws.on('close', () => clearInterval(interval));
});
```

---

## Testing Strategy

### Unit Tests
```javascript
// tests/services/scriptRunner.test.js
describe('ScriptRunner', () => {
  it('should execute script successfully', async () => {
    const runner = new ScriptRunner();
    const result = await runner.execute('test_script.sh', {});
    expect(result.status).toBe('completed');
  });
  
  it('should timeout long-running scripts', async () => {
    const runner = new ScriptRunner({ timeout: 5000 });
    const result = await runner.execute('slow_script.sh', {});
    expect(result.status).toBe('timeout');
  });
});
```

### Integration Tests
```javascript
// tests/api/scripts.test.js
describe('Scripts API', () => {
  it('GET /api/scripts should return script list', async () => {
    const response = await request(app)
      .get('/api/scripts')
      .set('Authorization', `Bearer ${token}`);
    
    expect(response.status).toBe(200);
    expect(response.body).toHaveProperty('scripts');
  });
});
```

---

## Deployment

### Environment Variables
```env
# backend/.env
NODE_ENV=production
PORT=3001
JWT_SECRET=your-secret-key
DATABASE_URL=mysql://user:pass@localhost/dashboard_db
SCRIPTS_PATH=/home/dashboard/public_html/scripts
ALLOWED_SCRIPTS=system_monitor,cpu_optimize,queue_cleanup
MAX_EXECUTION_TIME=300
```

### PM2 Configuration
```javascript
// ecosystem.config.js
module.exports = {
  apps: [{
    name: 'dashboard-api',
    script: 'backend/server.js',
    instances: 2,
    exec_mode: 'cluster',
    env: {
      NODE_ENV: 'production'
    }
  }]
};
```

---

## Success Metrics

- [ ] All 55 scripts accessible and executable from dashboard
- [ ] Real-time monitoring with < 5 second latency
- [ ] Alert notifications within 30 seconds of threshold breach
- [ ] Script execution logs retained for 90 days
- [ ] Backup success rate > 99%
- [ ] API response time < 200ms for 95th percentile

---

## Next Steps

1. **Review and approve this plan**
2. **Setup backend API structure**
3. **Create database schema**
4. **Implement authentication**
5. **Build script execution service**
6. **Create React components**
7. **Integrate WebSocket for real-time**
8. **Test with existing scripts**
9. **Deploy to production**
10. **Train users on new dashboard**

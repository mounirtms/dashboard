# 🚀 Light WHM Dashboard — Comprehensive Plan

**Date:** 2026-04-11  
**Project:** Server Control Center v2.0  
**Type:** Lightweight WHM-like Dashboard for E-commerce Server Management

---

## 🎯 Executive Summary

Build a **lightweight WHM-style dashboard** using the existing React project (`techno-app`) as the foundation, combined with **PHP backend** (`api/monitor.php`) for WHM/cPanel operations and **Node.js backend** for ETL, Magento API, and database sync operations. The dashboard will manage **dev → beta → production** environments with CI/CD pipelines.

---

## 📊 Current State Analysis

### Existing Infrastructure
| Component | Location | Status |
|-----------|----------|--------|
| **Production** | `/home/technadminy7/public_html` | ✅ Live |
| **Beta** | `/home/beta/public_html` | ✅ Live (Magento 2.4.7) |
| **Dev** | `/home/dev/public_html` | ✅ Live |
| **Dashboard** | `/home/dashboard/public_html` | ✅ Unsuspended (fixed) |
| **PIM** | `/home/pim/public_html` | ⚠️ Check |
| **LMS** | `/home/lms/public_html` | ⚠️ Check |

### Current Backend Stack
| Backend | Location | Purpose |
|---------|----------|---------|
| **PHP (monitor.php)** | `/home/dashboard/public_html/api/` | System monitoring, sites, crons, queues, indexers, cleanup |
| **Node.js** | `/home/dashboard/public_html/webapp/backend/` | Firebase sync, Magento API, MDM/CEGID sync, caching, workers |
| **Node.js (techno-app)** | `github.com/mounirtms/techno-app` (ETL branch) | SQL Server (MDM/CEGID), Magento API, auth server |

### GitHub Repository Branches
| Branch | Purpose | Last Commit |
|--------|---------|-------------|
| `www` | Production web assets | `643f162` - stock sync |
| `master` | Main development | `8723e7c` |
| `react` | React frontend (Vite) | `d8d5fe5` |
| `ETL` | Node backend + React UI | `8d9c4ab` |
| `release` | Dashboard release | `42e68fb` - Session 36 |
| `main` | Firebase hosting | - |

### React Project Structure (ETL branch)
```
techno-app/
├── src/
│   ├── App.jsx                    # Main app
│   ├── main.jsx                   # Entry point
│   ├── pages/
│   │   ├── Dashboard.jsx          # Dashboard page
│   │   └── Login.jsx              # Login page
│   ├── contexts/                  # Auth, Theme, Tab, Language
│   ├── services/
│   │   ├── dashboardService.js    # Dashboard operations
│   │   ├── magentoApi.js          # Magento API
│   │   ├── magentoService.js      # Magento service layer
│   │   ├── cegidService.js        # CEGID API
│   │   └── dataService.js         # Data operations
│   ├── config/                    # App, grid, firebase config
│   ├── hooks/                     # useVersion, etc.
│   ├── utils/                     # Formatters, grid, axios
│   └── theme/                     # Theme configuration
├── backend/
│   ├── server.js                  # Express + SQL Server + Magento
│   ├── authServer.js              # JWT auth server
│   ├── src/
│   │   ├── utils/database.js      # MDM/CEGID connection pools
│   │   ├── config/                # Magento, sources config
│   │   ├── mdm/services.js        # MDM inventory operations
│   │   └── queries/               # SQL queries
│   └── package.json
├── docs/                          # Documentation
├── scripts/                       # Build/deploy scripts
├── swagger.json                   # API documentation
└── vite.config.js                 # Vite build config
```

### Existing Webapp Backend (webapp/backend/)
```
webapp/backend/src/
├── services/
│   ├── FirebaseService.js         # Firebase Realtime DB
│   ├── magentoService.js          # Magento operations
│   ├── mdmService.js              # MDM sync
│   ├── syncService.js             # Data synchronization
│   ├── cacheService.js            # Redis/file caching
│   ├── metricsService.js          # Performance metrics
│   ├── usageAnalytics.js          # Usage tracking
│   ├── workerPool.js              # Background workers
│   └── orderStatusSyncService.js  # Order sync
├── controllers/                   # API controllers
├── routes/                        # API routes
├── middleware/                    # Auth, validation
├── cron/                          # Scheduled tasks
├── queries/                       # SQL queries
└── utils/                         # Helpers
```

### Existing PHP API (api/monitor.php)
- ✅ `overview` — Load, memory, disk, uptime, services, top processes
- ✅ `sites` — All sites: PHP-FPM, disk, DB size, Magento mode, cache
- ✅ `crons` — Crontab entries with running status
- ✅ `queues` — Magento queue consumers and pending counts
- ✅ `cleanup` — Kill messenger, restart PHP-FPM, flush cache
- ✅ `indexer` — Magento indexer status
- ✅ `execute` — Script execution (allowed paths only)

---

## 🏗️ Architecture Plan

### Light WHM Dashboard Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                     LIGHT WHM DASHBOARD                              │
│                        (React + Vite)                                │
│                                                                      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────────┐   │
│  │  OVERVIEW    │  │ ENVIRONMENTS │  │    OPERATIONS PANEL      │   │
│  │  Dashboard   │  │  Manager     │  │    (WHM-like actions)    │   │
│  │  - Load      │  │  - Prod      │  │    - Services control    │   │
│  │  - Memory    │  │  - Beta      │  │    - Queue management    │   │
│  │  - Disk      │  │  - Dev       │  │    - Cache management    │   │
│  │  - Services  │  │  - PIM/LMS   │  │    - Cron management     │   │
│  │  - Processes │  │              │  │    - Deploy/rollback     │   │
│  └──────────────┘  └──────────────┘  └──────────────────────────┘   │
│                                                                      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────────┐   │
│  │   MAGENTO    │  │   CI/CD      │  │     SCRIPTS & LOGS       │   │
│  │   OPERATIONS │  │   PIPELINE   │  │     MANAGER              │   │
│  │  - API test  │  │  - Dev deploy│  │     - Run scripts        │   │
│  │  - Cache     │  │  - Beta deploy│ │     - View logs          │   │
│  │  - Indexer   │  │  - Prod deploy│ │     - Log analysis       │   │
│  │  - Compiler  │  │  - Rollback  │  │     - Real-time tail     │   │
│  │  - Static    │  │  - Migrations│  │     - Error monitoring   │   │
│  └──────────────┘  └──────────────┘  └──────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
         │                        │                        │
         ▼                        ▼                        ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────────┐
│  PHP API        │    │  Node.js API    │    │  WHM/cPanel API     │
│  (monitor.php)  │    │  (backend/)     │    │  (whmapi1/uapi)     │
│                 │    │                 │    │                     │
│  - System data  │    │  - Magento API  │    │  - Account mgmt     │
│  - Site status  │    │  - MDM/CEGID    │    │  - Suspend/unsuspend│
│  - Queues       │    │  - SQL Server   │    │  - DNS/email        │
│  - Crons        │    │  - Auth/JWT     │    │  - Backups          │
│  - Cleanup      │    │  - ETL sync     │    │  - SSL/certs        │
│  - Script exec  │    │  - Workers      │    │  - Resource limits  │
└─────────────────┘    └─────────────────┘    └─────────────────────┘
```

### Technology Stack Decision

| Layer | Technology | Reason |
|-------|-----------|--------|
| **Frontend** | React + Vite + Recharts | Existing project, fast, charts |
| **System Backend** | PHP (monitor.php) | Direct server access, cPanel compatible |
| **Business Backend** | Node.js (Express) | Magento API, SQL Server, ETL, workers |
| **WHM Operations** | whmapi1/uapi CLI | Native cPanel/WHM API |
| **Auth** | JWT + Firebase Auth | Existing auth system |
| **Real-time** | Server-Sent Events (SSE) | Simple, no WebSocket complexity |
| **Caching** | Redis (existing) | Already running on server |

---

## 📋 Phase-by-Phase Implementation Plan

### Phase 1: Foundation & Project Setup (Week 1)
**Goal:** Set up React project structure and basic connectivity

#### Task 1.1: Pull and Merge React Project
```bash
cd /home/dashboard/public_html/webapp
git remote add upstream https://github.com/mounirtms/techno-app.git
git fetch upstream
git merge upstream/ETL --allow-unrelated-histories
```

#### Task 1.2: Create Dashboard-Specific Components
- `ServerOverview.jsx` — System metrics cards (from monitor.php)
- `EnvironmentManager.jsx` — Prod/Beta/Dev environment cards
- `ServiceControl.jsx` — Start/stop/restart services
- `QueueManager.jsx` — Queue consumers visualization
- `CronManager.jsx` — Cron list with enable/disable
- `CacheManager.jsx` — Clear cache operations
- `IndexerStatus.jsx` — Magento indexer grid
- `ScriptRunner.jsx` — Execute scripts with live output
- `LogViewer.jsx` — Real-time log tailing

#### Task 1.3: Set Up API Proxy Layer
- Create `webapp/src/api/` with service wrappers
- `serverApi.js` → `api/monitor.php` endpoints
- `magentoApi.js` → Magento REST API
- `mabApi.js` → MAB Environment Manager (beta)
- `whmApi.js` → Server-side proxy for whmapi1

#### Task 1.4: Real-time Data via SSE
- Create `api/stream.php` — Server-Sent Events endpoint
- Push system metrics every 5 seconds
- Push queue status changes
- Push deployment progress

---

### Phase 2: Core Dashboard Features (Week 2)
**Goal:** Fully functional monitoring and operations dashboard

#### Task 2.1: Server Overview Page
**Data source:** `api/monitor.php?action=overview`
- Load average (1/5/15 min) with sparkline charts
- Memory usage bar with color coding
- Disk usage per partition
- Uptime display
- Service status grid (running/stopped/failed)
- Top 10 CPU processes table
- HTTP error rate (503/500 counters)

#### Task 2.2: Environment Manager
**Data source:** MAB Environment Manager API + monitor.php sites
- Environment cards: Production, Beta, Dev, PIM
- Each card shows:
  - Status badge (active/suspended/minimized)
  - PHP-FPM workers count
  - Disk usage
  - Database size
  - Magento mode
  - Cache status
  - Quick actions: Deploy, Suspend, Resume, Restart PHP-FPM
- Production protection: Cannot suspend/minimize/kill prod

#### Task 2.3: Operations Panel
**Actions via PHP API:**
- **Services Control:** Restart PHP-FPM, Elasticsearch, MySQL, Varnish, Redis, Apache, Cron
- **Queue Management:** View consumers, kill stuck processes, restart consumers
- **Cache Management:** Flush full cache, flush config cache, flush static content
- **Cron Management:** List crons, enable/disable, run manually, view history

#### Task 2.4: Magento Operations
**Via Node.js backend + PHP CLI:**
- Indexer status grid with reindex buttons
- Compiler status (setup:di:compile)
- Static content deploy (with environment selector)
- Config diff viewer
- Module status list

---

### Phase 3: CI/CD Pipeline (Week 3)
**Goal:** Automated deployment for dev → beta → production

#### Task 3.1: Deployment Scripts
```
scripts/deployment/
├── deploy-dev.sh        # Deploy to dev.technostationery.com
├── deploy-beta.sh       # Deploy to beta.technostationery.com
├── deploy-prod.sh       # Deploy to technostationery.com
├── rollback.sh          # Rollback any environment
├── migrate-db.sh        # Run database migrations
├── health-check.sh      # Post-deployment health check
└── notify.sh            # Slack/email notifications
```

#### Task 3.2: Git-Based Deployment Flow
```
Developer → push to feature branch → PR → merge to release → auto-deploy to dev
                                                                        ↓
                                                              Manual approve → beta
                                                                        ↓
                                                              Manual approve → production
```

#### Task 3.3: Dashboard CI/CD Integration
- `/cicd` page in dashboard showing:
  - Recent commits
  - Deployment history
  - Environment status
  - One-click deploy buttons
  - Rollback history

#### Task 3.4: Pre-deployment Checks
- Disk space check
- Database backup verification
- Composer audit (security)
- PHP syntax check
- Magento compile check
- Queue drain check

---

### Phase 4: Advanced Features (Week 4)
**Goal:** ETL operations, advanced monitoring, automation

#### Task 4.1: ETL Dashboard
**Via Node.js backend:**
- MDM SQL Server connection status
- CEGID connection status
- Price sync status
- Inventory sync status
- Product sync history
- Sync error logs
- Manual sync trigger

#### Task 4.2: Advanced Monitoring
- **Real-time process monitor:** Live updating process list
- **Log aggregation:** Centralized log viewer across all environments
- **Error tracking:** PHP errors, Apache errors, Magento exceptions
- **Performance metrics:** Response times, DB query times, cache hit rates
- **Alert system:** Threshold-based alerts (CPU, memory, disk, errors)

#### Task 4.3: Script Management
- Script library organized by category:
  - Deployment scripts
  - Cleanup scripts
  - Database scripts
  - Magento maintenance
  - Emergency recovery
- Execute with parameters
- View output in real-time
- Save execution history

#### Task 4.4: User Management
- Dashboard user accounts (separate from Magento)
- Role-based access (admin, operator, viewer)
- Audit log of all actions
- Session management

---

## 🔧 Immediate Fixes & Optimizations

### Fix 1: PHP-FPM Stability
```bash
# Ensure PHP-FPM config is always in sync
/usr/local/cpanel/scripts/php_fpm_config --rebuild
systemctl restart ea-php82-php-fpm
```

### Fix 2: Directory Ownership Audit
```bash
# Fix all home directory ownership
chown technadminy7:technadminy7 /home/technadminy7/
chown beta:beta /home/beta/
chown dashboard:dashboard /home/dashboard/
chown dev:dev /home/dev/
chown pim:pim /home/pim/
```

### Fix 3: Suspension File Cleanup
```bash
# Remove all stale suspension files
ls /var/cpanel/suspended/
# Remove any that shouldn't be suspended
rm -f /var/cpanel/suspended/<user>
# Remove SUSPENDTIME from user files
sed -i '/^SUSPENDTIME=/d' /var/cpanel/users/<user>
```

### Optimization 1: API Response Time
- Add Redis caching to `monitor.php` responses (5-second TTL)
- Use gzip compression (already enabled)
- Implement conditional requests (ETag/Last-Modified)

### Optimization 2: React Build
- Enable code splitting (lazy load pages)
- Enable Vite compression (gzip + brotli)
- Minimize bundle size (tree-shake unused imports)
- Add service worker for offline cache

### Optimization 3: Database Queries
- Add indexes to frequently queried columns
- Use connection pooling (already in Node.js backend)
- Implement query result caching

---

## 📁 File Structure Plan

```
/home/dashboard/public_html/
├── index.html                    # Server Control Center (static fallback)
├── cicd-dashboard.html           # CI/CD pipeline viewer (static)
├── queue-monitor.html            # Queue monitor (static)
│
├── webapp/                       # React Application (main dashboard)
│   ├── src/
│   │   ├── App.jsx               # Main app with routes
│   │   ├── main.jsx              # Entry point
│   │   ├── api/                  # NEW: API service wrappers
│   │   │   ├── serverApi.js      # PHP monitor.php wrapper
│   │   │   ├── magentoApi.js     # Magento REST API
│   │   │   ├── mabApi.js         # MAB Environment Manager
│   │   │   ├── whmApi.js         # WHM API proxy
│   │   │   └── etlApi.js         # Node.js ETL API
│   │   ├── pages/
│   │   │   ├── Dashboard.jsx     # Main server overview
│   │   │   ├── Environments.jsx  # Environment manager
│   │   │   ├── Operations.jsx    # WHM-like operations
│   │   │   ├── Magento.jsx       # Magento operations
│   │   │   ├── CICD.jsx          # CI/CD pipeline
│   │   │   ├── Scripts.jsx       # Script runner
│   │   │   ├── Logs.jsx          # Log viewer
│   │   │   └── Settings.jsx      # Dashboard settings
│   │   ├── components/
│   │   │   ├── MetricCard.jsx    # Reusable metric display
│   │   │   ├── ServiceBadge.jsx  # Service status badge
│   │   │   ├── ProcessTable.jsx  # Process list table
│   │   │   ├── QueueStatus.jsx   # Queue consumer status
│   │   │   ├── CronList.jsx      # Cron job list
│   │   │   ├── DeployProgress.jsx# Deployment progress
│   │   │   └── LogTail.jsx       # Real-time log tail
│   │   ├── contexts/             # Existing + new contexts
│   │   ├── hooks/                # Custom hooks
│   │   ├── services/             # Existing services
│   │   ├── utils/                # Helpers
│   │   └── config/               # Config files
│   ├── backend/                  # Node.js API server
│   │   ├── server.js             # Express server
│   │   ├── src/
│   │   │   ├── controllers/      # API controllers
│   │   │   ├── services/         # Business logic
│   │   │   ├── middleware/       # Auth, validation
│   │   │   └── utils/            # Helpers
│   │   └── package.json
│   ├── package.json
│   ├── vite.config.js
│   └── .env.production
│
├── api/                          # PHP API (existing)
│   ├── monitor.php               # System monitoring API
│   ├── dashboard.php             # Dashboard data API
│   ├── health.php                # Health check API
│   ├── logs.php                  # Log retrieval API
│   └── queue-monitor.php         # Queue monitoring API
│
├── scripts/                      # Automation scripts
│   ├── deployment/
│   │   ├── deploy.sh
│   │   ├── rebuild.sh
│   │   └── production-build-fix.sh.broken
│   ├── database/
│   │   └── db-manage.sh
│   ├── emergency/
│   │   └── load-recovery.sh
│   ├── testing/
│   └── migration/
│       └── migrate-db.sh
│
├── logs/                         # Application logs
│   ├── deployments/
│   ├── errors/
│   └── testing/
│
├── docs/                         # Documentation (copied from beta + new)
│   ├── AUDIT_REPORT_ENVIRONMENT_MANAGER.md
│   ├── BETA_FINALIZATION_SESSION_35.md
│   ├── PRODUCTION_DEPLOYMENT_CHECKLIST.md
│   ├── FINALIZED_TASK_PLAN.md
│   ├── ROLLBACK_BACKUP_STRATEGY.md
│   ├── SECURITY_UPDATES_APPLIED.md
│   ├── LIGHT_WHM_DASHBOARD_PLAN.md  # This file
│   └── ...
│
├── backend/                      # Compiled backend (existing)
│   ├── index.js
│   └── queries/
│
└── config/                       # Server config
```

---

## 🔄 CI/CD Pipeline Design

### Deployment Flow

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   Developer  │     │    DEV      │     │    BETA     │     │  PRODUCTION │
│   (Local)    │────▶│  (dev.)     │────▶│  (beta.)    │────▶│   (www.)    │
│              │     │  Auto       │     │  Manual     │     │  Manual     │
│   git push   │     │  Deploy     │     │  Approve    │     │  Approve    │
└─────────────┘     └─────────────┘     └─────────────┘     └─────────────┘
                          │                     │                     │
                          ▼                     ▼                     ▼
                    Auto Tests           Integration Tests      Smoke Tests
                    Lint Check           Security Scan          Performance Check
                    Build Check          Data Validation        Rollback Ready
```

### Deployment Commands

```bash
# Deploy to dev (automatic on merge to release)
./scripts/deployment/deploy.sh dev

# Deploy to beta (manual approval)
./scripts/deployment/deploy.sh beta

# Deploy to production (manual approval + backup)
./scripts/deployment/deploy.sh prod

# Rollback last deployment
./scripts/deployment/rebuild.sh <environment>

# Run migrations
./scripts/migration/migrate-db.sh <environment>

# Health check
./scripts/deployment/health-check.sh <environment>
```

---

## 📊 Monitoring & Tunings

### Real-time Metrics (SSE Stream)
| Metric | Update Interval | Alert Threshold |
|--------|----------------|-----------------|
| Load Average | 5s | > 10 (1min) |
| Memory Usage | 5s | > 90% |
| Disk Usage | 30s | > 80% |
| PHP-FPM Workers | 10s | > 80% of max |
| DB Connections | 10s | > 90% of max |
| Queue Pending | 30s | > 10000 |
| HTTP 503 Rate | 30s | > 10/min |
| HTTP 500 Rate | 30s | > 5/min |

### Magento-Specific Tunings
- Cache hit rate monitoring
- Indexer duration tracking
- Cron job execution times
- Queue consumer throughput
- API response times
- Static content deploy duration

### ETL-Specific Tunings
- MDM sync duration and success rate
- CEGID sync status
- Price sync completeness
- Inventory sync accuracy
- Error retry counts

---

## ✅ Next Steps (Priority Order)

1. **Fix directory ownership** on all cPanel accounts ✅ DONE
2. **Verify PHP-FPM stability** across all sites ✅ DONE
3. **Merge techno-app ETL branch** into webapp project
4. **Create API proxy layer** in React app
5. **Build Server Overview component** with real data from monitor.php
6. **Build Environment Manager component** integrating MAB API
7. **Set up SSE streaming** for real-time updates
8. **Create deployment scripts** for dev → beta → prod
9. **Add CI/CD page** to dashboard
10. **Implement script runner** for operations
11. **Add log viewer** with real-time tailing
12. **Set up monitoring alerts** for critical thresholds

---

## 📝 Notes

- The existing `api/monitor.php` is already comprehensive and production-ready
- The MAB Environment Manager on beta provides environment suspend/resume/deploy APIs
- The Node.js backend has MDM/CEGID sync already implemented
- All WHM operations can be done via `whmapi1` CLI (available on server)
- Cloudflare caching needs careful handling for API endpoints
- Production operations need confirmation dialogs and audit logging

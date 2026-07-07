# Architecture Audit & Migration Roadmap

**Date:** 2026-04-28  
**Project:** Techno Stationery Server Control Center v3.1.0  
**Auditor:** Qoder CLI

---

## Executive Summary

This document provides a comprehensive architecture audit of the current dashboard system and presents a strategic migration plan to modernize the codebase while preserving existing functionality and integrations.

### Current State Assessment: ⚠️ NEEDS MODERNIZATION

The dashboard is functional but suffers from:
- Mixed architecture (vanilla JS + React SPA + PHP monolith)
- Hardcoded credentials (now extracted to .env)
- No build pipeline for main dashboard
- Tight coupling between UI and API
- Limited test coverage
- No API documentation

---

## 1. Current Architecture Analysis

### 1.1 Technology Stack

| Layer | Current Technology | Status |
|-------|-------------------|--------|
| **Frontend (Main)** | Vanilla HTML/CSS/JS (85KB monolithic HTML) | ⚠️ Needs refactoring |
| **Frontend (ETL)** | React + Vite + MUI + Firebase | ✅ Modern |
| **Backend API** | PHP 8.2 procedural scripts | ⚠️ Needs OOP refactor |
| **Backend (Node)** | Express + Socket.io + JWT | ✅ Modern |
| **Database** | MariaDB 10.6 | ✅ Stable |
| **Cache** | Redis | ✅ Modern |
| **Search** | Elasticsearch | ✅ Modern |
| **CDN/WAF** | Cloudflare | ✅ Modern |

### 1.2 File Structure Issues

```
❌ index.html (85KB) - Monolithic frontend with embedded CSS/JS
❌ api/*.php - Procedural code, no OOP patterns
❌ Hardcoded credentials (now fixed with .env)
❌ No separation of concerns in API files
❌ No input validation layer
❌ No API versioning
❌ No request/response middleware
❌ Mixed authentication patterns
❌ No error handling standardization
❌ No API documentation (Swagger/OpenAPI)
```

### 1.3 Security Concerns (Current)

| Issue | Severity | Status |
|-------|----------|--------|
| Hardcoded credentials | 🔴 Critical | ✅ Fixed (extracted to .env) |
| SQL injection risk | 🟡 Medium | ⚠️ Partial (using mysqli, needs PDO) |
| XSS vulnerability | 🟡 Medium | ⚠️ Needs output sanitization |
| CSRF protection | 🟡 Medium | ❌ Not implemented |
| Rate limiting | 🟡 Medium | ⚠️ Partial (Telegram only) |
| API token management | 🟡 Medium | ❌ Hardcoded Cloudflare token |
| Session security | 🟢 Low | ✅ Session-based with lockout |

### 1.4 Performance Issues

| Issue | Impact | Recommendation |
|-------|--------|----------------|
| Monolithic HTML file | High page load time | Split into components |
| No asset bundling | Multiple HTTP requests | Use build tool |
| No code splitting | Large initial bundle | Lazy load tabs |
| No caching strategy | Repeated API calls | Implement cache layer |
| Polling every 30s | Server load | WebSocket/SSE |

### 1.5 Maintainability Issues

| Issue | Impact |
|-------|--------|
| No TypeScript | Runtime errors |
| No linting | Inconsistent code style |
| No tests | Regression risk |
| No CI/CD | Manual deployment |
| No API docs | Developer onboarding difficulty |
| Mixed paradigms | Cognitive load |

---

## 2. Migration Strategy

### 2.1 Recommended Framework: **Laravel + Inertia.js + Vue 3**

#### Why This Stack?

| Criteria | Laravel + Inertia + Vue 3 | Alternative: Next.js | Alternative: Laravel Livewire |
|----------|--------------------------|---------------------|----------------------------|
| **PHP Integration** | ✅ Native | ❌ Requires API layer | ✅ Native |
| **Learning Curve** | 🟡 Moderate | 🟢 Easy (if React) | 🟢 Easy (if PHP) |
| **Server Monitoring** | ✅ Excellent (shell exec) | ⚠️ Complex | ✅ Excellent |
| **Existing Code Reuse** | ✅ 80% reusable | ❌ Complete rewrite | ✅ 90% reusable |
| **Ecosystem** | ✅ Mature | ✅ Mature | ✅ Mature |
| **Real-time** | ✅ WebSockets (Pusher/Soketi) | ✅ Socket.io | ✅ Livewire |
| **Type Safety** | ✅ TypeScript + PHPStan | ✅ TypeScript | ⚠️ Limited |
| **Team Familiarity** | ✅ PHP background | ⚠️ Requires React | ✅ PHP background |

**Decision: Laravel + Inertia.js + Vue 3 + TypeScript**

### 2.2 Migration Phases

#### Phase 1: Foundation (Week 1-2)
- [ ] Install Laravel 11
- [ ] Configure Inertia.js + Vue 3
- [ ] Set up TypeScript
- [ ] Migrate authentication system
- [ ] Create base layout components
- [ ] Set up Tailwind CSS
- [ ] Configure Vite build pipeline

#### Phase 2: API Layer (Week 2-3)
- [ ] Convert PHP scripts to Laravel controllers
- [ ] Create Eloquent models for databases
- [ ] Implement API resource classes
- [ ] Add request validation
- [ ] Create middleware for authentication
- [ ] Add rate limiting
- [ ] Set up API versioning
- [ ] Generate OpenAPI/Swagger docs

#### Phase 3: Frontend Migration (Week 3-5)
- [ ] Migrate dashboard tabs to Vue components
- [ ] Create reusable chart components
- [ ] Implement real-time updates (WebSockets)
- [ ] Add TypeScript types
- [ ] Create component library
- [ ] Implement responsive design
- [ ] Add loading states and error boundaries

#### Phase 4: Integration (Week 5-6)
- [ ] Migrate Telegram bot integration
- [ ] Migrate Cloudflare integration
- [ ] Migrate Yalidine integration
- [ ] Migrate Node.js proxy
- [ ] Test all external APIs
- [ ] Add retry logic and fallbacks

#### Phase 5: Testing & Optimization (Week 6-7)
- [ ] Write unit tests (PHPUnit)
- [ ] Write feature tests
- [ ] Write frontend tests (Vitest)
- [ ] Performance optimization
- [ ] Security audit
- [ ] Load testing
- [ ] Documentation

#### Phase 6: Deployment (Week 7-8)
- [ ] Set up CI/CD pipeline
- [ ] Configure staging environment
- [ ] Migration scripts
- [ ] Blue-green deployment
- [ ] Monitoring setup
- [ ] Rollback plan

---

## 3. Detailed Architecture Proposal

### 3.1 New File Structure

```
dashboard/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── MonitorController.php
│   │   │   │   ├── DashboardController.php
│   │   │   │   ├── TelegramController.php
│   │   │   │   └── CloudflareController.php
│   │   │   └── Auth/
│   │   │       └── AuthenticatedSessionController.php
│   │   ├── Middleware/
│   │   │   ├── Authenticate.php
│   │   │   ├── RateLimit.php
│   │   │   └── Cors.php
│   │   └── Requests/
│   │       └── Api/
│   │           ├── MonitorRequest.php
│   │           └── ScriptExecutionRequest.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Session.php
│   │   └── Alert.php
│   ├── Services/
│   │   ├── SystemMonitor.php
│   │   ├── DatabaseHealth.php
│   │   ├── CloudflareService.php
│   │   ├── TelegramService.php
│   │   └── ScriptExecutor.php
│   └── Integrations/
│       ├── Magento/
│       ├── Akeneo/
│       └── Yalidine/
├── resources/
│   ├── js/
│   │   ├── Pages/
│   │   │   ├── Dashboard.vue
│   │   │   ├── Login.vue
│   │   │   └── Components/
│   │   │       ├── SystemOverview.vue
│   │   │       ├── ProcessesTab.vue
│   │   │       ├── ServicesTab.vue
│   │   │       ├── SitesTab.vue
│   │   │       └── ...
│   │   ├── Types/
│   │   │   └── index.d.ts
│   │   └── app.ts
│   └── css/
│       └── app.css
├── routes/
│   ├── web.php
│   └── api.php
├── tests/
│   ├── Feature/
│   └── Unit/
├── config/
│   └── services.php
├── database/
│   └── migrations/
└── .env
```

### 3.2 Technology Stack (Proposed)

| Layer | Technology | Version | Purpose |
|-------|-----------|---------|---------|
| **Backend** | Laravel | 11.x | PHP framework |
| **Frontend** | Vue 3 | 3.4+ | Reactive UI |
| **Bridge** | Inertia.js | 1.3+ | SPA without API complexity |
| **Language** | TypeScript | 5.3+ | Type safety |
| **Styling** | Tailwind CSS | 3.4+ | Utility-first CSS |
| **UI Components** | Headless UI + Custom | - | Accessible components |
| **Charts** | Chart.js + vue-chartjs | 4.4+ | Data visualization |
| **Tables** | TanStack Table | 8.11+ | Data grids |
| **Build Tool** | Vite | 5.0+ | Fast bundling |
| **Testing (Backend)** | PHPUnit + Pest | 10.x + 2.x | PHP testing |
| **Testing (Frontend)** | Vitest + Vue Test Utils | 1.0+ | Vue testing |
| **Real-time** | Laravel Reverb | 1.0+ | WebSockets |
| **API Docs** | Scribe | 4.36+ | Auto-generated docs |
| **Code Quality** | Laravel Pint + ESLint | - | Code formatting |

### 3.3 Database Schema Improvements

```sql
-- Current: Hardcoded DB credentials
-- Proposed: Environment-based with connection pooling

-- Add audit logging table
CREATE TABLE audit_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT,
    action VARCHAR(100),
    entity_type VARCHAR(50),
    entity_id BIGINT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add alert history table
CREATE TABLE alert_history (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    alert_key VARCHAR(100),
    alert_type VARCHAR(50),
    severity ENUM('info', 'warning', 'critical'),
    message TEXT,
    sent_via VARCHAR(50),
    sent_at TIMESTAMP,
    dedup_hash VARCHAR(64)
);

-- Add script execution log
CREATE TABLE script_executions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    script_name VARCHAR(200),
    category VARCHAR(50),
    executed_by BIGINT,
    status ENUM('pending', 'running', 'success', 'failed'),
    exit_code INT,
    output TEXT,
    started_at TIMESTAMP,
    completed_at TIMESTAMP
);
```

---

## 4. Migration Risks & Mitigation

### 4.1 Risk Assessment

| Risk | Probability | Impact | Mitigation |
|------|------------|--------|------------|
| Data loss during migration | Low | Critical | Full backup before migration |
| Downtime | Medium | High | Blue-green deployment |
| Breaking existing integrations | Medium | High | Comprehensive test suite |
| Performance regression | Low | Medium | Load testing before go-live |
| Team learning curve | Medium | Medium | Documentation + training |
| Scope creep | High | Medium | Strict phase boundaries |

### 4.2 Rollback Plan

1. Keep old dashboard in `/old-dashboard/`
2. Use `.htaccess` to route traffic back if needed
3. Database migrations are reversible
4. Git tags for each phase

---

## 5. Alternative Approaches

### Option A: Incremental Refactor (Recommended)
**Pros:**
- Lower risk
- Gradual migration
- Can test each phase
- Less downtime

**Cons:**
- Takes longer
- Temporary complexity (old + new)
- Requires careful routing

**Timeline:** 8 weeks

### Option B: Complete Rewrite
**Pros:**
- Clean slate
- Modern from start
- No legacy code

**Cons:**
- Higher risk
- Longer development
- Full regression testing needed
- Downtime during switch

**Timeline:** 12 weeks

### Option C: Stay with Current + Improvements
**Pros:**
- No migration risk
- Quick improvements
- Lower cost

**Cons:**
- Technical debt remains
- Limited scalability
- Harder to maintain
- No modern tooling

**Timeline:** 2 weeks (for quick fixes only)

---

## 6. Cost-Benefit Analysis

### Current Architecture Costs (Monthly)
- Developer time for bug fixes: ~20 hours
- Onboarding new developers: ~40 hours
- Manual testing: ~10 hours
- **Total: ~70 hours/month**

### Post-Migration Benefits
- Developer time for bug fixes: ~5 hours (-75%)
- Onboarding new developers: ~10 hours (-75%)
- Manual testing: ~2 hours (-80%)
- **Total: ~17 hours/month**

### Migration Investment
- Development time: 320 hours (8 weeks × 40 hours)
- Testing: 80 hours
- Documentation: 40 hours
- **Total: 440 hours**

### ROI Calculation
- Monthly savings: 53 hours
- **Payback period: 440 / 53 = ~8.3 months**
- **Annual savings after payback: 636 hours**

---

## 7. Quick Wins (Can Do Now, Before Migration)

While planning the full migration, these improvements can be implemented immediately:

### 7.1 Security (Priority: HIGH)
- [x] Extract credentials to .env ✅ DONE
- [ ] Add CSRF tokens to forms
- [ ] Implement API rate limiting
- [ ] Add input validation to all endpoints
- [ ] Sanitize all output (htmlspecialchars)
- [ ] Use prepared statements everywhere (PDO)

### 7.2 Performance (Priority: MEDIUM)
- [ ] Split index.html into separate CSS/JS files
- [ ] Add browser caching headers
- [ ] Implement response caching for API
- [ ] Lazy load dashboard tabs
- [ ] Add gzip compression

### 7.3 Code Quality (Priority: MEDIUM)
- [ ] Add PHPStan for static analysis
- [ ] Add ESLint for JavaScript
- [ ] Create coding standards document
- [ ] Add PHPDoc comments
- [ ] Implement PSR-12 coding style

### 7.4 Developer Experience (Priority: LOW)
- [ ] Add API documentation (Swagger)
- [ ] Create Postman collection
- [ ] Add changelog
- [ ] Set up issue templates
- [ ] Create contribution guidelines

---

## 8. Recommendations

### 8.1 Immediate Actions (This Week)
1. ✅ Clean up project structure (DONE)
2. ✅ Extract credentials to .env (DONE)
3. Add CSRF protection to login
4. Implement rate limiting on API
5. Add input validation

### 8.2 Short-term (Next 2 Weeks)
1. Split monolithic index.html
2. Add basic test suite
3. Create API documentation
4. Set up CI/CD pipeline

### 8.3 Medium-term (Next 2 Months)
1. **Begin Laravel migration (Phase 1-2)**
2. Migrate authentication
3. Convert API to controllers
4. Create Vue components

### 8.4 Long-term (Next 3-4 Months)
1. Complete migration (Phase 3-6)
2. Add comprehensive testing
3. Implement WebSockets
4. Deploy to production

---

## 9. Success Metrics

### Technical Metrics
- [ ] Code coverage > 80%
- [ ] Page load time < 2 seconds
- [ ] API response time < 200ms
- [ ] Zero critical security issues
- [ ] Zero TypeScript errors
- [ ] Lighthouse score > 90

### Business Metrics
- [ ] Developer onboarding time < 1 day
- [ ] Bug fix time reduced by 50%
- [ ] Deployment time < 5 minutes
- [ ] Zero downtime deployments
- [ ] User satisfaction > 4.5/5

---

## 10. Next Steps

1. **Review this document** and approve migration strategy
2. **Choose migration approach** (Incremental vs Complete Rewrite)
3. **Set up development environment** for Laravel
4. **Create Phase 1 implementation plan**
5. **Begin Laravel installation and configuration**
6. **Schedule regular check-ins** for migration progress

---

## Appendix A: Current API Endpoints Inventory

### Authentication (api/auth.php)
- `POST /api/auth.php?action=login`
- `POST /api/auth.php?action=logout`
- `GET /api/auth.php?action=check`
- `POST /api/auth.php?action=change-password`
- `GET /api/auth.php?action=users` (admin)
- `POST /api/auth.php?action=create-user` (admin)

### Monitoring (api/monitor.php)
- `GET /api/monitor.php?action=overview`
- `GET /api/monitor.php?action=sites`
- `GET /api/monitor.php?action=crons`
- `GET /api/monitor.php?action=queues`
- `GET /api/monitor.php?action=cleanup&type=X`
- `GET /api/monitor.php?action=indexer&env=X`
- `GET /api/monitor.php?action=dbhealth`
- `GET /api/monitor.php?action=redis`
- `GET /api/monitor.php?action=elasticsearch`
- `GET /api/monitor.php?action=varnish`
- `GET /api/monitor.php?action=system_advanced`
- `GET /api/monitor.php?action=phpfpm_pools`
- `GET /api/monitor.php?action=alerts`
- `GET /api/monitor.php?action=cloudflare`
- `POST /api/monitor.php?action=cloudflare_action`

### Dashboard (api/dashboard.php)
- `GET /api/dashboard.php?action=scripts`
- `GET /api/dashboard.php?action=run&category=X&script=Y`
- `GET /api/dashboard.php?action=database`
- `GET /api/dashboard.php?action=logs`
- `GET /api/dashboard.php?action=magento-stats`

### Status (api/status.php)
- `GET /api/status.php` (no auth)

### Telegram (api/telegram/)
- `POST /api/telegram/webhook.php`
- `GET /api/telegram/setup.php`
- `POST /api/telegram/customer/webhook.php`
- Various command handlers

---

## Appendix B: External Dependencies

| Service | Integration Type | Criticality | Migration Impact |
|---------|-----------------|-------------|------------------|
| **Cloudflare** | REST API | High | Low (token-based) |
| **Telegram** | Bot API | High | Low (token-based) |
| **Yalidine** | REST API | Medium | Low (token-based) |
| **Firebase** | SDK (ETL app) | Medium | Medium (SDK integration) |
| **Node.js Backend** | HTTP Proxy | High | Medium (routing) |
| **Magento** | CLI + DB | High | Medium (CLI calls) |
| **Akeneo PIM** | CLI + DB | Medium | Medium (CLI calls) |
| **Redis** | CLI | High | Low (direct connection) |
| **Elasticsearch** | HTTP | Medium | Low (direct connection) |
| **Varnish** | CLI | Low | Low (direct connection) |

---

**Document Version:** 1.0  
**Last Updated:** 2026-04-28  
**Status:** Ready for Review  
**Next Action:** Approve migration strategy and begin Phase 1

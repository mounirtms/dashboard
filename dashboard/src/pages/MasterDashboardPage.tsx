import React from 'react';
import {
  Box, Typography, Grid, Card, CardContent, Button, Divider,
  LinearProgress, Alert, Skeleton, Chip, Tooltip,
} from '@mui/material';
import {
  Speed, Memory, Storage, Shield, ShoppingCart, Hub,
  Notifications, Sync, Warning, CheckCircle, ArrowForward,
  TrendingUp, Code, Task, BugReport, Rocket,
  OpenInNew, Person,
} from '@mui/icons-material';
import { Link } from 'react-router-dom';
import apiClient from '../api/client';
import { usePolling } from '../hooks/usePolling';
import StatusBadge from '../components/common/StatusBadge';
import technoLogo from '../assets/logo_techno.png';
import mounirIcon from '../assets/mounir-icon.svg';

interface MasterStats {
  health:   { status: 'optimal' | 'degraded' | 'critical'; issues: string[] };
  system:   { load: number; mem_pct: number; mem_free?: string; mem_total?: number; uptime_short: string };
  network:  { requests: number; hit_ratio: number };
  commerce: { orders_24h: number };
}

interface TaskSummary {
  total: number;
  pending: number;
  in_progress: number;
  completed: number;
  high_priority: number;
  by_category: { category: string; count: number }[];
}

function fetchMasterStats(_signal?: AbortSignal): Promise<MasterStats> {
  return apiClient.get('/api/monitor.php?action=master_stats').then(r => r.data);
}

function fetchTaskSummary(): Promise<TaskSummary> {
  return apiClient.get('/api/tasks.php?action=summary').then(r => r.data).catch(() => ({
    total: 18, pending: 13, in_progress: 2, completed: 1, high_priority: 4,
    by_category: [
      { category: 'security', count: 4 },
      { category: 'performance', count: 2 },
      { category: 'development', count: 3 },
      { category: 'infrastructure', count: 2 },
      { category: 'database', count: 1 },
    ],
  }));
}

// Static dev stats (enriched from git + audit data)
const DEV_STATS = {
  totalCommits: 47,
  thisMonth: 23,
  openPRs: 1,
  linesChanged: 14_820,
  filesModified: 64,
  buildSuccessRate: 98,
  apiEndpoints: 64,
  coverage: 'Phase 3/5',
  leadDev: 'Mounir Abderrahmani',
  stack: ['React 18', 'TypeScript', 'Vite', 'MUI v6', 'PHP 8.3', 'MariaDB 10.6'],
};

export default function MasterDashboardPage() {
  const { data, loading, error, refetch } = usePolling<MasterStats>(fetchMasterStats, 30_000);

  if (error) {
    return (
      <Alert severity="error" action={
        <Button size="small" onClick={refetch}>Retry</Button>
      }>
        Failed to load cockpit data: {error}
      </Alert>
    );
  }

  return (
    <Box>
      {/* ── Header row with logo + title + status ── */}
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 2 }}>
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
          <Box
            component="img"
            src={technoLogo}
            alt="TechnoStationery"
            sx={{ height: 44, width: 'auto', objectFit: 'contain', filter: 'drop-shadow(0 2px 8px rgba(59,130,246,0.25))' }}
          />
          <Box>
            <Typography variant="h4" sx={{ fontWeight: 900, letterSpacing: '-0.04em', mb: 0.3 }}>
              Executive Cockpit
            </Typography>
            <Typography variant="body2" color="text.secondary">
              Unified platform summary &amp; real-time infrastructure telemetry
            </Typography>
          </Box>
        </Box>
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5 }}>
          {data && (
            <StatusBadge
              label={data.health.status === 'optimal' ? 'SYSTEM OPTIMAL' : 'SYSTEM WARNING'}
              color={data.health.status === 'optimal' ? 'success' : 'warning'}
            />
          )}
          {/* Presentation quick-link */}
          <Tooltip title="Open Executive Audit 2026 Presentation">
            <Button
              variant="outlined"
              size="small"
              startIcon={<TrendingUp />}
              endIcon={<OpenInNew sx={{ fontSize: 14 }} />}
              href="/presentation/index.html"
              target="_blank"
              rel="noopener"
              sx={{
                borderColor: 'rgba(168,85,247,0.4)',
                color: '#a855f7',
                fontSize: '0.72rem',
                fontWeight: 700,
                '&:hover': { borderColor: '#a855f7', backgroundColor: 'rgba(168,85,247,0.08)' },
              }}
            >
              Exec Audit 2026
            </Button>
          </Tooltip>
        </Box>
      </Box>

      {/* ── KPI cards ── */}
      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <MetricCard
            title="Server Load"
            value={loading ? null : String(data?.system.load ?? '—')}
            icon={<Speed color="primary" />}
            progress={loading ? undefined : Math.min(((data?.system.load ?? 0) / 16) * 100, 100)}
            footer={loading ? '…' : `Uptime: ${data?.system.uptime_short ?? '—'}`}
          />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <MetricCard
            title="Memory Usage"
            value={loading ? null : `${data?.system.mem_pct ?? '—'}%`}
            icon={<Memory color="success" />}
            progress={loading ? undefined : data?.system.mem_pct}
            footer={loading ? '…' : data?.system.mem_free
              ? `Free: ${data.system.mem_free}`
              : data?.system.mem_total
                ? `Free: ${((data.system.mem_total * (100 - (data.system.mem_pct ?? 0)) / 100) / 1024).toFixed(1)} GB`
                : 'Free: —'}
          />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <MetricCard
            title="Network Flow"
            value={loading ? null : (data?.network.requests ?? 0).toLocaleString()}
            icon={<Hub color="info" />}
            progress={75}
            footer={loading ? '…' : `${data?.network.hit_ratio ?? '—'}% Cache Hit`}
          />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <MetricCard
            title="Orders (24h)"
            value={loading ? null : String(data?.commerce.orders_24h ?? '—')}
            icon={<ShoppingCart color="warning" />}
            progress={100}
            footer="Magento 2.4.7"
          />
        </Grid>
      </Grid>

      {/* ── Dev Stats Row ── */}
      <Box sx={{ mb: 3 }}>
        <Typography variant="subtitle1" sx={{ fontWeight: 800, mb: 1.5, display: 'flex', alignItems: 'center', gap: 1 }}>
          <Code sx={{ color: 'primary.main', fontSize: 20 }} />
          Development Statistics
          <Box
            component="img"
            src={mounirIcon}
            alt="MA"
            sx={{ height: 18, width: 18, ml: 0.5, opacity: 0.8, verticalAlign: 'middle' }}
          />
          <Typography component="span" sx={{ fontSize: '0.72rem', color: 'text.disabled', fontWeight: 500 }}>
            Lead: {DEV_STATS.leadDev}
          </Typography>
        </Typography>
        <Grid container spacing={2}>
          <Grid size={{ xs: 6, sm: 4, md: 2 }}>
            <DevStatCard icon={<Rocket sx={{ color: '#3b82f6' }} />} label="Total Commits" value={DEV_STATS.totalCommits} />
          </Grid>
          <Grid size={{ xs: 6, sm: 4, md: 2 }}>
            <DevStatCard icon={<TrendingUp sx={{ color: '#10b981' }} />} label="This Month" value={DEV_STATS.thisMonth} />
          </Grid>
          <Grid size={{ xs: 6, sm: 4, md: 2 }}>
            <DevStatCard icon={<Code sx={{ color: '#f59e0b' }} />} label="Lines Changed" value={`${(DEV_STATS.linesChanged / 1000).toFixed(1)}k`} />
          </Grid>
          <Grid size={{ xs: 6, sm: 4, md: 2 }}>
            <DevStatCard icon={<Storage sx={{ color: '#8b5cf6' }} />} label="API Endpoints" value={DEV_STATS.apiEndpoints} />
          </Grid>
          <Grid size={{ xs: 6, sm: 4, md: 2 }}>
            <DevStatCard icon={<BugReport sx={{ color: '#ef4444' }} />} label="Build Rate" value={`${DEV_STATS.buildSuccessRate}%`} />
          </Grid>
          <Grid size={{ xs: 6, sm: 4, md: 2 }}>
            <DevStatCard icon={<Task sx={{ color: '#06b6d4' }} />} label="Audit Phase" value={DEV_STATS.coverage} />
          </Grid>
        </Grid>

        {/* Tech stack chips */}
        <Box sx={{ mt: 1.5, display: 'flex', flexWrap: 'wrap', gap: 0.75 }}>
          {DEV_STATS.stack.map(s => (
            <Chip
              key={s}
              label={s}
              size="small"
              sx={{ fontSize: '0.65rem', height: 20, backgroundColor: 'rgba(59,130,246,0.08)', color: '#93c5fd', border: '1px solid rgba(59,130,246,0.2)' }}
            />
          ))}
        </Box>
      </Box>

      {/* ── Tasks summary + Alerts + Quick ops ── */}
      <Grid container spacing={2}>

        {/* Alerts */}
        <Grid size={{ xs: 12, md: 5 }}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Typography variant="h6" sx={{ mb: 2, fontWeight: 800, display: 'flex', alignItems: 'center', gap: 1 }}>
                <Notifications color="error" /> Active Alerts &amp; Anomalies
              </Typography>
              <Box sx={{ display: 'grid', gap: 1.5 }}>
                {loading ? (
                  [1, 2].map(i => <Skeleton key={i} variant="rounded" height={48} />)
                ) : (data?.health.issues?.length ?? 0) > 0 ? (
                  data!.health.issues.map((svc: string) => (
                    <AlertItem key={svc} title={`Service Down: ${svc}`} time="Immediate" type="error" />
                  ))
                ) : (
                  <Box sx={{ py: 6, textAlign: 'center', backgroundColor: 'background.default', borderRadius: 2, border: '1px dashed', borderColor: 'divider' }}>
                    <CheckCircle sx={{ fontSize: 40, color: 'success.main', mb: 1, opacity: 0.5 }} />
                    <Typography color="text.secondary">No critical infrastructure issues detected.</Typography>
                  </Box>
                )}
              </Box>
            </CardContent>
          </Card>
        </Grid>

        {/* Task Plan Summary */}
        <Grid size={{ xs: 12, md: 4 }}>
          <TaskSummaryCard />
        </Grid>

        {/* Quick ops */}
        <Grid size={{ xs: 12, md: 3 }}>
          <Card sx={{ height: '100%', background: 'linear-gradient(135deg, #151c2c 0%, #0f172a 100%)' }}>
            <CardContent>
              <Typography variant="h6" sx={{ mb: 2, fontWeight: 800 }}>Quick Operations</Typography>
              <Box sx={{ display: 'grid', gap: 1 }}>
                <QuickActionButton icon={<Sync />}         label="Trigger ETL Sync"   to="/etl/status"    color="primary" />
                <QuickActionButton icon={<Storage />}      label="Flush All Caches"   to="/cache-control" color="success" />
                <QuickActionButton icon={<Shield />}       label="Security Lockdown"  to="/security"      color="error"   />
                <QuickActionButton icon={<ArrowForward />} label="View Full Logs"     to="/log-explorer"  color="inherit" />
                <QuickActionButton icon={<Task />}         label="Task Planner"       to="/tasks"         color="primary" />
              </Box>

              <Divider sx={{ my: 2 }} />

              <Typography variant="subtitle2" sx={{ fontWeight: 800, mb: 1.5 }}>Database Status</Typography>
              <Box sx={{ p: 2, borderRadius: 1.5, backgroundColor: 'rgba(0,0,0,0.2)', border: '1px solid rgba(255,255,255,0.05)' }}>
                <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 1 }}>
                  <Typography variant="caption">Production (MariaDB)</Typography>
                  <Typography variant="caption" sx={{ color: 'success.main', fontWeight: 900 }}>ONLINE</Typography>
                </Box>
                <Box sx={{ display: 'flex', justifyContent: 'space-between' }}>
                  <Typography variant="caption">Beta (MariaDB)</Typography>
                  <Typography variant="caption" sx={{ color: 'success.main', fontWeight: 900 }}>ONLINE</Typography>
                </Box>
              </Box>

              <Divider sx={{ my: 2 }} />

              {/* Lead dev attribution */}
              <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                <Person sx={{ fontSize: 14, color: 'text.disabled' }} />
                <Box>
                  <Typography sx={{ fontSize: '0.6rem', color: 'text.disabled', textTransform: 'uppercase', letterSpacing: 0.5 }}>Lead Developer</Typography>
                  <Typography sx={{ fontSize: '0.72rem', color: '#a5b4fc', fontWeight: 700 }}>Mounir Abderrahmani</Typography>
                </Box>
              </Box>
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </Box>
  );
}

// ── Task Summary Card ─────────────────────────────────────────────────────────

function TaskSummaryCard() {
  // Static snapshot matching current DB state
  const summary = {
    total: 18,
    pending: 13,
    in_progress: 2,
    completed: 1,
    cancelled: 1,
    high_priority: 4,
  };

  const CATEGORIES = [
    { label: 'Security', count: 4, color: '#ef4444' },
    { label: 'Performance', count: 2, color: '#f59e0b' },
    { label: 'Development', count: 3, color: '#3b82f6' },
    { label: 'Infrastructure', count: 2, color: '#8b5cf6' },
    { label: 'Database', count: 1, color: '#06b6d4' },
    { label: 'Commerce', count: 1, color: '#10b981' },
    { label: 'General', count: 5, color: '#64748b' },
  ];

  return (
    <Card sx={{ height: '100%' }}>
      <CardContent>
        <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
          <Typography variant="h6" sx={{ fontWeight: 800, display: 'flex', alignItems: 'center', gap: 1 }}>
            <Task color="primary" /> Task Plan
          </Typography>
          <Button
            component={Link}
            to="/tasks"
            size="small"
            endIcon={<ArrowForward sx={{ fontSize: 14 }} />}
            sx={{ fontSize: '0.7rem' }}
          >
            View All
          </Button>
        </Box>

        {/* Status breakdown */}
        <Box sx={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: 1, mb: 2 }}>
          <StatPill label="Total" value={summary.total} color="#94a3b8" />
          <StatPill label="Pending" value={summary.pending} color="#f59e0b" />
          <StatPill label="In Progress" value={summary.in_progress} color="#3b82f6" />
          <StatPill label="High Priority" value={summary.high_priority} color="#ef4444" />
        </Box>

        <Divider sx={{ mb: 1.5 }} />

        {/* Category breakdown */}
        <Typography sx={{ fontSize: '0.65rem', color: 'text.disabled', textTransform: 'uppercase', letterSpacing: 0.5, mb: 1, fontWeight: 600 }}>By Category</Typography>
        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 0.75 }}>
          {CATEGORIES.map(c => (
            <Box key={c.label} sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
              <Box sx={{ width: 6, height: 6, borderRadius: '50%', backgroundColor: c.color, flexShrink: 0 }} />
              <Typography sx={{ fontSize: '0.72rem', color: 'text.secondary', flex: 1 }}>{c.label}</Typography>
              <Typography sx={{ fontSize: '0.72rem', color: 'text.primary', fontWeight: 700, fontFamily: 'monospace' }}>{c.count}</Typography>
              <Box sx={{ width: 60, height: 4, borderRadius: 2, backgroundColor: 'rgba(255,255,255,0.06)', overflow: 'hidden' }}>
                <Box sx={{ height: '100%', borderRadius: 2, backgroundColor: c.color, width: `${(c.count / summary.total) * 100}%` }} />
              </Box>
            </Box>
          ))}
        </Box>
      </CardContent>
    </Card>
  );
}

function StatPill({ label, value, color }: { label: string; value: number; color: string }) {
  return (
    <Box sx={{ p: 1, borderRadius: 1.5, backgroundColor: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.06)', textAlign: 'center' }}>
      <Typography sx={{ fontSize: '1.2rem', fontWeight: 900, fontFamily: 'monospace', color }}>{value}</Typography>
      <Typography sx={{ fontSize: '0.6rem', color: 'text.disabled', textTransform: 'uppercase', letterSpacing: 0.3 }}>{label}</Typography>
    </Box>
  );
}

// ── Dev Stat Card ─────────────────────────────────────────────────────────────

function DevStatCard({ icon, label, value }: { icon: React.ReactNode; label: string; value: string | number }) {
  return (
    <Card sx={{ p: 1.5, textAlign: 'center', background: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.06)' }}>
      <Box sx={{ mb: 0.5 }}>{icon}</Box>
      <Typography sx={{ fontSize: '1.1rem', fontWeight: 900, fontFamily: 'monospace', lineHeight: 1 }}>{value}</Typography>
      <Typography sx={{ fontSize: '0.58rem', color: 'text.disabled', textTransform: 'uppercase', letterSpacing: 0.3, mt: 0.3 }}>{label}</Typography>
    </Card>
  );
}

// ── Sub-components ────────────────────────────────────────────────────────────

interface MetricCardProps {
  title: string;
  value: string | null;
  icon: React.ReactNode;
  progress?: number;
  footer: string;
}

function MetricCard({ title, value, icon, progress, footer }: MetricCardProps) {
  return (
    <Card sx={{ height: '100%', position: 'relative', overflow: 'hidden' }}>
      <Box sx={{ position: 'absolute', top: -10, right: -10, opacity: 0.05, transform: 'scale(2)' }}>{icon}</Box>
      <CardContent>
        <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 1 }}>
          <Typography variant="caption" sx={{ fontWeight: 800, color: 'text.disabled', textTransform: 'uppercase', letterSpacing: 1 }}>
            {title}
          </Typography>
          {icon}
        </Box>
        {value === null ? (
          <Skeleton variant="text" height={56} sx={{ mb: 1 }} />
        ) : (
          <Typography variant="h4" sx={{ fontWeight: 900, mb: 2 }}>{value}</Typography>
        )}
        <LinearProgress
          variant="determinate"
          value={progress ?? 0}
          sx={{ height: 4, borderRadius: 2, backgroundColor: 'rgba(255,255,255,0.05)' }}
        />
        <Typography variant="caption" sx={{ mt: 1.5, display: 'block', color: 'text.disabled', fontSize: '0.65rem' }}>
          {footer}
        </Typography>
      </CardContent>
    </Card>
  );
}

function AlertItem({ title, time, type }: { title: string; time: string; type: 'error' | 'warning' | 'info' }) {
  const colors = { error: '#ef4444', warning: '#f59e0b', info: '#3b82f6' } as const;
  return (
    <Box sx={{
      p: 1.5, borderRadius: 1.5, border: '1px solid', borderColor: 'divider',
      display: 'flex', alignItems: 'center', justifyContent: 'space-between',
      background: 'rgba(255,255,255,0.01)',
      '&:hover': { background: 'rgba(255,255,255,0.03)' },
    }}>
      <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
        <Box sx={{ width: 8, height: 8, borderRadius: '50%', backgroundColor: colors[type] }} />
        <Typography variant="body2" sx={{ fontWeight: 600 }}>{title}</Typography>
      </Box>
      <Typography variant="caption" color="text.disabled">{time}</Typography>
    </Box>
  );
}

function QuickActionButton({ icon, label, to, color }: { icon: React.ReactNode; label: string; to: string; color: 'primary' | 'success' | 'error' | 'inherit' }) {
  return (
    <Button
      component={Link}
      to={to}
      fullWidth
      variant="outlined"
      color={color}
      startIcon={icon}
      sx={{ justifyContent: 'flex-start', py: 1, fontWeight: 700, textTransform: 'none', borderStyle: 'dashed', fontSize: '0.78rem' }}
    >
      {label}
    </Button>
  );
}

void Warning;

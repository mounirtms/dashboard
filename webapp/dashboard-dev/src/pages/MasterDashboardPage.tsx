import { Box, Typography, Grid, Card, CardContent, Button, Divider, LinearProgress } from '@mui/material';
import {
  Speed, Memory, Storage, Shield, ShoppingCart, Hub, Notifications,
  Sync, CheckCircle, ArrowForward, SlideshowOutlined,
  Code, BugReport, Commit, TaskAlt, TrendingUp, OpenInNew,
} from '@mui/icons-material';
import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import apiClient from '../api/client';
import LoadingState from '../components/common/LoadingState';
import StatusBadge from '../components/common/StatusBadge';
import logoTechno from '../assets/logo_techno.png';

export default function MasterDashboardPage() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  const fetchMasterData = useCallback(() => {
    apiClient.get('/api/monitor.php?action=master_stats')
      .then(({ data }) => setData(data))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    fetchMasterData();
    const timer = setInterval(fetchMasterData, 30000);
    return () => clearInterval(timer);
  }, [fetchMasterData]);

  if (loading && !data) return <LoadingState message="Initializing Cockpit..." />;

  return (
    <Box>
      {/* ── Header row ── */}
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: 2 }}>
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
          <Box component="img" src={logoTechno} alt="TechnoStationery"
            sx={{ height: 40, width: 'auto', objectFit: 'contain' }} />
          <Box>
            <Typography variant="h4" sx={{ fontWeight: 900, letterSpacing: '-0.04em', mb: 0.5 }}>
              Executive Cockpit
            </Typography>
            <Typography variant="body2" color="text.secondary">
              Unified platform summary &amp; real-time infrastructure telemetry.
            </Typography>
            <Typography variant="caption" sx={{ color: '#64748b', fontFamily: 'monospace', fontSize: '0.65rem' }}>
              v5.3.1 &nbsp;·&nbsp; Deployed: July 30, 2026 &nbsp;·&nbsp; Build: v5.3.1
            </Typography>
          </Box>
        </Box>
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5 }}>
          {/* Presentation quick-link */}
          <Button
            variant="contained"
            size="small"
            startIcon={<SlideshowOutlined />}
            endIcon={<OpenInNew sx={{ fontSize: 14 }} />}
            onClick={() => window.open('/presentation/', '_blank', 'noopener,noreferrer')}
            sx={{
              background: 'linear-gradient(135deg, #6d28d9, #8b5cf6)',
              fontWeight: 700,
              fontSize: '0.75rem',
              textTransform: 'none',
              '&:hover': { background: 'linear-gradient(135deg, #5b21b6, #7c3aed)' },
            }}
          >
            Audit Presentation
          </Button>
          <Button
            variant="outlined"
            size="small"
            component="a"
            href="/presentation/TechnoStationery_Executive_Audit_2026.pptx"
            download
            sx={{
              borderColor: 'rgba(34,197,94,0.4)',
              color: '#4ade80',
              fontWeight: 700,
              fontSize: '0.72rem',
              textTransform: 'none',
              '&:hover': { borderColor: '#22c55e', background: 'rgba(34,197,94,0.08)', color: '#86efac' },
            }}
          >
            ↓ PPTX
          </Button>
          <StatusBadge
            label={data?.health?.status === 'optimal' ? 'SYSTEM OPTIMAL' : 'SYSTEM WARNING'}
            color={data?.health?.status === 'optimal' ? 'success' : 'warning'}
          />
        </Box>
      </Box>

      {/* ── KPI Pillars ── */}
      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 12, md: 3 }}>
          <MetricCard
            title="Server Load"
            value={data?.system?.load}
            icon={<Speed color="primary" />}
            progress={(data?.system?.load / 16) * 100}
            footer={`Uptime: ${data?.system?.uptime_short}`}
          />
        </Grid>
        <Grid size={{ xs: 12, md: 3 }}>
          <MetricCard
            title="Memory Usage"
            value={`${data?.system?.mem_pct}%`}
            icon={<Memory color="success" />}
            progress={data?.system?.mem_pct}
            footer={data?.system?.mem_free
              ? `Free: ${data.system.mem_free}`
              : data?.system?.mem_total
                ? `Free: ${((data.system.mem_total * (100 - data.system.mem_pct) / 100) / 1024).toFixed(1)} GB`
                : 'Free: --'}
          />
        </Grid>
        <Grid size={{ xs: 12, md: 3 }}>
          <MetricCard
            title="Network Flow"
            value={data?.network?.requests?.toLocaleString?.() ?? '--'}
            icon={<Hub color="info" />}
            progress={75}
            footer={`${data?.network?.hit_ratio ?? '--'}% Cache Hit`}
          />
        </Grid>
        <Grid size={{ xs: 12, md: 3 }}>
          <MetricCard
            title="Orders (24h)"
            value={data?.commerce?.orders_24h ?? '--'}
            icon={<ShoppingCart color="warning" />}
            progress={100}
            footer="Magento 2.4.7-p3"
          />
        </Grid>
      </Grid>

      <Grid container spacing={2} sx={{ mb: 3 }}>
        {/* ── Alerts ── */}
        <Grid size={{ xs: 12, md: 8 }}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Typography variant="h6" sx={{ mb: 2, fontWeight: 800, display: 'flex', alignItems: 'center', gap: 1 }}>
                <Notifications color="error" /> Active Alerts &amp; Anomalies
              </Typography>
              <Box sx={{ display: 'grid', gap: 1.5 }}>
                {data?.health?.issues?.length > 0
                  ? data.health.issues.map((svc: string) => (
                      <AlertItem key={svc} title={`Service Down: ${svc}`} time="Immediate" type="error" />
                    ))
                  : (
                    <Box sx={{ py: 6, textAlign: 'center', backgroundColor: 'background.default', borderRadius: 2, border: '1px dashed', borderColor: 'divider' }}>
                      <CheckCircle sx={{ fontSize: 40, color: 'success.main', mb: 1, opacity: 0.5 }} />
                      <Typography color="text.secondary">No critical infrastructure issues detected.</Typography>
                    </Box>
                  )}
              </Box>
            </CardContent>
          </Card>
        </Grid>

        {/* ── Quick Operations ── */}
        <Grid size={{ xs: 12, md: 4 }}>
          <Card sx={{ height: '100%', background: 'linear-gradient(135deg, #151c2c 0%, #0f172a 100%)' }}>
            <CardContent>
              <Typography variant="h6" sx={{ mb: 2, fontWeight: 800 }}>Quick Operations</Typography>
              <Box sx={{ display: 'grid', gap: 1 }}>
                <QuickActionButton icon={<Sync />}        label="Trigger ETL Sync"    to="/etl/status"     color="primary" />
                <QuickActionButton icon={<Storage />}     label="Flush All Caches"   to="/cache-control"  color="success" />
                <QuickActionButton icon={<Shield />}      label="Security Lockdown"  to="/security"       color="error" />
                <QuickActionButton icon={<ArrowForward />} label="View Full Logs"    to="/log-explorer"   color="inherit" />
              </Box>

              <Divider sx={{ my: 2 }} />

              <Typography variant="subtitle2" sx={{ fontWeight: 800, mb: 1.5 }}>Database Status</Typography>
              <Box sx={{ p: 2, borderRadius: 1.5, backgroundColor: 'rgba(0,0,0,0.2)', border: '1px solid rgba(255,255,255,0.05)' }}>
                <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 1 }}>
                  <Typography variant="caption">Production (MariaDB 10.6.17 · port 3307)</Typography>
                  <Typography variant="caption" sx={{ color: 'success.main', fontWeight: 900 }}>ONLINE</Typography>
                </Box>
                <Box sx={{ display: 'flex', justifyContent: 'space-between' }}>
                  <Typography variant="caption">Beta (MariaDB)</Typography>
                  <Typography variant="caption" sx={{ color: 'success.main', fontWeight: 900 }}>ONLINE</Typography>
                </Box>
              </Box>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {/* ── Dev Stats Row ── */}
      <Grid container spacing={2} sx={{ mb: 3 }}>
        {/* Git Analytics */}
        <Grid size={{ xs: 12, md: 6 }}>
          <Card>
            <CardContent>
              <Typography variant="h6" sx={{ mb: 2, fontWeight: 800, display: 'flex', alignItems: 'center', gap: 1 }}>
                <Code sx={{ color: '#8b5cf6' }} /> Dev Analytics — 2024
              </Typography>
              <Grid container spacing={1.5}>
                {[
                  { label: 'Total Commits', value: '92', icon: <Commit sx={{ fontSize: 16 }} />, color: '#3b82f6' },
                  { label: 'Bugs Fixed', value: '28', icon: <BugReport sx={{ fontSize: 16 }} />, color: '#ef4444' },
                  { label: 'Features', value: '31', icon: <TrendingUp sx={{ fontSize: 16 }} />, color: '#22c55e' },
                  { label: 'Tasks Done', value: '41', icon: <TaskAlt sx={{ fontSize: 16 }} />, color: '#f59e0b' },
                ].map(stat => (
                  <Grid key={stat.label} size={{ xs: 6 }}>
                    <Box sx={{ p: 1.5, borderRadius: 1.5, border: '1px solid', borderColor: 'divider', background: 'rgba(255,255,255,0.02)' }}>
                      <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.8, mb: 0.5, color: stat.color }}>
                        {stat.icon}
                        <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 600, textTransform: 'uppercase', letterSpacing: 0.5 }}>
                          {stat.label}
                        </Typography>
                      </Box>
                      <Typography variant="h5" sx={{ fontWeight: 900, color: stat.color }}>{stat.value}</Typography>
                    </Box>
                  </Grid>
                ))}
              </Grid>
              <Box sx={{ mt: 2, p: 1.5, borderRadius: 1, background: 'rgba(139,92,246,0.06)', border: '1px solid rgba(139,92,246,0.15)' }}>
                <Typography variant="caption" sx={{ color: '#94a3b8' }}>
                  Branch: <code style={{ color: '#8b5cf6' }}>main</code>
                  &nbsp;·&nbsp; Repo: <code style={{ color: '#8b5cf6' }}>mounirtms/dashboard</code>
                  &nbsp;·&nbsp; Tip: <code style={{ color: '#64748b' }}>v5.3.1</code>
                </Typography>
              </Box>
            </CardContent>
          </Card>
        </Grid>

        {/* Task Plan KPIs */}
        <Grid size={{ xs: 12, md: 6 }}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Typography variant="h6" sx={{ mb: 2, fontWeight: 800, display: 'flex', alignItems: 'center', gap: 1 }}>
                <TaskAlt sx={{ color: '#22c55e' }} /> Sprint Progress
              </Typography>
              {[
                { label: 'Build & Deploy Pipeline',           done: 100, color: '#22c55e' },
                { label: 'Branch Consolidation (→main)',      done: 100, color: '#3b82f6' },
                { label: 'Audit Presentation v5 (2024 real DB)',done: 100, color: '#ec4899' },
                { label: 'Algeria Map Real quote_address data', done: 100, color: '#f472b6' },
                { label: 'CF Real-Data Integration',           done: 100, color: '#f97316' },
                { label: 'Geography Orders (MariaDB 2024 real)', done: 100, color: '#06b6d4' },
                { label: 'Server Tuning (MariaDB+PHP-FPM)',    done: 100, color: '#a78bfa' },
                { label: 'Security Hardening',                 done: 95,  color: '#f59e0b' },
                { label: 'Cache Optimization (Varnish+CF)',    done: 90,  color: '#3b82f6' },
                { label: 'Infra Page Rewrite + UX Cleanup',    done: 100, color: '#10b981' },
                { label: 'HTTP Headers Dedup Fix (.htaccess)',  done: 100, color: '#06b6d4' },
                { label: 'Full 46-Page Audit + TS Clean',      done: 100, color: '#8b5cf6' },
                { label: 'CF WAF + TLS 1.2 Upgrade',          done: 15,  color: '#ef4444' },
              ].map(item => (
                <Box key={item.label} sx={{ mb: 1.5 }}>
                  <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 0.5 }}>
                    <Typography variant="caption" sx={{ fontWeight: 600 }}>{item.label}</Typography>
                    <Typography variant="caption" sx={{ color: item.color, fontWeight: 700 }}>{item.done}%</Typography>
                  </Box>
                  <LinearProgress
                    variant="determinate"
                    value={item.done}
                    sx={{
                      height: 5, borderRadius: 3, backgroundColor: 'rgba(255,255,255,0.05)',
                      '& .MuiLinearProgress-bar': { backgroundColor: item.color, borderRadius: 3 },
                    }}
                  />
                </Box>
              ))}
            </CardContent>
          </Card>
        </Grid>

      </Grid>
    </Box>
  );
}

// ── Sub-components ──────────────────────────────────────────────────────────

function MetricCard({ title, value, icon, progress, footer }: any) {
  return (
    <Card sx={{ height: '100%', position: 'relative', overflow: 'hidden' }}>
      <Box sx={{ position: 'absolute', top: -10, right: -10, opacity: 0.05, transform: 'scale(2)' }}>{icon}</Box>
      <CardContent>
        <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 1 }}>
          <Typography variant="caption" sx={{ fontWeight: 800, color: 'text.disabled', textTransform: 'uppercase', letterSpacing: 1 }}>{title}</Typography>
          {icon}
        </Box>
        <Typography variant="h4" sx={{ fontWeight: 900, mb: 2 }}>{value ?? '--'}</Typography>
        <LinearProgress variant="determinate" value={Math.min(progress ?? 0, 100)} sx={{ height: 4, borderRadius: 2, backgroundColor: 'rgba(255,255,255,0.05)' }} />
        <Typography variant="caption" sx={{ mt: 1.5, display: 'block', color: 'text.disabled', fontSize: '0.65rem' }}>{footer}</Typography>
      </CardContent>
    </Card>
  );
}

function AlertItem({ title, time, type }: any) {
  const colors: any = { error: '#ef4444', warning: '#f59e0b', info: '#3b82f6' };
  return (
    <Box sx={{
      p: 1.5, borderRadius: 1.5, border: '1px solid', borderColor: 'divider',
      display: 'flex', alignItems: 'center', justifyContent: 'space-between',
      background: 'rgba(255,255,255,0.01)', '&:hover': { background: 'rgba(255,255,255,0.03)' },
    }}>
      <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
        <Box sx={{ width: 8, height: 8, borderRadius: '50%', backgroundColor: colors[type] }} />
        <Typography variant="body2" sx={{ fontWeight: 600 }}>{title}</Typography>
      </Box>
      <Typography variant="caption" color="text.disabled">{time}</Typography>
    </Box>
  );
}

function QuickActionButton({ icon, label, to, color }: any) {
  return (
    <Button
      component={Link}
      to={to}
      fullWidth
      variant="outlined"
      color={color}
      startIcon={icon}
      sx={{ justifyContent: 'flex-start', py: 1.2, fontWeight: 700, textTransform: 'none', borderStyle: 'dashed' }}
    >
      {label}
    </Button>
  );
}

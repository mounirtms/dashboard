import {
  Box, Typography, Card, CardContent, Grid, Chip, CircularProgress,
  Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Paper,
} from '@mui/material';
import { Shield, Storage, Security, Warning as WarningIcon, CheckCircle, Refresh } from '@mui/icons-material';
import { useCallback } from 'react';
import apiClient from '../api/client';
import { usePolling } from '../hooks/usePolling';

// ── API response shapes ──────────────────────────────────────────────────────

interface ServiceEntry {
  name: string;
  status: string;
  enabled: boolean;
  pid: number;
  uptime_seconds: number;
}

interface OverviewResponse {
  active_users?: number;
  disk?: {
    pct?: string;
    used?: string;
    free?: string;
  };
}

interface ServicesResponse {
  categories?: Record<string, ServiceEntry[]>;
  summary?: {
    total: number;
    active: number;
    inactive: number;
    failed: number;
  };
}

interface SshResponse {
  failed_logins_24h?: number;
  failed_logins_total?: number;
  total_attempts_24h?: number;
  blocked_ips?: number;
}

interface AlertsResponse {
  total?: number;
}

// ── Composed audit bundle ────────────────────────────────────────────────────

interface AuditData {
  services: Record<string, ServiceEntry[]>;
  service_summary: { total: number; active: number; inactive: number; failed: number };
  databases: Array<{ name: string; size_mb: number; tables: number }>;
  disk_usage: Array<{ path: string; usage_pct: number; used: string; available: string }>;
  security: { failed_logins_24h: number; ssh_attempts: number; blocked_ips: number };
  log_errors: { total_24h: number; critical: number; errors: number; warnings: number };
  users: { active: number; total: number; locked: number };
  timestamp: number;
}

// ── Sub-components ───────────────────────────────────────────────────────────

function StatusChip({ status }: { status: string }) {
  const isOk = ['running', 'active', 'healthy'].includes(status.toLowerCase());
  return (
    <Chip
      label={status}
      size="small"
      sx={{
        bgcolor: isOk ? 'rgba(74,222,128,0.15)' : 'rgba(248,113,113,0.15)',
        color: isOk ? '#4ade80' : '#f87171',
        fontWeight: 600, fontSize: '0.65rem', height: 20,
      }}
    />
  );
}

interface AuditCardProps {
  title: string;
  value: string | number;
  icon: React.ReactNode;
  color: string;
  subtitle?: string;
}

function AuditCard({ title, value, icon, color, subtitle }: AuditCardProps) {
  return (
    <Card sx={{ bgcolor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.06)' }}>
      <CardContent sx={{ display: 'flex', alignItems: 'center', gap: 2, py: 2.5, px: 3 }}>
        <Box sx={{ color, bgcolor: `${color}15`, p: 1.5, borderRadius: 2 }}>
          {icon}
        </Box>
        <Box>
          <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 600, textTransform: 'uppercase', fontSize: '0.65rem' }}>
            {title}
          </Typography>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em' }}>
            {value}
          </Typography>
          {subtitle && (
            <Typography variant="caption" sx={{ color: 'text.disabled' }}>
              {subtitle}
            </Typography>
          )}
        </Box>
      </CardContent>
    </Card>
  );
}

// ── Fetcher ──────────────────────────────────────────────────────────────────

async function fetchAuditData(signal?: AbortSignal): Promise<AuditData> {
  const [overviewRes, servicesRes, sshRes, alertsRes] = await Promise.allSettled([
    apiClient.get('/api/monitor.php?action=overview', { signal }),
    apiClient.get('/api/monitor.php?action=services', { signal }),
    apiClient.get('/api/monitor.php?action=ssh', { signal }),
    apiClient.get('/api/monitor.php?action=alerts', { signal }),
  ]);

  const overview: OverviewResponse  = overviewRes.status  === 'fulfilled' ? (overviewRes.value.data  as OverviewResponse)  : {};
  const services: ServicesResponse  = servicesRes.status  === 'fulfilled' ? (servicesRes.value.data  as ServicesResponse)  : {};
  const ssh: SshResponse            = sshRes.status       === 'fulfilled' ? (sshRes.value.data       as SshResponse)       : {};
  const alerts: AlertsResponse      = alertsRes.status    === 'fulfilled' ? (alertsRes.value.data    as AlertsResponse)    : {};

  const auditData: AuditData = {
    services: services.categories ?? {},
    service_summary: services.summary ?? { total: 0, active: 0, inactive: 0, failed: 0 },
    databases: [],
    disk_usage: [],
    security: {
      failed_logins_24h: ssh.failed_logins_24h ?? ssh.failed_logins_total ?? 0,
      ssh_attempts: ssh.total_attempts_24h ?? 0,
      blocked_ips: ssh.blocked_ips ?? 0,
    },
    log_errors: {
      total_24h: alerts.total ?? 0,
      critical: 0,
      errors: 0,
      warnings: 0,
    },
    users: {
      active: overview.active_users ?? 0,
      total: 0,
      locked: 0,
    },
    timestamp: Date.now(),
  };

  // Parse disk from overview
  if (overview.disk) {
    const pctStr = overview.disk.pct ?? '0%';
    auditData.disk_usage = [{
      path: '/',
      usage_pct: parseInt(pctStr) || 0,
      used: overview.disk.used ?? '0',
      available: overview.disk.free ?? '0',
    }];
  }

  return auditData;
}

// ── Page ─────────────────────────────────────────────────────────────────────

export default function SystemAuditPage() {
  const fetcher = useCallback((signal?: AbortSignal) => fetchAuditData(signal), []);
  const { data, loading, refreshing, refetch } = usePolling<AuditData>(fetcher, 60_000);

  if (loading && !data) {
    return (
      <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: '60vh', gap: 2 }}>
        <CircularProgress size={32} />
        <Typography color="text.secondary">Running system audit...</Typography>
      </Box>
    );
  }

  const services = data?.services ?? {};
  const summary  = data?.service_summary ?? { total: 0, active: 0, inactive: 0, failed: 0 };

  // Collect non-active services for the security panel
  const downServices: { category: string; name: string; status: string }[] = [];
  Object.entries(services).forEach(([cat, svcs]) => {
    svcs.forEach((s) => {
      if (s.status !== 'active') downServices.push({ category: cat, name: s.name, status: s.status });
    });
  });

  return (
    <Box sx={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
      {/* Header */}
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            System Audit
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Comprehensive health and security audit of the infrastructure.
          </Typography>
        </Box>
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5 }}>
          {refreshing && <CircularProgress size={16} sx={{ color: 'text.disabled' }} />}
          <Chip
            icon={<CheckCircle sx={{ fontSize: 14 }} />}
            label={`Last checked: ${new Date(data?.timestamp ?? Date.now()).toLocaleTimeString()}`}
            size="small"
            onClick={refetch}
            sx={{ bgcolor: 'rgba(74,222,128,0.1)', color: '#4ade80', fontWeight: 600, cursor: 'pointer' }}
          />
        </Box>
      </Box>

      {/* Summary cards */}
      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 6, md: 3 }}>
          <AuditCard
            title="Services"
            value={`${summary.active}/${summary.total}`}
            icon={<Shield sx={{ fontSize: 20 }} />}
            color="#10b981"
            subtitle={downServices.length > 0 ? `${downServices.length} down` : 'All healthy'}
          />
        </Grid>
        <Grid size={{ xs: 6, md: 3 }}>
          <AuditCard
            title="Failed Logins (24h)"
            value={data?.security.failed_logins_24h ?? 0}
            icon={<Security sx={{ fontSize: 20 }} />}
            color={data?.security.failed_logins_24h ? '#f59e0b' : '#10b981'}
          />
        </Grid>
        <Grid size={{ xs: 6, md: 3 }}>
          <AuditCard
            title="Blocked IPs"
            value={data?.security.blocked_ips ?? 0}
            icon={<WarningIcon sx={{ fontSize: 20 }} />}
            color="#8b5cf6"
          />
        </Grid>
        <Grid size={{ xs: 6, md: 3 }}>
          <AuditCard
            title="Disk Usage"
            value={data?.disk_usage[0]?.usage_pct != null ? `${data.disk_usage[0].usage_pct}%` : 'N/A'}
            icon={<Storage sx={{ fontSize: 20 }} />}
            color="#3b82f6"
            subtitle={data?.disk_usage[0]?.used ? `${data.disk_usage[0].used} used` : ''}
          />
        </Grid>
      </Grid>

      {/* Detail panels */}
      <Grid container spacing={2} sx={{ flex: 1, minHeight: 0 }}>
        {/* Service health table */}
        <Grid size={{ xs: 12, md: 6 }}>
          <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 1.5 }}>
            Service Health
          </Typography>
          <TableContainer component={Paper} sx={{ bgcolor: 'transparent', border: '1px solid rgba(255,255,255,0.06)', maxHeight: 400, overflow: 'auto' }}>
            <Table size="small" stickyHeader>
              <TableHead>
                <TableRow>
                  <TableCell sx={{ fontWeight: 700, color: 'text.primary', fontSize: '0.7rem', bgcolor: 'rgba(255,255,255,0.03)' }}>Service</TableCell>
                  <TableCell sx={{ fontWeight: 700, color: 'text.primary', fontSize: '0.7rem', bgcolor: 'rgba(255,255,255,0.03)' }}>Status</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {Object.entries(services).map(([category, svcs]) =>
                  svcs.map((svc) => (
                    <TableRow key={`${category}-${svc.name}`} sx={{ '&:last-child td': { borderBottom: 0 } }}>
                      <TableCell sx={{ fontSize: '0.75rem', py: 0.75 }}>
                        <Typography component="span" sx={{ color: 'text.disabled', fontSize: '0.65rem', mr: 0.5 }}>{category}:</Typography>
                        {svc.name}
                      </TableCell>
                      <TableCell sx={{ py: 0.75 }}><StatusChip status={svc.status} /></TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </TableContainer>
        </Grid>

        {/* Security summary */}
        <Grid size={{ xs: 12, md: 6 }}>
          <Typography variant="subtitle1" sx={{ fontWeight: 700, mb: 1.5 }}>
            Security Summary
          </Typography>
          <Card sx={{ bgcolor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.06)' }}>
            <CardContent>
              <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <Typography variant="body2" sx={{ color: 'text.secondary' }}>Failed SSH logins (24h)</Typography>
                  <Chip label={data?.security.failed_logins_24h ?? 0} size="small" sx={{ bgcolor: 'rgba(248,113,113,0.15)', color: '#f87171', fontWeight: 700 }} />
                </Box>
                <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <Typography variant="body2" sx={{ color: 'text.secondary' }}>Total SSH attempts (24h)</Typography>
                  <Chip label={data?.security.ssh_attempts ?? 0} size="small" sx={{ bgcolor: 'rgba(251,191,36,0.15)', color: '#fbbf24', fontWeight: 700 }} />
                </Box>
                <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <Typography variant="body2" sx={{ color: 'text.secondary' }}>Blocked IPs (CSF)</Typography>
                  <Chip label={data?.security.blocked_ips ?? 0} size="small" sx={{ bgcolor: 'rgba(139,92,246,0.15)', color: '#8b5cf6', fontWeight: 700 }} />
                </Box>
                {downServices.length > 0 && (
                  <Box sx={{ mt: 1 }}>
                    <Typography variant="body2" sx={{ color: 'text.secondary', mb: 1 }}>Down Services:</Typography>
                    {downServices.map((svc) => (
                      <Chip
                        key={`${svc.category}-${svc.name}`}
                        label={`${svc.name}: ${svc.status}`}
                        size="small"
                        sx={{ mr: 1, mb: 0.5, bgcolor: 'rgba(248,113,113,0.15)', color: '#f87171', fontWeight: 600 }}
                      />
                    ))}
                  </Box>
                )}
              </Box>
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </Box>
  );
}

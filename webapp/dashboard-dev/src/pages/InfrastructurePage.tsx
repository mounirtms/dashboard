import { useState, useEffect, useCallback } from 'react';
import {
  Box, Typography, Grid, Card, CardContent, Chip, Button, Alert,
  LinearProgress, Table, TableBody, TableCell, TableContainer,
  TableHead, TableRow, Divider, Tooltip, CircularProgress, Tabs, Tab,
} from '@mui/material';
import {
  Refresh, CheckCircle, Cancel, Speed, Memory, Storage,
  Cloud, DeveloperBoard, Dns, Http, Cached, NetworkCheck,
  DataObject, Lan, Warning, ArrowForward,
} from '@mui/icons-material';
import {
  fetchSystemOverview, fetchVarnishStats, fetchApacheStats,
  fetchRedisStats, fetchServices, fetchNetworkConnections, fetchDbHealth,
  SystemOverview, VarnishStats, ApacheStats, RedisStats, ServicesData, NetworkData,
} from '../api/system';
import LoadingState from '../components/common/LoadingState';
import StatCard from '../components/common/StatCard';

// ── helpers ───────────────────────────────────────────────────────────────────
function fmtUptime(seconds: number): string {
  if (!seconds || seconds <= 0) return '—';
  const d = Math.floor(seconds / 86400);
  const h = Math.floor((seconds % 86400) / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  if (d > 0) return `${d}d ${h}h`;
  if (h > 0) return `${h}h ${m}m`;
  return `${m}m`;
}

function StatusDot({ ok, label }: { ok: boolean; label?: string }) {
  return (
    <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.6 }}>
      {ok
        ? <CheckCircle sx={{ fontSize: 16, color: '#22c55e' }} />
        : <Cancel sx={{ fontSize: 16, color: '#ef4444' }} />}
      {label && (
        <Typography variant="caption" sx={{ color: ok ? '#22c55e' : '#ef4444', fontWeight: 600 }}>
          {label}
        </Typography>
      )}
    </Box>
  );
}

function SectionCard({ title, icon, children, action, minHeight }: any) {
  return (
    <Card sx={{ height: '100%', minHeight: minHeight }}>
      <CardContent>
        <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
          <Typography variant="h6" sx={{ fontWeight: 800, display: 'flex', alignItems: 'center', gap: 1 }}>
            {icon} {title}
          </Typography>
          {action}
        </Box>
        {children}
      </CardContent>
    </Card>
  );
}

function GaugeBar({ value, max = 100, color }: { value: number; max?: number; color: string }) {
  const pct = Math.min((value / max) * 100, 100);
  return (
    <LinearProgress
      variant="determinate"
      value={pct}
      sx={{
        height: 6, borderRadius: 3, backgroundColor: 'rgba(255,255,255,0.06)',
        '& .MuiLinearProgress-bar': { backgroundColor: color, borderRadius: 3 },
      }}
    />
  );
}

function KVRow({ label, value, mono = false }: { label: string; value: React.ReactNode; mono?: boolean }) {
  return (
    <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', py: 0.3 }}>
      <Typography variant="caption" sx={{ color: 'text.disabled' }}>{label}</Typography>
      <Typography variant="caption" sx={{ fontWeight: 700, fontFamily: mono ? 'monospace' : 'inherit' }}>
        {value}
      </Typography>
    </Box>
  );
}

const SERVICE_STATUS_COLOR: Record<string, string> = {
  running: '#22c55e', active: '#22c55e',
  stopped: '#ef4444', failed: '#ef4444', inactive: '#ef4444',
  'not-found': '#94a3b8',
};

// ── Main Component ─────────────────────────────────────────────────────────────
export default function InfrastructurePage() {
  const [sys, setSys]           = useState<SystemOverview | null>(null);
  const [varnish, setVarnish]   = useState<VarnishStats | null>(null);
  const [apache, setApache]     = useState<ApacheStats | null>(null);
  const [redis, setRedis]       = useState<RedisStats | null>(null);
  const [services, setServices] = useState<ServicesData | null>(null);
  const [network, setNetwork]   = useState<NetworkData | null>(null);
  const [dbHealth, setDbHealth] = useState<any>(null);
  const [loading, setLoading]   = useState(true);
  const [lastUpdate, setLastUpdate] = useState<Date | null>(null);
  const [errors, setErrors]     = useState<string[]>([]);
  const [tab, setTab]           = useState(0);

  const fetchAll = useCallback(async () => {
    setLoading(true);
    const errs: string[] = [];

    const results = await Promise.allSettled([
      fetchSystemOverview(),
      fetchVarnishStats(),
      fetchApacheStats(),
      fetchRedisStats(),
      fetchServices(),
      fetchNetworkConnections(),
      fetchDbHealth(),
    ]);

    if (results[0].status === 'fulfilled') setSys(results[0].value);
    else errs.push('System: ' + (results[0].reason?.message || 'error'));

    if (results[1].status === 'fulfilled') setVarnish(results[1].value);
    else errs.push('Varnish: ' + (results[1].reason?.message || 'error'));

    if (results[2].status === 'fulfilled') setApache(results[2].value);
    else errs.push('Apache: ' + (results[2].reason?.message || 'error'));

    if (results[3].status === 'fulfilled') setRedis(results[3].value);
    else errs.push('Redis: ' + (results[3].reason?.message || 'error'));

    if (results[4].status === 'fulfilled') setServices(results[4].value);
    else errs.push('Services: ' + (results[4].reason?.message || 'error'));

    if (results[5].status === 'fulfilled') setNetwork(results[5].value);
    // Network errors are non-critical, silent

    if (results[6].status === 'fulfilled') setDbHealth(results[6].value);
    // DB health errors are non-critical, silent

    setErrors(errs);
    setLastUpdate(new Date());
    setLoading(false);
  }, []);

  useEffect(() => {
    fetchAll();
    const t = setInterval(fetchAll, 30000);
    return () => clearInterval(t);
  }, [fetchAll]);

  if (loading && !sys) return <LoadingState message="Loading infrastructure data…" />;

  // ── derived ────────────────────────────────────────────────────────────────
  const load1   = sys?.load?.['1min']  ?? 0;
  const load5   = sys?.load?.['5min']  ?? 0;
  const load15  = sys?.load?.['15min'] ?? 0;
  const memPct  = sys?.memory?.used_pct ?? 0;
  const swapPct = sys?.memory?.swap_pct ?? 0;
  const diskPct = parseInt(sys?.disk?.pct?.replace('%', '') ?? '0');
  const hitRate = varnish?.hit_ratio ?? 0;

  const loadColor  = load1 > 8 ? '#ef4444' : load1 > 4 ? '#f59e0b' : '#22c55e';
  const memColor   = memPct > 85 ? '#ef4444' : memPct > 70 ? '#f59e0b' : '#22c55e';
  const diskColor  = diskPct > 90 ? '#ef4444' : diskPct > 80 ? '#f59e0b' : '#22c55e';
  const hitColor   = hitRate >= 80 ? '#22c55e' : hitRate >= 50 ? '#f59e0b' : '#ef4444';

  // Flat services list from categories
  const allServices = services
    ? Object.values(services.categories ?? {}).flat()
    : Object.entries(sys?.services ?? {}).map(([name, status]) => ({ name, status, description: '', enabled: true, pid: 0, uptime_seconds: 0 }));

  const activeCount   = allServices.filter(s => ['running', 'active'].includes(s.status)).length;
  const inactiveCount = allServices.length - activeCount;

  // DB health summary
  const dbOk = dbHealth && !dbHealth.error && (dbHealth.status === 'ok' || dbHealth.connected);

  // Network summary
  const totalConns = network?.established_total ?? 0;
  const listeningCount = network?.listening_ports?.length ?? 0;

  return (
    <Box>
      {/* ── Header ── */}
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: 2 }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 900, letterSpacing: '-0.03em', mb: 0.5 }}>
            Infrastructure
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Live server metrics — techno-dz.com · CentOS · 4 vCPU · 8 GB RAM
            {lastUpdate && ` · Updated ${lastUpdate.toLocaleTimeString()}`}
          </Typography>
        </Box>
        <Box sx={{ display: 'flex', gap: 1, alignItems: 'center' }}>
          {loading && <CircularProgress size={18} />}
          <Button startIcon={<Refresh />} variant="outlined" size="small" onClick={fetchAll} disabled={loading}>
            Refresh
          </Button>
        </Box>
      </Box>

      {errors.length > 0 && (
        <Alert severity="warning" icon={<Warning />} sx={{ mb: 2 }}>
          Partial data — {errors.join(' | ')}
        </Alert>
      )}

      {/* ── KPI row ── */}
      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 6, sm: 3 }}>
          <StatCard label="CPU Load (1m)" value={load1.toFixed(2)}
            color={load1 > 8 ? 'error' : load1 > 4 ? 'warning' : 'success'}
            subvalue={`5m: ${load5} · 15m: ${load15}`} icon={<Speed />} />
        </Grid>
        <Grid size={{ xs: 6, sm: 3 }}>
          <StatCard label="RAM Used" value={`${memPct}%`}
            color={memPct > 85 ? 'error' : memPct > 70 ? 'warning' : 'success'}
            subvalue={`${sys?.memory?.available_mb ?? '—'} MB free`} icon={<Memory />} />
        </Grid>
        <Grid size={{ xs: 6, sm: 3 }}>
          <StatCard label="Disk Usage" value={sys?.disk?.pct ?? '—'}
            color={diskPct > 90 ? 'error' : diskPct > 80 ? 'warning' : 'success'}
            subvalue={`${sys?.disk?.free ?? '—'} free of ${sys?.disk?.total ?? '—'}`} icon={<Storage />} />
        </Grid>
        <Grid size={{ xs: 6, sm: 3 }}>
          <StatCard label="Varnish Hit Rate" value={`${hitRate.toFixed(1)}%`}
            color={hitRate >= 80 ? 'success' : hitRate >= 50 ? 'warning' : 'error'}
            subvalue={`${(varnish?.hits ?? 0).toLocaleString()} hits · ${(varnish?.misses ?? 0).toLocaleString()} misses`}
            icon={<Cached />} />
        </Grid>
      </Grid>

      {/* ── Tab navigation ── */}
      <Box sx={{ mb: 2, borderBottom: 1, borderColor: 'divider' }}>
        <Tabs value={tab} onChange={(_, v) => setTab(v)} sx={{ minHeight: 36, '& .MuiTab-root': { minHeight: 36, fontSize: '0.78rem', py: 0.5 } }}>
          <Tab label="Overview" />
          <Tab label="Services" />
          <Tab label="Cache & Web" />
          <Tab label="Database & Network" />
        </Tabs>
      </Box>

      {/* ── Tab 0: Overview ── */}
      {tab === 0 && (
        <Grid container spacing={2}>

          {/* CPU / Memory / Disk gauges */}
          <Grid size={{ xs: 12, md: 4 }}>
            <SectionCard title="Server Resources" icon={<DeveloperBoard sx={{ color: '#3b82f6' }} />} minHeight={320}>
              <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2.5 }}>
                {/* CPU */}
                <Box>
                  <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 0.5 }}>
                    <Typography variant="caption" sx={{ fontWeight: 600 }}>CPU Load (1m)</Typography>
                    <Typography variant="caption" sx={{ color: loadColor, fontWeight: 700 }}>{load1.toFixed(2)}</Typography>
                  </Box>
                  <GaugeBar value={load1} max={16} color={loadColor} />
                  <Typography variant="caption" sx={{ color: 'text.disabled' }}>
                    5m: {load5} · 15m: {load15} · Uptime: {sys?.uptime ?? '—'}
                  </Typography>
                </Box>
                <Divider />
                {/* RAM */}
                <Box>
                  <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 0.5 }}>
                    <Typography variant="caption" sx={{ fontWeight: 600 }}>RAM Usage</Typography>
                    <Typography variant="caption" sx={{ color: memColor, fontWeight: 700 }}>{memPct}%</Typography>
                  </Box>
                  <GaugeBar value={memPct} color={memColor} />
                  <Typography variant="caption" sx={{ color: 'text.disabled' }}>
                    {sys?.memory?.available_mb ?? '—'} MB free · Swap: {swapPct}%
                  </Typography>
                </Box>
                {swapPct > 0 && (
                  <Box>
                    <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 0.5 }}>
                      <Typography variant="caption" sx={{ fontWeight: 600 }}>Swap</Typography>
                      <Typography variant="caption" sx={{ color: swapPct > 50 ? '#f59e0b' : '#94a3b8', fontWeight: 700 }}>{swapPct}%</Typography>
                    </Box>
                    <GaugeBar value={swapPct} color={swapPct > 50 ? '#f59e0b' : '#94a3b8'} />
                  </Box>
                )}
                <Divider />
                {/* Disk */}
                <Box>
                  <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 0.5 }}>
                    <Typography variant="caption" sx={{ fontWeight: 600 }}>Disk ({sys?.disk?.pct ?? '—'})</Typography>
                    <Typography variant="caption" sx={{ color: diskColor, fontWeight: 700 }}>
                      {sys?.disk?.used ?? '—'} / {sys?.disk?.total ?? '—'}
                    </Typography>
                  </Box>
                  <GaugeBar value={diskPct} color={diskColor} />
                  <Typography variant="caption" sx={{ color: 'text.disabled' }}>
                    {sys?.disk?.free ?? '—'} available
                  </Typography>
                </Box>
              </Box>
            </SectionCard>
          </Grid>

          {/* Services summary */}
          <Grid size={{ xs: 12, md: 4 }}>
            <SectionCard
              title="Services"
              icon={<Dns sx={{ color: '#8b5cf6' }} />}
              minHeight={320}
              action={
                <Box sx={{ display: 'flex', gap: 0.8 }}>
                  <Chip label={`${activeCount} OK`} size="small" sx={{ bgcolor: '#16a34a22', color: '#22c55e', fontSize: '0.7rem' }} />
                  {inactiveCount > 0 && <Chip label={`${inactiveCount} ↓`} size="small" sx={{ bgcolor: '#ef444422', color: '#ef4444', fontSize: '0.7rem' }} />}
                </Box>
              }
            >
              <Box sx={{ display: 'flex', flexDirection: 'column', gap: 0.6 }}>
                {allServices.length === 0 ? (
                  <Typography variant="caption" sx={{ color: 'text.disabled' }}>No service data</Typography>
                ) : (
                  allServices.slice(0, 10).map((svc: any, i: number) => {
                    const isOk = ['running', 'active'].includes(svc.status);
                    const color = SERVICE_STATUS_COLOR[svc.status] ?? '#94a3b8';
                    return (
                      <Box key={i} sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', py: 0.3, borderBottom: '1px solid rgba(255,255,255,0.04)' }}>
                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.8 }}>
                          <Box sx={{ width: 6, height: 6, borderRadius: '50%', bgcolor: color, flexShrink: 0 }} />
                          <Typography variant="caption" sx={{ fontFamily: 'monospace', fontWeight: 600 }}>{svc.name}</Typography>
                        </Box>
                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.8 }}>
                          {svc.uptime_seconds > 0 && (
                            <Typography variant="caption" sx={{ color: 'text.disabled', fontSize: '0.65rem' }}>
                              {fmtUptime(svc.uptime_seconds)}
                            </Typography>
                          )}
                          <Chip
                            label={svc.status}
                            size="small"
                            sx={{ bgcolor: color + '22', color, fontSize: '0.65rem', height: 18 }}
                          />
                        </Box>
                      </Box>
                    );
                  })
                )}
                {allServices.length > 10 && (
                  <Typography variant="caption" sx={{ color: 'text.disabled', textAlign: 'center', mt: 0.5 }}>
                    +{allServices.length - 10} more — see Services tab
                  </Typography>
                )}
              </Box>
            </SectionCard>
          </Grid>

          {/* Top Processes */}
          <Grid size={{ xs: 12, md: 4 }}>
            <SectionCard title="Top Processes" icon={<Speed sx={{ color: '#a78bfa' }} />} minHeight={320}>
              {!sys?.top_procs?.length ? (
                <Typography variant="caption" sx={{ color: 'text.disabled' }}>No process data</Typography>
              ) : (
                <TableContainer>
                  <Table size="small">
                    <TableHead>
                      <TableRow>
                        <TableCell sx={{ fontSize: '0.7rem', color: 'text.disabled', py: 0.5, px: 1 }}>PID</TableCell>
                        <TableCell sx={{ fontSize: '0.7rem', color: 'text.disabled', py: 0.5, px: 1 }} align="right">CPU%</TableCell>
                        <TableCell sx={{ fontSize: '0.7rem', color: 'text.disabled', py: 0.5, px: 1 }} align="right">MEM%</TableCell>
                        <TableCell sx={{ fontSize: '0.7rem', color: 'text.disabled', py: 0.5, px: 1 }}>Command</TableCell>
                      </TableRow>
                    </TableHead>
                    <TableBody>
                      {sys.top_procs.slice(0, 8).map((p, i) => (
                        <TableRow key={i}>
                          <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.72rem', py: 0.4, px: 1 }}>{p.pid}</TableCell>
                          <TableCell align="right" sx={{ fontFamily: 'monospace', fontSize: '0.72rem', py: 0.4, px: 1, color: parseFloat(p.cpu) > 10 ? '#f59e0b' : 'text.primary' }}>
                            {p.cpu}
                          </TableCell>
                          <TableCell align="right" sx={{ fontFamily: 'monospace', fontSize: '0.72rem', py: 0.4, px: 1 }}>{p.mem}</TableCell>
                          <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.68rem', py: 0.4, px: 1, maxWidth: 120, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                            <Tooltip title={p.cmd} arrow>
                              <span>{p.cmd?.split('/').pop() ?? p.cmd}</span>
                            </Tooltip>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </TableContainer>
              )}
            </SectionCard>
          </Grid>

          {/* Network Stack Info */}
          <Grid size={{ xs: 12 }}>
            <Card>
              <CardContent>
                <Typography variant="h6" sx={{ fontWeight: 800, mb: 2, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <NetworkCheck sx={{ color: '#10b981' }} /> Network Stack — technostationery.com
                </Typography>
                <Grid container spacing={2}>
                  {[
                    { label: 'Origin IP',          value: '205.134.249.177',    color: '#3b82f6' },
                    { label: 'Cloudflare Proxy',   value: 'Active — Free Plan', color: '#f97316' },
                    { label: 'SSL Mode',            value: 'Strict',             color: '#22c55e' },
                    { label: 'Port :80 → Varnish',  value: 'Frontend cache',     color: '#06b6d4' },
                    { label: 'Port :81 → Apache',   value: 'Backend origin',     color: '#8b5cf6' },
                    { label: 'Port :443 → Apache',  value: 'SSL termination',    color: '#a78bfa' },
                    { label: 'PHP-FPM',             value: 'Multi-site pools',   color: '#f59e0b' },
                    { label: 'MariaDB',             value: ':3306 local socket', color: '#ec4899' },
                    { label: 'Active Connections',  value: totalConns > 0 ? `${totalConns.toLocaleString()} established` : '—', color: '#10b981' },
                    { label: 'Listening Ports',     value: listeningCount > 0 ? `${listeningCount} ports` : '—', color: '#64748b' },
                  ].map(({ label, value, color }) => (
                    <Grid key={label} size={{ xs: 6, sm: 4, md: 2.4 }}>
                      <Box sx={{ p: 1.5, borderRadius: 1.5, border: '1px solid', borderColor: 'divider', background: 'rgba(255,255,255,0.02)', height: '100%' }}>
                        <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 600, display: 'block', mb: 0.3 }}>{label}</Typography>
                        <Typography variant="caption" sx={{ fontFamily: 'monospace', fontWeight: 700, color }}>{value}</Typography>
                      </Box>
                    </Grid>
                  ))}
                </Grid>

                <Divider sx={{ my: 2 }} />

                <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 700 }}>REQUEST FLOW</Typography>
                <Box sx={{ mt: 1, display: 'flex', gap: 0.5, flexWrap: 'wrap', alignItems: 'center' }}>
                  {[
                    { label: 'Cloudflare CDN', color: '#f97316' },
                    { label: 'Varnish :80', color: '#06b6d4' },
                    { label: 'Apache :81', color: '#f59e0b' },
                    { label: 'PHP-FPM', color: '#8b5cf6' },
                    { label: 'MariaDB', color: '#3b82f6' },
                    { label: '+ Redis', color: '#ef4444' },
                  ].map(({ label, color }, i, arr) => (
                    <Box key={label} sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
                      <Chip label={label} size="small"
                        sx={{ bgcolor: color + '22', color, fontSize: '0.72rem', fontWeight: 600 }} />
                      {i < arr.length - 1 && <ArrowForward sx={{ fontSize: 12, color: 'text.disabled' }} />}
                    </Box>
                  ))}
                </Box>
              </CardContent>
            </Card>
          </Grid>
        </Grid>
      )}

      {/* ── Tab 1: Services ── */}
      {tab === 1 && (
        <Grid container spacing={2}>
          {services ? (
            Object.entries(services.categories ?? {}).map(([category, svcs]) => (
              <Grid key={category} size={{ xs: 12, sm: 6, md: 4 }}>
                <SectionCard
                  title={category.charAt(0).toUpperCase() + category.slice(1)}
                  icon={<Dns sx={{ color: '#8b5cf6', fontSize: 20 }} />}
                  action={
                    <Chip
                      label={`${(svcs as any[]).filter(s => s.status === 'active').length}/${(svcs as any[]).length}`}
                      size="small"
                      sx={{ bgcolor: '#16a34a22', color: '#22c55e', fontSize: '0.7rem' }}
                    />
                  }
                >
                  <Box sx={{ display: 'flex', flexDirection: 'column', gap: 0.8 }}>
                    {(svcs as any[]).map((svc, i) => {
                      const isOk = ['running', 'active'].includes(svc.status);
                      const color = SERVICE_STATUS_COLOR[svc.status] ?? '#94a3b8';
                      return (
                        <Box key={i} sx={{ p: 1, borderRadius: 1, border: '1px solid', borderColor: color + '33', background: color + '08' }}>
                          <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 0.3 }}>
                            <Typography variant="caption" sx={{ fontFamily: 'monospace', fontWeight: 700 }}>{svc.name}</Typography>
                            <Chip
                              label={svc.status}
                              size="small"
                              sx={{ bgcolor: color + '22', color, fontSize: '0.65rem', height: 18 }}
                            />
                          </Box>
                          <Box sx={{ display: 'flex', gap: 1.5 }}>
                            <Typography variant="caption" sx={{ color: 'text.disabled', fontSize: '0.65rem' }}>
                              PID: {svc.pid > 0 ? svc.pid : '—'}
                            </Typography>
                            <Typography variant="caption" sx={{ color: 'text.disabled', fontSize: '0.65rem' }}>
                              Up: {fmtUptime(svc.uptime_seconds)}
                            </Typography>
                            <Typography variant="caption" sx={{ color: svc.enabled ? '#22c55e' : '#94a3b8', fontSize: '0.65rem' }}>
                              {svc.enabled ? 'enabled' : 'disabled'}
                            </Typography>
                          </Box>
                        </Box>
                      );
                    })}
                  </Box>
                </SectionCard>
              </Grid>
            ))
          ) : (
            <Grid size={{ xs: 12 }}>
              <Alert severity="info">Services data not available. The API may be loading or unreachable.</Alert>
            </Grid>
          )}

          {/* Services summary bar */}
          {services?.summary && (
            <Grid size={{ xs: 12 }}>
              <Card>
                <CardContent sx={{ py: 1.5 }}>
                  <Box sx={{ display: 'flex', gap: 3, alignItems: 'center', flexWrap: 'wrap' }}>
                    <Typography variant="caption" sx={{ fontWeight: 700, color: 'text.disabled' }}>SUMMARY</Typography>
                    {[
                      { label: 'Total', value: services.summary.total, color: '#94a3b8' },
                      { label: 'Active', value: services.summary.active, color: '#22c55e' },
                      { label: 'Inactive', value: services.summary.inactive, color: '#f59e0b' },
                      { label: 'Failed', value: services.summary.failed, color: '#ef4444' },
                    ].map(({ label, value, color }) => (
                      <Box key={label} sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
                        <Typography variant="caption" sx={{ color: 'text.disabled' }}>{label}:</Typography>
                        <Typography variant="caption" sx={{ fontWeight: 800, color }}>{value}</Typography>
                      </Box>
                    ))}
                  </Box>
                </CardContent>
              </Card>
            </Grid>
          )}
        </Grid>
      )}

      {/* ── Tab 2: Cache & Web ── */}
      {tab === 2 && (
        <Grid container spacing={2}>

          {/* Varnish */}
          <Grid size={{ xs: 12, md: 6 }}>
            <SectionCard title="Varnish Cache" icon={<Cached sx={{ color: '#06b6d4' }} />}>
              {!(varnish as any)?.hit_ratio && (varnish as any)?.error ? (
                <Alert severity="warning">{(varnish as any).error}</Alert>
              ) : !varnish ? (
                <Typography variant="caption" sx={{ color: 'text.disabled' }}>No Varnish data</Typography>
              ) : (
                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1.5 }}>
                  <Box>
                    <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 0.5 }}>
                      <Typography variant="caption" sx={{ fontWeight: 600 }}>Hit Rate</Typography>
                      <Typography variant="caption" sx={{ color: hitColor, fontWeight: 800 }}>{hitRate.toFixed(1)}%</Typography>
                    </Box>
                    <GaugeBar value={hitRate} color={hitColor} />
                  </Box>
                  <Divider />
                  <KVRow label="Total Requests" value={(varnish.hits + varnish.misses).toLocaleString()} mono />
                  <KVRow label="Cache Hits" value={<span style={{ color: '#22c55e' }}>{varnish.hits.toLocaleString()}</span>} mono />
                  <KVRow label="Cache Misses" value={<span style={{ color: '#ef4444' }}>{varnish.misses.toLocaleString()}</span>} mono />
                  <KVRow label="Storage Used" value={varnish.storage?.used ?? '—'} mono />
                  <KVRow label="Storage Total" value={varnish.storage?.total ?? '—'} mono />
                  <KVRow label="Backend" value={
                    <StatusDot ok={varnish.backend_healthy} label={varnish.backend_healthy ? 'Healthy' : 'Sick'} />
                  } />
                  <KVRow label="Uptime" value={varnish.uptime_seconds ? fmtUptime(varnish.uptime_seconds) : '—'} mono />

                  {varnish.devices && Object.keys(varnish.devices).length > 0 && (
                    <>
                      <Divider />
                      <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 700 }}>BY DEVICE</Typography>
                      {Object.entries(varnish.devices).map(([dev, d]: [string, any]) => (
                        <Box key={dev} sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                          <Typography variant="caption" sx={{ textTransform: 'capitalize', color: 'text.secondary' }}>{dev}</Typography>
                          <Box sx={{ display: 'flex', gap: 1.5 }}>
                            <Typography variant="caption" sx={{ color: 'text.disabled' }}>{d.percentage}% traffic</Typography>
                            <Typography variant="caption" sx={{ color: hitColor, fontWeight: 700 }}>{parseFloat(d.hit_rate).toFixed(0)}% hit</Typography>
                          </Box>
                        </Box>
                      ))}
                    </>
                  )}
                </Box>
              )}
            </SectionCard>
          </Grid>

          {/* Apache + Redis side by side */}
          <Grid size={{ xs: 12, md: 6 }}>
            <Grid container spacing={2} sx={{ height: '100%' }}>
              <Grid size={{ xs: 12 }}>
                <SectionCard title="Apache HTTP" icon={<Http sx={{ color: '#f97316' }} />}>
                  {!apache ? (
                    <Typography variant="caption" sx={{ color: 'text.disabled' }}>No Apache data</Typography>
                  ) : (
                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                      <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                        <Typography variant="caption" sx={{ color: 'text.disabled' }}>Status</Typography>
                        <StatusDot ok={apache.running} label={apache.running ? 'Running' : 'Down'} />
                      </Box>
                      {apache.version && <KVRow label="Version" value={apache.version} mono />}
                      <KVRow label="Processes" value={apache.processes ?? '—'} mono />
                      <Divider />
                      <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 700 }}>PORTS</Typography>
                      {[
                        ['HTTP :80',    apache.ports?.http],
                        ['SSL :443',   apache.ports?.ssl ?? apache.ports?.https],
                        ['Backend :81', apache.ports?.backend ?? false],
                      ].map(([label, ok]) => (
                        <Box key={label as string} sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                          <Typography variant="caption" sx={{ color: 'text.secondary' }}>{label}</Typography>
                          <StatusDot ok={!!ok} />
                        </Box>
                      ))}
                    </Box>
                  )}
                </SectionCard>
              </Grid>
              <Grid size={{ xs: 12 }}>
                <SectionCard title="Redis Cache" icon={<Cloud sx={{ color: '#ef4444' }} />}>
                  {!redis ? (
                    <Typography variant="caption" sx={{ color: 'text.disabled' }}>No Redis data</Typography>
                  ) : (
                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                      <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                        <Typography variant="caption" sx={{ color: 'text.disabled' }}>Status</Typography>
                        <StatusDot ok={redis.connected} label={redis.connected ? 'Connected' : 'Disconnected'} />
                      </Box>
                      <KVRow label="Memory Used"  value={redis.memory?.used_human ?? '—'} mono />
                      <KVRow label="Peak Memory"  value={redis.memory?.peak_human ?? '—'} mono />
                      <KVRow label="Total Keys"   value={(redis.keyspace?.total_keys ?? 0).toLocaleString()} mono />
                      <KVRow label="Hit Rate"     value={`${(redis.stats?.hit_rate ?? 0).toFixed(1)}%`} mono />
                      <KVRow label="Ops/sec"      value={(redis.stats?.ops_per_sec ?? 0).toLocaleString()} mono />
                      <KVRow label="Clients"      value={redis.stats?.connected_clients ?? '—'} mono />
                    </Box>
                  )}
                </SectionCard>
              </Grid>
            </Grid>
          </Grid>
        </Grid>
      )}

      {/* ── Tab 3: Database & Network ── */}
      {tab === 3 && (
        <Grid container spacing={2}>

          {/* DB Health */}
          <Grid size={{ xs: 12, md: 5 }}>
            <SectionCard title="MariaDB Health" icon={<DataObject sx={{ color: '#ec4899' }} />}>
              {!dbHealth ? (
                <Alert severity="info" sx={{ fontSize: '0.8rem' }}>
                  Loading DB health data… If persistent, check DB credentials in config.php.
                </Alert>
              ) : dbHealth.error ? (
                <Alert severity="error">{dbHealth.error}</Alert>
              ) : (
                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                  <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <Typography variant="caption" sx={{ color: 'text.disabled' }}>Status</Typography>
                    <StatusDot ok={dbOk} label={dbOk ? 'Connected' : 'Issue detected'} />
                  </Box>
                  {dbHealth.version && <KVRow label="Version" value={dbHealth.version} mono />}
                  {dbHealth.uptime && <KVRow label="Uptime" value={dbHealth.uptime} mono />}
                  {dbHealth.databases && (
                    <KVRow label="Databases" value={dbHealth.databases?.length ?? '—'} mono />
                  )}
                  {dbHealth.connections !== undefined && (
                    <KVRow label="Connections" value={dbHealth.connections} mono />
                  )}
                  {dbHealth.max_connections !== undefined && (
                    <KVRow label="Max Connections" value={dbHealth.max_connections} mono />
                  )}
                  {dbHealth.slow_queries !== undefined && (
                    <KVRow label="Slow Queries" value={
                      <span style={{ color: dbHealth.slow_queries > 0 ? '#f59e0b' : '#22c55e' }}>
                        {dbHealth.slow_queries}
                      </span>
                    } />
                  )}
                  {dbHealth.size_mb && (
                    <KVRow label="Total Size" value={`${dbHealth.size_mb} MB`} mono />
                  )}
                  {/* Issues / warnings */}
                  {dbHealth.issues?.length > 0 && (
                    <>
                      <Divider />
                      <Typography variant="caption" sx={{ color: '#f59e0b', fontWeight: 700 }}>ISSUES</Typography>
                      {dbHealth.issues.slice(0, 5).map((issue: string, i: number) => (
                        <Alert key={i} severity="warning" sx={{ py: 0.25, fontSize: '0.72rem' }}>{issue}</Alert>
                      ))}
                    </>
                  )}
                </Box>
              )}
            </SectionCard>
          </Grid>

          {/* Network connections */}
          <Grid size={{ xs: 12, md: 7 }}>
            <SectionCard title="Network Connections" icon={<Lan sx={{ color: '#10b981' }} />}>
              {!network ? (
                <Alert severity="info" sx={{ fontSize: '0.8rem' }}>Network data not available.</Alert>
              ) : (
                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1.5 }}>
                  {/* Summary */}
                  <Grid container spacing={1.5}>
                    {[
                      { label: 'Established', value: network.established_total, color: '#22c55e' },
                      { label: 'TIME_WAIT',    value: network.time_wait_total,   color: '#f59e0b' },
                      { label: 'Listening',    value: network.listening_ports?.length ?? 0, color: '#3b82f6' },
                    ].map(({ label, value, color }) => (
                      <Grid key={label} size={{ xs: 4 }}>
                        <Box sx={{ p: 1, textAlign: 'center', borderRadius: 1, border: '1px solid', borderColor: color + '33', background: color + '0a' }}>
                          <Typography variant="h6" sx={{ color, fontWeight: 800, lineHeight: 1 }}>{value}</Typography>
                          <Typography variant="caption" sx={{ color: 'text.disabled', fontSize: '0.65rem' }}>{label}</Typography>
                        </Box>
                      </Grid>
                    ))}
                  </Grid>

                  <Divider />

                  {/* Listening ports table */}
                  {network.listening_ports?.length > 0 && (
                    <>
                      <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 700 }}>LISTENING PORTS</Typography>
                      <TableContainer sx={{ maxHeight: 200 }}>
                        <Table size="small" stickyHeader>
                          <TableHead>
                            <TableRow>
                              <TableCell sx={{ fontSize: '0.68rem', color: 'text.disabled', py: 0.5, bgcolor: 'background.paper' }}>Port</TableCell>
                              <TableCell sx={{ fontSize: '0.68rem', color: 'text.disabled', py: 0.5, bgcolor: 'background.paper' }}>Address</TableCell>
                              <TableCell sx={{ fontSize: '0.68rem', color: 'text.disabled', py: 0.5, bgcolor: 'background.paper' }}>Process</TableCell>
                              <TableCell sx={{ fontSize: '0.68rem', color: 'text.disabled', py: 0.5, bgcolor: 'background.paper' }}>PID</TableCell>
                            </TableRow>
                          </TableHead>
                          <TableBody>
                            {network.listening_ports.slice(0, 15).map((p, i) => (
                              <TableRow key={i}>
                                <TableCell sx={{ fontFamily: 'monospace', fontWeight: 700, fontSize: '0.72rem', py: 0.3, color: (p as any).is_common ? '#3b82f6' : 'text.primary' }}>
                                  :{p.port}
                                </TableCell>
                                <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.68rem', py: 0.3, color: 'text.disabled' }}>{p.address}</TableCell>
                                <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.68rem', py: 0.3 }}>{p.process}</TableCell>
                                <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.68rem', py: 0.3, color: 'text.disabled' }}>{p.pid}</TableCell>
                              </TableRow>
                            ))}
                          </TableBody>
                        </Table>
                      </TableContainer>
                    </>
                  )}

                  {/* Top remote IPs */}
                  {network.top_remote_ips?.length > 0 && (
                    <>
                      <Divider />
                      <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 700 }}>TOP REMOTE IPs</Typography>
                      {network.top_remote_ips.slice(0, 5).map((ip, i) => (
                        <Box key={i} sx={{ display: 'flex', justifyContent: 'space-between' }}>
                          <Typography variant="caption" sx={{ fontFamily: 'monospace' }}>{ip.ip}</Typography>
                          <Chip label={`${ip.connections} conn`} size="small"
                            sx={{ bgcolor: '#3b82f622', color: '#3b82f6', fontSize: '0.65rem', height: 18 }} />
                        </Box>
                      ))}
                    </>
                  )}
                </Box>
              )}
            </SectionCard>
          </Grid>

          {/* Connection state breakdown */}
          {network?.connection_states && network.connection_states.length > 0 && (
            <Grid size={{ xs: 12 }}>
              <Card>
                <CardContent>
                  <Typography variant="h6" sx={{ fontWeight: 800, mb: 2, display: 'flex', alignItems: 'center', gap: 1 }}>
                    <NetworkCheck sx={{ color: '#10b981', fontSize: 20 }} /> Connection State Breakdown
                  </Typography>
                  <Box sx={{ display: 'flex', gap: 1.5, flexWrap: 'wrap' }}>
                    {network.connection_states.map((s, i) => {
                      const stateColor = s.state === 'ESTABLISHED' ? '#22c55e' : s.state === 'TIME_WAIT' ? '#f59e0b' : s.state === 'CLOSE_WAIT' ? '#ef4444' : '#94a3b8';
                      return (
                        <Box key={i} sx={{ p: 1.5, borderRadius: 1.5, border: '1px solid', borderColor: stateColor + '33', background: stateColor + '0a', textAlign: 'center', minWidth: 100 }}>
                          <Typography variant="h6" sx={{ color: stateColor, fontWeight: 800, lineHeight: 1.2 }}>{s.count}</Typography>
                          <Typography variant="caption" sx={{ color: 'text.disabled', fontSize: '0.65rem', fontFamily: 'monospace' }}>{s.state}</Typography>
                        </Box>
                      );
                    })}
                  </Box>
                </CardContent>
              </Card>
            </Grid>
          )}
        </Grid>
      )}
    </Box>
  );
}

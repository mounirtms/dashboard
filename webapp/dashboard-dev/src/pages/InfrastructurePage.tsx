import { useState, useEffect, useCallback } from 'react';
import {
  Box, Typography, Grid, Card, CardContent, Chip, Button, Alert,
  LinearProgress, Table, TableBody, TableCell, TableContainer,
  TableHead, TableRow, Divider, Tooltip, CircularProgress,
} from '@mui/material';
import {
  Refresh, CheckCircle, Cancel, Warning, Speed, Memory, Storage,
  Cloud, DeveloperBoard, Dns, Http, Cached, Timer, NetworkCheck,
  CircleOutlined,
} from '@mui/icons-material';
import {
  fetchSystemOverview, fetchVarnishStats, fetchApacheStats,
  fetchRedisStats, fetchServices,
  SystemOverview, VarnishStats, ApacheStats, RedisStats, ServicesData,
} from '../api/system';
import LoadingState from '../components/common/LoadingState';
import StatCard from '../components/common/StatCard';

// ── helpers ───────────────────────────────────────────────────────────────────
function fmtBytes(bytes: number): string {
  if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(1) + ' GB';
  if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
  if (bytes >= 1024) return (bytes / 1024).toFixed(0) + ' KB';
  return bytes + ' B';
}

function StatusDot({ ok, label }: { ok: boolean; label?: string }) {
  return (
    <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.6 }}>
      {ok
        ? <CheckCircle sx={{ fontSize: 16, color: '#22c55e' }} />
        : <Cancel sx={{ fontSize: 16, color: '#ef4444' }} />}
      {label && <Typography variant="caption" sx={{ color: ok ? '#22c55e' : '#ef4444', fontWeight: 600 }}>{label}</Typography>}
    </Box>
  );
}

function SectionCard({ title, icon, children, action }: any) {
  return (
    <Card sx={{ height: '100%' }}>
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

const SERVICE_STATUS_COLOR: Record<string, string> = {
  running: '#22c55e', active: '#22c55e',
  stopped: '#ef4444', failed: '#ef4444', inactive: '#ef4444',
  'not-found': '#94a3b8',
};

// ── Main Component ─────────────────────────────────────────────────────────────
export default function InfrastructurePage() {
  const [sys, setSys]         = useState<SystemOverview | null>(null);
  const [varnish, setVarnish] = useState<VarnishStats | null>(null);
  const [apache, setApache]   = useState<ApacheStats | null>(null);
  const [redis, setRedis]     = useState<RedisStats | null>(null);
  const [services, setServices] = useState<ServicesData | null>(null);
  const [loading, setLoading] = useState(true);
  const [lastUpdate, setLastUpdate] = useState<Date | null>(null);
  const [errors, setErrors]   = useState<string[]>([]);

  const fetchAll = useCallback(async () => {
    setLoading(true);
    const errs: string[] = [];

    const results = await Promise.allSettled([
      fetchSystemOverview(),
      fetchVarnishStats(),
      fetchApacheStats(),
      fetchRedisStats(),
      fetchServices(),
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
    : Object.entries(sys?.services ?? {}).map(([name, status]) => ({ name, status, description: '' }));

  const activeCount  = allServices.filter(s => ['running','active'].includes(s.status)).length;
  const inactiveCount = allServices.length - activeCount;

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
        <Alert severity="warning" sx={{ mb: 2 }}>
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

      {/* ── Main grid ── */}
      <Grid container spacing={2}>

        {/* ── CPU / Memory / Disk gauges ── */}
        <Grid size={{ xs: 12, md: 4 }}>
          <SectionCard title="Server Resources" icon={<DeveloperBoard sx={{ color: '#3b82f6' }} />}>
            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2.5 }}>
              {/* CPU */}
              <Box>
                <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 0.5 }}>
                  <Typography variant="caption" sx={{ fontWeight: 600 }}>CPU Load (1m)</Typography>
                  <Typography variant="caption" sx={{ color: loadColor, fontWeight: 700 }}>{load1.toFixed(2)}</Typography>
                </Box>
                <GaugeBar value={load1} max={16} color={loadColor} />
                <Typography variant="caption" sx={{ color: 'text.disabled' }}>
                  5m: {load5} · 15m: {load15} · {sys?.uptime ?? '—'}
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

        {/* ── Services Status ── */}
        <Grid size={{ xs: 12, md: 4 }}>
          <SectionCard
            title="Services"
            icon={<Dns sx={{ color: '#8b5cf6' }} />}
            action={
              <Box sx={{ display: 'flex', gap: 0.8 }}>
                <Chip label={`${activeCount} OK`} size="small" sx={{ bgcolor: '#16a34a22', color: '#22c55e', fontSize: '0.7rem' }} />
                {inactiveCount > 0 && <Chip label={`${inactiveCount} ↓`} size="small" sx={{ bgcolor: '#ef444422', color: '#ef4444', fontSize: '0.7rem' }} />}
              </Box>
            }
          >
            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 0.8 }}>
              {allServices.length === 0 ? (
                <Typography variant="caption" sx={{ color: 'text.disabled' }}>No service data</Typography>
              ) : (
                allServices.map((svc: any, i: number) => {
                  const isOk = ['running', 'active'].includes(svc.status);
                  const color = SERVICE_STATUS_COLOR[svc.status] ?? '#94a3b8';
                  return (
                    <Box key={i} sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', py: 0.4, borderBottom: '1px solid rgba(255,255,255,0.04)' }}>
                      <Typography variant="caption" sx={{ fontFamily: 'monospace', fontWeight: 600 }}>{svc.name}</Typography>
                      <Chip
                        label={svc.status}
                        size="small"
                        icon={isOk ? <CheckCircle sx={{ fontSize: '12px !important' }} /> : <Cancel sx={{ fontSize: '12px !important' }} />}
                        sx={{ bgcolor: color + '22', color, fontSize: '0.65rem', height: 20, '& .MuiChip-icon': { color } }}
                      />
                    </Box>
                  );
                })
              )}
            </Box>
          </SectionCard>
        </Grid>

        {/* ── Varnish Details ── */}
        <Grid size={{ xs: 12, md: 4 }}>
          <SectionCard title="Varnish Cache" icon={<Cached sx={{ color: '#06b6d4' }} />}>
            {!varnish ? (
              <Typography variant="caption" sx={{ color: 'text.disabled' }}>No Varnish data</Typography>
            ) : (
              <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                <Box>
                  <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 0.5 }}>
                    <Typography variant="caption" sx={{ fontWeight: 600 }}>Hit Rate</Typography>
                    <Typography variant="caption" sx={{ color: hitColor, fontWeight: 800 }}>{hitRate.toFixed(1)}%</Typography>
                  </Box>
                  <GaugeBar value={hitRate} color={hitColor} />
                </Box>
                <Divider />
                {[
                  ['Total Requests', varnish.hits + varnish.misses],
                  ['Cache Hits',    varnish.hits],
                  ['Cache Misses',  varnish.misses],
                  ['Storage Used',  varnish.storage?.used ?? '—'],
                  ['Uptime',        varnish.uptime_seconds ? `${Math.floor(varnish.uptime_seconds / 3600)}h` : '—'],
                ].map(([k, v]) => (
                  <Box key={k as string} sx={{ display: 'flex', justifyContent: 'space-between' }}>
                    <Typography variant="caption" sx={{ color: 'text.disabled' }}>{k}</Typography>
                    <Typography variant="caption" sx={{ fontWeight: 700, fontFamily: 'monospace' }}>
                      {typeof v === 'number' ? v.toLocaleString() : v}
                    </Typography>
                  </Box>
                ))}
                {/* Device breakdown */}
                {varnish.devices && (
                  <>
                    <Divider />
                    <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 700 }}>BY DEVICE</Typography>
                    {Object.entries(varnish.devices).map(([dev, d]: [string, any]) => (
                      <Box key={dev} sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                        <Typography variant="caption" sx={{ textTransform: 'capitalize' }}>{dev}</Typography>
                        <Box sx={{ display: 'flex', gap: 1 }}>
                          <Typography variant="caption" sx={{ color: 'text.disabled' }}>{d.percentage}%</Typography>
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

        {/* ── Apache ── */}
        <Grid size={{ xs: 12, md: 4 }}>
          <SectionCard title="Apache HTTP" icon={<Http sx={{ color: '#f97316' }} />}>
            {!apache ? (
              <Typography variant="caption" sx={{ color: 'text.disabled' }}>No Apache data</Typography>
            ) : (
              <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1.2 }}>
                <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <Typography variant="caption" sx={{ color: 'text.disabled' }}>Status</Typography>
                  <StatusDot ok={apache.running} label={apache.running ? 'Running' : 'Down'} />
                </Box>
                {apache.version && (
                  <Box sx={{ display: 'flex', justifyContent: 'space-between' }}>
                    <Typography variant="caption" sx={{ color: 'text.disabled' }}>Version</Typography>
                    <Typography variant="caption" sx={{ fontFamily: 'monospace', fontWeight: 600 }}>{apache.version}</Typography>
                  </Box>
                )}
                <Box sx={{ display: 'flex', justifyContent: 'space-between' }}>
                  <Typography variant="caption" sx={{ color: 'text.disabled' }}>Processes</Typography>
                  <Typography variant="caption" sx={{ fontWeight: 700 }}>{apache.processes ?? '—'}</Typography>
                </Box>
                <Divider />
                <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 700 }}>PORTS</Typography>
                {[
                  ['HTTP :80',    apache.ports?.http],
                  ['SSL :443',   apache.ports?.ssl ?? apache.ports?.https],
                  ['Backend :81', apache.ports?.backend ?? false],
                ].map(([label, ok]) => (
                  <Box key={label as string} sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <Typography variant="caption">{label}</Typography>
                    <StatusDot ok={!!ok} />
                  </Box>
                ))}
              </Box>
            )}
          </SectionCard>
        </Grid>

        {/* ── Redis ── */}
        <Grid size={{ xs: 12, md: 4 }}>
          <SectionCard title="Redis Cache" icon={<Cloud sx={{ color: '#ef4444' }} />}>
            {!redis ? (
              <Typography variant="caption" sx={{ color: 'text.disabled' }}>No Redis data</Typography>
            ) : (
              <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1.2 }}>
                <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <Typography variant="caption" sx={{ color: 'text.disabled' }}>Status</Typography>
                  <StatusDot ok={redis.connected} label={redis.connected ? 'Connected' : 'Disconnected'} />
                </Box>
                {[
                  ['Memory Used',    redis.memory?.used_human ?? '—'],
                  ['Peak Memory',    redis.memory?.peak_human ?? '—'],
                  ['Total Keys',     redis.keyspace?.total_keys?.toLocaleString() ?? '—'],
                  ['Hit Rate',       `${(redis.stats?.hit_rate ?? 0).toFixed(1)}%`],
                  ['Ops/sec',        (redis.stats?.ops_per_sec ?? 0).toLocaleString()],
                  ['Clients',        redis.stats?.connected_clients ?? '—'],
                ].map(([k, v]) => (
                  <Box key={k as string} sx={{ display: 'flex', justifyContent: 'space-between' }}>
                    <Typography variant="caption" sx={{ color: 'text.disabled' }}>{k}</Typography>
                    <Typography variant="caption" sx={{ fontWeight: 700, fontFamily: 'monospace' }}>{v}</Typography>
                  </Box>
                ))}
              </Box>
            )}
          </SectionCard>
        </Grid>

        {/* ── Top Processes ── */}
        <Grid size={{ xs: 12, md: 4 }}>
          <SectionCard title="Top Processes" icon={<Speed sx={{ color: '#a78bfa' }} />}>
            {!sys?.top_procs?.length ? (
              <Typography variant="caption" sx={{ color: 'text.disabled' }}>No process data</Typography>
            ) : (
              <TableContainer>
                <Table size="small">
                  <TableHead>
                    <TableRow>
                      <TableCell sx={{ fontSize: '0.7rem', color: 'text.disabled', py: 0.5 }}>PID</TableCell>
                      <TableCell sx={{ fontSize: '0.7rem', color: 'text.disabled', py: 0.5 }} align="right">CPU%</TableCell>
                      <TableCell sx={{ fontSize: '0.7rem', color: 'text.disabled', py: 0.5 }} align="right">MEM%</TableCell>
                      <TableCell sx={{ fontSize: '0.7rem', color: 'text.disabled', py: 0.5 }}>Command</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {sys.top_procs.slice(0, 8).map((p, i) => (
                      <TableRow key={i}>
                        <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.72rem', py: 0.4 }}>{p.pid}</TableCell>
                        <TableCell align="right" sx={{ fontFamily: 'monospace', fontSize: '0.72rem', py: 0.4, color: parseFloat(p.cpu) > 10 ? '#f59e0b' : 'text.primary' }}>
                          {p.cpu}
                        </TableCell>
                        <TableCell align="right" sx={{ fontFamily: 'monospace', fontSize: '0.72rem', py: 0.4 }}>{p.mem}</TableCell>
                        <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.68rem', py: 0.4, maxWidth: 140, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
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

        {/* ── Network Stack Info ── */}
        <Grid size={{ xs: 12 }}>
          <Card>
            <CardContent>
              <Typography variant="h6" sx={{ fontWeight: 800, mb: 2, display: 'flex', alignItems: 'center', gap: 1 }}>
                <NetworkCheck sx={{ color: '#10b981' }} /> Network Stack — technostationery.com
              </Typography>
              <Grid container spacing={2}>
                {[
                  { label: 'Origin IP',         value: '205.134.249.177',    color: '#3b82f6' },
                  { label: 'Cloudflare Proxy',  value: 'Active — Free Plan', color: '#f97316' },
                  { label: 'SSL Mode',           value: 'Strict',             color: '#22c55e' },
                  { label: 'Port :80 → Varnish', value: 'Frontend cache',     color: '#06b6d4' },
                  { label: 'Port :81 → Apache',  value: 'Backend origin',     color: '#8b5cf6' },
                  { label: 'Port :443 → Apache', value: 'SSL termination',    color: '#a78bfa' },
                  { label: 'PHP-FPM',            value: 'Multi-site pools',   color: '#f59e0b' },
                  { label: 'MariaDB',            value: ':3306 local socket', color: '#ec4899' },
                ].map(({ label, value, color }) => (
                  <Grid key={label} size={{ xs: 6, sm: 4, md: 3 }}>
                    <Box sx={{ p: 1.5, borderRadius: 1.5, border: '1px solid', borderColor: 'divider', background: 'rgba(255,255,255,0.02)' }}>
                      <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 600, display: 'block', mb: 0.3 }}>{label}</Typography>
                      <Typography variant="caption" sx={{ fontFamily: 'monospace', fontWeight: 700, color }}>{value}</Typography>
                    </Box>
                  </Grid>
                ))}
              </Grid>

              <Divider sx={{ my: 2 }} />

              <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 700 }}>STACK OVERVIEW</Typography>
              <Box sx={{ mt: 1, display: 'flex', gap: 1, flexWrap: 'wrap' }}>
                {[
                  { label: 'Cloudflare CDN', color: '#f97316' },
                  { label: '→ Varnish :80', color: '#06b6d4' },
                  { label: '→ Apache :81', color: '#f59e0b' },
                  { label: '→ PHP-FPM', color: '#8b5cf6' },
                  { label: '→ MariaDB', color: '#3b82f6' },
                  { label: '+ Redis Cache', color: '#ef4444' },
                ].map(({ label, color }) => (
                  <Chip key={label} label={label} size="small"
                    sx={{ bgcolor: color + '22', color, fontSize: '0.72rem', fontWeight: 600 }} />
                ))}
              </Box>
            </CardContent>
          </Card>
        </Grid>

      </Grid>
    </Box>
  );
}

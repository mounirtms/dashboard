import {
  Box, Typography, Grid, Card, CardContent, Button, Divider, Alert,
  CircularProgress, Chip, LinearProgress, Table, TableBody, TableCell,
  TableContainer, TableHead, TableRow, Paper, IconButton, Tabs, Tab, Skeleton
} from '@mui/material';
import {
  Cached, CleaningServices, LocalFireDepartment, Hub, Bolt, Storage,
  Speed, Memory, Refresh, PlayArrow, Stop, CheckCircle, Warning,
  TrendingUp, Devices, Web, Phone, Tablet, Info, HealthAndSafety
} from '@mui/icons-material';
import { useState, useCallback } from 'react';
import apiClient from '../api/client';
import { fetchVarnishStats, fetchRedisStats } from '../api/system';
import { usePolling } from '../hooks/usePolling';
import ConsoleOutput from '../components/common/ConsoleOutput';

const SITES = [
  { key: 'prod', name: 'Production', url: 'technostationery.com' },
];

// ---------- types ----------
interface VarnishStats {
  hit_ratio?: number;
  hits?: number;
  misses?: number;
  total_requests?: number;
  storage?: { usage_pct?: number; used?: string };
  uptime?: number;
  uptime_seconds?: number;
  devices?: {
    desktop?: DeviceStat;
    mobile?: DeviceStat;
    tablet?: DeviceStat;
  };
}

interface DeviceStat {
  hits: number;
  misses: number;
  total: number;
  hit_rate: number;
  bytes_human?: string;
  percentage?: number;
}

interface RedisStats {
  connected_clients?: number;
  used_memory_human?: string;
  keyspace_hits?: number;
  keyspace_misses?: number;
}

interface CacheStats {
  varnish?: {
    hits?: number;
    misses?: number;
    cached_objects?: number;
    total_requests?: number;
    hit_rate?: number;
    health_status?: string;
  };
  recent_activity?: { last_hour_hits?: number; last_hour_misses?: number };
  top_cached?: Array<{ url: string; count: number }>;
  devices?: {
    desktop?: DeviceStat;
    mobile?: DeviceStat;
    tablet?: DeviceStat;
    _distribution?: { desktop_pct?: number; mobile_pct?: number; tablet_pct?: number };
  };
  recommendations?: Array<{ severity: 'error' | 'warning' | 'info' | 'success'; message: string }>;
  cloudflare?: { edge_status?: string; cf_ray?: string; warning?: string };
}

interface WarmupStatus {
  running?: boolean;
  complete?: boolean;
  hits?: number;
  misses?: number;
  hit_rate?: number;
}

interface CacheBundle {
  varnish: VarnishStats | null;
  redis: RedisStats | null;
  stats: CacheStats | null;
  warmup: WarmupStatus | null;
}

// ---------- component ----------
export default function CacheControlPage() {
  const [executing, setExecuting] = useState<string | null>(null);
  const [output, setOutput] = useState<string>('');
  const [testResult, setTestResult] = useState<{
    cache_status?: string; response_time_ms?: number; http_code?: number;
    age_seconds?: number; device_type?: string; vary_header?: string; error?: string;
  } | null>(null);
  const [testUrl, setTestUrl] = useState('/');
  const [multiDeviceTest, setMultiDeviceTest] = useState<Record<string, {
    cache_status?: string; response_time_ms?: number; http_code?: number;
    age_seconds?: number; device_type?: string;
  }> | null>(null);
  const [tab, setTab] = useState(0);

  // ---- usePolling: stale-while-revalidate, no flicker on 30s polls ----
  const fetcher = useCallback(async (): Promise<CacheBundle> => {
    const [v, r, stats, warmup] = await Promise.all([
      fetchVarnishStats().catch(() => null),
      fetchRedisStats().catch(() => null),
      apiClient.get('/api/cache-control.php?action=stats').then(res => res.data as CacheStats).catch(() => null),
      apiClient.get('/api/cache-control.php?action=warmup_status').then(res => res.data as WarmupStatus).catch(() => null),
    ]);
    return { varnish: v, redis: r, stats, warmup };
  }, []);

  const { data, loading, refreshing, refetch } = usePolling<CacheBundle>(fetcher, 30_000);

  const varnishData  = data?.varnish  ?? null;
  const redisData    = data?.redis    ?? null;
  const cacheStats   = data?.stats    ?? null;
  const warmupStatus = data?.warmup   ?? null;

  // ---- derived values ----
  const devices        = cacheStats?.devices ?? {};
  const distribution   = cacheStats?.devices?._distribution ?? {};
  const recommendations = cacheStats?.recommendations ?? [];
  const cloudflare     = cacheStats?.cloudflare ?? {};
  const healthStatus   = cacheStats?.varnish?.health_status ?? 'unknown';
  const overallHitRate = varnishData?.hit_ratio ?? cacheStats?.varnish?.hit_rate ?? 0;

  // ---- mutation handlers ----
  const handleCacheOp = async (site: string, op: string) => {
    const actionKey = `${site}-${op}`;
    setExecuting(actionKey);
    setOutput(`> Initiating ${op} for ${site}...\n`);
    try {
      const type = op.includes('magento') ? 'magento' : op.includes('varnish') ? 'varnish' : 'all';
      const { data: res } = await apiClient.get(`/api/cache-control.php?action=purge&type=${type}`);
      setOutput(prev => prev + (res.output || 'Operation completed.') + '\n');
      refetch();
    } catch (e: unknown) {
      const msg = e instanceof Error ? e.message : String(e);
      setOutput(prev => prev + `Error: ${msg}\n`);
    } finally {
      setExecuting(null);
    }
  };

  const handleWarmup = async () => {
    setExecuting('warmup');
    setOutput('> Starting cache warmup...\n');
    try {
      const { data: res } = await apiClient.post('/api/cache-control.php?action=warmup', { urls: 500, parallel: 6 });
      setOutput(prev => prev + res.message + '\n');
    } catch (e: unknown) {
      const msg = e instanceof Error ? e.message : String(e);
      setOutput(prev => prev + `Error: ${msg}\n`);
    } finally {
      setExecuting(null);
      refetch();
    }
  };

  const handleTestUrl = async () => {
    try {
      const { data: res } = await apiClient.get(`/api/cache-control.php?action=test_url&url=${encodeURIComponent(testUrl)}`);
      setTestResult(res);
    } catch (e: unknown) {
      const msg = e instanceof Error ? e.message : String(e);
      setTestResult({ error: msg });
    }
  };

  const handleMultiDeviceTest = async () => {
    try {
      const { data: res } = await apiClient.get(`/api/cache-control.php?action=test_url_multi&url=${encodeURIComponent(testUrl)}`);
      setMultiDeviceTest(res);
    } catch (e: unknown) {
      const msg = e instanceof Error ? e.message : String(e);
      setMultiDeviceTest({ error: { cache_status: msg } } as never);
    }
  };

  return (
    <Box>
      {/* Header */}
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 2, mb: 0.5 }}>
            <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em' }}>
              Cache Control Center
            </Typography>
            <Chip
              label={healthStatus.toUpperCase()}
              size="small"
              color={healthStatus === 'healthy' ? 'success' : healthStatus === 'warning' ? 'warning' : healthStatus === 'degraded' ? 'warning' : 'error'}
              icon={<HealthAndSafety />}
            />
          </Box>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Varnish cache management, per-device hit rates, and warmup control
            {refreshing && (
              <CircularProgress size={10} sx={{ ml: 1, verticalAlign: 'middle' }} />
            )}
          </Typography>
        </Box>
        <Box sx={{ display: 'flex', gap: 2, alignItems: 'center' }}>
          {loading ? (
            <Skeleton variant="rounded" width={140} height={58} />
          ) : (
            <Card
              variant="outlined"
              sx={{
                px: 2, py: 1,
                backgroundColor: overallHitRate > 70 ? 'rgba(16,185,129,0.05)' : overallHitRate > 40 ? 'rgba(255,152,0,0.05)' : 'rgba(244,67,54,0.05)',
                borderColor: overallHitRate > 70 ? 'rgba(16,185,129,0.2)' : overallHitRate > 40 ? 'rgba(255,152,0,0.2)' : 'rgba(244,67,54,0.2)',
              }}
            >
              <Typography variant="caption" sx={{ color: overallHitRate > 70 ? 'success.main' : overallHitRate > 40 ? 'warning.main' : 'error.main', fontWeight: 700, display: 'block' }}>
                VARNISH HIT RATE
              </Typography>
              <Typography variant="h5" sx={{ fontWeight: 800, color: overallHitRate > 70 ? 'success.main' : overallHitRate > 40 ? 'warning.main' : 'error.main' }}>
                {overallHitRate.toFixed(1)}%
              </Typography>
            </Card>
          )}
          <IconButton onClick={refetch} disabled={refreshing}><Refresh /></IconButton>
        </Box>
      </Box>

      {/* Tabs */}
      <Tabs value={tab} onChange={(_, v) => setTab(v)} sx={{ mb: 3 }}>
        <Tab label="Overview" />
        <Tab label="Per-Device" />
        <Tab label="Controls" />
        <Tab label="Test URL" />
      </Tabs>

      {/* Initial skeleton load */}
      {loading && (
        <Grid container spacing={2}>
          {[...Array(4)].map((_, i) => (
            <Grid key={i} size={{ xs: 12, md: 6 }}>
              <Skeleton variant="rounded" height={180} />
            </Grid>
          ))}
        </Grid>
      )}

      {/* Refreshing progress bar (background poll) */}
      {!loading && refreshing && <LinearProgress sx={{ mb: 1 }} />}

      {/* Overview Tab */}
      {!loading && tab === 0 && (
        <Grid container spacing={2}>
          {/* Hit Rate Card */}
          <Grid size={{ xs: 12, md: 6 }}>
            <Card>
              <CardContent>
                <Typography variant="h6" sx={{ mb: 2, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <Speed sx={{ color: 'primary.main' }} /> Varnish Cache Stats
                </Typography>
                <Grid container spacing={2}>
                  <Grid size={6}>
                    <Typography variant="caption" color="text.secondary">Hits</Typography>
                    <Typography variant="h5" sx={{ fontWeight: 700, color: 'success.main' }}>{cacheStats?.varnish?.hits?.toLocaleString() ?? '—'}</Typography>
                  </Grid>
                  <Grid size={6}>
                    <Typography variant="caption" color="text.secondary">Misses</Typography>
                    <Typography variant="h5" sx={{ fontWeight: 700, color: 'error.main' }}>{cacheStats?.varnish?.misses?.toLocaleString() ?? '—'}</Typography>
                  </Grid>
                  <Grid size={6}>
                    <Typography variant="caption" color="text.secondary">Cached Objects</Typography>
                    <Typography variant="h6" sx={{ fontWeight: 700 }}>{cacheStats?.varnish?.cached_objects?.toLocaleString() ?? '—'}</Typography>
                  </Grid>
                  <Grid size={6}>
                    <Typography variant="caption" color="text.secondary">Total Requests</Typography>
                    <Typography variant="h6" sx={{ fontWeight: 700 }}>{cacheStats?.varnish?.total_requests?.toLocaleString() ?? '—'}</Typography>
                  </Grid>
                </Grid>
                <Box sx={{ mt: 2 }}>
                  <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 0.5 }}>
                    <Typography variant="caption">Hit Rate</Typography>
                    <Typography variant="caption" sx={{ fontWeight: 700 }}>{overallHitRate.toFixed(1)}%</Typography>
                  </Box>
                  <LinearProgress
                    variant="determinate"
                    value={Math.min(overallHitRate, 100)}
                    sx={{
                      height: 8, borderRadius: 4, backgroundColor: 'action.hover',
                      '& .MuiLinearProgress-bar': { backgroundColor: overallHitRate > 70 ? '#10b981' : overallHitRate > 40 ? '#f59e0b' : '#ef4444' },
                    }}
                  />
                </Box>
              </CardContent>
            </Card>
          </Grid>

          {/* Recent Activity */}
          <Grid size={{ xs: 12, md: 6 }}>
            <Card>
              <CardContent>
                <Typography variant="h6" sx={{ mb: 2, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <TrendingUp sx={{ color: 'primary.main' }} /> Recent Activity
                </Typography>
                <Grid container spacing={2}>
                  <Grid size={6}>
                    <Typography variant="caption" color="text.secondary">Last Hour Hits</Typography>
                    <Typography variant="h5" sx={{ fontWeight: 700 }}>{cacheStats?.recent_activity?.last_hour_hits ?? '—'}</Typography>
                  </Grid>
                  <Grid size={6}>
                    <Typography variant="caption" color="text.secondary">Last Hour Misses</Typography>
                    <Typography variant="h5" sx={{ fontWeight: 700 }}>{cacheStats?.recent_activity?.last_hour_misses ?? '—'}</Typography>
                  </Grid>
                </Grid>
                {cacheStats?.top_cached && cacheStats.top_cached.length > 0 && (
                  <>
                    <Divider sx={{ my: 2 }} />
                    <Typography variant="subtitle2" sx={{ mb: 1, fontWeight: 600 }}>Top Cached URLs</Typography>
                    <TableContainer component={Paper} variant="outlined">
                      <Table size="small">
                        <TableHead>
                          <TableRow>
                            <TableCell>URL</TableCell>
                            <TableCell align="right">Hits</TableCell>
                          </TableRow>
                        </TableHead>
                        <TableBody>
                          {cacheStats.top_cached.slice(0, 8).map((row, i) => (
                            <TableRow key={i}>
                              <TableCell sx={{ fontSize: '0.75rem' }}>{row.url}</TableCell>
                              <TableCell align="right" sx={{ fontWeight: 600 }}>{row.count}</TableCell>
                            </TableRow>
                          ))}
                        </TableBody>
                      </Table>
                    </TableContainer>
                  </>
                )}
              </CardContent>
            </Card>
          </Grid>

          {/* Warmup Status */}
          <Grid size={{ xs: 12 }}>
            <Card>
              <CardContent>
                <Typography variant="h6" sx={{ mb: 2, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <PlayArrow sx={{ color: warmupStatus?.running ? 'success.main' : 'text.secondary' }} /> Cache Warmup
                </Typography>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                  <Chip
                    label={warmupStatus?.running ? 'Running' : warmupStatus?.complete ? 'Complete' : 'Idle'}
                    color={warmupStatus?.running ? 'success' : warmupStatus?.complete ? 'primary' : 'default'}
                  />
                  {warmupStatus?.complete && (
                    <>
                      <Typography>Hits: {warmupStatus.hits?.toLocaleString()}</Typography>
                      <Typography>Misses: {warmupStatus.misses?.toLocaleString()}</Typography>
                      <Typography sx={{ fontWeight: 700 }}>Hit Rate: {warmupStatus.hit_rate}%</Typography>
                    </>
                  )}
                  <Button
                    variant="contained"
                    startIcon={executing === 'warmup' ? <CircularProgress size={16} /> : <PlayArrow />}
                    onClick={handleWarmup}
                    disabled={executing === 'warmup' || warmupStatus?.running}
                  >
                    {warmupStatus?.running ? 'Running...' : 'Start Warmup (500 URLs)'}
                  </Button>
                </Box>
              </CardContent>
            </Card>
          </Grid>

          {/* Cloudflare Edge Status */}
          {cloudflare?.edge_status && (
            <Grid size={{ xs: 12 }}>
              <Card sx={{ borderColor: cloudflare.edge_status === 'HIT' ? 'warning.main' : 'success.main' }}>
                <CardContent>
                  <Typography variant="h6" sx={{ mb: 2, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                    <Hub sx={{ color: cloudflare.edge_status === 'HIT' ? 'warning.main' : 'success.main' }} /> Cloudflare Edge Cache
                  </Typography>
                  <Box sx={{ display: 'flex', alignItems: 'center', gap: 2, flexWrap: 'wrap' }}>
                    <Chip label={`Edge: ${cloudflare.edge_status}`} color={cloudflare.edge_status === 'HIT' ? 'warning' : 'success'} size="small" />
                    {cloudflare.cf_ray && <Typography variant="body2">Ray: {cloudflare.cf_ray}</Typography>}
                    {cloudflare.warning && (
                      <Alert severity="warning" sx={{ width: '100%', mt: 1 }}>{cloudflare.warning}</Alert>
                    )}
                  </Box>
                </CardContent>
              </Card>
            </Grid>
          )}

          {/* Recommendations */}
          {recommendations.length > 0 && (
            <Grid size={{ xs: 12 }}>
              <Card>
                <CardContent>
                  <Typography variant="h6" sx={{ mb: 2, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                    <Info sx={{ color: 'primary.main' }} /> Cache Recommendations
                  </Typography>
                  {recommendations.map((rec, i) => (
                    <Alert key={i} severity={rec.severity} sx={{ mb: 1 }}>{rec.message}</Alert>
                  ))}
                </CardContent>
              </Card>
            </Grid>
          )}
        </Grid>
      )}

      {/* Per-Device Tab */}
      {!loading && tab === 1 && (
        <Grid container spacing={2}>
          {[
            { key: 'desktop' as const, label: 'Desktop', icon: <Web />, color: '#3b82f6' },
            { key: 'mobile'  as const, label: 'Mobile',  icon: <Phone />, color: '#10b981' },
            { key: 'tablet'  as const, label: 'Tablet',  icon: <Tablet />, color: '#f59e0b' },
          ].map((device) => {
            const d = devices[device.key] ?? { hits: 0, misses: 0, total: 0, hit_rate: 0, bytes_human: '0 B' };
            const trafficPct = distribution[`${device.key}_pct` as keyof typeof distribution] ?? 0;
            return (
              <Grid key={device.key} size={{ xs: 12, md: 4 }}>
                <Card sx={{ borderColor: `${device.color}33` }}>
                  <CardContent>
                    <Typography variant="h6" sx={{ mb: 2, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1, color: device.color }}>
                      {device.icon} {device.label}
                    </Typography>
                    <Grid container spacing={2}>
                      <Grid size={6}>
                        <Typography variant="caption" color="text.secondary">Hits</Typography>
                        <Typography variant="h5" sx={{ fontWeight: 700, color: 'success.main' }}>{d.hits}</Typography>
                      </Grid>
                      <Grid size={6}>
                        <Typography variant="caption" color="text.secondary">Misses</Typography>
                        <Typography variant="h5" sx={{ fontWeight: 700, color: 'error.main' }}>{d.misses}</Typography>
                      </Grid>
                      <Grid size={12}>
                        <Typography variant="caption" color="text.secondary">Traffic Share</Typography>
                        <Typography variant="h6" sx={{ fontWeight: 700 }}>{trafficPct}%</Typography>
                      </Grid>
                      <Grid size={12}>
                        <Typography variant="caption" color="text.secondary">Data Transferred</Typography>
                        <Typography variant="body2" sx={{ fontWeight: 600 }}>{d.bytes_human ?? '0 B'}</Typography>
                      </Grid>
                    </Grid>
                    <Box sx={{ mt: 2 }}>
                      <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 0.5 }}>
                        <Typography variant="caption">Hit Rate</Typography>
                        <Typography variant="caption" sx={{ fontWeight: 700 }}>{d.hit_rate}%</Typography>
                      </Box>
                      <LinearProgress
                        variant="determinate"
                        value={Math.min(d.hit_rate, 100)}
                        sx={{ height: 8, borderRadius: 4, backgroundColor: 'action.hover', '& .MuiLinearProgress-bar': { backgroundColor: device.color } }}
                      />
                    </Box>
                  </CardContent>
                </Card>
              </Grid>
            );
          })}
        </Grid>
      )}

      {/* Controls Tab */}
      {!loading && tab === 2 && (
        <Grid container spacing={2}>
          {SITES.map((site) => (
            <Grid key={site.key} size={{ xs: 12 }}>
              <Card>
                <CardContent>
                  <Typography variant="h6" sx={{ mb: 2, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                    <Hub sx={{ color: 'primary.main' }} /> {site.name}
                  </Typography>
                  <Box sx={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: 1 }}>
                    <CacheButton label="Magento Clean"  icon={<CleaningServices />}      loading={executing === `${site.key}-magento_clean`}  onClick={() => handleCacheOp(site.key, 'magento_clean')} />
                    <CacheButton label="Magento Flush"  icon={<Cached />}                loading={executing === `${site.key}-magento_flush`}  onClick={() => handleCacheOp(site.key, 'magento_flush')} />
                    <CacheButton label="Varnish Purge"  icon={<Bolt />}    color="info"  loading={executing === `${site.key}-varnish_purge`}  onClick={() => handleCacheOp(site.key, 'varnish_purge')} />
                    <CacheButton label="Full Purge"     icon={<LocalFireDepartment />} color="error" loading={executing === `${site.key}-full`} onClick={() => handleCacheOp(site.key, 'full')} />
                  </Box>
                </CardContent>
              </Card>
            </Grid>
          ))}
        </Grid>
      )}

      {/* Test URL Tab */}
      {!loading && tab === 3 && (
        <Grid container spacing={2}>
          <Grid size={{ xs: 12 }}>
            <Card>
              <CardContent>
                <Typography variant="h6" sx={{ mb: 2, fontWeight: 700 }}>Test URL Cache Status</Typography>
                <Box sx={{ display: 'flex', gap: 1, mb: 2 }}>
                  <input
                    value={testUrl}
                    onChange={e => setTestUrl(e.target.value)}
                    placeholder="/page-url"
                    style={{ flex: 1, padding: '8px 12px', borderRadius: 4, border: '1px solid #ccc', fontSize: '0.875rem' }}
                  />
                  <Button variant="contained" onClick={handleTestUrl}>Test Single</Button>
                  <Button variant="contained" color="secondary" onClick={handleMultiDeviceTest}>Test All Devices</Button>
                </Box>
                {testResult && (
                  <Alert severity={testResult.cache_status === 'HIT' ? 'success' : 'warning'} sx={{ mb: 2 }}>
                    <Typography variant="body2" sx={{ fontWeight: 600 }}>
                      {testResult.cache_status} — {testResult.response_time_ms}ms — HTTP {testResult.http_code}
                      {testResult.age_seconds != null && testResult.age_seconds > 0 && ` (Age: ${testResult.age_seconds}s)`}
                      {testResult.device_type && ` [Device: ${testResult.device_type}]`}
                    </Typography>
                    {testResult.vary_header && (
                      <Typography variant="caption">Vary: {testResult.vary_header}</Typography>
                    )}
                  </Alert>
                )}
              </CardContent>
            </Card>
          </Grid>

          {/* Multi-Device Test Results */}
          {multiDeviceTest && (
            <Grid size={{ xs: 12 }}>
              <Card>
                <CardContent>
                  <Typography variant="h6" sx={{ mb: 2, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                    <Devices /> Multi-Device Cache Test Results
                  </Typography>
                  <Grid container spacing={2}>
                    {[
                      { key: 'desktop', label: 'Desktop', color: '#3b82f6', icon: <Web /> },
                      { key: 'mobile',  label: 'Mobile',  color: '#10b981', icon: <Phone /> },
                      { key: 'tablet',  label: 'Tablet',  color: '#f59e0b', icon: <Tablet /> },
                    ].map((device) => {
                      const r = multiDeviceTest[device.key];
                      if (!r) return null;
                      return (
                        <Grid key={device.key} size={{ xs: 12, md: 4 }}>
                          <Card sx={{ borderColor: `${device.color}33` }}>
                            <CardContent>
                              <Typography variant="subtitle1" sx={{ fontWeight: 700, color: device.color, mb: 1, display: 'flex', alignItems: 'center', gap: 1 }}>
                                {device.icon} {device.label}
                              </Typography>
                              <Alert severity={r.cache_status === 'HIT' ? 'success' : 'warning'} sx={{ mb: 1 }}>
                                <Typography variant="body2" sx={{ fontWeight: 600 }}>
                                  {r.cache_status} — {r.response_time_ms}ms
                                </Typography>
                              </Alert>
                              <Typography variant="caption">HTTP {r.http_code} | Age: {r.age_seconds}s</Typography>
                              <br />
                              <Typography variant="caption">Detected: {r.device_type}</Typography>
                              {r.device_type !== device.key && (
                                <Typography variant="caption" color="error" sx={{ display: 'block', fontWeight: 700 }}>
                                  MISMATCH! Expected: {device.key}
                                </Typography>
                              )}
                            </CardContent>
                          </Card>
                        </Grid>
                      );
                    })}
                  </Grid>
                </CardContent>
              </Card>
            </Grid>
          )}
        </Grid>
      )}

      {output && (
        <ConsoleOutput text={output} onClear={() => setOutput('')} title="OPERATION LOG" autoScroll />
      )}
    </Box>
  );
}

// ---- sub-component (unchanged interface, typing improved) ----
interface CacheButtonProps {
  label: string;
  icon: React.ReactNode;
  onClick: () => void;
  loading?: boolean;
  color?: 'primary' | 'secondary' | 'error' | 'info' | 'success' | 'warning' | 'inherit';
}
function CacheButton({ label, icon, onClick, loading = false, color = 'primary' }: CacheButtonProps) {
  return (
    <Button
      fullWidth
      variant="outlined"
      color={color}
      startIcon={loading ? <CircularProgress size={16} color="inherit" /> : icon}
      disabled={loading}
      onClick={onClick}
      sx={{ justifyContent: 'flex-start', py: 1, px: 2, fontWeight: 600, fontSize: '0.75rem' }}
    >
      {label}
    </Button>
  );
}

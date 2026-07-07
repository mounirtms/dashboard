import {
  Box, Typography, Card, CardContent, Grid, LinearProgress,
  Chip, Table, TableBody, TableCell, TableContainer, TableHead, TableRow,
  useTheme, Alert, Tooltip,
} from '@mui/material';
import {
  AreaChart, Area, BarChart, Bar, XAxis, YAxis, CartesianGrid,
  Tooltip as RTooltip, ResponsiveContainer, Legend, ReferenceLine,
} from 'recharts';
import { CheckCircle, Cancel, Speed, CachedOutlined, CloudDone, OfflineBolt } from '@mui/icons-material';
import { useCloudflareData } from '../hooks/useCloudflareData';
import LoadingState from '../components/common/LoadingState';
import StatCard from '../components/common/StatCard';
import { formatNumber, formatBytes } from '../utils/formatters';

// Describe each Cloudflare setting with friendly label + "good" value
const CF_SETTINGS_META: { key: string; label: string; good: string | string[] }[] = [
  { key: 'cache_level',               label: 'Cache Level',          good: ['aggressive', 'basic'] },
  { key: 'brotli',                    label: 'Brotli',               good: 'on' },
  { key: 'http3',                     label: 'HTTP/3 (QUIC)',        good: 'on' },
  { key: 'http2',                     label: 'HTTP/2',               good: 'on' },
  { key: 'early_hints',               label: 'Early Hints',          good: 'on' },
  { key: 'automatic_https_rewrites',  label: 'Auto HTTPS Rewrites',  good: 'on' },
  { key: 'always_online',             label: 'Always Online',        good: 'on' },
  { key: 'rocket_loader',             label: 'Rocket Loader',        good: ['on', 'automatic'] },
  { key: 'minify_css',                label: 'Minify CSS',           good: 'on' },
  { key: 'minify_js',                 label: 'Minify JS',            good: 'on' },
  { key: 'polish',                    label: 'Polish (Images)',       good: ['lossy', 'lossless'] },
  { key: 'browser_cache_ttl',         label: 'Browser Cache TTL',    good: [] },
];

function isGood(key: string, val: any, good: string | string[]): boolean {
  if (key === 'browser_cache_ttl') return Number(val) >= 14400;
  const v = String(val).toLowerCase();
  if (Array.isArray(good)) return good.includes(v);
  return v === good;
}

export default function PerformancePage() {
  const theme = useTheme();
  const { data, loading, error } = useCloudflareData();

  if (loading) return <LoadingState />;
  if (error)   return <Alert severity="error" sx={{ mb: 2 }}>Error loading performance data: {error}</Alert>;
  if (!data)   return null;

  const gc    = `${theme.palette.divider}99`;
  const ratio = data.cache_hit_ratio ?? 0;
  const ratioColor = ratio > 80 ? '#22c55e' : ratio > 50 ? '#f59e0b' : '#ef4444';
  const totals: any = data.analytics_totals || {};
  const s      = data.settings || {};

  const uncached      = (totals.requests || 0) - (totals.cachedRequests || 0);
  const savingsPct    = totals.bytes > 0 ? Math.round((totals.cachedBytes / totals.bytes) * 100) : 0;
  const browserTTLh   = Math.round((Number(s.browser_cache_ttl) || 0) / 3600);

  // Per-day cache ratio trend
  const cacheRatioTrend = (data.analytics || []).map((d: any) => ({
    date:  d.date ? d.date.slice(5) : '?',
    ratio: d.requests > 0 ? Math.round((d.cachedRequests / d.requests) * 100) : 0,
    uncached: d.requests - d.cachedRequests,
    cached:   d.cachedRequests,
    bw:       d.bytes,
  }));

  // Count good/warn settings
  const goodCount = CF_SETTINGS_META.filter(m => isGood(m.key, s[m.key], m.good)).length;
  const scoreColor = goodCount >= 10 ? 'success' : goodCount >= 7 ? 'warning' : 'error';

  return (
    <Box>
      {/* ── Header ── */}
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: 2 }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>Performance</Typography>
          <Typography variant="body2" color="text.secondary">Cloudflare cache efficiency &amp; optimization settings</Typography>
        </Box>
        <Chip
          label={`${goodCount}/${CF_SETTINGS_META.length} settings optimal`}
          size="small"
          color={scoreColor}
          variant="outlined"
          sx={{ fontWeight: 700 }}
        />
      </Box>

      {/* ── KPI cards ── */}
      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 6, sm: 3 }}>
          <StatCard label="Cache Hit Ratio"    value={`${ratio}%`}                          color={ratio > 80 ? 'success' : ratio > 50 ? 'warning' : 'error'} icon={<CachedOutlined />} />
        </Grid>
        <Grid size={{ xs: 6, sm: 3 }}>
          <StatCard label="Cached Requests"    value={formatNumber(totals.cachedRequests)}   color="success" icon={<CloudDone />} />
        </Grid>
        <Grid size={{ xs: 6, sm: 3 }}>
          <StatCard label="Uncached Requests"  value={formatNumber(uncached)}                color="error"   icon={<OfflineBolt />} />
        </Grid>
        <Grid size={{ xs: 6, sm: 3 }}>
          <StatCard label="Bandwidth Savings"  value={`${savingsPct}%`}                      color="info"    icon={<Speed />} />
        </Grid>
      </Grid>

      {/* ── Cache ratio gauge + trend ── */}
      <Grid container spacing={3} sx={{ mb: 3 }}>
        <Grid size={{ xs: 12, md: 4 }}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 2 }}>Cache Hit Ratio</Typography>
              {/* Big gauge-style display */}
              <Box sx={{ textAlign: 'center', py: 2 }}>
                <Typography variant="h2" sx={{ fontWeight: 900, color: ratioColor, letterSpacing: '-0.05em' }}>
                  {ratio}<Typography component="span" variant="h4" sx={{ color: ratioColor }}>%</Typography>
                </Typography>
                <Typography variant="caption" color="text.disabled" sx={{ display: 'block', mb: 2 }}>
                  {formatNumber(totals.cachedRequests)} cached / {formatNumber(totals.requests)} total
                </Typography>
                <LinearProgress
                  variant="determinate"
                  value={Math.min(ratio, 100)}
                  sx={{
                    height: 12, borderRadius: 6,
                    backgroundColor: `${theme.palette.divider}66`,
                    '& .MuiLinearProgress-bar': { backgroundColor: ratioColor, borderRadius: 6 },
                  }}
                />
              </Box>
              <Box sx={{ mt: 3, display: 'grid', gap: 1 }}>
                {[
                  { label: 'Cached Bandwidth',   value: formatBytes(totals.cachedBytes) },
                  { label: 'Uncached Bandwidth',  value: formatBytes((totals.bytes || 0) - (totals.cachedBytes || 0)) },
                  { label: 'Browser Cache TTL',   value: `${browserTTLh}h` },
                ].map(({ label, value }) => (
                  <Box key={label} sx={{ display: 'flex', justifyContent: 'space-between', py: 0.5, borderBottom: `1px solid ${gc}` }}>
                    <Typography variant="caption" color="text.disabled">{label}</Typography>
                    <Typography variant="caption" sx={{ fontWeight: 700 }}>{value}</Typography>
                  </Box>
                ))}
              </Box>
            </CardContent>
          </Card>
        </Grid>

        <Grid size={{ xs: 12, md: 8 }}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 2 }}>Cache Hit % — Daily Trend</Typography>
              <ResponsiveContainer width="100%" height={260}>
                <AreaChart data={cacheRatioTrend}>
                  <defs>
                    <linearGradient id="gradCR" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%"  stopColor="#22c55e" stopOpacity={0.3} />
                      <stop offset="95%" stopColor="#22c55e" stopOpacity={0.02} />
                    </linearGradient>
                  </defs>
                  <CartesianGrid strokeDasharray="3 3" stroke={gc} />
                  <XAxis dataKey="date" stroke={theme.palette.text.secondary} tick={{ fontSize: 12 }} />
                  <YAxis domain={[0, 100]} stroke={theme.palette.text.secondary} tick={{ fontSize: 11 }} tickFormatter={(v) => `${v}%`} />
                  <RTooltip
                    contentStyle={{ backgroundColor: theme.palette.background.paper, border: `1px solid ${theme.palette.divider}`, borderRadius: 8 }}
                    formatter={(val: any) => [`${val}%`, 'Cache Hit Ratio']}
                  />
                  <ReferenceLine y={80} stroke="#22c55e" strokeDasharray="4 2" label={{ value: '80% target', fill: '#22c55e', fontSize: 11, position: 'right' }} />
                  <Area type="monotone" dataKey="ratio" name="Hit Ratio %" stroke="#22c55e" fill="url(#gradCR)" strokeWidth={2.5} dot={{ r: 4 }} />
                </AreaChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {/* ── Cached vs Uncached bar chart ── */}
      <Card sx={{ mb: 3 }}>
        <CardContent>
          <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 2 }}>Cached vs Uncached Requests — Daily</Typography>
          <ResponsiveContainer width="100%" height={220}>
            <BarChart data={cacheRatioTrend} barCategoryGap="25%">
              <CartesianGrid strokeDasharray="3 3" stroke={gc} />
              <XAxis dataKey="date" stroke={theme.palette.text.secondary} tick={{ fontSize: 12 }} />
              <YAxis stroke={theme.palette.text.secondary} tick={{ fontSize: 11 }} tickFormatter={(v) => v >= 1000 ? `${(v/1000).toFixed(0)}k` : v} />
              <RTooltip
                contentStyle={{ backgroundColor: theme.palette.background.paper, border: `1px solid ${theme.palette.divider}`, borderRadius: 8 }}
                formatter={(val: any) => [formatNumber(val)]}
              />
              <Legend wrapperStyle={{ fontSize: 12 }} />
              <Bar dataKey="cached"   name="Cached"   fill={`${theme.palette.success.main}cc`} radius={[3,3,0,0]} stackId="a" />
              <Bar dataKey="uncached" name="Uncached" fill={`${theme.palette.error.main}66`}   radius={[3,3,0,0]} stackId="a" />
            </BarChart>
          </ResponsiveContainer>
        </CardContent>
      </Card>

      {/* ── CF Settings badges ── */}
      <Card>
        <CardContent>
          <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
            <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>Cloudflare Edge Settings</Typography>
            <Chip label={`${goodCount} / ${CF_SETTINGS_META.length} optimal`} size="small" color={scoreColor} />
          </Box>
          <Grid container spacing={1.5}>
            {CF_SETTINGS_META.map(({ key, label, good }) => {
              const val   = s[key];
              const ok    = isGood(key, val, good);
              const display = key === 'browser_cache_ttl'
                ? `${browserTTLh}h`
                : String(val || 'N/A');
              return (
                <Grid size={{ xs: 12, sm: 6, md: 4 }} key={key}>
                  <Box sx={{
                    display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                    p: 1.2, borderRadius: 1.5, border: `1px solid`,
                    borderColor: ok ? 'rgba(34,197,94,0.2)' : 'rgba(239,68,68,0.15)',
                    background: ok ? 'rgba(34,197,94,0.04)' : 'rgba(239,68,68,0.04)',
                  }}>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                      {ok
                        ? <CheckCircle sx={{ fontSize: 14, color: 'success.main' }} />
                        : <Cancel      sx={{ fontSize: 14, color: 'error.main' }} />}
                      <Typography variant="caption" sx={{ fontWeight: 600 }}>{label}</Typography>
                    </Box>
                    <Typography variant="caption" sx={{ fontWeight: 800, textTransform: 'capitalize', color: ok ? 'success.main' : 'text.secondary' }}>
                      {display}
                    </Typography>
                  </Box>
                </Grid>
              );
            })}
          </Grid>
        </CardContent>
      </Card>
    </Box>
  );
}

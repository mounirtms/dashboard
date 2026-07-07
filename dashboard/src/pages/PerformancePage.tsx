import { Box, Typography, Card, CardContent, Grid, LinearProgress, useTheme, Alert } from '@mui/material';
import { useCloudflareData } from '../hooks/useCloudflareData';
import { Skeleton } from '@mui/material';
import StatCard from '../components/common/StatCard';
import { formatNumber, formatBytes } from '../utils/formatters';

export default function PerformancePage() {
  const theme = useTheme();
  const { data, loading, error } = useCloudflareData();

  if (loading) return <Skeleton variant="rectangular" height={400} sx={{ borderRadius: 2, mx: 2, my: 3 }} />;
  if (error) return <Alert severity="error" sx={{ mb: 2 }}>Error loading performance data: {error}</Alert>;
  if (!data) return null;

  const ratio = data.cache_hit_ratio;
  const ratioColor: 'success' | 'warning' | 'error' = ratio > 80 ? 'success' : ratio > 50 ? 'warning' : 'error';
  const totals = data.analytics_totals;
  const uncachedRequests = totals.requests - totals.cachedRequests;
  const s = data.settings;

  const perfSettings = [
    { label: 'Cache Level', value: s.cache_level },
    { label: 'Brotli', value: s.brotli },
    { label: 'HTTP/3', value: s.http3 },
    { label: 'HTTP/2', value: s.http2 },
    { label: 'Browser Cache TTL', value: `${Math.round((Number(s.browser_cache_ttl) || 0) / 3600)}h` },
    { label: 'Rocket Loader', value: s.rocket_loader },
    { label: 'Minify CSS', value: s.minify_css },
    { label: 'Minify JS', value: s.minify_js },
    { label: 'Always Online', value: s.always_online },
    { label: 'Auto HTTPS', value: s.automatic_https_rewrites },
    { label: 'Early Hints', value: s.early_hints },
    { label: 'Polish', value: s.polish },
  ];

  return (
    <Box>
      <Typography variant="h6" sx={{ mb: 0.5, fontWeight: 700 }}>Performance</Typography>
      <Typography variant="body2" color="textSecondary" sx={{ mb: 3 }}>Cache and optimization metrics</Typography>

      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 12, sm: 3 }}>
          <StatCard label="Cache Hit Ratio" value={`${ratio}%`} color={ratioColor} />
        </Grid>
        <Grid size={{ xs: 12, sm: 3 }}>
          <StatCard label="Cached Requests" value={formatNumber(totals.cachedRequests)} color="success" />
        </Grid>
        <Grid size={{ xs: 12, sm: 3 }}>
          <StatCard label="Uncached Requests" value={formatNumber(uncachedRequests)} color="error" />
        </Grid>
        <Grid size={{ xs: 12, sm: 3 }}>
          <StatCard label="Cached Bandwidth" value={formatBytes(totals.cachedBytes)} color="info" />
        </Grid>
      </Grid>

      <Card sx={{ mb: 3 }}>
        <CardContent>
          <Typography variant="subtitle2" sx={{ mb: 1, fontWeight: 600 }}>Cache Hit Ratio</Typography>
          <Typography variant="h4" sx={{ color: ratioColor === 'success' ? 'success.main' : ratioColor === 'warning' ? 'warning.main' : 'error.main', fontWeight: 700, mb: 1 }}>
            {ratio}%
          </Typography>
          <LinearProgress
            variant="determinate"
            value={ratio}
            sx={{ height: 10, borderRadius: 5, backgroundColor: `${theme.palette.divider}99` }}
            color={ratioColor}
          />
          <Typography variant="caption" color="textSecondary" sx={{ display: 'block', mt: 1 }}>
            {formatNumber(totals.cachedRequests)} cached / {formatNumber(totals.requests)} total requests
          </Typography>
        </CardContent>
      </Card>

      <Card>
        <CardContent>
          <Typography variant="subtitle2" sx={{ mb: 2, fontWeight: 600 }}>Performance Settings</Typography>
          <Grid container spacing={1.5}>
            {perfSettings.map((item) => (
              <Grid size={{ xs: 12, sm: 6, md: 4 }} key={item.label}>
                <Box sx={{ display: 'flex', justifyContent: 'space-between', py: 0.5, borderBottom: 1, borderColor: `${theme.palette.divider}66` }}>
                  <Typography variant="body2" color="textSecondary">{item.label}</Typography>
                  <Typography variant="body2" sx={{ fontWeight: 600, textTransform: 'capitalize' }}>{item.value}</Typography>
                </Box>
              </Grid>
            ))}
          </Grid>
        </CardContent>
      </Card>
    </Box>
  );
}

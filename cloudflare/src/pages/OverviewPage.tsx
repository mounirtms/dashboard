import { Grid, Box, Typography, Card, CardContent, Chip } from '@mui/material';
import { CloudOutlined, CheckCircle, Warning, Schedule } from '@mui/icons-material';
import { useCloudflareData } from '../hooks/useCloudflareData';
import StatCard from '../components/common/StatCard';
import LoadingState from '../components/common/LoadingState';
import StatusBadge from '../components/common/StatusBadge';
import { formatNumber, formatBytes } from '../utils/formatters';

export default function OverviewPage() {
  const { data, loading, error } = useCloudflareData();

  if (loading) return <LoadingState message="Loading Cloudflare data..." />;
  if (error) return <LoadingState message={`Error: ${error}`} />;
  if (!data) return null;

  const z = data.zone;
  const sslCert = data.ssl_certificate;
  const totals = data.analytics_totals;

  const statusColor = z.status === 'active' ? 'success' : 'warning';
  const sslColor = ['full', 'strict'].includes(data.settings.ssl) ? 'success' : data.settings.ssl === 'flexible' ? 'warning' : 'error';
  const certColor = sslCert?.status === 'active' ? 'success' : (sslCert?.days_left ?? 999) < 30 ? 'error' : 'warning';

  return (
    <Box>
      <Typography variant="h6" sx={{ mb: 0.5, fontWeight: 700 }}>Overview</Typography>
      <Typography variant="body2" color="textSecondary" sx={{ mb: 3 }}>{z.name}</Typography>

      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatCard label="Status" value={z.status} color={statusColor} />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatCard label="Plan" value={z.plan} color="primary" />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatCard label="SSL Mode" value={data.settings.ssl?.toUpperCase() || 'OFF'} color={sslColor} />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatCard label="Cache Level" value={data.settings.cache_level || '-'} color="info" />
        </Grid>
      </Grid>

      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatCard label="Total Requests (7d)" value={formatNumber(totals.requests)} color="primary" />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatCard label="Bandwidth (7d)" value={data.bandwidth_formatted} color="info" />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatCard label="Cache Hit Ratio" value={`${data.cache_hit_ratio}%`} color={data.cache_hit_ratio > 80 ? 'success' : data.cache_hit_ratio > 50 ? 'warning' : 'error'} />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatCard label="Threats (7d)" value={formatNumber(totals.threats)} color={totals.threats > 0 ? 'error' : 'success'} />
        </Grid>
      </Grid>

      <Grid container spacing={2}>
        {sslCert && (
          <Grid size={{ xs: 12, sm: 6 }}>
            <Card>
              <CardContent>
                <Typography variant="caption" color="textSecondary" sx={{ display: 'block', mb: 1 }}>SSL Certificate</Typography>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 1 }}>
                  <StatusBadge label={sslCert.status} color={certColor} />
                  {sslCert.days_left !== null && (
                    <Typography variant="body2" color={certColor === 'error' ? 'error.main' : 'text.secondary'}>
                      {sslCert.days_left} days remaining
                    </Typography>
                  )}
                </Box>
                {sslCert.hostnames.length > 0 && (
                  <Typography variant="caption" color="textSecondary">
                    {sslCert.hostnames.join(', ')}
                  </Typography>
                )}
              </CardContent>
            </Card>
          </Grid>
        )}
        <Grid size={{ xs: 12, sm: 6 }}>
          <Card>
            <CardContent>
              <Typography variant="caption" color="textSecondary" sx={{ display: 'block', mb: 1 }}>Quick Status</Typography>
              <Box sx={{ display: 'flex', gap: 1, flexWrap: 'wrap' }}>
                <Chip
                  icon={<CloudOutlined />}
                  label={`Dev Mode: ${z.development_mode}`}
                  size="small"
                  color={z.development_mode === 'on' ? 'warning' : 'default'}
                  variant="outlined"
                />
                <Chip
                  icon={data.settings.brotli === 'on' ? <CheckCircle /> : <Warning />}
                  label={`Brotli: ${data.settings.brotli}`}
                  size="small"
                  color={data.settings.brotli === 'on' ? 'success' : 'default'}
                  variant="outlined"
                />
                <Chip
                  icon={<Schedule />}
                  label={`Browser TTL: ${Math.round((Number(data.settings.browser_cache_ttl) || 0) / 3600)}h`}
                  size="small"
                  variant="outlined"
                />
              </Box>
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </Box>
  );
}

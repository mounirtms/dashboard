import { Grid, Box, Typography, Card, CardContent, Chip, Divider, useTheme } from '@mui/material';
import { CloudOutlined, CheckCircle, Warning, Schedule, Shield, Cached, Http } from '@mui/icons-material';
import { useCloudflareData } from '../hooks/useCloudflareData';
import StatCard from '../components/common/StatCard';
import LoadingState from '../components/common/LoadingState';
import StatusBadge from '../components/common/StatusBadge';
import { formatNumber, formatBytes } from '../utils/formatters';

export default function OverviewPage() {
  const { data, loading, error } = useCloudflareData();
  const theme = useTheme();

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
      <Box sx={{ mb: 3 }}>
        <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
          Overview
        </Typography>
        <Typography variant="body2" sx={{ color: 'text.secondary', fontSize: '0.88rem' }}>
          {z.name} &middot; {data.account}
        </Typography>
      </Box>

      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatCard label="Zone Status" value={z.status.charAt(0).toUpperCase() + z.status.slice(1)} color={statusColor} icon={<CheckCircle />} />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatCard label="Plan" value={z.plan.charAt(0).toUpperCase() + z.plan.slice(1)} color="primary" icon={<CloudOutlined />} />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatCard label="SSL Mode" value={data.settings.ssl?.toUpperCase() || 'OFF'} color={sslColor} icon={<Shield />} />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatCard label="Cache Level" value={(data.settings.cache_level || '-').charAt(0).toUpperCase() + (data.settings.cache_level || '-').slice(1)} color="info" icon={<Cached />} />
        </Grid>
      </Grid>

      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatCard label="Total Requests (7d)" value={formatNumber(totals.requests)} color="primary" icon={<Http />} />
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
              <CardContent sx={{ p: 3 }}>
                <Typography variant="body2" sx={{ color: 'text.secondary', fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.05em', fontSize: '0.72rem', mb: 1.5 }}>
                  SSL Certificate
                </Typography>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, mb: 1.5 }}>
                  <StatusBadge label={sslCert.status} color={certColor} />
                  {sslCert.days_left !== null && (
                    <Typography variant="body2" sx={{ color: certColor === 'error' ? 'error.main' : 'text.secondary', fontWeight: 500 }}>
                      {sslCert.days_left} days remaining
                    </Typography>
                  )}
                </Box>
                {sslCert.hostnames.length > 0 && (
                  <Typography variant="body2" sx={{ color: 'text.disabled', fontSize: '0.82rem', fontFamily: 'monospace' }}>
                    {sslCert.hostnames.join(', ')}
                  </Typography>
                )}
              </CardContent>
            </Card>
          </Grid>
        )}
        <Grid size={{ xs: 12, sm: sslCert ? 6 : 12 }}>
          <Card>
            <CardContent sx={{ p: 3 }}>
              <Typography variant="body2" sx={{ color: 'text.secondary', fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.05em', fontSize: '0.72rem', mb: 1.5 }}>
                Quick Status
              </Typography>
              <Box sx={{ display: 'flex', gap: 1.5, flexWrap: 'wrap' }}>
                <Chip
                  icon={<CloudOutlined sx={{ fontSize: 18 }} />}
                  label={`Dev Mode: ${z.development_mode}`}
                  size="small"
                  sx={{
                    backgroundColor: z.development_mode === 'on' ? `${theme.palette.warning.main}1f` : `${theme.palette.text.secondary}14`,
                    borderColor: z.development_mode === 'on' ? `${theme.palette.warning.main}4d` : `${theme.palette.text.secondary}33`,
                    color: z.development_mode === 'on' ? theme.palette.warning.main : theme.palette.text.secondary,
                    fontWeight: 600,
                    borderWidth: 1,
                    borderStyle: 'solid',
                  }}
                />
                <Chip
                  icon={data.settings.brotli === 'on' ? <CheckCircle sx={{ fontSize: 18 }} /> : <Warning sx={{ fontSize: 18 }} />}
                  label={`Brotli: ${data.settings.brotli}`}
                  size="small"
                  sx={{
                    backgroundColor: data.settings.brotli === 'on' ? `${theme.palette.success.main}1f` : `${theme.palette.text.secondary}14`,
                    borderColor: data.settings.brotli === 'on' ? `${theme.palette.success.main}4d` : `${theme.palette.text.secondary}33`,
                    color: data.settings.brotli === 'on' ? theme.palette.success.main : theme.palette.text.secondary,
                    fontWeight: 600,
                    borderWidth: 1,
                    borderStyle: 'solid',
                  }}
                />
                <Chip
                  icon={<Schedule sx={{ fontSize: 18 }} />}
                  label={`Browser TTL: ${Math.round((Number(data.settings.browser_cache_ttl) || 0) / 3600)}h`}
                  size="small"
                  sx={{
                    backgroundColor: `${theme.palette.text.secondary}14`,
                    borderColor: `${theme.palette.text.secondary}33`,
                    color: theme.palette.text.secondary,
                    fontWeight: 600,
                    borderWidth: 1,
                    borderStyle: 'solid',
                  }}
                />
              </Box>
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </Box>
  );
}

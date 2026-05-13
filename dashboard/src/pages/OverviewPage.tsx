import { Grid, Box, Typography, Card, CardContent, Chip, Divider, useTheme, Alert } from '@mui/material';
import { CloudOutlined, Shield, Cached, Http, Public, GppMaybe } from '@mui/icons-material';
import { useCloudflareData } from '../hooks/useCloudflareData';
import LoadingState from '../components/common/LoadingState';
import StatCard from '../components/common/StatCard';
import StatusBadge from '../components/common/StatusBadge';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';

export default function OverviewPage() {
  const { data, loading, error } = useCloudflareData(30000); // 30s refresh
  const theme = useTheme();

  if (loading && !data) return <LoadingState message="Connecting to Cloudflare Edge..." />;
  if (error) return <Alert severity="error" sx={{ mb: 2 }}>Cloudflare Error: {error}</Alert>;
  if (!data) return null;

  return (
    <Box>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Network Overview
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary', display: 'flex', alignItems: 'center', gap: 1 }}>
            <Public sx={{ fontSize: 14 }} /> {data.zone?.name} &middot; {data.zone?.plan} Plan &middot; Last updated: {new Date(data.timestamp * 1000).toLocaleTimeString()}
          </Typography>
        </Box>
        <StatusBadge label={data.zone?.status.toUpperCase()} color={data.zone?.status === 'active' ? 'success' : 'warning'} />
      </Box>

      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatCard 
            label="Requests (24h)" 
            value={data.analytics_totals?.requests.toLocaleString() || '0'} 
            color="primary" 
            icon={<Http />} 
          />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatCard 
            label="Cache Hit Ratio" 
            value={`${data.cache_hit_ratio}%`} 
            color="success" 
            icon={<Cached />} 
          />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatCard 
            label="Bandwidth" 
            value={data.bandwidth_formatted || '0 B'} 
            color="info" 
            icon={<CloudOutlined />} 
          />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatCard 
            label="Threats Blocked" 
            value={data.analytics_totals?.threats.toLocaleString() || '0'} 
            color="error" 
            icon={<Shield />} 
          />
        </Grid>
      </Grid>

      <Grid container spacing={2}>
        <Grid size={{ xs: 12, md: 8 }}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Typography variant="h6" sx={{ mb: 3, fontWeight: 700 }}>Traffic Trend (Hourly)</Typography>
              <Box sx={{ height: 300, width: '100%' }}>
                <ResponsiveContainer width="100%" height="100%">
                  <AreaChart data={data.hourly_analytics}>
                    <defs>
                      <linearGradient id="colorRequests" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="5%" stopColor={theme.palette.primary.main} stopOpacity={0.3}/>
                        <stop offset="95%" stopColor={theme.palette.primary.main} stopOpacity={0}/>
                      </linearGradient>
                    </defs>
                    <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.05)" vertical={false} />
                    <XAxis dataKey="time" stroke={theme.palette.text.disabled} fontSize={12} tickLine={false} axisLine={false} />
                    <YAxis stroke={theme.palette.text.disabled} fontSize={12} tickLine={false} axisLine={false} />
                    <Tooltip 
                      contentStyle={{ backgroundColor: '#1e293b', border: '1px solid #334155', borderRadius: 8 }}
                      itemStyle={{ color: '#fff', fontSize: 12 }}
                    />
                    <Area type="monotone" dataKey="requests" stroke={theme.palette.primary.main} fillOpacity={1} fill="url(#colorRequests)" strokeWidth={2} />
                  </AreaChart>
                </ResponsiveContainer>
              </Box>
            </CardContent>
          </Card>
        </Grid>

        <Grid size={{ xs: 12, md: 4 }}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Typography variant="h6" sx={{ mb: 2, fontWeight: 700 }}>Edge Settings</Typography>
              <Box sx={{ display: 'grid', gap: 2 }}>
                <Box>
                  <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 0.5 }}>
                    <Typography variant="body2" sx={{ fontWeight: 600 }}>SSL/TLS Mode</Typography>
                    <Chip label={data.settings?.ssl.toUpperCase()} size="small" color="success" sx={{ fontSize: '0.65rem', height: 18, fontWeight: 700 }} />
                  </Box>
                  <Typography variant="caption" color="text.disabled">Encrypts traffic between edge and origin</Typography>
                </Box>
                <Divider />
                <Box>
                  <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 0.5 }}>
                    <Typography variant="body2" sx={{ fontWeight: 600 }}>Development Mode</Typography>
                    <Chip label={data.zone?.development_mode.toUpperCase()} size="small" color={data.zone?.development_mode === 'on' ? 'warning' : 'default'} sx={{ fontSize: '0.65rem', height: 18, fontWeight: 700 }} />
                  </Box>
                  <Typography variant="caption" color="text.disabled">Bypass edge cache for real-time debugging</Typography>
                </Box>
                <Divider />
                <Box>
                  <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 0.5 }}>
                    <Typography variant="body2" sx={{ fontWeight: 600 }}>Caching Level</Typography>
                    <Typography variant="body2" sx={{ fontWeight: 700, color: 'primary.light' }}>{data.settings?.cache_level.toUpperCase()}</Typography>
                  </Box>
                  <Typography variant="caption" color="text.disabled">Standard caching policy applied at the edge</Typography>
                </Box>
              </Box>

              <Box sx={{ mt: 4, p: 2, borderRadius: 2, backgroundColor: 'rgba(239, 68, 68, 0.05)', border: '1px solid rgba(239, 68, 68, 0.1)' }}>
                <Typography sx={{ color: 'error.light', fontSize: '0.75rem', fontWeight: 800, mb: 1, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <GppMaybe sx={{ fontSize: 16 }} /> SECURITY ADVISORY
                </Typography>
                <Typography sx={{ color: 'text.secondary', fontSize: '0.7rem', lineHeight: 1.4 }}>
                  There were {data.analytics_totals?.threats} threats mitigated in the last 24 hours. Review WAF logs for details.
                </Typography>
              </Box>
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </Box>
  );
}

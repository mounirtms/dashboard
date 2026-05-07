import { Box, Typography, Grid, Card, CardContent, Chip, LinearProgress } from '@mui/material';
import { Storage, Hub, Devices, PhoneIphone, Tablet, DesktopWindows } from '@mui/icons-material';
import React, { useState, useEffect } from 'react';
import { fetchVarnishStats, fetchApacheStats, ApacheStats } from '../api/system';
import LoadingState from '../components/common/LoadingState';
import StatCard from '../components/common/StatCard';
import StatusBadge from '../components/common/StatusBadge';

export default function InfrastructurePage() {
  const [varnish, setVarnish] = useState<any>(null);
  const [apache, setApache] = useState<ApacheStats | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    Promise.all([fetchVarnishStats(), fetchApacheStats()])
      .then(([v, a]) => {
        setVarnish(v);
        setApache(a);
      })
      .finally(() => setLoading(false));
  }, []);

  if (loading && !varnish) return <LoadingState message="Loading infrastructure stats..." />;

  return (
    <Box>
      <Box sx={{ mb: 3 }}>
        <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
          Infrastructure
        </Typography>
        <Typography variant="body2" sx={{ color: 'text.secondary', fontSize: '0.88rem' }}>
          Performance metrics for Varnish Cache and Apache Web Server.
        </Typography>
      </Box>

      <Grid container spacing={2}>
        {/* Varnish Section */}
        <Grid size={{ xs: 12, md: 8 }}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 3 }}>
                <Typography variant="h6" sx={{ fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <Hub sx={{ color: 'primary.main' }} /> Varnish Cache Accelerator
                </Typography>
                <StatusBadge label={varnish?.backend_healthy ? 'Healthy' : 'Backend Fail'} color={varnish?.backend_healthy ? 'success' : 'error'} />
              </Box>

              <Grid container spacing={2} sx={{ mb: 3 }}>
                <Grid size={{ xs: 12, sm: 4 }}>
                  <StatCard label="Hit Ratio" value={`${varnish?.hit_ratio}%`} color="success" subvalue={`${varnish?.hits?.toLocaleString()} hits`} />
                </Grid>
                <Grid size={{ xs: 12, sm: 4 }}>
                  <StatCard label="Total Requests" value={varnish?.total_requests?.toLocaleString() || '0'} color="info" subvalue={`${varnish?.misses?.toLocaleString()} misses`} />
                </Grid>
                <Grid size={{ xs: 12, sm: 4 }}>
                  <StatCard label="Uptime" value={Math.floor((varnish?.uptime || 0) / 3600) + 'h'} color="primary" subvalue="Service active" />
                </Grid>
              </Grid>

              <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1, color: 'text.secondary' }}>Cache Storage</Typography>
              <Box sx={{ p: 2, borderRadius: 1.5, backgroundColor: 'background.default', border: '1px solid', borderColor: 'divider', mb: 3 }}>
                <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 1 }}>
                  <Typography variant="body2" sx={{ fontWeight: 700 }}>{varnish?.storage?.used} / {varnish?.storage?.total}</Typography>
                  <Typography variant="body2" sx={{ fontWeight: 700, color: 'primary.main' }}>{varnish?.storage?.usage_pct}%</Typography>
                </Box>
                <LinearProgress variant="determinate" value={varnish?.storage?.usage_pct || 0} sx={{ height: 6, borderRadius: 3 }} />
              </Box>

              <Typography variant="h6" sx={{ fontWeight: 700, mb: 2, display: 'flex', alignItems: 'center', gap: 1 }}>
                <Devices sx={{ fontSize: 20 }} /> Traffic by Device Type
              </Typography>
              <Grid container spacing={2}>
                <DeviceStat icon={<DesktopWindows />} label="Desktop" pct={varnish?.devices?.desktop_pct} color="#3b82f6" />
                <DeviceStat icon={<PhoneIphone />} label="Mobile" pct={varnish?.devices?.mobile_pct} color="#10b981" />
                <DeviceStat icon={<Tablet />} label="Tablet" pct={varnish?.devices?.tablet_pct} color="#f59e0b" />
              </Grid>
            </CardContent>
          </Card>
        </Grid>

        {/* Apache & OS Details */}
        <Grid size={{ xs: 12, md: 4 }}>
          <Box sx={{ display: 'grid', gap: 2, height: '100%' }}>
            <Card>
              <CardContent>
                <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
                  <Typography variant="subtitle1" sx={{ fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                    <Storage sx={{ color: 'warning.main' }} /> Apache HTTPD
                  </Typography>
                  <StatusBadge label={apache?.running ? 'Running' : 'Stopped'} color={apache?.running ? 'success' : 'error'} />
                </Box>
                <Box sx={{ display: 'grid', gap: 1.5 }}>
                  <InfoRow label="Version" value={apache?.version || 'N/A'} />
                  <InfoRow label="Active Workers" value={apache?.processes || 0} />
                  <Box sx={{ display: 'flex', gap: 1, mt: 1 }}>
                    <Chip label="HTTP: 80" size="small" color={apache?.ports?.http ? 'success' : 'default'} variant="outlined" sx={{ fontSize: '0.65rem' }} />
                    <Chip label="HTTPS: 443" size="small" color={apache?.ports?.https ? 'success' : 'default'} variant="outlined" sx={{ fontSize: '0.65rem' }} />
                  </Box>
                </Box>
              </CardContent>
            </Card>

            <Card sx={{ backgroundColor: 'primary.main', backgroundImage: 'linear-gradient(rgba(255,255,255,0.05), rgba(255,255,255,0))' }}>
              <CardContent>
                <Typography sx={{ color: 'white', fontWeight: 800, mb: 1 }}>Infrastructure Note</Typography>
                <Typography sx={{ color: 'rgba(255,255,255,0.8)', fontSize: '0.75rem', lineHeight: 1.5 }}>
                  Varnish is configured as a reverse proxy in front of Apache. Device detection is performed via VCL header analysis (X-Device).
                </Typography>
              </CardContent>
            </Card>
          </Box>
        </Grid>
      </Grid>
    </Box>
  );
}

function DeviceStat({ icon, label, pct, color }: { icon: any, label: string, pct: number, color: string }) {
  return (
    <Grid size={{ xs: 4 }}>
      <Box sx={{ textAlign: 'center', p: 1.5, borderRadius: 1.5, backgroundColor: 'background.default', border: '1px solid', borderColor: 'divider' }}>
        <Box sx={{ color: color, mb: 0.5, display: 'flex', justifyContent: 'center' }}>{React.cloneElement(icon as React.ReactElement, { sx: { fontSize: 24 } } as any)}</Box>
        <Typography variant="caption" sx={{ display: 'block', fontWeight: 700, color: 'text.secondary', textTransform: 'uppercase' }}>{label}</Typography>
        <Typography variant="h6" sx={{ fontWeight: 800 }}>{pct}%</Typography>
      </Box>
    </Grid>
  );
}

function InfoRow({ label, value }: { label: string, value: any }) {
  return (
    <Box sx={{ display: 'flex', justifyContent: 'space-between', borderBottom: '1px solid rgba(255,255,255,0.05)', pb: 0.5 }}>
      <Typography variant="caption" sx={{ color: 'text.disabled' }}>{label}</Typography>
      <Typography variant="body2" sx={{ fontWeight: 700 }}>{value}</Typography>
    </Box>
  );
}

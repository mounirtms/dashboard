import { Box, Typography, Card, CardContent, Grid, Chip, Divider, Button } from '@mui/material';
import { CheckCircle, Warning, RestartAlt, Cached, Settings, Delete } from '@mui/icons-material';
import { useState } from 'react';
import { useCloudflareData } from '../hooks/useCloudflareData';
import { performCloudflareAction } from '../api/cloudflare';
import LoadingState from '../components/common/LoadingState';
import StatusBadge from '../components/common/StatusBadge';
import { formatNumber } from '../utils/formatters';

export default function SettingsPage() {
  const { data, loading, error, refetch } = useCloudflareData();
  const [actionLoading, setActionLoading] = useState<string | null>(null);
  const [actionMessage, setActionMessage] = useState<string | null>(null);

  if (loading) return <LoadingState />;
  if (error) return <LoadingState message={`Error: ${error}`} />;
  if (!data) return null;

  const s = data.settings;
  const sslCert = data.ssl_certificate;
  const z = data.zone;

  const handleAction = async (action: string, params: Record<string, string> = {}) => {
    setActionLoading(action);
    setActionMessage(null);
    try {
      const result = await performCloudflareAction(action, params);
      setActionMessage(result.message || `${action} completed`);
      setTimeout(() => refetch(), 2000);
    } catch (e: any) {
      setActionMessage(`Error: ${e.message}`);
    } finally {
      setActionLoading(null);
    }
  };

  const toggleValue = (key: string, value: string) => {
    const isOn = value === 'on';
    return {
      color: isOn ? 'success' : 'default',
      icon: isOn ? <CheckCircle fontSize="small" /> : <Warning fontSize="small" />,
    };
  };

  const settingGroups = [
    {
      title: 'Security',
      items: [
        { label: 'WAF', value: s.waf },
        { label: 'Security Level', value: s.security_level },
        { label: 'Auto HTTPS Rewrites', value: s.automatic_https_rewrites },
        { label: 'Always Online', value: s.always_online },
      ],
    },
    {
      title: 'Performance',
      items: [
        { label: 'Cache Level', value: s.cache_level },
        { label: 'Brotli', value: s.brotli },
        { label: 'HTTP/2', value: s.http2 },
        { label: 'HTTP/3', value: s.http3 },
        { label: 'Rocket Loader', value: s.rocket_loader },
        { label: 'Minify CSS', value: s.minify_css },
        { label: 'Minify JS', value: s.minify_js },
        { label: 'Early Hints', value: s.early_hints },
        { label: 'Polish', value: s.polish },
      ],
    },
    {
      title: 'Network',
      items: [
        { label: 'IPv6', value: s.ipv6 },
        { label: 'Browser Cache TTL', value: `${Math.round((Number(s.browser_cache_ttl) || 0) / 3600)}h` },
      ],
    },
  ];

  return (
    <Box>
      <Typography variant="h6" sx={{ mb: 0.5, fontWeight: 700 }}>Settings & Actions</Typography>
      <Typography variant="body2" color="textSecondary" sx={{ mb: 3 }}>Zone configuration and quick actions</Typography>

      {sslCert && (
        <Card sx={{ mb: 3 }}>
          <CardContent>
            <Typography variant="subtitle2" sx={{ mb: 2, fontWeight: 600 }}>SSL Certificate</Typography>
            <Grid container spacing={2}>
              <Grid size={{ xs: 12, sm: 3 }}>
                <StatusBadge label={sslCert.status} color={sslCert.status === 'active' ? 'success' : 'warning'} />
              </Grid>
              <Grid size={{ xs: 12, sm: 3 }}>
                <Typography variant="body2">
                  Expires: {sslCert.expires_on || 'N/A'}
                </Typography>
              </Grid>
              <Grid size={{ xs: 12, sm: 3 }}>
                <Typography variant="body2" color={sslCert.days_left !== null && sslCert.days_left < 30 ? 'error.main' : 'text.secondary'}>
                  {sslCert.days_left} days remaining
                </Typography>
              </Grid>
              <Grid size={{ xs: 12, sm: 3 }}>
                <Typography variant="body2" sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>
                  {sslCert.hostnames.slice(0, 2).join(', ')}
                </Typography>
              </Grid>
            </Grid>
          </CardContent>
        </Card>
      )}

      <Grid container spacing={3} sx={{ mb: 3 }}>
        {settingGroups.map((group) => (
          <Grid size={{ xs: 12, md: 4 }} key={group.title}>
            <Card>
              <CardContent>
                <Typography variant="subtitle2" sx={{ mb: 2, fontWeight: 600 }}>{group.title}</Typography>
                <Box>
                  {group.items.map((item) => {
                    const tc = toggleValue(item.label, item.value);
                    return (
                      <Box key={item.label} sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', py: 0.75, borderBottom: '1px solid rgba(30, 41, 59, 0.3)' }}>
                        <Typography variant="body2" color="textSecondary">{item.label}</Typography>
                        <Chip
                          label={item.value}
                          size="small"
                          icon={tc.icon}
                          color={tc.color as any}
                          variant="outlined"
                          sx={{ textTransform: 'capitalize', minWidth: 60 }}
                        />
                      </Box>
                    );
                  })}
                </Box>
              </CardContent>
            </Card>
          </Grid>
        ))}
      </Grid>

      <Card>
        <CardContent>
          <Typography variant="subtitle2" sx={{ mb: 2, fontWeight: 600 }}>Quick Actions</Typography>
          <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1.5 }}>
            <Button
              variant="contained"
              color="warning"
              startIcon={<Delete />}
              onClick={() => handleAction('purge_all')}
              disabled={actionLoading !== null}
              size="small"
            >
              Purge All Cache
            </Button>
            <Button
              variant="contained"
              color={z.development_mode === 'on' ? 'secondary' : 'primary'}
              startIcon={<RestartAlt />}
              onClick={() => handleAction('toggle_dev_mode', { value: z.development_mode === 'on' ? 'off' : 'on' })}
              disabled={actionLoading !== null}
              size="small"
            >
              {z.development_mode === 'on' ? 'Dev Mode OFF' : 'Dev Mode ON'}
            </Button>
            <Button
              variant="contained"
              color="success"
              startIcon={<Cached />}
              onClick={() => handleAction('cache_level', { level: 'aggressive' })}
              disabled={actionLoading !== null}
              size="small"
            >
              Cache: Aggressive
            </Button>
            <Button
              variant="contained"
              color="error"
              startIcon={<Settings />}
              onClick={() => handleAction('cache_level', { level: 'basic' })}
              disabled={actionLoading !== null}
              size="small"
            >
              Cache: Basic
            </Button>
          </Box>
          {actionLoading && <Typography variant="caption" color="textSecondary" sx={{ display: 'block', mt: 1 }}>Executing {actionLoading}...</Typography>}
          {actionMessage && (
            <Typography
              variant="caption"
              sx={{ display: 'block', mt: 1, color: actionMessage.startsWith('Error') ? 'error.main' : 'success.main', fontWeight: 600 }}
            >
              {actionMessage}
            </Typography>
          )}
        </CardContent>
      </Card>
    </Box>
  );
}

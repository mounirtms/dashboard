import React, { useState, useCallback } from 'react';
import {
  Box,
  Card,
  CardContent,
  Grid,
  Typography,
  CircularProgress,
  LinearProgress,
  Button,
  Alert,
  Chip,
  Paper,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Skeleton,
} from '@mui/material';
import {
  Refresh as RefreshIcon,
  CheckCircle as CheckCircleIcon,
  Error as ErrorIcon,
  Speed as SpeedIcon,
  Storage as StorageIcon,
  Public as PublicIcon,
  CloudQueue as CloudQueueIcon,
  DeleteSweep as DeleteSweepIcon,
  Devices as DevicesIcon,
  Web as WebIcon,
  Phone as PhoneIcon,
  Tablet as TabletIcon,
} from '@mui/icons-material';
import apiClient from '../api/client';
import { usePolling } from '../hooks/usePolling';

// ---- types ----
interface DeviceEntry {
  hits?: number;
  misses?: number;
  hit_rate?: number | string;
  percentage?: number | string;
}

interface VarnishBundle {
  varnish: {
    hit_ratio?: number | string;
    total_requests?: number;
    hits?: number;
    misses?: number;
    storage?: { usage_pct?: number; used?: string };
    uptime_seconds?: number;
    uptime?: number;
    devices?: {
      desktop?: DeviceEntry;
      mobile?: DeviceEntry;
      tablet?: DeviceEntry;
    };
  } | null;
  logs: string[];
}

interface StatCardProps {
  title: string;
  value: string | number | null | undefined;
  subtitle?: string;
  icon?: React.ComponentType<{ sx?: object }>;
  color?: string;
  unit?: string;
}

// ---- sub-component ----
const StatCard = ({ title, value, subtitle, icon: Icon, color = 'primary', unit = '' }: StatCardProps) => (
  <Card elevation={2}>
    <CardContent>
      <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
        <Box>
          <Typography color="textSecondary" gutterBottom variant="body2">
            {title}
          </Typography>
          <Typography variant="h4" component="div" sx={{ color: `${color}.main` }}>
            {value !== null && value !== undefined ? `${value}${unit}` : '—'}
          </Typography>
          {subtitle && (
            <Typography variant="body2" color="textSecondary" sx={{ mt: 1 }}>
              {subtitle}
            </Typography>
          )}
        </Box>
        {Icon && (
          <Icon sx={{ fontSize: 48, color: `${color}.main`, opacity: 0.3 }} />
        )}
      </Box>
    </CardContent>
  </Card>
);

const getHitRateColor = (rate: number): 'success' | 'warning' | 'error' => {
  if (rate >= 80) return 'success';
  if (rate >= 50) return 'warning';
  return 'error';
};

// ---- component ----
const InfraMonitoring = () => {
  const [showLogs, setShowLogs] = useState(false);

  const fetcher = useCallback(async (): Promise<VarnishBundle> => {
    const [varnishRes, logsRes] = await Promise.allSettled([
      apiClient.get('/api/monitor.php?action=varnish'),
      apiClient.get('/api/monitor.php?action=logs&type=varnish&lines=50'),
    ]);

    const varnish = varnishRes.status === 'fulfilled' ? varnishRes.value.data : null;
    const logs    = logsRes.status === 'fulfilled' && logsRes.value.data?.lines
      ? (logsRes.value.data.lines as string[])
      : [];

    return { varnish, logs };
  }, []);

  const { data, loading, refreshing, error, refetch, lastFetched } = usePolling<VarnishBundle>(fetcher, 30_000);

  const varnishStats = data?.varnish ?? null;
  const logs         = data?.logs    ?? [];

  const handlePurgeCache = async () => {
    if (!window.confirm('Are you sure you want to purge ALL Varnish cache?')) return;
    try {
      const { data: res } = await apiClient.get('/api/monitor.php?action=cache_manage&site=prod&op=varnish_purge');
      if (res.success) {
        alert('Cache purged successfully!');
        refetch();
      } else {
        alert('Failed to purge cache: ' + (res.error ?? 'Unknown error'));
      }
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Unknown error';
      alert('Error: ' + msg);
    }
  };

  if (loading && !varnishStats) {
    return (
      <Box sx={{ p: 3 }}>
        <Skeleton variant="text" width={280} height={40} sx={{ mb: 2 }} />
        <Box sx={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: 3, mb: 3 }}>
          {[...Array(4)].map((_, i) => <Skeleton key={i} variant="rounded" height={120} />)}
        </Box>
        <Skeleton variant="rounded" height={12} sx={{ mb: 3 }} />
        <Box sx={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 3 }}>
          {[...Array(3)].map((_, i) => <Skeleton key={i} variant="rounded" height={120} />)}
        </Box>
      </Box>
    );
  }

  const stats   = varnishStats ?? {};
  const hitRate = parseFloat(String(stats.hit_ratio ?? 0)) || 0;

  return (
    <Box sx={{ p: 3 }}>
      <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 3 }}>
        <Typography variant="h4" component="h1">
          Infrastructure Monitoring
        </Typography>
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
          {refreshing && <CircularProgress size={16} />}
          <Button
            variant="outlined"
            startIcon={<RefreshIcon />}
            onClick={refetch}
            disabled={refreshing}
          >
            Refresh
          </Button>
        </Box>
      </Box>

      {lastFetched && (
        <Typography variant="caption" color="textSecondary" sx={{ mb: 2, display: 'block' }}>
          Last updated: {new Date(lastFetched).toLocaleTimeString()}
        </Typography>
      )}

      {error && (
        <Alert severity="warning" sx={{ mb: 2 }}>
          {error}
        </Alert>
      )}

      <Typography variant="h5" gutterBottom sx={{ mt: 3, mb: 2 }}>
        Varnish Cache Performance (Port 80)
      </Typography>

      <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', md: '1fr 1fr', lg: 'repeat(4, 1fr)' }, gap: 3 }}>
        <StatCard title="Cache Hit Rate" value={hitRate.toFixed(1)} unit="%" subtitle="Target: >80%" icon={SpeedIcon} color={getHitRateColor(hitRate)} />
        <StatCard title="Total Requests" value={stats.total_requests ?? 0} icon={PublicIcon} color="info" />
        <StatCard title="Cache Hits" value={stats.hits ?? 0} icon={CheckCircleIcon} color="success" />
        <StatCard title="Cache Misses" value={stats.misses ?? 0} icon={ErrorIcon} color="error" />
      </Box>

      <Box sx={{ mt: 3 }}>
        <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 1 }}>
          <Typography variant="body2" color="textSecondary">Cache Efficiency</Typography>
          <Typography variant="body2" sx={{ fontWeight: 'bold', color: `${getHitRateColor(hitRate)}.main` }}>
            {hitRate.toFixed(1)}%
          </Typography>
        </Box>
        <LinearProgress
          variant="determinate"
          value={Math.min(hitRate, 100)}
          color={getHitRateColor(hitRate)}
          sx={{ height: 10, borderRadius: 5 }}
        />
      </Box>

      <Box sx={{ mt: 3, display: 'grid', gridTemplateColumns: { xs: '1fr', md: 'repeat(3, 1fr)' }, gap: 3 }}>
        <StatCard
          title="Memory Usage"
          value={stats.storage?.usage_pct ?? 0}
          unit="%"
          subtitle={`Used: ${stats.storage?.used ?? '0 B'}`}
          icon={StorageIcon}
          color="primary"
        />
        <StatCard
          title="Mobile Traffic"
          value={stats.devices?.mobile?.percentage ?? 0}
          unit="%"
          icon={DevicesIcon}
          color="warning"
          subtitle={`Desktop: ${stats.devices?.desktop?.percentage ?? 0}% | Tablet: ${stats.devices?.tablet?.percentage ?? 0}%`}
        />
        <StatCard
          title="Uptime"
          value={Math.floor((stats.uptime_seconds ?? stats.uptime ?? 0) / 3600)}
          unit="h"
          icon={CloudQueueIcon}
          color="info"
        />
      </Box>

      {/* Per-Device Cache Performance */}
      {stats.devices && (
        <Box sx={{ mt: 4 }}>
          <Typography variant="h6" gutterBottom sx={{ fontWeight: 'bold', display: 'flex', alignItems: 'center', gap: 1 }}>
            <DevicesIcon /> Per-Device Cache Performance
          </Typography>
          <Grid container spacing={2}>
            {[
              { key: 'desktop' as const, label: 'Desktop', icon: <WebIcon />, color: '#3b82f6' },
              { key: 'mobile'  as const, label: 'Mobile',  icon: <PhoneIcon />, color: '#10b981' },
              { key: 'tablet'  as const, label: 'Tablet',  icon: <TabletIcon />, color: '#f59e0b' },
            ].map((device) => {
              const d = stats.devices?.[device.key];
              if (!d) return null;
              const deviceHitRate = parseFloat(String(d.hit_rate ?? 0)) || 0;
              return (
                <Grid size={{ xs: 12, md: 4 }} key={device.key}>
                  <Card elevation={2} sx={{ borderColor: `${device.color}33` }}>
                    <CardContent>
                      <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 2 }}>
                        {device.icon}
                        <Typography variant="subtitle1" sx={{ fontWeight: 'bold', color: device.color }}>
                          {device.label}
                        </Typography>
                      </Box>
                      <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 1 }}>
                        <Typography variant="body2" color="textSecondary">Hit Rate</Typography>
                        <Typography variant="body2" sx={{ fontWeight: 'bold', color: deviceHitRate >= 80 ? 'success.main' : deviceHitRate >= 50 ? 'warning.main' : 'error.main' }}>
                          {deviceHitRate.toFixed(1)}%
                        </Typography>
                      </Box>
                      <LinearProgress
                        variant="determinate"
                        value={Math.min(deviceHitRate, 100)}
                        sx={{ height: 6, borderRadius: 3, mb: 1, backgroundColor: 'action.hover', '& .MuiLinearProgress-bar': { backgroundColor: device.color } }}
                      />
                      <Box sx={{ display: 'flex', justifyContent: 'space-between', mt: 1 }}>
                        <Typography variant="caption">Hits: {d.hits ?? 0}</Typography>
                        <Typography variant="caption">Misses: {d.misses ?? 0}</Typography>
                      </Box>
                      <Typography variant="caption" color="textSecondary" sx={{ display: 'block', mt: 0.5 }}>
                        Traffic: {d.percentage ?? 0}%
                      </Typography>
                    </CardContent>
                  </Card>
                </Grid>
              );
            })}
          </Grid>
        </Box>
      )}

      <Box sx={{ mt: 4, display: 'flex', gap: 2, flexWrap: 'wrap' }}>
        <Button variant="outlined" color="error" startIcon={<DeleteSweepIcon />} onClick={handlePurgeCache}>
          Purge Cache
        </Button>
        <Button variant="outlined" onClick={() => setShowLogs(true)}>
          View Messages
        </Button>
      </Box>

      <Dialog open={showLogs} onClose={() => setShowLogs(false)} maxWidth="lg" fullWidth>
        <DialogTitle>Varnish System Messages</DialogTitle>
        <DialogContent>
          <Paper elevation={0} sx={{ p: 2, bgcolor: '#1e1e1e', color: '#d4d4d4', fontFamily: 'monospace', fontSize: '12px', maxHeight: '500px', overflow: 'auto' }}>
            {logs.length > 0
              ? logs.map((log, idx) => <div key={idx}>{log}</div>)
              : <Typography color="textSecondary">No logs available</Typography>
            }
          </Paper>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setShowLogs(false)}>Close</Button>
          <Button onClick={refetch} variant="outlined">Refresh</Button>
        </DialogActions>
      </Dialog>

      <Alert severity="info" sx={{ mt: 4 }}>
        <Typography variant="body2" sx={{ fontWeight: 'bold' }}>System Architecture:</Typography>
        <Typography variant="body2">
          Port 80 → Varnish Cache (Frontend) <br />
          Port 81 → Apache HTTP (Backend) <br />
          Port 443 → Apache SSL/TLS <br />
          Port 6082 → Varnish Admin
        </Typography>
      </Alert>
    </Box>
  );
};

export default InfraMonitoring;

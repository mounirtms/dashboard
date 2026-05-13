import React, { useState, useEffect } from 'react';
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
  DialogActions
} from '@mui/material';
import {
  Refresh as RefreshIcon,
  CheckCircle as CheckCircleIcon,
  Error as ErrorIcon,
  Speed as SpeedIcon,
  Storage as StorageIcon,
  Memory as MemoryIcon,
  Public as PublicIcon,
  CloudQueue as CloudQueueIcon,
  DeleteSweep as DeleteSweepIcon,
  Whatshot as WhatshotIcon
} from '@mui/icons-material';

const InfraMonitoring = () => {
  const [varnishStats, setVarnishStats] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<any>(null);
  const [lastUpdate, setLastUpdate] = useState<any>(null);
  const [autoRefresh, setAutoRefresh] = useState(true);
  const [logs, setLogs] = useState<any[]>([]);
  const [showLogs, setShowLogs] = useState(false);

  const fetchVarnishStats = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await fetch('/api/monitor.php?action=varnish');
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      
      const data = await response.json();
      setVarnishStats(data);
      setLastUpdate(new Date());
    } catch (err: any) {
      console.error('Failed to fetch Varnish stats:', err);
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const fetchLogs = async () => {
    try {
      const response = await fetch('/api/monitor.php?action=logs&type=varnish&lines=50');
      if (!response.ok) throw new Error('Failed to fetch logs');
      
      const data = await response.json();
      if (data.lines) {
        setLogs(data.lines);
      }
    } catch (err) {
      console.error('Failed to fetch logs:', err);
    }
  };

  const handlePurgeCache = async () => {
    if (!window.confirm('Are you sure you want to purge ALL Varnish cache?')) return;
    
    try {
      const response = await fetch('/api/monitor.php?action=cache_manage&site=prod&op=varnish_purge');
      const data = await response.json();
      
      if (data.success) {
        alert('Cache purged successfully!');
        fetchVarnishStats();
      } else {
        alert('Failed to purge cache: ' + data.error);
      }
    } catch (err: any) {
      alert('Error: ' + err.message);
    }
  };

  useEffect(() => {
    fetchVarnishStats();
    fetchLogs();
    
    let interval: any;
    if (autoRefresh) {
      interval = setInterval(() => {
        fetchVarnishStats();
        fetchLogs();
      }, 30000);
    }
    
    return () => {
      if (interval) clearInterval(interval);
    };
  }, [autoRefresh]);

  const StatCard = ({ title, value, subtitle, icon: Icon, color = 'primary', unit = '' }: any) => (
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

  const getHitRateColor = (rate: number) => {
    if (rate >= 80) return 'success';
    if (rate >= 50) return 'warning';
    return 'error';
  };

  if (loading && !varnishStats) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '400px' }}>
        <CircularProgress />
      </Box>
    );
  }

  const stats = varnishStats || {};
  const hitRate = parseFloat(stats.hit_ratio) || 0;

  return (
    <Box sx={{ p: 3 }}>
      <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 3 }}>
        <Typography variant="h4" component="h1">
          Infrastructure Monitoring
        </Typography>
        <Box>
          <Chip
            label={autoRefresh ? 'Auto-refresh ON' : 'Auto-refresh OFF'}
            color={autoRefresh ? 'success' : 'default'}
            onClick={() => setAutoRefresh(!autoRefresh)}
            sx={{ mr: 1 }}
          />
          <Button
            variant="outlined"
            startIcon={<RefreshIcon />}
            onClick={fetchVarnishStats}
            disabled={loading}
          >
            Refresh
          </Button>
        </Box>
      </Box>

      {lastUpdate && (
        <Typography variant="caption" color="textSecondary" sx={{ mb: 2, display: 'block' }}>
          Last updated: {lastUpdate.toLocaleTimeString()}
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
        <StatCard
          title="Cache Hit Rate"
          value={hitRate.toFixed(1)}
          unit="%"
          subtitle="Target: >80%"
          icon={SpeedIcon}
          color={getHitRateColor(hitRate)}
        />

        <StatCard
          title="Total Requests"
          value={stats.total_requests || 0}
          icon={PublicIcon}
          color="info"
        />

        <StatCard
          title="Cache Hits"
          value={stats.hits || 0}
          icon={CheckCircleIcon}
          color="success"
        />

        <StatCard
          title="Cache Misses"
          value={stats.misses || 0}
          icon={ErrorIcon}
          color="error"
        />
      </Box>

      <Box sx={{ mt: 3 }}>
        <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 1 }}>
          <Typography variant="body2" color="textSecondary">
            Cache Efficiency
          </Typography>
          <Typography variant="body2" sx={{ fontWeight: 'bold', color: `${getHitRateColor(hitRate)}.main` }}>
            {hitRate.toFixed(1)}%
          </Typography>
        </Box>
        <LinearProgress
          variant="determinate"
          value={Math.min(hitRate, 100)}
          color={getHitRateColor(hitRate) as any}
          sx={{ height: 10, borderRadius: 5 }}
        />
      </Box>

      <Box sx={{ mt: 3, display: 'grid', gridTemplateColumns: { xs: '1fr', md: 'repeat(3, 1fr)' }, gap: 3 }}>
        <StatCard
          title="Memory Usage"
          value={stats.storage?.usage_pct || 0}
          unit="%"
          subtitle={`Used: ${stats.storage?.used || '0 B'}`}
          icon={StorageIcon}
          color="primary"
        />
        <StatCard
          title="Mobile Traffic"
          value={stats.devices?.mobile_pct || 0}
          unit="%"
          icon={MemoryIcon}
          color="warning"
        />
        <StatCard
          title="Uptime"
          value={Math.floor((stats.uptime || 0) / 3600)}
          unit="h"
          icon={CloudQueueIcon}
          color="info"
        />
      </Box>

      <Box sx={{ mt: 4, display: 'flex', gap: 2, flexWrap: 'wrap' }}>
        <Button
          variant="outlined"
          color="error"
          startIcon={<DeleteSweepIcon />}
          onClick={handlePurgeCache}
        >
          Purge Cache
        </Button>
        <Button
          variant="outlined"
          onClick={() => {
            setShowLogs(true);
            fetchLogs();
          }}
        >
          View Messages
        </Button>
      </Box>

      <Dialog
        open={showLogs}
        onClose={() => setShowLogs(false)}
        maxWidth="lg"
        fullWidth
      >
        <DialogTitle>Varnish System Messages</DialogTitle>
        <DialogContent>
          <Paper
            elevation={0}
            sx={{
              p: 2,
              bgcolor: '#1e1e1e',
              color: '#d4d4d4',
              fontFamily: 'monospace',
              fontSize: '12px',
              maxHeight: '500px',
              overflow: 'auto'
            }}
          >
            {logs.length > 0 ? (
              logs.map((log, idx) => (
                <div key={idx}>{log}</div>
              ))
            ) : (
              <Typography color="textSecondary">No logs available</Typography>
            )}
          </Paper>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setShowLogs(false)}>Close</Button>
          <Button onClick={fetchLogs} variant="outlined">Refresh</Button>
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

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
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
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
  const [varnishStats, setVarnishStats] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [lastUpdate, setLastUpdate] = useState(null);
  const [autoRefresh, setAutoRefresh] = useState(true);
  const [logs, setLogs] = useState([]);
  const [showLogs, setShowLogs] = useState(false);

  const fetchVarnishStats = async () => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await fetch('/api/varnish.php?action=overview');
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      
      const data = await response.json();
      
      if (data.success) {
        setVarnishStats(data.data);
        setLastUpdate(new Date());
      } else {
        throw new Error(data.error || 'Failed to fetch stats');
      }
    } catch (err) {
      console.error('Failed to fetch Varnish stats:', err);
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const fetchLogs = async () => {
    try {
      const response = await fetch('/api/varnish.php?action=logs&lines=50');
      if (!response.ok) throw new Error('Failed to fetch logs');
      
      const data = await response.json();
      if (data.success) {
        setLogs(data.data.logs || []);
      }
    } catch (err) {
      console.error('Failed to fetch logs:', err);
    }
  };

  const handlePurgeCache = async () => {
    if (!window.confirm('Are you sure you want to purge ALL Varnish cache?')) return;
    
    try {
      const response = await fetch('/api/varnish.php?action=purge', {
        method: 'POST'
      });
      const data = await response.json();
      
      if (data.success) {
        alert('Cache purged successfully!');
        fetchVarnishStats();
      } else {
        alert('Failed to purge cache: ' + data.error);
      }
    } catch (err) {
      alert('Error: ' + err.message);
    }
  };

  const handleWarmup = async () => {
    if (!window.confirm('Start Varnish cache warmup? This may take a few minutes.')) return;
    
    try {
      const response = await fetch('/api/varnish.php?action=warmup', {
        method: 'POST'
      });
      const data = await response.json();
      
      if (data.success) {
        alert('Warmup started! Check logs for progress.');
        fetchVarnishStats();
      } else {
        alert('Failed to start warmup: ' + data.error);
      }
    } catch (err) {
      alert('Error: ' + err.message);
    }
  };

  useEffect(() => {
    fetchVarnishStats();
    fetchLogs();
    
    let interval;
    if (autoRefresh) {
      interval = setInterval(() => {
        fetchVarnishStats();
        fetchLogs();
      }, 10000);
    }
    
    return () => {
      if (interval) clearInterval(interval);
    };
  }, [autoRefresh]);

  const StatCard = ({ title, value, subtitle, icon: Icon, color = 'primary', unit = '' }) => (
    <Card elevation={2}>
      <CardContent>
        <Box display="flex" alignItems="center" justifyContent="space-between">
          <Box>
            <Typography color="textSecondary" gutterBottom variant="body2">
              {title}
            </Typography>
            <Typography variant="h4" component="div" color={color}>
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

  const getHitRateColor = (rate) => {
    if (rate >= 80) return 'success';
    if (rate >= 50) return 'warning';
    return 'error';
  };

  if (loading && !varnishStats) {
    return (
      <Box display="flex" justifyContent="center" alignItems="center" minHeight="400px">
        <CircularProgress />
      </Box>
    );
  }

  if (error && !varnishStats) {
    return (
      <Box p={3}>
        <Alert severity="error">
          <Typography variant="h6">Failed to load infrastructure monitoring</Typography>
          <Typography>{error}</Typography>
          <Button onClick={fetchVarnishStats} sx={{ mt: 2 }}>
            Retry
          </Button>
        </Alert>
      </Box>
    );
  }

  const stats = varnishStats?.stats || {};
  const backends = varnishStats?.backends || [];
  const hitRate = parseFloat(stats.hit_rate) || 0;

  return (
    <Box p={3}>
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
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
        Varnish Cache Performance
      </Typography>

      <Grid container spacing={3}>
        <Grid item xs={12} md={6} lg={3}>
          <StatCard
            title="Cache Hit Rate"
            value={hitRate.toFixed(1)}
            unit="%"
            subtitle="Target: >80%"
            icon={SpeedIcon}
            color={getHitRateColor(hitRate)}
          />
        </Grid>

        <Grid item xs={12} md={6} lg={3}>
          <StatCard
            title="Total Requests"
            value={stats.client_req || 0}
            icon={PublicIcon}
            color="info"
          />
        </Grid>

        <Grid item xs={12} md={6} lg={3}>
          <StatCard
            title="Cache Hits"
            value={stats.cache_hit || 0}
            subtitle={`Hit+Pass: ${stats.cache_hitpass || 0}`}
            icon={CheckCircleIcon}
            color="success"
          />
        </Grid>

        <Grid item xs={12} md={6} lg={3}>
          <StatCard
            title="Cache Misses"
            value={stats.cache_miss || 0}
            subtitle={`Hit-Miss: ${stats.cache_hitmiss || 0}`}
            icon={ErrorIcon}
            color="error"
          />
        </Grid>
      </Grid>

      <Box sx={{ mt: 3 }}>
        <Box display="flex" justifyContent="space-between" mb={1}>
          <Typography variant="body2" color="textSecondary">
            Cache Efficiency
          </Typography>
          <Typography variant="body2" fontWeight="bold" color={`${getHitRateColor(hitRate)}.main`}>
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

      <Typography variant="h5" gutterBottom sx={{ mt: 4, mb: 2 }}>
        Backend Servers
      </Typography>

      <TableContainer component={Paper} elevation={2}>
        <Table>
          <TableHead>
            <TableRow>
              <TableCell>Backend</TableCell>
              <TableCell>Status</TableCell>
              <TableCell align="right">Connections</TableCell>
              <TableCell align="right">Failed</TableCell>
              <TableCell align="right">Reused</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {backends.map((backend, idx) => (
              <TableRow key={idx}>
                <TableCell>
                  <Typography variant="body2" fontWeight="bold">
                    {backend.name}
                  </Typography>
                </TableCell>
                <TableCell>
                  <Chip
                    label={backend.status || 'Unknown'}
                    color={backend.status === 'healthy' ? 'success' : 'error'}
                    size="small"
                  />
                </TableCell>
                <TableCell align="right">{backend.conn || 0}</TableCell>
                <TableCell align="right">{backend.fail || 0}</TableCell>
                <TableCell align="right">{backend.reuse || 0}</TableCell>
              </TableRow>
            ))}
            {backends.length === 0 && (
              <TableRow>
                <TableCell colSpan={5} align="center">
                  <Typography color="textSecondary">No backend data available</Typography>
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </TableContainer>

      <Grid container spacing={3} sx={{ mt: 2 }}>
        <Grid item xs={12} md={4}>
          <StatCard
            title="Cached Objects"
            value={stats.n_object || 0}
            icon={StorageIcon}
            color="primary"
          />
        </Grid>
        <Grid item xs={12} md={4}>
          <StatCard
            title="Expired Objects"
            value={stats.n_expired || 0}
            icon={MemoryIcon}
            color="warning"
          />
        </Grid>
        <Grid item xs={12} md={4}>
          <StatCard
            title="Client Connections"
            value={stats.client_conn || 0}
            icon={CloudQueueIcon}
            color="info"
          />
        </Grid>
      </Grid>

      <Box sx={{ mt: 4, display: 'flex', gap: 2, flexWrap: 'wrap' }}>
        <Button
          variant="contained"
          color="primary"
          startIcon={<WhatshotIcon />}
          onClick={handleWarmup}
        >
          Warm Up Cache
        </Button>
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
          View Logs
        </Button>
      </Box>

      <Dialog
        open={showLogs}
        onClose={() => setShowLogs(false)}
        maxWidth="lg"
        fullWidth
      >
        <DialogTitle>Varnish Logs (Last 50 lines)</DialogTitle>
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
        <Typography variant="body2" fontWeight="bold">System Architecture:</Typography>
        <Typography variant="body2">
          Port 80 → Apache HTTP (redirects to HTTPS) <br />
          Port 443 → Apache SSL/TLS <br />
          Port 81 → Apache Backend <br />
          Port 8888 → Varnish Cache <br />
          Port 6082 → Varnish Admin
        </Typography>
      </Alert>
    </Box>
  );
};

export default InfraMonitoring;

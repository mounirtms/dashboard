import { Box, Typography, Card, CardContent, Grid, Chip, Button, Snackbar, Alert, CircularProgress, FormControlLabel, Switch } from '@mui/material';
import { Mail, SettingsSuggest, Engineering, Autorenew, Refresh } from '@mui/icons-material';
import { useState, useEffect, useCallback, useRef } from 'react';
import { fetchQueues, QueueData, runEmergencyCleanup } from '../api/system';
import LoadingState from '../components/common/LoadingState';
import StatCard from '../components/common/StatCard';

export default function QueuesPage() {
  const [data, setData] = useState<QueueData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [restarting, setRestarting] = useState(false);
  const [autoRefresh, setAutoRefresh] = useState(false);
  const [notify, setNotify] = useState({ open: false, message: '', severity: 'success' as any });
  const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const loadData = useCallback(() => {
    setLoading(true);
    fetchQueues()
      .then(setData)
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  useEffect(() => {
    if (timerRef.current) clearInterval(timerRef.current);
    if (autoRefresh) {
      timerRef.current = setInterval(loadData, 15000);
    }
    return () => { if (timerRef.current) clearInterval(timerRef.current); };
  }, [autoRefresh, loadData]);

  const handleRestartConsumers = async () => {
    setRestarting(true);
    try {
      // Use existing cleanup action which usually restarts consumers
      await runEmergencyCleanup('queues');
      setNotify({ open: true, message: 'Consumer restart command sent', severity: 'success' });
      setTimeout(loadData, 3000);
    } catch (e: any) {
      setNotify({ open: true, message: 'Restart failed: ' + e.message, severity: 'error' });
    } finally {
      setRestarting(false);
    }
  };

  if (loading && !data) return <LoadingState message="Loading queue data..." />;
  if (!data) return (
    <Box sx={{ p: 3 }}>
      <Alert severity={error ? 'error' : 'info'} action={
        <Button color="inherit" size="small" onClick={loadData}>Retry</Button>
      }>
        {error ?? 'No queue data available'}
      </Alert>
    </Box>
  );

  const totalPending = Object.values(data.queue_counts).reduce((a, b) => a + b, 0);

  return (
    <Box>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Message Queues
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary', fontSize: '0.88rem' }}>
            Real-time status of message brokers and consumer workers.
          </Typography>
        </Box>
        <Box sx={{ display: 'flex', gap: 1, alignItems: 'center' }}>
          <FormControlLabel
            control={<Switch size="small" checked={autoRefresh} onChange={(e) => setAutoRefresh(e.target.checked)} />}
            label={<Typography variant="caption" sx={{ color: 'text.secondary' }}>Auto (15s)</Typography>}
          />
          <Button startIcon={<Refresh />} variant="outlined" onClick={loadData} disabled={loading}>Refresh</Button>
          <Button 
            startIcon={restarting ? <CircularProgress size={18} color="inherit" /> : <Autorenew />} 
            variant="contained" 
            color="warning" 
            onClick={handleRestartConsumers} 
            disabled={restarting}
          >
            Restart Consumers
          </Button>
        </Box>
      </Box>

      <Grid container spacing={2} sx={{ mb: 4 }}>
        <Grid size={{ xs: 12, sm: 6, md: 4 }}>
          <StatCard 
            label="Total Pending" 
            value={totalPending} 
            color={totalPending > 100 ? 'error' : totalPending > 20 ? 'warning' : 'success'} 
            icon={<Mail />} 
          />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 4 }}>
          <StatCard 
            label="Active Consumers" 
            value={data.consumers.length} 
            color="primary" 
            icon={<Engineering />} 
          />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 4 }}>
          <StatCard 
            label="Queues with Messages" 
            value={Object.keys(data.queue_counts).length} 
            color="info" 
            icon={<SettingsSuggest />} 
          />
        </Grid>
      </Grid>

      <Grid container spacing={2}>
        <Grid size={{ xs: 12, md: 6 }}>
          <Card>
            <CardContent>
              <Typography variant="h6" sx={{ mb: 2, fontWeight: 700 }}>Queue Depth</Typography>
              {Object.keys(data.queue_counts).length > 0 ? (
                <Box sx={{ display: 'grid', gap: 1.5 }}>
                  {Object.entries(data.queue_counts).map(([name, count]) => (
                    <Box key={name} sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', p: 1.5, borderRadius: 1, backgroundColor: 'background.default', border: '1px solid', borderColor: 'divider' }}>
                      <Typography variant="body2" sx={{ fontWeight: 600, fontSize: '0.85rem', fontFamily: 'monospace' }}>{name}</Typography>
                      <Chip 
                        label={count} 
                        size="small" 
                        color={count > 50 ? 'error' : count > 10 ? 'warning' : 'primary'} 
                        sx={{ fontWeight: 800 }} 
                      />
                    </Box>
                  ))}
                </Box>
              ) : (
                <Typography variant="body2" sx={{ color: 'text.disabled', textAlign: 'center', py: 4 }}>
                  All queues are empty
                </Typography>
              )}
            </CardContent>
          </Card>
        </Grid>

        <Grid size={{ xs: 12, md: 6 }}>
          <Card>
            <CardContent>
              <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
                <Typography variant="h6" sx={{ fontWeight: 700 }}>Registered Consumers</Typography>
                <Chip
                  label={`${data.consumers.length} total`}
                  size="small"
                  color={data.consumers.length > 0 ? 'success' : 'default'}
                  variant="outlined"
                />
              </Box>
              {data.consumers.length === 0 ? (
                <Typography variant="body2" sx={{ color: 'text.disabled', textAlign: 'center', py: 3 }}>
                  No consumers registered
                </Typography>
              ) : (
                <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1 }}>
                  {data.consumers.map((consumer) => (
                    <Chip
                      key={consumer}
                      label={consumer}
                      size="small"
                      variant="outlined"
                      color="primary"
                      sx={{ fontSize: '0.75rem', fontFamily: 'monospace' }}
                    />
                  ))}
                </Box>
              )}
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      <Snackbar 
        open={notify.open} 
        autoHideDuration={4000} 
        onClose={() => setNotify({ ...notify, open: false })}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
      >
        <Alert severity={notify.severity} variant="filled" sx={{ width: '100%' }}>{notify.message}</Alert>
      </Snackbar>
    </Box>
  );
}

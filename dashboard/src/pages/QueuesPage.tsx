import { getErrMsg } from '../utils/formatters';
import {
  Box, Typography, Card, CardContent, Grid, Chip, Button,
  Snackbar, Alert, CircularProgress, Skeleton,
} from '@mui/material';
import { Mail, SettingsSuggest, Engineering, Autorenew, Refresh } from '@mui/icons-material';
import { useState, useCallback } from 'react';
import { fetchQueues, QueueData, runEmergencyCleanup } from '../api/system';
import { usePolling } from '../hooks/usePolling';
import StatCard from '../components/common/StatCard';

export default function QueuesPage() {
  const [restarting, setRestarting] = useState(false);
  const [notify, setNotify] = useState({ open: false, message: '', severity: 'success' as 'success' | 'error' | 'warning' | 'info' });

  const fetcher = useCallback(() => fetchQueues(), []);
  const { data, loading, refreshing, error, refetch } = usePolling<QueueData>(fetcher, 30_000);

  const handleRestartConsumers = async () => {
    setRestarting(true);
    try {
      await runEmergencyCleanup('queues');
      setNotify({ open: true, message: 'Consumer restart command sent', severity: 'success' });
      setTimeout(refetch, 3000);
    } catch (e: unknown) {
      setNotify({ open: true, message: 'Restart failed: ' + getErrMsg(e), severity: 'error' });
    } finally {
      setRestarting(false);
    }
  };

  if (loading) {
    return (
      <Box>
        <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between' }}>
          <Skeleton width={220} height={40} />
          <Box sx={{ display: 'flex', gap: 1 }}>
            <Skeleton width={100} height={40} />
            <Skeleton width={160} height={40} />
          </Box>
        </Box>
        <Grid container spacing={2} sx={{ mb: 4 }}>
          {[...Array(3)].map((_, i) => <Grid key={i} size={{ xs: 12, sm: 6, md: 4 }}><Skeleton height={100} /></Grid>)}
        </Grid>
        <Grid container spacing={2}>
          <Grid size={{ xs: 12, md: 6 }}><Skeleton height={300} /></Grid>
          <Grid size={{ xs: 12, md: 6 }}><Skeleton height={300} /></Grid>
        </Grid>
      </Box>
    );
  }

  if (error && !data) {
    return (
      <Box>
        <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>
        <Button variant="outlined" onClick={refetch}>Retry</Button>
      </Box>
    );
  }

  if (!data) return null;

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
            {refreshing && <CircularProgress size={12} sx={{ ml: 1, verticalAlign: 'middle' }} />}
          </Typography>
        </Box>
        <Box sx={{ display: 'flex', gap: 1 }}>
          <Button startIcon={<Refresh />} variant="outlined" onClick={refetch} disabled={refreshing}>
            Refresh
          </Button>
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

      {error && <Alert severity="warning" sx={{ mb: 2 }}>{error}</Alert>}

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
                    <Box
                      key={name}
                      sx={{
                        display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                        p: 1.5, borderRadius: 1, backgroundColor: 'background.default',
                        border: '1px solid', borderColor: 'divider',
                      }}
                    >
                      <Typography variant="body2" sx={{ fontWeight: 600, fontSize: '0.85rem', fontFamily: 'monospace' }}>
                        {name}
                      </Typography>
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
              <Typography variant="h6" sx={{ mb: 2, fontWeight: 700 }}>Registered Consumers</Typography>
              <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1 }}>
                {data.consumers.map((consumer) => (
                  <Chip
                    key={consumer}
                    label={consumer}
                    size="small"
                    variant="outlined"
                    sx={{ fontSize: '0.75rem', fontFamily: 'monospace' }}
                  />
                ))}
              </Box>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      <Snackbar
        open={notify.open}
        autoHideDuration={4000}
        onClose={() => setNotify(prev => ({ ...prev, open: false }))}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
      >
        <Alert severity={notify.severity} variant="filled" sx={{ width: '100%' }}>
          {notify.message}
        </Alert>
      </Snackbar>
    </Box>
  );
}

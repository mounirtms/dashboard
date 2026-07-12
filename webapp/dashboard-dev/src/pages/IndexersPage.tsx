import { Box, Typography, Card, CardContent, Button, Chip, Select, MenuItem, FormControl, InputLabel, LinearProgress, Divider, Grid, Snackbar, Alert } from '@mui/material';
import { Refresh, PlayArrow, CheckCircle, Error, Warning, HourglassEmpty, Autorenew } from '@mui/icons-material';
import { useState, useEffect, useCallback } from 'react';
import { fetchMagentoIndexers, runMagentoIndexer } from '../api/magento';
import LoadingState from '../components/common/LoadingState';
import StatusBadge from '../components/common/StatusBadge';

export default function IndexersPage() {
  const [indexers, setIndexers] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [env, setEnv] = useState('prod');
  const [processing, setProcessing] = useState<string | null>(null);
  const [notify, setNotify] = useState({ open: false, message: '', severity: 'success' as any });

  const loadData = useCallback(() => {
    setLoading(true);
    setError(null);
    fetchMagentoIndexers(env)
      .then(setIndexers)
      .catch((e) => {
        setError(e.response?.data?.message || e.message || 'Failed to load indexers');
        setNotify({ open: true, message: e.message, severity: 'error' });
      })
      .finally(() => setLoading(false));
  }, [env]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleReindex = async (id: string) => {
    setProcessing(id);
    try {
      await runMagentoIndexer(env, id);
      setNotify({ open: true, message: `Reindex of ${id} completed`, severity: 'success' });
      loadData();
    } catch (e: any) {
      setNotify({ open: true, message: 'Indexer failed: ' + e.message, severity: 'error' });
    } finally {
      setProcessing(null);
    }
  };

  const handleReindexAll = async () => {
    setProcessing('all');
    try {
      await runMagentoIndexer(env, 'all');
      setNotify({ open: true, message: 'Full reindex triggered', severity: 'success' });
      loadData();
    } catch (e: any) {
      setNotify({ open: true, message: 'Reindex all failed: ' + e.message, severity: 'error' });
    } finally {
      setProcessing(null);
    }
  };

  if (loading && indexers.length === 0 && !error) return <LoadingState message="Fetching Magento index status..." />;

  const getStatusInfo = (status: string) => {
    switch(status.toLowerCase()) {
      case 'valid': return { color: 'success' as const, icon: <CheckCircle sx={{ fontSize: 14 }} /> };
      case 'invalid': return { color: 'error' as const, icon: <Error sx={{ fontSize: 14 }} /> };
      case 'working': return { color: 'warning' as const, icon: <HourglassEmpty sx={{ fontSize: 14 }} /> };
      default: return { color: 'default' as const, icon: <Warning sx={{ fontSize: 14 }} /> };
    }
  };

  return (
    <Box>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Magento Indexers
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Manage search indexes, prices, and category permissions.
          </Typography>
        </Box>

        <Box sx={{ display: 'flex', gap: 2, alignItems: 'center' }}>
          <FormControl size="small" sx={{ minWidth: 140 }}>
            <InputLabel>Environment</InputLabel>
            <Select value={env} label="Environment" onChange={(e) => setEnv(e.target.value)}>
              <MenuItem value="prod">Production</MenuItem>
              <MenuItem value="beta">Beta</MenuItem>
              <MenuItem value="dev">Dev</MenuItem>
            </Select>
          </FormControl>
          <Button variant="outlined" startIcon={<Refresh />} onClick={loadData}>Refresh</Button>
          <Button 
            variant="contained" 
            color="secondary" 
            startIcon={<Autorenew />} 
            onClick={handleReindexAll} 
            disabled={!!processing}
          >
            Reindex All
          </Button>
        </Box>
      </Box>

      {error && (
        <Alert severity="error" sx={{ mb: 2 }} action={
          <Button color="inherit" size="small" onClick={loadData}>Retry</Button>
        }>
          {error}
        </Alert>
      )}

      <Grid container spacing={2}>
        {indexers.map((idx) => {
          const info = getStatusInfo(idx.status);
          return (
            <Grid key={idx.id} size={{ xs: 12, md: 6, lg: 4 }}>
              <Card sx={{ height: '100%', position: 'relative' }}>
                {processing === idx.id && <LinearProgress sx={{ position: 'absolute', top: 0, left: 0, right: 0, height: 2 }} />}
                <CardContent>
                  <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 1.5 }}>
                    <Typography variant="subtitle2" sx={{ fontWeight: 700, fontFamily: 'monospace' }}>{idx.id}</Typography>
                    <StatusBadge label={idx.status.toUpperCase()} color={info.color} icon={info.icon} />
                  </Box>
                  <Typography variant="body2" sx={{ mb: 2, color: 'text.secondary', minHeight: 40 }}>{idx.title}</Typography>
                  <Divider sx={{ mb: 2 }} />
                  <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <Typography variant="caption" sx={{ color: 'text.disabled' }}>Mode: <strong>{idx.mode}</strong></Typography>
                    <Button 
                      size="small" 
                      variant="contained" 
                      startIcon={<PlayArrow />}
                      disabled={!!processing}
                      onClick={() => handleReindex(idx.id)}
                    >
                      Reindex
                    </Button>
                  </Box>
                </CardContent>
              </Card>
            </Grid>
          );
        })}
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

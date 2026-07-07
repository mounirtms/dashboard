import {
  Box, Typography, Card, CardContent, Button, Select, MenuItem,
  FormControl, InputLabel, Tooltip, LinearProgress, Divider, Grid,
  Snackbar, Alert,
} from '@mui/material';
import { Refresh, PlayArrow, CheckCircle, Error as ErrorIcon, Warning, HourglassEmpty, Autorenew } from '@mui/icons-material';
import { useState, useCallback } from 'react';
import { fetchMagentoIndexers, runMagentoIndexer } from '../api/magento';
import StatusBadge from '../components/common/StatusBadge';
import { usePolling } from '../hooks/usePolling';

// ── Types ────────────────────────────────────────────────────────────────────

interface Indexer {
  id: string;
  title: string;
  status: string;
  mode: string;
}

type NotifySeverity = 'success' | 'error' | 'warning' | 'info';

interface NotifyState {
  open: boolean;
  message: string;
  severity: NotifySeverity;
}

// ── Helper ───────────────────────────────────────────────────────────────────

function getStatusInfo(status: string): {
  color: 'success' | 'error' | 'warning' | 'default';
  icon: React.ReactElement;
} {
  switch (status.toLowerCase()) {
    case 'valid':   return { color: 'success', icon: <CheckCircle sx={{ fontSize: 14 }} /> };
    case 'invalid': return { color: 'error',   icon: <ErrorIcon   sx={{ fontSize: 14 }} /> };
    case 'working': return { color: 'warning', icon: <HourglassEmpty sx={{ fontSize: 14 }} /> };
    default:        return { color: 'default', icon: <Warning     sx={{ fontSize: 14 }} /> };
  }
}

function extractMessage(e: unknown): string {
  if (e instanceof Error) return e.message;
  return 'Unknown error';
}

// ── Page ─────────────────────────────────────────────────────────────────────

export default function IndexersPage() {
  const [env, setEnv]           = useState('prod');
  const [processing, setProcessing] = useState<string | null>(null);
  const [notify, setNotify]     = useState<NotifyState>({ open: false, message: '', severity: 'success' });

  // fetchMagentoIndexers doesn't yet accept AbortSignal — wrap to satisfy hook type
  const fetcher = useCallback(
    (_signal?: AbortSignal) => fetchMagentoIndexers(env) as Promise<Indexer[]>,
    [env],
  );
  const { data: indexers = [], loading, refreshing, refetch } = usePolling<Indexer[]>(fetcher, 60_000);

  const handleReindex = async (id: string) => {
    setProcessing(id);
    try {
      await runMagentoIndexer(env, id);
      setNotify({ open: true, message: `Reindex of ${id} completed`, severity: 'success' });
      refetch();
    } catch (e: unknown) {
      setNotify({ open: true, message: 'Indexer failed: ' + extractMessage(e), severity: 'error' });
    } finally {
      setProcessing(null);
    }
  };

  const handleReindexAll = async () => {
    setProcessing('all');
    try {
      await runMagentoIndexer(env, 'all');
      setNotify({ open: true, message: 'Full reindex triggered', severity: 'success' });
      refetch();
    } catch (e: unknown) {
      setNotify({ open: true, message: 'Reindex all failed: ' + extractMessage(e), severity: 'error' });
    } finally {
      setProcessing(null);
    }
  };

  return (
    <Box>
      {/* Header */}
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
          <Button
            variant="outlined"
            startIcon={<Refresh />}
            onClick={refetch}
            disabled={loading || refreshing}
          >
            Refresh
          </Button>
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

      {/* Initial skeleton rows */}
      {loading && indexers.length === 0 && (
        <Grid container spacing={2}>
          {[...Array(6)].map((_, i) => (
            <Grid key={i} size={{ xs: 12, md: 6, lg: 4 }}>
              <Card sx={{ height: 140 }}>
                <Box sx={{ p: 2, display: 'flex', flexDirection: 'column', gap: 1.5 }}>
                  <Box sx={{ height: 16, bgcolor: 'rgba(255,255,255,0.07)', borderRadius: 1, width: '60%' }} />
                  <Box sx={{ height: 12, bgcolor: 'rgba(255,255,255,0.04)', borderRadius: 1, width: '90%' }} />
                  <Box sx={{ height: 12, bgcolor: 'rgba(255,255,255,0.04)', borderRadius: 1, width: '75%' }} />
                </Box>
              </Card>
            </Grid>
          ))}
        </Grid>
      )}

      {/* Index cards */}
      <Grid container spacing={2}>
        {indexers.map((idx) => {
          const info = getStatusInfo(idx.status);
          return (
            <Grid key={idx.id} size={{ xs: 12, md: 6, lg: 4 }}>
              <Card sx={{ height: '100%', position: 'relative' }}>
                {(processing === idx.id || (refreshing && !processing)) && (
                  <LinearProgress sx={{ position: 'absolute', top: 0, left: 0, right: 0, height: 2 }} />
                )}
                <CardContent>
                  <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 1.5 }}>
                    <Typography variant="subtitle2" sx={{ fontWeight: 700, fontFamily: 'monospace' }}>{idx.id}</Typography>
                    <StatusBadge label={idx.status.toUpperCase()} color={info.color} icon={info.icon} />
                  </Box>
                  <Typography variant="body2" sx={{ mb: 2, color: 'text.secondary', minHeight: 40 }}>{idx.title}</Typography>
                  <Divider sx={{ mb: 2 }} />
                  <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <Typography variant="caption" sx={{ color: 'text.disabled' }}>
                      Mode: <strong>{idx.mode}</strong>
                    </Typography>
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
        onClose={() => setNotify(prev => ({ ...prev, open: false }))}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
      >
        <Alert severity={notify.severity} variant="filled" sx={{ width: '100%' }}>{notify.message}</Alert>
      </Snackbar>
    </Box>
  );
}

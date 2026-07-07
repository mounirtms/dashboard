import { getErrMsg } from '../utils/formatters';
import {
  Box, Typography, Card, CardContent, Table, TableBody, TableCell,
  TableContainer, TableHead, TableRow, Chip, IconButton, Tooltip,
  Alert, Snackbar, CircularProgress, Button, FormControl, InputLabel,
  Select, MenuItem, Skeleton,
} from '@mui/material';
import { Schedule, PlayArrow, Comment, Refresh, Dns, Code } from '@mui/icons-material';
import { useState, useCallback } from 'react';
import { fetchCrons, runCron, CronEntry } from '../api/system';
import { usePolling } from '../hooks/usePolling';

const SITES = [
  { key: '', name: 'System (crontab)' },
  { key: 'prod', name: 'Production' },
  { key: 'beta', name: 'Beta' },
  { key: 'tsdnd', name: 'TSDND' },
  { key: 'dev', name: 'Dev' },
  { key: 'pim', name: 'PIM' },
];

export default function CronsPage() {
  const [selectedSite, setSelectedSite] = useState<string>('prod');
  const [executing, setExecuting] = useState<string | null>(null);
  const [notify, setNotify] = useState({ open: false, message: '', severity: 'success' as 'success' | 'error' | 'warning' | 'info' });

  // Stale-while-revalidate: re-create fetcher when selectedSite changes
  const fetcher = useCallback(
    () => fetchCrons(selectedSite || undefined),
    [selectedSite],
  );

  const { data, loading, refreshing, error, refetch } = usePolling(fetcher, 60_000);

  const crons: CronEntry[] = data?.entries ?? [];

  const handleRunNow = async (command: string) => {
    setExecuting(command);
    try {
      const res = await runCron(command);
      setNotify({
        open: true,
        message: res.success ? `Job started (PID: ${res.pid})` : res.message,
        severity: res.success ? 'success' : 'error',
      });
      if (res.success) {
        setTimeout(refetch, 2000);
      }
    } catch (e: unknown) {
      setNotify({ open: true, message: getErrMsg(e), severity: 'error' });
    } finally {
      setExecuting(null);
    }
  };

  // Initial full-page loading skeleton
  if (loading) {
    return (
      <Box>
        <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <Skeleton width={200} height={40} />
          <Box sx={{ display: 'flex', gap: 2 }}>
            <Skeleton width={160} height={40} />
            <Skeleton width={100} height={40} />
          </Box>
        </Box>
        <Card>
          <CardContent sx={{ p: 0 }}>
            {[...Array(6)].map((_, i) => (
              <Skeleton key={i} height={52} sx={{ mx: 2, my: 0.5 }} />
            ))}
          </CardContent>
        </Card>
      </Box>
    );
  }

  if (error && crons.length === 0) {
    return (
      <Box>
        <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>
        <Button variant="outlined" onClick={refetch}>Retry</Button>
      </Box>
    );
  }

  return (
    <Box>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Cron Jobs
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary', fontSize: '0.88rem' }}>
            {selectedSite
              ? `${SITES.find(s => s.key === selectedSite)?.name} Magento cron schedule`
              : 'System crontab entries'}
            {refreshing && (
              <CircularProgress size={12} sx={{ ml: 1, verticalAlign: 'middle' }} />
            )}
          </Typography>
        </Box>
        <Box sx={{ display: 'flex', gap: 2, alignItems: 'center' }}>
          <FormControl size="small" sx={{ minWidth: 160 }}>
            <InputLabel>Site</InputLabel>
            <Select value={selectedSite} label="Site" onChange={(e) => setSelectedSite(e.target.value)}>
              {SITES.map(s => <MenuItem key={s.key} value={s.key}>{s.name}</MenuItem>)}
            </Select>
          </FormControl>
          <Button startIcon={<Refresh />} variant="outlined" onClick={refetch} disabled={refreshing}>
            Refresh
          </Button>
        </Box>
      </Box>

      {error && <Alert severity="warning" sx={{ mb: 2 }}>{error}</Alert>}

      <Card>
        <CardContent sx={{ p: 0 }}>
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow sx={{ backgroundColor: 'background.default' }}>
                  <TableCell sx={{ fontWeight: 700 }}>Source</TableCell>
                  <TableCell sx={{ fontWeight: 700 }}>Schedule</TableCell>
                  <TableCell sx={{ fontWeight: 700 }}>Command</TableCell>
                  <TableCell sx={{ fontWeight: 700 }}>Status</TableCell>
                  <TableCell sx={{ fontWeight: 700 }}>Details</TableCell>
                  <TableCell align="right" sx={{ fontWeight: 700 }}>Actions</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {crons.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={6} sx={{ textAlign: 'center', py: 4, color: 'text.disabled' }}>
                      No cron jobs found.{' '}
                      {selectedSite
                        ? 'Check if Magento is properly configured.'
                        : 'System crontab may be empty.'}
                    </TableCell>
                  </TableRow>
                ) : crons.map((cron, idx) => (
                  <TableRow key={idx} hover>
                    <TableCell>
                      {cron.source === 'magento' ? (
                        <Chip label="MAGENTO" size="small" color="secondary" variant="outlined"
                          sx={{ fontWeight: 600, fontSize: '0.6rem' }} />
                      ) : (
                        <Chip label="SYSTEM" size="small" variant="outlined"
                          sx={{ fontWeight: 600, fontSize: '0.6rem' }} />
                      )}
                    </TableCell>
                    <TableCell>
                      <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                        <Schedule sx={{ fontSize: 18, color: 'primary.main' }} />
                        <Typography variant="body2" sx={{ fontFamily: 'monospace', fontWeight: 600, fontSize: '0.75rem' }}>
                          {cron.schedule}
                        </Typography>
                      </Box>
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2"
                        sx={{ fontFamily: 'monospace', fontSize: '0.75rem', maxWidth: 350, overflow: 'hidden', textOverflow: 'ellipsis' }}>
                        {cron.command}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      {cron.running > 0 ? (
                        <Chip
                          icon={<PlayArrow sx={{ fontSize: 16 }} />}
                          label="Running"
                          size="small"
                          color="success"
                          sx={{ fontWeight: 700 }}
                        />
                      ) : cron.source === 'magento' && cron.magento_status ? (
                        <Chip
                          label={cron.magento_status.toUpperCase()}
                          size="small"
                          color={
                            cron.color === 'error' ? 'error'
                              : cron.color === 'warning' ? 'warning'
                                : cron.color === 'success' ? 'success'
                                  : 'default'
                          }
                          variant="outlined"
                          sx={{ fontWeight: 700, fontSize: '0.65rem' }}
                        />
                      ) : (
                        <Chip label="Idle" size="small" variant="outlined" />
                      )}
                    </TableCell>
                    <TableCell>
                      {cron.comment ? (
                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                          <Comment sx={{ fontSize: 16, color: 'text.disabled' }} />
                          <Typography variant="caption" sx={{ color: 'text.secondary', fontSize: '0.7rem' }}>
                            {cron.comment}
                          </Typography>
                        </Box>
                      ) : '—'}
                    </TableCell>
                    <TableCell align="right">
                      <Tooltip title="Run Now">
                        <IconButton
                          color="primary"
                          size="small"
                          onClick={() => handleRunNow(cron.command)}
                          disabled={!!executing}
                        >
                          {executing === cron.command
                            ? <CircularProgress size={20} color="inherit" />
                            : <PlayArrow />}
                        </IconButton>
                      </Tooltip>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </CardContent>
      </Card>

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

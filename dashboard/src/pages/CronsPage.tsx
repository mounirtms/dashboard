import { Box, Typography, Card, CardContent, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Chip, IconButton, Tooltip, Alert, Snackbar, CircularProgress, Button } from '@mui/material';
import { Schedule, PlayArrow, Comment, Refresh } from '@mui/icons-material';
import { useState, useEffect } from 'react';
import { fetchCrons, runCron, CronEntry } from '../api/system';
import LoadingState from '../components/common/LoadingState';

export default function CronsPage() {
  const [crons, setCrons] = useState<CronEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [executing, setExecuting] = useState<string | null>(null);
  const [notify, setNotify] = useState({ open: false, message: '', severity: 'success' as any });

  const loadCrons = () => {
    setLoading(true);
    fetchCrons()
      .then((data) => setCrons(data.entries))
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    loadCrons();
  }, []);

  const handleRunNow = async (command: string) => {
    setExecuting(command);
    try {
      const res = await runCron(command);
      setNotify({ 
        open: true, 
        message: res.success ? `Job started (PID: ${res.pid})` : res.message, 
        severity: res.success ? 'success' : 'error' 
      });
      if (res.success) {
        setTimeout(loadCrons, 2000); // Wait a bit then refresh
      }
    } catch (e: any) {
      setNotify({ open: true, message: e.message, severity: 'error' });
    } finally {
      setExecuting(null);
    }
  };

  if (loading && crons.length === 0) return <LoadingState message="Loading cron jobs..." />;
  if (error) return <LoadingState message={`Error: ${error}`} />;

  return (
    <Box>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Cron Jobs
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary', fontSize: '0.88rem' }}>
            Current system crontab entries and their execution status.
          </Typography>
        </Box>
        <Button startIcon={<Refresh />} variant="outlined" onClick={loadCrons} disabled={loading}>Refresh</Button>
      </Box>

      <Card>
        <CardContent sx={{ p: 0 }}>
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow sx={{ backgroundColor: 'background.default' }}>
                  <TableCell sx={{ fontWeight: 700 }}>Schedule</TableCell>
                  <TableCell sx={{ fontWeight: 700 }}>Command</TableCell>
                  <TableCell sx={{ fontWeight: 700 }}>Status</TableCell>
                  <TableCell sx={{ fontWeight: 700 }}>Description</TableCell>
                  <TableCell align="right" sx={{ fontWeight: 700 }}>Actions</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {crons.map((cron, idx) => (
                  <TableRow key={idx} hover>
                    <TableCell>
                      <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                        <Schedule sx={{ fontSize: 18, color: 'primary.main' }} />
                        <Typography variant="body2" sx={{ fontFamily: 'monospace', fontWeight: 600 }}>
                          {cron.schedule}
                        </Typography>
                      </Box>
                    </TableCell>
                    <TableCell>
                      <Typography variant="body2" sx={{ fontFamily: 'monospace', fontSize: '0.75rem', maxWidth: 400, overflow: 'hidden', textOverflow: 'ellipsis' }}>
                        {cron.command}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      {cron.running > 0 ? (
                        <Chip 
                          icon={<PlayArrow sx={{ fontSize: 16 }} />} 
                          label={`Running (${cron.running})`} 
                          size="small" 
                          color="success" 
                          sx={{ fontWeight: 700 }}
                        />
                      ) : (
                        <Chip label="Idle" size="small" variant="outlined" />
                      )}
                    </TableCell>
                    <TableCell>
                      {cron.comment ? (
                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                          <Comment sx={{ fontSize: 16, color: 'text.disabled' }} />
                          <Typography variant="caption" sx={{ color: 'text.secondary' }}>
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
                          {executing === cron.command ? <CircularProgress size={20} color="inherit" /> : <PlayArrow />}
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
        onClose={() => setNotify({ ...notify, open: false })}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
      >
        <Alert severity={notify.severity} variant="filled" sx={{ width: '100%' }}>{notify.message}</Alert>
      </Snackbar>
    </Box>
  );
}

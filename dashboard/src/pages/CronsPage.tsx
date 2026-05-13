import { Box, Typography, Card, CardContent, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Chip, IconButton, Tooltip, Alert, Snackbar, CircularProgress, Button, FormControl, InputLabel, Select, MenuItem } from '@mui/material';
import { Schedule, PlayArrow, Comment, Refresh, Dns, Code } from '@mui/icons-material';
import { useState, useEffect } from 'react';
import { fetchCrons, runCron, CronEntry } from '../api/system';
import LoadingState from '../components/common/LoadingState';

const SITES = [
  { key: '', name: 'System (crontab)' },
  { key: 'prod', name: 'Production' },
  { key: 'beta', name: 'Beta' },
  { key: 'dev', name: 'Dev' },
  { key: 'pim', name: 'PIM' },
];

export default function CronsPage() {
  const [selectedSite, setSelectedSite] = useState<string>('prod');
  const [crons, setCrons] = useState<CronEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [executing, setExecuting] = useState<string | null>(null);
  const [notify, setNotify] = useState({ open: false, message: '', severity: 'success' as any });

  const loadCrons = () => {
    setLoading(true);
    fetchCrons(selectedSite || undefined)
      .then((data) => setCrons(data.entries))
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    loadCrons();
  }, [selectedSite]);

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
        setTimeout(loadCrons, 2000);
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
            {selectedSite ? `${SITES.find(s => s.key === selectedSite)?.name} Magento cron schedule` : 'System crontab entries'}
          </Typography>
        </Box>
        <Box sx={{ display: 'flex', gap: 2, alignItems: 'center' }}>
          <FormControl size="small" sx={{ minWidth: 160 }}>
            <InputLabel>Site</InputLabel>
            <Select value={selectedSite} label="Site" onChange={(e) => setSelectedSite(e.target.value)}>
              {SITES.map(s => <MenuItem key={s.key} value={s.key}>{s.name}</MenuItem>)}
            </Select>
          </FormControl>
          <Button startIcon={<Refresh />} variant="outlined" onClick={loadCrons} disabled={loading}>Refresh</Button>
        </Box>
      </Box>

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
                      No cron jobs found. {selectedSite ? 'Check if Magento is properly configured.' : 'System crontab may be empty.'}
                    </TableCell>
                  </TableRow>
                ) : crons.map((cron, idx) => (
                  <TableRow key={idx} hover>
                    <TableCell>
                      {cron.source === 'magento' ? (
                        <Chip label="MAGENTO" size="small" color="secondary" variant="outlined" sx={{ fontWeight: 600, fontSize: '0.6rem' }} />
                      ) : (
                        <Chip label="SYSTEM" size="small" variant="outlined" sx={{ fontWeight: 600, fontSize: '0.6rem' }} />
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
                      <Typography variant="body2" sx={{ fontFamily: 'monospace', fontSize: '0.75rem', maxWidth: 350, overflow: 'hidden', textOverflow: 'ellipsis' }}>
                        {cron.command}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      {cron.running > 0 ? (
                        <Chip 
                          icon={<PlayArrow sx={{ fontSize: 16 }} />} 
                          label={`Running`} 
                          size="small" 
                          color="success" 
                          sx={{ fontWeight: 700 }}
                        />
                      ) : cron.source === 'magento' && cron.magento_status ? (
                        <Chip 
                          label={cron.magento_status.toUpperCase()} 
                          size="small" 
                          color={cron.color === 'error' ? 'error' : cron.color === 'warning' ? 'warning' : cron.color === 'success' ? 'success' : 'default'}
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

import { Box, Typography, Card, CardContent, Grid, Button, IconButton, Tooltip, Chip, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Paper, Alert, Snackbar } from '@mui/material';
import { Refresh, PowerSettingsNew, Gavel, History, Security, Terminal, NetworkCheck, Dangerous } from '@mui/icons-material';
import { useState, useEffect, useCallback } from 'react';
import apiClient from '../api/client';
import LoadingState from '../components/common/LoadingState';

interface SshSession {
  type: string;
  pid?: string;
  user: string;
  system_user?: string;
  tty: string;
  from: string;
  login_at: string;
  idle: string;
  status: string;
  duration_seconds?: number;
}

interface SshData {
  service_active: boolean;
  active_sessions: number;
  total_sessions: number;
  sessions: SshSession[];
  established_connections: number;
  connections: any[];
  failed_logins_total: number;
  recent_failed: any[];
  sshd_status: string;
  timestamp: number;
}

export default function SshSessionsPage() {
  const [data, setData] = useState<SshData | null>(null);
  const [loading, setLoading] = useState(true);
  const [snackbar, setSnackbar] = useState({ open: false, message: '', severity: 'success' as any });

  const loadData = useCallback(() => {
    setLoading(true);
    apiClient.get('/api/monitor.php?action=ssh')
      .then(({ data }) => setData(data))
      .catch((e) => {
        console.error(e);
        setSnackbar({ open: true, message: 'Failed to fetch SSH sessions', severity: 'error' });
      })
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    loadData();
    const interval = setInterval(loadData, 15000);
    return () => clearInterval(interval);
  }, [loadData]);

  const handleKillSession = async (id: string) => {
    if (!confirm(`Are you sure you want to terminate session ${id}?`)) return;
    try {
      const { data } = await apiClient.post('/api/monitor.php?action=ssh_kill_single', { session_id: id });
      setSnackbar({ open: true, message: data.message || 'Session terminated', severity: data.success ? 'success' : 'error' });
      if (data.success) loadData();
    } catch (e: any) {
      setSnackbar({ open: true, message: e.message, severity: 'error' });
    }
  };

  const handleKillAll = async () => {
    if (!confirm('Are you sure you want to kill ALL other SSH sessions? This will exclude your current session if possible.')) return;
    try {
      const { data } = await apiClient.post('/api/monitor.php?action=ssh_kill');
      setSnackbar({ open: true, message: data.message || 'All sessions killed', severity: data.success ? 'success' : 'error' });
      if (data.success) loadData();
    } catch (e: any) {
      setSnackbar({ open: true, message: e.message, severity: 'error' });
    }
  };

  const handleRestartSshd = async () => {
    if (!confirm('Warning: Restarting SSHD might briefly interrupt connections. Continue?')) return;
    try {
      const { data } = await apiClient.get('/api/monitor.php?action=sshd_restart');
      setSnackbar({ open: true, message: data.message || 'SSHD restarted', severity: data.success ? 'success' : 'error' });
      if (data.success) loadData();
    } catch (e: any) {
      setSnackbar({ open: true, message: e.message, severity: 'error' });
    }
  };

  if (loading && !data) return <LoadingState message="Scanning SSH sessions..." />;

  return (
    <Box sx={{ pb: 4 }}>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            SSH & Session Monitor
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Real-time tracking and control of server SSH access and active sessions.
          </Typography>
        </Box>
        <Box sx={{ display: 'flex', gap: 1.5 }}>
          <Button variant="outlined" startIcon={<Refresh />} onClick={loadData} disabled={loading}>
            Refresh
          </Button>
          <Button variant="outlined" color="warning" startIcon={<PowerSettingsNew />} onClick={handleRestartSshd}>
            Restart SSHD
          </Button>
          <Button variant="contained" color="error" startIcon={<Gavel />} onClick={handleKillAll}>
            Kill All Sessions
          </Button>
        </Box>
      </Box>

      {/* Stats Cards */}
      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <Card sx={{ bgcolor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.06)' }}>
            <CardContent>
              <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                <Terminal color="primary" />
                <Box>
                  <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 700, textTransform: 'uppercase' }}>Active SSH</Typography>
                  <Typography variant="h5" sx={{ fontWeight: 800 }}>{data?.active_sessions || 0}</Typography>
                </Box>
              </Box>
            </CardContent>
          </Card>
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <Card sx={{ bgcolor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.06)' }}>
            <CardContent>
              <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                <NetworkCheck color="success" />
                <Box>
                  <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 700, textTransform: 'uppercase' }}>Connections</Typography>
                  <Typography variant="h5" sx={{ fontWeight: 800 }}>{data?.established_connections || 0}</Typography>
                </Box>
              </Box>
            </CardContent>
          </Card>
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <Card sx={{ bgcolor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.06)' }}>
            <CardContent>
              <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                <Dangerous color="error" />
                <Box>
                  <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 700, textTransform: 'uppercase' }}>Failed Logins</Typography>
                  <Typography variant="h5" sx={{ fontWeight: 800 }}>{data?.failed_logins_total || 0}</Typography>
                </Box>
              </Box>
            </CardContent>
          </Card>
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <Card sx={{ bgcolor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.06)' }}>
            <CardContent>
              <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                <Security color={data?.service_active ? "success" : "error"} />
                <Box>
                  <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 700, textTransform: 'uppercase' }}>Service Status</Typography>
                  <Typography variant="h5" sx={{ fontWeight: 800, color: data?.service_active ? 'success.main' : 'error.main' }}>
                    {data?.sshd_status?.toUpperCase() || 'OFFLINE'}
                  </Typography>
                </Box>
              </Box>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      <Grid container spacing={3}>
        {/* Active Sessions Table */}
        <Grid size={{ xs: 12, lg: 8 }}>
          <Typography variant="h6" sx={{ mb: 1.5, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
            <History /> Active Sessions
          </Typography>
          <TableContainer component={Paper} sx={{ bgcolor: 'transparent', border: '1px solid rgba(255,255,255,0.06)' }}>
            <Table size="small">
              <TableHead>
                <TableRow sx={{ bgcolor: 'rgba(255,255,255,0.02)' }}>
                  <TableCell sx={{ fontWeight: 700, py: 1.5 }}>User</TableCell>
                  <TableCell sx={{ fontWeight: 700 }}>TTY / PID</TableCell>
                  <TableCell sx={{ fontWeight: 700 }}>From (IP)</TableCell>
                  <TableCell sx={{ fontWeight: 700 }}>Login Time</TableCell>
                  <TableCell sx={{ fontWeight: 700 }}>Duration / Idle</TableCell>
                  <TableCell sx={{ fontWeight: 700 }}>Status</TableCell>
                  <TableCell align="right" sx={{ fontWeight: 700 }}>Actions</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {data?.sessions && data.sessions.length > 0 ? data.sessions.map((session, i) => (
                  <TableRow key={i} sx={{ '&:last-child td': { borderBottom: 0 } }}>
                    <TableCell sx={{ fontWeight: 600 }}>{session.user}</TableCell>
                    <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.75rem' }}>{session.tty} / {session.pid || '—'}</TableCell>
                    <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.75rem' }}>{session.from}</TableCell>
                    <TableCell sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>{session.login_at}</TableCell>
                    <TableCell sx={{ fontSize: '0.75rem' }}>{session.idle}</TableCell>
                    <TableCell>
                      <Chip 
                        label={session.status} 
                        size="small" 
                        sx={{ 
                          height: 20, 
                          fontSize: '0.65rem', 
                          fontWeight: 700,
                          bgcolor: session.status === 'active' ? 'rgba(74,222,128,0.1)' : 'rgba(251,191,36,0.1)',
                          color: session.status === 'active' ? '#4ade80' : '#fbbf24'
                        }} 
                      />
                    </TableCell>
                    <TableCell align="right">
                      <Tooltip title="Kill Session">
                        <IconButton size="small" color="error" onClick={() => handleKillSession(session.pid || session.tty)}>
                          <PowerSettingsNew sx={{ fontSize: 18 }} />
                        </IconButton>
                      </Tooltip>
                    </TableCell>
                  </TableRow>
                )) : (
                  <TableRow>
                    <TableCell colSpan={7} sx={{ py: 4, textAlign: 'center', color: 'text.disabled' }}>No active SSH sessions</TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </TableContainer>
        </Grid>

        {/* Failed Logins & Security Alert */}
        <Grid size={{ xs: 12, lg: 4 }}>
          <Typography variant="h6" sx={{ mb: 1.5, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
            <Security /> Security Audit
          </Typography>
          
          <Card sx={{ mb: 2, bgcolor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.06)' }}>
            <CardContent sx={{ p: 2 }}>
              <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1.5 }}>Recent Failed Logins</Typography>
              {data?.recent_failed && data.recent_failed.length > 0 ? (
                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                  {data.recent_failed.map((fail, i) => (
                    <Box key={i} sx={{ p: 1, bgcolor: 'rgba(248,113,113,0.05)', border: '1px solid rgba(248,113,113,0.1)', borderRadius: 1 }}>
                      <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 0.5 }}>
                        <Typography sx={{ fontWeight: 700, fontSize: '0.75rem', color: '#f87171' }}>
                          {fail.invalid_user ? 'INVALID USER' : 'VALID USER'}
                        </Typography>
                        <Typography variant="caption" sx={{ color: 'text.disabled' }}>{fail.user}</Typography>
                      </Box>
                      <Typography sx={{ fontFamily: 'monospace', fontSize: '0.7rem' }}>Source: {fail.ip}</Typography>
                    </Box>
                  ))}
                </Box>
              ) : (
                <Typography variant="body2" sx={{ color: 'text.disabled', py: 2, textAlign: 'center' }}>No recent failed attempts</Typography>
              )}
            </CardContent>
          </Card>

          <Alert severity="info" variant="outlined" sx={{ border: '1px solid rgba(96,165,250,0.2)', bgcolor: 'rgba(96,165,250,0.02)' }}>
            <Typography variant="caption" sx={{ fontWeight: 600 }}>
              Audit logs are automatically written to <code>/var/log/secure</code>. Use CSF Firewall to block repeat offenders.
            </Typography>
          </Alert>
        </Grid>
      </Grid>

      <Snackbar open={snackbar.open} autoHideDuration={4000} onClose={() => setSnackbar(prev => ({ ...prev, open: false }))}>
        <Alert severity={snackbar.severity} sx={{ width: '100%' }}>{snackbar.message}</Alert>
      </Snackbar>
    </Box>
  );
}

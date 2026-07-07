import { getErrMsg } from '../utils/formatters';
import {
  Box, Typography, Card, CardContent, Grid, Button, IconButton, Tooltip,
  Chip, Table, TableBody, TableCell, TableContainer, TableHead, TableRow,
  Paper, Alert, Snackbar, Dialog, DialogTitle, DialogContent, DialogActions,
  TextField, Divider, List, ListItem, ListItemText, ListItemSecondaryAction,
  CircularProgress,
} from '@mui/material';
import {
  Refresh, PowerSettingsNew, Gavel, History, Security, Terminal,
  NetworkCheck, Dangerous, PersonAdd, PersonRemove, Shield, Lock,
  CheckCircle, Warning, AdminPanelSettings,
} from '@mui/icons-material';
import React, { useState, useCallback } from 'react';
import apiClient from '../api/client';
import { usePolling } from '../hooks/usePolling';

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

interface SshAllowedUser {
  username: string;
  exists: boolean;
  in_wheel: boolean;
  in_sudo: boolean;
  has_key: boolean;
  last_login?: string;
  status: 'active' | 'inactive' | 'unknown';
}

interface SshUsersData {
  allowed_users: string[];
  user_details: SshAllowedUser[];
  permit_root_login: string;
  password_auth: boolean;
  timestamp: number;
}

async function fetchSshData(): Promise<SshData> {
  const { data } = await apiClient.get('/api/monitor.php?action=ssh');
  return data;
}

async function fetchSshUsers(): Promise<SshUsersData> {
  const { data } = await apiClient.get('/api/monitor.php?action=ssh_users');
  return data;
}

export default function SshSessionsPage() {
  const [snackbar, setSnackbar] = useState({ open: false, message: '', severity: 'success' as 'success' | 'error' | 'warning' | 'info' });
  const [addDialog, setAddDialog] = useState(false);
  const [removeDialog, setRemoveDialog] = useState<{ open: boolean; username?: string }>({ open: false });
  const [newUsername, setNewUsername] = useState('');
  const [saving, setSaving] = useState(false);

  const sessionFetcher  = useCallback(() => fetchSshData(), []);
  const usersFetcher    = useCallback(() => fetchSshUsers(), []);

  const { data, loading, refreshing, error: sessionError, refetch: refetchSessions } = usePolling<SshData>(sessionFetcher, 15_000);
  const { data: sshUsers, refetch: refetchUsers } = usePolling<SshUsersData>(usersFetcher, 60_000);

  const showSnack = (message: string, severity: typeof snackbar.severity) =>
    setSnackbar({ open: true, message, severity });

  const handleKillSession = async (id: string) => {
    if (!confirm(`Are you sure you want to terminate session ${id}?`)) return;
    try {
      const { data: res } = await apiClient.post('/api/monitor.php?action=ssh_kill_single', { session_id: id });
      showSnack(res.message || 'Session terminated', res.success ? 'success' : 'error');
      if (res.success) refetchSessions();
    } catch (e: unknown) {
      showSnack(getErrMsg(e), 'error');
    }
  };

  const handleKillAll = async () => {
    if (!confirm('Are you sure you want to kill ALL other SSH sessions?')) return;
    try {
      const { data: res } = await apiClient.post('/api/monitor.php?action=ssh_kill');
      showSnack(res.message || 'All sessions killed', res.success ? 'success' : 'error');
      if (res.success) refetchSessions();
    } catch (e: unknown) {
      showSnack(getErrMsg(e), 'error');
    }
  };

  const handleRestartSshd = async () => {
    if (!confirm('Warning: Restarting SSHD might briefly interrupt connections. Continue?')) return;
    try {
      const { data: res } = await apiClient.get('/api/monitor.php?action=sshd_restart');
      showSnack(res.message || 'SSHD restarted', res.success ? 'success' : 'error');
      if (res.success) { refetchSessions(); refetchUsers(); }
    } catch (e: unknown) {
      showSnack(getErrMsg(e), 'error');
    }
  };

  const handleAddUser = async () => {
    if (!newUsername.trim() || !/^[a-z0-9_-]{1,32}$/.test(newUsername.trim())) {
      showSnack('Invalid username format', 'error');
      return;
    }
    setSaving(true);
    try {
      const { data: res } = await apiClient.post('/api/monitor.php?action=ssh_user_add', { username: newUsername.trim() });
      showSnack(res.message || `${newUsername} added to SSH AllowUsers`, res.success ? 'success' : 'error');
      if (res.success) { setAddDialog(false); setNewUsername(''); refetchUsers(); }
    } catch (e: unknown) {
      showSnack(getErrMsg(e), 'error');
    } finally {
      setSaving(false);
    }
  };

  const handleRemoveUser = async () => {
    const username = removeDialog.username;
    if (!username) return;
    setSaving(true);
    try {
      const { data: res } = await apiClient.post('/api/monitor.php?action=ssh_user_remove', { username });
      showSnack(res.message || `${username} removed from SSH AllowUsers`, res.success ? 'success' : 'error');
      if (res.success) { setRemoveDialog({ open: false }); refetchUsers(); }
    } catch (e: unknown) {
      showSnack(getErrMsg(e), 'error');
    } finally {
      setSaving(false);
    }
  };

  const isRefreshing = refreshing;

  return (
    <Box sx={{ pb: 4 }}>
      {/* Header */}
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            SSH &amp; Session Monitor
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Real-time tracking, access control and management of SSH users and active sessions.
            {isRefreshing && <CircularProgress size={12} sx={{ ml: 1, verticalAlign: 'middle' }} />}
          </Typography>
        </Box>
        <Box sx={{ display: 'flex', gap: 1.5 }}>
          <Button variant="outlined" startIcon={<Refresh />} onClick={() => { refetchSessions(); refetchUsers(); }} disabled={loading}>
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

      {sessionError && <Alert severity="warning" sx={{ mb: 2 }}>{sessionError}</Alert>}

      {/* Stats Cards */}
      <Grid container spacing={2} sx={{ mb: 3 }}>
        {(
          [
            { icon: <Terminal color="primary" />,    label: 'Active SSH',     value: data?.active_sessions || 0, color: undefined },
            { icon: <NetworkCheck color="success" />, label: 'Connections',   value: data?.established_connections || 0, color: undefined },
            { icon: <Dangerous color="error" />,      label: 'Failed Logins', value: data?.failed_logins_total || 0, color: undefined },
            {
              icon:  <Security color={data?.service_active ? 'success' : 'error'} />,
              label: 'Service Status',
              value: data?.sshd_status?.toUpperCase() || 'OFFLINE',
              color: data?.service_active ? 'success.main' : 'error.main',
            },
          ] as { icon: React.ReactNode; label: string; value: string | number; color: string | undefined }[]
        ).map((stat, i) => (
          <Grid key={i} size={{ xs: 12, sm: 6, md: 3 }}>
            <Card sx={{ bgcolor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.06)' }}>
              <CardContent>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                  {stat.icon}
                  <Box>
                    <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 700, textTransform: 'uppercase' }}>
                      {stat.label}
                    </Typography>
                    <Typography variant="h5" sx={{ fontWeight: 800, color: stat.color }}>
                      {loading ? <CircularProgress size={20} /> : stat.value}
                    </Typography>
                  </Box>
                </Box>
              </CardContent>
            </Card>
          </Grid>
        ))}
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
                  <TableCell sx={{ fontWeight: 700 }}>Idle</TableCell>
                  <TableCell sx={{ fontWeight: 700 }}>Status</TableCell>
                  <TableCell align="right" sx={{ fontWeight: 700 }}>Actions</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {data?.sessions && data.sessions.length > 0
                  ? data.sessions.map((session, i) => (
                    <TableRow key={i} sx={{ '&:last-child td': { borderBottom: 0 } }}>
                      <TableCell sx={{ fontWeight: 600 }}>{session.user}</TableCell>
                      <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.75rem' }}>
                        {session.tty} / {session.pid || '—'}
                      </TableCell>
                      <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.75rem' }}>{session.from}</TableCell>
                      <TableCell sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>{session.login_at}</TableCell>
                      <TableCell sx={{ fontSize: '0.75rem' }}>{session.idle}</TableCell>
                      <TableCell>
                        <Chip
                          label={session.status}
                          size="small"
                          sx={{
                            height: 20, fontSize: '0.65rem', fontWeight: 700,
                            bgcolor: session.status === 'active' ? 'rgba(74,222,128,0.1)' : 'rgba(251,191,36,0.1)',
                            color:   session.status === 'active' ? '#4ade80' : '#fbbf24',
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
                  ))
                  : (
                    <TableRow>
                      <TableCell colSpan={7} sx={{ py: 4, textAlign: 'center', color: 'text.disabled' }}>
                        {loading ? 'Loading sessions…' : 'No active SSH sessions'}
                      </TableCell>
                    </TableRow>
                  )}
              </TableBody>
            </Table>
          </TableContainer>

          {/* SSH AllowUsers Management */}
          <Box sx={{ mt: 3 }}>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 1.5 }}>
              <Typography variant="h6" sx={{ fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                <AdminPanelSettings /> SSH Allowed Users
              </Typography>
              <Button
                size="small"
                variant="contained"
                startIcon={<PersonAdd />}
                onClick={() => { setNewUsername(''); setAddDialog(true); }}
                sx={{ fontSize: '0.75rem' }}
              >
                Add User
              </Button>
            </Box>

            {sshUsers && (
              <Box sx={{ display: 'flex', gap: 1, mb: 2, flexWrap: 'wrap' }}>
                <Chip
                  icon={<Lock sx={{ fontSize: 14 }} />}
                  label={`PermitRootLogin: ${sshUsers.permit_root_login}`}
                  size="small"
                  color={sshUsers.permit_root_login === 'no' ? 'success' : 'warning'}
                  sx={{ fontSize: '0.7rem' }}
                />
                <Chip
                  icon={sshUsers.password_auth ? <Warning sx={{ fontSize: 14 }} /> : <CheckCircle sx={{ fontSize: 14 }} />}
                  label={`PasswordAuth: ${sshUsers.password_auth ? 'YES' : 'NO'}`}
                  size="small"
                  color={sshUsers.password_auth ? 'warning' : 'success'}
                  sx={{ fontSize: '0.7rem' }}
                />
                <Chip
                  icon={<Shield sx={{ fontSize: 14 }} />}
                  label={`${sshUsers.allowed_users?.length || 0} Allowed Users`}
                  size="small"
                  color="info"
                  sx={{ fontSize: '0.7rem' }}
                />
              </Box>
            )}

            <Card sx={{ bgcolor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.06)' }}>
              {sshUsers?.user_details && sshUsers.user_details.length > 0 ? (
                <List disablePadding>
                  {sshUsers.user_details.map((u, i) => (
                    <Box key={u.username}>
                      <ListItem sx={{ py: 1.2 }}>
                        <ListItemText
                          primary={
                            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                              <Typography sx={{ fontWeight: 700, fontSize: '0.9rem', fontFamily: 'monospace' }}>
                                {u.username}
                              </Typography>
                              {u.in_wheel && <Chip label="SUDO"  size="small" color="warning" sx={{ height: 18, fontSize: '0.6rem', fontWeight: 800 }} />}
                              {u.has_key  && <Chip label="KEY"   size="small" color="success" sx={{ height: 18, fontSize: '0.6rem', fontWeight: 800 }} />}
                              {!u.exists  && <Chip label="NO HOME" size="small" color="error" sx={{ height: 18, fontSize: '0.6rem', fontWeight: 800 }} />}
                            </Box>
                          }
                          secondary={
                            <Typography variant="caption" sx={{ color: 'text.disabled' }}>
                              Last login: {u.last_login || '—'}
                            </Typography>
                          }
                        />
                        <ListItemSecondaryAction>
                          <Tooltip title={u.username === 'root' ? 'Cannot remove root' : `Remove ${u.username} from SSH access`}>
                            <span>
                              <IconButton
                                size="small"
                                color="error"
                                disabled={u.username === 'root'}
                                onClick={() => setRemoveDialog({ open: true, username: u.username })}
                              >
                                <PersonRemove sx={{ fontSize: 18 }} />
                              </IconButton>
                            </span>
                          </Tooltip>
                        </ListItemSecondaryAction>
                      </ListItem>
                      {i < sshUsers.user_details.length - 1 && <Divider sx={{ opacity: 0.3 }} />}
                    </Box>
                  ))}
                </List>
              ) : (
                <Box sx={{ py: 3, textAlign: 'center' }}>
                  <Typography variant="body2" sx={{ color: 'text.disabled' }}>
                    No SSH AllowUsers data available
                  </Typography>
                </Box>
              )}
            </Card>
          </Box>
        </Grid>

        {/* Security Panel */}
        <Grid size={{ xs: 12, lg: 4 }}>
          <Typography variant="h6" sx={{ mb: 1.5, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
            <Security /> Security Audit
          </Typography>

          <Card sx={{ mb: 2, bgcolor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.06)' }}>
            <CardContent sx={{ p: 2 }}>
              <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1.5 }}>
                Recent Failed Logins
              </Typography>
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
                <Typography variant="body2" sx={{ color: 'text.disabled', py: 2, textAlign: 'center' }}>
                  No recent failed attempts
                </Typography>
              )}
            </CardContent>
          </Card>

          <Card sx={{ mb: 2, bgcolor: 'rgba(74,222,128,0.03)', border: '1px solid rgba(74,222,128,0.1)' }}>
            <CardContent sx={{ p: 2 }}>
              <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1.5, color: 'success.main', display: 'flex', alignItems: 'center', gap: 1 }}>
                <CheckCircle sx={{ fontSize: 16 }} /> Recent Security Changes
              </Typography>
              <Box sx={{ display: 'flex', flexDirection: 'column', gap: 0.8 }}>
                {[
                  'tsdnd removed from wheel group',
                  'tsdnd sudoers file deleted',
                  'tsdnd removed from SSH AllowUsers',
                  'dashboard added to SSH AllowUsers',
                  'root password rotated',
                  'dashboard password rotated',
                ].map((item, i) => (
                  <Box key={i} sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                    <CheckCircle sx={{ fontSize: 14, color: 'success.main', flexShrink: 0 }} />
                    <Typography variant="caption" sx={{ color: 'text.secondary' }}>{item}</Typography>
                  </Box>
                ))}
              </Box>
            </CardContent>
          </Card>

          <Alert severity="info" variant="outlined" sx={{ border: '1px solid rgba(96,165,250,0.2)', bgcolor: 'rgba(96,165,250,0.02)' }}>
            <Typography variant="caption" sx={{ fontWeight: 600 }}>
              Audit logs are written to <code>/var/log/secure</code>. Use CSF Firewall to block repeat offenders.
            </Typography>
          </Alert>
        </Grid>
      </Grid>

      {/* Add User Dialog */}
      <Dialog open={addDialog} onClose={() => setAddDialog(false)} maxWidth="xs" fullWidth>
        <DialogTitle sx={{ fontWeight: 800 }}>Add SSH User</DialogTitle>
        <DialogContent>
          <Typography variant="body2" sx={{ color: 'text.secondary', mb: 2 }}>
            This will add the user to the <code>AllowUsers</code> directive in <code>/etc/ssh/sshd_config</code> and reload SSHD.
          </Typography>
          <TextField
            autoFocus fullWidth label="System Username" value={newUsername}
            onChange={(e) => setNewUsername(e.target.value.toLowerCase().replace(/[^a-z0-9_-]/g, ''))}
            helperText="Lowercase, alphanumeric, underscore, hyphen only"
            size="small"
            onKeyDown={(e) => e.key === 'Enter' && handleAddUser()}
          />
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setAddDialog(false)}>Cancel</Button>
          <Button variant="contained" onClick={handleAddUser} disabled={saving || !newUsername.trim()}>
            {saving ? <CircularProgress size={16} color="inherit" /> : 'Add User'}
          </Button>
        </DialogActions>
      </Dialog>

      {/* Remove User Dialog */}
      <Dialog open={removeDialog.open} onClose={() => setRemoveDialog({ open: false })} maxWidth="xs" fullWidth>
        <DialogTitle sx={{ fontWeight: 800, color: 'error.main' }}>Remove SSH Access</DialogTitle>
        <DialogContent>
          <Typography variant="body2">
            Remove <strong>{removeDialog.username}</strong> from SSH <code>AllowUsers</code>?
          </Typography>
          <Typography variant="body2" sx={{ color: 'warning.main', mt: 1 }}>
            ⚠️ This will immediately prevent <strong>{removeDialog.username}</strong> from connecting via SSH after SSHD reload.
          </Typography>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setRemoveDialog({ open: false })}>Cancel</Button>
          <Button variant="contained" color="error" onClick={handleRemoveUser} disabled={saving}>
            {saving ? <CircularProgress size={16} color="inherit" /> : 'Remove Access'}
          </Button>
        </DialogActions>
      </Dialog>

      <Snackbar open={snackbar.open} autoHideDuration={4000} onClose={() => setSnackbar(prev => ({ ...prev, open: false }))}>
        <Alert severity={snackbar.severity} sx={{ width: '100%' }}>{snackbar.message}</Alert>
      </Snackbar>
    </Box>
  );
}

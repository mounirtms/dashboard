import { useState, useEffect, useCallback, useRef } from 'react';
import {
  Box, Typography, Tabs, Tab, Grid, Card, CardContent, Table, TableBody,
  TableCell, TableContainer, TableHead, TableRow, Button, Alert, Chip,
  Paper, Dialog, DialogTitle, DialogContent, DialogActions, Snackbar,
  TextField, IconButton, Tooltip, InputAdornment
} from '@mui/material';
import {
  Refresh, Dns, Lan, Warning, CheckCircle, Cancel,
  AccessTime, Security, Fingerprint, Lock, LockOpen, Block, Shield, Delete
} from '@mui/icons-material';
import LoadingState from '../components/common/LoadingState';
import StatCard from '../components/common/StatCard';
import StatusBadge from '../components/common/StatusBadge';
import {
  fetchSshConnections, fetchServices, fetchNetworkConnections,
  killAllSshSessions, killSingleSshSession, restartSshd,
  fetchCsfFirewall, csfAction,
  SshData, ServicesData, NetworkData, CsfFirewallData
} from '../api/system';

const REFRESH_INTERVAL = 60000;

export default function SystemHealthPage() {
  const [tab, setTab] = useState(0);
  const [sshData, setSshData] = useState<SshData | null>(null);
  const [servicesData, setServicesData] = useState<ServicesData | null>(null);
  const [networkData, setNetworkData] = useState<NetworkData | null>(null);
  const [csfData, setCsfData] = useState<CsfFirewallData | null>(null);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [dialogConfig, setDialogConfig] = useState<{ title: string; message: string; action: () => void } | null>(null);
  const [snackbar, setSnackbar] = useState<{ open: boolean; message: string; severity: 'success' | 'error' | 'warning' | 'info' }>({ open: false, message: '', severity: 'info' });
  const [ipInput, setIpInput] = useState('');
  const inFlightRef = useRef(false);

  const loadData = useCallback(async () => {
    if (inFlightRef.current) return;
    inFlightRef.current = true;
    try {
      setLoading(true);
      setError(null);
      const [ssh, svc, net, csf] = await Promise.all([
        fetchSshConnections(),
        fetchServices(),
        fetchNetworkConnections(),
        fetchCsfFirewall(),
      ]);
      setSshData(ssh);
      setServicesData(svc);
      setNetworkData(net);
      setCsfData(csf);
    } catch (e: any) {
      setError(e.message || 'Failed to fetch system health data');
    } finally {
      setLoading(false);
      inFlightRef.current = false;
    }
  }, []);

  useEffect(() => {
    loadData();
    const interval = setInterval(loadData, REFRESH_INTERVAL);
    return () => clearInterval(interval);
  }, [loadData]);

  if (loading && !sshData && !servicesData && !networkData) {
    return <LoadingState message="Loading system health data..." />;
  }

  const formatUptime = (seconds: number): string => {
    if (seconds <= 0) return 'N/A';
    const d = Math.floor(seconds / 86400);
    const h = Math.floor((seconds % 86400) / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    if (d > 0) return `${d}d ${h}h`;
    if (h > 0) return `${h}h ${m}m`;
    return `${m}m`;
  };

  const statusColor = (status: string) => {
    switch (status) {
      case 'active': return 'success';
      case 'failed': return 'error';
      case 'inactive': return 'warning';
      default: return 'default';
    }
  };

  const showSnackbar = (message: string, severity: 'success' | 'error' | 'warning' | 'info') => {
    setSnackbar({ open: true, message, severity });
  };

  const confirmAction = (title: string, message: string, action: () => void) => {
    setDialogConfig({ title, message, action });
    setDialogOpen(true);
  };

  const handleKillSession = async (session: any) => {
    const sessionId = session.pid || session.tty;
    confirmAction(
      'Kill SSH Session',
      `Are you sure you want to terminate the session for user "${session.user}" from ${session.from}?`,
      async () => {
        try {
          setActionLoading(true);
          const result = await killSingleSshSession(sessionId);
          showSnackbar(result.message || 'Session terminated', result.success ? 'success' : 'error');
          loadData();
        } catch (e: any) {
          showSnackbar(e.message || 'Failed to kill session', 'error');
        } finally {
          setActionLoading(false);
        }
      }
    );
  };

  const handleKillAllSessions = async () => {
    confirmAction(
      'Kill All SSH Sessions',
      'This will terminate all SSH sessions except your current one. Continue?',
      async () => {
        try {
          setActionLoading(true);
          const result = await killAllSshSessions(sshData?.sessions.find(s => s.user === 'current')?.tty);
          showSnackbar(result.message || 'All sessions terminated', result.success ? 'success' : 'error');
          loadData();
        } catch (e: any) {
          showSnackbar(e.message || 'Failed to kill sessions', 'error');
        } finally {
          setActionLoading(false);
        }
      }
    );
  };

  const handleRestartSshd = async () => {
    confirmAction(
      'Restart SSH Daemon',
      'This will restart the SSH service. Existing connections may be affected. Continue?',
      async () => {
        try {
          setActionLoading(true);
          const result = await restartSshd();
          showSnackbar(result.message || 'SSH service restarted', result.success ? 'success' : 'error');
          loadData();
        } catch (e: any) {
          showSnackbar(e.message || 'Failed to restart SSH', 'error');
        } finally {
          setActionLoading(false);
        }
      }
    );
  };

  const handleCsfAction = async (action: string, ip?: string) => {
    const actionLabels: Record<string, string> = {
      deny: 'Block IP',
      allow: 'Allow IP',
      unblock: 'Unblock IP',
      restart: 'Restart CSF',
      disable_testing: 'Disable Testing Mode'
    };

    if (!ip && !['restart', 'disable_testing'].includes(action)) {
      showSnackbar('IP address is required', 'error');
      return;
    }

    confirmAction(
      actionLabels[action] || action,
      ip ? `Are you sure you want to ${actionLabels[action]?.toLowerCase()} ${ip}?` : `Are you sure you want to ${actionLabels[action]?.toLowerCase()}?`,
      async () => {
        try {
          setActionLoading(true);
          const result = await csfAction(action, ip);
          showSnackbar(result.message || 'Action completed', result.success ? 'success' : 'error');
          loadData();
        } catch (e: any) {
          showSnackbar(e.message || 'Action failed', 'error');
        } finally {
          setActionLoading(false);
        }
      }
    );
  };

  const handleBlockFailedIp = (ip: string) => {
    setIpInput(ip);
    handleCsfAction('deny', ip);
  };

  return (
    <Box>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            System Health
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary', fontSize: '0.88rem' }}>
            SSH connections, services, and network monitoring
          </Typography>
        </Box>
        <Button
          startIcon={<Refresh />}
          variant="outlined"
          size="small"
          onClick={loadData}
          disabled={loading}
        >
          Refresh
        </Button>
      </Box>

      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}

      <Tabs value={tab} onChange={(_, v) => setTab(v)} sx={{ mb: 3 }}>
        <Tab icon={<Security />} label="SSH" iconPosition="start" />
        <Tab icon={<Dns />} label="Services" iconPosition="start" />
        <Tab icon={<Lan />} label="Network" iconPosition="start" />
        <Tab icon={<Shield />} label="Firewall" iconPosition="start" />
      </Tabs>

      {/* SSH Tab */}
      {tab === 0 && sshData && (
        <Box>
          {!sshData.service_active && (
            <Alert severity="error" sx={{ mb: 2 }} icon={<Security />}>
              SSH daemon (sshd) is not running!
            </Alert>
          )}

          <Box sx={{ mb: 2, display: 'flex', gap: 1, flexWrap: 'wrap' }}>
            <Button
              startIcon={<Delete />}
              variant="outlined"
              size="small"
              color="error"
              onClick={handleKillAllSessions}
              disabled={actionLoading || sshData.active_sessions === 0}
            >
              Kill All Sessions
            </Button>
            <Button
              startIcon={<Refresh />}
              variant="outlined"
              size="small"
              color="warning"
              onClick={handleRestartSshd}
              disabled={actionLoading}
            >
              Restart SSHD
            </Button>
          </Box>

          <Grid container spacing={2} sx={{ mb: 3 }}>
            <Grid size={{ xs: 12, sm: 4 }}>
              <StatCard
                label="Active Sessions"
                value={sshData.active_sessions}
                color={sshData.active_sessions > 0 ? 'success' : 'default'}
                icon={<Security />}
              />
            </Grid>
            <Grid size={{ xs: 12, sm: 4 }}>
              <StatCard
                label="Failed Logins (total)"
                value={sshData.failed_logins_total}
                color={sshData.failed_logins_total > 100 ? 'error' : sshData.failed_logins_total > 10 ? 'warning' : 'success'}
                icon={<Warning />}
              />
            </Grid>
            <Grid size={{ xs: 12, sm: 4 }}>
              <StatCard
                label="SSH Service"
                value={sshData.sshd_status}
                color={sshData.service_active ? 'success' : 'error'}
                icon={sshData.service_active ? <CheckCircle /> : <Cancel />}
              />
            </Grid>
          </Grid>

          {sshData.sessions.length > 0 && (
            <Card sx={{ mb: 3 }}>
              <CardContent>
                <Typography variant="h6" sx={{ fontWeight: 700, mb: 2 }}>Active SSH Sessions</Typography>
                <TableContainer>
                  <Table size="small">
                    <TableHead>
                      <TableRow>
                        <TableCell>User</TableCell>
                        <TableCell>TTY</TableCell>
                        <TableCell>From</TableCell>
                        <TableCell>Login At</TableCell>
                        <TableCell>Idle</TableCell>
                        <TableCell>Actions</TableCell>
                      </TableRow>
                    </TableHead>
                    <TableBody>
                      {sshData.sessions.map((s, i) => (
                        <TableRow key={i}>
                          <TableCell sx={{ fontFamily: 'monospace' }}>{s.user}</TableCell>
                          <TableCell>{s.tty}</TableCell>
                          <TableCell sx={{ fontFamily: 'monospace' }}>{s.from}</TableCell>
                          <TableCell>{s.login_at}</TableCell>
                          <TableCell>{s.idle}</TableCell>
                          <TableCell>
                            <Tooltip title="Terminate session">
                              <IconButton
                                size="small"
                                color="error"
                                onClick={() => handleKillSession(s)}
                                disabled={actionLoading}
                              >
                                <Delete fontSize="small" />
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
          )}

          {sshData.recent_failed.length > 0 && (
            <Card>
              <CardContent>
                <Typography variant="h6" sx={{ fontWeight: 700, mb: 2, color: 'error.main' }}>
                  Recent Failed Login Attempts
                </Typography>
                <TableContainer>
                  <Table size="small">
                    <TableHead>
                      <TableRow>
                        <TableCell>User</TableCell>
                        <TableCell>IP Address</TableCell>
                        <TableCell>Invalid User</TableCell>
                        <TableCell>Actions</TableCell>
                      </TableRow>
                    </TableHead>
                    <TableBody>
                      {sshData.recent_failed.map((f, i) => (
                        <TableRow key={i}>
                          <TableCell sx={{ fontFamily: 'monospace' }}>{f.user}</TableCell>
                          <TableCell sx={{ fontFamily: 'monospace' }}>{f.ip}</TableCell>
                          <TableCell>
                            {f.invalid_user ? (
                              <Chip label="Yes" size="small" color="error" variant="outlined" />
                            ) : (
                              <Chip label="No" size="small" color="default" variant="outlined" />
                            )}
                          </TableCell>
                          <TableCell>
                            <Tooltip title="Block IP in firewall">
                              <IconButton
                                size="small"
                                color="error"
                                onClick={() => handleBlockFailedIp(f.ip)}
                                disabled={actionLoading}
                              >
                                <Block fontSize="small" />
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
          )}

          {sshData.sessions.length === 0 && sshData.recent_failed.length === 0 && (
            <Alert severity="info">No active SSH sessions or recent failed logins.</Alert>
          )}
        </Box>
      )}

      {/* Services Tab */}
      {tab === 1 && servicesData && (
        <Box>
          <Grid container spacing={2} sx={{ mb: 3 }}>
            <Grid size={{ xs: 12, sm: 3 }}>
              <StatCard label="Total Services" value={servicesData.summary.total} color="primary" icon={<Dns />} />
            </Grid>
            <Grid size={{ xs: 12, sm: 3 }}>
              <StatCard label="Active" value={servicesData.summary.active} color="success" icon={<CheckCircle />} />
            </Grid>
            <Grid size={{ xs: 12, sm: 3 }}>
              <StatCard label="Inactive" value={servicesData.summary.inactive} color="warning" icon={<AccessTime />} />
            </Grid>
            <Grid size={{ xs: 12, sm: 3 }}>
              <StatCard label="Failed" value={servicesData.summary.failed} color={servicesData.summary.failed > 0 ? 'error' : 'default'} icon={<Cancel />} />
            </Grid>
          </Grid>

          {Object.entries(servicesData.categories).map(([category, services]) => (
            <Card key={category} sx={{ mb: 2 }}>
              <CardContent>
                <Typography variant="h6" sx={{ fontWeight: 700, mb: 2, textTransform: 'capitalize' }}>
                  {category} Services
                </Typography>
                <Grid container spacing={1}>
                  {services.map((svc) => (
                    <Grid size={{ xs: 12, sm: 6, md: 4 }} key={svc.name}>
                      <Paper
                        variant="outlined"
                        sx={{
                          p: 1.5,
                          display: 'flex',
                          alignItems: 'center',
                          gap: 1,
                          borderColor: statusColor(svc.status) === 'success' ? 'success.main' : statusColor(svc.status) === 'error' ? 'error.main' : 'divider',
                        }}
                      >
                        <StatusBadge label={svc.status} color={statusColor(svc.status) as any} />
                        <Box sx={{ flex: 1 }}>
                          <Typography sx={{ fontWeight: 600, fontSize: '0.85rem', fontFamily: 'monospace' }}>
                            {svc.name}
                          </Typography>
                          <Typography sx={{ fontSize: '0.7rem', color: 'text.secondary' }}>
                            {svc.enabled ? 'enabled' : 'disabled'}
                            {svc.pid > 0 && ` · PID ${svc.pid}`}
                            {svc.uptime_seconds > 0 && ` · ${formatUptime(svc.uptime_seconds)}`}
                          </Typography>
                        </Box>
                      </Paper>
                    </Grid>
                  ))}
                </Grid>
              </CardContent>
            </Card>
          ))}
        </Box>
      )}

      {/* Network Tab */}
      {tab === 2 && networkData && (
        <Box>
          <Grid container spacing={2} sx={{ mb: 3 }}>
            <Grid size={{ xs: 12, sm: 4 }}>
              <StatCard label="Established" value={networkData.established_total} color="success" icon={<Fingerprint />} />
            </Grid>
            <Grid size={{ xs: 12, sm: 4 }}>
              <StatCard label="Time-Wait" value={networkData.time_wait_total} color="warning" icon={<AccessTime />} />
            </Grid>
            <Grid size={{ xs: 12, sm: 4 }}>
              <StatCard label="Listening Ports" value={networkData.listening_ports.length} color="primary" icon={<Lan />} />
            </Grid>
          </Grid>

          <Card sx={{ mb: 3 }}>
            <CardContent>
              <Typography variant="h6" sx={{ fontWeight: 700, mb: 2 }}>Listening Ports</Typography>
              {networkData.listening_ports.length > 0 ? (
                <TableContainer>
                  <Table size="small">
                    <TableHead>
                      <TableRow>
                        <TableCell>Port</TableCell>
                        <TableCell>Address</TableCell>
                        <TableCell>Process</TableCell>
                        <TableCell>PID</TableCell>
                      </TableRow>
                    </TableHead>
                    <TableBody>
                      {networkData.listening_ports.map((p, i) => (
                        <TableRow key={i}>
                          <TableCell>
                            <Chip label={p.port} size="small" variant="outlined" />
                          </TableCell>
                          <TableCell sx={{ fontFamily: 'monospace' }}>{p.address}</TableCell>
                          <TableCell sx={{ fontFamily: 'monospace' }}>{p.process}</TableCell>
                          <TableCell>{p.pid > 0 ? p.pid : '—'}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </TableContainer>
              ) : (
                <Alert severity="info">No listening ports detected.</Alert>
              )}
            </CardContent>
          </Card>

          {networkData.top_remote_ips.length > 0 && (
            <Card sx={{ mb: 3 }}>
              <CardContent>
                <Typography variant="h6" sx={{ fontWeight: 700, mb: 2 }}>Top Remote IPs</Typography>
                <TableContainer>
                  <Table size="small">
                    <TableHead>
                      <TableRow>
                        <TableCell>IP Address</TableCell>
                        <TableCell align="right">Connections</TableCell>
                      </TableRow>
                    </TableHead>
                    <TableBody>
                      {networkData.top_remote_ips.map((ip, i) => (
                        <TableRow key={i}>
                          <TableCell sx={{ fontFamily: 'monospace' }}>{ip.ip}</TableCell>
                          <TableCell align="right">
                            <Chip label={ip.connections} size="small" />
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </TableContainer>
              </CardContent>
            </Card>
          )}

          {networkData.connection_states.length > 0 && (
            <Card>
              <CardContent>
                <Typography variant="h6" sx={{ fontWeight: 700, mb: 2 }}>Connection States</Typography>
                <Grid container spacing={1}>
                  {networkData.connection_states.map((s, i) => (
                    <Grid size={{ xs: 6, sm: 4, md: 2 }} key={i}>
                      <Paper variant="outlined" sx={{ p: 1.5, textAlign: 'center' }}>
                        <Typography sx={{ fontWeight: 700, fontSize: '1.2rem' }}>{s.count}</Typography>
                        <Typography sx={{ fontSize: '0.7rem', color: 'text.secondary', fontFamily: 'monospace' }}>
                          {s.state}
                        </Typography>
                      </Paper>
                    </Grid>
                  ))}
                </Grid>
              </CardContent>
            </Card>
          )}
        </Box>
      )}

      {/* Firewall Tab */}
      {tab === 3 && csfData && (
        <Box>
          {!csfData.csf_active && (
            <Alert severity="error" sx={{ mb: 2 }} icon={<Shield />}>
              CSF firewall is not active!
            </Alert>
          )}

          {csfData.testing_mode && (
            <Alert severity="warning" sx={{ mb: 2 }} icon={<Shield />}>
              CSF is in TESTING MODE - firewall rules will expire automatically.
              <Button
                size="small"
                color="warning"
                onClick={() => handleCsfAction('disable_testing')}
                sx={{ ml: 1 }}
                disabled={actionLoading}
              >
                Disable Testing Mode
              </Button>
            </Alert>
          )}

          <Grid container spacing={2} sx={{ mb: 3 }}>
            <Grid size={{ xs: 12, sm: 3 }}>
              <StatCard
                label="CSF Status"
                value={csfData.csf_active ? 'Active' : 'Inactive'}
                color={csfData.csf_active ? 'success' : 'error'}
                icon={<Shield />}
              />
            </Grid>
            <Grid size={{ xs: 12, sm: 3 }}>
              <StatCard
                label="Denied IPs"
                value={csfData.stats.denied_ips}
                color="error"
                icon={<Lock />}
              />
            </Grid>
            <Grid size={{ xs: 12, sm: 3 }}>
              <StatCard
                label="Allowed IPs"
                value={csfData.stats.allowed_ips}
                color="success"
                icon={<LockOpen />}
              />
            </Grid>
            <Grid size={{ xs: 12, sm: 3 }}>
              <StatCard
                label="iptables Rules"
                value={csfData.stats.iptables_rules}
                color="primary"
                icon={<Shield />}
              />
            </Grid>
          </Grid>

          <Card sx={{ mb: 3 }}>
            <CardContent>
              <Typography variant="h6" sx={{ fontWeight: 700, mb: 2 }}>IP Management</Typography>
              <Box sx={{ display: 'flex', gap: 1, mb: 2, flexWrap: 'wrap' }}>
                <TextField
                  size="small"
                  placeholder="Enter IP address"
                  value={ipInput}
                  onChange={(e) => setIpInput(e.target.value)}
                  sx={{ minWidth: 200 }}
                  slotProps={{
                    input: {
                      startAdornment: (
                        <InputAdornment position="start">
                          <Shield fontSize="small" color="action" />
                        </InputAdornment>
                      ),
                    }
                  }}
                />
                <Button
                  startIcon={<Block />}
                  variant="contained"
                  size="small"
                  color="error"
                  onClick={() => handleCsfAction('deny', ipInput)}
                  disabled={actionLoading || !ipInput}
                >
                  Block
                </Button>
                <Button
                  startIcon={<LockOpen />}
                  variant="contained"
                  size="small"
                  color="success"
                  onClick={() => handleCsfAction('allow', ipInput)}
                  disabled={actionLoading || !ipInput}
                >
                  Allow
                </Button>
                <Button
                  startIcon={<LockOpen />}
                  variant="outlined"
                  size="small"
                  onClick={() => handleCsfAction('unblock', ipInput)}
                  disabled={actionLoading || !ipInput}
                >
                  Unblock
                </Button>
                <Button
                  startIcon={<Refresh />}
                  variant="outlined"
                  size="small"
                  onClick={() => handleCsfAction('restart')}
                  disabled={actionLoading}
                >
                  Restart CSF
                </Button>
              </Box>
            </CardContent>
          </Card>

          {csfData.top_failed_ssh_ips.length > 0 && (
            <Card sx={{ mb: 3 }}>
              <CardContent>
                <Typography variant="h6" sx={{ fontWeight: 700, mb: 2, color: 'error.main' }}>
                  Top Failed SSH IPs
                </Typography>
                <TableContainer>
                  <Table size="small">
                    <TableHead>
                      <TableRow>
                        <TableCell>IP Address</TableCell>
                        <TableCell align="right">Failed Attempts</TableCell>
                        <TableCell>Actions</TableCell>
                      </TableRow>
                    </TableHead>
                    <TableBody>
                      {csfData.top_failed_ssh_ips.map((ip, i) => (
                        <TableRow key={i}>
                          <TableCell sx={{ fontFamily: 'monospace' }}>{ip.ip}</TableCell>
                          <TableCell align="right">
                            <Chip label={ip.attempts} size="small" color="error" variant="outlined" />
                          </TableCell>
                          <TableCell>
                            <Tooltip title="Block this IP">
                              <IconButton
                                size="small"
                                color="error"
                                onClick={() => handleCsfAction('deny', ip.ip)}
                                disabled={actionLoading}
                              >
                                <Block fontSize="small" />
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
          )}

          <Grid container spacing={2}>
            {csfData.recent_denied.length > 0 && (
              <Grid size={{ xs: 12, md: 6 }}>
                <Card>
                  <CardContent>
                    <Typography variant="h6" sx={{ fontWeight: 700, mb: 2, color: 'error.main' }}>
                      Recently Denied IPs
                    </Typography>
                    <TableContainer>
                      <Table size="small">
                        <TableHead>
                          <TableRow>
                            <TableCell>IP</TableCell>
                            <TableCell>Reason</TableCell>
                          </TableRow>
                        </TableHead>
                        <TableBody>
                          {csfData.recent_denied.slice(0, 10).map((entry, i) => (
                            <TableRow key={i}>
                              <TableCell sx={{ fontFamily: 'monospace' }}>{entry.ip}</TableCell>
                              <TableCell sx={{ fontSize: '0.75rem' }}>{entry.reason}</TableCell>
                            </TableRow>
                          ))}
                        </TableBody>
                      </Table>
                    </TableContainer>
                  </CardContent>
                </Card>
              </Grid>
            )}

            {csfData.recent_allowed.length > 0 && (
              <Grid size={{ xs: 12, md: 6 }}>
                <Card>
                  <CardContent>
                    <Typography variant="h6" sx={{ fontWeight: 700, mb: 2, color: 'success.main' }}>
                      Recently Allowed IPs
                    </Typography>
                    <TableContainer>
                      <Table size="small">
                        <TableHead>
                          <TableRow>
                            <TableCell>IP</TableCell>
                            <TableCell>Reason</TableCell>
                          </TableRow>
                        </TableHead>
                        <TableBody>
                          {csfData.recent_allowed.slice(0, 10).map((entry, i) => (
                            <TableRow key={i}>
                              <TableCell sx={{ fontFamily: 'monospace' }}>{entry.ip}</TableCell>
                              <TableCell sx={{ fontSize: '0.75rem' }}>{entry.reason}</TableCell>
                            </TableRow>
                          ))}
                        </TableBody>
                      </Table>
                    </TableContainer>
                  </CardContent>
                </Card>
              </Grid>
            )}
          </Grid>

          {csfData.recent_denied.length === 0 && csfData.recent_allowed.length === 0 && csfData.top_failed_ssh_ips.length === 0 && (
            <Alert severity="info">No firewall rules or failed SSH attempts detected.</Alert>
          )}
        </Box>
      )}

      {/* Confirmation Dialog */}
      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} maxWidth="xs" fullWidth>
        <DialogTitle>{dialogConfig?.title}</DialogTitle>
        <DialogContent>
          <Typography>{dialogConfig?.message}</Typography>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDialogOpen(false)} disabled={actionLoading}>
            Cancel
          </Button>
          <Button
            variant="contained"
            color="error"
            onClick={() => {
              setDialogOpen(false);
              dialogConfig?.action();
            }}
            disabled={actionLoading}
          >
            Confirm
          </Button>
        </DialogActions>
      </Dialog>

      {/* Snackbar */}
      <Snackbar
        open={snackbar.open}
        autoHideDuration={4000}
        onClose={() => setSnackbar(prev => ({ ...prev, open: false }))}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
      >
        <Alert
          severity={snackbar.severity}
          onClose={() => setSnackbar(prev => ({ ...prev, open: false }))}
          sx={{ width: '100%' }}
        >
          {snackbar.message}
        </Alert>
      </Snackbar>
    </Box>
  );
}

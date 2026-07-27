import {
  Box, Typography, Card, CardContent, Grid, TextField, Button,
  Switch, FormControlLabel, Divider, Chip, Alert, CircularProgress,
  Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Paper,
  IconButton, Tooltip, Dialog, DialogTitle, DialogContent, DialogActions,
  Select, MenuItem, FormControl, InputLabel, Tab, Tabs, Badge,
} from '@mui/material';
import {
  Email as EmailIcon, Send as SendIcon, Settings as SettingsIcon,
  History as HistoryIcon, Refresh as RefreshIcon, Delete as DeleteIcon,
  CheckCircle as OkIcon, Error as ErrIcon, Visibility as ViewIcon,

} from '@mui/icons-material';
import { useState, useEffect, useCallback } from 'react';
import {
  fetchEmailSettings, saveEmailSettings, testEmailSettings,
  fetchEmailLogs, fetchEmailLogStats,
  clearEmailLogs,
  type EmailSettings, type EmailLog, type EmailLogStats,
} from '../api/notifications';

// ─── helpers ────────────────────────────────────────────────────────────────
function TabPanel({ value, index, children }: { value: number; index: number; children: React.ReactNode }) {
  return <Box hidden={value !== index} sx={{ pt: 3 }}>{value === index && children}</Box>;
}

// ─── Main Component ──────────────────────────────────────────────────────────
export default function EmailNotificationsPage() {
  const [tab, setTab] = useState(0);

  // Settings state
  const [settings, setSettings] = useState<EmailSettings>({
    from_email: '', from_name: '', admin_email_1: '', admin_email_2: '',
    enabled: '1', smtp_host: '', smtp_port: '587', smtp_user: '',
    smtp_pass: '', smtp_encryption: 'tls',
  });
  const [settingsLoading, setSettingsLoading] = useState(true);
  const [settingsSaving, setSettingsSaving] = useState(false);
  const [settingsMsg, setSettingsMsg] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  // Test email state
  const [testEmail, setTestEmail] = useState('');
  const [testSending, setTestSending] = useState(false);
  const [testResult, setTestResult] = useState<{ success: boolean; message: string } | null>(null);
  const [testDialogOpen, setTestDialogOpen] = useState(false);

  // Logs state
  const [logs, setLogs] = useState<EmailLog[]>([]);
  const [logStats, setLogStats] = useState<EmailLogStats | null>(null);
  const [logsLoading, setLogsLoading] = useState(false);
  const [selectedLog, setSelectedLog] = useState<EmailLog | null>(null);
  const [logDetailOpen, setLogDetailOpen] = useState(false);
  const [clearConfirmOpen, setClearConfirmOpen] = useState(false);

  // ─── load settings ───────────────────────────────────────────────────────
  const loadSettings = useCallback(async () => {
    setSettingsLoading(true);
    try {
      const data = await fetchEmailSettings();
      setSettings(data);
    } catch (e: any) {
      setSettingsMsg({ type: 'error', text: e.message || 'Failed to load settings' });
    } finally {
      setSettingsLoading(false);
    }
  }, []);

  const loadLogs = useCallback(async () => {
    setLogsLoading(true);
    try {
      const [{ logs: l }, stats] = await Promise.all([
        fetchEmailLogs(100),
        fetchEmailLogStats(),
      ]);
      setLogs(l);
      setLogStats(stats);
    } catch {
      // silently fail
    } finally {
      setLogsLoading(false);
    }
  }, []);

  useEffect(() => { loadSettings(); }, [loadSettings]);
  useEffect(() => { if (tab === 1) loadLogs(); }, [tab, loadLogs]);

  // ─── save settings ───────────────────────────────────────────────────────
  const handleSave = async () => {
    setSettingsSaving(true);
    setSettingsMsg(null);
    try {
      await saveEmailSettings(settings);
      setSettingsMsg({ type: 'success', text: 'Email settings saved successfully.' });
    } catch (e: any) {
      setSettingsMsg({ type: 'error', text: e.message || 'Failed to save settings' });
    } finally {
      setSettingsSaving(false);
    }
  };

  // ─── test send ───────────────────────────────────────────────────────────
  const handleTest = async () => {
    if (!testEmail) return;
    setTestSending(true);
    setTestResult(null);
    try {
      const res = await testEmailSettings(testEmail);
      setTestResult({ success: true, message: res.message || 'Test email sent!' });
    } catch (e: any) {
      setTestResult({ success: false, message: e.message || 'Test send failed' });
    } finally {
      setTestSending(false);
    }
  };

  // ─── clear logs ──────────────────────────────────────────────────────────
  const handleClearLogs = async () => {
    try {
      await clearEmailLogs();
      setLogs([]);
      setLogStats(null);
      setClearConfirmOpen(false);
    } catch (e: any) {
      alert(e.message || 'Failed to clear logs');
    }
  };

  // ─── render ──────────────────────────────────────────────────────────────
  return (
    <Box>
      {/* Header */}
      <Box sx={{ mb: 4, display: 'flex', alignItems: 'center', gap: 2, flexWrap: 'wrap' }}>
        <EmailIcon sx={{ fontSize: 36, color: 'primary.main' }} />
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, lineHeight: 1.1 }}>Email Notifications</Typography>
          <Typography variant="body2" color="text.secondary">SMTP configuration, template delivery, and audit logs</Typography>
        </Box>
        <Box sx={{ ml: 'auto', display: 'flex', gap: 1, alignItems: 'center' }}>
          <Chip
            label={settings.enabled === '1' ? 'Enabled' : 'Disabled'}
            color={settings.enabled === '1' ? 'success' : 'default'}
            size="small"
            icon={settings.enabled === '1' ? <OkIcon /> : <ErrIcon />}
          />
        </Box>
      </Box>

      {/* Tabs */}
      <Tabs value={tab} onChange={(_, v) => setTab(v)} sx={{ mb: 1, borderBottom: 1, borderColor: 'divider' }}>
        <Tab icon={<SettingsIcon />} iconPosition="start" label="SMTP Config" />
        <Tab icon={<HistoryIcon />} iconPosition="start" label={
          <Badge badgeContent={logStats?.total ?? 0} color="primary" max={999}>
            <Box sx={{ pr: 1 }}>Delivery Logs</Box>
          </Badge>
        } />
      </Tabs>

      {/* ── Tab 0: SMTP Config ── */}
      <TabPanel value={tab} index={0}>
        {settingsLoading ? (
          <Box sx={{ display: 'flex', justifyContent: 'center', py: 6 }}><CircularProgress /></Box>
        ) : (
          <Grid container spacing={3}>
            {/* Sender Identity */}
            <Grid size={12}>
              <Card>
                <CardContent>
                  <Typography variant="h6" sx={{ fontWeight: 700, mb: 2, display: 'flex', alignItems: 'center', gap: 1 }}>
                    <EmailIcon fontSize="small" /> Sender Identity
                  </Typography>
                  <Grid container spacing={2}>
                    <Grid size={{ xs: 12, sm: 6 }}>
                      <TextField fullWidth label="From Email" value={settings.from_email}
                        onChange={e => setSettings(s => ({ ...s, from_email: e.target.value }))}
                        placeholder="alerts@dashboard.technostationery.com" size="small" />
                    </Grid>
                    <Grid size={{ xs: 12, sm: 6 }}>
                      <TextField fullWidth label="From Name" value={settings.from_name}
                        onChange={e => setSettings(s => ({ ...s, from_name: e.target.value }))}
                        placeholder="TechnoStationery Dashboard" size="small" />
                    </Grid>
                    <Grid size={{ xs: 12, sm: 6 }}>
                      <TextField fullWidth label="Admin Email 1" value={settings.admin_email_1}
                        onChange={e => setSettings(s => ({ ...s, admin_email_1: e.target.value }))}
                        placeholder="admin@dashboard.technostationery.com" size="small" />
                    </Grid>
                    <Grid size={{ xs: 12, sm: 6 }}>
                      <TextField fullWidth label="Admin Email 2" value={settings.admin_email_2}
                        onChange={e => setSettings(s => ({ ...s, admin_email_2: e.target.value }))}
                        placeholder="webmaster@techno-dz.com" size="small" />
                    </Grid>
                  </Grid>
                </CardContent>
              </Card>
            </Grid>

            {/* SMTP Server */}
            <Grid size={12}>
              <Card>
                <CardContent>
                  <Typography variant="h6" sx={{ fontWeight: 700, mb: 2 }}>SMTP Server</Typography>
                  <Grid container spacing={2}>
                    <Grid size={{ xs: 12, sm: 6 }}>
                      <TextField fullWidth label="SMTP Host" value={settings.smtp_host}
                        onChange={e => setSettings(s => ({ ...s, smtp_host: e.target.value }))}
                        placeholder="smtp.gmail.com" size="small" />
                    </Grid>
                    <Grid size={{ xs: 12, sm: 3 }}>
                      <TextField fullWidth label="Port" value={settings.smtp_port}
                        onChange={e => setSettings(s => ({ ...s, smtp_port: e.target.value }))}
                        placeholder="587" size="small" type="number" />
                    </Grid>
                    <Grid size={{ xs: 12, sm: 3 }}>
                      <FormControl fullWidth size="small">
                        <InputLabel>Encryption</InputLabel>
                        <Select label="Encryption" value={settings.smtp_encryption}
                          onChange={e => setSettings(s => ({ ...s, smtp_encryption: e.target.value }))}>
                          <MenuItem value="tls">TLS (STARTTLS)</MenuItem>
                          <MenuItem value="ssl">SSL</MenuItem>
                          <MenuItem value="none">None</MenuItem>
                        </Select>
                      </FormControl>
                    </Grid>
                    <Grid size={{ xs: 12, sm: 6 }}>
                      <TextField fullWidth label="SMTP Username" value={settings.smtp_user}
                        onChange={e => setSettings(s => ({ ...s, smtp_user: e.target.value }))}
                        size="small" />
                    </Grid>
                    <Grid size={{ xs: 12, sm: 6 }}>
                      <TextField fullWidth label="SMTP Password"
                        type="password"
                        value={settings.smtp_pass}
                        onChange={e => setSettings(s => ({ ...s, smtp_pass: e.target.value }))}
                        placeholder={settings.smtp_pass_set ? '••••••••••• (set)' : 'Enter password'}
                        size="small"
                      />
                    </Grid>
                  </Grid>
                </CardContent>
              </Card>
            </Grid>

            {/* Toggle + Actions */}
            <Grid size={12}>
              <Card>
                <CardContent sx={{ display: 'flex', alignItems: 'center', flexWrap: 'wrap', gap: 2 }}>
                  <FormControlLabel
                    control={
                      <Switch checked={settings.enabled === '1'}
                        onChange={e => setSettings(s => ({ ...s, enabled: e.target.checked ? '1' : '0' }))}
                        color="success" />
                    }
                    label="Email notifications enabled"
                  />
                  <Box sx={{ ml: 'auto', display: 'flex', gap: 1.5, alignItems: 'center' }}>
                    <Button variant="outlined" startIcon={<SendIcon />}
                      onClick={() => setTestDialogOpen(true)}>
                      Send Test Email
                    </Button>
                    <Button variant="contained" startIcon={settingsSaving ? <CircularProgress size={16} /> : <SettingsIcon />}
                      onClick={handleSave} disabled={settingsSaving}>
                      Save Settings
                    </Button>
                  </Box>
                </CardContent>
              </Card>
            </Grid>

            {/* Status message */}
            {settingsMsg && (
              <Grid size={12}>
                <Alert severity={settingsMsg.type} onClose={() => setSettingsMsg(null)}>
                  {settingsMsg.text}
                </Alert>
              </Grid>
            )}
          </Grid>
        )}
      </TabPanel>

      {/* ── Tab 1: Delivery Logs ── */}
      <TabPanel value={tab} index={1}>
        {/* Stats Row */}
        {logStats && (
          <Grid container spacing={2} sx={{ mb: 3 }}>
            {[
              { label: 'Total Sent', val: logStats.total, color: 'primary.main' },
              { label: 'Successful', val: logStats.success, color: 'success.main' },
              { label: 'Failed', val: logStats.failed, color: 'error.main' },
              { label: 'Security Alerts', val: logStats.by_type?.security_alert ?? 0, color: 'warning.main' },
              { label: 'Login Alerts', val: logStats.by_type?.login_alert ?? 0, color: 'info.main' },
            ].map(stat => (
              <Grid size={{ xs: 6, sm: 4, md: 2 }} key={stat.label}>
                <Card>
                  <CardContent sx={{ py: '12px !important', textAlign: 'center' }}>
                    <Typography variant="h4" sx={{ fontWeight: 800, color: stat.color }}>{stat.val}</Typography>
                    <Typography variant="caption" color="text.secondary">{stat.label}</Typography>
                  </CardContent>
                </Card>
              </Grid>
            ))}
          </Grid>
        )}

        {/* Controls */}
        <Box sx={{ display: 'flex', gap: 1.5, mb: 2 }}>
          <Button startIcon={<RefreshIcon />} onClick={loadLogs} disabled={logsLoading} variant="outlined" size="small">
            Refresh
          </Button>
          <Button startIcon={<DeleteIcon />} color="error" variant="outlined" size="small"
            onClick={() => setClearConfirmOpen(true)}>
            Clear All Logs
          </Button>
        </Box>

        {/* Logs Table */}
        {logsLoading ? (
          <Box sx={{ display: 'flex', justifyContent: 'center', py: 6 }}><CircularProgress /></Box>
        ) : (
          <TableContainer component={Paper} variant="outlined">
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell>Time</TableCell>
                  <TableCell>Type</TableCell>
                  <TableCell>To</TableCell>
                  <TableCell>Subject</TableCell>
                  <TableCell align="center">Status</TableCell>
                  <TableCell align="center">Actions</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {logs.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={6} align="center" sx={{ py: 4, color: 'text.secondary' }}>
                      No email logs found
                    </TableCell>
                  </TableRow>
                )}
                {logs.map((log, i) => (
                  <TableRow key={i} hover>
                    <TableCell sx={{ whiteSpace: 'nowrap' }}>
                      <Typography variant="caption">{new Date(log.timestamp).toLocaleString()}</Typography>
                    </TableCell>
                    <TableCell>
                      <Chip label={log.type} size="small" variant="outlined" />
                    </TableCell>
                    <TableCell sx={{ maxWidth: 160, overflow: 'hidden', textOverflow: 'ellipsis' }}>
                      <Tooltip title={log.to}><span>{log.to}</span></Tooltip>
                    </TableCell>
                    <TableCell sx={{ maxWidth: 220, overflow: 'hidden', textOverflow: 'ellipsis' }}>
                      <Tooltip title={log.subject}><span>{log.subject}</span></Tooltip>
                    </TableCell>
                    <TableCell align="center">
                      <Chip
                        label={log.success ? 'Sent' : 'Failed'}
                        color={log.success ? 'success' : 'error'}
                        size="small"
                        icon={log.success ? <OkIcon /> : <ErrIcon />}
                      />
                    </TableCell>
                    <TableCell align="center">
                      <Tooltip title="View details">
                        <IconButton size="small" onClick={() => { setSelectedLog(log); setLogDetailOpen(true); }}>
                          <ViewIcon fontSize="small" />
                        </IconButton>
                      </Tooltip>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        )}
      </TabPanel>

      {/* ── Test Email Dialog ── */}
      <Dialog open={testDialogOpen} onClose={() => { setTestDialogOpen(false); setTestResult(null); }} maxWidth="sm" fullWidth>
        <DialogTitle>Send Test Email</DialogTitle>
        <DialogContent>
          <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
            Enter a recipient address to verify your SMTP settings are working correctly.
          </Typography>
          <TextField
            fullWidth label="Recipient Email" value={testEmail}
            onChange={e => setTestEmail(e.target.value)}
            placeholder="you@example.com" size="small" sx={{ mb: 2 }}
            onKeyDown={e => { if (e.key === 'Enter') handleTest(); }}
          />
          {testResult && (
            <Alert severity={testResult.success ? 'success' : 'error'} sx={{ mt: 1 }}>
              {testResult.message}
            </Alert>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => { setTestDialogOpen(false); setTestResult(null); }}>Cancel</Button>
          <Button variant="contained" onClick={handleTest}
            disabled={testSending || !testEmail}
            startIcon={testSending ? <CircularProgress size={16} /> : <SendIcon />}>
            Send Test
          </Button>
        </DialogActions>
      </Dialog>

      {/* ── Log Detail Dialog ── */}
      <Dialog open={logDetailOpen} onClose={() => setLogDetailOpen(false)} maxWidth="md" fullWidth>
        <DialogTitle>Email Log Detail</DialogTitle>
        <DialogContent>
          {selectedLog && (
            <Grid container spacing={2} sx={{ pt: 1 }}>
              {[
                ['Timestamp', new Date(selectedLog.timestamp).toLocaleString()],
                ['Type', selectedLog.type],
                ['To', selectedLog.to],
                ['Subject', selectedLog.subject],
                ['Status', selectedLog.success ? 'Sent successfully' : `Failed: ${selectedLog.error ?? 'unknown'}`],
              ].map(([label, val]) => (
                <Grid size={{ xs: 12, sm: 6 }} key={label as string}>
                  <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>{label}</Typography>
                  <Typography variant="body2" sx={{ wordBreak: 'break-all' }}>{val as string}</Typography>
                  <Divider sx={{ mt: 1 }} />
                </Grid>
              ))}
              {selectedLog.error && (
                <Grid size={12}>
                  <Alert severity="error">{selectedLog.error}</Alert>
                </Grid>
              )}
            </Grid>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setLogDetailOpen(false)}>Close</Button>
        </DialogActions>
      </Dialog>

      {/* ── Clear Confirm Dialog ── */}
      <Dialog open={clearConfirmOpen} onClose={() => setClearConfirmOpen(false)} maxWidth="xs">
        <DialogTitle>Clear All Email Logs?</DialogTitle>
        <DialogContent>
          <Typography>This will permanently delete all {logs.length} email log entries. This cannot be undone.</Typography>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setClearConfirmOpen(false)}>Cancel</Button>
          <Button color="error" variant="contained" onClick={handleClearLogs}>Clear All</Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
}

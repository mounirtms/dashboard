/**
 * NotificationsHubPage — unified notifications management center
 * Routes: /notifications/hub
 * Tabs:
 *   0. Overview    — channel health summary + recent activity
 *   1. Email       — SMTP config + delivery logs (delegates to EmailNotificationsPage content)
 *   2. Telegram    — bot status + per-alert toggles + recent activity
 *   3. Push/Web    — Webpushr device counts + subscription controls
 *   4. Preferences — per-user per-channel per-event matrix
 */
import {
  Box, Typography, Card, CardContent, Grid, Tab, Tabs,
  Chip, CircularProgress, Alert, Divider, Switch, FormControlLabel,
  Button, IconButton, Tooltip, List, ListItem, ListItemText,
  ListItemSecondaryAction, Table, TableBody, TableCell,
  TableContainer, TableHead, TableRow, Paper, TextField, Select,
  MenuItem, FormControl, InputLabel, Dialog, DialogTitle,
  DialogContent, DialogActions,
} from '@mui/material';
import {
  Email as EmailIcon,
  Telegram as TelegramIcon,
  NotificationsActive as PushIcon,
  Settings as SettingsIcon,
  Dashboard as OverviewIcon,
  CheckCircle as OkIcon,
  Error as ErrIcon,
  Warning as WarnIcon,
  Send as SendIcon,
  Refresh as RefreshIcon,
  Tune as TuneIcon,
} from '@mui/icons-material';
import { useState, useEffect, useCallback } from 'react';
import {
  fetchEmailSettings, fetchEmailLogStats, saveEmailSettings, testEmailSettings,
  fetchTelegramStats, sendTelegramTest,
  fetchPushStats,
  type EmailSettings, type TelegramStats, type PushStats,
} from '../api/notifications';
import {
  fetchNotificationPreferences, saveNotificationPreferences,
  type NotificationPreferences,
} from '../api/notificationPreferences';

// ─── helpers ─────────────────────────────────────────────────────────────────
function TabPanel({ value, index, children }: { value: number; index: number; children: React.ReactNode }) {
  return <Box hidden={value !== index} sx={{ pt: 3 }}>{value === index && children}</Box>;
}

// Event types for preference matrix
const EVENT_TYPES = [
  { key: 'security_alert',    label: 'Security Alert',       icon: '🔴' },
  { key: 'login_alert',       label: 'Login / Auth Alert',   icon: '🔑' },
  { key: 'task_assigned',     label: 'Task Assigned',        icon: '📋' },
  { key: 'task_approved',     label: 'Task Approved',        icon: '✅' },
  { key: 'cron_failure',      label: 'Cron Job Failure',     icon: '⏰' },
  { key: 'deploy_complete',   label: 'Deploy Complete',      icon: '🚀' },
  { key: 'ecomscan_done',     label: 'EcomScan Complete',    icon: '🛒' },
  { key: 'high_cpu',          label: 'High CPU / Memory',    icon: '💻' },
  { key: 'service_down',      label: 'Service Down',         icon: '🔻' },
  { key: 'backup_done',       label: 'Backup Complete',      icon: '💾' },
];

const CHANNELS = [
  { key: 'email',    label: 'Email',    icon: <EmailIcon fontSize="small" /> },
  { key: 'telegram', label: 'Telegram', icon: <TelegramIcon fontSize="small" /> },
  { key: 'push',     label: 'Push',     icon: <PushIcon fontSize="small" /> },
];

// ─── Status Card ─────────────────────────────────────────────────────────────
function ChannelStatusCard({
  icon, title, status, detail, href,
}: {
  icon: React.ReactNode; title: string; status: 'ok' | 'warn' | 'err' | 'loading';
  detail: string; href?: string;
}) {
  const color = { ok: 'success.main', warn: 'warning.main', err: 'error.main', loading: 'text.secondary' }[status];
  const StatusIcon = { ok: OkIcon, warn: WarnIcon, err: ErrIcon, loading: RefreshIcon }[status];
  return (
    <Card sx={{ height: '100%', cursor: href ? 'pointer' : 'default' }}
      onClick={() => href && (window.location.hash = href)}>
      <CardContent sx={{ display: 'flex', gap: 2, alignItems: 'flex-start' }}>
        <Box sx={{ mt: 0.5 }}>{icon}</Box>
        <Box sx={{ flex: 1 }}>
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
            <Typography variant="subtitle1" sx={{ fontWeight: 700 }}>{title}</Typography>
            <StatusIcon sx={{ fontSize: 16, color }} />
          </Box>
          <Typography variant="body2" color="text.secondary">{detail}</Typography>
        </Box>
      </CardContent>
    </Card>
  );
}

// ─── Main Component ───────────────────────────────────────────────────────────
export default function NotificationsHubPage() {
  const [tab, setTab] = useState(0);

  // Overview data
  const [emailStats, setEmailStats] = useState<any>(null);
  const [telegramStats, setTelegramStats] = useState<TelegramStats | null>(null);
  const [pushStats, setPushStats] = useState<PushStats | null>(null);
  const [overviewLoading, setOverviewLoading] = useState(true);

  // Email settings
  const [emailSettings, setEmailSettings] = useState<EmailSettings>({
    from_email: '', from_name: '', admin_email_1: '', admin_email_2: '',
    enabled: '1', smtp_host: '', smtp_port: '587',
    smtp_user: '', smtp_pass: '', smtp_encryption: 'tls',
  });
  const [emailSaving, setEmailSaving] = useState(false);
  const [emailMsg, setEmailMsg] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
  const [testEmail, setTestEmail] = useState('');
  const [testSending, setTestSending] = useState(false);
  const [testResult, setTestResult] = useState<{ ok: boolean; msg: string } | null>(null);
  const [testDlgOpen, setTestDlgOpen] = useState(false);
  const [emailLoading, setEmailLoading] = useState(false);

  // Telegram
  const [tgTesting, setTgTesting] = useState(false);
  const [tgTestResult, setTgTestResult] = useState<{ ok: boolean; msg: string } | null>(null);

  // Preferences
  const [prefs, setPrefs] = useState<NotificationPreferences | null>(null);
  const [prefsSaving, setPrefsSaving] = useState(false);
  const [prefsMsg, setPrefsMsg] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
  const [prefsLoading, setPrefsLoading] = useState(false);

  // ── load overview ─────────────────────────────────────────────────────────
  const loadOverview = useCallback(async () => {
    setOverviewLoading(true);
    try {
      const [es, ts, ps] = await Promise.allSettled([
        fetchEmailLogStats(),
        fetchTelegramStats(),
        fetchPushStats('dashboard'),
      ]);
      if (es.status === 'fulfilled') setEmailStats(es.value);
      if (ts.status === 'fulfilled') setTelegramStats(ts.value);
      if (ps.status === 'fulfilled') setPushStats(ps.value);
    } finally {
      setOverviewLoading(false);
    }
  }, []);

  const loadEmailSettings = useCallback(async () => {
    setEmailLoading(true);
    try {
      const d = await fetchEmailSettings();
      setEmailSettings(d);
    } catch (e: any) {
      setEmailMsg({ type: 'error', text: e.message });
    } finally {
      setEmailLoading(false);
    }
  }, []);

  const loadPrefs = useCallback(async () => {
    setPrefsLoading(true);
    try {
      const d = await fetchNotificationPreferences();
      setPrefs(d);
    } catch {
      // default
    } finally {
      setPrefsLoading(false);
    }
  }, []);

  useEffect(() => { loadOverview(); }, [loadOverview]);
  useEffect(() => { if (tab === 1) loadEmailSettings(); }, [tab, loadEmailSettings]);
  useEffect(() => { if (tab === 4) loadPrefs(); }, [tab, loadPrefs]);

  // ── save email settings ───────────────────────────────────────────────────
  const handleEmailSave = async () => {
    setEmailSaving(true); setEmailMsg(null);
    try {
      await saveEmailSettings(emailSettings);
      setEmailMsg({ type: 'success', text: 'Email settings saved.' });
    } catch (e: any) {
      setEmailMsg({ type: 'error', text: e.message });
    } finally {
      setEmailSaving(false);
    }
  };

  const handleTestSend = async () => {
    setTestSending(true); setTestResult(null);
    try {
      const r = await testEmailSettings(testEmail);
      setTestResult({ ok: true, msg: r.message || 'Test sent!' });
    } catch (e: any) {
      setTestResult({ ok: false, msg: e.message || 'Failed' });
    } finally {
      setTestSending(false);
    }
  };

  // ── telegram test ─────────────────────────────────────────────────────────
  const handleTgTest = async () => {
    setTgTesting(true); setTgTestResult(null);
    try {
      await sendTelegramTest();
      setTgTestResult({ ok: true, msg: 'Test message sent to Telegram!' });
    } catch (e: any) {
      setTgTestResult({ ok: false, msg: e.message });
    } finally {
      setTgTesting(false);
    }
  };

  // ── save preferences ──────────────────────────────────────────────────────
  const handlePrefToggle = (event: string, channel: string) => {
    if (!prefs) return;
    const key = `${event}_${channel}` as keyof NotificationPreferences;
    setPrefs(p => p ? { ...p, [key]: !p[key] } : p);
  };

  const handlePrefsSave = async () => {
    if (!prefs) return;
    setPrefsSaving(true); setPrefsMsg(null);
    try {
      await saveNotificationPreferences(prefs);
      setPrefsMsg({ type: 'success', text: 'Preferences saved.' });
    } catch (e: any) {
      setPrefsMsg({ type: 'error', text: e.message });
    } finally {
      setPrefsSaving(false);
    }
  };

  // ── render ────────────────────────────────────────────────────────────────
  return (
    <Box>
      {/* Header */}
      <Box sx={{ mb: 4 }}>
        <Typography variant="h4" sx={{ fontWeight: 800, mb: 0.5 }}>Notifications Hub</Typography>
        <Typography variant="body2" color="text.secondary">
          Manage all notification channels — email, Telegram, and push notifications
        </Typography>
      </Box>

      {/* Tabs */}
      <Tabs value={tab} onChange={(_, v) => setTab(v)} sx={{ mb: 1, borderBottom: 1, borderColor: 'divider' }}
        variant="scrollable" scrollButtons="auto">
        <Tab icon={<OverviewIcon />} iconPosition="start" label="Overview" />
        <Tab icon={<EmailIcon />} iconPosition="start" label="Email" />
        <Tab icon={<TelegramIcon />} iconPosition="start" label="Telegram" />
        <Tab icon={<PushIcon />} iconPosition="start" label="Push (Webpushr)" />
        <Tab icon={<TuneIcon />} iconPosition="start" label="My Preferences" />
      </Tabs>

      {/* ── TAB 0: Overview ── */}
      <TabPanel value={tab} index={0}>
        {overviewLoading ? (
          <Box sx={{ display: 'flex', justifyContent: 'center', py: 8 }}><CircularProgress /></Box>
        ) : (
          <Grid container spacing={3}>
            <Grid size={{ xs: 12, md: 4 }}>
              <ChannelStatusCard
                icon={<EmailIcon sx={{ color: 'primary.main' }} />}
                title="Email"
                status={emailStats ? (emailStats.failed > 0 ? 'warn' : 'ok') : 'err'}
                detail={emailStats
                  ? `${emailStats.success} sent · ${emailStats.failed} failed (total ${emailStats.total})`
                  : 'No data available'}
                href="/notifications/email"
              />
            </Grid>
            <Grid size={{ xs: 12, md: 4 }}>
              <ChannelStatusCard
                icon={<TelegramIcon sx={{ color: '#2AABEE' }} />}
                title="Telegram Bot"
                status={telegramStats?.webhook_status ? 'ok' : 'err'}
                detail={telegramStats
                  ? `${telegramStats.bot_username} · ${telegramStats.auth_count} auth'd users`
                  : 'Cannot reach Telegram API'}
                href="/notifications/telegram"
              />
            </Grid>
            <Grid size={{ xs: 12, md: 4 }}>
              <ChannelStatusCard
                icon={<PushIcon sx={{ color: 'warning.main' }} />}
                title="Push (Webpushr)"
                status={pushStats ? 'ok' : 'warn'}
                detail={pushStats
                  ? `${pushStats.subscribers ?? 0} subscribers`
                  : 'Push service data unavailable'}
                href="/notifications/push"
              />
            </Grid>

            {/* Quick links */}
            <Grid size={12}>
              <Card>
                <CardContent>
                  <Typography variant="h6" sx={{ fontWeight: 700, mb: 2 }}>Quick Actions</Typography>
                  <Box sx={{ display: 'flex', gap: 1.5, flexWrap: 'wrap' }}>
                    <Button variant="outlined" size="small" startIcon={<EmailIcon />}
                      onClick={() => setTab(1)}>Configure Email SMTP</Button>
                    <Button variant="outlined" size="small" startIcon={<TelegramIcon />}
                      onClick={() => setTab(2)}>Test Telegram Bot</Button>
                    <Button variant="outlined" size="small" startIcon={<TuneIcon />}
                      onClick={() => setTab(4)}>My Alert Preferences</Button>
                    <Button variant="outlined" size="small" startIcon={<RefreshIcon />}
                      onClick={loadOverview}>Refresh Status</Button>
                  </Box>
                </CardContent>
              </Card>
            </Grid>
          </Grid>
        )}
      </TabPanel>

      {/* ── TAB 1: Email Config ── */}
      <TabPanel value={tab} index={1}>
        {emailLoading ? (
          <Box sx={{ display: 'flex', justifyContent: 'center', py: 6 }}><CircularProgress /></Box>
        ) : (
          <Grid container spacing={3}>
            <Grid size={12}>
              <Card>
                <CardContent>
                  <Typography variant="h6" sx={{ fontWeight: 700, mb: 2 }}>Sender Identity</Typography>
                  <Grid container spacing={2}>
                    <Grid size={{ xs: 12, sm: 6 }}>
                      <TextField fullWidth size="small" label="From Email" value={emailSettings.from_email}
                        onChange={e => setEmailSettings(s => ({ ...s, from_email: e.target.value }))} />
                    </Grid>
                    <Grid size={{ xs: 12, sm: 6 }}>
                      <TextField fullWidth size="small" label="From Name" value={emailSettings.from_name}
                        onChange={e => setEmailSettings(s => ({ ...s, from_name: e.target.value }))} />
                    </Grid>
                    <Grid size={{ xs: 12, sm: 6 }}>
                      <TextField fullWidth size="small" label="Admin Email 1" value={emailSettings.admin_email_1}
                        onChange={e => setEmailSettings(s => ({ ...s, admin_email_1: e.target.value }))} />
                    </Grid>
                    <Grid size={{ xs: 12, sm: 6 }}>
                      <TextField fullWidth size="small" label="Admin Email 2" value={emailSettings.admin_email_2}
                        onChange={e => setEmailSettings(s => ({ ...s, admin_email_2: e.target.value }))} />
                    </Grid>
                  </Grid>
                </CardContent>
              </Card>
            </Grid>

            <Grid size={12}>
              <Card>
                <CardContent>
                  <Typography variant="h6" sx={{ fontWeight: 700, mb: 2 }}>SMTP Server</Typography>
                  <Grid container spacing={2}>
                    <Grid size={{ xs: 12, sm: 6 }}>
                      <TextField fullWidth size="small" label="Host" value={emailSettings.smtp_host}
                        onChange={e => setEmailSettings(s => ({ ...s, smtp_host: e.target.value }))} />
                    </Grid>
                    <Grid size={{ xs: 12, sm: 3 }}>
                      <TextField fullWidth size="small" label="Port" type="number" value={emailSettings.smtp_port}
                        onChange={e => setEmailSettings(s => ({ ...s, smtp_port: e.target.value }))} />
                    </Grid>
                    <Grid size={{ xs: 12, sm: 3 }}>
                      <FormControl fullWidth size="small">
                        <InputLabel>Encryption</InputLabel>
                        <Select label="Encryption" value={emailSettings.smtp_encryption}
                          onChange={e => setEmailSettings(s => ({ ...s, smtp_encryption: e.target.value }))}>
                          <MenuItem value="tls">TLS (STARTTLS)</MenuItem>
                          <MenuItem value="ssl">SSL</MenuItem>
                          <MenuItem value="none">None</MenuItem>
                        </Select>
                      </FormControl>
                    </Grid>
                    <Grid size={{ xs: 12, sm: 6 }}>
                      <TextField fullWidth size="small" label="Username" value={emailSettings.smtp_user}
                        onChange={e => setEmailSettings(s => ({ ...s, smtp_user: e.target.value }))} />
                    </Grid>
                    <Grid size={{ xs: 12, sm: 6 }}>
                      <TextField fullWidth size="small" label="Password" type="password"
                        value={emailSettings.smtp_pass}
                        placeholder={emailSettings.smtp_pass_set ? '(password set)' : ''}
                        onChange={e => setEmailSettings(s => ({ ...s, smtp_pass: e.target.value }))}
                      />
                    </Grid>
                  </Grid>
                </CardContent>
              </Card>
            </Grid>

            <Grid size={12}>
              <Card>
                <CardContent sx={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 2 }}>
                  <FormControlLabel
                    control={<Switch checked={emailSettings.enabled === '1'}
                      onChange={e => setEmailSettings(s => ({ ...s, enabled: e.target.checked ? '1' : '0' }))} color="success" />}
                    label="Email notifications enabled"
                  />
                  <Box sx={{ ml: 'auto', display: 'flex', gap: 1.5 }}>
                    <Button variant="outlined" size="small" startIcon={<SendIcon />}
                      onClick={() => setTestDlgOpen(true)}>Test</Button>
                    <Button variant="contained" size="small"
                      startIcon={emailSaving ? <CircularProgress size={14} /> : <SettingsIcon />}
                      onClick={handleEmailSave} disabled={emailSaving}>
                      Save
                    </Button>
                  </Box>
                </CardContent>
              </Card>
            </Grid>
            {emailMsg && (
              <Grid size={12}>
                <Alert severity={emailMsg.type} onClose={() => setEmailMsg(null)}>{emailMsg.text}</Alert>
              </Grid>
            )}
          </Grid>
        )}
      </TabPanel>

      {/* ── TAB 2: Telegram ── */}
      <TabPanel value={tab} index={2}>
        <Grid container spacing={3}>
          {/* Bot Status */}
          <Grid size={{ xs: 12, md: 6 }}>
            <Card>
              <CardContent>
                <Typography variant="h6" sx={{ fontWeight: 700, mb: 2, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <TelegramIcon sx={{ color: '#2AABEE' }} /> Bot Status
                </Typography>
                {telegramStats ? (
                  <List dense>
                    <ListItem>
                      <ListItemText primary="Bot Username" secondary={telegramStats.bot_username} />
                      <ListItemSecondaryAction>
                        <Chip label={telegramStats.webhook_status ? 'Webhook Active' : 'No Webhook'}
                          color={telegramStats.webhook_status ? 'success' : 'error'} size="small" />
                      </ListItemSecondaryAction>
                    </ListItem>
                    <ListItem>
                      <ListItemText primary="Authorized Users" secondary={`${telegramStats.auth_count} users registered`} />
                    </ListItem>
                    <ListItem>
                      <ListItemText primary="Alerts Status" />
                      <ListItemSecondaryAction>
                        <Chip label={telegramStats.alerts_enabled ? 'Enabled' : 'Paused'}
                          color={telegramStats.alerts_enabled ? 'success' : 'warning'} size="small" />
                      </ListItemSecondaryAction>
                    </ListItem>
                    {telegramStats.webhook_url && (
                      <ListItem>
                        <ListItemText primary="Webhook URL"
                          secondary={<Typography variant="caption" sx={{ wordBreak: 'break-all' }}>{telegramStats.webhook_url}</Typography>} />
                      </ListItem>
                    )}
                  </List>
                ) : (
                  <Alert severity="warning">Could not load Telegram stats</Alert>
                )}
              </CardContent>
            </Card>
          </Grid>

          {/* Quick test */}
          <Grid size={{ xs: 12, md: 6 }}>
            <Card>
              <CardContent>
                <Typography variant="h6" sx={{ fontWeight: 700, mb: 2 }}>Send Test Message</Typography>
                <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
                  Send a test notification to all authorized Telegram users to verify the bot is working.
                </Typography>
                <Button variant="contained" startIcon={tgTesting ? <CircularProgress size={16} /> : <SendIcon />}
                  onClick={handleTgTest} disabled={tgTesting} fullWidth sx={{ mb: 1.5 }}>
                  Send Test via Telegram
                </Button>
                {tgTestResult && (
                  <Alert severity={tgTestResult.ok ? 'success' : 'error'} onClose={() => setTgTestResult(null)}>
                    {tgTestResult.msg}
                  </Alert>
                )}
              </CardContent>
            </Card>
          </Grid>

          {/* Alert type toggles */}
          <Grid size={12}>
            <Card>
              <CardContent>
                <Typography variant="h6" sx={{ fontWeight: 700, mb: 1 }}>
                  Per-Alert Type Configuration
                </Typography>
                <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
                  Fine-grained control is available in the full Telegram page.
                </Typography>
                <Button variant="outlined" startIcon={<TelegramIcon />}
                  onClick={() => { window.location.hash = '/notifications/telegram'; }}>
                  Open Full Telegram Management
                </Button>
              </CardContent>
            </Card>
          </Grid>
        </Grid>
      </TabPanel>

      {/* ── TAB 3: Push ── */}
      <TabPanel value={tab} index={3}>
        <Grid container spacing={3}>
          <Grid size={{ xs: 12, md: 6 }}>
            <Card>
              <CardContent>
                <Typography variant="h6" sx={{ fontWeight: 700, mb: 2, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <PushIcon sx={{ color: 'warning.main' }} /> Webpushr Status
                </Typography>
                {pushStats ? (
                  <List dense>
                    <ListItem>
                      <ListItemText primary="Total Subscribers" secondary={pushStats.subscribers ?? 0} />
                    </ListItem>
                    {pushStats.env_status && Object.keys(pushStats.env_status).length > 0 && (
                      <ListItem>
                        <ListItemText primary="Environments" secondary={Object.keys(pushStats.env_status).join(', ')} />
                      </ListItem>
                    )}
                  </List>
                ) : (
                  <Alert severity="warning">Push stats unavailable — check Webpushr API key</Alert>
                )}
              </CardContent>
            </Card>
          </Grid>
          <Grid size={{ xs: 12, md: 6 }}>
            <Card>
              <CardContent>
                <Typography variant="h6" sx={{ fontWeight: 700, mb: 2 }}>Advanced Management</Typography>
                <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
                  Send notifications, manage segments, view analytics, and manage subscribers in the full push page.
                </Typography>
                <Button variant="outlined" startIcon={<PushIcon />}
                  onClick={() => { window.location.hash = '/notifications/push'; }}>
                  Open Push Notifications Manager
                </Button>
              </CardContent>
            </Card>
          </Grid>
        </Grid>
      </TabPanel>

      {/* ── TAB 4: Preferences ── */}
      <TabPanel value={tab} index={4}>
        {prefsLoading ? (
          <Box sx={{ display: 'flex', justifyContent: 'center', py: 6 }}><CircularProgress /></Box>
        ) : (
          <Grid container spacing={3}>
            <Grid size={12}>
              <Card>
                <CardContent>
                  <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', mb: 2 }}>
                    <Box>
                      <Typography variant="h6" sx={{ fontWeight: 700 }}>My Notification Preferences</Typography>
                      <Typography variant="body2" color="text.secondary">
                        Choose which event types you receive on each channel
                      </Typography>
                    </Box>
                    <Button variant="contained" size="small"
                      startIcon={prefsSaving ? <CircularProgress size={14} /> : <SettingsIcon />}
                      onClick={handlePrefsSave} disabled={prefsSaving || !prefs}>
                      Save
                    </Button>
                  </Box>

                  {prefsMsg && (
                    <Alert severity={prefsMsg.type} onClose={() => setPrefsMsg(null)} sx={{ mb: 2 }}>
                      {prefsMsg.text}
                    </Alert>
                  )}

                  {!prefs ? (
                    <Alert severity="info">Could not load preferences. Check API connectivity.</Alert>
                  ) : (
                    <TableContainer component={Paper} variant="outlined">
                      <Table size="small">
                        <TableHead>
                          <TableRow>
                            <TableCell sx={{ fontWeight: 700, minWidth: 200 }}>Event Type</TableCell>
                            {CHANNELS.map(ch => (
                              <TableCell key={ch.key} align="center" sx={{ fontWeight: 700, minWidth: 100 }}>
                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5, justifyContent: 'center' }}>
                                  {ch.icon} {ch.label}
                                </Box>
                              </TableCell>
                            ))}
                          </TableRow>
                        </TableHead>
                        <TableBody>
                          {EVENT_TYPES.map(evt => (
                            <TableRow key={evt.key} hover>
                              <TableCell>
                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                  <span style={{ fontSize: 16 }}>{evt.icon}</span>
                                  <Typography variant="body2">{evt.label}</Typography>
                                </Box>
                              </TableCell>
                              {CHANNELS.map(ch => {
                                const prefKey = `${evt.key}_${ch.key}` as keyof NotificationPreferences;
                                const val = prefs[prefKey];
                                return (
                                  <TableCell key={ch.key} align="center">
                                    <Switch
                                      size="small"
                                      checked={val === true || val === 1}
                                      onChange={() => handlePrefToggle(evt.key, ch.key)}
                                      color="primary"
                                    />
                                  </TableCell>
                                );
                              })}
                            </TableRow>
                          ))}
                        </TableBody>
                      </Table>
                    </TableContainer>
                  )}
                </CardContent>
              </Card>
            </Grid>
          </Grid>
        )}
      </TabPanel>

      {/* ── Test Email Dialog ── */}
      <Dialog open={testDlgOpen} onClose={() => { setTestDlgOpen(false); setTestResult(null); }} maxWidth="sm" fullWidth>
        <DialogTitle>Send Test Email</DialogTitle>
        <DialogContent>
          <TextField fullWidth label="Recipient" value={testEmail}
            onChange={e => setTestEmail(e.target.value)} size="small" sx={{ mt: 1, mb: 2 }}
            onKeyDown={e => { if (e.key === 'Enter') handleTestSend(); }} />
          {testResult && (
            <Alert severity={testResult.ok ? 'success' : 'error'}>{testResult.msg}</Alert>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => { setTestDlgOpen(false); setTestResult(null); }}>Cancel</Button>
          <Button variant="contained" onClick={handleTestSend}
            disabled={testSending || !testEmail}
            startIcon={testSending ? <CircularProgress size={14} /> : <SendIcon />}>
            Send
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
}

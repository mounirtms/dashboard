import {
  Box, Typography, Grid, Card, CardContent, Button, Divider, List,
  ListItem, ListItemText, Chip, Snackbar, Alert, CircularProgress,
  Switch, FormControlLabel, Tooltip, IconButton, Tab, Tabs, Paper,
  TextField, Collapse,
} from '@mui/material';
import {
  SmartToy, Send, Settings, Security, History, Bolt, Refresh,
  CheckCircle, Error as ErrIcon, Warning as WarnIcon,
  Tune as TuneIcon, NotificationsActive, ContentCopy, Terminal,
  ExpandMore, ExpandLess, FiberManualRecord,
} from '@mui/icons-material';
import { useState, useEffect, useCallback, useRef } from 'react';
import {
  fetchTelegramStats, fetchTelegramRecentLogs, sendTelegramTest,
  type TelegramStats, type TelegramLog,
} from '../api/notifications';
import apiClient from '../api/client';
import LoadingState from '../components/common/LoadingState';
import StatusBadge from '../components/common/StatusBadge';

// ── Types ─────────────────────────────────────────────────────────────────────
interface AlertType {
  key: string;
  label: string;
  emoji: string;
  enabled: boolean;
}

interface WebhookInfo {
  url: string;
  pending_updates: number;
  last_error: string;
  last_error_date?: number;
  has_custom_cert?: boolean;
  ip_address?: string;
  max_connections?: number;
}

interface CommandResult {
  cmd: string;
  success: boolean;
  message: string;
  timestamp: string;
}

function TabPanel({ value, index, children }: { value: number; index: number; children: React.ReactNode }) {
  return <Box hidden={value !== index} sx={{ pt: 3 }}>{value === index && children}</Box>;
}

// ── Quick command groups ───────────────────────────────────────────────────────
const QUICK_COMMANDS = [
  { cmd: '/status',      label: 'Status',       color: '#3b82f6', group: 'System' },
  { cmd: '/services',    label: 'Services',     color: '#3b82f6', group: 'System' },
  { cmd: '/load',        label: 'Load',         color: '#3b82f6', group: 'System' },
  { cmd: '/processes',   label: 'Processes',    color: '#3b82f6', group: 'System' },
  { cmd: '/orders',      label: 'Orders',       color: '#10b981', group: 'Magento' },
  { cmd: '/online',      label: 'Online Users', color: '#10b981', group: 'Magento' },
  { cmd: '/cache:flush', label: 'Cache Flush',  color: '#f59e0b', group: 'Cache' },
  { cmd: '/db:size',     label: 'DB Size',      color: '#8b5cf6', group: 'Database' },
  { cmd: '/queues',      label: 'Queues',       color: '#06b6d4', group: 'Queue' },
  { cmd: '/logs:summary','label': 'Log Summary', color: '#ef4444', group: 'Logs' },
  { cmd: '/ai:help',     label: 'AI Help',      color: '#ec4899', group: 'AI' },
  { cmd: '/help',        label: 'Full Help',    color: '#64748b', group: 'Admin' },
];

// ── Component ─────────────────────────────────────────────────────────────────
export default function TelegramPage() {
  const [tab, setTab] = useState(0);

  // Core stats
  const [stats, setStats] = useState<TelegramStats | null>(null);
  const [loading, setLoading] = useState(true);

  // Logs
  const [recentLogs, setRecentLogs] = useState<TelegramLog[]>([]);
  const [logsLoading, setLogsLoading] = useState(false);
  const autoRefreshRef = useRef<ReturnType<typeof setInterval> | null>(null);

  // Test send
  const [sending, setSending] = useState(false);

  // Quick commands — per-command loading + result history
  const [cmdLoading, setCmdLoading] = useState<string | null>(null);
  const [cmdResults, setCmdResults] = useState<CommandResult[]>([]);
  const [showCmdHistory, setShowCmdHistory] = useState(false);

  // Custom command field
  const [customCmd, setCustomCmd] = useState('');

  // Alert type toggles
  const [alertTypes, setAlertTypes] = useState<AlertType[]>([]);
  const [alertTypesLoading, setAlertTypesLoading] = useState(false);
  const [alertTypesSaving, setAlertTypesSaving] = useState(false);

  // Webhook
  const [webhookInfo, setWebhookInfo] = useState<WebhookInfo | null>(null);
  const [webhookLoading, setWebhookLoading] = useState(false);

  // Notifications
  const [notify, setNotify] = useState({ open: false, message: '', severity: 'success' as 'success' | 'error' | 'info' | 'warning' });

  const showNotify = (message: string, severity: typeof notify.severity = 'success') =>
    setNotify({ open: true, message, severity });

  // ── Loaders ────────────────────────────────────────────────────────────────
  const loadStats = useCallback(() => {
    setLoading(true);
    fetchTelegramStats()
      .then(setStats)
      .catch(() => setStats({
        bot_username: '@ServerNotif205bot',
        webhook_status: false,
        auth_count: 1,
        alerts_enabled: true,
      }))
      .finally(() => setLoading(false));
  }, []);

  const loadLogs = useCallback(() => {
    setLogsLoading(true);
    fetchTelegramRecentLogs()
      .then(setRecentLogs)
      .catch(() => setRecentLogs([]))
      .finally(() => setLogsLoading(false));
  }, []);

  const loadAlertTypes = useCallback(async () => {
    setAlertTypesLoading(true);
    try {
      const { data } = await apiClient.get('/api/telegram_settings.php?action=get_alert_types');
      if (data.alert_types) setAlertTypes(data.alert_types);
    } catch {
      // silently handle — will show empty state
    } finally {
      setAlertTypesLoading(false);
    }
  }, []);

  const loadWebhook = useCallback(async () => {
    setWebhookLoading(true);
    try {
      const { data } = await apiClient.get('/api/telegram_settings.php?action=get_webhook');
      if (data.webhook) setWebhookInfo(data.webhook);
    } catch {
      // silently handle
    } finally {
      setWebhookLoading(false);
    }
  }, []);

  useEffect(() => {
    loadStats();
    loadLogs();
  }, [loadStats, loadLogs]);

  // Auto-refresh logs every 15s when on the Activity tab
  useEffect(() => {
    if (tab === 0) {
      autoRefreshRef.current = setInterval(() => loadLogs(), 15000);
    } else {
      if (autoRefreshRef.current) clearInterval(autoRefreshRef.current);
    }
    return () => { if (autoRefreshRef.current) clearInterval(autoRefreshRef.current); };
  }, [tab, loadLogs]);

  // Load alert types when Alert Types tab is opened (tab index 1)
  useEffect(() => {
    if (tab === 1) loadAlertTypes();
    if (tab === 2) loadWebhook();
  }, [tab, loadAlertTypes, loadWebhook]);

  // ── Actions ────────────────────────────────────────────────────────────────
  const handleTest = async () => {
    setSending(true);
    try {
      const result = await sendTelegramTest();
      if (result?.success) {
        showNotify('✅ Test alert sent — check Telegram!');
        loadLogs();
      } else {
        showNotify('Failed: ' + (result?.message || 'Unknown error'), 'error');
      }
    } catch (e: any) {
      showNotify('Failed: ' + (e.response?.data?.message || e.message), 'error');
    } finally {
      setSending(false);
    }
  };

  const handleQuickCommand = async (cmd: string) => {
    const target = cmd.trim() || customCmd.trim();
    if (!target) return;
    setCmdLoading(target);
    try {
      const { data } = await apiClient.post('/api/monitor.php?action=telegram_action', { command: target });
      const success = !!data?.success;
      const message = data?.message || (success ? 'Dispatched' : 'Failed');
      setCmdResults(prev => [
        { cmd: target, success, message, timestamp: new Date().toLocaleTimeString() },
        ...prev.slice(0, 19),
      ]);
      if (success) {
        showNotify(`✅ "${target}" dispatched — reply sent to Telegram`);
        loadLogs();
        if (target === customCmd) setCustomCmd('');
      } else {
        showNotify(`⚠️ "${target}": ${message}`, 'warning');
      }
    } catch (e: any) {
      const msg = e.response?.data?.message || e.message || 'Network error';
      setCmdResults(prev => [
        { cmd: target, success: false, message: msg, timestamp: new Date().toLocaleTimeString() },
        ...prev.slice(0, 19),
      ]);
      showNotify(`Failed: ${msg}`, 'error');
    } finally {
      setCmdLoading(null);
    }
  };

  const handleToggleAlertType = (key: string, enabled: boolean) => {
    setAlertTypes(prev => prev.map(a => a.key === key ? { ...a, enabled } : a));
  };

  const handleSaveAlertTypes = async () => {
    setAlertTypesSaving(true);
    try {
      const { data } = await apiClient.post('/api/telegram_settings.php', {
        action: 'save_alert_types',
        alert_types: alertTypes,
      });
      if (data.success) {
        showNotify('Alert type settings saved');
      } else {
        showNotify(data.error || 'Failed to save', 'error');
      }
    } catch (e: any) {
      showNotify(e.message || 'Failed to save', 'error');
    } finally {
      setAlertTypesSaving(false);
    }
  };

  // ── Loading state ──────────────────────────────────────────────────────────
  if (loading) return <LoadingState message="Loading Telegram status..." />;

  const enabledCount = alertTypes.filter(a => a.enabled).length;
  const totalCount   = alertTypes.length;
  const cmdGroups = [...new Set(QUICK_COMMANDS.map(c => c.group))];

  return (
    <Box>
      {/* ─── Header ─── */}
      <Box sx={{ mb: 3, display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', flexWrap: 'wrap', gap: 2 }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Telegram Bot Control
          </Typography>
          <Typography variant="body2" color="text.secondary">
            Server monitoring and alert dispatch via {stats?.bot_username ?? '@ServerNotif205bot'}
          </Typography>
        </Box>
        <Box sx={{ display: 'flex', gap: 1, alignItems: 'center' }}>
          <StatusBadge
            label={stats?.webhook_status ? 'Webhook Active' : 'Webhook Offline'}
            color={stats?.webhook_status ? 'success' : 'error'}
          />
          <Tooltip title="Refresh">
            <IconButton size="small" onClick={() => { loadStats(); loadLogs(); }}>
              <Refresh fontSize="small" />
            </IconButton>
          </Tooltip>
        </Box>
      </Box>

      {/* ─── Stats Row ─── */}
      <Grid container spacing={2} sx={{ mb: 3 }}>
        {[
          { label: 'Bot',                value: stats?.bot_username ?? '—',                       icon: <SmartToy sx={{ color: '#2AABEE' }} /> },
          { label: 'Authorized Users',   value: stats?.auth_count ?? 0,                            icon: <CheckCircle sx={{ color: 'success.main' }} /> },
          { label: 'Alerts',             value: stats?.alerts_enabled ? 'Enabled' : 'Paused',       icon: <NotificationsActive sx={{ color: stats?.alerts_enabled ? 'success.main' : 'warning.main' }} /> },
          { label: 'Alert Types Active', value: totalCount > 0 ? `${enabledCount}/${totalCount}` : '—', icon: <TuneIcon sx={{ color: 'primary.main' }} /> },
        ].map(s => (
          <Grid size={{ xs: 6, md: 3 }} key={s.label}>
            <Card>
              <CardContent sx={{ display: 'flex', gap: 1.5, alignItems: 'center', py: '14px !important' }}>
                {s.icon}
                <Box>
                  <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>{s.label}</Typography>
                  <Typography variant="subtitle1" sx={{ fontWeight: 700, lineHeight: 1.2 }}>{s.value}</Typography>
                </Box>
              </CardContent>
            </Card>
          </Grid>
        ))}
      </Grid>

      {/* ─── Tabs ─── */}
      <Tabs value={tab} onChange={(_, v) => setTab(v)} sx={{ mb: 1, borderBottom: 1, borderColor: 'divider' }}>
        <Tab icon={<History />} iconPosition="start" label="Activity" />
        <Tab icon={<TuneIcon />} iconPosition="start" label={
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
            Alert Types
            {totalCount > 0 && (
              <Chip label={`${enabledCount}/${totalCount}`} size="small"
                color={enabledCount > 0 ? 'primary' : 'default'} sx={{ height: 16, fontSize: '0.6rem' }} />
            )}
          </Box>
        } />
        <Tab icon={<Security />} iconPosition="start" label="Webhook" />
        <Tab icon={<Bolt />} iconPosition="start" label="Commands" />
      </Tabs>

      {/* ─── TAB 0: Activity ─── */}
      <TabPanel value={tab} index={0}>
        <Grid container spacing={2}>
          {/* Bot Info Card */}
          <Grid size={{ xs: 12, md: 4 }}>
            <Card sx={{ height: '100%' }}>
              <CardContent>
                <Typography variant="h6" sx={{ fontWeight: 700, mb: 2, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <SmartToy sx={{ color: '#2AABEE' }} /> Bot Info
                </Typography>
                <Box sx={{ display: 'grid', gap: 1.5 }}>
                  <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <Typography variant="body2" color="text.secondary">Username</Typography>
                    <Typography variant="body2" sx={{ fontWeight: 700, fontFamily: 'monospace' }}>
                      {stats?.bot_username}
                    </Typography>
                  </Box>
                  <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <Typography variant="body2" color="text.secondary">Authorized Users</Typography>
                    <Chip label={stats?.auth_count} size="small" color="primary" />
                  </Box>
                  <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <Typography variant="body2" color="text.secondary">Alerts Status</Typography>
                    <Chip
                      label={stats?.alerts_enabled ? 'Active' : 'Paused'}
                      size="small"
                      color={stats?.alerts_enabled ? 'success' : 'warning'}
                    />
                  </Box>
                  <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <Typography variant="body2" color="text.secondary">Webhook</Typography>
                    <Chip
                      label={stats?.webhook_status ? 'Connected' : 'Offline'}
                      size="small"
                      color={stats?.webhook_status ? 'success' : 'error'}
                    />
                  </Box>
                </Box>
                <Button
                  fullWidth variant="contained" sx={{ mt: 3 }}
                  startIcon={sending ? undefined : <Send />}
                  onClick={handleTest} disabled={sending}
                >
                  {sending
                    ? <><CircularProgress size={16} sx={{ mr: 1 }} /> Sending...</>
                    : 'Send Test Alert'}
                </Button>
                <Typography variant="caption" color="text.disabled" sx={{ display: 'block', mt: 1, textAlign: 'center' }}>
                  Sends a test message to {stats?.bot_username}
                </Typography>
              </CardContent>
            </Card>
          </Grid>

          {/* Recent Logs — with live indicator */}
          <Grid size={{ xs: 12, md: 8 }}>
            <Card sx={{ height: '100%' }}>
              <CardContent>
                <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
                  <Typography variant="h6" sx={{ fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                    <History /> Recent Activity
                    {/* live dot */}
                    <Box component="span" sx={{ display: 'inline-flex', alignItems: 'center', ml: 0.5 }}>
                      <FiberManualRecord sx={{ fontSize: 10, color: 'success.main', animation: 'pulse 2s infinite' }} />
                    </Box>
                  </Typography>
                  <Button size="small"
                    startIcon={logsLoading ? <CircularProgress size={12} /> : <Refresh />}
                    onClick={loadLogs} disabled={logsLoading}>
                    Refresh
                  </Button>
                </Box>
                {logsLoading && recentLogs.length === 0 ? (
                  <Box sx={{ display: 'flex', justifyContent: 'center', py: 3 }}><CircularProgress size={24} /></Box>
                ) : recentLogs.length > 0 ? (
                  <List disablePadding>
                    {recentLogs.map((log, idx) => (
                      <ListItem key={idx} divider={idx < recentLogs.length - 1} sx={{ px: 0, py: 0.75 }}>
                        <ListItemText
                          primary={
                            <Typography sx={{ fontSize: '0.8rem', fontWeight: 600, fontFamily: 'monospace' }}>
                              {log.command || '(no command)'}
                            </Typography>
                          }
                          secondary={
                            <Typography sx={{ fontSize: '0.7rem', color: 'text.disabled' }}>
                              {log.timestamp ? new Date(log.timestamp).toLocaleString() : 'N/A'} · {log.user}
                            </Typography>
                          }
                        />
                        <Chip
                          label={log.status}
                          size="small" variant="outlined"
                          color={
                            log.status?.toLowerCase() === 'executed' ? 'success' :
                            log.status?.toLowerCase() === 'error'    ? 'error' :
                            log.status?.toLowerCase() === 'unauthorized' ? 'warning' : 'default'
                          }
                          sx={{ fontSize: '0.65rem' }}
                        />
                      </ListItem>
                    ))}
                  </List>
                ) : (
                  <Box sx={{ py: 4, textAlign: 'center' }}>
                    <Typography variant="body2" color="text.disabled">No bot interaction logs found.</Typography>
                    <Typography variant="caption" color="text.disabled">
                      Logs appear after the bot receives commands via Telegram. Auto-refreshes every 15s.
                    </Typography>
                  </Box>
                )}
              </CardContent>
            </Card>
          </Grid>
        </Grid>
      </TabPanel>

      {/* ─── TAB 1: Alert Types ─── */}
      <TabPanel value={tab} index={1}>
        <Card>
          <CardContent>
            <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', mb: 2, flexWrap: 'wrap', gap: 1 }}>
              <Box>
                <Typography variant="h6" sx={{ fontWeight: 700 }}>Per-Alert Type Control</Typography>
                <Typography variant="body2" color="text.secondary">
                  Toggle which event types trigger Telegram notifications globally
                </Typography>
              </Box>
              <Box sx={{ display: 'flex', gap: 1 }}>
                <Button size="small" variant="outlined" onClick={loadAlertTypes}
                  startIcon={alertTypesLoading ? <CircularProgress size={14} /> : <Refresh />}
                  disabled={alertTypesLoading}>
                  Reload
                </Button>
                <Button size="small" variant="contained" onClick={handleSaveAlertTypes}
                  disabled={alertTypesSaving || alertTypesLoading}
                  startIcon={alertTypesSaving ? <CircularProgress size={14} /> : <Settings />}>
                  Save Changes
                </Button>
              </Box>
            </Box>

            <Divider sx={{ mb: 2 }} />

            {alertTypesLoading ? (
              <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}><CircularProgress /></Box>
            ) : alertTypes.length === 0 ? (
              <Alert severity="info">No alert types configured. Check API connectivity.</Alert>
            ) : (
              <Grid container spacing={1.5}>
                {alertTypes.map(at => (
                  <Grid size={{ xs: 12, sm: 6, md: 4 }} key={at.key}>
                    <Paper
                      variant="outlined"
                      sx={{
                        p: 1.5,
                        display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                        borderColor: at.enabled ? 'primary.main' : 'divider',
                        borderLeftWidth: 3,
                        transition: 'border-color 0.2s',
                      }}
                    >
                      <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                        <Typography sx={{ fontSize: 20 }}>{at.emoji}</Typography>
                        <Box>
                          <Typography variant="body2" sx={{ fontWeight: 600, lineHeight: 1.2 }}>{at.label}</Typography>
                          <Typography variant="caption" color="text.secondary" sx={{ fontFamily: 'monospace' }}>{at.key}</Typography>
                        </Box>
                      </Box>
                      <Switch
                        size="small"
                        checked={at.enabled}
                        onChange={e => handleToggleAlertType(at.key, e.target.checked)}
                        color="success"
                      />
                    </Paper>
                  </Grid>
                ))}
              </Grid>
            )}

            {alertTypes.length > 0 && (
              <Box sx={{ mt: 2, display: 'flex', gap: 1.5 }}>
                <Button size="small" variant="text" onClick={() =>
                  setAlertTypes(prev => prev.map(a => ({ ...a, enabled: true })))
                }>Enable All</Button>
                <Button size="small" variant="text" color="inherit" onClick={() =>
                  setAlertTypes(prev => prev.map(a => ({ ...a, enabled: false })))
                }>Disable All</Button>
              </Box>
            )}
          </CardContent>
        </Card>
      </TabPanel>

      {/* ─── TAB 2: Webhook ─── */}
      <TabPanel value={tab} index={2}>
        <Grid container spacing={2}>
          <Grid size={{ xs: 12, md: 6 }}>
            <Card>
              <CardContent>
                <Typography variant="h6" sx={{ fontWeight: 700, mb: 2, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <Security /> Webhook Status
                </Typography>
                {webhookLoading ? (
                  <Box sx={{ display: 'flex', justifyContent: 'center', py: 3 }}><CircularProgress /></Box>
                ) : webhookInfo ? (
                  <Box sx={{ display: 'grid', gap: 2 }}>
                    <Box>
                      <Typography variant="caption" color="text.secondary" sx={{ display: 'block', mb: 0.5 }}>
                        Registered URL
                      </Typography>
                      <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                        <Typography variant="body2" sx={{
                          fontFamily: 'monospace', fontSize: '0.72rem',
                          color: 'primary.main', wordBreak: 'break-all', flex: 1,
                        }}>
                          {webhookInfo.url || 'No webhook registered'}
                        </Typography>
                        {webhookInfo.url && (
                          <Tooltip title="Copy URL">
                            <IconButton size="small" onClick={() => {
                              navigator.clipboard.writeText(webhookInfo.url);
                              showNotify('URL copied to clipboard', 'info');
                            }}>
                              <ContentCopy fontSize="small" />
                            </IconButton>
                          </Tooltip>
                        )}
                      </Box>
                    </Box>

                    {/* Details row */}
                    <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 2 }}>
                      <Box>
                        <Typography variant="caption" color="text.secondary">Pending Updates</Typography>
                        <Typography variant="h6" sx={{ fontWeight: 700 }}>
                          {webhookInfo.pending_updates ?? 0}
                        </Typography>
                      </Box>
                      {webhookInfo.ip_address && (
                        <Box>
                          <Typography variant="caption" color="text.secondary">Server IP</Typography>
                          <Typography variant="body2" sx={{ fontFamily: 'monospace', fontWeight: 600 }}>
                            {webhookInfo.ip_address}
                          </Typography>
                        </Box>
                      )}
                      {webhookInfo.max_connections && (
                        <Box>
                          <Typography variant="caption" color="text.secondary">Max Connections</Typography>
                          <Typography variant="body2" sx={{ fontWeight: 600 }}>
                            {webhookInfo.max_connections}
                          </Typography>
                        </Box>
                      )}
                    </Box>

                    {/* Last error — prominently shown */}
                    {webhookInfo.last_error ? (
                      <Alert severity="warning" icon={<WarnIcon />} sx={{ py: 0.5 }}>
                        <Typography variant="caption" sx={{ fontWeight: 700, display: 'block' }}>
                          Last Error{webhookInfo.last_error_date
                            ? ` (${new Date(webhookInfo.last_error_date * 1000).toLocaleString()})`
                            : ''}
                        </Typography>
                        <Typography variant="caption">{webhookInfo.last_error}</Typography>
                      </Alert>
                    ) : (
                      <Alert severity="success" sx={{ py: 0.5 }}>
                        <Typography variant="caption">No recent errors — webhook healthy ✅</Typography>
                      </Alert>
                    )}
                  </Box>
                ) : (
                  <Alert severity="warning">Could not fetch webhook info from Telegram API</Alert>
                )}

                <Divider sx={{ my: 2 }} />

                <Typography variant="body2" color="text.secondary" sx={{ mb: 1.5 }}>
                  Webhook endpoint (on this server):
                </Typography>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                  <Typography variant="body2" sx={{
                    fontFamily: 'monospace', fontSize: '0.72rem',
                    color: 'primary.main', wordBreak: 'break-all', flex: 1,
                  }}>
                    https://dashboard.technostationery.com/api/telegram/webhook.php
                  </Typography>
                  <Tooltip title="Copy">
                    <IconButton size="small" onClick={() => {
                      navigator.clipboard.writeText('https://dashboard.technostationery.com/api/telegram/webhook.php');
                      showNotify('Copied!', 'info');
                    }}>
                      <ContentCopy fontSize="small" />
                    </IconButton>
                  </Tooltip>
                </Box>

                <Box sx={{ mt: 2, display: 'flex', gap: 1 }}>
                  <Button size="small" variant="outlined" startIcon={<Refresh />}
                    onClick={loadWebhook} disabled={webhookLoading}>
                    Refresh Status
                  </Button>
                  <Button size="small" variant="outlined" startIcon={<Settings />}
                    onClick={() => showNotify('Visit /api/telegram/setup.php on the server to reconfigure', 'info')}>
                    Re-configure
                  </Button>
                </Box>
              </CardContent>
            </Card>
          </Grid>

          <Grid size={{ xs: 12, md: 6 }}>
            <Card>
              <CardContent>
                <Typography variant="h6" sx={{ fontWeight: 700, mb: 2 }}>User Management</Typography>
                <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
                  Manage which Telegram users/chat IDs are authorized to receive alerts and use bot commands.
                </Typography>
                <Button variant="outlined" fullWidth size="small"
                  onClick={() => window.open('/api/telegram-users.php?action=list', '_blank')}>
                  View Authorized Users (JSON)
                </Button>
                <Divider sx={{ my: 2 }} />
                <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                  Bot Commands Reference:
                </Typography>
                <Box sx={{ display: 'grid', gap: 0.5 }}>
                  {[
                    ['/status',      'Full server overview'],
                    ['/services',    'Running services list'],
                    ['/load',        'CPU/memory/disk metrics'],
                    ['/orders',      'Recent Magento orders'],
                    ['/cache:flush', 'Flush Magento cache'],
                    ['/db:size',     'Database size report'],
                    ['/logs:summary','Log analysis summary'],
                    ['/ai:help',     'AI command reference'],
                    ['/help',        'All commands'],
                  ].map(([cmd, desc]) => (
                    <Box key={cmd} sx={{ display: 'flex', gap: 1, alignItems: 'center' }}>
                      <Typography variant="caption" sx={{ fontFamily: 'monospace', color: 'primary.main', minWidth: 110 }}>
                        {cmd}
                      </Typography>
                      <Typography variant="caption" color="text.secondary">{desc}</Typography>
                    </Box>
                  ))}
                </Box>
              </CardContent>
            </Card>
          </Grid>
        </Grid>
      </TabPanel>

      {/* ─── TAB 3: Commands ─── */}
      <TabPanel value={tab} index={3}>
        <Grid container spacing={2}>
          {/* Quick Commands */}
          <Grid size={{ xs: 12, md: 8 }}>
            <Card>
              <CardContent>
                <Typography variant="h6" sx={{ mb: 0.5, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <Bolt /> Quick Commands
                </Typography>
                <Typography variant="caption" color="text.disabled" sx={{ display: 'block', mb: 2 }}>
                  Click any command — the bot executes it and replies in Telegram. Logs update automatically.
                </Typography>

                {cmdGroups.map(group => (
                  <Box key={group} sx={{ mb: 2 }}>
                    <Typography variant="caption" color="text.disabled" sx={{ display: 'block', mb: 0.75, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.08em' }}>
                      {group}
                    </Typography>
                    <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1 }}>
                      {QUICK_COMMANDS.filter(c => c.group === group).map(({ cmd, label, color }) => (
                        <Chip
                          key={cmd}
                          label={cmdLoading === cmd
                            ? <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}><CircularProgress size={10} />{label}</Box>
                            : label}
                          clickable
                          onClick={() => handleQuickCommand(cmd)}
                          disabled={cmdLoading !== null}
                          variant={cmdLoading === cmd ? 'filled' : 'outlined'}
                          sx={{
                            fontFamily: 'monospace', fontWeight: 600, fontSize: '0.75rem',
                            borderColor: color, color: cmdLoading === cmd ? '#fff' : color,
                            backgroundColor: cmdLoading === cmd ? color : 'transparent',
                            '&:hover': { backgroundColor: color + '20' },
                          }}
                        />
                      ))}
                    </Box>
                  </Box>
                ))}

                {/* Custom command field */}
                <Divider sx={{ my: 2 }} />
                <Typography variant="caption" color="text.disabled" sx={{ display: 'block', mb: 1, fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.08em' }}>
                  Custom Command
                </Typography>
                <Box sx={{ display: 'flex', gap: 1 }}>
                  <TextField
                    size="small"
                    fullWidth
                    value={customCmd}
                    onChange={e => setCustomCmd(e.target.value)}
                    onKeyDown={e => { if (e.key === 'Enter') handleQuickCommand(customCmd); }}
                    placeholder="/command arg1 arg2"
                    slotProps={{
                      input: {
                        startAdornment: <Terminal sx={{ color: 'text.disabled', fontSize: 18, mr: 0.5 }} />,
                        sx: { fontFamily: 'monospace', fontSize: '0.85rem' },
                      },
                    }}
                  />
                  <Button
                    variant="contained"
                    size="small"
                    startIcon={cmdLoading ? <CircularProgress size={14} color="inherit" /> : <Send />}
                    onClick={() => handleQuickCommand(customCmd)}
                    disabled={!customCmd.trim() || cmdLoading !== null}
                    sx={{ minWidth: 90 }}
                  >
                    Run
                  </Button>
                </Box>
              </CardContent>
            </Card>
          </Grid>

          {/* Command Results */}
          <Grid size={{ xs: 12, md: 4 }}>
            <Card>
              <CardContent>
                <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 1.5 }}>
                  <Typography variant="h6" sx={{ fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                    <Terminal /> Dispatch Log
                  </Typography>
                  {cmdResults.length > 0 && (
                    <Button size="small" variant="text" color="inherit"
                      onClick={() => setCmdResults([])}
                      sx={{ fontSize: '0.7rem', minWidth: 'auto' }}>
                      Clear
                    </Button>
                  )}
                </Box>

                {cmdResults.length === 0 ? (
                  <Box sx={{ py: 3, textAlign: 'center' }}>
                    <Typography variant="caption" color="text.disabled">
                      Command results will appear here after dispatch.
                    </Typography>
                  </Box>
                ) : (
                  <List disablePadding dense>
                    {cmdResults.map((r, i) => (
                      <ListItem key={i} sx={{ px: 0, py: 0.5 }} divider={i < cmdResults.length - 1}>
                        <ListItemText
                          primary={
                            <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
                              {r.success
                                ? <CheckCircle sx={{ fontSize: 14, color: 'success.main' }} />
                                : <ErrIcon sx={{ fontSize: 14, color: 'error.main' }} />}
                              <Typography sx={{ fontFamily: 'monospace', fontSize: '0.78rem', fontWeight: 700 }}>
                                {r.cmd}
                              </Typography>
                            </Box>
                          }
                          secondary={
                            <Typography sx={{ fontSize: '0.68rem', color: 'text.disabled' }}>
                              {r.timestamp} — {r.message}
                            </Typography>
                          }
                        />
                      </ListItem>
                    ))}
                  </List>
                )}

                <Divider sx={{ my: 1.5 }} />
                <Button
                  fullWidth size="small" variant="outlined"
                  startIcon={logsLoading ? <CircularProgress size={12} /> : <Refresh />}
                  onClick={loadLogs} disabled={logsLoading}
                >
                  Refresh Activity Log
                </Button>
              </CardContent>
            </Card>
          </Grid>
        </Grid>
      </TabPanel>

      {/* ─── Snackbar ─── */}
      <Snackbar
        open={notify.open}
        autoHideDuration={5000}
        onClose={() => setNotify(n => ({ ...n, open: false }))}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
      >
        <Alert severity={notify.severity} variant="filled" sx={{ width: '100%' }}>
          {notify.message}
        </Alert>
      </Snackbar>
    </Box>
  );
}

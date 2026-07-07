import {
  Box, Typography, Grid, Card, CardContent, Button, Divider,
  List, ListItem, ListItemText, Chip, Snackbar, Alert,
  CircularProgress, Skeleton, Tooltip, IconButton,
} from '@mui/material';
import {
  SmartToy, Send, Settings, Security, History, Bolt,
  Refresh, CheckCircle, ErrorOutlined, WarningAmber, InfoOutlined,
} from '@mui/icons-material';
import { useState } from 'react';
import {
  fetchTelegramStats, sendTelegramTest, sendTelegramCommand, setTelegramWebhook,
  type TelegramStats, type TelegramLog,
} from '../api/notifications';
import { usePolling } from '../hooks/usePolling';
import StatusBadge from '../components/common/StatusBadge';

// ── Notification severity union ────────────────────────────────────────────────
type NotifySeverity = 'success' | 'error' | 'warning' | 'info';

interface Notify {
  open: boolean;
  message: string;
  severity: NotifySeverity;
}

// ── Status chip for log entries ────────────────────────────────────────────────
function LogStatusChip({ status }: { status: string }) {
  const s = status.toLowerCase();
  if (s === 'success' || s === 'executed')
    return <Chip icon={<CheckCircle sx={{ fontSize: 12 }} />} label="Success" size="small" color="success" variant="outlined" sx={{ fontSize: '0.62rem', height: 20 }} />;
  if (s === 'error' || s === 'failed')
    return <Chip icon={<ErrorOutlined sx={{ fontSize: 12 }} />} label="Error" size="small" color="error" variant="outlined" sx={{ fontSize: '0.62rem', height: 20 }} />;
  if (s === 'rate_limited')
    return <Chip icon={<WarningAmber sx={{ fontSize: 12 }} />} label="Rate Limited" size="small" color="warning" variant="outlined" sx={{ fontSize: '0.62rem', height: 20 }} />;
  return <Chip icon={<InfoOutlined sx={{ fontSize: 12 }} />} label={status} size="small" variant="outlined" sx={{ fontSize: '0.62rem', height: 20 }} />;
}

// ── Quick-command button chips ─────────────────────────────────────────────────
const QUICK_COMMANDS = [
  '/status', '/services', '/load', '/processes',
  '/orders', '/online', '/cache:flush', '/help',
] as const;

export default function TelegramPage() {
  const [sending, setSending] = useState(false);
  const [cmdLoading, setCmdLoading] = useState<string | null>(null);
  const [webhookSetting, setWebhookSetting] = useState(false);
  const [notify, setNotify] = useState<Notify>({ open: false, message: '', severity: 'success' });

  // ── Poll status every 30 s ─────────────────────────────────────────────────
  const {
    data: stats,
    loading,
    refreshing,
    refetch,
  } = usePolling<TelegramStats>(fetchTelegramStats, 30_000);

  const showNotify = (message: string, severity: NotifySeverity = 'success') =>
    setNotify({ open: true, message, severity });

  // ── Send test alert ────────────────────────────────────────────────────────
  const handleTest = async () => {
    setSending(true);
    try {
      const res = await sendTelegramTest();
      showNotify(res.message || 'Test alert sent!', 'success');
    } catch (e: unknown) {
      const msg = (e as { response?: { data?: { error?: string } }; message?: string })
        ?.response?.data?.error ?? (e instanceof Error ? e.message : String(e));
      showNotify('Failed to send test: ' + msg, 'error');
    } finally {
      setSending(false);
    }
  };

  // ── Dispatch quick command ────────────────────────────────────────────────
  const handleQuickCommand = async (cmd: string) => {
    setCmdLoading(cmd);
    try {
      const res = await sendTelegramCommand(cmd);
      showNotify(`Command "${cmd}" dispatched: ${res.message || 'OK'}`, 'success');
      // Refresh logs after a brief delay so the new entry appears
      setTimeout(refetch, 2000);
    } catch (e: unknown) {
      const msg = (e as { response?: { data?: { error?: string } }; message?: string })
        ?.response?.data?.error ?? (e instanceof Error ? e.message : String(e));
      showNotify(`Failed: ${msg}`, 'error');
    } finally {
      setCmdLoading(null);
    }
  };

  // ── Re-configure webhook ──────────────────────────────────────────────────
  const handleWebhookSet = async () => {
    setWebhookSetting(true);
    try {
      const res = await setTelegramWebhook();
      showNotify(res.message || 'Webhook configured', 'success');
      refetch();
    } catch (e: unknown) {
      const msg = (e as { response?: { data?: { error?: string } }; message?: string })
        ?.response?.data?.error ?? (e instanceof Error ? e.message : String(e));
      showNotify('Webhook error: ' + msg, 'error');
    } finally {
      setWebhookSetting(false);
    }
  };

  // ── Loading skeleton ───────────────────────────────────────────────────────
  if (loading) {
    return (
      <Box>
        <Skeleton variant="text" width={280} height={44} sx={{ mb: 1 }} />
        <Skeleton variant="text" width={380} height={24} sx={{ mb: 3 }} />
        <Grid container spacing={2} sx={{ mb: 3 }}>
          {[0, 1].map(i => (
            <Grid key={i} size={{ xs: 12, md: i === 0 ? 4 : 8 }}>
              <Skeleton variant="rounded" height={200} />
            </Grid>
          ))}
        </Grid>
        <Grid container spacing={2}>
          {[0, 1].map(i => (
            <Grid key={i} size={{ xs: 12, md: 6 }}>
              <Skeleton variant="rounded" height={140} />
            </Grid>
          ))}
        </Grid>
      </Box>
    );
  }

  const webhookOk = stats?.webhook_status ?? false;
  const recentLogs: TelegramLog[] = stats?.recent_logs ?? [];

  return (
    <Box>
      {/* Header */}
      <Box sx={{ mb: 3, display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Telegram Bot Control
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Server monitoring and customer engagement bot management.
          </Typography>
        </Box>
        <Tooltip title={refreshing ? 'Refreshing…' : 'Refresh status'}>
          <span>
            <IconButton size="small" onClick={refetch} disabled={refreshing}>
              {refreshing ? <CircularProgress size={18} /> : <Refresh fontSize="small" />}
            </IconButton>
          </span>
        </Tooltip>
      </Box>

      {/* Top row: Bot status card + Recent activity */}
      <Grid container spacing={2} sx={{ mb: 3 }}>
        {/* Bot Status */}
        <Grid size={{ xs: 12, md: 4 }}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2 }}>
                <Typography variant="h6" sx={{ fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <SmartToy sx={{ color: 'primary.main' }} />
                  {stats?.bot_username ?? '—'}
                </Typography>
                <StatusBadge
                  label={webhookOk ? 'Active' : 'Offline'}
                  color={webhookOk ? 'success' : 'error'}
                />
              </Box>

              {/* Bot ID */}
              {stats?.bot_id && (
                <Typography variant="caption" sx={{ color: 'text.disabled', display: 'block', mb: 2 }}>
                  ID: {stats.bot_id} · {stats.bot_first_name}
                </Typography>
              )}

              <Box sx={{ display: 'grid', gap: 1.5, mt: 1 }}>
                <Box sx={{ display: 'flex', justifyContent: 'space-between' }}>
                  <Typography variant="body2">Webhook</Typography>
                  <Chip
                    label={webhookOk ? 'Connected' : 'Not set'}
                    size="small"
                    color={webhookOk ? 'success' : 'error'}
                    sx={{ fontSize: '0.62rem', height: 18, fontWeight: 700 }}
                  />
                </Box>
                {stats?.webhook_pending !== undefined && stats.webhook_pending > 0 && (
                  <Box sx={{ display: 'flex', justifyContent: 'space-between' }}>
                    <Typography variant="body2">Pending updates</Typography>
                    <Typography variant="body2" sx={{ fontWeight: 700, color: 'warning.main' }}>
                      {stats.webhook_pending}
                    </Typography>
                  </Box>
                )}
                <Box sx={{ display: 'flex', justifyContent: 'space-between' }}>
                  <Typography variant="body2">Authorized Chats</Typography>
                  <Typography variant="body2" sx={{ fontWeight: 700 }}>{stats?.auth_count ?? '—'}</Typography>
                </Box>
                <Box sx={{ display: 'flex', justifyContent: 'space-between' }}>
                  <Typography variant="body2">Alerts Enabled</Typography>
                  <Chip
                    label={stats?.alerts_enabled ? 'YES' : 'NO'}
                    size="small"
                    color={stats?.alerts_enabled ? 'success' : 'default'}
                    sx={{ fontSize: '0.62rem', height: 18, fontWeight: 800 }}
                  />
                </Box>
              </Box>

              {/* Webhook error badge */}
              {stats?.webhook_last_err && (
                <Box sx={{ mt: 1.5, p: 1, borderRadius: 1, backgroundColor: 'rgba(239,68,68,0.08)', border: '1px solid rgba(239,68,68,0.2)' }}>
                  <Typography variant="caption" sx={{ color: 'error.main', fontSize: '0.65rem' }}>
                    ⚠️ {stats.webhook_last_err}
                  </Typography>
                </Box>
              )}

              <Button
                fullWidth
                variant="contained"
                startIcon={sending ? <CircularProgress size={16} color="inherit" /> : <Send />}
                sx={{ mt: 3 }}
                onClick={handleTest}
                disabled={sending}
              >
                {sending ? 'Sending…' : 'Send Test Alert'}
              </Button>
            </CardContent>
          </Card>
        </Grid>

        {/* Recent Activity */}
        <Grid size={{ xs: 12, md: 8 }}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Typography variant="h6" sx={{ mb: 2, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                <History /> Recent Activity
              </Typography>

              {recentLogs.length === 0 ? (
                <Box sx={{ py: 4, textAlign: 'center' }}>
                  <Typography variant="body2" sx={{ color: 'text.disabled' }}>
                    No recent activity. Webhook log is empty or not yet written.
                  </Typography>
                </Box>
              ) : (
                <List disablePadding>
                  {recentLogs.slice(0, 8).map((log, idx) => (
                    <ListItem
                      key={idx}
                      divider={idx < Math.min(recentLogs.length, 8) - 1}
                      sx={{ px: 0, py: 0.75 }}
                    >
                      <ListItemText
                        primary={
                          <Typography sx={{ fontSize: '0.8rem', fontWeight: 600, fontFamily: 'monospace' }}>
                            {log.command}
                          </Typography>
                        }
                        secondary={
                          <Typography sx={{ fontSize: '0.68rem', color: 'text.secondary' }}>
                            {log.timestamp} · {log.user}
                          </Typography>
                        }
                      />
                      <LogStatusChip status={log.status} />
                    </ListItem>
                  ))}
                </List>
              )}
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {/* Bottom row: Quick Commands + Security & Webhook */}
      <Grid container spacing={2}>
        {/* Quick Commands */}
        <Grid size={{ xs: 12, md: 6 }}>
          <Card>
            <CardContent>
              <Typography variant="h6" sx={{ mb: 2, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                <Bolt /> Quick Commands
              </Typography>
              <Typography variant="caption" sx={{ color: 'text.disabled', display: 'block', mb: 1.5 }}>
                Dispatch a read-only command to the bot from the dashboard.
              </Typography>
              <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1 }}>
                {QUICK_COMMANDS.map(cmd => (
                  <Chip
                    key={cmd}
                    label={cmd}
                    clickable
                    onClick={() => handleQuickCommand(cmd)}
                    disabled={cmdLoading !== null}
                    icon={cmdLoading === cmd ? <CircularProgress size={12} /> : undefined}
                    color={cmdLoading === cmd ? 'primary' : 'default'}
                    sx={{ fontFamily: 'monospace', fontWeight: 600, fontSize: '0.72rem' }}
                  />
                ))}
              </Box>
            </CardContent>
          </Card>
        </Grid>

        {/* Security & Webhook */}
        <Grid size={{ xs: 12, md: 6 }}>
          <Card>
            <CardContent>
              <Typography variant="h6" sx={{ mb: 2, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                <Security /> Security & Webhook
              </Typography>

              <Box sx={{ mb: 2 }}>
                <Typography variant="caption" sx={{ color: 'text.disabled', display: 'block', mb: 0.5 }}>
                  Current webhook URL
                </Typography>
                <Typography
                  variant="body2"
                  sx={{ fontFamily: 'monospace', fontSize: '0.72rem', color: 'text.secondary', wordBreak: 'break-all' }}
                >
                  {stats?.webhook_url || 'https://dashboard.technostationery.com/api/telegram/webhook.php'}
                </Typography>
              </Box>

              <Divider sx={{ my: 1.5 }} />

              <Button
                size="small"
                variant="outlined"
                startIcon={webhookSetting ? <CircularProgress size={14} /> : <Settings />}
                onClick={handleWebhookSet}
                disabled={webhookSetting}
              >
                {webhookSetting ? 'Configuring…' : 'Re-configure Webhook'}
              </Button>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {/* Snackbar */}
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

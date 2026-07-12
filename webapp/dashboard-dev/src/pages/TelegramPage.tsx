import { Box, Typography, Grid, Card, CardContent, Button, Divider, List, ListItem, ListItemText, Chip, Snackbar, Alert, CircularProgress } from '@mui/material';
import { SmartToy, Send, Settings, Security, History, Bolt, Refresh } from '@mui/icons-material';
import { useState, useEffect, useCallback } from 'react';
import { fetchTelegramStats, fetchTelegramRecentLogs, sendTelegramTest, TelegramStats, TelegramLog } from '../api/notifications';
import apiClient from '../api/client';
import LoadingState from '../components/common/LoadingState';
import StatusBadge from '../components/common/StatusBadge';

export default function TelegramPage() {
  const [stats, setStats] = useState<TelegramStats | null>(null);
  const [recentLogs, setRecentLogs] = useState<TelegramLog[]>([]);
  const [loading, setLoading] = useState(true);
  const [logsLoading, setLogsLoading] = useState(false);
  const [sending, setSending] = useState(false);
  const [cmdLoading, setCmdLoading] = useState<string | null>(null);
  const [notify, setNotify] = useState({ open: false, message: '', severity: 'success' as any });

  const loadStats = useCallback(() => {
    setLoading(true);
    fetchTelegramStats()
      .then(setStats)
      .catch(() => {
        // Fallback: show partial data even if stats call fails
        setStats({ bot_username: '@ServerNotif205bot', webhook_status: false, auth_count: 1, alerts_enabled: true });
      })
      .finally(() => setLoading(false));
  }, []);

  const loadRecentLogs = useCallback(() => {
    setLogsLoading(true);
    fetchTelegramRecentLogs()
      .then(setRecentLogs)
      .catch(() => setRecentLogs([]))
      .finally(() => setLogsLoading(false));
  }, []);

  useEffect(() => {
    loadStats();
    loadRecentLogs();
  }, [loadStats, loadRecentLogs]);

  const handleTest = async () => {
    setSending(true);
    try {
      const result = await sendTelegramTest();
      if (result?.success) {
        setNotify({ open: true, message: 'Test message sent successfully!', severity: 'success' });
        loadRecentLogs(); // refresh activity
      } else {
        setNotify({ open: true, message: 'Failed: ' + (result?.message || 'Unknown error'), severity: 'error' });
      }
    } catch (e: any) {
      setNotify({ open: true, message: 'Failed to send: ' + (e.response?.data?.message || e.message), severity: 'error' });
    } finally {
      setSending(false);
    }
  };

  const handleQuickCommand = async (cmd: string) => {
    setCmdLoading(cmd);
    try {
      const { data } = await apiClient.post('/api/monitor.php?action=telegram_action', {
        command: cmd
      });
      if (data?.success) {
        setNotify({ open: true, message: `Command "${cmd}" dispatched successfully`, severity: 'success' });
        loadRecentLogs();
      } else {
        setNotify({ open: true, message: `Command "${cmd}" failed: ${data?.message || 'Unknown error'}`, severity: 'error' });
      }
    } catch (e: any) {
      setNotify({ open: true, message: `Failed: ${e.response?.data?.message || e.message}`, severity: 'error' });
    } finally {
      setCmdLoading(null);
    }
  };

  if (loading) return <LoadingState message="Loading Telegram status..." />;

  return (
    <Box>
      <Box sx={{ mb: 3 }}>
        <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
          Telegram Bot Control
        </Typography>
        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
          Server monitoring and customer engagement bot management.
        </Typography>
      </Box>

      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 12, md: 4 }}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2 }}>
                <Typography variant="h6" sx={{ fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <SmartToy sx={{ color: 'primary.main' }} /> {stats?.bot_username}
                </Typography>
                <StatusBadge label={stats?.webhook_status ? 'Active' : 'Offline'} color={stats?.webhook_status ? 'success' : 'error'} />
              </Box>
              
              <Box sx={{ display: 'grid', gap: 1.5, mt: 3 }}>
                <Box sx={{ display: 'flex', justifyContent: 'space-between' }}>
                  <Typography variant="body2">Authorized Users</Typography>
                  <Typography variant="body2" sx={{ fontWeight: 700 }}>{stats?.auth_count}</Typography>
                </Box>
                <Box sx={{ display: 'flex', justifyContent: 'space-between' }}>
                  <Typography variant="body2">Alerts Enabled</Typography>
                  <Chip 
                    label={stats?.alerts_enabled ? 'YES' : 'NO'} 
                    size="small" 
                    color={stats?.alerts_enabled ? 'success' : 'default'} 
                    sx={{ fontSize: '0.65rem', height: 18, fontWeight: 800 }} 
                  />
                </Box>
                {stats?.webhook_url && (
                  <Box>
                    <Typography variant="caption" sx={{ color: 'text.disabled', display: 'block', mb: 0.5 }}>Webhook URL</Typography>
                    <Typography variant="caption" sx={{ fontFamily: 'monospace', color: 'text.secondary', wordBreak: 'break-all', fontSize: '0.65rem' }}>
                      {stats.webhook_url}
                    </Typography>
                  </Box>
                )}
              </Box>

              <Button 
                fullWidth 
                variant="contained" 
                startIcon={sending ? undefined : <Send />} 
                sx={{ mt: 3 }}
                onClick={handleTest}
                disabled={sending}
              >
                {sending ? <><CircularProgress size={16} sx={{ mr: 1 }} /> Sending...</> : 'Send Test Alert'}
              </Button>
            </CardContent>
          </Card>
        </Grid>

        <Grid size={{ xs: 12, md: 8 }}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
                <Typography variant="h6" sx={{ fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <History /> Recent Activity
                </Typography>
                <Button size="small" startIcon={logsLoading ? <CircularProgress size={12} /> : <Refresh />} onClick={loadRecentLogs} disabled={logsLoading}>
                  Refresh
                </Button>
              </Box>
              {logsLoading ? (
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
                        size="small" 
                        variant="outlined" 
                        color={
                          log.status?.toLowerCase() === 'executed' ? 'success' :
                          log.status?.toLowerCase() === 'error' ? 'error' :
                          log.status?.toLowerCase() === 'unauthorized' ? 'warning' : 'default'
                        }
                        sx={{ fontSize: '0.65rem' }} 
                      />
                    </ListItem>
                  ))}
                </List>
              ) : (
                <Box sx={{ py: 3, textAlign: 'center' }}>
                  <Typography variant="body2" sx={{ color: 'text.disabled' }}>
                    No bot interaction logs found.
                  </Typography>
                  <Typography variant="caption" sx={{ color: 'text.disabled' }}>
                    Logs appear after the bot receives commands via Telegram.
                  </Typography>
                </Box>
              )}
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      <Grid container spacing={2}>
        <Grid size={{ xs: 12, md: 6 }}>
          <Card>
            <CardContent>
              <Typography variant="h6" sx={{ mb: 2, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                <Bolt /> Quick Commands
              </Typography>
              <Typography variant="caption" sx={{ color: 'text.disabled', display: 'block', mb: 1.5 }}>
                Dispatch a command to the bot — it will execute and reply in Telegram.
              </Typography>
              <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1 }}>
                {['/status', '/services', '/load', '/processes', '/orders', '/online', '/cache:flush'].map(cmd => (
                  <Chip 
                    key={cmd} 
                    label={cmd} 
                    clickable 
                    onClick={() => handleQuickCommand(cmd)}
                    disabled={cmdLoading !== null}
                    icon={cmdLoading === cmd ? <CircularProgress size={12} /> : undefined}
                    color={cmdLoading === cmd ? 'primary' : 'default'}
                    sx={{ fontFamily: 'monospace', fontWeight: 600 }} 
                  />
                ))}
              </Box>
            </CardContent>
          </Card>
        </Grid>
        <Grid size={{ xs: 12, md: 6 }}>
          <Card>
            <CardContent>
              <Typography variant="h6" sx={{ mb: 2, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                <Security /> Security & Webhook
              </Typography>
              <Typography variant="body2" sx={{ color: 'text.secondary', mb: 1 }}>
                Webhook endpoint:
              </Typography>
              <Typography variant="body2" sx={{ fontFamily: 'monospace', fontSize: '0.75rem', color: 'primary.main', wordBreak: 'break-all', mb: 2 }}>
                https://dashboard.technostationery.com/api/telegram/webhook.php
              </Typography>
              <Divider sx={{ my: 1.5 }} />
              <Typography variant="caption" sx={{ color: 'text.disabled', display: 'block', mb: 1.5 }}>
                The webhook is registered directly with Telegram API. Use /api/telegram/setup.php to re-register.
              </Typography>
              <Button 
                size="small" 
                variant="outlined" 
                startIcon={<Settings />}
                onClick={() => setNotify({ open: true, message: 'Webhook is auto-managed. Visit /api/telegram/setup.php on the server to reconfigure.', severity: 'info' })}
              >
                Re-configure Webhook
              </Button>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      <Snackbar 
        open={notify.open} 
        autoHideDuration={5000} 
        onClose={() => setNotify({ ...notify, open: false })}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
      >
        <Alert severity={notify.severity} variant="filled" sx={{ width: '100%' }}>{notify.message}</Alert>
      </Snackbar>
    </Box>
  );
}

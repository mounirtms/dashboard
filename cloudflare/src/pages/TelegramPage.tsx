import { Box, Typography, Grid, Card, CardContent, Button, Divider, List, ListItem, ListItemText, Chip } from '@mui/material';
import { SmartToy, Send, Settings, Security, History, Bolt } from '@mui/icons-material';
import { useState, useEffect } from 'react';
import { fetchTelegramStats, sendTelegramTest, TelegramStats } from '../api/notifications';
import LoadingState from '../components/common/LoadingState';
import StatusBadge from '../components/common/StatusBadge';

export default function TelegramPage() {
  const [stats, setStats] = useState<TelegramStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [sending, setSending] = useState(false);

  useEffect(() => {
    fetchTelegramStats()
      .then(setStats)
      .finally(() => setLoading(false));
  }, []);

  const handleTest = async () => {
    setSending(true);
    try {
      await sendTelegramTest();
      alert('Test message sent!');
    } catch (e: any) {
      alert('Failed to send: ' + e.message);
    } finally {
      setSending(false);
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
                  <Chip label="YES" size="small" color="success" sx={{ fontSize: '0.65rem', height: 18, fontWeight: 800 }} />
                </Box>
              </Box>

              <Button 
                fullWidth 
                variant="contained" 
                startIcon={<Send />} 
                sx={{ mt: 3 }}
                onClick={handleTest}
                disabled={sending}
              >
                {sending ? 'Sending...' : 'Send Test Alert'}
              </Button>
            </CardContent>
          </Card>
        </Grid>

        <Grid size={{ xs: 12, md: 8 }}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Typography variant="h6" sx={{ mb: 2, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                <History /> Recent Activity
              </Typography>
              <List disablePadding>
                {[
                  { time: '10:42:01', user: 'Admin', cmd: '/status', status: 'Success' },
                  { time: '09:15:33', user: 'Admin', cmd: '/load', status: 'Success' },
                  { time: 'Yesterday', user: 'System', cmd: 'Alert: High CPU', status: 'Delivered' },
                ].map((log, idx) => (
                  <ListItem key={idx} divider={idx !== 2} sx={{ px: 0, py: 1 }}>
                    <ListItemText 
                      primary={<Typography sx={{ fontSize: '0.8rem', fontWeight: 600 }}>{log.cmd}</Typography>}
                      secondary={<Typography sx={{ fontSize: '0.7rem' }}>{log.time} &middot; {log.user}</Typography>}
                    />
                    <Chip label={log.status} size="small" variant="outlined" sx={{ fontSize: '0.65rem' }} />
                  </ListItem>
                ))}
              </List>
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
              <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1 }}>
                {['/status', '/services', '/load', '/processes', '/orders', '/online', '/cache:flush'].map(cmd => (
                  <Chip key={cmd} label={cmd} clickable sx={{ fontFamily: 'monospace', fontWeight: 600 }} />
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
              <Typography variant="body2" sx={{ color: 'text.secondary', mb: 2 }}>
                Current webhook: https://dashboard.technostationery.com/api/telegram/webhook.php
              </Typography>
              <Button size="small" variant="outlined" startIcon={<Settings />}>Re-configure Webhook</Button>
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </Box>
  );
}

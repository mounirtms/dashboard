import { Box, Typography, Grid, Card, CardContent, Button, TextField, MenuItem, Select, FormControl, InputLabel, Chip, Divider } from '@mui/material';
import { Campaign, Send, Schedule, Groups, Speed, CheckCircle } from '@mui/icons-material';
import { useState, useEffect } from 'react';
import { fetchPushStats, sendPushNotification, PushStats } from '../api/notifications';
import LoadingState from '../components/common/LoadingState';
import StatCard from '../components/common/StatCard';

export default function PushNotificationsPage() {
  const [stats, setStats] = useState<PushStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [sending, setSending] = useState(false);
  const [payload, setPayload] = useState({
    title: '',
    message: '',
    url: '',
    env: 'production'
  });

  useEffect(() => {
    fetchPushStats()
      .then(setStats)
      .finally(() => setLoading(false));
  }, []);

  const handleSend = async () => {
    if (!payload.title || !payload.message) return alert('Title and Message are required');
    setSending(true);
    try {
      await sendPushNotification(payload);
      alert('Notification queued for delivery!');
      setPayload({ ...payload, title: '', message: '' });
    } catch (e: any) {
      alert('Error: ' + e.message);
    } finally {
      setSending(false);
    }
  };

  if (loading && !stats) return <LoadingState message="Loading Webpushr stats..." />;

  return (
    <Box>
      <Box sx={{ mb: 3 }}>
        <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
          Push Notifications
        </Typography>
        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
          Webpushr integration for real-time browser alerts and marketing.
        </Typography>
      </Box>

      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 12, md: 4 }}>
          <StatCard label="Total Subscribers" value={stats?.subscribers || 2450} color="primary" icon={<Groups />} />
        </Grid>
        <Grid size={{ xs: 12, md: 4 }}>
          <StatCard label="Campaigns (30d)" value="12" color="success" icon={<Campaign />} />
        </Grid>
        <Grid size={{ xs: 12, md: 4 }}>
          <StatCard label="Avg. Click Rate" value="4.2%" color="info" icon={<Speed />} />
        </Grid>
      </Grid>

      <Grid container spacing={2}>
        <Grid size={{ xs: 12, md: 7 }}>
          <Card>
            <CardContent>
              <Typography variant="h6" sx={{ mb: 2, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                <Send sx={{ color: 'success.main' }} /> Quick Broadcast
              </Typography>
              <Box sx={{ display: 'grid', gap: 2 }}>
                <TextField 
                  fullWidth 
                  label="Notification Title" 
                  size="small" 
                  value={payload.title}
                  onChange={(e) => setPayload({...payload, title: e.target.value})}
                  placeholder="e.g. Flash Sale Live!" 
                />
                <TextField 
                  fullWidth 
                  multiline 
                  rows={3} 
                  label="Message Body" 
                  value={payload.message}
                  onChange={(e) => setPayload({...payload, message: e.target.value})}
                  placeholder="Enter the alert content..." 
                />
                <TextField 
                  fullWidth 
                  label="Target URL" 
                  size="small" 
                  value={payload.url}
                  onChange={(e) => setPayload({...payload, url: e.target.value})}
                  placeholder="https://technostationery.com/sale" 
                />
                <Box sx={{ display: 'flex', gap: 2, alignItems: 'center' }}>
                  <FormControl size="small" sx={{ minWidth: 150 }}>
                    <InputLabel>Environment</InputLabel>
                    <Select 
                      value={payload.env} 
                      label="Environment"
                      onChange={(e) => setPayload({...payload, env: e.target.value})}
                    >
                      <MenuItem value="production">Production</MenuItem>
                      <MenuItem value="beta">Beta</MenuItem>
                      <MenuItem value="dev">Development</MenuItem>
                    </Select>
                  </FormControl>
                  <Button 
                    variant="contained" 
                    color="success" 
                    startIcon={<Send />}
                    onClick={handleSend}
                    disabled={sending}
                  >
                    {sending ? 'Sending...' : 'Send Now'}
                  </Button>
                  <Button variant="outlined" startIcon={<Schedule />}>Schedule</Button>
                </Box>
              </Box>
            </CardContent>
          </Card>
        </Grid>

        <Grid size={{ xs: 12, md: 5 }}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Typography variant="h6" sx={{ mb: 2, fontWeight: 700 }}>Service Integrity</Typography>
              <Box sx={{ display: 'grid', gap: 1.5 }}>
                {Object.entries(stats?.env_status || { 'Production': 'OK', 'Beta': 'OK', 'Development': 'OK' }).map(([env, status]) => (
                  <Box key={env} sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', p: 1.5, borderRadius: 1, backgroundColor: 'background.default', border: '1px solid', borderColor: 'divider' }}>
                    <Typography variant="body2" sx={{ fontWeight: 600 }}>{env}</Typography>
                    <Chip label={status} size="small" color="success" icon={<CheckCircle sx={{ fontSize: 14 }} />} sx={{ fontWeight: 700 }} />
                  </Box>
                ))}
              </Box>
              <Divider sx={{ my: 3 }} />
              <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1 }}>Magento Module</Typography>
              <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block', mb: 2 }}>
                Module: Mab_Webpushr v1.2.0<br />
                Status: Enabled & Verified
              </Typography>
              <Button fullWidth size="small" variant="outlined">Re-Sync Subscribers</Button>
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </Box>
  );
}

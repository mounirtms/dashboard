import { Box, Typography, Grid, Card, CardContent, Button, TextField, MenuItem, Select, FormControl, InputLabel, Chip, Divider, Dialog, DialogTitle, DialogContent, DialogActions, Snackbar, Alert, CircularProgress, List, ListItem, ListItemText, ListItemIcon } from '@mui/material';
import { Campaign, Send, Schedule, Groups, Speed, CheckCircle, Sync, Refresh, Segment, Code } from '@mui/icons-material';
import { useState, useEffect } from 'react';
import { fetchPushStats, sendPushNotification, syncSubscribers, fetchSegments, PushStats, Segment as SegmentType } from '../api/notifications';
import LoadingState from '../components/common/LoadingState';
import StatCard from '../components/common/StatCard';

export default function PushNotificationsPage() {
  const [stats, setStats] = useState<PushStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [sending, setSending] = useState(false);
  const [selectedEnv, setSelectedEnv] = useState('dev');
  const [segments, setSegments] = useState<SegmentType[]>([]);
  const [payload, setPayload] = useState({
    title: '',
    message: '',
    url: '',
    env: 'dev',
    segment_id: ''
  });

  // Schedule dialog
  const [scheduleOpen, setScheduleOpen] = useState(false);
  const [scheduleDate, setScheduleDate] = useState('');
  const [scheduleTime, setScheduleTime] = useState('');
  const [scheduling, setScheduling] = useState(false);

  // Snackbar
  const [snackbar, setSnackbar] = useState<{ open: boolean; message: string; severity: 'success' | 'error' }>({
    open: false,
    message: '',
    severity: 'success'
  });

  // Re-sync
  const [syncing, setSyncing] = useState(false);

  const loadStats = (env: string) => {
    fetchPushStats(env)
      .then((data) => {
        setStats(data);
        setSegments(data.segments ?? []);
      })
      .catch(() => setSnackbar({ open: true, message: 'Failed to load Webpushr stats', severity: 'error' }))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    loadStats(selectedEnv);
  }, [selectedEnv]);

  const handleEnvChange = (env: string) => {
    setSelectedEnv(env);
    setPayload({ ...payload, env, segment_id: '' });
    setLoading(true);
  };

  const handleSend = async () => {
    if (!payload.title || !payload.message) {
      setSnackbar({ open: true, message: 'Title and Message are required', severity: 'error' });
      return;
    }
    setSending(true);
    try {
      await sendPushNotification(payload);
      setSnackbar({ open: true, message: 'Notification sent successfully!', severity: 'success' });
      setPayload({ ...payload, title: '', message: '' });
    } catch (e: any) {
      setSnackbar({ open: true, message: 'Error: ' + (e.response?.data?.message || e.message), severity: 'error' });
    } finally {
      setSending(false);
    }
  };

  const handleSchedule = async () => {
    if (!scheduleDate || !scheduleTime) {
      setSnackbar({ open: true, message: 'Please select date and time', severity: 'error' });
      return;
    }
    if (!payload.title || !payload.message) {
      setSnackbar({ open: true, message: 'Title and Message are required', severity: 'error' });
      return;
    }
    setScheduling(true);
    try {
      const scheduledAt = `${scheduleDate}T${scheduleTime}`;
      await sendPushNotification({ ...payload, scheduled_at: scheduledAt });
      setSnackbar({ open: true, message: `Notification scheduled for ${scheduledAt}`, severity: 'success' });
      setPayload({ ...payload, title: '', message: '' });
      setScheduleOpen(false);
      setScheduleDate('');
      setScheduleTime('');
    } catch (e: any) {
      setSnackbar({ open: true, message: 'Error: ' + (e.response?.data?.message || e.message), severity: 'error' });
    } finally {
      setScheduling(false);
    }
  };

  const handleSync = async () => {
    setSyncing(true);
    try {
      await syncSubscribers();
      setSnackbar({ open: true, message: 'Subscribers re-synced successfully!', severity: 'success' });
      // Refresh stats
      const freshStats = await fetchPushStats();
      setStats(freshStats);
    } catch (e: any) {
      setSnackbar({ open: true, message: 'Re-sync failed: ' + (e.response?.data?.message || e.message), severity: 'error' });
    } finally {
      setSyncing(false);
    }
  };

  const handleCloseSnackbar = () => setSnackbar(prev => ({ ...prev, open: false }));

  if (loading && !stats) return <LoadingState message="Loading Webpushr stats..." />;

  return (
    <Box>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 2 }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Push Notifications
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Webpushr integration for real-time browser alerts and marketing.
          </Typography>
        </Box>
        <Box sx={{ display: 'flex', gap: 1 }}>
          {['dev', 'beta', 'production'].map((env) => (
            <Chip
              key={env}
              label={env.charAt(0).toUpperCase() + env.slice(1)}
              onClick={() => handleEnvChange(env)}
              color={selectedEnv === env ? 'primary' : 'default'}
              variant={selectedEnv === env ? 'filled' : 'outlined'}
              clickable
              icon={<Code sx={{ fontSize: 14 }} />}
            />
          ))}
        </Box>
      </Box>

      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 12, sm: 4 }}>
          <StatCard
            label="Total Subscribers"
            value={stats?.subscribers ?? '...'}
            color="primary"
            icon={<Groups />}
          />
        </Grid>
        <Grid size={{ xs: 12, sm: 4 }}>
          <StatCard
            label="Active Environment"
            value={stats?.current_env ?? selectedEnv}
            color="info"
            icon={<Speed />}
          />
        </Grid>
        <Grid size={{ xs: 12, sm: 4 }}>
          <StatCard
            label="Segments"
            value={segments.length}
            color="success"
            icon={<Segment />}
          />
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
                  placeholder={`https://${selectedEnv}.technostationery.com`} 
                />
                <Box sx={{ display: 'flex', gap: 2, alignItems: 'center', flexWrap: 'wrap' }}>
                  <FormControl size="small" sx={{ minWidth: 180 }}>
                    <InputLabel>Segment</InputLabel>
                    <Select 
                      value={payload.segment_id} 
                      label="Segment"
                      onChange={(e) => setPayload({...payload, segment_id: e.target.value})}
                    >
                      <MenuItem value="">All Users (Default)</MenuItem>
                      {segments.filter(s => s.type !== 'Default' || s.id !== segments[0]?.id).map((seg) => (
                        <MenuItem key={seg.id} value={seg.id}>
                          {seg.title} ({seg.subscribers})
                        </MenuItem>
                      ))}
                    </Select>
                  </FormControl>
                  <Button 
                    variant="contained" 
                    color="success" 
                    startIcon={<Send />}
                    onClick={handleSend}
                    disabled={sending}
                  >
                    {sending ? <CircularProgress size={20} color="inherit" /> : 'Send Now'}
                  </Button>
                  <Button 
                    variant="outlined" 
                    startIcon={<Schedule />}
                    onClick={() => setScheduleOpen(true)}
                  >
                    Schedule
                  </Button>
                </Box>
              </Box>
            </CardContent>
          </Card>
        </Grid>

        <Grid size={{ xs: 12, md: 5 }}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Typography variant="h6" sx={{ mb: 2, fontWeight: 700 }}>Segments ({segments.length})</Typography>
              {segments.length > 0 ? (
                <List dense sx={{ maxHeight: 250, overflow: 'auto' }}>
                  {segments.map((seg) => (
                    <ListItem key={seg.id} sx={{ px: 0 }}>
                      <ListItemIcon sx={{ minWidth: 32 }}>
                        <Segment sx={{ fontSize: 18, color: seg.type === 'Default' ? 'primary.main' : 'text.secondary' }} />
                      </ListItemIcon>
                      <ListItemText 
                        primary={<Typography variant="body2" sx={{ fontWeight: 600 }}>{seg.title}</Typography>}
                        secondary={`${seg.subscribers} subscribers · ${seg.type}`}
                      />
                      <Chip label={seg.id} size="small" sx={{ fontSize: '0.6rem', height: 18 }} />
                    </ListItem>
                  ))}
                </List>
              ) : (
                <Typography variant="body2" sx={{ color: 'text.secondary', py: 2 }}>No segments found</Typography>
              )}
              <Divider sx={{ my: 2 }} />
              <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1 }}>Service Status</Typography>
              <Box sx={{ display: 'grid', gap: 1 }}>
                {Object.entries(stats?.env_status || {}).map(([env, status]) => (
                  <Box key={env} sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', p: 1, borderRadius: 1, backgroundColor: 'background.default', border: '1px solid', borderColor: 'divider' }}>
                    <Typography variant="body2" sx={{ fontWeight: 600 }}>{env}</Typography>
                    <Chip 
                      label={status} 
                      size="small" 
                      color={(status as string) === 'OK' ? 'success' : 'error'}
                      icon={<CheckCircle sx={{ fontSize: 14 }} />} 
                      sx={{ fontWeight: 700, fontSize: '0.65rem', height: 20 }} 
                    />
                  </Box>
                ))}
              </Box>
              <Button 
                fullWidth 
                size="small" 
                variant="outlined" 
                startIcon={<Sync />}
                onClick={handleSync}
                disabled={syncing}
                sx={{ mt: 2 }}
              >
                {syncing ? <CircularProgress size={20} /> : 'Re-Sync Subscribers'}
              </Button>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {/* Schedule Dialog */}
      <Dialog open={scheduleOpen} onClose={() => setScheduleOpen(false)}>
        <DialogTitle>Schedule Notification</DialogTitle>
        <DialogContent sx={{ minWidth: 320, pt: 2 }}>
          <Box sx={{ display: 'grid', gap: 2 }}>
            <Box>
              <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block', mb: 0.5 }}>Date</Typography>
              <input
                type="date"
                min={new Date().toISOString().split('T')[0]}
                value={scheduleDate}
                onChange={(e) => setScheduleDate(e.target.value)}
                style={{ width: '100%', padding: '8px', border: '1px solid #ccc', borderRadius: '4px' }}
              />
            </Box>
            <Box>
              <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block', mb: 0.5 }}>Time</Typography>
              <input
                type="time"
                value={scheduleTime}
                onChange={(e) => setScheduleTime(e.target.value)}
                style={{ width: '100%', padding: '8px', border: '1px solid #ccc', borderRadius: '4px' }}
              />
            </Box>
            <Typography variant="caption" sx={{ color: 'text.secondary' }}>
              Notification will be sent at {scheduleDate || '...'} {scheduleTime || '...'}
            </Typography>
          </Box>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setScheduleOpen(false)}>Cancel</Button>
          <Button 
            onClick={handleSchedule} 
            variant="contained" 
            disabled={scheduling || !scheduleDate || !scheduleTime}
          >
            {scheduling ? <CircularProgress size={20} /> : 'Schedule'}
          </Button>
        </DialogActions>
      </Dialog>

      {/* Snackbar */}
      <Snackbar open={snackbar.open} autoHideDuration={5000} onClose={handleCloseSnackbar}>
        <Alert onClose={handleCloseSnackbar} severity={snackbar.severity} sx={{ width: '100%' }}>
          {snackbar.message}
        </Alert>
      </Snackbar>
    </Box>
  );
}

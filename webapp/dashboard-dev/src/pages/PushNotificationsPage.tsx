import { Box, Typography, Grid, Card, CardContent, Button, TextField, MenuItem, Select, FormControl, InputLabel, Chip, Divider, Dialog, DialogTitle, DialogContent, DialogActions, Snackbar, Alert, CircularProgress, List, ListItem, ListItemText, ListItemIcon, Tabs, Tab, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Paper, LinearProgress } from '@mui/material';
import { Campaign, Send, Schedule, Groups, Speed, CheckCircle, Sync, Refresh, Segment, Code, CloudUpload, BarChart, TrendingUp, History, Warning, Error as ErrorIcon, Info as InfoIcon, Language, Public, Devices, Web, Dns, Person, Visibility } from '@mui/icons-material';
import { useState, useEffect } from 'react';
import { fetchPushStats, sendPushNotification, syncSubscribers, fetchSegments, fetchDeliveryStats, fetchSubscriberAnalytics, fetchSubscribers, fetchGeoAnalytics, fetchDeviceAnalytics, fetchBrowserAnalytics, fetchOsAnalytics, uploadPushImage, PushStats, Segment as SegmentType } from '../api/notifications';
import { usePermissions } from '../hooks/usePermissions';
import LoadingState from '../components/common/LoadingState';
import StatCard from '../components/common/StatCard';

interface AlertLogEntry {
  timestamp: string;
  severity: 'info' | 'warning' | 'critical';
  title: string;
  message: string;
  status: 'sent' | 'suppressed' | 'failed';
  channel: 'webpushr' | 'telegram';
}

interface TabPanelProps {
  children?: React.ReactNode;
  index: number;
  value: number;
}

function TabPanel(props: TabPanelProps) {
  const { children, value, index, ...other } = props;
  return (
    <div role="tabpanel" hidden={value !== index} {...other}>
      {value === index && <Box sx={{ pt: 2 }}>{children}</Box>}
    </div>
  );
}

export default function PushNotificationsPage() {
  const { hasPermission, isAdmin } = usePermissions();
  const [activeTab, setActiveTab] = useState(0);
  const [stats, setStats] = useState<PushStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [sending, setSending] = useState(false);
  // Valid envs that match webpushr.php config keys: dashboard, production, beta, dev
  const VALID_ENVS = ['dashboard', 'production', 'beta', 'dev'];
  const [selectedEnv, setSelectedEnv] = useState('production');
  const [segments, setSegments] = useState<SegmentType[]>([]);
  const [payload, setPayload] = useState({
    title: '',
    message: '',
    url: '',
    env: 'production',
    segment_id: ''
  });
  
  // Alert history
  const [alertLog, setAlertLog] = useState<AlertLogEntry[]>([]);
  const [alertLogLoading, setAlertLogLoading] = useState(false);

  // Image/icon uploads
  const [iconFile, setIconFile] = useState<File | null>(null);
  const [iconPreview, setIconPreview] = useState<string>('');
  const [iconUrl, setIconUrl] = useState<string>('');
  const [imageFile, setImageFile] = useState<File | null>(null);
  const [imagePreview, setImagePreview] = useState<string>('');
  const [imageUrl, setImageUrl] = useState<string>('');
  const [uploading, setUploading] = useState(false);
  const [tag, setTag] = useState('');

  // Analytics
  const [deliveryStats, setDeliveryStats] = useState<any[]>([]);
  const [subscriberAnalytics, setSubscriberAnalytics] = useState<any>(null);
  const [analyticsLoading, setAnalyticsLoading] = useState(false);

  // Geography and subscriber analytics
  const [geoData, setGeoData] = useState<any>(null);
  const [deviceData, setDeviceData] = useState<any>(null);
  const [browserData, setBrowserData] = useState<any>(null);
  const [osData, setOsData] = useState<any>(null);
  const [subscribers, setSubscribers] = useState<any[]>([]);
  const [geoLoading, setGeoLoading] = useState(false);

  // Segment detail dialog
  const [segmentDialogOpen, setSegmentDialogOpen] = useState(false);
  const [selectedSegment, setSelectedSegment] = useState<any>(null);

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

  // Permission check
  const canAccess = isAdmin || hasPermission('can_access_push_notifications');

  const loadStats = (env: string) => {
    fetchPushStats(env)
      .then((data) => {
        setStats(data);
        setSegments(data.segments ?? []);
      })
      .catch(() => setSnackbar({ open: true, message: 'Failed to load Webpushr stats', severity: 'error' }))
      .finally(() => setLoading(false));
  };

  const loadAnalytics = (env: string) => {
    setAnalyticsLoading(true);
    setGeoLoading(true);
    Promise.all([
      fetchDeliveryStats(env).catch(() => null),
      fetchSubscriberAnalytics(env).catch(() => null),
      fetchGeoAnalytics(env).catch(() => null),
      fetchDeviceAnalytics(env).catch(() => null),
      fetchBrowserAnalytics(env).catch(() => null),
      fetchOsAnalytics(env).catch(() => null),
      fetchSubscribers(env, 100).catch(() => null),
    ]).then(([delivery, subs, geo, device, browser, os, subsList]) => {
      if (delivery?.success) setDeliveryStats(delivery.data ?? []);
      if (subs?.success) setSubscriberAnalytics(subs.data);
      if (geo?.success) setGeoData(geo.data);
      if (device?.success) setDeviceData(device.data);
      if (browser?.success) setBrowserData(browser.data);
      if (os?.success) setOsData(os.data);
      if (subsList?.success) setSubscribers(subsList.data ?? []);
    }).finally(() => {
      setAnalyticsLoading(false);
      setGeoLoading(false);
    });
  };

  const loadAlertLog = async () => {
    setAlertLogLoading(true);
    try {
      const response = await fetch('/api/monitor.php?action=notification_log');
      const data = await response.json();
      if (data.success && data.logs) {
        setAlertLog(data.logs);
      }
    } catch (err) {
      console.error('Failed to load alert log:', err);
    } finally {
      setAlertLogLoading(false);
    }
  };

  useEffect(() => {
    loadStats(selectedEnv);
    loadAnalytics(selectedEnv);
  }, [selectedEnv]);

  useEffect(() => {
    if (activeTab === 1) loadAlertLog();
  }, [activeTab]);

  const handleEnvChange = (env: string) => {
    // useEffect on selectedEnv will trigger loadStats + loadAnalytics automatically
    setSelectedEnv(env);
    setPayload({ ...payload, env, segment_id: '' });
  };

  const handleIconUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setIconFile(file);
    setIconPreview(URL.createObjectURL(file));
    
    setUploading(true);
    try {
      const result = await uploadPushImage(file, 'icon');
      setIconUrl(result.url);
    } catch (err: any) {
      setSnackbar({ open: true, message: 'Icon upload failed: ' + (err.message || 'Unknown error'), severity: 'error' });
      setIconFile(null);
      setIconPreview('');
    } finally {
      setUploading(false);
    }
  };

  const handleImageUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setImageFile(file);
    setImagePreview(URL.createObjectURL(file));
    
    setUploading(true);
    try {
      const result = await uploadPushImage(file, 'image');
      setImageUrl(result.url);
    } catch (err: any) {
      setSnackbar({ open: true, message: 'Image upload failed: ' + (err.message || 'Unknown error'), severity: 'error' });
      setImageFile(null);
      setImagePreview('');
    } finally {
      setUploading(false);
    }
  };

  const handleSend = async () => {
    if (!payload.title || !payload.message) {
      setSnackbar({ open: true, message: 'Title and Message are required', severity: 'error' });
      return;
    }
    setSending(true);
    try {
      await sendPushNotification({
        ...payload,
        env: selectedEnv,
        icon: iconUrl || undefined,
        image: imageUrl || undefined,
        tag: tag || undefined,
      });
      setSnackbar({ open: true, message: 'Notification sent successfully!', severity: 'success' });
      setPayload({ ...payload, title: '', message: '' });
      setIconFile(null); setIconPreview(''); setIconUrl('');
      setImageFile(null); setImagePreview(''); setImageUrl('');
      setTag('');
      // Refresh analytics
      loadAnalytics(selectedEnv);
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
      await sendPushNotification({ 
        ...payload, 
        env: selectedEnv,
        scheduled_at: scheduledAt,
        icon: iconUrl || undefined,
        image: imageUrl || undefined,
        tag: tag || undefined,
      });
      setSnackbar({ open: true, message: `Notification scheduled for ${scheduledAt}`, severity: 'success' });
      setPayload({ ...payload, title: '', message: '' });
      setScheduleOpen(false);
      setScheduleDate('');
      setScheduleTime('');
      setIconFile(null); setIconPreview(''); setIconUrl('');
      setImageFile(null); setImagePreview(''); setImageUrl('');
      setTag('');
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
      const freshStats = await fetchPushStats(selectedEnv);
      setStats(freshStats);
    } catch (e: any) {
      setSnackbar({ open: true, message: 'Re-sync failed: ' + (e.response?.data?.message || e.message), severity: 'error' });
    } finally {
      setSyncing(false);
    }
  };

  const handleCloseSnackbar = () => setSnackbar(prev => ({ ...prev, open: false }));

  if (!canAccess) {
    return (
      <Box sx={{ p: 3, textAlign: 'center' }}>
        <Typography variant="h5" sx={{ mb: 2 }}>Access Denied</Typography>
        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
          You don't have permission to access Push Notifications. Contact your administrator.
        </Typography>
      </Box>
    );
  }

  if (loading && !stats) return <LoadingState message="Loading Webpushr stats..." />;

  return (
    <Box>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 2 }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Push Notifications
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Webpushr integration for real-time browser alerts and marketing campaigns.
          </Typography>
        </Box>
        <Box sx={{ display: 'flex', gap: 1, alignItems: 'center' }}>
          {VALID_ENVS.map((env) => (
            <Chip
              key={env}
              label={env.charAt(0).toUpperCase() + env.slice(1)}
              onClick={() => handleEnvChange(env)}
              color={selectedEnv === env ? 'primary' : 'default'}
              variant={selectedEnv === env ? 'filled' : 'outlined'}
              clickable
              icon={<Code sx={{ fontSize: 14 }} />}
              size="small"
            />
          ))}
        </Box>
      </Box>

      <Card sx={{ mb: 3 }}>
        <Box sx={{ borderBottom: 1, borderColor: 'divider' }}>
          <Tabs value={activeTab} onChange={(_, v) => setActiveTab(v)}>
            <Tab icon={<Send sx={{ fontSize: 18 }} />} iconPosition="start" label="Broadcast" />
            <Tab icon={<History sx={{ fontSize: 18 }} />} iconPosition="start" label="Alert History" />
            <Tab icon={<Language sx={{ fontSize: 18 }} />} iconPosition="start" label="Geography" />
            <Tab icon={<Person sx={{ fontSize: 18 }} />} iconPosition="start" label="Subscribers" />
            <Tab icon={<BarChart sx={{ fontSize: 18 }} />} iconPosition="start" label="Delivery" />
          </Tabs>
        </Box>
      </Card>

      {/* Stats Cards */}
      <TabPanel value={activeTab} index={0}>
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

      {/* Analytics Cards */}
      {analyticsLoading ? (
        <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}><CircularProgress /></Box>
      ) : subscriberAnalytics ? (
        <Grid container spacing={2} sx={{ mb: 3 }}>
          <Grid size={{ xs: 12, sm: 6 }}>
            <Card>
              <CardContent>
                <Typography variant="h6" sx={{ mb: 1, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <BarChart sx={{ color: 'info.main' }} /> Subscriber Analytics
                </Typography>
                <Typography variant="h3" sx={{ fontWeight: 800, color: 'primary.main' }}>
                  {subscriberAnalytics.total_subscribers?.toLocaleString() ?? 0}
                </Typography>
                <Typography variant="caption" sx={{ color: 'text.secondary' }}>Total subscribers across all segments</Typography>
                {subscriberAnalytics.segments?.length > 0 && (
                  <Box sx={{ mt: 2 }}>
                    <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block', mb: 0.5 }}>Top Segments:</Typography>
                    {subscriberAnalytics.segments.slice(0, 3).map((seg: any) => (
                      <Chip key={seg.id} label={`${seg.title}: ${seg.subscribers}`} size="small" sx={{ mr: 0.5, mb: 0.5 }} />
                    ))}
                  </Box>
                )}
              </CardContent>
            </Card>
          </Grid>
          <Grid size={{ xs: 12, sm: 6 }}>
            <Card>
              <CardContent>
                <Typography variant="h6" sx={{ mb: 1, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <TrendingUp sx={{ color: 'success.main' }} /> Delivery Reports
                </Typography>
                {deliveryStats.length > 0 ? (
                  <Box>
                    <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                      {deliveryStats.length} recent notifications tracked
                    </Typography>
                  </Box>
                ) : (
                  <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                    No delivery data available yet
                  </Typography>
                )}
              </CardContent>
            </Card>
          </Grid>
        </Grid>
      ) : null}

      <Grid container spacing={2}>
        {/* Quick Broadcast Card */}
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
                
                {/* Icon and Image Upload */}
                <Box sx={{ display: 'flex', gap: 2, alignItems: 'flex-start', flexWrap: 'wrap' }}>
                  {/* Icon Upload */}
                  <Box sx={{ flex: 1, minWidth: 140 }}>
                    <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block', mb: 0.5 }}>
                      Icon (max 192x192, 512KB)
                    </Typography>
                    <input
                      type="file"
                      accept="image/jpeg,image/png,image/webp,image/gif"
                      onChange={handleIconUpload}
                      style={{ display: 'none' }}
                      id="icon-upload-input"
                    />
                    <label htmlFor="icon-upload-input">
                      <Button 
                        variant="outlined" 
                        component="span" 
                        size="small"
                        startIcon={<CloudUpload sx={{ fontSize: 16 }} />}
                        disabled={uploading}
                      >
                        {uploading && !iconUrl ? <CircularProgress size={16} /> : iconUrl ? 'Change Icon' : 'Upload Icon'}
                      </Button>
                    </label>
                    {iconPreview && (
                      <Box sx={{ mt: 1, width: 48, height: 48, borderRadius: 1, overflow: 'hidden', border: '1px solid', borderColor: 'divider', backgroundColor: 'background.default' }}>
                        <img src={iconPreview} alt="Icon preview" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                      </Box>
                    )}
                  </Box>
                  
                  {/* Image Upload */}
                  <Box sx={{ flex: 1, minWidth: 140 }}>
                    <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block', mb: 0.5 }}>
                      Large Image (max 1200x1200, 2MB)
                    </Typography>
                    <input
                      type="file"
                      accept="image/jpeg,image/png,image/webp,image/gif"
                      onChange={handleImageUpload}
                      style={{ display: 'none' }}
                      id="image-upload-input"
                    />
                    <label htmlFor="image-upload-input">
                      <Button 
                        variant="outlined" 
                        component="span" 
                        size="small"
                        startIcon={<CloudUpload sx={{ fontSize: 16 }} />}
                        disabled={uploading}
                      >
                        {uploading && !imageUrl ? <CircularProgress size={16} /> : imageUrl ? 'Change Image' : 'Upload Image'}
                      </Button>
                    </label>
                    {imagePreview && (
                      <Box sx={{ mt: 1, width: 120, height: 80, borderRadius: 1, overflow: 'hidden', border: '1px solid', borderColor: 'divider', backgroundColor: 'background.default' }}>
                        <img src={imagePreview} alt="Image preview" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                      </Box>
                    )}
                  </Box>
                </Box>

                {/* Tag field */}
                <TextField 
                  fullWidth 
                  label="Notification Tag" 
                  size="small" 
                  value={tag}
                  onChange={(e) => setTag(e.target.value)}
                  placeholder="e.g. sale, promotion, alert"
                  helperText="Groups notifications with the same tag"
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

        {/* Segments and Status Card */}
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
      </TabPanel>

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
                style={{ width: '100%', padding: '8px', border: '1px solid #333', borderRadius: '4px', backgroundColor: '#1a1a1a', color: '#fff' }}
              />
            </Box>
            <Box>
              <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block', mb: 0.5 }}>Time</Typography>
              <input
                type="time"
                value={scheduleTime}
                onChange={(e) => setScheduleTime(e.target.value)}
                style={{ width: '100%', padding: '8px', border: '1px solid #333', borderRadius: '4px', backgroundColor: '#1a1a1a', color: '#fff' }}
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

      {/* Alert History Tab */}
      <TabPanel value={activeTab} index={1}>
        <Card>
          <CardContent>
            <Typography variant="h6" sx={{ mb: 2, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
              <History sx={{ color: 'info.main' }} /> Monitoring Alert Log
            </Typography>
            {alertLogLoading ? (
              <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}><CircularProgress /></Box>
            ) : alertLog.length > 0 ? (
              <TableContainer>
                <Table size="small">
                  <TableHead>
                    <TableRow>
                      <TableCell>Time</TableCell>
                      <TableCell>Severity</TableCell>
                      <TableCell>Title</TableCell>
                      <TableCell>Message</TableCell>
                      <TableCell>Channel</TableCell>
                      <TableCell>Status</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {alertLog.slice(0, 50).map((entry, i) => (
                      <TableRow key={i}>
                        <TableCell>{new Date(entry.timestamp).toLocaleString()}</TableCell>
                        <TableCell>
                          <Chip
                            size="small"
                            label={entry.severity}
                            color={entry.severity === 'critical' ? 'error' : entry.severity === 'warning' ? 'warning' : 'info'}
                            icon={entry.severity === 'critical' ? <ErrorIcon /> : entry.severity === 'warning' ? <Warning /> : <InfoIcon />}
                          />
                        </TableCell>
                        <TableCell>{entry.title}</TableCell>
                        <TableCell sx={{ maxWidth: 300, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{entry.message}</TableCell>
                        <TableCell>{entry.channel}</TableCell>
                        <TableCell>
                          <Chip size="small" label={entry.status} color={entry.status === 'sent' ? 'success' : entry.status === 'suppressed' ? 'default' : 'error'} />
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </TableContainer>
            ) : (
              <Typography variant="body2" sx={{ color: 'text.secondary', py: 4, textAlign: 'center' }}>No alerts logged yet</Typography>
            )}
          </CardContent>
        </Card>
      </TabPanel>

      {/* Geography Tab */}
      <TabPanel value={activeTab} index={2}>
        {geoLoading ? (
          <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}><CircularProgress /></Box>
        ) : (
        <Grid container spacing={2}>
          {/* Subscriber Distribution by Country */}
          <Grid size={{ xs: 12, md: 6 }}>
            <Card>
              <CardContent>
                <Typography variant="h6" sx={{ mb: 2, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <Public sx={{ color: 'info.main' }} /> Countries
                </Typography>
                {geoData?.countries && geoData.countries.length > 0 ? (
                  <Box>
                    {geoData.countries.slice(0, 10).map((country: any, i: number) => (
                      <Box key={i} sx={{ mb: 1.5 }}>
                        <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 0.5 }}>
                          <Typography variant="body2" sx={{ fontWeight: 600 }}>{country.name}</Typography>
                          <Typography variant="body2" sx={{ color: 'text.secondary' }}>{country.count}</Typography>
                        </Box>
                        <LinearProgress 
                          variant="determinate" 
                          value={geoData.countries[0]?.count ? (country.count / geoData.countries[0].count * 100) : 0}
                          sx={{ height: 6, borderRadius: 1 }}
                        />
                      </Box>
                    ))}
                  </Box>
                ) : (
                  <Typography variant="body2" sx={{ color: 'text.secondary', py: 4, textAlign: 'center' }}>No country data available</Typography>
                )}
              </CardContent>
            </Card>
          </Grid>

          {/* Top Cities */}
          <Grid size={{ xs: 12, md: 6 }}>
            <Card>
              <CardContent>
                <Typography variant="h6" sx={{ mb: 2, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <Language sx={{ color: 'success.main' }} /> Top Cities
                </Typography>
                {geoData?.cities && geoData.cities.length > 0 ? (
                  <Box>
                    {geoData.cities.slice(0, 10).map((city: any, i: number) => (
                      <Box key={i} sx={{ mb: 1.5 }}>
                        <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 0.5 }}>
                          <Typography variant="body2" sx={{ fontWeight: 600 }}>{city.name}</Typography>
                          <Typography variant="body2" sx={{ color: 'text.secondary' }}>{city.count}</Typography>
                        </Box>
                        <LinearProgress 
                          variant="determinate" 
                          value={geoData.cities[0]?.count ? (city.count / geoData.cities[0].count * 100) : 0}
                          sx={{ height: 6, borderRadius: 1 }}
                        />
                      </Box>
                    ))}
                  </Box>
                ) : (
                  <Typography variant="body2" sx={{ color: 'text.secondary', py: 4, textAlign: 'center' }}>No city data available</Typography>
                )}
              </CardContent>
            </Card>
          </Grid>

          {/* Device Types */}
          <Grid size={{ xs: 12, sm: 6, md: 3 }}>
            <Card>
              <CardContent>
                <Typography variant="h6" sx={{ mb: 2, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <Devices sx={{ color: 'primary.main' }} /> Devices
                </Typography>
                {deviceData ? (
                  <Box>
                    {Object.entries(deviceData).map(([device, count]) => (
                      <Box key={device} sx={{ display: 'flex', justifyContent: 'space-between', mb: 1 }}>
                        <Typography variant="body2">{device}</Typography>
                        <Typography variant="body2" sx={{ fontWeight: 600 }}>{(count as number).toLocaleString()}</Typography>
                      </Box>
                    ))}
                  </Box>
                ) : (
                  <Typography variant="body2" sx={{ color: 'text.secondary' }}>No data</Typography>
                )}
              </CardContent>
            </Card>
          </Grid>

          {/* Browsers */}
          <Grid size={{ xs: 12, sm: 6, md: 3 }}>
            <Card>
              <CardContent>
                <Typography variant="h6" sx={{ mb: 2, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <Web sx={{ color: 'success.main' }} /> Browsers
                </Typography>
                {browserData && browserData.length > 0 ? (
                  <Box>
                    {browserData.slice(0, 5).map((browser: any, i: number) => (
                      <Box key={i} sx={{ display: 'flex', justifyContent: 'space-between', mb: 1 }}>
                        <Typography variant="body2">{browser.name}</Typography>
                        <Typography variant="body2" sx={{ fontWeight: 600 }}>{browser.count.toLocaleString()}</Typography>
                      </Box>
                    ))}
                  </Box>
                ) : (
                  <Typography variant="body2" sx={{ color: 'text.secondary' }}>No data</Typography>
                )}
              </CardContent>
            </Card>
          </Grid>

          {/* Operating Systems */}
          <Grid size={{ xs: 12, sm: 6, md: 3 }}>
            <Card>
              <CardContent>
                <Typography variant="h6" sx={{ mb: 2, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <Dns sx={{ color: 'warning.main' }} /> Operating Systems
                </Typography>
                {osData && osData.length > 0 ? (
                  <Box>
                    {osData.slice(0, 5).map((os: any, i: number) => (
                      <Box key={i} sx={{ display: 'flex', justifyContent: 'space-between', mb: 1 }}>
                        <Typography variant="body2">{os.name}</Typography>
                        <Typography variant="body2" sx={{ fontWeight: 600 }}>{os.count.toLocaleString()}</Typography>
                      </Box>
                    ))}
                  </Box>
                ) : (
                  <Typography variant="body2" sx={{ color: 'text.secondary' }}>No data</Typography>
                )}
              </CardContent>
            </Card>
          </Grid>

          {/* Summary */}
          <Grid size={{ xs: 12, sm: 6, md: 3 }}>
            <Card>
              <CardContent>
                <Typography variant="h6" sx={{ mb: 2, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <Groups sx={{ color: 'info.main' }} /> Summary
                </Typography>
                <Box sx={{ display: 'grid', gap: 1 }}>
                  <Box sx={{ p: 1.5, backgroundColor: 'background.default', borderRadius: 1 }}>
                    <Typography variant="caption" sx={{ color: 'text.disabled', display: 'block' }}>Total Countries</Typography>
                    <Typography variant="h5" sx={{ fontWeight: 800, color: 'primary.main' }}>
                      {geoData?.countries?.length || 0}
                    </Typography>
                  </Box>
                  <Box sx={{ p: 1.5, backgroundColor: 'background.default', borderRadius: 1 }}>
                    <Typography variant="caption" sx={{ color: 'text.disabled', display: 'block' }}>Total Cities</Typography>
                    <Typography variant="h5" sx={{ fontWeight: 800, color: 'success.main' }}>
                      {geoData?.cities?.length || 0}
                    </Typography>
                  </Box>
                </Box>
              </CardContent>
            </Card>
          </Grid>
        </Grid>
        )}
      </TabPanel>

      {/* Subscribers Tab */}
      <TabPanel value={activeTab} index={3}>
        <Card>
          <CardContent>
            <Typography variant="h6" sx={{ mb: 2, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
              <Person sx={{ color: 'primary.main' }} /> Subscribers List
            </Typography>
            {geoLoading ? (
              <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}><CircularProgress /></Box>
            ) : subscribers.length > 0 ? (
              <TableContainer>
                <Table size="small">
                  <TableHead>
                    <TableRow>
                      <TableCell>Subscriber ID</TableCell>
                      <TableCell>Browser</TableCell>
                      <TableCell>OS</TableCell>
                      <TableCell>Device</TableCell>
                      <TableCell>Country</TableCell>
                      <TableCell>City</TableCell>
                      <TableCell>Last Active</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {subscribers.slice(0, 50).map((sub: any, i: number) => (
                      <TableRow key={i}>
                        <TableCell>
                          <Typography variant="body2" sx={{ fontFamily: 'monospace', fontSize: '0.75rem' }}>
                            {sub.sid || sub.id || 'N/A'}
                          </Typography>
                        </TableCell>
                        <TableCell>{sub.browser || sub.browser_name || 'Unknown'}</TableCell>
                        <TableCell>{sub.os || sub.os_name || 'Unknown'}</TableCell>
                        <TableCell>{sub.device_type || 'Unknown'}</TableCell>
                        <TableCell>{sub.country || sub.location?.country || 'Unknown'}</TableCell>
                        <TableCell>{sub.city || sub.location?.city || 'Unknown'}</TableCell>
                        <TableCell>{sub.last_active || sub.created || 'N/A'}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </TableContainer>
            ) : (
              <Typography variant="body2" sx={{ color: 'text.secondary', py: 4, textAlign: 'center' }}>No subscriber data available</Typography>
            )}
          </CardContent>
        </Card>
      </TabPanel>

      {/* Delivery Tab */}
      <TabPanel value={activeTab} index={4}>
        <Card>
          <CardContent>
            <Typography variant="h6" sx={{ mb: 2, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
              <BarChart sx={{ color: 'success.main' }} /> Delivery Reports
            </Typography>
            {analyticsLoading ? (
              <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}><CircularProgress /></Box>
            ) : deliveryStats.length > 0 ? (
              <TableContainer>
                <Table size="small">
                  <TableHead>
                    <TableRow>
                      <TableCell>Date</TableCell>
                      <TableCell>Notification</TableCell>
                      <TableCell>Sent</TableCell>
                      <TableCell>Delivered</TableCell>
                      <TableCell>Clicked</TableCell>
                      <TableCell>Failed</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {deliveryStats.slice(0, 50).map((report: any, i: number) => (
                      <TableRow key={i}>
                        <TableCell>{report.date || report.created || 'N/A'}</TableCell>
                        <TableCell>{report.title || report.name || 'Notification'}</TableCell>
                        <TableCell>{report.sent?.toLocaleString() || 0}</TableCell>
                        <TableCell>{report.delivered?.toLocaleString() || 0}</TableCell>
                        <TableCell>{report.clicked?.toLocaleString() || 0}</TableCell>
                        <TableCell>{report.failed?.toLocaleString() || 0}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </TableContainer>
            ) : (
              <Typography variant="body2" sx={{ color: 'text.secondary', py: 4, textAlign: 'center' }}>No delivery data available yet</Typography>
            )}
          </CardContent>
        </Card>
      </TabPanel>

      {/* Snackbar */}
      <Snackbar open={snackbar.open} autoHideDuration={5000} onClose={handleCloseSnackbar}>
        <Alert onClose={handleCloseSnackbar} severity={snackbar.severity} sx={{ width: '100%' }}>
          {snackbar.message}
        </Alert>
      </Snackbar>
    </Box>
  );
}

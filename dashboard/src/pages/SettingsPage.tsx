import { Box, Typography, Grid, Card, CardContent, Switch, FormControlLabel, TextField, Button, Divider, Alert, Tabs, Tab, List, ListItem, ListItemText, InputAdornment, IconButton, Chip, Select, MenuItem, FormControl, InputLabel, Avatar, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Paper } from '@mui/material';
import { Settings as SettingsIcon, Notifications, Security, Storage, Language, Api, Visibility, VisibilityOff, Code, Info, Refresh, CheckCircle, Person, Palette, Save, Delete, Laptop, Smartphone, Tablet, Email, Send, AdminPanelSettings, ErrorOutlined, Lock } from '@mui/icons-material';
import { useState, useEffect, useCallback } from 'react';
import { fetchSettings, saveSettings, fetchPushSubscriptions, unsubscribeDevice, type UserSettings, type PushSubscription } from '../api/settings';
import { fetchEmailSettings, saveEmailSettings, testEmailSettings, fetchEmailLogs, fetchEmailLogStats, clearEmailLogs, type EmailSettings, type EmailLog, type EmailLogStats } from '../api/notifications';
import { useWebpushrSubscription } from '../hooks/useWebpushrSubscription';
import apiClient from '../api/client';

interface TabPanelProps {
  children?: React.ReactNode;
  index: number;
  value: number;
}

function TabPanel(props: TabPanelProps) {
  const { children, value, index, ...other } = props;
  return (
    <div role="tabpanel" hidden={value !== index} {...other}>
      {value === index && <Box sx={{ pt: 3 }}>{children}</Box>}
    </div>
  );
}

const defaultPersonal: UserSettings['personal'] = { full_name: '', email: '', phone: '' };
const defaultAppearance: UserSettings['appearance'] = { theme: 'dark', font_size: 'medium', animations: true, language: 'en' };
const defaultGeneral: UserSettings['general'] = { notifications_enabled: true, auto_refresh: true, refresh_interval: 30, debug_mode: false };

export default function SettingsPage() {
  const [tab, setTab] = useState(0);
  const [showKey, setShowKey] = useState(false);
  const [apiToken, setApiToken] = useState('••••••••••••••••••••••••••••••••');
  const [telegramWebhook, setTelegramWebhook] = useState('https://dashboard.technostationery.com/api/telegram/webhook.php');
  
  // API-loaded settings
  const [personal, setPersonal] = useState<UserSettings['personal']>(defaultPersonal);
  const [appearance, setAppearance] = useState<UserSettings['appearance']>(defaultAppearance);
  const [general, setGeneral] = useState<UserSettings['general']>(defaultGeneral);
  const [lastSaved, setLastSaved] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  
  // Email settings
  const defaultEmailSettings: EmailSettings = {
    from_email: 'alerts@dashboard.technostationery.com',
    from_name: 'Techno Dashboard',
    admin_email_1: 'admin@dashboard.technostationery.com',
    admin_email_2: 'webmaster@techno-dz.com',
    enabled: 'true',
    smtp_host: '',
    smtp_port: '587',
    smtp_user: '',
    smtp_pass: '',
    smtp_encryption: 'tls'
  };
  const [emailSettings, setEmailSettings] = useState<EmailSettings>(defaultEmailSettings);
  const [emailSaving, setEmailSaving] = useState(false);
  const [emailTestLoading, setEmailTestLoading] = useState(false);
  const [emailTestResult, setEmailTestResult] = useState<{ success: boolean; message: string } | null>(null);
  const [showSmtpPass, setShowSmtpPass] = useState(false);
  const [emailLogs, setEmailLogs] = useState<EmailLog[]>([]);
  const [emailLogStats, setEmailLogStats] = useState<EmailLogStats | null>(null);
  const [emailLogsLoading, setEmailLogsLoading] = useState(false);
  
  // Push subscriptions
  const [subscriptions, setSubscriptions] = useState<PushSubscription[]>([]);
  const { isSupported, isSubscribed, isLoading: pushLoading, subscribe, unsubscribe } = useWebpushrSubscription();

  // Load settings from API on mount
  useEffect(() => {
    let cancelled = false;
    const load = async () => {
      try {
        const settings = await fetchSettings();
        if (!cancelled) {
          setPersonal(settings.personal || defaultPersonal);
          setAppearance(settings.appearance || defaultAppearance);
          setGeneral(settings.general || defaultGeneral);
        }
      } catch (err: any) {
        if (!cancelled) setLoadError(err.message);
      }
    };
    
    const loadEmail = async () => {
      try {
        const email = await fetchEmailSettings();
        if (!cancelled) {
          setEmailSettings(email);
        }
      } catch (err) {
        console.error('Failed to load email settings:', err);
      }
    };
    
    load();
    loadEmail();
    return () => { cancelled = true; };
  }, []);

  // Load push subscriptions
  useEffect(() => {
    let cancelled = false;
    const loadSubs = async () => {
      try {
        const subs = await fetchPushSubscriptions('dashboard');
        if (!cancelled) setSubscriptions(subs.filter((s: PushSubscription) => s.is_active === 1));
      } catch (err) {
        console.error('Failed to load push subscriptions:', err);
      }
    };
    if (general.notifications_enabled) loadSubs();
    return () => { cancelled = true; };
  }, [general.notifications_enabled]);

  const saveAll = useCallback(async () => {
    setSaving(true);
    try {
      await saveSettings({ personal, appearance, general });
      setLastSaved(new Date().toLocaleTimeString());
    } catch (err: any) {
      console.error('Failed to save settings:', err);
    } finally {
      setSaving(false);
    }
  }, [personal, appearance, general]);

  const saveIntegration = async () => {
    setSaving(true);
    try {
      await apiClient.post('/api/telegram_settings.php?action=set_webhook', {
        webhook_url: telegramWebhook,
      });
      setLastSaved(new Date().toLocaleTimeString());
    } catch (err: any) {
      console.error('Failed to save integration settings:', err);
    } finally {
      setSaving(false);
    }
  };

  // Auto-save on changes
  useEffect(() => {
    const timer = setTimeout(() => saveAll(), 2000);
    return () => clearTimeout(timer);
  }, [personal, appearance, general, saveAll]);

  const handlePersonalChange = (field: keyof UserSettings['personal'], value: string) => {
    setPersonal(prev => ({ ...prev, [field]: value }));
  };

  const handleAppearanceChange = (field: keyof UserSettings['appearance'], value: string | boolean) => {
    setAppearance(prev => ({ ...prev, [field]: value }));
  };

  const handleGeneralChange = async (field: keyof UserSettings['general'], value: any) => {
    if (field === 'notifications_enabled') {
      if (value) {
        // Subscribe to push notifications
        await subscribe();
      } else {
        // Unsubscribe from push notifications
        await unsubscribe();
      }
    }
    setGeneral(prev => ({ ...prev, [field]: value }));
  };

  const handleUnsubscribeDevice = async (subscriptionId: number) => {
    try {
      await unsubscribeDevice(subscriptionId);
      setSubscriptions(prev => prev.filter(s => s.id !== subscriptionId));
    } catch (err: any) {
      console.error('Failed to unsubscribe device:', err);
    }
  };

  const handleEmailSave = async () => {
    setEmailSaving(true);
    setEmailTestResult(null);
    try {
      await saveEmailSettings(emailSettings);
      setEmailTestResult({ success: true, message: 'Email settings saved successfully' });
    } catch (err: any) {
      setEmailTestResult({ success: false, message: err.message || 'Failed to save email settings' });
    } finally {
      setEmailSaving(false);
    }
  };

  const handleEmailTest = async () => {
    setEmailTestLoading(true);
    setEmailTestResult(null);
    try {
      const result = await testEmailSettings(emailSettings.admin_email_1);
      setEmailTestResult({ success: true, message: result.message || 'Test email sent successfully' });
    } catch (err: any) {
      setEmailTestResult({ success: false, message: err.message || 'Failed to send test email' });
    } finally {
      setEmailTestLoading(false);
    }
  };

  const handleEmailChange = (field: keyof EmailSettings, value: string) => {
    setEmailSettings(prev => ({ ...prev, [field]: value }));
  };

  const loadEmailLogs = useCallback(async () => {
    setEmailLogsLoading(true);
    try {
      const [logsRes, statsRes] = await Promise.all([
        fetchEmailLogs(50),
        fetchEmailLogStats()
      ]);
      setEmailLogs(logsRes.logs || []);
      setEmailLogStats(statsRes);
    } catch (err) {
      console.error('Failed to load email logs:', err);
    } finally {
      setEmailLogsLoading(false);
    }
  }, []);

  useEffect(() => {
    if (tab === 3) loadEmailLogs();
  }, [tab, loadEmailLogs]);

  const handleClearEmailLogs = async () => {
    try {
      await clearEmailLogs();
      setEmailLogs([]);
      setEmailLogStats(null);
    } catch (err) {
      console.error('Failed to clear email logs:', err);
    }
  };

  const getDeviceIcon = (deviceType: string) => {
    switch (deviceType) {
      case 'mobile': return <Smartphone fontSize="small" />;
      case 'tablet': return <Tablet fontSize="small" />;
      default: return <Laptop fontSize="small" />;
    }
  };

  return (
    <Box>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Global Settings
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Configure system behavior, API integrations, and personal preferences.
          </Typography>
        </Box>
        {lastSaved && (
          <Chip 
            size="small" 
            icon={saving ? <Refresh sx={{ animation: 'spin 1s linear infinite' }} /> : <CheckCircle />} 
            label={saving ? 'Saving...' : `Saved at ${lastSaved}`} 
            color={saving ? 'default' : 'success'} 
            variant="outlined"
            sx={{ fontSize: '0.7rem', height: 24 }}
          />
        )}
      </Box>

      <Card>
        <Box sx={{ borderBottom: 1, borderColor: 'divider', px: 2 }}>
          <Tabs value={tab} onChange={(_, v) => setTab(v)}>
            <Tab icon={<Person sx={{ fontSize: 18 }} />} iconPosition="start" label="Personal Info" />
            <Tab icon={<Palette sx={{ fontSize: 18 }} />} iconPosition="start" label="Appearance" />
            <Tab icon={<SettingsIcon sx={{ fontSize: 18 }} />} iconPosition="start" label="General" />
            <Tab icon={<Email sx={{ fontSize: 18 }} />} iconPosition="start" label="Email" />
            <Tab icon={<Api sx={{ fontSize: 18 }} />} iconPosition="start" label="API & Integration" />
            <Tab icon={<Security sx={{ fontSize: 18 }} />} iconPosition="start" label="Access Control" />
            <Tab icon={<Info sx={{ fontSize: 18 }} />} iconPosition="start" label="About" />
          </Tabs>
        </Box>
        
        <CardContent sx={{ p: 3 }}>
          <TabPanel value={tab} index={0}>
            <Grid container spacing={4}>
              <Grid size={{ xs: 12, md: 6 }}>
                <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 2 }}>Personal Information</Typography>
                <Typography variant="caption" sx={{ color: 'text.disabled', display: 'block', mb: 2 }}>This info is stored locally in your browser and used for task assignments and notifications.</Typography>
                <Box sx={{ display: 'flex', gap: 3, mb: 3 }}>
                  <Avatar sx={{ width: 80, height: 80, fontSize: '2rem', bgcolor: 'primary.main' }}>
                    {personal.full_name ? personal.full_name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) : '?'}
                  </Avatar>
                  <Box sx={{ display: 'flex', flexDirection: 'column', gap: 0.5 }}>
                    <Typography variant="body2" sx={{ fontWeight: 600 }}>{personal.full_name || 'No name set'}</Typography>
                    <Typography variant="caption" sx={{ color: 'text.disabled' }}>{personal.email || 'No email set'}</Typography>
                    <Typography variant="caption" sx={{ color: 'text.disabled' }}>{personal.phone || 'No phone set'}</Typography>
                  </Box>
                </Box>
                <Box sx={{ display: 'grid', gap: 2 }}>
                  <TextField 
                    label="Full Name" 
                    size="small" 
                    value={personal.full_name}
                    onChange={(e) => handlePersonalChange('full_name', e.target.value)}
                    placeholder="e.g., Mounir Abderrahmani"
                  />
                  <TextField 
                    label="Email" 
                    size="small" 
                    type="email"
                    value={personal.email}
                    onChange={(e) => handlePersonalChange('email', e.target.value)}
                    placeholder="e.g., mounir@technostationery.com"
                  />
                  <TextField 
                    label="Phone" 
                    size="small" 
                    value={personal.phone}
                    onChange={(e) => handlePersonalChange('phone', e.target.value)}
                    placeholder="e.g., +213 555 123 456"
                  />
                </Box>
              </Grid>
            </Grid>
          </TabPanel>

          <TabPanel value={tab} index={1}>
            <Grid container spacing={4}>
              <Grid size={{ xs: 12, md: 6 }}>
                <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 2 }}>Theme & Display</Typography>
                <Box sx={{ display: 'grid', gap: 2 }}>
                  <FormControl fullWidth size="small">
                    <InputLabel>Theme</InputLabel>
                    <Select 
                      value={appearance.theme} 
                      label="Theme"
                      onChange={(e) => handleAppearanceChange('theme', e.target.value)}
                    >
                      <MenuItem value="dark">Dark</MenuItem>
                      <MenuItem value="light">Light</MenuItem>
                      <MenuItem value="auto">Auto (System)</MenuItem>
                    </Select>
                  </FormControl>
                  <FormControl fullWidth size="small">
                    <InputLabel>Font Size</InputLabel>
                    <Select 
                      value={appearance.font_size} 
                      label="Font Size"
                      onChange={(e) => handleAppearanceChange('font_size', e.target.value)}
                    >
                      <MenuItem value="small">Small</MenuItem>
                      <MenuItem value="medium">Medium</MenuItem>
                      <MenuItem value="large">Large</MenuItem>
                    </Select>
                  </FormControl>
                  <FormControlLabel
                    control={<Switch checked={appearance.animations} onChange={(e) => handleAppearanceChange('animations', e.target.checked)} />}
                    label={<Box><Typography variant="body2" sx={{ fontWeight: 600 }}>Enable Animations</Typography><Typography variant="caption" color="text.disabled">Smooth transitions and loading animations</Typography></Box>}
                  />
                  <FormControl fullWidth size="small">
                    <InputLabel>Language</InputLabel>
                    <Select 
                      value={appearance.language} 
                      label="Language"
                      onChange={(e) => handleAppearanceChange('language', e.target.value)}
                    >
                      <MenuItem value="en">English</MenuItem>
                      <MenuItem value="fr">Français</MenuItem>
                    </Select>
                  </FormControl>
                </Box>
              </Grid>
              <Grid size={{ xs: 12, md: 6 }}>
                <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 2 }}>Preview</Typography>
                <Card sx={{ p: 2, bgcolor: appearance.theme === 'dark' ? '#0b0f1a' : '#f5f5f5', border: '1px solid', borderColor: 'divider' }}>
                  <Typography variant="h6" sx={{ fontWeight: 800, mb: 1 }}>Sample Heading</Typography>
                  <Typography variant="body2" sx={{ mb: 2 }}>This is how text appears with your chosen settings.</Typography>
                  <Button size="small" variant="contained">Sample Button</Button>
                </Card>
              </Grid>
            </Grid>
          </TabPanel>

          <TabPanel value={tab} index={2}>
            <Grid container spacing={4}>
              <Grid size={{ xs: 12, md: 6 }}>
                <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 2 }}>User Interface</Typography>
                <Box sx={{ display: 'grid', gap: 2 }}>
                  <FormControlLabel
                    control={<Switch checked={general.auto_refresh} onChange={(e) => handleGeneralChange('auto_refresh', e.target.checked)} />}
                    label={<Box><Typography variant="body2" sx={{ fontWeight: 600 }}>Real-time Data Polling</Typography><Typography variant="caption" color="text.disabled">Automatically refresh stats every X seconds</Typography></Box>}
                  />
                  <TextField 
                    label="Polling Interval (sec)" 
                    size="small" 
                    type="number" 
                    value={general.refresh_interval}
                    onChange={(e) => handleGeneralChange('refresh_interval', parseInt(e.target.value))}
                    sx={{ width: 160 }}
                  />
                  <FormControlLabel
                    control={<Switch checked={general.debug_mode} color="warning" onChange={(e) => handleGeneralChange('debug_mode', e.target.checked)} />}
                    label={<Box><Typography variant="body2" sx={{ fontWeight: 600 }}>Developer Debug Mode</Typography><Typography variant="caption" color="text.disabled">Show raw API responses and console logs</Typography></Box>}
                  />
                </Box>
              </Grid>
              <Grid size={{ xs: 12, md: 6 }}>
                <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 2 }}>System Notifications</Typography>
                <Box sx={{ display: 'grid', gap: 2 }}>
                  <FormControlLabel
                    control={<Switch checked={general.notifications_enabled} onChange={(e) => handleGeneralChange('notifications_enabled', e.target.checked)} disabled={pushLoading} />}
                    label={<Box><Typography variant="body2" sx={{ fontWeight: 600 }}>Browser Push Alerts</Typography><Typography variant="caption" color="text.disabled">Receive critical server alerts via Webpushr {isSupported ? '(Supported)' : '(Not supported)'}</Typography></Box>}
                  />
                  <Button variant="outlined" size="small" sx={{ alignSelf: 'flex-start' }} onClick={() => {
                    if (navigator.serviceWorker) {
                      navigator.serviceWorker.getRegistrations().then(regs => regs.forEach(reg => reg.unregister()));
                    }
                  }}>Reset Service Worker</Button>
                </Box>
                
                {general.notifications_enabled && subscriptions.length > 0 && (
                  <Box sx={{ mt: 3 }}>
                    <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1 }}>Push Subscription Devices</Typography>
                    <Typography variant="caption" sx={{ color: 'text.disabled', display: 'block', mb: 2 }}>Devices subscribed to receive dashboard alerts</Typography>
                    <TableContainer component={Paper} variant="outlined" sx={{ border: '1px solid', borderColor: 'divider' }}>
                      <Table size="small">
                        <TableHead>
                          <TableRow>
                            <TableCell>Device</TableCell>
                            <TableCell>Browser</TableCell>
                            <TableCell>OS</TableCell>
                            <TableCell>Last Active</TableCell>
                            <TableCell align="right">Action</TableCell>
                          </TableRow>
                        </TableHead>
                        <TableBody>
                          {subscriptions.map((sub) => (
                            <TableRow key={sub.id}>
                              <TableCell>
                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                  {getDeviceIcon(sub.device_type)}
                                  <Typography variant="body2">{sub.device_id || 'Device'}</Typography>
                                </Box>
                              </TableCell>
                              <TableCell>{sub.browser}</TableCell>
                              <TableCell>{sub.os}</TableCell>
                              <TableCell>{new Date(sub.last_used).toLocaleDateString()}</TableCell>
                              <TableCell align="right">
                                <Button size="small" color="error" startIcon={<Delete />} onClick={() => handleUnsubscribeDevice(sub.id)}>
                                  Remove
                                </Button>
                              </TableCell>
                            </TableRow>
                          ))}
                        </TableBody>
                      </Table>
                    </TableContainer>
                  </Box>
                )}
              </Grid>
            </Grid>
          </TabPanel>

          <TabPanel value={tab} index={3}>
            <Grid container spacing={4}>
              <Grid size={{ xs: 12, md: 6 }}>
                <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 2 }}>Email Configuration</Typography>
                <Typography variant="caption" sx={{ color: 'text.disabled', display: 'block', mb: 2 }}>Configure sender email and admin recipients for system notifications</Typography>
                <Box sx={{ display: 'grid', gap: 2 }}>
                  <TextField
                    label="From Email"
                    size="small"
                    value={emailSettings.from_email}
                    onChange={(e) => handleEmailChange('from_email', e.target.value)}
                    placeholder="e.g., alerts@dashboard.technostationery.com"
                  />
                  <TextField
                    label="From Name"
                    size="small"
                    value={emailSettings.from_name}
                    onChange={(e) => handleEmailChange('from_name', e.target.value)}
                    placeholder="e.g., Techno Dashboard"
                  />
                  <FormControlLabel
                    control={
                      <Switch
                        checked={emailSettings.enabled === 'true'}
                        onChange={(e) => handleEmailChange('enabled', e.target.checked ? 'true' : 'false')}
                      />
                    }
                    label={<Box><Typography variant="body2" sx={{ fontWeight: 600 }}>Enable Email Notifications</Typography><Typography variant="caption" color="text.disabled">Send email alerts for critical events</Typography></Box>}
                  />
                </Box>
              </Grid>
              <Grid size={{ xs: 12, md: 6 }}>
                <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 2 }}>Admin Recipients</Typography>
                <Typography variant="caption" sx={{ color: 'text.disabled', display: 'block', mb: 2 }}>Email addresses that receive admin notifications</Typography>
                <Box sx={{ display: 'grid', gap: 2 }}>
                  <TextField
                    label="Primary Admin Email"
                    size="small"
                    value={emailSettings.admin_email_1}
                    onChange={(e) => handleEmailChange('admin_email_1', e.target.value)}
                    placeholder="e.g., admin@dashboard.technostationery.com"
                    slotProps={{
                      input: {
                        startAdornment: (
                          <InputAdornment position="start">
                            <AdminPanelSettings sx={{ fontSize: 18, color: 'primary.main' }} />
                          </InputAdornment>
                        ),
                      }
                    }}
                  />
                  <TextField
                    label="Secondary Admin Email"
                    size="small"
                    value={emailSettings.admin_email_2}
                    onChange={(e) => handleEmailChange('admin_email_2', e.target.value)}
                    placeholder="e.g., webmaster@techno-dz.com"
                  />
                  <Box sx={{ display: 'flex', gap: 2, mt: 2 }}>
                    <Button
                      variant="contained"
                      startIcon={saving || emailSaving ? <Refresh sx={{ animation: 'spin 1s linear infinite' }} /> : <Save />}
                      onClick={handleEmailSave}
                      disabled={emailSaving}
                    >
                      {emailSaving ? 'Saving...' : 'Save Settings'}
                    </Button>
                    <Button
                      variant="outlined"
                      startIcon={<Send />}
                      onClick={handleEmailTest}
                      disabled={emailTestLoading}
                    >
                      {emailTestLoading ? 'Sending...' : 'Send Test'}
                    </Button>
                  </Box>
                  {emailTestResult && (
                    <Alert
                      severity={emailTestResult.success ? 'success' : 'error'}
                      sx={{ mt: 2 }}
                      onClose={() => setEmailTestResult(null)}
                    >
                      {emailTestResult.message}
                    </Alert>
                  )}
                </Box>
              </Grid>
            </Grid>

            <Divider sx={{ my: 4 }} />

            <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1 }}>
              <Lock sx={{ fontSize: 16, verticalAlign: 'middle', mr: 0.5 }} />
              SMTP Configuration
            </Typography>
            <Typography variant="caption" sx={{ color: 'text.disabled', display: 'block', mb: 2 }}>
              Optional: configure an external SMTP server for reliable email delivery. Leave empty to use the server's built-in mail system.
            </Typography>
            <Grid container spacing={3}>
              <Grid size={{ xs: 12, md: 4 }}>
                <TextField
                  label="SMTP Host"
                  size="small"
                  fullWidth
                  value={emailSettings.smtp_host}
                  onChange={(e) => handleEmailChange('smtp_host', e.target.value)}
                  placeholder="e.g., smtp.gmail.com"
                />
              </Grid>
              <Grid size={{ xs: 12, md: 2 }}>
                <TextField
                  label="Port"
                  size="small"
                  fullWidth
                  value={emailSettings.smtp_port}
                  onChange={(e) => handleEmailChange('smtp_port', e.target.value)}
                  placeholder="587"
                />
              </Grid>
              <Grid size={{ xs: 12, md: 3 }}>
                <FormControl fullWidth size="small">
                  <InputLabel>Encryption</InputLabel>
                  <Select
                    value={emailSettings.smtp_encryption}
                    label="Encryption"
                    onChange={(e) => handleEmailChange('smtp_encryption', e.target.value)}
                  >
                    <MenuItem value="tls">STARTTLS</MenuItem>
                    <MenuItem value="ssl">SSL/TLS</MenuItem>
                    <MenuItem value="none">None</MenuItem>
                  </Select>
                </FormControl>
              </Grid>
              <Grid size={{ xs: 12, md: 3 }} />
              <Grid size={{ xs: 12, md: 4 }}>
                <TextField
                  label="SMTP Username"
                  size="small"
                  fullWidth
                  value={emailSettings.smtp_user}
                  onChange={(e) => handleEmailChange('smtp_user', e.target.value)}
                  placeholder="e.g., alerts@dashboard.technostationery.com"
                />
              </Grid>
              <Grid size={{ xs: 12, md: 4 }}>
                <TextField
                  label="SMTP Password"
                  size="small"
                  fullWidth
                  type={showSmtpPass ? 'text' : 'password'}
                  value={emailSettings.smtp_pass}
                  onChange={(e) => handleEmailChange('smtp_pass', e.target.value)}
                  placeholder={emailSettings.smtp_pass_set ? '(saved - enter new to change)' : 'Optional'}
                  slotProps={{
                    input: {
                      endAdornment: (
                        <InputAdornment position="end">
                          <IconButton onClick={() => setShowSmtpPass(!showSmtpPass)} edge="end" size="small">
                            {showSmtpPass ? <VisibilityOff fontSize="small" /> : <Visibility fontSize="small" />}
                          </IconButton>
                        </InputAdornment>
                      ),
                    }
                  }}
                />
              </Grid>
            </Grid>
            {emailSettings.smtp_host && (
              <Alert severity="info" sx={{ mt: 2, fontSize: '0.75rem' }}>
                SMTP delivery will be attempted first via <strong>{emailSettings.smtp_host}:{emailSettings.smtp_port}</strong>, with fallback to server mail if it fails.
              </Alert>
            )}

            <Divider sx={{ my: 4 }} />

            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
              <Box>
                <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>Email Notification Log</Typography>
                <Typography variant="caption" sx={{ color: 'text.disabled' }}>Recent email delivery history for debugging</Typography>
              </Box>
              <Box sx={{ display: 'flex', gap: 1 }}>
                <Button size="small" startIcon={emailLogsLoading ? <Refresh sx={{ animation: 'spin 1s linear infinite' }} /> : <Refresh />} onClick={loadEmailLogs} disabled={emailLogsLoading}>
                  Refresh
                </Button>
                {emailLogs.length > 0 && (
                  <Button size="small" color="error" variant="outlined" startIcon={<Delete />} onClick={handleClearEmailLogs}>
                    Clear
                  </Button>
                )}
              </Box>
            </Box>

            {emailLogStats && (
              <Box sx={{ display: 'flex', gap: 2, mb: 2 }}>
                <Chip size="small" label={`Total: ${emailLogStats.total}`} variant="outlined" />
                <Chip size="small" icon={<CheckCircle />} label={`Success: ${emailLogStats.success}`} color="success" variant="outlined" />
                <Chip size="small" icon={<ErrorOutlined />} label={`Failed: ${emailLogStats.failed}`} color="error" variant="outlined" />
              </Box>
            )}

            {emailLogs.length === 0 ? (
              <Alert severity="info" sx={{ fontSize: '0.8rem' }}>No email notifications logged yet.</Alert>
            ) : (
              <TableContainer component={Paper} variant="outlined" sx={{ border: '1px solid', borderColor: 'divider', maxHeight: 400 }}>
                <Table size="small" stickyHeader>
                  <TableHead>
                    <TableRow>
                      <TableCell sx={{ fontWeight: 700 }}>Time</TableCell>
                      <TableCell sx={{ fontWeight: 700 }}>Type</TableCell>
                      <TableCell sx={{ fontWeight: 700 }}>To</TableCell>
                      <TableCell sx={{ fontWeight: 700 }}>Subject</TableCell>
                      <TableCell sx={{ fontWeight: 700 }}>Status</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {emailLogs.map((log, idx) => (
                      <TableRow key={idx} hover>
                        <TableCell sx={{ fontSize: '0.7rem', whiteSpace: 'nowrap' }}>{log.timestamp}</TableCell>
                        <TableCell>
                          <Chip size="small" label={log.type} variant="outlined" sx={{ fontSize: '0.65rem', height: 20 }} />
                        </TableCell>
                        <TableCell sx={{ fontSize: '0.75rem' }}>{log.to}</TableCell>
                        <TableCell sx={{ fontSize: '0.75rem', maxWidth: 250, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{log.subject}</TableCell>
                        <TableCell>
                          {log.success ? (
                            <Chip size="small" icon={<CheckCircle />} label="Sent" color="success" sx={{ fontSize: '0.65rem', height: 20 }} />
                          ) : (
                            <Chip size="small" icon={<ErrorOutlined />} label={log.error || 'Failed'} color="error" sx={{ fontSize: '0.65rem', height: 20 }} />
                          )}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </TableContainer>
            )}
          </TabPanel>

          <TabPanel value={tab} index={4}>
            <Box sx={{ maxWidth: 600 }}>
              <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 2 }}>External API Tokens</Typography>
              <TextField 
                fullWidth 
                label="Master Access Token" 
                size="small" 
                type={showKey ? 'text' : 'password'}
                value={apiToken}
                onChange={(e) => setApiToken(e.target.value)}
                sx={{ mb: 3 }}
                slotProps={{
                  input: {
                    endAdornment: (
                      <InputAdornment position="end">
                        <IconButton onClick={() => setShowKey(!showKey)} edge="end">
                          {showKey ? <VisibilityOff /> : <Visibility />}
                        </IconButton>
                      </InputAdornment>
                    ),
                    sx: { fontFamily: 'monospace' }
                  }
                }}
              />
              
              <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1 }}>Telegram Webhook URL</Typography>
              <Typography variant="caption" sx={{ color: 'text.disabled', display: 'block', mb: 1 }}>The destination for all Telegram bot events</Typography>
              <TextField 
                fullWidth 
                size="small" 
                value={telegramWebhook}
                onChange={(e) => setTelegramWebhook(e.target.value)}
                sx={{ mb: 3, '& .MuiInputBase-input': { fontSize: '0.75rem', fontFamily: 'monospace' } }}
              />
              
              <Button variant="contained" onClick={saveIntegration} disabled={saving}>Save Integration Settings</Button>
            </Box>
          </TabPanel>

          <TabPanel value={tab} index={5}>
            <Grid container spacing={3}>
              <Grid size={{ xs: 12, md: 6 }}>
                <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 2 }}>Session &amp; Access</Typography>
                <List sx={{ border: '1px solid', borderColor: 'divider', borderRadius: 2 }}>
                  <ListItem divider>
                    <ListItemText
                      primary={<Typography variant="body2" sx={{ fontWeight: 600 }}>Session Timeout</Typography>}
                      secondary="Sessions expire after 24 hours of inactivity"
                    />
                    <Chip label="24h" size="small" color="info" variant="outlined" />
                  </ListItem>
                  <ListItem divider>
                    <ListItemText
                      primary={<Typography variant="body2" sx={{ fontWeight: 600 }}>Two-Factor Authentication</Typography>}
                      secondary="Required for Admin role on every login"
                    />
                    <Chip icon={<AdminPanelSettings sx={{ fontSize: 14 }} />} label="ENABLED" size="small" color="success" />
                  </ListItem>
                  <ListItem>
                    <ListItemText
                      primary={<Typography variant="body2" sx={{ fontWeight: 600 }}>Cookie Security</Typography>}
                      secondary="HttpOnly + SameSite=Strict + Secure"
                    />
                    <Chip icon={<Lock sx={{ fontSize: 14 }} />} label="HARDENED" size="small" color="primary" />
                  </ListItem>
                </List>
              </Grid>
              <Grid size={{ xs: 12, md: 6 }}>
                <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 2 }}>Role Summary</Typography>
                <Box sx={{ display: 'grid', gap: 1 }}>
                  {[
                    { role: 'admin',      label: 'Administrator', color: '#ef4444', note: 'Full access — all pages + destructive ops' },
                    { role: 'editor',     label: 'Editor',        color: '#f59e0b', note: 'Commerce, logs, tasks, push notifications' },
                    { role: 'viewer',     label: 'Viewer',         color: '#3b82f6', note: 'Read-only — monitoring + cloudflare' },
                  ].map(({ role, label, color, note }) => (
                    <Box key={role} sx={{ p: 1.5, borderRadius: 1.5, border: '1px solid', borderColor: 'divider', background: 'rgba(255,255,255,0.02)' }}>
                      <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 0.5 }}>
                        <Box sx={{ width: 8, height: 8, borderRadius: '50%', backgroundColor: color }} />
                        <Typography variant="body2" sx={{ fontWeight: 700 }}>{label}</Typography>
                        <Chip label={role} size="small" variant="outlined" sx={{ fontSize: '0.6rem', height: 18, ml: 'auto' }} />
                      </Box>
                      <Typography variant="caption" color="text.disabled">{note}</Typography>
                    </Box>
                  ))}
                </Box>
              </Grid>
            </Grid>
          </TabPanel>

          <TabPanel value={tab} index={6}>
            <Grid container spacing={3}>
              <Grid size={{ xs: 12, md: 6 }}>
                <Box sx={{ p: 2, backgroundColor: 'background.default', borderRadius: 2, border: '1px solid', borderColor: 'divider' }}>
                  <Typography variant="h6" sx={{ fontWeight: 800, color: 'primary.main', mb: 1 }}>Techno Monitor</Typography>
                  <Typography variant="body2" sx={{ mb: 2 }}>The comprehensive infrastructure management platform for TechnoStationery e-commerce systems.</Typography>
                  <Box sx={{ display: 'grid', gap: 0.5 }}>
                    <Typography variant="caption" sx={{ color: 'text.disabled' }}>Platform Version: <strong>v4.3.0-TSM</strong></Typography>
                    <Typography variant="caption" sx={{ color: 'text.disabled' }}>Build Hash: <strong>c0934e53</strong></Typography>
                    <Typography variant="caption" sx={{ color: 'text.disabled' }}>Deployment Date: <strong>July 8, 2026</strong></Typography>
                  </Box>
                </Box>
              </Grid>
              <Grid size={{ xs: 12, md: 6 }}>
                <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1 }}>System Dependencies</Typography>
                <List disablePadding>
                  {['PHP 8.2.30', 'MariaDB 10.6.17', 'Redis 5.0.3', 'Varnish 6.0', 'Node.js 20.x', 'Magento 2.4.7-p3', 'React 18.3', 'Vite 8.0'].map(dep => (
                    <ListItem key={dep} sx={{ py: 0.5, px: 0 }}>
                      <ListItemText 
                        primary={<Typography sx={{ fontSize: '0.75rem', fontWeight: 600 }}>{dep}</Typography>} 
                      />
                      <CheckCircle sx={{ color: 'success.main', fontSize: 16 }} />
                    </ListItem>
                  ))}
                </List>
              </Grid>
            </Grid>
          </TabPanel>
        </CardContent>
        
        <Divider />
        <Box sx={{ p: 2, display: 'flex', justifyContent: 'flex-end', gap: 2 }}>
          <Button variant="outlined" color="inherit" onClick={() => { setPersonal(defaultPersonal); setAppearance(defaultAppearance); setGeneral(defaultGeneral); }}>Discard Changes</Button>
          <Button variant="contained" onClick={saveAll} disabled={saving}>Apply Global Settings</Button>
        </Box>
      </Card>
    </Box>
  );
}

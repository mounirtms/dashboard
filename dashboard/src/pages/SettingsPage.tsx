import { Box, Typography, Grid, Card, CardContent, Switch, FormControlLabel, TextField, Button, Divider, Alert, Tabs, Tab, List, ListItem, ListItemText, InputAdornment, IconButton, Chip, Select, MenuItem, FormControl, InputLabel, Avatar } from '@mui/material';
import { Settings as SettingsIcon, Notifications, Security, Storage, Language, Api, Visibility, VisibilityOff, Code, Info, Refresh, CheckCircle, Person, Palette, Save } from '@mui/icons-material';
import { useState, useEffect, useCallback, useRef } from 'react';

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

interface PersonalInfo {
  full_name: string;
  email: string;
  phone: string;
}

interface AppearanceSettings {
  theme: string;
  font_size: string;
  animations: boolean;
  language: string;
}

interface GeneralSettings {
  notifications_enabled: boolean;
  auto_refresh: boolean;
  refresh_interval: number;
  debug_mode: boolean;
}

const defaultPersonal: PersonalInfo = { full_name: '', email: '', phone: '' };
const defaultAppearance: AppearanceSettings = { theme: 'dark', font_size: 'medium', animations: true, language: 'en' };
const defaultGeneral: GeneralSettings = { notifications_enabled: true, auto_refresh: true, refresh_interval: 30, debug_mode: false };

export default function SettingsPage() {
  const [tab, setTab] = useState(0);
  const [showKey, setShowKey] = useState(false);
  const [apiToken, setApiToken] = useState('••••••••••••••••••••••••••••••••');
  const [telegramWebhook, setTelegramWebhook] = useState('https://dashboard.technostationery.com/api/telegram/webhook.php');
  const [personal, setPersonal] = useState<PersonalInfo>(() => {
    const saved = localStorage.getItem('dashboard_personal_info');
    return saved ? JSON.parse(saved) : defaultPersonal;
  });
  const [appearance, setAppearance] = useState<AppearanceSettings>(() => {
    const saved = localStorage.getItem('dashboard_appearance');
    return saved ? JSON.parse(saved) : defaultAppearance;
  });
  const [general, setGeneral] = useState<GeneralSettings>(() => {
    const saved = localStorage.getItem('dashboard_general_settings');
    return saved ? JSON.parse(saved) : defaultGeneral;
  });
  const [lastSaved, setLastSaved] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const saveTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const saveToStorage = useCallback((key: string, value: any) => {
    setSaving(true);
    localStorage.setItem(key, JSON.stringify(value));
    const now = new Date().toLocaleTimeString();
    setLastSaved(now);
    setTimeout(() => setSaving(false), 500);
  }, []);

  const debouncedSave = useCallback((key: string, value: any) => {
    if (saveTimerRef.current) clearTimeout(saveTimerRef.current);
    saveTimerRef.current = setTimeout(() => saveToStorage(key, value), 2000);
  }, [saveToStorage]);

  useEffect(() => {
    debouncedSave('dashboard_personal_info', personal);
    return () => { if (saveTimerRef.current) clearTimeout(saveTimerRef.current); };
  }, [personal, debouncedSave]);

  useEffect(() => {
    debouncedSave('dashboard_appearance', appearance);
    return () => { if (saveTimerRef.current) clearTimeout(saveTimerRef.current); };
  }, [appearance, debouncedSave]);

  useEffect(() => {
    debouncedSave('dashboard_general_settings', general);
    return () => { if (saveTimerRef.current) clearTimeout(saveTimerRef.current); };
  }, [general, debouncedSave]);

  const handlePersonalChange = (field: keyof PersonalInfo, value: string) => {
    setPersonal(prev => ({ ...prev, [field]: value }));
  };

  const handleAppearanceChange = (field: keyof AppearanceSettings, value: string | boolean) => {
    setAppearance(prev => ({ ...prev, [field]: value }));
  };

  const handleGeneralChange = (field: keyof GeneralSettings, value: any) => {
    setGeneral(prev => ({ ...prev, [field]: value }));
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
                    control={<Switch checked={general.notifications_enabled} onChange={(e) => handleGeneralChange('notifications_enabled', e.target.checked)} />}
                    label={<Box><Typography variant="body2" sx={{ fontWeight: 600 }}>Browser Push Alerts</Typography><Typography variant="caption" color="text.disabled">Receive critical server alerts via Webpushr</Typography></Box>}
                  />
                  <Button variant="outlined" size="small" sx={{ alignSelf: 'flex-start' }}>Reset Service Worker</Button>
                </Box>
              </Grid>
            </Grid>
          </TabPanel>

          <TabPanel value={tab} index={3}>
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
              
              <Button variant="contained">Save Integration Settings</Button>
            </Box>
          </TabPanel>

          <TabPanel value={tab} index={4}>
            <Alert severity="info" sx={{ mb: 3 }}>Session security and IP filtering settings.</Alert>
            <List sx={{ border: '1px solid', borderColor: 'divider', borderRadius: 2 }}>
              <ListItem divider>
                <ListItemText primary="Current IP Address" secondary="102.156.42.89 (Algeria)" />
                <Chip label="WHITELISTED" size="small" color="success" />
              </ListItem>
              <ListItem divider>
                <ListItemText primary="Session Timeout" secondary="Sessions expire after 24 hours of inactivity" />
                <Button size="small">Edit</Button>
              </ListItem>
              <ListItem>
                <ListItemText primary="Two-Factor Authentication" secondary="Required for Admin role" />
                <Chip label="ENABLED" size="small" color="primary" />
              </ListItem>
            </List>
          </TabPanel>

          <TabPanel value={tab} index={5}>
            <Grid container spacing={3}>
              <Grid size={{ xs: 12, md: 6 }}>
                <Box sx={{ p: 2, backgroundColor: 'background.default', borderRadius: 2, border: '1px solid', borderColor: 'divider' }}>
                  <Typography variant="h6" sx={{ fontWeight: 800, color: 'primary.main', mb: 1 }}>Techno Monitor</Typography>
                  <Typography variant="body2" sx={{ mb: 2 }}>The comprehensive infrastructure management platform for TechnoStationery e-commerce systems.</Typography>
                  <Box sx={{ display: 'grid', gap: 0.5 }}>
                    <Typography variant="caption" sx={{ color: 'text.disabled' }}>Platform Version: <strong>v3.1.5-TSM</strong></Typography>
                    <Typography variant="caption" sx={{ color: 'text.disabled' }}>Build Hash: <strong>rX82jL299a</strong></Typography>
                    <Typography variant="caption" sx={{ color: 'text.disabled' }}>Deployment Date: <strong>May 6, 2026</strong></Typography>
                  </Box>
                </Box>
              </Grid>
              <Grid size={{ xs: 12, md: 6 }}>
                <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1 }}>System Dependencies</Typography>
                <List disablePadding>
                  {['PHP 8.2.30', 'MariaDB 10.6', 'Redis 7.0', 'Varnish 6.0', 'Node.js 20.x'].map(dep => (
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
          <Button variant="outlined" color="inherit">Discard Changes</Button>
          <Button variant="contained">Apply Global Settings</Button>
        </Box>
      </Card>
    </Box>
  );
}

import { Box, Typography, Grid, Card, CardContent, Switch, FormControlLabel, TextField, Button, Divider, Alert, Tabs, Tab, List, ListItem, ListItemText, InputAdornment, IconButton, Chip } from '@mui/material';
import { Settings as SettingsIcon, Notifications, Security, Storage, Language, Api, Visibility, VisibilityOff, Code, Info, Refresh, CheckCircle } from '@mui/icons-material';
import { useState } from 'react';

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

export default function SettingsPage() {
  const [tab, setTab] = useState(0);
  const [showKey, setShowKey] = useState(false);
  const [settings, setSettings] = useState({
    notifications_enabled: true,
    auto_refresh: true,
    refresh_interval: 30,
    theme: 'dark',
    debug_mode: false,
    api_token: '••••••••••••••••••••••••••••••••',
    telegram_webhook: 'https://dashboard.technostationery.com/api/telegram/webhook.php'
  });

  return (
    <Box>
      <Box sx={{ mb: 3 }}>
        <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
          Global Settings
        </Typography>
        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
          Configure system behavior, API integrations, and personal preferences.
        </Typography>
      </Box>

      <Card>
        <Box sx={{ borderBottom: 1, borderColor: 'divider', px: 2 }}>
          <Tabs value={tab} onChange={(_, v) => setTab(v)}>
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
                <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 2 }}>User Interface</Typography>
                <Box sx={{ display: 'grid', gap: 2 }}>
                  <FormControlLabel
                    control={<Switch checked={settings.auto_refresh} onChange={(e) => setSettings({...settings, auto_refresh: e.target.checked})} />}
                    label={<Box><Typography variant="body2" sx={{ fontWeight: 600 }}>Real-time Data Polling</Typography><Typography variant="caption" color="text.disabled">Automatically refresh stats every X seconds</Typography></Box>}
                  />
                  <TextField 
                    label="Polling Interval (sec)" 
                    size="small" 
                    type="number" 
                    value={settings.refresh_interval}
                    onChange={(e) => setSettings({...settings, refresh_interval: parseInt(e.target.value)})}
                    sx={{ width: 160 }}
                  />
                  <FormControlLabel
                    control={<Switch checked={settings.debug_mode} color="warning" onChange={(e) => setSettings({...settings, debug_mode: e.target.checked})} />}
                    label={<Box><Typography variant="body2" sx={{ fontWeight: 600 }}>Developer Debug Mode</Typography><Typography variant="caption" color="text.disabled">Show raw API responses and console logs</Typography></Box>}
                  />
                </Box>
              </Grid>
              <Grid size={{ xs: 12, md: 6 }}>
                <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 2 }}>System Notifications</Typography>
                <Box sx={{ display: 'grid', gap: 2 }}>
                  <FormControlLabel
                    control={<Switch checked={settings.notifications_enabled} onChange={(e) => setSettings({...settings, notifications_enabled: e.target.checked})} />}
                    label={<Box><Typography variant="body2" sx={{ fontWeight: 600 }}>Browser Push Alerts</Typography><Typography variant="caption" color="text.disabled">Receive critical server alerts via Webpushr</Typography></Box>}
                  />
                  <Button variant="outlined" size="small" sx={{ alignSelf: 'flex-start' }}>Reset Service Worker</Button>
                </Box>
              </Grid>
            </Grid>
          </TabPanel>

          <TabPanel value={tab} index={1}>
            <Box sx={{ maxWidth: 600 }}>
              <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 2 }}>External API Tokens</Typography>
              <TextField 
                fullWidth 
                label="Master Access Token" 
                size="small" 
                type={showKey ? 'text' : 'password'}
                value={settings.api_token}
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
                value={settings.telegram_webhook}
                sx={{ mb: 3, '& .MuiInputBase-input': { fontSize: '0.75rem', fontFamily: 'monospace' } }}
              />
              
              <Button variant="contained">Save Integration Settings</Button>
            </Box>
          </TabPanel>

          <TabPanel value={tab} index={2}>
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

          <TabPanel value={tab} index={3}>
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

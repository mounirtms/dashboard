import { Box, Typography, Card, CardContent, TextField, Button, Chip, Alert, IconButton, InputAdornment, Divider, Grid, CircularProgress } from '@mui/material';
import { Save, Visibility, VisibilityOff, Refresh, CheckCircle, Error as ErrorIcon, Api, Wifi, WifiOff, Login, Schedule } from '@mui/icons-material';
import { useState, useEffect } from 'react';
import { fetchMagentoSettings, saveMagentoSettings, testMagentoConnection, fetchMagentoToken, type MagentoSettingsData } from '../api/magentoSettings';
import LoadingState from '../components/common/LoadingState';

const ENV_LABELS: Record<string, string> = { prod: 'Production', beta: 'Beta (Disabled)', tsdnd: 'TSDND', dev: 'Development', pim: 'PIM (Akeneo)' };

interface EnvState {
  base_url: string;
  token: string;
  has_token: boolean;
  username: string;
  password: string;
  token_updated_at: string;
  testing: boolean;
  fetching: boolean;
  testResult: { success: boolean; message: string } | null;
  storeInfo: any;
}

export default function MagentoSettingsPage() {
  const [envs, setEnvs] = useState<Record<string, EnvState>>({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState<{ message: string; severity: 'success' | 'error' } | null>(null);
  const [showTokens, setShowTokens] = useState<Record<string, boolean>>({});
  const [showPasswords, setShowPasswords] = useState<Record<string, boolean>>({});

  useEffect(() => {
    fetchMagentoSettings()
      .then(data => {
        const state: Record<string, EnvState> = {};
        for (const [env, cfg] of Object.entries(data)) {
          state[env] = {
            base_url: cfg.base_url || '',
            token: '',
            has_token: cfg.has_token,
            username: cfg.username || '',
            password: '',
            token_updated_at: cfg.token_updated_at || '',
            testing: false,
            fetching: false,
            testResult: null,
            storeInfo: null,
          };
        }
        setEnvs(state);
      })
      .catch(e => setToast({ message: e.message, severity: 'error' }))
      .finally(() => setLoading(false));
  }, []);

  const handleFetchToken = async (env: string) => {
    const state = envs[env];
    if (!state?.username || !state?.password) {
      setToast({ message: 'Enter username and password first', severity: 'error' });
      return;
    }
    setEnvs(prev => ({ ...prev, [env]: { ...prev[env], fetching: true, testResult: null } }));
    try {
      const result = await fetchMagentoToken(env, state.base_url, state.username, state.password);
      setEnvs(prev => ({
        ...prev,
        [env]: {
          ...prev[env],
          fetching: false,
          has_token: result.success,
          testResult: { success: result.success, message: result.message },
          token_updated_at: result.success ? new Date().toISOString() : prev[env].token_updated_at,
        }
      }));
      if (result.success) {
        setToast({ message: `${ENV_LABELS[env] || env}: ${result.message}`, severity: 'success' });
      }
    } catch (e: any) {
      setEnvs(prev => ({ ...prev, [env]: { ...prev[env], fetching: false, testResult: { success: false, message: e.message } } }));
    }
  };

  const handleTest = async (env: string) => {
    setEnvs(prev => ({ ...prev, [env]: { ...prev[env], testing: true, testResult: null } }));
    try {
      const result = await testMagentoConnection(env, envs[env]?.token || undefined);
      setEnvs(prev => ({
        ...prev,
        [env]: { ...prev[env], testing: false, testResult: { success: result.success, message: result.message }, storeInfo: result.store_info || null }
      }));
    } catch (e: any) {
      setEnvs(prev => ({ ...prev, [env]: { ...prev[env], testing: false, testResult: { success: false, message: e.message } } }));
    }
  };

  const handleSave = async () => {
    setSaving(true);
    const payload: Record<string, { base_url?: string; token?: string; username?: string }> = {};
    for (const [env, state] of Object.entries(envs)) {
      payload[env] = { base_url: state.base_url };
      if (state.token) payload[env].token = state.token;
      if (state.username) payload[env].username = state.username;
    }
    try {
      await saveMagentoSettings(payload);
      setToast({ message: 'Settings saved', severity: 'success' });
    } catch (e: any) {
      setToast({ message: e.message, severity: 'error' });
    } finally {
      setSaving(false);
    }
  };

  const updateEnv = (env: string, field: keyof EnvState, value: any) => {
    setEnvs(prev => ({ ...prev, [env]: { ...prev[env], [field]: value } }));
  };

  if (loading) return <LoadingState message="Loading Magento settings..." />;

  return (
    <Box>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>Magento Settings</Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>Configure API connections — enter admin credentials to auto-generate tokens</Typography>
        </Box>
        <Button variant="contained" startIcon={saving ? <CircularProgress size={16} /> : <Save />} onClick={handleSave} disabled={saving}>
          {saving ? 'Saving...' : 'Save All'}
        </Button>
      </Box>

      {toast && <Alert severity={toast.severity} onClose={() => setToast(null)} sx={{ mb: 2 }}>{toast.message}</Alert>}

      {/* ── Environment health summary ── */}
      <Box sx={{ mb: 3, display: 'flex', gap: 1.5, flexWrap: 'wrap' }}>
        {Object.entries(envs).map(([env, state]) => (
          <Chip
            key={env}
            icon={state.has_token ? <Wifi sx={{ fontSize: 14 }} /> : <WifiOff sx={{ fontSize: 14 }} />}
            label={`${ENV_LABELS[env] || env}: ${state.has_token ? 'Connected' : 'No Token'}`}
            size="small"
            color={state.has_token ? 'success' : 'default'}
            variant={state.has_token ? 'filled' : 'outlined'}
            sx={{ fontWeight: 700, fontSize: '0.72rem' }}
          />
        ))}
        <Chip
          label={`${Object.values(envs).filter(s => s.has_token).length} / ${Object.keys(envs).length} envs active`}
          size="small"
          color={Object.values(envs).every(s => s.has_token) ? 'success' : 'warning'}
          variant="outlined"
          sx={{ ml: 'auto', fontWeight: 700 }}
        />
      </Box>

      <Grid container spacing={2}>
        {Object.entries(envs).map(([env, state]) => (
          <Grid size={{ xs: 12, md: 6 }} key={env}>
            <Card>
              <CardContent>
                <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
                  <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                    <Api sx={{ fontSize: 20, color: 'primary.main' }} />
                    <Typography variant="h6" sx={{ fontWeight: 800, fontSize: '0.95rem' }}>{ENV_LABELS[env] || env}</Typography>
                  </Box>
                  <Box sx={{ display: 'flex', gap: 0.5, alignItems: 'center' }}>
                    <Chip icon={state.has_token ? <Wifi sx={{ fontSize: 14 }} /> : <WifiOff sx={{ fontSize: 14 }} />} label={state.has_token ? 'Connected' : 'No Token'} size="small" color={state.has_token ? 'success' : 'default'} variant="outlined" />
                  </Box>
                </Box>

                <Box sx={{ display: 'grid', gap: 1.5 }}>
                  <TextField label="Base URL" size="small" value={state.base_url} onChange={e => updateEnv(env, 'base_url', e.target.value)} placeholder="https://technostationery.com" />

                  <Divider sx={{ my: 0.5 }}>
                    <Chip label="Admin Credentials" size="small" variant="outlined" sx={{ fontSize: '0.65rem' }} />
                  </Divider>

                  <TextField label="Admin Username" size="small" value={state.username} onChange={e => updateEnv(env, 'username', e.target.value)} placeholder="e.g. admin" autoComplete="off" />

                  <TextField
                    label="Admin Password"
                    size="small"
                    type={showPasswords[env] ? 'text' : 'password'}
                    value={state.password}
                    onChange={e => updateEnv(env, 'password', e.target.value)}
                    placeholder="Enter password to generate token"
                    autoComplete="new-password"
                    slotProps={{
                      input: {
                        endAdornment: (
                          <InputAdornment position="end">
                            <IconButton size="small" onClick={() => setShowPasswords(prev => ({ ...prev, [env]: !prev[env] }))}>
                              {showPasswords[env] ? <VisibilityOff sx={{ fontSize: 16 }} /> : <Visibility sx={{ fontSize: 16 }} />}
                            </IconButton>
                          </InputAdornment>
                        ),
                      }
                    }}
                  />

                  <Button
                    size="small"
                    variant="contained"
                    color="primary"
                    startIcon={state.fetching ? <CircularProgress size={14} /> : <Login />}
                    onClick={() => handleFetchToken(env)}
                    disabled={state.fetching || !state.username || !state.password}
                  >
                    {state.fetching ? 'Authenticating...' : 'Get API Token'}
                  </Button>

                  {state.has_token && state.token_updated_at && (
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
                      <Schedule sx={{ fontSize: 12, color: 'text.disabled' }} />
                      <Typography variant="caption" sx={{ color: 'text.disabled' }}>
                        Token updated: {new Date(state.token_updated_at).toLocaleString()}
                      </Typography>
                    </Box>
                  )}

                  <Divider sx={{ my: 0.5 }}>
                    <Chip label="Manual Token" size="small" variant="outlined" sx={{ fontSize: '0.65rem' }} />
                  </Divider>

                  <TextField
                    label={state.has_token ? 'Override Token (leave empty to keep)' : 'Manual API Token'}
                    size="small"
                    type={showTokens[env] ? 'text' : 'password'}
                    value={state.token}
                    onChange={e => updateEnv(env, 'token', e.target.value)}
                    placeholder={state.has_token ? '••••••••••••' : 'Or paste token manually'}
                    slotProps={{
                      input: {
                        endAdornment: (
                          <InputAdornment position="end">
                            <IconButton size="small" onClick={() => setShowTokens(prev => ({ ...prev, [env]: !prev[env] }))}>
                              {showTokens[env] ? <VisibilityOff sx={{ fontSize: 16 }} /> : <Visibility sx={{ fontSize: 16 }} />}
                            </IconButton>
                          </InputAdornment>
                        ),
                        sx: { fontFamily: 'monospace', fontSize: '0.8rem' }
                      }
                    }}
                  />

                  <Button size="small" variant="outlined" startIcon={state.testing ? <CircularProgress size={14} /> : <Refresh />} onClick={() => handleTest(env)} disabled={state.testing}>
                    {state.testing ? 'Testing...' : 'Test Connection'}
                  </Button>

                  {state.testResult && (
                    <Alert severity={state.testResult.success ? 'success' : 'error'} icon={state.testResult.success ? <CheckCircle /> : <ErrorIcon />} sx={{ fontSize: '0.75rem' }}>
                      {state.testResult.message}
                    </Alert>
                  )}

                  {state.storeInfo && Array.isArray(state.storeInfo) && state.storeInfo.length > 0 && (
                    <Box sx={{ p: 1.5, bgcolor: 'rgba(255,255,255,0.02)', borderRadius: 1, border: '1px solid', borderColor: 'divider' }}>
                      <Typography variant="caption" sx={{ fontWeight: 700, display: 'block', mb: 0.5 }}>Store Info</Typography>
                      <Typography variant="caption" sx={{ color: 'text.disabled' }}>Name: {state.storeInfo[0]?.code || 'N/A'}</Typography><br />
                      <Typography variant="caption" sx={{ color: 'text.disabled' }}>ID: {state.storeInfo[0]?.id || 'N/A'}</Typography>
                    </Box>
                  )}
                </Box>
              </CardContent>
            </Card>
          </Grid>
        ))}
      </Grid>
    </Box>
  );
}

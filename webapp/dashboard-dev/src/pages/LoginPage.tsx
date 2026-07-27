import React, { useState, useEffect } from 'react';
import { Box, Typography, TextField, Button, Checkbox, FormControlLabel, Card, CardContent, CircularProgress, Alert, Dialog, DialogTitle, DialogContent, DialogActions, Link } from '@mui/material';
import { Person, Lock, CheckCircle, Warning } from '@mui/icons-material';
import logoTechno from '../assets/logo_techno.png';
import { useAuth } from '../hooks/useAuth.tsx';
import { useNavigate } from 'react-router-dom';
import apiClient from '../api/client.ts';

declare global {
  interface Window {
    turnstile?: {
      render: (container: string | HTMLElement, params: {
        sitekey: string;
        callback?: (token: string) => void;
        'error-callback'?: (error: string) => void;
      }) => string;
      reset: (container?: string | HTMLElement) => void;
      remove: (container?: string | HTMLElement) => void;
    };
  }
}

export default function LoginPage() {
  const [username, setUsername] = useState(localStorage.getItem('dashboard_username') || '');
  const [password, setPassword] = useState('');
  const [rememberMe, setRememberMe] = useState(!!localStorage.getItem('dashboard_username'));
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [csrfToken, setCsrfToken] = useState('');
  const [serverStatus, setServerStatus] = useState<any>(null);
  const [forgotDialogOpen, setForgotDialogOpen] = useState(false);
  const [forgotIdentifier, setForgotIdentifier] = useState('');
  const [forgotSent, setForgotSent] = useState(false);
  const [forgotLoading, setForgotLoading] = useState(false);
  const [turnstileEnabled, setTurnstileEnabled] = useState(false);
  const [turnstileSiteKey, setTurnstileSiteKey] = useState('');
  const [turnstileToken, setTurnstileToken] = useState<string | null>(null);
  const { login, forgotPassword, isAuthenticated } = useAuth();
  const navigate = useNavigate();

  // Redirect to home if already authenticated
  useEffect(() => {
    if (isAuthenticated) {
      navigate('/', { replace: true });
    }
  }, [isAuthenticated, navigate]);

  useEffect(() => {
    fetchCsrf();
    fetchStatus();
    fetchTurnstileConfig();
    const timer = setInterval(fetchStatus, 30000);
    return () => clearInterval(timer);
  }, []);

  const fetchTurnstileConfig = async () => {
    try {
      const { data } = await apiClient.get('/api/auth.php?action=turnstile_config');
      if (data.success && data.enabled) {
        setTurnstileEnabled(true);
        setTurnstileSiteKey(data.site_key);
        
        // Load Turnstile script
        if (!document.getElementById('cf-turnstile-script')) {
          const script = document.createElement('script');
          script.id = 'cf-turnstile-script';
          script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
          script.async = true;
          script.defer = true;
          script.onload = () => renderTurnstile();
          document.head.appendChild(script);
        } else {
          // Script already loaded, render widget
          setTimeout(() => renderTurnstile(), 100);
        }
      }
    } catch (e) {
      // Turnstile config fetch failed - continue without it
    }
  };

  const renderTurnstile = () => {
    if (window.turnstile && turnstileSiteKey) {
      const container = document.getElementById('turnstile-container');
      if (container) {
        window.turnstile.render(container, {
          sitekey: turnstileSiteKey,
          callback: (token: string) => {
            setTurnstileToken(token);
          },
          'error-callback': (error: string) => {
            setError('Security verification failed. Please refresh and try again.');
          },
        });
      }
    }
  };

  const fetchCsrf = async () => {
    try {
      const { data } = await apiClient.get('/api/auth.php?action=csrf_token');
      if (data.success) setCsrfToken(data.csrf_token);
    } catch (e) {}
  };

  const fetchStatus = async () => {
    try {
      const { data } = await apiClient.get('/api/status.php');
      setServerStatus(data);
    } catch (e) {}
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setLoading(true);

    let retried = false;

    try {
      await login({
        username,
        password,
        csrf_token: csrfToken,
        remember_me: rememberMe,
        turnstile_token: turnstileToken,
      });
      
      // Clean up old localStorage keys
      localStorage.removeItem('dashboard_username');

      navigate('/');
    } catch (err: any) {
      // On 403 (CSRF mismatch), fetch new token and retry once
      if (err.response?.status === 403 && !retried) {
        const errorData = err.response?.data;
        const isCsrfError = errorData?.error?.includes('CSRF') || errorData?.reason;
        
        // Only retry for CSRF errors, not Turnstile errors
        if (isCsrfError) {
          retried = true;
          try {
            const { data } = await apiClient.get('/api/auth.php?action=csrf_token');
            if (data.success) {
              setCsrfToken(data.csrf_token);
              // Retry login with fresh token
              await login({
                username,
                password,
                csrf_token: data.csrf_token,
                remember_me: rememberMe,
                turnstile_token: turnstileToken,
              });
              localStorage.removeItem('dashboard_username');
              navigate('/');
              return;
            }
          } catch (retryErr: any) {
            setError('Session expired. Please try logging in again.');
            fetchCsrf();
            setLoading(false);
            return;
          }
        } else {
          // Turnstile or other error - show the actual error
          setError(errorData?.error || err.message || 'Login failed. Please check your credentials.');
          fetchCsrf();
          setLoading(false);
          return;
        }
      }
      
      setError(err.message || 'Login failed. Please check your credentials.');
      fetchCsrf(); // Refresh token on failure
    } finally {
      if (!retried) {
        setLoading(false);
      }
    }
  };

  const handleForgotPassword = async () => {
    setForgotLoading(true);
    setError(null);
    try {
      await forgotPassword(forgotIdentifier);
      setForgotSent(true);
    } catch (err: any) {
      setError(err.message || 'Failed to process request');
    } finally {
      setForgotLoading(false);
    }
  };

  const openForgotDialog = () => {
    setForgotIdentifier('');
    setForgotSent(false);
    setError(null);
    setForgotDialogOpen(true);
  };

  return (
    <Box sx={{ 
      minHeight: '100vh', 
      display: 'flex', 
      flexDirection: 'column', 
      alignItems: 'center', 
      justifyContent: 'center',
      background: '#0b0f1a',
      position: 'relative',
      overflow: 'hidden',
      '&::before': {
        content: '""',
        position: 'absolute',
        top: '-50%',
        left: '-50%',
        width: '200%',
        height: '200%',
        background: 'radial-gradient(ellipse at 30% 20%, rgba(59, 130, 246, 0.08) 0%, transparent 50%), radial-gradient(ellipse at 70% 80%, rgba(168, 85, 247, 0.05) 0%, transparent 50%)',
        pointerEvents: 'none',
      }
    }}>
      <Box sx={{ width: '100%', maxWidth: 440, p: 3, zIndex: 1 }}>
        <Card sx={{ 
          background: '#151c2c', 
          border: '1px solid #2a3548', 
          borderRadius: '18px', 
          boxShadow: '0 24px 48px rgba(0, 0, 0, 0.4)',
          position: 'relative',
          overflow: 'hidden',
          '&::before': {
            content: '""',
            position: 'absolute',
            top: 0,
            left: 0,
            right: 0,
            height: '1px',
            background: 'linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.15), transparent)',
          }
        }}>
          <CardContent sx={{ p: '44px 36px' }}>
            <Box sx={{ textAlign: 'center', mb: 4 }}>
              {/* Techno logo */}
              <Box
                component="img"
                src={logoTechno}
                alt="TechnoStationery"
                sx={{ height: 48, width: 'auto', objectFit: 'contain', mb: 2, display: 'block', mx: 'auto' }}
              />
              <Typography variant="h5" sx={{ 
                fontWeight: 900, 
                letterSpacing: '-0.05em',
                background: 'linear-gradient(135deg, #f1f5f9, #3b82f6)',
                WebkitBackgroundClip: 'text',
                WebkitTextFillColor: 'transparent',
                mb: 1
              }}>
                TECHNO SERVER MONITOR
              </Typography>
              <Typography variant="body2" sx={{ color: '#94a3b8', fontWeight: 500 }}>
                technostationery.com
              </Typography>
              
              <Box sx={{ display: 'flex', gap: 1.5, justifyContent: 'center', mt: 2 }}>
                <Box sx={{ 
                  fontSize: '0.72rem', px: 1.2, py: 0.5, borderRadius: '9999px', border: '1px solid rgba(34, 197, 94, 0.3)', 
                  background: 'rgba(34, 197, 94, 0.15)', color: '#22c55e', fontWeight: 600 
                }}>
                  DB {serverStatus?.services?.db === 'ok' ? '✓' : '···'}
                </Box>
                <Box sx={{ 
                  fontSize: '0.72rem', px: 1.2, py: 0.5, borderRadius: '9999px', border: '1px solid rgba(34, 197, 94, 0.3)', 
                  background: 'rgba(34, 197, 94, 0.15)', color: '#22c55e', fontWeight: 600 
                }}>
                  Load {serverStatus?.load?.['1m'] || '···'}
                </Box>
              </Box>
            </Box>

            {error && (
              <Alert severity="error" sx={{ mb: 3, borderRadius: '10px', backgroundColor: 'rgba(239, 68, 68, 0.15)', color: '#ef4444', border: '1px solid rgba(239, 68, 68, 0.3)' }}>
                {error}
              </Alert>
            )}

            <form onSubmit={handleSubmit}>
              <Box sx={{ mb: 2.5 }}>
                <Typography sx={{ mb: 1, fontSize: '0.82rem', color: '#94a3b8', fontWeight: 600 }}>Username</Typography>
                <TextField
                  fullWidth
                  value={username}
                  onChange={(e) => setUsername(e.target.value)}
                  placeholder="Enter your username"
                  required
                  autoFocus
                  slotProps={{
                    input: {
                      startAdornment: <Person sx={{ mr: 1.5, color: '#94a3b8', fontSize: 18 }} />,
                      sx: { 
                        backgroundColor: '#0b0f1a', 
                        borderRadius: '10px',
                        '& fieldset': { borderColor: '#2a3548' },
                        '&:hover fieldset': { borderColor: '#3b82f6' },
                        color: '#f1f5f9'
                      }
                    }
                  }}
                />
              </Box>

              <Box sx={{ mb: 2.5 }}>
                <Typography sx={{ mb: 1, fontSize: '0.82rem', color: '#94a3b8', fontWeight: 600 }}>Password</Typography>
                <TextField
                  fullWidth
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="Enter your password"
                  required
                  slotProps={{
                    input: {
                      startAdornment: <Lock sx={{ mr: 1.5, color: '#94a3b8', fontSize: 18 }} />,
                      sx: { 
                        backgroundColor: '#0b0f1a', 
                        borderRadius: '10px',
                        '& fieldset': { borderColor: '#2a3548' },
                        '&:hover fieldset': { borderColor: '#3b82f6' },
                        color: '#f1f5f9'
                      }
                    }
                  }}
                />
              </Box>

              <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
                <FormControlLabel
                  control={
                    <Checkbox 
                      checked={rememberMe} 
                      onChange={(e) => setRememberMe(e.target.checked)}
                      sx={{ color: '#2a3548', '&.Mui-checked': { color: '#3b82f6' } }}
                    />
                  }
                  label={<Typography sx={{ fontSize: '0.82rem', color: '#94a3b8' }}>Remember me</Typography>}
                />
                <Link component="button" type="button" onClick={openForgotDialog} sx={{ fontSize: '0.82rem', color: '#3b82f6', fontWeight: 600, textDecoration: 'none', '&:hover': { textDecoration: 'underline' } }}>
                  Forgot password?
                </Link>
              </Box>

              {turnstileEnabled && (
                <Box sx={{ mb: 2.5 }}>
                  <div id="turnstile-container"></div>
                </Box>
              )}

              <Button
                fullWidth
                type="submit"
                variant="contained"
                disabled={loading}
                sx={{ 
                  py: 1.5, 
                  borderRadius: '10px', 
                  fontWeight: 700, 
                  fontSize: '0.95rem',
                  background: 'linear-gradient(135deg, #2563eb, #3b82f6)',
                  boxShadow: '0 8px 24px rgba(59, 130, 246, 0.35)',
                  '&:hover': { background: 'linear-gradient(135deg, #1d4ed8, #2563eb)' }
                }}
              >
                {loading ? <CircularProgress size={24} color="inherit" /> : 'Sign In'}
              </Button>
            </form>
          </CardContent>
        </Card>
        <Box sx={{ textAlign: 'center', mt: 3 }}>
          <Typography sx={{ color: '#94a3b8', fontSize: '0.75rem', fontWeight: 500, mb: 1 }}>
            v5.2.0 &nbsp;·&nbsp; {new Date().toLocaleTimeString()}
          </Typography>
          <Typography sx={{ color: '#475569', fontSize: '0.62rem' }}>
            TSM Platform v5.2.0
          </Typography>
        </Box>
      </Box>

      {/* Forgot Password Dialog */}
      <Dialog open={forgotDialogOpen} onClose={() => setForgotDialogOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Reset Your Password</DialogTitle>
        <DialogContent>
          {forgotSent ? (
            <Box sx={{ pt: 1 }}>
              <Alert severity="success" sx={{ mb: 2 }}>
                If the account exists, a password reset link has been sent to the registered email address.
              </Alert>
              <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                Check your inbox and follow the instructions in the email.
              </Typography>
            </Box>
          ) : (
            <Box sx={{ pt: 1 }}>
              <Typography variant="body2" sx={{ mb: 2, color: 'text.secondary' }}>
                Enter your username or email address. We'll send you a link to reset your password.
              </Typography>
              <TextField
                label="Username or Email"
                fullWidth
                value={forgotIdentifier}
                onChange={(e) => setForgotIdentifier(e.target.value)}
                autoFocus
              />
            </Box>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setForgotDialogOpen(false)}>Close</Button>
          {!forgotSent && (
            <Button variant="contained" onClick={handleForgotPassword} disabled={forgotLoading || !forgotIdentifier}>
              {forgotLoading ? <CircularProgress size={20} color="inherit" /> : 'Send Reset Link'}
            </Button>
          )}
        </DialogActions>
      </Dialog>
    </Box>
  );
}

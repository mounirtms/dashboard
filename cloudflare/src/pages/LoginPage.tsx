import React, { useState, useEffect } from 'react';
import { Box, Typography, TextField, Button, Checkbox, FormControlLabel, Card, CardContent, CircularProgress, Alert } from '@mui/material';
import { Person, Lock, CheckCircle, Warning } from '@mui/icons-material';
import { useAuth } from '../hooks/useAuth.tsx';
import { useNavigate } from 'react-router-dom';
import apiClient from '../api/client';

export default function LoginPage() {
  const [username, setUsername] = useState(localStorage.getItem('dashboard_username') || '');
  const [password, setPassword] = useState('');
  const [rememberMe, setRememberMe] = useState(!!localStorage.getItem('dashboard_username'));
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [csrfToken, setCsrfToken] = useState('');
  const [serverStatus, setServerStatus] = useState<any>(null);
  const { login } = useAuth();
  const navigate = useNavigate();

  useEffect(() => {
    fetchCsrf();
    fetchStatus();
    const timer = setInterval(fetchStatus, 30000);
    return () => clearInterval(timer);
  }, []);

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

    try {
      await login({
        username,
        password,
        csrf_token: csrfToken
      });
      
      if (rememberMe) {
        localStorage.setItem('dashboard_username', username);
      } else {
        localStorage.removeItem('dashboard_username');
      }

      navigate('/');
    } catch (err: any) {
      setError(err.message || 'Login failed');
      fetchCsrf(); // Refresh token on failure
    } finally {
      setLoading(false);
    }
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
              </Box>

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
        <Typography sx={{ textAlign: 'center', mt: 3, color: '#94a3b8', fontSize: '0.75rem', fontWeight: 500 }}>
          v3.1.0 &nbsp;·&nbsp; {new Date().toLocaleTimeString()}
        </Typography>
      </Box>
    </Box>
  );
}

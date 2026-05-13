import { useState, useEffect } from 'react';
import { Box, Typography, TextField, Button, Card, CardContent, CircularProgress, Alert, InputAdornment, IconButton } from '@mui/material';
import { Lock, Visibility, VisibilityOff, CheckCircle } from '@mui/icons-material';
import { useNavigate, useSearchParams } from 'react-router-dom';
import apiClient from '../api/client.ts';

const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*(),.?":{}|<>]).{8,}$/;

export default function ResetPasswordPage() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const token = searchParams.get('token') || '';

  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [verifying, setVerifying] = useState(true);
  const [tokenValid, setTokenValid] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);
  const [username, setUsername] = useState('');

  useEffect(() => {
    if (!token) {
      setVerifying(false);
      setError('No reset token provided.');
      return;
    }
    verifyToken(token);
  }, [token]);

  const verifyToken = async (t: string) => {
    try {
      const { data } = await apiClient.get(`/api/auth.php?action=verify_reset_token&token=${t}`);
      setVerifying(false);
      if (data.valid) {
        setTokenValid(true);
        setUsername(data.username || '');
      } else {
        setError(data.error || 'Invalid or expired token.');
      }
    } catch (e: any) {
      setVerifying(false);
      setError('Token verification failed.');
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    if (!passwordRegex.test(newPassword)) {
      setError('Password must be at least 8 characters with uppercase, lowercase, number, and special character.');
      return;
    }
    if (newPassword !== confirmPassword) {
      setError('Passwords do not match.');
      return;
    }

    setLoading(true);
    try {
      const { data } = await apiClient.post('/api/auth.php?action=reset_password_with_token', { token, new_password: newPassword });
      if (data.success) {
        setSuccess(true);
        setTimeout(() => navigate('/login'), 3000);
      } else {
        setError(data.error || 'Failed to reset password.');
      }
    } catch (e: any) {
      setError(e.response?.data?.error || e.message || 'Failed to reset password.');
    } finally {
      setLoading(false);
    }
  };

  if (verifying) {
    return (
      <Box sx={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#0b0f1a' }}>
        <CircularProgress sx={{ color: '#3b82f6' }} />
      </Box>
    );
  }

  if (!tokenValid && !success) {
    return (
      <Box sx={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#0b0f1a' }}>
        <Card sx={{ background: '#151c2c', border: '1px solid #2a3548', borderRadius: '18px', maxWidth: 440, width: '100%' }}>
          <CardContent sx={{ p: '44px 36px', textAlign: 'center' }}>
            <Typography variant="h6" sx={{ color: '#ef4444', mb: 2 }}>Invalid Reset Link</Typography>
            <Typography variant="body2" sx={{ color: '#94a3b8', mb: 3 }}>{error || 'This password reset link is invalid or has expired.'}</Typography>
            <Button variant="contained" onClick={() => navigate('/login')}>Back to Login</Button>
          </CardContent>
        </Card>
      </Box>
    );
  }

  if (success) {
    return (
      <Box sx={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#0b0f1a' }}>
        <Card sx={{ background: '#151c2c', border: '1px solid #2a3548', borderRadius: '18px', maxWidth: 440, width: '100%' }}>
          <CardContent sx={{ p: '44px 36px', textAlign: 'center' }}>
            <CheckCircle sx={{ fontSize: 48, color: '#22c55e', mb: 2 }} />
            <Typography variant="h6" sx={{ color: '#22c55e', mb: 2 }}>Password Reset Successful</Typography>
            <Typography variant="body2" sx={{ color: '#94a3b8', mb: 3 }}>Your password has been updated. Redirecting to login...</Typography>
            <Button variant="contained" onClick={() => navigate('/login')}>Go to Login</Button>
          </CardContent>
        </Card>
      </Box>
    );
  }

  return (
    <Box sx={{
      minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center',
      background: '#0b0f1a', position: 'relative', overflow: 'hidden',
      '&::before': {
        content: '""', position: 'absolute', top: '-50%', left: '-50%', width: '200%', height: '200%',
        background: 'radial-gradient(ellipse at 30% 20%, rgba(59, 130, 246, 0.08) 0%, transparent 50%), radial-gradient(ellipse at 70% 80%, rgba(168, 85, 247, 0.05) 0%, transparent 50%)',
        pointerEvents: 'none',
      }
    }}>
      <Box sx={{ width: '100%', maxWidth: 440, p: 3, zIndex: 1 }}>
        <Card sx={{
          background: '#151c2c', border: '1px solid #2a3548', borderRadius: '18px',
          boxShadow: '0 24px 48px rgba(0, 0, 0, 0.4)', position: 'relative', overflow: 'hidden',
          '&::before': {
            content: '""', position: 'absolute', top: 0, left: 0, right: 0, height: '1px',
            background: 'linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.15), transparent)',
          }
        }}>
          <CardContent sx={{ p: '44px 36px' }}>
            <Box sx={{ textAlign: 'center', mb: 4 }}>
              <Lock sx={{ fontSize: 40, color: '#3b82f6', mb: 1 }} />
              <Typography variant="h5" sx={{ fontWeight: 900, letterSpacing: '-0.05em', color: '#f1f5f9', mb: 1 }}>
                Reset Password
              </Typography>
              {username && <Typography variant="body2" sx={{ color: '#94a3b8' }}>Account: {username}</Typography>}
            </Box>

            {error && (
              <Alert severity="error" sx={{ mb: 3, borderRadius: '10px', backgroundColor: 'rgba(239, 68, 68, 0.15)', color: '#ef4444', border: '1px solid rgba(239, 68, 68, 0.3)' }}>
                {error}
              </Alert>
            )}

            <form onSubmit={handleSubmit}>
              <Box sx={{ mb: 2.5 }}>
                <Typography sx={{ mb: 1, fontSize: '0.82rem', color: '#94a3b8', fontWeight: 600 }}>New Password</Typography>
                <TextField
                  fullWidth
                  type={showPassword ? 'text' : 'password'}
                  value={newPassword}
                  onChange={(e) => setNewPassword(e.target.value)}
                  placeholder="Enter new password"
                  required
                  autoFocus
                  slotProps={{
                    input: {
                      startAdornment: <Lock sx={{ mr: 1.5, color: '#94a3b8', fontSize: 18 }} />,
                      endAdornment: (
                        <InputAdornment position="end">
                          <IconButton size="small" onClick={() => setShowPassword(!showPassword)}>
                            {showPassword ? <VisibilityOff sx={{ fontSize: 18 }} /> : <Visibility sx={{ fontSize: 18 }} />}
                          </IconButton>
                        </InputAdornment>
                      ),
                      sx: {
                        backgroundColor: '#0b0f1a', borderRadius: '10px',
                        '& fieldset': { borderColor: '#2a3548' },
                        '&:hover fieldset': { borderColor: '#3b82f6' },
                        color: '#f1f5f9'
                      }
                    }
                  }}
                />
              </Box>

              <Box sx={{ mb: 2.5 }}>
                <Typography sx={{ mb: 1, fontSize: '0.82rem', color: '#94a3b8', fontWeight: 600 }}>Confirm Password</Typography>
                <TextField
                  fullWidth
                  type={showPassword ? 'text' : 'password'}
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  placeholder="Confirm new password"
                  required
                  slotProps={{
                    input: {
                      startAdornment: <Lock sx={{ mr: 1.5, color: '#94a3b8', fontSize: 18 }} />,
                      sx: {
                        backgroundColor: '#0b0f1a', borderRadius: '10px',
                        '& fieldset': { borderColor: '#2a3548' },
                        '&:hover fieldset': { borderColor: '#3b82f6' },
                        color: '#f1f5f9'
                      }
                    }
                  }}
                />
              </Box>

              <Button
                fullWidth
                type="submit"
                variant="contained"
                disabled={loading}
                sx={{
                  py: 1.5, borderRadius: '10px', fontWeight: 700, fontSize: '0.95rem',
                  background: 'linear-gradient(135deg, #2563eb, #3b82f6)',
                  boxShadow: '0 8px 24px rgba(59, 130, 246, 0.35)',
                  '&:hover': { background: 'linear-gradient(135deg, #1d4ed8, #2563eb)' }
                }}
              >
                {loading ? <CircularProgress size={24} color="inherit" /> : 'Reset Password'}
              </Button>
            </form>
          </CardContent>
        </Card>
      </Box>
    </Box>
  );
}

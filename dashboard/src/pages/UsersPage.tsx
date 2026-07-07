import {
  Box, Typography, Card, Button, Chip, IconButton, Tooltip,
  Snackbar, Alert, Dialog, DialogTitle, DialogContent, DialogActions,
  TextField, MenuItem, InputAdornment, CircularProgress,
} from '@mui/material';
import { DataGrid, GridColDef, GridRenderCellParams } from '@mui/x-data-grid';
import {
  Person, Shield, Lock, PowerSettingsNew, Refresh, Edit, Add, Delete,
  Visibility, VisibilityOff,
} from '@mui/icons-material';
import { useState, useCallback } from 'react';
import {
  fetchUsers, createUser, updateUser, deleteUser,
  resetUserPassword, toggleUserStatus,
  type CreateUserInput, type UpdateUserInput, type UserRole,
} from '../api/users';
import StatusBadge from '../components/common/StatusBadge';
import { validatePassword, validatePasswordMatch } from '../utils/validation';
import { usePolling } from '../hooks/usePolling';

// ── Types ────────────────────────────────────────────────────────────────────

interface DashboardUser {
  id: number;
  username: string;
  full_name?: string;
  email?: string;
  role: UserRole;
  is_active: boolean;
  last_login?: string;
}

type SnackSeverity = 'success' | 'error' | 'info';

// ── Helpers ──────────────────────────────────────────────────────────────────

function extractMessage(e: unknown): string {
  if (e && typeof e === 'object') {
    const axiosErr = e as { response?: { data?: { error?: string } }; message?: string };
    return axiosErr.response?.data?.error ?? axiosErr.message ?? 'Unknown error';
  }
  return 'Unknown error';
}

// ── Page ─────────────────────────────────────────────────────────────────────

export default function UsersPage() {
  // ── Fetch ──
  const fetcher = useCallback(
    (_signal?: AbortSignal) => fetchUsers() as Promise<DashboardUser[]>,
    [],
  );
  const { data: users = [], loading, refreshing, refetch } = usePolling<DashboardUser[]>(fetcher, 0);

  // ── UI state ──
  const [snackbar, setSnackbar]   = useState<{ open: boolean; message: string; severity: SnackSeverity }>({ open: false, message: '', severity: 'info' });
  const [userDialog, setUserDialog] = useState<{ open: boolean; mode: 'add' | 'edit' | 'reset'; user?: DashboardUser }>({ open: false, mode: 'add' });
  const [deleteDialog, setDeleteDialog] = useState<{ open: boolean; user?: DashboardUser }>({ open: false });

  // ── Form state ──
  const [formUsername, setFormUsername]               = useState('');
  const [formEmail, setFormEmail]                     = useState('');
  const [formFullName, setFormFullName]               = useState('');
  const [formRole, setFormRole]                       = useState<UserRole>('viewer');
  const [formPassword, setFormPassword]               = useState('');
  const [formConfirmPassword, setFormConfirmPassword] = useState('');
  const [showPassword, setShowPassword]               = useState(false);
  const [formErrors, setFormErrors]                   = useState<Record<string, string>>({});

  const notify = (message: string, severity: SnackSeverity) =>
    setSnackbar({ open: true, message, severity });

  // ── Dialog openers ──
  const openAddDialog = () => {
    setFormUsername(''); setFormEmail(''); setFormFullName('');
    setFormRole('viewer'); setFormPassword(''); setFormConfirmPassword('');
    setFormErrors({});
    setUserDialog({ open: true, mode: 'add' });
  };

  const openEditDialog = (user: DashboardUser) => {
    setFormUsername(user.username ?? '');
    setFormEmail(user.email ?? '');
    setFormFullName(user.full_name ?? '');
    setFormRole(user.role ?? 'viewer');
    setFormPassword(''); setFormConfirmPassword('');
    setFormErrors({});
    setUserDialog({ open: true, mode: 'edit', user });
  };

  const openResetDialog = (user: DashboardUser) =>
    setUserDialog({ open: true, mode: 'reset', user });

  // ── Validation ──
  const validateAddForm = (): boolean => {
    const errors: Record<string, string> = {};
    if (!/^[a-zA-Z0-9_]{3,50}$/.test(formUsername))
      errors.username = '3-50 chars, alphanumeric and underscore only';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formEmail))
      errors.email = 'Invalid email address';
    if (!formFullName.trim())
      errors.full_name = 'Full name is required';
    const pwError = validatePassword(formPassword);
    if (pwError) errors.password = pwError;
    const matchError = validatePasswordMatch(formPassword, formConfirmPassword);
    if (matchError) errors.confirmPassword = matchError;
    setFormErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const validateEditForm = (): boolean => {
    const errors: Record<string, string> = {};
    if (!/^[a-zA-Z0-9_]{3,50}$/.test(formUsername))
      errors.username = '3-50 chars, alphanumeric and underscore only';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formEmail))
      errors.email = 'Invalid email address';
    if (!formFullName.trim())
      errors.full_name = 'Full name is required';
    setFormErrors(errors);
    return Object.keys(errors).length === 0;
  };

  // ── Actions ──
  const handleSaveAdd = async () => {
    if (!validateAddForm()) return;
    try {
      const input: CreateUserInput = {
        username: formUsername, email: formEmail,
        full_name: formFullName, role: formRole, password: formPassword,
      };
      const result = await createUser(input);
      notify(result.message, 'success');
      setUserDialog(prev => ({ ...prev, open: false }));
      refetch();
    } catch (e: unknown) {
      notify(extractMessage(e), 'error');
    }
  };

  const handleSaveEdit = async () => {
    if (!validateEditForm()) return;
    try {
      const input: UpdateUserInput = {
        id: userDialog.user!.id, username: formUsername,
        email: formEmail, full_name: formFullName, role: formRole,
      };
      const result = await updateUser(input);
      notify(result.message, 'success');
      setUserDialog(prev => ({ ...prev, open: false }));
      refetch();
    } catch (e: unknown) {
      notify(extractMessage(e), 'error');
    }
  };

  const handleResetPassword = async () => {
    try {
      const result = await resetUserPassword(userDialog.user!.id);
      notify(result.message, 'success');
      setUserDialog(prev => ({ ...prev, open: false }));
    } catch (e: unknown) {
      notify(extractMessage(e), 'error');
    }
  };

  const handleToggleStatus = async (id: number) => {
    try {
      await toggleUserStatus(id);
      refetch();
      notify('User status updated', 'success');
    } catch (e: unknown) {
      notify('Failed to update user: ' + extractMessage(e), 'error');
    }
  };

  const handleDeleteUser = async () => {
    try {
      const result = await deleteUser(deleteDialog.user!.id);
      notify(result.message, 'success');
      setDeleteDialog({ open: false });
      refetch();
    } catch (e: unknown) {
      notify(extractMessage(e), 'error');
    }
  };

  // ── Grid columns ──
  const columns: GridColDef[] = [
    { field: 'id', headerName: 'ID', width: 70 },
    {
      field: 'username',
      headerName: 'Identity',
      flex: 1,
      renderCell: (params: GridRenderCellParams) => (
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, height: '100%' }}>
          <Person sx={{ color: 'text.secondary' }} />
          <Box>
            <Typography variant="body2" sx={{ fontWeight: 700 }}>{params.value as string}</Typography>
            <Typography variant="caption" sx={{ color: 'text.disabled' }}>{(params.row as DashboardUser).full_name || '—'}</Typography>
          </Box>
        </Box>
      ),
    },
    {
      field: 'role',
      headerName: 'Role',
      width: 120,
      renderCell: (params: GridRenderCellParams) => (
        <Chip
          icon={<Shield sx={{ fontSize: 14 }} />}
          label={(params.value as string).toUpperCase()}
          size="small"
          color={(params.value as string) === 'admin' ? 'primary' : 'default'}
          sx={{ fontWeight: 700, fontSize: '0.65rem' }}
        />
      ),
    },
    {
      field: 'is_active',
      headerName: 'Status',
      width: 120,
      renderCell: (params: GridRenderCellParams) => (
        <StatusBadge
          label={params.value ? 'ACTIVE' : 'DISABLED'}
          color={params.value ? 'success' : 'error'}
        />
      ),
    },
    {
      field: 'last_login',
      headerName: 'Last Activity',
      width: 180,
      renderCell: (params: GridRenderCellParams) => (
        <Typography variant="caption" sx={{ fontFamily: 'monospace' }}>
          {params.value ? new Date(params.value as string).toLocaleString() : 'Never'}
        </Typography>
      ),
    },
    {
      field: 'actions',
      headerName: 'Actions',
      width: 180,
      renderCell: (params: GridRenderCellParams) => {
        const user = params.row as DashboardUser;
        return (
          <Box sx={{ display: 'flex', gap: 0.5 }}>
            <Tooltip title="Edit User">
              <IconButton size="small" onClick={() => openEditDialog(user)}>
                <Edit sx={{ fontSize: 16 }} />
              </IconButton>
            </Tooltip>
            <Tooltip title="Reset Password">
              <IconButton size="small" onClick={() => openResetDialog(user)}>
                <Lock sx={{ fontSize: 16 }} />
              </IconButton>
            </Tooltip>
            <Tooltip title={user.is_active ? 'Disable User' : 'Enable User'}>
              <IconButton
                size="small"
                color={user.is_active ? 'error' : 'success'}
                onClick={() => handleToggleStatus(user.id)}
              >
                <PowerSettingsNew sx={{ fontSize: 16 }} />
              </IconButton>
            </Tooltip>
            <Tooltip title="Delete User">
              <IconButton size="small" color="error" onClick={() => setDeleteDialog({ open: true, user })}>
                <Delete sx={{ fontSize: 16 }} />
              </IconButton>
            </Tooltip>
          </Box>
        );
      },
    },
  ];

  // ── Render ──
  return (
    <Box sx={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Identity &amp; Access
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Manage administrative users and platform permissions.
          </Typography>
        </Box>
        <Box sx={{ display: 'flex', gap: 2, alignItems: 'center' }}>
          {refreshing && <CircularProgress size={18} sx={{ color: 'text.disabled' }} />}
          <Button variant="outlined" startIcon={<Refresh />} onClick={refetch} disabled={loading || refreshing}>
            Sync
          </Button>
          <Button variant="contained" startIcon={<Add />} onClick={openAddDialog}>
            Add User
          </Button>
        </Box>
      </Box>

      <Card sx={{ flexGrow: 1 }}>
        <DataGrid
          rows={users}
          columns={columns}
          loading={loading}
          density="compact"
          disableRowSelectionOnClick
          sx={{ border: 'none' }}
        />
      </Card>

      {/* ── Add User Dialog ── */}
      <Dialog open={userDialog.open && userDialog.mode === 'add'} onClose={() => setUserDialog(prev => ({ ...prev, open: false }))} maxWidth="sm" fullWidth>
        <DialogTitle>Add New User</DialogTitle>
        <DialogContent>
          <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2, mt: 1 }}>
            <TextField label="Username" fullWidth value={formUsername} onChange={(e) => setFormUsername(e.target.value)} error={!!formErrors.username} helperText={formErrors.username} />
            <TextField label="Email" fullWidth type="email" value={formEmail} onChange={(e) => setFormEmail(e.target.value)} error={!!formErrors.email} helperText={formErrors.email} />
            <TextField label="Full Name" fullWidth value={formFullName} onChange={(e) => setFormFullName(e.target.value)} error={!!formErrors.full_name} helperText={formErrors.full_name} />
            <TextField label="Role" select fullWidth value={formRole} onChange={(e) => setFormRole(e.target.value as UserRole)}>
              <MenuItem value="admin">Admin</MenuItem>
              <MenuItem value="editor">Editor</MenuItem>
              <MenuItem value="moderator">Moderator</MenuItem>
              <MenuItem value="viewer">Viewer</MenuItem>
              <MenuItem value="marketing">Marketing</MenuItem>
            </TextField>
            <TextField
              label="Password" fullWidth
              type={showPassword ? 'text' : 'password'}
              value={formPassword} onChange={(e) => setFormPassword(e.target.value)}
              error={!!formErrors.password} helperText={formErrors.password}
              slotProps={{ input: { endAdornment: (
                <InputAdornment position="end">
                  <IconButton size="small" onClick={() => setShowPassword(p => !p)}>
                    {showPassword ? <VisibilityOff sx={{ fontSize: 18 }} /> : <Visibility sx={{ fontSize: 18 }} />}
                  </IconButton>
                </InputAdornment>
              )}}}
            />
            <TextField
              label="Confirm Password" fullWidth
              type={showPassword ? 'text' : 'password'}
              value={formConfirmPassword} onChange={(e) => setFormConfirmPassword(e.target.value)}
              error={!!formErrors.confirmPassword} helperText={formErrors.confirmPassword}
            />
          </Box>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setUserDialog(prev => ({ ...prev, open: false }))}>Cancel</Button>
          <Button variant="contained" onClick={handleSaveAdd}>Create User</Button>
        </DialogActions>
      </Dialog>

      {/* ── Edit User Dialog ── */}
      <Dialog open={userDialog.open && userDialog.mode === 'edit'} onClose={() => setUserDialog(prev => ({ ...prev, open: false }))} maxWidth="sm" fullWidth>
        <DialogTitle>Edit User: {userDialog.user?.username}</DialogTitle>
        <DialogContent>
          <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2, mt: 1 }}>
            <TextField label="Username" fullWidth value={formUsername} onChange={(e) => setFormUsername(e.target.value)} error={!!formErrors.username} helperText={formErrors.username} />
            <TextField label="Email" fullWidth type="email" value={formEmail} onChange={(e) => setFormEmail(e.target.value)} error={!!formErrors.email} helperText={formErrors.email} />
            <TextField label="Full Name" fullWidth value={formFullName} onChange={(e) => setFormFullName(e.target.value)} error={!!formErrors.full_name} helperText={formErrors.full_name} />
            <TextField label="Role" select fullWidth value={formRole} onChange={(e) => setFormRole(e.target.value as UserRole)}>
              <MenuItem value="admin">Admin</MenuItem>
              <MenuItem value="editor">Editor</MenuItem>
              <MenuItem value="moderator">Moderator</MenuItem>
              <MenuItem value="viewer">Viewer</MenuItem>
              <MenuItem value="marketing">Marketing</MenuItem>
            </TextField>
          </Box>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setUserDialog(prev => ({ ...prev, open: false }))}>Cancel</Button>
          <Button variant="contained" onClick={handleSaveEdit}>Save Changes</Button>
        </DialogActions>
      </Dialog>

      {/* ── Reset Password Dialog ── */}
      <Dialog open={userDialog.open && userDialog.mode === 'reset'} onClose={() => setUserDialog(prev => ({ ...prev, open: false }))}>
        <DialogTitle>Reset Password</DialogTitle>
        <DialogContent>
          <Typography variant="body2" sx={{ mb: 2 }}>
            Reset the password for <strong>{userDialog.user?.username}</strong> ({userDialog.user?.email || 'no email set'}).
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            A temporary password will be generated and sent to the user's email address.
          </Typography>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setUserDialog(prev => ({ ...prev, open: false }))}>Cancel</Button>
          <Button variant="contained" color="warning" onClick={handleResetPassword} disabled={!userDialog.user?.email}>
            Generate &amp; Send Password
          </Button>
        </DialogActions>
      </Dialog>

      {/* ── Delete Confirmation Dialog ── */}
      <Dialog open={deleteDialog.open} onClose={() => setDeleteDialog({ open: false })}>
        <DialogTitle>Delete User</DialogTitle>
        <DialogContent>
          <Typography variant="body2">
            Are you sure you want to delete <strong>{deleteDialog.user?.username}</strong>? This action cannot be undone.
          </Typography>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDeleteDialog({ open: false })}>Cancel</Button>
          <Button variant="contained" color="error" onClick={handleDeleteUser}>Delete</Button>
        </DialogActions>
      </Dialog>

      <Snackbar open={snackbar.open} autoHideDuration={4000} onClose={() => setSnackbar(prev => ({ ...prev, open: false }))}>
        <Alert onClose={() => setSnackbar(prev => ({ ...prev, open: false }))} severity={snackbar.severity} sx={{ width: '100%' }}>
          {snackbar.message}
        </Alert>
      </Snackbar>
    </Box>
  );
}

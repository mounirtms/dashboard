import { Box, Typography, Card, CardContent, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Switch, Paper, Chip, Snackbar, Alert, CircularProgress, Button } from '@mui/material';
import { Refresh } from '@mui/icons-material';
import { useState, useEffect, useCallback } from 'react';
import { fetchAllRolePermissions, updateRolePermission, type RolePermissions } from '../api/permissions';
import LoadingState from '../components/common/LoadingState';

const PERMISSION_GROUPS = [
  {
    label: 'Page Access',
    permissions: [
      { key: 'can_access_users_page', label: 'User Management' },
      { key: 'can_access_settings_page', label: 'Dashboard Settings' },
      { key: 'can_access_emergency_actions', label: 'Emergency Actions' },
      { key: 'can_access_cache_control', label: 'Cache Control' },
      { key: 'can_access_process_explorer', label: 'Process Explorer' },
      { key: 'can_access_permissions_page', label: 'Permissions Matrix' },
      { key: 'can_access_cloudflare', label: 'Cloudflare Management' },
    ] as const,
  },
  {
    label: 'Tasks',
    permissions: [
      { key: 'can_create_tasks', label: 'Create tasks' },
      { key: 'can_update_own_tasks', label: 'Update own tasks' },
      { key: 'can_update_any_task', label: 'Update any task' },
      { key: 'can_delete_tasks', label: 'Delete tasks' },
      { key: 'can_add_task_notes', label: 'Add task notes/screenshots' },
    ] as const,
  },
  {
    label: 'Notes',
    permissions: [
      { key: 'can_edit_own_notes', label: 'Edit own notes' },
      { key: 'can_edit_any_note', label: 'Edit any note' },
      { key: 'can_delete_own_notes', label: 'Delete own notes' },
      { key: 'can_delete_any_note', label: 'Delete any note' },
      { key: 'can_pin_notes', label: 'Pin/unpin notes' },
    ] as const,
  },
  {
    label: 'Administration',
    permissions: [
      { key: 'can_manage_users', label: 'Manage users' },
    ] as const,
  },
  {
    label: 'System & DevOps',
    permissions: [
      { key: 'can_access_ssh_monitor', label: 'SSH Monitor' },
      { key: 'can_access_command_audit', label: 'Command Audit' },
      { key: 'can_access_user_activity', label: 'User Activity' },
      { key: 'can_access_system_audit', label: 'System Audit' },
      { key: 'can_access_plans', label: 'Plans' },
      { key: 'can_access_cicd', label: 'CI/CD' },
      { key: 'can_access_script_runner', label: 'Script Runner' },
      { key: 'can_access_task_queue',    label: 'Task Queue' },
      { key: 'can_access_etl',           label: 'ETL Platform' },
    ] as const,
  },
  {
    label: 'Push Notifications',
    permissions: [
      { key: 'can_access_push_notifications', label: 'Access Push Notifications page' },
      { key: 'can_send_notifications', label: 'Send push notifications' },
      { key: 'can_view_subscribers', label: 'View subscriber analytics' },
      { key: 'can_manage_segments', label: 'Manage subscriber segments' },
    ] as const,
  },
  {
    label: 'Magento / Commerce',
    permissions: [
      { key: 'can_access_magento_products', label: 'View products catalog' },
      { key: 'can_edit_products', label: 'Create/update/delete products' },
      { key: 'can_bulk_products', label: 'Bulk product operations & image upload' },
      { key: 'can_access_magento_customers', label: 'View customers' },
      { key: 'can_edit_customers', label: 'Create/update/delete customers' },
      { key: 'can_access_magento_orders', label: 'View orders' },
      { key: 'can_manage_orders', label: 'Cancel/ship/invoice orders' },
      { key: 'can_access_magento_cms', label: 'View CMS pages & blocks' },
      { key: 'can_edit_cms', label: 'Create/update/delete CMS content' },
      { key: 'can_access_magento_settings', label: 'Magento connection settings' },
    ] as const,
  },
];

const ROLES = ['admin', 'editor', 'moderator', 'viewer', 'marketing'] as const;
const ROLE_LABELS: Record<string, string> = { admin: 'Admin', editor: 'Editor', moderator: 'Moderator', viewer: 'Viewer', marketing: 'Marketing' };
const ROLE_COLORS: Record<string, string> = { admin: 'error', editor: 'primary', moderator: 'warning', viewer: 'default', marketing: 'secondary' };

export default function PermissionsPage() {
  const [permissions, setPermissions] = useState<Record<string, RolePermissions> | null>(null);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [snackbar, setSnackbar] = useState<{ open: boolean; message: string; severity: 'success' | 'error' }>({ open: false, message: '', severity: 'success' });

  const loadData = useCallback(() => {
    setLoading(true);
    setLoadError(null);
    fetchAllRolePermissions()
      .then(setPermissions)
      .catch((e) => setLoadError(e.response?.data?.error || e.message || 'Failed to load permissions'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  const handleToggle = async (role: string, permission: string, value: boolean) => {
    setSaving(true);
    try {
      await updateRolePermission(role, permission, value);
      // Update local state
      setPermissions(prev => {
        if (!prev) return prev;
        return {
          ...prev,
          [role]: { ...prev[role], [permission]: value ? 1 : 0 },
        };
      });
      setSnackbar({ open: true, message: `Updated ${ROLE_LABELS[role]} permission`, severity: 'success' });
    } catch (e: any) {
      setSnackbar({ open: true, message: e.response?.data?.error || 'Failed to update', severity: 'error' });
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <LoadingState message="Loading permissions..." />;
  if (!permissions) return <LoadingState message="Failed to load permissions" />;

  return (
    <Box sx={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
      {/* Header with Refresh */}
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Permissions Matrix
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Manage role-based permissions. Changes take effect immediately on next request.
          </Typography>
        </Box>
        <Button variant="outlined" startIcon={<Refresh />} onClick={loadData} disabled={loading || saving}>
          Refresh
        </Button>
      </Box>

      {/* Matrix */}
      <Card sx={{ mb: 3 }}>
        <TableContainer component={Paper} sx={{ backgroundColor: 'transparent', border: 'none' }}>
          <Table size="small" sx={{ '& th, & td': { borderColor: 'rgba(255,255,255,0.06)' } }}>
            <TableHead>
              <TableRow>
                <TableCell sx={{ fontWeight: 700, color: 'text.secondary', fontSize: '0.75rem' }}>Permission</TableCell>
                {ROLES.map(role => (
                  <TableCell key={role} align="center" sx={{ fontWeight: 700, fontSize: '0.75rem' }}>
                    <Chip label={ROLE_LABELS[role]} size="small" color={ROLE_COLORS[role] as any} variant="outlined" />
                  </TableCell>
                ))}
              </TableRow>
            </TableHead>
            <TableBody>
              {PERMISSION_GROUPS.map(group => (
                <>
                  {/* Group header */}
                  <TableRow key={`group-${group.label}`}>
                    <TableCell colSpan={ROLES.length + 1} sx={{ backgroundColor: 'rgba(255,255,255,0.02)', fontWeight: 700, fontSize: '0.7rem', color: 'primary.main', letterSpacing: '0.05em', textTransform: 'uppercase' }}>
                      {group.label}
                    </TableCell>
                  </TableRow>
                  {/* Permission rows */}
                  {group.permissions.map(perm => (
                    <TableRow key={perm.key} sx={{ '&:hover': { backgroundColor: 'rgba(255,255,255,0.01)' } }}>
                      <TableCell sx={{ fontSize: '0.8rem', color: 'text.primary' }}>{perm.label}</TableCell>
                      {ROLES.map(role => {
                        const value = permissions[role]?.[perm.key as keyof RolePermissions];
                        const isChecked = value === 1 || value === true;
                        return (
                          <TableCell key={`${role}-${perm.key}`} align="center">
                            <Switch
                              size="small"
                              checked={isChecked}
                              onChange={(e) => handleToggle(role, perm.key, e.target.checked)}
                              disabled={saving}
                              sx={{
                                '& .MuiSwitch-thumb': { backgroundColor: isChecked ? '#3b82f6' : '#64748b' },
                                '& .MuiSwitch-track': { backgroundColor: isChecked ? 'rgba(59,130,246,0.5)' : 'rgba(100,116,139,0.3)' },
                              }}
                            />
                          </TableCell>
                        );
                      })}
                    </TableRow>
                  ))}
                </>
              ))}
            </TableBody>
          </Table>
        </TableContainer>
      </Card>

      {/* Role descriptions */}
      <Card>
        <CardContent sx={{ py: 2 }}>
          <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1.5 }}>Role Descriptions</Typography>
          <Box sx={{ display: 'grid', gridTemplateColumns: 'repeat(5, 1fr)', gap: 2 }}>
            <Box>
              <Chip label="Admin" size="small" color="error" sx={{ mb: 1, fontWeight: 700 }} />
              <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block' }}>Full access to all pages, tasks, notes, and user management.</Typography>
            </Box>
            <Box>
              <Chip label="Editor" size="small" color="primary" sx={{ mb: 1, fontWeight: 700 }} />
              <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block' }}>Can create tasks, edit own tasks, add/edit/delete own notes. Limited page access.</Typography>
            </Box>
            <Box>
              <Chip label="Moderator" size="small" color="warning" sx={{ mb: 1, fontWeight: 700 }} />
              <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block' }}>Like Editor but can edit any task, delete any note, and access Settings/Cache/Process Explorer.</Typography>
            </Box>
            <Box>
              <Chip label="Viewer" size="small" sx={{ mb: 1, fontWeight: 700 }} />
              <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block' }}>Read-only access to most pages. Can create tasks and manage own notes.</Typography>
            </Box>
            <Box>
              <Chip label="Marketing" size="small" color="secondary" sx={{ mb: 1, fontWeight: 700 }} />
              <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block' }}>Can manage push notifications, view subscriber analytics, and manage segments. No task or user access.</Typography>
            </Box>
          </Box>
        </CardContent>
      </Card>

      <Snackbar open={snackbar.open} autoHideDuration={3000} onClose={() => setSnackbar({ ...snackbar, open: false })}>
        <Alert onClose={() => setSnackbar({ ...snackbar, open: false })} severity={snackbar.severity} sx={{ width: '100%' }}>{snackbar.message}</Alert>
      </Snackbar>
    </Box>
  );
}

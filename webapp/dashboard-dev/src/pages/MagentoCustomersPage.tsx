import { Box, Typography, Button, TextField, Chip, Drawer, IconButton, Tooltip, Snackbar, Alert, InputAdornment, Divider, Grid, Card, CardContent, FormControl, Select, MenuItem, Dialog, DialogTitle, DialogContent, DialogActions } from '@mui/material';
import { DataGrid, GridColDef } from '@mui/x-data-grid';
import { Add, Delete, Edit, Search, Refresh, Close, Save, Email, Person } from '@mui/icons-material';
import { useState, useEffect, useCallback } from 'react';
import { fetchMagentoCustomers, saveMagentoCustomer, deleteMagentoCustomer, type MagentoCustomer } from '../api/magento';
import LoadingState from '../components/common/LoadingState';
import PermissionGate from '../components/common/PermissionGate';

const ENVS = [
  { key: 'prod', label: 'Production' },
  { key: 'tsdnd', label: 'TSDND' },
  { key: 'dev', label: 'Development' },
];

export default function MagentoCustomersPage() {
  const [env, setEnv] = useState('prod');
  const [customers, setCustomers] = useState<MagentoCustomer[]>([]);
  const [totalCount, setTotalCount] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(0);
  const [pageSize, setPageSize] = useState(20);
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [editCustomer, setEditCustomer] = useState<Partial<MagentoCustomer> | null>(null);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState<{ message: string; severity: 'success' | 'error' } | null>(null);

  useEffect(() => {
    const t = setTimeout(() => setDebouncedSearch(search), 300);
    return () => clearTimeout(t);
  }, [search]);

  const loadCustomers = useCallback(async () => {
    setLoading(true);
    try {
      const data = await fetchMagentoCustomers(env, page + 1, pageSize, debouncedSearch || undefined);
      setCustomers(data.items || []);
      setTotalCount(data.total_count || 0);
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, [page, pageSize, debouncedSearch, env]);

  useEffect(() => { loadCustomers(); }, [loadCustomers]);

  const [deleteDialog, setDeleteDialog] = useState<{ open: boolean; id?: number }>({ open: false });

  const handleDelete = (id: number) => setDeleteDialog({ open: true, id });

  const handleDeleteConfirm = async () => {
    const { id } = deleteDialog;
    setDeleteDialog({ open: false });
    if (!id) return;
    try {
      await deleteMagentoCustomer(id, env);
      setToast({ message: 'Customer deleted', severity: 'success' });
      loadCustomers();
    } catch (e: any) {
      setToast({ message: e.message, severity: 'error' });
    }
  };

  const handleSave = async () => {
    if (!editCustomer) return;
    setSaving(true);
    try {
      await saveMagentoCustomer(editCustomer, env);
      setToast({ message: 'Customer saved', severity: 'success' });
      setDrawerOpen(false);
      setEditCustomer(null);
      loadCustomers();
    } catch (e: any) {
      setToast({ message: e.message, severity: 'error' });
    } finally {
      setSaving(false);
    }
  };

  const columns: GridColDef[] = [
    { field: 'id', headerName: 'ID', width: 70 },
    { field: 'firstname', headerName: 'First Name', width: 120 },
    { field: 'lastname', headerName: 'Last Name', width: 120 },
    { field: 'email', headerName: 'Email', flex: 1, minWidth: 200, renderCell: (p) => (
      <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
        <Email sx={{ fontSize: 14, color: 'text.disabled' }} />
        <Typography sx={{ fontSize: '0.78rem' }}>{p.value}</Typography>
      </Box>
    )},
    { field: 'group_id', headerName: 'Group', width: 90, renderCell: (p) => {
      const groups: Record<number, string> = { 0: 'General', 1: 'Wholesale', 2: 'Retailer' };
      return <Chip label={groups[p.value] || `Group ${p.value}`} size="small" variant="outlined" sx={{ fontSize: '0.65rem' }} />;
    }},
    { field: 'created_at', headerName: 'Created', width: 110, renderCell: (p) => p.value ? new Date(p.value).toLocaleDateString() : '' },
    { field: 'actions', headerName: '', width: 100, sortable: false, filterable: false, renderCell: (p) => (
      <Box sx={{ display: 'flex', gap: 0.5 }}>
        <PermissionGate permission="can_edit_customers">
          <Tooltip title="Edit">
            <IconButton size="small" onClick={() => { setEditCustomer(p.row); setDrawerOpen(true); }}><Edit sx={{ fontSize: 16 }} /></IconButton>
          </Tooltip>
        </PermissionGate>
        <PermissionGate permission="can_edit_customers">
          <Tooltip title="Delete">
            <IconButton size="small" color="error" onClick={() => handleDelete(p.row.id)}><Delete sx={{ fontSize: 16 }} /></IconButton>
          </Tooltip>
        </PermissionGate>
      </Box>
    )},
  ];

  if (error && !customers.length) return <LoadingState message={`Error: ${error}`} />;

  return (
    <Box>
      <Box sx={{ mb: 2, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>Customers</Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>{totalCount} registered customers</Typography>
        </Box>
        <Box sx={{ display: 'flex', gap: 1, alignItems: 'center' }}>
          <FormControl size="small" sx={{ minWidth: 140 }}>
            <Select value={env} onChange={e => { setEnv(e.target.value); setPage(0); }} sx={{ fontWeight: 700, fontSize: '0.8rem' }}>
              {ENVS.map(e => <MenuItem key={e.key} value={e.key}>{e.label}</MenuItem>)}
            </Select>
          </FormControl>
          <PermissionGate permission="can_edit_customers">
            <Button variant="contained" startIcon={<Add />} onClick={() => { setEditCustomer({ email: '', firstname: '', lastname: '', group_id: 0 }); setDrawerOpen(true); }}>Add Customer</Button>
          </PermissionGate>
          <Button variant="outlined" startIcon={<Refresh />} onClick={loadCustomers}>Refresh</Button>
        </Box>
      </Box>

      {/* Customer stats banner — real DB data */}
      {env === 'prod' && (
        <Box sx={{ mb: 2, p: 1.5, borderRadius: 1.5, background: 'linear-gradient(135deg, rgba(99,102,241,0.08) 0%, rgba(34,197,94,0.05) 100%)', border: '1px solid rgba(99,102,241,0.2)' }}>
          <Typography variant="caption" sx={{ color: 'text.disabled', textTransform: 'uppercase', letterSpacing: 0.8, fontWeight: 700, display: 'block', mb: 1 }}>
            Inscriptions clients — Source: customer_entity · 2026 = aucun nouveau client
          </Typography>
          <Box sx={{ display: 'flex', gap: 2.5, flexWrap: 'wrap', alignItems: 'center' }}>
            {[
              { yr: '2021', n: '1,764', color: '#94a3b8' },
              { yr: '2022', n: '1,077', color: '#64748b' },
              { yr: '2023', n: '1,204', color: '#3b82f6' },
              { yr: '2024', n: '838',   color: '#22c55e' },
              { yr: '2025*', n: '150',  color: '#f59e0b' },
            ].map(({ yr, n, color }) => (
              <Box key={yr} sx={{ borderLeft: `3px solid ${color}`, pl: 1.5 }}>
                <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 700, textTransform: 'uppercase' }}>{yr}</Typography>
                <Typography variant="h5" sx={{ fontWeight: 900, color, lineHeight: 1 }}>{n}</Typography>
                <Typography variant="caption" sx={{ color: 'text.disabled' }}>nouveaux</Typography>
              </Box>
            ))}
            <Box sx={{ ml: 'auto', textAlign: 'right', borderLeft: '3px solid #6366f1', pl: 1.5 }}>
              <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 700 }}>TOTAL BASE</Typography>
              <Typography variant="h5" sx={{ fontWeight: 900, color: '#6366f1', lineHeight: 1 }}>5,033</Typography>
              <Typography variant="caption" sx={{ color: 'text.disabled' }}>clients actifs · *2025 = Jan–Avr</Typography>
            </Box>
          </Box>
        </Box>
      )}

      <Box sx={{ mb: 2 }}>
        <TextField size="small" placeholder="Search by email..." value={search} onChange={e => setSearch(e.target.value)} sx={{ width: 320 }}
          slotProps={{ input: { startAdornment: <InputAdornment position="start"><Search sx={{ fontSize: 18, color: 'text.disabled' }} /></InputAdornment> } }}
        />
      </Box>

      <DataGrid
        rows={customers} columns={columns} rowCount={totalCount} loading={loading}
        pageSizeOptions={[10, 20, 50, 100]}
        paginationMode="server"
        paginationModel={{ page, pageSize }}
        onPaginationModelChange={m => { setPage(m.page); setPageSize(m.pageSize); }}
        getRowId={r => r.id}
        disableRowSelectionOnClick
        sx={{ border: '1px solid', borderColor: 'divider', borderRadius: 2, '& .MuiDataGrid-cell': { fontSize: '0.78rem' } }}
        autoHeight
      />

      <Drawer anchor="right" open={drawerOpen} onClose={() => { setDrawerOpen(false); setEditCustomer(null); }} slotProps={{ paper: { sx: { width: 420, bgcolor: 'background.paper' } } }}>
        {editCustomer && (
          <Box sx={{ p: 3, display: 'flex', flexDirection: 'column', height: '100%' }}>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 3 }}>
              <Typography variant="h6" sx={{ fontWeight: 800 }}>{editCustomer.id ? 'Edit Customer' : 'New Customer'}</Typography>
              <IconButton onClick={() => { setDrawerOpen(false); setEditCustomer(null); }}><Close /></IconButton>
            </Box>
            <Box sx={{ display: 'grid', gap: 2, flex: 1, overflowY: 'auto' }}>
              <TextField label="First Name" size="small" required value={editCustomer.firstname || ''} onChange={e => setEditCustomer({ ...editCustomer, firstname: e.target.value })} />
              <TextField label="Last Name" size="small" required value={editCustomer.lastname || ''} onChange={e => setEditCustomer({ ...editCustomer, lastname: e.target.value })} />
              <TextField label="Email" size="small" type="email" required value={editCustomer.email || ''} onChange={e => setEditCustomer({ ...editCustomer, email: e.target.value })} />
              <TextField label="Group ID" size="small" type="number" value={editCustomer.group_id ?? 0} onChange={e => setEditCustomer({ ...editCustomer, group_id: parseInt(e.target.value) })} helperText="0=General, 1=Wholesale, 2=Retailer" />
            </Box>
            <Divider sx={{ my: 2 }} />
            <Box sx={{ display: 'flex', gap: 2 }}>
              <Button variant="contained" startIcon={saving ? <Refresh sx={{ animation: 'spin 1s linear infinite' }} /> : <Save />} onClick={handleSave} disabled={saving || !editCustomer.email || !editCustomer.firstname || !editCustomer.lastname}>
                {saving ? 'Saving...' : 'Save Customer'}
              </Button>
              <Button variant="outlined" onClick={() => { setDrawerOpen(false); setEditCustomer(null); }}>Cancel</Button>
            </Box>
          </Box>
        )}
      </Drawer>

      <Dialog open={deleteDialog.open} onClose={() => setDeleteDialog({ open: false })} maxWidth="xs" fullWidth>
        <DialogTitle sx={{ color: 'error.main' }}>Delete Customer</DialogTitle>
        <DialogContent>
          <Typography variant="body2">Delete customer #{deleteDialog.id}? This cannot be undone.</Typography>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDeleteDialog({ open: false })} variant="outlined">Cancel</Button>
          <Button onClick={handleDeleteConfirm} variant="contained" color="error" autoFocus>Delete</Button>
        </DialogActions>
      </Dialog>

      <Snackbar open={!!toast} autoHideDuration={4000} onClose={() => setToast(null)} anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}>
        {toast ? <Alert severity={toast.severity} onClose={() => setToast(null)} variant="filled">{toast.message}</Alert> : undefined}
      </Snackbar>
    </Box>
  );
}

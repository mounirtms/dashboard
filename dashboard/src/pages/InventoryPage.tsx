import {
  Box, Typography, Card, CardContent, Button, Chip, Select, MenuItem,
  FormControl, InputLabel, Tooltip, IconButton, TextField, InputAdornment,
  Grid, Dialog, DialogTitle, DialogContent, DialogActions, Snackbar, Alert,
} from '@mui/material';
import { DataGrid, GridColDef } from '@mui/x-data-grid';
import {
  Refresh, Warning, CheckCircle, Edit, Search, Inventory as InventoryIcon,
  ErrorOutlined as ErrorOutline, TrendingDown, Save, Close,
} from '@mui/icons-material';
import { useState, useEffect, useCallback } from 'react';
import { fetchMagentoStock } from '../api/magento';
import apiClient from '../api/client';
import LoadingState from '../components/common/LoadingState';
import StatCard from '../components/common/StatCard';

const ENVS = [
  { key: 'prod',  label: 'Production' },
  { key: 'tsdnd', label: 'TSDND' },
  { key: 'dev',   label: 'Development' },
];

export default function InventoryPage() {
  const [items,       setItems]       = useState<any[]>([]);
  const [allItems,    setAllItems]    = useState<any[]>([]);   // unfiltered
  const [loading,     setLoading]     = useState(true);
  const [env,         setEnv]         = useState('prod');
  const [search,      setSearch]      = useState('');
  const [paginationModel, setPaginationModel] = useState({ page: 0, pageSize: 25 });
  const [editItem,    setEditItem]    = useState<any | null>(null);
  const [editQty,     setEditQty]     = useState('');
  const [saving,      setSaving]      = useState(false);
  const [toast,       setToast]       = useState<{ message: string; severity: 'success' | 'error' } | null>(null);

  const loadData = useCallback(() => {
    setLoading(true);
    fetchMagentoStock(env, paginationModel.page + 1)
      .then((data: any) => {
        const mapped = (data.items || []).map((item: any) => ({
          ...item,
          id: item.item_id || `${item.product_id}-${item.website_id}`,
        }));
        setAllItems(mapped);
      })
      .catch((e: any) => console.error(e))
      .finally(() => setLoading(false));
  }, [env, paginationModel.page]);

  useEffect(() => { loadData(); }, [loadData]);

  // Client-side search filter
  useEffect(() => {
    if (!search.trim()) {
      setItems(allItems);
    } else {
      const q = search.toLowerCase();
      setItems(allItems.filter(i => (i.sku || '').toLowerCase().includes(q)));
    }
  }, [search, allItems]);

  // Derived KPIs
  const outOfStock  = allItems.filter(i => !i.is_in_stock).length;
  const lowStock    = allItems.filter(i => i.is_in_stock && i.qty <= 5).length;
  const totalQty    = allItems.reduce((s, i) => s + (Number(i.qty) || 0), 0);
  const managed     = allItems.filter(i => i.manage_stock).length;

  const handleSaveQty = async () => {
    if (!editItem || editQty === '') return;
    setSaving(true);
    try {
      await apiClient.post(
        `/api/magento.php?action=stock_update&env=${env}&item_id=${editItem.item_id}`,
        { qty: Number(editQty), is_in_stock: Number(editQty) > 0 }
      );
      setToast({ message: `Stock for ${editItem.sku} updated to ${editQty}`, severity: 'success' });
      setEditItem(null);
      loadData();
    } catch (e: any) {
      setToast({ message: e.message || 'Update failed', severity: 'error' });
    } finally {
      setSaving(false);
    }
  };

  const columns: GridColDef[] = [
    {
      field: 'sku', headerName: 'SKU', width: 200,
      renderCell: (p) => (
        <Typography sx={{ fontFamily: 'monospace', fontWeight: 700, fontSize: '0.75rem' }}>{p.value}</Typography>
      ),
    },
    {
      field: 'qty', headerName: 'Qty', width: 100, type: 'number',
      renderCell: (p) => (
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
          <Typography sx={{ fontWeight: 700, color: p.value <= 0 ? 'error.main' : p.value <= 5 ? 'warning.main' : 'text.primary' }}>
            {p.value}
          </Typography>
          {p.value <= 0 && <ErrorOutline sx={{ fontSize: 14, color: 'error.main' }} />}
          {p.value > 0 && p.value <= 5 && <Warning sx={{ fontSize: 14, color: 'warning.main' }} />}
        </Box>
      ),
    },
    {
      field: 'is_in_stock', headerName: 'Availability', width: 140,
      renderCell: (p) => (
        <Chip
          label={p.value ? 'IN STOCK' : 'OUT OF STOCK'}
          size="small"
          color={p.value ? 'success' : 'error'}
          icon={p.value ? <CheckCircle sx={{ fontSize: 12 }} /> : <ErrorOutline sx={{ fontSize: 12 }} />}
          sx={{ fontWeight: 800, fontSize: '0.65rem' }}
        />
      ),
    },
    {
      field: 'min_qty', headerName: 'Min Qty', width: 90, type: 'number',
      renderCell: (p) => <Typography sx={{ fontSize: '0.78rem' }}>{p.value ?? 0}</Typography>,
    },
    {
      field: 'manage_stock', headerName: 'Managed', width: 100, type: 'boolean',
    },
    {
      field: 'actions', headerName: 'Operations', width: 120, sortable: false, filterable: false,
      renderCell: (p) => (
        <Box sx={{ display: 'flex', gap: 0.5 }}>
          <Tooltip title="Update Stock">
            <IconButton size="small" onClick={() => { setEditItem(p.row); setEditQty(String(p.row.qty ?? 0)); }}>
              <Edit sx={{ fontSize: 16 }} />
            </IconButton>
          </Tooltip>
        </Box>
      ),
    },
  ];

  return (
    <Box>
      {/* ── Header ── */}
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 2 }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Inventory Management
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Monitor and update product stock levels across Magento environments.
          </Typography>
        </Box>
        <Box sx={{ display: 'flex', gap: 1.5, alignItems: 'center' }}>
          <FormControl size="small" sx={{ minWidth: 140 }}>
            <InputLabel>Environment</InputLabel>
            <Select value={env} label="Environment" onChange={(e) => { setEnv(e.target.value); setPaginationModel(m => ({ ...m, page: 0 })); }}>
              {ENVS.map(e => <MenuItem key={e.key} value={e.key}>{e.label}</MenuItem>)}
            </Select>
          </FormControl>
          <Button variant="contained" startIcon={<Refresh />} onClick={loadData} disabled={loading}>
            Sync Stock
          </Button>
        </Box>
      </Box>

      {/* ── KPI cards ── */}
      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 6, sm: 3 }}>
          <StatCard label="Total SKUs"     value={allItems.length}    color="primary" icon={<InventoryIcon />} />
        </Grid>
        <Grid size={{ xs: 6, sm: 3 }}>
          <StatCard label="Out of Stock"   value={outOfStock}         color="error"   icon={<ErrorOutline />} />
        </Grid>
        <Grid size={{ xs: 6, sm: 3 }}>
          <StatCard label="Low Stock (≤5)" value={lowStock}           color="warning" icon={<TrendingDown />} />
        </Grid>
        <Grid size={{ xs: 6, sm: 3 }}>
          <StatCard label="Total Units"    value={totalQty.toLocaleString()} color="success" />
        </Grid>
      </Grid>

      {/* ── Search + status chips ── */}
      <Box sx={{ mb: 2, display: 'flex', gap: 2, alignItems: 'center', flexWrap: 'wrap' }}>
        <TextField
          size="small"
          placeholder="Search by SKU..."
          value={search}
          onChange={e => setSearch(e.target.value)}
          sx={{ width: 280 }}
          slotProps={{ input: { startAdornment: <InputAdornment position="start"><Search sx={{ fontSize: 18, color: 'text.disabled' }} /></InputAdornment> } }}
        />
        {outOfStock > 0 && (
          <Chip
            icon={<ErrorOutline sx={{ fontSize: 14 }} />}
            label={`${outOfStock} out of stock`}
            size="small" color="error" variant="outlined"
          />
        )}
        {lowStock > 0 && (
          <Chip
            icon={<Warning sx={{ fontSize: 14 }} />}
            label={`${lowStock} low stock`}
            size="small" color="warning" variant="outlined"
          />
        )}
        <Chip
          label={`${managed} managed`}
          size="small" color="info" variant="outlined"
        />
      </Box>

      {/* ── DataGrid ── */}
      <Card>
        <DataGrid
          rows={items}
          columns={columns}
          loading={loading}
          paginationModel={paginationModel}
          onPaginationModelChange={setPaginationModel}
          pageSizeOptions={[25, 50, 100]}
          disableRowSelectionOnClick
          density="compact"
          sx={{
            border: 'none',
            '& .MuiDataGrid-columnHeaderTitle': { fontWeight: 700, fontSize: '0.7rem', color: 'text.secondary' },
            '& .MuiDataGrid-cell': { fontSize: '0.78rem' },
          }}
          autoHeight
        />
      </Card>

      {/* ── Edit qty dialog ── */}
      <Dialog open={!!editItem} onClose={() => setEditItem(null)} maxWidth="xs" fullWidth>
        <DialogTitle sx={{ fontWeight: 800 }}>
          Update Stock — <Typography component="span" sx={{ fontFamily: 'monospace', color: 'primary.main' }}>{editItem?.sku}</Typography>
        </DialogTitle>
        <DialogContent>
          <Box sx={{ pt: 1, display: 'grid', gap: 2 }}>
            <TextField
              label="New Quantity"
              type="number"
              size="small"
              fullWidth
              value={editQty}
              onChange={e => setEditQty(e.target.value)}
              autoFocus
              slotProps={{ htmlInput: { min: 0, step: 1 } }}
            />
            <Typography variant="caption" color="text.disabled">
              Current: <strong>{editItem?.qty}</strong> &nbsp;|&nbsp; Min Qty: <strong>{editItem?.min_qty ?? 0}</strong>
            </Typography>
          </Box>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setEditItem(null)} startIcon={<Close />}>Cancel</Button>
          <Button
            variant="contained"
            startIcon={<Save />}
            onClick={handleSaveQty}
            disabled={saving || editQty === '' || isNaN(Number(editQty))}
          >
            {saving ? 'Saving...' : 'Save'}
          </Button>
        </DialogActions>
      </Dialog>

      <Snackbar open={!!toast} autoHideDuration={4000} onClose={() => setToast(null)} anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}>
        {toast ? <Alert severity={toast.severity} onClose={() => setToast(null)} variant="filled">{toast.message}</Alert> : undefined}
      </Snackbar>
    </Box>
  );
}

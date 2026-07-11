import { Box, Typography, Button, Chip, Drawer, IconButton, Tooltip, Snackbar, Alert, InputAdornment, TextField, Divider, Grid, Card, CardContent, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Paper, FormControl, InputLabel, Select, MenuItem, Dialog, DialogTitle, DialogContent, DialogActions } from '@mui/material';
import { DataGrid, GridColDef } from '@mui/x-data-grid';
import { Search, Refresh, Close, LocalShipping, Receipt, Cancel, Pause, PlayArrow, Visibility, Send } from '@mui/icons-material';
import { useState, useEffect, useCallback } from 'react';
import { fetchMagentoOrders, performOrderAction } from '../api/magento';
import LoadingState from '../components/common/LoadingState';
import PermissionGate from '../components/common/PermissionGate';

const ENVS = [
  { key: 'prod', label: 'Production' },
  { key: 'tsdnd', label: 'TSDND' },
  { key: 'dev', label: 'Development' },
];

const STATUS_COLORS: Record<string, 'success' | 'warning' | 'error' | 'info' | 'default'> = {
  processing: 'info',
  complete: 'success',
  closed: 'default',
  canceled: 'error',
  pending: 'warning',
  holded: 'warning',
  pending_payment: 'warning',
};

export default function MagentoOrdersPage() {
  const [env, setEnv] = useState('prod');
  const [orders, setOrders] = useState<any[]>([]);
  const [totalCount, setTotalCount] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(0);
  const [pageSize, setPageSize] = useState(20);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [detailOrder, setDetailOrder] = useState<any>(null);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [toast, setToast] = useState<{ message: string; severity: 'success' | 'error' } | null>(null);
  const [actionLoading, setActionLoading] = useState<number | null>(null);
  const [orderActionDialog, setOrderActionDialog] = useState<{ open: boolean; orderId?: number; op?: string }>({ open: false });

  const loadOrders = useCallback(async () => {
    setLoading(true);
    try {
      const data = await fetchMagentoOrders(env, page + 1, pageSize);
      let items = (data.items || []);
      if (search) {
        const q = search.toLowerCase();
        items = items.filter((o: any) => o.increment_id?.toLowerCase().includes(q) || o.customer_email?.toLowerCase().includes(q) || `${o.customer_firstname} ${o.customer_lastname}`.toLowerCase().includes(q));
      }
      if (statusFilter) {
        items = items.filter((o: any) => o.status === statusFilter);
      }
      setOrders(items);
      setTotalCount(data.total_count || 0);
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, [page, pageSize, search, statusFilter, env]);

  useEffect(() => { loadOrders(); }, [loadOrders]);

  const handleOrderAction = (orderId: number, op: 'cancel' | 'hold' | 'unhold' | 'ship' | 'invoice') => {
    setOrderActionDialog({ open: true, orderId, op });
  };

  const handleOrderActionConfirm = async () => {
    const { orderId, op } = orderActionDialog;
    setOrderActionDialog({ open: false });
    if (!orderId || !op) return;
    setActionLoading(orderId);
    try {
      await performOrderAction(orderId, op as any, env);
      setToast({ message: `Order #${orderId} ${op} successful`, severity: 'success' });
      loadOrders();
    } catch (e: any) {
      setToast({ message: e.message, severity: 'error' });
    } finally {
      setActionLoading(null);
    }
  };

  const columns: GridColDef[] = [
    { field: 'increment_id', headerName: 'Order #', width: 120, renderCell: (p) => <Typography sx={{ fontFamily: 'monospace', fontWeight: 700, fontSize: '0.78rem' }}>{p.value}</Typography> },
    { field: 'created_at', headerName: 'Date', width: 110, renderCell: (p) => p.value ? new Date(p.value).toLocaleDateString() : '' },
    { field: 'customer', headerName: 'Customer', flex: 1, minWidth: 160, valueGetter: (v, r) => `${r.customer_firstname || ''} ${r.customer_lastname || ''}`.trim() || r.customer_email },
    { field: 'status', headerName: 'Status', width: 110, renderCell: (p) => <Chip label={p.value?.toUpperCase()} size="small" color={STATUS_COLORS[p.value] || 'default'} sx={{ fontSize: '0.65rem', fontWeight: 700 }} /> },
    { field: 'total_item_count', headerName: 'Items', width: 70, type: 'number' },
    { field: 'base_grand_total', headerName: 'Total', width: 110, type: 'number', renderCell: (p) => <Typography sx={{ fontWeight: 700, fontSize: '0.78rem' }}>{p.row.order_currency_code || 'DZD'} {Number(p.value || 0).toFixed(2)}</Typography> },
    { field: 'payment_method', headerName: 'Payment', width: 120, valueGetter: (v, r) => r.payment?.method || '' },
    { field: 'actions', headerName: '', width: 160, sortable: false, filterable: false, renderCell: (p) => (
      <Box sx={{ display: 'flex', gap: 0.3, flexWrap: 'wrap' }}>
        <Tooltip title="View Details">
          <IconButton size="small" onClick={() => { setDetailOrder(p.row); setDrawerOpen(true); }}><Visibility sx={{ fontSize: 16 }} /></IconButton>
        </Tooltip>
        <PermissionGate permission="can_manage_orders">
          {p.row.status === 'processing' && (
            <>
              <Tooltip title="Ship"><IconButton size="small" color="primary" onClick={() => handleOrderAction(p.row.entity_id, 'ship')} disabled={actionLoading === p.row.entity_id}><LocalShipping sx={{ fontSize: 16 }} /></IconButton></Tooltip>
              <Tooltip title="Invoice"><IconButton size="small" color="info" onClick={() => handleOrderAction(p.row.entity_id, 'invoice')} disabled={actionLoading === p.row.entity_id}><Receipt sx={{ fontSize: 16 }} /></IconButton></Tooltip>
              <Tooltip title="Hold"><IconButton size="small" color="warning" onClick={() => handleOrderAction(p.row.entity_id, 'hold')} disabled={actionLoading === p.row.entity_id}><Pause sx={{ fontSize: 16 }} /></IconButton></Tooltip>
              <Tooltip title="Cancel"><IconButton size="small" color="error" onClick={() => handleOrderAction(p.row.entity_id, 'cancel')} disabled={actionLoading === p.row.entity_id}><Cancel sx={{ fontSize: 16 }} /></IconButton></Tooltip>
            </>
          )}
          {p.row.status === 'holded' && (
            <Tooltip title="Unhold"><IconButton size="small" color="success" onClick={() => handleOrderAction(p.row.entity_id, 'unhold')} disabled={actionLoading === p.row.entity_id}><PlayArrow sx={{ fontSize: 16 }} /></IconButton></Tooltip>
          )}
        </PermissionGate>
      </Box>
    )},
  ];

  if (error && !orders.length) return <LoadingState message={`Error: ${error}`} />;

  return (
    <Box>
      <Box sx={{ mb: 2, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>Orders</Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>{totalCount} total orders</Typography>
        </Box>
        <Box sx={{ display: 'flex', gap: 1, alignItems: 'center' }}>
          <FormControl size="small" sx={{ minWidth: 140 }}>
            <Select value={env} onChange={e => { setEnv(e.target.value); setPage(0); }} sx={{ fontWeight: 700, fontSize: '0.8rem' }}>
              {ENVS.map(e => <MenuItem key={e.key} value={e.key}>{e.label}</MenuItem>)}
            </Select>
          </FormControl>
          <Button variant="outlined" startIcon={<Refresh />} onClick={loadOrders}>Refresh</Button>
        </Box>
      </Box>

      {/* H1 2026 KPI Banner */}
      {env === 'prod' && (
        <Box sx={{ mb: 2, p: 1.5, borderRadius: 1.5, background: 'linear-gradient(135deg, rgba(34,197,94,0.08) 0%, rgba(59,130,246,0.06) 100%)', border: '1px solid rgba(34,197,94,0.2)', display: 'flex', gap: 3, flexWrap: 'wrap', alignItems: 'center' }}>
          <Box>
            <Typography variant="caption" sx={{ color: 'text.disabled', textTransform: 'uppercase', letterSpacing: 0.8, fontWeight: 700 }}>H1 2026 Orders</Typography>
            <Typography variant="h5" sx={{ fontWeight: 900, color: 'success.main', lineHeight: 1 }}>875</Typography>
            <Typography variant="caption" sx={{ color: 'text.disabled' }}>Jan–Jun · MariaDB verified</Typography>
          </Box>
          <Box>
            <Typography variant="caption" sx={{ color: 'text.disabled', textTransform: 'uppercase', letterSpacing: 0.8, fontWeight: 700 }}>H1 2025 Orders</Typography>
            <Typography variant="h5" sx={{ fontWeight: 900, color: '#64748b', lineHeight: 1 }}>844</Typography>
            <Typography variant="caption" sx={{ color: 'text.disabled' }}>Jan–Jun · Prior year</Typography>
          </Box>
          <Box>
            <Typography variant="caption" sx={{ color: 'text.disabled', textTransform: 'uppercase', letterSpacing: 0.8, fontWeight: 700 }}>YoY Growth</Typography>
            <Typography variant="h5" sx={{ fontWeight: 900, color: '#22c55e', lineHeight: 1 }}>+3.7%</Typography>
            <Typography variant="caption" sx={{ color: 'text.disabled' }}>875 vs 844</Typography>
          </Box>
          <Box>
            <Typography variant="caption" sx={{ color: 'text.disabled', textTransform: 'uppercase', letterSpacing: 0.8, fontWeight: 700 }}>Avg Order Value</Typography>
            <Typography variant="h5" sx={{ fontWeight: 900, color: '#3b82f6', lineHeight: 1 }}>4,970 DZD</Typography>
            <Typography variant="caption" sx={{ color: 'text.disabled' }}>H1 2026</Typography>
          </Box>
        </Box>
      )}

      <Box sx={{ mb: 2, display: 'flex', gap: 2 }}>
        <TextField size="small" placeholder="Search order #, email, name..." value={search} onChange={e => setSearch(e.target.value)} sx={{ width: 280 }}
          slotProps={{ input: { startAdornment: <InputAdornment position="start"><Search sx={{ fontSize: 18, color: 'text.disabled' }} /></InputAdornment> } }}
        />
        <FormControl size="small" sx={{ width: 160 }}>
          <InputLabel>Status</InputLabel>
          <Select value={statusFilter} label="Status" onChange={e => setStatusFilter(e.target.value)}>
            <MenuItem value="">All</MenuItem>
            <MenuItem value="pending">Pending</MenuItem>
            <MenuItem value="processing">Processing</MenuItem>
            <MenuItem value="complete">Complete</MenuItem>
            <MenuItem value="closed">Closed</MenuItem>
            <MenuItem value="canceled">Canceled</MenuItem>
            <MenuItem value="holded">Holded</MenuItem>
          </Select>
        </FormControl>
      </Box>

      <DataGrid
        rows={orders} columns={columns} rowCount={totalCount} loading={loading}
        pageSizeOptions={[10, 20, 50, 100]}
        paginationMode="server"
        paginationModel={{ page, pageSize }}
        onPaginationModelChange={m => { setPage(m.page); setPageSize(m.pageSize); }}
        getRowId={r => r.entity_id}
        disableRowSelectionOnClick
        sx={{ border: '1px solid', borderColor: 'divider', borderRadius: 2, '& .MuiDataGrid-cell': { fontSize: '0.78rem' } }}
        autoHeight
      />

      <Drawer anchor="right" open={drawerOpen} onClose={() => { setDrawerOpen(false); setDetailOrder(null); }} slotProps={{ paper: { sx: { width: 520, bgcolor: 'background.paper' } } }}>
        {detailOrder && (
          <Box sx={{ p: 3 }}>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 3 }}>
              <Typography variant="h6" sx={{ fontWeight: 800 }}>Order #{detailOrder.increment_id}</Typography>
              <IconButton onClick={() => { setDrawerOpen(false); setDetailOrder(null); }}><Close /></IconButton>
            </Box>

            <Grid container spacing={2} sx={{ mb: 3 }}>
              <Grid size={{ xs: 6 }}><Typography variant="caption" color="text.disabled">Status</Typography><br /><Chip label={detailOrder.status?.toUpperCase()} size="small" color={STATUS_COLORS[detailOrder.status] || 'default'} /></Grid>
              <Grid size={{ xs: 6 }}><Typography variant="caption" color="text.disabled">Date</Typography><br /><Typography variant="body2">{new Date(detailOrder.created_at).toLocaleString()}</Typography></Grid>
              <Grid size={{ xs: 6 }}><Typography variant="caption" color="text.disabled">Customer</Typography><br /><Typography variant="body2">{detailOrder.customer_firstname} {detailOrder.customer_lastname}</Typography><Typography variant="caption" color="text.disabled">{detailOrder.customer_email}</Typography></Grid>
              <Grid size={{ xs: 6 }}><Typography variant="caption" color="text.disabled">Total</Typography><br /><Typography variant="body1" sx={{ fontWeight: 800 }}>{detailOrder.order_currency_code} {Number(detailOrder.base_grand_total || 0).toFixed(2)}</Typography></Grid>
            </Grid>

            <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1 }}>Order Items</Typography>
            <TableContainer component={Paper} variant="outlined">
              <Table size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>Product</TableCell>
                    <TableCell>SKU</TableCell>
                    <TableCell align="right">Qty</TableCell>
                    <TableCell align="right">Price</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {(detailOrder.items || []).map((item: any) => (
                    <TableRow key={item.item_id}>
                      <TableCell>{item.name}</TableCell>
                      <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.7rem' }}>{item.sku}</TableCell>
                      <TableCell align="right">{item.qty_ordered}</TableCell>
                      <TableCell align="right" sx={{ fontWeight: 700 }}>{Number(item.price || 0).toFixed(2)}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>

            {detailOrder.status === 'processing' && (
              <Box sx={{ mt: 3, display: 'flex', gap: 1, flexWrap: 'wrap' }}>
                <PermissionGate permission="can_manage_orders">
                  <Button size="small" variant="outlined" startIcon={<LocalShipping />} onClick={() => handleOrderAction(detailOrder.entity_id, 'ship')}>Ship</Button>
                  <Button size="small" variant="outlined" startIcon={<Receipt />} onClick={() => handleOrderAction(detailOrder.entity_id, 'invoice')}>Invoice</Button>
                  <Button size="small" variant="outlined" color="warning" startIcon={<Pause />} onClick={() => handleOrderAction(detailOrder.entity_id, 'hold')}>Hold</Button>
                  <Button size="small" variant="outlined" color="error" startIcon={<Cancel />} onClick={() => handleOrderAction(detailOrder.entity_id, 'cancel')}>Cancel</Button>
                </PermissionGate>
              </Box>
            )}
          </Box>
        )}
      </Drawer>

      {/* Order Action Confirmation Dialog */}
      <Dialog open={orderActionDialog.open} onClose={() => setOrderActionDialog({ open: false })} maxWidth="xs" fullWidth>
        <DialogTitle sx={{ textTransform: 'capitalize' }}>
          {orderActionDialog.op} Order #{orderActionDialog.orderId}?
        </DialogTitle>
        <DialogContent>
          <Typography variant="body2">
            Are you sure you want to <strong>{orderActionDialog.op}</strong> order <strong>#{orderActionDialog.orderId}</strong>?
          </Typography>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setOrderActionDialog({ open: false })} variant="outlined">Cancel</Button>
          <Button onClick={handleOrderActionConfirm} variant="contained" color="warning" autoFocus sx={{ textTransform: 'capitalize' }}>
            {orderActionDialog.op}
          </Button>
        </DialogActions>
      </Dialog>

      <Snackbar open={!!toast} autoHideDuration={4000} onClose={() => setToast(null)} anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}>
        {toast ? <Alert severity={toast.severity} onClose={() => setToast(null)} variant="filled">{toast.message}</Alert> : undefined}
      </Snackbar>
    </Box>
  );
}

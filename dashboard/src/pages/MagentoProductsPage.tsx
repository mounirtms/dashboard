import { Box, Typography, Button, TextField, Chip, Dialog, DialogTitle, DialogContent, DialogActions, Select, MenuItem, FormControl, InputLabel, IconButton, Tooltip, Alert, Snackbar, Drawer, Grid, Card, CardContent, InputAdornment, Divider } from '@mui/material';
import { DataGrid, GridColDef } from '@mui/x-data-grid';
import { Add, Delete, Edit, CloudUpload, FileDownload, Search, Refresh, Close, Save, Image as ImageIcon, Visibility, VisibilityOff } from '@mui/icons-material';
import { useState, useEffect, useCallback, useRef } from 'react';
import { fetchMagentoProducts, saveMagentoProduct, deleteMagentoProduct, uploadProductMedia, type MagentoProduct } from '../api/magento';
import LoadingState from '../components/common/LoadingState';
import PermissionGate from '../components/common/PermissionGate';

const ENVS = [
  { key: 'prod', label: 'Production' },
  { key: 'tsdnd', label: 'TSDND' },
  { key: 'dev', label: 'Development' },
];

export default function MagentoProductsPage() {
  const [env, setEnv] = useState('prod');
  const [products, setProducts] = useState<MagentoProduct[]>([]);
  const [totalCount, setTotalCount] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(0);
  const [pageSize, setPageSize] = useState(20);
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [selection, setSelection] = useState<number[]>([]);
  const [editProduct, setEditProduct] = useState<Partial<MagentoProduct> | null>(null);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState<{ message: string; severity: 'success' | 'error' } | null>(null);
  const [uploadOpen, setUploadOpen] = useState(false);
  const [deleteDialog, setDeleteDialog] = useState<{ open: boolean; sku?: string; bulkSkus?: string[] }>({ open: false });
  const [uploadSku, setUploadSku] = useState('');
  const [uploadFiles, setUploadFiles] = useState<File[]>([]);
  const [uploading, setUploading] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    const t = setTimeout(() => setDebouncedSearch(search), 300);
    return () => clearTimeout(t);
  }, [search]);

  const loadProducts = useCallback(async () => {
    setLoading(true);
    try {
      const data = await fetchMagentoProducts(env, page + 1, pageSize, debouncedSearch || undefined);
      setProducts(data.items || []);
      setTotalCount(data.total_count || 0);
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, [page, pageSize, debouncedSearch, env]);

  useEffect(() => { loadProducts(); }, [loadProducts]);

  const handleDelete = (sku: string) => {
    setDeleteDialog({ open: true, sku });
  };

  const handleBulkDelete = () => {
    const skus = selection.map(id => products.find(p => p.id === id)?.sku).filter((s): s is string => !!s);
    if (!skus.length) return;
    setDeleteDialog({ open: true, bulkSkus: skus });
  };

  const handleDeleteConfirm = async () => {
    const { sku, bulkSkus } = deleteDialog;
    setDeleteDialog({ open: false });
    try {
      if (bulkSkus) {
        for (const s of bulkSkus) {
          try { await deleteMagentoProduct(s, env); } catch { /* continue */ }
        }
        setToast({ message: `${bulkSkus.length} product(s) deleted`, severity: 'success' });
        setSelection([]);
      } else if (sku) {
        await deleteMagentoProduct(sku, env);
        setToast({ message: `Product ${sku} deleted`, severity: 'success' });
      }
      loadProducts();
    } catch (e: any) {
      setToast({ message: e.message, severity: 'error' });
    }
  };

  const handleSave = async () => {
    if (!editProduct) return;
    setSaving(true);
    try {
      await saveMagentoProduct(editProduct, env);
      setToast({ message: 'Product saved', severity: 'success' });
      setDrawerOpen(false);
      setEditProduct(null);
      loadProducts();
    } catch (e: any) {
      setToast({ message: e.message, severity: 'error' });
    } finally {
      setSaving(false);
    }
  };

  const openNewProduct = () => {
    setEditProduct({ sku: '', name: '', price: 0, status: 1, visibility: 4, type_id: 'simple' });
    setDrawerOpen(true);
  };

  const openEditProduct = (product: MagentoProduct) => {
    setEditProduct({ ...product });
    setDrawerOpen(true);
  };

  const handleUploadSubmit = async () => {
    if (!uploadSku || !uploadFiles.length) return;
    setUploading(true);
    for (const file of uploadFiles) {
      const reader = new FileReader();
      const base64 = await new Promise<string>((resolve) => {
        reader.onload = () => resolve((reader.result as string).split(',')[1]);
        reader.readAsDataURL(file);
      });
      try {
        await uploadProductMedia(uploadSku, {
          media_type: 'image',
          label: file.name.replace(/\.[^.]+$/, ''),
          file: { name: file.name, base64_encoded_data: base64 }
        }, env);
      } catch (e: any) {
        setToast({ message: `Upload failed for ${file.name}: ${e.message}`, severity: 'error' });
      }
    }
    setUploading(false);
    setUploadOpen(false);
    setUploadFiles([]);
    setToast({ message: 'Images uploaded', severity: 'success' });
  };

  const getAttributeValue = (product: MagentoProduct, code: string): any => {
    return product.custom_attributes?.find(a => a.attribute_code === code)?.value;
  };

  const columns: GridColDef[] = [
    { field: 'sku', headerName: 'SKU', width: 140, renderCell: (p) => <Typography sx={{ fontFamily: 'monospace', fontWeight: 700, fontSize: '0.75rem' }}>{p.value}</Typography> },
    { field: 'name', headerName: 'Name', flex: 1, minWidth: 200 },
    { field: 'type_id', headerName: 'Type', width: 90, renderCell: (p) => <Chip label={p.value} size="small" variant="outlined" sx={{ fontSize: '0.65rem' }} /> },
    { field: 'price', headerName: 'Price', width: 100, type: 'number', renderCell: (p) => <Typography sx={{ fontWeight: 700 }}>DZD {Number(p.value || 0).toFixed(2)}</Typography> },
    { field: 'status', headerName: 'Status', width: 90, renderCell: (p) => <Chip label={p.value === 1 ? 'Enabled' : 'Disabled'} size="small" color={p.value === 1 ? 'success' : 'default'} sx={{ fontSize: '0.65rem' }} /> },
    { field: 'visibility', headerName: 'Visibility', width: 100, renderCell: (p) => {
      const labels: Record<number, string> = { 1: 'Not Visible', 2: 'Catalog', 3: 'Search', 4: 'Both' };
      return <Chip label={labels[p.value] || p.value} size="small" variant="outlined" sx={{ fontSize: '0.65rem' }} />;
    }},
    { field: 'actions', headerName: '', width: 100, sortable: false, filterable: false, renderCell: (p) => (
      <Box sx={{ display: 'flex', gap: 0.5 }}>
        <PermissionGate permission="can_edit_products">
          <Tooltip title="Edit">
            <IconButton size="small" onClick={() => openEditProduct(p.row)}><Edit sx={{ fontSize: 16 }} /></IconButton>
          </Tooltip>
        </PermissionGate>
        <PermissionGate permission="can_edit_products">
          <Tooltip title="Delete">
            <IconButton size="small" color="error" onClick={() => handleDelete(p.row.sku)}><Delete sx={{ fontSize: 16 }} /></IconButton>
          </Tooltip>
        </PermissionGate>
      </Box>
    )},
  ];

  if (error && !products.length) return <LoadingState message={`Error: ${error}`} />;

  return (
    <Box>
      <Box sx={{ mb: 2, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>Products</Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Manage Magento product catalog — {totalCount} products
          </Typography>
        </Box>
        <Box sx={{ display: 'flex', gap: 1, alignItems: 'center' }}>
          <FormControl size="small" sx={{ minWidth: 140 }}>
            <Select value={env} onChange={e => { setEnv(e.target.value); setPage(0); }} sx={{ fontWeight: 700, fontSize: '0.8rem' }}>
              {ENVS.map(e => <MenuItem key={e.key} value={e.key}>{e.label}</MenuItem>)}
            </Select>
          </FormControl>
          <PermissionGate permission="can_edit_products">
            <Button variant="contained" startIcon={<Add />} onClick={openNewProduct}>Add Product</Button>
          </PermissionGate>
          <PermissionGate permission="can_bulk_products">
            <Button variant="outlined" startIcon={<CloudUpload />} onClick={() => { setUploadSku(selection.length === 1 ? (products.find(p => p.id === selection[0])?.sku || '') : ''); setUploadOpen(true); }}>
              Upload Images
            </Button>
          </PermissionGate>
          <Button variant="outlined" startIcon={<Refresh />} onClick={loadProducts}>Refresh</Button>
        </Box>
      </Box>

      {selection.length > 0 && (
        <Alert severity="info" action={
          <PermissionGate permission="can_bulk_products">
            <Button color="error" size="small" startIcon={<Delete />} onClick={handleBulkDelete}>
              Delete {selection.length} selected
            </Button>
          </PermissionGate>
        } sx={{ mb: 2 }}>
          {selection.length} product(s) selected
        </Alert>
      )}

      <Box sx={{ mb: 2 }}>
        <TextField size="small" placeholder="Search by name or SKU..." value={search} onChange={e => setSearch(e.target.value)} sx={{ width: 320 }}
          slotProps={{ input: { startAdornment: <InputAdornment position="start"><Search sx={{ fontSize: 18, color: 'text.disabled' }} /></InputAdornment> } }}
        />
      </Box>

      <DataGrid
        rows={products}
        columns={columns}
        rowCount={totalCount}
        loading={loading}
        pageSizeOptions={[10, 20, 50, 100]}
        paginationMode="server"
        paginationModel={{ page, pageSize }}
        onPaginationModelChange={m => { setPage(m.page); setPageSize(m.pageSize); }}
        checkboxSelection
        onRowSelectionModelChange={(model: any) => {
          const ids = model?.ids ? Array.from(model.ids) as number[] : [];
          setSelection(ids);
        }}
        rowSelectionModel={selection as any}
        getRowId={r => r.id || r.sku}
        disableRowSelectionOnClick
        sx={{ border: '1px solid', borderColor: 'divider', borderRadius: 2, '& .MuiDataGrid-cell': { fontSize: '0.78rem' } }}
        autoHeight
      />

      {/* Edit Drawer */}
      <Drawer anchor="right" open={drawerOpen} onClose={() => { setDrawerOpen(false); setEditProduct(null); }} slotProps={{ paper: { sx: { width: 480, bgcolor: 'background.paper' } } }}>
        {editProduct && (
          <Box sx={{ p: 3, display: 'flex', flexDirection: 'column', height: '100%' }}>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 3 }}>
              <Typography variant="h6" sx={{ fontWeight: 800 }}>{editProduct.id ? 'Edit Product' : 'New Product'}</Typography>
              <IconButton onClick={() => { setDrawerOpen(false); setEditProduct(null); }}><Close /></IconButton>
            </Box>
            <Box sx={{ display: 'grid', gap: 2, flex: 1, overflowY: 'auto' }}>
              <TextField label="SKU" size="small" required value={editProduct.sku || ''} onChange={e => setEditProduct({ ...editProduct, sku: e.target.value })} disabled={!!editProduct.id} />
              <TextField label="Name" size="small" required value={editProduct.name || ''} onChange={e => setEditProduct({ ...editProduct, name: e.target.value })} />
              <TextField label="Price" size="small" type="number" value={editProduct.price || 0} onChange={e => setEditProduct({ ...editProduct, price: parseFloat(e.target.value) })} />
              <FormControl size="small">
                <InputLabel>Type</InputLabel>
                <Select value={editProduct.type_id || 'simple'} label="Type" onChange={e => setEditProduct({ ...editProduct, type_id: e.target.value })}>
                  <MenuItem value="simple">Simple</MenuItem>
                  <MenuItem value="configurable">Configurable</MenuItem>
                  <MenuItem value="virtual">Virtual</MenuItem>
                  <MenuItem value="downloadable">Downloadable</MenuItem>
                  <MenuItem value="grouped">Grouped</MenuItem>
                  <MenuItem value="bundle">Bundle</MenuItem>
                </Select>
              </FormControl>
              <FormControl size="small">
                <InputLabel>Status</InputLabel>
                <Select value={editProduct.status ?? 1} label="Status" onChange={e => setEditProduct({ ...editProduct, status: Number(e.target.value) })}>
                  <MenuItem value={1}>Enabled</MenuItem>
                  <MenuItem value={2}>Disabled</MenuItem>
                </Select>
              </FormControl>
              <FormControl size="small">
                <InputLabel>Visibility</InputLabel>
                <Select value={editProduct.visibility ?? 4} label="Visibility" onChange={e => setEditProduct({ ...editProduct, visibility: Number(e.target.value) })}>
                  <MenuItem value={1}>Not Visible Individually</MenuItem>
                  <MenuItem value={2}>Catalog</MenuItem>
                  <MenuItem value={3}>Search</MenuItem>
                  <MenuItem value={4}>Catalog, Search</MenuItem>
                </Select>
              </FormControl>
              <TextField label="Short Description" size="small" multiline rows={3} value={getAttributeValue(editProduct as MagentoProduct, 'description') || ''} onChange={e => {
                const attrs = [...(editProduct.custom_attributes || [])];
                const idx = attrs.findIndex(a => a.attribute_code === 'description');
                if (idx >= 0) attrs[idx].value = e.target.value;
                else attrs.push({ attribute_code: 'description', value: e.target.value });
                setEditProduct({ ...editProduct, custom_attributes: attrs });
              }} />
            </Box>
            <Divider sx={{ my: 2 }} />
            <Box sx={{ display: 'flex', gap: 2 }}>
              <Button variant="contained" startIcon={saving ? <Refresh sx={{ animation: 'spin 1s linear infinite' }} /> : <Save />} onClick={handleSave} disabled={saving || !editProduct.sku || !editProduct.name}>
                {saving ? 'Saving...' : 'Save Product'}
              </Button>
              <Button variant="outlined" onClick={() => { setDrawerOpen(false); setEditProduct(null); }}>Cancel</Button>
            </Box>
          </Box>
        )}
      </Drawer>

      {/* Image Upload Dialog */}
      <Dialog open={uploadOpen} onClose={() => setUploadOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Upload Product Images</DialogTitle>
        <DialogContent>
          <Box sx={{ display: 'grid', gap: 2, mt: 1 }}>
            <TextField label="Product SKU" size="small" value={uploadSku} onChange={e => setUploadSku(e.target.value)} />
            <Button variant="outlined" startIcon={<ImageIcon />} onClick={() => fileInputRef.current?.click()}>
              {uploadFiles.length ? `${uploadFiles.length} file(s) selected` : 'Select Images'}
            </Button>
            <input ref={fileInputRef} type="file" multiple accept="image/*" hidden onChange={e => setUploadFiles(Array.from(e.target.files || []))} />
            {uploadFiles.length > 0 && (
              <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1 }}>
                {uploadFiles.map((f, i) => <Chip key={i} label={f.name} size="small" onDelete={() => setUploadFiles(uploadFiles.filter((_, j) => j !== i))} />)}
              </Box>
            )}
          </Box>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setUploadOpen(false)}>Cancel</Button>
          <Button variant="contained" onClick={handleUploadSubmit} disabled={uploading || !uploadSku || !uploadFiles.length}>
            {uploading ? 'Uploading...' : 'Upload'}
          </Button>
        </DialogActions>
      </Dialog>

      {/* Delete Confirmation Dialog */}
      <Dialog open={deleteDialog.open} onClose={() => setDeleteDialog({ open: false })} maxWidth="xs" fullWidth>
        <DialogTitle sx={{ color: 'error.main' }}>Confirm Delete</DialogTitle>
        <DialogContent>
          <Typography variant="body2">
            {deleteDialog.bulkSkus
              ? `Delete ${deleteDialog.bulkSkus.length} selected product(s)? This cannot be undone.`
              : `Delete product "${deleteDialog.sku}"? This cannot be undone.`}
          </Typography>
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

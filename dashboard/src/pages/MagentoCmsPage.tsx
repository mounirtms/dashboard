import { Box, Typography, Button, Chip, Drawer, IconButton, Tooltip, Snackbar, Alert, Tabs, Tab, TextField, Dialog, DialogTitle, DialogContent, DialogActions, Divider, List, ListItemButton, ListItemText, Collapse, FormControlLabel, Switch, FormControl, Select, MenuItem } from '@mui/material';
import { DataGrid, GridColDef } from '@mui/x-data-grid';
import { Add, Delete, Edit, Refresh, Close, Save, ExpandLess, ExpandMore, FolderOpen, Folder, Article, ViewModule, CloudUpload, Image } from '@mui/icons-material';
import { useState, useEffect, useCallback } from 'react';
import { fetchMagentoCmsPages, fetchMagentoCmsBlocks, fetchMagentoCategories, saveMagentoCmsPage, deleteMagentoCmsPage, saveMagentoCmsBlock, deleteMagentoCmsBlock, uploadProductMedia } from '../api/magento';
import LoadingState from '../components/common/LoadingState';
import PermissionGate from '../components/common/PermissionGate';

const ENVS = [
  { key: 'prod', label: 'Production' },
  { key: 'tsdnd', label: 'TSDND' },
  { key: 'dev', label: 'Development' },
];

interface TabPanelProps { children?: React.ReactNode; index: number; value: number; }
function TabPanel(props: TabPanelProps) {
  const { children, value, index, ...other } = props;
  return <div role="tabpanel" hidden={value !== index} {...other}>{value === index && <Box>{children}</Box>}</div>;
}

interface CategoryNode {
  id: number;
  name: string;
  children_data?: CategoryNode[];
}

function CategoryTree({ node, level = 0, onSelect, selected }: { node: CategoryNode; level?: number; onSelect: (id: number) => void; selected?: number }) {
  const [open, setOpen] = useState(level < 2);
  const hasChildren = node.children_data && node.children_data.length > 0;

  return (
    <Box>
      <ListItemButton onClick={() => { onSelect(node.id); if (hasChildren) setOpen(!open); }} selected={selected === node.id} sx={{ pl: level * 2 + 1, py: 0.3 }}>
        {hasChildren ? (open ? <ExpandLess sx={{ fontSize: 16, mr: 0.5 }} /> : <ExpandMore sx={{ fontSize: 16, mr: 0.5 }} />) : <Box sx={{ width: 16, mr: 0.5 }} />}
        {hasChildren ? <FolderOpen sx={{ fontSize: 16, mr: 1, color: 'primary.main' }} /> : <Folder sx={{ fontSize: 16, mr: 1, color: 'text.disabled' }} />}
        <ListItemText primary={<Typography sx={{ fontSize: '0.75rem' }}>{node.name}</Typography>} />
        <Chip label={node.id} size="small" variant="outlined" sx={{ fontSize: '0.6rem', height: 18 }} />
      </ListItemButton>
      {hasChildren && (
        <Collapse in={open} timeout="auto" unmountOnExit>
          {node.children_data!.map(child => <CategoryTree key={child.id} node={child} level={level + 1} onSelect={onSelect} selected={selected} />)}
        </Collapse>
      )}
    </Box>
  );
}

export default function MagentoCmsPage() {
  const [env, setEnv] = useState('prod');
  const [tab, setTab] = useState(0);
  const [pages, setPages] = useState<any[]>([]);
  const [blocks, setBlocks] = useState<any[]>([]);
  const [categoryTree, setCategoryTree] = useState<CategoryNode | null>(null);
  const [selectedCategory, setSelectedCategory] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);
  const [toast, setToast] = useState<{ message: string; severity: 'success' | 'error' } | null>(null);
  const [editItem, setEditItem] = useState<any>(null);
  const [editType, setEditType] = useState<'page' | 'block'>('page');
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [pagesTotal, setPagesTotal] = useState(0);
  const [blocksTotal, setBlocksTotal] = useState(0);
  const [pagesPage, setPagesPage] = useState(0);
  const [blocksPage, setBlocksPage] = useState(0);
  const [homeUploadOpen, setHomeUploadOpen] = useState(false);
  const [homeUploadSku, setHomeUploadSku] = useState('');
  const [homeUploadFiles, setHomeUploadFiles] = useState<File[]>([]);
  const [homeUploading, setHomeUploading] = useState(false);

  const loadPages = useCallback(async () => {
    try {
      const data = await fetchMagentoCmsPages(env, pagesPage + 1, 20);
      setPages(data.items || []);
      setPagesTotal(data.total_count || 0);
    } catch (e: any) {
      setToast({ message: e.message, severity: 'error' });
    }
  }, [pagesPage, env]);

  const loadBlocks = useCallback(async () => {
    try {
      const data = await fetchMagentoCmsBlocks(env, blocksPage + 1, 20);
      setBlocks(data.items || []);
      setBlocksTotal(data.total_count || 0);
    } catch (e: any) {
      setToast({ message: e.message, severity: 'error' });
    }
  }, [blocksPage, env]);

  const loadCategories = useCallback(async () => {
    try {
      const data = await fetchMagentoCategories(env);
      setCategoryTree(data);
    } catch { /* non-critical */ }
  }, [env]);

  useEffect(() => {
    setLoading(true);
    Promise.all([loadPages(), loadBlocks(), loadCategories()]).finally(() => setLoading(false));
  }, [loadPages, loadBlocks, loadCategories]);

  const handleDeletePage = async (id: number) => {
    if (!confirm(`Delete CMS page #${id}?`)) return;
    try { await deleteMagentoCmsPage(id, env); setToast({ message: 'CMS page deleted', severity: 'success' }); loadPages(); } catch (e: any) { setToast({ message: e.message, severity: 'error' }); }
  };

  const handleDeleteBlock = async (id: number) => {
    if (!confirm(`Delete CMS block #${id}?`)) return;
    try { await deleteMagentoCmsBlock(id, env); setToast({ message: 'CMS block deleted', severity: 'success' }); loadBlocks(); } catch (e: any) { setToast({ message: e.message, severity: 'error' }); }
  };

  const handleSave = async () => {
    if (!editItem) return;
    setSaving(true);
    try {
      if (editType === 'page') {
        await saveMagentoCmsPage(editItem, env);
      } else {
        await saveMagentoCmsBlock(editItem, env);
      }
      setToast({ message: `${editType === 'page' ? 'CMS Page' : 'CMS Block'} saved`, severity: 'success' });
      setDrawerOpen(false);
      setEditItem(null);
      if (editType === 'page') loadPages();
      else loadBlocks();
    } catch (e: any) {
      setToast({ message: e.message, severity: 'error' });
    } finally {
      setSaving(false);
    }
  };

  const handleHomeUpload = async () => {
    if (!homeUploadSku || !homeUploadFiles.length) return;
    setHomeUploading(true);
    for (const file of homeUploadFiles) {
      const reader = new FileReader();
      const base64 = await new Promise<string>((resolve) => {
        reader.onload = () => resolve((reader.result as string).split(',')[1]);
        reader.readAsDataURL(file);
      });
      try {
        await uploadProductMedia(homeUploadSku, { media_type: 'image', label: 'Homepage Banner', file: { name: file.name, base64_encoded_data: base64 } }, env);
      } catch (e: any) {
        setToast({ message: `Upload failed: ${e.message}`, severity: 'error' });
      }
    }
    setHomeUploading(false);
    setHomeUploadOpen(false);
    setHomeUploadFiles([]);
    setToast({ message: 'Homepage images uploaded', severity: 'success' });
  };

  const pageColumns: GridColDef[] = [
    { field: 'id', headerName: 'ID', width: 60 },
    { field: 'title', headerName: 'Title', flex: 1, minWidth: 200 },
    { field: 'identifier', headerName: 'Identifier', width: 160, renderCell: (p) => <Typography sx={{ fontFamily: 'monospace', fontSize: '0.75rem' }}>{p.value}</Typography> },
    { field: 'is_active', headerName: 'Active', width: 80, renderCell: (p) => <Chip label={p.value ? 'Yes' : 'No'} size="small" color={p.value ? 'success' : 'default'} sx={{ fontSize: '0.65rem' }} /> },
    { field: 'update_time', headerName: 'Modified', width: 110, renderCell: (p) => p.value ? new Date(p.value).toLocaleDateString() : '' },
    { field: 'actions', headerName: '', width: 90, sortable: false, renderCell: (p) => (
      <Box sx={{ display: 'flex', gap: 0.5 }}>
        <PermissionGate permission="can_edit_cms">
          <Tooltip title="Edit"><IconButton size="small" onClick={() => { setEditItem(p.row); setEditType('page'); setDrawerOpen(true); }}><Edit sx={{ fontSize: 16 }} /></IconButton></Tooltip>
        </PermissionGate>
        <PermissionGate permission="can_edit_cms">
          <Tooltip title="Delete"><IconButton size="small" color="error" onClick={() => handleDeletePage(p.row.id)}><Delete sx={{ fontSize: 16 }} /></IconButton></Tooltip>
        </PermissionGate>
      </Box>
    )},
  ];

  const blockColumns: GridColDef[] = [
    { field: 'id', headerName: 'ID', width: 60 },
    { field: 'title', headerName: 'Title', flex: 1, minWidth: 200 },
    { field: 'identifier', headerName: 'Identifier', width: 160, renderCell: (p) => <Typography sx={{ fontFamily: 'monospace', fontSize: '0.75rem' }}>{p.value}</Typography> },
    { field: 'is_active', headerName: 'Active', width: 80, renderCell: (p) => <Chip label={p.value ? 'Yes' : 'No'} size="small" color={p.value ? 'success' : 'default'} sx={{ fontSize: '0.65rem' }} /> },
    { field: 'actions', headerName: '', width: 90, sortable: false, renderCell: (p) => (
      <Box sx={{ display: 'flex', gap: 0.5 }}>
        <PermissionGate permission="can_edit_cms">
          <Tooltip title="Edit"><IconButton size="small" onClick={() => { setEditItem(p.row); setEditType('block'); setDrawerOpen(true); }}><Edit sx={{ fontSize: 16 }} /></IconButton></Tooltip>
        </PermissionGate>
        <PermissionGate permission="can_edit_cms">
          <Tooltip title="Delete"><IconButton size="small" color="error" onClick={() => handleDeleteBlock(p.row.id)}><Delete sx={{ fontSize: 16 }} /></IconButton></Tooltip>
        </PermissionGate>
      </Box>
    )},
  ];

  if (loading && !pages.length && !blocks.length) return <LoadingState message="Loading CMS data..." />;

  return (
    <Box>
      <Box sx={{ mb: 2, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>CMS & Content</Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>Manage CMS pages, blocks, categories, and homepage images</Typography>
        </Box>
        <Box sx={{ display: 'flex', gap: 1, alignItems: 'center' }}>
          <FormControl size="small" sx={{ minWidth: 140 }}>
            <Select value={env} onChange={e => { setEnv(e.target.value); setPagesPage(0); setBlocksPage(0); }} sx={{ fontWeight: 700, fontSize: '0.8rem' }}>
              {ENVS.map(e => <MenuItem key={e.key} value={e.key}>{e.label}</MenuItem>)}
            </Select>
          </FormControl>
          <PermissionGate permission="can_bulk_products">
            <Button variant="outlined" startIcon={<Image />} onClick={() => setHomeUploadOpen(true)}>Homepage Images</Button>
          </PermissionGate>
          <Button variant="outlined" startIcon={<Refresh />} onClick={() => { loadPages(); loadBlocks(); loadCategories(); }}>Refresh</Button>
        </Box>
      </Box>

      <Box sx={{ display: 'flex', gap: 2 }}>
        {/* Category Tree Panel */}
        <Box sx={{ width: 260, flexShrink: 0, border: '1px solid', borderColor: 'divider', borderRadius: 2, overflow: 'hidden', maxHeight: 600, overflowY: 'auto' }}>
          <Box sx={{ px: 2, py: 1.5, borderBottom: '1px solid', borderColor: 'divider', bgcolor: 'rgba(255,255,255,0.02)' }}>
            <Typography variant="subtitle2" sx={{ fontWeight: 700, fontSize: '0.8rem' }}>Category Tree</Typography>
          </Box>
          {categoryTree ? (
            <List disablePadding dense>
              <CategoryTree node={categoryTree} onSelect={setSelectedCategory} selected={selectedCategory ?? undefined} />
            </List>
          ) : (
            <Box sx={{ p: 2, textAlign: 'center' }}><Typography variant="caption" color="text.disabled">No categories loaded</Typography></Box>
          )}
          {selectedCategory && (
            <Box sx={{ p: 1.5, borderTop: '1px solid', borderColor: 'divider', bgcolor: 'rgba(59,130,246,0.05)' }}>
              <Typography variant="caption" color="text.disabled">Selected Category ID</Typography>
              <Typography variant="body2" sx={{ fontWeight: 700 }}>{selectedCategory}</Typography>
            </Box>
          )}
        </Box>

        {/* Main Content Area */}
        <Box sx={{ flex: 1, minWidth: 0 }}>
          <Tabs value={tab} onChange={(_, v) => setTab(v)} sx={{ mb: 2 }}>
            <Tab icon={<Article sx={{ fontSize: 18 }} />} iconPosition="start" label={`Pages (${pagesTotal})`} />
            <Tab icon={<ViewModule sx={{ fontSize: 18 }} />} iconPosition="start" label={`Blocks (${blocksTotal})`} />
          </Tabs>

          <TabPanel value={tab} index={0}>
            <Box sx={{ mb: 1, display: 'flex', justifyContent: 'flex-end' }}>
              <PermissionGate permission="can_edit_cms">
                <Button size="small" variant="contained" startIcon={<Add />} onClick={() => { setEditItem({ title: '', identifier: '', content: '', is_active: true }); setEditType('page'); setDrawerOpen(true); }}>New Page</Button>
              </PermissionGate>
            </Box>
            <DataGrid rows={pages} columns={pageColumns} rowCount={pagesTotal} pageSizeOptions={[10, 20, 50]} paginationMode="server" paginationModel={{ page: pagesPage, pageSize: 20 }} onPaginationModelChange={m => setPagesPage(m.page)} getRowId={r => r.id} disableRowSelectionOnClick sx={{ border: '1px solid', borderColor: 'divider', borderRadius: 2, '& .MuiDataGrid-cell': { fontSize: '0.78rem' } }} autoHeight />
          </TabPanel>

          <TabPanel value={tab} index={1}>
            <Box sx={{ mb: 1, display: 'flex', justifyContent: 'flex-end' }}>
              <PermissionGate permission="can_edit_cms">
                <Button size="small" variant="contained" startIcon={<Add />} onClick={() => { setEditItem({ title: '', identifier: '', content: '', is_active: true }); setEditType('block'); setDrawerOpen(true); }}>New Block</Button>
              </PermissionGate>
            </Box>
            <DataGrid rows={blocks} columns={blockColumns} rowCount={blocksTotal} pageSizeOptions={[10, 20, 50]} paginationMode="server" paginationModel={{ page: blocksPage, pageSize: 20 }} onPaginationModelChange={m => setBlocksPage(m.page)} getRowId={r => r.id} disableRowSelectionOnClick sx={{ border: '1px solid', borderColor: 'divider', borderRadius: 2, '& .MuiDataGrid-cell': { fontSize: '0.78rem' } }} autoHeight />
          </TabPanel>
        </Box>
      </Box>

      {/* Edit Drawer */}
      <Drawer anchor="right" open={drawerOpen} onClose={() => { setDrawerOpen(false); setEditItem(null); }} slotProps={{ paper: { sx: { width: 520, bgcolor: 'background.paper' } } }}>
        {editItem && (
          <Box sx={{ p: 3, display: 'flex', flexDirection: 'column', height: '100%' }}>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 3 }}>
              <Typography variant="h6" sx={{ fontWeight: 800 }}>{editItem.id ? 'Edit' : 'New'} {editType === 'page' ? 'CMS Page' : 'CMS Block'}</Typography>
              <IconButton onClick={() => { setDrawerOpen(false); setEditItem(null); }}><Close /></IconButton>
            </Box>
            <Box sx={{ display: 'grid', gap: 2, flex: 1, overflowY: 'auto' }}>
              <TextField label="Title" size="small" required value={editItem.title || ''} onChange={e => setEditItem({ ...editItem, title: e.target.value })} />
              <TextField label="Identifier" size="small" required value={editItem.identifier || ''} onChange={e => setEditItem({ ...editItem, identifier: e.target.value })} helperText="URL-safe identifier (e.g., about-us)" />
              <TextField label="Content" size="small" multiline rows={12} value={editItem.content || ''} onChange={e => setEditItem({ ...editItem, content: e.target.value })} placeholder="HTML content..." sx={{ '& textarea': { fontFamily: 'monospace', fontSize: '0.75rem' } }} />
              <FormControlLabel control={<Switch checked={editItem.is_active ?? true} onChange={e => setEditItem({ ...editItem, is_active: e.target.checked })} />} label="Active" />
            </Box>
            <Divider sx={{ my: 2 }} />
            <Box sx={{ display: 'flex', gap: 2 }}>
              <Button variant="contained" startIcon={saving ? <Refresh sx={{ animation: 'spin 1s linear infinite' }} /> : <Save />} onClick={handleSave} disabled={saving || !editItem.title || !editItem.identifier}>
                {saving ? 'Saving...' : 'Save'}
              </Button>
              <Button variant="outlined" onClick={() => { setDrawerOpen(false); setEditItem(null); }}>Cancel</Button>
            </Box>
          </Box>
        )}
      </Drawer>

      {/* Homepage Image Upload Dialog */}
      <Dialog open={homeUploadOpen} onClose={() => setHomeUploadOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Upload Homepage Images</DialogTitle>
        <DialogContent>
          <Typography variant="body2" sx={{ color: 'text.secondary', mb: 2 }}>Upload banner/hero images for the homepage. Enter the product SKU to attach images to.</Typography>
          <Box sx={{ display: 'grid', gap: 2 }}>
            <TextField label="Product SKU" size="small" value={homeUploadSku} onChange={e => setHomeUploadSku(e.target.value)} placeholder="e.g., HOME-BANNER-01" />
            <Button variant="outlined" startIcon={<CloudUpload />} onClick={() => document.getElementById('home-img-input')?.click()}>
              {homeUploadFiles.length ? `${homeUploadFiles.length} file(s) selected` : 'Select Images'}
            </Button>
            <input id="home-img-input" type="file" multiple accept="image/*" hidden onChange={e => setHomeUploadFiles(Array.from(e.target.files || []))} />
          </Box>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setHomeUploadOpen(false)}>Cancel</Button>
          <Button variant="contained" onClick={handleHomeUpload} disabled={homeUploading || !homeUploadSku || !homeUploadFiles.length}>
            {homeUploading ? 'Uploading...' : 'Upload'}
          </Button>
        </DialogActions>
      </Dialog>

      <Snackbar open={!!toast} autoHideDuration={4000} onClose={() => setToast(null)} anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}>
        {toast ? <Alert severity={toast.severity} onClose={() => setToast(null)} variant="filled">{toast.message}</Alert> : undefined}
      </Snackbar>
    </Box>
  );
}

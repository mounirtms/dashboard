import { Box, Typography, Card, CardContent, Button, Chip, Select, MenuItem, FormControl, InputLabel, Tooltip, IconButton } from '@mui/material';
import { DataGrid, GridColDef, GridRenderCellParams } from '@mui/x-data-grid';
import { Refresh, Inventory, Warning, CheckCircle, Edit, History } from '@mui/icons-material';
import { useState, useEffect } from 'react';
import { fetchMagentoStock } from '../api/magento';
import LoadingState from '../components/common/LoadingState';

export default function InventoryPage() {
  const [items, setItems] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [env, setEnv] = useState('prod');
  const [paginationModel, setPaginationModel] = useState({ page: 0, pageSize: 20 });

  const loadData = () => {
    setLoading(true);
    fetchMagentoStock(env, paginationModel.page + 1)
      .then((data) => {
        // Magento stock items often need mapping to include a stable ID for DataGrid
        const mapped = (data.items || []).map((item: any) => ({
          ...item,
          id: item.item_id || `${item.product_id}-${item.website_id}`
        }));
        setItems(mapped);
      })
      .catch((e) => console.error(e))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    loadData();
  }, [env, paginationModel.page]);

  const columns: GridColDef[] = [
    { field: 'sku', headerName: 'SKU', width: 180, renderCell: (params) => <Typography sx={{ fontFamily: 'monospace', fontWeight: 700, fontSize: '0.75rem' }}>{params.value}</Typography> },
    { field: 'qty', headerName: 'Quantity', width: 120, type: 'number', renderCell: (params) => (
      <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
        <Typography sx={{ fontWeight: 700, color: params.value <= 5 ? 'error.main' : 'text.primary' }}>{params.value}</Typography>
        {params.value <= 5 && <Warning sx={{ fontSize: 14, color: 'error.main' }} />}
      </Box>
    )},
    { field: 'is_in_stock', headerName: 'Availability', width: 130, renderCell: (params) => (
      <Chip 
        label={params.value ? 'IN STOCK' : 'OUT OF STOCK'} 
        size="small" 
        color={params.value ? 'success' : 'error'} 
        variant="outlined"
        sx={{ fontWeight: 800, fontSize: '0.65rem' }}
      />
    )},
    { field: 'manage_stock', headerName: 'Managed', width: 100, type: 'boolean' },
    { 
      field: 'actions', 
      headerName: 'Operations', 
      width: 150, 
      sortable: false,
      renderCell: () => (
        <Box sx={{ display: 'flex', gap: 0.5 }}>
          <Tooltip title="Update Stock"><IconButton size="small"><Edit sx={{ fontSize: 16 }} /></IconButton></Tooltip>
          <Tooltip title="View History"><IconButton size="small"><History sx={{ fontSize: 16 }} /></IconButton></Tooltip>
        </Box>
      )
    }
  ];

  return (
    <Box sx={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Inventory Management
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Monitor and update product stock levels across Magento environments.
          </Typography>
        </Box>

        <Box sx={{ display: 'flex', gap: 2, alignItems: 'center' }}>
          <FormControl size="small" sx={{ minWidth: 140 }}>
            <InputLabel>Environment</InputLabel>
            <Select value={env} label="Environment" onChange={(e) => setEnv(e.target.value)}>
              <MenuItem value="prod">Production</MenuItem>
              <MenuItem value="beta">Beta Store</MenuItem>
              <MenuItem value="dev">Development</MenuItem>
            </Select>
          </FormControl>
          <Button variant="contained" startIcon={<Refresh />} onClick={loadData} disabled={loading}>
            Sync Stock
          </Button>
        </Box>
      </Box>

      <Card sx={{ flexGrow: 1, minHeight: 600 }}>
        <DataGrid
          rows={items}
          columns={columns}
          loading={loading}
          paginationModel={paginationModel}
          onPaginationModelChange={setPaginationModel}
          pageSizeOptions={[20, 50, 100]}
          disableRowSelectionOnClick
          density="compact"
          sx={{
            border: 'none',
            '& .MuiDataGrid-columnHeaderTitle': { fontWeight: 700, fontSize: '0.7rem', color: 'text.secondary' }
          }}
        />
      </Card>
    </Box>
  );
}

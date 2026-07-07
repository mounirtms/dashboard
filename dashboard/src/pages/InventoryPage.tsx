import {
  Box, Typography, Card, Button, Chip, Select, MenuItem,
  FormControl, InputLabel, Tooltip, IconButton, CircularProgress,
} from '@mui/material';
import { DataGrid, GridColDef, GridRenderCellParams } from '@mui/x-data-grid';
import { Refresh, Warning, Edit, History } from '@mui/icons-material';
import { useState, useCallback } from 'react';
import { fetchMagentoStock } from '../api/magento';
import { usePolling } from '../hooks/usePolling';

// ── Types ────────────────────────────────────────────────────────────────────

interface StockItem {
  id: string | number;
  item_id?: number | string;
  product_id?: number | string;
  website_id?: number | string;
  sku: string;
  qty: number;
  is_in_stock: boolean;
  manage_stock: boolean;
}

interface StockResponse {
  items?: Array<Omit<StockItem, 'id'>>;
}

// ── Page ─────────────────────────────────────────────────────────────────────

export default function InventoryPage() {
  const [env, setEnv]                     = useState('prod');
  const [paginationModel, setPaginationModel] = useState({ page: 0, pageSize: 20 });

  const fetcher = useCallback(
    async (_signal?: AbortSignal): Promise<StockItem[]> => {
      const response = (await fetchMagentoStock(env, paginationModel.page + 1)) as StockResponse;
      return (response.items ?? []).map((item) => ({
        ...item,
        id: item.item_id ?? `${item.product_id ?? 0}-${item.website_id ?? 0}`,
      }));
    },
    [env, paginationModel.page],
  );

  const { data: items = [], loading, refreshing, refetch } = usePolling<StockItem[]>(fetcher, 60_000);

  const columns: GridColDef[] = [
    {
      field: 'sku',
      headerName: 'SKU',
      width: 180,
      renderCell: (params: GridRenderCellParams) => (
        <Typography sx={{ fontFamily: 'monospace', fontWeight: 700, fontSize: '0.75rem' }}>
          {params.value as string}
        </Typography>
      ),
    },
    {
      field: 'qty',
      headerName: 'Quantity',
      width: 120,
      type: 'number',
      renderCell: (params: GridRenderCellParams) => {
        const qty = params.value as number;
        return (
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
            <Typography sx={{ fontWeight: 700, color: qty <= 5 ? 'error.main' : 'text.primary' }}>
              {qty}
            </Typography>
            {qty <= 5 && <Warning sx={{ fontSize: 14, color: 'error.main' }} />}
          </Box>
        );
      },
    },
    {
      field: 'is_in_stock',
      headerName: 'Availability',
      width: 130,
      renderCell: (params: GridRenderCellParams) => (
        <Chip
          label={params.value ? 'IN STOCK' : 'OUT OF STOCK'}
          size="small"
          color={params.value ? 'success' : 'error'}
          variant="outlined"
          sx={{ fontWeight: 800, fontSize: '0.65rem' }}
        />
      ),
    },
    { field: 'manage_stock', headerName: 'Managed', width: 100, type: 'boolean' },
    {
      field: 'actions',
      headerName: 'Operations',
      width: 150,
      sortable: false,
      renderCell: () => (
        <Box sx={{ display: 'flex', gap: 0.5 }}>
          <Tooltip title="Update Stock">
            <IconButton size="small"><Edit sx={{ fontSize: 16 }} /></IconButton>
          </Tooltip>
          <Tooltip title="View History">
            <IconButton size="small"><History sx={{ fontSize: 16 }} /></IconButton>
          </Tooltip>
        </Box>
      ),
    },
  ];

  return (
    <Box sx={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
      {/* Header */}
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
          <Button
            variant="contained"
            startIcon={refreshing ? <CircularProgress size={16} color="inherit" /> : <Refresh />}
            onClick={refetch}
            disabled={loading || refreshing}
          >
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
            '& .MuiDataGrid-columnHeaderTitle': { fontWeight: 700, fontSize: '0.7rem', color: 'text.secondary' },
          }}
        />
      </Card>
    </Box>
  );
}

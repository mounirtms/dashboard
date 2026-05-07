import { Box, Typography, Button, IconButton, Tooltip, Chip, Card } from '@mui/material';
import { DataGrid, GridColDef, GridRenderCellParams } from '@mui/x-data-grid';
import { Language, Refresh, OpenInNew, Delete, Settings, Storage } from '@mui/icons-material';
import { useState, useEffect } from 'react';
import { fetchSites, SiteInfo } from '../api/system';
import LoadingState from '../components/common/LoadingState';
import StatusBadge from '../components/common/StatusBadge';

export default function SitesPage() {
  const [sites, setSites] = useState<SiteInfo[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadData = () => {
    setLoading(true);
    fetchSites()
      .then(setSites)
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    loadData();
  }, []);

  const columns: GridColDef[] = [
    { 
      field: 'name', 
      headerName: 'Site / Domain', 
      flex: 1.5,
      renderCell: (params: GridRenderCellParams) => (
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, height: '100%' }}>
          <Language sx={{ color: 'primary.main', fontSize: 18 }} />
          <Box>
            <Typography variant="body2" sx={{ fontWeight: 700 }}> {params.row.key}</Typography>
            <Typography variant="caption" sx={{ color: 'text.disabled', fontFamily: 'monospace', display: 'block', lineHeight: 1 }}>
              {params.value}
            </Typography>
          </Box>
        </Box>
      )
    },
    { 
      field: 'exists', 
      headerName: 'Status', 
      width: 100,
      renderCell: (params: GridRenderCellParams) => (
        <StatusBadge 
          label={params.value ? 'ACTIVE' : 'MISSING'} 
          color={params.value ? 'success' : 'error'} 
        />
      )
    },
    { 
      field: 'php_fpm', 
      headerName: 'PHP-FPM', 
      width: 110,
      renderCell: (params: GridRenderCellParams) => (
        <Chip 
          label={`${params.value} workers`} 
          size="small" 
          variant="outlined"
          sx={{ fontWeight: 600, fontSize: '0.7rem' }}
        />
      )
    },
    { 
      field: 'disk', 
      headerName: 'Disk Usage', 
      width: 100,
      renderCell: (params: GridRenderCellParams) => (
        <Typography variant="body2" sx={{ fontWeight: 600, color: 'text.secondary' }}>
          {params.value}
        </Typography>
      )
    },
    { 
      field: 'is_magento', 
      headerName: 'Platform', 
      width: 120,
      renderCell: (params: GridRenderCellParams) => (
        params.value ? (
          <Chip 
            label="Magento 2" 
            size="small" 
            sx={{ backgroundColor: '#f263221a', color: '#f26322', fontWeight: 700, border: '1px solid #f2632233' }}
          />
        ) : <Typography variant="caption" sx={{ color: 'text.disabled' }}>Generic PHP</Typography>
      )
    },
    {
      field: 'actions',
      headerName: 'Actions',
      width: 150,
      sortable: false,
      renderCell: (params: GridRenderCellParams) => (
        <Box sx={{ display: 'flex', gap: 0.5 }}>
          <Tooltip title="View Logs">
            <IconButton size="small"><Storage sx={{ fontSize: 16 }} /></IconButton>
          </Tooltip>
          <Tooltip title="Settings">
            <IconButton size="small"><Settings sx={{ fontSize: 16 }} /></IconButton>
          </Tooltip>
          <Tooltip title="Open Website">
            <IconButton size="small" href={`https://${params.row.name}`} target="_blank"><OpenInNew sx={{ fontSize: 16 }} /></IconButton>
          </Tooltip>
        </Box>
      )
    }
  ];

  if (loading && sites.length === 0) return <LoadingState message="Scanning infrastructure..." />;

  return (
    <Box sx={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Managed Sites
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Infrastructure overview of {sites.length} managed environments.
          </Typography>
        </Box>
        <Button variant="outlined" startIcon={<Refresh />} onClick={loadData} disabled={loading}>
          Refresh List
        </Button>
      </Box>

      <Card sx={{ flexGrow: 1, minHeight: 500 }}>
        <DataGrid
          rows={sites}
          columns={columns}
          getRowId={(row) => row.key}
          pageSizeOptions={[10, 20, 50]}
          initialState={{
            pagination: { paginationModel: { pageSize: 10 } },
          }}
          disableRowSelectionOnClick
          density="compact"
          sx={{
            border: 'none',
            '& .MuiDataGrid-cell:focus': { outline: 'none' },
            '& .MuiDataGrid-columnHeaderTitle': { fontWeight: 700, fontSize: '0.75rem', color: 'text.secondary' }
          }}
        />
      </Card>
    </Box>
  );
}

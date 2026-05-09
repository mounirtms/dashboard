import { Box, Typography, Button, IconButton, Tooltip, Chip, Card, Snackbar, Alert, CircularProgress } from '@mui/material';
import { DataGrid, GridColDef, GridRenderCellParams } from '@mui/x-data-grid';
import { Language, Refresh, OpenInNew, Delete, Settings, Storage, PowerSettingsNew, VisibilityOff } from '@mui/icons-material';
import { useState, useEffect } from 'react';
import { fetchSites, performSiteAction, SiteInfo } from '../api/system';
import LoadingState from '../components/common/LoadingState';
import StatusBadge from '../components/common/StatusBadge';

export default function SitesPage() {
  const [sites, setSites] = useState<SiteInfo[]>([]);
  const [loading, setLoading] = useState(true);
  const [executing, setExecuting] = useState<string | null>(null);
  const [notify, setNotify] = useState({ open: false, message: '', severity: 'success' as any });

  const loadData = () => {
    setLoading(true);
    fetchSites()
      .then(setSites)
      .catch((e) => setNotify({ open: true, message: e.message, severity: 'error' }))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    loadData();
  }, []);

  const handleMaintenance = async (site: string, current: boolean) => {
    const op = current ? 'maint_off' : 'maint_on';
    setExecuting(`${site}-${op}`);
    try {
      const res = await performSiteAction(site, op);
      setNotify({ open: true, message: res.message, severity: res.success ? 'success' : 'error' });
      if (res.success) loadData();
    } catch (e: any) {
      setNotify({ open: true, message: e.message, severity: 'error' });
    } finally {
      setExecuting(null);
    }
  };

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
      field: 'maintenance', 
      headerName: 'Maint. Mode', 
      width: 120,
      renderCell: (params: GridRenderCellParams) => (
        params.row.is_magento ? (
          <Chip 
            label={params.value ? 'ENABLED' : 'DISABLED'} 
            size="small" 
            color={params.value ? 'warning' : 'default'}
            variant={params.value ? 'filled' : 'outlined'}
            sx={{ fontWeight: 700, fontSize: '0.65rem' }}
          />
        ) : <Typography variant="caption" sx={{ color: 'text.disabled' }}>N/A</Typography>
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
      headerName: 'Disk', 
      width: 80,
      renderCell: (params: GridRenderCellParams) => (
        <Typography variant="body2" sx={{ fontWeight: 600, color: 'text.secondary' }}>
          {params.value}
        </Typography>
      )
    },
    {
      field: 'actions',
      headerName: 'Actions',
      width: 180,
      sortable: false,
      renderCell: (params: GridRenderCellParams) => (
        <Box sx={{ display: 'flex', gap: 0.5, alignItems: 'center', height: '100%' }}>
          {params.row.is_magento && (
            <Tooltip title={params.row.maintenance ? "Disable Maintenance" : "Enable Maintenance"}>
              <IconButton 
                size="small" 
                color={params.row.maintenance ? "success" : "warning"}
                onClick={() => handleMaintenance(params.row.key, params.row.maintenance)}
                disabled={!!executing}
              >
                {executing?.startsWith(params.row.key) ? <CircularProgress size={16} color="inherit" /> : <PowerSettingsNew sx={{ fontSize: 18 }} />}
              </IconButton>
            </Tooltip>
          )}
          <Tooltip title="View Logs">
            <IconButton size="small" href={`/#/logs?site=${params.row.key}`}><Storage sx={{ fontSize: 16 }} /></IconButton>
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

      <Snackbar 
        open={notify.open} 
        autoHideDuration={4000} 
        onClose={() => setNotify({ ...notify, open: false })}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
      >
        <Alert severity={notify.severity} variant="filled" sx={{ width: '100%' }}>{notify.message}</Alert>
      </Snackbar>
    </Box>
  );
}

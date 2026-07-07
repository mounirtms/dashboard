import {
  Box, Typography, Button, IconButton, Tooltip, Chip,
  Card, Snackbar, Alert, CircularProgress,
} from '@mui/material';
import { DataGrid, GridColDef, GridRenderCellParams } from '@mui/x-data-grid';
import { Language, Refresh, OpenInNew, Storage, PowerSettingsNew, VisibilityOff } from '@mui/icons-material';
import { useCallback } from 'react';
import { useState } from 'react';
import { fetchSites, performSiteAction, type SiteInfo } from '../api/system';
import StatusBadge from '../components/common/StatusBadge';
import { usePolling } from '../hooks/usePolling';

// ── Types ────────────────────────────────────────────────────────────────────

type NotifySeverity = 'success' | 'error';

interface NotifyState {
  open: boolean;
  message: string;
  severity: NotifySeverity;
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function extractMessage(e: unknown): string {
  if (e instanceof Error) return e.message;
  return 'Unknown error';
}

// ── Page ─────────────────────────────────────────────────────────────────────

export default function SitesPage() {
  const [executing, setExecuting] = useState<string | null>(null);
  const [notify, setNotify]       = useState<NotifyState>({ open: false, message: '', severity: 'success' });

  const fetcher = useCallback(
    (_signal?: AbortSignal) => fetchSites() as Promise<SiteInfo[]>,
    [],
  );
  const { data: sites = [], loading, refreshing, refetch } = usePolling<SiteInfo[]>(fetcher, 60_000);

  const showNotify = (message: string, severity: NotifySeverity) =>
    setNotify({ open: true, message, severity });

  const handleMaintenance = async (site: string, current: boolean) => {
    const op = current ? 'maint_off' : 'maint_on';
    setExecuting(`${site}-${op}`);
    try {
      const res = await performSiteAction(site, op);
      showNotify(res.message, res.success ? 'success' : 'error');
      if (res.success) refetch();
    } catch (e: unknown) {
      showNotify(extractMessage(e), 'error');
    } finally {
      setExecuting(null);
    }
  };

  const handleSuspendResume = async (site: string, op: 'suspend' | 'resume') => {
    setExecuting(`${site}-${op}`);
    try {
      const res = await performSiteAction(site, op);
      showNotify(res.message, res.success ? 'success' : 'error');
      if (res.success) refetch();
    } catch (e: unknown) {
      showNotify(extractMessage(e), 'error');
    } finally {
      setExecuting(null);
    }
  };

  const columns: GridColDef[] = [
    {
      field: 'name',
      headerName: 'Site / Domain',
      flex: 1.5,
      renderCell: (params: GridRenderCellParams) => {
        const row = params.row as SiteInfo;
        return (
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, height: '100%' }}>
            <Language sx={{ color: 'primary.main', fontSize: 18 }} />
            <Box>
              <Typography variant="body2" sx={{ fontWeight: 700 }}>{row.key}</Typography>
              <Typography variant="caption" sx={{ color: 'text.disabled', fontFamily: 'monospace', display: 'block', lineHeight: 1 }}>
                {params.value as string}
              </Typography>
            </Box>
          </Box>
        );
      },
    },
    {
      field: 'exists',
      headerName: 'Status',
      width: 100,
      renderCell: (params: GridRenderCellParams) => {
        const row = params.row as SiteInfo;
        return row.is_suspended ? (
          <StatusBadge label="SUSPENDED" color="warning" />
        ) : (
          <StatusBadge
            label={params.value ? 'ACTIVE' : 'MISSING'}
            color={params.value ? 'success' : 'error'}
          />
        );
      },
    },
    {
      field: 'maintenance',
      headerName: 'Maint. Mode',
      width: 120,
      renderCell: (params: GridRenderCellParams) => {
        const row = params.row as SiteInfo;
        return row.is_magento ? (
          <Chip
            label={params.value ? 'ENABLED' : 'DISABLED'}
            size="small"
            color={params.value ? 'warning' : 'default'}
            variant={params.value ? 'filled' : 'outlined'}
            sx={{ fontWeight: 700, fontSize: '0.65rem' }}
          />
        ) : (
          <Typography variant="caption" sx={{ color: 'text.disabled' }}>N/A</Typography>
        );
      },
    },
    {
      field: 'php_fpm',
      headerName: 'PHP-FPM',
      width: 110,
      renderCell: (params: GridRenderCellParams) => (
        <Chip
          label={`${params.value as string} workers`}
          size="small"
          variant="outlined"
          sx={{ fontWeight: 600, fontSize: '0.7rem' }}
        />
      ),
    },
    {
      field: 'disk',
      headerName: 'Disk',
      width: 80,
      renderCell: (params: GridRenderCellParams) => (
        <Typography variant="body2" sx={{ fontWeight: 600, color: 'text.secondary' }}>
          {params.value as string}
        </Typography>
      ),
    },
    {
      field: 'actions',
      headerName: 'Actions',
      width: 180,
      sortable: false,
      renderCell: (params: GridRenderCellParams) => {
        const row = params.row as SiteInfo;
        const isBusy = !!executing && executing.startsWith(row.key);
        return (
          <Box sx={{ display: 'flex', gap: 0.5, alignItems: 'center', height: '100%' }}>
            {row.is_magento && (
              <Tooltip title={row.maintenance ? 'Disable Maintenance' : 'Enable Maintenance'}>
                <IconButton
                  size="small"
                  color={row.maintenance ? 'success' : 'warning'}
                  onClick={() => handleMaintenance(row.key, row.maintenance ?? false)}
                  disabled={!!executing}
                >
                  {isBusy ? <CircularProgress size={16} color="inherit" /> : <PowerSettingsNew sx={{ fontSize: 18 }} />}
                </IconButton>
              </Tooltip>
            )}
            {!['prod'].includes(row.key) && row.exists && (
              row.is_suspended ? (
                <Tooltip title="Resume Site">
                  <IconButton
                    size="small"
                    color="success"
                    onClick={() => handleSuspendResume(row.key, 'resume')}
                    disabled={!!executing}
                  >
                    {isBusy ? <CircularProgress size={16} color="inherit" /> : <VisibilityOff sx={{ fontSize: 18 }} />}
                  </IconButton>
                </Tooltip>
              ) : (
                <Tooltip title="Suspend Site">
                  <IconButton
                    size="small"
                    color="warning"
                    onClick={() => handleSuspendResume(row.key, 'suspend')}
                    disabled={!!executing}
                  >
                    {isBusy ? <CircularProgress size={16} color="inherit" /> : <VisibilityOff sx={{ fontSize: 18 }} />}
                  </IconButton>
                </Tooltip>
              )
            )}
            <Tooltip title="View Logs">
              <IconButton size="small" href={`/#/logs?site=${row.key}`}>
                <Storage sx={{ fontSize: 16 }} />
              </IconButton>
            </Tooltip>
            <Tooltip title="Open Website">
              <IconButton size="small" href={`https://${row.name}`} target="_blank">
                <OpenInNew sx={{ fontSize: 16 }} />
              </IconButton>
            </Tooltip>
          </Box>
        );
      },
    },
  ];

  return (
    <Box sx={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
      {/* Header */}
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Managed Sites
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Infrastructure overview of {sites.length} managed environments.
          </Typography>
        </Box>
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5 }}>
          {refreshing && <CircularProgress size={18} sx={{ color: 'text.disabled' }} />}
          <Button
            variant="outlined"
            startIcon={<Refresh />}
            onClick={refetch}
            disabled={loading || refreshing}
          >
            Refresh List
          </Button>
        </Box>
      </Box>

      <Card sx={{ flexGrow: 1, minHeight: 500 }}>
        <DataGrid
          rows={sites}
          columns={columns}
          getRowId={(row: SiteInfo) => row.key}
          loading={loading}
          pageSizeOptions={[10, 20, 50]}
          initialState={{ pagination: { paginationModel: { pageSize: 10 } } }}
          disableRowSelectionOnClick
          density="compact"
          sx={{
            border: 'none',
            '& .MuiDataGrid-cell:focus': { outline: 'none' },
            '& .MuiDataGrid-columnHeaderTitle': { fontWeight: 700, fontSize: '0.75rem', color: 'text.secondary' },
          }}
        />
      </Card>

      <Snackbar
        open={notify.open}
        autoHideDuration={4000}
        onClose={() => setNotify(prev => ({ ...prev, open: false }))}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
      >
        <Alert severity={notify.severity} variant="filled" sx={{ width: '100%' }}>
          {notify.message}
        </Alert>
      </Snackbar>
    </Box>
  );
}

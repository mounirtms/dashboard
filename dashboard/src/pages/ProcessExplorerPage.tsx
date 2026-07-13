import { Box, Typography, Card, Button, IconButton, Tooltip, Chip, TextField, InputAdornment, Alert, Snackbar, Dialog, DialogTitle, DialogContent, DialogContentText, DialogActions, FormControlLabel, Switch } from '@mui/material';
import { DataGrid, GridColDef } from '@mui/x-data-grid';
import { Refresh, Search, Dangerous, PowerSettingsNew, Warning } from '@mui/icons-material';
import { useState, useEffect, useCallback, useRef } from 'react';
import apiClient from '../api/client';
import LoadingState from '../components/common/LoadingState';

export default function ProcessExplorerPage() {
  const [procs, setProcs] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [search, setSearch] = useState('');
  const [notify, setNotify] = useState({ open: false, message: '', severity: 'success' as any });
  const [killing, setKilling] = useState<Set<string>>(new Set());
  const [autoRefresh, setAutoRefresh] = useState(true);
  const [confirmPid, setConfirmPid] = useState<string | null>(null);
  const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const loadData = useCallback(() => {
    setLoading(true);
    setLoadError(null);
    apiClient.get('/api/monitor.php?action=processes')
      .then(({ data }) => setProcs(data.processes || []))
      .catch((e) => setLoadError(e.response?.data?.message || e.message || 'Failed to load process list'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  // Auto-refresh with toggle support
  useEffect(() => {
    if (timerRef.current) clearInterval(timerRef.current);
    if (autoRefresh) {
      timerRef.current = setInterval(loadData, 30000); // 30s — was 10s, reduces 429 storms
    }
    return () => {
      if (timerRef.current) clearInterval(timerRef.current);
    };
  }, [autoRefresh, loadData]);

  const handleKillConfirm = async () => {
    if (!confirmPid) return;
    const pid = confirmPid;
    setConfirmPid(null);
    setKilling(prev => new Set(prev).add(pid));
    try {
      const { data } = await apiClient.get(`/api/monitor.php?action=process_action&op=kill&pid=${pid}`);
      setNotify({ open: true, message: data.message || 'Action executed', severity: data.success ? 'success' : 'error' });
      if (data.success) loadData();
    } catch (e: any) {
      setNotify({ open: true, message: e.message, severity: 'error' });
    } finally {
      setKilling(prev => {
        const next = new Set(prev);
        next.delete(pid);
        return next;
      });
    }
  };

  const filtered = procs.filter(p =>
    p.cmd.toLowerCase().includes(search.toLowerCase()) ||
    p.user.toLowerCase().includes(search.toLowerCase()) ||
    p.pid.toString().includes(search)
  );

  const columns: GridColDef[] = [
    {
      field: 'pid', headerName: 'PID', width: 90,
      renderCell: (params) => (
        <Typography sx={{ fontFamily: 'monospace', fontWeight: 700 }}>{params.value}</Typography>
      )
    },
    { field: 'user', headerName: 'User', width: 100 },
    {
      field: 'cpu', headerName: 'CPU%', width: 80, type: 'number',
      renderCell: (params) => (
        <Typography sx={{ fontWeight: 700, color: params.value > 50 ? 'error.main' : 'text.primary' }}>
          {params.value}%
        </Typography>
      )
    },
    { field: 'mem', headerName: 'MEM%', width: 80, type: 'number' },
    { field: 'time', headerName: 'Time', width: 100 },
    {
      field: 'cmd', headerName: 'Command', flex: 1,
      renderCell: (params) => (
        <Tooltip title={params.value}>
          <Typography sx={{ fontFamily: 'monospace', fontSize: '0.72rem', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
            {params.value}
          </Typography>
        </Tooltip>
      )
    },
    {
      field: 'actions',
      headerName: 'Kill',
      width: 70,
      sortable: false,
      renderCell: (params) => {
        const pid = params.row.pid?.toString();
        const isKilling = killing.has(pid);
        return (
          <Tooltip title={isKilling ? 'Terminating…' : `Kill PID ${pid}`}>
            <span>
              <IconButton
                size="small"
                color="error"
                onClick={() => setConfirmPid(pid)}
                disabled={isKilling}
              >
                {isKilling
                  ? <PowerSettingsNew sx={{ fontSize: 18, opacity: 0.4 }} />
                  : <Dangerous sx={{ fontSize: 18 }} />}
              </IconButton>
            </span>
          </Tooltip>
        );
      }
    }
  ];

  return (
    <Box sx={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Process Explorer
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Real-time server process monitoring and emergency control.
          </Typography>
        </Box>

        <Box sx={{ display: 'flex', gap: 2, alignItems: 'center' }}>
          <TextField
            size="small"
            placeholder="Search processes..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            slotProps={{
              input: {
                startAdornment: <Search sx={{ mr: 1, color: 'text.disabled', fontSize: 18 }} />,
                sx: { width: 240 }
              }
            }}
          />
          <FormControlLabel
            control={
              <Switch
                size="small"
                checked={autoRefresh}
                onChange={(e) => setAutoRefresh(e.target.checked)}
              />
            }
            label={
              <Typography variant="caption" sx={{ color: 'text.secondary' }}>
                Auto (10s)
              </Typography>
            }
          />
          <Button variant="outlined" startIcon={<Refresh />} onClick={loadData} disabled={loading}>
            Refresh
          </Button>
        </Box>
      </Box>

      {autoRefresh && (
        <Chip
          label="Auto-refreshing every 10s"
          size="small"
          color="info"
          variant="outlined"
          sx={{ alignSelf: 'flex-start', mb: 1.5, fontSize: '0.7rem' }}
        />
      )}

      {loadError && (
        <Alert severity="error" sx={{ mb: 2 }} action={<Button size="small" color="inherit" onClick={loadData}>Retry</Button>}>
          {loadError}
        </Alert>
      )}

      <Card sx={{ flexGrow: 1 }}>
        <DataGrid
          rows={filtered}
          columns={columns}
          getRowId={(row) => row.pid}
          density="compact"
          disableRowSelectionOnClick
          sx={{ border: 'none' }}
        />
      </Card>

      {/* Kill Confirmation Dialog */}
      <Dialog open={!!confirmPid} onClose={() => setConfirmPid(null)} maxWidth="xs" fullWidth>
        <DialogTitle sx={{ display: 'flex', alignItems: 'center', gap: 1, color: 'error.main' }}>
          <Warning />
          Kill Process
        </DialogTitle>
        <DialogContent>
          <DialogContentText>
            Are you sure you want to kill PID <strong>{confirmPid}</strong>? This will immediately terminate the process.
          </DialogContentText>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setConfirmPid(null)} variant="outlined">Cancel</Button>
          <Button onClick={handleKillConfirm} variant="contained" color="error" autoFocus>
            Kill PID {confirmPid}
          </Button>
        </DialogActions>
      </Dialog>

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

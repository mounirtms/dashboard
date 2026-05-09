import { Box, Typography, Card, Button, IconButton, Tooltip, Chip, TextField, InputAdornment, Alert, Snackbar } from '@mui/material';
import { DataGrid, GridColDef, GridRenderCellParams } from '@mui/x-data-grid';
import { Refresh, Search, Dangerous, PowerSettingsNew, Terminal } from '@mui/icons-material';
import { useState, useEffect } from 'react';
import apiClient from '../api/client';
import LoadingState from '../components/common/LoadingState';

export default function ProcessExplorerPage() {
  const [procs, setProcs] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [notify, setNotify] = useState({ open: false, message: '', severity: 'success' as any });

  const loadData = () => {
    setLoading(true);
    apiClient.get('/api/monitor.php?action=processes')
      .then(({ data }) => setProcs(data.processes || []))
      .catch((e) => console.error(e))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    loadData();
    const timer = setInterval(loadData, 10000);
    return () => clearInterval(timer);
  }, []);

  const handleKill = async (pid: string) => {
    if (!confirm(`Are you sure you want to kill PID ${pid}?`)) return;
    try {
      const { data } = await apiClient.get(`/api/monitor.php?action=process_action&op=kill&pid=${pid}`);
      setNotify({ open: true, message: data.message || 'Action executed', severity: data.success ? 'success' : 'error' });
      if (data.success) loadData();
    } catch (e: any) {
      setNotify({ open: true, message: e.message, severity: 'error' });
    }
  };

  const filtered = procs.filter(p => 
    p.cmd.toLowerCase().includes(search.toLowerCase()) || 
    p.user.toLowerCase().includes(search.toLowerCase()) ||
    p.pid.toString().includes(search)
  );

  const columns: GridColDef[] = [
    { field: 'pid', headerName: 'PID', width: 90, renderCell: (params) => <Typography sx={{ fontFamily: 'monospace', fontWeight: 700 }}>{params.value}</Typography> },
    { field: 'user', headerName: 'User', width: 100 },
    { field: 'cpu', headerName: 'CPU%', width: 80, type: 'number', renderCell: (params) => (
      <Typography sx={{ fontWeight: 700, color: params.value > 50 ? 'error.main' : 'text.primary' }}>{params.value}%</Typography>
    )},
    { field: 'mem', headerName: 'MEM%', width: 80, type: 'number' },
    { field: 'time', headerName: 'Time', width: 100 },
    { field: 'cmd', headerName: 'Command', flex: 1, renderCell: (params) => (
      <Tooltip title={params.value}>
        <Typography sx={{ fontFamily: 'monospace', fontSize: '0.72rem', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
          {params.value}
        </Typography>
      </Tooltip>
    )},
    {
      field: 'actions',
      headerName: 'Actions',
      width: 80,
      sortable: false,
      renderCell: (params) => (
        <IconButton size="small" color="error" onClick={() => handleKill(params.row.pid)}>
          <Dangerous sx={{ fontSize: 18 }} />
        </IconButton>
      )
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
          <Button variant="outlined" startIcon={<Refresh />} onClick={loadData} disabled={loading}>
            Refresh
          </Button>
        </Box>
      </Box>

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

import { Box, Typography, Card, CardContent, Button, Chip, IconButton, Tooltip } from '@mui/material';
import { DataGrid, GridColDef, GridRenderCellParams } from '@mui/x-data-grid';
import { Person, Shield, Lock, PowerSettingsNew, Refresh, Edit } from '@mui/icons-material';
import { useState, useEffect } from 'react';
import apiClient from '../api/client';
import LoadingState from '../components/common/LoadingState';
import StatusBadge from '../components/common/StatusBadge';

export default function UsersPage() {
  const [users, setUsers] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  const loadData = () => {
    setLoading(true);
    apiClient.get('/api/users.php?action=list')
      .then(({ data }) => setUsers(data))
      .catch((e) => console.error(e))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    loadData();
  }, []);

  const toggleUserStatus = async (id: number) => {
    try {
      await apiClient.get(`/api/users.php?action=toggle_status&id=${id}`);
      loadData();
    } catch (e) {}
  };

  const columns: GridColDef[] = [
    { field: 'id', headerName: 'ID', width: 70 },
    { 
      field: 'username', 
      headerName: 'Identity', 
      flex: 1,
      renderCell: (params) => (
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, height: '100%' }}>
          <Person sx={{ color: 'text.secondary' }} />
          <Box>
            <Typography variant="body2" sx={{ fontWeight: 700 }}>{params.value}</Typography>
            <Typography variant="caption" sx={{ color: 'text.disabled' }}>{params.row.full_name || 'No full name'}</Typography>
          </Box>
        </Box>
      )
    },
    { 
      field: 'role', 
      headerName: 'Role', 
      width: 120,
      renderCell: (params) => (
        <Chip 
          icon={<Shield sx={{ fontSize: 14 }} />}
          label={params.value.toUpperCase()} 
          size="small" 
          color={params.value === 'admin' ? 'primary' : 'default'}
          sx={{ fontWeight: 700, fontSize: '0.65rem' }}
        />
      )
    },
    { 
      field: 'is_active', 
      headerName: 'Status', 
      width: 120,
      renderCell: (params) => (
        <StatusBadge 
          label={params.value ? 'ACTIVE' : 'DISABLED'} 
          color={params.value ? 'success' : 'error'} 
        />
      )
    },
    { 
      field: 'last_login', 
      headerName: 'Last Activity', 
      width: 180,
      renderCell: (params) => (
        <Typography variant="caption" sx={{ fontFamily: 'monospace' }}>
          {params.value ? new Date(params.value * 1000).toLocaleString() : 'Never'}
        </Typography>
      )
    },
    {
      field: 'actions',
      headerName: 'Actions',
      width: 150,
      renderCell: (params) => (
        <Box sx={{ display: 'flex', gap: 1 }}>
          <Tooltip title="Edit User"><IconButton size="small"><Edit sx={{ fontSize: 16 }} /></IconButton></Tooltip>
          <Tooltip title="Reset Password"><IconButton size="small"><Lock sx={{ fontSize: 16 }} /></IconButton></Tooltip>
          <Tooltip title={params.row.is_active ? 'Disable User' : 'Enable User'}>
            <IconButton 
              size="small" 
              color={params.row.is_active ? 'error' : 'success'}
              onClick={() => toggleUserStatus(params.row.id)}
            >
              <PowerSettingsNew sx={{ fontSize: 16 }} />
            </IconButton>
          </Tooltip>
        </Box>
      )
    }
  ];

  if (loading && users.length === 0) return <LoadingState message="Accessing user vault..." />;

  return (
    <Box sx={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Identity & Access
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Manage administrative users and platform permissions.
          </Typography>
        </Box>
        <Box sx={{ display: 'flex', gap: 2 }}>
          <Button variant="outlined" startIcon={<Refresh />} onClick={loadData}>Sync</Button>
          <Button variant="contained" startIcon={<Person />}>Add User</Button>
        </Box>
      </Box>

      <Card sx={{ flexGrow: 1 }}>
        <DataGrid
          rows={users}
          columns={columns}
          density="compact"
          disableRowSelectionOnClick
          sx={{ border: 'none' }}
        />
      </Card>
    </Box>
  );
}

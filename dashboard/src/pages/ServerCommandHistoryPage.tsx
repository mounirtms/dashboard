import {
  Box, Typography, Card, CardContent, Button, TextField, Select,
  MenuItem, FormControl, InputLabel, Table, TableBody, TableCell,
  TableContainer, TableHead, TableRow, Paper, InputAdornment,
  CircularProgress, Skeleton
} from '@mui/material';
import { History, Search, Refresh, Person, Terminal, Download } from '@mui/icons-material';
import { useState, useCallback } from 'react';
import apiClient from '../api/client';
import { usePolling } from '../hooks/usePolling';

interface BashEntry {
  timestamp: string;
  epoch: string | null;
  command: string;
}

interface HistoryResponse {
  history: BashEntry[];
}

const BASH_USERS = ['root', 'dev', 'beta', 'tsdnd', 'technadminy7', 'dnd', 'dashboard', 'pim', 'salah'] as const;

export default function ServerCommandHistoryPage() {
  const [user, setUser]   = useState('dev');
  const [search, setSearch] = useState('');
  const [limit, setLimit] = useState(200);

  const fetcher = useCallback(async (): Promise<HistoryResponse> => {
    const { data } = await apiClient.get(`/api/monitor.php?action=bash_history&username=${user}&lines=${limit}`);
    return data as HistoryResponse;
  }, [user, limit]);

  // no background auto-poll: history is on-demand; pass 0 to disable interval
  const { data, loading, refreshing, refetch } = usePolling<HistoryResponse>(fetcher, 0);

  const history  = data?.history ?? [];
  const filtered = search
    ? history.filter(h => h.command.toLowerCase().includes(search.toLowerCase()))
    : history;

  const handleDownload = () => {
    const content = filtered.map(h => `[${h.timestamp}] ${h.command}`).join('\n');
    const blob = new Blob([content], { type: 'text/plain' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url;
    a.download = `bash_history_${user}_${new Date().toISOString().slice(0, 10)}.txt`;
    a.click();
    URL.revokeObjectURL(url);
  };

  return (
    <Box sx={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
      {/* Header */}
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Server Command Audit
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Unified view of bash command history across all system users.
          </Typography>
        </Box>
        <Box sx={{ display: 'flex', gap: 1.5 }}>
          <Button
            variant="outlined"
            startIcon={<Download />}
            onClick={handleDownload}
            disabled={filtered.length === 0}
          >
            Export
          </Button>
          <Button
            variant="outlined"
            startIcon={refreshing ? <CircularProgress size={16} /> : <Refresh />}
            onClick={refetch}
            disabled={refreshing}
          >
            Refresh
          </Button>
        </Box>
      </Box>

      {/* Filter Bar */}
      <Card sx={{ mb: 3, bgcolor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.06)' }}>
        <CardContent sx={{ py: 2, display: 'flex', gap: 2, alignItems: 'center', flexWrap: 'wrap' }}>
          <FormControl size="small" sx={{ minWidth: 160 }}>
            <InputLabel>System User</InputLabel>
            <Select value={user} label="System User" onChange={(e) => setUser(e.target.value)}>
              {BASH_USERS.map(u => (
                <MenuItem key={u} value={u}>
                  <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                    <Person sx={{ fontSize: 16 }} />
                    {u}
                  </Box>
                </MenuItem>
              ))}
            </Select>
          </FormControl>

          <FormControl size="small" sx={{ minWidth: 120 }}>
            <InputLabel>Limit</InputLabel>
            <Select value={limit} label="Limit" onChange={(e) => setLimit(Number(e.target.value))}>
              <MenuItem value={50}>50 lines</MenuItem>
              <MenuItem value={200}>200 lines</MenuItem>
              <MenuItem value={500}>500 lines</MenuItem>
              <MenuItem value={1000}>1000 lines</MenuItem>
            </Select>
          </FormControl>

          <TextField
            size="small"
            placeholder="Search commands..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            sx={{ flexGrow: 1 }}
            slotProps={{
              input: {
                startAdornment: (
                  <InputAdornment position="start">
                    <Search sx={{ fontSize: 18, color: 'text.disabled' }} />
                  </InputAdornment>
                ),
              },
            }}
          />
        </CardContent>
      </Card>

      {/* Table */}
      <TableContainer
        component={Paper}
        sx={{ bgcolor: 'transparent', border: '1px solid rgba(255,255,255,0.06)', flexGrow: 1, overflow: 'auto' }}
      >
        <Table stickyHeader size="small">
          <TableHead>
            <TableRow>
              <TableCell sx={{ fontWeight: 700, bgcolor: 'rgba(255,255,255,0.03)', width: 180 }}>Timestamp</TableCell>
              <TableCell sx={{ fontWeight: 700, bgcolor: 'rgba(255,255,255,0.03)' }}>Command</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {loading ? (
              [...Array(8)].map((_, i) => (
                <TableRow key={i}>
                  <TableCell sx={{ py: 1 }}><Skeleton variant="text" width={140} /></TableCell>
                  <TableCell sx={{ py: 1 }}><Skeleton variant="text" width="80%" /></TableCell>
                </TableRow>
              ))
            ) : filtered.length > 0 ? (
              filtered.map((entry, i) => (
                <TableRow key={i} sx={{ '&:hover': { bgcolor: 'rgba(255,255,255,0.01)' } }}>
                  <TableCell sx={{ color: 'text.disabled', fontSize: '0.75rem', py: 0.5, borderBottom: '1px solid rgba(255,255,255,0.04)' }}>
                    {entry.timestamp}
                  </TableCell>
                  <TableCell sx={{ py: 0.5, borderBottom: '1px solid rgba(255,255,255,0.04)' }}>
                    <Typography sx={{ fontFamily: 'monospace', fontSize: '0.85rem', color: '#e2e8f0', whiteSpace: 'pre-wrap', wordBreak: 'break-all' }}>
                      {entry.command}
                    </Typography>
                  </TableCell>
                </TableRow>
              ))
            ) : (
              <TableRow>
                <TableCell colSpan={2} sx={{ py: 10, textAlign: 'center' }}>
                  <Terminal sx={{ fontSize: 40, color: 'text.disabled', opacity: 0.2, mb: 1 }} />
                  <Typography color="text.disabled">No commands found matching your criteria</Typography>
                </TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      </TableContainer>
    </Box>
  );
}

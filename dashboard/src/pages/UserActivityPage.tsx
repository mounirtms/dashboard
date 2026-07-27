import { Box, Typography, Card, CardContent, Grid, Chip, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Paper, IconButton, Collapse, TablePagination, Button, Alert } from '@mui/material';
import { Person, ExpandMore, ExpandLess, Terminal, Storage, Memory, NetworkPing, AccessTime, Refresh } from '@mui/icons-material';
import { useState, useEffect, useCallback, useRef } from 'react';
import { fetchUserActivity, fetchBashHistory, UserData, BashHistoryEntry } from '../api/monitor';
import LoadingState from '../components/common/LoadingState';

function MetricCard({ title, value, icon, color }: { title: string; value: string | number; icon: React.ReactNode; color: string }) {
  return (
    <Card sx={{ bgcolor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.06)' }}>
      <CardContent sx={{ display: 'flex', alignItems: 'center', gap: 2, py: 2, px: 3 }}>
        <Box sx={{ color, bgcolor: `${color}15`, p: 1.2, borderRadius: 2 }}>
          {icon}
        </Box>
        <Box>
          <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 600, textTransform: 'uppercase', fontSize: '0.65rem' }}>
            {title}
          </Typography>
          <Typography variant="h5" sx={{ fontWeight: 800, letterSpacing: '-0.03em' }}>
            {value}
          </Typography>
        </Box>
      </CardContent>
    </Card>
  );
}

function BashHistoryTable({ entries }: { entries: BashHistoryEntry[] }) {
  if (entries.length === 0) {
    return (
      <Box sx={{ py: 4, textAlign: 'center' }}>
        <Terminal sx={{ fontSize: 32, color: 'text.disabled', mb: 1 }} />
        <Typography variant="body2" sx={{ color: 'text.disabled' }}>No commands found</Typography>
      </Box>
    );
  }
  return (
    <TableContainer component={Paper} sx={{ bgcolor: 'transparent', border: '1px solid rgba(255,255,255,0.04)' }}>
      <Table size="small" stickyHeader>
        <TableHead>
          <TableRow>
            <TableCell sx={{ fontWeight: 700, color: 'text.primary', fontSize: '0.7rem', bgcolor: 'rgba(255,255,255,0.03)' }}>Time</TableCell>
            <TableCell sx={{ fontWeight: 700, color: 'text.primary', fontSize: '0.7rem', bgcolor: 'rgba(255,255,255,0.03)' }}>Command</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {entries.map((entry, i) => (
            <TableRow key={i} sx={{ '&:last-child td': { borderBottom: 0 } }}>
              <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.7rem', py: 0.5, whiteSpace: 'nowrap', color: 'text.secondary' }}>
                {entry.timestamp !== 'unknown' ? entry.timestamp.slice(5) : '—'}
              </TableCell>
              <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.75rem', py: 0.5, color: '#e2e8f0' }}>
                {entry.command}
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </TableContainer>
  );
}

function UserRow({ user, expanded, onToggle }: { user: UserData; expanded: boolean; onToggle: () => void }) {
  const [bashHistory, setBashHistory] = useState<BashHistoryEntry[]>([]);
  const [loadingBash, setLoadingBash] = useState(false);

  const loadBashHistory = useCallback(() => {
    if (bashHistory.length > 0) return;
    setLoadingBash(true);
    fetchBashHistory(user.username, 30).then((data) => {
      setBashHistory(data.history || []);
    }).catch(() => {}).finally(() => setLoadingBash(false));
  }, [user.username, bashHistory.length]);

  useEffect(() => {
    if (expanded) loadBashHistory();
  }, [expanded, loadBashHistory]);

  const role = user.dashboard_user?.role || 'system';
  const fullName = user.dashboard_user?.full_name || user.username;
  const isActive = user.dashboard_user?.is_active ?? true;
  const lastLogin = user.dashboard_user?.last_login
    ? new Date(user.dashboard_user.last_login).toLocaleString()
    : 'Never';

  const roleColor = role === 'admin' ? '#f87171' : role === 'editor' ? '#fbbf24' : '#60a5fa';

  return (
    <>
      <TableRow
        onClick={onToggle}
        sx={{
          cursor: 'pointer',
          '&:hover': { bgcolor: 'rgba(255,255,255,0.02)' },
          borderBottom: '1px solid rgba(255,255,255,0.04)',
        }}
      >
        <TableCell sx={{ py: 1, px: 2 }}>
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5 }}>
            <Person sx={{ fontSize: 16, color: roleColor }} />
            <Box>
              <Typography sx={{ fontWeight: 700, fontSize: '0.8rem' }}>{user.username}</Typography>
              <Typography sx={{ fontSize: '0.65rem', color: 'text.disabled' }}>{fullName}</Typography>
            </Box>
          </Box>
        </TableCell>
        <TableCell sx={{ py: 1, px: 2 }}>
          <Chip label={role} size="small" sx={{ bgcolor: `${roleColor}20`, color: roleColor, fontWeight: 600, fontSize: '0.65rem', height: 20 }} />
        </TableCell>
        <TableCell sx={{ py: 1, px: 2 }}>
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
            <AccessTime sx={{ fontSize: 14, color: user.active_sessions > 0 ? '#4ade80' : 'text.disabled' }} />
            <Typography sx={{ fontSize: '0.75rem', fontWeight: 600 }}>{user.active_sessions}</Typography>
          </Box>
        </TableCell>
        <TableCell sx={{ py: 1, px: 2 }}>
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
            <Terminal sx={{ fontSize: 14, color: user.ssh_sessions > 0 ? '#fbbf24' : 'text.disabled' }} />
            <Typography sx={{ fontSize: '0.75rem', fontWeight: 600 }}>{user.ssh_sessions}</Typography>
          </Box>
        </TableCell>
        <TableCell sx={{ py: 1, px: 2 }}>
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
            <Memory sx={{ fontSize: 14, color: 'text.secondary' }} />
            <Typography sx={{ fontSize: '0.75rem', fontWeight: 600 }}>{user.process_count}</Typography>
          </Box>
        </TableCell>
        <TableCell sx={{ py: 1, px: 2 }}>
          <Typography sx={{ fontFamily: 'monospace', fontSize: '0.75rem', color: 'text.secondary' }}>{user.disk_usage}</Typography>
        </TableCell>
        <TableCell sx={{ py: 1, px: 2 }}>
          <Typography sx={{ fontSize: '0.7rem', color: 'text.disabled' }}>{lastLogin}</Typography>
        </TableCell>
        <TableCell sx={{ py: 1, px: 2 }}>
          <Chip
            label={isActive ? 'Active' : 'Disabled'}
            size="small"
            sx={{
              bgcolor: isActive ? 'rgba(74,222,128,0.15)' : 'rgba(248,113,113,0.15)',
              color: isActive ? '#4ade80' : '#f87171',
              fontWeight: 600, fontSize: '0.6rem', height: 18
            }}
          />
        </TableCell>
        <TableCell sx={{ py: 1, px: 1 }}>
          <IconButton size="small" sx={{ color: 'text.disabled' }}>
            {expanded ? <ExpandLess sx={{ fontSize: 18 }} /> : <ExpandMore sx={{ fontSize: 18 }} />}
          </IconButton>
        </TableCell>
      </TableRow>
      <TableRow>
        <TableCell style={{ paddingBottom: 0, paddingTop: 0 }} colSpan={9}>
          <Collapse in={expanded} timeout="auto" unmountOnExit>
            <Box sx={{ px: 4, py: 2 }}>
              <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1.5, display: 'flex', alignItems: 'center', gap: 1 }}>
                <Terminal sx={{ fontSize: 16 }} />
                Recent Commands ({user.username})
              </Typography>
              {loadingBash ? (
                <LoadingState message="Loading bash history..." />
              ) : (
                <BashHistoryTable entries={bashHistory} />
              )}
              {user.ssh_details && user.ssh_details.length > 0 && (
                <Box sx={{ mt: 2 }}>
                  <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1, display: 'flex', alignItems: 'center', gap: 1 }}>
                    <NetworkPing sx={{ fontSize: 16 }} />
                    SSH Sessions
                  </Typography>
                  {user.ssh_details.map((detail, i) => (
                    <Typography key={i} sx={{ fontFamily: 'monospace', fontSize: '0.7rem', color: 'text.secondary', mb: 0.3 }}>
                      {detail}
                    </Typography>
                  ))}
                </Box>
              )}
            </Box>
          </Collapse>
        </TableCell>
      </TableRow>
    </>
  );
}

export default function UserActivityPage() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [expandedUser, setExpandedUser] = useState<string | null>(null);
  const [page, setPage] = useState(0);
  const [rowsPerPage, setRowsPerPage] = useState(10);
  const inFlightRef = useRef(false);

  const loadData = useCallback(() => {
    if (inFlightRef.current) return;
    inFlightRef.current = true;
    setLoading(true);
    setError(null);
    fetchUserActivity()
      .then((d) => { setData(d); })
      .catch((e) => {
        if (e?.response?.status !== 429) {
          console.error('Failed to fetch user activity', e);
          setError(e.message || 'Failed to load user activity');
        }
      })
      .finally(() => { setLoading(false); inFlightRef.current = false; });
  }, []);

  useEffect(() => {
    loadData();
    const interval = setInterval(loadData, 60000);
    return () => clearInterval(interval);
  }, [loadData]);

  if (loading && !data) return <LoadingState message="Loading user activity..." />;

  const users = data?.users || [];
  const global = data?.global || {};

  const paginatedUsers = users.slice(page * rowsPerPage, page * rowsPerPage + rowsPerPage);

  return (
    <Box sx={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            User Activity
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Monitor system users, active sessions, and resource usage.
          </Typography>
        </Box>
        <Button variant="outlined" size="small" startIcon={<Refresh />} onClick={loadData} disabled={loading}>
          Refresh
        </Button>
      </Box>

      {error && (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>
          {error}
        </Alert>
      )}

      {/* Summary Cards */}
      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 6, md: 3 }}>
          <MetricCard title="SSH Users" value={global.total_ssh_users || 0} icon={<Person sx={{ fontSize: 20 }} />} color="#3b82f6" />
        </Grid>
        <Grid size={{ xs: 6, md: 3 }}>
          <MetricCard title="Processes" value={global.total_processes || 0} icon={<Memory sx={{ fontSize: 20 }} />} color="#f59e0b" />
        </Grid>
        <Grid size={{ xs: 6, md: 3 }}>
          <MetricCard title="Load (1m)" value={global.load_1min || 0} icon={<NetworkPing sx={{ fontSize: 20 }} />} color="#10b981" />
        </Grid>
        <Grid size={{ xs: 6, md: 3 }}>
          <MetricCard title="Dashboard Users" value={users.filter((u: UserData) => u.active_sessions > 0).length} icon={<Storage sx={{ fontSize: 20 }} />} color="#8b5cf6" />
        </Grid>
      </Grid>

      {/* Users Table */}
      <TableContainer component={Paper} sx={{ bgcolor: 'transparent', border: '1px solid rgba(255,255,255,0.06)', flex: 1, overflow: 'auto' }}>
        <Table stickyHeader size="small">
          <TableHead>
            <TableRow>
              <TableCell sx={{ fontWeight: 700, color: 'text.primary', fontSize: '0.7rem', bgcolor: 'rgba(255,255,255,0.03)' }}>User</TableCell>
              <TableCell sx={{ fontWeight: 700, color: 'text.primary', fontSize: '0.7rem', bgcolor: 'rgba(255,255,255,0.03)' }}>Role</TableCell>
              <TableCell sx={{ fontWeight: 700, color: 'text.primary', fontSize: '0.7rem', bgcolor: 'rgba(255,255,255,0.03)' }}>Sessions</TableCell>
              <TableCell sx={{ fontWeight: 700, color: 'text.primary', fontSize: '0.7rem', bgcolor: 'rgba(255,255,255,0.03)' }}>SSH</TableCell>
              <TableCell sx={{ fontWeight: 700, color: 'text.primary', fontSize: '0.7rem', bgcolor: 'rgba(255,255,255,0.03)' }}>Processes</TableCell>
              <TableCell sx={{ fontWeight: 700, color: 'text.primary', fontSize: '0.7rem', bgcolor: 'rgba(255,255,255,0.03)' }}>Disk</TableCell>
              <TableCell sx={{ fontWeight: 700, color: 'text.primary', fontSize: '0.7rem', bgcolor: 'rgba(255,255,255,0.03)' }}>Last Login</TableCell>
              <TableCell sx={{ fontWeight: 700, color: 'text.primary', fontSize: '0.7rem', bgcolor: 'rgba(255,255,255,0.03)' }}>Status</TableCell>
              <TableCell sx={{ fontWeight: 700, color: 'text.primary', fontSize: '0.7rem', bgcolor: 'rgba(255,255,255,0.03)', width: 40 }}></TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {paginatedUsers.map((user: UserData) => (
              <UserRow
                key={user.username}
                user={user}
                expanded={expandedUser === user.username}
                onToggle={() => setExpandedUser(expandedUser === user.username ? null : user.username)}
              />
            ))}
          </TableBody>
        </Table>
      </TableContainer>

      <TablePagination
        component="div"
        count={users.length}
        page={page}
        onPageChange={(_, p) => setPage(p)}
        rowsPerPage={rowsPerPage}
        onRowsPerPageChange={(e) => { setRowsPerPage(parseInt(e.target.value, 10)); setPage(0); }}
        rowsPerPageOptions={[10, 25, 50, 100]}
        sx={{ color: 'text.secondary', '.MuiTablePagination-selectLabel,.MuiTablePagination-displayedRows': { fontSize: '0.7rem' } }}
      />
    </Box>
  );
}

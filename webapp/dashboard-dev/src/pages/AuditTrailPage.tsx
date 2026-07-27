import { Box, Typography, Card, CardContent, List, ListItem, ListItemText, Chip, Button, TextField, InputAdornment, TablePagination, Alert } from '@mui/material';
import { History as AuditIcon, Refresh, Security, AdminPanelSettings, TravelExplore, Search } from '@mui/icons-material';
import { useState, useEffect, useCallback } from 'react';
import apiClient from '../api/client';
import LoadingState from '../components/common/LoadingState';

function getActionColor(action: string): string {
  const a = action?.toUpperCase() || '';
  if (['EXECUTE', 'RUN', 'TRIGGER', 'RESTART', 'KILL'].includes(a)) return '#f87171'; // red
  if (['DELETE', 'REMOVE', 'PURGE', 'FLUSH'].includes(a))             return '#fb923c'; // orange
  if (['UPDATE', 'EDIT', 'MODIFY', 'SAVE', 'SET'].includes(a))        return '#fbbf24'; // yellow
  if (['CREATE', 'ADD', 'DEPLOY'].includes(a))                        return '#34d399'; // green
  if (['VIEW', 'GET', 'LIST', 'READ', 'FETCH'].includes(a))           return '#60a5fa'; // blue
  if (['LOGIN', 'LOGOUT', 'AUTH'].includes(a))                        return '#a78bfa'; // purple
  return '#94a3b8'; // muted grey for unknown
}

export default function AuditTrailPage() {
  const [entries, setEntries] = useState<string[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(0);
  const rowsPerPage = 25;

  const fetchAudit = useCallback(() => {
    setLoading(true);
    setError(null);
    apiClient.get('/api/monitor.php?action=audit')
      .then(({ data }) => setEntries(data.entries || []))
      .catch(e => {
        setError(e.response?.data?.message || e.message || 'Failed to load audit trail');
      })
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    fetchAudit();
  }, [fetchAudit]);

  const parseEntry = (entry: string) => {
    const regex = /^\[(.*?)\]\s+\[(.*?)\]\s+\[(.*?)\]\s+(.*?)\s+on\s+(.*?)\s+-\s+(.*)$/;
    const m = entry.match(regex);
    if (!m) return { raw: entry };
    return {
      time: m[1],
      ip: m[2],
      user: m[3],
      action: m[4],
      target: m[5],
      details: m[6]
    };
  };

  // Filter
  const filtered = search.trim()
    ? entries.filter(e => e.toLowerCase().includes(search.toLowerCase()))
    : entries;

  const paginated = filtered.slice(page * rowsPerPage, page * rowsPerPage + rowsPerPage);

  return (
    <Box>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Audit Infrastructure
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Historical record of administrative operations and platform actions.
          </Typography>
        </Box>
        <Box sx={{ display: 'flex', gap: 1.5, alignItems: 'center' }}>
          <TextField
            size="small"
            placeholder="Search audit entries..."
            value={search}
            onChange={(e) => { setSearch(e.target.value); setPage(0); }}
            sx={{ width: 260 }}
            slotProps={{
              input: {
                startAdornment: (
                  <InputAdornment position="start">
                    <Search sx={{ fontSize: 18, color: 'text.disabled' }} />
                  </InputAdornment>
                )
              }
            }}
          />
          <Button variant="outlined" startIcon={<Refresh />} onClick={fetchAudit} disabled={loading}>
            Refresh Trail
          </Button>
        </Box>
      </Box>

      {error && (
        <Alert severity="error" sx={{ mb: 2 }} action={
          <Button color="inherit" size="small" onClick={fetchAudit}>Retry</Button>
        }>
          {error}
        </Alert>
      )}

      {loading ? (
        <LoadingState message="Loading audit trail..." />
      ) : (
        <Card>
          <CardContent sx={{ p: 0 }}>
            {filtered.length > 0 && (
              <Box sx={{ px: 2, py: 1, borderBottom: '1px solid', borderColor: 'divider', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <Typography variant="caption" sx={{ color: 'text.disabled' }}>
                  {filtered.length} {search ? 'matching' : 'total'} entries
                </Typography>
              </Box>
            )}
            <List disablePadding>
              {paginated.length > 0 ? paginated.map((entry, idx) => {
                const p = parseEntry(entry);
                if (p.raw) return (
                  <ListItem key={idx} divider>
                    <ListItemText
                      primary={<Typography sx={{ fontFamily: 'monospace', fontSize: '0.75rem', color: 'text.secondary' }}>{p.raw}</Typography>}
                    />
                  </ListItem>
                );

                return (
                  <ListItem key={idx} divider sx={{ py: 2, px: 3, '&:hover': { backgroundColor: 'rgba(255,255,255,0.01)' } }}>
                    <Box sx={{ display: 'flex', gap: 3, width: '100%', alignItems: 'center' }}>
                      <Box sx={{ minWidth: 140 }}>
                        <Typography variant="caption" color="text.disabled" sx={{ fontWeight: 700, display: 'block' }}>{p.time}</Typography>
                        <Typography variant="caption" sx={{ fontFamily: 'monospace', color: 'primary.light' }}>{p.ip}</Typography>
                      </Box>

                      <Box sx={{ minWidth: 100 }}>
                        <Chip
                          icon={<AdminPanelSettings sx={{ fontSize: 14 }} />}
                          label={p.user}
                          size="small"
                          variant="outlined"
                          sx={{ fontWeight: 700, borderColor: 'rgba(255,255,255,0.1)' }}
                        />
                      </Box>

                      <Box sx={{ minWidth: 120 }}>
                        <Typography sx={{
                          fontWeight: 900,
                          fontSize: '0.75rem',
                          color: getActionColor(p.action || ''),
                          letterSpacing: '0.04em',
                        }}>
                          {p.action}
                        </Typography>
                      </Box>

                      <Box sx={{ flexGrow: 1 }}>
                        <Typography variant="body2" sx={{ fontWeight: 600 }}>{p.target}</Typography>
                        <Typography variant="caption" color="text.secondary">{p.details}</Typography>
                      </Box>
                    </Box>
                  </ListItem>
                );
              }) : (
                <Box sx={{ py: 10, textAlign: 'center' }}>
                  <TravelExplore sx={{ fontSize: 48, color: 'text.disabled', opacity: 0.2, mb: 1 }} />
                  <Typography color="text.disabled">
                    {search ? `No entries matching "${search}"` : 'No audit entries found in the secure vault.'}
                  </Typography>
                </Box>
              )}
            </List>

            {filtered.length > rowsPerPage && (
              <TablePagination
                component="div"
                count={filtered.length}
                page={page}
                onPageChange={(_, p) => setPage(p)}
                rowsPerPage={rowsPerPage}
                rowsPerPageOptions={[25]}
                sx={{ color: 'text.secondary', '.MuiTablePagination-displayedRows': { fontSize: '0.7rem' } }}
              />
            )}
          </CardContent>
        </Card>
      )}
    </Box>
  );
}

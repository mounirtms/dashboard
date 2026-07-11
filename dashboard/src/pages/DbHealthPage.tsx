import { Box, Typography, Grid, Card, CardContent, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, LinearProgress, Button, Alert, Snackbar, CircularProgress, Chip } from '@mui/material';
import { Storage, CleaningServices, Info, CheckCircle, Refresh, TuneOutlined } from '@mui/icons-material';
import { useState, useEffect, useCallback } from 'react';
import { fetchDbHealth, performDbAction } from '../api/system';
import LoadingState from '../components/common/LoadingState';
import { formatNumber } from '../utils/formatters';

export default function DbHealthPage() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [executing, setExecuting] = useState<string | null>(null);
  const [notify, setNotify] = useState({ open: false, message: '', severity: 'success' as any });

  const loadData = useCallback(() => {
    setLoading(true);
    fetchDbHealth()
      .then(setData)
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleOptimize = async (db: string, table: string) => {
    const key = `${db}-${table}`;
    setExecuting(key);
    try {
      const res = await performDbAction('optimize', db, table);
      setNotify({ open: true, message: res.message || 'Operation successful', severity: res.success ? 'success' : 'error' });
      if (res.success) loadData();
    } catch (e: any) {
      setNotify({ open: true, message: e.message, severity: 'error' });
    } finally {
      setExecuting(null);
    }
  };

  if (loading && !data) return <LoadingState message="Loading Database metrics..." />;
  if (!data) return null;

  return (
    <Box>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Database Health
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            MariaDB {data.version} performance and storage optimization.
          </Typography>
        </Box>
        <Button startIcon={<Refresh />} variant="outlined" onClick={loadData} disabled={loading}>Refresh</Button>
      </Box>

      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 12, md: 8 }}>
          <Card>
            <CardContent>
              <Typography variant="h6" sx={{ mb: 2, fontWeight: 700 }}>Fragmented Tables</Typography>
              <TableContainer>
                <Table size="small">
                  <TableHead>
                    <TableRow>
                      <TableCell>Table Name</TableCell>
                      <TableCell>Database</TableCell>
                      <TableCell align="right">Size</TableCell>
                      <TableCell align="right">Fragmented</TableCell>
                      <TableCell align="right">Rows</TableCell>
                      <TableCell align="right">Action</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {data.fragmented_tables.map((row: any, i: number) => (
                      <TableRow key={i} hover>
                        <TableCell sx={{ fontFamily: 'monospace', fontWeight: 600 }}>{row.TABLE_NAME}</TableCell>
                        <TableCell sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>{row.db}</TableCell>
                        <TableCell align="right">{row.size_mb} MB</TableCell>
                        <TableCell align="right" sx={{ color: row.frag_mb > 100 ? 'error.main' : 'warning.main', fontWeight: 700 }}>{row.frag_mb} MB</TableCell>
                        <TableCell align="right">{formatNumber(row.TABLE_ROWS)}</TableCell>
                        <TableCell align="right">
                          <Button 
                            size="small" 
                            variant="outlined" 
                            startIcon={executing === `${row.db}-${row.TABLE_NAME}` ? <CircularProgress size={14} color="inherit" /> : <CleaningServices sx={{ fontSize: 14 }} />}
                            onClick={() => handleOptimize(row.db, row.TABLE_NAME)}
                            disabled={!!executing}
                          >
                            Optimize
                          </Button>
                        </TableCell>
                      </TableRow>
                    ))}
                    {data.fragmented_tables.length === 0 && (
                      <TableRow>
                        <TableCell colSpan={6} align="center" sx={{ py: 4, color: 'text.disabled' }}>No fragmented tables found</TableCell>
                      </TableRow>
                    )}
                  </TableBody>
                </Table>
              </TableContainer>
            </CardContent>
          </Card>
        </Grid>

        <Grid size={{ xs: 12, md: 4 }}>
          <Box sx={{ display: 'grid', gap: 2 }}>
            <Card>
              <CardContent>
                <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1.5, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <Storage sx={{ color: 'primary.main', fontSize: 20 }} /> Connection Usage
                </Typography>
                <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 1 }}>
                  <Typography variant="caption">Active Connections</Typography>
                  <Typography variant="caption" sx={{ fontWeight: 700 }}>{data.connections.active} / {data.connections.max}</Typography>
                </Box>
                <LinearProgress 
                  variant="determinate" 
                  value={data.connections.percentage} 
                  color={data.connections.percentage > 80 ? "error" : data.connections.percentage > 50 ? "warning" : "success"} 
                  sx={{ height: 6, borderRadius: 3 }} 
                />
                <Typography variant="caption" sx={{ mt: 1, display: 'block', color: 'text.disabled' }}>Peak connections: {data.connections.peak}</Typography>
              </CardContent>
            </Card>
            
            <Card>
              <CardContent>
                <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1.5, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <Info sx={{ color: 'info.main', fontSize: 20 }} /> Slow Queries
                </Typography>
                <Typography variant="h4" sx={{ fontWeight: 800 }}>{data.slow_queries}</Typography>
                <Typography variant="caption" sx={{ color: 'text.secondary' }}>Since server start</Typography>
              </CardContent>
            </Card>

            <Card>
              <CardContent>
                <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1.5, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <CheckCircle sx={{ color: 'success.main', fontSize: 20 }} /> Uptime
                </Typography>
                <Typography variant="h4" sx={{ fontWeight: 800 }}>{data.uptime_formatted}</Typography>
                <Typography variant="caption" sx={{ color: 'text.secondary' }}>Service health: Online</Typography>
              </CardContent>
            </Card>
          </Box>
        </Grid>
      </Grid>

      {/* ── Audit Tuning Applied Banner ── */}
      <Card sx={{ mb: 0, mt: 1, background: 'linear-gradient(135deg, rgba(6,182,212,0.06) 0%, rgba(59,130,246,0.06) 100%)', border: '1px solid rgba(6,182,212,0.2)' }}>
        <CardContent sx={{ py: '12px !important' }}>
          <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 1 }}>
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
              <TuneOutlined sx={{ fontSize: 16, color: '#06b6d4' }} />
              <Typography variant="caption" sx={{ fontWeight: 700, color: '#94a3b8' }}>Audit Tuning Applied — Jul 8, 2026</Typography>
            </Box>
            <Box sx={{ display: 'flex', gap: 1, flexWrap: 'wrap' }}>
              {[
                { label: 'buffer_pool = 8 GB', color: 'success' as const },
                { label: 'max_connections = 300', color: 'success' as const },
                { label: 'innodb_flush = 2', color: 'info' as const },
                { label: 'port = 3307', color: 'default' as const },
                { label: 'pm=static max=30', color: 'secondary' as const },
              ].map(c => (
                <Chip key={c.label} label={c.label} size="small" color={c.color} variant="outlined"
                  sx={{ fontSize: '0.6rem', height: 20, fontFamily: 'monospace', fontWeight: 700 }} />
              ))}
            </Box>
          </Box>
        </CardContent>
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

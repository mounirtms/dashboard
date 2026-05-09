import { Box, Typography, Grid, Card, CardContent, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, LinearProgress, Button, Alert, Snackbar, CircularProgress } from '@mui/material';
import { Storage, CleaningServices, Info, CheckCircle, Refresh } from '@mui/icons-material';
import { useState, useEffect } from 'react';
import { fetchDbHealth, performDbAction } from '../api/system';
import LoadingState from '../components/common/LoadingState';
import { formatNumber } from '../utils/formatters';

export default function DbHealthPage() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [executing, setExecuting] = useState<string | null>(null);
  const [notify, setNotify] = useState({ open: false, message: '', severity: 'success' as any });

  const loadData = () => {
    setLoading(true);
    fetchDbHealth()
      .then(setData)
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    loadData();
  }, []);

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

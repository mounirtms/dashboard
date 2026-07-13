import { Grid, Box, Typography, Card, CardContent, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Button, Divider, Alert, useTheme } from '@mui/material';
import { Memory, Storage, Speed, Timer, CheckCircle, Warning, TipsAndUpdates, Refresh, CleaningServices, LocalFireDepartment } from '@mui/icons-material';
import { useSystemOverviewContext } from '../contexts/SystemOverviewContext.tsx';
import StatCard from '../components/common/StatCard';
import LoadingState from '../components/common/LoadingState';
import StatusBadge from '../components/common/StatusBadge';
import { runEmergencyCleanup } from '../api/system';
import { useState } from 'react';

export default function SystemOverviewPage() {
  const { data, loading, error, refetch } = useSystemOverviewContext();
  const theme = useTheme();
  const [cleaning, setCleaning] = useState(false);

  if (loading && !data) return <LoadingState message="Loading system data..." />;
  if (error) return <Alert severity="error" sx={{ mb: 2 }}>Error: {error}</Alert>;
  if (!data) return null;

  const loadColor = data.load['1min'] > 8 ? 'error' : data.load['1min'] > 4 ? 'warning' : 'success';
  const memColor = data.memory.used_pct > 85 ? 'error' : data.memory.used_pct > 70 ? 'warning' : 'success';
  const diskPct = parseInt(data.disk.pct.replace('%', ''));
  const diskColor = diskPct > 90 ? 'error' : diskPct > 80 ? 'warning' : 'success';

  const handleQuickCleanup = async () => {
    setCleaning(true);
    try {
      await runEmergencyCleanup('all');
      refetch();
    } catch (e) {}
    setCleaning(false);
  };

  const insights = [];
  if (data.load['1min'] > 6) insights.push({ type: 'warning', text: 'CPU Load is high. Check for rogue PHP processes in Process Explorer.' });
  if (data.memory.used_pct > 80) insights.push({ type: 'error', text: 'RAM usage is critical. Consider flushing Varnish or Magento caches.' });
  if (diskPct > 85) insights.push({ type: 'error', text: 'Disk space is critical! Truncate large log files immediately.' });
  if (data.load['1min'] < 2 && data.memory.used_pct < 50) insights.push({ type: 'info', text: 'System load is very low. Good time for indexing or backups.' });
  if (Object.values(data.services).some(s => s !== 'running')) insights.push({ type: 'error', text: 'Service alert: One or more critical services are offline!' });
  if (insights.length === 0) insights.push({ type: 'success', text: 'All systems green. No immediate actions required.' });

  return (
    <Box>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            System Overview
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary', fontSize: '0.88rem' }}>
            {data.uptime} &middot; Last updated: {new Date(data.timestamp * 1000).toLocaleTimeString()}
          </Typography>
        </Box>
        <Button startIcon={<Refresh />} variant="outlined" size="small" onClick={() => refetch()} disabled={loading}>
          Refresh
        </Button>
      </Box>

      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatCard 
            label="CPU Load (1m)" 
            value={data.load['1min']} 
            color={loadColor} 
            subvalue={`5m: ${data.load['5min']} | 15m: ${data.load['15min']}`}
            icon={<Speed />} 
          />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatCard 
            label="Memory Usage" 
            value={`${data.memory.used_pct}%`} 
            color={memColor} 
            subvalue={`${data.memory.available_mb}MB available`}
            icon={<Memory />} 
          />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatCard 
            label="Disk Usage" 
            value={data.disk.pct} 
            color={diskColor} 
            subvalue={`${data.disk.free} free of ${data.disk.total}`}
            icon={<Storage />} 
          />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatCard 
            label="Uptime" 
            value={data.uptime.replace('uptime ', '')} 
            color="info" 
            icon={<Timer />} 
          />
        </Grid>
      </Grid>

      <Grid container spacing={2}>
        <Grid size={{ xs: 12, md: 8 }}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Typography variant="h6" sx={{ mb: 2, fontWeight: 700 }}>Top CPU Processes</Typography>
              <TableContainer>
                <Table size="small">
                  <TableHead>
                    <TableRow>
                      <TableCell>PID</TableCell>
                      <TableCell align="right">CPU%</TableCell>
                      <TableCell align="right">MEM%</TableCell>
                      <TableCell align="right">Time</TableCell>
                      <TableCell>Command</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {data.top_procs.map((proc) => (
                      <TableRow key={proc.pid} sx={{ '&:last-child td, &:last-child th': { border: 0 } }}>
                        <TableCell component="th" scope="row" sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>{proc.pid}</TableCell>
                        <TableCell align="right" sx={{ fontWeight: 600 }}>{proc.cpu}</TableCell>
                        <TableCell align="right">{proc.mem}</TableCell>
                        <TableCell align="right" sx={{ color: 'text.secondary', fontSize: '0.75rem' }}>{proc.time}</TableCell>
                        <TableCell sx={{ fontSize: '0.75rem', maxWidth: 300, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{proc.cmd}</TableCell>
                      </TableRow>
                    ))}
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
                <Typography variant="h6" sx={{ mb: 2, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <TipsAndUpdates color="primary" /> Smart Insights
                </Typography>
                <Box sx={{ display: 'grid', gap: 1 }}>
                  {insights.map((insight, i) => (
                    <Alert key={i} severity={insight.type as any} sx={{ '& .MuiAlert-message': { fontSize: '0.75rem' }, py: 0 }}>
                      {insight.text}
                    </Alert>
                  ))}
                </Box>
                <Divider sx={{ my: 2 }} />
                <Button 
                  fullWidth 
                  variant="contained" 
                  color="error" 
                  startIcon={<LocalFireDepartment />}
                  onClick={handleQuickCleanup}
                  disabled={cleaning}
                >
                  {cleaning ? 'Cleaning...' : 'Run Quick Cleanup'}
                </Button>
              </CardContent>
            </Card>

            <Card>
              <CardContent>
                <Typography variant="h6" sx={{ mb: 2, fontWeight: 700 }}>Service Status</Typography>
                <Box sx={{ display: 'grid', gap: 1.5 }}>
                  {Object.entries(data.services).map(([name, status]) => (
                    <Box key={name} sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', p: 1.5, borderRadius: 1, backgroundColor: 'background.default', border: '1px solid', borderColor: 'divider' }}>
                      <Typography variant="body2" sx={{ fontWeight: 600, fontSize: '0.85rem' }}>{name}</Typography>
                      <StatusBadge 
                        label={status.toUpperCase()} 
                        color={status === 'running' ? 'success' : 'error'} 
                        icon={status === 'running' ? <CheckCircle sx={{ fontSize: 14 }} /> : <Warning sx={{ fontSize: 14 }} />}
                      />
                    </Box>
                  ))}
                </Box>
              </CardContent>
            </Card>
          </Box>
        </Grid>
      </Grid>
    </Box>
  );
}

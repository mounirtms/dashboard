import { Grid, Box, Typography, Card, CardContent, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, useTheme } from '@mui/material';
import { Memory, Storage, Speed, Timer, CheckCircle, Warning } from '@mui/icons-material';
import { useSystemOverview } from '../hooks/useSystemData';
import StatCard from '../components/common/StatCard';
import LoadingState from '../components/common/LoadingState';
import StatusBadge from '../components/common/StatusBadge';

export default function SystemOverviewPage() {
  const { data, loading, error } = useSystemOverview();
  const theme = useTheme();

  if (loading && !data) return <LoadingState message="Loading system data..." />;
  if (error) return <LoadingState message={`Error: ${error}`} />;
  if (!data) return null;

  const loadColor = data.load['1min'] > 8 ? 'error' : data.load['1min'] > 4 ? 'warning' : 'success';
  const memColor = data.memory.used_pct > 85 ? 'error' : data.memory.used_pct > 70 ? 'warning' : 'success';
  const diskPct = parseInt(data.disk.pct.replace('%', ''));
  const diskColor = diskPct > 90 ? 'error' : diskPct > 80 ? 'warning' : 'success';

  return (
    <Box>
      <Box sx={{ mb: 3 }}>
        <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
          System Overview
        </Typography>
        <Typography variant="body2" sx={{ color: 'text.secondary', fontSize: '0.88rem' }}>
          {data.uptime} &middot; Last updated: {new Date(data.timestamp * 1000).toLocaleTimeString()}
        </Typography>
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
          <Card sx={{ height: '100%' }}>
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
        </Grid>
      </Grid>
    </Box>
  );
}

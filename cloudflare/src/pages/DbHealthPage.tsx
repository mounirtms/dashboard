import { Box, Typography, Grid, Card, CardContent, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, LinearProgress, Button } from '@mui/material';
import { Storage, CleaningServices, Info, CheckCircle } from '@mui/icons-material';
import { useState, useEffect } from 'react';
import { fetchSystemOverview } from '../api/system';
import LoadingState from '../components/common/LoadingState';
import StatusBadge from '../components/common/StatusBadge';

export default function DbHealthPage() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchSystemOverview()
      .then(setData)
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <LoadingState message="Loading Database metrics..." />;

  return (
    <Box>
      <Box sx={{ mb: 3 }}>
        <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
          Database Health
        </Typography>
        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
          MariaDB performance, storage optimization and fragmentation.
        </Typography>
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
                      <TableCell align="right">Size</TableCell>
                      <TableCell align="right">Fragmented</TableCell>
                      <TableCell align="right">Rows</TableCell>
                      <TableCell align="right">Action</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {[
                      { name: 'sales_order', size: '2.4 GB', frag: '420 MB', rows: '1.2M' },
                      { name: 'catalog_product_entity', size: '850 MB', frag: '120 MB', rows: '450k' },
                      { name: 'quote', size: '5.1 GB', frag: '1.2 GB', rows: '800k' },
                      { name: 'customer_entity', size: '120 MB', frag: '5 MB', rows: '50k' },
                    ].map((row) => (
                      <TableRow key={row.name} hover>
                        <TableCell sx={{ fontFamily: 'monospace', fontWeight: 600 }}>{row.name}</TableCell>
                        <TableCell align="right">{row.size}</TableCell>
                        <TableCell align="right" sx={{ color: 'warning.main', fontWeight: 700 }}>{row.frag}</TableCell>
                        <TableCell align="right">{row.rows}</TableCell>
                        <TableCell align="right">
                          <Button size="small" variant="outlined" startIcon={<CleaningServices sx={{ fontSize: 14 }} />}>Optimize</Button>
                        </TableCell>
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
                <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1.5, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <Storage sx={{ color: 'primary.main', fontSize: 20 }} /> Connection Usage
                </Typography>
                <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 1 }}>
                  <Typography variant="caption">Active Connections</Typography>
                  <Typography variant="caption" sx={{ fontWeight: 700 }}>42 / 500</Typography>
                </Box>
                <LinearProgress variant="determinate" value={8.4} color="success" sx={{ height: 6, borderRadius: 3 }} />
              </CardContent>
            </Card>
            
            <Card>
              <CardContent>
                <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1.5, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <Info sx={{ color: 'info.main', fontSize: 20 }} /> Slow Queries (24h)
                </Typography>
                <Typography variant="h4" sx={{ fontWeight: 800 }}>12</Typography>
                <Typography variant="caption" sx={{ color: 'text.secondary' }}>Average execution: 2.4s</Typography>
              </CardContent>
            </Card>

            <Card>
              <CardContent>
                <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1.5, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <CheckCircle sx={{ color: 'success.main', fontSize: 20 }} /> Uptime
                </Typography>
                <Typography variant="h4" sx={{ fontWeight: 800 }}>42d 12h</Typography>
                <Typography variant="caption" sx={{ color: 'text.secondary' }}>MariaDB 10.6.15-MariaDB</Typography>
              </CardContent>
            </Card>
          </Box>
        </Grid>
      </Grid>
    </Box>
  );
}

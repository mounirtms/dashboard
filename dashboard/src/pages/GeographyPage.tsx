import { Box, Typography, Card, CardContent, Grid, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Paper, useTheme } from '@mui/material';
import { DataGrid, type GridColDef } from '@mui/x-data-grid';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import { Map as AlgeriaMap } from 'algeria-map-ts';
import { useCloudflareData } from '../hooks/useCloudflareData';
import LoadingState from '../components/common/LoadingState';
import { formatNumber, formatBytes } from '../utils/formatters';

export default function GeographyPage() {
  const theme = useTheme();
  const { data, loading, error } = useCloudflareData();

  if (loading) return <LoadingState />;
  if (error) return <LoadingState message={`Error: ${error}`} />;
  if (!data) return null;

  const countryColumns: GridColDef[] = [
    { field: 'flag', headerName: '', width: 50, renderCell: (params) => <span style={{ fontSize: 20 }}>{params.value}</span> },
    { field: 'name', headerName: 'Country', width: 160, flex: 1 },
    { field: 'requests', headerName: 'Requests', width: 120, type: 'number', valueFormatter: (v) => formatNumber(v) },
    { field: 'percentage', headerName: '% of Total', width: 110, type: 'number', valueFormatter: (v) => `${v}%` },
    { field: 'bytes', headerName: 'Bandwidth', width: 120, valueFormatter: (v) => formatBytes(v) },
    { field: 'threats', headerName: 'Threats', width: 100, type: 'number', valueFormatter: (v) => formatNumber(v) },
  ];

  const chartData = (data.countries || []).slice(0, 8).map((c) => ({
    name: `${c.flag} ${c.name}`,
    requests: c.requests,
  }));

  const gridColor40 = `${theme.palette.divider}66`;
  const gridColor60 = `${theme.palette.divider}99`;

  // Map data for Algeria Map component (mock values or from real data if available)
  const mapData = {
    '16': { value: 'High', color: theme.palette.primary.main },
    '31': { value: 'Medium', color: theme.palette.primary.light },
    '06': { value: 'Low', color: theme.palette.primary.dark },
  };

  return (
    <Box>
      <Typography variant="h6" sx={{ mb: 0.5, fontWeight: 700 }}>Geography & Regional Traffic</Typography>
      <Typography variant="body2" color="textSecondary" sx={{ mb: 3 }}>Traffic by country and regional distribution in Algeria</Typography>

      <Grid container spacing={3}>
        <Grid size={{ xs: 12, md: 7 }}>
          <Card sx={{ mb: 3 }}>
            <CardContent>
              <Typography variant="subtitle2" sx={{ mb: 2, fontWeight: 600 }}>Global Traffic Distribution (7d)</Typography>
              <div style={{ height: 380, width: '100%' }}>
                <DataGrid
                  rows={data.countries || []}
                  columns={countryColumns}
                  getRowId={(row) => row.code}
                  pageSizeOptions={[10]}
                  initialState={{ pagination: { paginationModel: { pageSize: 10 } } }}
                  disableRowSelectionOnClick
                  sx={{
                    '& .MuiDataGrid-cell': { borderBottom: `1px solid ${gridColor40}` },
                    '& .MuiDataGrid-columnHeaders': { borderBottom: `1px solid ${gridColor60}`, backgroundColor: `${theme.palette.divider}4d` },
                    '& .MuiDataGrid-footerContainer': { borderTop: `1px solid ${gridColor60}`, backgroundColor: `${theme.palette.divider}33` },
                  }}
                />
              </div>
            </CardContent>
          </Card>
        </Grid>

        <Grid size={{ xs: 12, md: 5 }}>
          <Card sx={{ mb: 3, background: 'rgba(10, 14, 24, 0.4)' }}>
            <CardContent>
              <Typography variant="subtitle2" sx={{ mb: 2, fontWeight: 600 }}>Regional Focus: Algeria</Typography>
              <Box sx={{ 
                height: 380, 
                display: 'flex', 
                alignItems: 'center', 
                justifyContent: 'center',
                '& svg': { maxWidth: '100%', maxHeight: '100%' }
              }}>
                <AlgeriaMap 
                  data={mapData} 
                  color="#1e293b" 
                  HoverColor={theme.palette.primary.main}
                  stroke="#334155"
                  width="100%"
                  height="100%"
                />
              </Box>
            </CardContent>
          </Card>

          <Card sx={{ mb: 3 }}>
            <CardContent>
              <Typography variant="subtitle2" sx={{ mb: 2, fontWeight: 600 }}>Top Countries Chart</Typography>
              <ResponsiveContainer width="100%" height={300}>
                <BarChart data={chartData}>
                  <CartesianGrid strokeDasharray="3 3" stroke={gridColor60} />
                  <XAxis dataKey="name" stroke={theme.palette.text.secondary} tick={{ fontSize: 11 }} angle={-30} textAnchor="end" height={70} />
                  <YAxis stroke={theme.palette.text.secondary} tick={{ fontSize: 11 }} tickFormatter={(v) => v >= 1000 ? `${(v / 1000).toFixed(0)}k` : v} />
                  <Tooltip contentStyle={{ backgroundColor: theme.palette.background.paper, border: `1px solid ${theme.palette.divider}`, borderRadius: 10, color: theme.palette.text.primary }} />
                  <Bar dataKey="requests" fill={`${theme.palette.success.main}99`} radius={[4, 4, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        </Grid>

        <Grid size={12}>
          <Card>
            <CardContent>
              <Typography variant="subtitle2" sx={{ mb: 2, fontWeight: 600 }}>Top URLs by Requests (7d)</Typography>
              <TableContainer component={Paper} sx={{ backgroundColor: 'transparent', boxShadow: 'none' }}>
                <Table size="small">
                  <TableHead>
                    <TableRow sx={{ borderBottom: 1, borderColor: gridColor60 }}>
                      <TableCell sx={{ fontWeight: 600, color: 'text.secondary' }}>URL</TableCell>
                      <TableCell sx={{ fontWeight: 600, color: 'text.secondary' }}>Requests</TableCell>
                      <TableCell sx={{ fontWeight: 600, color: 'text.secondary' }}>Bandwidth</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {(data.top_urls || []).slice(0, 10).map((url: any, i: number) => (
                      <TableRow key={i} sx={{ borderBottom: 1, borderColor: gridColor40 }}>
                        <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.8rem', color: 'primary.main' }}>{url.path}</TableCell>
                        <TableCell>{formatNumber(url.requests)}</TableCell>
                        <TableCell>{formatBytes(url.bytes)}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </TableContainer>
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </Box>
  );
}

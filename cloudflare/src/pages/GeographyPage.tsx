import { Box, Typography, Card, CardContent, Grid, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Paper } from '@mui/material';
import { DataGrid, type GridColDef } from '@mui/x-data-grid';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import { useCloudflareData } from '../hooks/useCloudflareData';
import LoadingState from '../components/common/LoadingState';
import { formatNumber, formatBytes } from '../utils/formatters';

const tooltipStyle = { backgroundColor: '#1e293b', border: '1px solid rgba(30, 41, 59, 0.8)', borderRadius: 8, color: '#e2e8f0' };

export default function GeographyPage() {
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

  const chartData = data.countries.slice(0, 8).map((c) => ({
    name: `${c.flag} ${c.name}`,
    requests: c.requests,
  }));

  return (
    <Box>
      <Typography variant="h6" sx={{ mb: 0.5, fontWeight: 700 }}>Geography</Typography>
      <Typography variant="body2" color="textSecondary" sx={{ mb: 3 }}>Traffic by country and top URLs</Typography>

      <Grid container spacing={3}>
        <Grid size={{ xs: 12, md: 7 }}>
          <Card sx={{ mb: 3 }}>
            <CardContent>
              <Typography variant="subtitle2" sx={{ mb: 2, fontWeight: 600 }}>Countries by Requests (7d)</Typography>
              <div style={{ height: 380, width: '100%' }}>
                <DataGrid
                  rows={data.countries}
                  columns={countryColumns}
                  getRowId={(row) => row.code}
                  pageSizeOptions={[10]}
                  initialState={{ pagination: { paginationModel: { pageSize: 10 } } }}
                  disableRowSelectionOnClick
                  sx={{
                    '& .MuiDataGrid-cell': { borderBottom: '1px solid rgba(30, 41, 59, 0.4)' },
                    '& .MuiDataGrid-columnHeaders': { borderBottom: '1px solid rgba(30, 41, 59, 0.6)', backgroundColor: 'rgba(30, 41, 59, 0.3)' },
                    '& .MuiDataGrid-footerContainer': { borderTop: '1px solid rgba(30, 41, 59, 0.6)', backgroundColor: 'rgba(30, 41, 59, 0.2)' },
                  }}
                />
              </div>
            </CardContent>
          </Card>
        </Grid>

        <Grid size={{ xs: 12, md: 5 }}>
          <Card sx={{ mb: 3 }}>
            <CardContent>
              <Typography variant="subtitle2" sx={{ mb: 2, fontWeight: 600 }}>Top Countries Chart</Typography>
              <ResponsiveContainer width="100%" height={300}>
                <BarChart data={chartData}>
                  <CartesianGrid strokeDasharray="3 3" stroke="rgba(30, 41, 59, 0.6)" />
                  <XAxis dataKey="name" stroke="#94a3b8" tick={{ fontSize: 11 }} angle={-30} textAnchor="end" height={70} />
                  <YAxis stroke="#94a3b8" tick={{ fontSize: 11 }} tickFormatter={(v) => v >= 1000 ? `${(v / 1000).toFixed(0)}k` : v} />
                  <Tooltip contentStyle={tooltipStyle} />
                  <Bar dataKey="requests" fill="rgba(34, 197, 94, 0.6)" radius={[4, 4, 0, 0]} />
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
                    <TableRow sx={{ borderBottom: '1px solid rgba(30, 41, 59, 0.6)' }}>
                      <TableCell sx={{ fontWeight: 600, color: 'text.secondary' }}>URL</TableCell>
                      <TableCell sx={{ fontWeight: 600, color: 'text.secondary' }}>Requests</TableCell>
                      <TableCell sx={{ fontWeight: 600, color: 'text.secondary' }}>Bandwidth</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {data.top_urls.slice(0, 10).map((url, i) => (
                      <TableRow key={i} sx={{ borderBottom: '1px solid rgba(30, 41, 59, 0.3)' }}>
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

import {
  Box, Typography, Card, CardContent, Grid, Table, TableBody, TableCell,
  TableContainer, TableHead, TableRow, Paper, useTheme, Alert, Chip, Tooltip,
} from '@mui/material';
import { DataGrid, type GridColDef } from '@mui/x-data-grid';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip as RTooltip, ResponsiveContainer } from 'recharts';
import { useCloudflareData } from '../hooks/useCloudflareData';
import LoadingState from '../components/common/LoadingState';
import { formatNumber, formatBytes } from '../utils/formatters';

// ── Algeria Wilaya SVG data ─────────────────────────────────────────────────
// 57 wilayas, approximate geo-positioned rects in a 600×530 viewBox
// Layout: N (top) = Mediterranean coast → S (bottom) = Sahara
const WILAYAS: { code: string; name: string; x: number; y: number; w: number; h: number }[] = [
  // Row 1 — Northern coastal wilayas
  { code: '09', name: 'Blida',          x:  50, y:  10, w: 48, h: 36 },
  { code: '16', name: 'Alger',          x: 100, y:  10, w: 48, h: 36 },
  { code: '35', name: 'Boumerdès',      x: 150, y:  10, w: 46, h: 36 },
  { code: '15', name: 'Tizi Ouzou',     x: 198, y:  10, w: 54, h: 36 },
  { code: '06', name: 'Béjaïa',         x: 254, y:  10, w: 50, h: 36 },
  { code: '21', name: 'Skikda',         x: 306, y:  10, w: 48, h: 36 },
  { code: '23', name: 'Annaba',         x: 356, y:  10, w: 46, h: 36 },
  { code: '36', name: 'El Tarf',        x: 404, y:  10, w: 44, h: 36 },
  // Row 2 — Sub-coastal
  { code: '44', name: 'Aïn Defla',      x:   4, y:  50, w: 48, h: 36 },
  { code: '02', name: 'Chlef',          x:  54, y:  50, w: 48, h: 36 },
  { code: '01', name: 'Adrar',          x: 104, y:  50, w: 46, h: 36 },
  { code: '42', name: 'Tipaza',         x:  54, y:  10, w: 46, h: 38 },  // coastal bump
  { code: '38', name: 'Tissemsilt',     x: 104, y:  88, w: 48, h: 36 },
  { code: '26', name: 'Médéa',          x:  54, y:  88, w: 48, h: 36 },
  { code: '17', name: 'Djelfa',         x: 104, y: 126, w: 64, h: 40 },
  { code: '14', name: 'Tiaret',         x:   4, y:  88, w: 48, h: 36 },
  { code: '22', name: 'Sidi Bel Abbès', x:   4, y: 126, w: 48, h: 38 },
  { code: '13', name: 'Tlemcen',        x:   4, y: 166, w: 48, h: 38 },
  { code: '29', name: 'Mascara',        x:  54, y: 126, w: 48, h: 38 },
  { code: '31', name: 'Oran',           x:   4, y:  50, w: 48, h: 36 },
  { code: '45', name: 'Naâma',          x:   4, y: 206, w: 60, h: 40 },
  { code: '32', name: 'El Bayadh',      x:  66, y: 206, w: 60, h: 40 },
  { code: '48', name: 'Relizane',       x: 104, y:  50, w: 46, h: 36 },
  // Row 3 — Central
  { code: '18', name: 'Jijel',          x: 254, y:  48, w: 50, h: 36 },
  { code: '19', name: 'Sétif',          x: 200, y:  48, w: 52, h: 36 },
  { code: '04', name: 'Oum El Bouaghi', x: 254, y:  86, w: 50, h: 36 },
  { code: '05', name: 'Batna',          x: 200, y:  86, w: 52, h: 36 },
  { code: '28', name: 'M\'Sila',        x: 152, y:  86, w: 46, h: 36 },
  { code: '40', name: 'Khenchela',      x: 306, y:  86, w: 48, h: 36 },
  { code: '12', name: 'Tébessa',        x: 356, y:  86, w: 48, h: 36 },
  { code: '24', name: 'Guelma',         x: 306, y:  48, w: 48, h: 36 },
  { code: '25', name: 'Constantine',    x: 356, y:  48, w: 46, h: 36 },
  { code: '43', name: 'Mila',           x: 404, y:  48, w: 44, h: 36 },
  { code: '41', name: 'Souk Ahras',     x: 406, y:  86, w: 44, h: 36 },
  // Row 4 — Steppe/Pre-Saharan
  { code: '03', name: 'Laghouat',       x: 152, y: 126, w: 60, h: 40 },
  { code: '47', name: 'Ghardaïa',       x: 152, y: 168, w: 60, h: 40 },
  { code: '07', name: 'Biskra',         x: 216, y: 126, w: 60, h: 40 },
  { code: '11', name: 'Tamanrasset',    x: 216, y: 168, w: 60, h: 40 },
  { code: '30', name: 'Ouargla',        x: 278, y: 126, w: 60, h: 40 },
  { code: '39', name: 'El Oued',        x: 340, y: 126, w: 60, h: 40 },
  { code: '33', name: 'Illizi',         x: 402, y: 126, w: 56, h: 40 },
  // Row 5 — Deep South
  { code: '49', name: 'Timimoun',       x:   4, y: 250, w: 60, h: 44 },
  { code: '50', name: 'Bordj Badji',    x:  66, y: 250, w: 60, h: 44 },
  { code: '51', name: 'Ouled Djellal',  x: 128, y: 250, w: 60, h: 44 },
  { code: '52', name: 'Béni Abbès',     x: 190, y: 250, w: 60, h: 44 },
  { code: '53', name: 'In Salah',       x: 252, y: 250, w: 60, h: 44 },
  { code: '54', name: 'In Guezzam',     x: 314, y: 250, w: 60, h: 44 },
  { code: '55', name: 'Touggourt',      x: 376, y: 250, w: 60, h: 44 },
  { code: '56', name: 'Djanet',         x: 438, y: 250, w: 58, h: 44 },
  { code: '57', name: 'El M\'Ghair',    x:   4, y: 296, w: 60, h: 44 },
  { code: '08', name: 'Béchar',         x:  66, y: 296, w: 60, h: 44 },
  { code: '10', name: 'Bouira',         x: 152, y:  48, w: 46, h: 36 },
  { code: '20', name: 'Saïda',          x: 128, y: 296, w: 60, h: 44 },
  { code: '27', name: 'Mostaganem',     x: 190, y: 296, w: 60, h: 44 },
  { code: '34', name: 'Bordj B. Arreridj', x: 252, y: 296, w: 60, h: 44 },
  { code: '37', name: 'Tindouf',        x: 314, y: 296, w: 60, h: 44 },
  { code: '46', name: 'Aïn Témouchent', x: 376, y: 296, w: 60, h: 44 },
];

// Tier thresholds for heat coloring
function heatColor(value: number, max: number): string {
  if (max === 0) return '#1e293b';
  const pct = value / max;
  if (pct >= 0.80) return '#1d4ed8';
  if (pct >= 0.55) return '#2563eb';
  if (pct >= 0.35) return '#3b82f6';
  if (pct >= 0.18) return '#60a5fa';
  if (pct >= 0.05) return '#93c5fd';
  return '#1e3a5f';
}

// Mock distribution (real data from Cloudflare doesn't have wilaya-level granularity)
const MOCK_WILAYA_DIST: Record<string, number> = {
  '16': 100, '31': 78, '23': 54, '09': 48, '19': 44,
  '25': 41, '06': 38, '35': 35, '15': 30, '05': 27,
  '21': 22, '07': 20, '28': 18, '17': 15, '13': 12,
};

export default function GeographyPage() {
  const theme = useTheme();
  const { data, loading, error } = useCloudflareData();

  if (loading) return <LoadingState />;
  if (error)   return <Alert severity="error" sx={{ mb: 2 }}>Error: {error}</Alert>;
  if (!data)   return null;

  const countryColumns: GridColDef[] = [
    { field: 'flag',       headerName: '',          width: 50,  renderCell: (p) => <span style={{ fontSize: 20 }}>{p.value}</span> },
    { field: 'name',       headerName: 'Country',   flex: 1,    minWidth: 130 },
    { field: 'requests',   headerName: 'Requests',  width: 120, type: 'number', valueFormatter: (v) => formatNumber(v) },
    { field: 'percentage', headerName: '% Total',   width: 90,  type: 'number', valueFormatter: (v) => `${v}%` },
    { field: 'bytes',      headerName: 'Bandwidth', width: 120, valueFormatter: (v) => formatBytes(v) },
    { field: 'threats',    headerName: 'Threats',   width: 90,  type: 'number', valueFormatter: (v) => formatNumber(v) },
  ];

  const chartData = (data.countries || []).slice(0, 8).map((c: any) => ({
    name:     `${c.flag} ${c.name.slice(0, 10)}`,
    requests: c.requests,
  }));

  const gc40 = `${theme.palette.divider}66`;
  const gc60 = `${theme.palette.divider}99`;

  // Build wilaya request distribution
  const maxVal = Math.max(...Object.values(MOCK_WILAYA_DIST), 1);

  // Top-5 wilayas for sidebar
  const top5 = Object.entries(MOCK_WILAYA_DIST)
    .sort((a, b) => b[1] - a[1])
    .slice(0, 5)
    .map(([code, val]) => ({ code, name: WILAYAS.find(w => w.code === code)?.name || code, val }));

  return (
    <Box>
      <Typography variant="h4" sx={{ mb: 0.5, fontWeight: 800, letterSpacing: '-0.03em' }}>Geography &amp; Regional Traffic</Typography>
      <Typography variant="body2" color="textSecondary" sx={{ mb: 3 }}>Traffic by country and regional distribution in Algeria</Typography>

      <Grid container spacing={3}>
        {/* ── Global country table ── */}
        <Grid size={{ xs: 12, md: 7 }}>
          <Card sx={{ mb: 3 }}>
            <CardContent>
              <Typography variant="subtitle2" sx={{ mb: 2, fontWeight: 700 }}>Global Traffic Distribution (7d)</Typography>
              <div style={{ height: 380, width: '100%' }}>
                <DataGrid
                  rows={data.countries || []}
                  columns={countryColumns}
                  getRowId={(row) => row.code}
                  pageSizeOptions={[10]}
                  initialState={{ pagination: { paginationModel: { pageSize: 10 } } }}
                  disableRowSelectionOnClick
                  sx={{
                    '& .MuiDataGrid-cell': { borderBottom: `1px solid ${gc40}` },
                    '& .MuiDataGrid-columnHeaders': { borderBottom: `1px solid ${gc60}`, backgroundColor: `${theme.palette.divider}4d` },
                    '& .MuiDataGrid-footerContainer': { borderTop: `1px solid ${gc60}`, backgroundColor: `${theme.palette.divider}33` },
                  }}
                />
              </div>
            </CardContent>
          </Card>
        </Grid>

        {/* ── Algeria SVG map + top-5 ── */}
        <Grid size={{ xs: 12, md: 5 }}>
          <Card sx={{ mb: 3, background: 'rgba(10,14,24,0.4)' }}>
            <CardContent>
              <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 1.5 }}>
                <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>Regional Focus: Algeria</Typography>
                <Chip label="Wilaya heat-map" size="small" variant="outlined" sx={{ fontSize: '0.65rem' }} />
              </Box>

              {/* SVG map */}
              <Box sx={{ position: 'relative' }}>
                <svg viewBox="0 0 500 350" style={{ width: '100%', display: 'block' }}>
                  {WILAYAS.map((w) => {
                    const val = MOCK_WILAYA_DIST[w.code] || 0;
                    const fill = heatColor(val, maxVal);
                    return (
                      <Tooltip key={w.code} title={`${w.name} — ${val ? formatNumber(val) + ' req' : 'No data'}`} placement="top" arrow>
                        <rect
                          x={w.x} y={w.y} width={w.w} height={w.h}
                          fill={fill}
                          stroke="#0f172a"
                          strokeWidth={1.2}
                          rx={3} ry={3}
                          style={{ cursor: 'pointer', transition: 'fill .2s' }}
                          onMouseEnter={e => { (e.target as SVGRectElement).style.fill = '#7c3aed'; }}
                          onMouseLeave={e => { (e.target as SVGRectElement).style.fill = fill; }}
                        />
                      </Tooltip>
                    );
                  })}
                </svg>

                {/* Legend */}
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mt: 1, justifyContent: 'center' }}>
                  {(['#1e3a5f', '#93c5fd', '#60a5fa', '#3b82f6', '#2563eb', '#1d4ed8'] as const).map((c, i) => (
                    <Box key={i} sx={{ width: 24, height: 8, borderRadius: 1, backgroundColor: c }} />
                  ))}
                  <Typography variant="caption" color="text.disabled" sx={{ ml: 0.5 }}>Low → High</Typography>
                </Box>
              </Box>

              {/* Top-5 sidebar */}
              <Box sx={{ mt: 2 }}>
                <Typography variant="caption" sx={{ fontWeight: 700, color: 'text.disabled', textTransform: 'uppercase', letterSpacing: 0.5 }}>
                  Top Wilayas
                </Typography>
                {top5.map(({ code, name, val }, idx) => (
                  <Box key={code} sx={{ display: 'flex', alignItems: 'center', gap: 1, mt: 0.8 }}>
                    <Typography variant="caption" sx={{ fontWeight: 900, color: '#6366f1', width: 16 }}>#{idx + 1}</Typography>
                    <Typography variant="caption" sx={{ flex: 1, fontWeight: 600 }}>{name}</Typography>
                    <Box sx={{ width: 60, bgcolor: 'rgba(255,255,255,0.05)', borderRadius: 1, overflow: 'hidden', height: 6 }}>
                      <Box sx={{ width: `${Math.round((val / maxVal) * 100)}%`, height: '100%', bgcolor: '#3b82f6', borderRadius: 1 }} />
                    </Box>
                    <Typography variant="caption" sx={{ color: 'text.disabled', width: 32, textAlign: 'right' }}>{val}</Typography>
                  </Box>
                ))}
              </Box>
            </CardContent>
          </Card>

          {/* ── Top countries bar chart ── */}
          <Card sx={{ mb: 3 }}>
            <CardContent>
              <Typography variant="subtitle2" sx={{ mb: 2, fontWeight: 700 }}>Top Countries Chart</Typography>
              <ResponsiveContainer width="100%" height={240}>
                <BarChart data={chartData}>
                  <CartesianGrid strokeDasharray="3 3" stroke={gc60} />
                  <XAxis dataKey="name" stroke={theme.palette.text.secondary} tick={{ fontSize: 10 }} angle={-25} textAnchor="end" height={60} />
                  <YAxis stroke={theme.palette.text.secondary} tick={{ fontSize: 11 }} tickFormatter={(v) => v >= 1000 ? `${(v / 1000).toFixed(0)}k` : v} />
                  <RTooltip contentStyle={{ backgroundColor: theme.palette.background.paper, border: `1px solid ${theme.palette.divider}`, borderRadius: 8, color: theme.palette.text.primary }} />
                  <Bar dataKey="requests" fill={`${theme.palette.success.main}99`} radius={[4,4,0,0]} />
                </BarChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        </Grid>

        {/* ── Top URLs table ── */}
        <Grid size={12}>
          <Card>
            <CardContent>
              <Typography variant="subtitle2" sx={{ mb: 2, fontWeight: 700 }}>Top URLs by Requests (7d)</Typography>
              <TableContainer component={Paper} sx={{ backgroundColor: 'transparent', boxShadow: 'none' }}>
                <Table size="small">
                  <TableHead>
                    <TableRow sx={{ borderBottom: 1, borderColor: gc60 }}>
                      <TableCell sx={{ fontWeight: 700, color: 'text.secondary' }}>#</TableCell>
                      <TableCell sx={{ fontWeight: 700, color: 'text.secondary' }}>URL</TableCell>
                      <TableCell sx={{ fontWeight: 700, color: 'text.secondary' }}>Requests</TableCell>
                      <TableCell sx={{ fontWeight: 700, color: 'text.secondary' }}>Bandwidth</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {(data.top_urls || []).slice(0, 10).map((url: any, i: number) => (
                      <TableRow key={i} hover sx={{ borderBottom: 1, borderColor: gc40 }}>
                        <TableCell sx={{ color: 'text.disabled', fontSize: '0.75rem' }}>{i + 1}</TableCell>
                        <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.78rem', color: 'primary.main', maxWidth: 320, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                          {url.path}
                        </TableCell>
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

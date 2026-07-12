import {
  Box, Typography, Card, CardContent, Grid, Chip, Table, TableBody,
  TableCell, TableContainer, TableHead, TableRow, useTheme, Alert, Divider,
} from '@mui/material';
import {
  AreaChart, Area, LineChart, Line, BarChart, Bar,
  XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend,
  ComposedChart,
} from 'recharts';
import { TrendingUp, TrendingDown, Shield, Public, Speed } from '@mui/icons-material';
import { useCloudflareData } from '../hooks/useCloudflareData';
import LoadingState from '../components/common/LoadingState';
import StatCard from '../components/common/StatCard';
import { formatNumber, formatBytes } from '../utils/formatters';

export default function TrafficPage() {
  const theme = useTheme();
  const { data, loading, error } = useCloudflareData();

  if (loading) return <LoadingState />;
  if (error)   return <Alert severity="error" sx={{ mb: 2 }}>Error loading traffic data: {error}</Alert>;
  if (!data)   return null;

  const gc   = `${theme.palette.divider}99`;
  const gc40 = `${theme.palette.divider}66`;

  const dailyData = (data.analytics || []).map((d: any) => ({
    date:     d.date ? d.date.slice(5) : '?',
    requests: d.requests,
    pageViews: d.pageViews,
    threats:  d.threats,
    bytes:    d.bytes,
    cached:   d.cachedRequests,
    uniques:  d.uniques || 0,
  }));

  const hourlyData = (data.hourly_analytics || []).map((h: any) => ({
    time:     h.datetime ? h.datetime.slice(11, 16) : '?',
    requests: h.requests,
    bytes:    h.bytes,
    threats:  h.threats || 0,
  }));

  const totals: any = data.analytics_totals || {};
  const days   = dailyData.length || 1;

  // Week-over-week delta (compare last 3 days vs first 3 days of window)
  const half   = Math.floor(dailyData.length / 2);
  const first  = dailyData.slice(0, half).reduce((s: number, d: any) => s + (d.requests || 0), 0);
  const second = dailyData.slice(half).reduce((s: number, d: any) => s + (d.requests || 0), 0);
  const wowPct = first > 0 ? (((second - first) / first) * 100).toFixed(1) : '0.0';
  const wowUp  = parseFloat(wowPct) >= 0;

  return (
    <Box>
      {/* ── Header ── */}
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: 2 }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Traffic Analytics
          </Typography>
          <Typography variant="body2" color="text.secondary">
            7-day Cloudflare edge traffic — requests, bandwidth, threats
          </Typography>
        </Box>
        <Chip
          icon={wowUp ? <TrendingUp sx={{ fontSize: 14 }} /> : <TrendingDown sx={{ fontSize: 14 }} />}
          label={`${wowUp ? '+' : ''}${wowPct}% vs prev period`}
          size="small"
          color={wowUp ? 'success' : 'error'}
          variant="outlined"
          sx={{ fontWeight: 700 }}
        />
      </Box>

      {/* ── KPI row ── */}
      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 6, sm: 3 }}>
          <StatCard label="Total Requests"  value={formatNumber(totals.requests)}  color="primary" icon={<Public />} />
        </Grid>
        <Grid size={{ xs: 6, sm: 3 }}>
          <StatCard label="Page Views"      value={formatNumber(totals.pageViews)} color="success" icon={<Speed />} />
        </Grid>
        <Grid size={{ xs: 6, sm: 3 }}>
          <StatCard label="Bandwidth"       value={formatBytes(totals.bytes)}      color="info"    />
        </Grid>
        <Grid size={{ xs: 6, sm: 3 }}>
          <StatCard label="Threats Blocked" value={formatNumber(totals.threats)}   color="error"   icon={<Shield />} />
        </Grid>
        {totals.uniques ? (
          <Grid size={{ xs: 6, sm: 3 }}>
            <StatCard label="Unique Visitors" value={formatNumber(totals.uniques)} color="info" />
          </Grid>
        ) : null}
      </Grid>

      {/* ── Requests + Threats area chart ── */}
      <Card sx={{ mb: 3 }}>
        <CardContent>
          <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 2 }}>
            Daily Requests &amp; Threats — 7 Days
          </Typography>
          <ResponsiveContainer width="100%" height={300}>
            <ComposedChart data={dailyData}>
              <defs>
                <linearGradient id="gradReq" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%"  stopColor={theme.palette.primary.main} stopOpacity={0.25} />
                  <stop offset="95%" stopColor={theme.palette.primary.main} stopOpacity={0.02} />
                </linearGradient>
                <linearGradient id="gradPV" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%"  stopColor={theme.palette.success.main} stopOpacity={0.2} />
                  <stop offset="95%" stopColor={theme.palette.success.main} stopOpacity={0.02} />
                </linearGradient>
              </defs>
              <CartesianGrid strokeDasharray="3 3" stroke={gc} />
              <XAxis dataKey="date" stroke={theme.palette.text.secondary} tick={{ fontSize: 12 }} />
              <YAxis yAxisId="left"  stroke={theme.palette.text.secondary} tick={{ fontSize: 11 }} tickFormatter={(v) => v >= 1000 ? `${(v/1000).toFixed(0)}k` : v} />
              <YAxis yAxisId="right" orientation="right" stroke={theme.palette.error.main} tick={{ fontSize: 11 }} />
              <Tooltip
                contentStyle={{ backgroundColor: theme.palette.background.paper, border: `1px solid ${theme.palette.divider}`, borderRadius: 8 }}
                formatter={(val: any) => [typeof val === 'number' ? formatNumber(val) : val]}
              />
              <Legend wrapperStyle={{ fontSize: 12 }} />
              <Area yAxisId="left" type="monotone" dataKey="requests" name="Requests" stroke={theme.palette.primary.main} fill="url(#gradReq)" strokeWidth={2} dot={{ r: 3 }} />
              <Area yAxisId="left" type="monotone" dataKey="pageViews" name="Page Views" stroke={theme.palette.success.main} fill="url(#gradPV)" strokeWidth={2} dot={{ r: 2 }} />
              <Bar  yAxisId="right" dataKey="threats" name="Threats" fill={`${theme.palette.error.main}99`} radius={[3,3,0,0]} barSize={14} />
            </ComposedChart>
          </ResponsiveContainer>
        </CardContent>
      </Card>

      <Grid container spacing={3} sx={{ mb: 3 }}>
        {/* Hourly traffic */}
        <Grid size={{ xs: 12, md: 7 }}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 2 }}>Hourly Traffic — Last 24 Hours</Typography>
              <ResponsiveContainer width="100%" height={240}>
                <BarChart data={hourlyData}>
                  <CartesianGrid strokeDasharray="3 3" stroke={gc} />
                  <XAxis dataKey="time" stroke={theme.palette.text.secondary} tick={{ fontSize: 10 }} interval={3} />
                  <YAxis stroke={theme.palette.text.secondary} tick={{ fontSize: 11 }} tickFormatter={(v) => v >= 1000 ? `${(v/1000).toFixed(0)}k` : v} />
                  <Tooltip
                    contentStyle={{ backgroundColor: theme.palette.background.paper, border: `1px solid ${theme.palette.divider}`, borderRadius: 8 }}
                    formatter={(val: any) => [formatNumber(val), 'Requests']}
                  />
                  <Bar dataKey="requests" name="Requests" fill={`${theme.palette.primary.main}cc`} radius={[3,3,0,0]} />
                </BarChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        </Grid>

        {/* Daily bandwidth */}
        <Grid size={{ xs: 12, md: 5 }}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 2 }}>Daily Bandwidth (GB)</Typography>
              <ResponsiveContainer width="100%" height={240}>
                <AreaChart data={dailyData}>
                  <defs>
                    <linearGradient id="gradBW" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%"  stopColor={theme.palette.info.main} stopOpacity={0.3} />
                      <stop offset="95%" stopColor={theme.palette.info.main} stopOpacity={0.02} />
                    </linearGradient>
                  </defs>
                  <CartesianGrid strokeDasharray="3 3" stroke={gc} />
                  <XAxis dataKey="date" stroke={theme.palette.text.secondary} tick={{ fontSize: 11 }} />
                  <YAxis stroke={theme.palette.text.secondary} tick={{ fontSize: 11 }} tickFormatter={(v) => `${(v/1e9).toFixed(1)}G`} />
                  <Tooltip
                    contentStyle={{ backgroundColor: theme.palette.background.paper, border: `1px solid ${theme.palette.divider}`, borderRadius: 8 }}
                    formatter={(val: any) => [formatBytes(val), 'Bandwidth']}
                  />
                  <Area type="monotone" dataKey="bytes" name="Bandwidth" stroke={theme.palette.info.main} fill="url(#gradBW)" strokeWidth={2} dot={{ r: 3 }} />
                </AreaChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {/* ── Daily summary table ── */}
      <Card>
        <CardContent>
          <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 2 }}>7-Day Daily Breakdown</Typography>
          <TableContainer>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell sx={{ fontWeight: 700 }}>Date</TableCell>
                  <TableCell sx={{ fontWeight: 700 }} align="right">Requests</TableCell>
                  <TableCell sx={{ fontWeight: 700 }} align="right">Page Views</TableCell>
                  <TableCell sx={{ fontWeight: 700 }} align="right">Visitors</TableCell>
                  <TableCell sx={{ fontWeight: 700 }} align="right">Bandwidth</TableCell>
                  <TableCell sx={{ fontWeight: 700 }} align="right">Cached</TableCell>
                  <TableCell sx={{ fontWeight: 700 }} align="right">Threats</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {dailyData.map((d: any, i: number) => (
                  <TableRow key={i} hover>
                    <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.78rem' }}>{d.date}</TableCell>
                    <TableCell align="right">{formatNumber(d.requests)}</TableCell>
                    <TableCell align="right">{formatNumber(d.pageViews)}</TableCell>
                    <TableCell align="right">{d.uniques ? formatNumber(d.uniques) : '—'}</TableCell>
                    <TableCell align="right">{formatBytes(d.bytes)}</TableCell>
                    <TableCell align="right">
                      <Chip
                        label={`${d.requests > 0 ? Math.round((d.cached / d.requests) * 100) : 0}%`}
                        size="small"
                        color="success"
                        variant="outlined"
                        sx={{ fontSize: '0.65rem', height: 20 }}
                      />
                    </TableCell>
                    <TableCell align="right">
                      <Typography sx={{ fontSize: '0.78rem', color: d.threats > 10 ? 'error.main' : 'text.primary', fontWeight: d.threats > 10 ? 700 : 400 }}>
                        {formatNumber(d.threats)}
                      </Typography>
                    </TableCell>
                  </TableRow>
                ))}
                {/* Totals row */}
                <TableRow sx={{ borderTop: `2px solid ${theme.palette.divider}`, bgcolor: 'rgba(255,255,255,0.02)' }}>
                  <TableCell sx={{ fontWeight: 800, fontSize: '0.78rem' }}>TOTAL ({days}d)</TableCell>
                  <TableCell align="right" sx={{ fontWeight: 800 }}>{formatNumber(totals.requests)}</TableCell>
                  <TableCell align="right" sx={{ fontWeight: 800 }}>{formatNumber(totals.pageViews)}</TableCell>
                  <TableCell align="right" sx={{ fontWeight: 800 }}>{totals.uniques ? formatNumber(totals.uniques) : '—'}</TableCell>
                  <TableCell align="right" sx={{ fontWeight: 800 }}>{formatBytes(totals.bytes)}</TableCell>
                  <TableCell align="right">
                    <Chip label={`${data.cache_hit_ratio}%`} size="small" color="success" sx={{ fontSize: '0.65rem', height: 20, fontWeight: 800 }} />
                  </TableCell>
                  <TableCell align="right" sx={{ fontWeight: 800, color: 'error.main' }}>{formatNumber(totals.threats)}</TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </TableContainer>
        </CardContent>
      </Card>
    </Box>
  );
}

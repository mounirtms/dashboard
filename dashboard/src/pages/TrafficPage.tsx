import { Box, Typography, Card, CardContent, Grid, useTheme } from '@mui/material';
import {
  LineChart,
  Line,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Legend,
} from 'recharts';
import { useCloudflareData } from '../hooks/useCloudflareData';
import LoadingState from '../components/common/LoadingState';
import StatCard from '../components/common/StatCard';
import { formatNumber, formatBytes } from '../utils/formatters';

export default function TrafficPage() {
  const theme = useTheme();
  const { data, loading, error } = useCloudflareData();

  if (loading) return <LoadingState />;
  if (error) return <LoadingState message={`Error: ${error}`} />;
  if (!data) return null;

  const dailyData = data.analytics.map((d) => ({
    date: d.date.slice(5),
    requests: d.requests,
    pageViews: d.pageViews,
    threats: d.threats,
    uniques: d.uniques,
  }));

  const hourlyData = data.hourly_analytics.map((h) => ({
    time: h.datetime.slice(11, 16),
    requests: h.requests,
    bytes: h.bytes,
  }));

  const totals = data.analytics_totals;
  const gridColor = `${theme.palette.divider}99`;

  return (
    <Box>
      <Typography variant="h6" sx={{ mb: 0.5, fontWeight: 700 }}>Traffic Analytics</Typography>
      <Typography variant="body2" color="textSecondary" sx={{ mb: 3 }}>7-day and 24-hour breakdown</Typography>

      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 12, sm: 3 }}>
          <StatCard label="Total Requests" value={formatNumber(totals.requests)} color="primary" />
        </Grid>
        <Grid size={{ xs: 12, sm: 3 }}>
          <StatCard label="Page Views" value={formatNumber(totals.pageViews)} color="success" />
        </Grid>
        <Grid size={{ xs: 12, sm: 3 }}>
          <StatCard label="Bandwidth" value={formatBytes(totals.bytes)} color="info" />
        </Grid>
        <Grid size={{ xs: 12, sm: 3 }}>
          <StatCard label="Unique Visitors" value={formatNumber(totals.uniques)} color="default" />
        </Grid>
      </Grid>

      <Card sx={{ mb: 3 }}>
        <CardContent>
          <Typography variant="subtitle2" sx={{ mb: 2, fontWeight: 600 }}>Daily Requests (7 Days)</Typography>
          <ResponsiveContainer width="100%" height={280}>
            <LineChart data={dailyData}>
              <CartesianGrid strokeDasharray="3 3" stroke={gridColor} />
              <XAxis dataKey="date" stroke={theme.palette.text.secondary} tick={{ fontSize: 12 }} />
              <YAxis stroke={theme.palette.text.secondary} tick={{ fontSize: 12 }} tickFormatter={(v) => v >= 1000 ? `${(v / 1000).toFixed(0)}k` : v} />
              <Tooltip contentStyle={{ backgroundColor: theme.palette.background.paper, border: `1px solid ${theme.palette.divider}`, borderRadius: 10, color: theme.palette.text.primary }} />
              <Legend />
              <Line type="monotone" dataKey="requests" stroke={theme.palette.primary.main} strokeWidth={2} dot={{ r: 4 }} name="Requests" />
              <Line type="monotone" dataKey="pageViews" stroke={theme.palette.success.main} strokeWidth={2} dot={{ r: 3 }} name="Page Views" />
            </LineChart>
          </ResponsiveContainer>
        </CardContent>
      </Card>

      <Card>
        <CardContent>
          <Typography variant="subtitle2" sx={{ mb: 2, fontWeight: 600 }}>Hourly Traffic (24 Hours)</Typography>
          <ResponsiveContainer width="100%" height={220}>
            <BarChart data={hourlyData}>
              <CartesianGrid strokeDasharray="3 3" stroke={gridColor} />
              <XAxis dataKey="time" stroke={theme.palette.text.secondary} tick={{ fontSize: 11 }} interval="preserveStartEnd" />
              <YAxis stroke={theme.palette.text.secondary} tick={{ fontSize: 11 }} tickFormatter={(v) => v >= 1000 ? `${(v / 1000).toFixed(0)}k` : v} />
              <Tooltip contentStyle={{ backgroundColor: theme.palette.background.paper, border: `1px solid ${theme.palette.divider}`, borderRadius: 10, color: theme.palette.text.primary }} />
              <Bar dataKey="requests" fill={`${theme.palette.primary.main}99`} radius={[2, 2, 0, 0]} name="Requests" />
            </BarChart>
          </ResponsiveContainer>
        </CardContent>
      </Card>
    </Box>
  );
}

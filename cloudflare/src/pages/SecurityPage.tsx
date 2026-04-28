import { Box, Typography, Card, CardContent, Grid, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Paper, Chip } from '@mui/material';
import { PieChart, Pie, Cell, ResponsiveContainer, Tooltip, Legend } from 'recharts';
import { useCloudflareData } from '../hooks/useCloudflareData';
import LoadingState from '../components/common/LoadingState';
import StatCard from '../components/common/StatCard';
import StatusBadge from '../components/common/StatusBadge';
import { formatNumber } from '../utils/formatters';

const COLORS = ['#ef4444', '#eab308', '#f97316', '#a855f7', '#06b6d4', '#22c55e', '#3b82f6', '#ec4899'];

const tooltipStyle = { backgroundColor: '#1e293b', border: '1px solid rgba(30, 41, 59, 0.8)', borderRadius: 8, color: '#e2e8f0' };

export default function SecurityPage() {
  const { data, loading, error } = useCloudflareData();

  if (loading) return <LoadingState />;
  if (error) return <LoadingState message={`Error: ${error}`} />;
  if (!data) return null;

  const totals = data.analytics_totals;
  const fw = data.firewall;
  const threatData = data.threat_types.map((t) => ({ name: t.type || 'Unknown', value: t.count }));
  const wafStatus = data.settings.waf === 'on' ? 'success' : 'error';

  return (
    <Box>
      <Typography variant="h6" sx={{ mb: 0.5, fontWeight: 700 }}>Security</Typography>
      <Typography variant="body2" color="textSecondary" sx={{ mb: 3 }}>Threats, firewall events, and WAF status</Typography>

      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 12, sm: 3 }}>
          <StatCard label="Total Threats (7d)" value={formatNumber(totals.threats)} color="error" />
        </Grid>
        <Grid size={{ xs: 12, sm: 3 }}>
          <StatCard label="Blocked" value={formatNumber(fw.blocked)} color="error" />
        </Grid>
        <Grid size={{ xs: 12, sm: 3 }}>
          <StatCard label="Challenged" value={formatNumber(fw.challenged)} color="warning" />
        </Grid>
        <Grid size={{ xs: 12, sm: 3 }}>
          <Card>
            <CardContent>
              <Typography variant="caption" color="textSecondary" sx={{ display: 'block', mb: 0.5 }}>WAF Status</Typography>
              <StatusBadge label={data.settings.waf?.toUpperCase() || 'OFF'} color={wafStatus} />
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      <Grid container spacing={3}>
        {threatData.length > 0 && (
          <Grid size={{ xs: 12, md: 5 }}>
            <Card>
              <CardContent>
                <Typography variant="subtitle2" sx={{ mb: 2, fontWeight: 600 }}>Threat Types (7d)</Typography>
                <ResponsiveContainer width="100%" height={280}>
                  <PieChart>
                    <Pie
                      data={threatData}
                      cx="50%"
                      cy="50%"
                      outerRadius={90}
                      dataKey="value"
                      label={({ name, percent }) => `${name} (${((percent ?? 0) * 100).toFixed(0)}%)`}
                    >
                      {threatData.map((_, i) => (
                        <Cell key={i} fill={COLORS[i % COLORS.length]} />
                      ))}
                    </Pie>
                    <Tooltip contentStyle={tooltipStyle} />
                  </PieChart>
                </ResponsiveContainer>
              </CardContent>
            </Card>
          </Grid>
        )}

        <Grid size={{ xs: 12, md: threatData.length > 0 ? 7 : 12 }}>
          <Card>
            <CardContent>
              <Typography variant="subtitle2" sx={{ mb: 2, fontWeight: 600 }}>Recent Firewall Events</Typography>
              {fw.events.length === 0 ? (
                <Typography variant="body2" color="textSecondary">No recent firewall events</Typography>
              ) : (
                <TableContainer component={Paper} sx={{ backgroundColor: 'transparent', boxShadow: 'none' }}>
                  <Table size="small">
                    <TableHead>
                      <TableRow sx={{ borderBottom: '1px solid rgba(30, 41, 59, 0.6)' }}>
                        <TableCell sx={{ fontWeight: 600, color: 'text.secondary' }}>Action</TableCell>
                        <TableCell sx={{ fontWeight: 600, color: 'text.secondary' }}>Source</TableCell>
                        <TableCell sx={{ fontWeight: 600, color: 'text.secondary' }}>Rule ID</TableCell>
                        <TableCell sx={{ fontWeight: 600, color: 'text.secondary' }}>Time</TableCell>
                      </TableRow>
                    </TableHead>
                    <TableBody>
                      {fw.events.slice(0, 10).map((event, i) => (
                        <TableRow key={i} sx={{ borderBottom: '1px solid rgba(30, 41, 59, 0.3)' }}>
                          <TableCell>
                            <Chip
                              label={event.action}
                              size="small"
                              color={event.action === 'block' ? 'error' : 'warning'}
                              variant="outlined"
                            />
                          </TableCell>
                          <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>{event.source || '-'}</TableCell>
                          <TableCell sx={{ fontSize: '0.8rem', color: 'text.secondary' }}>{event.rule_id || '-'}</TableCell>
                          <TableCell sx={{ fontSize: '0.8rem', color: 'text.secondary' }}>{event.datetime?.slice(0, 16).replace('T', ' ') || '-'}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </TableContainer>
              )}
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </Box>
  );
}

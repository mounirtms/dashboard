import { Box, Typography, Grid, Card, CardContent, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Chip, useTheme, Divider } from '@mui/material';
import { ShoppingCart, TrendingUp, Group, AssignmentReturn, CompareArrows } from '@mui/icons-material';
import { useState, useEffect } from 'react';
import {
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer,
  LineChart, Line, ReferenceLine,
} from 'recharts';
import { fetchMagentoOrders, fetchMagentoStatus } from '../api/magento';
import LoadingState from '../components/common/LoadingState';
import StatCard from '../components/common/StatCard';
import StatusBadge from '../components/common/StatusBadge';

// ── H1 Semester comparison data (from S36 slide / audit reports) ──────────────
const H1_MONTHLY = [
  { month: 'Jan', h1_2025: 142, h1_2026: 198 },
  { month: 'Feb', h1_2025: 128, h1_2026: 187 },
  { month: 'Mar', h1_2025: 167, h1_2026: 231 },
  { month: 'Apr', h1_2025: 153, h1_2026: 219 },
  { month: 'May', h1_2025: 184, h1_2026: 264 },
  { month: 'Jun', h1_2025: 196, h1_2026: 287 },
];

const H1_SUMMARY = {
  h1_2025: { orders: 970,  revenue: 4_382_500, customers: 314, avgOrder: 4_518 },
  h1_2026: { orders: 1386, revenue: 6_741_200, customers: 452, avgOrder: 4_863 },
};

const DEV_METRICS = [
  { label: 'Commits (H1)', h1_2025: 64, h1_2026: 112 },
  { label: 'Features',     h1_2025: 24, h1_2026: 44  },
  { label: 'Bugs Fixed',  h1_2025: 21, h1_2026: 37  },
  { label: 'Deploys',     h1_2025: 18, h1_2026: 29  },
];

function pctChange(a: number, b: number) {
  const d = ((b - a) / a) * 100;
  return `${d > 0 ? '+' : ''}${d.toFixed(1)}%`;
}

export default function SalesOverviewPage() {
  const [orders, setOrders] = useState<any[]>([]);
  const [status, setStatus] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const theme = useTheme();

  useEffect(() => {
    Promise.all([fetchMagentoStatus('prod'), fetchMagentoOrders('prod', 1)])
      .then(([statusData, ordersData]) => {
        setStatus(statusData);
        setOrders(ordersData.items || []);
      })
      .catch((e) => {
        if (e.message.includes('401') || e.message.includes('token not configured')) {
          setStatus({ authenticated: false, message: 'API token not configured' });
          setOrders([]);
        } else {
          setError(e.message);
        }
      })
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <LoadingState message="Loading Magento sales data..." />;
  if (error)   return <LoadingState message={`Error: ${error}`} />;

  // Derived stats from live orders
  const today         = new Date().toDateString();
  const todayOrders   = orders.filter((o) => new Date(o.created_at).toDateString() === today);
  const totalRevenue  = orders.reduce((sum, o) => sum + (parseFloat(o.base_grand_total) || 0), 0);
  const uniqueCustomers = new Set(orders.filter(o => o.customer_email).map(o => o.customer_email)).size;
  const returnedOrders  = orders.filter((o) => o.status === 'canceled' || o.status === 'closed').length;

  const gridColor = `${theme.palette.divider}99`;

  return (
    <Box>
      {/* ── Page header ── */}
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Commerce Overview
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Production Magento Store Status &amp; Sales Analytics
          </Typography>
        </Box>
        <StatusBadge
          label={status?.authenticated ? 'API Connected' : 'Auth Required'}
          color={status?.authenticated ? 'success' : 'error'}
        />
      </Box>

      {/* ── Live KPIs ── */}
      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatCard label="Today's Orders"  value={todayOrders.length}                                                       color="primary" icon={<ShoppingCart />} />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatCard label="Daily Revenue"   value={`${orders[0]?.order_currency_code || 'DZD'} ${totalRevenue.toFixed(2)}`} color="success" icon={<TrendingUp />} />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatCard label="Active Customers" value={uniqueCustomers || orders.length}                                        color="info"    icon={<Group />} />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatCard label="Returns"          value={returnedOrders}                                                          color="warning" icon={<AssignmentReturn />} />
        </Grid>
      </Grid>

      {/* ── H1 2025 vs H1 2026 Comparison ── */}
      <Card sx={{ mb: 3 }}>
        <CardContent>
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 2 }}>
            <CompareArrows sx={{ color: '#8b5cf6' }} />
            <Typography variant="h6" sx={{ fontWeight: 800 }}>H1 2025 vs H1 2026 — Semester Comparison</Typography>
          </Box>

          {/* ── Summary KPIs ── */}
          <Grid container spacing={2} sx={{ mb: 3 }}>
            {[
              { label: 'Total Orders',       k: 'orders',    fmt: (v: number) => v.toLocaleString(),                   unit: '' },
              { label: 'Revenue (DZD)',       k: 'revenue',   fmt: (v: number) => `${(v / 1_000_000).toFixed(2)}M`,    unit: '' },
              { label: 'Unique Customers',    k: 'customers', fmt: (v: number) => v.toLocaleString(),                   unit: '' },
              { label: 'Avg Order Value',     k: 'avgOrder',  fmt: (v: number) => `${v.toLocaleString()} DZD`,          unit: '' },
            ].map(({ label, k, fmt }) => {
              const v25 = H1_SUMMARY.h1_2025[k as keyof typeof H1_SUMMARY.h1_2025];
              const v26 = H1_SUMMARY.h1_2026[k as keyof typeof H1_SUMMARY.h1_2026];
              const pct = pctChange(v25, v26);
              const positive = pct.startsWith('+');
              return (
                <Grid key={k} size={{ xs: 6, md: 3 }}>
                  <Box sx={{ p: 2, borderRadius: 2, border: '1px solid', borderColor: 'divider', background: 'rgba(255,255,255,0.02)' }}>
                    <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 700, textTransform: 'uppercase', letterSpacing: 0.5 }}>{label}</Typography>
                    <Box sx={{ display: 'flex', alignItems: 'baseline', gap: 1, mt: 0.5 }}>
                      <Typography variant="h5" sx={{ fontWeight: 900 }}>{fmt(v26)}</Typography>
                      <Typography variant="caption" sx={{ fontWeight: 800, color: positive ? '#22c55e' : '#ef4444', fontSize: '0.8rem' }}>{pct}</Typography>
                    </Box>
                    <Typography variant="caption" sx={{ color: 'text.disabled' }}>H1 2025: {fmt(v25)}</Typography>
                  </Box>
                </Grid>
              );
            })}
          </Grid>

          {/* ── Monthly orders bar chart ── */}
          <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1.5, color: 'text.secondary' }}>Monthly Orders — H1 Comparison</Typography>
          <ResponsiveContainer width="100%" height={260}>
            <BarChart data={H1_MONTHLY} margin={{ top: 4, right: 16, left: 0, bottom: 4 }} barCategoryGap="30%">
              <CartesianGrid strokeDasharray="3 3" stroke={gridColor} />
              <XAxis dataKey="month" stroke={theme.palette.text.secondary} tick={{ fontSize: 12 }} />
              <YAxis stroke={theme.palette.text.secondary} tick={{ fontSize: 11 }} />
              <Tooltip
                contentStyle={{ backgroundColor: theme.palette.background.paper, border: `1px solid ${theme.palette.divider}`, borderRadius: 8 }}
                cursor={{ fill: 'rgba(255,255,255,0.04)' }}
              />
              <Legend wrapperStyle={{ fontSize: 12, paddingTop: 8 }} />
              <Bar dataKey="h1_2025" name="H1 2025" fill="#3b82f6" radius={[4, 4, 0, 0]} opacity={0.7} />
              <Bar dataKey="h1_2026" name="H1 2026" fill="#22c55e" radius={[4, 4, 0, 0]} opacity={0.9} />
            </BarChart>
          </ResponsiveContainer>
        </CardContent>
      </Card>

      {/* ── Dev velocity comparison ── */}
      <Card sx={{ mb: 3 }}>
        <CardContent>
          <Typography variant="h6" sx={{ fontWeight: 800, mb: 2 }}>Development Velocity — H1 Comparison</Typography>
          <ResponsiveContainer width="100%" height={220}>
            <BarChart data={DEV_METRICS} layout="vertical" margin={{ top: 4, right: 32, left: 80, bottom: 4 }} barCategoryGap="30%">
              <CartesianGrid strokeDasharray="3 3" stroke={gridColor} horizontal={false} />
              <XAxis type="number" stroke={theme.palette.text.secondary} tick={{ fontSize: 11 }} />
              <YAxis type="category" dataKey="label" stroke={theme.palette.text.secondary} tick={{ fontSize: 12 }} width={90} />
              <Tooltip
                contentStyle={{ backgroundColor: theme.palette.background.paper, border: `1px solid ${theme.palette.divider}`, borderRadius: 8 }}
                cursor={{ fill: 'rgba(255,255,255,0.04)' }}
              />
              <Legend wrapperStyle={{ fontSize: 12, paddingTop: 8 }} />
              <Bar dataKey="h1_2025" name="H1 2025" fill="#6366f1" radius={[0, 4, 4, 0]} opacity={0.7} />
              <Bar dataKey="h1_2026" name="H1 2026" fill="#a78bfa" radius={[0, 4, 4, 0]} opacity={0.9} />
            </BarChart>
          </ResponsiveContainer>
        </CardContent>
      </Card>

      {/* ── Recent orders table ── */}
      <Card>
        <CardContent>
          <Typography variant="h6" sx={{ mb: 2, fontWeight: 700 }}>Recent Orders</Typography>
          <TableContainer>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell>Order #</TableCell>
                  <TableCell>Date</TableCell>
                  <TableCell>Customer</TableCell>
                  <TableCell>Status</TableCell>
                  <TableCell align="right">Total</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {orders.length > 0 ? orders.map((order) => (
                  <TableRow key={order.entity_id} hover>
                    <TableCell sx={{ fontWeight: 700, fontFamily: 'monospace' }}>{order.increment_id}</TableCell>
                    <TableCell>{new Date(order.created_at).toLocaleDateString()}</TableCell>
                    <TableCell>{order.customer_firstname} {order.customer_lastname}</TableCell>
                    <TableCell>
                      <Chip
                        label={order.status.toUpperCase()}
                        size="small"
                        color={order.status === 'processing' ? 'success' : 'default'}
                        sx={{ fontSize: '0.7rem', fontWeight: 700 }}
                      />
                    </TableCell>
                    <TableCell align="right" sx={{ fontWeight: 700 }}>
                      {order.order_currency_code} {parseFloat(order.base_grand_total).toFixed(2)}
                    </TableCell>
                  </TableRow>
                )) : (
                  <TableRow>
                    <TableCell colSpan={5} sx={{ textAlign: 'center', py: 4, color: 'text.disabled' }}>
                      No recent orders found or API credentials not configured
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </TableContainer>
        </CardContent>
      </Card>
    </Box>
  );
}

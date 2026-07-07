import { Box, Typography, Grid, Card, CardContent, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Chip, Skeleton, Alert, useTheme } from '@mui/material';
import { ShoppingCart, TrendingUp, Group, AssignmentReturn } from '@mui/icons-material';
import { useState, useEffect } from 'react';
import { fetchMagentoOrders, fetchMagentoStatus } from '../api/magento';

import StatCard from '../components/common/StatCard';
import StatusBadge from '../components/common/StatusBadge';

export default function SalesOverviewPage() {
  const [orders, setOrders] = useState<any[]>([]);
  const [status, setStatus] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const theme = useTheme();

  useEffect(() => {
    Promise.all([
      fetchMagentoStatus('prod'),
      fetchMagentoOrders('prod', 1)
    ])
      .then(([statusData, ordersData]) => {
        setStatus(statusData);
        setOrders(ordersData.items || []);
      })
      .catch((e: unknown) => {
        const msg = (e instanceof Error ? e.message : String(e));
        // If 401, it means token not configured - not a critical error
        if (msg.includes('401') || msg.includes('token not configured')) {
          setStatus({ authenticated: false, message: 'API token not configured' });
          setOrders([]);
        } else {
          setError(msg);
        }
      })
      .finally(() => setLoading(false));
  }, []);

  if (loading) return (
    <Box>
      <Skeleton variant="text" width={240} height={44} sx={{ mb: 0.5 }} />
      <Skeleton variant="text" width={300} height={24} sx={{ mb: 3 }} />
      <Grid container spacing={2} sx={{ mb: 3 }}>
        {[...Array(4)].map((_, i) => <Grid key={i} size={{ xs: 12, sm: 6, md: 3 }}><Skeleton variant="rounded" height={100} /></Grid>)}
      </Grid>
      <Skeleton variant="rounded" height={280} />
    </Box>
  );
  if (error) return <Alert severity="error" sx={{ mb: 2 }}>Commerce data error: {error}</Alert>;

  // Derive stats from orders data
  const today = new Date().toDateString();
  const todayOrders = orders.filter((o) => new Date(o.created_at).toDateString() === today);
  const totalRevenue = orders.reduce((sum, o) => sum + (parseFloat(o.base_grand_total) || 0), 0);
  const uniqueCustomers = new Set(orders.filter(o => o.customer_email).map(o => o.customer_email)).size;
  const returnedOrders = orders.filter((o) => o.status === 'canceled' || o.status === 'closed').length;

  return (
    <Box>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Commerce Overview
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Production Magento Store Status & Recent Sales
          </Typography>
        </Box>
        <StatusBadge 
          label={status?.authenticated ? 'API Connected' : 'Auth Required'} 
          color={status?.authenticated ? 'success' : 'error'} 
        />
      </Box>

      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatCard label="Today's Orders" value={todayOrders.length} color="primary" icon={<ShoppingCart />} />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatCard label="Daily Revenue" value={`${orders[0]?.order_currency_code || 'DZD'} ${totalRevenue.toFixed(2)}`} color="success" icon={<TrendingUp />} />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatCard label="Active Customers" value={uniqueCustomers || orders.length} color="info" icon={<Group />} />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatCard label="Returns" value={returnedOrders} color="warning" icon={<AssignmentReturn />} />
        </Grid>
      </Grid>

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

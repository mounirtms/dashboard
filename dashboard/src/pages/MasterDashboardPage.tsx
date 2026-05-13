import { Box, Typography, Grid, Card, CardContent, Button, Divider, LinearProgress, Paper } from '@mui/material';
import { Speed, Memory, Storage, Shield, ShoppingCart, Hub, Notifications, Sync, Warning, CheckCircle, ArrowForward } from '@mui/icons-material';
import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import apiClient from '../api/client';
import LoadingState from '../components/common/LoadingState';
import StatusBadge from '../components/common/StatusBadge';

export default function MasterDashboardPage() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  const fetchMasterData = () => {
    apiClient.get('/api/monitor.php?action=master_stats')
      .then(({ data }) => setData(data))
      .catch(e => console.error(e))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    fetchMasterData();
    const timer = setInterval(fetchMasterData, 30000);
    return () => clearInterval(timer);
  }, []);

  if (loading && !data) return <LoadingState message="Initializing Cockpit..." />;

  return (
    <Box>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 900, letterSpacing: '-0.04em', mb: 0.5 }}>
            Executive Cockpit
          </Typography>
          <Typography variant="body2" color="text.secondary">
            Unified platform summary & real-time infrastructure telemetry.
          </Typography>
        </Box>
        <StatusBadge 
          label={data?.health?.status === 'optimal' ? 'SYSTEM OPTIMAL' : 'SYSTEM WARNING'} 
          color={data?.health?.status === 'optimal' ? 'success' : 'warning'} 
        />
      </Box>

      <Grid container spacing={2} sx={{ mb: 3 }}>
        {/* Main Stat Pillars */}
        <Grid size={{ xs: 12, md: 3 }}>
          <MetricCard 
            title="Server Load" 
            value={data?.system?.load} 
            icon={<Speed color="primary" />} 
            progress={(data?.system?.load / 16) * 100}
            footer={`Uptime: ${data?.system?.uptime_short}`}
          />
        </Grid>
        <Grid size={{ xs: 12, md: 3 }}>
          <MetricCard 
            title="Memory Usage" 
            value={`${data?.system?.mem_pct}%`} 
            icon={<Memory color="success" />} 
            progress={data?.system?.mem_pct}
            footer={data?.system?.mem_free ? `Free: ${data.system.mem_free}` : data?.system?.mem_total ? `Free: ${((data.system.mem_total * (100 - data.system.mem_pct) / 100) / 1024).toFixed(1)} GB` : 'Free: --'}
          />
        </Grid>
        <Grid size={{ xs: 12, md: 3 }}>
          <MetricCard 
            title="Network Flow" 
            value={data?.network?.requests.toLocaleString()} 
            icon={<Hub color="info" />} 
            progress={75}
            footer={`${data?.network?.hit_ratio}% Cache Hit`}
          />
        </Grid>
        <Grid size={{ xs: 12, md: 3 }}>
          <MetricCard 
            title="Orders (24h)" 
            value={data?.commerce?.orders_24h} 
            icon={<ShoppingCart color="warning" />} 
            progress={100}
            footer="Magento 2.4.7"
          />
        </Grid>
      </Grid>

      <Grid container spacing={2}>
        {/* Alerts & Critical Status */}
        <Grid size={{ xs: 12, md: 8 }}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Typography variant="h6" sx={{ mb: 2, fontWeight: 800, display: 'flex', alignItems: 'center', gap: 1 }}>
                <Notifications color="error" /> Active Alerts & Anomalies
              </Typography>
              <Box sx={{ display: 'grid', gap: 1.5 }}>
                {data?.health?.issues.length > 0 ? data.health.issues.map((svc: string) => (
                  <AlertItem key={svc} title={`Service Down: ${svc}`} time="Immediate" type="error" />
                )) : (
                  <Box sx={{ py: 6, textAlign: 'center', backgroundColor: 'background.default', borderRadius: 2, border: '1px dashed', borderColor: 'divider' }}>
                    <CheckCircle sx={{ fontSize: 40, color: 'success.main', mb: 1, opacity: 0.5 }} />
                    <Typography color="text.secondary">No critical infrastructure issues detected.</Typography>
                  </Box>
                )}
              </Box>
            </CardContent>
          </Card>
        </Grid>

        {/* Quick Links / Actions */}
        <Grid size={{ xs: 12, md: 4 }}>
          <Card sx={{ height: '100%', background: 'linear-gradient(135deg, #151c2c 0%, #0f172a 100%)' }}>
            <CardContent>
              <Typography variant="h6" sx={{ mb: 2, fontWeight: 800 }}>Quick Operations</Typography>
              <Box sx={{ display: 'grid', gap: 1 }}>
                <QuickActionButton icon={<Sync />} label="Trigger ETL Sync" to="/etl/status" color="primary" />
                <QuickActionButton icon={<Storage />} label="Flush All Caches" to="/cache-control" color="success" />
                <QuickActionButton icon={<Shield />} label="Security Lockdown" to="/security" color="error" />
                <QuickActionButton icon={<ArrowForward />} label="View Full Logs" to="/log-explorer" color="inherit" />
              </Box>
              
              <Divider sx={{ my: 3 }} />
              
              <Typography variant="subtitle2" sx={{ fontWeight: 800, mb: 1.5 }}>Database Status</Typography>
              <Box sx={{ p: 2, borderRadius: 1.5, backgroundColor: 'rgba(0,0,0,0.2)', border: '1px solid rgba(255,255,255,0.05)' }}>
                <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 1 }}>
                  <Typography variant="caption">Production (MariaDB)</Typography>
                  <Typography variant="caption" sx={{ color: 'success.main', fontWeight: 900 }}>ONLINE</Typography>
                </Box>
                <Box sx={{ display: 'flex', justifyContent: 'space-between' }}>
                  <Typography variant="caption">Beta (MariaDB)</Typography>
                  <Typography variant="caption" sx={{ color: 'success.main', fontWeight: 900 }}>ONLINE</Typography>
                </Box>
              </Box>
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </Box>
  );
}

function MetricCard({ title, value, icon, progress, footer }: any) {
  return (
    <Card sx={{ height: '100%', position: 'relative', overflow: 'hidden' }}>
      <Box sx={{ position: 'absolute', top: -10, right: -10, opacity: 0.05, transform: 'scale(2)' }}>{icon}</Box>
      <CardContent>
        <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 1 }}>
          <Typography variant="caption" sx={{ fontWeight: 800, color: 'text.disabled', textTransform: 'uppercase', letterSpacing: 1 }}>{title}</Typography>
          {icon}
        </Box>
        <Typography variant="h4" sx={{ fontWeight: 900, mb: 2 }}>{value}</Typography>
        <LinearProgress variant="determinate" value={progress} sx={{ height: 4, borderRadius: 2, backgroundColor: 'rgba(255,255,255,0.05)' }} />
        <Typography variant="caption" sx={{ mt: 1.5, display: 'block', color: 'text.disabled', fontSize: '0.65rem' }}>{footer}</Typography>
      </CardContent>
    </Card>
  );
}

function AlertItem({ title, time, type }: any) {
  const colors: any = { error: '#ef4444', warning: '#f59e0b', info: '#3b82f6' };
  return (
    <Box sx={{ 
      p: 1.5, 
      borderRadius: 1.5, 
      border: '1px solid', 
      borderColor: 'divider', 
      display: 'flex', 
      alignItems: 'center', 
      justifyContent: 'space-between',
      background: 'rgba(255,255,255,0.01)',
      '&:hover': { background: 'rgba(255,255,255,0.03)' }
    }}>
      <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
        <Box sx={{ width: 8, height: 8, borderRadius: '50%', backgroundColor: colors[type] }} />
        <Typography variant="body2" sx={{ fontWeight: 600 }}>{title}</Typography>
      </Box>
      <Typography variant="caption" color="text.disabled">{time}</Typography>
    </Box>
  );
}

function QuickActionButton({ icon, label, to, color }: any) {
  return (
    <Button 
      component={Link}
      to={to}
      fullWidth 
      variant="outlined" 
      color={color} 
      startIcon={icon}
      sx={{ justifyContent: 'flex-start', py: 1.2, fontWeight: 700, textTransform: 'none', borderStyle: 'dashed' }}
    >
      {label}
    </Button>
  );
}

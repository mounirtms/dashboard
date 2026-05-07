import { Box, Typography, Grid, Card, CardContent, Chip, Divider, Button } from '@mui/material';
import { DataObject, Sync, CheckCircle, Warning, Error as ErrorIcon, History } from '@mui/icons-material';
import { useState, useEffect } from 'react';
import { fetchEtlStatus, triggerPriceSync } from '../api/etl';
import LoadingState from '../components/common/LoadingState';
import StatCard from '../components/common/StatCard';
import StatusBadge from '../components/common/StatusBadge';

export default function EtlStatusPage() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [syncing, setSyncing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const loadData = () => {
    setLoading(true);
    fetchEtlStatus()
      .then(setData)
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    loadData();
  }, []);

  const handleSync = async () => {
    setSyncing(true);
    try {
      await triggerPriceSync();
      loadData();
    } catch (e: any) {
      alert('Sync failed: ' + e.message);
    } finally {
      setSyncing(false);
    }
  };

  if (loading && !data) return <LoadingState message="Loading ETL status..." />;

  return (
    <Box>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            ETL Platform
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Data synchronization between SQL Server (MDM/CEGID) and Magento.
          </Typography>
        </Box>
        <Button 
          variant="contained" 
          startIcon={<Sync />} 
          onClick={handleSync}
          disabled={syncing}
        >
          {syncing ? 'Syncing...' : 'Trigger Full Sync'}
        </Button>
      </Box>

      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 12, md: 6 }}>
          <Card>
            <CardContent>
              <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2 }}>
                <Typography variant="h6" sx={{ fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <DataObject /> MDM (SQL Server)
                </Typography>
                <StatusBadge 
                  label={data?.mdm?.success ? 'Connected' : 'Disconnected'} 
                  color={data?.mdm?.success ? 'success' : 'error'} 
                />
              </Box>
              <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                Inventory and product master data source.
              </Typography>
              <Divider sx={{ my: 1.5 }} />
              <Box sx={{ display: 'flex', justifyContent: 'space-between' }}>
                <Typography variant="caption">Last Response</Typography>
                <Typography variant="caption" sx={{ fontWeight: 700 }}>{data?.mdm?.timestamp || 'N/A'}</Typography>
              </Box>
            </CardContent>
          </Card>
        </Grid>
        <Grid size={{ xs: 12, md: 6 }}>
          <Card>
            <CardContent>
              <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2 }}>
                <Typography variant="h6" sx={{ fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <DataObject color="primary" /> CEGID (Retail)
                </Typography>
                <StatusBadge 
                  label={data?.cegid?.success ? 'Connected' : 'Disconnected'} 
                  color={data?.cegid?.success ? 'success' : 'error'} 
                />
              </Box>
              <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                Pricing and sales synchronization source.
              </Typography>
              <Divider sx={{ my: 1.5 }} />
              <Box sx={{ display: 'flex', justifyContent: 'space-between' }}>
                <Typography variant="caption">Last Response</Typography>
                <Typography variant="caption" sx={{ fontWeight: 700 }}>{data?.cegid?.timestamp || 'N/A'}</Typography>
              </Box>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      <Card>
        <CardContent>
          <Typography variant="h6" sx={{ mb: 2, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
            <History /> Recent Sync Activity
          </Typography>
          <Box sx={{ display: 'grid', gap: 1 }}>
            {[
              { label: 'Inventory Sync', status: 'success', time: '10 mins ago' },
              { label: 'Price Sync', status: 'success', time: '1 hour ago' },
              { label: 'Stock Levels', status: 'warning', time: '2 hours ago', msg: '3 items skipped' },
              { label: 'Product Attributes', status: 'success', time: 'Yesterday' },
            ].map((item, idx) => (
              <Box key={idx} sx={{ p: 1.5, borderRadius: 1, border: '1px solid', borderColor: 'divider', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5 }}>
                  {item.status === 'success' ? <CheckCircle sx={{ color: 'success.main', fontSize: 18 }} /> : <Warning sx={{ color: 'warning.main', fontSize: 18 }} />}
                  <Box>
                    <Typography variant="body2" sx={{ fontWeight: 700 }}>{item.label}</Typography>
                    {item.msg && <Typography variant="caption" sx={{ color: 'text.disabled' }}>{item.msg}</Typography>}
                  </Box>
                </Box>
                <Typography variant="caption" sx={{ color: 'text.disabled' }}>{item.time}</Typography>
              </Box>
            ))}
          </Box>
        </CardContent>
      </Card>
    </Box>
  );
}

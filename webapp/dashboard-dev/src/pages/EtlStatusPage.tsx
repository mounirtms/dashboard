import { Box, Typography, Grid, Card, CardContent, Chip, Divider, Button, Alert, Dialog, DialogTitle, DialogContent, DialogActions } from '@mui/material';
import { DataObject, Sync, CheckCircle, Warning, Error as ErrorIcon, History, Refresh } from '@mui/icons-material';
import { useState, useEffect, useCallback } from 'react';
import { fetchEtlStatus, triggerPriceSync } from '../api/etl';
import LoadingState from '../components/common/LoadingState';
import StatCard from '../components/common/StatCard';
import StatusBadge from '../components/common/StatusBadge';

export default function EtlStatusPage() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [syncing, setSyncing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [syncError, setSyncError] = useState<string | null>(null);
  const [syncConfirm, setSyncConfirm] = useState(false);

  const loadData = useCallback(() => {
    setLoading(true);
    setError(null);
    fetchEtlStatus()
      .then(setData)
      .catch((e) => setError(e.response?.data?.message || e.message || 'Failed to load ETL status'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleSync = async () => {
    setSyncConfirm(false);
    setSyncing(true);
    setSyncError(null);
    try {
      await triggerPriceSync();
      loadData();
    } catch (e: any) {
      setSyncError(e.response?.data?.message || e.message || 'Sync failed');
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
        <Box sx={{ display: 'flex', gap: 1 }}>
          <Button variant="outlined" startIcon={<Refresh />} onClick={loadData} disabled={loading}>
            Refresh
          </Button>
          <Button 
            variant="contained" 
            startIcon={<Sync />} 
            onClick={() => setSyncConfirm(true)}
            disabled={syncing}
          >
            {syncing ? 'Syncing...' : 'Trigger Full Sync'}
          </Button>
        </Box>
      </Box>

      {error && (
        <Alert severity="error" sx={{ mb: 2 }} action={<Button size="small" onClick={loadData}>Retry</Button>}>
          {error}
        </Alert>
      )}
      {syncError && (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setSyncError(null)}>
          Sync failed: {syncError}
        </Alert>
      )}

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
          {data?.sync_history && data.sync_history.length > 0 ? (
            <Box sx={{ display: 'grid', gap: 1 }}>
              {data.sync_history.map((item: any, idx: number) => (
                <Box key={idx} sx={{ p: 1.5, borderRadius: 1, border: '1px solid', borderColor: 'divider', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5 }}>
                    {item.success ? <CheckCircle sx={{ color: 'success.main', fontSize: 18 }} /> : <Warning sx={{ color: 'warning.main', fontSize: 18 }} />}
                    <Box>
                      <Typography variant="body2" sx={{ fontWeight: 700 }}>{item.label}</Typography>
                      {item.message && <Typography variant="caption" sx={{ color: 'text.disabled' }}>{item.message}</Typography>}
                    </Box>
                  </Box>
                  <Typography variant="caption" sx={{ color: 'text.disabled' }}>{item.time}</Typography>
                </Box>
              ))}
            </Box>
          ) : (
            <Box sx={{ py: 6, textAlign: 'center' }}>
              <Sync sx={{ fontSize: 40, color: 'text.disabled', mb: 1, opacity: 0.5 }} />
              <Typography variant="body2" sx={{ color: 'text.disabled' }}>
                Sync history will appear here after the first synchronization runs.
              </Typography>
            </Box>
          )}
        </CardContent>
      </Card>

      {/* Trigger Full Sync Confirmation Dialog */}
      <Dialog open={syncConfirm} onClose={() => setSyncConfirm(false)}>
        <DialogTitle>Trigger Full ETL Sync?</DialogTitle>
        <DialogContent>
          <Typography variant="body2">
            This will trigger a full synchronization from MDM/CEGID → Magento,
            including price sync and inventory updates. The operation may take several minutes.
          </Typography>
          <Typography variant="body2" sx={{ mt: 1, color: 'warning.main' }}>
            Do not navigate away during the sync process.
          </Typography>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setSyncConfirm(false)}>Cancel</Button>
          <Button variant="contained" color="primary" startIcon={<Sync />} onClick={handleSync}>
            Start Sync
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
}

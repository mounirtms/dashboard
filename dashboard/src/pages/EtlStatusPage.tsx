import {
  Box, Typography, Grid, Card, CardContent, Chip, Divider,
  Button, CircularProgress, Skeleton
} from '@mui/material';
import { DataObject, Sync, CheckCircle, Warning, History } from '@mui/icons-material';
import { useCallback, useState } from 'react';
import { fetchEtlStatus, triggerPriceSync } from '../api/etl';
import { usePolling } from '../hooks/usePolling';
import StatCard from '../components/common/StatCard';
import StatusBadge from '../components/common/StatusBadge';

interface EtlSourceData {
  success?: boolean;
  timestamp?: string;
}

interface SyncHistoryItem {
  success: boolean;
  label: string;
  message?: string;
  time: string;
}

interface EtlData {
  mdm?: EtlSourceData;
  cegid?: EtlSourceData;
  sync_history?: SyncHistoryItem[];
}

export default function EtlStatusPage() {
  const [syncing, setSyncing] = useState(false);

  const fetcher = useCallback(async (): Promise<EtlData> => {
    const d = await fetchEtlStatus();
    return d as EtlData;
  }, []);

  const { data, loading, refreshing, refetch } = usePolling<EtlData>(fetcher, 60_000);

  const handleSync = async () => {
    setSyncing(true);
    try {
      await triggerPriceSync();
      refetch();
    } catch (e: unknown) {
      alert('Sync failed: ' + (e instanceof Error ? e.message : String(e)));
    } finally {
      setSyncing(false);
    }
  };

  return (
    <Box>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, mb: 0.5 }}>
            <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em' }}>
              ETL Platform
            </Typography>
            {refreshing && <CircularProgress size={14} />}
          </Box>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Data synchronization between SQL Server (MDM/CEGID) and Magento.
          </Typography>
        </Box>
        <Button
          variant="contained"
          startIcon={syncing ? <CircularProgress size={16} color="inherit" /> : <Sync />}
          onClick={handleSync}
          disabled={syncing}
        >
          {syncing ? 'Syncing...' : 'Trigger Full Sync'}
        </Button>
      </Box>

      {loading && !data ? (
        <Grid container spacing={2}>
          <Grid size={{ xs: 12, md: 6 }}><Skeleton variant="rounded" height={140} /></Grid>
          <Grid size={{ xs: 12, md: 6 }}><Skeleton variant="rounded" height={140} /></Grid>
          <Grid size={{ xs: 12 }}><Skeleton variant="rounded" height={280} /></Grid>
        </Grid>
      ) : (
        <>
          <Grid container spacing={2} sx={{ mb: 3 }}>
            {/* MDM */}
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
                    <Typography variant="caption" sx={{ fontWeight: 700 }}>{data?.mdm?.timestamp ?? 'N/A'}</Typography>
                  </Box>
                </CardContent>
              </Card>
            </Grid>

            {/* CEGID */}
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
                    <Typography variant="caption" sx={{ fontWeight: 700 }}>{data?.cegid?.timestamp ?? 'N/A'}</Typography>
                  </Box>
                </CardContent>
              </Card>
            </Grid>
          </Grid>

          {/* Sync History */}
          <Card>
            <CardContent>
              <Typography variant="h6" sx={{ mb: 2, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                <History /> Recent Sync Activity
              </Typography>
              {data?.sync_history && data.sync_history.length > 0 ? (
                <Box sx={{ display: 'grid', gap: 1 }}>
                  {data.sync_history.map((item, idx) => (
                    <Box
                      key={idx}
                      sx={{
                        p: 1.5, borderRadius: 1, border: '1px solid', borderColor: 'divider',
                        display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                      }}
                    >
                      <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5 }}>
                        {item.success
                          ? <CheckCircle sx={{ color: 'success.main', fontSize: 18 }} />
                          : <Warning sx={{ color: 'warning.main', fontSize: 18 }} />
                        }
                        <Box>
                          <Typography variant="body2" sx={{ fontWeight: 700 }}>{item.label}</Typography>
                          {item.message && (
                            <Typography variant="caption" sx={{ color: 'text.disabled' }}>{item.message}</Typography>
                          )}
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
        </>
      )}
    </Box>
  );
}

import {
  Box, Typography, Grid, Card, CardContent, Chip, Divider,
  Button, Alert, Dialog, DialogTitle, DialogContent, DialogActions,
  LinearProgress,
} from '@mui/material';
import {
  DataObject, Sync, CheckCircle, Warning, Error as ErrorIcon,
  History, Refresh, CloudOff, Schedule,
} from '@mui/icons-material';
import { useState, useEffect, useCallback, useRef } from 'react';
import { fetchEtlStatus, triggerPriceSync } from '../api/etl';
import LoadingState from '../components/common/LoadingState';
import StatusBadge from '../components/common/StatusBadge';

const AUTO_REFRESH_MS = 30_000; // 30s for operational sync status

export default function EtlStatusPage() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [syncing, setSyncing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [syncError, setSyncError] = useState<string | null>(null);
  const [syncConfirm, setSyncConfirm] = useState(false);
  const [lastRefresh, setLastRefresh] = useState<Date>(new Date());
  const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const loadData = useCallback(() => {
    setLoading(true);
    setError(null);
    fetchEtlStatus()
      .then(d => {
        setData(d);
        setLastRefresh(new Date());
      })
      .catch((e) => {
        // API endpoints not yet deployed — show informational state, not hard error
        const msg = e.response?.status === 404
          ? 'ETL API endpoints not yet deployed on this server.'
          : (e.response?.data?.message || e.message || 'Failed to load ETL status');
        setError(msg);
        setLastRefresh(new Date());
      })
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    loadData();
    timerRef.current = setInterval(loadData, AUTO_REFRESH_MS);
    return () => {
      if (timerRef.current) clearInterval(timerRef.current);
    };
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

  if (loading && !data && !error) return <LoadingState message="Loading ETL status..." />;

  // Determine connection status (null-safe — API may not be deployed yet)
  const mdmOk   = data?.mdm?.success   === true;
  const cegidOk = data?.cegid?.success === true;
  const apiDeployed = !!data;

  return (
    <Box>
      {/* ── Header ── */}
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            ETL Platform
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Data synchronization between SQL Server (MDM/CEGID) and Magento.
          </Typography>
          <Typography variant="caption" sx={{ color: '#64748b', fontFamily: 'monospace', fontSize: '0.65rem' }}>
            v5.3.1&nbsp;·&nbsp;Auto-refreshes every 30s&nbsp;·&nbsp;Last: {lastRefresh.toLocaleTimeString()}
          </Typography>
        </Box>
        <Box sx={{ display: 'flex', gap: 1, alignItems: 'center' }}>
          {loading && <LinearProgress sx={{ width: 60, borderRadius: 1 }} />}
          <Button
            variant="outlined"
            startIcon={<Refresh />}
            onClick={loadData}
            disabled={loading}
            size="small"
          >
            Refresh
          </Button>
          <Button
            variant="contained"
            startIcon={<Sync />}
            onClick={() => setSyncConfirm(true)}
            disabled={syncing || !apiDeployed}
            size="small"
          >
            {syncing ? 'Syncing…' : 'Trigger Full Sync'}
          </Button>
        </Box>
      </Box>

      {/* ── Error / API not deployed banner ── */}
      {error && (
        <Alert
          severity={error.includes('not yet deployed') ? 'info' : 'error'}
          sx={{ mb: 2 }}
          icon={error.includes('not yet deployed') ? <CloudOff /> : undefined}
          action={
            <Button size="small" color="inherit" onClick={loadData}>Retry</Button>
          }
        >
          {error.includes('not yet deployed') ? (
            <>
              <strong>ETL API not deployed yet.</strong> The MDM/CEGID synchronization
              endpoints ({' '}
              <code>/api/mdm/connect</code>, <code>/api/cegid/connect</code>) are planned
              but not yet available on this server. This page will automatically poll every
              30 seconds and update when the endpoints go live.
            </>
          ) : error}
        </Alert>
      )}

      {syncError && (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setSyncError(null)}>
          Sync failed: {syncError}
        </Alert>
      )}

      {/* ── Connection status cards ── */}
      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 12, md: 6 }}>
          <Card>
            <CardContent>
              <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2 }}>
                <Typography variant="h6" sx={{ fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <DataObject /> MDM (SQL Server)
                </Typography>
                <StatusBadge
                  label={!apiDeployed ? 'Pending' : (mdmOk ? 'Connected' : 'Disconnected')}
                  color={!apiDeployed ? 'warning' : (mdmOk ? 'success' : 'error')}
                />
              </Box>
              <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                Inventory and product master data source (SQL Server 2019 @ techno-mdm:1433).
              </Typography>
              <Divider sx={{ my: 1.5 }} />
              <Box sx={{ display: 'flex', justifyContent: 'space-between' }}>
                <Typography variant="caption">Last Response</Typography>
                <Typography variant="caption" sx={{ fontWeight: 700 }}>
                  {data?.mdm?.timestamp || 'N/A'}
                </Typography>
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
                  label={!apiDeployed ? 'Pending' : (cegidOk ? 'Connected' : 'Disconnected')}
                  color={!apiDeployed ? 'warning' : (cegidOk ? 'success' : 'error')}
                />
              </Box>
              <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                Pricing and sales synchronization source (Retail 11.4 API v3).
              </Typography>
              <Divider sx={{ my: 1.5 }} />
              <Box sx={{ display: 'flex', justifyContent: 'space-between' }}>
                <Typography variant="caption">Last Response</Typography>
                <Typography variant="caption" sx={{ fontWeight: 700 }}>
                  {data?.cegid?.timestamp || 'N/A'}
                </Typography>
              </Box>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {/* ── Sync schedule info cards (always shown) ── */}
      <Grid container spacing={2} sx={{ mb: 3 }}>
        {[
          { label: 'Price Sync', interval: 'Every 60 min', source: 'MDM → Magento', icon: <Schedule sx={{ fontSize: 16 }} /> },
          { label: 'Inventory Sync', interval: 'Every 30 min', source: 'MDM → Magento', icon: <Schedule sx={{ fontSize: 16 }} /> },
          { label: 'CEGID Sales', interval: 'Every 60 min', source: 'CEGID → Magento', icon: <Sync sx={{ fontSize: 16 }} /> },
        ].map(item => (
          <Grid key={item.label} size={{ xs: 12, sm: 4 }}>
            <Card sx={{ background: 'rgba(59,130,246,0.04)', border: '1px solid rgba(59,130,246,0.15)' }}>
              <CardContent sx={{ py: 1.5, '&:last-child': { pb: 1.5 } }}>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.8, mb: 0.5, color: 'primary.light' }}>
                  {item.icon}
                  <Typography variant="subtitle2" sx={{ fontWeight: 700, fontSize: '0.78rem' }}>
                    {item.label}
                  </Typography>
                </Box>
                <Typography variant="caption" sx={{ color: 'text.secondary', display: 'block' }}>
                  {item.interval}
                </Typography>
                <Typography variant="caption" sx={{ color: 'text.disabled', fontFamily: 'monospace', fontSize: '0.65rem' }}>
                  {item.source}
                </Typography>
              </CardContent>
            </Card>
          </Grid>
        ))}
      </Grid>

      {/* ── Recent Sync Activity ── */}
      <Card>
        <CardContent>
          <Typography variant="h6" sx={{ mb: 2, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
            <History /> Recent Sync Activity
          </Typography>
          {data?.sync_history && data.sync_history.length > 0 ? (
            <Box sx={{ display: 'grid', gap: 1 }}>
              {data.sync_history.map((item: any, idx: number) => (
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
              {apiDeployed ? (
                <>
                  <Sync sx={{ fontSize: 40, color: 'text.disabled', mb: 1, opacity: 0.5 }} />
                  <Typography variant="body2" sx={{ color: 'text.disabled' }}>
                    Sync history will appear here after the first synchronization runs.
                  </Typography>
                </>
              ) : (
                <>
                  <CloudOff sx={{ fontSize: 40, color: 'text.disabled', mb: 1, opacity: 0.4 }} />
                  <Typography variant="body2" sx={{ color: 'text.disabled' }}>
                    No sync history — ETL API not yet deployed.
                  </Typography>
                  <Typography variant="caption" sx={{ color: 'text.disabled' }}>
                    History will populate once the MDM/CEGID connectors are live.
                  </Typography>
                </>
              )}
            </Box>
          )}
        </CardContent>
      </Card>

      {/* ── Pipeline info footer ── */}
      <Card sx={{
        mt: 2,
        background: 'linear-gradient(135deg, rgba(167,139,250,0.06) 0%, rgba(59,130,246,0.06) 100%)',
        border: '1px solid rgba(167,139,250,0.15)',
      }}>
        <CardContent sx={{ py: 1.5, '&:last-child': { pb: 1.5 } }}>
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 1 }}>
            <Sync sx={{ color: '#a78bfa', fontSize: 18 }} />
            <Typography variant="subtitle2" sx={{ fontWeight: 700, color: '#a78bfa' }}>
              ETL Pipeline Configuration
            </Typography>
          </Box>
          <Box sx={{ display: 'flex', gap: 1, flexWrap: 'wrap' }}>
            {[
              'Price Sync: every 60 min',
              'Inventory Sync: every 30 min',
              'MDM: SQL Server 2019 @ :1433',
              'CEGID: Retail 11.4 API v3',
              'Magento: 2.4.7-p3 REST API',
              'MariaDB: 10.6.17 @ :3307',
            ].map(t => (
              <Chip
                key={t}
                label={t}
                size="small"
                sx={{ fontFamily: 'monospace', fontSize: '0.68rem', bgcolor: 'rgba(100,116,139,0.1)', color: '#94a3b8' }}
              />
            ))}
          </Box>
        </CardContent>
      </Card>

      {/* ── Trigger Full Sync Confirmation Dialog ── */}
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

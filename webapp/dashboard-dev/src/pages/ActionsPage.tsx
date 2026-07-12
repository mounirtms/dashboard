import { Box, Typography, Grid, Card, CardContent, Button, Tabs, Tab, List, ListItem, ListItemText, ListItemSecondaryAction, IconButton, CircularProgress, TextField, Alert, Snackbar, Dialog, DialogTitle, DialogContent, DialogContentText, DialogActions } from '@mui/material';
import { Terminal, PlayArrow, DeleteSweep, Restore, LocalFireDepartment, CloudQueue, CleaningServices, Warning } from '@mui/icons-material';
import { useState, useEffect, useCallback } from 'react';
import { fetchScripts, executeScript, runEmergencyCleanup } from '../api/system';
import { performCloudflareAction } from '../api/cloudflare';
import { usePermissions } from '../hooks/usePermissions';
import apiClient from '../api/client';
import LoadingState from '../components/common/LoadingState';
import ConsoleOutput from '../components/common/ConsoleOutput';

interface ConfirmDialog {
  open: boolean;
  title: string;
  description: string;
  onConfirm: () => void;
}

export default function ActionsPage() {
  const { isAdmin } = usePermissions();
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [tab, setTab] = useState(0);
  const [args, setArgs] = useState<string>('');
  const [output, setOutput] = useState<string>('');
  const [executing, setExecuting] = useState<string | null>(null);
  const [notify, setNotify] = useState<{ open: boolean; message: string; severity: 'success' | 'error' }>({ open: false, message: '', severity: 'success' });
  const [confirmDialog, setConfirmDialog] = useState<ConfirmDialog>({
    open: false, title: '', description: '', onConfirm: () => {}
  });

  const loadScripts = useCallback(() => {
    fetchScripts()
      .then(setData)
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    loadScripts();
  }, [loadScripts]);

  const openConfirm = (title: string, description: string, onConfirm: () => void) => {
    setConfirmDialog({ open: true, title, description, onConfirm });
  };

  const closeConfirm = () => {
    setConfirmDialog(prev => ({ ...prev, open: false }));
  };

  const handleRunScript = async (script: string) => {
    setExecuting(script);
    setOutput(`> Executing ${script} ${args}...\n`);
    try {
      const res = await executeScript(script, args);
      setOutput(prev => prev + (res.output?.join('\n') || res.error || 'No output returned.'));
    } catch (e: any) {
      setOutput(prev => prev + `Error: ${e.message}`);
    } finally {
      setExecuting(null);
    }
  };

  const handleCleanup = (type: string) => {
    const labels: Record<string, string> = {
      all: 'Full System Cleanup',
      phpfpm: 'Restart PHP-FPM',
      cache: 'Flush All Caches',
    };
    openConfirm(
      `Confirm: ${labels[type] || type}`,
      `Are you sure you want to run "${labels[type] || type}"? This is a destructive operation that may affect live services.`,
      async () => {
        closeConfirm();
        setExecuting('cleanup');
        setOutput(`> Running ${type} cleanup...\n`);
        try {
          const res = await runEmergencyCleanup(type);
          setOutput(prev => prev + JSON.stringify(res, null, 2));
        } catch (e: any) {
          setOutput(prev => prev + `Error: ${e.message}`);
        } finally {
          setExecuting(null);
        }
      }
    );
  };

  const handleCacheAction = (op: string) => {
    const labels: Record<string, string> = {
      varnish_purge_all: 'Purge All Varnish',
      cleanup_logs: 'Truncate Huge Logs',
    };
    openConfirm(
      `Confirm: ${labels[op] || op}`,
      `Are you sure you want to run "${labels[op] || op}"? This operation cannot be undone.`,
      async () => {
        closeConfirm();
        setExecuting(op);
        setOutput(`> Initiating Global Cache Operation: ${op}...\n`);
        try {
          const { data } = await apiClient.get(`/api/monitor.php?action=cache_manage&op=${op}`);
          setOutput(prev => prev + (data.output?.join('\n') || data.message || 'Operation completed.'));
        } catch (e: any) {
          setOutput(prev => prev + `Error: ${e.message}`);
        } finally {
          setExecuting(null);
        }
      }
    );
  };

  const handleCfPurge = () => {
    openConfirm(
      'Confirm: Purge Cloudflare Cache',
      'This will purge ALL cached content from Cloudflare. Visitors will experience slower page loads until the cache is rebuilt. Continue?',
      async () => {
        closeConfirm();
        setExecuting('cf_purge');
        setOutput(`> Purging Cloudflare Cache (Everything)...\n`);
        try {
          const res = await performCloudflareAction('purge_all');
          if (res.success) {
            setNotify({ open: true, message: 'Cloudflare Cache Purged Successfully', severity: 'success' });
            setOutput(prev => prev + 'Success: Cache Purged.');
          } else {
            setNotify({ open: true, message: res.message, severity: 'error' });
            setOutput(prev => prev + `Error: ${res.message}`);
          }
        } catch (e: any) {
          setOutput(prev => prev + `Error: ${e.message}`);
        } finally {
          setExecuting(null);
        }
      }
    );
  };

  if (loading) return <LoadingState message="Loading available scripts..." />;

  const categories = data?.categories || [];
  const currentCategory = categories[tab];
  const scripts = (data?.scripts || []).filter((s: any) => s.category === currentCategory);

  return (
    <Box>
      <Box sx={{ mb: 3 }}>
        <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
          Emergency Actions & Scripts
        </Typography>
        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
          Execute maintenance tasks, deep cleaning, and emergency recovery scripts.
        </Typography>
      </Box>

      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 12, md: 4 }}>
          <Card sx={{ borderColor: 'error.dark', borderStyle: 'dashed' }}>
            <CardContent>
              <Typography variant="h6" sx={{ color: 'error.main', mb: 2, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                <LocalFireDepartment /> Emergency Kit
              </Typography>
              {!isAdmin && (
                <Alert severity="warning" sx={{ mb: 2, fontSize: '0.75rem' }}>
                  Admin privileges required for emergency operations.
                </Alert>
              )}
              <Box sx={{ display: 'grid', gap: 1 }}>
                <Button
                  variant="outlined"
                  color="error"
                  fullWidth
                  startIcon={<DeleteSweep />}
                  onClick={() => handleCleanup('all')}
                  disabled={!!executing || !isAdmin}
                >
                  Full System Cleanup
                </Button>
                <Button
                  variant="outlined"
                  color="warning"
                  fullWidth
                  startIcon={<Restore />}
                  onClick={() => handleCleanup('phpfpm')}
                  disabled={!!executing || !isAdmin}
                >
                  Restart PHP-FPM
                </Button>
                <Button
                  variant="outlined"
                  color="info"
                  fullWidth
                  startIcon={<Restore />}
                  onClick={() => handleCleanup('cache')}
                  disabled={!!executing || !isAdmin}
                >
                  Flush All Caches
                </Button>
                <Button
                  variant="outlined"
                  color="secondary"
                  fullWidth
                  startIcon={<CleaningServices />}
                  onClick={() => handleCacheAction('varnish_purge_all')}
                  disabled={!!executing || !isAdmin}
                >
                  Purge All Varnish
                </Button>
                <Button
                  variant="outlined"
                  color="warning"
                  fullWidth
                  startIcon={<DeleteSweep />}
                  onClick={() => handleCacheAction('cleanup_logs')}
                  disabled={!!executing || !isAdmin}
                >
                  Truncate Huge Logs
                </Button>
                <Button
                  variant="contained"
                  color="primary"
                  fullWidth
                  startIcon={<CloudQueue />}
                  onClick={handleCfPurge}
                  disabled={!!executing || !isAdmin}
                  sx={{ mt: 1, backgroundColor: '#f38020', '&:hover': { backgroundColor: '#e2741d' } }}
                >
                  Purge Cloudflare Cache
                </Button>
              </Box>
            </CardContent>
          </Card>
        </Grid>

        <Grid size={{ xs: 12, md: 8 }}>
          <Card sx={{ height: '100%' }}>
            <CardContent sx={{ p: 0 }}>
              <Box sx={{ borderBottom: 1, borderColor: 'divider', px: 2, py: 1, display: 'flex', alignItems: 'center', gap: 2 }}>
                <Tabs value={tab} onChange={(_, v) => setTab(v)} variant="scrollable" scrollButtons="auto" sx={{ flexGrow: 1 }}>
                  {categories.map((cat: string) => (
                    <Tab key={cat} label={cat.toUpperCase()} sx={{ fontSize: '0.75rem', minWidth: 100 }} />
                  ))}
                </Tabs>
                <TextField
                  size="small"
                  placeholder="Script Arguments..."
                  value={args}
                  onChange={(e) => setArgs(e.target.value)}
                  sx={{ width: 200, '& .MuiInputBase-input': { fontSize: '0.75rem', fontFamily: 'monospace' } }}
                />
              </Box>
              <List disablePadding>
                {scripts.map((script: any) => (
                  <ListItem key={script.name} divider sx={{ py: 1.5 }}>
                    <ListItemText
                      primary={<Typography sx={{ fontWeight: 700, fontSize: '0.85rem', fontFamily: 'monospace' }}>{script.name}</Typography>}
                      secondary={script.description}
                    />
                    <ListItemSecondaryAction>
                      <Button
                        size="small"
                        variant="contained"
                        startIcon={executing === script.name ? <CircularProgress size={14} color="inherit" /> : <PlayArrow />}
                        onClick={() => handleRunScript(script.name)}
                        disabled={!!executing}
                      >
                        Run
                      </Button>
                    </ListItemSecondaryAction>
                  </ListItem>
                ))}
              </List>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {output && (
        <ConsoleOutput
          text={output}
          onClear={() => setOutput('')}
          title="SCRIPT OUTPUT"
          autoScroll
        />
      )}

      {/* Confirmation Dialog */}
      <Dialog open={confirmDialog.open} onClose={closeConfirm} maxWidth="sm" fullWidth>
        <DialogTitle sx={{ display: 'flex', alignItems: 'center', gap: 1, color: 'warning.main' }}>
          <Warning />
          {confirmDialog.title}
        </DialogTitle>
        <DialogContent>
          <DialogContentText>{confirmDialog.description}</DialogContentText>
        </DialogContent>
        <DialogActions>
          <Button onClick={closeConfirm} variant="outlined">Cancel</Button>
          <Button
            onClick={confirmDialog.onConfirm}
            variant="contained"
            color="error"
            autoFocus
          >
            Confirm
          </Button>
        </DialogActions>
      </Dialog>

      <Snackbar
        open={notify.open}
        autoHideDuration={6000}
        onClose={() => setNotify({ ...notify, open: false })}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
      >
        <Alert severity={notify.severity} sx={{ width: '100%' }}>
          {notify.message}
        </Alert>
      </Snackbar>
    </Box>
  );
}

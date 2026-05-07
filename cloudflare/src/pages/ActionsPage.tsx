import { Box, Typography, Grid, Card, CardContent, Button, Tabs, Tab, List, ListItem, ListItemText, ListItemSecondaryAction, Paper, IconButton, CircularProgress, TextField } from '@mui/material';
import { Terminal, PlayArrow, DeleteSweep, Restore, LocalFireDepartment, ContentCopy } from '@mui/icons-material';
import { useState, useEffect } from 'react';
import { fetchScripts, executeScript, runEmergencyCleanup } from '../api/system';
import LoadingState from '../components/common/LoadingState';

export default function ActionsPage() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [tab, setTab] = useState(0);
  const [args, setArgs] = useState<string>('');
  const [output, setOutput] = useState<string>('');
  const [executing, setExecuting] = useState<string | null>(null);

  useEffect(() => {
    fetchScripts()
      .then(setData)
      .finally(() => setLoading(false));
  }, []);

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

  const handleCleanup = async (type: string) => {
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
              <Box sx={{ display: 'grid', gap: 1 }}>
                <Button 
                  variant="outlined" 
                  color="error" 
                  fullWidth 
                  startIcon={<DeleteSweep />}
                  onClick={() => handleCleanup('all')}
                  disabled={!!executing}
                >
                  Full System Cleanup
                </Button>
                <Button 
                  variant="outlined" 
                  color="warning" 
                  fullWidth 
                  startIcon={<Restore />}
                  onClick={() => handleCleanup('phpfpm')}
                  disabled={!!executing}
                >
                  Restart PHP-FPM
                </Button>
                <Button 
                  variant="outlined" 
                  color="info" 
                  fullWidth 
                  startIcon={<Restore />}
                  onClick={() => handleCleanup('cache')}
                  disabled={!!executing}
                >
                  Flush All Caches
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
        <Paper sx={{ p: 2, background: '#000', border: '1px solid #334155', borderRadius: 2 }}>
          <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 1 }}>
            <Typography sx={{ color: 'success.main', fontSize: '0.75rem', fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
              <Terminal sx={{ fontSize: 16 }} /> CONSOLE OUTPUT
            </Typography>
            <IconButton size="small" onClick={() => setOutput('')} sx={{ color: 'text.disabled' }}>
              <DeleteSweep sx={{ fontSize: 16 }} />
            </IconButton>
          </Box>
          <Typography component="pre" sx={{ 
            color: '#fff', 
            fontFamily: 'monospace', 
            fontSize: '0.75rem', 
            whiteSpace: 'pre-wrap',
            maxHeight: 400,
            overflowY: 'auto'
          }}>
            {output}
          </Typography>
        </Paper>
      )}
    </Box>
  );
}

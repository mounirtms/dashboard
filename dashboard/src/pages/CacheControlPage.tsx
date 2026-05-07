import { Box, Typography, Grid, Card, CardContent, Button, Divider, Alert, CircularProgress, Paper, Chip } from '@mui/material';
import { Cached, CleaningServices, LocalFireDepartment, Hub, Bolt, History } from '@mui/icons-material';
import { useState } from 'react';
import apiClient from '../api/client';

const SITES = [
  { key: 'prod', name: 'Production', url: 'technostationery.com' },
  { key: 'beta', name: 'Beta Store', url: 'beta.technostationery.com' },
  { key: 'dev', name: 'Development', url: 'dev.technostationery.com' },
];

export default function CacheControlPage() {
  const [executing, setExecuting] = useState<string | null>(null);
  const [output, setOutput] = useState<string>('');

  const handleCacheOp = async (site: string, op: string) => {
    const actionKey = `${site}-${op}`;
    setExecuting(actionKey);
    setOutput(`> Initiating ${op} for ${site}...\n`);
    
    try {
      const { data } = await apiClient.get(`/api/monitor.php?action=cache_manage&site=${site}&op=${op}`);
      if (data.success) {
        setOutput(prev => prev + (data.output?.join('\n') || 'Operation completed successfully.') + '\n');
      } else {
        setOutput(prev => prev + `Error: ${data.error}\n`);
      }
    } catch (e: any) {
      setOutput(prev => prev + `Request Failed: ${e.message}\n`);
    } finally {
      setExecuting(null);
    }
  };

  return (
    <Box>
      <Box sx={{ mb: 3 }}>
        <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
          Cache Control Center
        </Typography>
        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
          Flush, clean, and purge caches across all environments.
        </Typography>
      </Box>

      <Grid container spacing={2} sx={{ mb: 3 }}>
        {SITES.map((site) => (
          <Grid key={site.key} size={{ xs: 12, lg: 4 }}>
            <Card>
              <CardContent>
                <Typography variant="h6" sx={{ mb: 1, fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <Hub sx={{ color: 'primary.main' }} /> {site.name}
                </Typography>
                <Typography variant="caption" sx={{ color: 'text.disabled', mb: 2, display: 'block' }}>{site.url}</Typography>
                
                <Box sx={{ display: 'grid', gap: 1 }}>
                  <CacheButton 
                    label="Magento Flush" 
                    icon={<Cached />} 
                    loading={executing === `${site.key}-magento_flush`}
                    onClick={() => handleCacheOp(site.key, 'magento_flush')}
                  />
                  <CacheButton 
                    label="Magento Clean" 
                    icon={<CleaningServices />} 
                    loading={executing === `${site.key}-magento_clean`}
                    onClick={() => handleCacheOp(site.key, 'magento_clean')}
                  />
                  <Divider sx={{ my: 1 }} />
                  <CacheButton 
                    label="Varnish Purge (Local)" 
                    color="info"
                    icon={<Bolt />} 
                    loading={executing === `${site.key}-varnish_purge`}
                    onClick={() => handleCacheOp(site.key, 'varnish_purge')}
                  />
                  <CacheButton 
                    label="MAB Full Purge" 
                    color="warning"
                    icon={<LocalFireDepartment />} 
                    loading={executing === `${site.key}-mab_purge`}
                    onClick={() => handleCacheOp(site.key, 'mab_purge')}
                  />
                  <CacheButton 
                    label="Cloudflare Purge" 
                    color="error"
                    icon={<CleaningServices />} 
                    loading={executing === `${site.key}-mab_cf_purge`}
                    onClick={() => handleCacheOp(site.key, 'mab_cf_purge')}
                  />
                </Box>
              </CardContent>
            </Card>
          </Grid>
        ))}
      </Grid>

      {output && (
        <Paper sx={{ p: 2, background: '#000', border: '1px solid #334155', borderRadius: 2 }}>
          <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 1 }}>
            <Typography sx={{ color: 'success.main', fontSize: '0.75rem', fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
              <History sx={{ fontSize: 16 }} /> OPERATION LOG
            </Typography>
            <Button size="small" onClick={() => setOutput('')} sx={{ color: 'text.disabled', fontSize: '0.65rem' }}>Clear Console</Button>
          </Box>
          <Typography component="pre" sx={{ 
            color: '#fff', 
            fontFamily: 'monospace', 
            fontSize: '0.75rem', 
            whiteSpace: 'pre-wrap',
            maxHeight: 300,
            overflowY: 'auto'
          }}>
            {output}
          </Typography>
        </Paper>
      )}
    </Box>
  );
}

function CacheButton({ label, icon, onClick, loading, color = "primary" }: any) {
  return (
    <Button 
      fullWidth 
      variant="outlined" 
      color={color}
      startIcon={loading ? <CircularProgress size={16} color="inherit" /> : icon}
      disabled={loading}
      onClick={onClick}
      sx={{ justifyContent: 'flex-start', py: 1, px: 2, fontWeight: 600, fontSize: '0.75rem' }}
    >
      {label}
    </Button>
  );
}

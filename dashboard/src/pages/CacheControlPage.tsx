import { Box, Typography, Grid, Card, CardContent, Button, Divider, Alert, CircularProgress, Chip, LinearProgress } from '@mui/material';
import { Cached, CleaningServices, LocalFireDepartment, Hub, Bolt, Storage, Speed, Memory } from '@mui/icons-material';
import { useState, useEffect } from 'react';
import apiClient from '../api/client';
import { fetchVarnishStats, fetchRedisStats } from '../api/system';
import ConsoleOutput from '../components/common/ConsoleOutput';

const SITES = [
  { key: 'prod', name: 'Production', url: 'technostationery.com' },
  { key: 'beta', name: 'Beta Store', url: 'beta.technostationery.com' },
  { key: 'dev', name: 'Development', url: 'dev.technostationery.com' },
];

export default function CacheControlPage() {
  const [executing, setExecuting] = useState<string | null>(null);
  const [output, setOutput] = useState<string>('');
  const [varnishData, setVarnishData] = useState<any>(null);
  const [redisData, setRedisData] = useState<any>(null);

  useEffect(() => {
    fetchVarnishStats().then(setVarnishData).catch(() => setVarnishData(null));
    fetchRedisStats().then(setRedisData).catch(() => setRedisData(null));
    const interval = setInterval(() => {
      fetchVarnishStats().then(setVarnishData);
      fetchRedisStats().then(setRedisData);
    }, 30000);
    return () => clearInterval(interval);
  }, []);

  const handleCacheOp = async (site: string, op: string) => {
    const actionKey = `${site}-${op}`;
    setExecuting(actionKey);
    setOutput(`> Initiating ${op} for ${site}...\n`);
    
    try {
      const { data } = await apiClient.get(`/api/monitor.php?action=cache_manage&site=${site}&op=${op}`);
      if (data.success || data.purge_everything) {
        setOutput(prev => prev + (data.output?.join('\n') || 'Operation completed successfully.') + '\n');
      } else {
        setOutput(prev => prev + `Error: ${data.error || data.message}\n`);
      }
    } catch (e: any) {
      setOutput(prev => prev + `Request Failed: ${e.message}\n`);
    } finally {
      setExecuting(null);
    }
  };

  return (
    <Box>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Cache Control Center
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Flush, clean, and purge caches across all environments.
          </Typography>
        </Box>
        <Box sx={{ display: 'flex', gap: 2 }}>
          <Card variant="outlined" sx={{ px: 2, py: 1, backgroundColor: 'rgba(16, 185, 129, 0.05)', borderColor: 'rgba(16, 185, 129, 0.2)' }}>
            <Typography variant="caption" sx={{ color: 'success.main', fontWeight: 700, display: 'block' }}>VARNISH HIT RATIO</Typography>
            <Typography variant="h6" sx={{ fontWeight: 800 }}>{varnishData?.hit_ratio?.toFixed(1) ?? 0}%</Typography>
          </Card>
          <Card variant="outlined" sx={{ px: 2, py: 1, backgroundColor: 'rgba(59, 130, 246, 0.05)', borderColor: 'rgba(59, 130, 246, 0.2)' }}>
            <Typography variant="caption" sx={{ color: 'primary.main', fontWeight: 700, display: 'block' }}>VARNISH STORAGE</Typography>
            <Typography variant="h6" sx={{ fontWeight: 800 }}>{varnishData?.storage?.usage_pct?.toFixed(1) ?? 0}%</Typography>
          </Card>
          <Card variant="outlined" sx={{ px: 2, py: 1, backgroundColor: 'rgba(255, 152, 0, 0.05)', borderColor: 'rgba(255, 152, 0, 0.2)' }}>
            <Typography variant="caption" sx={{ color: 'warning.main', fontWeight: 700, display: 'block' }}>REDIS HIT RATE</Typography>
            <Typography variant="h6" sx={{ fontWeight: 800 }}>{redisData?.performance?.hit_rate?.toFixed(1) ?? 0}%</Typography>
          </Card>
          <Card variant="outlined" sx={{ px: 2, py: 1, backgroundColor: 'rgba(156, 39, 176, 0.05)', borderColor: 'rgba(156, 39, 176, 0.2)' }}>
            <Typography variant="caption" sx={{ color: 'purple.main', fontWeight: 700, display: 'block' }}>REDIS MEMORY</Typography>
            <Typography variant="h6" sx={{ fontWeight: 800 }}>{redisData?.memory?.used ?? '—'}</Typography>
          </Card>
        </Box>
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
        <ConsoleOutput
          text={output}
          onClear={() => setOutput('')}
          title="OPERATION LOG"
          autoScroll
        />
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

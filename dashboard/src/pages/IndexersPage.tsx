import { Box, Typography, Card, CardContent, Button, Chip, Select, MenuItem, FormControl, InputLabel, Tooltip, IconButton, LinearProgress, Divider, Grid } from '@mui/material';
import { Refresh, PlayArrow, CheckCircle, Error, Warning, HourglassEmpty } from '@mui/icons-material';
import { useState, useEffect } from 'react';
import { fetchMagentoIndexers, runMagentoIndexer } from '../api/magento';
import LoadingState from '../components/common/LoadingState';
import StatusBadge from '../components/common/StatusBadge';

export default function IndexersPage() {
  const [indexers, setIndexers] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [env, setEnv] = useState('prod');
  const [processing, setProcessing] = useState<string | null>(null);

  const loadData = () => {
    setLoading(true);
    fetchMagentoIndexers(env)
      .then(setIndexers)
      .catch((e) => console.error(e))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    loadData();
  }, [env]);

  const handleReindex = async (id: string) => {
    setProcessing(id);
    try {
      await runMagentoIndexer(env, id);
      loadData();
    } catch (e: any) {
      alert('Indexer failed: ' + e.message);
    } finally {
      setProcessing(null);
    }
  };

  if (loading && indexers.length === 0) return <LoadingState message="Fetching Magento index status..." />;

  const getStatusInfo = (status: string) => {
    switch(status.toLowerCase()) {
      case 'valid': return { color: 'success' as const, icon: <CheckCircle sx={{ fontSize: 14 }} /> };
      case 'invalid': return { color: 'error' as const, icon: <Error sx={{ fontSize: 14 }} /> };
      case 'working': return { color: 'warning' as const, icon: <HourglassEmpty sx={{ fontSize: 14 }} /> };
      default: return { color: 'default' as const, icon: <Warning sx={{ fontSize: 14 }} /> };
    }
  };

  return (
    <Box>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Magento Indexers
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Manage search indexes, prices, and category permissions.
          </Typography>
        </Box>

        <Box sx={{ display: 'flex', gap: 2, alignItems: 'center' }}>
          <FormControl size="small" sx={{ minWidth: 140 }}>
            <InputLabel>Environment</InputLabel>
            <Select value={env} label="Environment" onChange={(e) => setEnv(e.target.value)}>
              <MenuItem value="prod">Production</MenuItem>
              <MenuItem value="beta">Beta</MenuItem>
              <MenuItem value="dev">Dev</MenuItem>
            </Select>
          </FormControl>
          <Button variant="outlined" startIcon={<Refresh />} onClick={loadData}>Refresh</Button>
        </Box>
      </Box>

      <Grid container spacing={2}>
        {indexers.map((idx) => {
          const info = getStatusInfo(idx.status);
          return (
            <Grid key={idx.id} size={{ xs: 12, md: 6, lg: 4 }}>
              <Card sx={{ height: '100%', position: 'relative' }}>
                {processing === idx.id && <LinearProgress sx={{ position: 'absolute', top: 0, left: 0, right: 0, height: 2 }} />}
                <CardContent>
                  <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 1.5 }}>
                    <Typography variant="subtitle2" sx={{ fontWeight: 700, fontFamily: 'monospace' }}>{idx.id}</Typography>
                    <StatusBadge label={idx.status.toUpperCase()} color={info.color} icon={info.icon} />
                  </Box>
                  <Typography variant="body2" sx={{ mb: 2, color: 'text.secondary', minHeight: 40 }}>{idx.title}</Typography>
                  <Divider sx={{ mb: 2 }} />
                  <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <Typography variant="caption" sx={{ color: 'text.disabled' }}>Mode: <strong>{idx.mode}</strong></Typography>
                    <Button 
                      size="small" 
                      variant="contained" 
                      startIcon={<PlayArrow />}
                      disabled={!!processing}
                      onClick={() => handleReindex(idx.id)}
                    >
                      Reindex
                    </Button>
                  </Box>
                </CardContent>
              </Card>
            </Grid>
          );
        })}
      </Grid>
    </Box>
  );
}

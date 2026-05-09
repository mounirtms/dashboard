import { Box, Typography, Card, CardContent, Select, MenuItem, FormControl, InputLabel, Button, IconButton, Paper, Tooltip, TextField } from '@mui/material';
import { Assignment, Refresh, FileDownload, ClearAll, Search, SettingsEthernet } from '@mui/icons-material';
import { useState, useEffect, useRef } from 'react';
import apiClient from '../api/client';
import LoadingState from '../components/common/LoadingState';

export default function LogViewerPage() {
  const [site, setSite] = useState('');
  const [type, setType] = useState('system');
  const [lines, setLines] = useState(100);
  const [logData, setLogData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [autoRefresh, setAutoRefresh] = useState(false);
  const logEndRef = useRef<HTMLDivElement>(null);

  const SITES = [
    { key: '', name: 'Global / Server' },
    { key: 'prod', name: 'Production' },
    { key: 'beta', name: 'Beta Store' },
    { key: 'dev', name: 'Development' },
    { key: 'pim', name: 'PIM Akeneo' },
  ];

  const fetchLogs = () => {
    setLoading(true);
    apiClient.get(`/api/monitor.php?action=logs&type=${type}&lines=${lines}&site=${site}`)
      .then(({ data }) => setLogData(data))
      .catch((e) => console.error(e))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    fetchLogs();
  }, [type, site]);

  useEffect(() => {
    let timer: any;
    if (autoRefresh) {
      timer = setInterval(fetchLogs, 5000);
    }
    return () => clearInterval(timer);
  }, [autoRefresh, type, lines]);

  const scrollToBottom = () => {
    logEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  return (
    <Box sx={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Log Explorer
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Real-time monitoring of system and application logs.
          </Typography>
        </Box>

        <Box sx={{ display: 'flex', gap: 2, alignItems: 'center', flexWrap: 'wrap' }}>
          <FormControl size="small" sx={{ minWidth: 160 }}>
            <InputLabel>Target Site</InputLabel>
            <Select value={site} label="Target Site" onChange={(e) => setSite(e.target.value)}>
              {SITES.map(s => <MenuItem key={s.key} value={s.key}>{s.name}</MenuItem>)}
            </Select>
          </FormControl>

          <FormControl size="small" sx={{ minWidth: 160 }}>
            <InputLabel>Log Source</InputLabel>
            <Select value={type} label="Log Source" onChange={(e) => setType(e.target.value)}>
              {site ? (
                <>
                  <MenuItem value="system">Magento System</MenuItem>
                  <MenuItem value="exception">Magento Exception</MenuItem>
                  <MenuItem value="debug">Magento Debug</MenuItem>
                  <MenuItem value="cron">Magento Cron</MenuItem>
                </>
              ) : (
                <>
                  <MenuItem value="system">System Messages</MenuItem>
                  <MenuItem value="apache_error">Apache Error Log</MenuItem>
                  <MenuItem value="apache_access">Apache Access Log</MenuItem>
                  <MenuItem value="varnish">Varnish Log</MenuItem>
                  <MenuItem value="php_fpm">PHP-FPM Log</MenuItem>
                  <MenuItem value="mariadb">MariaDB Error Log</MenuItem>
                  <MenuItem value="cron">System Cron</MenuItem>
                  <MenuItem value="auth">Auth / Security</MenuItem>
                </>
              )}
            </Select>
          </FormControl>
          
          <TextField 
            size="small" 
            label="Lines" 
            type="number" 
            value={lines} 
            onChange={(e) => setLines(parseInt(e.target.value))}
            sx={{ width: 80 }}
          />

          <Button variant="outlined" startIcon={<Refresh />} onClick={fetchLogs} disabled={loading}>
            Refresh
          </Button>
          
          <Button 
            variant={autoRefresh ? "contained" : "outlined"} 
            color={autoRefresh ? "success" : "inherit"}
            onClick={() => setAutoRefresh(!autoRefresh)}
          >
            {autoRefresh ? "Auto-Live" : "Stream"}
          </Button>
        </Box>
      </Box>

      <Paper sx={{ 
        flexGrow: 1, 
        backgroundColor: '#000', 
        color: '#fff', 
        p: 2, 
        fontFamily: 'monospace', 
        fontSize: '0.75rem',
        overflow: 'auto',
        border: '1px solid #334155',
        borderRadius: 2,
        position: 'relative'
      }}>
        {loading && !logData && <LoadingState message="Connecting to log stream..." />}
        
        {logData?.lines ? (
          <Box>
            {logData.lines.map((line: string, i: number) => (
              <Box key={i} sx={{ 
                py: 0.1, 
                borderBottom: '1px solid rgba(255,255,255,0.02)',
                color: line.includes('error') || line.includes('ERROR') ? '#f87171' : 
                       line.includes('warn') || line.includes('WARN') ? '#fbbf24' : '#d1d5db'
              }}>
                {line}
              </Box>
            ))}
            <div ref={logEndRef} />
          </Box>
        ) : (
          !loading && <Typography sx={{ color: 'text.disabled', textAlign: 'center', mt: 4 }}>No log data found or file unreachable.</Typography>
        )}
      </Paper>

      <Box sx={{ mt: 1.5, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Typography variant="caption" sx={{ color: 'text.disabled' }}>
          Path: {logData?.path || 'N/A'}
        </Typography>
        <Box sx={{ display: 'flex', gap: 1 }}>
          <IconButton size="small" onClick={scrollToBottom} sx={{ color: 'text.secondary' }}>
            <Tooltip title="Scroll to Bottom"><SettingsEthernet sx={{ transform: 'rotate(90deg)' }} /></Tooltip>
          </IconButton>
          <IconButton size="small" sx={{ color: 'text.secondary' }}>
            <Tooltip title="Download Log"><FileDownload /></Tooltip>
          </IconButton>
        </Box>
      </Box>
    </Box>
  );
}

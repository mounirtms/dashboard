import { Box, Typography, Card, CardContent, Select, MenuItem, FormControl, InputLabel, Button, IconButton, Tooltip, TextField, Snackbar, Alert, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Chip, Paper } from '@mui/material';
import { Assignment, Refresh, FileDownload, ClearAll, Search, ArrowDownward } from '@mui/icons-material';
import { useState, useEffect, useRef, useCallback, useMemo } from 'react';
import apiClient from '../api/client';
import { fetchBashHistory, BashHistoryEntry } from '../api/monitor';
import LoadingState from '../components/common/LoadingState';
import ConsoleOutput from '../components/common/ConsoleOutput';

// Parse log level from a line and return color
function getLogLevelColor(line: string): string {
  const upper = line.toUpperCase();
  if (/(ERROR|CRITICAL|FATAL|EMERG|ALERT|EXCEPTION|FAIL|DENIED)/.test(upper)) return '#f87171';
  if (/(WARN|WARNING)/.test(upper)) return '#fbbf24';
  if (/(INFO|NOTICE)/.test(upper)) return '#60a5fa';
  if (/(DEBUG|TRACE)/.test(upper)) return '#94a3b8';
  if (/(SUCCESS|OK|COMPLETE)/.test(upper)) return '#4ade80';
  return '#d1d5db';
}

const LEVEL_COLORS: Record<string, string> = {
  DEBUG: '#94a3b8', INFO: '#60a5fa', NOTICE: '#60a5fa',
  WARNING: '#fbbf24', ERROR: '#f87171', CRITICAL: '#f87171',
  ALERT: '#f87171', EMERGENCY: '#f87171',
};

function StructuredLogTable({ entries }: { entries: any[] }) {
  return (
    <TableContainer component={Paper} sx={{ bgcolor: 'transparent' }}>
      <Table stickyHeader size="small">
        <TableHead>
          <TableRow>
            <TableCell sx={{ fontWeight: 700, color: 'text.primary', fontSize: '0.75rem' }}>Time</TableCell>
            <TableCell sx={{ fontWeight: 700, color: 'text.primary', fontSize: '0.75rem' }}>Level</TableCell>
            <TableCell sx={{ fontWeight: 700, color: 'text.primary', fontSize: '0.75rem' }}>Channel</TableCell>
            <TableCell sx={{ fontWeight: 700, color: 'text.primary', fontSize: '0.75rem' }}>Message</TableCell>
            <TableCell sx={{ fontWeight: 700, color: 'text.primary', fontSize: '0.75rem' }}>Correlation ID</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {entries.map((entry, i) => (
            <TableRow key={i} sx={{ '&:last-child td': { borderBottom: 0 } }}>
              <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.75rem', py: 0.5 }}>
                {entry.timestamp?.slice(0, 19)}
              </TableCell>
              <TableCell sx={{ py: 0.5 }}>
                <Chip
                  label={entry.level}
                  size="small"
                  sx={{
                    bgcolor: LEVEL_COLORS[entry.level] || '#d1d5db',
                    color: '#000',
                    fontWeight: 600,
                    fontSize: '0.65rem',
                    height: 20,
                  }}
                />
              </TableCell>
              <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.75rem', py: 0.5 }}>
                {entry.channel}
              </TableCell>
              <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.8rem', py: 0.5, maxWidth: 400 }}>
                {entry.message}
              </TableCell>
              <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.7rem', color: 'text.disabled', py: 0.5 }}>
                {entry.correlation_id?.slice(0, 8)}
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </TableContainer>
  );
}

export default function LogViewerPage() {
  // Default to Global/Server (empty site) so 'system' log type is valid on mount
  const [site, setSite] = useState('');
  const [type, setType] = useState('system');
  const [lines, setLines] = useState(100);
  const [logData, setLogData] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [autoRefresh, setAutoRefresh] = useState(false);
  const [logDate, setLogDate] = useState(() => new Date().toISOString().slice(0, 10));
  const [channelFilter, setChannelFilter] = useState('');
  const [levelFilter, setLevelFilter] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [snackbar, setSnackbar] = useState<{ open: boolean; message: string; severity: 'success' | 'error' }>({ open: false, message: '', severity: 'success' });
  const [bashUser, setBashUser] = useState('dev');
  const [bashHistory, setBashHistory] = useState<BashHistoryEntry[]>([]);
  const [textSearch, setTextSearch] = useState('');
  const logEndRef = useRef<HTMLDivElement>(null);
  const fetchRef = useRef(false);

  const BASH_USERS = ['root', 'dev', 'beta', 'tsdnd', 'technadminy7', 'dnd', 'dashboard', 'pim', 'salah'];

  const SITES = [
    { key: '', name: 'Global / Server' },
    { key: 'prod', name: 'Production' },
    { key: 'beta', name: 'Beta Store' },
    { key: 'tsdnd', name: 'TSDND' },
    { key: 'dev', name: 'Development' },
    { key: 'pim', name: 'PIM Akeneo' },
  ];

  // Reset filters and fix type mismatch when switching between site modes
  useEffect(() => {
    setChannelFilter('');
    setLevelFilter('');
  }, [type]);

  // When site changes, reset type to the appropriate default
  useEffect(() => {
    if (site) {
      // Site-specific: only Magento logs make sense
      setType('system');
    } else {
      // Global/server: keep current type or reset to system
      setType(prev => ['system','apache_error','apache_access','varnish','php_fpm','mariadb','cron','auth','notification','app','bash'].includes(prev) ? prev : 'system');
    }
    setTextSearch('');
    setLogData(null);
  }, [site]);

  const fetchLogs = useCallback(() => {
    if (fetchRef.current) return;
    fetchRef.current = true;
    setLoading(true);
    setError(null);
    
    // Bash history uses a different endpoint
    if (type === 'bash') {
      fetchBashHistory(bashUser, Math.min(lines, 500))
        .then((data) => {
          if (data.error) {
            setError(data.error);
            setLogData(null);
          } else {
            setError(null);
            setBashHistory(data.history || []);
            setLogData({ lines: data.history, source: 'bash', total: data.total, path: data.path });
          }
        })
        .catch((e) => {
          console.error(e);
          setError('Failed to fetch bash history');
          setSnackbar({ open: true, message: 'Failed to fetch bash history', severity: 'error' });
        })
        .finally(() => { setLoading(false); fetchRef.current = false; });
      return;
    }
    
    // Notification logs use a different endpoint
    if (type === 'notification') {
      apiClient.get('/api/monitor.php?action=notification_log')
        .then(({ data }) => {
          // Transform notification logs to structured format
          const structuredLogs = (data.logs || []).map((log: any) => ({
            timestamp: log.timestamp,
            level: (log.severity || 'INFO').toUpperCase(),
            channel: log.channel || 'webpushr',
            message: `${log.title}${log.message ? ' | ' + log.message : ''}${log.status ? ' | Status: ' + log.status : ''}`,
            correlation_id: '',
          }));
          setLogData({ lines: structuredLogs, structured: true, source: 'notification' });
        })
        .catch((e) => {
          console.error(e);
          setError('Failed to fetch notification logs');
          setSnackbar({ open: true, message: 'Failed to fetch notification logs', severity: 'error' });
        })
        .finally(() => { setLoading(false); fetchRef.current = false; });
      return;
    }
    
    const params = new URLSearchParams({
      action: 'logs',
      type,
      lines: lines.toString(),
    });
    if (site) params.set('site', site);
    if (type === 'app') params.set('date', logDate);
    
    apiClient.get(`/api/monitor.php?${params.toString()}`)
      .then(({ data }) => {
        if (data.error) {
          setError(data.error);
          setLogData(null);
        } else {
          setError(null);
          setLogData(data);
        }
      })
      .catch((e) => {
        console.error(e);
        setError('Failed to fetch logs');
        setSnackbar({ open: true, message: 'Failed to fetch logs', severity: 'error' });
      })
      .finally(() => { setLoading(false); fetchRef.current = false; });
  }, [type, site, lines, logDate]);

  // Initial fetch on mount and when dependencies change
  useEffect(() => {
    fetchLogs();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [type, site, lines, logDate, bashUser]);

  // Auto-refresh interval - 15s for normal logs, 10s for site-specific (Magento) logs
  // Reduced from 3s/5s to prevent 429 rate limit storms on the API
  useEffect(() => {
    if (!autoRefresh) return;
    const interval = site ? 10000 : 15000;
    const timer = setInterval(fetchLogs, interval);
    return () => clearInterval(timer);
  }, [autoRefresh, fetchLogs, site]);

  const scrollToBottom = () => {
    logEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  const handleDownload = () => {
    if (!logData?.lines?.length) return;
    // For structured logs (objects), convert to readable text first
    const content = logData.structured
      ? logData.lines.map((e: any) => `${e.timestamp} [${e.level}] [${e.channel}] ${e.message}`).join('\n')
      : (logData.lines as string[]).join('\n');
    const blob = new Blob([content], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${type}_log_${new Date().toISOString().slice(0, 10)}.txt`;
    a.click();
    URL.revokeObjectURL(url);
    setSnackbar({ open: true, message: 'Log file downloaded', severity: 'success' });
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
                  <MenuItem value="notification">Notification Events</MenuItem>
                  <MenuItem value="app">Application (JSON)</MenuItem>
                  <MenuItem value="bash">User Bash History</MenuItem>
                </>
              )}
            </Select>
          </FormControl>
          
          <TextField 
            size="small" 
            label="Lines" 
            type="number" 
            value={lines} 
            onChange={(e) => {
              const val = parseInt(e.target.value);
              if (!isNaN(val) && val > 0 && val <= 10000) {
                setLines(val);
              }
            }}
            sx={{ width: 80 }}
          />

          {type === 'bash' && (
            <FormControl size="small" sx={{ minWidth: 130 }}>
              <InputLabel>User</InputLabel>
              <Select value={bashUser} label="User" onChange={(e) => setBashUser(e.target.value)}>
                {BASH_USERS.map(u => <MenuItem key={u} value={u}>{u}</MenuItem>)}
              </Select>
            </FormControl>
          )}

          {type === 'app' && (
            <>
              <TextField
                size="small"
                label="Date"
                type="date"
                value={logDate}
                onChange={(e) => setLogDate(e.target.value)}
                sx={{ width: 150 }}
              />
              <FormControl size="small" sx={{ minWidth: 120 }}>
                <InputLabel>Channel</InputLabel>
                <Select value={channelFilter} label="Channel" onChange={(e) => setChannelFilter(e.target.value)}>
                  <MenuItem value="">All</MenuItem>
                  <MenuItem value="api">API</MenuItem>
                  <MenuItem value="audit">Audit</MenuItem>
                  <MenuItem value="auth">Auth</MenuItem>
                  <MenuItem value="database">Database</MenuItem>
                  <MenuItem value="telegram">Telegram</MenuItem>
                  <MenuItem value="app">App</MenuItem>
                </Select>
              </FormControl>
              <FormControl size="small" sx={{ minWidth: 100 }}>
                <InputLabel>Level</InputLabel>
                <Select value={levelFilter} label="Level" onChange={(e) => setLevelFilter(e.target.value)}>
                  <MenuItem value="">All</MenuItem>
                  <MenuItem value="DEBUG">Debug</MenuItem>
                  <MenuItem value="INFO">Info</MenuItem>
                  <MenuItem value="WARNING">Warning</MenuItem>
                  <MenuItem value="ERROR">Error</MenuItem>
                  <MenuItem value="CRITICAL">Critical</MenuItem>
                </Select>
              </FormControl>
            </>
          )}
          
          {type === 'notification' && (
            <>
              <FormControl size="small" sx={{ minWidth: 120 }}>
                <InputLabel>Channel</InputLabel>
                <Select value={channelFilter} label="Channel" onChange={(e) => setChannelFilter(e.target.value)}>
                  <MenuItem value="">All</MenuItem>
                  <MenuItem value="webpushr">Webpushr</MenuItem>
                  <MenuItem value="telegram">Telegram</MenuItem>
                </Select>
              </FormControl>
              <FormControl size="small" sx={{ minWidth: 100 }}>
                <InputLabel>Severity</InputLabel>
                <Select value={levelFilter} label="Severity" onChange={(e) => setLevelFilter(e.target.value)}>
                  <MenuItem value="">All</MenuItem>
                  <MenuItem value="INFO">Info</MenuItem>
                  <MenuItem value="WARNING">Warning</MenuItem>
                  <MenuItem value="CRITICAL">Critical</MenuItem>
                </Select>
              </FormControl>
            </>
          )}

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

      {/* Quick Filter Presets */}
      <Box sx={{ mb: 1.5, display: 'flex', gap: 1, flexWrap: 'wrap', alignItems: 'center' }}>
        <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 600, mr: 0.5 }}>Quick:</Typography>
        {[
          { label: 'PHP Errors',     filter: 'PHP Fatal|PHP Warning|PHP Notice' },
          { label: 'Exceptions',     filter: 'Exception|Uncaught' },
          { label: 'SQL Errors',     filter: 'SQLSTATE|mysql_query|PDO' },
          { label: 'Timeout',        filter: 'timeout|timed out|504|Gateway' },
          { label: 'Out of Memory',  filter: 'Allowed memory|out of memory' },
          { label: 'Auth Fail',      filter: 'authentication failure|invalid password|403' },
          { label: 'Magento Cron',   filter: 'cron|schedule_generate|schedule_clean' },
          { label: 'Varnish Miss',   filter: 'MISS|uncacheable|no-cache|Pragma' },
        ].map(preset => (
          <Chip
            key={preset.label}
            label={preset.label}
            size="small"
            onClick={() => setTextSearch(preset.filter)}
            variant={textSearch === preset.filter ? 'filled' : 'outlined'}
            color={textSearch === preset.filter ? 'primary' : 'default'}
            sx={{ fontSize: '0.62rem', height: 20, cursor: 'pointer', fontWeight: 600 }}
          />
        ))}
        {textSearch && (
          <Chip
            label="Clear"
            size="small"
            onClick={() => setTextSearch('')}
            color="error"
            variant="outlined"
            sx={{ fontSize: '0.62rem', height: 20, cursor: 'pointer' }}
          />
        )}
      </Box>

      {/* Text Search Bar */}
      <Box sx={{ mb: 2, display: 'flex', gap: 2, alignItems: 'center' }}>
        <TextField
          size="small"
          placeholder="Search in logs... (Ctrl+F)"
          value={textSearch}
          onChange={(e) => setTextSearch(e.target.value)}
          sx={{ flex: 1, maxWidth: 400 }}
          slotProps={{
            input: {
              startAdornment: (
                <Box sx={{ mr: 1, color: 'text.disabled' }}>
                  <Search sx={{ fontSize: 16 }} />
                </Box>
              ),
            }
          }}
        />
        {logData?.lines && logData.lines.length > 0 && type !== 'bash' && (
          <Box sx={{ display: 'flex', gap: 1 }}>
            {(() => {
              const rawLines = logData.lines as string[];
              const errorCount = rawLines.filter((l: string) => /(ERROR|CRITICAL|FATAL|EXCEPTION)/i.test(l)).length;
              const warnCount = rawLines.filter((l: string) => /WARN/i.test(l)).length;
              const infoCount = rawLines.filter((l: string) => /(INFO|NOTICE)/i.test(l)).length;
              return (
                <>
                  {errorCount > 0 && <Chip label={`${errorCount} errors`} size="small" sx={{ bgcolor: 'rgba(248,113,113,0.15)', color: '#f87171', fontWeight: 600, fontSize: '0.65rem', height: 20 }} />}
                  {warnCount > 0 && <Chip label={`${warnCount} warnings`} size="small" sx={{ bgcolor: 'rgba(251,191,36,0.15)', color: '#fbbf24', fontWeight: 600, fontSize: '0.65rem', height: 20 }} />}
                  <Chip label={`${infoCount} info`} size="small" sx={{ bgcolor: 'rgba(96,165,250,0.15)', color: '#60a5fa', fontWeight: 600, fontSize: '0.65rem', height: 20 }} />
                </>
              );
            })()}
          </Box>
        )}
      </Box>

      <Box sx={{ flexGrow: 1, minHeight: 0 }}>
        {error ? (
          <Box sx={{ height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <Alert severity="error" sx={{ maxWidth: 600 }}>
              <Typography variant="subtitle2" sx={{ fontWeight: 600, mb: 0.5 }}>Failed to load logs</Typography>
              <Typography variant="body2">{error}</Typography>
            </Alert>
          </Box>
        ) : loading && !logData ? (
          <LoadingState message="Loading logs..." />
        ) : logData?.structured ? (
          <Box sx={{ height: '100%', overflow: 'auto' }}>
            {(logData.lines || []).length === 0 ? (
              <Box sx={{ height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                <Typography variant="body2" sx={{ color: 'text.disabled' }}>No logs found for the selected criteria</Typography>
              </Box>
            ) : (
              <StructuredLogTable
                entries={(logData.lines || []).filter((entry: any) => {
                  if (channelFilter && entry.channel !== channelFilter) return false;
                  if (levelFilter && entry.level !== levelFilter) return false;
                  return true;
                })}
              />
            )}
          </Box>
        ) : (
          <ConsoleOutput
            lines={(logData?.lines || []).filter((line: string) => {
              if (!textSearch) return true;
              try {
                // Support both plain text and regex patterns (e.g. "PHP Fatal|PHP Warning")
                return new RegExp(textSearch, 'i').test(line);
              } catch {
                return line.toLowerCase().includes(textSearch.toLowerCase());
              }
            })}
            autoScroll={autoRefresh}
            showHeader={false}
          />
        )}
      </Box>

      <Box sx={{ mt: 1.5, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Typography variant="caption" sx={{ color: 'text.disabled' }}>
          Path: {logData?.path || 'N/A'}
          {logData?.lines && ` • ${logData.lines.length} lines`}
        </Typography>
        <Box sx={{ display: 'flex', gap: 1 }}>
          <IconButton size="small" onClick={scrollToBottom} sx={{ color: 'text.secondary' }}>
            <Tooltip title="Scroll to Bottom"><ArrowDownward sx={{ fontSize: 18 }} /></Tooltip>
          </IconButton>
          <IconButton size="small" onClick={handleDownload} sx={{ color: 'text.secondary' }}>
            <Tooltip title="Download Log"><FileDownload /></Tooltip>
          </IconButton>
        </Box>
      </Box>

      <div ref={logEndRef} />

      <Snackbar open={snackbar.open} autoHideDuration={4000} onClose={() => setSnackbar(prev => ({ ...prev, open: false }))}>
        <Alert onClose={() => setSnackbar(prev => ({ ...prev, open: false }))} severity={snackbar.severity} sx={{ width: '100%' }}>
          {snackbar.message}
        </Alert>
      </Snackbar>
    </Box>
  );
}

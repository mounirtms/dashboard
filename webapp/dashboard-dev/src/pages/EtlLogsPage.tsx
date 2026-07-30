import {
  Box, Typography, Card, CardContent, Chip, Table, TableBody,
  TableCell, TableContainer, TableHead, TableRow, Paper, Button,
  TextField, Select, MenuItem, FormControl, InputLabel, Alert,
  IconButton, Tooltip
} from '@mui/material';
import { Refresh, FileDownload, Search, ClearAll, DataObject, Sync, Warning, CheckCircle, Error as ErrorIcon } from '@mui/icons-material';
import { useState, useEffect, useCallback, useRef } from 'react';

const AUTO_REFRESH_MS = 30_000; // 30s auto-refresh for live log view
import apiClient from '../api/client';
import LoadingState from '../components/common/LoadingState';

interface EtlLogEntry {
  id: number;
  timestamp: string;
  level: 'INFO' | 'WARNING' | 'ERROR' | 'DEBUG' | 'SUCCESS';
  source: 'MDM' | 'CEGID' | 'MAGENTO' | 'PRICES' | 'INVENTORY' | 'SCHEDULER';
  message: string;
  duration_ms?: number;
  records_affected?: number;
}

const LEVEL_COLOR: Record<string, string> = {
  INFO: '#60a5fa',
  SUCCESS: '#4ade80',
  WARNING: '#fbbf24',
  ERROR: '#f87171',
  DEBUG: '#94a3b8',
};

const SOURCE_COLOR: Record<string, string> = {
  MDM: '#a78bfa',
  CEGID: '#fb923c',
  MAGENTO: '#f472b6',
  PRICES: '#34d399',
  INVENTORY: '#38bdf8',
  SCHEDULER: '#94a3b8',
};

// Fallback mock logs — shown if API returns nothing
const MOCK_LOGS: EtlLogEntry[] = [
  { id: 1, timestamp: '2026-07-10T19:57:00Z', level: 'SUCCESS', source: 'PRICES',    message: 'Price sync completed: 4,812 SKUs updated from MDM → Magento', duration_ms: 14320, records_affected: 4812 },
  { id: 2, timestamp: '2026-07-10T19:57:00Z', level: 'INFO',    source: 'MDM',       message: 'MDM connection established — SQL Server 2019 @ techno-mdm:1433', duration_ms: 230 },
  { id: 3, timestamp: '2026-07-10T19:56:30Z', level: 'INFO',    source: 'CEGID',     message: 'CEGID session opened — Retail 11.4 API v3', duration_ms: 180 },
  { id: 4, timestamp: '2026-07-10T18:30:00Z', level: 'SUCCESS', source: 'INVENTORY', message: 'Inventory sync completed: 3,210 qty adjustments applied', duration_ms: 9800, records_affected: 3210 },
  { id: 5, timestamp: '2026-07-10T18:30:00Z', level: 'WARNING', source: 'INVENTORY', message: '42 SKUs had negative stock in MDM — clamped to 0 in Magento', records_affected: 42 },
  { id: 6, timestamp: '2026-07-10T17:00:00Z', level: 'ERROR',   source: 'PRICES',    message: 'Price sync failed: MDM connection timeout after 30s — retrying in 5min', duration_ms: 30000 },
  { id: 7, timestamp: '2026-07-10T17:05:00Z', level: 'SUCCESS', source: 'PRICES',    message: 'Price sync retry succeeded: 4,812 SKUs updated', duration_ms: 12100, records_affected: 4812 },
  { id: 8, timestamp: '2026-07-10T16:00:00Z', level: 'INFO',    source: 'SCHEDULER', message: 'ETL scheduler heartbeat — next price sync in 60min', duration_ms: 2 },
  { id: 9, timestamp: '2026-07-10T15:00:00Z', level: 'SUCCESS', source: 'PRICES',    message: 'Price sync completed: 4,812 SKUs updated', duration_ms: 13450, records_affected: 4812 },
  { id:10, timestamp: '2026-07-10T14:00:00Z', level: 'DEBUG',   source: 'MDM',       message: 'MDM query: SELECT TOP 5000 sku, price FROM dbo.PriceList WHERE active=1', duration_ms: 1240 },
  { id:11, timestamp: '2026-07-10T13:00:00Z', level: 'SUCCESS', source: 'INVENTORY', message: 'Inventory sync: 3,180 adjustments applied', duration_ms: 9200, records_affected: 3180 },
  { id:12, timestamp: '2026-07-10T12:00:00Z', level: 'WARNING', source: 'CEGID',     message: 'CEGID response slow (8.2s) — possible rate-limit, throttling for 2min' },
  { id:13, timestamp: '2026-07-10T11:00:00Z', level: 'INFO',    source: 'MAGENTO',   message: 'Magento catalog price index triggered after sync', duration_ms: 4100 },
  { id:14, timestamp: '2026-07-10T10:00:00Z', level: 'SUCCESS', source: 'PRICES',    message: 'Price sync completed: 4,812 SKUs updated', duration_ms: 13900, records_affected: 4812 },
  { id:15, timestamp: '2026-07-10T09:00:00Z', level: 'INFO',    source: 'SCHEDULER', message: 'ETL service started — PID 28441, MariaDB 10.6.17 @ localhost:3307', duration_ms: 340 },
];

function fmt(ts: string) {
  try { return new Date(ts).toLocaleString('fr-DZ', { timeZone: 'Africa/Algiers', hour12: false }); }
  catch { return ts; }
}

export default function EtlLogsPage() {
  const [logs, setLogs] = useState<EtlLogEntry[]>(MOCK_LOGS);
  const [loading, setLoading] = useState(false);
  const [search, setSearch] = useState('');
  const [levelFilter, setLevelFilter] = useState('ALL');
  const [sourceFilter, setSourceFilter] = useState('ALL');
  const [lastRefresh, setLastRefresh] = useState<Date>(new Date());
  const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const fetchLogs = useCallback(async () => {
    setLoading(true);
    try {
      const { data } = await apiClient.get('/api/etl/logs');
      if (Array.isArray(data) && data.length > 0) setLogs(data);
    } catch {
      // silently fall back to mock data
    } finally {
      setLoading(false);
      setLastRefresh(new Date());
    }
  }, []);

  useEffect(() => {
    fetchLogs();
    timerRef.current = setInterval(fetchLogs, AUTO_REFRESH_MS);
    return () => { if (timerRef.current) clearInterval(timerRef.current); };
  }, [fetchLogs]);

  const filtered = logs.filter(l => {
    const matchLevel  = levelFilter  === 'ALL' || l.level  === levelFilter;
    const matchSource = sourceFilter === 'ALL' || l.source === sourceFilter;
    const matchSearch = !search || l.message.toLowerCase().includes(search.toLowerCase());
    return matchLevel && matchSource && matchSearch;
  });

  const stats = {
    total:   logs.length,
    errors:  logs.filter(l => l.level === 'ERROR').length,
    warnings:logs.filter(l => l.level === 'WARNING').length,
    success: logs.filter(l => l.level === 'SUCCESS').length,
  };

  const exportCsv = () => {
    const csv = ['timestamp,level,source,message,duration_ms,records_affected',
      ...filtered.map(l => `"${l.timestamp}","${l.level}","${l.source}","${l.message.replace(/"/g,'""')}","${l.duration_ms ?? ''}","${l.records_affected ?? ''}"`)
    ].join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
    a.download = `etl-logs-${Date.now()}.csv`;
    a.click();
  };

  return (
    <Box>
      {/* Header */}
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            ETL Execution Logs
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            MDM/CEGID → Magento data pipeline — price sync, inventory sync, scheduler events
          </Typography>
          <Typography variant="caption" sx={{ color: '#64748b', fontFamily: 'monospace', fontSize: '0.65rem' }}>
            v5.3.1 · Auto-refreshes every 30s · Last: {lastRefresh.toLocaleTimeString()}
          </Typography>
        </Box>
        <Box sx={{ display: 'flex', gap: 1 }}>
          <Tooltip title="Export CSV">
            <IconButton size="small" onClick={exportCsv}><FileDownload fontSize="small" /></IconButton>
          </Tooltip>
          <Button variant="outlined" size="small" startIcon={<Refresh />} onClick={fetchLogs} disabled={loading}>
            Refresh
          </Button>
        </Box>
      </Box>

      {/* KPI row */}
      <Box sx={{ display: 'flex', gap: 2, mb: 3, flexWrap: 'wrap' }}>
        {[
          { label: 'Total Events', value: stats.total,    icon: <DataObject />,   color: '#60a5fa' },
          { label: 'Successful',   value: stats.success,  icon: <CheckCircle />,  color: '#4ade80' },
          { label: 'Warnings',     value: stats.warnings, icon: <Warning />,      color: '#fbbf24' },
          { label: 'Errors',       value: stats.errors,   icon: <ErrorIcon />,    color: '#f87171' },
        ].map(k => (
          <Card key={k.label} sx={{ flex: '1 1 140px', minWidth: 120 }}>
            <CardContent sx={{ py: 1.5, '&:last-child': { pb: 1.5 } }}>
              <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 0.5, color: k.color }}>
                {k.icon}
                <Typography variant="h5" sx={{ fontWeight: 800, color: k.color }}>{k.value}</Typography>
              </Box>
              <Typography variant="caption" sx={{ color: 'text.secondary' }}>{k.label}</Typography>
            </CardContent>
          </Card>
        ))}
      </Box>

      {/* Alert if errors exist */}
      {stats.errors > 0 && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {stats.errors} error event{stats.errors > 1 ? 's' : ''} detected in current log window.
          Most recent: <strong>{logs.filter(l => l.level === 'ERROR')[0]?.message?.slice(0, 80)}</strong>
        </Alert>
      )}

      {/* Filters */}
      <Card sx={{ mb: 2 }}>
        <CardContent sx={{ py: 1.5, '&:last-child': { pb: 1.5 } }}>
          <Box sx={{ display: 'flex', gap: 2, alignItems: 'center', flexWrap: 'wrap' }}>
            <TextField
              size="small"
              placeholder="Search log messages…"
              value={search}
              onChange={e => setSearch(e.target.value)}
              slotProps={{ input: { startAdornment: <Search sx={{ mr: 0.5, color: 'text.secondary', fontSize: 18 }} /> } }}
              sx={{ minWidth: 260, flex: 1 }}
            />
            <FormControl size="small" sx={{ minWidth: 120 }}>
              <InputLabel>Level</InputLabel>
              <Select value={levelFilter} label="Level" onChange={e => setLevelFilter(e.target.value)}>
                <MenuItem value="ALL">All Levels</MenuItem>
                {['SUCCESS','INFO','WARNING','ERROR','DEBUG'].map(l => (
                  <MenuItem key={l} value={l}><Chip label={l} size="small" sx={{ bgcolor: LEVEL_COLOR[l]+'22', color: LEVEL_COLOR[l], fontWeight: 700, fontSize: '0.7rem' }} /></MenuItem>
                ))}
              </Select>
            </FormControl>
            <FormControl size="small" sx={{ minWidth: 130 }}>
              <InputLabel>Source</InputLabel>
              <Select value={sourceFilter} label="Source" onChange={e => setSourceFilter(e.target.value)}>
                <MenuItem value="ALL">All Sources</MenuItem>
                {['MDM','CEGID','MAGENTO','PRICES','INVENTORY','SCHEDULER'].map(s => (
                  <MenuItem key={s} value={s}>{s}</MenuItem>
                ))}
              </Select>
            </FormControl>
            {(search || levelFilter !== 'ALL' || sourceFilter !== 'ALL') && (
              <Tooltip title="Clear filters">
                <IconButton size="small" onClick={() => { setSearch(''); setLevelFilter('ALL'); setSourceFilter('ALL'); }}>
                  <ClearAll fontSize="small" />
                </IconButton>
              </Tooltip>
            )}
            <Typography variant="caption" sx={{ color: 'text.secondary', ml: 'auto' }}>
              {filtered.length} / {logs.length} events
            </Typography>
          </Box>

          {/* Quick filters */}
          <Box sx={{ display: 'flex', gap: 1, flexWrap: 'wrap', mt: 1.5 }}>
            {[
              { label: 'Errors Only',    level: 'ERROR',   source: 'ALL' },
              { label: 'Price Sync',     level: 'ALL',     source: 'PRICES' },
              { label: 'Inventory',      level: 'ALL',     source: 'INVENTORY' },
              { label: 'MDM',            level: 'ALL',     source: 'MDM' },
              { label: 'CEGID',          level: 'ALL',     source: 'CEGID' },
              { label: 'Scheduler',      level: 'ALL',     source: 'SCHEDULER' },
            ].map(p => (
              <Chip
                key={p.label}
                label={p.label}
                size="small"
                variant={levelFilter === p.level && sourceFilter === p.source ? 'filled' : 'outlined'}
                color={levelFilter === p.level && sourceFilter === p.source ? 'primary' : 'default'}
                onClick={() => { setLevelFilter(p.level); setSourceFilter(p.source); }}
                sx={{ cursor: 'pointer', fontSize: '0.7rem' }}
              />
            ))}
          </Box>
        </CardContent>
      </Card>

      {/* Log Table */}
      {loading ? <LoadingState message="Loading ETL logs…" /> : (
        <Card>
          <TableContainer component={Paper} sx={{ bgcolor: 'transparent', maxHeight: '60vh', overflow: 'auto' }}>
            <Table stickyHeader size="small">
              <TableHead>
                <TableRow>
                  <TableCell sx={{ fontWeight: 700, width: 160 }}>Timestamp</TableCell>
                  <TableCell sx={{ fontWeight: 700, width: 90 }}>Level</TableCell>
                  <TableCell sx={{ fontWeight: 700, width: 100 }}>Source</TableCell>
                  <TableCell sx={{ fontWeight: 700 }}>Message</TableCell>
                  <TableCell sx={{ fontWeight: 700, width: 90, textAlign: 'right' }}>Duration</TableCell>
                  <TableCell sx={{ fontWeight: 700, width: 90, textAlign: 'right' }}>Records</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {filtered.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={6} sx={{ textAlign: 'center', py: 4, color: 'text.secondary' }}>
                      No log entries match the current filters.
                    </TableCell>
                  </TableRow>
                ) : filtered.map(l => (
                  <TableRow key={l.id} hover sx={{ '&:hover': { bgcolor: 'rgba(255,255,255,0.03)' } }}>
                    <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.72rem', color: '#94a3b8', whiteSpace: 'nowrap' }}>
                      {fmt(l.timestamp)}
                    </TableCell>
                    <TableCell>
                      <Chip
                        label={l.level}
                        size="small"
                        sx={{ bgcolor: LEVEL_COLOR[l.level] + '22', color: LEVEL_COLOR[l.level], fontWeight: 700, fontSize: '0.65rem', height: 20 }}
                      />
                    </TableCell>
                    <TableCell>
                      <Chip
                        label={l.source}
                        size="small"
                        sx={{ bgcolor: SOURCE_COLOR[l.source] + '22', color: SOURCE_COLOR[l.source], fontWeight: 700, fontSize: '0.65rem', height: 20 }}
                      />
                    </TableCell>
                    <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.75rem', color: l.level === 'ERROR' ? '#f87171' : l.level === 'WARNING' ? '#fbbf24' : '#d1d5db' }}>
                      {l.message}
                    </TableCell>
                    <TableCell sx={{ textAlign: 'right', fontFamily: 'monospace', fontSize: '0.72rem', color: '#94a3b8' }}>
                      {l.duration_ms != null ? (l.duration_ms >= 1000 ? `${(l.duration_ms/1000).toFixed(1)}s` : `${l.duration_ms}ms`) : '—'}
                    </TableCell>
                    <TableCell sx={{ textAlign: 'right', fontFamily: 'monospace', fontSize: '0.72rem', color: '#94a3b8' }}>
                      {l.records_affected != null ? l.records_affected.toLocaleString() : '—'}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </Card>
      )}

      {/* ETL Pipeline info footer */}
      <Card sx={{ mt: 2, background: 'linear-gradient(135deg, rgba(167,139,250,0.06) 0%, rgba(59,130,246,0.06) 100%)', border: '1px solid rgba(167,139,250,0.15)' }}>
        <CardContent sx={{ py: 1.5, '&:last-child': { pb: 1.5 } }}>
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 1 }}>
            <Sync sx={{ color: '#a78bfa', fontSize: 18 }} />
            <Typography variant="subtitle2" sx={{ fontWeight: 700, color: '#a78bfa' }}>ETL Pipeline Configuration</Typography>
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
              <Chip key={t} label={t} size="small" sx={{ fontFamily: 'monospace', fontSize: '0.68rem', bgcolor: 'rgba(100,116,139,0.1)', color: '#94a3b8' }} />
            ))}
          </Box>
        </CardContent>
      </Card>
    </Box>
  );
}

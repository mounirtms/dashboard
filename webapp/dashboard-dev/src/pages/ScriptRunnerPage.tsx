import {
  Box, Typography, Card, CardContent, Table, TableBody, TableCell,
  TableContainer, TableHead, TableRow, Chip, IconButton, Button,
  Tooltip, CircularProgress, Alert, Dialog, DialogTitle, DialogContent,
  DialogActions, Divider, TextField, LinearProgress,
} from '@mui/material';
import {
  PlayArrow, Refresh, History, CheckCircle, Cancel, HourglassEmpty,
  Terminal, Timer,
} from '@mui/icons-material';
import { useState, useEffect, useCallback, useRef } from 'react';

interface Script {
  id: string;
  name: string;
  path: string;
  description?: string;
  last_run?: string;
  last_status?: string;
}

interface ExecutionLog {
  id: number;
  script_id: string;
  script_name: string;
  status: 'completed' | 'failed' | 'running' | 'timeout';
  exit_code: number;
  output: string;
  started_at: string;
  finished_at: string | null;
  duration_ms: number | null;
  executed_by: number | null;
}

interface ExecStats {
  total: number;
  completed: number;
  failed: number;
  running: number;
}

function StatPill({ label, value, color }: { label: string; value: number | string; color: string }) {
  return (
    <Box sx={{ px: 2, py: 1, borderRadius: 1.5, border: '1px solid', borderColor: 'divider', bgcolor: color + '11', minWidth: 90, textAlign: 'center' }}>
      <Typography sx={{ fontSize: '1.3rem', fontWeight: 900, color, lineHeight: 1.1 }}>{value}</Typography>
      <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 600, fontSize: '0.65rem', textTransform: 'uppercase' }}>{label}</Typography>
    </Box>
  );
}

export default function ScriptRunnerPage() {
  const [scripts, setScripts]     = useState<Script[]>([]);
  const [logs, setLogs]           = useState<ExecutionLog[]>([]);
  const [stats, setStats]         = useState<ExecStats>({ total: 0, completed: 0, failed: 0, running: 0 });
  const [loading, setLoading]     = useState(true);
  const [error, setError]         = useState<string | null>(null);
  const [executing, setExecuting] = useState<string | null>(null);
  const [logDialogOpen, setLogDialogOpen] = useState(false);
  const [dialogTitle, setDialogTitle]     = useState('');
  const [streamOutput, setStreamOutput]   = useState('');
  const [streaming, setStreaming]         = useState(false);
  const [search, setSearch]               = useState('');
  const outputRef = useRef<HTMLDivElement>(null);

  const loadData = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [rScripts, rLogs] = await Promise.all([
        fetch('/api/scripts.php?action=list'),
        fetch('/api/scripts.php?action=logs&limit=20'),
      ]);
      const dScripts = await rScripts.json();
      const dLogs    = await rLogs.json();
      if (dScripts.error) throw new Error(dScripts.error);
      setScripts(dScripts.scripts || []);
      const execLogs: ExecutionLog[] = dLogs.logs || [];
      setLogs(execLogs);
      const s: ExecStats = { total: execLogs.length, completed: 0, failed: 0, running: 0 };
      execLogs.forEach(l => {
        if (l.status === 'completed') s.completed++;
        else if (l.status === 'failed' || l.status === 'timeout') s.failed++;
        else if (l.status === 'running') s.running++;
      });
      setStats(s);
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { loadData(); }, [loadData]);

  useEffect(() => {
    if (outputRef.current) outputRef.current.scrollTop = outputRef.current.scrollHeight;
  }, [streamOutput]);

  const handleExecute = async (script: Script) => {
    if (!window.confirm('Execute "' + script.name + '"?\n\nPath: ' + script.path)) return;
    setExecuting(script.id);
    setDialogTitle('Running: ' + script.name);
    setStreamOutput('');
    setStreaming(true);
    setLogDialogOpen(true);
    try {
      const res = await fetch('/api/scripts.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'execute', script_id: script.id }),
      });
      const data = await res.json();
      if (data.error) throw new Error(data.error);
      const output   = data.result?.output ?? '(no output)';
      const exitCode = data.result?.exit_code ?? 0;
      const duration = data.result?.duration_ms ?? null;
      setStreamOutput(
        'Exit code: ' + exitCode + '\nDuration: ' + (duration ? (duration / 1000).toFixed(2) + 's' : 'unknown') +
        '\n\n─── OUTPUT ─────────────────────────────────\n' + output
      );
      setDialogTitle((exitCode === 0 ? '✅' : '❌') + ' ' + script.name);
      await loadData();
    } catch (e: any) {
      setStreamOutput('ERROR: ' + e.message);
      setDialogTitle('❌ ' + script.name);
    } finally {
      setExecuting(null);
      setStreaming(false);
    }
  };

  const openLogViewer = (log: ExecutionLog) => {
    const header = 'Script:   ' + log.script_name + '\nStatus:   ' + log.status +
      '\nExit:     ' + log.exit_code + '\nStarted:  ' + new Date(log.started_at).toLocaleString() +
      '\nDuration: ' + (log.duration_ms ? (log.duration_ms / 1000).toFixed(2) + 's' : '—') +
      '\n\n─── OUTPUT ─────────────────────────────────\n';
    setStreamOutput(header + (log.output || '(empty output)'));
    setDialogTitle('History: ' + log.script_name);
    setStreaming(false);
    setLogDialogOpen(true);
  };

  const filteredScripts = scripts.filter(s =>
    !search || s.name.toLowerCase().includes(search.toLowerCase()) || s.id.toLowerCase().includes(search.toLowerCase())
  );

  const statusIcon = (status: string) => {
    if (status === 'completed') return <CheckCircle sx={{ fontSize: 14, color: '#22c55e' }} />;
    if (status === 'failed' || status === 'timeout') return <Cancel sx={{ fontSize: 14, color: '#ef4444' }} />;
    return <HourglassEmpty sx={{ fontSize: 14, color: '#f59e0b' }} />;
  };

  const successRate = (stats.completed + stats.failed) > 0
    ? Math.round((stats.completed / (stats.completed + stats.failed)) * 100)
    : 100;

  return (
    <Box>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: 2 }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 900, letterSpacing: '-0.03em', mb: 0.5, display: 'flex', alignItems: 'center', gap: 1 }}>
            <Terminal sx={{ fontSize: 32, color: 'primary.main' }} /> Script Runner
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Execute server-side scripts from the allow-list · full audit trail
          </Typography>
        </Box>
        <Button startIcon={<Refresh />} variant="outlined" size="small" onClick={loadData} disabled={loading}>
          Refresh
        </Button>
      </Box>

      {error && <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>{error}</Alert>}

      <Box sx={{ display: 'flex', gap: 2, mb: 3, flexWrap: 'wrap' }}>
        <StatPill label="Scripts"      value={scripts.length}  color="#3b82f6" />
        <StatPill label="Total Runs"   value={stats.total}     color="#8b5cf6" />
        <StatPill label="Completed"    value={stats.completed} color="#22c55e" />
        <StatPill label="Failed"       value={stats.failed}    color="#ef4444" />
        {stats.running > 0 && <StatPill label="Running" value={stats.running} color="#f59e0b" />}
        <StatPill label="Success Rate" value={successRate + '%'} color={successRate >= 80 ? '#22c55e' : '#f59e0b'} />
      </Box>

      <Card sx={{ mb: 3 }}>
        <CardContent sx={{ pb: '12px !important' }}>
          <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <Typography variant="h6" sx={{ fontWeight: 800 }}>Available Scripts</Typography>
            <TextField size="small" placeholder="Filter…" value={search} onChange={e => setSearch(e.target.value)} sx={{ width: 180 }} />
          </Box>
        </CardContent>
        <Divider />
        <TableContainer>
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell sx={{ fontWeight: 700, fontSize: '0.72rem', color: 'text.disabled' }}>SCRIPT</TableCell>
                <TableCell sx={{ fontWeight: 700, fontSize: '0.72rem', color: 'text.disabled' }}>PATH</TableCell>
                <TableCell sx={{ fontWeight: 700, fontSize: '0.72rem', color: 'text.disabled' }}>LAST RUN</TableCell>
                <TableCell sx={{ fontWeight: 700, fontSize: '0.72rem', color: 'text.disabled' }} align="right">ACTION</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {loading ? (
                <TableRow><TableCell colSpan={4} align="center" sx={{ py: 3 }}><CircularProgress size={24} /></TableCell></TableRow>
              ) : filteredScripts.length === 0 ? (
                <TableRow><TableCell colSpan={4} align="center" sx={{ py: 4, color: 'text.disabled' }}>
                  {search ? 'No scripts match your filter' : 'No scripts in allow-list'}
                </TableCell></TableRow>
              ) : (
                filteredScripts.map((script) => {
                  const isRunning = executing === script.id;
                  return (
                    <TableRow key={script.id} hover sx={{ '&:last-child td': { border: 0 } }}>
                      <TableCell>
                        <Typography sx={{ fontWeight: 700, fontSize: '0.85rem' }}>{script.name}</Typography>
                        {script.description && <Typography variant="caption" sx={{ color: 'text.disabled' }}>{script.description}</Typography>}
                      </TableCell>
                      <TableCell>
                        <Typography variant="caption" sx={{ fontFamily: 'monospace', color: 'text.secondary', fontSize: '0.75rem' }}>{script.path}</Typography>
                      </TableCell>
                      <TableCell>
                        {script.last_run ? (
                          <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
                            {statusIcon(script.last_status ?? '')}
                            <Typography variant="caption" sx={{ color: 'text.disabled' }}>{new Date(script.last_run).toLocaleString()}</Typography>
                          </Box>
                        ) : (
                          <Typography variant="caption" sx={{ color: 'text.disabled' }}>Never run</Typography>
                        )}
                      </TableCell>
                      <TableCell align="right">
                        <Tooltip title={isRunning ? 'Executing…' : 'Run ' + script.name}>
                          <span>
                            <Button
                              size="small" variant="contained" color="primary"
                              startIcon={isRunning ? <CircularProgress size={13} color="inherit" /> : <PlayArrow />}
                              onClick={() => handleExecute(script)}
                              disabled={!!executing}
                              sx={{ minWidth: 88, fontSize: '0.72rem' }}
                            >
                              {isRunning ? 'Running' : 'Execute'}
                            </Button>
                          </span>
                        </Tooltip>
                      </TableCell>
                    </TableRow>
                  );
                })
              )}
            </TableBody>
          </Table>
        </TableContainer>
      </Card>

      <Typography variant="h6" sx={{ mb: 2, fontWeight: 800 }}>Execution History</Typography>
      <Card>
        <TableContainer>
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell sx={{ fontWeight: 700, fontSize: '0.72rem', color: 'text.disabled' }}>#</TableCell>
                <TableCell sx={{ fontWeight: 700, fontSize: '0.72rem', color: 'text.disabled' }}>SCRIPT</TableCell>
                <TableCell sx={{ fontWeight: 700, fontSize: '0.72rem', color: 'text.disabled' }}>STATUS</TableCell>
                <TableCell sx={{ fontWeight: 700, fontSize: '0.72rem', color: 'text.disabled' }}>EXIT</TableCell>
                <TableCell sx={{ fontWeight: 700, fontSize: '0.72rem', color: 'text.disabled' }}>DURATION</TableCell>
                <TableCell sx={{ fontWeight: 700, fontSize: '0.72rem', color: 'text.disabled' }}>STARTED</TableCell>
                <TableCell align="right" sx={{ fontWeight: 700, fontSize: '0.72rem', color: 'text.disabled' }}>OUTPUT</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {logs.length === 0 ? (
                <TableRow><TableCell colSpan={7} align="center" sx={{ py: 4, color: 'text.disabled' }}>No executions yet</TableCell></TableRow>
              ) : (
                logs.map((log) => (
                  <TableRow key={log.id} hover sx={{ '&:last-child td': { border: 0 } }}>
                    <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.75rem', color: 'text.disabled' }}>#{log.id}</TableCell>
                    <TableCell sx={{ fontWeight: 600, fontSize: '0.82rem' }}>{log.script_name}</TableCell>
                    <TableCell>
                      <Chip label={log.status} size="small" sx={{
                        fontSize: '0.68rem', height: 20,
                        bgcolor: log.status === 'completed' ? '#22c55e22' : (log.status === 'failed' || log.status === 'timeout') ? '#ef444422' : '#f59e0b22',
                        color:   log.status === 'completed' ? '#22c55e'   : (log.status === 'failed' || log.status === 'timeout') ? '#ef4444'   : '#f59e0b',
                      }} />
                    </TableCell>
                    <TableCell>
                      <Typography variant="caption" sx={{ fontFamily: 'monospace', color: log.exit_code === 0 ? '#22c55e' : '#ef4444', fontWeight: 700 }}>
                        {log.exit_code}
                      </Typography>
                    </TableCell>
                    <TableCell>
                      <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
                        <Timer sx={{ fontSize: 12, color: 'text.disabled' }} />
                        <Typography variant="caption" sx={{ fontFamily: 'monospace', color: 'text.secondary' }}>
                          {log.duration_ms ? (log.duration_ms / 1000).toFixed(2) + 's' : '—'}
                        </Typography>
                      </Box>
                    </TableCell>
                    <TableCell>
                      <Typography variant="caption" sx={{ color: 'text.disabled' }}>{new Date(log.started_at).toLocaleString()}</Typography>
                    </TableCell>
                    <TableCell align="right">
                      <Tooltip title="View output">
                        <IconButton size="small" onClick={() => openLogViewer(log)}>
                          <History sx={{ fontSize: 16 }} />
                        </IconButton>
                      </Tooltip>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </TableContainer>
      </Card>

      <Dialog open={logDialogOpen} onClose={() => { if (!streaming) setLogDialogOpen(false); }} maxWidth="lg" fullWidth>
        <DialogTitle sx={{ display: 'flex', alignItems: 'center', gap: 1, pb: 1 }}>
          <Terminal sx={{ color: 'primary.main' }} />
          {dialogTitle}
          {streaming && <CircularProgress size={16} sx={{ ml: 1 }} />}
        </DialogTitle>
        {streaming && <LinearProgress sx={{ height: 2 }} />}
        <DialogContent dividers sx={{ p: 0 }}>
          <Box
            ref={outputRef}
            component="pre"
            sx={{
              m: 0, p: 2.5,
              backgroundColor: '#0d1117',
              color: '#c9d1d9',
              fontSize: '0.82rem',
              fontFamily: '"Fira Code", "Cascadia Code", monospace',
              minHeight: 300, maxHeight: 500,
              overflowY: 'auto',
              lineHeight: 1.6,
              whiteSpace: 'pre-wrap',
              wordBreak: 'break-all',
              '&::-webkit-scrollbar': { width: 6 },
              '&::-webkit-scrollbar-thumb': { background: 'rgba(255,255,255,0.15)', borderRadius: 3 },
            }}
          >
            {streamOutput || (streaming ? 'Executing…' : 'No output.')}
          </Box>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => navigator.clipboard.writeText(streamOutput)} size="small" disabled={!streamOutput}>Copy</Button>
          <Button onClick={() => setLogDialogOpen(false)} disabled={streaming}>Close</Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
}

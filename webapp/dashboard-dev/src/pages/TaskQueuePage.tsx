import {
  Box, Typography, Card, CardContent, Table, TableBody, TableCell,
  TableContainer, TableHead, TableRow, Chip, IconButton, Button,
  Tooltip, CircularProgress, Alert, Dialog, DialogTitle, DialogContent,
  DialogActions, Divider, TextField, MenuItem, Select, FormControl,
  InputLabel, Tabs, Tab, Badge,
} from '@mui/material';
import {
  Check, Close, Refresh, Add, Assignment, HourglassTop,
  CheckCircle, Cancel, PlayArrow, InfoOutlined,
} from '@mui/icons-material';
import { useState, useEffect, useCallback, useRef } from 'react';
import { useAuth } from '../hooks/useAuth';

const AUTO_REFRESH_MS = 15_000; // 15 seconds

interface Task {
  id: number;
  title: string;
  description: string;
  task_type: string;
  status: 'pending' | 'in_progress' | 'completed' | 'failed' | 'rejected';
  requested_by_user: string;
  approved_by_user: string | null;
  created_at: string;
  updated_at: string;
  payload?: string;
}

function StatPill({ label, value, color }: { label: string; value: number; color: string }) {
  return (
    <Box sx={{ px: 2, py: 1, borderRadius: 1.5, border: '1px solid', borderColor: 'divider', bgcolor: color + '11', minWidth: 80, textAlign: 'center' }}>
      <Typography sx={{ fontSize: '1.3rem', fontWeight: 900, color, lineHeight: 1.1 }}>{value}</Typography>
      <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 600, fontSize: '0.65rem', textTransform: 'uppercase' }}>{label}</Typography>
    </Box>
  );
}

const STATUS_COLOR: Record<string, string> = {
  pending: '#f59e0b', in_progress: '#3b82f6', completed: '#22c55e', failed: '#ef4444', rejected: '#94a3b8',
};

export default function TaskQueuePage() {
  const [tasks, setTasks]         = useState<Task[]>([]);
  const [loading, setLoading]     = useState(true);
  const [error, setError]         = useState<string | null>(null);
  const [acting, setActing]       = useState<number | null>(null);
  const [statusFilter, setStatusFilter] = useState('');
  const [detailTask, setDetailTask]     = useState<Task | null>(null);

  // Submit form state
  const [submitOpen, setSubmitOpen] = useState(false);
  const [formTitle, setFormTitle]   = useState('');
  const [formDesc, setFormDesc]     = useState('');
  const [formType, setFormType]     = useState('script');
  const [formPayload, setFormPayload] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const { user } = useAuth();
  const isAdmin = user?.role === 'admin';
  const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const loadTasks = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const url = '/api/task_queue.php' + (statusFilter ? '?action=list&status=' + statusFilter : '?action=list');
      const res  = await fetch(url);
      const data = await res.json();
      if (data.error) throw new Error(data.error);
      setTasks(data.tasks || []);
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }, [statusFilter]);

  useEffect(() => {
    loadTasks();
    timerRef.current = setInterval(loadTasks, AUTO_REFRESH_MS);
    return () => { if (timerRef.current) clearInterval(timerRef.current); };
  }, [loadTasks]);

  const handleAction = async (taskId: number, action: 'approve' | 'reject') => {
    setActing(taskId);
    try {
      const res = await fetch('/api/task_queue.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, task_id: taskId }),
      });
      const data = await res.json();
      if (data.error) throw new Error(data.error);
      await loadTasks();
    } catch (e: any) {
      setError(e.message);
    } finally {
      setActing(null);
    }
  };

  const handleSubmit = async () => {
    if (!formTitle.trim() || !formType) return;
    setSubmitting(true);
    try {
      let payload: any = {};
      if (formPayload.trim()) {
        try { payload = JSON.parse(formPayload); }
        catch { payload = { raw: formPayload }; }
      }
      const res = await fetch('/api/task_queue.php?action=submit', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'submit', title: formTitle, description: formDesc, task_type: formType, payload }),
      });
      const data = await res.json();
      if (data.error) throw new Error(data.error);
      setSubmitOpen(false);
      setFormTitle(''); setFormDesc(''); setFormPayload(''); setFormType('script');
      await loadTasks();
    } catch (e: any) {
      setError(e.message);
    } finally {
      setSubmitting(false);
    }
  };

  const counts = {
    pending:     tasks.filter(t => t.status === 'pending').length,
    in_progress: tasks.filter(t => t.status === 'in_progress').length,
    completed:   tasks.filter(t => t.status === 'completed').length,
    failed:      tasks.filter(t => t.status === 'failed').length,
  };

  return (
    <Box>
      {/* Header */}
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: 2 }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 900, letterSpacing: '-0.03em', mb: 0.5, display: 'flex', alignItems: 'center', gap: 1 }}>
            <Assignment sx={{ fontSize: 32, color: 'primary.main' }} /> Task Queue
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Delegate sensitive operations for admin approval · full audit trail
          </Typography>
        </Box>
        <Box sx={{ display: 'flex', gap: 1 }}>
          <Button startIcon={<Add />} variant="contained" size="small" onClick={() => setSubmitOpen(true)}>
            Submit Task
          </Button>
          <Button startIcon={<Refresh />} variant="outlined" size="small" onClick={loadTasks} disabled={loading}>
            Refresh
          </Button>
        </Box>
      </Box>

      {error && <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>{error}</Alert>}

      {/* Stats */}
      <Box sx={{ display: 'flex', gap: 2, mb: 3, flexWrap: 'wrap' }}>
        <StatPill label="Pending"     value={counts.pending}     color="#f59e0b" />
        <StatPill label="In Progress" value={counts.in_progress} color="#3b82f6" />
        <StatPill label="Completed"   value={counts.completed}   color="#22c55e" />
        <StatPill label="Failed"      value={counts.failed}      color="#ef4444" />
      </Box>

      {/* Filter */}
      <Box sx={{ mb: 2, display: 'flex', gap: 2, alignItems: 'center' }}>
        <FormControl size="small" sx={{ minWidth: 160 }}>
          <InputLabel>Filter by status</InputLabel>
          <Select
            label="Filter by status"
            value={statusFilter}
            onChange={e => setStatusFilter(e.target.value)}
          >
            <MenuItem value="">All statuses</MenuItem>
            <MenuItem value="pending">Pending</MenuItem>
            <MenuItem value="in_progress">In Progress</MenuItem>
            <MenuItem value="completed">Completed</MenuItem>
            <MenuItem value="failed">Failed</MenuItem>
            <MenuItem value="rejected">Rejected</MenuItem>
          </Select>
        </FormControl>
        <Typography variant="caption" sx={{ color: 'text.disabled' }}>
          {tasks.length} task{tasks.length !== 1 ? 's' : ''} shown
        </Typography>
      </Box>

      {/* Tasks Table */}
      <Card>
        <TableContainer>
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell sx={{ fontWeight: 700, fontSize: '0.72rem', color: 'text.disabled' }}>#</TableCell>
                <TableCell sx={{ fontWeight: 700, fontSize: '0.72rem', color: 'text.disabled' }}>TITLE</TableCell>
                <TableCell sx={{ fontWeight: 700, fontSize: '0.72rem', color: 'text.disabled' }}>TYPE</TableCell>
                <TableCell sx={{ fontWeight: 700, fontSize: '0.72rem', color: 'text.disabled' }}>REQUESTED BY</TableCell>
                <TableCell sx={{ fontWeight: 700, fontSize: '0.72rem', color: 'text.disabled' }}>STATUS</TableCell>
                <TableCell sx={{ fontWeight: 700, fontSize: '0.72rem', color: 'text.disabled' }}>CREATED</TableCell>
                <TableCell align="right" sx={{ fontWeight: 700, fontSize: '0.72rem', color: 'text.disabled' }}>ACTIONS</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {loading ? (
                <TableRow><TableCell colSpan={7} align="center" sx={{ py: 3 }}><CircularProgress size={24} /></TableCell></TableRow>
              ) : tasks.length === 0 ? (
                <TableRow><TableCell colSpan={7} align="center" sx={{ py: 5, color: 'text.disabled' }}>
                  <HourglassTop sx={{ fontSize: 32, mb: 1, display: 'block', mx: 'auto', opacity: 0.3 }} />
                  No tasks in queue{statusFilter ? ' with this status' : ''}
                </TableCell></TableRow>
              ) : (
                tasks.map((task) => {
                  const color = STATUS_COLOR[task.status] ?? '#94a3b8';
                  const isActing = acting === task.id;
                  return (
                    <TableRow key={task.id} hover sx={{ '&:last-child td': { border: 0 } }}>
                      <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.75rem', color: 'text.disabled' }}>#{task.id}</TableCell>
                      <TableCell>
                        <Typography sx={{ fontWeight: 700, fontSize: '0.85rem' }}>{task.title}</Typography>
                        {task.description && (
                          <Typography variant="caption" sx={{ color: 'text.disabled', display: 'block', maxWidth: 280, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                            {task.description}
                          </Typography>
                        )}
                      </TableCell>
                      <TableCell>
                        <Chip label={task.task_type} size="small" sx={{ fontSize: '0.68rem', height: 20, bgcolor: 'rgba(59,130,246,0.12)', color: '#3b82f6' }} />
                      </TableCell>
                      <TableCell>
                        <Typography variant="caption" sx={{ fontWeight: 600 }}>{task.requested_by_user}</Typography>
                      </TableCell>
                      <TableCell>
                        <Chip label={task.status.replace('_', ' ')} size="small" sx={{
                          fontSize: '0.68rem', height: 20,
                          bgcolor: color + '22', color,
                          textTransform: 'capitalize',
                        }} />
                        {task.approved_by_user && (
                          <Typography variant="caption" sx={{ color: 'text.disabled', display: 'block', fontSize: '0.65rem' }}>
                            by {task.approved_by_user}
                          </Typography>
                        )}
                      </TableCell>
                      <TableCell>
                        <Typography variant="caption" sx={{ color: 'text.disabled' }}>
                          {new Date(task.created_at).toLocaleString()}
                        </Typography>
                      </TableCell>
                      <TableCell align="right">
                        <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 0.5 }}>
                          <Tooltip title="View details">
                            <IconButton size="small" onClick={() => setDetailTask(task)}>
                              <InfoOutlined sx={{ fontSize: 16 }} />
                            </IconButton>
                          </Tooltip>
                          {task.status === 'pending' && isAdmin && (
                            <>
                              <Tooltip title="Approve & Execute">
                                <IconButton
                                  size="small" color="success"
                                  onClick={() => handleAction(task.id, 'approve')}
                                  disabled={isActing}
                                >
                                  {isActing ? <CircularProgress size={14} /> : <Check sx={{ fontSize: 16 }} />}
                                </IconButton>
                              </Tooltip>
                              <Tooltip title="Reject">
                                <IconButton
                                  size="small" color="error"
                                  onClick={() => handleAction(task.id, 'reject')}
                                  disabled={isActing}
                                >
                                  <Close sx={{ fontSize: 16 }} />
                                </IconButton>
                              </Tooltip>
                            </>
                          )}
                        </Box>
                      </TableCell>
                    </TableRow>
                  );
                })
              )}
            </TableBody>
          </Table>
        </TableContainer>
      </Card>

      {/* Submit Dialog */}
      <Dialog open={submitOpen} onClose={() => setSubmitOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
          <Add sx={{ color: 'primary.main' }} /> Submit Task for Approval
        </DialogTitle>
        <DialogContent>
          <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2, pt: 1 }}>
            <Alert severity="info" sx={{ fontSize: '0.78rem' }}>
              Tasks require admin approval before execution. Provide full context.
            </Alert>
            <TextField
              label="Title *"
              value={formTitle}
              onChange={e => setFormTitle(e.target.value)}
              fullWidth size="small"
              placeholder="e.g. Clear Varnish cache for homepage"
            />
            <TextField
              label="Description"
              value={formDesc}
              onChange={e => setFormDesc(e.target.value)}
              fullWidth size="small" multiline rows={2}
              placeholder="Why is this needed? What will it do?"
            />
            <FormControl fullWidth size="small">
              <InputLabel>Task Type</InputLabel>
              <Select label="Task Type" value={formType} onChange={e => setFormType(e.target.value)}>
                <MenuItem value="script">Script Execution</MenuItem>
                <MenuItem value="migration">Database Migration</MenuItem>
                <MenuItem value="deploy">Deployment</MenuItem>
                <MenuItem value="maintenance">Maintenance</MenuItem>
                <MenuItem value="other">Other</MenuItem>
              </Select>
            </FormControl>
            <TextField
              label="Payload (JSON)"
              value={formPayload}
              onChange={e => setFormPayload(e.target.value)}
              fullWidth size="small" multiline rows={3}
              placeholder={'{"script_id": "clear_cache", "args": []}'}
              slotProps={{ input: { sx: { fontFamily: 'monospace', fontSize: '0.8rem' } } }}
            />
          </Box>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setSubmitOpen(false)} disabled={submitting}>Cancel</Button>
          <Button
            variant="contained"
            onClick={handleSubmit}
            disabled={submitting || !formTitle.trim()}
            startIcon={submitting ? <CircularProgress size={14} /> : <PlayArrow />}
          >
            Submit
          </Button>
        </DialogActions>
      </Dialog>

      {/* Detail Dialog */}
      <Dialog open={!!detailTask} onClose={() => setDetailTask(null)} maxWidth="sm" fullWidth>
        <DialogTitle sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
          <Assignment sx={{ color: 'primary.main' }} />
          Task #{detailTask?.id} — {detailTask?.title}
        </DialogTitle>
        <DialogContent dividers>
          {detailTask && (
            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1.5 }}>
              {[
                ['Type',         detailTask.task_type],
                ['Status',       detailTask.status],
                ['Requested by', detailTask.requested_by_user],
                ['Approved by',  detailTask.approved_by_user ?? '—'],
                ['Created',      new Date(detailTask.created_at).toLocaleString()],
                ['Updated',      new Date(detailTask.updated_at).toLocaleString()],
              ].map(([k, v]) => (
                <Box key={k} sx={{ display: 'flex', gap: 2 }}>
                  <Typography variant="caption" sx={{ color: 'text.disabled', minWidth: 90 }}>{k}</Typography>
                  <Typography variant="caption" sx={{ fontWeight: 600 }}>{v}</Typography>
                </Box>
              ))}
              {detailTask.description && (
                <>
                  <Divider />
                  <Typography variant="caption" sx={{ color: 'text.disabled' }}>Description</Typography>
                  <Typography variant="body2">{detailTask.description}</Typography>
                </>
              )}
              {detailTask.payload && (
                <>
                  <Divider />
                  <Typography variant="caption" sx={{ color: 'text.disabled' }}>Payload</Typography>
                  <Box component="pre" sx={{
                    m: 0, p: 1.5, bgcolor: '#0d1117', color: '#c9d1d9',
                    borderRadius: 1, fontSize: '0.78rem', fontFamily: 'monospace',
                    overflowX: 'auto', maxHeight: 180,
                  }}>
                    {(() => { try { return JSON.stringify(JSON.parse(detailTask.payload ?? ''), null, 2); } catch { return detailTask.payload; } })()}
                  </Box>
                </>
              )}
            </Box>
          )}
        </DialogContent>
        <DialogActions>
          {detailTask?.status === 'pending' && isAdmin && (
            <>
              <Button
                color="success" variant="outlined" size="small"
                startIcon={<Check />}
                onClick={() => { handleAction(detailTask.id, 'approve'); setDetailTask(null); }}
              >
                Approve
              </Button>
              <Button
                color="error" variant="outlined" size="small"
                startIcon={<Close />}
                onClick={() => { handleAction(detailTask.id, 'reject'); setDetailTask(null); }}
              >
                Reject
              </Button>
            </>
          )}
          <Button onClick={() => setDetailTask(null)}>Close</Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
}

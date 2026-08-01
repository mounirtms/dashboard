import {
  Box, Typography, Card, Button, Chip, IconButton, Tooltip,
  Snackbar, Alert, Dialog, DialogTitle, DialogContent, DialogActions,
  TextField, MenuItem, Select, FormControl, InputLabel, Menu, Avatar,
  Badge, LinearProgress, Divider, Stack, Popover, Paper, CircularProgress,
} from '@mui/material';
import {
  DataGrid, GridColDef, GridRenderCellParams, GridRowParams,
} from '@mui/x-data-grid';
import {
  Add, Edit, Delete, CheckCircle, Refresh, FilterList, MoreVert, Notes,
  Download, Person, Schedule, Send, AssignmentInd, Flag, AccessTime,
  TrendingUp, Clear, Close, RadioButtonUnchecked,
} from '@mui/icons-material';
import { useState, useEffect, useCallback, useRef, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  fetchTasks, createTask, updateTask, deleteTask, fetchTaskStats,
  fetchTaskNotesCount, bulkUpdate, type Task, type TaskStats,
  getTaskStatusColor, getTaskPriorityColor,
  TASK_CATEGORIES, TASK_STATUSES, TASK_PRIORITIES, type TaskFilters,
} from '../api/tasks';
import { fetchUsers, type User } from '../api/users';
import LoadingState from '../components/common/LoadingState';
import StatusBadge from '../components/common/StatusBadge';
import { usePermissions } from '../hooks/usePermissions';
import { useAuth } from '../hooks/useAuth';

/* ─── colour palette ──────────────────────────────────────────────────────── */
const PRIORITY_META: Record<string, { color: string; bg: string; label: string }> = {
  high:     { color: '#ef4444', bg: 'rgba(239,68,68,0.12)',   label: 'HIGH' },
  medium:   { color: '#f59e0b', bg: 'rgba(245,158,11,0.12)',  label: 'MED'  },
  low:      { color: '#64748b', bg: 'rgba(100,116,139,0.1)',  label: 'LOW'  },
  critical: { color: '#dc2626', bg: 'rgba(220,38,38,0.15)',   label: 'CRIT' },
};

const STATUS_META: Record<string, { color: string; bg: string; dot: string }> = {
  'pending':     { color: '#94a3b8', bg: 'rgba(148,163,184,0.1)', dot: '#94a3b8' },
  'in-progress': { color: '#3b82f6', bg: 'rgba(59,130,246,0.1)',  dot: '#3b82f6' },
  'completed':   { color: '#22c55e', bg: 'rgba(34,197,94,0.1)',   dot: '#22c55e' },
  'cancelled':   { color: '#ef4444', bg: 'rgba(239,68,68,0.1)',   dot: '#ef4444' },
};

const CATEGORY_COLORS: Record<string, string> = {
  general:       '#64748b',
  development:   '#6366f1',
  design:        '#ec4899',
  testing:       '#f59e0b',
  documentation: '#06b6d4',
  maintenance:   '#8b5cf6',
};

/* ─── helpers ────────────────────────────────────────────────────────────── */
function relativeTime(dateStr: string) {
  const d = new Date(dateStr);
  const diff = Date.now() - d.getTime();
  const mins  = Math.floor(diff / 60_000);
  const hours = Math.floor(diff / 3_600_000);
  const days  = Math.floor(diff / 86_400_000);
  if (mins < 2)   return 'just now';
  if (hours < 1)  return `${mins}m ago`;
  if (hours < 24) return `${hours}h ago`;
  if (days < 7)   return `${days}d ago`;
  return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

function dueDateInfo(task: Task): { label: string; color: string; urgent: boolean } | null {
  if (!task.due_date) return null;
  if (task.status === 'completed' || task.status === 'cancelled') return null;
  const days = Math.ceil((new Date(task.due_date).getTime() - Date.now()) / 86_400_000);
  if (days < 0) return { label: `${Math.abs(days)}d overdue`, color: '#ef4444', urgent: true };
  if (days === 0) return { label: 'due today', color: '#f59e0b', urgent: true };
  if (days <= 3) return { label: `${days}d left`, color: '#f59e0b', urgent: false };
  return { label: `${days}d`, color: '#64748b', urgent: false };
}

function avatarColor(name: string) {
  const colors = ['#6366f1','#8b5cf6','#ec4899','#0ea5e9','#22c55e','#f59e0b','#ef4444'];
  let hash = 0;
  for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
  return colors[Math.abs(hash) % colors.length];
}

/* ─── Inline Dispatch Popover ────────────────────────────────────────────── */
interface DispatchPopoverProps {
  task: Task;
  users: User[];
  anchorEl: HTMLElement | null;
  onClose: () => void;
  onDispatch: (taskId: number, username: string) => Promise<void>;
}
function DispatchPopover({ task, users, anchorEl, onClose, onDispatch }: DispatchPopoverProps) {
  const [saving, setSaving] = useState(false);
  const [selected, setSelected] = useState(task.assigned_to || '');

  const handleSave = async () => {
    setSaving(true);
    await onDispatch(task.id, selected);
    setSaving(false);
    onClose();
  };

  return (
    <Popover
      open={Boolean(anchorEl)}
      anchorEl={anchorEl}
      onClose={onClose}
      anchorOrigin={{ vertical: 'bottom', horizontal: 'left' }}
      transformOrigin={{ vertical: 'top', horizontal: 'left' }}
      slotProps={{ paper: { sx: { width: 260, p: 1.5, background: '#0f172a', border: '1px solid #1e293b', borderRadius: 2 } } }}
    >
      <Typography variant="caption" sx={{ color: 'text.secondary', fontWeight: 700, letterSpacing: '0.05em', display: 'block', mb: 1 }}>
        DISPATCH: {task.title.length > 28 ? task.title.slice(0, 28) + '…' : task.title}
      </Typography>
      <FormControl fullWidth size="small" sx={{ mb: 1.5 }}>
        <Select
          value={selected}
          displayEmpty
          onChange={(e) => setSelected(e.target.value)}
          sx={{ fontSize: '0.78rem' }}
        >
          <MenuItem value=""><em>— Unassigned —</em></MenuItem>
          {users.filter(u => u.is_active).map(u => (
            <MenuItem key={u.id} value={u.username}>
              <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                <Avatar sx={{ width: 22, height: 22, fontSize: '0.6rem', bgcolor: avatarColor(u.username) }}>
                  {u.username.charAt(0).toUpperCase()}
                </Avatar>
                <Box>
                  <Typography variant="body2" sx={{ fontSize: '0.78rem', lineHeight: 1.2 }}>
                    {u.full_name || u.username}
                  </Typography>
                  <Typography variant="caption" sx={{ color: 'text.secondary', fontSize: '0.62rem' }}>
                    {u.role}
                  </Typography>
                </Box>
              </Box>
            </MenuItem>
          ))}
        </Select>
      </FormControl>
      <Box sx={{ display: 'flex', gap: 1 }}>
        <Button size="small" variant="outlined" onClick={onClose} fullWidth sx={{ fontSize: '0.72rem' }}>Cancel</Button>
        <Button
          size="small" variant="contained" onClick={handleSave}
          disabled={saving} fullWidth sx={{ fontSize: '0.72rem' }}
          startIcon={saving ? <CircularProgress size={10} /> : <Send sx={{ fontSize: 13 }} />}
        >
          Dispatch
        </Button>
      </Box>
    </Popover>
  );
}

/* ═══════════════════════════════════════════════════════════════════════════ */
export default function TasksPage() {
  const navigate = useNavigate();
  const { user } = useAuth();
  const { permissions } = usePermissions();
  const currentUsername = user?.username || '';
  const isAdmin = user?.role === 'admin' || !!permissions?.can_update_any_task;

  /* ── state ── */
  const [tasks, setTasks] = useState<Task[]>([]);
  const [users, setUsers] = useState<User[]>([]);
  const [notesCount, setNotesCount] = useState<Record<number, number>>({});
  const [stats, setStats] = useState<TaskStats>({ total: 0, completed: 0, in_progress: 0, pending: 0, cancelled: 0 });
  const [loading, setLoading] = useState(true);
  const [snackbar, setSnackbar] = useState({ open: false, message: '', severity: 'success' as 'success' | 'error' | 'warning' | 'info' });
  const showSnack = (message: string, severity: typeof snackbar.severity = 'success') =>
    setSnackbar({ open: true, message, severity });

  /* ── filters ── */
  const [search, setSearch] = useState('');
  const [filterStatus, setFilterStatus] = useState('');
  const [filterPriority, setFilterPriority] = useState('');
  const [filterCategory, setFilterCategory] = useState('');
  const [filterAssignee, setFilterAssignee] = useState('');
  const [showOverdueOnly, setShowOverdueOnly] = useState(false);
  // myTasksOnly defaults to true for non-admins; isAdmin may not be stable at init time
  // so we use a separate effect to sync once permissions are resolved
  const [myTasksOnly, setMyTasksOnly] = useState(true);
  const [pagination, setPagination] = useState({ page: 1, perPage: 25, total: 0, totalPages: 0 });

  // Sync myTasksOnly default once we know the user role — admins see all by default
  const adminKnown = useRef(false);
  useEffect(() => {
    if (permissions !== null && !adminKnown.current) {
      adminKnown.current = true;
      if (isAdmin) setMyTasksOnly(false);
    }
  }, [permissions, isAdmin]); // eslint-disable-line react-hooks/exhaustive-deps

  /* ── bulk ── */
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [bulkUpdating, setBulkUpdating] = useState(false);

  /* ── dispatch popover ── */
  const [dispatchState, setDispatchState] = useState<{ task: Task | null; anchor: HTMLElement | null }>({ task: null, anchor: null });

  /* ── dialogs ── */
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingTask, setEditingTask] = useState<Task | null>(null);
  const [deleteDialog, setDeleteDialog] = useState<{ open: boolean; id?: number; title?: string }>({ open: false });
  const [duplicateWarning, setDuplicateWarning] = useState<{ show: boolean; existingId: number | null }>({ show: false, existingId: null });
  const [formData, setFormData] = useState({
    title: '', description: '', priority: 'medium' as Task['priority'],
    status: 'pending' as Task['status'], assigned_to: '', due_date: '', category: 'general',
  });

  /* ── more menu ── */
  const [moreAnchor, setMoreAnchor] = useState<null | HTMLElement>(null);
  const [loadError, setLoadError] = useState<string | null>(null);

  /* ─── data load ──────────────────────────────────────────────────────────── */
  const loadData = useCallback(async () => {
    setLoading(true);
    setLoadError(null);
    try {
      const filters: TaskFilters = {
        page: pagination.page,
        per_page: pagination.perPage,
        sort_field: 'created_at',
        sort_direction: 'desc',
      };
      if (filterStatus)   filters.status      = filterStatus;
      if (filterPriority) filters.priority     = filterPriority;
      if (filterCategory) filters.category     = filterCategory;
      if (myTasksOnly)    filters.assigned_to  = currentUsername;
      else if (filterAssignee) filters.assigned_to = filterAssignee;
      if (search)         filters.search       = search;
      if (showOverdueOnly) filters.overdue     = true;

      const [result, s, u, nc] = await Promise.all([
        fetchTasks(filters),
        fetchTaskStats(),
        fetchUsers(),
        fetchTaskNotesCount(),
      ]);
      setTasks(result.tasks);
      setPagination(prev => ({ ...prev, total: result.total, totalPages: result.total_pages }));
      setStats(s);
      setUsers(u);
      setNotesCount(nc);
    } catch (e: any) {
      setLoadError(e.response?.data?.error || e.message || 'Failed to load tasks');
    } finally {
      setLoading(false);
    }
  }, [filterStatus, filterPriority, filterCategory, filterAssignee, myTasksOnly,
      showOverdueOnly, search, pagination.page, pagination.perPage, currentUsername]);

  useEffect(() => { loadData(); }, [loadData]);

  /* ─── task actions ───────────────────────────────────────────────────────── */
  const openCreate = () => {
    setEditingTask(null);
    setFormData({ title: '', description: '', priority: 'medium', status: 'pending', assigned_to: '', due_date: '', category: 'general' });
    setDialogOpen(true);
  };

  const openEdit = (task: Task) => {
    setEditingTask(task);
    setFormData({
      title: task.title, description: task.description || '',
      priority: task.priority, status: task.status,
      assigned_to: task.assigned_to, due_date: task.due_date || '',
      category: task.category,
    });
    setDialogOpen(true);
  };

  const handleSave = async (forceCreate = false) => {
    if (!formData.title.trim()) { showSnack('Title is required', 'error'); return; }
    try {
      if (editingTask) {
        await updateTask({ id: editingTask.id, ...formData });
        showSnack('Task updated');
      } else {
        const result = await createTask({ ...formData, force_create: forceCreate });
        if (result.duplicate_warning && !forceCreate) {
          setDuplicateWarning({ show: true, existingId: result.existing_task_id });
          return;
        }
        showSnack('Task created');
      }
      setDialogOpen(false);
      setDuplicateWarning({ show: false, existingId: null });
      loadData();
    } catch (e: any) {
      showSnack(e.response?.data?.error || e.message || 'Save failed', 'error');
    }
  };

  const handleDeleteConfirm = async () => {
    const { id } = deleteDialog;
    setDeleteDialog({ open: false });
    if (!id) return;
    try {
      await deleteTask(id);
      showSnack('Task deleted');
      loadData();
    } catch (e: any) {
      showSnack(e.message, 'error');
    }
  };

  const handleToggleComplete = async (task: Task) => {
    try {
      const next = task.status === 'completed' ? 'pending' : 'completed';
      await updateTask({ id: task.id, status: next });
      loadData();
    } catch (e: any) {
      showSnack(e.message, 'error');
    }
  };

  /* ─── dispatch ───────────────────────────────────────────────────────────── */
  const handleDispatch = async (taskId: number, username: string) => {
    try {
      await updateTask({ id: taskId, assigned_to: username });
      showSnack(`Task dispatched${username ? ' to ' + username : ' (unassigned)'}`);
      loadData();
    } catch (e: any) {
      showSnack(e.response?.data?.error || e.message, 'error');
    }
  };

  /* ─── bulk actions ───────────────────────────────────────────────────────── */
  const handleBulkAction = async (action: string, value?: string) => {
    if (selectedIds.length === 0) return;
    setBulkUpdating(true);
    try {
      const fields: Partial<Task> = {};
      if (action === 'status') (fields as any).status = value;
      else if (action === 'priority') (fields as any).priority = value;
      else if (action === 'assigned_to') (fields as any).assigned_to = value;
      await bulkUpdate(selectedIds, fields);
      showSnack(`${selectedIds.length} tasks updated`);
      setSelectedIds([]);
      loadData();
    } catch (e: any) {
      showSnack(e.response?.data?.error || e.message, 'error');
    } finally {
      setBulkUpdating(false);
    }
  };

  /* ─── export ─────────────────────────────────────────────────────────────── */
  const exportCSV = useCallback(() => {
    const headers = ['ID', 'Title', 'Priority', 'Status', 'Assigned To', 'Due Date', 'Category', 'Created By', 'Created At'];
    const rows = tasks.map(t => [
      t.id,
      `"${t.title.replace(/"/g, '""')}"`,
      t.priority, t.status,
      t.assigned_to || 'Unassigned',
      t.due_date || '',
      t.category, t.created_by,
      new Date(t.created_at).toLocaleDateString(),
    ]);
    const csv = [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = `tasks_${new Date().toISOString().slice(0,10)}.csv`; a.click();
    URL.revokeObjectURL(url);
    showSnack(`${tasks.length} tasks exported`);
  }, [tasks]);

  /* ─── grid columns ───────────────────────────────────────────────────────── */
  const columns: GridColDef[] = useMemo(() => [

    /* ── Title ──────────────────────────────────────────────────────────────── */
    {
      field: 'title', headerName: 'Task', flex: 1.6, minWidth: 200,
      renderCell: (p: GridRenderCellParams) => {
        const dd = dueDateInfo(p.row);
        const nc = notesCount[p.row.id] || 0;
        return (
          <Box sx={{ display: 'flex', flexDirection: 'column', justifyContent: 'center', py: 0.5, gap: 0.3, width: '100%' }}>
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.75 }}>
              {/* completion toggle dot */}
              <Tooltip title={p.row.status === 'completed' ? 'Reopen' : 'Mark complete'}>
                <IconButton
                  size="small"
                  sx={{ p: 0.2, color: p.row.status === 'completed' ? '#22c55e' : '#475569' }}
                  onClick={(e) => { e.stopPropagation(); handleToggleComplete(p.row); }}
                >
                  {p.row.status === 'completed'
                    ? <CheckCircle sx={{ fontSize: 15 }} />
                    : <RadioButtonUnchecked sx={{ fontSize: 15 }} />}
                </IconButton>
              </Tooltip>
              <Typography
                variant="body2"
                sx={{
                  fontWeight: 600, cursor: 'pointer', fontSize: '0.8rem',
                  textDecoration: p.row.status === 'completed' ? 'line-through' : 'none',
                  color: p.row.status === 'completed' ? 'text.disabled' : 'text.primary',
                  overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', flex: 1,
                  '&:hover': { color: 'primary.main' },
                }}
                onClick={() => navigate(`/tasks/${p.row.id}`)}
              >
                {p.value}
              </Typography>
              {nc > 0 && (
                <Tooltip title={`${nc} note${nc > 1 ? 's' : ''}`}>
                  <Chip
                    icon={<Notes sx={{ fontSize: 10, mr: '-4px' }} />}
                    label={nc}
                    size="small"
                    onClick={(e) => { e.stopPropagation(); navigate(`/tasks/${p.row.id}?tab=notes`); }}
                    sx={{ height: 16, fontSize: '0.6rem', fontWeight: 700, px: 0.2,
                      bgcolor: 'rgba(59,130,246,0.12)', color: '#3b82f6',
                      cursor: 'pointer', '& .MuiChip-label': { px: 0.4 } }}
                  />
                </Tooltip>
              )}
            </Box>
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.75, pl: 0.25 }}>
              <Chip
                label={p.row.category}
                size="small"
                sx={{
                  height: 13, fontSize: '0.55rem', fontWeight: 700,
                  bgcolor: `${CATEGORY_COLORS[p.row.category] || '#64748b'}18`,
                  color: CATEGORY_COLORS[p.row.category] || '#64748b',
                  textTransform: 'capitalize', '& .MuiChip-label': { px: 0.6 },
                }}
              />
              {dd && (
                <Typography variant="caption" sx={{ fontSize: '0.6rem', color: dd.color, fontWeight: dd.urgent ? 700 : 500 }}>
                  <Schedule sx={{ fontSize: 9, mr: 0.3, verticalAlign: 'middle' }} />
                  {dd.label}
                </Typography>
              )}
              <Typography variant="caption" sx={{ fontSize: '0.58rem', color: 'text.disabled' }}>
                #{p.row.id}
              </Typography>
            </Box>
          </Box>
        );
      },
    },

    /* ── Priority ────────────────────────────────────────────────────────────── */
    {
      field: 'priority', headerName: 'Priority', width: 82,
      renderCell: (p: GridRenderCellParams) => {
        const m = PRIORITY_META[p.value] || PRIORITY_META.low;
        return (
          <Box sx={{
            display: 'inline-flex', alignItems: 'center', gap: 0.4,
            bgcolor: m.bg, color: m.color,
            px: 0.8, py: 0.2, borderRadius: 1,
            fontSize: '0.62rem', fontWeight: 800, letterSpacing: '0.04em',
          }}>
            <Flag sx={{ fontSize: 10 }} />
            {m.label}
          </Box>
        );
      },
    },

    /* ── Status ──────────────────────────────────────────────────────────────── */
    {
      field: 'status', headerName: 'Status', width: 118,
      renderCell: (p: GridRenderCellParams) => {
        const m = STATUS_META[p.value] || STATUS_META['pending'];
        const label = p.value === 'in-progress' ? 'In Progress'
                    : p.value.charAt(0).toUpperCase() + p.value.slice(1);
        return (
          <Box sx={{
            display: 'inline-flex', alignItems: 'center', gap: 0.5,
            bgcolor: m.bg, color: m.color,
            px: 0.9, py: 0.25, borderRadius: 1,
            fontSize: '0.68rem', fontWeight: 700,
          }}>
            <Box sx={{ width: 6, height: 6, borderRadius: '50%', bgcolor: m.dot, flexShrink: 0 }} />
            {label}
          </Box>
        );
      },
    },

    /* ── Assigned To ─────────────────────────────────────────────────────────── */
    {
      field: 'assigned_to', headerName: 'Assignee', width: 130,
      renderCell: (p: GridRenderCellParams) => {
        const username = p.value as string;
        if (!username) {
          return (
            <Typography variant="caption" sx={{ color: 'text.disabled', fontSize: '0.7rem', fontStyle: 'italic' }}>
              Unassigned
            </Typography>
          );
        }
        const u = users.find(x => x.username === username);
        const displayName = u?.full_name || username;
        return (
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.75 }}>
            <Avatar sx={{ width: 22, height: 22, fontSize: '0.6rem', bgcolor: avatarColor(username) }}>
              {username.charAt(0).toUpperCase()}
            </Avatar>
            <Box>
              <Typography variant="caption" sx={{ fontWeight: 600, fontSize: '0.7rem', lineHeight: 1.2, display: 'block' }}>
                {displayName.length > 12 ? displayName.slice(0, 12) + '…' : displayName}
              </Typography>
              {u?.role && (
                <Typography variant="caption" sx={{ color: 'text.disabled', fontSize: '0.58rem', lineHeight: 1 }}>
                  {u.role}
                </Typography>
              )}
            </Box>
          </Box>
        );
      },
    },

    /* ── Created By ──────────────────────────────────────────────────────────── */
    {
      field: 'created_by', headerName: 'Creator', width: 100,
      renderCell: (p: GridRenderCellParams) => {
        const username = p.value as string;
        if (!username) return <Typography variant="caption" sx={{ color: 'text.disabled' }}>—</Typography>;
        const isSelf = username === currentUsername;
        return (
          <Tooltip title={isSelf ? 'You' : username}>
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
              <Avatar sx={{ width: 18, height: 18, fontSize: '0.55rem',
                bgcolor: isSelf ? 'primary.main' : avatarColor(username),
                outline: isSelf ? '2px solid' : 'none',
                outlineColor: 'primary.main', outlineOffset: 1,
              }}>
                {username.charAt(0).toUpperCase()}
              </Avatar>
              <Typography variant="caption" sx={{ fontSize: '0.68rem', color: isSelf ? 'primary.main' : 'text.secondary' }}>
                {isSelf ? 'You' : (username.length > 9 ? username.slice(0, 9) + '…' : username)}
              </Typography>
            </Box>
          </Tooltip>
        );
      },
    },

    /* ── Due Date ────────────────────────────────────────────────────────────── */
    {
      field: 'due_date', headerName: 'Due', width: 96,
      renderCell: (p: GridRenderCellParams) => {
        const val = p.value as string | null;
        if (!val) return <Typography variant="caption" sx={{ color: 'text.disabled', fontSize: '0.68rem' }}>—</Typography>;
        const dd = dueDateInfo(p.row);
        const display = new Date(val).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
        return (
          <Tooltip title={new Date(val).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' })}>
            <Typography variant="caption" sx={{
              fontSize: '0.68rem',
              fontWeight: dd?.urgent ? 700 : 500,
              color: dd ? dd.color : 'text.secondary',
            }}>
              {dd?.urgent && <Schedule sx={{ fontSize: 9, mr: 0.3, verticalAlign: 'middle' }} />}
              {display}
            </Typography>
          </Tooltip>
        );
      },
    },

    /* ── Age ─────────────────────────────────────────────────────────────────── */
    {
      field: 'created_at', headerName: 'Age', width: 82,
      renderCell: (p: GridRenderCellParams) => {
        const rel = relativeTime(p.value as string);
        const isRecent = Date.now() - new Date(p.value as string).getTime() < 3_600_000;
        return (
          <Tooltip title={new Date(p.value as string).toLocaleString()}>
            <Typography variant="caption" sx={{
              fontSize: '0.68rem',
              color: isRecent ? '#22c55e' : 'text.secondary',
              fontWeight: isRecent ? 700 : 400,
            }}>
              <AccessTime sx={{ fontSize: 10, mr: 0.3, verticalAlign: 'middle' }} />
              {rel}
            </Typography>
          </Tooltip>
        );
      },
    },

    /* ── Actions ─────────────────────────────────────────────────────────────── */
    {
      field: 'actions', headerName: '', width: isAdmin ? 110 : 84,
      sortable: false, disableColumnMenu: true,
      renderCell: (p: GridRenderCellParams) => {
        const isOwner  = p.row.created_by === currentUsername || p.row.assigned_to === currentUsername;
        const canEdit  = isOwner || isAdmin;
        const canDel   = !!permissions?.can_delete_tasks;
        return (
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.3 }}>
            {/* Dispatch button — admin only */}
            {isAdmin && (
              <Tooltip title="Dispatch / Reassign">
                <IconButton
                  size="small"
                  sx={{ p: 0.4, color: '#3b82f6', '&:hover': { bgcolor: 'rgba(59,130,246,0.1)' } }}
                  onClick={(e) => {
                    e.stopPropagation();
                    setDispatchState({ task: p.row, anchor: e.currentTarget as HTMLElement });
                  }}
                >
                  <AssignmentInd sx={{ fontSize: 15 }} />
                </IconButton>
              </Tooltip>
            )}
            {canEdit && (
              <Tooltip title="Edit">
                <IconButton size="small" sx={{ p: 0.4 }} onClick={(e) => { e.stopPropagation(); openEdit(p.row); }}>
                  <Edit sx={{ fontSize: 15 }} />
                </IconButton>
              </Tooltip>
            )}
            {canDel && (
              <Tooltip title="Delete">
                <IconButton
                  size="small" color="error" sx={{ p: 0.4 }}
                  onClick={(e) => { e.stopPropagation(); setDeleteDialog({ open: true, id: p.row.id, title: p.row.title }); }}
                >
                  <Delete sx={{ fontSize: 15 }} />
                </IconButton>
              </Tooltip>
            )}
          </Box>
        );
      },
    },
  // eslint-disable-next-line react-hooks/exhaustive-deps
  ], [tasks, users, notesCount, isAdmin, currentUsername, permissions]);

  /* ─── row style ──────────────────────────────────────────────────────────── */
  const getRowClassName = useCallback((params: GridRowParams) => {
    const t = params.row as Task;
    if (t.status === 'completed') return 'row-completed';
    if (t.status === 'cancelled') return 'row-cancelled';
    const dd = dueDateInfo(t);
    if (dd?.urgent) return 'row-overdue';
    if ((t.priority as string) === 'high' || (t.priority as string) === 'critical') return 'row-high';
    return '';
  }, []);

  /* ─── render ─────────────────────────────────────────────────────────────── */
  const completedPct = stats.total > 0 ? Math.round((stats.completed / stats.total) * 100) : 0;

  if (loading && tasks.length === 0) return <LoadingState message="Loading tasks…" />;

  return (
    <Box sx={{ height: '100%', display: 'flex', flexDirection: 'column', gap: 1.5 }}>

      {/* ── Header ──────────────────────────────────────────────────────────── */}
      <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', lineHeight: 1.1 }}>
            Tasks
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary', mt: 0.3 }}>
            {stats.total} total · {stats.pending} pending · {stats.in_progress} in progress · {completedPct}% complete
          </Typography>
        </Box>
        <Box sx={{ display: 'flex', gap: 1, alignItems: 'center' }}>
          {isAdmin && (
            <Chip
              icon={<Flag sx={{ fontSize: 13 }} />}
              label="Admin View"
              size="small"
              color="error"
              variant="outlined"
              sx={{ fontSize: '0.68rem', height: 22 }}
            />
          )}
          <Button size="small" variant="outlined" startIcon={<Refresh />} onClick={loadData} disabled={loading}>
            Sync
          </Button>
          {permissions?.can_create_tasks && (
            <Button size="small" variant="contained" startIcon={<Add />} onClick={openCreate}>
              New Task
            </Button>
          )}
          <Tooltip title="More">
            <IconButton size="small" onClick={(e) => setMoreAnchor(e.currentTarget)}>
              <MoreVert sx={{ fontSize: 18 }} />
            </IconButton>
          </Tooltip>
          <Menu anchorEl={moreAnchor} open={Boolean(moreAnchor)} onClose={() => setMoreAnchor(null)}>
            <MenuItem onClick={() => { setMoreAnchor(null); loadData(); }}>
              <Refresh sx={{ fontSize: 15, mr: 1 }} /> Refresh
            </MenuItem>
            <MenuItem onClick={() => { setMoreAnchor(null); exportCSV(); }}>
              <Download sx={{ fontSize: 15, mr: 1 }} /> Export CSV
            </MenuItem>
          </Menu>
        </Box>
      </Box>

      {/* ── Stats mini-bar ──────────────────────────────────────────────────── */}
      <Box sx={{ display: 'flex', gap: 1 }}>
        {[
          { label: 'Total', value: stats.total,       color: '#e2e8f0', bg: 'rgba(226,232,240,0.06)' },
          { label: 'Pending', value: stats.pending,   color: '#94a3b8', bg: 'rgba(148,163,184,0.06)' },
          { label: 'In Progress', value: stats.in_progress, color: '#3b82f6', bg: 'rgba(59,130,246,0.08)' },
          { label: 'Completed', value: stats.completed, color: '#22c55e', bg: 'rgba(34,197,94,0.08)' },
          { label: 'Cancelled', value: stats.cancelled, color: '#ef4444', bg: 'rgba(239,68,68,0.07)' },
        ].map(s => (
          <Card
            key={s.label}
            onClick={() => {
              if (s.label === 'Pending') setFilterStatus('pending');
              else if (s.label === 'In Progress') setFilterStatus('in-progress');
              else if (s.label === 'Completed') setFilterStatus('completed');
              else if (s.label === 'Cancelled') setFilterStatus('cancelled');
              else setFilterStatus('');
            }}
            sx={{ flex: 1, px: 1.5, py: 0.75, bgcolor: s.bg, border: '1px solid #1e293b',
              cursor: 'pointer', transition: 'all 0.15s',
              '&:hover': { borderColor: s.color, transform: 'translateY(-1px)' },
            }}
          >
            <Typography variant="caption" sx={{ color: s.color, fontWeight: 600, fontSize: '0.6rem', letterSpacing: '0.06em' }}>
              {s.label.toUpperCase()}
            </Typography>
            <Typography sx={{ fontWeight: 800, color: s.color, fontSize: '1.25rem', lineHeight: 1.1 }}>
              {s.value}
            </Typography>
          </Card>
        ))}
        {/* Progress bar card */}
        <Card sx={{ flex: 1.5, px: 1.5, py: 0.75, border: '1px solid #1e293b', bgcolor: 'rgba(34,197,94,0.04)' }}>
          <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 0.5 }}>
            <Typography variant="caption" sx={{ color: '#22c55e', fontWeight: 600, fontSize: '0.6rem', letterSpacing: '0.06em' }}>
              COMPLETION
            </Typography>
            <Typography variant="caption" sx={{ color: '#22c55e', fontWeight: 800, fontSize: '0.75rem' }}>
              {completedPct}%
            </Typography>
          </Box>
          <LinearProgress
            variant="determinate"
            value={completedPct}
            sx={{
              height: 6, borderRadius: 3,
              bgcolor: 'rgba(255,255,255,0.05)',
              '& .MuiLinearProgress-bar': { bgcolor: '#22c55e', borderRadius: 3 },
            }}
          />
          <Typography variant="caption" sx={{ color: 'text.disabled', fontSize: '0.58rem', mt: 0.4, display: 'block' }}>
            {stats.completed} of {stats.total} tasks done
          </Typography>
        </Card>
      </Box>

      {/* ── Compact Toolbar ─────────────────────────────────────────────────── */}
      <Card sx={{ py: 0.75, px: 1.5, bgcolor: 'rgba(255,255,255,0.02)', border: '1px solid #1e293b' }}>
        <Box sx={{ display: 'flex', gap: 0.75, alignItems: 'center', flexWrap: 'wrap' }}>

          {/* My Tasks toggle */}
          <Chip
            label="My Tasks"
            icon={<Person sx={{ fontSize: 13 }} />}
            onClick={() => setMyTasksOnly(!myTasksOnly)}
            color={myTasksOnly ? 'primary' : 'default'}
            variant={myTasksOnly ? 'filled' : 'outlined'}
            clickable size="small"
            sx={{ fontSize: '0.7rem', height: 24 }}
          />

          {/* Search */}
          <TextField
            size="small" placeholder="Search tasks…" value={search}
            onChange={(e) => setSearch(e.target.value)}
            sx={{ width: 170, '& .MuiInputBase-root': { fontSize: '0.75rem', height: 28 } }}
            slotProps={{
              input: {
                endAdornment: search
                  ? <IconButton size="small" sx={{ p: 0.1 }} onClick={() => setSearch('')}><Clear sx={{ fontSize: 13 }} /></IconButton>
                  : undefined,
              }
            }}
          />

          {/* Status */}
          <FormControl size="small" sx={{ minWidth: 115 }}>
            <Select value={filterStatus} displayEmpty onChange={(e) => setFilterStatus(e.target.value)}
              sx={{ fontSize: '0.72rem', height: 28 }}>
              <MenuItem value="">All Status</MenuItem>
              <MenuItem value="pending">⏳ Pending</MenuItem>
              <MenuItem value="in-progress">🔄 In Progress</MenuItem>
              <MenuItem value="completed">✅ Completed</MenuItem>
              <MenuItem value="cancelled">❌ Cancelled</MenuItem>
            </Select>
          </FormControl>

          {/* Priority */}
          <FormControl size="small" sx={{ minWidth: 115 }}>
            <Select value={filterPriority} displayEmpty onChange={(e) => setFilterPriority(e.target.value)}
              sx={{ fontSize: '0.72rem', height: 28 }}>
              <MenuItem value="">All Priority</MenuItem>
              <MenuItem value="critical">🚨 Critical</MenuItem>
              <MenuItem value="high">🔴 High</MenuItem>
              <MenuItem value="medium">🟡 Medium</MenuItem>
              <MenuItem value="low">🔵 Low</MenuItem>
            </Select>
          </FormControl>

          {/* Category */}
          <FormControl size="small" sx={{ minWidth: 125 }}>
            <Select value={filterCategory} displayEmpty onChange={(e) => setFilterCategory(e.target.value)}
              sx={{ fontSize: '0.72rem', height: 28 }}>
              <MenuItem value="">All Categories</MenuItem>
              {TASK_CATEGORIES.map(cat => (
                <MenuItem key={cat.value} value={cat.value}>
                  <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
                    <Box sx={{ width: 7, height: 7, borderRadius: '50%', bgcolor: CATEGORY_COLORS[cat.value] }} />
                    {cat.label}
                  </Box>
                </MenuItem>
              ))}
            </Select>
          </FormControl>

          {/* Assignee — only if not myTasksOnly */}
          {!myTasksOnly && (
            <FormControl size="small" sx={{ minWidth: 120 }}>
              <Select value={filterAssignee} displayEmpty onChange={(e) => setFilterAssignee(e.target.value)}
                sx={{ fontSize: '0.72rem', height: 28 }}>
                <MenuItem value="">All Assignees</MenuItem>
                {users.filter(u => u.is_active).map(u => (
                  <MenuItem key={u.username} value={u.username}>{u.full_name || u.username}</MenuItem>
                ))}
              </Select>
            </FormControl>
          )}

          {/* Overdue */}
          <Chip
            label="Overdue"
            icon={<Schedule sx={{ fontSize: 13 }} />}
            onClick={() => setShowOverdueOnly(!showOverdueOnly)}
            color={showOverdueOnly ? 'error' : 'default'}
            variant={showOverdueOnly ? 'filled' : 'outlined'}
            clickable size="small"
            sx={{ fontSize: '0.7rem', height: 24 }}
          />

          {/* Clear filters */}
          <Tooltip title="Clear all filters">
            <IconButton size="small" onClick={() => {
              setSearch(''); setFilterStatus(''); setFilterPriority('');
              setFilterCategory(''); setFilterAssignee('');
              setMyTasksOnly(!isAdmin); setShowOverdueOnly(false);
            }}>
              <FilterList sx={{ fontSize: 16 }} />
            </IconButton>
          </Tooltip>

          <Box sx={{ flexGrow: 1 }} />

          {/* Active filters count badge */}
          {[filterStatus, filterPriority, filterCategory, filterAssignee, search].filter(Boolean).length > 0 && (
            <Chip
              label={`${[filterStatus, filterPriority, filterCategory, filterAssignee, search].filter(Boolean).length} filter${[filterStatus, filterPriority, filterCategory, filterAssignee, search].filter(Boolean).length > 1 ? 's' : ''} active`}
              size="small"
              color="warning"
              variant="outlined"
              sx={{ fontSize: '0.65rem', height: 22 }}
            />
          )}
        </Box>
      </Card>

      {/* ── Bulk Action Bar ──────────────────────────────────────────────────── */}
      {selectedIds.length > 0 && (
        <Card sx={{ py: 0.75, px: 1.5, bgcolor: 'rgba(59,130,246,0.07)', border: '1px solid rgba(59,130,246,0.25)' }}>
          <Box sx={{ display: 'flex', gap: 1, alignItems: 'center', flexWrap: 'wrap' }}>
            <Typography variant="caption" sx={{ fontWeight: 800, color: 'primary.main', minWidth: 70 }}>
              {selectedIds.length} selected
            </Typography>
            <Divider orientation="vertical" flexItem sx={{ mx: 0.5 }} />

            <Typography variant="caption" sx={{ color: 'text.secondary' }}>Status:</Typography>
            {(['in-progress', 'completed', 'cancelled'] as const).map(s => (
              <Button key={s} size="small" variant="outlined"
                color={s === 'completed' ? 'success' : s === 'cancelled' ? 'error' : 'info'}
                onClick={() => handleBulkAction('status', s)} disabled={bulkUpdating}
                sx={{ fontSize: '0.68rem', py: 0.2, px: 0.8, height: 24 }}>
                {s === 'in-progress' ? 'In Progress' : s.charAt(0).toUpperCase() + s.slice(1)}
              </Button>
            ))}
            <Divider orientation="vertical" flexItem sx={{ mx: 0.5 }} />

            <Typography variant="caption" sx={{ color: 'text.secondary' }}>Priority:</Typography>
            {(['high', 'medium', 'low'] as const).map(pri => (
              <Button key={pri} size="small" variant="outlined"
                color={pri === 'high' ? 'error' : pri === 'medium' ? 'warning' : 'inherit'}
                onClick={() => handleBulkAction('priority', pri)} disabled={bulkUpdating}
                sx={{ fontSize: '0.68rem', py: 0.2, px: 0.8, height: 24 }}>
                {pri.charAt(0).toUpperCase() + pri.slice(1)}
              </Button>
            ))}

            {isAdmin && (
              <>
                <Divider orientation="vertical" flexItem sx={{ mx: 0.5 }} />
                <Typography variant="caption" sx={{ color: 'text.secondary' }}>Assign:</Typography>
                <Select
                  size="small" value="__pick__" displayEmpty
                  onChange={(e) => {
                    const val = e.target.value as string;
                    if (val !== '__pick__') handleBulkAction('assigned_to', val);
                  }}
                  sx={{ fontSize: '0.72rem', height: 24, minWidth: 120 }}
                >
                  <MenuItem value="__pick__" disabled>Pick user…</MenuItem>
                  <MenuItem value="">— Unassign —</MenuItem>
                  {users.filter(u => u.is_active).map(u => (
                    <MenuItem key={u.id} value={u.username}>{u.full_name || u.username}</MenuItem>
                  ))}
                </Select>
              </>
            )}
            {bulkUpdating && <CircularProgress size={14} />}
            <IconButton size="small" sx={{ ml: 'auto' }} onClick={() => setSelectedIds([])}>
              <Close sx={{ fontSize: 15 }} />
            </IconButton>
          </Box>
        </Card>
      )}

      {/* ── Load error ───────────────────────────────────────────────────────── */}
      {loadError && (
        <Alert severity="error" action={<Button size="small" color="inherit" onClick={loadData}>Retry</Button>}>
          {loadError}
        </Alert>
      )}

      {/* ── DataGrid ─────────────────────────────────────────────────────────── */}
      <Card sx={{ flexGrow: 1, overflow: 'hidden' }}>
        <DataGrid
          rows={tasks ?? []}
          columns={columns}
          getRowId={(r) => r.id}
          density="comfortable"
          rowHeight={52}
          pageSizeOptions={[10, 25, 50, 100]}
          initialState={{
            pagination: { paginationModel: { pageSize: 25 } },
            sorting: { sortModel: [{ field: 'priority', sort: 'desc' }] },
          }}
          checkboxSelection
          disableRowSelectionOnClick
          onRowSelectionModelChange={(model) => {
            const ids = 'ids' in model ? Array.from(model.ids) : [];
            setSelectedIds(ids as number[]);
          }}
          getRowClassName={getRowClassName}
          onRowDoubleClick={(params) => navigate(`/tasks/${params.id}`)}
          loading={loading}
          sx={{
            border: 'none',
            '& .MuiDataGrid-columnHeader': {
              bgcolor: 'rgba(255,255,255,0.02)',
              fontSize: '0.7rem',
              fontWeight: 700,
              letterSpacing: '0.04em',
              color: 'text.secondary',
            },
            '& .MuiDataGrid-row': {
              transition: 'background 0.12s',
              '&:hover': { bgcolor: 'rgba(255,255,255,0.03)' },
            },
            '& .row-completed': {
              opacity: 0.55,
              '&:hover': { opacity: 0.75 },
            },
            '& .row-cancelled': { opacity: 0.45 },
            '& .row-overdue': {
              bgcolor: 'rgba(239,68,68,0.04)',
              borderLeft: '2px solid #ef4444',
            },
            '& .row-high': {
              borderLeft: '2px solid #ef4444',
            },
            '& .MuiDataGrid-cell': { borderColor: 'rgba(255,255,255,0.04)', alignItems: 'center' },
            '& .MuiDataGrid-footerContainer': { borderColor: 'rgba(255,255,255,0.06)' },
          }}
        />
      </Card>

      {/* ── Dispatch Popover ────────────────────────────────────────────────── */}
      {dispatchState.task && (
        <DispatchPopover
          task={dispatchState.task}
          users={users}
          anchorEl={dispatchState.anchor}
          onClose={() => setDispatchState({ task: null, anchor: null })}
          onDispatch={handleDispatch}
        />
      )}

      {/* ── Create / Edit Dialog ─────────────────────────────────────────────── */}
      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} maxWidth="sm" fullWidth
        slotProps={{ paper: { sx: { bgcolor: '#0f172a', border: '1px solid #1e293b' } } }}>
        <DialogTitle sx={{ pb: 1, fontWeight: 700 }}>
          {editingTask ? `Edit Task #${editingTask.id}` : 'New Task'}
          {editingTask && (
            <Typography variant="caption" sx={{ display: 'block', color: 'text.secondary', mt: 0.3, fontWeight: 400 }}>
              {editingTask.title}
            </Typography>
          )}
        </DialogTitle>
        <DialogContent>
          <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2, mt: 1 }}>
            <TextField
              label="Title" fullWidth required
              value={formData.title} onChange={(e) => setFormData({ ...formData, title: e.target.value })}
              placeholder="Short, clear task title…"
            />
            <TextField
              label="Description" fullWidth multiline rows={3}
              value={formData.description} onChange={(e) => setFormData({ ...formData, description: e.target.value })}
              placeholder="Optional details, context, acceptance criteria…"
            />
            <Box sx={{ display: 'flex', gap: 2 }}>
              <FormControl fullWidth size="small">
                <InputLabel>Priority</InputLabel>
                <Select value={formData.priority} label="Priority"
                  onChange={(e) => setFormData({ ...formData, priority: e.target.value as Task['priority'] })}>
                  <MenuItem value="low"><Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}><Flag sx={{ fontSize: 14, color: '#64748b' }} /> Low</Box></MenuItem>
                  <MenuItem value="medium"><Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}><Flag sx={{ fontSize: 14, color: '#f59e0b' }} /> Medium</Box></MenuItem>
                  <MenuItem value="high"><Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}><Flag sx={{ fontSize: 14, color: '#ef4444' }} /> High</Box></MenuItem>
                  <MenuItem value="critical"><Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}><Flag sx={{ fontSize: 14, color: '#dc2626' }} /> Critical</Box></MenuItem>
                </Select>
              </FormControl>
              <FormControl fullWidth size="small">
                <InputLabel>Status</InputLabel>
                <Select value={formData.status} label="Status"
                  onChange={(e) => setFormData({ ...formData, status: e.target.value as Task['status'] })}>
                  <MenuItem value="pending">⏳ Pending</MenuItem>
                  <MenuItem value="in-progress">🔄 In Progress</MenuItem>
                  <MenuItem value="completed">✅ Completed</MenuItem>
                  <MenuItem value="cancelled">❌ Cancelled</MenuItem>
                </Select>
              </FormControl>
            </Box>
            <Box sx={{ display: 'flex', gap: 2 }}>
              <FormControl fullWidth size="small">
                <InputLabel>Assign To</InputLabel>
                <Select value={formData.assigned_to} label="Assign To"
                  onChange={(e) => setFormData({ ...formData, assigned_to: e.target.value })}>
                  <MenuItem value=""><em>Unassigned</em></MenuItem>
                  {users.filter(u => u.is_active).map(u => (
                    <MenuItem key={u.id} value={u.username}>
                      <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                        <Avatar sx={{ width: 22, height: 22, fontSize: '0.6rem', bgcolor: avatarColor(u.username) }}>
                          {u.username.charAt(0).toUpperCase()}
                        </Avatar>
                        <Box>
                          <Typography variant="body2" sx={{ fontSize: '0.78rem' }}>{u.full_name || u.username}</Typography>
                          <Typography variant="caption" sx={{ color: 'text.secondary', fontSize: '0.62rem' }}>{u.role}</Typography>
                        </Box>
                      </Box>
                    </MenuItem>
                  ))}
                </Select>
              </FormControl>
              <TextField
                label="Due Date" fullWidth size="small" type="date"
                value={formData.due_date} onChange={(e) => setFormData({ ...formData, due_date: e.target.value })}
                slotProps={{ inputLabel: { shrink: true } }}
              />
            </Box>
            <FormControl fullWidth size="small">
              <InputLabel>Category</InputLabel>
              <Select value={formData.category} label="Category"
                onChange={(e) => setFormData({ ...formData, category: e.target.value })}>
                {TASK_CATEGORIES.map(cat => (
                  <MenuItem key={cat.value} value={cat.value}>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                      <Box sx={{ width: 8, height: 8, borderRadius: '50%', bgcolor: CATEGORY_COLORS[cat.value] }} />
                      {cat.label}
                    </Box>
                  </MenuItem>
                ))}
              </Select>
            </FormControl>
          </Box>
        </DialogContent>
        <DialogActions sx={{ px: 3, pb: 2 }}>
          <Button onClick={() => setDialogOpen(false)} variant="outlined">Cancel</Button>
          <Button variant="contained" onClick={() => handleSave(false)}>
            {editingTask ? 'Save Changes' : 'Create Task'}
          </Button>
        </DialogActions>
      </Dialog>

      {/* ── Duplicate Warning ────────────────────────────────────────────────── */}
      <Dialog open={duplicateWarning.show} onClose={() => setDuplicateWarning({ show: false, existingId: null })}
        slotProps={{ paper: { sx: { bgcolor: '#0f172a', border: '1px solid #f59e0b44' } } }}>
        <DialogTitle sx={{ color: 'warning.main', pb: 1 }}>⚠️ Duplicate Task Detected</DialogTitle>
        <DialogContent>
          <Typography variant="body2" sx={{ mb: 1 }}>
            A task titled <strong>"{formData.title}"</strong> was already created in the last 24 hours
            {duplicateWarning.existingId && <> (Task #{duplicateWarning.existingId})</>}.
          </Typography>
          <Typography variant="body2" color="textSecondary">
            Create a new one anyway, or view the existing task?
          </Typography>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDuplicateWarning({ show: false, existingId: null })}>Cancel</Button>
          {duplicateWarning.existingId && (
            <Button onClick={() => {
              setDuplicateWarning({ show: false, existingId: null });
              setDialogOpen(false);
              navigate(`/tasks/${duplicateWarning.existingId}`);
            }}>View Existing</Button>
          )}
          <Button variant="contained" color="warning" onClick={() => {
            setDuplicateWarning({ show: false, existingId: null });
            handleSave(true);
          }}>Create Anyway</Button>
        </DialogActions>
      </Dialog>

      {/* ── Delete Confirmation ──────────────────────────────────────────────── */}
      <Dialog open={deleteDialog.open} onClose={() => setDeleteDialog({ open: false })} maxWidth="xs" fullWidth
        slotProps={{ paper: { sx: { bgcolor: '#0f172a', border: '1px solid #ef444444' } } }}>
        <DialogTitle sx={{ color: 'error.main', pb: 1 }}>Delete Task</DialogTitle>
        <DialogContent>
          <Typography variant="body2">
            Delete <strong>"{deleteDialog.title}"</strong>?<br />
            <Typography component="span" variant="caption" color="text.secondary">
              This action cannot be undone. Notes and screenshots will also be removed.
            </Typography>
          </Typography>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDeleteDialog({ open: false })} variant="outlined">Cancel</Button>
          <Button onClick={handleDeleteConfirm} variant="contained" color="error">Delete</Button>
        </DialogActions>
      </Dialog>

      {/* ── Snackbar ─────────────────────────────────────────────────────────── */}
      <Snackbar open={snackbar.open} autoHideDuration={3500} onClose={() => setSnackbar(s => ({ ...s, open: false }))}>
        <Alert onClose={() => setSnackbar(s => ({ ...s, open: false }))} severity={snackbar.severity} sx={{ width: '100%' }}>
          {snackbar.message}
        </Alert>
      </Snackbar>
    </Box>
  );
}

import { Box, Typography, Card, CardContent, Button, Chip, IconButton, Tooltip, Snackbar, Alert, Dialog, DialogTitle, DialogContent, DialogActions, TextField, MenuItem, Select, FormControl, InputLabel, Menu, Avatar, Checkbox, Slide, Popover, FormGroup, FormControlLabel, Divider } from '@mui/material';
import { DataGrid, GridColDef, GridRenderCellParams } from '@mui/x-data-grid';
import { Add, Edit, Delete, CheckCircle, Refresh, FilterList, MoreVert, Notes, Download, Person, LinkOff, Schedule, ViewColumn, DensityMedium, DensitySmall, DensityLarge, FormatListBulleted } from '@mui/icons-material';
import { useState, useEffect, useCallback, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { fetchTasks, createTask, updateTask, deleteTask, fetchTaskStats, fetchTaskNotesCount, bulkUpdate, type Task, type TaskStats, getTaskStatusColor, getTaskPriorityColor, TASK_CATEGORIES, TASK_STATUSES, TASK_PRIORITIES, type TaskFilters } from '../api/tasks';
import { fetchUsers, type User } from '../api/users';
import LoadingState from '../components/common/LoadingState';
import StatusBadge from '../components/common/StatusBadge';
import { usePermissions } from '../hooks/usePermissions';
import { useAuth } from '../hooks/useAuth';

export default function TasksPage() {
  const navigate = useNavigate();
  const { user } = useAuth();
  const { permissions } = usePermissions();
  const currentUsername = user?.username || '';
  const [tasks, setTasks] = useState<Task[]>([]);
  const [users, setUsers] = useState<User[]>([]);
  const [notesCount, setNotesCount] = useState<Record<number, number>>({});
  const [stats, setStats] = useState<TaskStats>({ total: 0, completed: 0, in_progress: 0, pending: 0, cancelled: 0 });
  const [loading, setLoading] = useState(true);
  const [snackbar, setSnackbar] = useState({ open: false, message: '', severity: 'success' as any });
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingTask, setEditingTask] = useState<Task | null>(null);
  const [search, setSearch] = useState('');
  const [filterStatus, setFilterStatus] = useState('');
  const [filterPriority, setFilterPriority] = useState('');
  const [filterCategory, setFilterCategory] = useState('');
  const [filterAssignee, setFilterAssignee] = useState('');
  const [filterDepartment, setFilterDepartment] = useState('');
  const [showOverdueOnly, setShowOverdueOnly] = useState(false);
  const [myTasksOnly, setMyTasksOnly] = useState(false);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [bulkActionMenu, setBulkActionMenu] = useState<null | HTMLElement>(null);
  const [bulkUpdating, setBulkUpdating] = useState(false);
  const [moreAnchor, setMoreAnchor] = useState<null | HTMLElement>(null);
  const [columnMenuAnchor, setColumnMenuAnchor] = useState<null | HTMLElement>(null);
  const [density, setDensity] = useState<'compact' | 'standard' | 'comfortable'>('standard');
  const [columnVisibility, setColumnVisibility] = useState<Record<string, boolean>>(() => {
    const saved = localStorage.getItem('tasks_column_visibility_v2');
    if (saved) {
      try { return JSON.parse(saved); } catch {}
    }
    return {
      id: true,
      title: true,
      description: false,
      priority: true,
      status: true,
      category: true,
      assigned_to: true,
      notes: true,
      due_date: true,
      actions: true,
    };
  });

  const toggleColumn = (field: string) => {
    setColumnVisibility(prev => {
      const next = { ...prev, [field]: !prev[field] };
      localStorage.setItem('tasks_column_visibility_v2', JSON.stringify(next));
      return next;
    });
  };

  const [formData, setFormData] = useState({ title: '', description: '', priority: 'medium' as any, status: 'pending' as any, assigned_to: 'mounirAb', due_date: '', category: 'general' });
  const [pagination, setPagination] = useState({ page: 1, perPage: 25, total: 0, totalPages: 0 });
  const [deleteTaskDialog, setDeleteTaskDialog] = useState<{ open: boolean; id?: number }>({ open: false });
  const [loadError, setLoadError] = useState<string | null>(null);

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
      if (filterStatus) filters.status = filterStatus;
      if (filterPriority) filters.priority = filterPriority;
      if (filterCategory) filters.category = filterCategory;
      if (myTasksOnly) filters.assigned_to = currentUsername;
      else if (filterAssignee) filters.assigned_to = filterAssignee;
      if (filterDepartment) filters.department = filterDepartment;
      if (search) filters.search = search;
      if (showOverdueOnly) filters.overdue = true;

      const [result, s, u, nc] = await Promise.all([fetchTasks(filters), fetchTaskStats(), fetchUsers(), fetchTaskNotesCount()]);
      setTasks(result.tasks);
      setPagination(prev => ({ ...prev, page: result.page, perPage: result.per_page, total: result.total, totalPages: result.total_pages }));
      setStats(s);
      setUsers(u);
      setNotesCount(nc);
    } catch (e: any) {
      setLoadError(e.response?.data?.error || e.message || 'Failed to load tasks');
    } finally {
      setLoading(false);
    }
  }, [filterStatus, filterPriority, filterCategory, filterAssignee, myTasksOnly, showOverdueOnly, search, pagination.page, pagination.perPage, currentUsername, filterDepartment]);

  useEffect(() => { loadData(); }, [loadData]);

  const openCreate = () => {
    setEditingTask(null);
    setFormData({ title: '', description: '', priority: 'medium', status: 'pending', assigned_to: 'mounirAb', due_date: '', category: 'general' });
    setDialogOpen(true);
  };

  const openEdit = (task: Task) => {
    setEditingTask(task);
    setFormData({ title: task.title, description: task.description || '', priority: task.priority, status: task.status, assigned_to: task.assigned_to, due_date: task.due_date || '', category: task.category });
    setDialogOpen(true);
  };

  const [duplicateWarning, setDuplicateWarning] = useState<{ show: boolean; existingId: number | null }>({ show: false, existingId: null });

  const handleSave = async (forceCreate = false) => {
    if (!formData.title.trim()) { setSnackbar({ open: true, message: 'Title is required', severity: 'error' }); return; }
    try {
      if (editingTask) {
        await updateTask({ id: editingTask.id, ...formData });
        setSnackbar({ open: true, message: 'Task updated', severity: 'success' });
      } else {
        const result = await createTask({ ...formData, force_create: forceCreate });
        if (result.duplicate_warning && !forceCreate) {
          setDuplicateWarning({ show: true, existingId: result.existing_task_id });
          return;
        }
        setSnackbar({ open: true, message: 'Task created', severity: 'success' });
      }
      setDialogOpen(false);
      setDuplicateWarning({ show: false, existingId: null });
      loadData();
    } catch (e: any) {
      setSnackbar({ open: true, message: e.response?.data?.error || e.message, severity: 'error' });
    }
  };

  const handleDelete = (id: number) => {
    setDeleteTaskDialog({ open: true, id });
  };

  const handleDeleteConfirm = async () => {
    const { id } = deleteTaskDialog;
    setDeleteTaskDialog({ open: false });
    if (!id) return;
    try {
      await deleteTask(id);
      setSnackbar({ open: true, message: 'Task deleted', severity: 'success' });
      loadData();
    } catch (e: any) {
      setSnackbar({ open: true, message: e.message, severity: 'error' });
    }
  };

  const handleComplete = async (task: Task) => {
    try {
      await updateTask({ id: task.id, status: task.status === 'completed' ? 'pending' : 'completed' });
      loadData();
    } catch (e: any) {
      setSnackbar({ open: true, message: e.message, severity: 'error' });
    }
  };

  const exportCSV = useCallback(() => {
    const headers = ['ID', 'Title', 'Priority', 'Status', 'Assigned To', 'Due Date', 'Category', 'Created'];
    const rows = tasks.map(t => [
      t.id,
      `"${t.title.replace(/"/g, '""')}"`,
      t.priority,
      t.status,
      t.assigned_to || 'Unassigned',
      t.due_date || '',
      t.category,
      new Date(t.created_at).toLocaleDateString(),
    ]);
    const csv = [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `tasks_${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
    setSnackbar({ open: true, message: `${tasks.length} tasks exported`, severity: 'success' });
  }, [tasks]);

  const handleBulkAction = async (action: string, value?: string) => {
    if (selectedIds.length === 0) return;
    setBulkUpdating(true);
    try {
      const fields: any = {};
      if (action === 'status') fields.status = value;
      else if (action === 'priority') fields.priority = value;
      
      await bulkUpdate(selectedIds, fields);
      setSnackbar({ open: true, message: `${selectedIds.length} tasks updated`, severity: 'success' });
      setSelectedIds([]);
      loadData();
    } catch (e: any) {
      setSnackbar({ open: true, message: e.response?.data?.error || e.message, severity: 'error' });
    } finally {
      setBulkUpdating(false);
      setBulkActionMenu(null);
    }
  };

  const isOverdue = (task: Task) => {
    if (!task.due_date || task.status === 'completed' || task.status === 'cancelled') return false;
    return new Date(task.due_date) < new Date();
  };

  const dueDateUrgency = (task: Task) => {
    if (!task.due_date) return null;
    const now = new Date();
    const due = new Date(task.due_date);
    const diffDays = Math.ceil((due.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
    if (diffDays < 0 && task.status !== 'completed' && task.status !== 'cancelled') return { color: 'error' as const, label: `${Math.abs(diffDays)}d overdue` };
    if (diffDays <= 3) return { color: 'warning' as const, label: `${diffDays}d left` };
    return null;
  };

  const taskAge = (task: Task) => {
    const created = new Date(task.created_at);
    const now = new Date();
    const days = Math.floor((now.getTime() - created.getTime()) / (1000 * 60 * 60 * 24));
    return days;
  };

  const getCategoryStyle = (cat: string) => {
    switch (cat?.toLowerCase()) {
      case 'optimization':
        return { bgcolor: 'rgba(6, 182, 212, 0.15)', color: '#22d3ee', border: '1px solid rgba(6, 182, 212, 0.3)' };
      case 'bugfix':
        return { bgcolor: 'rgba(239, 68, 68, 0.15)', color: '#f87171', border: '1px solid rgba(239, 68, 68, 0.3)' };
      case 'database':
        return { bgcolor: 'rgba(168, 85, 247, 0.15)', color: '#c084fc', border: '1px solid rgba(168, 85, 247, 0.3)' };
      case 'maintenance':
        return { bgcolor: 'rgba(245, 158, 11, 0.15)', color: '#fbbf24', border: '1px solid rgba(245, 158, 11, 0.3)' };
      case 'security':
        return { bgcolor: 'rgba(16, 185, 129, 0.15)', color: '#34d399', border: '1px solid rgba(16, 185, 129, 0.3)' };
      case 'frontend':
        return { bgcolor: 'rgba(59, 130, 246, 0.15)', color: '#60a5fa', border: '1px solid rgba(59, 130, 246, 0.3)' };
      default:
        return { bgcolor: 'rgba(148, 163, 184, 0.12)', color: '#94a3b8', border: '1px solid rgba(148, 163, 184, 0.2)' };
    }
  };

  const allColumns: GridColDef[] = [
    {
      field: 'id',
      headerName: 'ID',
      width: 75,
      align: 'center',
      headerAlign: 'center',
      renderCell: (p: GridRenderCellParams) => (
        <Chip
          label={`#${p.value}`}
          size="small"
          variant="outlined"
          sx={{
            fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace',
            fontWeight: 700,
            fontSize: '0.72rem',
            color: 'text.secondary',
            borderColor: 'rgba(255,255,255,0.12)',
            height: 22,
            cursor: 'pointer',
            '&:hover': { color: 'primary.main', borderColor: 'primary.main', bgcolor: 'rgba(59,130,246,0.08)' }
          }}
          onClick={() => navigate(`/tasks/${p.row.id}`)}
        />
      )
    },
    {
      field: 'title',
      headerName: 'Task',
      flex: 2,
      minWidth: 260,
      renderCell: (p: GridRenderCellParams) => {
        const descSnippet = p.row.description
          ? p.row.description.split('\n')[0].replace(/[#*`_]/g, '').trim()
          : '';
        return (
          <Box
            sx={{
              py: 0.6,
              display: 'flex',
              flexDirection: 'column',
              justifyContent: 'center',
              cursor: 'pointer',
              overflow: 'hidden',
              width: '100%'
            }}
            onClick={() => navigate(`/tasks/${p.row.id}`)}
          >
            <Typography
              variant="body2"
              sx={{
                fontWeight: 650,
                fontSize: '0.84rem',
                color: p.row.status === 'completed' ? 'text.secondary' : 'text.primary',
                textDecoration: p.row.status === 'completed' ? 'line-through' : 'none',
                '&:hover': { color: 'primary.main' },
                whiteSpace: 'nowrap',
                overflow: 'hidden',
                textOverflow: 'ellipsis'
              }}
            >
              {p.value}
            </Typography>
            {descSnippet && (
              <Typography
                variant="caption"
                sx={{
                  color: 'text.secondary',
                  fontSize: '0.72rem',
                  mt: 0.2,
                  whiteSpace: 'nowrap',
                  overflow: 'hidden',
                  textOverflow: 'ellipsis',
                  opacity: 0.8
                }}
              >
                {descSnippet}
              </Typography>
            )}
          </Box>
        );
      }
    },
    {
      field: 'description',
      headerName: 'Full Description',
      flex: 1.8,
      minWidth: 200,
      renderCell: (p: GridRenderCellParams) => (
        <Tooltip title={p.value || 'No description'} placement="bottom-start">
          <Typography
            variant="caption"
            sx={{
              color: 'text.secondary',
              display: '-webkit-box',
              WebkitLineClamp: 2,
              WebkitBoxOrient: 'vertical',
              overflow: 'hidden',
              lineHeight: 1.3,
              fontSize: '0.75rem',
            }}
          >
            {p.value || '—'}
          </Typography>
        </Tooltip>
      )
    },
    {
      field: 'priority',
      headerName: 'Priority',
      width: 105,
      renderCell: (p: GridRenderCellParams) => (
        <Chip
          label={p.value?.toUpperCase()}
          size="small"
          color={getTaskPriorityColor(p.value)}
          sx={{ fontWeight: 700, fontSize: '0.62rem', height: 22 }}
        />
      )
    },
    {
      field: 'status',
      headerName: 'Status',
      width: 130,
      renderCell: (p: GridRenderCellParams) => (
        <StatusBadge
          label={p.value?.toUpperCase().replace('-', ' ').replace('_', ' ')}
          color={getTaskStatusColor(p.value)}
        />
      )
    },
    {
      field: 'category',
      headerName: 'Category',
      width: 125,
      renderCell: (p: GridRenderCellParams) => {
        const style = getCategoryStyle(p.value);
        return (
          <Chip
            label={p.value || 'General'}
            size="small"
            sx={{
              fontSize: '0.68rem',
              fontWeight: 650,
              textTransform: 'capitalize',
              height: 22,
              ...style
            }}
          />
        );
      }
    },
    {
      field: 'assigned_to',
      headerName: 'Assigned',
      width: 130,
      renderCell: (p: GridRenderCellParams) => (
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.6 }}>
          {p.value ? (
            <Avatar sx={{ width: 22, height: 22, fontSize: '0.65rem', bgcolor: 'primary.main', fontWeight: 700 }}>
              {p.value.charAt(0).toUpperCase()}
            </Avatar>
          ) : null}
          <Typography variant="caption" sx={{ fontWeight: 500, fontSize: '0.75rem' }}>
            {p.value || 'Unassigned'}
          </Typography>
        </Box>
      )
    },
    {
      field: 'notes',
      headerName: 'Notes',
      width: 75,
      align: 'center',
      renderCell: (p: GridRenderCellParams) => {
        const count = notesCount[p.row.id] || 0;
        return count > 0 ? (
          <Tooltip title={`${count} note${count > 1 ? 's' : ''}`}>
            <Box
              sx={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 0.4,
                cursor: 'pointer',
                bgcolor: 'rgba(255,255,255,0.04)',
                px: 0.8,
                py: 0.2,
                borderRadius: 1,
                border: '1px solid rgba(255,255,255,0.08)',
                '&:hover': { bgcolor: 'rgba(59,130,246,0.1)', borderColor: 'primary.main' }
              }}
              onClick={() => navigate(`/tasks/${p.row.id}?tab=notes`)}
            >
              <Notes sx={{ fontSize: 13, color: 'text.secondary' }} />
              <Typography variant="caption" sx={{ fontWeight: 700, fontSize: '0.72rem' }}>{count}</Typography>
            </Box>
          </Tooltip>
        ) : <Typography variant="caption" sx={{ color: 'text.disabled' }}>—</Typography>;
      }
    },
    {
      field: 'due_date',
      headerName: 'Due',
      width: 120,
      renderCell: (p: GridRenderCellParams) => {
        const urgency = dueDateUrgency(p.row);
        const overdue = isOverdue(p.row);
        return (
          <Tooltip title={urgency?.label || (p.value ? new Date(p.value).toLocaleDateString() : 'No due date')}>
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
              {p.value ? (
                <>
                  <Typography variant="caption" sx={{ color: overdue ? 'error.main' : urgency ? 'warning.main' : 'inherit', fontSize: '0.75rem' }}>
                    {new Date(p.value).toLocaleDateString()}
                  </Typography>
                  {overdue && <Chip label="Overdue" size="small" color="error" sx={{ height: 16, fontSize: '0.55rem', fontWeight: 700 }} />}
                </>
              ) : (
                <Typography variant="caption" sx={{ color: 'text.disabled' }}>—</Typography>
              )}
            </Box>
          </Tooltip>
        );
      }
    },
    {
      field: 'actions',
      headerName: '',
      width: 110,
      sortable: false,
      renderCell: (p: GridRenderCellParams) => {
        const isOwner = p.row.created_by === currentUsername || p.row.assigned_to === currentUsername;
        const canEdit = isOwner || !!permissions?.can_update_any_task;
        const canDelete = permissions?.can_delete_tasks;
        return (
          <Box sx={{ display: 'flex', gap: 0.4 }}>
            <Tooltip title={p.row.status === 'completed' ? 'Reopen' : 'Complete'}>
              <IconButton size="small" color={p.row.status === 'completed' ? 'default' : 'success'} onClick={() => handleComplete(p.row)}>
                <CheckCircle sx={{ fontSize: 16 }} />
              </IconButton>
            </Tooltip>
            {canEdit && (
              <Tooltip title="Edit">
                <IconButton size="small" onClick={() => openEdit(p.row)}>
                  <Edit sx={{ fontSize: 16 }} />
                </IconButton>
              </Tooltip>
            )}
            {canDelete && (
              <Tooltip title="Delete">
                <IconButton size="small" color="error" onClick={() => handleDelete(p.row.id)}>
                  <Delete sx={{ fontSize: 16 }} />
                </IconButton>
              </Tooltip>
            )}
          </Box>
        );
      }
    },
  ];

  const columns = useMemo(() => {
    return allColumns.filter(col => columnVisibility[col.field] !== false);
  }, [allColumns, columnVisibility]);

  if (loading && tasks.length === 0) return <LoadingState message="Loading tasks..." />;

  return (
    <Box sx={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
      {/* Header + Compact Toolbar */}
      <Box sx={{ mb: 2 }}>
        <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 1.5 }}>
          <Box>
            <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.2 }}>Tasks</Typography>
            <Typography variant="body2" sx={{ color: 'text.secondary' }}>Track and manage project tasks.</Typography>
          </Box>
          <Box sx={{ display: 'flex', gap: 1 }}>
            <Button size="small" variant="outlined" startIcon={<Refresh />} onClick={loadData} disabled={loading}>Sync</Button>
            {permissions?.can_create_tasks && <Button size="small" variant="contained" startIcon={<Add />} onClick={openCreate}>New Task</Button>}
          </Box>
        </Box>

        {/* Compact Toolbar */}
        <Card sx={{ py: 0.75, px: 1.5, background: 'rgba(255,255,255,0.02)', border: '1px solid #1e293b' }}>
          <Box sx={{ display: 'flex', gap: 1, alignItems: 'center', flexWrap: 'wrap' }}>
            {/* View Mode: All Tasks vs My Tasks */}
            <Chip
              label={`All Tasks (${stats.total || tasks.length})`}
              onClick={() => setMyTasksOnly(false)}
              color={!myTasksOnly ? 'primary' : 'default'}
              variant={!myTasksOnly ? 'filled' : 'outlined'}
              clickable
              size="small"
              sx={{ fontSize: '0.72rem', height: 26, fontWeight: !myTasksOnly ? 700 : 500 }}
            />
            <Chip
              label="Assigned to Me"
              icon={<Person sx={{ fontSize: 14 }} />}
              onClick={() => setMyTasksOnly(true)}
              color={myTasksOnly ? 'primary' : 'default'}
              variant={myTasksOnly ? 'filled' : 'outlined'}
              clickable
              size="small"
              sx={{ fontSize: '0.72rem', height: 26, fontWeight: myTasksOnly ? 700 : 500 }}
            />

            {/* Search */}
            <TextField size="small" placeholder="Search tasks..." value={search} onChange={(e) => setSearch(e.target.value)} sx={{ width: 180, '& .MuiInputBase-root': { fontSize: '0.75rem' } }} />

            {/* Status Filter */}
            <FormControl size="small" sx={{ minWidth: 120 }}>
              <Select value={filterStatus} displayEmpty onChange={(e) => setFilterStatus(e.target.value)} sx={{ fontSize: '0.75rem' }}>
                <MenuItem value="">All Status</MenuItem>
                <MenuItem value="pending">Pending</MenuItem>
                <MenuItem value="in-progress">In Progress</MenuItem>
                <MenuItem value="completed">Completed</MenuItem>
                <MenuItem value="cancelled">Cancelled</MenuItem>
              </Select>
            </FormControl>

            {/* Priority Filter */}
            <FormControl size="small" sx={{ minWidth: 110 }}>
              <Select value={filterPriority} displayEmpty onChange={(e) => setFilterPriority(e.target.value)} sx={{ fontSize: '0.75rem' }}>
                <MenuItem value="">All Priority</MenuItem>
                <MenuItem value="high">High</MenuItem>
                <MenuItem value="medium">Medium</MenuItem>
                <MenuItem value="low">Low</MenuItem>
              </Select>
            </FormControl>

            {/* Category Filter */}
            <FormControl size="small" sx={{ minWidth: 130 }}>
              <Select value={filterCategory} displayEmpty onChange={(e) => setFilterCategory(e.target.value)} sx={{ fontSize: '0.75rem' }}>
                <MenuItem value="">All Categories</MenuItem>
                {TASK_CATEGORIES.map(cat => <MenuItem key={cat.value} value={cat.value}>{cat.label}</MenuItem>)}
              </Select>
            </FormControl>

            {/* Assignee Filter */}
            <FormControl size="small" sx={{ minWidth: 120 }}>
              <Select value={filterAssignee} displayEmpty onChange={(e) => setFilterAssignee(e.target.value)} sx={{ fontSize: '0.75rem' }}>
                <MenuItem value="">All Assignees</MenuItem>
                {users.filter(u => u.is_active).map(u => <MenuItem key={u.username} value={u.username}>{u.full_name || u.username}</MenuItem>)}
              </Select>
            </FormControl>

            {/* Department / Role Filter */}
            <FormControl size="small" sx={{ minWidth: 140 }}>
              <Select value={filterDepartment} displayEmpty onChange={(e) => setFilterDepartment(e.target.value)} sx={{ fontSize: '0.75rem' }}>
                <MenuItem value="">All Departments</MenuItem>
                {Array.from(new Set(users.map(u => u.role))).map(r => (
                  <MenuItem key={r} value={r}>{r.charAt(0).toUpperCase() + r.slice(1)}</MenuItem>
                ))}
              </Select>
            </FormControl>

            {/* Overdue Filter */}
            <Chip
              label="Overdue"
              onClick={() => setShowOverdueOnly(!showOverdueOnly)}
              color={showOverdueOnly ? 'error' : 'default'}
              variant={showOverdueOnly ? 'filled' : 'outlined'}
              clickable
              size="small"
              sx={{ fontSize: '0.7rem', height: 24 }}
            />

            <Tooltip title="Clear Filters">
              <IconButton size="small" onClick={() => { setSearch(''); setFilterStatus(''); setFilterPriority(''); setFilterCategory(''); setFilterAssignee(''); setMyTasksOnly(false); setShowOverdueOnly(false); }}>
                <FilterList sx={{ fontSize: 16 }} />
              </IconButton>
            </Tooltip>

            <Box sx={{ flexGrow: 1 }} />

            {/* Columns Customizer */}
            <Tooltip title="Customize Columns">
              <Button
                size="small"
                variant="outlined"
                startIcon={<ViewColumn sx={{ fontSize: 16 }} />}
                onClick={(e) => setColumnMenuAnchor(e.currentTarget)}
                sx={{ fontSize: '0.72rem', height: 26, py: 0.2, px: 1, borderColor: 'rgba(255,255,255,0.15)' }}
              >
                Columns
              </Button>
            </Tooltip>
            <Popover
              open={Boolean(columnMenuAnchor)}
              anchorEl={columnMenuAnchor}
              onClose={() => setColumnMenuAnchor(null)}
              anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
              transformOrigin={{ vertical: 'top', horizontal: 'right' }}
              slotProps={{ paper: { sx: { p: 1.5, width: 220, bgcolor: '#111827', border: '1px solid #1f2937' } } }}
            >
              <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1, fontSize: '0.8rem' }}>Display Columns</Typography>
              <Divider sx={{ mb: 1, borderColor: 'rgba(255,255,255,0.08)' }} />
              <FormGroup>
                {[
                  { field: 'id', label: 'Task ID' },
                  { field: 'title', label: 'Task & Snippet' },
                  { field: 'description', label: 'Full Description' },
                  { field: 'priority', label: 'Priority' },
                  { field: 'status', label: 'Status' },
                  { field: 'category', label: 'Category' },
                  { field: 'assigned_to', label: 'Assignee' },
                  { field: 'notes', label: 'Notes Count' },
                  { field: 'due_date', label: 'Due Date' },
                  { field: 'actions', label: 'Actions' },
                ].map(col => (
                  <FormControlLabel
                    key={col.field}
                    control={
                      <Checkbox
                        size="small"
                        checked={columnVisibility[col.field] !== false}
                        onChange={() => toggleColumn(col.field)}
                        sx={{ py: 0.3 }}
                      />
                    }
                    label={<Typography variant="caption" sx={{ fontSize: '0.75rem' }}>{col.label}</Typography>}
                  />
                ))}
              </FormGroup>
              <Divider sx={{ my: 1, borderColor: 'rgba(255,255,255,0.08)' }} />
              <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 0.5, fontSize: '0.8rem' }}>Grid Density</Typography>
              <Box sx={{ display: 'flex', gap: 0.5, mt: 0.5 }}>
                <Button size="small" variant={density === 'compact' ? 'contained' : 'outlined'} onClick={() => setDensity('compact')} sx={{ fontSize: '0.65rem', py: 0.2, minWidth: 55 }}>Compact</Button>
                <Button size="small" variant={density === 'standard' ? 'contained' : 'outlined'} onClick={() => setDensity('standard')} sx={{ fontSize: '0.65rem', py: 0.2, minWidth: 55 }}>Standard</Button>
                <Button size="small" variant={density === 'comfortable' ? 'contained' : 'outlined'} onClick={() => setDensity('comfortable')} sx={{ fontSize: '0.65rem', py: 0.2, minWidth: 55 }}>Spacious</Button>
              </Box>
            </Popover>

            {/* More Actions */}
            <Tooltip title="More Actions">
              <IconButton size="small" onClick={(e) => setMoreAnchor(e.currentTarget)}>
                <MoreVert sx={{ fontSize: 16 }} />
              </IconButton>
            </Tooltip>
            <Menu anchorEl={moreAnchor} open={Boolean(moreAnchor)} onClose={() => setMoreAnchor(null)}>
              <MenuItem onClick={() => { setMoreAnchor(null); loadData(); }}>Refresh Data</MenuItem>
              <MenuItem onClick={() => { setMoreAnchor(null); exportCSV(); }}><Download sx={{ fontSize: 16, mr: 1 }} />Export CSV</MenuItem>
            </Menu>
          </Box>
        </Card>
      </Box>

      {/* Bulk Action Bar */}
      {selectedIds.length > 0 && (
        <Card sx={{ mb: 2, py: 1, px: 2, background: 'rgba(59,130,246,0.08)', border: '1px solid rgba(59,130,246,0.3)' }}>
          <Box sx={{ display: 'flex', gap: 1, alignItems: 'center', flexWrap: 'wrap' }}>
            <Typography variant="caption" sx={{ fontWeight: 700, color: 'primary.main' }}>{selectedIds.length} selected</Typography>
            <Box sx={{ display: 'flex', gap: 0.5, alignItems: 'center' }}>
              <Typography variant="caption" sx={{ color: 'text.secondary', mr: 0.5 }}>Set status:</Typography>
              <Button size="small" variant="outlined" onClick={() => handleBulkAction('status', 'in-progress')} disabled={bulkUpdating}>In Progress</Button>
              <Button size="small" variant="outlined" color="success" onClick={() => handleBulkAction('status', 'completed')} disabled={bulkUpdating}>Complete</Button>
              <Button size="small" variant="outlined" color="error" onClick={() => handleBulkAction('status', 'cancelled')} disabled={bulkUpdating}>Cancel</Button>
            </Box>
            <Box sx={{ display: 'flex', gap: 0.5, alignItems: 'center' }}>
              <Typography variant="caption" sx={{ color: 'text.secondary', mr: 0.5 }}>Set priority:</Typography>
              <Button size="small" variant="outlined" color="error" onClick={() => handleBulkAction('priority', 'high')} disabled={bulkUpdating}>High</Button>
              <Button size="small" variant="outlined" color="warning" onClick={() => handleBulkAction('priority', 'medium')} disabled={bulkUpdating}>Medium</Button>
              <Button size="small" variant="outlined" onClick={() => handleBulkAction('priority', 'low')} disabled={bulkUpdating}>Low</Button>
            </Box>
            <Box sx={{ display: 'flex', gap: 0.5, alignItems: 'center' }}>
              <Typography variant="caption" sx={{ color: 'text.secondary', mr: 0.5 }}>Assign to:</Typography>
              <Select
                size="small"
                value=""
                displayEmpty
                onChange={(e) => { if (e.target.value) handleBulkAction('assigned_to', e.target.value); }}
                sx={{ fontSize: '0.75rem', height: 24, minWidth: 120 }}
              >
                <MenuItem value="" disabled>Unchanged</MenuItem>
                <MenuItem value="">Unassigned</MenuItem>
                {users.filter(u => u.is_active).map(u => (
                  <MenuItem key={u.id} value={u.username}>{u.full_name || u.username}</MenuItem>
                ))}
              </Select>
            </Box>
            <IconButton size="small" onClick={() => setSelectedIds([])} sx={{ ml: 'auto' }}>
              <LinkOff sx={{ fontSize: 16 }} />
            </IconButton>
          </Box>
        </Card>
      )}

      {/* Load error */}
      {loadError && (
        <Alert severity="error" sx={{ mb: 2 }} action={<Button size="small" color="inherit" onClick={loadData}>Retry</Button>}>
          {loadError}
        </Alert>
      )}

      {/* DataGrid */}
      <Card sx={{ flexGrow: 1, mb: 2, border: '1px solid #1e293b' }}>
        <DataGrid 
          rows={tasks} 
          columns={columns} 
          getRowId={(r) => r.id} 
          density={density} 
          pageSizeOptions={[10, 25, 50, 100]} 
          initialState={{ 
            pagination: { paginationModel: { pageSize: 25 } },
            sorting: { sortModel: [{ field: 'id', sort: 'desc' }] }
          }}
          checkboxSelection
          onRowSelectionModelChange={(model) => {
            // Extract IDs from the selection model (v9 format)
            const ids = 'ids' in model ? Array.from(model.ids) : [];
            setSelectedIds(ids as number[]);
          }}
          disableRowSelectionOnClick 
          sx={{
            border: 'none',
            '& .MuiDataGrid-row:hover': {
              bgcolor: 'rgba(255,255,255,0.03)'
            }
          }} 
        />
      </Card>

      {/* Stats Cards at Bottom */}
      <Box sx={{ display: 'flex', gap: 1.5 }}>
        {[
          { label: 'Total', value: stats.total, color: 'text.primary', icon: '📋' },
          { label: 'Pending', value: stats.pending, color: '#94a3b8', icon: '⏳' },
          { label: 'In Progress', value: stats.in_progress, color: '#3b82f6', icon: '🔄' },
          { label: 'Completed', value: stats.completed, color: '#22c55e', icon: '✅' },
        ].map(s => (
          <Card key={s.label} sx={{ flex: 1, py: 1, px: 2, background: 'rgba(255,255,255,0.02)', border: '1px solid #1e293b' }}>
            <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
              <Box>
                <Typography variant="caption" sx={{ color: s.color, fontWeight: 600, fontSize: '0.65rem' }}>{s.label.toUpperCase()}</Typography>
                <Typography variant="h5" sx={{ fontWeight: 800, color: s.color, lineHeight: 1.2 }}>{s.value}</Typography>
              </Box>
              <Typography sx={{ fontSize: '1.5rem', opacity: 0.3 }}>{s.icon}</Typography>
            </Box>
          </Card>
        ))}
      </Box>

      {/* Create/Edit Dialog */}
      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>{editingTask ? 'Edit Task' : 'New Task'}</DialogTitle>
        <DialogContent>
          <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2, mt: 1 }}>
            <TextField label="Title" fullWidth value={formData.title} onChange={(e) => setFormData({ ...formData, title: e.target.value })} required />
            <TextField label="Description" fullWidth multiline rows={3} value={formData.description} onChange={(e) => setFormData({ ...formData, description: e.target.value })} />
            <Box sx={{ display: 'flex', gap: 2 }}>
              <FormControl fullWidth size="small"><InputLabel>Priority</InputLabel><Select value={formData.priority} label="Priority" onChange={(e) => setFormData({ ...formData, priority: e.target.value })}><MenuItem value="low">Low</MenuItem><MenuItem value="medium">Medium</MenuItem><MenuItem value="high">High</MenuItem></Select></FormControl>
              <FormControl fullWidth size="small"><InputLabel>Status</InputLabel><Select value={formData.status} label="Status" onChange={(e) => setFormData({ ...formData, status: e.target.value })}><MenuItem value="pending">Pending</MenuItem><MenuItem value="in-progress">In Progress</MenuItem><MenuItem value="completed">Completed</MenuItem><MenuItem value="cancelled">Cancelled</MenuItem></Select></FormControl>
            </Box>
            <Box sx={{ display: 'flex', gap: 2 }}>
              <FormControl fullWidth size="small"><InputLabel>Assign To</InputLabel><Select value={formData.assigned_to} label="Assign To" onChange={(e) => setFormData({ ...formData, assigned_to: e.target.value })}>
                <MenuItem value="">Unassigned</MenuItem>
                {users.filter(u => u.is_active).map(u => (
                  <MenuItem key={u.id} value={u.username}>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                      <Avatar sx={{ width: 24, height: 24, fontSize: '0.7rem', bgcolor: u.role === 'admin' ? 'error.main' : 'primary.main' }}>{u.username.charAt(0).toUpperCase()}</Avatar>
                      <Box>
                        <Typography variant="body2" sx={{ fontSize: '0.8rem' }}>{u.full_name || u.username}</Typography>
                        <Typography variant="caption" sx={{ color: 'text.secondary', fontSize: '0.65rem' }}>{u.role}</Typography>
                      </Box>
                    </Box>
                  </MenuItem>
                ))}
              </Select></FormControl>
              <TextField label="Due Date" fullWidth size="small" type="date" value={formData.due_date} onChange={(e) => setFormData({ ...formData, due_date: e.target.value })} slotProps={{ inputLabel: { shrink: true } }} />
            </Box>
            <FormControl fullWidth size="small"><InputLabel>Category</InputLabel><Select value={formData.category} label="Category" onChange={(e) => setFormData({ ...formData, category: e.target.value })}>{TASK_CATEGORIES.map(cat => <MenuItem key={cat.value} value={cat.value}>{cat.label}</MenuItem>)}</Select></FormControl>
          </Box>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDialogOpen(false)}>Cancel</Button>
          <Button variant="contained" onClick={() => handleSave(false)}>{editingTask ? 'Save' : 'Create'}</Button>
        </DialogActions>
      </Dialog>

      {/* Duplicate Warning Dialog */}
      <Dialog open={duplicateWarning.show} onClose={() => setDuplicateWarning({ show: false, existingId: null })}>
        <DialogTitle>Duplicate Task Detected</DialogTitle>
        <DialogContent>
          <Typography variant="body2" sx={{ mb: 1 }}>
            A task with the title "<strong>{formData.title}</strong>" was already created in the last 24 hours
            {duplicateWarning.existingId && <> (Task #{duplicateWarning.existingId})</>}.
          </Typography>
          <Typography variant="body2" color="textSecondary">
            Would you like to create it anyway, or view the existing task?
          </Typography>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDuplicateWarning({ show: false, existingId: null })}>Cancel</Button>
          {duplicateWarning.existingId && (
            <Button onClick={() => { setDuplicateWarning({ show: false, existingId: null }); setDialogOpen(false); navigate(`/tasks/${duplicateWarning.existingId}`); }}>
              View Existing
            </Button>
          )}
          <Button variant="contained" color="warning" onClick={() => { setDuplicateWarning({ show: false, existingId: null }); handleSave(true); }}>
            Create Anyway
          </Button>
        </DialogActions>
      </Dialog>

      {/* Delete Task Confirmation */}
      <Dialog open={deleteTaskDialog.open} onClose={() => setDeleteTaskDialog({ open: false })} maxWidth="xs" fullWidth>
        <DialogTitle sx={{ color: 'error.main' }}>Delete Task</DialogTitle>
        <DialogContent>
          <Typography variant="body2">Delete task #{deleteTaskDialog.id}? This action cannot be undone.</Typography>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDeleteTaskDialog({ open: false })} variant="outlined">Cancel</Button>
          <Button onClick={handleDeleteConfirm} variant="contained" color="error" autoFocus>Delete</Button>
        </DialogActions>
      </Dialog>

      <Snackbar open={snackbar.open} autoHideDuration={3000} onClose={() => setSnackbar({ ...snackbar, open: false })}>
        <Alert onClose={() => setSnackbar({ ...snackbar, open: false })} severity={snackbar.severity} sx={{ width: '100%' }}>{snackbar.message}</Alert>
      </Snackbar>
    </Box>
  );
}

import { Box, Typography, Card, CardContent, Button, Chip, IconButton, Tooltip, Snackbar, Alert, Dialog, DialogTitle, DialogContent, DialogActions, TextField, MenuItem, Select, FormControl, InputLabel, Menu, Avatar, Checkbox, Slide } from '@mui/material';
import { DataGrid, GridColDef, GridRenderCellParams } from '@mui/x-data-grid';
import { Add, Edit, Delete, CheckCircle, Refresh, FilterList, MoreVert, Notes, Download, Person, LinkOff, Schedule } from '@mui/icons-material';
import { useState, useEffect, useCallback } from 'react';
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
  const [showOverdueOnly, setShowOverdueOnly] = useState(false);
  const [myTasksOnly, setMyTasksOnly] = useState(false);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [bulkActionMenu, setBulkActionMenu] = useState<null | HTMLElement>(null);
  const [bulkUpdating, setBulkUpdating] = useState(false);
  const [moreAnchor, setMoreAnchor] = useState<null | HTMLElement>(null);
  const [formData, setFormData] = useState({ title: '', description: '', priority: 'medium' as any, status: 'pending' as any, assigned_to: '', due_date: '', category: 'general' });
  const [pagination, setPagination] = useState({ page: 1, perPage: 25, total: 0, totalPages: 0 });

  const loadData = async () => {
    setLoading(true);
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
      if (search) filters.search = search;
      if (showOverdueOnly) filters.overdue = true;

      const [result, s, u, nc] = await Promise.all([fetchTasks(filters), fetchTaskStats(), fetchUsers(), fetchTaskNotesCount()]);
      setTasks(result.tasks);
      setPagination({ page: result.page, perPage: result.per_page, total: result.total, totalPages: result.total_pages });
      setStats(s);
      setUsers(u);
      setNotesCount(nc);
    } catch (e) { console.error(e); }
    finally { setLoading(false); }
  };

  useEffect(() => { loadData(); }, [filterStatus, filterPriority, filterCategory, filterAssignee, myTasksOnly, showOverdueOnly, search, pagination.page, pagination.perPage]);

  const openCreate = () => {
    setEditingTask(null);
    setFormData({ title: '', description: '', priority: 'medium', status: 'pending', assigned_to: '', due_date: '', category: 'general' });
    setDialogOpen(true);
  };

  const openEdit = (task: Task) => {
    setEditingTask(task);
    setFormData({ title: task.title, description: task.description || '', priority: task.priority, status: task.status, assigned_to: task.assigned_to, due_date: task.due_date || '', category: task.category });
    setDialogOpen(true);
  };

  const handleSave = async () => {
    if (!formData.title.trim()) { setSnackbar({ open: true, message: 'Title is required', severity: 'error' }); return; }
    try {
      if (editingTask) {
        await updateTask({ id: editingTask.id, ...formData });
        setSnackbar({ open: true, message: 'Task updated', severity: 'success' });
      } else {
        await createTask(formData);
        setSnackbar({ open: true, message: 'Task created', severity: 'success' });
      }
      setDialogOpen(false);
      loadData();
    } catch (e: any) {
      setSnackbar({ open: true, message: e.response?.data?.error || e.message, severity: 'error' });
    }
  };

  const handleDelete = async (id: number) => {
    if (!confirm('Delete this task?')) return;
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

  const columns: GridColDef[] = [
    { field: 'title', headerName: 'Task', flex: 1.5, renderCell: (p: GridRenderCellParams) => (
      <Typography variant="body2" sx={{ fontWeight: 600, cursor: 'pointer', '&:hover': { color: 'primary.main' } }} onClick={() => navigate(`/tasks/${p.row.id}`)}>{p.value}</Typography>
    )},
    { field: 'priority', headerName: 'Priority', width: 100, renderCell: (p: GridRenderCellParams) => <Chip label={p.value.toUpperCase()} size="small" color={getTaskPriorityColor(p.value)} sx={{ fontWeight: 700, fontSize: '0.6rem' }} /> },
    { field: 'status', headerName: 'Status', width: 120, renderCell: (p: GridRenderCellParams) => <StatusBadge label={p.value.toUpperCase().replace('-', ' ')} color={getTaskStatusColor(p.value)} /> },
    { field: 'assigned_to', headerName: 'Assigned', width: 120, renderCell: (p: GridRenderCellParams) => (
      <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
        {p.value ? <Avatar sx={{ width: 20, height: 20, fontSize: '0.6rem', bgcolor: 'primary.main' }}>{p.value.charAt(0).toUpperCase()}</Avatar> : null}
        <Typography variant="caption">{p.value || '—'}</Typography>
      </Box>
    )},
    { field: 'notes', headerName: 'Notes', width: 70, align: 'center', renderCell: (p: GridRenderCellParams) => {
      const count = notesCount[p.row.id] || 0;
      return count > 0 ? (
        <Tooltip title={`${count} note${count > 1 ? 's' : ''}`}>
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.3, cursor: 'pointer' }} onClick={() => navigate(`/tasks/${p.row.id}?tab=notes`)}>
            <Notes sx={{ fontSize: 14, color: 'text.secondary' }} />
            <Typography variant="caption" sx={{ fontWeight: 600 }}>{count}</Typography>
          </Box>
        </Tooltip>
      ) : <Typography variant="caption" sx={{ color: 'text.disabled' }}>—</Typography>;
    }},
    { field: 'due_date', headerName: 'Due', width: 110, renderCell: (p: GridRenderCellParams) => {
      const urgency = dueDateUrgency(p.row);
      const overdue = isOverdue(p.row);
      return (
        <Tooltip title={urgency?.label || (p.value ? new Date(p.value).toLocaleDateString() : 'No due date')}>
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
            {p.value ? (
              <>
                <Typography variant="caption" sx={{ color: overdue ? 'error.main' : urgency ? 'warning.main' : 'inherit' }}>
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
    }},
    { field: 'category', headerName: 'Category', width: 100, renderCell: (p: GridRenderCellParams) => <Typography variant="caption" sx={{ textTransform: 'capitalize' }}>{p.value}</Typography> },
    { field: 'actions', headerName: '', width: 120, sortable: false, renderCell: (p: GridRenderCellParams) => {
      const isOwner = p.row.created_by === currentUsername;
      const canEdit = isOwner || permissions?.can_update_any_task;
      const canDelete = permissions?.can_delete_tasks;
      return (
        <Box sx={{ display: 'flex', gap: 0.5 }}>
          <Tooltip title={p.row.status === 'completed' ? 'Reopen' : 'Complete'}><IconButton size="small" color={p.row.status === 'completed' ? 'default' : 'success'} onClick={() => handleComplete(p.row)}><CheckCircle sx={{ fontSize: 16 }} /></IconButton></Tooltip>
          {canEdit && <Tooltip title="Edit"><IconButton size="small" onClick={() => openEdit(p.row)}><Edit sx={{ fontSize: 16 }} /></IconButton></Tooltip>}
          {canDelete && <Tooltip title="Delete"><IconButton size="small" color="error" onClick={() => handleDelete(p.row.id)}><Delete sx={{ fontSize: 16 }} /></IconButton></Tooltip>}
        </Box>
      );
    }},
  ];

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
            {/* My Tasks Filter */}
            <Chip
              label="My Tasks"
              icon={<Person sx={{ fontSize: 14 }} />}
              onClick={() => setMyTasksOnly(!myTasksOnly)}
              color={myTasksOnly ? 'primary' : 'default'}
              variant={myTasksOnly ? 'filled' : 'outlined'}
              clickable
              size="small"
              sx={{ fontSize: '0.7rem', height: 24 }}
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

      {/* DataGrid */}
      <Card sx={{ flexGrow: 1, mb: 2 }}>
        <DataGrid 
          rows={tasks} 
          columns={columns} 
          getRowId={(r) => r.id} 
          density="compact" 
          pageSizeOptions={[10, 25, 50]} 
          initialState={{ 
            pagination: { paginationModel: { pageSize: 25 } },
            sorting: { sortModel: [{ field: 'priority', sort: 'desc' }, { field: 'due_date', sort: 'asc' }] }
          }}
          checkboxSelection
          onRowSelectionModelChange={(model) => {
            // Extract IDs from the selection model (v9 format)
            const ids = 'ids' in model ? Array.from(model.ids) : [];
            setSelectedIds(ids as number[]);
          }}
          disableRowSelectionOnClick 
          sx={{ border: 'none' }} 
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
          <Button variant="contained" onClick={handleSave}>{editingTask ? 'Save' : 'Create'}</Button>
        </DialogActions>
      </Dialog>

      <Snackbar open={snackbar.open} autoHideDuration={3000} onClose={() => setSnackbar({ ...snackbar, open: false })}>
        <Alert onClose={() => setSnackbar({ ...snackbar, open: false })} severity={snackbar.severity} sx={{ width: '100%' }}>{snackbar.message}</Alert>
      </Snackbar>
    </Box>
  );
}

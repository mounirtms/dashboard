import { Box, Typography, Card, CardContent, Grid, Chip, Button, TextField, Dialog, DialogTitle, DialogContent, DialogActions, Select, MenuItem, FormControl, InputLabel } from '@mui/material';
import { Add, Task, CheckCircle, HourglassEmpty, Pending, Delete } from '@mui/icons-material';
import { useState, useEffect, useCallback } from 'react';
import apiClient from '../api/client';
import LoadingState from '../components/common/LoadingState';

interface TaskItem {
  id: number;
  title: string;
  description: string;
  status: string;
  priority: string;
  assigned_to: string;
  due_date: string;
  created_at: string;
}

const STATUS_COLORS: Record<string, { bg: string; color: string; label: string }> = {
  pending: { bg: 'rgba(251,191,36,0.15)', color: '#fbbf24', label: 'Planned' },
  in_progress: { bg: 'rgba(59,130,246,0.15)', color: '#3b82f6', label: 'In Progress' },
  completed: { bg: 'rgba(74,222,128,0.15)', color: '#4ade80', label: 'Completed' },
  cancelled: { bg: 'rgba(248,113,113,0.15)', color: '#f87171', label: 'Cancelled' },
};

const PRIORITY_COLORS: Record<string, string> = {
  low: '#94a3b8',
  medium: '#fbbf24',
  high: '#f87171',
};

export default function PlansPage() {
  const [tasks, setTasks] = useState<TaskItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [newTask, setNewTask] = useState({ title: '', description: '', status: 'pending', priority: 'medium' });

  const loadTasks = useCallback(() => {
    setLoading(true);
    apiClient.get('/api/tasks.php?action=list')
      .then(({ data }) => {
        setTasks(data.tasks || []);
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    loadTasks();
  }, [loadTasks]);

  const handleCreate = () => {
    if (!newTask.title.trim()) return;
    const params = new URLSearchParams({
      action: 'create',
      title: newTask.title,
      description: newTask.description,
      status: newTask.status,
      priority: newTask.priority,
    });
    apiClient.post(`/api/tasks.php?${params.toString()}`)
      .then(() => {
        setDialogOpen(false);
        setNewTask({ title: '', description: '', status: 'pending', priority: 'medium' });
        loadTasks();
      })
      .catch(() => {});
  };

  const handleStatusChange = (taskId: number, status: string) => {
    apiClient.post(`/api/tasks.php?action=update&id=${taskId}&status=${status}`)
      .then(() => loadTasks())
      .catch(() => {});
  };

  const handleDelete = (taskId: number) => {
    apiClient.post(`/api/tasks.php?action=delete&id=${taskId}`)
      .then(() => loadTasks())
      .catch(() => {});
  };

  if (loading && tasks.length === 0) return <LoadingState message="Loading plans..." />;

  const groupedTasks = {
    pending: tasks.filter(t => t.status === 'pending'),
    in_progress: tasks.filter(t => t.status === 'in_progress'),
    completed: tasks.filter(t => t.status === 'completed'),
  };

  return (
    <Box sx={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Plans & Roadmap
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Track upcoming features, improvements, and completed work.
          </Typography>
        </Box>
        <Button variant="contained" startIcon={<Add />} size="small" onClick={() => setDialogOpen(true)}>
          Add Plan
        </Button>
      </Box>

      {/* Summary */}
      <Grid container spacing={2} sx={{ mb: 3 }}>
        {Object.entries(groupedTasks).map(([status, items]) => {
          const config = STATUS_COLORS[status] || STATUS_COLORS.pending;
          return (
            <Grid size={{ xs: 4 }} key={status}>
              <Card sx={{ bgcolor: `${config.bg}`, border: `1px solid ${config.color}30` }}>
                <CardContent sx={{ py: 2, px: 3, textAlign: 'center' }}>
                  <Typography variant="h4" sx={{ fontWeight: 800, color: config.color }}>
                    {items.length}
                  </Typography>
                  <Typography variant="caption" sx={{ color: config.color, fontWeight: 600, textTransform: 'uppercase' }}>
                    {config.label}
                  </Typography>
                </CardContent>
              </Card>
            </Grid>
          );
        })}
      </Grid>

      {/* Kanban Board */}
      <Grid container spacing={2} sx={{ flex: 1, minHeight: 0 }}>
        {Object.entries(groupedTasks).map(([status, items]) => {
          const config = STATUS_COLORS[status] || STATUS_COLORS.pending;
          return (
            <Grid size={{ xs: 12, md: 4 }} key={status}>
              <Box sx={{ mb: 1.5, display: 'flex', alignItems: 'center', gap: 1 }}>
                {status === 'pending' ? <Pending sx={{ fontSize: 18, color: config.color }} /> :
                 status === 'in_progress' ? <HourglassEmpty sx={{ fontSize: 18, color: config.color }} /> :
                 <CheckCircle sx={{ fontSize: 18, color: config.color }} />}
                <Typography variant="subtitle2" sx={{ fontWeight: 700, color: config.color }}>
                  {config.label}
                </Typography>
                <Chip label={items.length} size="small" sx={{ ml: 'auto', bgcolor: `${config.color}20`, color: config.color, height: 18, fontSize: '0.6rem' }} />
              </Box>
              <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1, maxHeight: 'calc(100vh - 320px)', overflow: 'auto' }}>
                {items.map(task => (
                  <Card key={task.id} sx={{ bgcolor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.06)' }}>
                    <CardContent sx={{ py: 1.5, px: 2 }}>
                      <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', mb: 0.5 }}>
                        <Typography variant="body2" sx={{ fontWeight: 600, fontSize: '0.8rem', flex: 1 }}>
                          {task.title}
                        </Typography>
                        <Chip
                          label={task.priority}
                          size="small"
                          sx={{ bgcolor: `${PRIORITY_COLORS[task.priority] || '#94a3b8'}20`, color: PRIORITY_COLORS[task.priority] || '#94a3b8', fontWeight: 600, fontSize: '0.55rem', height: 16, ml: 1 }}
                        />
                      </Box>
                      {task.description && (
                        <Typography variant="caption" sx={{ color: 'text.disabled', display: 'block', mb: 1, lineHeight: 1.3 }}>
                          {task.description.slice(0, 100)}{task.description.length > 100 ? '...' : ''}
                        </Typography>
                      )}
                      <Box sx={{ display: 'flex', gap: 0.5, alignItems: 'center' }}>
                        {status !== 'completed' && (
                          <Button size="small" sx={{ minWidth: 'auto', p: 0.3, fontSize: '0.6rem' }} onClick={() => handleStatusChange(task.id, status === 'pending' ? 'in_progress' : 'completed')}>
                            {status === 'pending' ? 'Start' : 'Complete'}
                          </Button>
                        )}
                        {status !== 'pending' && (
                          <Button size="small" sx={{ minWidth: 'auto', p: 0.3, fontSize: '0.6rem' }} onClick={() => handleStatusChange(task.id, 'pending')}>
                            Reset
                          </Button>
                        )}
                        <Button size="small" color="error" sx={{ minWidth: 'auto', p: 0.3, fontSize: '0.6rem', ml: 'auto' }} onClick={() => handleDelete(task.id)}>
                          <Delete sx={{ fontSize: 12 }} />
                        </Button>
                      </Box>
                    </CardContent>
                  </Card>
                ))}
                {items.length === 0 && (
                  <Box sx={{ py: 4, textAlign: 'center', border: '1px dashed rgba(255,255,255,0.1)', borderRadius: 1 }}>
                    <Task sx={{ fontSize: 24, color: 'text.disabled', mb: 0.5 }} />
                    <Typography variant="caption" sx={{ color: 'text.disabled' }}>No items</Typography>
                  </Box>
                )}
              </Box>
            </Grid>
          );
        })}
      </Grid>

      {/* Create Dialog */}
      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Add New Plan</DialogTitle>
        <DialogContent>
          <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2, mt: 1 }}>
            <TextField
              label="Title"
              value={newTask.title}
              onChange={(e) => setNewTask(prev => ({ ...prev, title: e.target.value }))}
              fullWidth
              autoFocus
            />
            <TextField
              label="Description"
              value={newTask.description}
              onChange={(e) => setNewTask(prev => ({ ...prev, description: e.target.value }))}
              fullWidth
              multiline
              rows={3}
            />
            <FormControl size="small">
              <InputLabel>Priority</InputLabel>
              <Select
                value={newTask.priority}
                label="Priority"
                onChange={(e) => setNewTask(prev => ({ ...prev, priority: e.target.value }))}
              >
                <MenuItem value="low">Low</MenuItem>
                <MenuItem value="medium">Medium</MenuItem>
                <MenuItem value="high">High</MenuItem>
              </Select>
            </FormControl>
          </Box>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDialogOpen(false)}>Cancel</Button>
          <Button variant="contained" onClick={handleCreate} disabled={!newTask.title.trim()}>
            Create
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
}

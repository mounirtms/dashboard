import { Box, Typography, Card, CardContent, Button, Chip, TextField, IconButton, Tooltip, Divider, Avatar, CircularProgress, FormControl, InputLabel, Select, MenuItem, Paper, Menu, MenuItem as MuiMenuItem, Popper, ClickAwayListener, List, ListItem, ListItemButton, ListItemAvatar, ListItemText, Dialog, DialogTitle, DialogContent, DialogActions, Link } from '@mui/material';
import { ArrowBack, Delete, Send, CheckCircle, Edit, PushPin, Reply, Code, FormatQuote, MoreVert, Save, Close, AddPhotoAlternate, OpenInNew, KeyboardArrowRight, Link as LinkIcon, UnfoldMore, LinkOff } from '@mui/icons-material';
import React, { useState, useEffect, useRef, useCallback } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { fetchTasks, fetchTask, updateTask, fetchTaskNotes, addNote, deleteNote, editNote, pinNote, fetchTaskActivity, fetchScreenshots, deleteScreenshot, forwardNote, setNoteStatus, uploadScreenshot, getTaskLinks, linkTask, unlinkTask, type Task, type TaskNote, type TaskActivity, type TaskScreenshot, type TaskLink, type Task as TaskType, getTaskStatusColor, NOTE_CATEGORIES, TASK_CATEGORIES } from '../api/tasks';
import { fetchUsers, type User } from '../api/users';
import LoadingState from '../components/common/LoadingState';
import { usePermissions } from '../hooks/usePermissions';
import { useAuth } from '../hooks/useAuth';

export default function TaskDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { permissions } = usePermissions();
  const { user } = useAuth();
  const currentUser = user?.username || 'system';
  const [task, setTask] = useState<Task | null>(null);
  const [notes, setNotes] = useState<TaskNote[]>([]);
  const [activity, setActivity] = useState<TaskActivity[]>([]);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState(0);
  const [noteText, setNoteText] = useState('');
  const [noteCategory, setNoteCategory] = useState<'tuning' | 'fix' | 'implementation' | 'question' | 'general'>('general');
  const [noteSubmitting, setNoteSubmitting] = useState(false);
  const [editing, setEditing] = useState(false);
  const [editData, setEditData] = useState({ title: '', description: '', priority: 'medium' as 'low' | 'medium' | 'high', status: 'pending' as 'pending' | 'in-progress' | 'completed' | 'cancelled', assigned_to: '', due_date: '', category: 'general' });
  
  // Notes UX state
  const [editingNoteId, setEditingNoteId] = useState<number | null>(null);
  const [editNoteText, setEditNoteText] = useState('');
  const [replyingTo, setReplyingTo] = useState<number | null>(null);
  const [replyText, setReplyText] = useState('');
  const [noteMenuAnchor, setNoteMenuAnchor] = useState<null | HTMLElement>(null);
  const [activeNoteMenu, setActiveNoteMenu] = useState<TaskNote | null>(null);
  
  // Screenshots
  const [screenshots, setScreenshots] = useState<TaskScreenshot[]>([]);
  const [screenshotUploading, setScreenshotUploading] = useState(false);
  const screenshotInputRef = useRef<HTMLInputElement>(null);
  
  // Note forward and status
  const [forwardDialogOpen, setForwardDialogOpen] = useState(false);
  const [forwardingNote, setForwardingNote] = useState<TaskNote | null>(null);
  const [forwardTargetTaskId, setForwardTargetTaskId] = useState('');
  const [allTasks, setAllTasks] = useState<TaskType[]>([]);

  // Linked tasks
  const [taskLinks, setTaskLinks] = useState<TaskLink[]>([]);
  const [linkDialogOpen, setLinkDialogOpen] = useState(false);
  const [linkDialogType, setLinkDialogType] = useState<TaskLink['link_type']>('related');
  const [linkTargetTaskId, setLinkTargetTaskId] = useState('');

  // @mention state
  const [users, setUsers] = useState<User[]>([]);
  const [mentionQuery, setMentionQuery] = useState('');
  const [mentionVisible, setMentionVisible] = useState(false);
  const [mentionSelectedIndex, setMentionSelectedIndex] = useState(0);
  const [mentionCursorPos, setMentionCursorPos] = useState({ start: 0, end: 0 });
  const noteInputRef = useRef<HTMLDivElement>(null);
  const replyInputRef = useRef<HTMLDivElement>(null);

  const avatarColors = ['#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#06b6d4', '#ef4444', '#6366f1'];
  const getAvatarColor = (name: string) => {
    let hash = 0;
    for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
    return avatarColors[Math.abs(hash) % avatarColors.length];
  };

  const getInitials = (name: string) => name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);

  const getCategoryConfig = (category: string) => {
    return NOTE_CATEGORIES.find(c => c.value === category) || NOTE_CATEGORIES[4]; // general is default
  };

  const relativeTime = (dateStr: string) => {
    const now = new Date();
    const date = new Date(dateStr);
    const diffMs = now.getTime() - date.getTime();
    const diffSec = Math.floor(diffMs / 1000);
    const diffMin = Math.floor(diffSec / 60);
    const diffHr = Math.floor(diffMin / 60);
    const diffDay = Math.floor(diffHr / 24);
    if (diffSec < 60) return 'just now';
    if (diffMin < 60) return `${diffMin}m ago`;
    if (diffHr < 24) return `${diffHr}h ago`;
    if (diffDay < 7) return `${diffDay}d ago`;
    return date.toLocaleDateString();
  };

  const highlightMentions = (text: string) => {
    const parts = text.split(/(@\w+)/g);
    return parts.map((part, i) => {
      if (part.startsWith('@')) {
        return <Box key={i} component="span" sx={{ color: 'primary.main', fontWeight: 600, cursor: 'pointer' }}>{part}</Box>;
      }
      return part;
    });
  };

  const renderContent = (content: string) => {
    const lines = content.split('\n');
    const elements: React.ReactElement[] = [];
    let inCodeBlock = false;
    let codeLines: string[] = [];
    let codeKey = 0;

    lines.forEach((line, i) => {
      if (line.startsWith('```')) {
        if (inCodeBlock) {
          elements.push(
            <Paper key={`code-${codeKey++}`} sx={{ p: 1.5, my: 1, backgroundColor: 'rgba(0,0,0,0.3)', fontFamily: 'monospace', fontSize: '0.75rem', overflowX: 'auto', border: '1px solid rgba(255,255,255,0.05)' }}>
              <pre style={{ margin: 0 }}>{codeLines.join('\n')}</pre>
            </Paper>
          );
          codeLines = [];
          inCodeBlock = false;
        } else {
          inCodeBlock = true;
        }
      } else if (inCodeBlock) {
        codeLines.push(line);
      } else if (line.startsWith('> ')) {
        elements.push(
          <Box key={`q-${i}`} sx={{ pl: 1.5, ml: 1, borderLeft: '3px solid', borderColor: 'primary.main', my: 0.5, color: 'text.secondary', fontStyle: 'italic', fontSize: '0.8rem' }}>
            {highlightMentions(line.slice(2))}
          </Box>
        );
      } else {
        elements.push(
          <Typography key={`t-${i}`} variant="body2" sx={{ my: 0.25, whiteSpace: 'pre-wrap' }}>
            {highlightMentions(line)}
          </Typography>
        );
      }
    });

    if (codeLines.length > 0) {
      elements.push(
        <Paper key={`code-end-${codeKey}`} sx={{ p: 1.5, my: 1, backgroundColor: 'rgba(0,0,0,0.3)', fontFamily: 'monospace', fontSize: '0.75rem', overflowX: 'auto', border: '1px solid rgba(255,255,255,0.05)' }}>
          <pre style={{ margin: 0 }}>{codeLines.join('\n')}</pre>
        </Paper>
      );
    }

    return elements;
  };

  const handleAddNote = async () => {
    if (!task || !noteText.trim()) return;
    setNoteSubmitting(true);
    try {
      await addNote(task.id, noteText.trim(), undefined, noteCategory);
      setNoteText('');
      setNoteCategory('general');
      loadData();
    } catch (e: any) { console.error(e); }
    finally { setNoteSubmitting(false); }
  };

  const handleReply = async (parentId: number) => {
    if (!task || !replyText.trim()) return;
    try {
      await addNote(task.id, replyText.trim(), parentId);
      setReplyText('');
      setReplyingTo(null);
      loadData();
    } catch (e: any) { console.error(e); }
  };

  const startEditNote = (note: TaskNote) => {
    setEditingNoteId(note.id);
    setEditNoteText(note.content);
  };

  const saveEditNote = async (noteId: number) => {
    if (!editNoteText.trim()) return;
    try {
      await editNote(noteId, editNoteText.trim());
      setEditingNoteId(null);
      setEditNoteText('');
      loadData();
    } catch (e: any) { console.error(e); }
  };

  const handlePinNote = async (noteId: number, isPinned: boolean) => {
    try {
      await pinNote(noteId, isPinned);
      setNoteMenuAnchor(null);
      loadData();
    } catch (e: any) { console.error(e); }
  };

  const openNoteMenu = (event: React.MouseEvent<HTMLElement>, note: TaskNote) => {
    setNoteMenuAnchor(event.currentTarget);
    setActiveNoteMenu(note);
  };
  
  const handleScreenshotUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file || !task) return;
    setScreenshotUploading(true);
    try {
      await uploadScreenshot(task.id, file);
      loadData();
    } catch (err: any) {
      console.error('Screenshot upload failed:', err);
    } finally {
      setScreenshotUploading(false);
      if (screenshotInputRef.current) screenshotInputRef.current.value = '';
    }
  };
  
  const handleDeleteScreenshot = async (screenshotId: number) => {
    try {
      await deleteScreenshot(screenshotId);
      setScreenshots(prev => prev.filter(s => s.id !== screenshotId));
    } catch (err: any) {
      console.error('Screenshot delete failed:', err);
    }
  };
  
  const handleForwardNote = async () => {
    if (!forwardingNote || !forwardTargetTaskId) return;
    try {
      await forwardNote(forwardingNote.id, parseInt(forwardTargetTaskId));
      setForwardDialogOpen(false);
      setForwardingNote(null);
      setForwardTargetTaskId('');
      loadData();
    } catch (err: any) {
      console.error('Forward note failed:', err);
    }
  };
  
  const handleSetNoteStatus = async (noteId: number, status: TaskNote['status']) => {
    try {
      await setNoteStatus(noteId, status);
      loadData();
    } catch (err: any) {
      console.error('Set note status failed:', err);
    }
  };

  const loadAllTasks = useCallback(() => {
    if (allTasks.length === 0) {
      fetchTasks().then(result => setAllTasks(result.tasks)).catch(console.error);
    }
  }, [allTasks.length]);

  const loadData = useCallback(() => {
    if (!id) return;
    setLoading(true);
    Promise.all([fetchTask(+id), fetchTaskNotes(+id), fetchTaskActivity(+id), fetchUsers(), fetchScreenshots(+id), getTaskLinks(+id)])
      .then(([t, n, a, u, s, links]) => { setTask(t); setNotes(n); setActivity(a); setUsers(u); setScreenshots(s); setTaskLinks(links); setEditData({ title: t.title, description: t.description || '', priority: t.priority, status: t.status, assigned_to: t.assigned_to, due_date: t.due_date || '', category: t.category }); })
      .catch((e) => console.error(e))
      .finally(() => setLoading(false));
  }, [id]);

  // @mention helpers
  const handleMentionInput = (value: string, cursorPos: number) => {
    const textBeforeCursor = value.substring(0, cursorPos);
    const mentionMatch = textBeforeCursor.match(/@(\w*)$/);
    if (mentionMatch) {
      setMentionQuery(mentionMatch[1]);
      setMentionVisible(true);
      setMentionSelectedIndex(0);
      setMentionCursorPos({ start: cursorPos - mentionMatch[0].length, end: cursorPos });
    } else {
      setMentionVisible(false);
      setMentionQuery('');
    }
  };

  const selectMention = (username: string, isReply: boolean) => {
    const currentText = isReply ? replyText : noteText;
    const { start, end } = mentionCursorPos;
    const before = currentText.substring(0, start);
    const after = currentText.substring(end);
    const newText = before + '@' + username + ' ' + after;
    if (isReply) {
      setReplyText(newText);
    } else {
      setNoteText(newText);
    }
    setMentionVisible(false);
    setMentionQuery('');
  };

  const filteredUsers = users.filter(u =>
    u.is_active && (u.username.toLowerCase().includes(mentionQuery.toLowerCase()) || u.full_name?.toLowerCase().includes(mentionQuery.toLowerCase()))
  ).slice(0, 6);

  useEffect(() => { loadData(); }, [id]);

  const handleSave = useCallback(async () => {
    if (!task || !editData.title.trim()) return;
    try {
      await updateTask({ id: task.id, ...editData });
      setEditing(false);
      loadData();
    } catch (e: any) { console.error(e); }
  }, [task, editData]);

  const handleDeleteNote = async (noteId: number) => {
    try { await deleteNote(noteId); loadData(); } catch (e: any) { console.error(e); }
  };

  const handleStatusToggle = async () => {
    if (!task) return;
    try {
      const cycle: Task['status'][] = ['pending', 'in-progress', 'completed'];
      const currentIndex = cycle.indexOf(task.status as any);
      const nextStatus = task.status === 'completed' ? 'pending' : cycle[(currentIndex + 1) % cycle.length];
      await updateTask({ id: task.id, status: nextStatus });
      loadData();
    } catch (e: any) { console.error(e); }
  };

  const handleLinkTask = async () => {
    if (!task || !linkTargetTaskId) return;
    try {
      await linkTask(task.id, parseInt(linkTargetTaskId), linkDialogType);
      setLinkDialogOpen(false);
      setLinkTargetTaskId('');
      loadData();
    } catch (err: any) {
      console.error('Link task failed:', err);
    }
  };

  const handleUnlinkTask = async (linkId: number) => {
    try {
      await unlinkTask(linkId);
      loadData();
    } catch (err: any) {
      console.error('Unlink task failed:', err);
    }
  };

  const linkTypeConfig: Record<string, { label: string; color: string; icon: string }> = {
    'blocks': { label: 'Blocks', color: 'error', icon: '🚫' },
    'blocked-by': { label: 'Blocked by', color: 'warning', icon: '⏸️' },
    'related': { label: 'Related', color: 'info', icon: '🔗' },
    'duplicate-of': { label: 'Duplicate of', color: 'default', icon: '📋' },
  };

  // Keyboard shortcuts
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      // Ctrl+S to save when editing
      if ((e.ctrlKey || e.metaKey) && e.key === 's' && editing) {
        e.preventDefault();
        handleSave();
      }
      // Escape to close edit mode or dialogs
      if (e.key === 'Escape') {
        if (editing) setEditing(false);
        if (forwardDialogOpen) setForwardDialogOpen(false);
        if (linkDialogOpen) setLinkDialogOpen(false);
      }
    };
    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [editing, handleSave, forwardDialogOpen, linkDialogOpen]);

  if (loading) return <LoadingState message="Loading task details..." />;
  if (!task) return <Box sx={{ p: 3 }}><Typography>Task not found</Typography></Box>;

  const tabs = ['Overview', 'Notes', 'Activity', 'Settings'];
  const priorityColor = (p: string) => p === 'high' ? 'error' : p === 'medium' ? 'warning' : 'default';
  const actionIcons: Record<string, string> = { created: '📝', updated: '✏️', status_changed: '🔄', commented: '💬', deleted: '🗑️' };

  return (
    <Box sx={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
      {/* Header */}
      <Box sx={{ mb: 3, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
          <IconButton onClick={() => navigate('/tasks')}><ArrowBack /></IconButton>
          <Box>
            <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em' }}>{task.title}</Typography>
            <Typography variant="caption" sx={{ color: 'text.disabled' }}>#{task.id} · Created {new Date(task.created_at).toLocaleDateString()} by {task.created_by}</Typography>
          </Box>
        </Box>
        <Box sx={{ display: 'flex', gap: 1 }}>
          <Chip label={task.priority.toUpperCase()} size="small" color={priorityColor(task.priority)} sx={{ fontWeight: 700 }} />
          <Chip label={task.status.toUpperCase().replace('-', ' ')} size="small" color={getTaskStatusColor(task.status)} sx={{ fontWeight: 700, cursor: 'pointer' }} onClick={handleStatusToggle} title="Click to cycle status" />
          {task.status !== 'completed' && <Tooltip title="Mark Complete"><IconButton size="small" color="success" onClick={handleStatusToggle}><CheckCircle /></IconButton></Tooltip>}
          {(task.created_by === currentUser || permissions?.can_update_any_task) && (
            <Tooltip title="Edit"><IconButton size="small" onClick={() => setEditing(!editing)}><Edit /></IconButton></Tooltip>
          )}
        </Box>
      </Box>

      {/* Tabs */}
      <Box sx={{ borderBottom: '1px solid #1e293b', mb: 3, display: 'flex', gap: 1 }}>
        {tabs.map((tab, i) => (
          <Button key={tab} size="small" onClick={() => setActiveTab(i)} sx={{ borderRadius: 1, py: 0.5, px: 2, fontSize: '0.75rem', fontWeight: activeTab === i ? 700 : 500, color: activeTab === i ? 'primary.main' : 'text.secondary', backgroundColor: activeTab === i ? 'rgba(59,130,246,0.1)' : 'transparent', '&:hover': { backgroundColor: activeTab === i ? 'rgba(59,130,246,0.15)' : 'rgba(255,255,255,0.03)' } }}>{tab}</Button>
        ))}
      </Box>

      {/* Tab Content */}
      {activeTab === 0 && (
        <Box sx={{ display: 'grid', gap: 3 }}>
          <Card>
            <CardContent>
              <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1 }}>Description</Typography>
              <Typography variant="body2" sx={{ color: 'text.secondary', whiteSpace: 'pre-wrap' }}>{task.description || 'No description provided.'}</Typography>
            </CardContent>
          </Card>
          <Box sx={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 2 }}>
            <Card><CardContent><Typography variant="caption" sx={{ color: 'text.disabled' }}>Assigned To</Typography><Typography variant="body2" sx={{ fontWeight: 600 }}>{task.assigned_to || 'Unassigned'}</Typography></CardContent></Card>
            <Card><CardContent><Typography variant="caption" sx={{ color: 'text.disabled' }}>Due Date</Typography><Typography variant="body2" sx={{ fontWeight: 600 }}>{task.due_date ? new Date(task.due_date).toLocaleDateString() : 'No due date'}</Typography></CardContent></Card>
            <Card><CardContent><Typography variant="caption" sx={{ color: 'text.disabled' }}>Category</Typography><Typography variant="body2" sx={{ fontWeight: 600, textTransform: 'capitalize' }}>{task.category}</Typography></CardContent></Card>
          </Box>
          
          {/* Linked Tasks */}
          <Card>
            <CardContent>
              <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 1 }}>
                <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>Linked Tasks ({taskLinks.length})</Typography>
                <Button size="small" startIcon={<LinkIcon sx={{ fontSize: 14 }} />} onClick={() => { setLinkDialogOpen(true); loadAllTasks(); }}>Link Task</Button>
              </Box>
              {taskLinks.length === 0 ? (
                <Typography variant="caption" sx={{ color: 'text.disabled' }}>No linked tasks yet. Link related, blocking, or duplicate tasks.</Typography>
              ) : (
                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                  {taskLinks.map(link => {
                    const config = linkTypeConfig[link.link_type] || linkTypeConfig.related;
                    return (
                      <Box key={link.id} sx={{ display: 'flex', alignItems: 'center', gap: 1, p: 1, borderRadius: 1, backgroundColor: 'background.default', border: '1px solid', borderColor: 'divider' }}>
                        <Chip label={`${config.icon} ${config.label}`} size="small" color={config.color as any} sx={{ height: 20, fontSize: '0.6rem' }} />
                        <Link 
                          component="button"
                          sx={{ cursor: 'pointer', fontSize: '0.8rem', fontWeight: 600 }}
                          onClick={() => navigate(`/tasks/${link.linked_task_id}`)}
                        >
                          #{link.linked_task_id} - {link.linked_title}
                        </Link>
                        <Chip label={link.linked_status.replace('-', ' ')} size="small" color={getTaskStatusColor(link.linked_status)} sx={{ height: 18, fontSize: '0.55rem', ml: 'auto' }} />
                        <IconButton size="small" onClick={() => handleUnlinkTask(link.id)} sx={{ p: 0.25 }}>
                          <LinkOff sx={{ fontSize: 14, color: 'text.disabled' }} />
                        </IconButton>
                      </Box>
                    );
                  })}
                </Box>
              )}
            </CardContent>
          </Card>
        </Box>
      )}

      {activeTab === 1 && (
        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
          {/* Screenshots Section */}
          <Paper sx={{ p: 2, backgroundColor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.05)' }}>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
              <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>Screenshots ({screenshots.length})</Typography>
              <input
                type="file"
                accept="image/*"
                ref={screenshotInputRef}
                style={{ display: 'none' }}
                onChange={handleScreenshotUpload}
              />
              <Button
                size="small"
                startIcon={screenshotUploading ? <CircularProgress size={16} /> : <AddPhotoAlternate />}
                onClick={() => screenshotInputRef.current?.click()}
                disabled={screenshotUploading}
              >
                {screenshotUploading ? 'Uploading...' : 'Add Screenshot'}
              </Button>
            </Box>
            {screenshots.length > 0 && (
              <Box sx={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))', gap: 2 }}>
                {screenshots.map(ss => (
                  <Box key={ss.id} sx={{ position: 'relative', borderRadius: 1, overflow: 'hidden', border: '1px solid', borderColor: 'divider' }}>
                    <img src={ss.file_path} alt={ss.caption} style={{ width: '100%', height: 140, objectFit: 'cover' }} />
                    {ss.caption && (
                      <Typography variant="caption" sx={{ display: 'block', p: 0.5, backgroundColor: 'background.paper' }}>{ss.caption}</Typography>
                    )}
                    <Typography variant="caption" sx={{ display: 'block', p: 0.5, color: 'text.disabled' }}>by {ss.author}</Typography>
                    <IconButton
                      size="small"
                      color="error"
                      sx={{ position: 'absolute', top: 4, right: 4, backgroundColor: 'rgba(0,0,0,0.6)', '&:hover': { backgroundColor: 'rgba(0,0,0,0.8)' } }}
                      onClick={() => handleDeleteScreenshot(ss.id)}
                    >
                      <Delete sx={{ fontSize: 16 }} />
                    </IconButton>
                  </Box>
                ))}
              </Box>
            )}
          </Paper>
          
          {/* Notes List */}
          {notes.length === 0 && (
            <Box sx={{ textAlign: 'center', py: 6, color: 'text.disabled' }}>
              <Typography variant="body2">No notes yet. Start the conversation with a tuning request, fix report, or implementation note.</Typography>
            </Box>
          )}
          
          {(() => {
            // Pre-group replies by parent_id to avoid O(n^2) in render loop
            const repliesByParentId = new Map<number, TaskNote[]>();
            notes.forEach(note => {
              if (note.parent_id) {
                if (!repliesByParentId.has(note.parent_id)) repliesByParentId.set(note.parent_id, []);
                repliesByParentId.get(note.parent_id)!.push(note);
              }
            });
            return notes.map(note => {
            const isRoot = !note.parent_id;
            const isEditing = editingNoteId === note.id;
            const isReplying = replyingTo === note.id;
            const isOwn = note.author === currentUser;
            const cat = getCategoryConfig(note.category);

            return (
              <Box key={note.id} sx={{ ml: note.parent_id ? 4 : 0, borderLeft: note.parent_id ? '2px solid rgba(255,255,255,0.05)' : 'none', pl: note.parent_id ? 2 : 0 }}>
                <Paper sx={{ p: 2, backgroundColor: note.is_pinned ? 'rgba(59,130,246,0.06)' : 'rgba(255,255,255,0.02)', border: note.is_pinned ? '1px solid rgba(59,130,246,0.2)' : '1px solid rgba(255,255,255,0.05)' }}>
                  {/* Header */}
                  <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 1 }}>
                    <Avatar sx={{ width: 28, height: 28, fontSize: '0.65rem', fontWeight: 700, backgroundColor: getAvatarColor(note.author) }}>
                      {getInitials(note.author)}
                    </Avatar>
                    <Typography variant="caption" sx={{ fontWeight: 700 }}>{note.author}</Typography>
                    <Chip label={`${cat.icon} ${cat.label}`} size="small" color={cat.color as any} sx={{ height: 18, fontSize: '0.6rem', fontWeight: 600 }} />
                    {note.is_pinned && <PushPin sx={{ fontSize: 12, color: 'primary.main' }} />}
                    {note.status && note.status !== 'active' && (
                      <Chip
                        size="small"
                        label={note.status}
                        color={note.status === 'reviewed' ? 'success' : note.status === 'action-required' ? 'error' : 'default'}
                        sx={{ height: 18, fontSize: '0.6rem', fontWeight: 600 }}
                      />
                    )}
                    <Typography variant="caption" sx={{ color: 'text.disabled', ml: 'auto' }} title={new Date(note.created_at).toLocaleString()}>
                      {note.updated_at !== note.created_at ? `edited ${relativeTime(note.updated_at)}` : relativeTime(note.created_at)}
                    </Typography>
                    <IconButton size="small" onClick={(e) => openNoteMenu(e, note)} sx={{ p: 0.25 }}>
                      <MoreVert sx={{ fontSize: 14 }} />
                    </IconButton>
                  </Box>

                  {/* Content */}
                  {isEditing ? (
                    <Box sx={{ mt: 1 }}>
                      <TextField
                        fullWidth
                        multiline
                        rows={3}
                        size="small"
                        value={editNoteText}
                        onChange={(e) => setEditNoteText(e.target.value)}
                        placeholder="Edit your note..."
                        sx={{ mb: 1, '& .MuiInputBase-root': { fontSize: '0.82rem' } }}
                      />
                      <Box sx={{ display: 'flex', gap: 1 }}>
                        <Button size="small" variant="contained" onClick={() => saveEditNote(note.id)} disabled={!editNoteText.trim()}>
                          <Save sx={{ fontSize: 14, mr: 0.5 }} /> Save
                        </Button>
                        <Button size="small" onClick={() => { setEditingNoteId(null); setEditNoteText(''); }}>
                          <Close sx={{ fontSize: 14, mr: 0.5 }} /> Cancel
                        </Button>
                      </Box>
                    </Box>
                  ) : (
                    <Box sx={{ pl: 0.5 }}>
                      {renderContent(note.content)}
                    </Box>
                  )}

                  {/* Actions */}
                  {!isEditing && (
                    <Box sx={{ display: 'flex', gap: 1, mt: 1.5, pt: 1, borderTop: '1px solid rgba(255,255,255,0.05)' }}>
                      <Button size="small" startIcon={<Reply sx={{ fontSize: 14 }} />} onClick={() => setReplyingTo(isReplying ? null : note.id)} sx={{ fontSize: '0.7rem', color: 'text.secondary' }}>
                        Reply
                      </Button>
                      {(isOwn || permissions?.can_edit_any_note) && (
                        <Button size="small" startIcon={<Edit sx={{ fontSize: 14 }} />} onClick={() => startEditNote(note)} sx={{ fontSize: '0.7rem', color: 'text.secondary' }}>
                          Edit
                        </Button>
                      )}
                    </Box>
                  )}

                  {/* Reply Input */}
                  {isReplying && (
                    <Box sx={{ mt: 1.5, display: 'flex', gap: 1, position: 'relative' }}>
                      <Box sx={{ flex: 1, position: 'relative' }}>
                        <TextField
                          fullWidth
                          size="small"
                          multiline
                          rows={2}
                          value={replyText}
                          onChange={(e) => {
                            setReplyText(e.target.value);
                            const target = e.target as HTMLTextAreaElement;
                            handleMentionInput(e.target.value, target.selectionStart || 0);
                          }}
                          placeholder={`Reply to ${note.author}... Type @ to mention`}
                          onKeyDown={(e) => {
                            if (mentionVisible && filteredUsers.length > 0) {
                              if (e.key === 'ArrowDown') { e.preventDefault(); setMentionSelectedIndex(i => Math.min(i + 1, filteredUsers.length - 1)); }
                              else if (e.key === 'ArrowUp') { e.preventDefault(); setMentionSelectedIndex(i => Math.max(i - 1, 0)); }
                              else if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); selectMention(filteredUsers[mentionSelectedIndex].username, true); }
                              else if (e.key === 'Escape') { setMentionVisible(false); }
                            }
                            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleReply(note.id); }
                          }}
                        />
                        {/* @mention dropdown for reply */}
                        {mentionVisible && filteredUsers.length > 0 && (
                          <Paper sx={{ position: 'absolute', bottom: '100%', left: 0, zIndex: 10, width: 260, maxHeight: 200, overflow: 'auto', background: '#1e293b', border: '1px solid #334155', boxShadow: '0 8px 24px rgba(0,0,0,0.4)' }}>
                            <List dense disablePadding>
                              {filteredUsers.map((user, i) => (
                                <ListItemButton
                                  key={user.id}
                                  selected={i === mentionSelectedIndex}
                                  onClick={() => selectMention(user.username, true)}
                                  sx={{ py: 0.5, '&.Mui-selected': { backgroundColor: 'rgba(59,130,246,0.15)' }, '&:hover': { backgroundColor: 'rgba(59,130,246,0.1)' } }}
                                >
                                  <ListItemAvatar>
                                    <Avatar sx={{ width: 24, height: 24, fontSize: '0.65rem', bgcolor: user.role === 'admin' ? '#ef4444' : '#3b82f6' }}>{user.username.charAt(0).toUpperCase()}</Avatar>
                                  </ListItemAvatar>
                                  <ListItemText
                                    primary={<Typography variant="body2" sx={{ fontSize: '0.8rem', fontWeight: 600 }}>{user.full_name || user.username}</Typography>}
                                    secondary={<Typography variant="caption" sx={{ fontSize: '0.65rem', color: 'text.secondary' }}>{user.role} · @{user.username}</Typography>}
                                  />
                                </ListItemButton>
                              ))}
                            </List>
                          </Paper>
                        )}
                      </Box>
                      <Button size="small" variant="contained" onClick={() => handleReply(note.id)} disabled={!replyText.trim()}>
                        <Send sx={{ fontSize: 14 }} />
                      </Button>
                      <IconButton size="small" onClick={() => { setReplyingTo(null); setReplyText(''); setMentionVisible(false); }}>
                        <Close sx={{ fontSize: 16 }} />
                      </IconButton>
                    </Box>
                  )}
                </Paper>

                {/* Replies */}
                {(repliesByParentId.get(note.id) || []).map(reply => {
                  const replyCat = getCategoryConfig(reply.category);
                  return (
                    <Paper key={reply.id} sx={{ mt: 1, p: 1.5, backgroundColor: 'rgba(255,255,255,0.01)', border: '1px solid rgba(255,255,255,0.03)' }}>
                      <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 0.5 }}>
                        <Avatar sx={{ width: 22, height: 22, fontSize: '0.55rem', fontWeight: 700, backgroundColor: getAvatarColor(reply.author) }}>
                          {getInitials(reply.author)}
                        </Avatar>
                        <Typography variant="caption" sx={{ fontWeight: 700, fontSize: '0.68rem' }}>{reply.author}</Typography>
                        <Typography variant="caption" sx={{ color: 'text.disabled', fontSize: '0.65rem' }} title={new Date(reply.created_at).toLocaleString()}>
                          {relativeTime(reply.created_at)}
                        </Typography>
                      </Box>
                      <Box sx={{ pl: 0.5 }}>{renderContent(reply.content)}</Box>
                    </Paper>
                  );
                })}
              </Box>
            );
            });
          })()}

          {/* New Note Input */}
          <Paper sx={{ p: 2, backgroundColor: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.05)' }}>
            <Box sx={{ display: 'flex', gap: 1.5, alignItems: 'flex-start' }}>
              <Avatar sx={{ width: 32, height: 32, fontSize: '0.7rem', fontWeight: 700, backgroundColor: getAvatarColor(currentUser), flexShrink: 0 }}>
                {getInitials(currentUser)}
              </Avatar>
              <Box sx={{ flex: 1, position: 'relative' }}>
                <TextField
                  fullWidth
                  multiline
                  rows={3}
                  size="small"
                  value={noteText}
                  onChange={(e) => {
                    setNoteText(e.target.value);
                    const target = e.target as HTMLTextAreaElement;
                    handleMentionInput(e.target.value, target.selectionStart || 0);
                  }}
                  placeholder="Add a note... Use ``` for code blocks, > for quotes, @username for mentions"
                  onKeyDown={(e) => {
                    if (mentionVisible && filteredUsers.length > 0) {
                      if (e.key === 'ArrowDown') { e.preventDefault(); setMentionSelectedIndex(i => Math.min(i + 1, filteredUsers.length - 1)); }
                      else if (e.key === 'ArrowUp') { e.preventDefault(); setMentionSelectedIndex(i => Math.max(i - 1, 0)); }
                      else if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); selectMention(filteredUsers[mentionSelectedIndex].username, false); }
                      else if (e.key === 'Escape') { setMentionVisible(false); }
                    }
                    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); handleAddNote(); }
                  }}
                  sx={{ mb: 1, '& .MuiInputBase-root': { fontSize: '0.82rem' } }}
                />
                {/* @mention dropdown */}
                {mentionVisible && filteredUsers.length > 0 && (
                  <Paper sx={{ position: 'absolute', zIndex: 10, mt: 0.5, width: 260, maxHeight: 200, overflow: 'auto', background: '#1e293b', border: '1px solid #334155', boxShadow: '0 8px 24px rgba(0,0,0,0.4)' }}>
                    <List dense disablePadding>
                      {filteredUsers.map((user, i) => (
                        <ListItemButton
                          key={user.id}
                          selected={i === mentionSelectedIndex}
                          onClick={() => selectMention(user.username, false)}
                          sx={{ py: 0.5, '&.Mui-selected': { backgroundColor: 'rgba(59,130,246,0.15)' }, '&:hover': { backgroundColor: 'rgba(59,130,246,0.1)' } }}
                        >
                          <ListItemAvatar>
                            <Avatar sx={{ width: 24, height: 24, fontSize: '0.65rem', bgcolor: user.role === 'admin' ? '#ef4444' : '#3b82f6' }}>{user.username.charAt(0).toUpperCase()}</Avatar>
                          </ListItemAvatar>
                          <ListItemText
                            primary={<Typography variant="body2" sx={{ fontSize: '0.8rem', fontWeight: 600 }}>{user.full_name || user.username}</Typography>}
                            secondary={<Typography variant="caption" sx={{ fontSize: '0.65rem', color: 'text.secondary' }}>{user.role} · @{user.username}</Typography>}
                          />
                        </ListItemButton>
                      ))}
                    </List>
                  </Paper>
                )}
                <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <Box sx={{ display: 'flex', gap: 0.5, alignItems: 'center' }}>
                    <FormControl size="small" sx={{ width: 130 }}>
                      <InputLabel shrink>Category</InputLabel>
                      <Select
                        value={noteCategory}
                        label="Category"
                        onChange={(e) => setNoteCategory(e.target.value as any)}
                        sx={{ fontSize: '0.72rem', height: 28 }}
                      >
                        {NOTE_CATEGORIES.map((cat) => (
                          <MuiMenuItem key={cat.value} value={cat.value} sx={{ fontSize: '0.75rem' }}>{cat.icon} {cat.label}</MuiMenuItem>
                        ))}
                      </Select>
                    </FormControl>
                    <Typography variant="caption" sx={{ color: 'text.disabled', fontSize: '0.65rem' }}>
                      Ctrl+Enter to send
                    </Typography>
                  </Box>
                  <Button
                    variant="contained"
                    size="small"
                    onClick={handleAddNote}
                    disabled={!noteText.trim() || noteSubmitting}
                    sx={{ fontSize: '0.72rem' }}
                  >
                    {noteSubmitting ? <CircularProgress size={14} /> : <><Send sx={{ fontSize: 14, mr: 0.5 }} /> Add Note</>}
                  </Button>
                </Box>
              </Box>
            </Box>
          </Paper>
        </Box>
      )}

      {activeTab === 2 && (
        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
          {activity.map(a => (
            <Box key={a.id} sx={{ display: 'flex', gap: 1.5, py: 1, px: 2, backgroundColor: 'rgba(255,255,255,0.01)', borderRadius: 1 }}>
              <Typography sx={{ fontSize: '0.9rem' }}>{actionIcons[a.action] || '•'}</Typography>
              <Box>
                <Typography variant="body2" sx={{ fontWeight: 500 }}>
                  <strong>{a.actor}</strong> {a.action.replace('_', ' ')}
                </Typography>
                <Typography variant="caption" sx={{ color: 'text.disabled' }}>{a.details} · {new Date(a.created_at).toLocaleString()}</Typography>
              </Box>
            </Box>
          ))}
        </Box>
      )}

      {activeTab === 3 && (
        <Card>
          <CardContent>
            <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 2 }}>Edit Task</Typography>
            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
              <TextField label="Title" fullWidth size="small" value={editData.title} onChange={(e) => setEditData({ ...editData, title: e.target.value })} />
              <TextField label="Description" fullWidth multiline rows={3} size="small" value={editData.description} onChange={(e) => setEditData({ ...editData, description: e.target.value })} />
              <Box sx={{ display: 'flex', gap: 2 }}>
                <FormControl fullWidth size="small">
                  <InputLabel>Priority</InputLabel>
                  <Select value={editData.priority} label="Priority" onChange={(e) => setEditData({ ...editData, priority: e.target.value as any })}>
                    <MenuItem value="low">Low</MenuItem><MenuItem value="medium">Medium</MenuItem><MenuItem value="high">High</MenuItem>
                  </Select>
                </FormControl>
                <FormControl fullWidth size="small">
                  <InputLabel>Status</InputLabel>
                  <Select value={editData.status} label="Status" onChange={(e) => setEditData({ ...editData, status: e.target.value as any })}>
                    <MenuItem value="pending">Pending</MenuItem><MenuItem value="in-progress">In Progress</MenuItem><MenuItem value="completed">Completed</MenuItem><MenuItem value="cancelled">Cancelled</MenuItem>
                  </Select>
                </FormControl>
              </Box>
              <Box sx={{ display: 'flex', gap: 2 }}>
                <TextField label="Assigned To" fullWidth size="small" value={editData.assigned_to} onChange={(e) => setEditData({ ...editData, assigned_to: e.target.value })} />
                <TextField label="Due Date" fullWidth size="small" type="date" value={editData.due_date} onChange={(e) => setEditData({ ...editData, due_date: e.target.value })} slotProps={{ inputLabel: { shrink: true } }} />
              </Box>
              <FormControl fullWidth size="small">
                <InputLabel>Category</InputLabel>
                <Select value={editData.category} label="Category" onChange={(e) => setEditData({ ...editData, category: e.target.value })}>
                  {TASK_CATEGORIES.map(cat => <MenuItem key={cat.value} value={cat.value}>{cat.label}</MenuItem>)}
                </Select>
              </FormControl>
              <Box sx={{ display: 'flex', gap: 2, justifyContent: 'flex-end' }}>
                <Button variant="outlined" onClick={() => { setEditing(false); setEditData({ title: task.title, description: task.description || '', priority: task.priority, status: task.status, assigned_to: task.assigned_to, due_date: task.due_date || '', category: task.category }); }}>Cancel</Button>
                <Button variant="contained" onClick={handleSave}>Save Changes</Button>
              </Box>
            </Box>
          </CardContent>
        </Card>
      )}

      {/* Note Context Menu */}
      <Menu anchorEl={noteMenuAnchor} open={!!noteMenuAnchor} onClose={() => setNoteMenuAnchor(null)}>
        {activeNoteMenu && (
          <>
            {/* Status submenu */}
            <MuiMenuItem disabled>
              <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 600 }}>Set Status</Typography>
            </MuiMenuItem>
            {(['draft', 'active', 'reviewed', 'action-required'] as const).map(status => (
              <MuiMenuItem
                key={status}
                onClick={() => { handleSetNoteStatus(activeNoteMenu.id, status); setNoteMenuAnchor(null); }}
                sx={{ pl: 4 }}
              >
                <Chip
                  size="small"
                  label={status}
                  color={status === 'reviewed' ? 'success' : status === 'action-required' ? 'error' : status === 'active' ? 'primary' : 'default'}
                  sx={{ mr: 1, height: 18, fontSize: '0.65rem' }}
                />
                {status.replace('-', ' ')}
              </MuiMenuItem>
            ))}
            
            <Divider />
            
            {/* Forward note */}
            <MuiMenuItem onClick={() => { setForwardingNote(activeNoteMenu); setForwardDialogOpen(true); setNoteMenuAnchor(null); loadAllTasks(); }}>
              <Send sx={{ fontSize: 16, mr: 1 }} />
              Forward to Task...
            </MuiMenuItem>
            
            {permissions?.can_pin_notes && (
              <MuiMenuItem onClick={() => { handlePinNote(activeNoteMenu.id, !activeNoteMenu.is_pinned); }}>
                <PushPin sx={{ fontSize: 16, mr: 1, transform: activeNoteMenu.is_pinned ? 'rotate(-45deg)' : 'none' }} />
                {activeNoteMenu.is_pinned ? 'Unpin' : 'Pin'}
              </MuiMenuItem>
            )}
            {(activeNoteMenu.author === currentUser || permissions?.can_delete_any_note) && (
              <MuiMenuItem onClick={() => { handleDeleteNote(activeNoteMenu.id); setNoteMenuAnchor(null); }} sx={{ color: 'error.main' }}>
                <Delete sx={{ fontSize: 16, mr: 1 }} />
                Delete
              </MuiMenuItem>
            )}
          </>
        )}
      </Menu>
      
      {/* Forward Note Dialog */}
      <Dialog open={forwardDialogOpen} onClose={() => { setForwardDialogOpen(false); setForwardingNote(null); setForwardTargetTaskId(''); }}>
        <DialogTitle>Forward Note to Another Task</DialogTitle>
        <DialogContent sx={{ minWidth: 400, pt: 2 }}>
          <Typography variant="body2" sx={{ mb: 2, color: 'text.secondary' }}>
            Forwarding note from Task #{forwardingNote?.task_id}
          </Typography>
          <FormControl fullWidth size="small">
            <InputLabel>Target Task</InputLabel>
            <Select
              value={forwardTargetTaskId}
              label="Target Task"
              onChange={(e) => setForwardTargetTaskId(e.target.value)}
            >
              <MenuItem value="" disabled>Select a task...</MenuItem>
              {allTasks.filter(t => t.id !== forwardingNote?.task_id).map(task => (
                <MenuItem key={task.id} value={task.id}>
                  #{task.id} - {task.title}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => { setForwardDialogOpen(false); setForwardingNote(null); setForwardTargetTaskId(''); }}>Cancel</Button>
          <Button variant="contained" onClick={handleForwardNote} disabled={!forwardTargetTaskId}>
            Forward Note
          </Button>
        </DialogActions>
      </Dialog>

      {/* Link Task Dialog */}
      <Dialog open={linkDialogOpen} onClose={() => { setLinkDialogOpen(false); setLinkTargetTaskId(''); }}>
        <DialogTitle>Link to Another Task</DialogTitle>
        <DialogContent sx={{ minWidth: 400, pt: 2 }}>
          <Typography variant="body2" sx={{ mb: 2, color: 'text.secondary' }}>
            Linking from Task #{task.id} - {task.title}
          </Typography>
          <FormControl fullWidth size="small" sx={{ mb: 2 }}>
            <InputLabel>Link Type</InputLabel>
            <Select
              value={linkDialogType}
              label="Link Type"
              onChange={(e) => setLinkDialogType(e.target.value as TaskLink['link_type'])}
            >
              <MenuItem value="related">🔗 Related</MenuItem>
              <MenuItem value="blocks">🚫 Blocks</MenuItem>
              <MenuItem value="blocked-by">⏸️ Blocked by</MenuItem>
              <MenuItem value="duplicate-of">📋 Duplicate of</MenuItem>
            </Select>
          </FormControl>
          <FormControl fullWidth size="small">
            <InputLabel>Target Task</InputLabel>
            <Select
              value={linkTargetTaskId}
              label="Target Task"
              onChange={(e) => setLinkTargetTaskId(e.target.value)}
            >
              <MenuItem value="" disabled>Select a task...</MenuItem>
              {allTasks.filter(t => t.id !== task.id).map(t => (
                <MenuItem key={t.id} value={t.id}>
                  #{t.id} - {t.title}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => { setLinkDialogOpen(false); setLinkTargetTaskId(''); }}>Cancel</Button>
          <Button variant="contained" onClick={handleLinkTask} disabled={!linkTargetTaskId}>
            Link Tasks
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
}

import apiClient from './client';

// Shared constants
export const TASK_CATEGORIES = [
  { value: 'general', label: 'General' },
  { value: 'development', label: 'Development' },
  { value: 'design', label: 'Design' },
  { value: 'testing', label: 'Testing' },
  { value: 'documentation', label: 'Documentation' },
  { value: 'maintenance', label: 'Maintenance' },
] as const;

export const NOTE_CATEGORIES = [
  { value: 'tuning', label: 'Tuning', icon: '🔧', color: 'info' },
  { value: 'fix', label: 'Fix', icon: '🐛', color: 'error' },
  { value: 'implementation', label: 'Implementation', icon: '🚀', color: 'success' },
  { value: 'question', label: 'Question', icon: '❓', color: 'warning' },
  { value: 'general', label: 'General', icon: '📝', color: 'default' },
] as const;

export const TASK_STATUSES = ['pending', 'in-progress', 'completed', 'cancelled'] as const;
export const TASK_PRIORITIES = ['low', 'medium', 'high'] as const;

export const getTaskStatusColor = (status: string): 'success' | 'info' | 'error' | 'default' => {
  switch (status) {
    case 'completed': return 'success';
    case 'in-progress': return 'info';
    case 'cancelled': return 'error';
    default: return 'default';
  }
};

export const getTaskPriorityColor = (priority: string): 'error' | 'warning' | 'default' => {
  switch (priority) {
    case 'high': return 'error';
    case 'medium': return 'warning';
    default: return 'default';
  }
};

export interface Task {
  id: number;
  title: string;
  description: string;
  priority: 'low' | 'medium' | 'high';
  status: 'pending' | 'in-progress' | 'completed' | 'cancelled';
  assigned_to: string;
  due_date: string | null;
  category: string;
  created_by: string;
  created_at: string;
  updated_at: string;
}

export interface TaskNote {
  id: number;
  task_id: number;
  author: string;
  content: string;
  category: 'tuning' | 'fix' | 'implementation' | 'question' | 'general';
  is_pinned: number;
  status: 'draft' | 'active' | 'reviewed' | 'action-required';
  parent_id: number | null;
  created_at: string;
  updated_at: string;
}

export interface TaskScreenshot {
  id: number;
  task_id: number;
  author: string;
  file_path: string;
  caption: string;
  created_at: string;
}

export interface TaskActivity {
  id: number;
  task_id: number;
  action: string;
  actor: string;
  details: string;
  created_at: string;
}

export interface TaskStats {
  total: number;
  completed: number;
  in_progress: number;
  pending: number;
  cancelled: number;
}

export interface TaskFilters {
  status?: string;
  priority?: string;
  category?: string;
  assigned_to?: string;
  search?: string;
  overdue?: boolean;
  page?: number;
  per_page?: number;
  sort_field?: string;
  sort_direction?: 'asc' | 'desc';
}

export async function fetchTasks(filters?: TaskFilters): Promise<{ tasks: Task[]; total: number; page: number; per_page: number; total_pages: number }> {
  const params = new URLSearchParams();
  if (filters?.status) params.set('status', filters.status);
  if (filters?.priority) params.set('priority', filters.priority);
  if (filters?.category) params.set('category', filters.category);
  if (filters?.assigned_to) params.set('assigned_to', filters.assigned_to);
  if (filters?.search) params.set('search', filters.search);
  if (filters?.overdue) params.set('overdue', '1');
  if (filters?.page) params.set('page', String(filters.page));
  if (filters?.per_page) params.set('per_page', String(filters.per_page));
  if (filters?.sort_field) params.set('sort_field', filters.sort_field);
  if (filters?.sort_direction) params.set('sort_direction', filters.sort_direction);
  
  const queryString = params.toString();
  const url = `/api/tasks.php?action=list${queryString ? '&' + queryString : ''}`;
  const { data } = await apiClient.get(url);
  return data;
}

export async function fetchTask(id: number): Promise<Task> {
  const { data } = await apiClient.get(`/api/tasks.php?action=get&id=${id}`);
  return data;
}

export async function createTask(input: Partial<Task>): Promise<any> {
  const { data } = await apiClient.post('/api/tasks.php?action=create', input);
  return data;
}

export async function updateTask(input: Partial<Task> & { id: number }): Promise<any> {
  const { data } = await apiClient.post('/api/tasks.php?action=update', input);
  return data;
}

export async function deleteTask(id: number): Promise<any> {
  const { data } = await apiClient.post('/api/tasks.php?action=delete', { id });
  return data;
}

export async function fetchTaskStats(): Promise<TaskStats> {
  const { data } = await apiClient.get('/api/tasks.php?action=stats');
  return data;
}

export async function fetchTaskNotes(taskId: number): Promise<TaskNote[]> {
  const { data } = await apiClient.get(`/api/tasks.php?action=notes&task_id=${taskId}`);
  return data;
}

export async function addNote(taskId: number, content: string, parentId?: number, category?: string): Promise<any> {
  const { data } = await apiClient.post('/api/tasks.php?action=add_note', { task_id: taskId, content, parent_id: parentId, category });
  return data;
}

export async function editNote(id: number, content: string, category?: string): Promise<any> {
  const { data } = await apiClient.post('/api/tasks.php?action=edit_note', { id, content, category });
  return data;
}

export async function pinNote(id: number, isPinned: boolean): Promise<any> {
  const { data } = await apiClient.post('/api/tasks.php?action=pin_note', { id, is_pinned: isPinned ? 1 : 0 });
  return data;
}

export async function deleteNote(id: number): Promise<any> {
  const { data } = await apiClient.post('/api/tasks.php?action=delete_note', { id });
  return data;
}

export async function fetchTaskActivity(taskId: number): Promise<TaskActivity[]> {
  const { data } = await apiClient.get(`/api/tasks.php?action=activity&task_id=${taskId}`);
  return data;
}

export async function fetchTaskNotesCount(): Promise<Record<number, number>> {
  const { data } = await apiClient.get('/api/tasks.php?action=notes_counts');
  return data;
}

export async function uploadScreenshot(taskId: number, file: File, caption: string = ''): Promise<any> {
  const formData = new FormData();
  formData.append('screenshot', file);
  formData.append('task_id', taskId.toString());
  formData.append('caption', caption);
  
  const { data } = await apiClient.post('/api/tasks.php?action=upload_screenshot', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return data;
}

export async function fetchScreenshots(taskId: number): Promise<TaskScreenshot[]> {
  const { data } = await apiClient.get(`/api/tasks.php?action=get_screenshots&task_id=${taskId}`);
  return data;
}

export async function deleteScreenshot(id: number): Promise<any> {
  const { data } = await apiClient.post('/api/tasks.php?action=delete_screenshot', { id });
  return data;
}

export async function forwardNote(noteId: number, targetTaskId: number): Promise<any> {
  const { data } = await apiClient.post('/api/tasks.php?action=forward_note', {
    note_id: noteId,
    target_task_id: targetTaskId,
  });
  return data;
}

export async function setNoteStatus(noteId: number, status: TaskNote['status']): Promise<any> {
  const { data } = await apiClient.post('/api/tasks.php?action=set_note_status', {
    note_id: noteId,
    status,
  });
  return data;
}

export async function bulkUpdate(ids: number[], fields: Partial<Task>): Promise<any> {
  const { data } = await apiClient.post('/api/tasks.php?action=bulk_update', { ids, ...fields });
  return data;
}

export interface TaskLink {
  id: number;
  task_id: number;
  linked_task_id: number;
  link_type: 'blocks' | 'blocked-by' | 'related' | 'duplicate-of';
  linked_title: string;
  linked_status: Task['status'];
  linked_priority: Task['priority'];
  created_at: string;
}

export async function linkTask(taskId: number, linkedTaskId: number, linkType: TaskLink['link_type'] = 'related'): Promise<any> {
  const { data } = await apiClient.post('/api/tasks.php?action=link_task', {
    task_id: taskId,
    linked_task_id: linkedTaskId,
    link_type: linkType,
  });
  return data;
}

export async function getTaskLinks(taskId: number): Promise<TaskLink[]> {
  const { data } = await apiClient.get(`/api/tasks.php?action=get_task_links&task_id=${taskId}`);
  return data;
}

export async function unlinkTask(linkId: number): Promise<any> {
  const { data } = await apiClient.post('/api/tasks.php?action=unlink_task', { id: linkId });
  return data;
}

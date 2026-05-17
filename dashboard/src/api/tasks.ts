import apiClient from './client';

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

export async function fetchTasks(): Promise<Task[]> {
  const { data } = await apiClient.get('/api/tasks.php?action=list');
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

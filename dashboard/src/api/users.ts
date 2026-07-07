import apiClient from './client';

export type UserRole = 'admin' | 'editor' | 'moderator' | 'viewer' | 'marketing';

export interface User {
  id: number;
  username: string;
  email: string;
  full_name: string;
  role: UserRole;
  is_active: boolean;
  last_login: string | null;
  created_at: string;
}

export interface CreateUserInput {
  username: string;
  email: string;
  full_name: string;
  role: UserRole;
  password: string;
}

export interface UpdateUserInput {
  id: number;
  username: string;
  email: string;
  full_name: string;
  role: UserRole;
}

export async function fetchUsers(): Promise<User[]> {
  const { data } = await apiClient.get('/api/users.php?action=list');
  return data;
}

export async function getUser(id: number): Promise<User> {
  const { data } = await apiClient.get(`/api/users.php?action=get&id=${id}`);
  return data;
}

export async function createUser(input: CreateUserInput): Promise<any> {
  const { data } = await apiClient.post('/api/users.php?action=create', input);
  return data;
}

export async function updateUser(input: UpdateUserInput): Promise<any> {
  const { data } = await apiClient.post('/api/users.php?action=update', input);
  return data;
}

export async function deleteUser(id: number): Promise<any> {
  const { data } = await apiClient.post('/api/users.php?action=delete', { id });
  return data;
}

export async function resetUserPassword(id: number): Promise<any> {
  const { data } = await apiClient.post('/api/users.php?action=reset_password', { id });
  return data;
}

export async function toggleUserStatus(id: number): Promise<any> {
  const { data } = await apiClient.post('/api/users.php?action=toggle_status', { id });
  return data;
}

export async function forgotPassword(identifier: string): Promise<any> {
  const { data } = await apiClient.post('/api/auth.php?action=forgot_password', { username: identifier, email: identifier });
  return data;
}

export async function verifyResetToken(token: string): Promise<any> {
  const { data } = await apiClient.get(`/api/auth.php?action=verify_reset_token&token=${token}`);
  return data;
}

export async function resetPasswordWithToken(token: string, newPassword: string): Promise<any> {
  const { data } = await apiClient.post('/api/auth.php?action=reset_password_with_token', { token, new_password: newPassword });
  return data;
}

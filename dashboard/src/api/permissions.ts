import apiClient from './client';

export interface RolePermissions {
  id: number;
  role: string;
  can_access_users_page: boolean;
  can_access_settings_page: boolean;
  can_access_emergency_actions: boolean;
  can_access_cache_control: boolean;
  can_access_process_explorer: boolean;
  can_access_permissions_page: boolean;
  can_create_tasks: boolean;
  can_update_own_tasks: boolean;
  can_update_any_task: boolean;
  can_delete_tasks: boolean;
  can_edit_own_notes: boolean;
  can_edit_any_note: boolean;
  can_delete_own_notes: boolean;
  can_delete_any_note: boolean;
  can_pin_notes: boolean;
  can_manage_users: boolean;
  created_at: string;
  updated_at: string;
}

export async function fetchAllRolePermissions(): Promise<Record<string, RolePermissions>> {
  const { data } = await apiClient.get('/api/permissions.php?action=get_all');
  return data;
}

export async function fetchRolePermissions(role: string): Promise<RolePermissions> {
  const { data } = await apiClient.get(`/api/permissions.php?action=get_role&role=${role}`);
  return data;
}

export async function updateRolePermission(role: string, permission: string, value: boolean): Promise<void> {
  await apiClient.post('/api/permissions.php?action=update', { role, permission, value });
}

export async function fetchAvailableRoles(): Promise<string[]> {
  const { data } = await apiClient.get('/api/permissions.php?action=roles');
  return data;
}

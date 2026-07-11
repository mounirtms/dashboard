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
  can_add_task_notes: boolean;
  can_manage_users: boolean;
  // Push notification permissions
  can_access_push_notifications: boolean;
  can_send_notifications: boolean;
  can_view_subscribers: boolean;
  can_manage_segments: boolean;
  // Magento / Commerce permissions
  can_access_magento_products: boolean;
  can_edit_products: boolean;
  can_bulk_products: boolean;
  can_access_magento_customers: boolean;
  can_edit_customers: boolean;
  can_access_magento_orders: boolean;
  can_manage_orders: boolean;
  can_access_magento_cms: boolean;
  can_edit_cms: boolean;
  can_access_magento_settings: boolean;
  // System & DevOps page-access permissions
  can_access_cloudflare: boolean;
  can_access_ssh_monitor: boolean;
  can_access_command_audit: boolean;
  can_access_user_activity: boolean;
  can_access_system_audit: boolean;
  can_access_plans: boolean;
  can_access_cicd: boolean;
  can_access_script_runner: boolean;
  // ETL
  can_access_etl: boolean;
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

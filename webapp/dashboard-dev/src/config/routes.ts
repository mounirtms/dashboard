/**
 * Route configuration — single source of truth for access control.
 * Used by ProtectedRoute (App.tsx) and Sidebar (nav filtering).
 */

import type { PermissionKey } from '../hooks/usePermissions';

/** Paths that require the 'admin' role explicitly (bypass permission matrix). */
export const ADMIN_PATHS = new Set([
  '/tools/users',
  '/tools/permissions',
  '/tools/actions',
  '/settings',
]);

/**
 * Paths that require a specific permission key from the permission matrix.
 * Non-admins are checked against their role's permissions.
 * Admins always pass (hasPermission returns true for admin).
 */
export const PERMISSION_PATHS: Record<string, PermissionKey> = {
  '/cache-control':          'can_access_cache_control',
  '/process-explorer':       'can_access_process_explorer',
  '/monitoring/ssh':         'can_access_ssh_monitor',
  '/monitoring/commands':    'can_access_command_audit',
  '/monitoring/users':       'can_access_user_activity',
  '/tools/system-audit':     'can_access_system_audit',
  '/tools/backups':          'can_access_cache_control', // backups = admin-adjacent
  '/plans':                  'can_access_plans',
  '/cicd':                   'can_access_cicd',
  '/cicd/gitlab':            'can_access_cicd',
  '/scripts':                'can_access_script_runner',
  '/tools/script-runner':    'can_access_script_runner',
  '/tools/task-queue':       'can_access_task_queue',
  '/commerce/settings':      'can_access_magento_settings',
  '/cloudflare':             'can_access_cloudflare',
  '/traffic':                'can_access_cloudflare',
  '/performance':            'can_access_cloudflare',
  '/geography':              'can_access_cloudflare',
  '/security':               'can_access_cloudflare',
  '/notifications/push':     'can_access_push_notifications',
  '/etl/status':             'can_access_etl',
  '/etl/logs':               'can_access_etl',
  '/commerce/products':      'can_access_magento_products',
  '/commerce/customers':     'can_access_magento_customers',
  '/commerce/orders':        'can_access_magento_orders',
  '/commerce/cms':           'can_access_magento_cms',
};

/**
 * Route configuration — single source of truth for admin-only paths.
 * Used by both App.tsx (ProtectedRoute) and Sidebar.tsx (nav filtering).
 */

/** Paths that require the 'admin' role to access. */
export const ADMIN_PATHS = new Set([
  '/tools/users',
  '/settings',
  '/tools/actions',
  '/cache-control',
  '/process-explorer',
  '/tools/permissions',
  // Critical monitoring pages - admin only
  '/monitoring/users',
  '/tools/system-audit',
  '/plans',
  // Infrastructure control
  '/scripts',
  '/cicd',
]);

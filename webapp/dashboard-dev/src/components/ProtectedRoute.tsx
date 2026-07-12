import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth.tsx';
import { usePermissions } from '../hooks/usePermissions.ts';
import LoadingState from './common/LoadingState.tsx';
import { ADMIN_PATHS, PERMISSION_PATHS } from '../config/routes.ts';

interface ProtectedRouteProps {
  /** If set, only this exact role is permitted (legacy admin-only routes). */
  requiredRole?: 'admin' | 'editor' | 'moderator' | 'viewer';
}

export default function ProtectedRoute({ requiredRole }: ProtectedRouteProps = {}) {
  const { isAuthenticated, loading: authLoading } = useAuth();
  const { hasPermission, isAdmin, loading: permLoading, role } = usePermissions();
  const location = useLocation();

  // Wait for both auth and permissions to resolve
  if (authLoading || permLoading) {
    return <LoadingState message="Verifying session..." />;
  }

  // Not authenticated — send to login, preserve intended destination
  if (!isAuthenticated) {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  const path = location.pathname;

  // Legacy requiredRole check (used by admin-only <ProtectedRoute requiredRole="admin">)
  if (requiredRole) {
    if (role !== requiredRole) {
      return <Navigate to="/" replace />;
    }
    return <Outlet />;
  }

  // Hard admin-only paths (always block non-admins regardless of permission matrix)
  if (ADMIN_PATHS.has(path) && !isAdmin) {
    return <Navigate to="/" replace />;
  }

  // Fine-grained permission paths — check against permission matrix
  const permKey = PERMISSION_PATHS[path];
  if (permKey && !hasPermission(permKey)) {
    return <Navigate to="/" replace />;
  }

  return <Outlet />;
}

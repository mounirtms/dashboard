import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth.tsx';
import LoadingState from './common/LoadingState.tsx';

interface ProtectedRouteProps {
  requiredRole?: 'admin' | 'editor' | 'moderator' | 'viewer';
}

export default function ProtectedRoute({ requiredRole }: ProtectedRouteProps = {}) {
  const { isAuthenticated, user, loading } = useAuth();
  const location = useLocation();

  // Show loading briefly while checking session
  if (loading) {
    return <LoadingState message="Verifying session..." />;
  }

  // Redirect to login if not authenticated
  if (!isAuthenticated) {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  // Redirect to home if wrong role
  const userRole = user?.role;
  if (requiredRole && userRole !== requiredRole) {
    return <Navigate to="/" replace />;
  }

  return <Outlet />;
}

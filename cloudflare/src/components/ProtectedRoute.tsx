import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth.tsx';
import LoadingState from './common/LoadingState';

export default function ProtectedRoute() {
  const { isAuthenticated, loading } = useAuth();

  if (loading) {
    return <LoadingState message="Verifying session..." />;
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  return <Outlet />;
}

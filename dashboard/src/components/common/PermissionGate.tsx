import { ReactNode } from 'react';
import { usePermissions, type PermissionKey } from '../../hooks/usePermissions';

interface PermissionGateProps {
  permission: PermissionKey;
  fallback?: ReactNode;
  children: ReactNode;
}

export default function PermissionGate({ permission, fallback, children }: PermissionGateProps) {
  const { hasPermission } = usePermissions();

  if (!hasPermission(permission)) {
    return <>{fallback}</>;
  }

  return <>{children}</>;
}

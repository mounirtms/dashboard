import { useState, useEffect, useCallback } from 'react';
import { useAuth } from './useAuth';
import { fetchRolePermissions, type RolePermissions } from '../api/permissions';

export type PermissionKey = keyof Omit<RolePermissions, 'id' | 'role' | 'created_at' | 'updated_at'>;

/** Module-level cache — keyed by role. Deduplicates concurrent fetches. */
const cache = new Map<string, { data: RolePermissions; timestamp: number }>();
const CACHE_TTL_MS = 60_000; // 60 seconds

/** Shared in-flight promise map — prevents duplicate parallel requests */
const inFlight = new Map<string, Promise<RolePermissions>>();

/** Call this on logout to purge stale cached permissions */
export function clearPermissionsCache(): void {
  cache.clear();
  inFlight.clear();
}

function fetchWithCache(role: string): Promise<RolePermissions> {
  const now = Date.now();
  const cached = cache.get(role);
  if (cached && now - cached.timestamp < CACHE_TTL_MS) {
    return Promise.resolve(cached.data);
  }

  if (inFlight.has(role)) {
    return inFlight.get(role)!;
  }

  const promise = fetchRolePermissions(role)
    .then((data) => {
      cache.set(role, { data, timestamp: Date.now() });
      inFlight.delete(role);
      return data;
    })
    .catch((err) => {
      inFlight.delete(role);
      throw err;
    });

  inFlight.set(role, promise);
  return promise;
}

export function usePermissions() {
  const { user, isAuthenticated } = useAuth();
  const [permissions, setPermissions] = useState<RolePermissions | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const role = user?.role || '';

  useEffect(() => {
    // Clear permissions when logged out
    if (!isAuthenticated || !role) {
      setPermissions(null);
      setError(null);
      return;
    }

    let cancelled = false;
    setLoading(true);
    setError(null);

    fetchWithCache(role)
      .then((data) => {
        if (!cancelled) {
          setPermissions(data);
          setError(null);
        }
      })
      .catch((err) => {
        if (!cancelled) {
          setPermissions(null);
          setError(err instanceof Error ? err.message : 'Failed to load permissions');
        }
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => { cancelled = true; };
  }, [isAuthenticated, role]);

  const hasPermission = useCallback(
    (permission: PermissionKey): boolean => {
      // Admins always pass all permission checks
      if (role === 'admin') return true;
      if (!permissions) return false;
      const value: unknown = permissions[permission as keyof RolePermissions];
      return value === true || value === 1;
    },
    [permissions, role],
  );

  /** Force-refresh permissions from server (bypasses cache) */
  const refreshPermissions = useCallback(async () => {
    if (!role) return;
    cache.delete(role);
    inFlight.delete(role);
    try {
      const data = await fetchWithCache(role);
      setPermissions(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to refresh permissions');
    }
  }, [role]);

  return {
    role,
    permissions,
    hasPermission,
    refreshPermissions,
    isAdmin:     role === 'admin',
    isEditor:    role === 'editor',
    isModerator: role === 'moderator',
    isViewer:    role === 'viewer',
    isMarketing: role === 'marketing',
    loading,
    error,
  };
}

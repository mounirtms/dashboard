/**
 * useSystemOverview — stale-while-revalidate wrapper for system metrics.
 * Uses usePolling so background refreshes don't cause layout flicker.
 */

import { useCallback } from 'react';
import { fetchSystemOverview, SystemOverview } from '../api/system';
import { usePolling } from './usePolling';

export function useSystemOverview(refreshInterval = 30_000) {
  const fetcher = useCallback(
    (_signal?: AbortSignal) => fetchSystemOverview(),
    [],
  );
  return usePolling<SystemOverview>(fetcher, refreshInterval);
}

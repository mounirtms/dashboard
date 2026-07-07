/**
 * useCloudflareData — stale-while-revalidate wrapper for Cloudflare analytics.
 * Uses usePolling so background refreshes don't cause layout flicker.
 */

import { useCallback } from 'react';
import { fetchCloudflareData } from '../api/cloudflare';
import { usePolling } from './usePolling';

export function useCloudflareData(refreshInterval = 30_000) {
  const fetcher = useCallback(
    (_signal?: AbortSignal) => fetchCloudflareData(),
    [],
  );
  return usePolling<any>(fetcher, refreshInterval);
}

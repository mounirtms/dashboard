/**
 * usePolling — generic stale-while-revalidate data-fetching hook.
 *
 * Features:
 *  - First fetch shows loading spinner; subsequent background polls keep
 *    stale data visible (no layout flicker on auto-refresh).
 *  - AbortController cancels in-flight requests on unmount / key change.
 *  - Configurable refresh interval (pass 0 to disable polling).
 *  - `refreshing` flag is true only during background polls (not first load).
 *  - `refetch` triggers an immediate manual refresh.
 */

import { useState, useEffect, useCallback, useRef } from 'react';

export interface UsePollingResult<T> {
  data: T | null;
  loading: boolean;     // true only on the initial (no-data) fetch
  refreshing: boolean;  // true during background refresh polls
  error: string | null;
  refetch: () => void;
  lastFetched: number | null;  // timestamp (ms) of most recent successful fetch
}

export function usePolling<T>(
  fetcher: (signal?: AbortSignal) => Promise<T>,
  refreshInterval = 30_000,
): UsePollingResult<T> {
  const [data, setData]             = useState<T | null>(null);
  const [loading, setLoading]       = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError]           = useState<string | null>(null);
  const [lastFetched, setLastFetched] = useState<number | null>(null);

  // Keep the latest fetcher stable in a ref so the interval closure is always current
  const fetcherRef = useRef(fetcher);
  fetcherRef.current = fetcher;

  // Bump to trigger an imperative refetch
  const [tick, setTick] = useState(0);
  const refetch = useCallback(() => setTick(t => t + 1), []);

  // Track whether we have data yet (using ref to avoid closure staleness)
  const hasDataRef = useRef(false);
  hasDataRef.current = data !== null;

  useEffect(() => {
    const controller = new AbortController();

    if (!hasDataRef.current) {
      setLoading(true);
    } else {
      setRefreshing(true);
    }

    fetcherRef.current(controller.signal)
      .then(result => {
        if (!controller.signal.aborted) {
          setData(result);
          setError(null);
          setLastFetched(Date.now());
        }
      })
      .catch(err => {
        if (!controller.signal.aborted) {
          const msg =
            err?.response?.data?.message ||
            err?.response?.data?.error ||
            err?.message ||
            'An unknown error occurred';
          setError(msg);
        }
      })
      .finally(() => {
        if (!controller.signal.aborted) {
          setLoading(false);
          setRefreshing(false);
        }
      });

    return () => controller.abort();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tick]);

  // Auto-polling interval
  useEffect(() => {
    if (refreshInterval <= 0) return;
    const interval = setInterval(() => setTick(t => t + 1), refreshInterval);
    return () => clearInterval(interval);
  }, [refreshInterval]);

  return { data, loading, refreshing, error, refetch, lastFetched };
}

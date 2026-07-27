import { useState, useEffect, useCallback, useRef } from 'react';
import { fetchSystemOverview, SystemOverview } from '../api/system';

/** @deprecated Use SystemOverviewContext instead — this hook polls independently and can cause 429 storms. */
export function useSystemOverview(refreshInterval = 60000) {
  const [data, setData] = useState<SystemOverview | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const inFlightRef = useRef(false);

  const fetchData = useCallback(async () => {
    if (inFlightRef.current) return;
    inFlightRef.current = true;
    try {
      setLoading(true);
      const result = await fetchSystemOverview();
      setData(result);
      setError(null);
    } catch (e: any) {
      if (e?.response?.status === 429) {
        setError('Rate limited — retrying next cycle');
      } else {
        setError(e.message || 'Failed to fetch system overview');
      }
    } finally {
      setLoading(false);
      inFlightRef.current = false;
    }
  }, []);

  useEffect(() => {
    fetchData();
    const interval = setInterval(fetchData, refreshInterval);
    return () => clearInterval(interval);
  }, [fetchData, refreshInterval]);

  return { data, loading, error, refetch: fetchData };
}

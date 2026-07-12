import { useState, useEffect, useCallback } from 'react';
import { fetchSystemOverview, SystemOverview } from '../api/system';

export function useSystemOverview(refreshInterval = 30000) {
  const [data, setData] = useState<SystemOverview | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchData = useCallback(async () => {
    try {
      setLoading(true);
      const result = await fetchSystemOverview();
      setData(result);
      setError(null);
    } catch (e: any) {
      setError(e.message || 'Failed to fetch system overview');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchData();
    const interval = setInterval(fetchData, refreshInterval);
    return () => clearInterval(interval);
  }, [fetchData, refreshInterval]);

  return { data, loading, error, refetch: fetchData };
}

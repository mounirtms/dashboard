import { useState, useEffect, useCallback } from 'react';
import { fetchCloudflareData, CloudflareData } from '../api/cloudflare';

export function useCloudflareData(refreshInterval = 60000) {
  const [data, setData] = useState<CloudflareData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchData = useCallback(async () => {
    try {
      setLoading(true);
      const result = await fetchCloudflareData();
      setData(result);
      setError(null);
    } catch (e: any) {
      setError(e.message || 'Failed to fetch Cloudflare data');
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

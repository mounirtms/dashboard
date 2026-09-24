import { useState, useEffect, useCallback, useRef } from 'react';
import { fetchCloudflareData, CloudflareData } from '../api/cloudflare';

export function useCloudflareData(refreshInterval = 60000) {
  const [data, setData] = useState<CloudflareData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const inFlightRef = useRef(false);

  const fetchData = useCallback(async () => {
    // Prevent overlapping polls (slow upstream + short interval) from stacking requests.
    if (inFlightRef.current) return;
    inFlightRef.current = true;
    try {
      setLoading(true);
      const result = await fetchCloudflareData();
      setData(result);
      setError(null);
    } catch (e: any) {
      // Keep the last successful snapshot visible; 429 is transient.
      if (e?.response?.status !== 429) {
        setError(e.message || 'Failed to fetch Cloudflare data');
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

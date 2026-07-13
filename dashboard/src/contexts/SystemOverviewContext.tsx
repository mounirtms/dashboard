import { createContext, useContext, useState, useEffect, useCallback, useRef, ReactNode } from 'react';
import { fetchSystemOverview, SystemOverview } from '../api/system';

interface SystemOverviewContextValue {
  data: SystemOverview | null;
  loading: boolean;
  error: string | null;
  refetch: () => void;
}

const SystemOverviewContext = createContext<SystemOverviewContextValue>({
  data: null,
  loading: true,
  error: null,
  refetch: () => {},
});

// Single polling interval — shared across ALL consumers (Header, Footer, pages).
// This prevents the 429 cascade caused by each component instantiating its own
// useSystemOverview() with independent setInterval + 3x retry on 429.
const POLL_INTERVAL_MS = 60000; // 60s — one request per minute from the whole app

interface Props {
  children: ReactNode;
}

export function SystemOverviewProvider({ children }: Props) {
  const [data, setData] = useState<SystemOverview | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  // Track in-flight request to avoid parallel fetches on rapid refetch calls
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
      // On 429, keep last good data — don't wipe the UI
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
    const interval = setInterval(fetchData, POLL_INTERVAL_MS);
    return () => clearInterval(interval);
  }, [fetchData]);

  return (
    <SystemOverviewContext.Provider value={{ data, loading, error, refetch: fetchData }}>
      {children}
    </SystemOverviewContext.Provider>
  );
}

// Drop-in replacement for useSystemOverview() — reads from shared context instead of polling independently
export function useSystemOverviewContext(): SystemOverviewContextValue {
  return useContext(SystemOverviewContext);
}

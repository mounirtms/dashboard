import { useState, useEffect, useCallback } from 'react';
import apiClient from '../api/client.ts';

interface User {
  username: string;
  full_name: string;
  role: string;
}

export function useAuth() {
  const [authenticated, setAuthenticated] = useState<boolean | null>(null);
  const [user, setUser] = useState<User | null>(null);

  const checkAuth = useCallback(async () => {
    try {
      const { data } = await apiClient.get('/api/auth.php?action=check');
      setAuthenticated(data.authenticated);
      setUser(data.user || null);
      if (!data.authenticated) {
        window.location.href = '/login.html';
      }
    } catch {
      window.location.href = '/login.html';
    }
  }, []);

  useEffect(() => {
    checkAuth();
  }, [checkAuth]);

  return { authenticated, user, checkAuth };
}

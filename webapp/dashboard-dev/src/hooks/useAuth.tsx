import { useState, useEffect, useCallback, createContext, useContext } from 'react';
import apiClient from '../api/client';
import { clearPermissionsCache } from './usePermissions';

export type UserRole = 'admin' | 'editor' | 'moderator' | 'viewer' | 'marketing';

export interface User {
  id: string;
  username: string;
  email?: string;
  full_name?: string;
  role?: UserRole;
}

interface AuthContextType {
  isAuthenticated: boolean;
  user: User | null;
  loading: boolean;
  login: (credentials: any) => Promise<void>;
  logout: () => Promise<void>;
  forgotPassword: (identifier: string) => Promise<any>;
  resetPasswordWithToken: (token: string, newPassword: string) => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

const REMEMBER_TOKEN_KEY = 'dashboard_remember_token';

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [isAuthenticated, setIsAuthenticated] = useState<boolean>(false);
  const [loading, setLoading] = useState<boolean>(true);

  const checkAuth = useCallback(async () => {
    try {
      const { data } = await apiClient.get('/api/auth.php?action=status');
      if (data.authenticated || data.logged_in) {
        setIsAuthenticated(true);
        setUser(data.user || null);
        
        // Store remember token if provided (from auto-login restoration)
        if (data.remember_token) {
          localStorage.setItem(REMEMBER_TOKEN_KEY, data.remember_token);
        }
      } else {
        setIsAuthenticated(false);
        setUser(null);
      }
    } catch (error) {
      setIsAuthenticated(false);
      setUser(null);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    checkAuth();
  }, [checkAuth]);

  const login = async (credentials: any) => {
    let data: any;
    try {
      const resp = await apiClient.post('/api/auth.php?action=login', credentials);
      data = resp.data;
    } catch (err: any) {
      // Axios throws on 4xx/5xx — extract the real error from the response body
      const respData = err.response?.data;
      const msg = respData?.error || respData?.message || err.message || 'Login failed';
      throw new Error(msg);
    }

    if (data.success) {
      setIsAuthenticated(true);
      setUser(data.user);
      
      // Store remember token if provided
      if (data.remember_token) {
        localStorage.setItem(REMEMBER_TOKEN_KEY, data.remember_token);
        // Set cookie for PHP auto-login
        document.cookie = `remember_token=${data.remember_token}; path=/; max-age=2592000; samesite=lax`;
      }
    } else {
      throw new Error(data.message || data.error || 'Login failed');
    }
  };

  const logout = async () => {
    try {
      await apiClient.post('/api/auth.php?action=logout');
    } catch (e) {}

    // Purge permission cache so next login gets fresh permissions
    clearPermissionsCache();

    // Clear remember token
    localStorage.removeItem(REMEMBER_TOKEN_KEY);
    document.cookie = 'remember_token=; path=/; max-age=0; samesite=lax';

    setIsAuthenticated(false);
    setUser(null);
    window.location.hash = '/login';
  };

  const forgotPassword = async (identifier: string) => {
    const { data } = await apiClient.post('/api/auth.php?action=forgot_password', { username: identifier, email: identifier });
    if (data.success) {
      return data;
    }
    throw new Error(data.error || 'Failed to process request');
  };

  const resetPasswordWithToken = async (token: string, newPassword: string) => {
    const { data } = await apiClient.post('/api/auth.php?action=reset_password_with_token', { token, new_password: newPassword });
    if (data.success) {
      return data;
    }
    throw new Error(data.error || 'Failed to reset password');
  };

  return (
    <AuthContext.Provider value={{ isAuthenticated, user, loading, login, logout, forgotPassword, resetPasswordWithToken }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}

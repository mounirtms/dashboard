import { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import { useAuth } from '../hooks/useAuth';

export type ThemeMode = 'dark' | 'light' | 'auto';

interface ThemeContextValue {
  mode: ThemeMode;
  setMode: (mode: ThemeMode) => void;
  isDark: boolean;
  toggleMode: () => void;
  loadPreference: () => void;
}

const ThemeContext = createContext<ThemeContextValue | null>(null);

export function ThemeProvider({ children }: { children: ReactNode }) {
  const { user } = useAuth();
  const [mode, setMode] = useState<ThemeMode>('dark');
  const [initialized, setInitialized] = useState(false);

  // Load preference from localStorage
  useEffect(() => {
    const saved = localStorage.getItem('theme_mode');
    const preferred = saved || 'dark';
    setMode(preferred as ThemeMode);
    setInitialized(true);
  }, []);

  // Apply theme to document
  useEffect(() => {
    const root = document.documentElement;
    if (mode === 'dark') {
      root.classList.add('dark');
      root.style.colorScheme = 'dark';
    } else if (mode === 'light') {
      root.classList.remove('dark');
      root.style.colorScheme = 'light';
    } else {
      // Auto: use system preference
      const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      if (prefersDark) {
        root.classList.add('dark');
        root.style.colorScheme = 'dark';
      } else {
        root.classList.remove('dark');
        root.style.colorScheme = 'light';
      }
    }
    localStorage.setItem('theme_mode', mode);
  }, [mode]);

  // Listen for system theme changes when in auto mode
  useEffect(() => {
    if (mode !== 'auto') return;
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    const handleChange = (e: MediaQueryListEvent) => {
      const root = document.documentElement;
      if (e.matches) {
        root.classList.add('dark');
        root.style.colorScheme = 'dark';
      } else {
        root.classList.remove('dark');
        root.style.colorScheme = 'light';
      }
    };
    mediaQuery.addEventListener('change', handleChange);
    return () => mediaQuery.removeEventListener('change', handleChange);
  }, [mode]);

  const toggleMode = () => {
    setMode(prev => prev === 'dark' ? 'light' : 'dark');
  };

  const value = {
    mode,
    setMode,
    isDark: mode === 'dark',
    toggleMode,
    loadPreference: () => {},
  };

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

export function useThemeMode(): ThemeContextValue {
  const context = useContext(ThemeContext);
  if (!context) {
    throw new Error('useThemeMode must be used within ThemeProvider');
  }
  return context;
}

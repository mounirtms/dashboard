import { createTheme, type Theme } from '@mui/material/styles';

// ── Shared overrides applied to both dark and light themes ─────────────────
function buildComponentOverrides(mode: 'dark' | 'light') {
  const isDark = mode === 'dark';
  return {
    MuiCssBaseline: {
      styleOverrides: {
        body: {
          backgroundImage: 'none',
          '&::-webkit-scrollbar':       { width: '8px', height: '8px' },
          '&::-webkit-scrollbar-track': { background: isDark ? '#0b0f1a' : '#e2e8f0' },
          '&::-webkit-scrollbar-thumb': {
            background: isDark ? '#2a3548' : '#94a3b8',
            borderRadius: '4px',
          },
          '&::-webkit-scrollbar-thumb:hover': {
            background: isDark ? '#334155' : '#64748b',
          },
        },
      },
    },
    MuiCard: {
      styleOverrides: {
        root: {
          backgroundImage: 'none',
          border: `1px solid ${isDark ? '#2a3548' : '#e2e8f0'}`,
          borderRadius: 8,
          backgroundColor: isDark ? '#151c2c' : '#ffffff',
          transition: 'all 0.2s ease',
          '&:hover': {
            borderColor: isDark ? '#334155' : '#cbd5e1',
            boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
          },
        },
      },
    },
    MuiCardContent: {
      styleOverrides: {
        root: { padding: '12px 16px', '&:last-child': { paddingBottom: 12 } },
      },
    },
    MuiPaper: {
      styleOverrides: { root: { backgroundImage: 'none' } },
    },
    MuiDrawer: {
      styleOverrides: {
        paper: {
          backgroundColor: isDark ? '#111827' : '#f8fafc',
          borderRight: `1px solid ${isDark ? '#2a3548' : '#e2e8f0'}`,
        },
      },
    },
    MuiAppBar: {
      styleOverrides: {
        root: {
          backgroundColor: isDark ? '#111827' : '#ffffff',
          borderBottom: `1px solid ${isDark ? '#2a3548' : '#e2e8f0'}`,
          backgroundImage: 'none',
        },
      },
    },
    MuiTableCell: {
      styleOverrides: {
        root: {
          borderBottom: `1px solid ${isDark ? 'rgba(42,53,72,0.6)' : 'rgba(226,232,240,0.8)'}`,
          fontSize: '0.78rem',
          padding: '6px 10px',
        },
        head: {
          backgroundColor: isDark ? '#1a2235' : '#f1f5f9',
          fontWeight: 700,
          textTransform: 'uppercase' as const,
          letterSpacing: '0.04em',
          fontSize: '0.7rem',
          color: isDark ? '#94a3b8' : '#64748b',
          padding: '8px 10px',
        },
      },
    },
    MuiTableRow: {
      styleOverrides: {
        root: {
          '&:hover': { backgroundColor: 'rgba(59,130,246,0.06)' },
        },
      },
    },
    MuiListItemIcon: {
      styleOverrides: { root: { minWidth: 38 } },
    },
    MuiButton: {
      styleOverrides: {
        root: {
          textTransform: 'none' as const,
          fontWeight: 600,
          borderRadius: 8,
          padding: '8px 16px',
        },
      },
    },
    MuiChip: {
      styleOverrides: { root: { fontWeight: 600, borderRadius: 9999 } },
    },
  };
}

const sharedTypography = {
  fontFamily: '"Inter", "Roboto", "Helvetica", "Arial", sans-serif',
  h6: { fontSize: '0.88rem', fontWeight: 700, letterSpacing: '-0.01em' },
  h5: { fontSize: '1.0rem',  fontWeight: 700, letterSpacing: '-0.01em' },
  h4: { fontSize: '1.2rem',  fontWeight: 800, letterSpacing: '-0.02em' },
  body1:   { fontSize: '0.82rem' },
  body2:   { fontSize: '0.78rem' },
  caption: { fontSize: '0.70rem', fontWeight: 500 },
  button:  { fontWeight: 600, textTransform: 'none' as const, fontSize: '0.8rem' },
};

// ── Dark theme (default) ────────────────────────────────────────────────────
export const darkTheme: Theme = createTheme({
  palette: {
    mode: 'dark',
    primary:    { main: '#3b82f6', light: '#60a5fa', dark: '#2563eb' },
    background: { default: '#0b0f1a', paper: '#151c2c' },
    text:       { primary: '#f1f5f9', secondary: '#94a3b8' },
    success:    { main: '#22c55e', light: '#4ade80' },
    warning:    { main: '#eab308', light: '#facc15' },
    error:      { main: '#ef4444', light: '#f87171' },
    info:       { main: '#06b6d4', light: '#22d3ee' },
  },
  typography: sharedTypography,
  shape: { borderRadius: 8 },
  components: buildComponentOverrides('dark'),
});

// ── Light theme ─────────────────────────────────────────────────────────────
export const lightTheme: Theme = createTheme({
  palette: {
    mode: 'light',
    primary:    { main: '#2563eb', light: '#3b82f6', dark: '#1d4ed8' },
    background: { default: '#f1f5f9', paper: '#ffffff' },
    text:       { primary: '#0f172a', secondary: '#475569' },
    success:    { main: '#16a34a', light: '#22c55e' },
    warning:    { main: '#d97706', light: '#f59e0b' },
    error:      { main: '#dc2626', light: '#ef4444' },
    info:       { main: '#0891b2', light: '#06b6d4' },
  },
  typography: sharedTypography,
  shape: { borderRadius: 8 },
  components: buildComponentOverrides('light'),
});

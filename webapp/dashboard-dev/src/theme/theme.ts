import { createTheme } from '@mui/material/styles';

export const darkTheme = createTheme({
  palette: {
    mode: 'dark',
    primary: { main: '#3b82f6', light: '#60a5fa', dark: '#2563eb' },
    background: {
      default: '#0b0f1a',
      paper: '#151c2c',
    },
    text: {
      primary: '#f1f5f9',
      secondary: '#94a3b8',
    },
    success: { main: '#22c55e', light: '#4ade80' },
    warning: { main: '#eab308', light: '#facc15' },
    error: { main: '#ef4444', light: '#f87171' },
    info: { main: '#06b6d4', light: '#22d3ee' },
  },
  typography: {
    fontFamily: '"Inter", "Roboto", "Helvetica", "Arial", sans-serif',
    h6: { fontSize: '0.88rem', fontWeight: 700, letterSpacing: '-0.01em' },
    h5: { fontSize: '1.0rem', fontWeight: 700, letterSpacing: '-0.01em' },
    h4: { fontSize: '1.2rem', fontWeight: 800, letterSpacing: '-0.02em' },
    body1: { fontSize: '0.82rem' },
    body2: { fontSize: '0.78rem' },
    caption: { fontSize: '0.70rem', fontWeight: 500 },
    button: { fontWeight: 600, textTransform: 'none', fontSize: '0.8rem' },
  },
  shape: {
    borderRadius: 8,
  },
  components: {
    MuiCssBaseline: {
      styleOverrides: {
        body: {
          backgroundImage: 'none',
          '&::-webkit-scrollbar': { width: '8px', height: '8px' },
          '&::-webkit-scrollbar-track': { background: '#0b0f1a' },
          '&::-webkit-scrollbar-thumb': { background: '#2a3548', borderRadius: '4px' },
          '&::-webkit-scrollbar-thumb:hover': { background: '#334155' },
        },
      },
    },
    MuiCard: {
      styleOverrides: {
        root: {
          backgroundImage: 'none',
          border: '1px solid #2a3548',
          borderRadius: 8,
          backgroundColor: '#151c2c',
          transition: 'all 0.2s ease',
          '&:hover': {
            borderColor: '#334155',
            boxShadow: '0 4px 12px rgba(0, 0, 0, 0.3)',
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
      styleOverrides: {
        root: {
          backgroundImage: 'none',
        },
      },
    },
    MuiDrawer: {
      styleOverrides: {
        paper: {
          backgroundColor: '#111827',
          borderRight: '1px solid #2a3548',
        },
      },
    },
    MuiAppBar: {
      styleOverrides: {
        root: {
          backgroundColor: '#111827',
          borderBottom: '1px solid #2a3548',
          backgroundImage: 'none',
        },
      },
    },
    MuiTableCell: {
      styleOverrides: {
        root: {
          borderBottom: '1px solid rgba(42, 53, 72, 0.6)',
          fontSize: '0.78rem',
          padding: '6px 10px',
        },
        head: {
          backgroundColor: '#1a2235',
          fontWeight: 700,
          textTransform: 'uppercase',
          letterSpacing: '0.04em',
          fontSize: '0.7rem',
          color: '#94a3b8',
          padding: '8px 10px',
        },
      },
    },
    MuiTableRow: {
      styleOverrides: {
        root: {
          '&:hover': {
            backgroundColor: 'rgba(59, 130, 246, 0.08)',
          },
        },
      },
    },
    MuiListItemIcon: {
      styleOverrides: {
        root: { minWidth: 38 },
      },
    },
    MuiButton: {
      styleOverrides: {
        root: {
          textTransform: 'none',
          fontWeight: 600,
          borderRadius: 8,
          padding: '8px 16px',
        },
      },
    },
    MuiChip: {
      styleOverrides: {
        root: {
          fontWeight: 600,
          borderRadius: 9999,
        },
      },
    },
  },
});

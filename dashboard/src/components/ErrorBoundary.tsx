import { Component, type ReactNode, type ErrorInfo } from 'react';
import { Box, Typography, Button, Card, CardContent } from '@mui/material';
import { BugReport, Refresh } from '@mui/icons-material';

// ── Types ────────────────────────────────────────────────────────────────────

interface Props {
  children: ReactNode;
  /** Optional custom fallback UI. Falls back to the built-in MUI card. */
  fallback?: ReactNode;
}

interface State {
  hasError: boolean;
  error: Error | null;
}

// ── ErrorBoundary ────────────────────────────────────────────────────────────

/**
 * React error boundary that wraps route pages.
 * Catches render-time JavaScript errors and shows a professional fallback UI
 * instead of a blank screen, logging full details to the console.
 *
 * NOTE: React error boundaries must be class components — they cannot be
 * written as function components (no equivalent hook for componentDidCatch).
 */
export default class ErrorBoundary extends Component<Props, State> {
  constructor(props: Props) {
    super(props);
    this.state = { hasError: false, error: null };
  }

  static getDerivedStateFromError(error: Error): State {
    return { hasError: true, error };
  }

  componentDidCatch(error: Error, info: ErrorInfo): void {
    // Log full error + component stack for debugging
    console.error('[ErrorBoundary] Caught render error:', error);
    console.error('[ErrorBoundary] Component stack:', info.componentStack);
  }

  private handleReload = (): void => {
    window.location.reload();
  };

  private handleReset = (): void => {
    this.setState({ hasError: false, error: null });
  };

  render(): ReactNode {
    if (!this.state.hasError) {
      return this.props.children;
    }

    // Use custom fallback if provided
    if (this.props.fallback) {
      return this.props.fallback;
    }

    const { error } = this.state;

    return (
      <Box
        sx={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          minHeight: '60vh',
          p: 3,
        }}
      >
        <Card
          sx={{
            maxWidth: 560,
            width: '100%',
            bgcolor: 'rgba(248,113,113,0.06)',
            border: '1px solid rgba(248,113,113,0.25)',
            borderRadius: 2,
          }}
        >
          <CardContent sx={{ p: 4 }}>
            {/* Icon + title */}
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, mb: 2 }}>
              <BugReport sx={{ color: '#f87171', fontSize: 28 }} />
              <Typography variant="h6" sx={{ fontWeight: 700, color: '#f87171' }}>
                Something went wrong
              </Typography>
            </Box>

            {/* Description */}
            <Typography variant="body2" sx={{ color: 'text.secondary', mb: 2.5 }}>
              An unexpected error occurred while rendering this page. The rest of the
              dashboard is unaffected. You can try navigating to a different page or
              reloading the application.
            </Typography>

            {/* Error details */}
            {error && (
              <Box
                component="pre"
                sx={{
                  p: 1.5,
                  mb: 3,
                  bgcolor: 'rgba(0,0,0,0.3)',
                  borderRadius: 1,
                  fontSize: '0.72rem',
                  fontFamily: 'monospace',
                  color: '#fca5a5',
                  overflowX: 'auto',
                  whiteSpace: 'pre-wrap',
                  wordBreak: 'break-word',
                  maxHeight: 140,
                  overflow: 'auto',
                }}
              >
                {error.message}
              </Box>
            )}

            {/* Actions */}
            <Box sx={{ display: 'flex', gap: 2 }}>
              <Button
                variant="contained"
                color="error"
                startIcon={<Refresh />}
                onClick={this.handleReload}
                size="small"
              >
                Reload Page
              </Button>
              <Button
                variant="outlined"
                color="error"
                onClick={this.handleReset}
                size="small"
              >
                Try Again
              </Button>
            </Box>
          </CardContent>
        </Card>
      </Box>
    );
  }
}

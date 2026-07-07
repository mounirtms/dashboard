import { useMemo, useState, createContext, useContext } from 'react';
import { HashRouter, Routes, Route, Navigate } from 'react-router-dom';
import { ThemeProvider } from '@mui/material/styles';
import CssBaseline from '@mui/material/CssBaseline';
import { darkTheme, lightTheme } from './theme/theme.ts';
import AppLayout from './components/layout/AppLayout.tsx';
import ProtectedRoute from './components/ProtectedRoute.tsx';
import ErrorBoundary from './components/ErrorBoundary.tsx';
import LoginPage from './pages/LoginPage.tsx';
import { AuthProvider } from './hooks/useAuth.tsx';

// Page imports
import OverviewPage from './pages/OverviewPage.tsx';
import SystemOverviewPage from './pages/SystemOverviewPage.tsx';
import SitesPage from './pages/SitesPage.tsx';
import CronsPage from './pages/CronsPage.tsx';
import QueuesPage from './pages/QueuesPage.tsx';
import InfrastructurePage from './pages/InfrastructurePage.tsx';
import TrafficPage from './pages/TrafficPage.tsx';
import PerformancePage from './pages/PerformancePage.tsx';
import GeographyPage from './pages/GeographyPage.tsx';
import SecurityPage from './pages/SecurityPage.tsx';
import SettingsPage from './pages/SettingsPage.tsx';
import PlaceholderPage from './pages/PlaceholderPage.tsx';
import SalesOverviewPage from './pages/SalesOverviewPage.tsx';
import InventoryPage from './pages/InventoryPage.tsx';
import IndexersPage from './pages/IndexersPage.tsx';
import UsersPage from './pages/UsersPage.tsx';
import EtlStatusPage from './pages/EtlStatusPage.tsx';
import DbHealthPage from './pages/DbHealthPage.tsx';
import TelegramPage from './pages/TelegramPage.tsx';
import PushNotificationsPage from './pages/PushNotificationsPage.tsx';
import ActionsPage from './pages/ActionsPage.tsx';
import LogViewerPage from './pages/LogViewerPage.tsx';
import MasterDashboardPage from './pages/MasterDashboardPage.tsx';
import CacheControlPage from './pages/CacheControlPage.tsx';
import ProcessExplorerPage from './pages/ProcessExplorerPage.tsx';
import AuditTrailPage from './pages/AuditTrailPage.tsx';
import TerminalAiPage from './pages/TerminalAiPage.tsx';
import ResetPasswordPage from './pages/ResetPasswordPage.tsx';
import TasksPage from './pages/TasksPage.tsx';
import TaskDetailPage from './pages/TaskDetailPage.tsx';
import PermissionsPage from './pages/PermissionsPage.tsx';
import SystemAuditPage from './pages/SystemAuditPage.tsx';
import PlansPage from './pages/PlansPage.tsx';
import SystemHealthPage from './pages/SystemHealthPage.tsx';
import UserActivityPage from './pages/UserActivityPage.tsx';
import SshSessionsPage from './pages/SshSessionsPage.tsx';
import ServerCommandHistoryPage from './pages/ServerCommandHistoryPage.tsx';
import BackupsPage from './pages/BackupsPage.tsx';
import MagentoProductsPage from './pages/MagentoProductsPage.tsx';
import MagentoCustomersPage from './pages/MagentoCustomersPage.tsx';
import MagentoOrdersPage from './pages/MagentoOrdersPage.tsx';
import MagentoCmsPage from './pages/MagentoCmsPage.tsx';
import MagentoSettingsPage from './pages/MagentoSettingsPage.tsx';

// ── Theme context (lets Header and any child toggle the theme) ───────────────
export interface ThemeContextValue {
  themeMode: 'dark' | 'light';
  toggleTheme: () => void;
}

export const ThemeContext = createContext<ThemeContextValue>({
  themeMode: 'dark',
  toggleTheme: () => {},
});

export function useAppTheme() {
  return useContext(ThemeContext);
}

// ── Helper: wrap a page element in ErrorBoundary ─────────────────────────────
function eb(element: React.ReactNode) {
  return <ErrorBoundary>{element}</ErrorBoundary>;
}

// ── App root ────────────────────────────────────────────────────────────────
export default function App() {
  const [themeMode, setThemeMode] = useState<'dark' | 'light'>(() => {
    const stored = localStorage.getItem('dashboard_theme');
    return stored === 'light' ? 'light' : 'dark';
  });

  const toggleTheme = () => {
    setThemeMode(prev => {
      const next = prev === 'dark' ? 'light' : 'dark';
      localStorage.setItem('dashboard_theme', next);
      return next;
    });
  };

  const themeCtx = useMemo(() => ({ themeMode, toggleTheme }), [themeMode]);
  const muiTheme = themeMode === 'light' ? lightTheme : darkTheme;

  return (
    <ThemeContext.Provider value={themeCtx}>
      <ThemeProvider theme={muiTheme}>
        <CssBaseline />
        <AuthProvider>
          <HashRouter>
            <Routes>
              {/* Public routes */}
              <Route path="/reset-password" element={<ResetPasswordPage />} />
              <Route path="/login" element={<LoginPage />} />

              {/* All authenticated routes — single AppLayout instance */}
              <Route element={<ProtectedRoute />}>
                <Route path="/" element={<AppLayout />}>
                  {/* Default index */}
                  <Route index element={eb(<MasterDashboardPage />)} />

                  {/* Monitoring */}
                  <Route path="system-overview"    element={eb(<SystemOverviewPage />)} />
                  <Route path="sites"              element={eb(<SitesPage />)} />
                  <Route path="infrastructure"     element={eb(<InfrastructurePage />)} />
                  <Route path="log-explorer"       element={eb(<LogViewerPage />)} />
                  <Route path="system-health"      element={eb(<SystemHealthPage />)} />
                  <Route path="terminal-ai"        element={eb(<TerminalAiPage />)} />

                  {/* Monitoring — admin */}
                  <Route path="monitoring/users"    element={eb(<UserActivityPage />)} />
                  <Route path="monitoring/ssh"      element={eb(<SshSessionsPage />)} />
                  <Route path="monitoring/commands" element={eb(<ServerCommandHistoryPage />)} />

                  {/* Commerce */}
                  <Route path="commerce/sales"      element={eb(<SalesOverviewPage />)} />
                  <Route path="commerce/products"   element={eb(<MagentoProductsPage />)} />
                  <Route path="commerce/customers"  element={eb(<MagentoCustomersPage />)} />
                  <Route path="commerce/orders"     element={eb(<MagentoOrdersPage />)} />
                  <Route path="commerce/inventory"  element={eb(<InventoryPage />)} />
                  <Route path="commerce/cms"        element={eb(<MagentoCmsPage />)} />
                  <Route path="commerce/indexers"   element={eb(<IndexersPage />)} />

                  {/* Dev & CI/CD */}
                  <Route path="cicd"    element={eb(<PlaceholderPage title="CI/CD Pipeline" />)} />
                  <Route path="scripts" element={eb(<ActionsPage />)} />

                  {/* Automation */}
                  <Route path="crons"  element={eb(<CronsPage />)} />
                  <Route path="queues" element={eb(<QueuesPage />)} />

                  {/* Notifications */}
                  <Route path="notifications/telegram" element={eb(<TelegramPage />)} />
                  <Route path="notifications/push"     element={eb(<PushNotificationsPage />)} />

                  {/* Cloudflare */}
                  <Route path="cloudflare"  element={eb(<OverviewPage />)} />
                  <Route path="traffic"     element={eb(<TrafficPage />)} />
                  <Route path="performance" element={eb(<PerformancePage />)} />
                  <Route path="geography"   element={eb(<GeographyPage />)} />
                  <Route path="security"    element={eb(<SecurityPage />)} />

                  {/* Tools (accessible to all roles) */}
                  <Route path="tools/db-health"    element={eb(<DbHealthPage />)} />
                  <Route path="tools/audit"        element={eb(<AuditTrailPage />)} />
                  <Route path="tools/system-audit" element={eb(<SystemAuditPage />)} />

                  {/* Tools (admin-only — enforced at PermissionsPage / server) */}
                  <Route path="tools/users"       element={eb(<UsersPage />)} />
                  <Route path="tools/actions"     element={eb(<ActionsPage />)} />
                  <Route path="tools/permissions" element={eb(<PermissionsPage />)} />
                  <Route path="tools/backups"     element={eb(<BackupsPage />)} />
                  <Route path="cache-control"     element={eb(<CacheControlPage />)} />
                  <Route path="process-explorer"  element={eb(<ProcessExplorerPage />)} />
                  <Route path="settings"          element={eb(<SettingsPage />)} />
                  <Route path="commerce/settings" element={eb(<MagentoSettingsPage />)} />

                  {/* Project Management */}
                  <Route path="plans"        element={eb(<PlansPage />)} />
                  <Route path="tasks"        element={eb(<TasksPage />)} />
                  <Route path="tasks/:id"    element={eb(<TaskDetailPage />)} />

                  {/* ETL */}
                  <Route path="etl/status" element={eb(<EtlStatusPage />)} />
                  <Route path="etl/logs"   element={eb(<PlaceholderPage title="ETL Execution Logs" />)} />
                </Route>
              </Route>

              {/* Catch-all */}
              <Route path="*" element={<Navigate to="/login" replace />} />
            </Routes>
          </HashRouter>
        </AuthProvider>
      </ThemeProvider>
    </ThemeContext.Provider>
  );
}

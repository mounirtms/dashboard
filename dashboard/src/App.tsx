import { HashRouter, Routes, Route, Navigate } from 'react-router-dom';
import { ThemeProvider } from '@mui/material/styles';
import CssBaseline from '@mui/material/CssBaseline';
import { darkTheme } from './theme/theme.ts';
import AppLayout from './components/layout/AppLayout.tsx';
import ProtectedRoute from './components/ProtectedRoute.tsx';
import LoginPage from './pages/LoginPage.tsx';
import { AuthProvider } from './hooks/useAuth.tsx';
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
import EtlLogsPage from './pages/EtlLogsPage.tsx';
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
import CiCdPage from './pages/CiCdPage.tsx';

export default function App() {
  return (
    <ThemeProvider theme={darkTheme}>
      <CssBaseline />
      <AuthProvider>
        <HashRouter>
          <Routes>
            {/* Public routes */}
            <Route path="/reset-password" element={<ResetPasswordPage />} />
            <Route path="/login" element={<LoginPage />} />

            {/* All authenticated routes — ProtectedRoute enforces ADMIN_PATHS + PERMISSION_PATHS */}
            <Route element={<ProtectedRoute />}>
              <Route path="/" element={<AppLayout />}>
                <Route index element={<MasterDashboardPage />} />

                {/* Monitoring */}
                <Route path="system-overview" element={<SystemOverviewPage />} />
                <Route path="sites" element={<SitesPage />} />
                <Route path="infrastructure" element={<InfrastructurePage />} />
                <Route path="log-explorer" element={<LogViewerPage />} />
                <Route path="monitoring/users" element={<UserActivityPage />} />
                <Route path="terminal-ai" element={<TerminalAiPage />} />
                <Route path="system-health" element={<SystemHealthPage />} />
                <Route path="monitoring/ssh" element={<SshSessionsPage />} />
                <Route path="monitoring/commands" element={<ServerCommandHistoryPage />} />

                {/* Admin-only tools (ADMIN_PATHS checked in ProtectedRoute) */}
                <Route path="tools/users" element={<UsersPage />} />
                <Route path="settings" element={<SettingsPage />} />
                <Route path="tools/actions" element={<ActionsPage />} />
                <Route path="tools/permissions" element={<PermissionsPage />} />

                {/* Permission-matrix-gated pages */}
                <Route path="cache-control" element={<CacheControlPage />} />
                <Route path="process-explorer" element={<ProcessExplorerPage />} />
                <Route path="tools/backups" element={<BackupsPage />} />

                {/* Commerce */}
                <Route path="commerce/sales" element={<SalesOverviewPage />} />
                <Route path="commerce/products" element={<MagentoProductsPage />} />
                <Route path="commerce/customers" element={<MagentoCustomersPage />} />
                <Route path="commerce/orders" element={<MagentoOrdersPage />} />
                <Route path="commerce/inventory" element={<InventoryPage />} />
                <Route path="commerce/cms" element={<MagentoCmsPage />} />
                <Route path="commerce/indexers" element={<IndexersPage />} />
                <Route path="commerce/settings" element={<MagentoSettingsPage />} />

                {/* Dev & CI/CD */}
                <Route path="cicd" element={<CiCdPage />} />
                <Route path="scripts" element={<ActionsPage />} />

                {/* Automation */}
                <Route path="crons" element={<CronsPage />} />
                <Route path="queues" element={<QueuesPage />} />

                {/* Notifications */}
                <Route path="notifications/telegram" element={<TelegramPage />} />
                <Route path="notifications/push" element={<PushNotificationsPage />} />

                {/* Cloudflare */}
                <Route path="cloudflare" element={<OverviewPage />} />
                <Route path="traffic" element={<TrafficPage />} />
                <Route path="performance" element={<PerformancePage />} />
                <Route path="geography" element={<GeographyPage />} />
                <Route path="security" element={<SecurityPage />} />

                {/* Tools */}
                <Route path="tools/db-health" element={<DbHealthPage />} />
                <Route path="tools/audit" element={<AuditTrailPage />} />
                <Route path="tools/system-audit" element={<SystemAuditPage />} />
                <Route path="plans" element={<PlansPage />} />
                <Route path="tasks" element={<TasksPage />} />
                <Route path="tasks/:id" element={<TaskDetailPage />} />

                {/* ETL */}
                <Route path="etl/status" element={<EtlStatusPage />} />
                <Route path="etl/logs" element={<EtlLogsPage />} />
              </Route>
            </Route>

            {/* Catch-all: redirect to login for unauthenticated, home for authenticated */}
            <Route path="*" element={<Navigate to="/login" replace />} />
          </Routes>
        </HashRouter>
      </AuthProvider>
    </ThemeProvider>
  );
}

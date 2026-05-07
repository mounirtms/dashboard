import { HashRouter, Routes, Route, Navigate } from 'react-router-dom';
import { ThemeProvider } from '@mui/material/styles';
import CssBaseline from '@mui/material/CssBaseline';
import { darkTheme } from './theme/theme';
import AppLayout from './components/layout/AppLayout';
import ProtectedRoute from './components/ProtectedRoute';
import LoginPage from './pages/LoginPage';
import { AuthProvider } from './hooks/useAuth.tsx';
import OverviewPage from './pages/OverviewPage';
import SystemOverviewPage from './pages/SystemOverviewPage';
import SitesPage from './pages/SitesPage';
import CronsPage from './pages/CronsPage';
import QueuesPage from './pages/QueuesPage';
import InfrastructurePage from './pages/InfrastructurePage';
import TrafficPage from './pages/TrafficPage';
import PerformancePage from './pages/PerformancePage';
import GeographyPage from './pages/GeographyPage';
import SecurityPage from './pages/SecurityPage';
import SettingsPage from './pages/SettingsPage';
import PlaceholderPage from './pages/PlaceholderPage';
import SalesOverviewPage from './pages/SalesOverviewPage';
import InventoryPage from './pages/InventoryPage';
import IndexersPage from './pages/IndexersPage';
import UsersPage from './pages/UsersPage';
import EtlStatusPage from './pages/EtlStatusPage';
import DbHealthPage from './pages/DbHealthPage';
import TelegramPage from './pages/TelegramPage';
import PushNotificationsPage from './pages/PushNotificationsPage';
import ActionsPage from './pages/ActionsPage';
import LogViewerPage from './pages/LogViewerPage';
import MasterDashboardPage from './pages/MasterDashboardPage';
import CacheControlPage from './pages/CacheControlPage';
import ProcessExplorerPage from './pages/ProcessExplorerPage';
import AuditTrailPage from './pages/AuditTrailPage';
import TerminalAiPage from './pages/TerminalAiPage';

export default function App() {
  return (
    <ThemeProvider theme={darkTheme}>
      <CssBaseline />
      <AuthProvider>
        <HashRouter>
          <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route element={<ProtectedRoute />}>
              <Route path="/" element={<AppLayout />}>
                <Route index element={<MasterDashboardPage />} />
                
                {/* Monitoring */}
                <Route path="system-overview" element={<SystemOverviewPage />} />
                <Route path="sites" element={<SitesPage />} />
                <Route path="infrastructure" element={<InfrastructurePage />} />
                <Route path="cache-control" element={<CacheControlPage />} />
                <Route path="process-explorer" element={<ProcessExplorerPage />} />
                <Route path="log-explorer" element={<LogViewerPage />} />
                <Route path="terminal-ai" element={<TerminalAiPage />} />
                
                {/* Commerce */}
                <Route path="commerce/sales" element={<SalesOverviewPage />} />
                <Route path="commerce/inventory" element={<InventoryPage />} />
                <Route path="commerce/indexers" element={<IndexersPage />} />
                
                {/* Dev & CI/CD */}
                <Route path="cicd" element={<PlaceholderPage title="CI/CD Pipeline" />} />
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
                <Route path="tools/users" element={<UsersPage />} />
                <Route path="tools/audit" element={<AuditTrailPage />} />
                <Route path="tools/actions" element={<ActionsPage />} />
                <Route path="settings" element={<SettingsPage />} />
                
                {/* ETL */}
                <Route path="etl/status" element={<EtlStatusPage />} />
                <Route path="etl/logs" element={<PlaceholderPage title="ETL Execution Logs" />} />
              </Route>
            </Route>
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </HashRouter>
      </AuthProvider>
    </ThemeProvider>
  );
}

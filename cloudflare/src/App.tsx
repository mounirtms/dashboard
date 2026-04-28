import { HashRouter, Routes, Route, Navigate } from 'react-router-dom';
import { ThemeProvider } from '@mui/material/styles';
import CssBaseline from '@mui/material/CssBaseline';
import { darkTheme } from './theme/theme';
import AppLayout from './components/layout/AppLayout';
import OverviewPage from './pages/OverviewPage';
import TrafficPage from './pages/TrafficPage';
import PerformancePage from './pages/PerformancePage';
import GeographyPage from './pages/GeographyPage';
import SecurityPage from './pages/SecurityPage';
import SettingsPage from './pages/SettingsPage';

export default function App() {
  return (
    <ThemeProvider theme={darkTheme}>
      <CssBaseline />
      <HashRouter>
        <Routes>
          <Route path="/" element={<AppLayout />}>
            <Route index element={<OverviewPage />} />
            <Route path="traffic" element={<TrafficPage />} />
            <Route path="performance" element={<PerformancePage />} />
            <Route path="geography" element={<GeographyPage />} />
            <Route path="security" element={<SecurityPage />} />
            <Route path="settings" element={<SettingsPage />} />
            <Route path="*" element={<Navigate to="/" replace />} />
          </Route>
        </Routes>
      </HashRouter>
    </ThemeProvider>
  );
}

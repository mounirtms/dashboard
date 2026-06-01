import { useState } from 'react';
import { Outlet } from 'react-router-dom';
import {
  Box,
  Drawer,
  CssBaseline,
  useMediaQuery,
  useTheme,
} from '@mui/material';
import Sidebar from './Sidebar';
import Header from './Header';
import Footer from './Footer';
import ErrorBoundary from '../common/ErrorBoundary';

const DRAWER_WIDTH = 260;

export default function AppLayout() {
  const [mobileOpen, setMobileOpen] = useState(false);
  const theme = useTheme();
  const isMobile = useMediaQuery(theme.breakpoints.down('md'));

  const handleDrawerToggle = () => setMobileOpen(!mobileOpen);

  const drawer = <Sidebar onClose={() => setMobileOpen(false)} />;

  return (
    <Box sx={{ display: 'flex', minHeight: '100vh', backgroundColor: '#0a0e18' }}>
      <CssBaseline />
      
      {/* Sidebar Navigation */}
      <Box component="nav" sx={{ width: { md: DRAWER_WIDTH }, flexShrink: { md: 0 } }}>
        {isMobile ? (
          <Drawer
            variant="temporary"
            open={mobileOpen}
            onClose={handleDrawerToggle}
            ModalProps={{ keepMounted: true }}
            sx={{
              display: { xs: 'block', md: 'none' },
              '& .MuiDrawer-paper': { 
                boxSizing: 'border-box', 
                width: DRAWER_WIDTH,
                backgroundColor: '#111827',
                borderRight: '1px solid #1e293b'
              },
            }}
          >
            {drawer}
          </Drawer>
        ) : (
          <Drawer
            variant="permanent"
            sx={{
              display: { xs: 'none', md: 'block' },
              '& .MuiDrawer-paper': { 
                boxSizing: 'border-box', 
                width: DRAWER_WIDTH,
                backgroundColor: '#111827',
                borderRight: '1px solid #1e293b',
                height: '100vh',
                position: 'fixed',
                top: 0,
                left: 0
              },
            }}
          >
            {drawer}
          </Drawer>
        )}
      </Box>

      {/* Main Content Area */}
      <Box
        sx={{
          flexGrow: 1,
          display: 'flex',
          flexDirection: 'column',
          width: { md: `calc(100% - ${DRAWER_WIDTH}px)` },
          minHeight: '100vh',
          backgroundColor: '#0a0e18',
          position: 'relative'
        }}
      >
        <Header onMenuClick={handleDrawerToggle} />

        <Box
          component="main"
          sx={{
            flexGrow: 1,
            p: { xs: 2, md: 3 },
            display: 'flex',
            flexDirection: 'column'
          }}
        >
          <ErrorBoundary>
            <Outlet />
          </ErrorBoundary>
        </Box>

        <Footer />
      </Box>
    </Box>
  );
}

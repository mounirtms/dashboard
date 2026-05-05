import { List, ListItem, ListItemButton, ListItemIcon, ListItemText, Divider, Toolbar, Typography, Box, useTheme } from '@mui/material';
import { Link, useLocation } from 'react-router-dom';
import {
  Dashboard as OverviewIcon,
  ShowChart as TrafficIcon,
  Speed as PerformanceIcon,
  Public as GeographyIcon,
  Shield as SecurityIcon,
  Settings as SettingsIcon,
  Cloud as CloudIcon,
} from '@mui/icons-material';

const navItems = [
  { path: '#/', label: 'Overview', icon: <OverviewIcon /> },
  { path: '#/traffic', label: 'Traffic', icon: <TrafficIcon /> },
  { path: '#/performance', label: 'Performance', icon: <PerformanceIcon /> },
  { path: '#/geography', label: 'Geography', icon: <GeographyIcon /> },
  { path: '#/security', label: 'Security', icon: <SecurityIcon /> },
  { path: '#/settings', label: 'Settings', icon: <SettingsIcon /> },
];

interface SidebarProps { onClose?: () => void }

export default function Sidebar({ onClose }: SidebarProps) {
  const location = useLocation();
  const theme = useTheme();

  const getPath = (hash: string) => hash.replace('#', '') || '/';

  return (
    <Toolbar sx={{ display: 'flex', flexDirection: 'column', p: 0 }}>
      <Box sx={{ px: 2, py: 2.5, borderBottom: 1, borderColor: 'divider' }}>
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.2 }}>
          <CloudIcon sx={{ color: theme.palette.primary.main, fontSize: 28 }} />
          <Box>
            <Typography variant="body2" sx={{ fontWeight: 700, fontSize: '0.88rem', letterSpacing: '-0.01em' }}>
              Cloudflare
            </Typography>
            <Typography variant="caption" sx={{ color: theme.palette.text.secondary, fontSize: '0.68rem', fontWeight: 500 }}>
              Analytics Dashboard
            </Typography>
          </Box>
        </Box>
      </Box>

      <List sx={{ px: 1.5, py: 1.5, flex: 1 }}>
        {navItems.map((item) => {
          const itemPath = getPath(item.path);
          const currentPath = getPath(location.hash || '#/');
          const isSelected = currentPath === itemPath;

          return (
            <ListItem key={item.path} disablePadding sx={{ mb: 0.5 }}>
              <ListItemButton
                component={Link}
                to={item.path}
                selected={isSelected}
                onClick={onClose}
                sx={{
                  borderRadius: 2,
                  py: 0.8,
                  px: 1.2,
                  '&.Mui-selected': {
                    backgroundColor: 'rgba(59, 130, 246, 0.15)',
                    '&:hover': { backgroundColor: 'rgba(59, 130, 246, 0.22)' },
                  },
                  '&:hover': { backgroundColor: 'rgba(255, 255, 255, 0.04)' },
                }}
              >
                <ListItemIcon sx={{
                  color: isSelected ? theme.palette.primary.light : theme.palette.text.secondary,
                  minWidth: 38,
                  transition: 'color 0.2s ease',
                }}>
                  {item.icon}
                </ListItemIcon>
                <ListItemText
                  primary={item.label}
                  sx={{
                    '& .MuiTypography-root': {
                      fontWeight: isSelected ? 600 : 500,
                      fontSize: '0.84rem',
                      color: isSelected ? theme.palette.text.primary : theme.palette.text.secondary,
                      transition: 'color 0.2s ease',
                    },
                  }}
                />
              </ListItemButton>
            </ListItem>
          );
        })}
      </List>

      <Divider sx={{ mx: 2, my: 1.5, borderColor: 'divider' }} />

      <List sx={{ px: 2, pb: 2 }}>
        <ListItem sx={{ px: 0 }}>
          <ListItemText
            primary={<Typography sx={{ fontSize: '0.78rem', fontWeight: 600, color: theme.palette.text.secondary }}>technostationery.com</Typography>}
            secondary={<Typography sx={{ fontSize: '0.68rem', color: theme.palette.text.disabled }}>v1.0.0</Typography>}
          />
        </ListItem>
      </List>
    </Toolbar>
  );
}

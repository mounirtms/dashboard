import { List, ListItem, ListItemButton, ListItemIcon, ListItemText, Divider, Toolbar } from '@mui/material';
import { Link, useLocation } from 'react-router-dom';
import {
  Dashboard as OverviewIcon,
  ShowChart as TrafficIcon,
  Speed as PerformanceIcon,
  Public as GeographyIcon,
  Shield as SecurityIcon,
  Settings as SettingsIcon,
} from '@mui/icons-material';

const navItems = [
  { path: '/cloudflare/#/', label: 'Overview', icon: <OverviewIcon /> },
  { path: '/cloudflare/#/traffic', label: 'Traffic', icon: <TrafficIcon /> },
  { path: '/cloudflare/#/performance', label: 'Performance', icon: <PerformanceIcon /> },
  { path: '/cloudflare/#/geography', label: 'Geography', icon: <GeographyIcon /> },
  { path: '/cloudflare/#/security', label: 'Security', icon: <SecurityIcon /> },
  { path: '/cloudflare/#/settings', label: 'Settings', icon: <SettingsIcon /> },
];

interface SidebarProps { onClose?: () => void }

export default function Sidebar({ onClose }: SidebarProps) {
  const location = useLocation();

  const getPath = (hash: string) => hash.replace('/cloudflare/#', '') || '/';

  return (
    <Toolbar sx={{ display: 'flex', flexDirection: 'column', p: 0 }}>
      <List sx={{ px: 1 }}>
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
                sx={{ borderRadius: 8, '&.Mui-selected': { backgroundColor: 'rgba(59, 130, 246, 0.15)' } }}
              >
                <ListItemIcon sx={{ color: isSelected ? 'primary.main' : 'inherit' }}>{item.icon}</ListItemIcon>
                <ListItemText primary={item.label} sx={{ '& .MuiTypography-root': { fontWeight: isSelected ? 600 : 400 } }} />
              </ListItemButton>
            </ListItem>
          );
        })}
      </List>
      <Divider sx={{ my: 2 }} />
      <List sx={{ px: 1 }}>
        <ListItem>
          <ListItemText
            primary="Cloudflare Dashboard"
            secondary="v1.0.0"
          />
        </ListItem>
      </List>
    </Toolbar>
  );
}

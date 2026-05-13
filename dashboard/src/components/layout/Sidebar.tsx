import { 
  List, 
  ListItem, 
  ListItemButton, 
  ListItemIcon, 
  ListItemText, 
  Divider, 
  Toolbar, 
  Typography, 
  Box, 
  useTheme,
  Collapse,
  TextField,
  InputAdornment,
  IconButton
} from '@mui/material';
import { Link, useLocation } from 'react-router-dom';
import {
  Dashboard as OverviewIcon,
  Refresh,
  Cached,
  Dns as SystemIcon,
  Language as SitesIcon,
  Schedule as CronsIcon,
  ViewList as QueuesIcon,
  SettingsInputComponent as InfrastructureIcon,
  ShowChart as TrafficIcon,
  Speed as PerformanceIcon,
  Public as GeographyIcon,
  Shield as SecurityIcon,
  Settings as SettingsIcon,
  Person,
  ExpandLess,
  ExpandMore,
  ShoppingCart as CommerceIcon,
  Inventory,
  Terminal as ScriptsIcon,
  Sync as CicdIcon,
  Storage as DbIcon,
  Notifications as AlertsIcon,
  TerminalOutlined as ActionsIcon,
  DataObject as EtlIcon,
  Cloud as CloudIcon,
  Description as DocsIcon,
  Api as ApiIcon,
  Terminal,
  History as AuditIcon,
  AutoAwesome,
  Search,
  Close,
  Task,
} from '@mui/icons-material';
import React, { useState, useEffect, useRef, useCallback } from 'react';
import { usePermissions } from '../../hooks/usePermissions';
import { ADMIN_PATHS } from '../../config/routes';

interface NavItem {
  label: string;
  icon: React.ReactNode;
  path?: string;
  children?: NavItem[];
}

const navItems: NavItem[] = [
  { path: '/terminal-ai', label: 'Terminal AI', icon: <AutoAwesome /> },
  {
    label: 'Monitoring',
    icon: <OverviewIcon />,
    children: [
      { path: '/', label: 'System Overview', icon: <SystemIcon /> },
      { path: '/sites', label: 'Managed Sites', icon: <SitesIcon /> },
      { path: '/infrastructure', label: 'Infrastructure', icon: <InfrastructureIcon /> },
      { path: '/cache-control', label: 'Cache Control', icon: <Cached /> },
      { path: '/process-explorer', label: 'Process Explorer', icon: <Terminal /> },
      { path: '/log-explorer', label: 'Log Explorer', icon: <DocsIcon /> },
    ]
  },
  {
    label: 'Commerce',
    icon: <CommerceIcon />,
    children: [
      { path: '/commerce/sales', label: 'Sales Overview', icon: <CommerceIcon /> },
      { path: '/commerce/inventory', label: 'Inventory', icon: <Inventory /> },
      { path: '/commerce/indexers', label: 'Indexers', icon: <Refresh /> },
    ]
  },
  {
    label: 'Dev & CI/CD',
    icon: <CicdIcon />,
    children: [
      { path: '/cicd', label: 'Pipeline', icon: <CicdIcon /> },
      { path: '/scripts', label: 'Script Runner', icon: <ScriptsIcon /> },
    ]
  },
  {
    label: 'Automation',
    icon: <QueuesIcon />,
    children: [
      { path: '/crons', label: 'Cron Jobs', icon: <CronsIcon /> },
      { path: '/queues', label: 'Message Queues', icon: <QueuesIcon /> },
    ]
  },
  {
    label: 'Notifications',
    icon: <AlertsIcon />,
    children: [
      { path: '/notifications/telegram', label: 'Telegram Bot', icon: <AlertsIcon /> },
      { path: '/notifications/push', label: 'Push (Webpushr)', icon: <ActionsIcon /> },
    ]
  },
  {
    label: 'Cloudflare',
    icon: <CloudIcon />,
    children: [
      { path: '/cloudflare', label: 'Overview', icon: <OverviewIcon /> },
      { path: '/traffic', label: 'Traffic', icon: <TrafficIcon /> },
      { path: '/performance', label: 'Performance', icon: <PerformanceIcon /> },
      { path: '/geography', label: 'Geography', icon: <GeographyIcon /> },
      { path: '/security', label: 'Security', icon: <SecurityIcon /> },
    ]
  },
  {
    label: 'Tools',
    icon: <ActionsIcon />,
    children: [
      { path: '/tools/db-health', label: 'DB Health', icon: <DbIcon /> },
      { path: '/tools/users', label: 'User Management', icon: <Person /> },
      { path: '/tasks', label: 'Tasks', icon: <Task /> },
      { path: '/tools/audit', label: 'Audit Trail', icon: <AuditIcon /> },
      { path: '/tools/actions', label: 'Emergency Actions', icon: <ActionsIcon /> },
      { path: '/settings', label: 'Dashboard Settings', icon: <SettingsIcon /> },
    ]
  },
  {
    label: 'ETL Platform',
    icon: <EtlIcon />,
    children: [
      { path: '/etl/status', label: 'Sync Status', icon: <EtlIcon /> },
      { path: '/etl/logs', label: 'Execution Logs', icon: <ScriptsIcon /> },
    ]
  },
  {
    label: 'RESOURCES',
    icon: <DocsIcon />,
    children: [
      { path: '/docs/index.html', label: 'System Docs', icon: <DocsIcon /> },
      { path: '/docs/ARCHITECTURE.md', label: 'Architecture', icon: <DocsIcon /> },
      { path: '/api/info.php', label: 'API Explorer', icon: <ApiIcon /> },
    ]
  }
];

interface SidebarProps { onClose?: () => void }

export default function Sidebar({ onClose }: SidebarProps) {
  const location = useLocation();
  const theme = useTheme();
  const { isAdmin } = usePermissions();
  const [openMenus, setOpenMenus] = useState<Record<string, boolean>>({
    'Monitoring': true,
    'Cloudflare': false
  });
  const [searchQuery, setSearchQuery] = useState('');
  const [debouncedQuery, setDebouncedQuery] = useState('');
  const searchRef = useRef<HTMLInputElement>(null);
  const [forcedExpand, setForcedExpand] = useState<Record<string, boolean>>({});

  // Debounce search input
  useEffect(() => {
    const timer = setTimeout(() => setDebouncedQuery(searchQuery), 150);
    return () => clearTimeout(timer);
  }, [searchQuery]);

  // Ctrl+K keyboard shortcut
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        searchRef.current?.focus();
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, []);

  const toggleMenu = (label: string) => {
    setOpenMenus(prev => ({ ...prev, [label]: !prev[label] }));
  };

  const matchesSearch = useCallback((text: string, query: string) => {
    if (!query) return true;
    try { return new RegExp(query, 'i').test(text); }
    catch { return text.toLowerCase().includes(query.toLowerCase()); }
  }, []);

  // Check if a nav item should be visible based on permissions
  const isNavItemVisible = useCallback((item: NavItem): boolean => {
    if (item.path && ADMIN_PATHS.has(item.path) && !isAdmin) {
      return false;
    }
    if (item.children) {
      return item.children.some(child => isNavItemVisible(child));
    }
    return true;
  }, [isAdmin]);

  // Deep filter nav items by both search and permissions
  const deepFilterItems = useCallback((items: NavItem[]): NavItem[] => {
    return items.reduce<NavItem[]>((acc, item) => {
      if (!isNavItemVisible(item)) return acc;
      if (item.children) {
        const visibleChildren = deepFilterItems(item.children);
        if (visibleChildren.length === 0) return acc;
        // Also apply search filtering to children
        const searchedChildren = debouncedQuery
          ? visibleChildren.filter(child => matchesSearch(child.label, debouncedQuery))
          : visibleChildren;
        if (searchedChildren.length === 0) return acc;
        acc.push({ ...item, children: searchedChildren });
      } else {
        if (debouncedQuery && !matchesSearch(item.label, debouncedQuery)) return acc;
        acc.push(item);
      }
      return acc;
    }, []);
  }, [isNavItemVisible, matchesSearch, debouncedQuery]);

  const displayItems = deepFilterItems(navItems);

  // Auto-expand sections when search query is active
  useEffect(() => {
    if (!debouncedQuery) {
      setForcedExpand({});
      return;
    }
    const expanded: Record<string, boolean> = {};
    navItems.forEach(item => {
      if (item.children) {
        const hasMatch = item.children.some(child => matchesSearch(child.label, debouncedQuery) && isNavItemVisible(child));
        if (hasMatch) expanded[item.label] = true;
      }
    });
    setForcedExpand(expanded);
  }, [debouncedQuery, matchesSearch, isNavItemVisible]);

  // Count total matching child items
  const countMatchingItems = useCallback((items: NavItem[]): number => {
    return items.reduce((count, item) => {
      if (item.children) return count + countMatchingItems(item.children);
      return count + 1;
    }, 0);
  }, []);

  const isActive = (path?: string) => {
    if (!path) return false;
    if (path === '/') return location.pathname === '/' || location.pathname === '';
    return location.pathname === path;
  };
  
  const isChildActive = (children?: NavItem[]) => 
    children ? children.some(child => isActive(child.path)) : false;

  // Merge forced expand with user open state
  const isOpen = (label: string) => forcedExpand[label] ?? openMenus[label];

  const clearSearch = () => {
    setSearchQuery('');
    setForcedExpand({});
    searchRef.current?.focus();
  };

  return (
    <Box sx={{ height: '100%', display: 'flex', flexDirection: 'column', pt: 1 }}>
      <Box sx={{ px: 2.5, py: 1.5, mb: 1 }}>
        <Typography variant="h6" sx={{ 
          color: 'primary.main', 
          fontWeight: 900, 
          letterSpacing: '-0.05em',
          fontSize: '1rem',
          display: 'flex',
          alignItems: 'center',
          gap: 1
        }}>
          TECHNO <Box component="span" sx={{ color: 'text.primary', fontWeight: 400 }}>MONITOR</Box>
        </Typography>
        <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 600, letterSpacing: '0.04em', textTransform: 'uppercase', fontSize: '0.6rem' }}>
          Infrastructure Platform
        </Typography>
      </Box>

      {/* Search Field */}
      <Box sx={{ px: 1.5, mb: 1 }}>
        <TextField
          inputRef={searchRef}
          size="small"
          fullWidth
          placeholder="Search pages... (Ctrl+K)"
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          slotProps={{
            input: {
              startAdornment: (
                <InputAdornment position="start">
                  <Search sx={{ fontSize: 16, color: 'text.disabled' }} />
                </InputAdornment>
              ),
              endAdornment: searchQuery ? (
                <InputAdornment position="end">
                  <IconButton size="small" onClick={clearSearch} edge="end">
                    <Close sx={{ fontSize: 16 }} />
                  </IconButton>
                </InputAdornment>
              ) : undefined,
              sx: {
                borderRadius: '8px',
                backgroundColor: 'rgba(255,255,255,0.03)',
                '& fieldset': { borderColor: 'rgba(255,255,255,0.06)' },
                '&:hover fieldset': { borderColor: 'rgba(255,255,255,0.12)' },
              }
            }
          }}
          sx={{
            '& .MuiOutlinedInput-root': { fontSize: '0.75rem', height: 34 },
            '& .MuiOutlinedInput-input': { py: '5px', color: 'text.primary' },
            '& .MuiOutlinedInput-notchedOutline': { borderWidth: '1px !important' },
          }}
        />
        {debouncedQuery && (
          <Typography variant="caption" sx={{ color: 'text.disabled', fontSize: '0.6rem', mt: 0.5, ml: 1, display: 'block' }}>
            {countMatchingItems(displayItems)} page{countMatchingItems(displayItems) !== 1 ? 's' : ''} found
          </Typography>
        )}
      </Box>

      <List sx={{ px: 1, pb: 4, flex: 1, overflowY: 'auto', '&::-webkit-scrollbar': { width: 4 }, '&::-webkit-scrollbar-thumb': { background: 'rgba(255,255,255,0.05)', borderRadius: 2 } }}>
        {displayItems.length === 0 && debouncedQuery ? (
          <Box sx={{ px: 2, py: 3, textAlign: 'center' }}>
            <Search sx={{ fontSize: 24, color: 'text.disabled', mb: 1 }} />
            <Typography variant="caption" sx={{ color: 'text.disabled', display: 'block' }}>
              No pages found
            </Typography>
          </Box>
        ) : null}
        {displayItems.map((item) => (
          <Box key={item.label} sx={{ mb: 0.25 }}>
            {item.children ? (
              <>
                <ListItemButton 
                  onClick={() => toggleMenu(item.label)}
                  sx={{ 
                    borderRadius: 1.5,
                    py: 0.5,
                    px: 1.5,
                    backgroundColor: isChildActive(item.children) ? 'rgba(59, 130, 246, 0.06)' : 'transparent',
                    '&:hover': { backgroundColor: 'rgba(255, 255, 255, 0.03)' }
                  }}
                >
                  <ListItemIcon sx={{ color: isChildActive(item.children) ? 'primary.main' : 'text.secondary', minWidth: 28 }}>
                    {React.cloneElement(item.icon as React.ReactElement, { sx: { fontSize: 18 } } as any)}
                  </ListItemIcon>
                  <ListItemText 
                    primary={
                      <Typography sx={{ 
                        fontSize: '0.78rem', 
                        fontWeight: isChildActive(item.children) ? 700 : 500,
                        color: isChildActive(item.children) ? 'text.primary' : 'text.secondary'
                      }}>
                        {item.label}
                      </Typography>
                    } 
                  />
                  {isOpen(item.label) ? <ExpandLess sx={{ fontSize: 16, color: 'text.disabled' }} /> : <ExpandMore sx={{ fontSize: 16, color: 'text.disabled' }} />}
                </ListItemButton>
                <Collapse in={isOpen(item.label)} timeout="auto" unmountOnExit>
                  <List component="div" disablePadding sx={{ pl: 1.5 }}>
                    {item.children.map((child) => (
                      <ListItemButton
                        key={child.path}
                        {...(child.path?.startsWith('/docs') || child.path?.startsWith('/api') 
                          ? { component: 'a', href: child.path } 
                          : { component: Link, to: child.path! }
                        )}
                        onClick={onClose}
                        selected={isActive(child.path)}
                        sx={{
                          borderRadius: 1,
                          py: 0.4,
                          my: 0.1,
                          '&.Mui-selected': {
                            backgroundColor: 'rgba(59, 130, 246, 0.12)',
                            '&:hover': { backgroundColor: 'rgba(59, 130, 246, 0.18)' },
                          },
                        }}
                      >
                        <ListItemIcon sx={{ minWidth: 24, color: isActive(child.path) ? 'primary.light' : 'text.disabled' }}>
                          {React.cloneElement(child.icon as React.ReactElement, { sx: { fontSize: 14 } } as any)}
                        </ListItemIcon>
                        <ListItemText 
                          primary={
                            <Typography sx={{ 
                              fontSize: '0.75rem', 
                              fontWeight: isActive(child.path) ? 700 : 400,
                              color: isActive(child.path) ? 'primary.light' : 'text.secondary'
                            }}>
                              {child.label}
                            </Typography>
                          } 
                        />
                      </ListItemButton>
                    ))}
                  </List>
                </Collapse>
              </>
            ) : (
              <ListItemButton
                component={Link}
                to={item.path!}
                onClick={onClose}
                selected={isActive(item.path)}
                sx={{
                  borderRadius: 1.5,
                  py: 0.5,
                  px: 1.5,
                  '&.Mui-selected': {
                    backgroundColor: 'rgba(59, 130, 246, 0.12)',
                  },
                }}
              >
                <ListItemIcon sx={{ color: isActive(item.path) ? 'primary.main' : 'text.secondary', minWidth: 28 }}>
                  {React.cloneElement(item.icon as React.ReactElement, { sx: { fontSize: 18 } } as any)}
                </ListItemIcon>
                <ListItemText 
                  primary={
                    <Typography sx={{ fontSize: '0.78rem', fontWeight: isActive(item.path) ? 700 : 500 }}>
                      {item.label}
                    </Typography>
                  } 
                />
              </ListItemButton>
            )}
          </Box>
        ))}
      </List>

      <Box sx={{ mt: 'auto', p: 2, borderTop: '1px solid', borderColor: 'divider', background: 'rgba(0,0,0,0.2)' }}>
        <Typography sx={{ fontSize: '0.7rem', fontWeight: 700, color: 'text.secondary' }}>technostationery.com</Typography>
        <Typography sx={{ fontSize: '0.62rem', color: 'text.disabled' }}>TSM Platform v3.1.5</Typography>
      </Box>
    </Box>
  );
}

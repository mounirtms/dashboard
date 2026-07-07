import {
  List, ListItemButton, ListItemIcon, ListItemText,
  Typography, Box, Collapse, TextField, InputAdornment,
  IconButton, Badge, Tooltip,
} from '@mui/material';
import { Link, useLocation } from 'react-router-dom';
import {
  Dashboard as DashboardIcon,
  Refresh, Cached,
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
  ExpandLess, ExpandMore,
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
  History as HistoryIcon,
  AutoAwesome,
  Search, Close,
  Task, Lan, TrendingUp, CloudDownload,
  SpeedOutlined as OverviewIcon,
} from '@mui/icons-material';
import { useState, useEffect, useRef, useCallback } from 'react';
import { usePermissions } from '../../hooks/usePermissions';
import { ADMIN_PATHS } from '../../config/routes';
import technoLogo from '../../assets/logo_techno.png';

// ── Nav tree ────────────────────────────────────────────────────────────────

interface NavItem {
  label: string;
  icon: JSX.Element;
  path?: string;
  badge?: number;
  external?: boolean;
  children?: NavItem[];
}

const navItems: NavItem[] = [
  { path: '/',           label: 'Cockpit',       icon: <OverviewIcon /> },
  { path: '/terminal-ai', label: 'Terminal AI',  icon: <AutoAwesome /> },
  {
    label: 'Monitoring', icon: <DashboardIcon />,
    children: [
      { path: '/system-overview',   label: 'System Overview',   icon: <SystemIcon /> },
      { path: '/sites',             label: 'Managed Sites',     icon: <SitesIcon /> },
      { path: '/infrastructure',    label: 'Infrastructure',    icon: <InfrastructureIcon /> },
      { path: '/cache-control',     label: 'Cache Control',     icon: <Cached /> },
      { path: '/process-explorer',  label: 'Process Explorer',  icon: <Terminal /> },
      { path: '/log-explorer',      label: 'Log Explorer',      icon: <DocsIcon /> },
      { path: '/monitoring/ssh',    label: 'SSH Sessions',      icon: <SecurityIcon /> },
      { path: '/monitoring/commands', label: 'Command History', icon: <HistoryIcon /> },
      { path: '/monitoring/users',  label: 'User Activity',     icon: <Person /> },
      { path: '/system-health',     label: 'System Health',     icon: <Lan /> },
    ],
  },
  {
    label: 'Commerce', icon: <CommerceIcon />,
    children: [
      { path: '/commerce/sales',     label: 'Sales Overview',   icon: <CommerceIcon /> },
      { path: '/commerce/products',  label: 'Products',         icon: <Inventory /> },
      { path: '/commerce/customers', label: 'Customers',        icon: <Person /> },
      { path: '/commerce/orders',    label: 'Orders',           icon: <CommerceIcon /> },
      { path: '/commerce/inventory', label: 'Inventory',        icon: <Inventory /> },
      { path: '/commerce/cms',       label: 'CMS & Content',    icon: <DocsIcon /> },
      { path: '/commerce/indexers',  label: 'Indexers',         icon: <Refresh /> },
      { path: '/commerce/settings',  label: 'Magento Settings', icon: <SettingsIcon /> },
    ],
  },
  {
    label: 'Dev & CI/CD', icon: <CicdIcon />,
    children: [
      { path: '/cicd',    label: 'Pipeline',      icon: <CicdIcon /> },
      { path: '/scripts', label: 'Script Runner', icon: <ScriptsIcon /> },
    ],
  },
  {
    label: 'Automation', icon: <QueuesIcon />,
    children: [
      { path: '/crons',  label: 'Cron Jobs',       icon: <CronsIcon /> },
      { path: '/queues', label: 'Message Queues',  icon: <QueuesIcon /> },
    ],
  },
  {
    label: 'Notifications', icon: <AlertsIcon />,
    children: [
      { path: '/notifications/telegram', label: 'Telegram Bot',    icon: <AlertsIcon /> },
      { path: '/notifications/push',     label: 'Push (Webpushr)', icon: <ActionsIcon /> },
    ],
  },
  {
    label: 'Project Management', icon: <TrendingUp />,
    children: [
      { path: '/plans',      label: 'Plans & Roadmap', icon: <TrendingUp /> },
      { path: '/tasks',      label: 'Tasks',           icon: <Task /> },
      { path: '/tools/audit', label: 'Audit Trail',   icon: <HistoryIcon /> },
    ],
  },
  {
    label: 'Cloudflare', icon: <CloudIcon />,
    children: [
      { path: '/cloudflare',  label: 'Overview',    icon: <OverviewIcon /> },
      { path: '/traffic',     label: 'Traffic',     icon: <TrafficIcon /> },
      { path: '/performance', label: 'Performance', icon: <PerformanceIcon /> },
      { path: '/geography',   label: 'Geography',   icon: <GeographyIcon /> },
      { path: '/security',    label: 'Security',    icon: <SecurityIcon /> },
    ],
  },
  {
    label: 'Tools', icon: <ActionsIcon />,
    children: [
      { path: '/tools/backups',      label: 'Server Backups',    icon: <CloudDownload /> },
      { path: '/tools/db-health',    label: 'DB Health',         icon: <DbIcon /> },
      { path: '/tools/users',        label: 'User Management',   icon: <Person /> },
      { path: '/tools/system-audit', label: 'System Audit',      icon: <SecurityIcon /> },
      { path: '/tools/actions',      label: 'Emergency Actions', icon: <ActionsIcon /> },
      { path: '/settings',           label: 'Dashboard Settings', icon: <SettingsIcon /> },
    ],
  },
  {
    label: 'ETL Platform', icon: <EtlIcon />,
    children: [
      { path: '/etl/status', label: 'Sync Status',     icon: <EtlIcon /> },
      { path: '/etl/logs',   label: 'Execution Logs',  icon: <ScriptsIcon /> },
    ],
  },
  {
    label: 'RESOURCES', icon: <DocsIcon />,
    children: [
      { path: '/presentation/index.html',                              label: 'Exec Audit 2026',      icon: <TrendingUp />,   external: true },
      { path: '/docs/index.html',                                      label: 'System Docs',          icon: <DocsIcon />,    external: true },
      { path: '/docs/ARCHITECTURE.md',                                 label: 'Architecture',         icon: <DocsIcon />,    external: true },
      { path: '/reports/view.php?file=security_audit_report.html',     label: 'Security Audit',       icon: <SecurityIcon />, external: true },
      { path: '/reports/view.php?file=ssh_hardening_report.html',      label: 'SSH Hardening',        icon: <SecurityIcon />, external: true },
      { path: '/reports/view.php?file=2fa_setup_guide.html',           label: '2FA Setup Guide',      icon: <SecurityIcon />, external: true },
      { path: '/api/info.php',                                         label: 'API Explorer',         icon: <ApiIcon />,     external: true },
    ],
  },
];

// ── Props ────────────────────────────────────────────────────────────────────

interface SidebarProps { onClose?: () => void }

// ── Component ────────────────────────────────────────────────────────────────

export default function Sidebar({ onClose }: SidebarProps) {
  const location = useLocation();
  const { isAdmin } = usePermissions();

  const [openMenus, setOpenMenus] = useState<Record<string, boolean>>({
    Monitoring: true,
    Cloudflare: false,
  });
  const [searchQuery, setSearchQuery]   = useState('');
  const [debouncedQuery, setDebouncedQuery] = useState('');
  const searchRef = useRef<HTMLInputElement>(null);
  const [forcedExpand, setForcedExpand] = useState<Record<string, boolean>>({});
  const [taskBadgeCount, setTaskBadgeCount] = useState(0);

  // Fetch pending task badge count (every 2 min)
  useEffect(() => {
    const load = async () => {
      try {
        const r = await fetch('/api/tasks.php?action=stats');
        if (r.ok) {
          const s = await r.json();
          setTaskBadgeCount(s.pending ?? 0);
        }
      } catch { /* silent */ }
    };
    load();
    const iv = setInterval(load, 2 * 60_000);
    return () => clearInterval(iv);
  }, []);

  // Debounce search
  useEffect(() => {
    const t = setTimeout(() => setDebouncedQuery(searchQuery), 150);
    return () => clearTimeout(t);
  }, [searchQuery]);

  // Ctrl+K → focus search
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

  const toggleMenu = (label: string) =>
    setOpenMenus(prev => ({ ...prev, [label]: !prev[label] }));

  const matchesSearch = useCallback((text: string, q: string) => {
    if (!q) return true;
    try { return new RegExp(q, 'i').test(text); }
    catch { return text.toLowerCase().includes(q.toLowerCase()); }
  }, []);

  const isNavItemVisible = useCallback((item: NavItem): boolean => {
    if (item.path && ADMIN_PATHS.has(item.path) && !isAdmin) return false;
    if (item.children) return item.children.some(c => isNavItemVisible(c));
    return true;
  }, [isAdmin]);

  const deepFilterItems = useCallback((items: NavItem[]): NavItem[] =>
    items.reduce<NavItem[]>((acc, item) => {
      if (!isNavItemVisible(item)) return acc;
      const patched = { ...item };
      if (patched.path === '/tasks') patched.badge = taskBadgeCount;

      if (patched.children) {
        const visible   = deepFilterItems(patched.children);
        if (!visible.length) return acc;
        const searched  = debouncedQuery
          ? visible.filter(c => matchesSearch(c.label, debouncedQuery))
          : visible;
        if (!searched.length) return acc;
        acc.push({ ...patched, children: searched });
      } else {
        if (debouncedQuery && !matchesSearch(patched.label, debouncedQuery)) return acc;
        acc.push(patched);
      }
      return acc;
    }, []),
  [isNavItemVisible, matchesSearch, debouncedQuery, taskBadgeCount]);

  // Auto-expand when searching
  useEffect(() => {
    if (!debouncedQuery) { setForcedExpand({}); return; }
    const expanded: Record<string, boolean> = {};
    navItems.forEach(item => {
      if (item.children) {
        if (item.children.some(c => matchesSearch(c.label, debouncedQuery) && isNavItemVisible(c)))
          expanded[item.label] = true;
      }
    });
    setForcedExpand(expanded);
  }, [debouncedQuery, matchesSearch, isNavItemVisible]);

  const displayItems = deepFilterItems(navItems);

  const countLeafItems = useCallback((items: NavItem[]): number =>
    items.reduce((n, i) => n + (i.children ? countLeafItems(i.children) : 1), 0),
  []);

  const isActive   = (path?: string) => {
    if (!path) return false;
    if (path === '/') return location.pathname === '/' || location.pathname === '';
    return location.pathname === path;
  };
  const isChildActive = (children?: NavItem[]) =>
    children ? children.some(c => isActive(c.path)) : false;

  const isOpen = (label: string) => forcedExpand[label] ?? openMenus[label];
  const clearSearch = () => { setSearchQuery(''); setForcedExpand({}); searchRef.current?.focus(); };

  return (
    <Box sx={{ height: '100%', display: 'flex', flexDirection: 'column', pt: 1 }}>
      {/* Brand */}
      <Box sx={{ px: 2.5, py: 1.5, mb: 1 }}>
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, mb: 0.5 }}>
          <Box
            component="img"
            src={technoLogo}
            alt="TechnoStationery"
            sx={{ height: 28, width: 'auto', objectFit: 'contain', flexShrink: 0 }}
          />
          <Typography variant="h6" sx={{
            color: 'primary.main', fontWeight: 900, letterSpacing: '-0.05em',
            fontSize: '1rem', display: 'flex', alignItems: 'center', gap: 0.5,
          }}>
            TECHNO <Box component="span" sx={{ color: 'text.primary', fontWeight: 400 }}>MONITOR</Box>
          </Typography>
        </Box>
        <Typography variant="caption" sx={{
          color: 'text.disabled', fontWeight: 600, letterSpacing: '0.04em',
          textTransform: 'uppercase', fontSize: '0.6rem',
        }}>
          Infrastructure Platform
        </Typography>
      </Box>

      {/* Search */}
      <Box sx={{ px: 1.5, mb: 1 }}>
        <TextField
          inputRef={searchRef}
          size="small"
          fullWidth
          placeholder="Search pages… (Ctrl+K)"
          value={searchQuery}
          onChange={e => setSearchQuery(e.target.value)}
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
              },
            },
          }}
          sx={{
            '& .MuiOutlinedInput-root': { fontSize: '0.75rem', height: 34 },
            '& .MuiOutlinedInput-input': { py: '5px', color: 'text.primary' },
            '& .MuiOutlinedInput-notchedOutline': { borderWidth: '1px !important' },
          }}
        />
        {debouncedQuery && (
          <Typography variant="caption" sx={{ color: 'text.disabled', fontSize: '0.6rem', mt: 0.5, ml: 1, display: 'block' }}>
            {countLeafItems(displayItems)} page{countLeafItems(displayItems) !== 1 ? 's' : ''} found
          </Typography>
        )}
      </Box>

      {/* Nav list */}
      <List sx={{
        px: 1, pb: 4, flex: 1, overflowY: 'auto',
        '&::-webkit-scrollbar': { width: 4 },
        '&::-webkit-scrollbar-thumb': { background: 'rgba(255,255,255,0.05)', borderRadius: 2 },
      }}>
        {displayItems.length === 0 && debouncedQuery && (
          <Box sx={{ px: 2, py: 3, textAlign: 'center' }}>
            <Search sx={{ fontSize: 24, color: 'text.disabled', mb: 1 }} />
            <Typography variant="caption" sx={{ color: 'text.disabled', display: 'block' }}>
              No pages found
            </Typography>
          </Box>
        )}

        {displayItems.map(item => (
          <Box key={item.label || item.path} sx={{ mb: 0.25 }}>
            {item.children ? (
              <>
                <ListItemButton
                  onClick={() => toggleMenu(item.label)}
                  sx={{
                    borderRadius: 1.5, py: 0.5, px: 1.5,
                    backgroundColor: isChildActive(item.children)
                      ? 'rgba(59,130,246,0.06)' : 'transparent',
                    '&:hover': { backgroundColor: 'rgba(255,255,255,0.03)' },
                  }}
                >
                  <ListItemIcon sx={{
                    color: isChildActive(item.children) ? 'primary.main' : 'text.secondary',
                    minWidth: 28,
                  }}>
                    <IconClone icon={item.icon} size={18} />
                  </ListItemIcon>
                  <ListItemText primary={
                    <Typography sx={{
                      fontSize: '0.78rem',
                      fontWeight: isChildActive(item.children) ? 700 : 500,
                      color: isChildActive(item.children) ? 'text.primary' : 'text.secondary',
                    }}>
                      {item.label}
                    </Typography>
                  } />
                  {isOpen(item.label)
                    ? <ExpandLess sx={{ fontSize: 16, color: 'text.disabled' }} />
                    : <ExpandMore sx={{ fontSize: 16, color: 'text.disabled' }} />}
                </ListItemButton>

                <Collapse in={isOpen(item.label)} timeout="auto" unmountOnExit>
                  <List component="div" disablePadding sx={{ pl: 1.5 }}>
                    {item.children.map(child => (
                      <ListItemButton
                        key={child.path}
                        {...(child.external
                          ? { component: 'a', href: child.path, target: '_blank', rel: 'noopener noreferrer' }
                          : { component: Link, to: child.path! }
                        )}
                        onClick={onClose}
                        selected={isActive(child.path)}
                        sx={{
                          borderRadius: 1, py: 0.4, my: 0.1,
                          '&.Mui-selected': {
                            backgroundColor: 'rgba(59,130,246,0.12)',
                            '&:hover': { backgroundColor: 'rgba(59,130,246,0.18)' },
                          },
                        }}
                      >
                        <ListItemIcon sx={{
                          minWidth: 24,
                          color: isActive(child.path) ? 'primary.light' : 'text.disabled',
                        }}>
                          <IconClone icon={child.icon} size={14} />
                        </ListItemIcon>
                        <ListItemText primary={
                          <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                            <Typography sx={{
                              fontSize: '0.75rem',
                              fontWeight: isActive(child.path) ? 700 : 400,
                              color: isActive(child.path) ? 'primary.light' : 'text.secondary',
                            }}>
                              {child.label}
                            </Typography>
                            {(child.badge ?? 0) > 0 && (
                              <Badge badgeContent={child.badge} color="error" sx={{ ml: 1 }}>
                                <Box />
                              </Badge>
                            )}
                          </Box>
                        } />
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
                  borderRadius: 1.5, py: 0.5, px: 1.5,
                  '&.Mui-selected': { backgroundColor: 'rgba(59,130,246,0.12)' },
                }}
              >
                <ListItemIcon sx={{
                  color: isActive(item.path) ? 'primary.main' : 'text.secondary',
                  minWidth: 28,
                }}>
                  <IconClone icon={item.icon} size={18} />
                </ListItemIcon>
                <ListItemText primary={
                  <Typography sx={{ fontSize: '0.78rem', fontWeight: isActive(item.path) ? 700 : 500 }}>
                    {item.label}
                  </Typography>
                } />
              </ListItemButton>
            )}
          </Box>
        ))}
      </List>

      {/* Footer */}
      <Box sx={{ mt: 'auto', p: 2, borderTop: '1px solid', borderColor: 'divider', background: 'rgba(0,0,0,0.2)' }}>
        <Typography sx={{ fontSize: '0.7rem', fontWeight: 700, color: 'text.secondary' }}>technostationery.com</Typography>
        <Typography sx={{ fontSize: '0.62rem', color: 'text.disabled' }}>TSM Platform v3.3.0</Typography>
      </Box>
    </Box>
  );
}

/** Clones an icon JSX element with a fixed fontSize — avoids React.cloneElement with sx prop type issues */
function IconClone({ icon, size }: { icon: JSX.Element; size: number }) {
  // MUI SvgIcon accepts `sx` and `fontSize` props directly
  return <icon.type {...icon.props} sx={{ fontSize: size }} />;
}

// Suppress unused import warnings for icons that are part of the navItems data
void Refresh; void Tooltip;

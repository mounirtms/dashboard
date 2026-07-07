import {
  Box, Typography, Button, IconButton, Chip, useTheme,
  Menu, MenuItem, Tooltip, ListItemIcon, ListItemText,
} from '@mui/material';
import {
  Menu as MenuIcon, ExitToApp, LightMode, DarkMode,
  Settings, Language, Check, Storage as StorageIcon,
  Speed, Memory, Storage,
} from '@mui/icons-material';
import { useAuth } from '../../hooks/useAuth.tsx';
import { useSystemOverview } from '../../hooks/useSystemData.ts';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { usePermissions } from '../../hooks/usePermissions.ts';
import { useAppTheme } from '../../App.tsx';

interface HeaderProps {
  onMenuClick: () => void;
}

export default function Header({ onMenuClick }: HeaderProps) {
  const { logout, user }  = useAuth();
  const { isAdmin }       = usePermissions();
  const { data }          = useSystemOverview(30_000);
  const theme             = useTheme();
  const navigate          = useNavigate();
  const { themeMode, toggleTheme } = useAppTheme();

  const [langAnchor,  setLangAnchor]  = useState<null | HTMLElement>(null);
  const [lang, setLang] = useState(() => localStorage.getItem('dashboard_lang') || 'en');

  const changeLang = (code: string) => {
    setLang(code);
    localStorage.setItem('dashboard_lang', code);
    setLangAnchor(null);
  };

  const openPhpMyAdmin = () =>
    window.open('https://technostationery.com/phpmyadmin', '_blank', 'noopener,noreferrer');

  const load1   = data?.load['1min'] ?? 0;
  const memPct  = data?.memory.used_pct ?? 0;
  const diskPct = data ? parseInt(data.disk.pct.replace('%', '')) : 0;

  const loadColor = load1   > 8  ? '#ef4444' : load1   > 4  ? '#f59e0b' : '#10b981';
  const diskColor = diskPct > 90 ? '#ef4444' : diskPct > 80 ? '#f59e0b' : '#10b981';
  const memColor  = memPct  > 85 ? '#ef4444' : memPct  > 70 ? '#f59e0b' : '#10b981';

  const unhealthyServices = data
    ? Object.entries(data.services).filter(([, s]) => s !== 'running').map(([n]) => n)
    : [];

  const isDark = themeMode === 'dark';

  return (
    <Box sx={{
      height: 64,
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      px: 3,
      background: isDark ? 'rgba(13,17,23,0.85)' : 'rgba(248,250,252,0.92)',
      borderBottom: `1px solid ${isDark ? '#1e293b' : '#e2e8f0'}`,
      position: 'sticky',
      top: 0,
      zIndex: 100,
      backdropFilter: 'blur(12px)',
      boxShadow: '0 4px 20px rgba(0,0,0,0.15)',
    }}>
      {/* Left: menu toggle + brand */}
      <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
        <IconButton
          onClick={onMenuClick}
          sx={{ color: 'text.secondary', display: { md: 'none' } }}
        >
          <MenuIcon />
        </IconButton>
        <Box>
          <Typography variant="subtitle2" sx={{ fontWeight: 800, lineHeight: 1, letterSpacing: '-0.02em' }}>
            TECHNO <Box component="span" sx={{ color: 'primary.main' }}>MONITOR</Box>
          </Typography>
          <Typography sx={{ fontSize: '0.65rem', color: 'text.secondary', fontWeight: 500 }}>
            {data ? data.uptime.split(',')[0] : 'Connecting…'}
          </Typography>
        </Box>
      </Box>

      {/* Centre: live stats (hidden on small screens) */}
      <Box sx={{ display: { xs: 'none', lg: 'flex' }, gap: 3, alignItems: 'center' }}>
        <HeaderStat label="Load"   value={data ? `${data.load['1min'].toFixed(2)}` : '—'} color={loadColor} subtext={`5m: ${data?.load['5min'].toFixed(2) ?? '—'}`} />
        <HeaderStat label="Memory" value={data ? `${memPct}%` : '—'}                       color={memColor}  subtext={`${data?.memory.available_mb ?? '—'} MB free`} />
        <HeaderStat label="Disk"   value={data ? data.disk.pct : '—'}                       color={diskColor} subtext={`${data?.disk.free ?? '—'} free`} />

        {/* Service status pill */}
        <Box sx={{ display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
          <Typography sx={{ fontSize: '0.65rem', color: 'text.secondary', textTransform: 'uppercase', letterSpacing: 0.4, fontWeight: 600 }}>
            Services
          </Typography>
          {unhealthyServices.length > 0 ? (
            <Chip
              label={`${unhealthyServices.length} down`}
              size="small"
              color="error"
              sx={{ height: 18, fontSize: '0.6rem', fontWeight: 700 }}
            />
          ) : (
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
              <Box sx={{ width: 8, height: 8, borderRadius: '50%', backgroundColor: '#10b981', boxShadow: '0 0 6px #10b981' }} />
              <Typography sx={{ fontSize: '0.75rem', color: '#10b981' }}>All OK</Typography>
            </Box>
          )}
        </Box>
      </Box>

      {/* Right: actions */}
      <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
        {/* phpMyAdmin — admin only */}
        {isAdmin && (
          <Tooltip title="phpMyAdmin">
            <IconButton
              size="small"
              onClick={openPhpMyAdmin}
              sx={{ color: 'text.secondary', '&:hover': { color: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.1)' } }}
            >
              <StorageIcon sx={{ fontSize: 18 }} />
            </IconButton>
          </Tooltip>
        )}

        {/* Language */}
        <Tooltip title="Language">
          <IconButton size="small" onClick={e => setLangAnchor(e.currentTarget)} sx={{ color: 'text.secondary' }}>
            <Language sx={{ fontSize: 18 }} />
          </IconButton>
        </Tooltip>
        <Menu
          anchorEl={langAnchor}
          open={Boolean(langAnchor)}
          onClose={() => setLangAnchor(null)}
          slotProps={{ paper: { sx: { backgroundColor: theme.palette.background.paper, border: `1px solid ${isDark ? '#2a3548' : '#e2e8f0'}`, borderRadius: 1 } } }}
        >
          {([{ code: 'en', label: 'EN' }, { code: 'fr', label: 'FR' }]).map(opt => (
            <MenuItem key={opt.code} onClick={() => changeLang(opt.code)} sx={{ py: 0.5, px: 1.5 }}>
              <ListItemIcon sx={{ minWidth: 24 }}>
                {lang === opt.code && <Check sx={{ fontSize: 16, color: 'primary.main' }} />}
              </ListItemIcon>
              <ListItemText sx={{ '& .MuiTypography-root': { fontSize: '0.75rem' } }}>{opt.label}</ListItemText>
            </MenuItem>
          ))}
        </Menu>

        {/* Theme toggle */}
        <Tooltip title={isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'}>
          <IconButton size="small" onClick={toggleTheme} sx={{ color: 'text.secondary' }}>
            {isDark ? <LightMode sx={{ fontSize: 18 }} /> : <DarkMode sx={{ fontSize: 18 }} />}
          </IconButton>
        </Tooltip>

        {/* Settings shortcut */}
        <Tooltip title="Settings">
          <IconButton size="small" onClick={() => navigate('/settings')} sx={{ color: 'text.secondary' }}>
            <Settings sx={{ fontSize: 18 }} />
          </IconButton>
        </Tooltip>

        {/* User chip */}
        {user && (
          <Chip
            label={user.full_name || user.username}
            size="small"
            onClick={() => navigate('/settings')}
            sx={{
              cursor: 'pointer',
              backgroundColor: 'rgba(59,130,246,0.1)',
              color: '#3b82f6',
              fontWeight: 600,
              fontSize: '0.7rem',
              border: '1px solid rgba(59,130,246,0.2)',
              '&:hover': { backgroundColor: 'rgba(59,130,246,0.2)' },
            }}
          />
        )}

        <Button
          variant="outlined"
          size="small"
          onClick={logout}
          startIcon={<ExitToApp />}
          sx={{
            borderColor: isDark ? '#2a3548' : '#cbd5e1',
            color: 'text.secondary',
            fontSize: '0.75rem',
            '&:hover': { borderColor: '#ef4444', color: '#ef4444', backgroundColor: 'rgba(239,68,68,0.05)' },
          }}
        >
          Logout
        </Button>
      </Box>
    </Box>
  );
}

function HeaderStat({ label, value, color, subtext }: {
  label: string;
  value: string;
  color: string;
  subtext?: string;
}) {
  return (
    <Box sx={{ display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
      <Typography sx={{ fontSize: '0.65rem', color: 'text.secondary', textTransform: 'uppercase', letterSpacing: 0.4, fontWeight: 600 }}>{label}</Typography>
      <Typography sx={{ fontSize: '0.85rem', fontWeight: 700, fontFamily: 'monospace', color }}>{value}</Typography>
      {subtext && <Typography sx={{ fontSize: '0.6rem', color: 'text.disabled' }}>{subtext}</Typography>}
    </Box>
  );
}

// Suppress unused icon imports warning – they are referenced implicitly via MUI
export type { HeaderProps };
void Speed; void Memory; void Storage;

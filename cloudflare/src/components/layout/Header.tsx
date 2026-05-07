import { Box, Typography, Button, IconButton, useTheme } from '@mui/material';
import { Menu as MenuIcon, ExitToApp, CloudDone, Warning, Speed, Memory, Storage } from '@mui/icons-material';
import { useAuth } from '../../hooks/useAuth.tsx';
import { useSystemOverview } from '../../hooks/useSystemData';

interface HeaderProps {
  onMenuClick: () => void;
}

export default function Header({ onMenuClick }: HeaderProps) {
  const { logout } = useAuth();
  const { data } = useSystemOverview(15000); // 15s refresh
  const theme = useTheme();

  const loadPct = data ? data.load['1min'] : 0;
  const memPct = data ? data.memory.used_pct : 0;
  const statusColor = loadPct > 8 ? '#ef4444' : loadPct > 4 ? '#f59e0b' : '#10b981';

  return (
    <Box sx={{
      height: 64,
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      px: 3,
      background: 'rgba(13, 17, 23, 0.8)',
      borderBottom: '1px solid #1e293b',
      position: 'sticky',
      top: 0,
      zIndex: 100,
      backdropFilter: 'blur(12px)',
      boxShadow: '0 4px 20px rgba(0, 0, 0, 0.2)'
    }}>
      <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
        <IconButton onClick={onMenuClick} sx={{ color: 'text.secondary', display: { md: 'none' } }}>
          <MenuIcon />
        </IconButton>
        <Box>
          <Typography variant="subtitle2" sx={{ fontWeight: 800, lineHeight: 1, letterSpacing: '-0.02em' }}>
            TECHNO <Box component="span" sx={{ color: 'primary.main' }}>MONITOR</Box>
          </Typography>
          <Typography sx={{ fontSize: '0.65rem', color: '#94a3b8', fontWeight: 500 }}>
            {data ? `Server Status: ${data.uptime.split(',')[0]}` : 'Connecting to Server...'}
          </Typography>
        </Box>
      </Box>

      <Box sx={{ display: { xs: 'none', md: 'flex' }, gap: 4, alignItems: 'center' }}>
        <HeaderStat label="CPU Load" value={data ? data.load['1min'] : '-'} color="#3b82f6" />
        <HeaderStat label="Memory" value={data ? `${data.memory.used_pct}%` : '-'} color="#10b981" />
        <HeaderStat label="Disk" value={data ? data.disk.pct : '-'} color="#f59e0b" />
        <Box sx={{ display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
          <Typography sx={{ fontSize: '0.65rem', color: '#94a3b8', textTransform: 'uppercase', letterSpacing: 0.4, fontWeight: 600 }}>Status</Typography>
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
            <Box sx={{ width: 8, height: 8, borderRadius: '50%', backgroundColor: statusColor, boxShadow: `0 0 6px ${statusColor}` }} />
            <Typography sx={{ fontSize: '0.75rem', color: '#94a3b8' }}>
              {loadPct > 8 ? 'High Load' : 'Stable'}
            </Typography>
          </Box>
        </Box>
      </Box>

      <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
        <Button 
          variant="outlined" 
          size="small" 
          onClick={logout}
          startIcon={<ExitToApp />}
          sx={{ 
            borderColor: '#2a3548', 
            color: '#94a3b8',
            fontSize: '0.75rem',
            '&:hover': { borderColor: '#ef4444', color: '#ef4444', backgroundColor: 'rgba(239, 68, 68, 0.05)' }
          }}
        >
          Logout
        </Button>
      </Box>
    </Box>
  );
}

function HeaderStat({ label, value, color }: { label: string, value: any, color: string }) {
  return (
    <Box sx={{ display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
      <Typography sx={{ fontSize: '0.65rem', color: '#94a3b8', textTransform: 'uppercase', letterSpacing: 0.4, fontWeight: 600 }}>{label}</Typography>
      <Typography sx={{ fontSize: '0.85rem', fontWeight: 700, fontFamily: 'monospace', color }}>{value}</Typography>
    </Box>
  );
}

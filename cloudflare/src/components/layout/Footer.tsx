import { Box, Typography, Divider, Tooltip } from '@mui/material';
import { useState, useEffect } from 'react';
import { useSystemOverview } from '../../hooks/useSystemData';
import { Speed, Memory, Storage } from '@mui/icons-material';

export default function Footer() {
  const [time, setTime] = useState(new Date());
  const { data } = useSystemOverview(30000); // 30s refresh for global stats

  useEffect(() => {
    const timer = setInterval(() => setTime(new Date()), 1000);
    return () => clearInterval(timer);
  }, []);

  const getHealthColor = (val: number, warn: number, crit: number) => 
    val > crit ? '#ef4444' : val > warn ? '#f59e0b' : '#10b981';

  const loadVal = data?.load['1min'] || 0;
  const memVal = data?.memory.used_pct || 0;
  const diskPct = data ? parseInt(data.disk.pct) : 0;

  return (
    <Box sx={{
      height: 40,
      mt: 'auto',
      background: 'rgba(10, 14, 24, 0.7)',
      borderTop: '1px solid #1e293b',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      px: 3,
      zIndex: 90,
      backdropFilter: 'blur(12px)',
    }}>
      <Box sx={{ display: 'flex', gap: 2.5, alignItems: 'center' }}>
        <Typography sx={{ fontSize: '0.7rem', color: '#64748b', fontWeight: 600 }}>
          © 2026 TECHNO MONITOR
        </Typography>
        <Divider orientation="vertical" flexItem sx={{ height: 16, my: 'auto', borderColor: 'rgba(255,255,255,0.1)' }} />
        
        <Box sx={{ display: 'flex', gap: 3, alignItems: 'center' }}>
          <FooterStat 
            icon={<Speed sx={{ fontSize: 14 }} />} 
            label="Load" 
            value={loadVal} 
            color={getHealthColor(loadVal, 4, 8)} 
          />
          <FooterStat 
            icon={<Memory sx={{ fontSize: 14 }} />} 
            label="RAM" 
            value={`${memVal}%`} 
            color={getHealthColor(memVal, 75, 90)} 
          />
          <FooterStat 
            icon={<Storage sx={{ fontSize: 14 }} />} 
            label="Disk" 
            value={`${diskPct}%`} 
            color={getHealthColor(diskPct, 80, 95)} 
          />
        </Box>
      </Box>

      <Box sx={{ display: 'flex', gap: 2, alignItems: 'center' }}>
        <Typography sx={{ fontSize: '0.65rem', color: '#475569', fontFamily: 'monospace', letterSpacing: 0.5 }}>
          LATENCY: 42ms
        </Typography>
        <Typography sx={{ fontSize: '0.75rem', color: 'primary.light', fontFamily: 'monospace', fontWeight: 700, minWidth: 80, textAlign: 'right' }}>
          {time.toLocaleTimeString()}
        </Typography>
      </Box>
    </Box>
  );
}

function FooterStat({ icon, label, value, color }: { icon: any, label: string, value: any, color: string }) {
  return (
    <Tooltip title={`${label}: ${value}`} arrow>
      <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.8 }}>
        <Box sx={{ color: '#64748b', display: 'flex' }}>{icon}</Box>
        <Typography sx={{ fontSize: '0.72rem', fontWeight: 700, color: color }}>
          {value}
        </Typography>
      </Box>
    </Tooltip>
  );
}

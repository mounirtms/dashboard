import { Box, Typography, Divider, Tooltip, Link } from '@mui/material';
import { useState, useEffect, useRef } from 'react';
import { useSystemOverview } from '../../hooks/useSystemData';
import { Speed, Memory, Storage } from '@mui/icons-material';
import mounirSignature from '../../assets/mounir-signature.svg';
import mounirIcon from '../../assets/mounir-icon.svg';

const APP_VERSION = '3.3.0';

export default function Footer() {
  const [time, setTime] = useState(new Date());
  const { data } = useSystemOverview(30000);
  const [latency, setLatency] = useState<number | null>(null);
  const pingRef = useRef(0);

  useEffect(() => {
    const timer = setInterval(() => setTime(new Date()), 1000);
    return () => clearInterval(timer);
  }, []);

  // Measure actual API latency
  useEffect(() => {
    const start = performance.now();
    fetch('/api/status.php', { method: 'HEAD', cache: 'no-store' })
      .then(() => {
        setLatency(Math.round(performance.now() - start));
      })
      .catch(() => {});
    const interval = setInterval(() => {
      const s = performance.now();
      fetch('/api/status.php', { method: 'HEAD', cache: 'no-store' })
        .then(() => setLatency(Math.round(performance.now() - s)))
        .catch(() => {});
    }, 30000);
    return () => clearInterval(interval);
  }, []);

  const getHealthColor = (val: number, warn: number, crit: number) => 
    val > crit ? '#ef4444' : val > warn ? '#f59e0b' : '#10b981';

  const loadVal = data?.load['1min'] || 0;
  const memVal = data?.memory.used_pct || 0;
  const diskPct = data ? parseInt(data.disk.pct) : 0;

  return (
    <Box sx={{
      height: 26,
      mt: 'auto',
      background: 'rgba(10, 14, 24, 0.95)',
      borderTop: '1px solid #1e293b',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      px: 2,
      zIndex: 100,
      position: 'sticky',
      bottom: 0,
    }}>
      <Box sx={{ display: 'flex', gap: 1.5, alignItems: 'center' }}>
        <Typography sx={{ fontSize: '0.62rem', color: '#64748b', fontWeight: 600 }}>
          © {new Date().getFullYear()} TECHNO
        </Typography>
        <Divider orientation="vertical" flexItem sx={{ height: 12, my: 'auto', borderColor: 'rgba(255,255,255,0.08)' }} />
        
        <Link href="https://technostationery.com" target="_blank" rel="noopener" sx={{ fontSize: '0.62rem', color: '#94a3b8', textDecoration: 'none', fontWeight: 600, '&:hover': { color: '#3b82f6' } }}>
          Techno Stationery
        </Link>

        <Tooltip title="Lead Developer & Editor — Mounir Abderrahmani" placement="top">
          <Link href="https://mounir1.github.io" target="_blank" rel="noopener" sx={{ display: 'flex', alignItems: 'center', gap: 0.5, ml: 0.5, textDecoration: 'none' }}>
            <Box component="img" src={mounirIcon} alt="MA" sx={{ height: 14, width: 14, opacity: 0.7, transition: 'opacity 0.2s', '&:hover': { opacity: 1 } }} />
            <Box component="img" src={mounirSignature} alt="Mounir Abderrahmani" sx={{ height: 14, width: 'auto', opacity: 0.6, transition: 'opacity 0.2s', '&:hover': { opacity: 1 } }} />
          </Link>
        </Tooltip>
      </Box>

      <Box sx={{ display: 'flex', gap: 1.5, alignItems: 'center' }}>
        <Link href="/docs" sx={{ fontSize: '0.6rem', color: '#64748b', textDecoration: 'none', '&:hover': { color: '#3b82f6' } }}>
          Docs
        </Link>
        <Divider orientation="vertical" flexItem sx={{ height: 12, my: 'auto', borderColor: 'rgba(255,255,255,0.08)' }} />
        
        <Box sx={{ display: 'flex', gap: 1.5, alignItems: 'center' }}>
          <Typography sx={{ fontSize: '0.58rem', fontWeight: 700, color: getHealthColor(loadVal, 4, 8) }}>
            Load: {loadVal}
          </Typography>
          <Typography sx={{ fontSize: '0.58rem', fontWeight: 700, color: getHealthColor(memVal, 75, 90) }}>
            RAM: {memVal}%
          </Typography>
        </Box>

        <Divider orientation="vertical" flexItem sx={{ height: 12, my: 'auto', borderColor: 'rgba(255,255,255,0.08)' }} />

        {latency !== null && (
          <Typography sx={{ fontSize: '0.58rem', color: '#475569', fontFamily: 'monospace', letterSpacing: 0.5 }}>
            {latency}ms
          </Typography>
        )}

        <Typography sx={{ fontSize: '0.65rem', color: 'primary.light', fontFamily: 'monospace', fontWeight: 700, minWidth: 60, textAlign: 'right' }}>
          {time.toLocaleTimeString()}
        </Typography>

        <Typography sx={{ fontSize: '0.58rem', color: '#64748b', fontFamily: 'monospace' }}>
          v{APP_VERSION}
        </Typography>
      </Box>
    </Box>
  );
}

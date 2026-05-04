import { Typography, Box, Chip } from '@mui/material';
import { useAuth } from '../../hooks/useAuth';
import { Person as PersonIcon } from '@mui/icons-material';

export default function TopBar() {
  const { user } = useAuth();

  return (
    <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', width: '100%', py: 0.5 }}>
      <Typography variant="h6" sx={{ fontWeight: 700, fontSize: '0.95rem', letterSpacing: '-0.02em' }}>
        Cloudflare Analytics
      </Typography>
      <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5 }}>
        <Chip
          icon={<PersonIcon sx={{ fontSize: 16, color: '#60a5fa !important' }} />}
          label={user?.full_name || user?.username || 'User'}
          size="small"
          sx={{
            backgroundColor: 'rgba(59, 130, 246, 0.12)',
            borderColor: 'rgba(59, 130, 246, 0.25)',
            color: '#cbd5e1',
            fontWeight: 500,
            fontSize: '0.78rem',
            borderWidth: 1,
            borderStyle: 'solid',
          }}
        />
      </Box>
    </Box>
  );
}

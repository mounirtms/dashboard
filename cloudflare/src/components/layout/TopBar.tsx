import { Typography, Box, Chip, useTheme } from '@mui/material';
import { useAuth } from '../../hooks/useAuth';
import { Person as PersonIcon } from '@mui/icons-material';

export default function TopBar() {
  const { user } = useAuth();
  const theme = useTheme();

  return (
    <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', width: '100%', py: 0.5 }}>
      <Typography variant="h6" sx={{ fontWeight: 700, fontSize: '0.95rem', letterSpacing: '-0.02em' }}>
        Cloudflare Analytics
      </Typography>
      <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5 }}>
        <Chip
          icon={<PersonIcon sx={{ fontSize: 16, color: `${theme.palette.primary.light} !important` }} />}
          label={user?.full_name || user?.username || 'User'}
          size="small"
          sx={{
            backgroundColor: `${theme.palette.primary.main}1f`,
            borderColor: `${theme.palette.primary.main}40`,
            color: theme.palette.text.secondary,
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

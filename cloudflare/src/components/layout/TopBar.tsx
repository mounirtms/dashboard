import { Typography, Box } from '@mui/material';
import { useAuth } from '../../hooks/useAuth';

export default function TopBar() {
  const { user } = useAuth();

  return (
    <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', width: '100%' }}>
      <Typography variant="h6" sx={{ fontWeight: 700 }}>
        Cloudflare Analytics
      </Typography>
      <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
        <Typography variant="body2" color="textSecondary">
          {user?.full_name || user?.username}
        </Typography>
      </Box>
    </Box>
  );
}

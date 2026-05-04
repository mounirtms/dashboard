import { Box, CircularProgress, Typography } from '@mui/material';

export default function LoadingState({ message = 'Loading...' }: { message?: string }) {
  return (
    <Box sx={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', py: 10, gap: 2.5 }}>
      <CircularProgress size={36} thickness={4} sx={{ color: '#3b82f6' }} />
      <Typography variant="body2" sx={{ color: '#94a3b8', fontWeight: 500, fontSize: '0.88rem' }}>
        {message}
      </Typography>
    </Box>
  );
}

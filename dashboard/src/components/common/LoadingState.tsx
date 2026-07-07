import { Box, CircularProgress, Typography, useTheme } from '@mui/material';

export default function LoadingState({ message = 'Loading...' }: { message?: string }) {
  const theme = useTheme();

  return (
    <Box sx={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', py: 10, gap: 2.5 }}>
      <CircularProgress size={36} thickness={4} sx={{ color: theme.palette.primary.main }} />
      <Typography variant="body2" sx={{ color: theme.palette.text.secondary, fontWeight: 500, fontSize: '0.88rem' }}>
        {message}
      </Typography>
    </Box>
  );
}

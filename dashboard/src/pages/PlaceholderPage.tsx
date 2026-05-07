import { Box, Typography } from '@mui/material';

export default function PlaceholderPage({ title }: { title: string }) {
  return (
    <Box sx={{ p: 4, textAlign: 'center', mt: 10 }}>
      <Typography variant="h4" sx={{ fontWeight: 800, mb: 2 }}>{title}</Typography>
      <Typography variant="body1" sx={{ color: 'text.secondary' }}>
        This module is currently being integrated from the previous ETL project.
      </Typography>
    </Box>
  );
}

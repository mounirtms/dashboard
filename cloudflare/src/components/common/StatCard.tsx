import { Card, CardContent, Typography, Box, useTheme } from '@mui/material';

interface StatCardProps {
  label: string;
  value: string | number;
  color?: 'primary' | 'success' | 'warning' | 'error' | 'info' | 'default';
  subvalue?: string;
  icon?: React.ReactNode;
}

export default function StatCard({ label, value, color = 'default', subvalue, icon }: StatCardProps) {
  const theme = useTheme();
  const isDefault = color === 'default';
  const mainColor = isDefault ? theme.palette.text.primary : theme.palette[color].main;
  const darkColor = isDefault ? theme.palette.text.secondary : (theme.palette[color].dark || theme.palette[color].main);
  const secondaryColor = theme.palette.text.secondary;

  return (
    <Card
      sx={{
        height: '100%',
        position: 'relative',
        overflow: 'hidden',
        '&::before': {
          content: '""',
          position: 'absolute',
          top: 0,
          left: 0,
          right: 0,
          height: 3,
          background: `linear-gradient(135deg, ${darkColor}, ${mainColor})`,
        },
      }}
    >
      <CardContent sx={{ pt: 2.5 }}>
        <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', mb: 1 }}>
          <Typography variant="caption" sx={{ color: secondaryColor, fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.05em', fontSize: '0.7rem' }}>
            {label}
          </Typography>
          {icon && (
            <Box sx={{ color: mainColor, opacity: 0.7 }}>
              {icon}
            </Box>
          )}
        </Box>
        <Typography variant="h4" sx={{ color: mainColor, fontWeight: 800, letterSpacing: '-0.03em', fontSize: '1.6rem', lineHeight: 1.2 }}>
          {value}
        </Typography>
        {subvalue && (
          <Typography variant="caption" sx={{ color: secondaryColor, display: 'block', mt: 0.5, fontSize: '0.72rem' }}>
            {subvalue}
          </Typography>
        )}
      </CardContent>
    </Card>
  );
}

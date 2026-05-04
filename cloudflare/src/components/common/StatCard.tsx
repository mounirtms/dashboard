import { Card, CardContent, Typography, Box } from '@mui/material';

interface StatCardProps {
  label: string;
  value: string | number;
  color?: 'primary' | 'success' | 'warning' | 'error' | 'info' | 'default';
  subvalue?: string;
  icon?: React.ReactNode;
}

const colorMap: Record<string, { main: string; glow: string; gradient: string }> = {
  primary: { main: '#3b82f6', glow: 'rgba(59, 130, 246, 0.15)', gradient: 'linear-gradient(135deg, #2563eb, #3b82f6)' },
  success: { main: '#22c55e', glow: 'rgba(34, 197, 94, 0.15)', gradient: 'linear-gradient(135deg, #16a34a, #22c55e)' },
  warning: { main: '#eab308', glow: 'rgba(234, 179, 8, 0.15)', gradient: 'linear-gradient(135deg, #ca8a04, #eab308)' },
  error: { main: '#ef4444', glow: 'rgba(239, 68, 68, 0.15)', gradient: 'linear-gradient(135deg, #dc2626, #ef4444)' },
  info: { main: '#06b6d4', glow: 'rgba(6, 182, 212, 0.15)', gradient: 'linear-gradient(135deg, #0891b2, #06b6d4)' },
  default: { main: '#cbd5e1', glow: 'rgba(203, 213, 225, 0.1)', gradient: 'linear-gradient(135deg, #94a3b8, #cbd5e1)' },
};

export default function StatCard({ label, value, color = 'default', subvalue, icon }: StatCardProps) {
  const colors = colorMap[color];

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
          background: colors.gradient,
        },
      }}
    >
      <CardContent sx={{ pt: 2.5 }}>
        <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', mb: 1 }}>
          <Typography variant="caption" sx={{ color: '#94a3b8', fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.05em', fontSize: '0.7rem' }}>
            {label}
          </Typography>
          {icon && (
            <Box sx={{ color: colors.main, opacity: 0.7 }}>
              {icon}
            </Box>
          )}
        </Box>
        <Typography variant="h4" sx={{ color: colors.main, fontWeight: 800, letterSpacing: '-0.03em', fontSize: '1.6rem', lineHeight: 1.2 }}>
          {value}
        </Typography>
        {subvalue && (
          <Typography variant="caption" sx={{ color: '#94a3b8', display: 'block', mt: 0.5, fontSize: '0.72rem' }}>
            {subvalue}
          </Typography>
        )}
      </CardContent>
    </Card>
  );
}

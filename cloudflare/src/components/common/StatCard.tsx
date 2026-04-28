import { Card, CardContent, Typography } from '@mui/material';

interface StatCardProps {
  label: string;
  value: string | number;
  color?: 'primary' | 'success' | 'warning' | 'error' | 'info' | 'default';
  subvalue?: string;
}

const colorMap: Record<string, string> = {
  primary: '#3b82f6',
  success: '#22c55e',
  warning: '#eab308',
  error: '#ef4444',
  info: '#06b6d4',
  default: '#e2e8f0',
};

export default function StatCard({ label, value, color = 'default', subvalue }: StatCardProps) {
  return (
    <Card>
      <CardContent>
        <Typography variant="caption" color="textSecondary" sx={{ display: 'block', mb: 0.5 }}>
          {label}
        </Typography>
        <Typography variant="h6" sx={{ color: colorMap[color], fontWeight: 700 }}>
          {value}
        </Typography>
        {subvalue && (
          <Typography variant="caption" color="textSecondary" sx={{ display: 'block', mt: 0.5 }}>
            {subvalue}
          </Typography>
        )}
      </CardContent>
    </Card>
  );
}

import { Chip } from '@mui/material';

interface StatusBadgeProps {
  label: string;
  color: 'success' | 'warning' | 'error' | 'default' | 'info';
}

const colorMap: Record<string, { bg: string; border: string; text: string }> = {
  success: { bg: 'rgba(34, 197, 94, 0.12)', border: 'rgba(34, 197, 94, 0.3)', text: '#22c55e' },
  warning: { bg: 'rgba(234, 179, 8, 0.12)', border: 'rgba(234, 179, 8, 0.3)', text: '#eab308' },
  error: { bg: 'rgba(239, 68, 68, 0.12)', border: 'rgba(239, 68, 68, 0.3)', text: '#ef4444' },
  info: { bg: 'rgba(6, 182, 212, 0.12)', border: 'rgba(6, 182, 212, 0.3)', text: '#06b6d4' },
  default: { bg: 'rgba(148, 163, 184, 0.1)', border: 'rgba(148, 163, 184, 0.2)', text: '#94a3b8' },
};

export default function StatusBadge({ label, color }: StatusBadgeProps) {
  const colors = colorMap[color] || colorMap.default;

  return (
    <Chip
      label={label}
      size="small"
      sx={{
        fontWeight: 600,
        textTransform: 'capitalize',
        fontSize: '0.72rem',
        backgroundColor: colors.bg,
        borderColor: colors.border,
        color: colors.text,
        borderWidth: 1,
        borderStyle: 'solid',
      }}
    />
  );
}

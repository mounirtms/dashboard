import { Chip } from '@mui/material';

interface StatusBadgeProps {
  label: string;
  color: 'success' | 'warning' | 'error' | 'default' | 'info';
}

export default function StatusBadge({ label, color }: StatusBadgeProps) {
  return <Chip label={label} color={color} size="small" sx={{ fontWeight: 600, textTransform: 'capitalize' }} />;
}

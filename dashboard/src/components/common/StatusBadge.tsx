import React from 'react';
import { Chip, useTheme } from '@mui/material';

interface StatusBadgeProps {
  label: string;
  color: 'success' | 'warning' | 'error' | 'default' | 'info';
  icon?: React.ReactElement;
}

export default function StatusBadge({ label, color, icon }: StatusBadgeProps) {
  const theme = useTheme();
  const isDefault = color === 'default';
  const paletteColor = isDefault ? theme.palette.text.secondary : theme.palette[color].main;
  const bgColor = isDefault ? `${theme.palette.text.secondary}1f` : `${theme.palette[color].main}1f`;
  const borderColor = isDefault ? `${theme.palette.text.secondary}4d` : `${theme.palette[color].main}4d`;

  return (
    <Chip
      label={label}
      size="small"
      icon={icon}
      sx={{
        fontWeight: 600,
        textTransform: 'capitalize',
        fontSize: '0.72rem',
        backgroundColor: bgColor,
        borderColor: borderColor,
        color: paletteColor,
        borderWidth: 1,
        borderStyle: 'solid',
        '& .MuiChip-icon': {
          color: 'inherit',
          fontSize: 14,
          ml: 0.5
        }
      }}
    />
  );
}

import { Box, Typography, Paper, IconButton, Tooltip, Button, Checkbox, FormControlLabel } from '@mui/material';
import { ContentCopy, Clear, ArrowDownward } from '@mui/icons-material';
import { useEffect, useRef, useState, useMemo } from 'react';

// Strip ANSI escape sequences: \x1b[...m, \x1b[...H, etc.
function stripAnsi(text: string): string {
  return text.replace(/\x1b\[[0-9;]*[a-zA-Z]/g, '')
    .replace(/\x1b\([B0-9]/g, '')
    .replace(/\r/g, '');
}

// Parse log level from a line and return color
function getLogLevelColor(line: string): string {
  const upper = line.toUpperCase();
  if (/(ERROR|CRITICAL|FATAL|EMERG|ALERT|FAIL|DENIED)/.test(upper)) return '#f87171';
  if (/(WARN|WARNING)/.test(upper)) return '#fbbf24';
  if (/(INFO|NOTICE)/.test(upper)) return '#60a5fa';
  if (/(DEBUG|TRACE)/.test(upper)) return '#94a3b8';
  if (/(SUCCESS|OK|COMPLETE|DONE)/.test(upper)) return '#4ade80';
  return '#d1d5db';
}

interface ConsoleOutputProps {
  text?: string;
  lines?: string[];
  showHeader?: boolean;
  title?: string;
  onClear?: () => void;
  autoScroll?: boolean;
  maxLines?: number;
}

export default function ConsoleOutput({
  text,
  lines,
  showHeader = true,
  title = 'CONSOLE OUTPUT',
  onClear,
  autoScroll = false,
  maxLines = 2000,
}: ConsoleOutputProps) {
  const scrollRef = useRef<HTMLDivElement>(null);
  const [autoScrollEnabled, setAutoScrollEnabled] = useState(autoScroll);
  const [copied, setCopied] = useState(false);

  const allLines = useMemo(() => {
    const source = text !== undefined ? text.split('\n') : lines || [];
    return source.slice(-maxLines);
  }, [text, lines, maxLines]);

  // Auto-scroll on new content
  useEffect(() => {
    if (autoScrollEnabled && scrollRef.current) {
      scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
    }
  }, [allLines, autoScrollEnabled]);

  // Detect if user scrolled up (disable auto-scroll)
  const handleScroll = () => {
    if (!scrollRef.current) return;
    const { scrollTop, scrollHeight, clientHeight } = scrollRef.current;
    const isAtBottom = scrollHeight - scrollTop - clientHeight < 50;
    if (!isAtBottom && autoScrollEnabled) {
      setAutoScrollEnabled(false);
    } else if (isAtBottom && !autoScrollEnabled) {
      setAutoScrollEnabled(true);
    }
  };

  const handleCopy = async () => {
    const content = allLines.join('\n');
    await navigator.clipboard.writeText(content);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const fullText = allLines.join('\n');

  return (
    <Paper
      sx={{
        backgroundColor: '#0d1117',
        border: '1px solid #30363d',
        borderRadius: 1,
        overflow: 'hidden',
      }}
    >
      {showHeader && (
        <Box
          sx={{
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            px: 2,
            py: 1,
            borderBottom: '1px solid #30363d',
            backgroundColor: '#161b22',
          }}
        >
          <Typography variant="caption" sx={{ fontWeight: 700, color: '#8b949e', letterSpacing: '0.05em' }}>
            {title}
          </Typography>
          <Box sx={{ display: 'flex', gap: 0.5, alignItems: 'center' }}>
            <FormControlLabel
              control={
                <Checkbox
                  size="small"
                  checked={autoScrollEnabled}
                  onChange={(e) => setAutoScrollEnabled(e.target.checked)}
                  sx={{ color: '#8b949e', '&.Mui-checked': { color: '#58a6ff' } }}
                />
              }
              label={<ArrowDownward sx={{ fontSize: 14, color: autoScrollEnabled ? '#58a6ff' : '#8b949e' }} />}
              sx={{ mr: 1, '& .MuiFormControlLabel-label': { mr: 0 } }}
            />
            <Tooltip title="Copy to clipboard">
              <IconButton size="small" onClick={handleCopy} sx={{ color: '#8b949e' }}>
                <ContentCopy sx={{ fontSize: 16 }} />
              </IconButton>
            </Tooltip>
            {onClear && (
              <Tooltip title="Clear">
                <IconButton size="small" onClick={onClear} sx={{ color: '#8b949e' }}>
                  <Clear sx={{ fontSize: 16 }} />
                </IconButton>
              </Tooltip>
            )}
          </Box>
        </Box>
      )}

      <Box
        ref={scrollRef}
        onScroll={handleScroll}
        sx={{
          p: 1.5,
          fontFamily: '"JetBrains Mono", "Fira Code", "Cascadia Code", monospace',
          fontSize: '0.75rem',
          lineHeight: 1.6,
          maxHeight: 400,
          overflowY: 'auto',
          whiteSpace: 'pre-wrap',
          wordBreak: 'break-word',
          '&::-webkit-scrollbar': { width: '6px' },
          '&::-webkit-scrollbar-track': { backgroundColor: '#0d1117' },
          '&::-webkit-scrollbar-thumb': { backgroundColor: '#30363d', borderRadius: '3px' },
        }}
      >
        {allLines.length === 0 ? (
          <Typography sx={{ color: '#484f58', fontStyle: 'italic' }}>No output yet...</Typography>
        ) : (
          allLines.map((line, i) => {
            const cleanLine = stripAnsi(line);
            const color = getLogLevelColor(cleanLine);
            return (
              <Box key={i} component="div" sx={{ color, minHeight: '1.2em' }}>
                {cleanLine || '\u00A0'}
              </Box>
            );
          })
        )}
      </Box>

      {copied && (
        <Box
          sx={{
            position: 'fixed',
            bottom: 24,
            left: '50%',
            transform: 'translateX(-50%)',
            backgroundColor: '#238636',
            color: '#fff',
            px: 2,
            py: 0.75,
            borderRadius: 1,
            fontSize: '0.8rem',
            fontWeight: 600,
            zIndex: 9999,
          }}
        >
          Copied to clipboard
        </Box>
      )}
    </Paper>
  );
}

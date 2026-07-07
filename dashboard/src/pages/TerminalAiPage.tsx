import {
  Box, Typography, Card, TextField, IconButton, Paper, Avatar,
  CircularProgress, Button, Divider,
} from '@mui/material';
import { Send, SmartToy, Person, AutoAwesome, Assessment, Telegram } from '@mui/icons-material';
import { useState, useRef, useEffect } from 'react';
import apiClient from '../api/client';

// ── Types ────────────────────────────────────────────────────────────────────

interface Message {
  role: 'user' | 'assistant';
  content: string;
}

// ── Component ────────────────────────────────────────────────────────────────

export default function TerminalAiPage() {
  const [messages, setMessages] = useState<Message[]>([
    { role: 'assistant', content: 'Hello! I am the Techno Monitor AI. How can I help you manage the infrastructure today?' }
  ]);
  const [input, setInput]               = useState('');
  const [loading, setLoading]           = useState(false);
  const [reportLoading, setReportLoading] = useState(false);
  const scrollRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    scrollRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const handleSend = async () => {
    if (!input.trim() || loading) return;

    const userMsg: Message = { role: 'user', content: input };
    setMessages(prev => [...prev, userMsg]);
    setInput('');
    setLoading(true);

    try {
      const { data } = await apiClient.post('/api/ai.php?action=chat', {
        messages: [...messages, userMsg],
      });
      if (data.success) {
        setMessages(prev => [...prev, { role: 'assistant', content: data.response as string }]);
      }
    } catch (e: unknown) {
      setMessages(prev => [
        ...prev,
        { role: 'assistant', content: 'Sorry, I encountered an error connecting to the AI engine.' },
      ]);
    } finally {
      setLoading(false);
    }
  };

  const handleGenerateReport = async () => {
    setReportLoading(true);
    try {
      const { data } = await apiClient.get('/api/ai.php?action=report');
      if (data.success) {
        setMessages(prev => [
          ...prev,
          { role: 'assistant', content: `**GENERATED SYSTEM INSIGHT:**\n\n${data.response as string}` },
        ]);
      }
    } catch (e: unknown) {
      setMessages(prev => [...prev, { role: 'assistant', content: 'Failed to generate report. Please try again.' }]);
    } finally {
      setReportLoading(false);
    }
  };

  const handleTelegramReport = async () => {
    try {
      await apiClient.get('/api/ai.php?action=telegram_report');
      alert('AI Insight Report has been dispatched to Telegram!');
    } catch (e: unknown) {
      alert('Failed to send Telegram report.');
    }
  };

  /** Send on Enter (but allow Shift+Enter for new lines) */
  const handleKeyDown = (e: React.KeyboardEvent<HTMLDivElement>) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSend();
    }
  };

  return (
    <Box sx={{ height: 'calc(100vh - 140px)', display: 'flex', flexDirection: 'column' }}>
      {/* Header */}
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 900, letterSpacing: '-0.04em', mb: 0.5, display: 'flex', alignItems: 'center', gap: 1.5 }}>
            <AutoAwesome sx={{ color: 'primary.main' }} /> Terminal AI
          </Typography>
          <Typography variant="body2" color="text.secondary">
            Natural language interface for infrastructure management and insights.
          </Typography>
        </Box>
        <Box sx={{ display: 'flex', gap: 2 }}>
          <Button
            variant="outlined"
            startIcon={reportLoading ? <CircularProgress size={16} /> : <Assessment />}
            onClick={handleGenerateReport}
            disabled={reportLoading}
          >
            Analyze System
          </Button>
          <Button
            variant="contained"
            startIcon={<Telegram />}
            onClick={handleTelegramReport}
            color="info"
          >
            Dispatch to Staff
          </Button>
        </Box>
      </Box>

      {/* Chat card */}
      <Card sx={{ flexGrow: 1, display: 'flex', flexDirection: 'column', overflow: 'hidden', borderStyle: 'dashed' }}>
        {/* Message list */}
        <Box sx={{ flexGrow: 1, overflowY: 'auto', p: 3, display: 'flex', flexDirection: 'column', gap: 2 }}>
          {messages.map((msg, i) => (
            <Box
              key={i}
              sx={{
                display: 'flex',
                gap: 2,
                flexDirection: msg.role === 'user' ? 'row-reverse' : 'row',
                alignSelf: msg.role === 'user' ? 'flex-end' : 'flex-start',
                maxWidth: '80%',
              }}
            >
              <Avatar sx={{ bgcolor: msg.role === 'user' ? 'primary.dark' : 'secondary.dark', width: 32, height: 32 }}>
                {msg.role === 'user'
                  ? <Person sx={{ fontSize: 18 }} />
                  : <SmartToy sx={{ fontSize: 18 }} />}
              </Avatar>
              <Paper sx={{
                p: 2,
                borderRadius: 2,
                bgcolor: msg.role === 'user' ? 'primary.main' : 'background.default',
                border: '1px solid',
                borderColor: msg.role === 'user' ? 'primary.dark' : 'divider',
              }}>
                <Typography sx={{
                  fontSize: '0.85rem',
                  whiteSpace: 'pre-wrap',
                  color: msg.role === 'user' ? 'white' : 'text.primary',
                }}>
                  {msg.content}
                </Typography>
              </Paper>
            </Box>
          ))}

          {/* Typing indicator */}
          {loading && (
            <Box sx={{ display: 'flex', gap: 2, alignItems: 'center' }}>
              <Avatar sx={{ bgcolor: 'secondary.dark', width: 32, height: 32 }}>
                <SmartToy sx={{ fontSize: 18 }} />
              </Avatar>
              <CircularProgress size={20} />
            </Box>
          )}
          <div ref={scrollRef} />
        </Box>

        <Divider />

        {/* Input bar */}
        <Box sx={{ p: 2, backgroundColor: 'rgba(0,0,0,0.1)' }}>
          <Box sx={{ display: 'flex', gap: 1 }}>
            <TextField
              fullWidth
              size="small"
              placeholder="Ask anything (e.g. 'How is Varnish performing?' or 'Check Magento errors')"
              value={input}
              onChange={(e) => setInput(e.target.value)}
              onKeyDown={handleKeyDown}
              sx={{ '& .MuiInputBase-root': { borderRadius: 2 } }}
            />
            <IconButton color="primary" onClick={handleSend} disabled={loading || !input.trim()}>
              <Send />
            </IconButton>
          </Box>
        </Box>
      </Card>
    </Box>
  );
}

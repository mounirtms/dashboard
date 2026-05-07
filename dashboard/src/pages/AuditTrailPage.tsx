import { Box, Typography, Card, CardContent, List, ListItem, ListItemText, Chip, Button } from '@mui/material';
import { History as AuditIcon, Refresh, Security, AdminPanelSettings, TravelExplore } from '@mui/icons-material';
import { useState, useEffect } from 'react';
import apiClient from '../api/client';
import LoadingState from '../components/common/LoadingState';

export default function AuditTrailPage() {
  const [entries, setEntries] = useState<string[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchAudit = () => {
    setLoading(true);
    apiClient.get('/api/monitor.php?action=audit')
      .then(({ data }) => setEntries(data.entries || []))
      .catch(e => console.error(e))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    fetchAudit();
  }, []);

  const parseEntry = (entry: string) => {
    const regex = /^\[(.*?)\]\s+\[(.*?)\]\s+\[(.*?)\]\s+(.*?)\s+on\s+(.*?)\s+-\s+(.*)$/;
    const m = entry.match(regex);
    if (!m) return { raw: entry };
    return {
      time: m[1],
      ip: m[2],
      user: m[3],
      action: m[4],
      target: m[5],
      details: m[6]
    };
  };

  return (
    <Box>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Audit Infrastructure
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Historical record of administrative operations and platform actions.
          </Typography>
        </Box>
        <Button variant="outlined" startIcon={<Refresh />} onClick={fetchAudit} disabled={loading}>
          Refresh Trail
        </Button>
      </Box>

      <Card>
        <CardContent sx={{ p: 0 }}>
          <List disablePadding>
            {entries.length > 0 ? entries.map((entry, idx) => {
              const p = parseEntry(entry);
              if (p.raw) return <ListItem key={idx} divider><ListItemText primary={p.raw} /></ListItem>;
              
              return (
                <ListItem key={idx} divider sx={{ py: 2, px: 3, '&:hover': { backgroundColor: 'rgba(255,255,255,0.01)' } }}>
                  <Box sx={{ display: 'flex', gap: 3, width: '100%', alignItems: 'center' }}>
                    <Box sx={{ minWidth: 140 }}>
                      <Typography variant="caption" color="text.disabled" sx={{ fontWeight: 700, display: 'block' }}>{p.time}</Typography>
                      <Typography variant="caption" sx={{ fontFamily: 'monospace', color: 'primary.light' }}>{p.ip}</Typography>
                    </Box>
                    
                    <Box sx={{ minWidth: 100 }}>
                      <Chip 
                        icon={<AdminPanelSettings sx={{ fontSize: 14 }} />} 
                        label={p.user} 
                        size="small" 
                        variant="outlined" 
                        sx={{ fontWeight: 700, borderColor: 'rgba(255,255,255,0.1)' }} 
                      />
                    </Box>

                    <Box sx={{ minWidth: 120 }}>
                      <Typography sx={{ 
                        fontWeight: 900, 
                        fontSize: '0.75rem', 
                        color: p.action === 'EXECUTE' ? 'error.light' : 'success.light' 
                      }}>
                        {p.action}
                      </Typography>
                    </Box>

                    <Box sx={{ flexGrow: 1 }}>
                      <Typography variant="body2" sx={{ fontWeight: 600 }}>{p.target}</Typography>
                      <Typography variant="caption" color="text.secondary">{p.details}</Typography>
                    </Box>
                  </Box>
                </ListItem>
              );
            }) : (
              <Box sx={{ py: 10, textAlign: 'center' }}>
                <TravelExplore sx={{ fontSize: 48, color: 'text.disabled', opacity: 0.2, mb: 1 }} />
                <Typography color="text.disabled">No audit entries found in the secure vault.</Typography>
              </Box>
            )}
          </List>
        </CardContent>
      </Card>
    </Box>
  );
}

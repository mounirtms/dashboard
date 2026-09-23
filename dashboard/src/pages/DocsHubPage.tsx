import {
  Box, Typography, Card, CardContent, Button, LinearProgress, Alert, Paper,
  Chip, TextField, InputAdornment, List, ListItemButton, ListItemText
} from '@mui/material';
import {
  ArrowBack, Description, Search, CompareArrows, Fullscreen, FolderOpen
} from '@mui/icons-material';
import { Link } from 'react-router-dom';
import { useState, useEffect, useMemo, useRef } from 'react';
import apiClient from '../api/client';

interface DocItem {
  file: string; title: string; category: string;
  bytes: number; mtime: string; featured?: boolean;
}

export default function DocsHubPage() {
  const [docs, setDocs] = useState<DocItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [q, setQ] = useState('');
  const [cat, setCat] = useState('All');
  const [active, setActive] = useState<DocItem | null>(null);
  const [html, setHtml] = useState('');
  const [docLoading, setDocLoading] = useState(false);
  const [docError, setDocError] = useState<string | null>(null);
  const readerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    apiClient.get('/api/cicd.php?action=docs')
      .then((r) => {
        if (r.data.error) throw new Error(r.data.error);
        setDocs(r.data.docs || []);
      })
      .catch((e) => setError(e.message || 'Failed to load docs catalog'))
      .finally(() => setLoading(false));
  }, []);

  const cats = useMemo(
    () => ['All', ...Array.from(new Set(docs.map((d) => d.category)))],
    [docs]
  );
  const filtered = useMemo(
    () => docs.filter((d) =>
      (cat === 'All' || d.category === cat) &&
      (!q || (d.title + ' ' + d.file).toLowerCase().includes(q.toLowerCase()))
    ),
    [docs, cat, q]
  );
  const grouped = useMemo(() => {
    const m = new Map<string, DocItem[]>();
    filtered.forEach((d) => {
      const arr = m.get(d.category) || [];
      arr.push(d);
      m.set(d.category, arr);
    });
    return Array.from(m.entries());
  }, [filtered]);
  const featured = docs.find((d) => d.featured);

  const openDoc = (d: DocItem) => {
    setActive(d);
    setDocLoading(true);
    setDocError(null);
    setHtml('');
    apiClient.get('/api/cicd.php?action=doc&file=' + encodeURIComponent(d.file))
      .then((r) => {
        if (r.data.error) throw new Error(r.data.error);
        setHtml(r.data.doc.html);
      })
      .catch((e) => setDocError(e.message || 'Failed to load document'))
      .finally(() => setDocLoading(false));
  };

  return (
    <Box sx={{ py: 3, px: 2, bgcolor: 'background.default' }}>
      <Card sx={{ border: 1, borderColor: 'divider' }}>
        <CardContent>
          <Box sx={{ display: 'flex', alignItems: 'center', mb: 2, flexWrap: 'wrap', gap: 1 }}>
            <Button startIcon={<ArrowBack />} component={Link} to="/" variant="text" color="primary">
              Overview
            </Button>
            <Description sx={{ mr: 1, fontSize: 28, color: '#a78bfa' }} />
            <Typography variant="h4" sx={{ flex: 1 }}>Reports &amp; Docs</Typography>
            <Chip label={`${docs.length} documents`} size="small" variant="outlined" sx={{ fontWeight: 600 }} />
          </Box>

          {loading && <LinearProgress sx={{ mb: 2 }} />}
          {error && (
            <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>{error}</Alert>
          )}

          {featured && (
            <Paper elevation={0} sx={{ mb: 2, p: 2, border: 1, borderColor: 'primary.main',
              borderRadius: 2, bgcolor: 'action.hover' }}>
              <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, flexWrap: 'wrap' }}>
                <CompareArrows sx={{ color: 'primary.main', fontSize: 28 }} />
                <Box sx={{ flex: 1, minWidth: 260 }}>
                  <Typography variant="h6" sx={{ fontWeight: 700 }}>
                    Featured: CI/CD — July 1 vs Today
                  </Typography>
                  <Typography variant="body2" color="text.secondary">
                    Meeting brief comparing Damien&apos;s original pipeline (2026-07-01) with
                    today&apos;s — additions, tunings, fixes, runner analysis, scripts, discussion points.
                  </Typography>
                </Box>
                <Button component={Link} to="/cicd-comparison" variant="contained" color="primary">
                  Open comparison page
                </Button>
                <Button variant="outlined" onClick={() => openDoc(featured)}>Read inline</Button>
              </Box>
            </Paper>
          )}

          <Box sx={{ display: 'flex', gap: 2, flexWrap: 'wrap' }}>
            {/* Left: catalog */}
            <Box sx={{ flex: '1 1 300px', minWidth: 260, maxWidth: 420 }}>
              <TextField fullWidth size="small" placeholder="Search title or file…" value={q}
                onChange={(e) => setQ(e.target.value)} sx={{ mb: 1.5 }}
                slotProps={{ input: {
                  startAdornment: <InputAdornment position="start"><Search /></InputAdornment>
                } }} />
              <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 0.75, mb: 1.5 }}>
                {cats.map((c) => (
                  <Chip key={c} label={c} size="small" clickable
                    color={cat === c ? 'primary' : 'default'} variant={cat === c ? 'filled' : 'outlined'}
                    onClick={() => setCat(c)} sx={{ fontWeight: 600 }} />
                ))}
              </Box>
              {grouped.map(([category, items]) => (
                <Box key={category} sx={{ mb: 1 }}>
                  <Typography variant="overline" color="text.secondary"
                    sx={{ fontWeight: 700, display: 'block', pl: 0.5 }}>
                    {category}
                  </Typography>
                  <List dense disablePadding>
                    {items.map((d) => (
                      <ListItemButton key={d.file} selected={active?.file === d.file}
                        onClick={() => openDoc(d)} sx={{ borderRadius: 1, py: 0.25 }}>
                        <ListItemText
                          primary={<Typography sx={{ fontSize: '0.8rem', fontWeight: 600 }}>
                            {d.featured ? '★ ' : ''}{d.title}</Typography>}
                          secondary={<Typography sx={{ fontSize: '0.68rem' }}>
                            {d.file} · {d.mtime} · {Math.round(d.bytes / 1024)} KB</Typography>}
                        />
                      </ListItemButton>
                    ))}
                  </List>
                </Box>
              ))}
              {!loading && filtered.length === 0 && (
                <Typography variant="body2" color="text.secondary" sx={{ p: 1 }}>
                  No documents match.
                </Typography>
              )}
            </Box>

            {/* Right: reader */}
            <Box sx={{ flex: '1 1 480px', minWidth: 300 }}>
              <Paper elevation={0}
                sx={{ border: 1, borderColor: 'divider', overflow: 'hidden', height: '68vh' }}>
                <CardContent sx={{ p: 0, height: '100%', overflow: 'auto' }}>
                  {docLoading && <LinearProgress sx={{ m: 2 }} />}
                  {docError && <Alert severity="error" sx={{ m: 2 }}>{docError}</Alert>}
                  {!docLoading && !docError && active && html && (
                    <Box sx={{ p: 0.5, px: 1.5, borderBottom: 1, borderColor: 'divider',
                      display: 'flex', alignItems: 'center', gap: 1, bgcolor: 'action.hover' }}>
                      <Description sx={{ fontSize: 16, color: '#a78bfa' }} />
                      <Typography variant="subtitle2" sx={{ fontWeight: 700, flex: 1 }}>
                        {active.title}
                      </Typography>
                      <Chip icon={<Fullscreen sx={{ fontSize: 12 }} />} label={active.file}
                        size="small" sx={{ fontWeight: 600 }} />
                    </Box>
                  )}
                  {!docLoading && !docError && !active && (
                    <Box sx={{ p: 4, textAlign: 'center' }}>
                      <Description sx={{ fontSize: 48, color: 'text.disabled', mb: 1 }} />
                      <Typography color="text.secondary">
                        Select a document from the list to read it here.
                      </Typography>
                    </Box>
                  )}
                  {active && html && (
                    <div ref={readerRef} dangerouslySetInnerHTML={{ __html: html }}
                      style={{ padding: '16px 24px', fontFamily: 'Roboto, sans-serif' }} />
                  )}
                </CardContent>
              </Paper>
            </Box>
          </Box>

          <Box sx={{ mt: 2, p: 1.5, bgcolor: 'warning.light', borderRadius: 1 }}>
            <Typography variant="caption" color="warning.dark">
              <strong>Live source:</strong> catalog from <code>api/cicd.php?action=docs</code>,
              content from <code>api/cicd.php?action=doc&amp;file=…</code>. Markdown files under{' '}
              <code>docs/</code> and the repo root are indexed; edits reflect immediately without a
              rebuild.
            </Typography>
          </Box>
        </CardContent>
      </Card>
    </Box>
  );
}



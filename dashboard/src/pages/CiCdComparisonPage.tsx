import {
  Box, Typography, Card, CardContent, Button, LinearProgress, Alert, Paper, Chip
} from '@mui/material';
import {
  ArrowBack, CompareArrows, Description, History, FolderOpen, Fullscreen
} from '@mui/icons-material';
import { Link } from 'react-router-dom';
import { useState, useEffect, useRef, useCallback } from 'react';
import apiClient from '../api/client';

const DOC_FILE = 'docs/CD_JULY1_VS_TODAY.md';

interface Section { id: string; label: string; }

export default function CiCdComparisonPage() {
  const [html, setHtml] = useState('');
  const [title, setTitle] = useState('');
  const [mtime, setMtime] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [sections, setSections] = useState<Section[]>([]);
  const contentRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    setLoading(true);
    setError(null);
    apiClient.get('/api/cicd.php?action=doc&file=' + encodeURIComponent(DOC_FILE))
      .then((res) => {
        if (res.data.error) throw new Error(res.data.error);
        setHtml(res.data.doc.html);
        setTitle(res.data.doc.title);
        setMtime(res.data.doc.mtime);
        const doc = new DOMParser().parseFromString(res.data.doc.html, 'text/html');
        setSections(Array.from(doc.querySelectorAll('h2')).map((h, i) => ({
          id: `cmp-h2-${i}`,
          label: h.textContent || `Section ${i + 1}`,
        })));
      })
      .catch((err) => setError(err.message || 'Failed to load comparison'))
      .finally(() => setLoading(false));
  }, []);

  // Assign ids to h2 elements in the rendered DOM for stepper scrolling.
  useEffect(() => {
    if (!contentRef.current || !html) return;
    contentRef.current.querySelectorAll('h2').forEach((h, i) => { h.id = `cmp-h2-${i}`; });
  }, [html]);

  const scrollTo = useCallback((id: string) => {
    const el = contentRef.current?.querySelector('#' + CSS.escape(id));
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }, []);

  return (
    <Box sx={{ py: 3, px: 2, bgcolor: 'background.default' }}>
      <Card sx={{ maxWidth: 1280, margin: '0 auto', border: 1, borderColor: 'divider' }}>
        <CardContent>
          <Box sx={{ display: 'flex', alignItems: 'center', mb: 2, flexWrap: 'wrap', gap: 1 }}>
            <Button startIcon={<ArrowBack />} component={Link} to="/docs" variant="text" color="primary">
              Reports &amp; Docs
            </Button>
            <CompareArrows sx={{ mr: 1, fontSize: 28, color: '#a78bfa' }} />
            <Typography variant="h4" sx={{ flex: 1 }}>CI/CD — July 1 vs Today</Typography>
            <Chip icon={<History sx={{ fontSize: 14 }} />} label="Meeting brief"
              size="small" color="primary" variant="outlined" sx={{ fontWeight: 600 }} />
            <Chip icon={<FolderOpen sx={{ fontSize: 14 }} />} label={DOC_FILE}
              size="small" sx={{ fontWeight: 600, bgcolor: 'action.hover' }} />
          </Box>

          <Alert severity="info" sx={{ mb: 2 }}>
            <Typography variant="body2">
              <strong>Purpose:</strong> detailed comparison of Damien&apos;s CI/CD as presented on{' '}
              <strong>2026-07-01</strong> (commit <code>79665b2bb</code>) vs the pipeline running{' '}
              <strong>today</strong> — all additions, tunings, fixes, runner analysis, scripts and
              commands, plus prepared discussion points. Companion to{' '}
              <Link to="/cicd-report">/cicd-report</Link>.
            </Typography>
          </Alert>

          {loading && (
            <Box sx={{ mb: 2 }}>
              <LinearProgress />
              <Typography variant="body2" color="text.secondary" sx={{ mt: 1, textAlign: 'center' }}>
                Loading comparison document…
              </Typography>
            </Box>
          )}

          {error && (
            <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>
              {error}
              <Typography variant="body2" sx={{ mt: 0.5 }} color="text.secondary">
                Make sure <code>{DOC_FILE}</code> exists in the dashboard root.
              </Typography>
            </Alert>
          )}

          {!loading && !error && html && (
            <>
              <Paper elevation={0} sx={{ mb: 2, p: 1.5, bgcolor: 'action.hover', borderRadius: 1 }}>
                <Typography variant="subtitle2"
                  sx={{ fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <Description sx={{ fontSize: 16, color: '#a78bfa' }} />{title}
                </Typography>
                <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1, mt: 0.5 }}>
                  <Chip label={`Source: ${DOC_FILE}`} size="small" variant="outlined" sx={{ fontWeight: 600 }} />
                  <Chip label={`Updated: ${mtime}`} size="small" variant="outlined" sx={{ fontWeight: 600 }} />
                  <Chip label={`${sections.length} sections`} size="small" variant="outlined" sx={{ fontWeight: 600 }} />
                  <Chip label="31 CI/CD commits since 79665b2bb" size="small" variant="outlined" sx={{ fontWeight: 600 }} />
                </Box>
              </Paper>

              {sections.length > 0 && (
                <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1, mb: 2 }}>
                  <Button size="small" startIcon={<Fullscreen />} variant="outlined"
                    onClick={() => {
                      contentRef.current?.parentElement?.scrollTo({ top: 0, behavior: 'smooth' });
                      window.scrollTo({ top: 0, behavior: 'smooth' });
                    }}>
                    Top
                  </Button>
                  {sections.map((s, i) => (
                    <Chip key={s.id} label={`${i}. ${s.label}`} size="small" clickable
                      onClick={() => scrollTo(s.id)} color={i === 0 ? 'primary' : 'default'}
                      variant="outlined" sx={{ fontWeight: 600, maxWidth: 340 }} />
                  ))}
                </Box>
              )}

              <Paper elevation={0} sx={{ border: 1, borderColor: 'divider', overflow: 'hidden' }}>
                <CardContent sx={{ p: 0, maxHeight: '70vh', overflow: 'auto' }}>
                  <div ref={contentRef} dangerouslySetInnerHTML={{ __html: html }}
                    style={{ padding: '16px 24px', fontFamily: 'Roboto, sans-serif' }} />
                </CardContent>
              </Paper>

              <Box sx={{ mt: 2, p: 1.5, bgcolor: 'warning.light', borderRadius: 1 }}>
                <Typography variant="caption" color="warning.dark">
                  <strong>Live source:</strong> served by{' '}
                  <code>api/cicd.php?action=doc&amp;file={DOC_FILE}</code> — markdown edits appear
                  immediately without a rebuild. Also readable in the{' '}
                  <Link to="/docs">Reports &amp; Docs</Link> hub.
                </Typography>
              </Box>
            </>
          )}
        </CardContent>
      </Card>
    </Box>
  );
}


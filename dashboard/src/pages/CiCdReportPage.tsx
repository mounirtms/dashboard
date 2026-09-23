import {
  Box, Typography, Card, CardContent, Button, LinearProgress,
  Alert, Paper, Stepper, Step, StepLabel, Chip
} from '@mui/material';
import {
  ArrowBack, Description, FolderOpen, Article, Timeline,
  FormatListBulleted, TableRows, Code, Build, History,
  CloudDownload
} from '@mui/icons-material';
import { Link } from 'react-router-dom';
import { useState, useEffect, useRef } from 'react';
import apiClient from '../api/client';

const REPORT_SECTIONS = [
  { id: 'glance',         label: 'At a glance',              icon: Article },
  { id: 'pipeline',       label: 'Pipeline change history',  icon: Timeline },
  { id: 'dashboard',      label: 'Dashboard integration',    icon: Build },
  { id: 'env-compare',    label: '3-environment comparison', icon: TableRows },
  { id: 'api-fixes',      label: 'API fixes',                icon: Code },
  { id: 'cicd-page',      label: '/cicd page enhancement',  icon: History },
  { id: 'cleanups',       label: 'Cleanups',                 icon: FormatListBulleted },
  { id: 'pipeline-state', label: 'Current pipeline state',   icon: CloudDownload },
  { id: 'task-map',       label: 'Task map',                 icon: History },
  { id: 'appendix-c',     label: 'Env comparison (detailed)', icon: TableRows },
  { id: 'appendix-d',     label: 'All changes (categorized)', icon: FormatListBulleted },
];

export default function CiCdReportPage() {
  const [report, setReport] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [activeSection, setActiveSection] = useState<string | null>(null);
  const contentRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    setLoading(true);
    setError(null);
    apiClient.get('/api/cicd.php?action=report')
      .then((res) => {
        if (res.data.error) throw new Error(res.data.error);
        setReport(res.data.report);
      })
      .catch((err) => setError(err.message || 'Failed to load report'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    if (!contentRef.current || !activeSection) return;
    const el = contentRef.current.querySelector(`[data-section="${activeSection}"]`);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }, [activeSection]);

  return (
    <Box sx={{ py: 3, px: 2, bgcolor: 'background.default' }}>
      <Card sx={{ maxWidth: 1280, margin: '0 auto', border: 1, borderColor: 'divider' }}>
        <CardContent>
          <Box sx={{ display: 'flex', alignItems: 'center', mb: 2, flexWrap: 'wrap', gap: 1 }}>
            <Button startIcon={<ArrowBack />} component={Link} to="/cicd" variant="text" color="primary">
              Back to CI/CD
            </Button>
            <Description sx={{ mr: 1, fontSize: 28, color: '#a78bfa' }} />
            <Typography variant="h4" sx={{ flex: 1 }}>CI/CD Full Change Report</Typography>
            <Chip icon={<FolderOpen sx={{ fontSize: 14 }} />} label="docs/CI_CDARCHITECTURE_MAX.md"
              size="small" sx={{ fontWeight: 600, bgcolor: 'action.hover' }} />
          </Box>

          {loading && (
            <Box sx={{ mb: 2 }}>
              <LinearProgress />
              <Typography variant="body2" color="text.secondary" sx={{ mt: 1, textAlign: 'center' }}>
                Loading full report ({REPORT_SECTIONS.length} sections)…
              </Typography>
            </Box>
          )}

          {error && (
            <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>
              {error}
              <Typography variant="body2" sx={{ mt: 0.5 }} color="text.secondary">
                Make sure <code>docs/CI_CDARCHITECTURE_MAX.md</code> exists in the dashboard root.
              </Typography>
            </Alert>
          )}

          {report && (
            <>
              <Paper elevation={0} sx={{ mb: 2, p: 1.5, bgcolor: 'action.hover', borderRadius: 1 }}>
                <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 0.5, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <History sx={{ fontSize: 16, color: '#a78bfa' }} />{report.title}
                </Typography>
                <Typography variant="caption" color="text.secondary" sx={{ display: 'block', mb: 0.5 }}>
                  Project: {report.project} · Dashboard: {report.dashboard}
                </Typography>
                <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1 }}>
                  <Chip label={`Coverage: ${report.coverage}`} size="small" variant="outlined" sx={{ fontWeight: 600 }} />
                  <Chip label={`Owner: ${report.owner}`} size="small" variant="outlined" sx={{ fontWeight: 600 }} />
                  <Chip label={`Generated: ${report.generated}`} size="small" variant="outlined" sx={{ fontWeight: 600 }} />
                </Box>
              </Paper>

              <Stepper activeStep={REPORT_SECTIONS.findIndex((s) => s.id === activeSection)}
                alternativeLabel sx={{ mb: 2, px: 1, flexWrap: 'wrap' }}>
                {REPORT_SECTIONS.map((s) => (
                  <Step key={s.id} onClick={() => setActiveSection(s.id)} style={{ cursor: 'pointer' }}>
                    <StepLabel>
                      <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
                        <s.icon sx={{ fontSize: 16, color: '#a78bfa' }} />
                        <Typography variant="caption" sx={{ fontWeight: 600 }}>{s.label}</Typography>
                      </Box>
                    </StepLabel>
                  </Step>
                ))}
              </Stepper>

              <Paper elevation={0} sx={{ border: 1, borderColor: 'divider', overflow: 'hidden' }}>
                <CardContent sx={{ p: 0, maxHeight: '72vh', overflow: 'auto' }}>
                  <div ref={contentRef}
                    dangerouslySetInnerHTML={{ __html: report.content }}
                    style={{ padding: '16px 24px', fontFamily: 'Roboto, sans-serif' }} />
                </CardContent>
              </Paper>

              <Box sx={{ mt: 2, p: 1.5, bgcolor: 'warning.light', borderRadius: 1 }}>
                <Typography variant="caption" color="warning.dark">
                  <strong>Live source:</strong> This report is served by <code>api/cicd.php?action=report</code>
                  from <code>docs/CI_CDARCHITECTURE_MAX.md</code> and is also viewable at{' '}
                  <code>/cicd-report</code>. The API endpoint reads the markdown file at request time, so
                  edits to the markdown are reflected immediately without a rebuild.
                </Typography>
              </Box>
            </>
          )}

          {!loading && !error && !report && (
            <Box sx={{ textAlign: 'center', py: 4 }}>
              <FolderOpen sx={{ fontSize: 48, color: 'text.disabled', mb: 1 }} />
              <Typography variant="body1" color="text.secondary">
                No report data returned. Check that <code>docs/CI_CDARCHITECTURE_MAX.md</code> exists.
              </Typography>
            </Box>
          )}
        </CardContent>
      </Card>
    </Box>
  );
}

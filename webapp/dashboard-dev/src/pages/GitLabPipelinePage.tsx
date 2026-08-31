import { useState, useEffect, useCallback } from 'react';
import {
  Box, Typography, Grid, Card, CardContent, Chip, CircularProgress,
  Table, TableBody, TableCell, TableContainer, TableHead, TableRow,
  Alert, Button, IconButton, Tooltip, LinearProgress, Divider,
  Collapse, List, ListItem, ListItemIcon, ListItemText,
} from '@mui/material';
import {
  CheckCircle, Cancel, Schedule, AccountTree, RocketLaunch,
  Refresh, OpenInNew, PlayArrow, ExpandMore, ExpandLess,
  HourglassTop, Block, RadioButtonUnchecked, FiberManualRecord,
} from '@mui/icons-material';

// ── Types ─────────────────────────────────────────────────────────────────────

interface Pipeline {
  id: number;
  iid: number;
  sha: string;
  short_sha: string;
  ref: string;
  status: string;
  source: string;
  created_at: string | null;
  updated_at: string | null;
  web_url: string;
  duration: number | null;
  queued_duration: number | null;
}

interface Branch {
  name: string;
  protected: boolean;
  default: boolean;
  sha: string;
  full_sha: string;
  committed_at: string | null;
  committer_name: string;
  commit_title: string;
  web_url: string;
}

interface Job {
  id: number;
  name: string;
  stage: string;
  status: string;
  duration: number | null;
  started_at: string | null;
  finished_at: string | null;
  web_url: string;
  allow_failure: boolean;
}

// ── Helpers ───────────────────────────────────────────────────────────────────

const STATUS_COLOR: Record<string, string> = {
  success:  '#22c55e',
  failed:   '#ef4444',
  running:  '#3b82f6',
  pending:  '#f59e0b',
  canceled: '#64748b',
  skipped:  '#64748b',
  manual:   '#8b5cf6',
  created:  '#94a3b8',
  unknown:  '#64748b',
};

const STATUS_MUI: Record<string, 'success' | 'error' | 'info' | 'warning' | 'default'> = {
  success:  'success',
  failed:   'error',
  running:  'info',
  pending:  'warning',
  canceled: 'default',
  skipped:  'default',
  manual:   'default',
};

function StatusIcon({ status, size = 16 }: { status: string; size?: number }) {
  const s = size;
  const color = STATUS_COLOR[status] ?? '#64748b';
  if (status === 'success')  return <CheckCircle sx={{ fontSize: s, color }} />;
  if (status === 'failed')   return <Cancel sx={{ fontSize: s, color }} />;
  if (status === 'running')  return <HourglassTop sx={{ fontSize: s, color }} />;
  if (status === 'pending')  return <Schedule sx={{ fontSize: s, color }} />;
  if (status === 'canceled') return <Block sx={{ fontSize: s, color }} />;
  if (status === 'manual')   return <PlayArrow sx={{ fontSize: s, color }} />;
  return <RadioButtonUnchecked sx={{ fontSize: s, color }} />;
}

function fmtDuration(s: number | null): string {
  if (!s) return '—';
  const m = Math.floor(s / 60);
  const sec = Math.round(s % 60);
  return m > 0 ? `${m}m ${sec}s` : `${sec}s`;
}

function fmtDate(iso: string | null): string {
  if (!iso) return '—';
  const d = new Date(iso);
  return d.toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function fmtDateShort(iso: string | null): string {
  if (!iso) return '—';
  const d = new Date(iso);
  return d.toLocaleString('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
}

// ── Branch row ────────────────────────────────────────────────────────────────

const TRACKED_BRANCHES = ['master', 'dev', 'tsdnd'];

function BranchCard({ branch, onTrigger, triggering }: {
  branch: Branch;
  onTrigger: (b: string) => void;
  triggering: string | null;
}) {
  const isTracked = TRACKED_BRANCHES.includes(branch.name);
  const borderColor = branch.name === 'master'
    ? 'rgba(34,197,94,0.3)'
    : branch.name === 'tsdnd'
    ? 'rgba(59,130,246,0.3)'
    : 'divider';
  const bgColor = branch.name === 'master'
    ? 'rgba(34,197,94,0.04)'
    : branch.name === 'tsdnd'
    ? 'rgba(59,130,246,0.04)'
    : 'transparent';

  return (
    <Box sx={{ p: 1.5, mb: 1.5, borderRadius: 1.5, border: '1px solid', borderColor, background: bgColor }}>
      <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 0.5 }}>
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
          <AccountTree sx={{ fontSize: 13, color: 'text.secondary' }} />
          <Typography variant="caption" sx={{ fontWeight: 800, fontFamily: 'monospace' }}>
            {branch.name}
          </Typography>
          {branch.protected && (
            <Chip label="protected" size="small" sx={{ height: 16, fontSize: '0.55rem', fontWeight: 700 }} />
          )}
          {branch.default && (
            <Chip label="default" size="small" color="success" sx={{ height: 16, fontSize: '0.55rem', fontWeight: 700 }} />
          )}
        </Box>
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
          {isTracked && (
            <Tooltip title={`Trigger pipeline on ${branch.name}`}>
              <span>
                <IconButton
                  size="small"
                  onClick={() => onTrigger(branch.name)}
                  disabled={triggering !== null}
                  sx={{ p: 0.3, color: STATUS_COLOR['manual'] }}
                >
                  {triggering === branch.name
                    ? <CircularProgress size={12} />
                    : <PlayArrow sx={{ fontSize: 14 }} />}
                </IconButton>
              </span>
            </Tooltip>
          )}
          <Tooltip title="Open in GitLab">
            <IconButton size="small" href={branch.web_url} target="_blank" sx={{ p: 0.3, color: 'text.disabled' }}>
              <OpenInNew sx={{ fontSize: 12 }} />
            </IconButton>
          </Tooltip>
        </Box>
      </Box>
      <Typography variant="caption" sx={{ fontFamily: 'monospace', color: '#8b5cf6', fontSize: '0.7rem' }}>
        {branch.sha}
      </Typography>
      <Typography variant="caption" sx={{ display: 'block', color: 'text.disabled', fontSize: '0.68rem', mt: 0.2,
        overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', maxWidth: '100%' }}>
        {branch.commit_title || '—'}
      </Typography>
      <Typography variant="caption" sx={{ display: 'block', color: 'text.disabled', fontSize: '0.62rem' }}>
        {branch.committer_name} · {fmtDateShort(branch.committed_at)}
      </Typography>
    </Box>
  );
}

// ── Pipeline row (expandable) ─────────────────────────────────────────────────

function PipelineRow({ pipeline }: { pipeline: Pipeline }) {
  const [open, setOpen]   = useState(false);
  const [jobs, setJobs]   = useState<Job[]>([]);
  const [loading, setLoading] = useState(false);

  const loadJobs = async () => {
    if (jobs.length > 0) { setOpen(o => !o); return; }
    setLoading(true);
    try {
      const r = await fetch(`/api/gitlab-pipeline.php?action=jobs&pipeline_id=${pipeline.id}`);
      const d = await r.json();
      if (d.jobs) setJobs(d.jobs);
      setOpen(true);
    } catch { /* ignore */ }
    finally { setLoading(false); }
  };

  const stages = ['release', 'deploy', 'promote'];
  const jobsByStage = stages.reduce<Record<string, Job[]>>((acc, s) => {
    acc[s] = jobs.filter(j => j.stage === s);
    return acc;
  }, {});

  return (
    <>
      <TableRow
        hover
        onClick={loadJobs}
        sx={{ cursor: 'pointer', '&:hover': { background: 'rgba(255,255,255,0.02)' } }}
      >
        <TableCell sx={{ width: 24, py: 1 }}>
          {loading
            ? <CircularProgress size={12} />
            : open
            ? <ExpandLess sx={{ fontSize: 14, color: 'text.disabled' }} />
            : <ExpandMore sx={{ fontSize: 14, color: 'text.disabled' }} />}
        </TableCell>
        <TableCell sx={{ py: 1 }}>
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.8 }}>
            <StatusIcon status={pipeline.status} />
            <Typography variant="caption" sx={{ fontFamily: 'monospace', color: STATUS_COLOR[pipeline.status] ?? '#64748b', fontWeight: 700 }}>
              #{pipeline.id}
            </Typography>
          </Box>
        </TableCell>
        <TableCell sx={{ py: 1 }}>
          <Chip
            label={pipeline.ref}
            size="small"
            sx={{
              fontSize: '0.6rem', height: 18, fontFamily: 'monospace',
              fontWeight: 700,
              color: pipeline.ref === 'master' ? '#22c55e' : pipeline.ref === 'tsdnd' ? '#3b82f6' : '#a78bfa',
              borderColor: pipeline.ref === 'master' ? 'rgba(34,197,94,0.3)' : pipeline.ref === 'tsdnd' ? 'rgba(59,130,246,0.3)' : 'rgba(167,139,250,0.3)',
            }}
            variant="outlined"
          />
        </TableCell>
        <TableCell sx={{ py: 1 }}>
          <Chip
            label={pipeline.status.toUpperCase()}
            size="small"
            color={STATUS_MUI[pipeline.status] ?? 'default'}
            sx={{ fontSize: '0.6rem', height: 18, fontWeight: 700 }}
          />
        </TableCell>
        <TableCell sx={{ py: 1, fontFamily: 'monospace', fontSize: '0.7rem', color: '#8b5cf6' }}>
          {pipeline.short_sha}
        </TableCell>
        <TableCell sx={{ py: 1, fontSize: '0.72rem', color: 'text.disabled' }}>
          {fmtDateShort(pipeline.created_at)}
        </TableCell>
        <TableCell sx={{ py: 1, fontFamily: 'monospace', fontSize: '0.7rem', color: 'text.secondary' }}>
          {fmtDuration(pipeline.duration)}
        </TableCell>
        <TableCell sx={{ py: 1 }}>
          <Tooltip title="Open in GitLab">
            <IconButton
              size="small"
              href={pipeline.web_url}
              target="_blank"
              onClick={e => e.stopPropagation()}
              sx={{ p: 0.3, color: 'text.disabled' }}
            >
              <OpenInNew sx={{ fontSize: 12 }} />
            </IconButton>
          </Tooltip>
        </TableCell>
      </TableRow>

      {/* Expanded jobs */}
      <TableRow>
        <TableCell colSpan={8} sx={{ p: 0, border: 0 }}>
          <Collapse in={open} unmountOnExit>
            <Box sx={{ p: 2, background: 'rgba(255,255,255,0.015)', borderBottom: '1px solid rgba(255,255,255,0.06)' }}>
              {jobs.length === 0 ? (
                <Typography variant="caption" color="text.disabled">No jobs found</Typography>
              ) : (
                <Grid container spacing={1}>
                  {stages.map(stage => (
                    <Grid size={{ xs: 12, md: 4 }} key={stage}>
                      <Typography variant="caption" sx={{ fontWeight: 800, textTransform: 'uppercase', color: 'text.disabled', letterSpacing: 0.8, display: 'block', mb: 0.5 }}>
                        {stage}
                      </Typography>
                      {jobsByStage[stage].length === 0 ? (
                        <Typography variant="caption" sx={{ color: 'text.disabled', fontStyle: 'italic' }}>—</Typography>
                      ) : (
                        jobsByStage[stage].map(job => (
                          <Box key={job.id} sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 0.5 }}>
                            <StatusIcon status={job.status} size={12} />
                            <Typography variant="caption" sx={{ fontFamily: 'monospace', fontSize: '0.68rem' }}>
                              {job.name}
                            </Typography>
                            {job.duration !== null && (
                              <Typography variant="caption" sx={{ color: 'text.disabled', fontSize: '0.62rem', ml: 'auto' }}>
                                {fmtDuration(job.duration)}
                              </Typography>
                            )}
                            <Tooltip title="View job log">
                              <IconButton size="small" href={job.web_url} target="_blank" sx={{ p: 0.2, color: 'text.disabled' }}>
                                <OpenInNew sx={{ fontSize: 10 }} />
                              </IconButton>
                            </Tooltip>
                          </Box>
                        ))
                      )}
                    </Grid>
                  ))}
                </Grid>
              )}
            </Box>
          </Collapse>
        </TableCell>
      </TableRow>
    </>
  );
}

// ── Main page ──────────────────────────────────────────────────────────────────

export default function GitLabPipelinePage() {
  const [pipelines,  setPipelines]  = useState<Pipeline[]>([]);
  const [branches,   setBranches]   = useState<Branch[]>([]);
  const [loading,    setLoading]    = useState(true);
  const [error,      setError]      = useState<string | null>(null);
  const [lastFetch,  setLastFetch]  = useState<Date | null>(null);
  const [triggering, setTriggering] = useState<string | null>(null);
  const [triggerMsg, setTriggerMsg] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
  const [filterRef,  setFilterRef]  = useState<string>('');

  const fetchAll = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [plRes, brRes] = await Promise.all([
        fetch('/api/gitlab-pipeline.php?action=pipelines&per_page=50'),
        fetch('/api/gitlab-pipeline.php?action=branches'),
      ]);
      const plData = await plRes.json();
      const brData = await brRes.json();

      if (plData.error) throw new Error(plData.error);
      if (brData.error) throw new Error(brData.error);

      setPipelines(plData.pipelines ?? []);
      // Only show tracked + recent branches
      const tracked = (brData.branches ?? []).filter((b: Branch) =>
        TRACKED_BRANCHES.includes(b.name)
      );
      setBranches(tracked);
      setLastFetch(new Date());
    } catch (e: unknown) {
      setError(e instanceof Error ? e.message : 'Failed to load GitLab data');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { fetchAll(); }, [fetchAll]);

  const handleTrigger = async (branch: string) => {
    if (!window.confirm(`Trigger pipeline on branch "${branch}"?`)) return;
    setTriggering(branch);
    setTriggerMsg(null);
    try {
      const r = await fetch('/api/gitlab-pipeline.php?action=trigger', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ branch }),
      });
      const d = await r.json();
      if (d.error) throw new Error(d.error);
      setTriggerMsg({ type: 'success', text: d.message });
      setTimeout(fetchAll, 2000); // refresh after trigger
    } catch (e: unknown) {
      setTriggerMsg({ type: 'error', text: e instanceof Error ? e.message : 'Trigger failed' });
    } finally {
      setTriggering(null);
    }
  };

  // KPI numbers
  const totalPipelines  = pipelines.length;
  const successCount    = pipelines.filter(p => p.status === 'success').length;
  const failedCount     = pipelines.filter(p => p.status === 'failed').length;
  const runningCount    = pipelines.filter(p => p.status === 'running').length;
  const successRate     = totalPipelines > 0 ? Math.round((successCount / totalPipelines) * 100) : 0;

  const filteredPipelines = filterRef
    ? pipelines.filter(p => p.ref === filterRef)
    : pipelines;

  return (
    <Box>
      {/* Header */}
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: 2 }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Magento GitLab Pipelines
          </Typography>
          <Typography variant="body2" color="text.secondary">
            Live data · technowebmaster-group/techno-magento · {lastFetch ? `Updated ${fmtDateShort(lastFetch.toISOString())}` : 'Loading…'}
          </Typography>
        </Box>
        <Box sx={{ display: 'flex', gap: 1 }}>
          <Button
            variant="outlined"
            startIcon={loading ? <CircularProgress size={14} /> : <Refresh />}
            onClick={fetchAll}
            disabled={loading}
            sx={{ fontWeight: 700, textTransform: 'none', borderColor: 'rgba(255,255,255,0.15)' }}
          >
            Refresh
          </Button>
          <Button
            variant="outlined"
            startIcon={<OpenInNew />}
            href="https://gitlab.com/technowebmaster-group/techno-magento/-/pipelines"
            target="_blank"
            rel="noopener noreferrer"
            sx={{ fontWeight: 700, textTransform: 'none', borderColor: 'rgba(255,255,255,0.15)' }}
          >
            GitLab
          </Button>
        </Box>
      </Box>

      {/* Loading bar */}
      {loading && <LinearProgress sx={{ mb: 2, borderRadius: 1 }} />}

      {/* Error */}
      {error && (
        <Alert severity="error" sx={{ mb: 2 }} onClose={() => setError(null)}>
          {error}
        </Alert>
      )}

      {/* Trigger feedback */}
      {triggerMsg && (
        <Alert severity={triggerMsg.type} sx={{ mb: 2 }} onClose={() => setTriggerMsg(null)}>
          {triggerMsg.text}
        </Alert>
      )}

      {/* KPIs */}
      <Grid container spacing={2} sx={{ mb: 3 }}>
        {[
          { label: 'Total Pipelines', value: totalPipelines,   color: '#3b82f6' },
          { label: 'Success',         value: successCount,     color: '#22c55e' },
          { label: 'Failed',          value: failedCount,      color: '#ef4444' },
          { label: 'Running',         value: runningCount,     color: '#f59e0b' },
          { label: 'Success Rate',    value: `${successRate}%`, color: successRate >= 90 ? '#22c55e' : successRate >= 70 ? '#f59e0b' : '#ef4444' },
        ].map(kpi => (
          <Grid size={{ xs: 6, sm: 4, md: 'auto' }} key={kpi.label} sx={{ flexGrow: 1 }}>
            <Card>
              <CardContent sx={{ py: '12px !important', px: '14px !important' }}>
                <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 700, textTransform: 'uppercase', letterSpacing: 0.8, display: 'block', mb: 0.5 }}>
                  {kpi.label}
                </Typography>
                <Typography variant="h5" sx={{ fontWeight: 900, color: kpi.color, fontFamily: 'monospace' }}>
                  {kpi.value}
                </Typography>
              </CardContent>
            </Card>
          </Grid>
        ))}
      </Grid>

      <Grid container spacing={3} sx={{ mb: 3 }}>

        {/* Branches */}
        <Grid size={{ xs: 12, md: 4 }}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 2, display: 'flex', alignItems: 'center', gap: 1 }}>
                <AccountTree sx={{ fontSize: 16, color: '#8b5cf6' }} /> Tracked Branches
              </Typography>
              {loading && branches.length === 0 ? (
                <Box sx={{ py: 4, textAlign: 'center' }}>
                  <CircularProgress size={24} />
                </Box>
              ) : branches.length === 0 ? (
                <Typography variant="caption" color="text.disabled">No branch data</Typography>
              ) : (
                branches.map(b => (
                  <BranchCard key={b.name} branch={b} onTrigger={handleTrigger} triggering={triggering} />
                ))
              )}
              <Divider sx={{ my: 1.5 }} />
              <Typography variant="caption" sx={{ color: 'text.disabled', fontSize: '0.65rem' }}>
                Click <PlayArrow sx={{ fontSize: 10, verticalAlign: 'middle' }} /> to trigger a new pipeline on that branch (admin only)
              </Typography>
            </CardContent>
          </Card>
        </Grid>

        {/* Pipeline status summary by branch */}
        <Grid size={{ xs: 12, md: 8 }}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
                <Typography variant="subtitle2" sx={{ fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
                  <FiberManualRecord sx={{ fontSize: 10, color: runningCount > 0 ? '#22c55e' : 'text.disabled', animation: runningCount > 0 ? 'pulse 1.5s infinite' : 'none' }} />
                  Pipeline Status by Branch
                </Typography>
                <Box sx={{ display: 'flex', gap: 0.5 }}>
                  {['', ...TRACKED_BRANCHES].map(ref => (
                    <Chip
                      key={ref}
                      label={ref || 'All'}
                      size="small"
                      onClick={() => setFilterRef(ref)}
                      variant={filterRef === ref ? 'filled' : 'outlined'}
                      sx={{
                        fontSize: '0.65rem', height: 20, cursor: 'pointer', fontWeight: 700,
                        borderColor: filterRef === ref ? 'primary.main' : 'divider',
                      }}
                    />
                  ))}
                </Box>
              </Box>

              {/* Per-branch last pipeline */}
              <List dense disablePadding sx={{ mb: 2 }}>
                {TRACKED_BRANCHES.map(branch => {
                  const last = pipelines.find(p => p.ref === branch);
                  return (
                    <ListItem key={branch} disableGutters sx={{ py: 0.5, borderBottom: '1px solid rgba(255,255,255,0.04)' }}>
                      <ListItemIcon sx={{ minWidth: 28 }}>
                        <StatusIcon status={last?.status ?? 'unknown'} />
                      </ListItemIcon>
                      <ListItemText
                        primary={
                          <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                            <Typography variant="caption" sx={{ fontWeight: 800, fontFamily: 'monospace', minWidth: 56 }}>{branch}</Typography>
                            {last ? (
                              <>
                                <Chip label={last.status.toUpperCase()} size="small" color={STATUS_MUI[last.status] ?? 'default'}
                                  sx={{ fontSize: '0.55rem', height: 16, fontWeight: 700 }} />
                                <Typography variant="caption" sx={{ color: '#8b5cf6', fontFamily: 'monospace', fontSize: '0.68rem' }}>
                                  #{last.id}
                                </Typography>
                                <Typography variant="caption" sx={{ color: 'text.disabled', fontSize: '0.65rem', ml: 'auto' }}>
                                  {fmtDateShort(last.created_at)} · {fmtDuration(last.duration)}
                                </Typography>
                              </>
                            ) : (
                              <Typography variant="caption" color="text.disabled">No pipeline found</Typography>
                            )}
                          </Box>
                        }
                      />
                    </ListItem>
                  );
                })}
              </List>

              <Divider sx={{ mb: 2 }} />

              {/* Recent pipeline mini-chart (last 20 as colored dots) */}
              <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 700, textTransform: 'uppercase', letterSpacing: 0.8, display: 'block', mb: 1 }}>
                Last {Math.min(filteredPipelines.length, 30)} Pipelines
              </Typography>
              <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 0.5 }}>
                {filteredPipelines.slice(0, 30).map(p => (
                  <Tooltip key={p.id} title={`#${p.id} · ${p.ref} · ${p.status} · ${fmtDateShort(p.created_at)}`}>
                    <Box
                      component="a"
                      href={p.web_url}
                      target="_blank"
                      sx={{
                        width: 14, height: 14, borderRadius: '2px',
                        background: STATUS_COLOR[p.status] ?? '#64748b',
                        opacity: p.status === 'canceled' ? 0.4 : 1,
                        display: 'inline-block',
                        textDecoration: 'none',
                        flexShrink: 0,
                        transition: 'transform 0.1s',
                        '&:hover': { transform: 'scale(1.3)' },
                      }}
                    />
                  </Tooltip>
                ))}
              </Box>
            </CardContent>
          </Card>
        </Grid>

      </Grid>

      {/* Pipeline table */}
      <Card>
        <CardContent sx={{ pb: '12px !important' }}>
          <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
            <Typography variant="subtitle2" sx={{ fontWeight: 700, display: 'flex', alignItems: 'center', gap: 1 }}>
              <Schedule sx={{ fontSize: 16, color: '#06b6d4' }} /> Pipeline History
              {filterRef && <Chip label={filterRef} size="small" onDelete={() => setFilterRef('')} sx={{ ml: 1, fontSize: '0.65rem', height: 20 }} />}
            </Typography>
            <Typography variant="caption" color="text.disabled">
              {filteredPipelines.length} pipeline{filteredPipelines.length !== 1 ? 's' : ''} · Click row to expand jobs
            </Typography>
          </Box>

          {filteredPipelines.length === 0 && !loading ? (
            <Typography variant="caption" color="text.disabled">No pipelines to display.</Typography>
          ) : (
            <TableContainer sx={{ maxHeight: 600 }}>
              <Table size="small" stickyHeader>
                <TableHead>
                  <TableRow>
                    <TableCell sx={{ width: 24, fontWeight: 700 }} />
                    <TableCell sx={{ fontWeight: 700 }}>Pipeline</TableCell>
                    <TableCell sx={{ fontWeight: 700 }}>Branch</TableCell>
                    <TableCell sx={{ fontWeight: 700 }}>Status</TableCell>
                    <TableCell sx={{ fontWeight: 700 }}>Commit</TableCell>
                    <TableCell sx={{ fontWeight: 700 }}>Created</TableCell>
                    <TableCell sx={{ fontWeight: 700 }}>Duration</TableCell>
                    <TableCell sx={{ width: 32 }} />
                  </TableRow>
                </TableHead>
                <TableBody>
                  {filteredPipelines.map(p => (
                    <PipelineRow key={p.id} pipeline={p} />
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          )}
        </CardContent>
      </Card>

      {/* Deploy triggers */}
      <Card sx={{ mt: 3 }}>
        <CardContent>
          <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 2, display: 'flex', alignItems: 'center', gap: 1 }}>
            <RocketLaunch sx={{ fontSize: 16, color: '#f59e0b' }} /> Trigger New Pipeline (Admin Only)
          </Typography>
          <Box sx={{ display: 'flex', gap: 2, flexWrap: 'wrap' }}>
            {TRACKED_BRANCHES.map(branch => (
              <Button
                key={branch}
                variant="outlined"
                startIcon={triggering === branch ? <CircularProgress size={14} /> : <PlayArrow />}
                onClick={() => handleTrigger(branch)}
                disabled={triggering !== null}
                sx={{
                  fontWeight: 700, textTransform: 'none',
                  borderColor: branch === 'master' ? 'rgba(239,68,68,0.4)' : branch === 'tsdnd' ? 'rgba(59,130,246,0.4)' : 'rgba(167,139,250,0.4)',
                  color: branch === 'master' ? '#ef4444' : branch === 'tsdnd' ? '#3b82f6' : '#a78bfa',
                  '&:hover': {
                    borderColor: branch === 'master' ? '#ef4444' : branch === 'tsdnd' ? '#3b82f6' : '#a78bfa',
                    background: branch === 'master' ? 'rgba(239,68,68,0.06)' : branch === 'tsdnd' ? 'rgba(59,130,246,0.06)' : 'rgba(167,139,250,0.06)',
                  },
                }}
              >
                Trigger {branch}
                {branch === 'master' && ' (production)'}
              </Button>
            ))}
          </Box>
          <Typography variant="caption" sx={{ color: 'text.disabled', mt: 1.5, display: 'block' }}>
            Pipelines run on GitLab runners (ded701-runner-production) · 4 stages: release → deploy:tsdnd/dev/master → promote
          </Typography>
        </CardContent>
      </Card>
    </Box>
  );
}

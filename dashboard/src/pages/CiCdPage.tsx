import {
  Box, Typography, Grid, Card, CardContent, Chip, Divider,
  LinearProgress, Table, TableBody, TableCell, TableContainer,
  TableHead, TableRow, Alert, Button,
} from '@mui/material';
import {
  CheckCircle, Cancel, Schedule, Commit, AccountTree,
  Merge, RocketLaunch, Build, Code, GitHub,
} from '@mui/icons-material';

// Static CI/CD data — real git stats from mounirtms/dashboard
const PIPELINE_RUNS = [
  { id: 'ff9f94b4', msg: 'feat(dashboard): S9+S10 — telegram/webpushr/log-explorer fixes + full audit sweep',                    branch: 'genspark_ai_developer', status: 'success', date: 'Jul 8, 2026', duration: '3m 10s', files: 925 },
  { id: 'c0934e53', msg: 'fix(dashboard+presentation): comprehensive audit pass v7+v8 — real data, server tunings, ecomscan accuracy', branch: 'genspark_ai_developer', status: 'success', date: 'Jul 8, 2026', duration: '2m 34s', files: 880 },
  { id: 'b92ede19', msg: 'feat(dashboard): major page upgrades — traffic, performance, inventory, geography, settings',             branch: 'genspark_ai_developer', status: 'success', date: 'Jul 7, 2026', duration: '3m 12s', files: 312 },
  { id: '6c45aeaf', msg: 'feat(dashboard): update sprint progress, commit hash, H1 2025 vs H1 2026 comparison charts',             branch: 'genspark_ai_developer', status: 'success', date: 'Jul 7, 2026', duration: '2m 08s', files: 47  },
  { id: '6fc21289', msg: 'feat(presentation): v4 — 37 slides, Algeria map, semester compare, server tunings, logo, Mounir credit', branch: 'genspark_ai_developer', status: 'success', date: 'Jul 4, 2026', duration: '1m 55s', files: 6   },
  { id: 'f0721a84', msg: 'perf(presentation): v3 optimizations — transitions, chart cache, kbd hint, data fixes',                  branch: 'genspark_ai_developer', status: 'success', date: 'Jun 29, 2026', duration: '0m 48s', files: 3  },
];

const BRANCH_STATUS = [
  { name: 'main',                  sha: 'b92ede19', behind: 2,  ahead: 0, status: 'behind', desc: 'Merge target — 2 commits behind genspark_ai_developer (S9+S10)' },
  { name: 'genspark_ai_developer', sha: 'ff9f94b4', behind: 0,  ahead: 0, status: 'current', desc: 'Active dev branch — PR #3 open → main (S9+S10 sweep)' },
  { name: 'master (local)',        sha: 'ff9f94b4', behind: 0,  ahead: 7, status: 'ahead', desc: 'Local master — 7 commits ahead of origin/master' },
];

const BUILD_STEPS = [
  { name: 'TypeScript Check',      cmd: 'tsc -b --noEmit',            status: 'success', time: '8.2s'  },
  { name: 'Vite Build',            cmd: 'npm run build',               status: 'success', time: '45.1s' },
  { name: 'post-build.sh',         cmd: 'bash post-build.sh',          status: 'success', time: '0.3s'  },
  { name: 'BUILD_STAMP inject',    cmd: 'sed BUILD_STAMP → index.html', status: 'success', time: '0.1s'  },
  { name: 'Stale chunk cleanup',   cmd: 'rm old index-*.js',           status: 'success', time: '0.1s'  },
  { name: '.htaccess write',       cmd: 'cat > build/.htaccess',       status: 'success', time: '0.1s'  },
  { name: 'Git commit',            cmd: 'git commit -m "..."',          status: 'success', time: '0.5s'  },
  { name: 'Git push (force)',      cmd: 'git push origin master:genspark_ai_developer --force', status: 'success', time: '4.7s' },
];

const TECH_STACK = [
  { label: 'React',      version: '18.3',   color: '#61dafb' },
  { label: 'TypeScript', version: '5.x',    color: '#3178c6' },
  { label: 'Vite',       version: '8.0',    color: '#646cff' },
  { label: 'MUI',        version: 'v6',     color: '#007fff' },
  { label: 'Recharts',   version: '2.x',    color: '#22c55e' },
  { label: 'PHP',        version: '8.2.30', color: '#8892bf' },
  { label: 'MariaDB',    version: '10.6.17',color: '#f59e0b' },
  { label: 'Varnish',    version: '6.0',    color: '#3b82f6' },
  { label: 'AlmaLinux',  version: '9.6',    color: '#ef4444' },
];

export default function CiCdPage() {
  return (
    <Box>
      {/* Header */}
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: 2 }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            CI/CD Pipeline
          </Typography>
          <Typography variant="body2" color="text.secondary">
            Build pipeline, deploy history, branch status — mounirtms/dashboard
          </Typography>
        </Box>
        <Button
          variant="outlined"
          startIcon={<GitHub />}
          href="https://github.com/mounirtms/dashboard/pull/3"
          target="_blank"
          rel="noopener noreferrer"
          sx={{ fontWeight: 700, textTransform: 'none', borderColor: 'rgba(255,255,255,0.15)' }}
        >
          PR #3 — Open
        </Button>
      </Box>

      {/* Build Stats KPIs */}
      <Grid container spacing={2} sx={{ mb: 3 }}>
        {[
          { label: 'Total Commits',    value: '94',       color: '#3b82f6', icon: <Commit sx={{ fontSize: 20 }} /> },
          { label: 'Active Branch',    value: 'genspark_ai_developer', color: '#8b5cf6', icon: <Code sx={{ fontSize: 20 }} /> },
          { label: 'Last Build',       value: 'ff9f94b4', color: '#22c55e', icon: <Build sx={{ fontSize: 20 }} /> },
          { label: 'Bundle Size',      value: '476 KB',   color: '#f59e0b', icon: <RocketLaunch sx={{ fontSize: 20 }} /> },
        ].map(stat => (
          <Grid size={{ xs: 6, md: 3 }} key={stat.label}>
            <Card>
              <CardContent sx={{ py: '16px !important' }}>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 1, color: stat.color }}>{stat.icon}
                  <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 700, textTransform: 'uppercase', letterSpacing: 0.8 }}>
                    {stat.label}
                  </Typography>
                </Box>
                <Typography variant="h6" sx={{ fontWeight: 900, color: stat.color, fontFamily: 'monospace' }}>{stat.value}</Typography>
              </CardContent>
            </Card>
          </Grid>
        ))}
      </Grid>

      <Grid container spacing={3} sx={{ mb: 3 }}>
        {/* Build Steps */}
        <Grid size={{ xs: 12, md: 6 }}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 2, display: 'flex', alignItems: 'center', gap: 1 }}>
                <Build sx={{ fontSize: 16, color: '#f59e0b' }} /> Build Pipeline Steps — Last Run
              </Typography>
              {BUILD_STEPS.map((step, i) => (
                <Box key={i} sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', py: 0.8, borderBottom: '1px solid rgba(255,255,255,0.04)' }}>
                  <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5 }}>
                    {step.status === 'success'
                      ? <CheckCircle sx={{ fontSize: 14, color: 'success.main' }} />
                      : <Cancel sx={{ fontSize: 14, color: 'error.main' }} />}
                    <Box>
                      <Typography variant="caption" sx={{ fontWeight: 600 }}>{step.name}</Typography>
                      <Typography variant="caption" sx={{ display: 'block', color: 'text.disabled', fontFamily: 'monospace', fontSize: '0.65rem' }}>{step.cmd}</Typography>
                    </Box>
                  </Box>
                  <Chip label={step.time} size="small" variant="outlined" sx={{ fontSize: '0.6rem', height: 18, fontFamily: 'monospace', color: 'text.disabled' }} />
                </Box>
              ))}
              <Box sx={{ mt: 2, p: 1.5, borderRadius: 1, background: 'rgba(34,197,94,0.06)', border: '1px solid rgba(34,197,94,0.15)' }}>
                <Typography variant="caption" sx={{ color: '#94a3b8' }}>
                  BUILD_STAMP: <code style={{ color: '#22c55e' }}>1783544141</code> &nbsp;·&nbsp;
                  Bundle: <code style={{ color: '#22c55e' }}>index-Dy37m8Cb.js</code> (476 KB) &nbsp;·&nbsp;
                  Chunks: <code style={{ color: '#22c55e' }}>9</code>
                </Typography>
              </Box>
            </CardContent>
          </Card>
        </Grid>

        {/* Branch Status */}
        <Grid size={{ xs: 12, md: 6 }}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 2, display: 'flex', alignItems: 'center', gap: 1 }}>
                <AccountTree sx={{ fontSize: 16, color: '#8b5cf6' }} /> Branch Status
              </Typography>
              {BRANCH_STATUS.map((b, i) => (
                <Box key={i} sx={{ p: 1.5, mb: 1.5, borderRadius: 1.5, border: '1px solid', borderColor: b.status === 'current' ? 'rgba(34,197,94,0.3)' : 'divider', background: b.status === 'current' ? 'rgba(34,197,94,0.04)' : 'transparent' }}>
                  <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 0.5 }}>
                    <Typography variant="caption" sx={{ fontWeight: 800, fontFamily: 'monospace', color: b.status === 'current' ? 'success.main' : 'text.primary' }}>
                      {b.name}
                    </Typography>
                    <Chip
                      label={b.status === 'current' ? 'ACTIVE' : b.status === 'behind' ? 'BEHIND' : 'AHEAD'}
                      size="small"
                      color={b.status === 'current' ? 'success' : b.status === 'behind' ? 'warning' : 'info'}
                      sx={{ fontSize: '0.6rem', height: 18, fontWeight: 700 }}
                    />
                  </Box>
                  <Typography variant="caption" sx={{ fontFamily: 'monospace', color: '#64748b', fontSize: '0.7rem' }}>SHA: {b.sha}</Typography>
                  <Typography variant="caption" sx={{ display: 'block', color: 'text.disabled', fontSize: '0.68rem', mt: 0.3 }}>{b.desc}</Typography>
                </Box>
              ))}
              <Alert severity="info" sx={{ mt: 1 }}>
                <Typography variant="caption">PR #3: <code>genspark_ai_developer → main</code> is open. Merge to promote to production.</Typography>
              </Alert>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {/* Deploy History */}
      <Card sx={{ mb: 3 }}>
        <CardContent>
          <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 2, display: 'flex', alignItems: 'center', gap: 1 }}>
            <Schedule sx={{ fontSize: 16, color: '#06b6d4' }} /> Deploy History (H1 2026 — Latest 6)
          </Typography>
          <TableContainer>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell sx={{ fontWeight: 600 }}>Commit</TableCell>
                  <TableCell sx={{ fontWeight: 600 }}>Message</TableCell>
                  <TableCell sx={{ fontWeight: 600 }}>Branch</TableCell>
                  <TableCell sx={{ fontWeight: 600, textAlign: 'center' }}>Status</TableCell>
                  <TableCell sx={{ fontWeight: 600 }}>Date</TableCell>
                  <TableCell sx={{ fontWeight: 600, textAlign: 'right' }}>Files</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {PIPELINE_RUNS.map((run, i) => (
                  <TableRow key={i} hover>
                    <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.75rem', color: '#8b5cf6', fontWeight: 700 }}>{run.id}</TableCell>
                    <TableCell sx={{ maxWidth: 380, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', fontSize: '0.78rem' }}>
                      {run.msg}
                    </TableCell>
                    <TableCell>
                      <Chip label={run.branch} size="small" sx={{ fontSize: '0.6rem', height: 18, fontFamily: 'monospace', color: '#94a3b8', borderColor: 'divider' }} variant="outlined" />
                    </TableCell>
                    <TableCell sx={{ textAlign: 'center' }}>
                      <CheckCircle sx={{ fontSize: 16, color: 'success.main' }} />
                    </TableCell>
                    <TableCell sx={{ fontSize: '0.75rem', color: 'text.disabled' }}>{run.date}</TableCell>
                    <TableCell sx={{ textAlign: 'right', fontFamily: 'monospace', fontSize: '0.75rem', color: '#64748b' }}>
                      {run.files.toLocaleString()}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </CardContent>
      </Card>

      {/* Tech Stack */}
      <Card>
        <CardContent>
          <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 2, display: 'flex', alignItems: 'center', gap: 1 }}>
            <Code sx={{ fontSize: 16, color: '#a78bfa' }} /> Tech Stack — ded701.inmotionhosting.com · AlmaLinux 9.6
          </Typography>
          <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1 }}>
            {TECH_STACK.map(t => (
              <Chip
                key={t.label}
                label={`${t.label} ${t.version}`}
                size="small"
                sx={{
                  fontWeight: 700,
                  fontSize: '0.7rem',
                  border: `1px solid ${t.color}44`,
                  color: t.color,
                  background: `${t.color}11`,
                }}
                variant="outlined"
              />
            ))}
          </Box>
          <Divider sx={{ my: 2 }} />
          <Box sx={{ display: 'flex', gap: 3, flexWrap: 'wrap' }}>
            <Box>
              <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 700, display: 'block', mb: 0.5 }}>Deployment Strategy</Typography>
              <Typography variant="caption">Manual push to <code style={{ color: '#8b5cf6' }}>genspark_ai_developer</code> → PR → merge to <code style={{ color: '#3b82f6' }}>main</code></Typography>
            </Box>
            <Box>
              <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 700, display: 'block', mb: 0.5 }}>Build Tool</Typography>
              <Typography variant="caption">Vite 8 + post-build.sh v3 (BUILD_STAMP injection)</Typography>
            </Box>
            <Box>
              <Typography variant="caption" sx={{ color: 'text.disabled', fontWeight: 700, display: 'block', mb: 0.5 }}>Hosting</Typography>
              <Typography variant="caption">InMotion Hosting · Apache + Varnish 6.0 · port 3307 MariaDB</Typography>
            </Box>
          </Box>
        </CardContent>
      </Card>
    </Box>
  );
}

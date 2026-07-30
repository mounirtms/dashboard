import { useState, useEffect, useCallback, useRef } from 'react';

const SECURITY_REFRESH_MS = 5 * 60_000; // 5 min auto-refresh for security scan
import {
  Box, Typography, Card, CardContent, Grid, Table, TableBody, TableCell, TableContainer,
  TableHead, TableRow, Paper, Chip, useTheme, Alert, AlertTitle, Button, Tabs, Tab, Select,
  MenuItem, FormControl, InputLabel, CircularProgress, Accordion, AccordionSummary,
  AccordionDetails, LinearProgress, Divider
} from '@mui/material';
import {
  PieChart, Pie, Cell, ResponsiveContainer, Tooltip, Legend
} from 'recharts';
import {
  Warning, PersonOff, VerifiedUser, Shield, BugReport,
} from '@mui/icons-material';
import { useCloudflareData } from '../hooks/useCloudflareData';
import LoadingState from '../components/common/LoadingState';
import StatCard from '../components/common/StatCard';
import StatusBadge from '../components/common/StatusBadge';
import { formatNumber } from '../utils/formatters';
import {
  fetchSecurityScan, runSecurityScan,
  fetchSecurityHarden, runSecurityHarden,
  fetchEcomscan, runEcomscan,
  type SecurityScanResult, type SecurityHardenResult, type SecurityFinding,
  type EcomscanResult, type EcomscanFinding
} from '../api/monitor';

// ── Real scan data summary (from /reports/ecomscan_latest.json + security_scan_latest.json)
// Scanned: 2026-07-11 | EcomScan: 125 issues (all vulnerabilities, 0 malware)
// Security scan: 36 issues — 28 critical (phpinfo, world-writable files, PHP in static dir),
//                             7 high (suspicious JS patterns, .git exposed, backup files),
//                             1 medium (modified core files)
const SCAN_SUMMARY_STATIC = {
  ecomscan: { total: 125, malware: 0, vulnerabilities: 125, critical_confidence: 125, scan_date: '2026-07-11' },
  security:  { total: 36,  critical: 28, high: 7, medium: 1, low: 0,                 scan_date: '2026-07-11' },
};

const ACCOUNTS = [
  { value: '', label: 'All Accounts' },
  { value: 'technadminy7', label: 'technadminy7 (Production — 11 modules)' },
  { value: 'dev', label: 'dev (Dev — 44 findings across 4 deployments)' },
  { value: 'tsdnd', label: 'tsdnd (TSDND — 12 modules ×6 deployments = 70 findings)' },
];

const severityColor: Record<string, 'error' | 'warning' | 'info' | 'success'> = {
  CRITICAL: 'error',
  HIGH: 'warning',
  MEDIUM: 'info',
  LOW: 'success',
};

function CloudflareTab() {
  const theme = useTheme();
  const { data, loading, error } = useCloudflareData();

  if (loading) return <LoadingState />;
  if (error) return <Alert severity="error" sx={{ mb: 2 }}>Error: {error}</Alert>;
  if (!data) return null;

  const totals = data.analytics_totals;
  const fw = data.firewall || { blocked: 0, challenged: 0, total: 0, events: [] };
  const threatData = (data.threat_types || []).map((t) => ({ name: t.type || 'Unknown', value: t.count }));
  const wafStatus = data.settings?.waf === 'on' ? 'success' : 'error';
  const gridColor = `${theme.palette.divider}99`;
  const borderColor = `${theme.palette.divider}4d`;

  // Real-time security posture derived from CF zone settings
  const s = data.settings || {};
  const securityChecks = [
    { label: 'SSL/TLS Mode',       val: s.ssl,                status: ['strict','full'].includes(s.ssl) ? 'ok' : 'warn' },
    { label: 'Always HTTPS',       val: s.always_use_https,   status: s.always_use_https === 'on' ? 'ok' : 'warn' },
    { label: 'Security Level',     val: s.security_level,     status: ['high','under_attack'].includes(s.security_level) ? 'ok' : 'warn' },
    { label: 'WAF',                val: s.waf,                status: s.waf === 'on' ? 'ok' : 'warn' },
    { label: 'Min TLS Version',    val: s.min_tls_version,    status: ['1.2','1.3'].includes(s.min_tls_version) ? 'ok' : 'warn' },
    { label: 'Hotlink Protection', val: s.hotlink_protection, status: s.hotlink_protection === 'on' ? 'ok' : 'info' },
    { label: 'Email Obfuscation',  val: s.email_obfuscation,  status: s.email_obfuscation === 'on' ? 'ok' : 'info' },
    { label: 'IP Geolocation',     val: s.ip_geolocation,     status: s.ip_geolocation === 'on' ? 'ok' : 'info' },
  ];
  const secOk = securityChecks.filter(c => c.status === 'ok').length;
  const secWarn = securityChecks.filter(c => c.status === 'warn').length;

  return (
    <Box>
      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 12, sm: 3 }}>
          <StatCard label="Total Threats (7d)" value={formatNumber(totals.threats)} color="error" />
        </Grid>
        <Grid size={{ xs: 12, sm: 3 }}>
          <StatCard label="Blocked" value={formatNumber(fw.blocked)} color="error" />
        </Grid>
        <Grid size={{ xs: 12, sm: 3 }}>
          <StatCard label="Challenged" value={formatNumber(fw.challenged)} color="warning" />
        </Grid>
        <Grid size={{ xs: 12, sm: 3 }}>
          <Card>
            <CardContent>
              <Typography variant="caption" color="textSecondary" sx={{ display: 'block', mb: 0.5 }}>WAF Status</Typography>
              <StatusBadge label={data.settings.waf?.toUpperCase() || 'OFF'} color={wafStatus} />
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {/* ── Real-time Security Posture from CF Zone Settings ── */}
      <Card sx={{ mb: 3, borderColor: secWarn > 0 ? 'warning.main' : 'success.main', borderWidth: 1, borderStyle: 'solid' }}>
        <CardContent>
          <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
            <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>
              Security Posture — technostationery.com (Live CF Settings)
            </Typography>
            <Chip
              label={`${secOk}/${securityChecks.length} OK${secWarn > 0 ? ` · ${secWarn} warnings` : ''}`}
              size="small"
              color={secWarn > 0 ? 'warning' : 'success'}
              sx={{ fontWeight: 700 }}
            />
          </Box>
          <Grid container spacing={1}>
            {securityChecks.map(chk => (
              <Grid size={{ xs: 6, sm: 3 }} key={chk.label}>
                <Box sx={{ p: 1, borderRadius: 1, border: '1px solid', borderColor: 'divider', display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 1 }}>
                  <Typography variant="caption" color="text.secondary" sx={{ fontSize: '0.68rem' }}>{chk.label}</Typography>
                  <Chip
                    label={String(chk.val ?? 'N/A').toUpperCase()}
                    size="small"
                    color={chk.status === 'ok' ? 'success' : chk.status === 'warn' ? 'warning' : 'default'}
                    sx={{ fontSize: '0.6rem', height: 18, fontWeight: 700 }}
                  />
                </Box>
              </Grid>
            ))}
          </Grid>
          {s.min_tls_version === '1.0' && (
            <Alert severity="warning" sx={{ mt: 2, py: 0.5 }}>
              <strong>TLS 1.0 is enabled</strong> — upgrade min_tls_version to 1.2 in Cloudflare SSL/TLS settings for PCI-DSS compliance.
            </Alert>
          )}
          {s.waf === 'off' && (
            <Alert severity="error" sx={{ mt: 1, py: 0.5 }}>
              <strong>WAF is disabled</strong> — enable the Cloudflare WAF to block SQL injection, XSS and other application-layer attacks.
            </Alert>
          )}
        </CardContent>
      </Card>

      <Grid container spacing={3}>
        {threatData.length > 0 && (
          <Grid size={{ xs: 12, md: 5 }}>
            <Card>
              <CardContent>
                <Typography variant="subtitle2" sx={{ mb: 2, fontWeight: 600 }}>Threat Types (7d)</Typography>
                <ResponsiveContainer width="100%" height={280}>
                  <PieChart>
                    <Pie data={threatData} cx="50%" cy="50%" outerRadius={90} dataKey="value"
                      label={({ name, percent }) => `${name} (${((percent ?? 0) * 100).toFixed(0)}%)`}>
                      {threatData.map((_, i) => (
                        <Cell key={i} fill={theme.palette[i % 2 === 0 ? 'error' : 'warning'].main} />
                      ))}
                    </Pie>
                    <Tooltip contentStyle={{ backgroundColor: theme.palette.background.paper, border: `1px solid ${theme.palette.divider}`, borderRadius: 10, color: theme.palette.text.primary }} />
                  </PieChart>
                </ResponsiveContainer>
              </CardContent>
            </Card>
          </Grid>
        )}

        <Grid size={{ xs: 12, md: threatData.length > 0 ? 7 : 12 }}>
          <Card>
            <CardContent>
              <Typography variant="subtitle2" sx={{ mb: 2, fontWeight: 600 }}>Recent Firewall Events</Typography>
              {fw.events.length === 0 ? (
                <Typography variant="body2" color="textSecondary">No recent firewall events</Typography>
              ) : (
                <TableContainer component={Paper} sx={{ backgroundColor: 'transparent', boxShadow: 'none' }}>
                  <Table size="small">
                    <TableHead>
                      <TableRow sx={{ borderBottom: 1, borderColor: gridColor }}>
                        <TableCell sx={{ fontWeight: 600, color: 'text.secondary' }}>Action</TableCell>
                        <TableCell sx={{ fontWeight: 600, color: 'text.secondary' }}>Source</TableCell>
                        <TableCell sx={{ fontWeight: 600, color: 'text.secondary' }}>Rule ID</TableCell>
                        <TableCell sx={{ fontWeight: 600, color: 'text.secondary' }}>Time</TableCell>
                      </TableRow>
                    </TableHead>
                    <TableBody>
                      {fw.events.slice(0, 10).map((event, i) => (
                        <TableRow key={i} sx={{ borderBottom: 1, borderColor: borderColor }}>
                          <TableCell>
                            <Chip label={event.action} size="small"
                              color={event.action === 'block' ? 'error' : 'warning'} variant="outlined" />
                          </TableCell>
                          <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>{event.source || '-'}</TableCell>
                          <TableCell sx={{ fontSize: '0.8rem', color: 'text.secondary' }}>{event.rule_id || '-'}</TableCell>
                          <TableCell sx={{ fontSize: '0.8rem', color: 'text.secondary' }}>{event.datetime?.slice(0, 16).replace('T', ' ') || '-'}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </TableContainer>
              )}
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </Box>
  );
}

function MalwareScanTab() {
  const [scanResult, setScanResult] = useState<SecurityScanResult | null>(null);
  const [loading, setLoading] = useState(true);
  const [scanning, setScanning] = useState(false);
  const [account, setAccount] = useState('');

  const scanTimerRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const loadScan = useCallback(async () => {
    try {
      const data = await fetchSecurityScan();
      setScanResult(data);
    } catch (err) {
      setScanResult({ status: 'error', message: String(err) });
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadScan();
    scanTimerRef.current = setInterval(loadScan, SECURITY_REFRESH_MS);
    return () => { if (scanTimerRef.current) clearInterval(scanTimerRef.current); };
  }, [loadScan]);

  const handleRunScan = async () => {
    setScanning(true);
    try {
      const result = await runSecurityScan(account || undefined);
      setScanResult(result);
    } catch (err) {
      setScanResult({ status: 'error', message: String(err) });
    } finally {
      setScanning(false);
    }
  };

  if (loading) return <CircularProgress size={24} />;

  const findings = (scanResult?.findings || []).sort((a, b) => {
    const order = { CRITICAL: 0, HIGH: 1, MEDIUM: 2, LOW: 3 };
    return (order[a.severity] ?? 4) - (order[b.severity] ?? 4);
  });

  return (
    <Box>
      <Box sx={{ display: 'flex', gap: 2, alignItems: 'center', mb: 3 }}>
        <FormControl size="small" sx={{ minWidth: 200 }}>
          <InputLabel>Account</InputLabel>
          <Select value={account} label="Account" onChange={(e) => setAccount(e.target.value)}>
            {ACCOUNTS.map((a) => (
              <MenuItem key={a.value} value={a.value}>{a.label}</MenuItem>
            ))}
          </Select>
        </FormControl>
        <Button variant="contained" color="error" onClick={handleRunScan} disabled={scanning}>
          {scanning ? <><CircularProgress size={16} sx={{ mr: 1 }} /> Scanning...</> : 'Run Malware Scan'}
        </Button>
        {scanResult?.scan_time && (
          <Typography variant="caption" color="textSecondary">
            Last scan: {new Date(scanResult.scan_time).toLocaleString()}
          </Typography>
        )}
      </Box>

      {scanning && <LinearProgress sx={{ mb: 2 }} />}

      {scanResult?.summary && (
        <Grid container spacing={2} sx={{ mb: 3 }}>
          <Grid size={{ xs: 6, sm: 2.4 }}>
            <StatCard label="Total Issues" value={String(scanResult.summary.total_issues)} color={scanResult.summary.total_issues > 0 ? 'error' : 'success'} />
          </Grid>
          <Grid size={{ xs: 6, sm: 2.4 }}>
            <StatCard label="Critical" value={String(scanResult.summary.critical)} color="error" />
          </Grid>
          <Grid size={{ xs: 6, sm: 2.4 }}>
            <StatCard label="High" value={String(scanResult.summary.high)} color="warning" />
          </Grid>
          <Grid size={{ xs: 6, sm: 2.4 }}>
            <StatCard label="Medium" value={String(scanResult.summary.medium)} color="info" />
          </Grid>
          <Grid size={{ xs: 6, sm: 2.4 }}>
            <StatCard label="Low" value={String(scanResult.summary.low)} color="success" />
          </Grid>
        </Grid>
      )}

      {scanResult?.status === 'no_scan' && (
        <Alert severity="info">{scanResult.message}</Alert>
      )}

      {scanResult?.status === 'error' && (
        <Alert severity="error">{scanResult.message || 'Scan failed'}</Alert>
      )}

      {findings.length > 0 && (
        <TableContainer component={Paper}>
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell sx={{ fontWeight: 600 }}>Severity</TableCell>
                <TableCell sx={{ fontWeight: 600 }}>Account</TableCell>
                <TableCell sx={{ fontWeight: 600 }}>Category</TableCell>
                <TableCell sx={{ fontWeight: 600 }}>Finding</TableCell>
                <TableCell sx={{ fontWeight: 600 }}>Detail</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {findings.map((f: SecurityFinding, i: number) => (
                <TableRow key={i}>
                  <TableCell>
                    <Chip label={f.severity} size="small" color={severityColor[f.severity] || 'info'} variant="outlined" />
                  </TableCell>
                  <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>{f.account}</TableCell>
                  <TableCell>{f.category}</TableCell>
                  <TableCell>{f.title}</TableCell>
                  <TableCell sx={{ fontSize: '0.8rem', maxWidth: 400, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                    {f.detail}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </TableContainer>
      )}

      {scanResult?.status === 'complete' && findings.length === 0 && (
        <Alert severity="success">No security issues found. All accounts are clean.</Alert>
      )}
    </Box>
  );
}

function HardeningTab() {
  const [hardenResult, setHardenResult] = useState<SecurityHardenResult | null>(null);
  const [loading, setLoading] = useState(true);
  const [running, setRunning] = useState(false);
  const [account, setAccount] = useState('');

  const loadStatus = useCallback(async () => {
    try {
      const data = await fetchSecurityHarden();
      setHardenResult(data);
    } catch (err) {
      setHardenResult({ status: 'error', message: String(err) });
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { loadStatus(); }, [loadStatus]);

  const handleRun = async (checkOnly: boolean) => {
    setRunning(true);
    try {
      const result = await runSecurityHarden(account || undefined, checkOnly);
      setHardenResult(result);
    } catch (err) {
      setHardenResult({ status: 'error', message: String(err) });
    } finally {
      setRunning(false);
    }
  };

  if (loading) return <CircularProgress size={24} />;

  return (
    <Box>
      <Box sx={{ display: 'flex', gap: 2, alignItems: 'center', mb: 3, flexWrap: 'wrap' }}>
        <FormControl size="small" sx={{ minWidth: 200 }}>
          <InputLabel>Account</InputLabel>
          <Select value={account} label="Account" onChange={(e) => setAccount(e.target.value)}>
            {ACCOUNTS.map((a) => (
              <MenuItem key={a.value} value={a.value}>{a.label}</MenuItem>
            ))}
          </Select>
        </FormControl>
        <Button variant="outlined" onClick={() => handleRun(true)} disabled={running}>
          {running ? 'Checking...' : 'Check Only'}
        </Button>
        <Button variant="contained" color="warning" onClick={() => handleRun(false)} disabled={running}>
          {running ? <><CircularProgress size={16} sx={{ mr: 1 }} /> Running...</> : 'Apply Hardening'}
        </Button>
        {hardenResult?.last_run && (
          <Typography variant="caption" color="textSecondary">
            Last run: {hardenResult.last_run}
          </Typography>
        )}
      </Box>

      {running && <LinearProgress sx={{ mb: 2 }} />}

      {hardenResult?.status === 'never_run' && (
        <Alert severity="info" sx={{ mb: 2 }}>{hardenResult.message}</Alert>
      )}

      {hardenResult?.issues_found !== undefined && (
        <Grid container spacing={2} sx={{ mb: 3 }}>
          <Grid size={{ xs: 6 }}>
            <StatCard label="Issues Found" value={String(hardenResult.issues_found)} color={hardenResult.issues_found > 0 ? 'warning' : 'success'} />
          </Grid>
          <Grid size={{ xs: 6 }}>
            <StatCard label="Issues Fixed" value={String(hardenResult.issues_fixed)} color="success" />
          </Grid>
        </Grid>
      )}

      {hardenResult?.output && (
        <Accordion defaultExpanded>
          <AccordionSummary expandIcon={<span>&#9660;</span>}>
            <Typography variant="subtitle2" sx={{ fontWeight: 600 }}>Hardening Output</Typography>
          </AccordionSummary>
          <AccordionDetails>
            <Paper sx={{ p: 2, backgroundColor: 'background.default', maxHeight: 500, overflow: 'auto' }}>
              <pre style={{ margin: 0, fontSize: '0.75rem', whiteSpace: 'pre-wrap', fontFamily: 'monospace' }}>
                {hardenResult.output}
              </pre>
            </Paper>
          </AccordionDetails>
        </Accordion>
      )}
    </Box>
  );
}

function EcomscanTab() {
  const [result, setResult] = useState<EcomscanResult | null>(null);
  const [loading, setLoading] = useState(true);
  const [scanning, setScanning] = useState(false);
  const [account, setAccount] = useState('');

  const loadResult = useCallback(async () => {
    try {
      const data = await fetchEcomscan();
      setResult(data);
    } catch (err) {
      setResult({ status: 'error', message: String(err) });
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { loadResult(); }, [loadResult]);

  const handleRun = async () => {
    setScanning(true);
    try {
      const data = await runEcomscan(account || undefined);
      setResult(data);
    } catch (err) {
      setResult({ status: 'error', message: String(err) });
    } finally {
      setScanning(false);
    }
  };

  if (loading) return <CircularProgress size={24} />;

  const findings = (result?.findings || []).sort((a, b) => b.confidence - a.confidence);

  return (
    <Box>
      <Box sx={{ display: 'flex', gap: 2, alignItems: 'center', mb: 3 }}>
        <FormControl size="small" sx={{ minWidth: 200 }}>
          <InputLabel>Account</InputLabel>
          <Select value={account} label="Account" onChange={(e) => setAccount(e.target.value)}>
            {ACCOUNTS.map((a) => (
              <MenuItem key={a.value} value={a.value}>{a.label}</MenuItem>
            ))}
          </Select>
        </FormControl>
        <Button variant="contained" color="secondary" onClick={handleRun} disabled={scanning}>
          {scanning ? <><CircularProgress size={16} sx={{ mr: 1 }} /> Scanning...</> : 'Run eComscan'}
        </Button>
        {result?.scan_time && (
          <Typography variant="caption" color="textSecondary">
            Last scan: {new Date(result.scan_time).toLocaleString()}
          </Typography>
        )}
      </Box>

      {scanning && <LinearProgress sx={{ mb: 2 }} />}

      {result?.summary && (
        <>
          <Grid container spacing={2} sx={{ mb: 2 }}>
            <Grid size={{ xs: 6, sm: 3 }}>
              <StatCard label="Total Issues" value={String(result.summary.total_issues)} color={result.summary.total_issues > 0 ? 'error' : 'success'} />
            </Grid>
            <Grid size={{ xs: 6, sm: 3 }}>
              <StatCard label="Malware" value={String(result.summary.malware)} color="error" />
            </Grid>
            <Grid size={{ xs: 6, sm: 3 }}>
              <StatCard label="Vulnerabilities" value={String(result.summary.vulnerabilities)} color="warning" />
            </Grid>
            <Grid size={{ xs: 6, sm: 3 }}>
              <StatCard label="High Confidence" value={String(result.summary.critical_confidence)} color="error" />
            </Grid>
          </Grid>
          {result.summary.total_issues >= 70 && (
            <Alert severity="warning" sx={{ mb: 2 }}>
              <strong>125 vulnérabilités Amasty détectées (EcomScan — {SCAN_SUMMARY_STATIC.ecomscan.scan_date}):</strong>{' '}
              tsdnd: 70 findings (12 modules × 6 déploiements), dev: 44, technadminy7: 11.
              Tous les modules Amasty nécessitent une mise à jour — voir colonne &ldquo;description&rdquo; pour la version cible.
              Abonnement EcomScan: <strong>Auto-renouvelé ✓</strong>
            </Alert>
          )}
        </>
      )}

      {result?.status === 'no_scan' && (
        <Alert severity="info">{result.message}</Alert>
      )}

      {findings.length > 0 && (
        <TableContainer component={Paper}>
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell sx={{ fontWeight: 600 }}>Class</TableCell>
                <TableCell sx={{ fontWeight: 600 }}>Account</TableCell>
                <TableCell sx={{ fontWeight: 600 }}>Module / Finding</TableCell>
                <TableCell sx={{ fontWeight: 600 }}>Confidence</TableCell>
                <TableCell sx={{ fontWeight: 600 }}>Path / Detail</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {findings.map((f: EcomscanFinding, i: number) => (
                <TableRow key={i}>
                  <TableCell>
                    <Chip label={f.class} size="small"
                      color={f.class === 'malware' ? 'error' : 'warning'} variant="outlined" />
                  </TableCell>
                  <TableCell sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>{f.account}</TableCell>
                  <TableCell>
                    <Typography variant="body2" sx={{ fontWeight: 500 }}>{f.name}</Typography>
                    {f.description && <Typography variant="caption" color="textSecondary">{f.description}</Typography>}
                  </TableCell>
                  <TableCell>{f.confidence}%</TableCell>
                  <TableCell sx={{ fontSize: '0.75rem', maxWidth: 350, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                    {f.path || f.snippet}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </TableContainer>
      )}

      {result?.status === 'complete' && findings.length === 0 && (
        <Alert severity="success">No security issues found by eComscan. All stores are clean.</Alert>
      )}
    </Box>
  );
}

function AuthTab() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetch('/api/auth-status.php?action=status')
      .then(r => r.json())
      .then(setData).catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <LoadingState />;
  if (!data) return <Alert severity="error">Failed to load auth data</Alert>;

  return (
    <Box>
      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 12, sm: 3 }}>
          <Card>
            <CardContent>
              <Typography variant="caption" color="textSecondary" sx={{ display: 'block', mb: 0.5 }}>Current Session</Typography>
              <Typography variant="body1" sx={{ fontWeight: 700 }}>{data.session.username}</Typography>
              <Chip label={data.session.role.toUpperCase()} size="small" color={data.session.role === 'admin' ? 'error' : 'primary'} sx={{ mt: 0.5, fontWeight: 700, fontSize: '0.65rem' }} />
            </CardContent>
          </Card>
        </Grid>
        <Grid size={{ xs: 12, sm: 3 }}>
          <Card>
            <CardContent>
              <Typography variant="caption" color="textSecondary" sx={{ display: 'block', mb: 0.5 }}>Session Age</Typography>
              <Typography variant="body1" sx={{ fontWeight: 700 }}>{data.session.session_age_human}</Typography>
              <Typography variant="caption" color="textSecondary">Regenerates every 4h</Typography>
            </CardContent>
          </Card>
        </Grid>
        <Grid size={{ xs: 12, sm: 3 }}>
          <StatCard label="Active Users" value={data.users.active} color="success" />
        </Grid>
        <Grid size={{ xs: 12, sm: 3 }}>
          <StatCard label="Total Users" value={data.users.total} color="info" />
        </Grid>
      </Grid>

      <Grid container spacing={2}>
        <Grid size={{ xs: 12, md: 5 }}>
          <Card>
            <CardContent>
              <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1 }}>Users by Role</Typography>
              {(data.users.by_role || []).map((r: any) => (
                <Box key={r.role} sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', py: 0.5 }}>
                  <Chip label={r.role.toUpperCase()} size="small" color={r.role === 'admin' ? 'error' : r.role === 'editor' ? 'primary' : 'default'} sx={{ fontWeight: 700, fontSize: '0.65rem' }} />
                  <Typography variant="body2" sx={{ fontWeight: 600 }}>{r.count}</Typography>
                </Box>
              ))}
            </CardContent>
          </Card>
        </Grid>

        <Grid size={{ xs: 12, md: 7 }}>
          <Card>
            <CardContent>
              <Typography variant="subtitle2" sx={{ fontWeight: 700, mb: 1 }}>Recent Logins</Typography>
              <TableContainer component={Paper} sx={{ backgroundColor: 'transparent', border: 'none' }}>
                <Table size="small">
                  <TableHead>
                    <TableRow>
                      <TableCell sx={{ fontWeight: 700, fontSize: '0.75rem' }}>User</TableCell>
                      <TableCell sx={{ fontWeight: 700, fontSize: '0.75rem' }}>Name</TableCell>
                      <TableCell sx={{ fontWeight: 700, fontSize: '0.75rem' }}>Last Login</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {(data.recent_logins || []).map((login: any, i: number) => (
                      <TableRow key={i} sx={{ '& td': { borderColor: 'rgba(255,255,255,0.06)' } }}>
                        <TableCell><Typography variant="body2" sx={{ fontWeight: 600, fontFamily: 'monospace' }}>{login.username}</Typography></TableCell>
                        <TableCell><Typography variant="caption">{login.full_name || '—'}</Typography></TableCell>
                        <TableCell><Typography variant="caption" sx={{ fontFamily: 'monospace' }}>{login.last_login ? new Date(login.last_login).toLocaleString() : 'Never'}</Typography></TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </TableContainer>
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </Box>
  );
}

export default function SecurityPage() {
  const [tab, setTab] = useState(0);

  return (
    <Box>
      {/* ══════════════════════════════════════════════════════════════
          🔴 CRITICAL — ACCOUNT CANCELLATION WARNING
          Dashboard users + Dev users cancelled next month (Aug 2026)
          ══════════════════════════════════════════════════════════════ */}
      <Alert
        severity="error"
        icon={<Warning sx={{ fontSize: 22 }} />}
        sx={{
          mb: 2,
          border: '2px solid #ef4444',
          backgroundColor: 'rgba(239,68,68,0.07)',
          '& .MuiAlert-message': { width: '100%' },
        }}
      >
        <AlertTitle sx={{ fontWeight: 900, fontSize: '0.95rem', mb: 0.5 }}>
          ⚠️ ANNULATION PROCHAINE — Comptes Dashboard &amp; Développeurs (Août 2026)
        </AlertTitle>
        <Typography variant="body2" sx={{ mb: 1.5 }}>
          Les <strong>comptes utilisateurs Dashboard</strong> et <strong>comptes Développeurs</strong> seront
          annulés le mois prochain. Exportez toutes les données, finalisez les revues de code et assurez le
          transfert de connaissances avant la date limite.
        </Typography>
        <Box sx={{ display: 'flex', gap: 1, flexWrap: 'wrap' }}>
          <Chip icon={<PersonOff sx={{ fontSize: 14 }} />} label="Dashboard Users — Annulés Août 2026"
            color="error" size="small" sx={{ fontWeight: 700, fontSize: '0.7rem' }} />
          <Chip icon={<PersonOff sx={{ fontSize: 14 }} />} label="Dev Users — Accès révoqué Août 2026"
            color="error" size="small" sx={{ fontWeight: 700, fontSize: '0.7rem' }} />
          <Chip icon={<VerifiedUser sx={{ fontSize: 14 }} />} label="EcomScan — Auto-renouvelé ✓"
            color="success" size="small" sx={{ fontWeight: 700, fontSize: '0.7rem' }} />
        </Box>
      </Alert>

      {/* ── Real Scan Summary Banner ── */}
      <Grid container spacing={2} sx={{ mb: 2 }}>
        {/* EcomScan summary */}
        <Grid size={{ xs: 12, md: 6 }}>
          <Card sx={{ border: '1px solid rgba(249,115,22,0.35)', backgroundColor: 'rgba(249,115,22,0.04)' }}>
            <CardContent sx={{ py: '14px !important' }}>
              <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 1 }}>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                  <BugReport sx={{ fontSize: 18, color: '#f97316' }} />
                  <Typography variant="subtitle2" sx={{ fontWeight: 800 }}>EcomScan Pro — Résultats Réels</Typography>
                </Box>
                <Chip label={`Scan: ${SCAN_SUMMARY_STATIC.ecomscan.scan_date}`} size="small"
                  sx={{ fontSize: '0.65rem', height: 18, color: 'text.disabled' }} />
              </Box>
              <Box sx={{ display: 'flex', gap: 3 }}>
                <Box>
                  <Typography variant="h4" sx={{ fontWeight: 900, color: '#f97316', fontFamily: 'monospace', lineHeight: 1 }}>
                    {SCAN_SUMMARY_STATIC.ecomscan.total}
                  </Typography>
                  <Typography variant="caption" sx={{ color: 'text.disabled' }}>Total Issues</Typography>
                </Box>
                <Box>
                  <Typography variant="h4" sx={{ fontWeight: 900, color: '#22c55e', fontFamily: 'monospace', lineHeight: 1 }}>
                    {SCAN_SUMMARY_STATIC.ecomscan.malware}
                  </Typography>
                  <Typography variant="caption" sx={{ color: 'text.disabled' }}>Malware</Typography>
                </Box>
                <Box>
                  <Typography variant="h4" sx={{ fontWeight: 900, color: '#f97316', fontFamily: 'monospace', lineHeight: 1 }}>
                    {SCAN_SUMMARY_STATIC.ecomscan.vulnerabilities}
                  </Typography>
                  <Typography variant="caption" sx={{ color: 'text.disabled' }}>Vulnérabilités</Typography>
                </Box>
                <Box>
                  <Typography variant="h4" sx={{ fontWeight: 900, color: '#f97316', fontFamily: 'monospace', lineHeight: 1 }}>
                    {SCAN_SUMMARY_STATIC.ecomscan.critical_confidence}%
                  </Typography>
                  <Typography variant="caption" sx={{ color: 'text.disabled' }}>Confiance</Typography>
                </Box>
              </Box>
              <Typography variant="caption" sx={{ color: 'text.disabled', fontSize: '0.65rem', mt: 0.5, display: 'block' }}>
                Tous Amasty modules obsolètes — technadminy7: 11, dev: 44, tsdnd: 70 findings
              </Typography>
            </CardContent>
          </Card>
        </Grid>

        {/* Security scan summary */}
        <Grid size={{ xs: 12, md: 6 }}>
          <Card sx={{ border: '1px solid rgba(239,68,68,0.35)', backgroundColor: 'rgba(239,68,68,0.04)' }}>
            <CardContent sx={{ py: '14px !important' }}>
              <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 1 }}>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                  <Shield sx={{ fontSize: 18, color: '#ef4444' }} />
                  <Typography variant="subtitle2" sx={{ fontWeight: 800 }}>Security Scan — Menaces Détectées</Typography>
                </Box>
                <Chip label={`Scan: ${SCAN_SUMMARY_STATIC.security.scan_date}`} size="small"
                  sx={{ fontSize: '0.65rem', height: 18, color: 'text.disabled' }} />
              </Box>
              <Box sx={{ display: 'flex', gap: 3 }}>
                <Box>
                  <Typography variant="h4" sx={{ fontWeight: 900, color: '#ef4444', fontFamily: 'monospace', lineHeight: 1 }}>
                    {SCAN_SUMMARY_STATIC.security.total}
                  </Typography>
                  <Typography variant="caption" sx={{ color: 'text.disabled' }}>Total Issues</Typography>
                </Box>
                <Box>
                  <Typography variant="h4" sx={{ fontWeight: 900, color: '#b91c1c', fontFamily: 'monospace', lineHeight: 1 }}>
                    {SCAN_SUMMARY_STATIC.security.critical}
                  </Typography>
                  <Typography variant="caption" sx={{ color: 'text.disabled' }}>Critiques</Typography>
                </Box>
                <Box>
                  <Typography variant="h4" sx={{ fontWeight: 900, color: '#f97316', fontFamily: 'monospace', lineHeight: 1 }}>
                    {SCAN_SUMMARY_STATIC.security.high}
                  </Typography>
                  <Typography variant="caption" sx={{ color: 'text.disabled' }}>Hauts</Typography>
                </Box>
                <Box>
                  <Typography variant="h4" sx={{ fontWeight: 900, color: '#eab308', fontFamily: 'monospace', lineHeight: 1 }}>
                    {SCAN_SUMMARY_STATIC.security.medium}
                  </Typography>
                  <Typography variant="caption" sx={{ color: 'text.disabled' }}>Moyens</Typography>
                </Box>
              </Box>
              <Typography variant="caption" sx={{ color: '#fca5a5', fontSize: '0.65rem', mt: 0.5, display: 'block', fontWeight: 600 }}>
                ⚠️ phpinfo() exposé, fichiers world-writable (971!), .git exposé, JS suspect
              </Typography>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {/* ── Page header + tabs ── */}
      <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: 1, mb: 1 }}>
        <Box>
          <Typography variant="h5" sx={{ fontWeight: 800, letterSpacing: '-0.02em' }}>Security Center</Typography>
          <Typography variant="body2" color="textSecondary">
            Cloudflare WAF · Malware Scan · Sansec EcomScan · Hardening · Auth &amp; Sessions
          </Typography>
        </Box>
      </Box>

      <Divider sx={{ mb: 2 }} />

      <Tabs value={tab} onChange={(_, v) => setTab(v)} sx={{ mb: 3 }}>
        <Tab label="Cloudflare WAF" />
        <Tab label="Malware Scan" />
        <Tab label="eComscan" />
        <Tab label="Hardening" />
        <Tab label="Auth & Sessions" />
      </Tabs>

      {tab === 0 && <CloudflareTab />}
      {tab === 1 && <MalwareScanTab />}
      {tab === 2 && <EcomscanTab />}
      {tab === 3 && <HardeningTab />}
      {tab === 4 && <AuthTab />}
    </Box>
  );
}

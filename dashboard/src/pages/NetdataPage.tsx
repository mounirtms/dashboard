import { useCallback, useEffect, useRef, useState } from 'react';
import {
  Box, Grid, Card, CardContent, Typography, Chip, Alert, IconButton, Tooltip,
  useTheme, LinearProgress, Divider,
} from '@mui/material';
import {
  Refresh, Memory, Storage, Speed, Public, Dns, Timeline,
  CheckCircle, Error as ErrorIcon, MonitorHeart,
} from '@mui/icons-material';
import {
  AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip as RTooltip,
  ResponsiveContainer, LineChart, Line,
} from 'recharts';
import apiClient from '../api/client';
import LoadingState from '../components/common/LoadingState';
import StatCard from '../components/common/StatCard';

const ALLOWED_CHARTS = [
  'system.cpu',
  'system.ram',
  'system.load',
  'disk_space./',
  'net.enp1s0f0',
  'system.processes',
  'mem.available',
  'cpu.cpu0',
];

const REFRESH_MS = 15000;

type ChartSerie = {
  chart: string;
  labels: string[];
  data: number[][];
};

type SerieMeta = {
  title: string;
  icon: any;
  color: 'primary' | 'success' | 'info' | 'warning';
  dims: string[]; // dimensions to plot
  unit: (v: number) => string;
};

const SERIES_META: Record<string, SerieMeta> = {
  'system.cpu': { title: 'CPU Utilization', icon: Speed, color: 'primary', dims: ['user', 'system', 'iowait'], unit: (v) => `${v.toFixed(1)}%` },
  'system.ram': { title: 'Memory Usage (MB)', icon: Memory, color: 'success', dims: ['used', 'cached'], unit: (v) => `${Math.round(v)} MB` },
  'system.load': { title: 'System Load', icon: Dns, color: 'info', dims: ['load1', 'load5', 'load15'], unit: (v) => v.toFixed(2) },
  'disk_space./': { title: 'Disk Usage (GB, /)', icon: Storage, color: 'warning', dims: ['avail', 'used'], unit: (v) => `${v.toFixed(1)} GB` },
  'net.enp1s0f0': { title: 'Network Throughput (kbps)', icon: Public, color: 'primary', dims: ['received', 'sent'], unit: (v) => `${Math.round(v)} kb/s` },
  'system.processes': { title: 'Processes', icon: Timeline, color: 'info', dims: ['running', 'blocked'], unit: (v) => `${Math.round(v)}` },
  'mem.available': { title: 'Available Memory (MB)', icon: Memory, color: 'success', dims: ['avail'], unit: (v) => `${Math.round(v)} MB` },
  'cpu.cpu0': { title: 'CPU Core 0', icon: Speed, color: 'warning', dims: ['user', 'system'], unit: (v) => `${v.toFixed(1)}%` },
};

const fmtTime = (epoch: number) => new Date(epoch * 1000).toLocaleTimeString([], { hour12: false });

function transformSerie(raw: any, chart: string) {
  void chart;
  const labels: string[] = raw?.labels ?? [];
  const rows: number[][] = raw?.data ?? [];
  return rows.map((row) => {
    const obj: Record<string, string | number> = { time: fmtTime(row[0]) };
    labels.forEach((label, i) => {
      if (i > 0) obj[label] = row[i] ?? 0;
    });
    return obj;
  }).reverse();
}

export default function NetdataPage() {
  const theme = useTheme();
  const [agent, setAgent] = useState<any>(null);
  const [series, setSeries] = useState<Record<string, ChartSerie>>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [lastUpdate, setLastUpdate] = useState<Date | null>(null);
  const inFlightRef = useRef(false);

  const fetchAll = useCallback(async () => {
    if (inFlightRef.current) return;
    inFlightRef.current = true;
    try {
      const ov = await apiClient.get('/api/netdata.php?action=overview');
      setAgent(ov.data);

      const results = await Promise.allSettled(
        ALLOWED_CHARTS.map((chart) =>
          apiClient.get(`/api/netdata.php?action=chart&chart=${encodeURIComponent(chart)}&points=45&after=-45`)
        ),
      );

      const next: Record<string, ChartSerie> = {};
      results.forEach((r, i) => {
        if (r.status === 'fulfilled' && r.value.data) {
          next[ALLOWED_CHARTS[i]] = {
            chart: ALLOWED_CHARTS[i],
            labels: r.value.data.labels ?? [],
            data: r.value.data.data ?? [],
          };
        }
      });
      setSeries(next);
      setLastUpdate(new Date());
      setError(null);
    } catch (err: any) {
      if (err?.response?.status !== 429) {
        setError(err?.response?.data?.error || err.message || 'Failed to load Netdata charts');
      }
    } finally {
      inFlightRef.current = false;
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchAll();
    const t = setInterval(fetchAll, REFRESH_MS);
    return () => clearInterval(t);
  }, [fetchAll]);

  if (loading && !agent && Object.keys(series).length === 0) {
    return <LoadingState message="Connecting to Netdata agent..." />;
  }

  const healthy = agent?.status === 'ok';

  return (
    <Box>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 2, flexWrap: 'wrap' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Netdata Live Charts
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary', display: 'flex', alignItems: 'center', gap: 1 }}>
            <MonitorHeart sx={{ fontSize: 14 }} />
            {agent?.agent?.version ? `Netdata v${agent.agent.version}` : 'Netdata agent'} &middot; 1s-resolution system metrics
            {lastUpdate && <> &middot; Updated {lastUpdate.toLocaleTimeString([], { hour12: false })}</>}
          </Typography>
        </Box>
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
          <Chip
            icon={healthy ? <CheckCircle /> : <ErrorIcon />}
            label={healthy ? 'AGENT ONLINE' : 'AGENT DEGRADED'}
            color={healthy ? 'success' : 'error'}
            size="small"
            sx={{ fontWeight: 700, fontSize: '0.7rem' }}
          />
          <Tooltip title="Refresh now">
            <IconButton onClick={fetchAll} size="small"><Refresh /></IconButton>
          </Tooltip>
        </Box>
      </Box>

      {error && <Alert severity="warning" sx={{ mb: 2 }} onClose={() => setError(null)}>{error}</Alert>}

      {agent?.agent?.alarms_normal !== undefined && (
        <Grid container spacing={2} sx={{ mb: 3 }}>
          <Grid size={{ xs: 12, sm: 4 }}>
            <StatCard label="Alarms Normal" value={String(agent.agent.alarms_normal)} color="success" icon={<CheckCircle />} />
          </Grid>
          <Grid size={{ xs: 12, sm: 4 }}>
            <StatCard label="Alarms Warning" value={String(agent.agent.alarms_warning)} color="warning" icon={<ErrorIcon />} />
          </Grid>
          <Grid size={{ xs: 12, sm: 4 }}>
            <StatCard label="Alarms Critical" value={String(agent.agent.alarms_critical)} color="error" icon={<ErrorIcon />} />
          </Grid>
        </Grid>
      )}

      <Grid container spacing={2}>
        {ALLOWED_CHARTS.map((chart) => {
          const meta = SERIES_META[chart];
          const serie = series[chart];
          if (!serie) return null;
          const data = transformSerie(serie, chart);
          const Icon = meta.icon;
          const color = theme.palette[meta.color].main;

          return (
            <Grid size={{ xs: 12, md: chart === 'system.cpu' || chart === 'system.ram' ? 6 : 6 }} key={chart}>
              <Card sx={{ height: '100%' }}>
                <CardContent>
                  <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 1 }}>
                    <Icon sx={{ fontSize: 18, color }} />
                    <Typography variant="h6" sx={{ fontWeight: 700, flex: 1 }}>{meta.title}</Typography>
                  </Box>
                  <Box sx={{ height: 200, width: '100%' }}>
                    <ResponsiveContainer width="100%" height="100%">
                      {meta.dims.length > 1 ? (
                        <AreaChart data={data} margin={{ top: 4, right: 4, bottom: 0, left: -14 }}>
                          <defs>
                            {meta.dims.map((d) => (
                              <linearGradient key={d} id={`grad-${chart}-${d}`} x1="0" y1="0" x2="0" y2="1">
                                <stop offset="5%" stopColor={color} stopOpacity={0.3} />
                                <stop offset="95%" stopColor={color} stopOpacity={0} />
                              </linearGradient>
                            ))}
                          </defs>
                          <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.05)" vertical={false} />
                          <XAxis dataKey="time" stroke={theme.palette.text.disabled} fontSize={10} tickLine={false} axisLine={false} minTickGap={40} />
                          <YAxis stroke={theme.palette.text.disabled} fontSize={10} tickLine={false} axisLine={false} width={54} />
                          <RTooltip
                            contentStyle={{ backgroundColor: '#1e293b', border: '1px solid #334155', borderRadius: 8 }}
                            itemStyle={{ color: '#fff', fontSize: 12 }}
                            labelStyle={{ color: '#94a3b8', fontWeight: 700 }}
                          />
                          {meta.dims.map((d) => (
                            <Area key={d} type="monotone" dataKey={d} name={d} stroke={color} fillOpacity={1} fill={`url(#grad-${chart}-${d})`} strokeWidth={1.5} />
                          ))}
                        </AreaChart>
                      ) : (
                        <LineChart data={data} margin={{ top: 4, right: 4, bottom: 0, left: -14 }}>
                          <CartesianGrid strokeDasharray="3 3" stroke="rgba(255,255,255,0.05)" vertical={false} />
                          <XAxis dataKey="time" stroke={theme.palette.text.disabled} fontSize={10} tickLine={false} axisLine={false} minTickGap={40} />
                          <YAxis stroke={theme.palette.text.disabled} fontSize={10} tickLine={false} axisLine={false} width={54} />
                          <RTooltip
                            contentStyle={{ backgroundColor: '#1e293b', border: '1px solid #334155', borderRadius: 8 }}
                            itemStyle={{ color: '#fff', fontSize: 12 }}
                            labelStyle={{ color: '#94a3b8', fontWeight: 700 }}
                          />
                          {meta.dims.map((d) => (
                            <Line key={d} type="monotone" dataKey={d} name={d} stroke={color} strokeWidth={1.5} dot={false} />
                          ))}
                        </LineChart>
                      )}
                    </ResponsiveContainer>
                  </Box>
                  <Box sx={{ mt: 1, display: 'flex', gap: 1, flexWrap: 'wrap' }}>
                    {meta.dims.map((d) => {
                      const last = data[data.length - 1];
                      return (
                        <Chip key={d} size="small" label={`${d}: ${last ? meta.unit(Number(last[d] ?? 0)) : '—'}`}
                          sx={{ fontSize: '0.62rem', height: 18, bgcolor: `${meta.color}.dark`, color: 'text.primary' }} />
                      );
                    })}
                  </Box>
                </CardContent>
              </Card>
            </Grid>
          );
        })}
      </Grid>

      {loading && <LinearProgress sx={{ mt: 2 }} />}

      <Box sx={{ mt: 3 }}>
        <Divider sx={{ mb: 2 }} />
        <Typography variant="caption" color="text.disabled">
          Live data streamed from the local Netdata agent (127.0.0.1:19999) via an authenticated proxy.
          Charts refresh every {REFRESH_MS / 1000}s. Alarms reflect Netdata's own health engine.
        </Typography>
      </Box>
    </Box>
  );
}
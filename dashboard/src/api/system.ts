import apiClient from './client';

export interface SystemLoad {
  '1min': number;
  '5min': number;
  '15min': number;
}

export interface SystemMemory {
  total_mb: number;
  used_pct: number;
  available_mb: number;
  swap_pct: number;
}

export interface SystemDisk {
  total: string;
  used: string;
  free: string;
  pct: string;
}

export interface ProcessInfo {
  pid: string;
  cpu: string;
  mem: string;
  time: string;
  cmd: string;
}

export interface SystemOverview {
  load: SystemLoad;
  memory: SystemMemory;
  disk: SystemDisk;
  uptime: string;
  services: Record<string, string>;
  top_procs: ProcessInfo[];
  varnish?: {
    hit_ratio: number;
    storage_pct: number;
    status: string;
  };
  redis?: {
    connected: boolean;
    keys: number;
  };
  timestamp: number;
}

export interface SiteInfo {
  key: string;
  name: string;
  exists: boolean;
  php_fpm: number;
  disk: string;
  is_magento: boolean;
  maintenance?: boolean;
  is_suspended?: boolean;
}

export interface CronEntry {
  schedule: string;
  command: string;
  comment: string;
  active: boolean;
  running: number;
  source?: 'system' | 'magento';
  magento_status?: string;
  job_code?: string;
  color?: string;
}

export interface CronData {
  entries: CronEntry[];
  total: number;
  timestamp: string;
}

export interface QueueData {
  consumers: string[];
  queue_counts: Record<string, number>;
  timestamp: string;
}

export interface RedisStats {
  connected: boolean;
  memory: {
    used_human: string;
    peak_human: string;
    used_mb: number;
  };
  stats: {
    hit_rate: number;
    ops_per_sec: number;
    connected_clients: number;
  };
  keyspace: {
    total_keys: number;
  };
}

export interface VarnishStats {
  hit_ratio: number;
  hits: number;
  misses: number;
  storage: {
    used: string;
    total: string;
  };
  backend_healthy: boolean;
}

export interface ApacheStats {
  running: boolean;
  version: string;
  processes: number;
  ports: {
    http: boolean;
    https: boolean;
  };
}

export async function fetchSystemOverview(): Promise<SystemOverview> {
  const { data } = await apiClient.get('/api/monitor.php?action=overview');
  if (data.error) throw new Error(data.message || data.error);
  return data;
}

export async function fetchSites(): Promise<SiteInfo[]> {
  const { data } = await apiClient.get('/api/monitor.php?action=sites');
  if (data.error) throw new Error(data.message || data.error);
  return data;
}

export async function fetchCrons(site?: string): Promise<CronData> {
  const params = new URLSearchParams({ action: 'crons' });
  if (site) params.set('site', site);
  const { data } = await apiClient.get(`/api/monitor.php?${params.toString()}`);
  if (data.error) throw new Error(data.message || data.error);
  return data;
}

export async function fetchQueues(): Promise<QueueData> {
  const { data } = await apiClient.get('/api/monitor.php?action=queues');
  if (data.error) throw new Error(data.message || data.error);
  return data;
}

export async function fetchRedisStats(): Promise<RedisStats> {
  const { data } = await apiClient.get('/api/monitor.php?action=redis');
  if (data.error) throw new Error(data.message || data.error);
  return data;
}

export async function fetchVarnishStats(): Promise<VarnishStats> {
  const { data } = await apiClient.get('/api/monitor.php?action=varnish');
  if (data.error) throw new Error(data.message || data.error);
  return data;
}

export async function fetchApacheStats(): Promise<ApacheStats> {
  const { data } = await apiClient.get('/api/monitor.php?action=apache');
  if (data.error) throw new Error(data.message || data.error);
  return data;
}

export async function fetchDbHealth(): Promise<any> {
  const { data } = await apiClient.get('/api/monitor.php?action=dbhealth');
  if (data.error) throw new Error(data.message || data.error);
  return data;
}

export async function performDbAction(op: string, db: string, table: string): Promise<any> {
  const { data } = await apiClient.get(`/api/monitor.php?action=db_action&op=${op}&db=${db}&table=${table}`);
  return data;
}

export async function runCron(command: string): Promise<any> {
  const { data } = await apiClient.get(`/api/monitor.php?action=cron_action&command=${encodeURIComponent(command)}`);
  return data;
}

export async function performSiteAction(site: string, op: string): Promise<any> {
  const { data } = await apiClient.get(`/api/monitor.php?action=site_action&site=${site}&op=${op}`);
  return data;
}

export async function fetchScripts(): Promise<any> {
  const { data } = await apiClient.get('/api/monitor.php?action=execute&list=1');
  if (data.error) throw new Error(data.message || data.error);
  return data;
}

export async function executeScript(script: string, args: string = ''): Promise<any> {
  const { data } = await apiClient.get(`/api/monitor.php?action=execute&script=${script}&args=${args}`);
  return data;
}

export async function runEmergencyCleanup(type: string = 'all'): Promise<any> {
  const { data } = await apiClient.get(`/api/monitor.php?action=cleanup&type=${type}`);
  return data;
}

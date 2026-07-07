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

// SSH Monitoring
export interface SshSession {
  user: string;
  tty: string;
  from: string;
  login_at: string;
  idle: string;
  pid?: string;
}

export interface SshConnection {
  state: string;
  local_ip: string;
  local_port: string;
  remote_ip: string;
  remote_port: string;
}

export interface FailedLogin {
  user: string;
  ip: string;
  invalid_user: boolean;
}

export interface SshData {
  service_active: boolean;
  active_sessions: number;
  sessions: SshSession[];
  established_connections: number;
  connections: SshConnection[];
  failed_logins_total: number;
  recent_failed: FailedLogin[];
  sshd_status: string;
  timestamp: number;
}

// CSF Firewall
export interface CsfDeniedIp {
  ip: string;
  reason: string;
}

export interface CsfAllowedIp {
  ip: string;
  reason: string;
}

export interface FailedSshIp {
  ip: string;
  attempts: number;
}

export interface CsfFirewallData {
  csf_active: boolean;
  lfd_active: boolean;
  version: string;
  testing_mode: boolean;
  stats: {
    denied_ips: number;
    allowed_ips: number;
    ignored_ips: number;
    iptables_rules: number;
  };
  recent_denied: CsfDeniedIp[];
  recent_allowed: CsfAllowedIp[];
  top_failed_ssh_ips: FailedSshIp[];
  timestamp: number;
}

// Services Monitoring
export interface ServiceInfo {
  name: string;
  status: 'active' | 'inactive' | 'failed' | 'not-found';
  enabled: boolean;
  pid: number;
  uptime_seconds: number;
}

export interface ServicesData {
  categories: Record<string, ServiceInfo[]>;
  summary: {
    total: number;
    active: number;
    inactive: number;
    failed: number;
  };
  timestamp: number;
}

// Network Monitoring
export interface ListeningPort {
  address: string;
  port: number;
  process: string;
  pid: number;
}

export interface ConnectionState {
  state: string;
  count: number;
}

export interface RemoteIpStat {
  ip: string;
  connections: number;
}

export interface NetworkData {
  listening_ports: ListeningPort[];
  established_total: number;
  time_wait_total: number;
  connection_summary: { protocol: string; count: number }[];
  connection_states: ConnectionState[];
  top_remote_ips: RemoteIpStat[];
  timestamp: number;
}

export async function fetchSshConnections(): Promise<SshData> {
  const { data } = await apiClient.get('/api/monitor.php?action=ssh');
  if (data.error) throw new Error(data.message || data.error);
  return data;
}

export async function fetchServices(): Promise<ServicesData> {
  const { data } = await apiClient.get('/api/monitor.php?action=services');
  if (data.error) throw new Error(data.message || data.error);
  return data;
}

export async function fetchNetworkConnections(): Promise<NetworkData> {
  const { data } = await apiClient.get('/api/monitor.php?action=network');
  if (data.error) throw new Error(data.message || data.error);
  return data;
}

// SSH Session Control
export async function killAllSshSessions(skipTty?: string): Promise<any> {
  const { data } = await apiClient.post('/api/monitor.php?action=ssh_kill', { skip_tty: skipTty });
  return data;
}

export async function killSingleSshSession(sessionId: string): Promise<any> {
  const { data } = await apiClient.post('/api/monitor.php?action=ssh_kill_single', { session_id: sessionId });
  return data;
}

export async function restartSshd(): Promise<any> {
  const { data } = await apiClient.post('/api/monitor.php?action=sshd_restart', {});
  return data;
}

// SSH User Management
export async function getSshUsers(): Promise<any> {
  const { data } = await apiClient.get('/api/monitor.php?action=ssh_users');
  if (data.error) throw new Error(data.message || data.error);
  return data;
}

export async function addSshUser(username: string): Promise<any> {
  const { data } = await apiClient.post('/api/monitor.php?action=ssh_user_add', { username });
  return data;
}

export async function removeSshUser(username: string): Promise<any> {
  const { data } = await apiClient.post('/api/monitor.php?action=ssh_user_remove', { username });
  return data;
}


// CSF Firewall
export async function fetchCsfFirewall(): Promise<CsfFirewallData> {
  const { data } = await apiClient.get('/api/monitor.php?action=csf');
  if (data.error) throw new Error(data.message || data.error);
  return data;
}

export async function csfAction(action: string, ip?: string): Promise<any> {
  const { data } = await apiClient.post('/api/monitor.php?action=csf_action', { action, ip });
  return data;
}

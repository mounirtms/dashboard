import apiClient from './client';

export interface UserData {
  username: string;
  home_exists: boolean;
  disk_usage: string;
  process_count: number;
  ssh_sessions: number;
  ssh_details: string[];
  active_sessions: number;
  last_system_login: string;
  dashboard_user?: {
    id: number;
    username: string;
    full_name: string;
    role: string;
    last_login: string;
    is_active: boolean;
    created_at: string;
  };
}

export interface SessionData {
  id: string;
  user_id: number;
  ip_address: string;
  user_agent: string;
  last_activity: number;
  username?: string;
  role?: string;
}

export interface UserActivityResponse {
  users: UserData[];
  sessions: SessionData[];
  global: {
    total_ssh_users: number;
    total_processes: number;
    load_1min: number;
    load_5min: number;
    load_15min: number;
    timestamp: number;
  };
}

export interface BashHistoryEntry {
  timestamp: string;
  epoch: string | null;
  command: string;
}

export interface BashHistoryResponse {
  username: string;
  path: string;
  history: BashHistoryEntry[];
  total: number;
  offset: number;
  lines: number;
  has_more: boolean;
  message?: string;
  error?: string;
}

export async function fetchUserActivity(): Promise<UserActivityResponse> {
  const { data } = await apiClient.get('/api/monitor.php?action=user_activity');
  return data;
}

export async function fetchBashHistory(username: string, lines = 50, offset = 0): Promise<BashHistoryResponse> {
  const { data } = await apiClient.get(
    `/api/monitor.php?action=bash_history&username=${encodeURIComponent(username)}&lines=${lines}&offset=${offset}`
  );
  return data;
}

export interface SecurityFinding {
  severity: 'CRITICAL' | 'HIGH' | 'MEDIUM' | 'LOW';
  account: string;
  category: string;
  title: string;
  detail: string;
  timestamp: string;
}

export interface SecurityScanResult {
  status: 'complete' | 'no_scan' | 'error' | 'running';
  message?: string;
  scan_time?: string;
  accounts?: string[];
  summary?: {
    total_issues: number;
    critical: number;
    high: number;
    medium: number;
    low: number;
  };
  findings?: SecurityFinding[];
  report_age?: number;
}

export interface SecurityHardenResult {
  status: 'complete' | 'never_run' | 'error';
  message?: string;
  last_run?: string;
  issues_found?: number;
  issues_fixed?: number;
  output?: string;
}

export async function fetchSecurityScan(): Promise<SecurityScanResult> {
  const { data } = await apiClient.get('/api/monitor.php?action=security_scan');
  return data;
}

export async function runSecurityScan(account?: string): Promise<SecurityScanResult> {
  const params = account ? `&account=${encodeURIComponent(account)}` : '';
  const { data } = await apiClient.get(`/api/monitor.php?action=security_scan_run${params}`);
  return data;
}

export async function fetchSecurityHarden(): Promise<SecurityHardenResult> {
  const { data } = await apiClient.get('/api/monitor.php?action=security_harden');
  return data;
}

export async function runSecurityHarden(account?: string, checkOnly = false): Promise<SecurityHardenResult> {
  const params = new URLSearchParams();
  if (account) params.set('account', account);
  if (checkOnly) params.set('check_only', 'true');
  const qs = params.toString() ? `&${params.toString()}` : '';
  const { data } = await apiClient.get(`/api/monitor.php?action=security_harden_run${qs}`);
  return data;
}

export interface EcomscanFinding {
  account: string;
  check: string;
  class: string;
  name: string;
  description: string;
  path: string;
  snippet: string;
  confidence: number;
  moreinfo: string;
}

export interface EcomscanResult {
  status: 'complete' | 'no_scan' | 'error';
  message?: string;
  scan_time?: string;
  scanner?: string;
  accounts?: string[];
  summary?: {
    total_issues: number;
    critical_confidence: number;
    malware: number;
    vulnerabilities: number;
  };
  findings?: EcomscanFinding[];
  report_age?: number;
}

export async function fetchEcomscan(): Promise<EcomscanResult> {
  const { data } = await apiClient.get('/api/monitor.php?action=ecomscan');
  return data;
}

export async function runEcomscan(account?: string): Promise<EcomscanResult> {
  const params = account ? `&account=${encodeURIComponent(account)}` : '';
  const { data } = await apiClient.get(`/api/monitor.php?action=ecomscan_run${params}`);
  return data;
}

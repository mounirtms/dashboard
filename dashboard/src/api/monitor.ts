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

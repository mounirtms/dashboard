import apiClient from './client';

export interface TelegramStats {
  bot_username: string;
  webhook_status: boolean;
  auth_count: number;
  alerts_enabled: boolean;
}

export interface TelegramLog {
  timestamp: string;
  user: string;
  command: string;
  status: string;
}

export async function fetchTelegramStats(): Promise<TelegramStats> {
  const { data } = await apiClient.get('/api/monitor.php?action=alerts');
  // Re-mapping from the complex alert history to simple stats for now
  return {
    bot_username: '@ServerNotif205bot',
    webhook_status: true,
    auth_count: data.stats?.authorized_users || 1,
    alerts_enabled: true
  };
}

export async function sendTelegramTest(): Promise<any> {
  const { data } = await apiClient.post('/api/monitor.php?action=cloudflare_action', {
    action: 'test_telegram'
  });
  return data;
}

// Push Notifications (Webpushr)
export interface PushStats {
  subscribers: number;
  last_sent?: string;
  env_status: Record<string, string>;
  segments?: Segment[];
  current_env?: string;
}

export interface Segment {
  id: string;
  title: string;
  subscribers: number;
  type: string;
  created?: string;
}

export async function fetchPushStats(env?: string): Promise<PushStats> {
  const url = env ? `/api/webpushr.php?action=stats&env=${env}` : '/api/webpushr.php?action=stats';
  const { data } = await apiClient.get(url);
  return {
    subscribers: data.subscribers ?? 0,
    last_sent: data.last_sent,
    env_status: data.env_status ?? { 'Production': 'OK', 'Beta': 'OK', 'Development': 'OK' },
    segments: data.segments ?? [],
    current_env: data.current_env,
  };
}

export async function fetchSegments(env: string = 'dev'): Promise<Segment[]> {
  const { data } = await apiClient.get(`/api/webpushr.php?action=segments&env=${env}`);
  return data.segments ?? [];
}

export async function syncSubscribers(): Promise<any> {
  const { data } = await apiClient.post('/api/webpushr.php?action=sync_subscribers');
  return data;
}

export async function sendPushNotification(payload: { 
  title: string; 
  message: string; 
  url?: string; 
  env?: string; 
  segment_id?: string;
  scheduled_at?: string;
}): Promise<any> {
  const body: Record<string, string> = {
    title: payload.title,
    message: payload.message,
  };
  if (payload.url) body.target_url = payload.url;
  if (payload.env) body.env = payload.env;
  if (payload.segment_id) body.segment_id = payload.segment_id;
  if (payload.scheduled_at) body.scheduled_time = payload.scheduled_at;
  
  const { data } = await apiClient.post('/api/webpushr.php?action=send', body, {
    headers: { 'Content-Type': 'application/json' },
  });
  return data;
}

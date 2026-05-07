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
}

export async function fetchPushStats(): Promise<PushStats> {
  const { data } = await apiClient.get('/api/webpushr.php?action=stats');
  return data;
}

export async function sendPushNotification(payload: any): Promise<any> {
  const { data } = await apiClient.post('/api/webpushr.php?action=send', payload);
  return data;
}

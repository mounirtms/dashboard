import apiClient from './client';

export interface TelegramStats {
  /** e.g. '@ServerNotif205bot' */
  bot_username: string;
  bot_first_name: string;
  bot_id: number | null;
  webhook_status: boolean;
  webhook_url: string;
  webhook_pending: number;
  webhook_last_err: string | null;
  auth_count: number;
  alerts_enabled: boolean;
  recent_logs: TelegramLog[];
}

export interface TelegramLog {
  timestamp: string;
  user: string;
  command: string;
  status: string;
}

export async function fetchTelegramStats(): Promise<TelegramStats> {
  const { data } = await apiClient.get('/api/telegram.php?action=status');
  return data as TelegramStats;
}

export async function sendTelegramTest(): Promise<{ success: boolean; message: string }> {
  const { data } = await apiClient.post('/api/telegram.php', { action: 'test' });
  return data;
}

export async function sendTelegramCommand(command: string): Promise<{ success: boolean; message: string }> {
  const { data } = await apiClient.post('/api/telegram.php', { action: 'command', command });
  return data;
}

export async function fetchTelegramLogs(limit = 50): Promise<TelegramLog[]> {
  const { data } = await apiClient.get(`/api/telegram.php?action=logs&limit=${limit}`);
  return (data.parsed ?? []) as TelegramLog[];
}

export async function setTelegramWebhook(url?: string): Promise<{ success: boolean; message: string }> {
  const payload: Record<string, string> = { action: 'webhook_set' };
  if (url) payload.url = url;
  const { data } = await apiClient.post('/api/telegram.php', payload);
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

export async function fetchSegments(env: string = 'production'): Promise<Segment[]> {
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
  icon?: string;
  image?: string;
  tag?: string;
}): Promise<any> {
  const body: Record<string, string> = {
    title: payload.title,
    message: payload.message,
  };
  if (payload.url) body.target_url = payload.url;
  if (payload.env) body.env = payload.env;
  if (payload.segment_id) body.segment_id = payload.segment_id;
  if (payload.scheduled_at) body.scheduled_time = payload.scheduled_at;
  if (payload.icon) body.icon = payload.icon;
  if (payload.image) body.image = payload.image;
  if (payload.tag) body.tag = payload.tag;
  
  const { data } = await apiClient.post('/api/webpushr.php?action=send', body, {
    headers: { 'Content-Type': 'application/json' },
  });
  return data;
}

export async function uploadPushImage(file: File, type: 'image' | 'icon' = 'image'): Promise<{ url: string; filename: string; size: number; width: number; height: number }> {
  const formData = new FormData();
  formData.append('file', file);
  formData.append('type', type);
  
  const { data } = await apiClient.post('/api/upload.php', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  
  if (!data.success) {
    throw new Error(data.error || 'Upload failed');
  }
  
  return { url: data.url, filename: data.filename, size: data.size, width: data.width, height: data.height };
}

export async function fetchDeliveryStats(env: string = 'production'): Promise<any> {
  const { data } = await apiClient.get(`/api/webpushr.php?action=delivery_stats&env=${env}`);
  return data;
}

export async function fetchSubscriberAnalytics(env: string = 'production'): Promise<any> {
  const { data } = await apiClient.get(`/api/webpushr.php?action=subscriber_analytics&env=${env}`);
  return data;
}

export async function fetchSubscribers(env: string = 'production', limit: number = 50, offset: number = 0, segmentId?: string): Promise<any> {
  let url = `/api/webpushr.php?action=get_subscribers&env=${env}&limit=${limit}&offset=${offset}`;
  if (segmentId) url += `&segment_id=${segmentId}`;
  const { data } = await apiClient.get(url);
  return data;
}

export async function fetchSubscriberDetail(env: string = 'production', sid: string): Promise<any> {
  const { data } = await apiClient.get(`/api/webpushr.php?action=get_subscriber_detail&env=${env}&sid=${sid}`);
  return data;
}

export async function fetchGeoAnalytics(env: string = 'production'): Promise<any> {
  const { data } = await apiClient.get(`/api/webpushr.php?action=get_geo_analytics&env=${env}`);
  return data;
}

export async function fetchDeviceAnalytics(env: string = 'production'): Promise<any> {
  const { data } = await apiClient.get(`/api/webpushr.php?action=get_device_analytics&env=${env}`);
  return data;
}

export async function fetchBrowserAnalytics(env: string = 'production'): Promise<any> {
  const { data } = await apiClient.get(`/api/webpushr.php?action=get_browser_analytics&env=${env}`);
  return data;
}

export async function fetchOsAnalytics(env: string = 'production'): Promise<any> {
  const { data } = await apiClient.get(`/api/webpushr.php?action=get_os_analytics&env=${env}`);
  return data;
}

export async function fetchSubscriberBySid(sid: string): Promise<any> {
  const { data } = await apiClient.get(`/api/webpushr.php?action=get_subscriber_by_sid&sid=${sid}`);
  return data;
}

// Email Notification Logs
export interface EmailLog {
  timestamp: string;
  type: string;
  to: string;
  subject: string;
  success: boolean;
  error?: string;
}

export interface EmailLogStats {
  total: number;
  success: number;
  failed: number;
  by_type: Record<string, number>;
  recent_failures: EmailLog[];
}

export async function fetchEmailLogs(limit: number = 50): Promise<{ logs: EmailLog[]; total: number }> {
  const { data } = await apiClient.get(`/api/email_logs.php?action=list&limit=${limit}`);
  return data;
}

export async function fetchEmailLogStats(): Promise<EmailLogStats> {
  const { data } = await apiClient.get('/api/email_logs.php?action=stats');
  return data.stats;
}

export async function clearEmailLogs(): Promise<any> {
  const { data } = await apiClient.post('/api/email_logs.php?action=clear');
  return data;
}

// Email Settings
export interface EmailSettings {
  from_email: string;
  from_name: string;
  admin_email_1: string;
  admin_email_2: string;
  enabled: string;
  smtp_host: string;
  smtp_port: string;
  smtp_user: string;
  smtp_pass: string;
  smtp_encryption: string;
  smtp_pass_set?: boolean;
}

export async function fetchEmailSettings(): Promise<EmailSettings> {
  const { data } = await apiClient.get('/api/email_settings.php?action=get');
  return data.settings;
}

export async function saveEmailSettings(settings: EmailSettings): Promise<any> {
  const { data } = await apiClient.post('/api/email_settings.php?action=save', settings);
  return data;
}

export async function testEmailSettings(email: string): Promise<any> {
  const { data } = await apiClient.post('/api/email_settings.php?action=test', { email });
  return data;
}

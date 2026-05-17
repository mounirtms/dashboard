import apiClient from './client';

export interface UserSettings {
  personal: {
    full_name: string;
    email: string;
    phone: string;
  };
  appearance: {
    theme: string;
    font_size: string;
    animations: boolean;
    language: string;
  };
  general: {
    notifications_enabled: boolean;
    auto_refresh: boolean;
    refresh_interval: number;
    debug_mode: boolean;
  };
}

export async function fetchSettings(): Promise<UserSettings> {
  const { data } = await apiClient.get('/api/settings.php?action=get');
  if (data.error) throw new Error(data.error);
  return data.settings;
}

export async function saveSettings(settings: UserSettings): Promise<void> {
  const { data } = await apiClient.post('/api/settings.php?action=save', settings, {
    headers: { 'Content-Type': 'application/json' },
  });
  if (data.error) throw new Error(data.error);
}

export interface PushSubscription {
  id: number;
  device_id: string | null;
  domain: string;
  browser: string;
  device_type: string;
  os: string;
  last_used: string;
  created_at: string;
  is_active: number;
}

export async function fetchPushSubscriptions(domain?: string): Promise<PushSubscription[]> {
  const url = domain
    ? `/api/webpushr.php?action=get_subscriptions&domain=${domain}`
    : '/api/webpushr.php?action=get_subscriptions';
  const { data } = await apiClient.get(url);
  if (data.error) throw new Error(data.error);
  return data.subscriptions || [];
}

export async function unsubscribeDevice(subscriptionId: number): Promise<void> {
  const { data } = await apiClient.post('/api/webpushr.php?action=unsubscribe', {
    subscription_id: subscriptionId,
  });
  if (data.error) throw new Error(data.error);
}

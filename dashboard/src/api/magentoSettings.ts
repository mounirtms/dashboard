import apiClient from './client';

export interface MagentoEnvSettings {
  base_url: string;
  token_masked: string;
  has_token: boolean;
  username: string;
  token_updated_at: string;
}

export interface MagentoSettingsData {
  [env: string]: MagentoEnvSettings;
}

export async function fetchMagentoSettings(): Promise<MagentoSettingsData> {
  const { data } = await apiClient.get('/api/magento-settings.php?action=get');
  if (data.error) throw new Error(data.error);
  return data.settings;
}

export async function saveMagentoSettings(settings: Record<string, { base_url?: string; token?: string; username?: string; password?: string }>): Promise<void> {
  const { data } = await apiClient.post('/api/magento-settings.php?action=save', settings);
  if (data.error) throw new Error(data.error);
}

export async function testMagentoConnection(env: string, token?: string): Promise<{ success: boolean; http_code: number; message: string; store_info?: any }> {
  let url = `/api/magento-settings.php?action=test&env=${env}`;
  if (token) url += `&token=${encodeURIComponent(token)}`;
  const { data } = await apiClient.get(url);
  return data;
}

export async function fetchMagentoToken(env: string, base_url: string, username: string, password: string): Promise<{ success: boolean; message: string; token_preview?: string }> {
  const { data } = await apiClient.post('/api/magento-settings.php?action=fetch_token', { env, base_url, username, password });
  if (data.error) throw new Error(data.error);
  return data;
}

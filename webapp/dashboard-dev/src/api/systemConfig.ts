import apiClient from './client';

export interface EnvKeyMeta {
  label: string;
  type: 'text' | 'password';
  hint: string;
  value: string;
  /** true if the key currently has a non-empty value in .env */
  set: boolean;
}

/** Map of envKey → meta+value for a group */
export type EnvGroup = Record<string, EnvKeyMeta>;

/** All groups returned by the API */
export interface SystemConfigData {
  groups: Record<string, EnvGroup>;
  env_file: string;
}

/**
 * Fetch current values of all managed .env keys.
 * Admin-only endpoint — will 403 for non-admins.
 */
export async function fetchSystemConfig(): Promise<SystemConfigData> {
  const { data } = await apiClient.get('/api/system-config.php?action=get');
  if (data.error) throw new Error(data.error);
  return data;
}

/**
 * Save updated key/value pairs back to the .env file.
 * Only managed (whitelisted) keys are accepted by the server.
 */
export async function saveSystemConfig(
  updates: Record<string, string>
): Promise<{ updated: string[]; message: string }> {
  const { data } = await apiClient.post('/api/system-config.php?action=save', { updates });
  if (data.error) throw new Error(data.error);
  return data;
}

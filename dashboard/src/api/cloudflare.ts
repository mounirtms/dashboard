import apiClient from './client';

export interface ZoneInfo {
  name: string;
  status: string;
  plan: string;
  development_mode: string;
}

export interface SslCertificate {
  status: string;
  expires_on: string | null;
  days_left: number | null;
  hostnames: string[];
}

export interface AnalyticsDay {
  date: string;
  requests: number;
  pageViews: number;
  threats: number;
  bytes: number;
  cachedBytes: number;
  cachedRequests: number;
}

export interface AnalyticsHour {
  datetime: string;
  time?: string;
  requests: number;
  bytes: number;
}

export interface Country {
  code: string;
  name: string;
  flag: string;
  requests: number;
  bytes: number;
  threats: number;
  percentage: number;
}

export interface StatusCode {
  class: string;
  label: string;
  requests: number;
}

export interface TopUrl {
  path: string;
  requests: number;
  bytes: number;
}

export interface ThreatType {
  type: string;
  count: number;
}

export interface AnalyticsTotals {
  requests: number;
  pageViews: number;
  threats: number;
  bytes: number;
  cachedBytes: number;
  cachedRequests: number;
}

export interface FirewallEvent {
  action: string;
  source: string;
  rule_id: string;
  datetime: string;
}

export interface FirewallSummary {
  blocked: number;
  challenged: number;
  total: number;
  events: FirewallEvent[];
}

export interface CloudflareData {
  zone: ZoneInfo;
  account: string;
  ssl_certificate: SslCertificate | null;
  settings: Record<string, string>;
  purge_history: any[];
  analytics: AnalyticsDay[];
  hourly_analytics: AnalyticsHour[];
  countries: Country[];
  status_codes: StatusCode[];
  top_urls: TopUrl[];
  threat_types: ThreatType[];
  analytics_totals: AnalyticsTotals;
  cache_hit_ratio: number;
  bandwidth_formatted: string;
  firewall: FirewallSummary;
  timestamp: number;
  error?: string;
}

export async function fetchCloudflareData(): Promise<CloudflareData> {
  const { data } = await apiClient.get('/api/monitor.php?action=cloudflare');
  if (data.error) throw new Error(data.error);
  return data;
}

export async function performCloudflareAction(
  action: string,
  params: Record<string, string> = {}
): Promise<{ success: boolean; message: string }> {
  const formData = new URLSearchParams({ action, ...params });
  const { data } = await apiClient.post(
    '/api/monitor.php?action=cloudflare_action',
    formData,
    { headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }
  );
  return data;
}

/**
 * notificationPreferences.ts
 * Per-user per-channel per-event notification preference API.
 * Backend: /api/notification_preferences.php
 */
import apiClient from './client';

// ── Types ────────────────────────────────────────────────────────────────────
export interface NotificationPreferences {
  // Security & Auth
  security_alert_email: boolean;
  security_alert_telegram: boolean;
  security_alert_push: boolean;

  login_alert_email: boolean;
  login_alert_telegram: boolean;
  login_alert_push: boolean;

  // Tasks
  task_assigned_email: boolean;
  task_assigned_telegram: boolean;
  task_assigned_push: boolean;

  task_approved_email: boolean;
  task_approved_telegram: boolean;
  task_approved_push: boolean;

  // Infrastructure
  cron_failure_email: boolean;
  cron_failure_telegram: boolean;
  cron_failure_push: boolean;

  deploy_complete_email: boolean;
  deploy_complete_telegram: boolean;
  deploy_complete_push: boolean;

  ecomscan_done_email: boolean;
  ecomscan_done_telegram: boolean;
  ecomscan_done_push: boolean;

  high_cpu_email: boolean;
  high_cpu_telegram: boolean;
  high_cpu_push: boolean;

  service_down_email: boolean;
  service_down_telegram: boolean;
  service_down_push: boolean;

  backup_done_email: boolean;
  backup_done_telegram: boolean;
  backup_done_push: boolean;

  // Allow indexing by dynamic keys
  [key: string]: boolean | number;
}

// ── Default preferences (all enabled) ─────────────────────────────────────
export const DEFAULT_PREFERENCES: NotificationPreferences = {
  security_alert_email: true, security_alert_telegram: true, security_alert_push: true,
  login_alert_email: true, login_alert_telegram: true, login_alert_push: false,
  task_assigned_email: true, task_assigned_telegram: true, task_assigned_push: true,
  task_approved_email: true, task_approved_telegram: false, task_approved_push: false,
  cron_failure_email: true, cron_failure_telegram: true, cron_failure_push: false,
  deploy_complete_email: false, deploy_complete_telegram: true, deploy_complete_push: false,
  ecomscan_done_email: false, ecomscan_done_telegram: false, ecomscan_done_push: false,
  high_cpu_email: true, high_cpu_telegram: true, high_cpu_push: true,
  service_down_email: true, service_down_telegram: true, service_down_push: true,
  backup_done_email: false, backup_done_telegram: false, backup_done_push: false,
};

// ── API calls ────────────────────────────────────────────────────────────────
export async function fetchNotificationPreferences(): Promise<NotificationPreferences> {
  const { data } = await apiClient.get('/api/notification_preferences.php?action=get');
  if (data.error) throw new Error(data.error);
  return { ...DEFAULT_PREFERENCES, ...data.preferences };
}

export async function saveNotificationPreferences(prefs: NotificationPreferences): Promise<any> {
  const { data } = await apiClient.post('/api/notification_preferences.php', {
    action: 'save',
    preferences: prefs,
  });
  if (data.error) throw new Error(data.error);
  return data;
}

export async function resetNotificationPreferences(): Promise<NotificationPreferences> {
  const { data } = await apiClient.post('/api/notification_preferences.php', {
    action: 'reset',
  });
  if (data.error) throw new Error(data.error);
  return { ...DEFAULT_PREFERENCES, ...data.preferences };
}

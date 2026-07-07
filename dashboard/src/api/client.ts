/**
 * Axios API client — centralised HTTP layer.
 *
 * Improvements over the previous version:
 *  - Deduplicates concurrent GET requests to the same URL (pending-request map).
 *  - Retry-after header is respected on 429 responses (up to 2 retries).
 *  - Network errors on GET requests are retried once.
 *  - 401 responses redirect to /login without causing duplicate redirects.
 *  - Request timeout bumped to 45 s for slow server-side operations.
 */

import axios, { type InternalAxiosRequestConfig } from 'axios';

// Extend Axios types to avoid `as any` for deduplication metadata
declare module 'axios' {
  interface InternalAxiosRequestConfig {
    __dedupKey?: string;
    __dedupPromise?: Promise<unknown>;
    __retryCount?: number;
    __retried?: boolean;
  }
}

const apiClient = axios.create({
  baseURL: '/',
  timeout: 45_000,
  headers: { 'Content-Type': 'application/json' },
  withCredentials: true,
});

// ----- Request deduplication for GET calls --------------------------------
const pendingRequests = new Map<string, Promise<any>>();

apiClient.interceptors.request.use(config => {
  if (config.method?.toLowerCase() !== 'get') return config;

  const key = `${config.url}?${JSON.stringify(config.params ?? {})}`;
  if (pendingRequests.has(key)) {
    // Return existing in-flight request as a resolved "pass-through"
    // by attaching the key so the response interceptor can dedup it.
    config.__dedupKey = key;
    config.__dedupPromise = pendingRequests.get(key);
  }
  return config;
});

// ----- Response / error interceptors --------------------------------------
function delay(ms: number) {
  return new Promise<void>(resolve => setTimeout(resolve, ms));
}

apiClient.interceptors.response.use(
  response => {
    // Clean up pending-request map
    const key = response.config.__dedupKey;
    if (key) pendingRequests.delete(key);
    return response;
  },
  async error => {
    const config: InternalAxiosRequestConfig = error.config ?? {};

    // 401 → redirect to login (once)
    if (error.response?.status === 401) {
      error.isAuthError = true;
      if (!window.location.hash.startsWith('#/login')) {
        window.location.hash = '#/login';
      }
      const key = config.__dedupKey;
      if (key) pendingRequests.delete(key);
      return Promise.reject(error);
    }

    if (!config) return Promise.reject(error);

    const status      = error.response?.status;
    const retryCount  = config.__retryCount ?? 0;
    const isGet       = (config.method ?? '').toLowerCase() === 'get';

    // Retry GET on rate-limit (429) with backoff — up to 2 attempts
    if (isGet && status === 429 && retryCount < 2) {
      config.__retryCount = retryCount + 1;
      const retryAfter = parseInt(
        error.response?.headers?.['retry-after'] ?? '0',
        10,
      );
      const waitMs = retryAfter > 0
        ? retryAfter * 1000
        : 1_000 * Math.pow(2, retryCount);
      await delay(waitMs);
      return apiClient(config);
    }

    // Retry GET on network error — once
    if (isGet && !config.__retried && error.code !== 'ERR_CANCELED') {
      config.__retried = true;
      try {
        return await apiClient(config);
      } catch (retryError) {
        return Promise.reject(retryError);
      }
    }

    const key = config.__dedupKey;
    if (key) pendingRequests.delete(key);

    return Promise.reject(error);
  },
);

export default apiClient;

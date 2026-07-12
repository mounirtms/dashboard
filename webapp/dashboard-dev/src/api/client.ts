import axios from 'axios';

const apiClient = axios.create({
  baseURL: '/',
  timeout: 30000,
  headers: { 'Content-Type': 'application/json' },
  withCredentials: true,
});

function delay(ms: number) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Auth endpoints that must NOT trigger the 401 → redirect loop.
 * If the login/status/reset-password endpoints themselves return 401
 * we handle it in the UI, not here.
 */
const AUTH_URLS = [
  '/api/auth.php',
];

function isAuthUrl(url?: string): boolean {
  if (!url) return false;
  return AUTH_URLS.some(a => url.includes(a));
}

apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    const config = error.config;

    if (error.response?.status === 401) {
      error.isAuthError = true;
      // Don't redirect if already on login page or request is an auth endpoint itself
      const onLoginPage = window.location.hash.startsWith('#/login') ||
                          window.location.hash.startsWith('#/reset-password');
      if (!onLoginPage && !isAuthUrl(config?.url)) {
        window.location.hash = '#/login';
      }
      return Promise.reject(error);
    }

    if (!config) return Promise.reject(error);

    const status = error.response?.status;
    const retryCount = config.__retryCount ?? 0;

    // Retry GET requests on 429 (rate limited) with exponential backoff
    if (config.method === 'get' && status === 429 && retryCount < 2) {
      config.__retryCount = retryCount + 1;
      const retryAfter = parseInt(error.response?.headers?.['retry-after'] ?? '0', 10);
      const waitMs = retryAfter > 0 ? retryAfter * 1000 : (1000 * Math.pow(2, retryCount));
      await delay(waitMs);
      return apiClient(config);
    }

    // Retry GET requests once on transient network errors (not auth/cancel)
    if (
      config.method === 'get' &&
      !config.__retried &&
      error.code !== 'ERR_CANCELED' &&
      !isAuthUrl(config.url)
    ) {
      config.__retried = true;
      try {
        return await apiClient(config);
      } catch (retryError) {
        return Promise.reject(retryError);
      }
    }

    return Promise.reject(error);
  }
);

export default apiClient;

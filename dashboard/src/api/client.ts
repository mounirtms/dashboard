import axios from 'axios';

const apiClient = axios.create({
  baseURL: '/',
  timeout: 30000,
  headers: { 'Content-Type': 'application/json' },
  withCredentials: true,
});


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

let _redirectingToLogin = false;

/**
 * CSRF token for mutating requests (task #155).
 *
 * api/monitor.php now rejects mutating actions without a valid
 * X-CSRF-TOKEN, so every POST must carry one. The token is issued by
 * /api/auth.php?action=csrf_token and stored in the session.
 *
 * It is cached in memory and refreshed lazily: one GET serves any number of
 * POSTs, and a 403 triggers a single refetch and replay rather than logging
 * the user out, since a stale token is not an auth failure.
 */
let csrfToken: string | null = null;
let csrfFetchInFlight: Promise<string> | null = null;

async function fetchCsrfToken(): Promise<string> {
  if (csrfToken) return csrfToken;
  // Collapse concurrent callers onto one request.
  if (csrfFetchInFlight) return csrfFetchInFlight;

  csrfFetchInFlight = apiClient
    .get('/api/auth.php?action=csrf_token')
    .then((res) => {
      const token = res.data?.csrf_token;
      if (!token) throw new Error('CSRF endpoint returned no token');
      csrfToken = token;
      return token as string;
    })
    .finally(() => {
      csrfFetchInFlight = null;
    });

  return csrfFetchInFlight;
}

const SAFE_METHODS = new Set(['get', 'head', 'options']);

apiClient.interceptors.request.use(async (config) => {
  const method = (config.method || 'get').toLowerCase();
  if (SAFE_METHODS.has(method)) return config;

  // Skip the CSRF fetch for the endpoint that hands out the token.
  if (config.url?.includes('/api/auth.php')) return config;

  try {
    const token = await fetchCsrfToken();
    config.headers = config.headers ?? {};
    (config.headers as Record<string, string>)['X-CSRF-TOKEN'] = token;
  } catch {
    // Send the request without a token; the server will 403 and the response
    // interceptor below will retry once with a fresh token. Failing here
    // silently is deliberate — it avoids turning a transient token-fetch
    // failure into a silent no-op.
  }
  return config;
});

apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    const config = error.config;

    // 403 with a CSRF complaint: the cached token went stale (session
    // regenerated, or the token was minted before this tab loaded).
    // Refetch once and replay. Guarded so we cannot loop.
    if (error.response?.status === 403 && config && !config.__csrfRetried
        && (config.url || '').includes('action=')
        && !SAFE_METHODS.has((config.method || 'get').toLowerCase())) {
      config.__csrfRetried = true;
      csrfToken = null;
      try {
        const token = await fetchCsrfToken();
        config.headers = config.headers ?? {};
        (config.headers as Record<string, string>)['X-CSRF-TOKEN'] = token;
        return await apiClient(config);
      } catch {
        return Promise.reject(error);
      }
    }

    if (error.response?.status === 401) {
      error.isAuthError = true;
      const onLoginPage = window.location.hash.startsWith('#/login') ||
                          window.location.hash.startsWith('#/reset-password');
      if (!onLoginPage && !isAuthUrl(config?.url) && !_redirectingToLogin) {
        _redirectingToLogin = true;
        window.location.hash = '#/login';
        setTimeout(() => { _redirectingToLogin = false; }, 2000);
      }
      return Promise.reject(error);
    }

    if (!config) return Promise.reject(error);

    const status = error.response?.status;
    // 429 — do NOT retry. The SystemOverviewContext uses a single shared polling
    // interval (60s) so cascading retries are no longer possible.
    // Just reject immediately and let the UI show a graceful fallback.
    if (status === 429) {
      return Promise.reject(error);
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

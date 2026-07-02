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

apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    const config = error.config;

    if (error.response?.status === 401) {
      error.isAuthError = true;
      if (!window.location.hash.startsWith('#/login')) {
        window.location.hash = '#/login';
      }
      return Promise.reject(error);
    }

    if (!config) return Promise.reject(error);

    const status = error.response?.status;
    const retryCount = config.__retryCount ?? 0;

    // Retry GET requests on 429 (rate limited) with backoff
    if (config.method === 'get' && status === 429 && retryCount < 2) {
      config.__retryCount = retryCount + 1;
      const retryAfter = parseInt(error.response?.headers?.['retry-after'] ?? '0', 10);
      const waitMs = retryAfter > 0 ? retryAfter * 1000 : (1000 * Math.pow(2, retryCount));
      await delay(waitMs);
      return apiClient(config);
    }

    // Retry GET requests once on network errors
    if (config.method === 'get' && !config.__retried && error.code !== 'ERR_CANCELED') {
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

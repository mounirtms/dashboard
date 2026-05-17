import axios from 'axios';

const apiClient = axios.create({
  baseURL: '/',
  timeout: 30000,
  headers: { 'Content-Type': 'application/json' },
  withCredentials: true,
});

// Retry failed requests (except POST/PUT/DELETE)
apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    const config = error.config;
    if (!config) return Promise.reject(error);
    
    if (error.response?.status === 401) {
      error.isAuthError = true;
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

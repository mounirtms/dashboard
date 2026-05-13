import axios from 'axios';

const apiClient = axios.create({
  baseURL: '/',
  timeout: 15000,
  headers: { 'Content-Type': 'application/json' },
  withCredentials: true,
});

apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Don't redirect immediately - let the auth context handle it via remember token
      // Only set a flag that can be checked by the auth context
      error.isAuthError = true;
    }
    return Promise.reject(error);
  }
);

export default apiClient;

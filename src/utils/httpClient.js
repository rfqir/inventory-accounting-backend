import axios from 'axios';
import * as auth from '../services/acounting/auth/auth.js';

let loginPromise = null;

const httpClient = axios.create({
  baseURL: process.env.ACCOUNTING || 'http://localhost/api',
  timeout: 600000,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Interceptor Request: Set token dan orgId sebelum request dikirim
httpClient.interceptors.request.use(
  async (config) => {
    try {
      // Cegah multiple login dengan mutex promise
      if (!auth.getToken() || !auth.getOrgId()) {
        if (!loginPromise) {
          loginPromise = auth.login();
        }
        await loginPromise;
        loginPromise = null;
      }

      const token = auth.getToken();
      const orgId = auth.getOrgId();

      if (token) config.headers['x-access-token'] = token;
      if (orgId) config.headers['organization-id'] = orgId;

      console.log(`[Request] ${config.method.toUpperCase()} ${config.url}`);
      return config;
    } catch (error) {
      loginPromise = null;
      console.error('[Request Interceptor Error]', error.message);
      return Promise.reject(error);
    }
  },
  (error) => Promise.reject(error)
);

// Interceptor Response: Tangani error 401 (token expired) dengan retry sekali
httpClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config;

    // Jika token expired dan belum dicoba ulang
    if (
      error.response &&
      error.response.status === 401 &&
      !originalRequest._retry
    ) {
      originalRequest._retry = true;

      try {
        console.warn('[Retry] Token expired, mencoba login ulang...');
        loginPromise = auth.login();
        await loginPromise;
        loginPromise = null;

        const token = auth.getToken();
        const orgId = auth.getOrgId();

        if (token) originalRequest.headers['x-access-token'] = token;
        if (orgId) originalRequest.headers['organization-id'] = orgId;

        return httpClient(originalRequest);
      } catch (retryError) {
        loginPromise = null;
        console.error('[Retry Gagal]', retryError.message);
        return Promise.reject(retryError);
      }
    }

    console.error(`[Response Error] ${error.message}`);
    return Promise.reject(error);
  }
);

export default httpClient;


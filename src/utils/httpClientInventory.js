import axios from 'axios';
import * as auth from '../services/inventory/auth/auth.js';

const httpClient = axios.create({
  baseURL: process.env.INVENTORY || 'http://localhost/api',
  timeout: 300000,
  headers: {
    'Content-Type': 'application/json',
  },
});

httpClient.interceptors.request.use(
  async (config) => {
    try {
      // Jika belum ada token, login terlebih dahulu
      if (!auth.getToken()) {
        await auth.login();
      }

      const token = auth.getToken();

      if (token) {
        config.headers['Authorization'] = `Token ${token}`;
      }

      console.log(`[Request] ${config.method.toUpperCase()} ${config.url}`);
      return config;
    } catch (error) {
      console.error('Gagal login atau set token:', error.message);
      return Promise.reject(error);
    }
  },
  (error) => Promise.reject(error)
);

httpClient.interceptors.response.use(
  (response) => response,
  (error) => {
    console.error(`[Error] ${error.message}`);
    return Promise.reject(error);
  }
);

export default httpClient;

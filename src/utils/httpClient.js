import axios from 'axios';
import * as auth from '../services/acounting/auth/auth.js';

const httpClient = axios.create({
  baseURL: process.env.ACCOUNTING || 'http://localhost/api',
  timeout: 1000,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Tambahkan header dinamis sebelum request dikirim
httpClient.interceptors.request.use(
  async (config) => {
    // Panggil login() sebelum menambahkan header
    try {
      // Pastikan login dijalankan terlebih dahulu
      if (!auth.getToken() || !auth.getOrgId()) {
        await auth.login(); // Jalankan login jika token atau orgId belum ada
      }

      // Ambil token dan orgId setelah login
      const token = auth.getToken();
      const orgId = auth.getOrgId();
      
      // Set header dengan token dan orgId
      if (token) {
        config.headers['x-access-token'] = token;
      }
      if (orgId) {
        config.headers['organization-id'] = orgId;
      }

      console.log(`[Request] ${config.method.toUpperCase()} ${config.url}`);
      return config;
    } catch (error) {
      console.error('Login gagal atau error saat set header:', error.message);
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

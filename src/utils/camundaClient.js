import axios from 'axios';

const httpClient = axios.create({
  baseURL: process.env.ENDPOINT_CAMUNDA || 'http://localhost/api',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Interceptor Request (tanpa auth)
httpClient.interceptors.request.use(
  (config) => {
    console.log(`[Request] ${config.method.toUpperCase()} ${config.url}`);
    return config;
  },
  (error) => Promise.reject(error)
);

// Interceptor Response (tanpa retry auth)
httpClient.interceptors.response.use(
  (response) => response,
  (error) => {
    console.error(`[Response Error] ${error.message}`);
    return Promise.reject(error);
  }
);

export default httpClient;

import axios from 'axios';
import { sanitizeInput } from '@/utils/sanitize';

const api = axios.create({
  baseURL: '/api',
  headers: {
    'Content-Type': 'application/json',
  },
});

// Request interceptor - add auth token and sanitize data
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }

    // Sanitize request data to prevent XSS
    if (config.data && typeof config.data === 'object') {
      const sanitizeObject = (obj: any): any => {
        if (typeof obj === 'string') {
          return sanitizeInput(obj);
        }
        if (Array.isArray(obj)) {
          return obj.map(sanitizeObject);
        }
        if (obj && typeof obj === 'object') {
          const sanitized: any = {};
          for (const key in obj) {
            sanitized[key] = sanitizeObject(obj[key]);
          }
          return sanitized;
        }
        return obj;
      };
      config.data = sanitizeObject(config.data);
    }

    return config;
  },
  (error) => Promise.reject(error)
);

// Response interceptor - handle errors
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('token');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export default api;

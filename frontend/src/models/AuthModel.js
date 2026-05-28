import { apiClient } from '../utils/apiClient';

export const AuthModel = {
  login: async (username, password) => {
    // Security: Basic input sanitization before sending to API
    const sanitizedUsername = username.trim();
    
    // Delegate to the secure HTTP client wrapper
    return await apiClient('auth/login', {
      method: 'POST',
      body: JSON.stringify({ username: sanitizedUsername, password })
    });
  },
  
  logout: async () => {
    sessionStorage.removeItem('is_authenticated');
    sessionStorage.removeItem('csrf_token');
    // await apiClient('auth/logout'); 
  }
};

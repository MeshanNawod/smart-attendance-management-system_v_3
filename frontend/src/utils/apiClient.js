import Swal from 'sweetalert2';

export const apiClient = async (endpoint, options = {}) => {
  // Security: Always include credentials so PHP session cookies are sent automatically.
  // We mock the CSRF token retrieval here.
  const defaultHeaders = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-CSRF-TOKEN': sessionStorage.getItem('csrf_token') || ''
  };

  const config = {
    ...options,
    headers: { ...defaultHeaders, ...options.headers },
    credentials: 'include' 
  };

  try {
    // In production, endpoint will point to your PHP API (e.g., `../api/${endpoint}`)
    // For now, we simulate secure network responses and validation
    await new Promise(resolve => setTimeout(resolve, 800)); // Simulate delay

    // Security check: Validate authentication status for protected routes
    if (sessionStorage.getItem('is_authenticated') !== 'true' && !endpoint.includes('login')) {
      throw new Error('401_UNAUTHORIZED');
    }

    // Mock API responses
    if (endpoint === 'teacher/profile') {
      return { name: 'Dr. Perera', email: 'teacher@pdn.ac.lk', department: 'Computer Science' };
    }
    
    if (endpoint === 'auth/login') {
      const { username, password } = JSON.parse(options.body);
      if (username && password) {
        // Successful login securely sets session state
        sessionStorage.setItem('is_authenticated', 'true');
        sessionStorage.setItem('csrf_token', 'mock_secure_token_abc123'); // Store mock token
        return { status: 'success', role: 'teacher' };
      }
      throw new Error('INVALID_CREDENTIALS');
    }

    return {};
  } catch (error) {
    if (error.message === '401_UNAUTHORIZED') {
      Swal.fire({
        icon: 'error',
        title: 'Session Expired',
        text: 'Your secure session has expired. Please log in again.',
        confirmButtonColor: '#6B1A2A'
      }).then(() => {
        sessionStorage.removeItem('is_authenticated');
        window.location.href = '/login';
      });
    }
    throw error;
  }
};

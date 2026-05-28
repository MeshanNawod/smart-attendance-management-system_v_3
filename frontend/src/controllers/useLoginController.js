import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import Swal from 'sweetalert2';
import { AuthModel } from '../models/AuthModel';

export function useLoginController() {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const navigate = useNavigate();

  const handleLogin = async (e) => {
    e.preventDefault();
    if (!username.trim() || !password.trim()) {
      Swal.fire({
        icon: 'info',
        title: 'Fields Required',
        text: 'Please enter both your username and password.',
        confirmButtonColor: '#6B1A2A',
        confirmButtonText: 'Okay'
      });
      return;
    }
    
    setLoading(true);
    setError('');

    try {
      const response = await AuthModel.login(username, password);
      
      if (response.status === 'success') {
        setLoading(false);
        Swal.fire({
          icon: 'success',
          title: 'Welcome to React MVC!',
          text: 'Login successful.',
          confirmButtonColor: '#6B1A2A',
          timer: 1500,
          showConfirmButton: false
        }).then(() => {
          navigate('/teacher_dashboard');
        });
      }
    } catch (err) {
      setLoading(false);
      setError('An error occurred. Please try again.');
    }
  };

  return {
    username, setUsername,
    password, setPassword,
    showPassword, setShowPassword,
    loading, error,
    handleLogin
  };
}

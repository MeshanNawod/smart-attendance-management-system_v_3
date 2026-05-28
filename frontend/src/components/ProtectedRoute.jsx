import React from 'react';
import { Navigate } from 'react-router-dom';

export default function ProtectedRoute({ children }) {
  // Security: Check local state/cookie. In a real scenario, the API Client 
  // also verifies the session cookie on the backend.
  const isAuthenticated = sessionStorage.getItem('is_authenticated') === 'true';

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  // Optionally, we can add Role-Based Access Control (RBAC) here.
  return children;
}

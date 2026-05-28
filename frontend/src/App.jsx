import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import Login from './Login';
import TeacherDashboard from './TeacherDashboard';
import ProtectedRoute from './components/ProtectedRoute';
import './index.css';

function App() {
  return (
    <BrowserRouter>
      <div className="App">
        <Routes>
          <Route path="/login" element={<Login />} />
          
          {/* SECURITY: This route is now protected */}
          <Route 
            path="/teacher_dashboard" 
            element={
              <ProtectedRoute>
                <TeacherDashboard />
              </ProtectedRoute>
            } 
          />
          
          <Route path="/" element={<Navigate to="/login" replace />} />
        </Routes>
      </div>
    </BrowserRouter>
  );
}

export default App;

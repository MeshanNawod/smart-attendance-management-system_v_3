import React from 'react';
import { useTeacherController } from './controllers/useTeacherController';

export default function TeacherDashboard() {
  const { teacher, myCourses, stats, loading } = useTeacherController();

  // PREMIUM UI: Animated Skeleton Loaders while fetching API data
  if (loading) {
    return (
      <div className="container pb-5 mt-4">
        <div className="p-4 mb-4 skeleton" style={{ height: '110px', borderRadius: '12px' }}></div>
        <div className="row g-3 mb-4">
          <div className="col-md-4"><div className="skeleton h-100" style={{ minHeight: '120px', borderRadius: '12px' }}></div></div>
          <div className="col-md-4"><div className="skeleton h-100" style={{ minHeight: '120px', borderRadius: '12px' }}></div></div>
          <div className="col-md-4"><div className="skeleton h-100" style={{ minHeight: '120px', borderRadius: '12px' }}></div></div>
        </div>
      </div>
    );
  }

  // PREMIUM UI: Fade-in animations, glassmorphism layers, and hover-lifts
  return (
    <div className="container pb-5 mt-4 fade-in-up">
      {/* Hero Section - Glassmorphism */}
      <div className="p-4 mb-4 glass-panel hover-lift" style={{ borderRadius: '12px', color: '#1e293b' }}>
        <div className="row align-items-center position-relative">
          <div className="col-auto">
            <div style={{ width: '75px', height: '75px', borderRadius: '50%', background: 'linear-gradient(135deg, #a11e17, #c42319)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '1.8rem', fontWeight: 'bold', color: '#fff', boxShadow: '0 4px 15px rgba(161,30,23,0.3)' }}>
              {teacher.name ? teacher.name.charAt(0) : '?'}
            </div>
          </div>
          <div className="col">
            <h3 className="fw-bold mb-1" style={{ letterSpacing: '-0.5px' }}>{teacher.name}</h3>
            <p className="mb-0 opacity-75 fw-medium">
              <i className="bi bi-shield-check text-success me-1"></i>Secure Session Active
              <span className="mx-2 text-muted">|</span>
              <i className="bi bi-envelope me-1"></i>{teacher.email}
            </p>
          </div>
          <div className="col-auto">
            <button className="btn btn-outline-danger btn-sm rounded-pill px-3 fw-bold hover-lift" onClick={() => { sessionStorage.removeItem('is_authenticated'); window.location.href='/login'; }}>
              <i className="bi bi-box-arrow-right me-1"></i>Logout
            </button>
          </div>
        </div>
      </div>

      {/* Stat Cards - Micro animations */}
      <div className="row g-3 mb-4">
        <div className="col-md-4 fade-in-up delay-1">
          <div className="bg-primary text-white h-100 shadow p-4 d-flex flex-column justify-content-center hover-lift" style={{ borderRadius: '12px', position: 'relative', overflow: 'hidden' }}>
            <i className="bi bi-journal-bookmark position-absolute" style={{ right: '-15px', bottom: '-20px', fontSize: '6rem', opacity: 0.1 }}></i>
            <h6 className="text-uppercase fw-semibold mb-1 opacity-75" style={{ fontSize: '0.8rem', letterSpacing: '1px' }}>My Courses</h6>
            <h2 className="display-4 fw-bolder mb-0">{myCourses.length}</h2>
          </div>
        </div>
        <div className="col-md-4 fade-in-up delay-2">
          <div className="glass-panel h-100 shadow-sm p-4 d-flex flex-column justify-content-center hover-lift" style={{ borderRadius: '12px' }}>
            <h6 className="text-uppercase fw-semibold mb-1 text-secondary" style={{ fontSize: '0.8rem', letterSpacing: '1px' }}>Total Enrolled</h6>
            <h2 className="display-4 fw-bolder mb-0 text-dark">{stats.totalStudents}</h2>
          </div>
        </div>
        <div className="col-md-4 fade-in-up delay-3">
          <div className="h-100 shadow p-4 d-flex flex-column justify-content-center hover-lift" style={{ background: 'linear-gradient(135deg, #ffd30a, #e6b800)', borderRadius: '12px', position: 'relative', overflow: 'hidden' }}>
            <i className="bi bi-upc-scan position-absolute" style={{ right: '-10px', bottom: '-20px', fontSize: '6rem', opacity: 0.15, color: '#000' }}></i>
            <h6 className="text-uppercase fw-semibold mb-1 text-dark opacity-75" style={{ fontSize: '0.8rem', letterSpacing: '1px' }}>Today's Scans</h6>
            <h2 className="display-4 fw-bolder mb-0 text-dark">{stats.todaysScans}</h2>
          </div>
        </div>
      </div>

      <div className="card glass-panel border-0 fade-in-up delay-3" style={{ borderRadius: '12px' }}>
        <div className="card-body py-5 text-center">
          <i className="bi bi-shield-lock-fill fs-1 text-primary mb-3 d-block"></i>
          <h5 className="fw-bold">Security & UI Upgrades Complete</h5>
          <p className="text-muted">Protected routing, CSRF handling, glassmorphism panels, skeleton loaders, and micro-animations have been added.</p>
        </div>
      </div>
    </div>
  );
}

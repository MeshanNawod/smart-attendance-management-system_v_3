import React, { useEffect, useRef } from 'react';
import './Login.css';
import { useLoginController } from './controllers/useLoginController';

export default function Login() {
  // MVC: We get all our state and logic from the Controller
  const {
    username, setUsername,
    password, setPassword,
    showPassword, setShowPassword,
    loading, error, handleLogin
  } = useLoginController();
  
  const canvasRef = useRef(null);

  // View Logic (UI interactions, Canvas) belongs here in the View
  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    let W, H;
    
    function resize() {
      W = canvas.width = window.innerWidth;
      H = canvas.height = window.innerHeight;
    }
    resize();
    window.addEventListener('resize', resize);

    const particles = [];
    const PARTICLE_COUNT = 80;
    const colors = ['#6B1A2A', '#8B2240', '#A12E4F', '#C45A77', '#D97A96', '#E8A0B5'];

    class Particle {
      constructor() {
        this.reset();
        this.y = Math.random() * H;
      }
      reset() {
        this.x = Math.random() * W;
        this.y = H + 20;
        this.size = Math.random() * 4 + 2;
        this.speedY = -(Math.random() * 0.4 + 0.15);
        this.speedX = (Math.random() - 0.5) * 0.3;
        this.color = colors[Math.floor(Math.random() * colors.length)];
        this.opacity = Math.random() * 0.5 + 0.2;
        this.wobble = Math.random() * Math.PI * 2;
        this.wobbleSpeed = Math.random() * 0.02 + 0.005;
        this.wobbleAmp = Math.random() * 0.5 + 0.2;
      }
      update(mouse) {
        this.wobble += this.wobbleSpeed;
        this.x += this.speedX + Math.sin(this.wobble) * this.wobbleAmp;
        this.y += this.speedY;

        if (mouse) {
          const dx = this.x - mouse.x;
          const dy = this.y - mouse.y;
          const dist = Math.sqrt(dx * dx + dy * dy);
          if (dist < 120) {
            const force = (120 - dist) / 120;
            const angle = Math.atan2(dy, dx);
            this.x += Math.cos(angle) * force * 4;
            this.y += Math.sin(angle) * force * 4;
          }
        }

        if (this.y < -20 || this.x < -20 || this.x > W + 20) {
          this.reset();
          this.y = H + 20;
        }
      }
      draw() {
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
        ctx.fillStyle = this.color;
        ctx.globalAlpha = this.opacity;
        ctx.fill();
        ctx.globalAlpha = 1;
      }
    }

    for (let i = 0; i < PARTICLE_COUNT; i++) {
      particles.push(new Particle());
    }

    let mouse = null;
    let mouseTimeout = null;
    let mouseClickBurst = [];
    let animId;

    const onMouseMove = (e) => {
      mouse = { x: e.clientX, y: e.clientY };
      clearTimeout(mouseTimeout);
      mouseTimeout = setTimeout(() => { mouse = null; }, 2000);
    };
    
    const onMouseLeave = () => { mouse = null; };

    const onClick = (e) => {
      for (let i = 0; i < 12; i++) {
        const angle = (Math.PI * 2 / 12) * i;
        const speed = Math.random() * 3 + 2;
        mouseClickBurst.push({
          x: e.clientX, y: e.clientY,
          vx: Math.cos(angle) * speed,
          vy: Math.sin(angle) * speed,
          size: Math.random() * 3 + 1,
          life: 1,
          color: colors[Math.floor(Math.random() * colors.length)]
        });
      }
    };

    document.addEventListener('mousemove', onMouseMove);
    document.addEventListener('mouseleave', onMouseLeave);
    document.addEventListener('click', onClick);

    function animate() {
      ctx.clearRect(0, 0, W, H);

      for (const p of particles) {
        p.update(mouse);
        p.draw();
      }

      for (let i = mouseClickBurst.length - 1; i >= 0; i--) {
        const b = mouseClickBurst[i];
        b.x += b.vx;
        b.y += b.vy;
        b.vy += 0.05;
        b.life -= 0.025;
        if (b.life <= 0) { mouseClickBurst.splice(i, 1); continue; }
        ctx.beginPath();
        ctx.arc(b.x, b.y, b.size * b.life, 0, Math.PI * 2);
        ctx.fillStyle = b.color;
        ctx.globalAlpha = b.life * 0.6;
        ctx.fill();
        ctx.globalAlpha = 1;
      }

      animId = requestAnimationFrame(animate);
    }

    if (window.innerWidth > 768) animate();
    else for (const p of particles) p.draw();

    return () => {
      window.removeEventListener('resize', resize);
      document.removeEventListener('mousemove', onMouseMove);
      document.removeEventListener('mouseleave', onMouseLeave);
      document.removeEventListener('click', onClick);
      cancelAnimationFrame(animId);
      clearTimeout(mouseTimeout);
    };
  }, []);

  return (
    <>
      <canvas id="particle-canvas" ref={canvasRef}></canvas>

      <div className="brand-bar">
        <i className="bi bi-fingerprint brand-icon"></i>
        <div>
          <div className="brand-name">Sentinel Swarm</div>
          <div className="brand-sub">University of Peradeniya — AMS</div>
        </div>
      </div>

      <div className="login-wrapper">
        <div className="login-card">
          <div className="card-header-strip">
            <div className="lock-icon"><i className="bi bi-shield-lock"></i></div>
            <h1>Sign In</h1>
            <p>Enter your credentials to access the system</p>
          </div>

          <div className="card-body-inner">
            {error && (
              <div className="alert-login alert-danger" role="alert">
                <i className="bi bi-exclamation-circle flex-shrink-0 mt-1"></i>
                <div>{error}</div>
              </div>
            )}

            <form onSubmit={handleLogin} noValidate>
              <div className="mb-3">
                <label className="form-label" htmlFor="username">Username or Student ID</label>
                <div className="input-group">
                  <span className="input-group-text"><i className="bi bi-person"></i></span>
                  <input
                    type="text" id="username" name="username"
                    className="form-control" placeholder="e.g. admin or S/20/123"
                    required autoComplete="username"
                    value={username}
                    onChange={(e) => setUsername(e.target.value)}
                  />
                </div>
              </div>

              <div className="mb-3">
                <label className="form-label" htmlFor="password">Password</label>
                <div className="input-group">
                  <span className="input-group-text"><i className="bi bi-key"></i></span>
                  <input
                    type={showPassword ? 'text' : 'password'} id="password" name="password"
                    className="form-control" placeholder="Enter your password"
                    required autoComplete="current-password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                  />
                  <button 
                    type="button" 
                    className="btn-pw-toggle" 
                    title="Show / hide password"
                    onClick={() => setShowPassword(!showPassword)}
                  >
                    <i className={showPassword ? "bi bi-eye-slash" : "bi bi-eye"}></i>
                  </button>
                </div>
              </div>

              <button type="submit" className="btn-signin" disabled={loading}>
                {loading ? (
                  <><span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Signing in...</>
                ) : (
                  'Sign In'
                )}
              </button>
            </form>

            <div className="helper-note">
              <i className="bi bi-info-circle me-1"></i>
              Students: your initial password is your Student ID.<br />
              Contact your administrator if you cannot log in.
            </div>
          </div>
        </div>
      </div>

      <div className="login-footer">
        &copy; {new Date().getFullYear()} Sentinel Swarm AMS &mdash; University of Peradeniya
      </div>
    </>
  );
}

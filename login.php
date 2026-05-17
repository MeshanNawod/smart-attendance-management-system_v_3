<?php
require_once 'includes/config.php';
$sessionPath = __DIR__ . '/sessions';
if (is_dir($sessionPath)) session_save_path($sessionPath);
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime'=>SESSION_LIFETIME,'path'=>'/','secure'=>SESSION_COOKIE_SECURE,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in']) {
    $r = $_SESSION['user_role'] ?? '';
    if ($r==='admin')   { header('Location: index.php'); exit; }
    if ($r==='teacher') { header('Location: teacher_dashboard.php'); exit; }
    if ($r==='student') { header('Location: student_dashboard.php'); exit; }
    $_SESSION=[]; session_destroy(); session_start();
}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];
$lk = 'login_lockout_'.md5($_SERVER['REMOTE_ADDR']??'');
$ak = 'login_attempts_'.md5($_SERVER['REMOTE_ADDR']??'');
$locked=false; $lr=0;
if (isset($_SESSION[$lk]) && $_SESSION[$lk]>time()) { $locked=true; $lr=$_SESSION[$lk]-time(); }
else unset($_SESSION[$lk],$_SESSION[$ak]);
$err='';
$rsn=$_GET['reason']??'';
if ($rsn==='timeout') $err='Session expired. Please log in again.';
elseif ($rsn==='hijack') $err='Security violation detected.';
if ($_SERVER['REQUEST_METHOD']==='POST' && !$locked) {
    if (!hash_equals($csrf,$_POST['csrf_token']??'')) { $err='Token mismatch.'; }
    else {
        $u=trim($_POST['username']??''); $p=$_POST['password']??'';
        if (!$u||!$p) { $err='Please enter both username and password.'; }
        else {
            $ok=false; $usr=null;
            try {
                require_once 'includes/db.php';
                $s=$pdo->prepare("SELECT * FROM users WHERE username=? AND is_active=1"); $s->execute([$u]); $usr=$s->fetch(PDO::FETCH_ASSOC);
                if ($usr && password_verify($p,$usr['password_hash'])) $ok=true;
                elseif ($usr && $usr['role']===ROLE_STUDENT && $p===$u) { $ok=true; $pdo->prepare("UPDATE users SET must_change_password=1 WHERE id=?")->execute([$usr['id']]); $usr['must_change_password']=1; }
                if (!$usr) {
                    $s2=$pdo->prepare("SELECT student_no,student_name FROM students WHERE student_no=?"); $s2->execute([$u]); $st=$s2->fetch(PDO::FETCH_ASSOC);
                    if ($st && $p===$u) {
                        $h=password_hash($u,PASSWORD_BCRYPT); $pdo->prepare("INSERT INTO users(username,password_hash,role,full_name,must_change_password)VALUES(?,?,'student',?,1)")->execute([$u,$h,$st['student_name']??$u]);
                        $uid=$pdo->lastInsertId(); $pdo->prepare("UPDATE students SET user_id=? WHERE student_no=?")->execute([$uid,$u]);
                        $ok=true; $usr=['id'=>$uid,'username'=>$u,'role'=>'student','full_name'=>$st['student_name']??$u,'must_change_password'=>1];
                    }
                }
            } catch(PDOException $e){ error_log($e->getMessage()); }
            if ($ok && $usr) {
                session_unset(); session_destroy();
                session_set_cookie_params(['lifetime'=>SESSION_LIFETIME,'path'=>'/','secure'=>SESSION_COOKIE_SECURE,'httponly'=>true,'samesite'=>'Lax']);
                session_start(); session_regenerate_id(true);
                $_SESSION+=['user_logged_in'=>true,'user_id'=>$usr['id'],'username'=>$usr['username'],'user_role'=>$usr['role'],'full_name'=>$usr['full_name']??'','must_change_password'=>(bool)($usr['must_change_password']??0),'last_activity'=>time(),'csrf_token'=>$csrf,'role_locked'=>$usr['role']];
                if ($usr['role']==='student') $_SESSION['student_no']=$usr['username'];
                elseif ($usr['role']==='teacher') { try { $t=$pdo->prepare("SELECT id FROM teachers WHERE user_id=?"); $t->execute([$usr['id']]); $tr=$t->fetch(); if($tr) $_SESSION['teacher_id']=$tr['id']; } catch(PDOException $e){} }
                try { $pdo->prepare("UPDATE users SET last_login=NOW() WHERE id=?")->execute([$usr['id']]); } catch(PDOException $e){}
                unset($_SESSION[$lk],$_SESSION[$ak]);
                $rd=$_GET['redirect']??'';
                if($rd){$rd=filter_var($rd,FILTER_SANITIZE_URL);if(!preg_match('/^[a-zA-Z0-9\/\._\-?&=%]+$/',$rd))$rd='';}
                if(!$rd){switch($usr['role']){case 'admin':$rd='index.php';break;case 'teacher':$rd='teacher_dashboard.php';break;default:$rd='student_dashboard.php';}}
                header('Cache-Control: no-cache,no-store,must-revalidate'); header('Location: '.$rd); exit;
            } else {
                $_SESSION[$ak]=($_SESSION[$ak]??0)+1;
                if ($_SESSION[$ak]>=MAX_LOGIN_ATTEMPTS){$_SESSION[$lk]=time()+LOGIN_LOCKOUT_SECONDS;$locked=true;$lr=LOGIN_LOCKOUT_SECONDS;$err='Too many failed attempts. Account locked for '.$lr.' seconds.';}
                else $err='Invalid credentials. '.( MAX_LOGIN_ATTEMPTS-$_SESSION[$ak]).' attempt(s) remaining.';
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign In — Sentinel Swarm AMS</title>
<meta name="description" content="Secure sign-in portal for the Sentinel Swarm Attendance Management System.">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
/* ── Tokens ── */
:root {
  --primary-color: #6B1A2A; /* UoP Maroon */
  --primary-hover: #4A121D;
  --bg-color: #fdfdfd;
  --card-bg: #FFFFFF;
  --text-dark: #1e293b;
  --text-muted: #64748b;
  --border-color: #e2e8f0;
  --input-bg: #fff;
  --focus-ring: rgba(107, 26, 42, 0.1);
}

/* ── Page ── */
*, *::before, *::after { box-sizing: border-box; }
body {
  font-family: 'Inter', sans-serif;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background-color: var(--bg-color);
  -webkit-font-smoothing: antialiased;
  color: var(--text-dark);
}

/* ── Top Brand Bar ── */
.brand-bar {
  background: var(--card-bg);
  padding: 1rem 2rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  border-bottom: 1px solid var(--border-color);
}
.brand-bar .brand-icon {
  font-size: 1.5rem;
  color: var(--primary-color);
  line-height: 1;
}
.brand-bar .brand-name {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--text-dark);
  letter-spacing: -0.02em;
}
.brand-bar .brand-sub {
  font-size: 0.75rem;
  color: var(--text-muted);
  font-weight: 400;
  margin-top: 2px;
}

/* ── Main area ── */
.login-wrapper {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2.5rem 1rem;
}

/* ── Card ── */
.login-card {
  background: var(--card-bg);
  border-radius: 8px;
  border: 1px solid var(--border-color);
  box-shadow: none;
  width: 100%;
  max-width: 400px;
  overflow: hidden;
}

/* Card header strip */
.card-header-strip {
  padding: 2.5rem 2rem 1.5rem;
  text-align: center;
}
.card-header-strip .lock-icon {
  width: 56px;
  height: 56px;
  background: var(--bg-color);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1.25rem;
  font-size: 1.5rem;
  color: var(--primary-color);
  border: 2px solid var(--border-color);
}
.card-header-strip h1 {
  font-size: 1.4rem;
  font-weight: 600;
  color: var(--text-dark);
  margin: 0 0 0.5rem;
  letter-spacing: -0.01em;
}
.card-header-strip p {
  font-size: 0.9rem;
  color: var(--text-muted);
  margin: 0;
}

/* Card body */
.card-body-inner {
  padding: 0 2rem 2.5rem;
}

/* Labels */
.form-label {
  font-size: 0.85rem;
  font-weight: 500;
  color: var(--text-dark);
  margin-bottom: 0.5rem;
}

/* Input groups */
.input-group-text {
  background: var(--input-bg);
  border-color: var(--border-color);
  color: var(--text-muted);
}
.form-control {
  font-family: 'Inter', sans-serif;
  font-size: 0.95rem;
  padding: 0.6rem 0.75rem;
  border-color: var(--border-color);
  background-color: var(--input-bg);
  color: var(--text-dark);
  transition: all 0.2s ease;
}
.form-control:focus {
  background-color: var(--card-bg);
  border-color: var(--primary-color);
  box-shadow: 0 0 0 3px var(--focus-ring);
}
.input-group:focus-within .input-group-text {
  border-color: var(--primary-color);
  background-color: var(--card-bg);
  color: var(--primary-color);
}
.btn-pw-toggle {
  background: var(--input-bg);
  border: 1px solid var(--border-color);
  border-left: none;
  color: var(--text-muted);
  cursor: pointer;
  padding: 0 1rem;
  transition: all 0.2s ease;
  border-radius: 0 6px 6px 0;
}
.btn-pw-toggle:hover, .input-group:focus-within .btn-pw-toggle {
  background-color: var(--card-bg);
  color: var(--text-dark);
}
.input-group:focus-within .btn-pw-toggle {
  border-color: var(--primary-color);
}

/* Submit button */
.btn-signin {
  background: var(--primary-color);
  color: #ffffff;
  border: none;
  border-radius: 6px;
  padding: 0.8rem 1.5rem;
  font-family: 'Inter', sans-serif;
  font-size: 0.95rem;
  font-weight: 500;
  width: 100%;
  transition: all 0.2s ease;
  cursor: pointer;
  margin-top: 1rem;
}
.btn-signin:hover:not(:disabled) {
  background: var(--primary-hover);
  transform: translateY(-1px);
}
.btn-signin:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

/* Helper note */
.helper-note {
  font-size: 0.8rem;
  color: var(--text-muted);
  text-align: center;
  margin-top: 1.5rem;
  line-height: 1.5;
  background: var(--bg-color);
  padding: 1rem;
  border-radius: 6px;
}
.helper-note i {
  color: var(--accent-color);
}

/* Alert */
.alert-login {
  font-size: 0.85rem;
  border-radius: 6px;
  padding: 0.75rem 1rem;
  margin-bottom: 1.25rem;
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  border: 1px solid transparent;
}
.alert-danger {
  background-color: #FEF2F2;
  border-color: #FCA5A5;
  color: #991B1B;
}

/* ── Footer ── */
.login-footer {
  text-align: center;
  padding: 1.5rem;
  font-size: 0.8rem;
  color: var(--text-muted);
}

/* Input border-radius fix */
.input-group > .form-control:not(:last-child) { border-radius: 6px 0 0 6px !important; }
.input-group > :first-child { border-radius: 6px 0 0 6px !important; }

/* ── Anti-Gravity Particle Canvas ── */
#particle-canvas {
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100%;
  pointer-events: none;
  z-index: 0;
}
.login-wrapper, .brand-bar, .login-footer {
  position: relative;
  z-index: 1;
}
</style>
</head>
<body>

<!-- Anti-Gravity Particle Canvas -->
<canvas id="particle-canvas"></canvas>

<!-- Top brand bar -->
<div class="brand-bar">
  <i class="bi bi-fingerprint brand-icon"></i>
  <div>
    <div class="brand-name">Sentinel Swarm</div>
    <div class="brand-sub">University of Peradeniya — AMS</div>
  </div>
</div>

<!-- Login area -->
<div class="login-wrapper">
  <div class="login-card">

    <!-- Card header -->
    <div class="card-header-strip">
      <div class="lock-icon"><i class="bi bi-shield-lock"></i></div>
      <h1>Sign In</h1>
      <p>Enter your credentials to access the system</p>
    </div>

    <!-- Card body -->
    <div class="card-body-inner">

      <?php if ($err): ?>
      <div class="alert-login alert-danger" role="alert">
        <i class="bi bi-exclamation-circle flex-shrink-0 mt-1"></i>
        <div><?php echo htmlspecialchars($err); ?></div>
      </div>
      <?php endif; ?>

      <?php if ($locked): ?>
      <script>
      document.addEventListener("DOMContentLoaded", function() {
        let s = <?php echo $lr; ?>;
        Swal.fire({
          icon: 'warning', title: 'Account Temporarily Locked',
          html: `Too many failed attempts.<br>Please wait <b id="sw-count">${s}</b> second(s).`,
          background: '#fff', confirmButtonColor: '#6B1A2A',
          showConfirmButton: false, allowOutsideClick: false,
          didOpen: () => {
            const el = document.getElementById('sw-count');
            const t = setInterval(() => { s--; el.textContent = s; if (s <= 0) { clearInterval(t); location.reload(); } }, 1000);
          }
        });
      });
      </script>
      <?php endif; ?>

      <form method="POST" id="login-form" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">

        <!-- Username -->
        <div class="mb-3">
          <label class="form-label" for="username">Username or Student ID</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input
              type="text" id="username" name="username"
              class="form-control" placeholder="e.g. admin or S/20/123"
              required autocomplete="username"
              value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
            >
          </div>
        </div>

        <!-- Password -->
        <div class="mb-3">
          <label class="form-label" for="password">Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-key"></i></span>
            <input
              type="password" id="password" name="password"
              class="form-control" placeholder="Enter your password"
              required autocomplete="current-password"
            >
            <button type="button" class="btn-pw-toggle" id="pw-toggle" title="Show / hide password">
              <i class="bi bi-eye" id="pw-eye"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-signin" id="btn-submit" <?php echo $locked ? 'disabled' : ''; ?>>
          Sign In
        </button>
      </form>

      <div class="helper-note">
        <i class="bi bi-info-circle me-1"></i>
        Students: your initial password is your Student ID.<br>
        Contact your administrator if you cannot log in.
      </div>

    </div>
  </div>
</div>

<div class="login-footer">
  &copy; <?php echo date('Y'); ?> Sentinel Swarm AMS &mdash; University of Peradeniya
</div>

<script>
/* Password toggle */
document.getElementById('pw-toggle').addEventListener('click', function() {
  const p = document.getElementById('password');
  const e = document.getElementById('pw-eye');
  p.type = p.type === 'password' ? 'text' : 'password';
  e.className = p.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
});

/* Form submit validation */
document.getElementById('login-form').addEventListener('submit', function(e) {
  const u = document.getElementById('username');
  const p = document.getElementById('password');
  if (!u.value.trim() || !p.value.trim()) {
    e.preventDefault();
    Swal.fire({
      icon: 'info', title: 'Fields Required',
      text: 'Please enter both your username and password.',
      confirmButtonColor: '#6B1A2A',
      confirmButtonText: 'Okay'
    }).then(() => { (!u.value.trim() ? u : p).focus(); });
    return;
  }
  const btn = document.getElementById('btn-submit');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Signing in...';
});

<?php if ($err && !$locked): ?>
document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('username').focus();
});
<?php endif; ?>
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Anti-Gravity Mouse Particle Effect -->
<script>
(function() {
  const canvas = document.getElementById('particle-canvas');
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

      // Mouse repulsion
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
    const p = new Particle();
    p.y = Math.random() * H;
    particles.push(p);
  }

  let mouse = null;
  let mouseTimeout = null;
  let mouseClickBurst = [];
  let animId;

  document.addEventListener('mousemove', function(e) {
    mouse = { x: e.clientX, y: e.clientY };
    clearTimeout(mouseTimeout);
    mouseTimeout = setTimeout(function() { mouse = null; }, 2000);
  });

  document.addEventListener('mouseleave', function() { mouse = null; });

  document.addEventListener('click', function(e) {
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
  });

  function animate() {
    ctx.clearRect(0, 0, W, H);

    // Update & draw particles
    for (const p of particles) {
      p.update(mouse);
      p.draw();
    }

    // Draw click burst particles
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

  if (window.innerWidth > 768) {
    animate();
  } else {
    // On mobile, just show static particles
    for (const p of particles) { p.draw(); }
  }
})();
</script>
</body>
</html>

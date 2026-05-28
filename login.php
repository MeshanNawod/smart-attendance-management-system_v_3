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
<link rel="icon" type="image/svg+xml" href="<?php echo htmlspecialchars(asset_url('assets/favicon.svg'), ENT_QUOTES, 'UTF-8'); ?>">
<meta name="description" content="Secure sign-in portal for the Sentinel Swarm Attendance Management System.">
<link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('css/vendor/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('css/vendor/bootstrap-icons.css'), ENT_QUOTES, 'UTF-8'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>">
<script src="<?php echo htmlspecialchars(asset_url('js/vendor/sweetalert2.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<style>
/* ── Tokens (Google-style) ── */
:root {
  --primary-color: #1a73e8;
  --primary-hover: #1557b0;
  --bg-color: #f0f4f9;
  --card-bg: #FFFFFF;
  --text-dark: #202124;
  --text-muted: #5f6368;
  --border-color: #dadce0;
  --input-bg: #fff;
  --focus-ring: rgba(26, 115, 232, 0.15);
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
  background: #fff;
  padding: 0.75rem 2rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  border-bottom: 1px solid var(--border-color);
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.brand-bar .brand-icon {
  font-size: 1.4rem;
  color: var(--primary-color);
  line-height: 1;
}
.brand-bar .brand-name {
  font-size: 1.05rem;
  font-weight: 600;
  color: var(--text-dark);
  letter-spacing: -0.01em;
}
.brand-bar .brand-sub {
  font-size: 0.72rem;
  color: var(--text-muted);
  font-weight: 400;
  margin-top: 1px;
}

/* ── Main area ── */
.login-wrapper {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem 1rem;
}

/* ── Card ── */
.login-card {
  background: var(--card-bg);
  border-radius: 12px;
  border: 1px solid var(--border-color);
  box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 4px 16px rgba(0,0,0,0.04);
  width: 100%;
  max-width: 400px;
  overflow: hidden;
}

/* Card header strip */
.card-header-strip {
  padding: 2rem 2rem 1rem;
  text-align: center;
}
.card-header-strip .lock-icon {
  width: 48px;
  height: 48px;
  background: #e8f0fe;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1rem;
  font-size: 1.3rem;
  color: var(--primary-color);
  border: none;
}
.card-header-strip h1 {
  font-size: 1.3rem;
  font-weight: 500;
  color: var(--text-dark);
  margin: 0 0 0.35rem;
  letter-spacing: -0.01em;
}
.card-header-strip p {
  font-size: 0.85rem;
  color: var(--text-muted);
  margin: 0;
}

/* Card body */
.card-body-inner {
  padding: 0 2rem 2rem;
}

/* Labels */
.form-label {
  font-size: 0.82rem;
  font-weight: 500;
  color: var(--text-dark);
  margin-bottom: 0.4rem;
}

/* Input groups */
.input-group-text {
  background: var(--input-bg);
  border-color: var(--border-color);
  color: var(--text-muted);
  border-radius: 8px 0 0 8px !important;
}
.form-control {
  font-family: 'Inter', sans-serif;
  font-size: 0.95rem;
  padding: 0.55rem 0.75rem;
  border-color: var(--border-color);
  background-color: var(--input-bg);
  color: var(--text-dark);
  transition: all 0.2s ease;
  border-radius: 8px;
}
.form-control:focus {
  background-color: var(--card-bg);
  border-color: var(--primary-color);
  box-shadow: 0 0 0 2px var(--focus-ring);
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
  padding: 0 0.9rem;
  transition: all 0.2s ease;
  border-radius: 0 8px 8px 0;
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
  border-radius: 100px;
  padding: 0.7rem 1.5rem;
  font-family: 'Inter', sans-serif;
  font-size: 0.95rem;
  font-weight: 500;
  width: 100%;
  transition: box-shadow 0.2s ease, background 0.2s ease;
  cursor: pointer;
  margin-top: 0.75rem;
  letter-spacing: 0.01em;
}
.btn-signin:hover:not(:disabled) {
  background: var(--primary-hover);
  box-shadow: 0 1px 3px rgba(26,115,232,0.3);
}
.btn-signin:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Helper note */
.helper-note {
  font-size: 0.78rem;
  color: var(--text-muted);
  text-align: center;
  margin-top: 1.25rem;
  line-height: 1.5;
  background: #f8f9fa;
  padding: 0.85rem 1rem;
  border-radius: 8px;
}

/* Alert */
.alert-login {
  font-size: 0.85rem;
  border-radius: 8px;
  padding: 0.7rem 1rem;
  margin-bottom: 1rem;
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  border: 1px solid transparent;
}
.alert-danger {
  background-color: #fce8e6;
  border-color: #f5c6c2;
  color: #c5221f;
}

/* ── Footer ── */
.login-footer {
  text-align: center;
  padding: 1.25rem;
  font-size: 0.78rem;
  color: var(--text-muted);
}

/* Input border-radius fix */
.input-group > .form-control:not(:last-child) { border-radius: 0 !important; }
.input-group > :first-child { border-radius: 8px 0 0 8px !important; }

/* ── Glow Aura Effect ── */
#glow-aura {
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100%;
  pointer-events: none;
  z-index: 0;
  transition: opacity 0.3s ease;
}
/* Rainbow glow layers */
#glow-aura::before,
#glow-aura::after {
  content: '';
  position: absolute;
  top: 0; left: 0;
  width: 100%; height: 100%;
  transition: transform 0.08s ease-out;
}
#glow-aura::before {
  background:
    radial-gradient(600px circle at 50% 50%, rgba(255,60,120,0.12) 0%, transparent 50%),
    radial-gradient(450px circle at 50% 50%, rgba(120,60,255,0.10) 0%, transparent 40%);
  filter: blur(18px);
  animation: hueShift1 6s linear infinite;
}
#glow-aura::after {
  background:
    radial-gradient(500px circle at 50% 50%, rgba(60,200,255,0.10) 0%, transparent 50%),
    radial-gradient(350px circle at 50% 50%, rgba(255,200,60,0.08) 0%, transparent 40%);
  filter: blur(24px);
  animation: hueShift2 8s linear infinite;
}
@keyframes hueShift1 {
  0%   { filter: hue-rotate(0deg) blur(18px); }
  50%  { filter: hue-rotate(180deg) blur(18px); }
  100% { filter: hue-rotate(360deg) blur(18px); }
}
@keyframes hueShift2 {
  0%   { filter: hue-rotate(0deg) blur(24px); }
  50%  { filter: hue-rotate(-180deg) blur(24px); }
  100% { filter: hue-rotate(-360deg) blur(24px); }
}
.login-wrapper, .brand-bar, .login-footer {
  position: relative;
  z-index: 1;
}
</style>
</head>
<body>

<!-- Glow effect -->
<div id="glow-aura"></div>

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
          background: '#fff', confirmButtonColor: '#1a73e8',
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
          confirmButtonColor: '#1a73e8',
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
<script src="<?php echo htmlspecialchars(asset_url('js/vendor/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>

<!-- Glow Aura - Rainbow interactive mouse-following glow (Google Gemini style) -->
<script>
(function() {
  const aura = document.getElementById('glow-aura');
  if (!aura) return;

  let mx = window.innerWidth / 2, my = window.innerHeight / 2;
  let tx = mx, ty = my;

  document.addEventListener('mousemove', function(e) {
    tx = e.clientX;
    ty = e.clientY;
  });
  document.addEventListener('touchmove', function(e) {
    const t = e.touches[0];
    if (t) { tx = t.clientX; ty = t.clientY; }
  }, { passive: true });

  const before = window.getComputedStyle(aura, '::before');
  const after = window.getComputedStyle(aura, '::after');

  function smoothGlow() {
    mx += (tx - mx) * 0.07;
    my += (ty - my) * 0.07;
    // Move pseudo-element backgrounds via CSS custom properties on the parent
    aura.style.setProperty('--mx', mx + 'px');
    aura.style.setProperty('--my', my + 'px');
    aura.style.background =
      'radial-gradient(600px circle at ' + mx + 'px ' + my + 'px, rgba(255,60,120,0.10) 0%, transparent 50%),' +
      'radial-gradient(400px circle at ' + mx + 'px ' + my + 'px, rgba(120,60,255,0.08) 0%, transparent 40%)';
    requestAnimationFrame(smoothGlow);
  }
  smoothGlow();

  // Click burst
  document.addEventListener('click', function(e) {
    aura.style.transition = 'background 0.05s ease';
    aura.style.background =
      'radial-gradient(500px circle at ' + e.clientX + 'px ' + e.clientY + 'px, rgba(255,100,150,0.22) 0%, transparent 50%),' +
      'radial-gradient(300px circle at ' + e.clientX + 'px ' + e.clientY + 'px, rgba(100,150,255,0.16) 0%, transparent 40%)';
    setTimeout(function() {
      aura.style.transition = '';
    }, 50);
  });
})();
</script>
</body>
</html>

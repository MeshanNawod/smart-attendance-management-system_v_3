<?php
require_once __DIR__ . '/../config/app.php';

$sessionPath = __DIR__ . '/../../sessions';
if (is_dir($sessionPath)) {
    session_save_path($sessionPath);
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => SESSION_COOKIE_SECURE,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (isset($_SESSION['role_locked']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] !== $_SESSION['role_locked']) {
    session_unset();
    session_destroy();
    header('Location: /csc2052/login.php?reason=hijack');
    exit;
}

// v4: bind the session to the browser only. Including the IP address logged
// students out whenever their phone moved between Wi-Fi and mobile data.
$fingerprint = hash('sha256', 'ua:' . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
if (isset($_SESSION['fingerprint']) && $_SESSION['fingerprint'] !== $fingerprint) {
    session_unset();
    session_destroy();
    header('Location: /csc2052/login.php?reason=hijack');
    exit;
}
if (!isset($_SESSION['fingerprint'])) {
    $_SESSION['fingerprint'] = $fingerprint;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verifyCsrfToken() {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'CSRF token validation failed.']);
        exit;
    }
}

function requireCsrfIfMutating() {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array(strtoupper($method), ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
        verifyCsrfToken();
    }
}

function requireRole(string ...$roles): void {
    if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], $roles, true)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Access denied. Insufficient permissions.']);
        exit;
    }
}

function isAdmin(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function isTeacher(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'teacher';
}

function isStudent(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'student';
}

function currentRole(): string {
    return $_SESSION['user_role'] ?? 'none';
}

function currentUser(): array {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? '',
        'role' => $_SESSION['user_role'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'teacher_id' => $_SESSION['teacher_id'] ?? null,
        'student_no' => $_SESSION['student_no'] ?? null,
    ];
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 0');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header('X-Permitted-Cross-Domain-Policies: none');
    header('Permissions-Policy: geolocation=(), microphone=(self), camera=(self)');
    header("Content-Security-Policy: default-src 'self' https://fonts.googleapis.com https://fonts.gstatic.com; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.gstatic.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; media-src 'self' blob:; connect-src 'self' http: https: https://*.firebaseio.com https://*.googleapis.com;");
}

$is_legacy_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$is_multi_role = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;

if (!$is_legacy_admin && !$is_multi_role) {
    session_unset();
    session_destroy();
    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
    header('Location: /csc2052/login.php' . ($redirect ? '?redirect=' . $redirect : ''));
    exit;
}

if ($is_legacy_admin && !$is_multi_role) {
    $_SESSION['user_logged_in'] = true;
    $_SESSION['user_role'] = 'admin';
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = $_SESSION['admin_username'] ?? 'admin';
    $_SESSION['full_name'] = 'System Administrator';
}

if (isset($_SESSION['user_role']) && !isset($_SESSION['role_locked'])) {
    $_SESSION['role_locked'] = $_SESSION['user_role'];
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
    session_unset();
    session_destroy();
    header('Location: /csc2052/login.php?reason=timeout');
    exit;
}
$_SESSION['last_activity'] = time();

/**
 * Print a PHP value as a JavaScript string literal inside an HTML attribute,
 * e.g. onclick="editStudent(<?php echo jsArg($name); ?>)".
 * htmlspecialchars() alone is not enough there: the browser decodes &#039;
 * back to ' before the JavaScript runs, so a name like O'Brien broke the
 * button (and a crafted name could run script).
 */
if (!function_exists('jsArg')) {
    function jsArg($value): string {
        $json = json_encode((string)($value ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
        return htmlspecialchars($json, ENT_QUOTES, 'UTF-8');
    }
}


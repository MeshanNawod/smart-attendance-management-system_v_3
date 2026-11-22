<?php
/**
 * Access control helpers — Sentinel Swarm
 *
 * Every API declares, per action, which roles may call it and whether the
 * action changes data. Changing actions must be POST so the CSRF check in
 * requireCsrfIfMutating() always applies (a GET link can't trigger them).
 *
 *   enforceActionPolicy($action, [
 *       'list'   => ['roles' => ['admin', 'teacher']],
 *       'delete' => ['roles' => ['admin'], 'post' => true],
 *   ]);
 *
 * Unknown actions are refused. Requires includes/auth/session.php first.
 */

function accessDenied(string $message = 'Access denied. Insufficient permissions.', int $code = 403): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

function enforceActionPolicy(string $action, array $policy, ?array $defaultRule = null): void {
    $rule = $policy[$action] ?? $defaultRule;
    if ($rule === null) {
        accessDenied('Unknown action.', 400);
    }
    $role = $_SESSION['user_role'] ?? '';
    if (!in_array($role, $rule['roles'] ?? ['admin'], true)) {
        accessDenied();
    }
    if (!empty($rule['post']) && strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        accessDenied('This action must be sent as POST.', 405);
    }
}

/** True when the request carries the hardware API key (ESP32 / Raspberry Pi). */
function isHardwareRequest(): bool {
    $key = $_SERVER['HTTP_X_HARDWARE_KEY']
        ?? ($_POST['hardware_key'] ?? ($_GET['hardware_key'] ?? ($_REQUEST['key'] ?? '')));
    return is_string($key) && $key !== '' && hash_equals(HARDWARE_API_KEY, $key);
}

/**
 * Start the PHP session without the login redirect in session.php, for
 * endpoints shared by hardware and the admin dashboard.
 * Returns the logged-in role, or null.
 */
function quietSessionRole(): ?string {
    $sessionPath = __DIR__ . '/../../sessions';
    if (session_status() === PHP_SESSION_NONE) {
        if (is_dir($sessionPath)) session_save_path($sessionPath);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => SESSION_COOKIE_SECURE,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
    if (empty($_SESSION['user_logged_in'])) return null;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) return null;
    if (!empty($_SESSION['role_locked']) && ($_SESSION['user_role'] ?? '') !== $_SESSION['role_locked']) return null;
    $_SESSION['last_activity'] = time();
    return $_SESSION['user_role'] ?? null;
}

/** Allow the request if it has the hardware key, or comes from a logged-in user with one of $roles. */
function requireHardwareOrRole(string ...$roles): string {
    if (isHardwareRequest()) return 'hardware';
    $role = quietSessionRole();
    if ($role !== null && in_array($role, $roles, true)) {
        // Browser calls that change data must carry the CSRF token.
        if (in_array(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
            if (!$token || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
                accessDenied('CSRF token validation failed.');
            }
        }
        return $role;
    }
    accessDenied($role === null ? 'Not logged in.' : 'Access denied. Insufficient permissions.', $role === null ? 401 : 403);
    return '';
}

/** Teachers may only act on courses they teach. Admins may act on any course. */
function requireCourseAccess(PDO $pdo, string $courseCode): void {
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'admin') return;
    if ($role === 'teacher') {
        $stmt = $pdo->prepare("SELECT 1 FROM teacher_courses WHERE teacher_id = ? AND course_code = ?");
        $stmt->execute([$_SESSION['teacher_id'] ?? 0, $courseCode]);
        if ($stmt->fetch()) return;
    }
    accessDenied("You do not teach {$courseCode}.");
}

/**
 * Plain-text name check for people, courses and devices: letters (any
 * language), digits, spaces and . , ' ( ) / & - _  — no HTML.
 */
function isSafeName(string $value, int $max = 100): bool {
    return $value !== '' && mb_strlen($value) <= $max && preg_match("/^[\p{L}\p{M}\p{N} .,'()\/&_-]+$/u", $value) === 1;
}

/**
 * Clean a display name (student, lecturer, course, device) before it is stored.
 * Names are shown in many dashboards, so markup characters are removed here as
 * a second line of defence; the pages also escape them when rendering.
 */
function cleanDisplayName($value, int $max = 100): string {
    $value = (string)$value;
    $value = preg_replace('/[\x00-\x1F\x7F<>"`\\\\]/u', '', $value) ?? '';
    $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';
    return mb_substr($value, 0, $max);
}


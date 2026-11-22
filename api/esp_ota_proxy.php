<?php
/**
 * ESP32 command proxy — Sentinel Swarm v4
 *
 * The dashboard cannot always reach an ESP32 directly (mixed content, CORS),
 * so it asks the server to forward a command to the device's built-in web
 * server on the local network.
 *
 * GET ?ip=<device ip>&command=<text>   → http://<ip>/cmd?command=<text>
 * GET ?ip=<device ip>&endpoint=<path>  → http://<ip>/<path>
 *
 * Who may call it: an admin session (with the X-CSRF-Token header, sent by
 * js/main.js), or hardware carrying the hardware key.
 * Where it may go: only to IPs listed in devices_registry.json, i.e. devices
 * that have sent a heartbeat or that an admin registered. Redirects are never
 * followed, so the proxy cannot be bounced to another host.
 */
require_once __DIR__ . '/../includes/config/app.php';
require_once __DIR__ . '/../includes/auth/access.php';

header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function proxyFail(int $code, string $msg): void {
    http_response_code($code);
    echo 'ERR:' . $msg;
    exit;
}

// ── Auth ──────────────────────────────────────────────────────────────
if (!isHardwareRequest()) {
    $role = quietSessionRole();
    if ($role !== 'admin') {
        proxyFail($role === null ? 401 : 403, 'UNAUTHORIZED');
    }
    // A GET can still reboot or reconfigure a device, so browser calls must
    // prove they come from our own pages.
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_GET['csrf_token'] ?? '');
    if (!$token || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        proxyFail(403, 'CSRF token validation failed');
    }
    session_write_close(); // don't hold the session lock while we wait on the device
}

// ── Target: must be a known device ────────────────────────────────────
$ip = trim($_GET['ip'] ?? '');
if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    proxyFail(400, 'Invalid IP');
}
$registryFile = __DIR__ . '/../devices_registry.json';
$registry = is_file($registryFile) ? (json_decode((string)file_get_contents($registryFile), true) ?: []) : [];
if (!isset($registry[$ip])) {
    proxyFail(403, 'Unknown device — it must send a heartbeat or be registered first');
}
if (!empty($registry[$ip]['blocked'])) {
    proxyFail(403, 'Device is blocked');
}

// ── Path ──────────────────────────────────────────────────────────────
$endpoint = trim($_GET['endpoint'] ?? '');
$cmd      = (string)($_GET['command'] ?? '');

if ($endpoint !== '') {
    // Plain relative paths only: letters, digits, - _ . / and a simple query.
    if (strlen($endpoint) > 200 || strpos($endpoint, '..') !== false
        || !preg_match('#^[A-Za-z0-9_\-./]+(\?[A-Za-z0-9_\-.=&%]*)?$#', $endpoint)) {
        proxyFail(400, 'Invalid endpoint');
    }
    $url = "http://{$ip}/" . ltrim($endpoint, '/');
} elseif ($cmd !== '') {
    if (strlen($cmd) > 4096) proxyFail(400, 'Command too long');
    $url = "http://{$ip}/cmd?command=" . rawurlencode($cmd);
} else {
    proxyFail(400, 'Provide endpoint or command');
}

// ── Forward ───────────────────────────────────────────────────────────
$resp = false;
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTP,
        CURLOPT_NOPROXY        => '*', // devices are on the LAN; never route through a proxy
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
} elseif (ini_get('allow_url_fopen')) {
    $ctx = stream_context_create(['http' => [
        'timeout' => 8, 'method' => 'GET', 'follow_location' => 0, 'max_redirects' => 0,
    ]]);
    $resp = @file_get_contents($url, false, $ctx);
}

if ($resp === false || $resp === '') {
    echo 'ERR:Failed to connect';
} else {
    echo $resp;
}

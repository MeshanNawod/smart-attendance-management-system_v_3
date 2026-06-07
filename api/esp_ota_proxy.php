<?php
require_once __DIR__ . '/../includes/config/app.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: *');
header('Content-Type: text/plain');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$hw_key = $_SERVER['HTTP_X_HARDWARE_KEY'] ?? ($_GET['hardware_key'] ?? '');
if ($hw_key === '' || !hash_equals(HARDWARE_API_KEY, $hw_key)) {
    http_response_code(401);
    echo 'ERR:UNAUTHORIZED';
    exit;
}

$ip = $_GET['ip'] ?? '';

if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) {
    http_response_code(400);
    echo 'ERR:Invalid IP';
    exit;
}

// Reject private/reserved ranges (SSRF prevention)
$private = false;
if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
    $private = true;
}
if ($private) {
    http_response_code(403);
    echo 'ERR:Private IP ranges not allowed';
    exit;
}

$endpoint = $_GET['endpoint'] ?? '';
$cmd      = $_GET['command'] ?? '';

if ($endpoint) {
    $url = "http://{$ip}/" . ltrim($endpoint, '/');
} elseif ($cmd) {
    $url = "http://{$ip}/cmd?command=" . rawurlencode($cmd);
} else {
    http_response_code(400);
    echo 'ERR: Provide endpoint or command';
    exit;
}

$resp = false;

if (ini_get('allow_url_fopen')) {
    $ctx = stream_context_create(['http' => ['timeout' => 8, 'method' => 'GET']]);
    $resp = @file_get_contents($url, false, $ctx);
}

if ($resp === false && function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $resp = @curl_exec($ch);
    curl_close($ch);
}

if ($resp === false || $resp === '') {
    echo 'ERR:Failed to connect';
} else {
    echo $resp;
}

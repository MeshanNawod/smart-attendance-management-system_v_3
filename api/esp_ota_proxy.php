<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: *');
header('Content-Type: text/plain');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$ip = $_GET['ip'] ?? '';

// If 'endpoint' param is set, fetch http://{ip}/{endpoint}
// Otherwise fall back to command: http://{ip}/cmd?command=...
$endpoint = $_GET['endpoint'] ?? '';
$cmd      = $_GET['command'] ?? '';

if (!$ip) { http_response_code(400); echo "ERR: Missing ip"; exit; }

if ($endpoint) {
    $url = "http://{$ip}/" . ltrim($endpoint, '/');
} elseif ($cmd) {
    $url = "http://{$ip}/cmd?command=" . rawurlencode($cmd);
} else {
    http_response_code(400); echo "ERR: Provide endpoint or command"; exit;
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
    echo "ERR: Failed to connect to {$ip} (check network, allow_url_fopen, or cURL)";
} else {
    echo $resp;
}

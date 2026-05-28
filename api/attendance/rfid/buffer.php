<?php
require_once __DIR__ . '/../../../includes/config/app.php';

header('Content-Type: application/json; charset=utf-8');

$buffer_file = __DIR__ . '/../../../sys_latest_rfid.txt';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'consume') {
    $sessionPath = __DIR__ . '/../../../sessions';
    if (is_dir($sessionPath)) session_save_path($sessionPath);
    if (session_status() === PHP_SESSION_NONE) session_start();
    $valid = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
    if (!$valid) {
        echo json_encode(['status' => 'error', 'message' => 'Session expired. Relogin and try again.']);
        exit;
    }

    if (!file_exists($buffer_file)) {
        echo json_encode(['status' => 'empty']);
        exit;
    }

    $uid = trim(file_get_contents($buffer_file));
    if (!empty($uid) && preg_match('/^[a-zA-Z0-9]{1,20}$/', $uid)) {
        file_put_contents($buffer_file, '', LOCK_EX);
        echo json_encode(['status' => 'success', 'uid' => strtoupper($uid)]);
    } else {
        echo json_encode(['status' => 'empty']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'status') {
    $sessionPath = __DIR__ . '/../../../sessions';
    if (is_dir($sessionPath)) session_save_path($sessionPath);
    if (session_status() === PHP_SESSION_NONE) session_start();
    $bufferExists = file_exists($buffer_file);
    $bufferContent = '';
    $bufferAge = -1;
    if ($bufferExists) {
        $bufferContent = trim(file_get_contents($buffer_file));
        $bufferAge = time() - filemtime($buffer_file);
    }
    echo json_encode([
        'buffer_exists' => $bufferExists,
        'buffer_content' => $bufferContent,
        'buffer_age_sec' => $bufferAge,
        'buffer_writable' => is_writable(dirname($buffer_file)),
        'session_valid' => isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true,
    ]);
    exit;
}

$hw_key = $_SERVER['HTTP_X_HARDWARE_KEY'] ?? ($_GET['hardware_key'] ?? ($_POST['hardware_key'] ?? ''));

if (!hash_equals(HARDWARE_API_KEY, $hw_key)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

$uid = trim($_GET['uid'] ?? ($_POST['uid'] ?? ''));

if (empty($uid)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing UID parameter.']);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9]{1,20}$/', $uid)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Invalid UID format. Must be 1-20 alphanumeric characters.']);
    exit;
}

if (file_exists($buffer_file)) {
    $last_modified = filemtime($buffer_file);
    if ((time() - $last_modified) < 2) {
        echo json_encode(['status' => 'ok', 'message' => 'Rate limited — duplicate suppressed.']);
        exit;
    }
}

$written = file_put_contents($buffer_file, strtoupper($uid), LOCK_EX);
if ($written !== false) {
    echo json_encode(['status' => 'ok', 'message' => 'UID received.']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to write RFID buffer.']);
}

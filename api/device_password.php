<?php
/**
 * API: Device Password — get/set SYSTEM_PIN for ESP32
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database/connection.php';
requireCsrfIfMutating();

// v4: role check (admin only).
require_once __DIR__ . '/../includes/auth/access.php';
if (!in_array($_SESSION['user_role'] ?? '', ['admin'], true)) {
    accessDenied();
}
header('Content-Type: application/json; charset=utf-8');

// v4: create the table on first use, so installs that skipped migration
// 008_device_password.sql don't fail with a database error.
try {
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS device_password (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(50) NOT NULL UNIQUE,
    system_pin VARCHAR(10) NOT NULL DEFAULT '123456',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);
} catch (PDOException $e) {
    error_log('[device_password.php] table check: ' . $e->getMessage());
}

$device_id = trim($_GET['device_id'] ?? $_POST['device_id'] ?? 'DEFAULT');
$device_id = substr(preg_replace('/[^a-zA-Z0-9_\-]/', '', $device_id), 0, 50);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare("SELECT system_pin FROM device_password WHERE device_id = ? LIMIT 1");
        $stmt->execute([$device_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->prepare("INSERT INTO device_password (device_id, system_pin) VALUES (?, '123456')")->execute([$device_id]);
            $row = ['system_pin' => '123456'];
        }
        echo json_encode(['status' => 'success', 'system_pin' => $row['system_pin']]);
    } catch (PDOException $e) {
        error_log('[DevicePassword] GET: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'A database error occurred.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = trim($_POST['system_pin'] ?? '');
    if (!preg_match('/^\d{4,10}$/', $pin)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'PIN must be 4-10 digits.']);
        exit;
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO device_password (device_id, system_pin) VALUES (?, ?) ON DUPLICATE KEY UPDATE system_pin = ?");
        $stmt->execute([$device_id, $pin, $pin]);
        echo json_encode(['status' => 'success', 'message' => 'Device password updated.']);
    } catch (PDOException $e) {
        error_log('[DevicePassword] POST: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'A database error occurred.']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);

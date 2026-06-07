<?php
require_once __DIR__ . '/../../../includes/database/connection.php';
require_once __DIR__ . '/../../../includes/config/app.php';
require_once __DIR__ . '/../../../includes/attendance/helper.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$method = $_POST['method'] ?? $_GET['method'] ?? 'plaintext';
$token  = $_POST['token']  ?? $_GET['token']  ?? '';

$hw_key = $_SERVER['HTTP_X_HARDWARE_KEY'] ?? ($_POST['hardware_key'] ?? '');

$auth_ok = false;
if ($token !== '' && hash_equals(HEARTBEAT_SECRET, $token)) {
    $auth_ok = true;
}
if ($hw_key !== '' && hash_equals(HARDWARE_API_KEY, $hw_key)) {
    $auth_ok = true;
}
if (!$auth_ok) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: valid token or hardware key required.']);
    exit;
}

$finger_id   = trim($_POST['finger_id'] ?? '');
$device_name = trim($_POST['device_name'] ?? 'Unknown');
$course_code = trim($_POST['course_code'] ?? '');
$timestamp   = trim($_POST['timestamp']   ?? date('Y-m-d H:i:s'));
$modality    = 'fingerprint';
$offline     = !empty($_POST['offline_sync']);

if (!$finger_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing finger_id']);
    exit;
}

if (is_numeric($finger_id)) {
    try {
        $transStmt = $pdo->prepare("SELECT student_no FROM students WHERE fingerprint_id = ? LIMIT 1");
        $transStmt->execute([$finger_id]);
        $row = $transStmt->fetch();
        if ($row) {
            $finger_id = $row['student_no'];
        } else {
            $adminStmt = $pdo->prepare("SELECT admin_name FROM admins WHERE fingerprint_id = ? LIMIT 1");
            $adminStmt->execute([$finger_id]);
            $rowAdmin = $adminStmt->fetch();
            if ($rowAdmin) {
                $finger_id = $rowAdmin['admin_name'];
            }
        }
    } catch (PDOException $e) {
        error_log('[Fingerprint API] Slot translation error: ' . $e->getMessage());
    }
}

$ip = $_SERVER['REMOTE_ADDR'];
$registry_file = __DIR__ . '/../../../devices_registry.json';
$registry = file_exists($registry_file) ? json_decode(file_get_contents($registry_file), true) ?: [] : [];
$safe_name = preg_replace('/[^a-zA-Z0-9_\- ]/', '', substr($device_name, 0, 64));
$registry[$ip] = [
    'type'      => 'esp32',
    'name'      => $safe_name,
    'last_seen' => time(),
    'method'    => $method,
    'blocked'   => $registry[$ip]['blocked'] ?? false,
];
file_put_contents($registry_file, json_encode($registry, JSON_PRETTY_PRINT), LOCK_EX);
file_put_contents(__DIR__ . '/../../../sys_status_esp32.txt', (string)time(), LOCK_EX);

try {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE username = ?");
    $stmt->execute([$finger_id]);
    $user = $stmt->fetch();
    if ($user && $user['role'] === 'admin') {
        echo json_encode(['status' => 'admin', 'message' => 'Admin login detected']);
        exit;
    }
} catch (PDOException $e) {}

try {
    $result = recordLectureAttendance($pdo, $finger_id, $course_code, $device_name, $modality, $timestamp, $offline);

    if ($result['status'] === 'not_enrolled') {
        http_response_code(200);
        echo json_encode(['status' => 'not_enrolled', 'message' => 'Student not enrolled in this course.', 'student_no' => $finger_id]);
        exit;
    }
    if ($result['status'] === 'no_session') {
        http_response_code(200);
        echo json_encode(['status' => 'no_session', 'message' => 'No active lecture session.', 'student_no' => $finger_id]);
        exit;
    }

    $studentStmt = $pdo->prepare("SELECT student_name FROM students WHERE student_no = ?");
    $studentStmt->execute([$finger_id]);
    $student = $studentStmt->fetch();

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS system_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            log_type ENUM('info','warning','error','power','debug') NOT NULL DEFAULT 'info',
            source VARCHAR(64) NOT NULL DEFAULT 'system',
            message TEXT NOT NULL,
            details TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_log_type (log_type),
            INDEX idx_source (source),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->prepare("INSERT INTO system_logs (log_type, source, message) VALUES ('info', 'esp32', ?)")
            ->execute(["Fingerprint: {$finger_id} @ {$course_code}"]);
    } catch (PDOException $e) {}

    $isUpdate = $result['status'] === 'updated';
    echo json_encode([
        'status' => $isUpdate ? 'updated' : 'ok',
        'message' => $isUpdate ? 'Attendance re-scanned — record updated.' : 'Attendance recorded',
        'student_no' => $finger_id,
        'student_name' => $student['student_name'] ?? 'Unknown',
        'session_id' => $result['session_id'],
    ]);
} catch (PDOException $e) {
    error_log('[Fingerprint Record] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error.']);
}

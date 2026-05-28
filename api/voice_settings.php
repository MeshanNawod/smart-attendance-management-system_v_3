<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database/connection.php';
requireCsrfIfMutating();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->query("SELECT * FROM voice_settings WHERE id = 1 LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->exec("INSERT INTO voice_settings (id) VALUES (1)");
            $row = [
                'rate' => 1.0, 'pitch' => 1.0, 'voice_name' => null,
                'announce_student_name' => 1, 'announce_student_no' => 0,
                'announce_modality' => 1, 'announce_course' => 0,
                'announce_timestamp' => 0, 'announce_unknown_card' => 1,
                'logs_columns' => 'student_no,student_name,modality,course_code,timestamp'
            ];
        }
        echo json_encode(['status' => 'success', 'settings' => $row]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rate = (float)($_POST['rate'] ?? 1.0);
    $pitch = (float)($_POST['pitch'] ?? 1.0);
    $voice_name = trim($_POST['voice_name'] ?? '');
    if ($voice_name === '') $voice_name = null;
    $announce_student_name = (int)(bool)($_POST['announce_student_name'] ?? 1);
    $announce_student_no = (int)(bool)($_POST['announce_student_no'] ?? 0);
    $announce_modality = (int)(bool)($_POST['announce_modality'] ?? 1);
    $announce_course = (int)(bool)($_POST['announce_course'] ?? 0);
    $announce_timestamp = (int)(bool)($_POST['announce_timestamp'] ?? 0);
    $announce_unknown_card = (int)(bool)($_POST['announce_unknown_card'] ?? 1);
    $logs_columns = trim($_POST['logs_columns'] ?? 'student_no,student_name,modality,course_code,timestamp');
    $logs_columns = preg_replace('/[^a-zA-Z0-9_,]/', '', $logs_columns);
    try {
        $stmt = $pdo->prepare(
            "UPDATE voice_settings SET rate=?, pitch=?, voice_name=?, 
             announce_student_name=?, announce_student_no=?, announce_modality=?,
             announce_course=?, announce_timestamp=?, announce_unknown_card=?,
             logs_columns=? WHERE id=1"
        );
        $stmt->execute([$rate, $pitch, $voice_name,
            $announce_student_name, $announce_student_no, $announce_modality,
            $announce_course, $announce_timestamp, $announce_unknown_card,
            $logs_columns]);
        echo json_encode(['status' => 'success', 'message' => 'Voice settings saved.']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);

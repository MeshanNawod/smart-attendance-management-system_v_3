<?php
require_once __DIR__ . '/../../../includes/config/app.php';
require_once __DIR__ . '/../../../includes/auth/session.php';
require_once __DIR__ . '/../../../includes/database/connection.php';
require_once __DIR__ . '/../../../includes/helpers/general.php';

requireCsrfIfMutating();

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'enroll_voice') {
    requireRole('teacher', 'admin');

    $studentNo = trim($_POST['student_no'] ?? '');
    $courseCode = trim($_POST['course_code'] ?? '');

    if (empty($studentNo) || empty($courseCode)) {
        respond('error', 'Student number and course are required.', 422);
    }

    if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
        respond('error', 'No audio file uploaded.', 400);
    }

    $file = $_FILES['audio'];
    $allowed = ['audio/webm', 'audio/ogg', 'audio/wav', 'audio/mp4', 'audio/mpeg'];
    if (!in_array($file['type'], $allowed, true)) {
        respond('error', 'Invalid audio format.', 415);
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        respond('error', 'Audio too large (max 5MB).', 413);
    }

    $uploadDir = __DIR__ . '/../../../uploads/voice/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'webm';
    $filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $studentNo) . '_' . time() . '.' . $ext;
    $dest = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        respond('error', 'Failed to save voice sample.', 500);
    }

    $voicePath = 'uploads/voice/' . $filename;

    try {
        $stmt = $pdo->prepare("UPDATE students SET voice_sample = ? WHERE student_no = ?");
        $stmt->execute([$voicePath, $studentNo]);

        if ($stmt->rowCount() === 0) {
            @unlink($dest);
            respond('error', 'Student not found.', 404);
        }

        respond('success', 'Voice sample enrolled successfully.', 200, ['voice_url' => $voicePath]);
    } catch (PDOException $e) {
        @unlink($dest);
        respond('error', 'Database error.', 500);
    }
}

if ($action === 'get_course_students') {
    requireRole('teacher', 'admin');

    $courseCode = trim($_GET['course_code'] ?? '');

    if (empty($courseCode)) {
        respond('error', 'Course code required.', 422);
    }

    try {
        $stmt = $pdo->prepare("
            SELECT s.student_no, s.student_name, s.voice_sample
            FROM student_courses sc
            JOIN students s ON sc.student_no = s.student_no
            WHERE sc.course_code = ?
            ORDER BY s.student_name
        ");
        $stmt->execute([$courseCode]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'students' => $students]);
    } catch (PDOException $e) {
        respond('error', 'Database error.', 500);
    }
    exit;
}

if ($action === 'get_voice_logs') {
    requireRole('teacher', 'admin');

    try {
        $stmt = $pdo->query("
            SELECT al.student_no, s.student_name, al.timestamp, al.course_code
            FROM attendance_logs al
            LEFT JOIN students s ON al.student_no = s.student_no
            WHERE al.modality = 'voice'
            ORDER BY al.timestamp DESC
            LIMIT 20
        ");
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'logs' => $logs]);
    } catch (PDOException $e) {
        respond('error', 'Database error.', 500);
    }
    exit;
}

respond('error', 'Invalid action.', 400);

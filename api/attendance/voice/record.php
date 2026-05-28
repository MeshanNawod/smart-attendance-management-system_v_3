<?php
require_once __DIR__ . '/../../../includes/config/app.php';
require_once __DIR__ . '/../../../includes/auth/session.php';
require_once __DIR__ . '/../../../includes/database/connection.php';
require_once __DIR__ . '/../../../includes/helpers/general.php';
require_once __DIR__ . '/../../../includes/attendance/helper.php';

requireCsrfIfMutating();

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'voice_attendance') {
    requireRole('teacher', 'admin');

    $studentNo = trim($_POST['student_no'] ?? '');
    $courseCode = trim($_POST['course_code'] ?? '');
    $timestamp = trim($_POST['timestamp'] ?? date('Y-m-d H:i:s'));

    if (empty($studentNo) || empty($courseCode)) {
        respond('error', 'Student number and course are required.', 422);
    }

    try {
        $checkStmt = $pdo->prepare("SELECT student_name, voice_sample FROM students WHERE student_no = ?");
        $checkStmt->execute([$studentNo]);
        $student = $checkStmt->fetch();

        if (!$student) {
            respond('error', 'Student not found.', 404);
        }
        if (empty($student['voice_sample'])) {
            respond('error', 'No voice sample enrolled. Please enroll voice first.', 400);
        }

        $audioSaved = false;
        $audioPath = null;
        if (isset($_FILES['audio']) && $_FILES['audio']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['audio'];
            $allowed = ['audio/webm', 'audio/ogg', 'audio/wav', 'audio/mp4', 'audio/mpeg'];
            if (in_array($file['type'], $allowed, true) && $file['size'] > 1024 && $file['size'] <= 5 * 1024 * 1024) {
                $uploadDir = __DIR__ . '/../../../uploads/voice/attendance/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'webm';
                $filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $studentNo) . '_att_' . time() . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                    $audioSaved = true;
                    $audioPath = 'uploads/voice/attendance/' . $filename;
                }
            }
        }
        if (!$audioSaved) {
            respond('error', 'Valid voice recording required.', 400);
        }

        $result = recordLectureAttendance($pdo, $studentNo, $courseCode, 'WEB_DASHBOARD', 'voice', $timestamp);

        if ($result['status'] === 'not_enrolled') {
            @unlink(__DIR__ . '/../../../' . $audioPath);
            respond('error', 'Student not enrolled in this course.', 403);
        }
        if ($result['status'] === 'no_session') {
            @unlink(__DIR__ . '/../../../' . $audioPath);
            respond('error', 'No active lecture session for this course.', 403);
        }

        respond('success', $result['status'] === 'updated' ? 'Voice attendance re-scanned — updated.' : 'Voice attendance recorded.', 200, [
            'student_no' => $studentNo,
            'student_name' => $student['student_name'] ?? 'Unknown',
            'session_id' => $result['session_id'],
        ]);
    } catch (PDOException $e) {
        respond('error', 'Database error.', 500);
    }
}

respond('error', 'Invalid action.', 400);

<?php
function ensureLectureSessionsTable(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS lecture_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_code VARCHAR(50) NOT NULL,
        teacher_id INT DEFAULT NULL,
        started_by VARCHAR(50) DEFAULT NULL,
        started_at DATETIME NOT NULL,
        ended_at DATETIME DEFAULT NULL,
        device_name VARCHAR(100) DEFAULT 'WEB_DASHBOARD',
        is_active TINYINT(1) DEFAULT 1,
        INDEX idx_course_active (course_code, is_active),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $checkCol = $pdo->query("SHOW COLUMNS FROM attendance_logs LIKE 'session_id'")->fetch();
    if (!$checkCol) {
        $pdo->exec("ALTER TABLE attendance_logs
            ADD COLUMN session_id INT DEFAULT NULL AFTER id,
            ADD COLUMN scan_count INT DEFAULT 1 AFTER session_id,
            ADD INDEX idx_session (session_id)");
    }
}

function getActiveSession(PDO $pdo, string $courseCode): ?int {
    $stmt = $pdo->prepare("SELECT id FROM lecture_sessions WHERE course_code = ? AND is_active = 1 ORDER BY started_at DESC LIMIT 1");
    $stmt->execute([$courseCode]);
    $id = $stmt->fetchColumn();
    return $id ? (int)$id : null;
}

function isStudentEnrolled(PDO $pdo, string $studentNo, string $courseCode): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM student_courses WHERE student_no = ? AND course_code = ?");
    $stmt->execute([$studentNo, $courseCode]);
    return (bool)$stmt->fetch();
}

function recordLectureAttendance(PDO $pdo, string $studentNo, string $courseCode, string $deviceName, string $modality, string $timestamp, bool $isOfflineSync = false): array {
    ensureLectureSessionsTable($pdo);

    if (!isStudentEnrolled($pdo, $studentNo, $courseCode)) {
        return ['status' => 'not_enrolled', 'message' => 'Student not enrolled in this course.'];
    }

    $sessionId = getActiveSession($pdo, $courseCode);
    if (!$sessionId) {
        return ['status' => 'no_session', 'message' => 'No active lecture session for this course.'];
    }

    $existing = $pdo->prepare("SELECT id, scan_count FROM attendance_logs WHERE student_no = ? AND course_code = ? AND session_id = ? LIMIT 1");
    $existing->execute([$studentNo, $courseCode, $sessionId]);
    $row = $existing->fetch();

    if ($row) {
        $pdo->prepare("UPDATE attendance_logs SET timestamp = ?, scan_count = scan_count + 1, device_name = ? WHERE id = ?")
            ->execute([$timestamp, $deviceName, (int)$row['id']]);
        return ['status' => 'updated', 'message' => 'Attendance re-scanned — record updated.', 'session_id' => $sessionId];
    }

    $pdo->prepare("INSERT INTO attendance_logs (student_no, course_code, timestamp, device_name, modality, is_offline_sync, session_id, scan_count) VALUES (?, ?, ?, ?, ?, ?, ?, 1)")
        ->execute([$studentNo, $courseCode, $timestamp, $deviceName, $modality, $isOfflineSync ? 1 : 0, $sessionId]);

    return ['status' => 'success', 'message' => 'Attendance recorded.', 'session_id' => $sessionId];
}

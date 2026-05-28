<?php
require_once __DIR__ . '/../../../includes/config/app.php';
require_once __DIR__ . '/../../../includes/auth/session.php';
require_once __DIR__ . '/../../../includes/database/connection.php';
require_once __DIR__ . '/../../../includes/helpers/general.php';
require_once __DIR__ . '/../../../includes/attendance/helper.php';

requireCsrfIfMutating();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS qr_tokens (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        course_code VARCHAR(50) NOT NULL,
        teacher_id  INT         NOT NULL,
        token       VARCHAR(64) NOT NULL,
        slot        BIGINT      NOT NULL COMMENT 'Sequential slot: floor(UNIX_TIMESTAMP / 5)',
        created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_course_slot (course_code, slot),
        INDEX idx_token  (token),
        INDEX idx_course (course_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Rotating QR tokens'");

    $pdo->exec("CREATE TABLE IF NOT EXISTS qr_scan_attempts (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        student_no   VARCHAR(50) NOT NULL,
        course_code  VARCHAR(50) NOT NULL,
        token_1      VARCHAR(64) NOT NULL,
        slot_1       BIGINT      NOT NULL,
        scanned_at_1 DATETIME    NOT NULL,
        token_2      VARCHAR(64) NULL DEFAULT NULL,
        slot_2       BIGINT      NULL DEFAULT NULL,
        scanned_at_2 DATETIME    NULL DEFAULT NULL,
        client_ip    VARCHAR(45) NULL DEFAULT NULL,
        completed    TINYINT(1)  NOT NULL DEFAULT 0,
        INDEX idx_student_course (student_no, course_code),
        INDEX idx_token1         (token_1),
        INDEX idx_ip_course_date (client_ip, course_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='2-scan sequential QR verification'");
    try { $pdo->exec("ALTER TABLE qr_scan_attempts ADD COLUMN client_ip VARCHAR(45) DEFAULT NULL, ADD INDEX idx_ip_course_date (client_ip, course_code)"); } catch (PDOException $e) {}
} catch (PDOException $e) {}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' && ($_GET['action'] ?? '') === 'get_qr') {
    requireRole('teacher', 'admin');
    $isAdmin  = ($_SESSION['role'] ?? '') === 'admin';
    $teacherId  = $_SESSION['teacher_id'] ?? null;
    $courseCode = strtoupper(trim($_GET['course_code'] ?? ''));

    if ((!$teacherId && !$isAdmin) || empty($courseCode)) apiError('course_code required.');

    $effectiveTeacherId = $teacherId ?? 0;

    try {
        if (!$isAdmin) {
            $chk = $pdo->prepare("SELECT 1 FROM teacher_courses WHERE teacher_id = ? AND course_code = ?");
            $chk->execute([$teacherId, $courseCode]);
            if (!$chk->fetch()) apiError('Access denied.', 403);
        }

        $now       = time();
        $slot      = (int) floor($now / 5);
        $expiresIn = (($slot + 1) * 5) - $now;

        $sel = $pdo->prepare("SELECT token FROM qr_tokens WHERE course_code = ? AND slot = ?");
        $sel->execute([$courseCode, $slot]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $token = bin2hex(random_bytes(16));
            $pdo->prepare("INSERT IGNORE INTO qr_tokens (course_code, teacher_id, token, slot) VALUES (?, ?, ?, ?)")
                ->execute([$courseCode, $effectiveTeacherId, $token, $slot]);
            $sel->execute([$courseCode, $slot]);
            $row   = $sel->fetch(PDO::FETCH_ASSOC);
            $token = $row ? $row['token'] : $token;
            $pdo->prepare("DELETE FROM qr_tokens WHERE course_code = ? AND slot < ?")
                ->execute([$courseCode, $slot - 60]);
        } else {
            $token = $row['token'];
        }

        echo json_encode([
            'status'          => 'ok',
            'qr_data'         => "SS-QR|{$courseCode}|{$token}|{$slot}",
            'slot'            => $slot,
            'slot_expires_in' => (int) $expiresIn,
            'course_code'     => $courseCode,
        ]);
    } catch (PDOException $e) {
        apiError('Database error.', 500);
    }
    exit;
}

if ($method === 'GET' && ($_GET['action'] ?? '') === 'get_session_stats') {
    requireRole('teacher', 'admin');
    $isAdmin  = ($_SESSION['role'] ?? '') === 'admin';
    $teacherId  = $_SESSION['teacher_id'] ?? null;
    $courseCode = strtoupper(trim($_GET['course_code'] ?? ''));

    if ((!$teacherId && !$isAdmin) || empty($courseCode)) apiError('course_code required.');

    try {
        if (!$isAdmin) {
            $chk = $pdo->prepare("SELECT 1 FROM teacher_courses WHERE teacher_id = ? AND course_code = ?");
            $chk->execute([$teacherId, $courseCode]);
            if (!$chk->fetch()) apiError('Access denied.', 403);
        }

        $s1 = $pdo->prepare("SELECT COUNT(DISTINCT student_no) FROM qr_scan_attempts WHERE course_code = ? AND completed = 1 AND DATE(scanned_at_2) = CURDATE()");
        $s1->execute([$courseCode]);
        $completed = (int) $s1->fetchColumn();

        $s2 = $pdo->prepare("SELECT COUNT(DISTINCT student_no) FROM qr_scan_attempts WHERE course_code = ? AND completed = 0 AND DATE(scanned_at_1) = CURDATE()");
        $s2->execute([$courseCode]);
        $pending = (int) $s2->fetchColumn();

        echo json_encode(['status' => 'ok', 'completed' => $completed, 'pending' => $pending]);
    } catch (PDOException $e) {
        apiError('Database error.', 500);
    }
    exit;
}

if ($method === 'POST' && ($_POST['action'] ?? '') === 'scan_qr') {
    requireRole('student');
    $studentNo = trim($_SESSION['student_no'] ?? $_SESSION['username'] ?? '');
    $qrData    = trim($_POST['qr_data'] ?? '');

    if (empty($studentNo) || empty($qrData)) apiError('Missing data.');

    $parts = explode('|', $qrData);
    if (count($parts) !== 4 || $parts[0] !== 'SS-QR') {
        echo json_encode(['status' => 'invalid', 'message' => 'Not a valid attendance QR.']);
        exit;
    }

    $courseCode   = strtoupper(trim($parts[1]));
    $scannedToken = trim($parts[2]);
    $scannedSlot  = (int) $parts[3];
    $currentSlot  = (int) floor(time() / 5);

    if ($currentSlot - $scannedSlot > 1 || $scannedSlot > $currentSlot) {
        echo json_encode(['status' => 'expired', 'message' => 'QR code has expired.']);
        exit;
    }

    try {
        $vld = $pdo->prepare("SELECT id FROM qr_tokens WHERE course_code = ? AND token = ? AND slot = ?");
        $vld->execute([$courseCode, $scannedToken, $scannedSlot]);
        if (!$vld->fetch()) {
            echo json_encode(['status' => 'invalid', 'message' => 'QR not recognised.']);
            exit;
        }

        $replay = $pdo->prepare("SELECT 1 FROM qr_scan_attempts WHERE student_no = ? AND course_code = ? AND (token_1 = ? OR token_2 = ?) AND completed = 1 AND DATE(scanned_at_1) = CURDATE()");
        $replay->execute([$studentNo, $courseCode, $scannedToken, $scannedToken]);
        if ($replay->fetch()) {
            echo json_encode(['status' => 'reused', 'message' => 'This QR has already been used by you.']);
            exit;
        }

        $enr = $pdo->prepare("SELECT 1 FROM student_courses WHERE student_no = ? AND course_code = ?");
        $enr->execute([$studentNo, $courseCode]);
        if (!$enr->fetch()) {
            echo json_encode(['status' => 'not_enrolled', 'message' => "You are not enrolled in {$courseCode}."]);
            exit;
        }

        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($clientIp) {
            $ipChk = $pdo->prepare("SELECT 1 FROM qr_scan_attempts WHERE completed = 1 AND course_code = ? AND student_no != ? AND client_ip = ? AND DATE(scanned_at_2) = CURDATE() LIMIT 1");
            $ipChk->execute([$courseCode, $studentNo, $clientIp]);
            if ($ipChk->fetch()) {
                echo json_encode(['status' => 'device_conflict', 'message' => 'Another account has already completed QR attendance from this device today.']);
                exit;
            }
        }

        $pen = $pdo->prepare("SELECT id, slot_1, token_1 FROM qr_scan_attempts WHERE student_no = ? AND course_code = ? AND completed = 0 AND DATE(scanned_at_1) = CURDATE() ORDER BY scanned_at_1 DESC LIMIT 1");
        $pen->execute([$studentNo, $courseCode]);
        $pending = $pen->fetch(PDO::FETCH_ASSOC);

        if (!$pending) {
            $pdo->prepare("INSERT INTO qr_scan_attempts (student_no, course_code, token_1, slot_1, scanned_at_1, client_ip) VALUES (?, ?, ?, ?, NOW(), ?)")
                ->execute([$studentNo, $courseCode, $scannedToken, $scannedSlot, $clientIp]);
            echo json_encode([
                'status'      => 'first_scan',
                'message'     => 'Scan 1 recorded.',
                'slot'        => $scannedSlot,
                'need_slot'   => $scannedSlot + 1,
                'course_code' => $courseCode,
            ]);
        } else {
            $slot1 = (int) $pending['slot_1'];

            if ($scannedToken === $pending['token_1']) {
                echo json_encode(['status' => 'same_qr', 'message' => "Same QR (#$slot1) detected. Wait for update."]);
                exit;
            }

            if ($scannedSlot <= $slot1) {
                echo json_encode(['status' => 'old_qr', 'message' => 'Please scan the current QR.']);
                exit;
            }

            if ($scannedSlot !== $slot1 + 1) {
                $pdo->prepare("UPDATE qr_scan_attempts SET token_1=?, slot_1=?, scanned_at_1=NOW(), token_2=NULL, slot_2=NULL, scanned_at_2=NULL WHERE id=?")
                    ->execute([$scannedToken, $scannedSlot, $pending['id']]);
                echo json_encode([
                    'status'    => 'not_consecutive',
                    'message'   => "QR #{$scannedSlot} is not consecutive to #{$slot1}. Restarted.",
                    'new_slot1' => $scannedSlot,
                    'need_slot' => $scannedSlot + 1,
                ]);
                exit;
            }

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE qr_scan_attempts SET token_2=?, slot_2=?, scanned_at_2=NOW(), completed=1 WHERE id=?")
                ->execute([$scannedToken, $scannedSlot, $pending['id']]);

            $qrResult = recordLectureAttendance($pdo, $studentNo, $courseCode, 'QR Attendance', 'qr', date('Y-m-d H:i:s'));
            if (in_array($qrResult['status'], ['not_enrolled', 'no_session'])) {
                $pdo->rollBack();
                echo json_encode(['status' => $qrResult['status'], 'message' => $qrResult['message']]);
                exit;
            }
            $pdo->commit();

            $snm = $pdo->prepare("SELECT student_name FROM students WHERE student_no = ?");
            $snm->execute([$studentNo]);
            $student = $snm->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'status'       => 'success',
                'message'      => 'Attendance recorded!',
                'student_name' => $student['student_name'] ?? $studentNo,
                'course_code'  => $courseCode,
                'pair'         => "#{$slot1} → #{$scannedSlot}",
            ]);
        }
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        apiError('Database error.', 500);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);

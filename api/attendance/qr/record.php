<?php
/**
 * Rotating QR attendance — Sentinel Swarm
 *
 * How it stops proxy attendance:
 *   1. The lecturer's screen shows a new random token every 5 seconds (one "slot").
 *   2. A student must scan two CONSECUTIVE codes (slot N, then N+1), so a photo
 *      of one code sent to a friend is useless: the friend also needs the next one.
 *   3. Tokens belong to the running lecture session; they stop working when it ends.
 *   4. One phone can complete QR attendance for only one student per lecture
 *      (anonymous per-browser device ID). Attempts to reuse a phone are flagged
 *      for the lecturer.
 *
 * Actions
 *   GET  ?action=get_qr&course_code=X             teacher / admin
 *   GET  ?action=get_session_stats&course_code=X  teacher / admin
 *   POST action=scan_qr, qr_data, device_id        student
 */
require_once __DIR__ . '/../../../includes/config/app.php';
require_once __DIR__ . '/../../../includes/auth/session.php';
require_once __DIR__ . '/../../../includes/database/connection.php';
require_once __DIR__ . '/../../../includes/helpers/general.php';
require_once __DIR__ . '/../../../includes/attendance/helper.php';

requireCsrfIfMutating();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

const QR_SLOT_SECONDS = 5;

function qrEnsureSchema(PDO $pdo): void {
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
        INDEX idx_token1         (token_1)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='2-scan sequential QR verification'");

    $pdo->exec("CREATE TABLE IF NOT EXISTS qr_flags (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        session_id  INT         NULL,
        course_code VARCHAR(50) NOT NULL,
        student_no  VARCHAR(50) NOT NULL,
        reason      VARCHAR(40) NOT NULL,
        detail      VARCHAR(255) NULL,
        created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_session (session_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Suspicious QR attempts shown to the lecturer'");

    // Columns added by the upgrade (older installs get them on first use).
    $add = [
        ['qr_tokens',        'session_id',  "ALTER TABLE qr_tokens ADD COLUMN session_id INT NULL AFTER teacher_id"],
        ['qr_scan_attempts', 'client_ip',   "ALTER TABLE qr_scan_attempts ADD COLUMN client_ip VARCHAR(45) NULL DEFAULT NULL"],
        ['qr_scan_attempts', 'session_id',  "ALTER TABLE qr_scan_attempts ADD COLUMN session_id INT NULL AFTER course_code, ADD INDEX idx_session_student (session_id, student_no)"],
        ['qr_scan_attempts', 'device_hash', "ALTER TABLE qr_scan_attempts ADD COLUMN device_hash CHAR(64) NULL DEFAULT NULL, ADD INDEX idx_session_device (session_id, device_hash)"],
    ];
    foreach ($add as [$table, $col, $ddl]) {
        $has = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$col'")->fetch();
        if (!$has) $pdo->exec($ddl);
    }
}

function qrRespond(array $payload): void {
    echo json_encode($payload);
    exit;
}

function qrFlag(PDO $pdo, ?int $sessionId, string $course, string $studentNo, string $reason, string $detail = ''): void {
    try {
        $pdo->prepare("INSERT INTO qr_flags (session_id, course_code, student_no, reason, detail) VALUES (?, ?, ?, ?, ?)")
            ->execute([$sessionId, $course, $studentNo, $reason, mb_substr($detail, 0, 255)]);
    } catch (PDOException $e) { /* never block a scan because logging failed */ }
}

/** Teacher may only use courses they teach; admin may use any course. */
function qrRequireCourseAccess(PDO $pdo, string $courseCode): int {
    requireRole('teacher', 'admin');
    $isAdmin   = ($_SESSION['user_role'] ?? '') === 'admin';
    $teacherId = $_SESSION['teacher_id'] ?? null;

    if ($courseCode === '') apiError('course_code required.');
    if (!$isAdmin) {
        if (!$teacherId) apiError('Teacher profile not found.', 403);
        $chk = $pdo->prepare("SELECT 1 FROM teacher_courses WHERE teacher_id = ? AND course_code = ?");
        $chk->execute([$teacherId, $courseCode]);
        if (!$chk->fetch()) apiError('Access denied.', 403);
    }
    return (int) ($teacherId ?? 0);
}

try {
    ensureLectureSessionsTable($pdo);
    qrEnsureSchema($pdo);
} catch (PDOException $e) {
    apiError('Database error.', 500);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $method === 'POST' ? ($_POST['action'] ?? '') : ($_GET['action'] ?? '');

// ─── Lecturer screen: current rotating code ──────────────────────────────
if ($method === 'GET' && $action === 'get_qr') {
    $courseCode = strtoupper(trim($_GET['course_code'] ?? ''));
    $teacherId  = qrRequireCourseAccess($pdo, $courseCode);

    try {
        $sessionId = getActiveSession($pdo, $courseCode);
        if (!$sessionId) {
            qrRespond([
                'status'  => 'no_session',
                'message' => "No lecture is running for {$courseCode}. Start the lecture first, then start the QR session.",
            ]);
        }

        $now       = time();
        $slot      = (int) floor($now / QR_SLOT_SECONDS);
        $expiresIn = (($slot + 1) * QR_SLOT_SECONDS) - $now;

        $sel = $pdo->prepare("SELECT token FROM qr_tokens WHERE course_code = ? AND slot = ? AND session_id = ?");
        $sel->execute([$courseCode, $slot, $sessionId]);
        $token = $sel->fetchColumn();

        if (!$token) {
            // A token left over from an earlier lecture in this same 5 s slot would block the
            // unique (course, slot) key, e.g. when a lecture is restarted. It is useless now.
            $pdo->prepare("DELETE FROM qr_tokens WHERE course_code = ? AND slot = ? AND (session_id IS NULL OR session_id <> ?)")
                ->execute([$courseCode, $slot, $sessionId]);
            $pdo->prepare("INSERT IGNORE INTO qr_tokens (course_code, teacher_id, session_id, token, slot) VALUES (?, ?, ?, ?, ?)")
                ->execute([$courseCode, $teacherId, $sessionId, bin2hex(random_bytes(16)), $slot]);
            $sel->execute([$courseCode, $slot, $sessionId]);
            $token = $sel->fetchColumn();
            if (!$token) apiError('Could not create QR token.', 500);
            $pdo->prepare("DELETE FROM qr_tokens WHERE course_code = ? AND slot < ?")
                ->execute([$courseCode, $slot - 60]);
        }

        qrRespond([
            'status'          => 'ok',
            'qr_data'         => "SS-QR|{$courseCode}|{$token}|{$slot}",
            'slot'            => $slot,
            'slot_expires_in' => (int) $expiresIn,
            'course_code'     => $courseCode,
            'session_id'      => $sessionId,
        ]);
    } catch (PDOException $e) {
        apiError('Database error.', 500);
    }
}

// ─── Lecturer screen: live counts ─────────────────────────────────────────
if ($method === 'GET' && $action === 'get_session_stats') {
    $courseCode = strtoupper(trim($_GET['course_code'] ?? ''));
    qrRequireCourseAccess($pdo, $courseCode);

    try {
        $sessionId = getActiveSession($pdo, $courseCode);
        if ($sessionId) {
            $where = "course_code = ? AND session_id = ?";
            $args  = [$courseCode, $sessionId];
        } else {
            $where = "course_code = ? AND DATE(scanned_at_1) = CURDATE()";
            $args  = [$courseCode];
        }
        $s1 = $pdo->prepare("SELECT COUNT(DISTINCT student_no) FROM qr_scan_attempts WHERE $where AND completed = 1");
        $s1->execute($args);
        $s2 = $pdo->prepare("SELECT COUNT(DISTINCT student_no) FROM qr_scan_attempts WHERE $where AND completed = 0");
        $s2->execute($args);
        $flagged = 0;
        if ($sessionId) {
            $s3 = $pdo->prepare("SELECT COUNT(*) FROM qr_flags WHERE session_id = ?");
            $s3->execute([$sessionId]);
            $flagged = (int) $s3->fetchColumn();
        }
        qrRespond([
            'status'     => 'ok',
            'completed'  => (int) $s1->fetchColumn(),
            'pending'    => (int) $s2->fetchColumn(),
            'flagged'    => $flagged,
            'session_id' => $sessionId,
        ]);
    } catch (PDOException $e) {
        apiError('Database error.', 500);
    }
}

// ─── Student phone: one scan ──────────────────────────────────────────────
if ($method === 'POST' && $action === 'scan_qr') {
    requireRole('student');
    $studentNo = trim($_SESSION['student_no'] ?? $_SESSION['username'] ?? '');
    $qrData    = trim($_POST['qr_data'] ?? '');
    $deviceId  = trim($_POST['device_id'] ?? '');
    $device    = preg_match('/^[A-Za-z0-9-]{16,64}$/', $deviceId) ? hash('sha256', $deviceId) : null;
    $clientIp  = $_SERVER['REMOTE_ADDR'] ?? null;

    if ($studentNo === '' || $qrData === '') apiError('Missing data.');

    $parts = explode('|', $qrData);
    if (count($parts) !== 4 || $parts[0] !== 'SS-QR') {
        qrRespond(['status' => 'invalid', 'message' => 'Not a valid attendance QR.']);
    }
    $courseCode   = strtoupper(trim($parts[1]));
    $scannedToken = trim($parts[2]);
    $scannedSlot  = (int) $parts[3];
    $currentSlot  = (int) floor(time() / QR_SLOT_SECONDS);

    if ($currentSlot - $scannedSlot > 1 || $scannedSlot > $currentSlot) {
        qrRespond(['status' => 'expired', 'message' => 'This QR has expired. Scan the code on the screen right now.']);
    }

    try {
        $sessionId = getActiveSession($pdo, $courseCode);
        if (!$sessionId) {
            qrRespond(['status' => 'no_session', 'message' => "The {$courseCode} lecture is not running."]);
        }

        // The token must belong to THIS lecture.
        $vld = $pdo->prepare("SELECT 1 FROM qr_tokens WHERE course_code = ? AND token = ? AND slot = ? AND session_id = ?");
        $vld->execute([$courseCode, $scannedToken, $scannedSlot, $sessionId]);
        if (!$vld->fetch()) {
            qrRespond(['status' => 'invalid', 'message' => 'QR not recognised for the current lecture.']);
        }

        $done = $pdo->prepare("SELECT 1 FROM qr_scan_attempts WHERE student_no = ? AND session_id = ? AND completed = 1 LIMIT 1");
        $done->execute([$studentNo, $sessionId]);
        if ($done->fetch()) {
            qrRespond(['status' => 'already_marked', 'message' => "Your attendance for {$courseCode} is already recorded."]);
        }

        if (!isStudentEnrolled($pdo, $studentNo, $courseCode)) {
            qrRespond(['status' => 'not_enrolled', 'message' => "You are not enrolled in {$courseCode}."]);
        }

        // One phone, one student, per lecture.
        if ($device) {
            $dev = $pdo->prepare("SELECT student_no FROM qr_scan_attempts WHERE session_id = ? AND device_hash = ? AND student_no <> ? AND completed = 1 LIMIT 1");
            $dev->execute([$sessionId, $device, $studentNo]);
            if ($other = $dev->fetchColumn()) {
                qrFlag($pdo, $sessionId, $courseCode, $studentNo, 'shared_device', "Phone already used by {$other}");
                qrRespond([
                    'status'  => 'device_conflict',
                    'message' => 'This phone has already been used to mark another student present in this lecture. Use your own phone.',
                ]);
            }
        }

        $pen = $pdo->prepare("SELECT id, slot_1, token_1 FROM qr_scan_attempts WHERE student_no = ? AND session_id = ? AND completed = 0 ORDER BY scanned_at_1 DESC LIMIT 1");
        $pen->execute([$studentNo, $sessionId]);
        $pending = $pen->fetch(PDO::FETCH_ASSOC);

        if (!$pending) {
            $pdo->prepare("INSERT INTO qr_scan_attempts (student_no, course_code, session_id, token_1, slot_1, scanned_at_1, client_ip, device_hash) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)")
                ->execute([$studentNo, $courseCode, $sessionId, $scannedToken, $scannedSlot, $clientIp, $device]);
            qrRespond([
                'status'      => 'first_scan',
                'message'     => 'Scan 1 recorded. Scan the next code when it changes.',
                'slot'        => $scannedSlot,
                'need_slot'   => $scannedSlot + 1,
                'course_code' => $courseCode,
            ]);
        }

        $slot1 = (int) $pending['slot_1'];
        if ($scannedToken === $pending['token_1']) {
            qrRespond(['status' => 'same_qr', 'message' => "Same QR (#{$slot1}). Wait for the code to change."]);
        }
        if ($scannedSlot <= $slot1) {
            qrRespond(['status' => 'old_qr', 'message' => 'Please scan the current QR.']);
        }
        if ($scannedSlot !== $slot1 + 1) {
            $pdo->prepare("UPDATE qr_scan_attempts SET token_1 = ?, slot_1 = ?, scanned_at_1 = NOW(), device_hash = ? WHERE id = ?")
                ->execute([$scannedToken, $scannedSlot, $device, $pending['id']]);
            qrRespond([
                'status'    => 'not_consecutive',
                'message'   => "QR #{$scannedSlot} does not follow #{$slot1}. Started again from this code.",
                'new_slot1' => $scannedSlot,
                'need_slot' => $scannedSlot + 1,
            ]);
        }

        // Second, consecutive scan: record attendance first, then close the attempt.
        // (recordLectureAttendance runs DDL, which would silently commit a transaction,
        //  so no transaction is used here.)
        $result = recordLectureAttendance($pdo, $studentNo, $courseCode, 'QR Attendance', 'qr', date('Y-m-d H:i:s'));
        if (!in_array($result['status'], ['success', 'updated'], true)) {
            qrRespond(['status' => $result['status'], 'message' => $result['message']]);
        }
        $pdo->prepare("UPDATE qr_scan_attempts SET token_2 = ?, slot_2 = ?, scanned_at_2 = NOW(), device_hash = COALESCE(?, device_hash), completed = 1 WHERE id = ?")
            ->execute([$scannedToken, $scannedSlot, $device, $pending['id']]);

        $snm = $pdo->prepare("SELECT student_name FROM students WHERE student_no = ?");
        $snm->execute([$studentNo]);
        qrRespond([
            'status'       => 'success',
            'message'      => 'Attendance recorded!',
            'student_name' => $snm->fetchColumn() ?: $studentNo,
            'course_code'  => $courseCode,
            'pair'         => "#{$slot1} → #{$scannedSlot}",
        ]);
    } catch (PDOException $e) {
        apiError('Database error.', 500);
    }
}

qrRespond(['status' => 'error', 'message' => 'Unknown action.']);

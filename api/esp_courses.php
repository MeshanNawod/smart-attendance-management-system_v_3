<?php
/**
 * esp_courses.php — Lightweight ESP32 Hardware API
 * Endpoints designed for easy parsing on resource-constrained devices.
 *
 * Auth: hardware key (hardware_key param or X-Hardware-Key header), or an admin session
 *
 * GET  ?action=list_courses            → plain text: CODE1,CODE2,CODE3
 * GET  ?action=get_course_name&code=X  → plain text: "CSC2052 - Intro to CS"
 * POST ?action=save_fp_template        → body: slot=N&student_no=S&hex=AABBCC...
 * GET  ?action=get_fp_template&slot=N  → plain text hex blob or ERR:...
 * GET  ?action=list_fp_slots           → plain text: slot:student_no,slot:student_no,...
 * GET  ?action=lookup_rfid&uid=AABBCC  → plain text: student_no|student_name or ERR:NOT_FOUND
 */

require_once __DIR__ . '/../includes/db.php';

// ── Auth ───────────────────────────────────────────────────────────────────
// v4: ESP32 nodes send the hardware key; admins can use the same actions
// from the dashboard with their session (RFID desk, template backup).
require_once __DIR__ . '/../includes/auth/access.php';
if (!isHardwareRequest()) {
    $__role = quietSessionRole();
    if ($__role !== 'admin') {
        http_response_code(401);
        echo 'ERR:UNAUTHORIZED';
        exit;
    }
    if (in_array(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
        $__tok = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
        if (!$__tok || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $__tok)) {
            http_response_code(403);
            echo 'ERR:CSRF';
            exit;
        }
    }
}

header('Cache-Control: no-cache');

$action = strtolower(trim($_GET['action'] ?? $_POST['action'] ?? ''));

// ── 1. LIST COURSES ────────────────────────────────────────────────────────
if ($action === 'list_courses') {
    header('Content-Type: text/plain');
    try {
        $rows = $pdo->query("SELECT course_code FROM courses ORDER BY course_code ASC")->fetchAll(PDO::FETCH_COLUMN);
        echo implode(',', $rows);
    } catch (PDOException $e) {
        echo 'ERR:DB';
    }
    exit;
}

// ── 2. GET COURSE NAME ─────────────────────────────────────────────────────
if ($action === 'get_course_name') {
    header('Content-Type: text/plain');
    $code = strtoupper(trim($_GET['code'] ?? ''));
    if (!$code) { echo 'ERR:NO_CODE'; exit; }
    try {
        $stmt = $pdo->prepare("SELECT course_code, course_name FROM courses WHERE course_code = ?");
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo $row ? $row['course_code'] . ' - ' . $row['course_name'] : 'ERR:NOT_FOUND';
    } catch (PDOException $e) {
        echo 'ERR:DB';
    }
    exit;
}

// ── 3. SAVE FINGERPRINT TEMPLATE (backup from ESP32 to server) ─────────────
if ($action === 'save_fp_template') {
    header('Content-Type: text/plain');
    $slot       = (int)($_POST['slot']       ?? -1);
    $student_no = trim($_POST['student_no']  ?? '');
    $hex        = trim($_POST['hex']         ?? '');

    if ($slot < 1 || $slot > 127)  { echo 'ERR:INVALID_SLOT'; exit; }
    if (!$student_no)               { echo 'ERR:NO_STUDENT';   exit; }
    if (!$hex || strlen($hex) < 10) { echo 'ERR:NO_TEMPLATE';  exit; }

    try {
        // Store hex blob in students table's fp_template column (add column if needed)
        $stmt = $pdo->prepare(
            "UPDATE students SET fp_template_hex = ?, fingerprint_id = ?
             WHERE student_no = ?"
        );
        $ok = $stmt->execute([$hex, $slot, $student_no]);
        if ($ok && $stmt->rowCount() > 0) {
            echo 'OK:SAVED:' . $student_no . ':SLOT' . $slot;
        } else {
            // Try inserting a minimal record so it doesn't silently fail
            echo 'ERR:STUDENT_NOT_FOUND:' . $student_no;
        }
    } catch (PDOException $e) {
        // Column may not exist yet — create it transparently
        if (strpos($e->getMessage(), 'fp_template_hex') !== false) {
            try {
                $pdo->exec("ALTER TABLE students ADD COLUMN fp_template_hex MEDIUMTEXT NULL DEFAULT NULL");
                $stmt = $pdo->prepare(
                    "UPDATE students SET fp_template_hex = ?, fingerprint_id = ?
                     WHERE student_no = ?"
                );
                $stmt->execute([$hex, $slot, $student_no]);
                echo 'OK:SAVED:' . $student_no . ':SLOT' . $slot;
            } catch (PDOException $e2) {
                error_log('[esp_courses] save_fp_template alter error: ' . $e2->getMessage());
                echo 'ERR:DB';
            }
        } else {
            error_log('[esp_courses] save_fp_template error: ' . $e->getMessage());
            echo 'ERR:DB';
        }
    }
    exit;
}

// ── 4. GET FINGERPRINT TEMPLATE (restore from server to ESP32) ─────────────
if ($action === 'get_fp_template') {
    header('Content-Type: text/plain');
    $slot = (int)($_GET['slot'] ?? -1);
    if ($slot < 1 || $slot > 127) { echo 'ERR:INVALID_SLOT'; exit; }
    try {
        $stmt = $pdo->prepare(
            "SELECT student_no, fp_template_hex FROM students
             WHERE fingerprint_id = ? AND fp_template_hex IS NOT NULL LIMIT 1"
        );
        $stmt->execute([$slot]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['fp_template_hex']) {
            // Format: student_no|hexblob
            echo $row['student_no'] . '|' . $row['fp_template_hex'];
        } else {
            echo 'ERR:NOT_FOUND';
        }
    } catch (PDOException $e) {
        echo 'ERR:DB';
    }
    exit;
}

// ── 5. LIST ALL STORED FP SLOTS ────────────────────────────────────────────
if ($action === 'list_fp_slots') {
    header('Content-Type: text/plain');
    try {
        $rows = $pdo->query(
            "SELECT fingerprint_id, student_no FROM students
             WHERE fingerprint_id IS NOT NULL AND fingerprint_id != ''
             ORDER BY fingerprint_id ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
        $parts = [];
        foreach ($rows as $r) {
            $parts[] = $r['fingerprint_id'] . ':' . $r['student_no'];
        }
        echo implode(',', $parts);
    } catch (PDOException $e) {
        echo 'ERR:DB';
    }
    exit;
}

// ── 6. LOOKUP RFID UID → Student ───────────────────────────────────────────
if ($action === 'lookup_rfid') {
    header('Content-Type: text/plain');
    $uid = strtoupper(trim($_GET['uid'] ?? ''));
    if (!$uid) { echo 'ERR:NO_UID'; exit; }
    try {
        $stmt = $pdo->prepare(
            "SELECT student_no, student_name FROM students WHERE UPPER(rfid_uid) = ? LIMIT 1"
        );
        $stmt->execute([$uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo $row ? $row['student_no'] . '|' . ($row['student_name'] ?? '') : 'ERR:NOT_FOUND';
    } catch (PDOException $e) {
        echo 'ERR:DB';
    }
    exit;
}

// ── 7. LOOKUP FINGERPRINT SLOT → Student ──────────────────────────────────
if ($action === 'lookup_fingerprint') {
    header('Content-Type: text/plain');
    $slot = (int)($_GET['slot'] ?? -1);
    if ($slot < 1 || $slot > 127) { echo 'ERR:INVALID_SLOT'; exit; }
    try {
        $stmt = $pdo->prepare(
            "SELECT student_no, student_name FROM students WHERE fingerprint_id = ? LIMIT 1"
        );
        $stmt->execute([$slot]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo $row ? $row['student_no'] . '|' . ($row['student_name'] ?? '') : 'ERR:NOT_FOUND';
    } catch (PDOException $e) {
        echo 'ERR:DB';
    }
    exit;
}

// ── 8. GET ALL RFID MAPPINGS (bulk sync to ESP32 NVS) ─────────────────────
if ($action === 'list_rfid') {
    header('Content-Type: text/plain');
    try {
        $rows = $pdo->query(
            "SELECT student_no, student_name, rfid_uid FROM students
             WHERE rfid_uid IS NOT NULL AND rfid_uid != ''
             ORDER BY student_no ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
        $parts = [];
        foreach ($rows as $r) {
            $parts[] = strtoupper($r['rfid_uid']) . ':' . $r['student_no'] . ':' . ($r['student_name'] ?? '');
        }
        echo implode("\n", $parts);
    } catch (PDOException $e) {
        echo 'ERR:DB';
    }
    exit;
}

// ── 9. DELETE FINGERPRINT TEMPLATE ─────────────────────────────────────
if ($action === 'delete_fp_template') {
    header('Content-Type: text/plain');
    $slot = (int)($_POST['slot'] ?? ($_GET['slot'] ?? -1));
    $student_no = trim($_POST['student_no'] ?? ($_GET['student_no'] ?? ''));
    $hasSlot = ($slot >= 1 && $slot <= 127);
    if (!$hasSlot && empty($student_no)) {
        echo 'ERR:INVALID_PARAMS: specify slot (1-127) or student_no'; exit;
    }
    try {
        if (!empty($student_no)) {
            $stmt = $pdo->prepare("UPDATE students SET fp_template_hex = NULL, fingerprint_id = NULL WHERE student_no = ?");
            $stmt->execute([$student_no]);
        } else {
            $stmt = $pdo->prepare("UPDATE students SET fp_template_hex = NULL, fingerprint_id = NULL WHERE fingerprint_id = ?");
            $stmt->execute([$slot]);
        }
        echo 'OK:DELETED:' . $stmt->rowCount();
    } catch (PDOException $e) {
        error_log('[esp_courses] delete_fp_template: ' . $e->getMessage());
        echo 'ERR:DB';
    }
    exit;
}

// ── 10. UPLOAD FINGERPRINT TEMPLATE (POST hex + file) ────────────────
if ($action === 'upload_fp_template') {
    header('Content-Type: application/json');
    $student_no = trim($_POST['student_no'] ?? '');
    $slot       = (int)($_POST['slot'] ?? -1);
    $hex        = trim($_POST['hex'] ?? '');

    // Also accept file upload
    if (empty($hex) && isset($_FILES['template_file']) && $_FILES['template_file']['error'] === UPLOAD_ERR_OK) {
        $hex = trim(file_get_contents($_FILES['template_file']['tmp_name']));
    }

    if (empty($student_no)) {
        echo json_encode(['status'=>'error','message'=>'student_no required']);
        exit;
    }
    if (empty($hex) || strlen($hex) < 10) {
        echo json_encode(['status'=>'error','message'=>'Invalid or empty template hex']);
        exit;
    }

    // Auto-assign slot if not specified
    if ($slot < 1 || $slot > 127) {
        try {
            $stmt = $pdo->query("SELECT fingerprint_id FROM students WHERE fingerprint_id IS NOT NULL AND fingerprint_id != '' ORDER BY CAST(fingerprint_id AS UNSIGNED) DESC LIMIT 1");
            $maxId = $stmt->fetchColumn();
            $slot = ($maxId ? (int)$maxId + 1 : 1);
            if ($slot > 127) $slot = 1;
        } catch (PDOException $e) {
            $slot = 1;
        }
    }

    try {
        $stmt = $pdo->prepare("UPDATE students SET fp_template_hex = ?, fingerprint_id = ? WHERE student_no = ?");
        $ok = $stmt->execute([$hex, $slot, $student_no]);
        if ($ok && $stmt->rowCount() > 0) {
            echo json_encode(['status'=>'ok','student_no'=>$student_no,'slot'=>$slot]);
        } else {
            echo json_encode(['status'=>'error','message'=>'Student not found: '.$student_no]);
        }
    } catch (PDOException $e) {
        error_log('[esp_courses] upload_fp_template: ' . $e->getMessage());
        echo json_encode(['status'=>'error','message'=>'Database error.']);
    }
    exit;
}

// ── 11. BACKUP ALL TEMPLATES as JSON ──────────────────────────────────
if ($action === 'backup_all') {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="fp_backup_'.date('Ymd_His').'.json"');
    try {
        $rows = $pdo->query(
            "SELECT student_no, student_name, fingerprint_id, rfid_uid, fp_template_hex
             FROM students
             WHERE fp_template_hex IS NOT NULL AND fp_template_hex != ''
             ORDER BY student_no ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status'=>'ok','exported_at'=>date('Y-m-d H:i:s'),'count'=>count($rows),'templates'=>$rows], JSON_PRETTY_PRINT);
    } catch (PDOException $e) {
        error_log('[esp_courses] backup_all: ' . $e->getMessage());
        echo json_encode(['status'=>'error','message'=>'Database error.']);
    }
    exit;
}

// ── 12. RESTORE ALL TEMPLATES from JSON ──────────────────────────────
if ($action === 'restore_all') {
    header('Content-Type: application/json');
    $json = trim($_POST['json'] ?? '');
    if (empty($json) && isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
        $json = trim(file_get_contents($_FILES['backup_file']['tmp_name']));
    }
    if (empty($json)) {
        echo json_encode(['status'=>'error','message'=>'No JSON data provided. Send as POST json field or backup_file upload.']);
        exit;
    }
    $data = json_decode($json, true);
    if (!$data || !isset($data['templates']) || !is_array($data['templates'])) {
        echo json_encode(['status'=>'error','message'=>'Invalid JSON format. Expected {templates: [...]}']);
        exit;
    }
    $imported = 0; $errors = [];
    try {
        $stmt = $pdo->prepare("UPDATE students SET fp_template_hex = ?, fingerprint_id = ? WHERE student_no = ?");
        foreach ($data['templates'] as $tpl) {
            if (empty($tpl['student_no']) || empty($tpl['fp_template_hex'])) continue;
            $ok = $stmt->execute([$tpl['fp_template_hex'], $tpl['fingerprint_id'] ?? null, $tpl['student_no']]);
            if ($stmt->rowCount() > 0) $imported++;
            else $errors[] = $tpl['student_no'] . ': not found';
        }
        echo json_encode(['status'=>'ok','imported'=>$imported,'errors'=>$errors]);
    } catch (PDOException $e) {
        error_log('[esp_courses] restore_all: ' . $e->getMessage());
        echo json_encode(['status'=>'error','message'=>'Database error.']);
    }
    exit;
}

// ── 13. LIST ADMINS (for ESP32 local unlock) ──────────────────────────
if ($action === 'list_admins') {
    header('Content-Type: text/plain');
    try {
        $rows = $pdo->query(
            "SELECT username, rfid_uid, fingerprint_id FROM admins
             WHERE (rfid_uid IS NOT NULL AND rfid_uid != '')
                OR (fingerprint_id IS NOT NULL AND fingerprint_id != '')
             ORDER BY username ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
        $parts = [];
        foreach ($rows as $r) {
            $rfid = strtoupper(trim($r['rfid_uid'] ?? ''));
            $fp = trim($r['fingerprint_id'] ?? '');
            $parts[] = $r['username'] . '|' . $rfid . '|' . $fp;
        }
        echo implode("\n", $parts);
    } catch (PDOException $e) {
        echo 'ERR:DB';
    }
    exit;
}

// ── 14. LIST COURSE TEMPLATES (fingerprint slots enrolled in a course) ──
if ($action === 'list_course_templates') {
    header('Content-Type: text/plain');
    $code = strtoupper(trim($_GET['code'] ?? ($_POST['code'] ?? '')));
    if (!$code) { echo 'ERR:NO_CODE'; exit; }
    try {
        $stmt = $pdo->prepare(
            "SELECT s.student_no, s.fingerprint_id, s.fp_template_hex
             FROM students s
             INNER JOIN student_courses sc ON s.student_no = sc.student_no
             WHERE sc.course_code = ?
               AND s.fingerprint_id IS NOT NULL
               AND s.fingerprint_id != ''
             ORDER BY CAST(s.fingerprint_id AS UNSIGNED) ASC"
        );
        $stmt->execute([$code]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $parts = [];
        foreach ($rows as $r) {
            $parts[] = $r['fingerprint_id'] . ':' . $r['student_no'];
        }
        echo implode(',', $parts);
    } catch (PDOException $e) {
        echo 'ERR:DB';
    }
    exit;
}

header('HTTP/1.1 400 Bad Request');
echo 'ERR:UNKNOWN_ACTION';
exit;

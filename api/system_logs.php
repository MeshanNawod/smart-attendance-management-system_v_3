<?php
/**
 * system_logs.php — Unified System Logs API
 *
 * Actions:
 *   POST ?action=insert      — Insert a log entry (hardware key or session auth)
 *   GET  ?action=fetch       — Fetch logs with filters
 *   GET  ?action=fetch_types — Get distinct log types/sources
 *   GET  ?action=esp_sd_log  — Proxy fetch a log file from ESP32 SD card
 *   POST ?action=clear       — Clear logs (admin only)
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ─── Auto-create system_logs table ───────────────────────────────────
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_logs (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            log_type   ENUM('info','warning','error','power','debug') NOT NULL DEFAULT 'info',
            source     VARCHAR(64) NOT NULL DEFAULT 'system',
            message    TEXT NOT NULL,
            details    TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_log_type (log_type),
            INDEX idx_source (source),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'Failed to create system_logs table: '.$e->getMessage()]);
    exit;
}

$action = strtolower(trim($_GET['action'] ?? $_POST['action'] ?? ''));

// ─── Check hardware key (used by ESP32) ──────────────────────────────
function authHardwareOrSession(): string {
    $hw_key = $_SERVER['HTTP_X_HARDWARE_KEY'] ?? ($_GET['hardware_key'] ?? ($_POST['hardware_key'] ?? ''));
    $valid_hw = !empty($hw_key) && hash_equals('ss_hw_api_key_2052', $hw_key);
    if ($valid_hw) return 'hw';
    if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin','teacher'])) return 'session';
    return '';
}

// =====================================================================
// 1. INSERT LOG
// =====================================================================
if ($action === 'insert') {
    $auth = authHardwareOrSession();
    if (!$auth) {
        http_response_code(401);
        echo json_encode(['status'=>'error','message'=>'Unauthorized']);
        exit;
    }

    $log_type = strtolower(trim($_POST['log_type'] ?? 'info'));
    $source   = trim($_POST['source']   ?? 'system');
    $message  = trim($_POST['message']  ?? '');
    $details  = $_POST['details']       ?? null;

    if (!in_array($log_type, ['info','warning','error','power','debug'])) {
        $log_type = 'info';
    }
    if (empty($message)) {
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'message is required']);
        exit;
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO system_logs (log_type, source, message, details) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$log_type, $source, $message, $details]);
        echo json_encode(['status'=>'ok','id'=>(int)$pdo->lastInsertId()]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status'=>'error','message'=>'DB error: '.$e->getMessage()]);
    }
    exit;
}

// =====================================================================
// 2. FETCH LOGS
// =====================================================================
if ($action === 'fetch') {
    // Must be logged in admin/teacher
    if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin','teacher','student'])) {
        http_response_code(401);
        echo json_encode(['status'=>'error','message'=>'Unauthorized']);
        exit;
    }

    $log_type  = trim($_GET['log_type']  ?? '');
    $source    = trim($_GET['source']    ?? '');
    $search    = trim($_GET['search']    ?? '');
    $date_from = trim($_GET['date_from'] ?? '');
    $date_to   = trim($_GET['date_to']   ?? '');
    $limit     = min((int)($_GET['limit'] ?? 200), 1000);
    $offset    = max((int)($_GET['offset'] ?? 0), 0);

    $query  = "SELECT * FROM system_logs WHERE 1=1";
    $params = [];

    if (!empty($log_type)) {
        $query   .= " AND log_type = ?";
        $params[] = $log_type;
    }
    if (!empty($source)) {
        $query   .= " AND source = ?";
        $params[] = $source;
    }
    if (!empty($search)) {
        $query   .= " AND (message LIKE ? OR COALESCE(details,'') LIKE ?)";
        $params[] = '%'.$search.'%';
        $params[] = '%'.$search.'%';
    }
    if (!empty($date_from)) {
        $query   .= " AND DATE(created_at) >= ?";
        $params[] = $date_from;
    }
    if (!empty($date_to)) {
        $query   .= " AND DATE(created_at) <= ?";
        $params[] = $date_to;
    }

    try {
        // Count total matching
        $countStmt = $pdo->prepare(str_replace('SELECT *', 'SELECT COUNT(*)', $query));
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $query .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        echo json_encode(['status'=>'ok','total'=>$total,'data'=>$rows]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status'=>'error','message'=>'DB error: '.$e->getMessage()]);
    }
    exit;
}

// =====================================================================
// 3. FETCH DISTINCT TYPES / SOURCES
// =====================================================================
if ($action === 'fetch_types') {
    if (!isset($_SESSION['user_role'])) {
        http_response_code(401);
        echo json_encode(['status'=>'error','message'=>'Unauthorized']);
        exit;
    }
    try {
        $types  = $pdo->query("SELECT DISTINCT log_type FROM system_logs ORDER BY log_type")->fetchAll(PDO::FETCH_COLUMN);
        $sources = $pdo->query("SELECT DISTINCT source FROM system_logs ORDER BY source")->fetchAll(PDO::FETCH_COLUMN);
        echo json_encode(['status'=>'ok','types'=>$types,'sources'=>$sources]);
    } catch (PDOException $e) {
        echo json_encode(['status'=>'ok','types'=>[],'sources'=>[]]);
    }
    exit;
}

// =====================================================================
// 4. FETCH LOG FILE FROM ESP32 SD CARD
// =====================================================================
if ($action === 'esp_sd_log') {
    if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin','teacher'])) {
        http_response_code(401);
        echo json_encode(['status'=>'error','message'=>'Unauthorized']);
        exit;
    }

    $esp_ip   = trim($_GET['esp_ip'] ?? '');
    $filename = trim($_GET['file']   ?? 'attendance.csv');

    // Validate filename to prevent path traversal
    $filename = basename($filename);
    $validFiles = ['attendance.csv','error.csv','power.csv'];
    if (!in_array($filename, $validFiles)) {
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'Invalid file. Allowed: '.implode(', ',$validFiles)]);
        exit;
    }

    if (empty($esp_ip)) {
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'esp_ip is required']);
        exit;
    }

    $url = "http://{$esp_ip}/sdfile?path=/logs/{$filename}&_=" . time();
    $context = stream_context_create(['http' => ['timeout' => 10]]);
    $content = @file_get_contents($url, false, $context);

    if ($content === false) {
        echo json_encode(['status'=>'error','message'=>'Failed to fetch from ESP32. Check IP and ensure device is online.']);
        exit;
    }

    // Parse CSV lines
    $lines = explode("\n", trim($content));
    $rows = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        $parts = str_getcsv($line);
        $rows[] = $parts;
    }

    echo json_encode(['status'=>'ok','file'=>$filename,'rows'=>$rows,'raw'=>$content]);
    exit;
}

// =====================================================================
// 5. CLEAR LOGS (admin only)
// =====================================================================
if ($action === 'clear') {
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['status'=>'error','message'=>'Admin only']);
        exit;
    }

    $log_type = trim($_POST['log_type'] ?? '');
    $older_than_days = max((int)($_POST['older_than_days'] ?? 0), 0);

    try {
        if (!empty($log_type)) {
            $stmt = $pdo->prepare("DELETE FROM system_logs WHERE log_type = ?");
            $stmt->execute([$log_type]);
            $deleted = $stmt->rowCount();
        } elseif ($older_than_days > 0) {
            $stmt = $pdo->prepare("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $stmt->execute([$older_than_days]);
            $deleted = $stmt->rowCount();
        } else {
            $deleted = $pdo->exec("TRUNCATE TABLE system_logs");
        }
        echo json_encode(['status'=>'ok','deleted'=>$deleted]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status'=>'error','message'=>'DB error: '.$e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['status'=>'error','message'=>'Unknown action: '.$action]);

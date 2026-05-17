<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$action = $_GET['action'] ?? '';

if ($action === 'daily_trend') {
    $days = min((int)($_GET['days'] ?? 7), 90);
    $stmt = $pdo->prepare("
        SELECT DATE(timestamp) AS dt, COUNT(*) AS cnt
        FROM attendance_logs
        WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(timestamp)
        ORDER BY dt ASC
    ");
    $stmt->execute([$days]);
    $labels = []; $data = [];
    while ($r = $stmt->fetch()) {
        $labels[] = $r['dt'];
        $data[]  = (int)$r['cnt'];
    }

    $course = $pdo->prepare("
        SELECT DATE(timestamp) AS dt, COUNT(*) AS cnt
        FROM attendance_logs
        WHERE course_code = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(timestamp)
        ORDER BY dt ASC
    ");

    header('Content-Type: application/json');
    echo json_encode(['labels' => $labels, 'data' => $data]);
    exit;
}

if ($action === 'course_distribution') {
    $stmt = $pdo->query("
        SELECT COALESCE(course_code, 'Unknown') AS course, COUNT(*) AS cnt
        FROM attendance_logs
        GROUP BY course_code
        ORDER BY cnt DESC
        LIMIT 15
    ");
    $labels = []; $data = []; $colors = [];
    $palette = ['#a11e17','#fcd40d','#1e293b','#0d6efd','#198754','#dc3545','#fd7e14','#6f42c1','#20c997','#e83e8c','#ffc107','#17a2b8','#6610f2','#d63384','#adb5bd'];
    $i = 0;
    while ($r = $stmt->fetch()) {
        $labels[] = $r['course'];
        $data[]   = (int)$r['cnt'];
        $colors[] = $palette[$i % count($palette)];
        $i++;
    }
    header('Content-Type: application/json');
    echo json_encode(['labels' => $labels, 'data' => $data, 'colors' => $colors]);
    exit;
}

if ($action === 'modality_distribution') {
    $stmt = $pdo->query("
        SELECT modality, COUNT(*) AS cnt
        FROM attendance_logs
        GROUP BY modality
        ORDER BY cnt DESC
    ");
    $labels = []; $data = []; $colors = [];
    $palette = ['#0d6efd','#198754','#ffc107','#dc3545','#6f42c1','#20c997'];
    $i = 0;
    while ($r = $stmt->fetch()) {
        $labels[] = ucfirst($r['modality']);
        $data[]   = (int)$r['cnt'];
        $colors[] = $palette[$i % count($palette)];
        $i++;
    }
    header('Content-Type: application/json');
    echo json_encode(['labels' => $labels, 'data' => $data, 'colors' => $colors]);
    exit;
}

if ($action === 'hourly_heat') {
    $stmt = $pdo->query("
        SELECT HOUR(timestamp) AS hr, COUNT(*) AS cnt
        FROM attendance_logs
        WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY HOUR(timestamp)
        ORDER BY hr ASC
    ");
    $labels = []; $data = [];
    for ($h = 0; $h < 24; $h++) $data[$h] = 0;
    while ($r = $stmt->fetch()) {
        $h = (int)$r['hr'];
        $data[$h] = (int)$r['cnt'];
    }
    for ($h = 0; $h < 24; $h++) {
        $labels[] = sprintf('%02d:00', $h);
    }
    $data = array_values($data);
    header('Content-Type: application/json');
    echo json_encode(['labels' => $labels, 'data' => $data]);
    exit;
}

if ($action === 'student_trend') {
    $studentNo = trim($_GET['student_no'] ?? '');
    $days = min((int)($_GET['days'] ?? 30), 90);
    if (!$studentNo) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Missing student_no']);
        exit;
    }
    $stmt = $pdo->prepare("
        SELECT DATE(timestamp) AS dt, COUNT(*) AS cnt
        FROM attendance_logs
        WHERE student_no = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(timestamp)
        ORDER BY dt ASC
    ");
    $stmt->execute([$studentNo, $days]);
    $labels = []; $data = [];
    while ($r = $stmt->fetch()) {
        $labels[] = $r['dt'];
        $data[]  = (int)$r['cnt'];
    }
    header('Content-Type: application/json');
    echo json_encode(['labels' => $labels, 'data' => $data]);
    exit;
}

if ($action === 'per_course') {
    $course = trim($_GET['course'] ?? '');
    $days = min((int)($_GET['days'] ?? 30), 90);
    $role = $_SESSION['role'] ?? 'student';
    $userId = $_SESSION['user_id'] ?? 0;

    if ($course) {
        $stmt = $pdo->prepare("
            SELECT DATE(timestamp) AS dt, COUNT(*) AS cnt
            FROM attendance_logs
            WHERE course_code = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(timestamp)
            ORDER BY dt ASC
        ");
        $stmt->execute([$course, $days]);
    } else {
        $where = '';
        $params = [$days];
        if ($role === 'teacher') {
            $where = 'AND al.course_code IN (SELECT tc.course_code FROM teacher_courses tc WHERE tc.teacher_id = ?)';
            $params[] = $userId;
        }
        $stmt = $pdo->prepare("
            SELECT al.course_code AS label, COUNT(*) AS cnt
            FROM attendance_logs al
            WHERE al.timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY) $where
            GROUP BY al.course_code
            ORDER BY cnt DESC
            LIMIT 15
        ");
        $stmt->execute($params);
    }
    $labels = []; $data = []; $colors = [];
    $palette = ['#a11e17','#fcd40d','#1e293b','#0d6efd','#198754','#dc3545','#fd7e14','#6f42c1','#20c997','#e83e8c','#ffc107','#17a2b8','#6610f2','#d63384','#adb5bd'];
    $i = 0;
    while ($r = $stmt->fetch()) {
        $labels[] = $course ? $r['dt'] : $r['label'];
        $data[]   = (int)$r['cnt'];
        $colors[] = $palette[$i % count($palette)];
        $i++;
    }
    header('Content-Type: application/json');
    echo json_encode(['labels' => $labels, 'data' => $data, 'colors' => $colors]);
    exit;
}

if ($action === 'per_teacher') {
    $days = min((int)($_GET['days'] ?? 30), 90);
    $role = $_SESSION['role'] ?? 'student';
    $userId = $_SESSION['user_id'] ?? 0;

    if ($role === 'teacher') {
        $stmt = $pdo->prepare("
            SELECT t.teacher_name, COUNT(al.id) AS cnt
            FROM attendance_logs al
            JOIN teacher_courses tc ON tc.course_code = al.course_code
            JOIN teachers t ON t.id = tc.teacher_id
            WHERE tc.teacher_id = ? AND al.timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY t.id, t.teacher_name
            ORDER BY cnt DESC
        ");
        $stmt->execute([$userId, $days]);
    } else {
        $stmt = $pdo->prepare("
            SELECT t.teacher_name, COUNT(al.id) AS cnt
            FROM attendance_logs al
            JOIN teacher_courses tc ON tc.course_code = al.course_code
            JOIN teachers t ON t.id = tc.teacher_id
            WHERE al.timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY t.id, t.teacher_name
            ORDER BY cnt DESC
            LIMIT 20
        ");
        $stmt->execute([$days]);
    }
    $labels = []; $data = []; $colors = [];
    $palette = ['#0d6efd','#198754','#ffc107','#dc3545','#6f42c1','#fd7e14','#20c997','#e83e8c','#17a2b8','#6610f2','#fcd40d','#a11e17','#1e293b'];

    $i = 0;
    while ($r = $stmt->fetch()) {
        $labels[] = $r['teacher_name'];
        $data[]   = (int)$r['cnt'];
        $colors[] = $palette[$i % count($palette)];
        $i++;
    }
    header('Content-Type: application/json');
    echo json_encode(['labels' => $labels, 'data' => $data, 'colors' => $colors]);
    exit;
}

if ($action === 'overall_summary') {
    $total = $pdo->query("SELECT COUNT(*) FROM attendance_logs")->fetchColumn();
    $today = $pdo->query("SELECT COUNT(*) FROM attendance_logs WHERE DATE(timestamp) = CURDATE()")->fetchColumn();
    $students = $pdo->query("SELECT COUNT(DISTINCT student_no) FROM attendance_logs")->fetchColumn();
    $courses = $pdo->query("SELECT COUNT(DISTINCT course_code) FROM attendance_logs")->fetchColumn();

    // Modality breakdown
    $stmt = $pdo->query("SELECT modality, COUNT(*) AS cnt FROM attendance_logs GROUP BY modality ORDER BY cnt DESC LIMIT 5");
    $modLabels = []; $modData = [];
    while ($r = $stmt->fetch()) {
        $modLabels[] = ucfirst($r['modality']);
        $modData[] = (int)$r['cnt'];
    }

    header('Content-Type: application/json');
    echo json_encode([
        'total_attendance' => (int)$total,
        'today_attendance' => (int)$today,
        'unique_students'  => (int)$students,
        'unique_courses'   => (int)$courses,
        'modality_labels'  => $modLabels,
        'modality_data'    => $modData,
    ]);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['error' => 'Invalid action']);

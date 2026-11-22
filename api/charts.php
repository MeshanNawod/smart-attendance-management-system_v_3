<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$action = $_GET['action'] ?? '';

// v4: analytics are for admins and lecturers only.
require_once __DIR__ . '/../includes/auth/access.php';
enforceActionPolicy($action, [], ['roles' => ['admin', 'teacher']]);

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

    header('Content-Type: application/json');
    echo json_encode(['labels' => $labels, 'data' => $data]);
    exit;
}

if ($action === 'course_distribution') {
    $days = min((int)($_GET['days'] ?? 0), 90);
    $sql = "
        SELECT COALESCE(course_code, 'Unknown') AS course, COUNT(*) AS cnt
        FROM attendance_logs
    ";
    $params = [];
    if ($days > 0) {
        $sql .= "WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY) ";
        $params[] = $days;
    }
    $sql .= "GROUP BY course_code ORDER BY cnt DESC LIMIT 15";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
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
    $days = min((int)($_GET['days'] ?? 0), 90);
    $sql = "SELECT modality, COUNT(*) AS cnt FROM attendance_logs";
    $params = [];
    if ($days > 0) {
        $sql .= " WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $params[] = $days;
    }
    $sql .= " GROUP BY modality ORDER BY cnt DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
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

if ($action === 'place_distribution') {
    $days = min((int)($_GET['days'] ?? 0), 90);
    $sql = "SELECT COALESCE(device_name, 'Unknown Place') AS place, COUNT(*) AS cnt FROM attendance_logs";
    $params = [];
    if ($days > 0) {
        $sql .= " WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $params[] = $days;
    }
    $sql .= " GROUP BY device_name ORDER BY cnt DESC LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $labels = []; $data = []; $colors = [];
    $palette = ['#6366f1','#10b981','#f59e0b','#ec4899','#8b5cf6','#ef4444','#06b6d4','#14b8a6','#f43f5e','#a855f7'];
    $i = 0;
    while ($r = $stmt->fetch()) {
        $labels[] = $r['place'];
        $data[]   = (int)$r['cnt'];
        $colors[] = $palette[$i % count($palette)];
        $i++;
    }
    header('Content-Type: application/json');
    echo json_encode(['labels' => $labels, 'data' => $data, 'colors' => $colors]);
    exit;
}

if ($action === 'student_course_stats') {
    $studentNo = trim($_GET['student_no'] ?? '');
    $courseCode = trim($_GET['course'] ?? $_GET['course_code'] ?? '');

    if (!$studentNo || !$courseCode) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Missing student_no or course_code']);
        exit;
    }

    // 1. Get student profile name
    $stmtName = $pdo->prepare("SELECT student_name FROM students WHERE student_no = ?");
    $stmtName->execute([$studentNo]);
    $studentName = $stmtName->fetchColumn() ?: 'Student';

    // 2. Get distinct calendar dates of course sessions (total sessions held)
    $stmtSessions = $pdo->prepare("
        SELECT DISTINCT DATE(timestamp) AS dt
        FROM attendance_logs
        WHERE course_code = ?
        ORDER BY dt ASC
    ");
    $sessions = [];
    if ($stmtSessions->execute([$courseCode])) {
        $sessions = $stmtSessions->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
    $totalSessions = count($sessions);

    // 3. Get target student's attendance count for this course
    $stmtStudentAtt = $pdo->prepare("
        SELECT COUNT(DISTINCT DATE(timestamp))
        FROM attendance_logs
        WHERE course_code = ? AND student_no = ?
    ");
    $stmtStudentAtt->execute([$courseCode, $studentNo]);
    $studentAttCount = (int)$stmtStudentAtt->fetchColumn();
    $studentRate = $totalSessions > 0 ? round(($studentAttCount / $totalSessions) * 100, 1) : 0;

    // 4. Query attendance count for every student enrolled or present in this course
    $stmtClass = $pdo->prepare("
        SELECT sc.student_no, COUNT(DISTINCT DATE(al.timestamp)) AS cnt
        FROM student_courses sc
        LEFT JOIN attendance_logs al ON al.student_no = sc.student_no AND al.course_code = sc.course_code
        WHERE sc.course_code = ?
        GROUP BY sc.student_no
    ");
    $stmtClass->execute([$courseCode]);
    $classData = $stmtClass->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (empty($classData)) {
        $stmtClassLogs = $pdo->prepare("
            SELECT student_no, COUNT(DISTINCT DATE(timestamp)) AS cnt
            FROM attendance_logs
            WHERE course_code = ?
            GROUP BY student_no
        ");
        $stmtClassLogs->execute([$courseCode]);
        $classData = $stmtClassLogs->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    $totalStudents = count($classData);
    $rates = [];

    foreach ($classData as $row) {
        $rate = $totalSessions > 0 ? round(($row['cnt'] / $totalSessions) * 100, 1) : 0;
        $rates[$row['student_no']] = $rate;
    }

    if (!isset($rates[$studentNo])) {
        $rates[$studentNo] = $studentRate;
    }

    $ratesList = array_values($rates);
    sort($ratesList);

    // 5. Compute Key Statistics
    $sum = array_sum($ratesList);
    $mean = $totalStudents > 0 ? round($sum / $totalStudents, 1) : 0;

    if ($totalStudents > 0) {
        $mid = floor($totalStudents / 2);
        $median = ($totalStudents % 2 !== 0) ? $ratesList[$mid] : round(($ratesList[$mid - 1] + $ratesList[$mid]) / 2, 1);
    } else {
        $median = 0;
    }

    $min = !empty($ratesList) ? $ratesList[0] : 0;
    $max = !empty($ratesList) ? $ratesList[count($ratesList) - 1] : 0;

    $varianceSum = 0;
    foreach ($ratesList as $r) {
        $varianceSum += pow($r - $mean, 2);
    }
    $variance = $totalStudents > 0 ? $varianceSum / $totalStudents : 0;
    $stdDev = round(sqrt($variance), 1);

    $zScore = $stdDev > 0 ? round(($studentRate - $mean) / $stdDev, 2) : 0;

    // Rank calculation (descending)
    arsort($rates);
    $rank = 1;
    $targetStudentRank = 1;
    $prevRate = -1;
    foreach ($rates as $sNo => $r) {
        if ($r !== $prevRate) {
            $currentRank = $rank;
        }
        if ($sNo === $studentNo) {
            $targetStudentRank = $currentRank;
            break;
        }
        $prevRate = $r;
        $rank++;
    }

    // Peer distribution groupings
    $distribution = ['0-20%' => 0, '21-40%' => 0, '41-60%' => 0, '61-80%' => 0, '81-100%' => 0];
    foreach ($ratesList as $r) {
        if ($r <= 20) $distribution['0-20%']++;
        elseif ($r <= 40) $distribution['21-40%']++;
        elseif ($r <= 60) $distribution['41-60%']++;
        elseif ($r <= 80) $distribution['61-80%']++;
        else $distribution['81-100%']++;
    }

    // 6. Modality Breakdowns
    $stmtModality = $pdo->prepare("
        SELECT modality, COUNT(*) AS cnt
        FROM attendance_logs
        WHERE course_code = ? AND student_no = ?
        GROUP BY modality
    ");
    $stmtModality->execute([$courseCode, $studentNo]);
    $studentModalities = $stmtModality->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

    $stmtClassModality = $pdo->prepare("
        SELECT modality, COUNT(*) AS cnt
        FROM attendance_logs
        WHERE course_code = ?
        GROUP BY modality
    ");
    $stmtClassModality->execute([$courseCode]);
    $classModalities = $stmtClassModality->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

    $modalitiesList = ['fingerprint', 'rfid', 'face', '2fa', 'manual'];
    $studentModalityCounts = [];
    $classModalityAverages = [];

    foreach ($modalitiesList as $mod) {
        $studentModalityCounts[ucfirst($mod)] = (int)($studentModalities[$mod] ?? 0);
        $classModalityAverages[ucfirst($mod)] = $totalStudents > 0 ? round(($classModalities[$mod] ?? 0) / $totalStudents, 1) : 0;
    }

    // 7. Session-by-session Timelines
    $stmtDatesAttended = $pdo->prepare("
        SELECT DISTINCT DATE(timestamp)
        FROM attendance_logs
        WHERE course_code = ? AND student_no = ?
    ");
    $stmtDatesAttended->execute([$courseCode, $studentNo]);
    $attendedDates = $stmtDatesAttended->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $attendedDatesSet = array_flip($attendedDates);

    $stmtClassDaily = $pdo->prepare("
        SELECT DATE(timestamp) AS dt, COUNT(DISTINCT student_no) AS cnt
        FROM attendance_logs
        WHERE course_code = ?
        GROUP BY DATE(timestamp)
    ");
    $stmtClassDaily->execute([$courseCode]);
    $classDailyCounts = $stmtClassDaily->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

    $sessionLabels = [];
    $studentPresence = [];
    $classPresencePercentage = [];
    $studentCumulativeRate = [];
    $classCumulativeRate = [];
    $studentPresentCumulative = 0;
    $classSumRate = 0;

    foreach ($sessions as $index => $dt) {
        $sessionLabels[] = date('d M', strtotime($dt));
        $isPresent = isset($attendedDatesSet[$dt]) ? 1 : 0;
        $studentPresence[] = $isPresent;
        
        $classCountOnDate = $classDailyCounts[$dt] ?? 0;
        $classTurnoutRate = $totalStudents > 0 ? round(($classCountOnDate / $totalStudents) * 100, 1) : 0;
        $classPresencePercentage[] = $classTurnoutRate;

        $studentPresentCumulative += $isPresent;
        $studentCumulativeRate[] = round(($studentPresentCumulative / ($index + 1)) * 100, 1);
        
        $classSumRate += $classTurnoutRate;
        $classCumulativeRate[] = round($classSumRate / ($index + 1), 1);
    }

    // --- 8. ADVANCED PREDICTIVE & STATISTICAL METRICS ---
    // A. Consistency Score (Running cumulative rates standard deviation)
    $cumulativeMean = count($studentCumulativeRate) > 0 ? array_sum($studentCumulativeRate) / count($studentCumulativeRate) : 0;
    $cumulativeVarSum = 0;
    foreach ($studentCumulativeRate as $cr) {
        $cumulativeVarSum += pow($cr - $cumulativeMean, 2);
    }
    $cumulativeVar = count($studentCumulativeRate) > 0 ? $cumulativeVarSum / count($studentCumulativeRate) : 0;
    $cumulativeStdDev = sqrt($cumulativeVar);
    $consistencyScore = count($studentCumulativeRate) > 0 ? max(0, round(100 - ($cumulativeStdDev * 2.2), 1)) : 100;

    // B. Trend Slope (Linear Regression)
    $n = count($sessions);
    $slope = 0;
    if ($n > 1) {
        $sumX = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0;
        foreach ($studentCumulativeRate as $x => $y) {
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }
        $denom = ($n * $sumX2) - ($sumX * $sumX);
        $slope = $denom != 0 ? (($n * $sumXY) - ($sumX * $sumY)) / $denom : 0;
        $slope = round($slope, 2);
    }

    // C. Percentile Rank
    $lessCount = 0;
    $equalCount = 0;
    foreach ($ratesList as $r) {
        if ($r < $studentRate) $lessCount++;
        elseif ($r == $studentRate) $equalCount++;
    }
    $percentile = $totalStudents > 0 ? round((($lessCount + (0.5 * $equalCount)) / $totalStudents) * 100, 1) : 100;

    // D. Consecutive Absences (from the end of timeline)
    $consecutiveAbsences = 0;
    for ($i = count($studentPresence) - 1; $i >= 0; $i--) {
        if ($studentPresence[$i] === 0) {
            $consecutiveAbsences++;
        } else {
            break;
        }
    }

    // E. Risk Level Classification
    $riskScore = 100 - $studentRate;
    if ($slope < -0.2) {
        $riskScore += 15;
    } elseif ($slope > 0.2) {
        $riskScore -= 10;
    }
    $riskScore += $consecutiveAbsences * 10;
    $riskScore = max(0, min(100, round($riskScore, 1)));

    $riskLevel = 'Low Risk';
    if ($riskScore >= 75) {
        $riskLevel = 'Critical Danger';
    } elseif ($riskScore >= 50) {
        $riskLevel = 'High Warning';
    } elseif ($riskScore >= 25) {
        $riskLevel = 'Moderate Warning';
    }

    header('Content-Type: application/json');
    echo json_encode([
        'student_name' => $studentName,
        'total_sessions' => $totalSessions,
        'student_rate' => $studentRate,
        'class_mean' => $mean,
        'class_median' => $median,
        'class_min' => $min,
        'class_max' => $max,
        'std_dev' => $stdDev,
        'z_score' => $zScore,
        'rank' => $targetStudentRank,
        'total_students' => $totalStudents,
        'distribution' => $distribution,
        'consistency_score' => $consistencyScore,
        'trend_slope' => $slope,
        'percentile_rank' => $percentile,
        'consecutive_absences' => $consecutiveAbsences,
        'risk_score' => $riskScore,
        'risk_level' => $riskLevel,
        'modality' => [
            'labels' => array_keys($studentModalityCounts),
            'student_data' => array_values($studentModalityCounts),
            'class_data' => array_values($classModalityAverages)
        ],
        'timeline' => [
            'labels' => $sessionLabels,
            'student_presence' => $studentPresence,
            'student_cumulative' => $studentCumulativeRate,
            'class_turnout' => $classPresencePercentage,
            'class_cumulative' => $classCumulativeRate
        ]
    ]);
    exit;
}

if ($action === 'hourly_heat') {
    $days = min((int)($_GET['days'] ?? 30), 90);
    $stmt = $pdo->prepare("
        SELECT HOUR(timestamp) AS hr, COUNT(*) AS cnt
        FROM attendance_logs
        WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY HOUR(timestamp)
        ORDER BY hr ASC
    ");
    $stmt->execute([$days]);
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
    $role = $_SESSION['user_role'] ?? 'student';
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
    $role = $_SESSION['user_role'] ?? 'student';
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

if ($action === 'lecture_hall_occupancy') {
    $days = min((int)($_GET['days'] ?? 30), 90);
    $device = trim($_GET['device'] ?? '');

    // Get all distinct logged device names
    $stmtDev = $pdo->query("SELECT DISTINCT COALESCE(device_name, 'Unknown Place') AS place FROM attendance_logs WHERE device_name IS NOT NULL ORDER BY place ASC");
    $devices = $stmtDev->fetchAll(PDO::FETCH_COLUMN) ?: [];

    if (!$device && !empty($devices)) {
        $device = $devices[0];
    }

    // Hourly check-ins (08:00 to 18:00)
    $stmt = $pdo->prepare("
        SELECT HOUR(timestamp) AS hr, COUNT(*) AS cnt
        FROM attendance_logs
        WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY) AND device_name = ?
        GROUP BY HOUR(timestamp)
        ORDER BY hr ASC
    ");
    $stmt->execute([$days, $device]);

    $data = array_fill(8, 11, 0); // 8:00 to 18:00
    while ($r = $stmt->fetch()) {
        $h = (int)$r['hr'];
        if ($h >= 8 && $h <= 18) {
            $data[$h] = (int)$r['cnt'];
        }
    }

    $labels = [];
    $dataValues = [];
    foreach ($data as $h => $cnt) {
        $labels[] = sprintf('%02d:00', $h);
        $dataValues[] = $cnt;
    }

    header('Content-Type: application/json');
    echo json_encode([
        'labels' => $labels,
        'data' => $dataValues,
        'devices' => $devices,
        'selected_device' => $device
    ]);
    exit;
}

if ($action === 'overall_summary') {
    $days = min((int)($_GET['days'] ?? 0), 90);
    if ($days > 0) {
        $stmtPeriod = $pdo->prepare("SELECT COUNT(*) FROM attendance_logs WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmtPeriod->execute([$days]);
        $total = (int)$stmtPeriod->fetchColumn();
    } else {
        $total = (int)$pdo->query("SELECT COUNT(*) FROM attendance_logs")->fetchColumn();
    }
    $today = (int)$pdo->query("SELECT COUNT(*) FROM attendance_logs WHERE DATE(timestamp) = CURDATE()")->fetchColumn();
    $students = (int)$pdo->query("SELECT COUNT(DISTINCT student_no) FROM attendance_logs")->fetchColumn();
    $courses = (int)$pdo->query("SELECT COUNT(DISTINCT course_code) FROM attendance_logs")->fetchColumn();

    // Modality breakdown
    $modSql = "SELECT modality, COUNT(*) AS cnt FROM attendance_logs";
    $modParams = [];
    if ($days > 0) {
        $modSql .= " WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $modParams[] = $days;
    }
    $modSql .= " GROUP BY modality ORDER BY cnt DESC LIMIT 5";
    $stmt = $pdo->prepare($modSql);
    $stmt->execute($modParams);
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

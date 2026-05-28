<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

if (!isTeacher()) {
    if (isAdmin()) { header('Location: index.php'); exit; }
    if (isStudent()) { header('Location: student_dashboard.php'); exit; }
    header('Location: login.php'); exit;
}

$teacherId = $_SESSION['teacher_id'] ?? null;
$teacherName = $_SESSION['full_name'] ?? 'Teacher';
$teacherEmail = '';
$department = '';
$profilePhoto = '';
$phone = '';
$initials = '';

$myCourses = [];
$allStudents = [];
$discoveredDevices = [];

$db_error = '';
try {
    if ($teacherId) {
        $p = $pdo->prepare("SELECT t.teacher_name, t.department, t.phone, t.profile_photo, u.email FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
        $p->execute([$teacherId]);
        $tInfo = $p->fetch(PDO::FETCH_ASSOC);
        if ($tInfo) {
            $teacherName = $tInfo['teacher_name'];
            $department = $tInfo['department'] ?? '';
            $teacherEmail = $tInfo['email'] ?? '';
            $phone = $tInfo['phone'] ?? '';
            $initials = getInitials($teacherName);
            if (!empty($tInfo['profile_photo']) && file_exists(__DIR__ . '/' . $tInfo['profile_photo'])) {
                $profilePhoto = $tInfo['profile_photo'];
            }
        }

        $q = $pdo->prepare("SELECT DISTINCT c.course_code, c.course_name FROM courses c JOIN teacher_courses tc ON c.course_code = tc.course_code WHERE tc.teacher_id = ? ORDER BY c.course_code");
        $q->execute([$teacherId]);
        $myCourses = $q->fetchAll(PDO::FETCH_ASSOC);

        $stmtStu = $pdo->query("SELECT student_no, student_name FROM students ORDER BY student_no ASC");
        $allStudents = $stmtStu->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (PDOException $e) {
    error_log('Teacher DB Error: ' . $e->getMessage());
    $db_error = $e->getMessage();
    $myCourses = [];
    $allStudents = [];
    $initials = getInitials($teacherName);
}

try {
    $registry_file = __DIR__ . '/devices_registry.json';
    if (file_exists($registry_file)) {
        $registry = json_decode(file_get_contents($registry_file), true) ?: [];
        $now = time();
        foreach ($registry as $ip => $info) {
            if (empty($info['blocked'])) {
                $online = ($now - (int)$info['last_seen']) <= 30;
                $discoveredDevices[] = ['ip' => $ip, 'name' => $info['name'] ?? 'Unknown Node', 'online' => $online];
            }
        }
    }
    // Deduplicate devices by name (keep first occurrence, prefer online)
    $seen = [];
    $deduped = [];
    foreach ($discoveredDevices as $d) {
        $n = $d['name'];
        if (!isset($seen[$n])) {
            $seen[$n] = true;
            $deduped[] = $d;
        } elseif ($d['online']) {
            // Replace offline entry with online one
            foreach ($deduped as &$dd) {
                if ($dd['name'] === $n && !$dd['online']) {
                    $dd = $d;
                    break;
                }
            }
        }
    }
    $discoveredDevices = $deduped;
} catch (Exception $e) {
    $discoveredDevices = [];
}

$csrf = $_SESSION['csrf_token'] ?? '';
$courseData = json_encode($myCourses);
$deviceOptions = '<option value="WEB_DASHBOARD">Web Dashboard</option>';
foreach ($discoveredDevices as $d) {
    $deviceOptions .= '<option value="'.htmlspecialchars($d['name']).'">'.htmlspecialchars($d['name']).($d['online']?'':' (Offline)').'</option>';
}
$devicesJson = json_encode(array_merge([['name'=>'WEB_DASHBOARD','online'=>true]], $discoveredDevices));
$avatarHtml = $profilePhoto
    ? '<img id="profile-img" src="'.htmlspecialchars($profilePhoto).'" alt="Photo">'
    : '<div id="profile-img" class="initials-avatar">'.htmlspecialchars($initials ?: '?').'</div>';
$editAvatarHtml = $profilePhoto
    ? '<img id="edit-photo-preview" src="'.htmlspecialchars($profilePhoto).'" alt="Preview">'
    : '<div id="edit-photo-preview" class="initials-avatar">'.htmlspecialchars($initials ?: '?').'</div>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Portal — Sentinel Swarm</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo htmlspecialchars(asset_url('assets/favicon.svg'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('css/vendor/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('css/vendor/bootstrap-icons.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('css/tables.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('css/cards.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <script src="<?php echo htmlspecialchars(asset_url('js/qrcode.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <style>
        body { background: #fdfdfd; }
        .teacher-hero { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; color: #1e293b; }
        .avatar-ring { width: 70px; height: 70px; border-radius: 50%; overflow: hidden; padding: 2px; background: #e2e8f0; position: relative; cursor: pointer; }
        .avatar-ring img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 2px solid #fff; }
        .avatar-ring .initials-avatar { width: 100%; height: 100%; border-radius: 50%; background: #f8fafc; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; font-weight: 600; color: #a11e17; border: 2px solid #fff; }
        .avatar-ring .cam-overlay { position: absolute; bottom: 0; right: 0; width: 22px; height: 22px; background: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 1px solid #e2e8f0; }
        .avatar-ring .cam-overlay i { font-size: 10px; color: #64748b; }
        .stat-select { border-radius: 4px; border: 1px solid #e2e8f0; }
        .ls-wide { letter-spacing: 0.02em; }
        .start-hero { border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; background: #fff; position: relative; }
        .start-hero h5 { color: #1e293b; }
        #btn-start { background: #a11e17; border: none; border-radius: 4px; }
        #btn-start:hover { background: #8b1510; }
        .log-row { border-bottom: 1px solid #f8fafc; padding: 0.6rem 0; }
        .log-row:last-child { border-bottom: none; }
        .course-item { border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; background: #fff; }
        .course-item .progress { border-radius: 4px; overflow: hidden; }
        .course-item .progress-bar { border-radius: 4px; }
        .nav-tabs .nav-link { border: none; color: #64748b; font-weight: 500; padding: 0.75rem 1rem; border-radius: 0; }
        .nav-tabs .nav-link.active { color: #a11e17; border-bottom: 2px solid #a11e17; background: transparent; }
        .badge-pill { border-radius: 4px; }
        #ai-assistant-container { display: none !important; }
        /* Styled dropdowns */
        select.form-select, .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.6rem center;
            background-size: 14px;
            padding-right: 2rem !important;
            border-radius: 8px;
            border: 1.5px solid #e2e8f0;
            background-color: #fff;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
        }
        select.form-select:hover {
            border-color: #a11e17;
        }
        select.form-select:focus {
            border-color: #a11e17;
            box-shadow: 0 0 0 3px rgba(161,30,23,0.12);
            outline: none;
        }
        .form-select-sm {
            font-size: 0.82rem;
            padding-top: 0.3rem;
            padding-bottom: 0.3rem;
        }
    </style>
</head>
<body>
<?php require 'includes/header_teacher.php'; ?>

<input type="hidden" id="start-course-input" value="">
<input type="hidden" id="manual-course-code" value="">

<div class="teacher-hero p-4 mb-4">
    <div class="row align-items-center position-relative" style="z-index:1;">
        <div class="col-auto">
            <div class="avatar-ring" onclick="document.getElementById('photo-upload').click()" title="Upload photo">
                <?php echo $avatarHtml; ?>
                <div class="cam-overlay"><i class="bi bi-camera-fill"></i></div>
            </div>
            <input type="file" id="photo-upload" class="d-none" accept="image/*" onchange="uploadPhoto(this)">
        </div>
        <div class="col">
            <h3 class="fw-bold mb-1"><?php echo htmlspecialchars($teacherName); ?></h3>
            <p class="mb-0 opacity-75"><i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($teacherEmail); ?>
            <?php if ($department): ?> &bull; <i class="bi bi-building me-1"></i><?php echo htmlspecialchars($department); ?><?php endif; ?>
            <?php if ($phone): ?> &bull; <i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($phone); ?><?php endif; ?></p>
        </div>
        <div class="col-auto text-end">
            <div class="mb-2">
                <strong class="clock-time" id="teacher-clock" style="font-size:1.5rem;font-variant-numeric:tabular-nums;">--:--:--</strong>
                <br><span class="text-muted small" id="teacher-date">---</span>
            </div>
            <button class="btn btn-sm btn-outline-secondary rounded px-3" data-bs-toggle="modal" data-bs-target="#editModal"><i class="bi bi-pencil me-1"></i>Edit Profile</button>
        </div>
    </div>
</div>

<?php if (!empty($db_error)): ?>
<div class="alert alert-danger mx-3 mb-4 d-flex align-items-center" role="alert" style="border-radius:12px;">
    <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
    <div>
        <h6 class="fw-bold mb-1">Database Connection Error</h6>
        <div class="small"><?php echo htmlspecialchars($db_error); ?></div>
        <div class="small text-muted mt-1">Please ensure your database is running and the schema is correct. You may not be able to view or manage your courses.</div>
    </div>
</div>
<?php endif; ?>

<script>
function updateTeacherClock() {
    var now = new Date();
    document.getElementById('teacher-clock').textContent = now.toLocaleTimeString('en-GB', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
    document.getElementById('teacher-date').textContent = now.toLocaleDateString('en-GB', {weekday:'long',day:'numeric',month:'long',year:'numeric'});
}
updateTeacherClock();
setInterval(updateTeacherClock, 1000);
</script>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card bg-primary bg-gradient text-white h-100 shadow p-4 d-flex flex-column justify-content-center position-relative">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-white bg-opacity-25 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 64px; height: 64px;">
                    <i class="bi bi-journal-bookmark-fill fs-2"></i>
                </div>
                <div>
                    <h6 class="text-uppercase fw-semibold mb-1 opacity-75 tracking-wide ls-wide" style="font-size: 0.75rem;">My Courses</h6>
                    <h2 class="display-5 fw-bolder mb-0"><?php echo count($myCourses); ?></h2>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card bg-white h-100 shadow border-0 p-4 d-flex flex-column justify-content-center position-relative" style="overflow:visible;">
            <div class="position-absolute top-0 end-0 mt-3 me-3" style="z-index:5;">
                <select id="filter-student-course" class="form-select form-select-sm text-muted fw-bold shadow-sm" style="width:auto; min-width:80px; border:1px solid #e2e8f0; border-radius:50px; font-size: 0.7rem;" onchange="refreshStats()">
                    <option value="">All Courses</option>
                    <?php foreach ($myCourses as $c): ?>
                    <option value="<?php echo htmlspecialchars($c['course_code']); ?>"><?php echo htmlspecialchars($c['course_code']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 64px; height: 64px; background: rgba(255,211,10,0.15);">
                    <i class="bi bi-people-fill fs-2" style="color:#ffd30a;"></i>
                </div>
                <div>
                    <h6 class="text-uppercase fw-semibold mb-1 text-secondary tracking-wide ls-wide" style="font-size: 0.75rem;">Total Students</h6>
                    <h2 class="display-5 fw-bolder mb-0 text-dark" id="stat-students">—</h2>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card h-100 shadow p-4 d-flex flex-column justify-content-center position-relative" style="background:linear-gradient(135deg,#ffd30a,#e6b800); overflow:visible;">
            <div class="position-absolute top-0 end-0 mt-3 me-3" style="z-index:5;">
                <select id="filter-today-course" class="form-select form-select-sm text-dark fw-bold shadow-none" style="width:auto; min-width:80px; border:1px solid #e2e8f0; border-radius:4px; font-size: 0.7rem;" onchange="refreshStats()">
                    <option value="" class="text-dark">All Courses</option>
                    <?php foreach ($myCourses as $c): ?>
                    <option value="<?php echo htmlspecialchars($c['course_code']); ?>" class="text-dark"><?php echo htmlspecialchars($c['course_code']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-white bg-opacity-25 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 64px; height: 64px;">
                    <i class="bi bi-calendar-check-fill fs-2"></i>
                </div>
                <div>
                    <h6 class="text-uppercase fw-semibold mb-1 opacity-75 tracking-wide ls-wide" style="font-size: 0.75rem;">Today's Scans</h6>
                    <h2 class="display-5 fw-bolder mb-0" id="stat-today">—</h2>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="text-center mb-4">
    <div class="start-hero p-4 d-inline-block" style="min-width: 500px; max-width: 700px; width: 100%;">
        <h5 class="fw-bold mb-1"><i class="bi bi-broadcast me-2"></i>Start Lecture Session</h5>
        <p class="text-muted small mb-3" style="font-size:0.78rem;">University of Peradeniya &mdash; Attendance broadcasting to hardware nodes</p>
        <div class="row g-2 align-items-center justify-content-center">
            <div class="col-sm-5">
                <label class="form-label small fw-semibold text-muted mb-1" for="start-course">Course</label>
                <select id="start-course" class="form-select form-select">
                    <option value="">Select course...</option>
                    <?php foreach ($myCourses as $c): ?>
                    <option value="<?php echo htmlspecialchars($c['course_code']); ?>" title="<?php echo htmlspecialchars($c['course_code'] . ' — ' . $c['course_name']); ?>"><?php echo htmlspecialchars($c['course_code']); ?> — <?php echo htmlspecialchars($c['course_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-3">
                <label class="form-label small fw-semibold text-muted mb-1" for="start-device">Device</label>
                <select id="start-device" class="form-select form-select">
                    <?php echo $deviceOptions; ?>
                </select>
            </div>
            <div class="col-sm-2">
                <label class="form-label small fw-semibold text-muted mb-1" for="start-timer">Timer</label>
                <select id="start-timer" class="form-select form-select" onchange="toggleCustomTimer()">
                    <option value="0">No Timer</option>
                    <option value="15">15 min</option>
                    <option value="30" selected>30 min</option>
                    <option value="45">45 min</option>
                    <option value="60">60 min</option>
                    <option value="90">90 min</option>
                    <option value="custom">Custom...</option>
                </select>
                <input type="number" id="start-timer-custom" class="form-control form-control-sm mt-1" placeholder="Minutes" min="1" max="480" style="display:none;">
            </div>
            <div class="col-sm-3 d-flex align-items-end gap-1">
                <button class="btn btn-success fw-bold w-100 py-2 btn-hover-lift btn-hover-shine btn-hover-ripple" id="btn-start" onclick="startLecture()"><i class="bi bi-play-fill me-1"></i>Start</button>
                <button class="btn btn-danger fw-bold py-2 px-3 btn-hover-lift btn-hover-shine btn-hover-ripple" id="btn-end" onclick="endLecture()" style="display:none;"><i class="bi bi-stop-fill"></i></button>
            </div>
        </div>
        <hr class="my-2 opacity-25">
        <div class="d-flex align-items-center justify-content-center gap-4 flex-wrap">
            <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" id="auto-start-check">
                <label class="form-check-label small text-muted" for="auto-start-check">Auto-start on schedule</label>
            </div>
            <div id="start-msg" class="small m-0"></div>
        </div>
    </div>
</div>

<ul class="nav nav-tabs mb-3" id="teacherTabs" role="tablist">
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-live"><i class="bi bi-broadcast me-1 text-success"></i><span class="live-indicator live-active align-middle me-1" style="width:8px;height:8px;vertical-align:middle;"></span><span data-i18n="live_logs">Live</span></button></li>
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-logs"><i class="bi bi-clock-history me-1"></i><span data-i18n="attendance_logs">Attendance Logs</span></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-absent"><i class="bi bi-person-x me-1 text-danger"></i><span data-i18n="absent_students">Absent Students</span></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-manual"><i class="bi bi-pencil-square me-1"></i>Manual Mark</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-camera"><i class="bi bi-camera-video me-1"></i>Camera</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-voice"><i class="bi bi-mic me-1"></i>Voice</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-qr-attendance"><i class="bi bi-qr-code me-1 text-primary"></i>QR Attendance</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-courses"><i class="bi bi-book me-1"></i><span data-i18n="my_courses">My Courses</span></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-enroll"><i class="bi bi-person-plus me-1"></i><span data-i18n="enroll_students">Enroll Students</span></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-attendance"><i class="bi bi-bar-chart me-1"></i><span data-i18n="attendance_percentage">Attendance %</span></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-forum"><i class="bi bi-star me-1"></i><span data-i18n="ratings_forum">Ratings & Forum</span></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-sections"><i class="bi bi-layers me-1"></i><span data-i18n="section_history">Section History</span></button></li>
</ul>

<div class="row g-3 mb-4" id="upcoming-section">
    <div class="col-12">
        <div class="card border-0 shadow-none" style="border: 1px solid #e2e8f0 !important;">
            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                <strong class="small"><i class="bi bi-calendar-event me-1"></i>Today's Scheduled Courses</strong>
                <span class="badge bg-primary" id="upcoming-count">0</span>
            </div>
            <div class="card-body p-0" style="max-height:220px;overflow-y:auto;">
                <div id="upcoming-container" class="px-3 py-2">
                    <div class="text-center text-muted py-3 small"><i class="bi bi-arrow-clockwise spin me-1"></i>Loading...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="tab-content">
    <div class="tab-pane fade" id="tab-live">
        <div class="card border-0 shadow-none">
            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                <strong class="small text-success"><i class="bi bi-broadcast me-1"></i>Live Attendance Feed</strong>
                <div>
                    <span class="badge bg-success me-2" id="live-count">0</span>
                    <button class="btn btn-sm btn-outline-success" onclick="toggleLivePolling()" id="btn-live-toggle"><i class="bi bi-pause-fill me-1"></i>Pause</button>
                </div>
            </div>
            <div class="card-body p-0" style="max-height:600px;overflow-y:auto;">
                <div id="live-container" class="px-3">
                    <div class="text-center text-muted py-5"><i class="bi bi-arrow-clockwise spin fs-4"></i><br>Listening...</div>
                </div>
            </div>
        </div>
    </div>
    <div class="tab-pane fade show active" id="tab-logs">
        <div class="card border-0 shadow-none" style="border: 1px solid #e2e8f0 !important;">
            <div class="card-header bg-white py-2">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <strong class="small"><i class="bi bi-funnel me-1"></i>Filters</strong>
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-success" onclick="downloadCsv()"><i class="bi bi-download me-1"></i>CSV</button>
                        <button class="btn btn-sm btn-outline-primary" onclick="fetchLogs()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
                    </div>
                </div>
            </div>
            <div class="card-body py-2">
                <div class="row g-2">
                    <div class="col-md-4"><input type="text" id="log-search" class="form-control form-control-sm" placeholder="Search student..." oninput="fetchLogs()"></div>
                    <div class="col-md-3"><select id="log-course" class="form-select form-select-sm" onchange="fetchLogs()"><option value="">All Courses</option><?php foreach ($myCourses as $c): ?><option value="<?php echo htmlspecialchars($c['course_code']); ?>"><?php echo htmlspecialchars($c['course_code']); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-2"><input type="date" id="log-date" class="form-control form-control-sm" onchange="fetchLogs()"></div>
                </div>
            </div>
            <div class="card-body p-0" style="max-height:500px;overflow-y:auto;">
                <div id="log-container" class="px-3">
                    <div class="text-center text-muted py-5"><i class="bi bi-arrow-clockwise spin fs-4"></i><br>Loading...</div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-absent">
        <div class="card border-0 shadow-none" style="border: 1px solid #e2e8f0 !important;">
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-5">
                        <select id="absent-course" class="form-select form-select-sm">
                            <option value="">Select course...</option>
                            <?php foreach ($myCourses as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['course_code']); ?>"><?php echo htmlspecialchars($c['course_code']); ?> — <?php echo htmlspecialchars($c['course_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="date" id="absent-date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-sm btn-danger w-100" onclick="checkAbsent()"><i class="bi bi-search me-1"></i>Check</button>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-sm btn-outline-dark w-100" onclick="emailAbsent()" id="btn-email-absent" disabled><i class="bi bi-envelope me-1"></i>Email</button>
                    </div>
                </div>
                <div id="absent-container">
                    <div class="text-center text-muted py-4 small">Select a course and date to check absences.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-courses">
        <div id="courses-container">
            <div class="text-center text-muted py-5"><i class="bi bi-arrow-clockwise spin fs-4"></i><br>Loading...</div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-attendance">
        <div class="card border-0 shadow-none" style="border: 1px solid #e2e8f0 !important;">
            <div class="card-header bg-white py-2">
                <strong class="small"><i class="bi bi-bar-chart me-1"></i>Student Attendance by Course</strong>
            </div>
            <div class="card-body p-0" style="max-height:500px;overflow-y:auto;">
                <div id="attendance-container">
                    <div class="text-center text-muted py-5"><i class="bi bi-arrow-clockwise spin fs-4"></i><br>Loading...</div>
                </div>
            </div>
        </div>
        <hr class="my-3">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card border-0 shadow-none" style="border:1px solid #e2e8f0!important;">
                    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                        <strong class="small"><i class="bi bi-graph-up me-1"></i> <span data-i18n="course_trend">Course Trend</span></strong>
                        <button class="btn btn-sm btn-outline-maroon fw-bold" onclick="printChart('teacherTrendChart','Course Trend - '+(document.getElementById('teacher-trend-course')?.value||''))" title="Print chart"><i class="bi bi-printer"></i></button>
                    </div>
                    <div class="card-body">
                        <select id="teacher-trend-course" class="form-select form-select-sm mb-2" onchange="loadTeacherCourseTrend()">
                            <option value="" data-i18n="select_course">Select course...</option>
                            <?php foreach ($myCourses as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['course_code']); ?>"><?php echo htmlspecialchars($c['course_code']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div id="teacher-trend-title" class="small text-muted text-center mb-1" style="font-weight:600;"></div>
                        <canvas id="teacherTrendChart" height="180"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-none" style="border:1px solid #e2e8f0!important;">
                    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                        <strong class="small"><i class="bi bi-person me-1"></i> <span data-i18n="student_trend">Student Trend</span></strong>
                        <button class="btn btn-sm btn-outline-maroon fw-bold" onclick="printChart('teacherStudentChart','Student - '+(document.getElementById('teacher-student-input')?.value||''))" title="Print chart"><i class="bi bi-printer"></i></button>
                    </div>
                    <div class="card-body">
                        <div class="searchable-select-wrapper position-relative mb-2">
                            <input type="text" id="teacher-student-search" class="form-control form-control-sm" placeholder="Search student..." autocomplete="off"
                                   oninput="filterDropdown(this,'teacher-student-dropdown','teacher-student-input')"
                                   onfocus="showDropdown('teacher-student-dropdown')">
                            <input type="hidden" id="teacher-student-input" value="">
                            <div id="teacher-student-dropdown" class="searchable-dropdown d-none" style="max-height:200px; overflow-y:auto; z-index: 1000;">
                                <?php foreach ($allStudents as $s): ?>
                                    <div class="searchable-dropdown-item" data-value="<?php echo htmlspecialchars($s['student_no']); ?>" onclick="selectDropdownItem(this, 'teacher-student-search', 'teacher-student-input', 'teacher-student-dropdown')">
                                        <?php echo htmlspecialchars($s['student_no'] . ' - ' . $s['student_name']); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button class="btn btn-sm btn-outline-maroon w-100 fw-bold mb-2" onclick="loadTeacherStudentChart()"><i class="bi bi-search me-1"></i>Load</button>
                        <div id="teacher-student-name" class="small text-muted text-center mb-1" style="font-weight:600;"></div>
                        <canvas id="teacherStudentChart" height="180"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-enroll">
        <div class="card border-0 shadow-none" style="border: 1px solid #e2e8f0 !important;">
            <div class="card-header bg-white py-2">
                <strong class="small"><i class="bi bi-person-plus me-1"></i>Enroll Students in Your Courses</strong>
            </div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <select id="enroll-course" class="form-select form-select-sm">
                            <option value="">Select course...</option>
                            <?php foreach ($myCourses as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['course_code']); ?>"><?php echo htmlspecialchars($c['course_code']); ?> — <?php echo htmlspecialchars($c['course_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <input type="text" id="enroll-search" class="form-control form-control-sm" placeholder="Search by student no or name..." oninput="searchEnrollStudents()">
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-sm btn-success w-100" onclick="searchEnrollStudents()"><i class="bi bi-search me-1"></i>Search</button>
                    </div>
                </div>
                <div id="enroll-search-results" class="mb-3" style="max-height:300px;overflow-y:auto;">
                    <div class="text-center text-muted py-3 small">Select a course and search for students to enroll.</div>
                </div>
                <hr>
                <h6 class="small fw-bold"><i class="bi bi-people me-1"></i>Currently Enrolled</h6>
                <div id="enrolled-list" style="max-height:250px;overflow-y:auto;">
                    <div class="text-center text-muted py-3 small">Select a course to view enrolled students.</div>
                </div>
            </div>
        </div>
    </div>
    <div class="tab-pane fade" id="tab-forum">
        <div class="row g-3">
            <div class="col-md-5">
                <div class="card border-0 shadow-none h-100" style="border: 1px solid #e2e8f0 !important;">
                    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                        <strong class="small"><i class="bi bi-star me-1"></i>Course Ratings</strong>
                    </div>
                    <div class="card-body" style="max-height:400px;overflow-y:auto;" id="teacher-ratings-list">
                        <div class="text-center text-muted py-3 small"><i class="bi bi-arrow-clockwise spin me-1"></i>Loading...</div>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card border-0 shadow-none h-100" style="border: 1px solid #e2e8f0 !important;">
                    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                        <strong class="small"><i class="bi bi-chat-dots me-1"></i>Course Discussion Forum</strong>
                        <div id="lang-switcher-teacher-forum"></div>
                    </div>
                    <div class="card-body p-0">
                        <div class="p-3 border-bottom">
                            <select id="teacher-forum-course" class="form-select form-select-sm" onchange="switchTeacherForumCourse()">
                                <option value="">Select a course...</option>
                                <?php foreach ($myCourses as $c): ?>
                                <option value="<?php echo htmlspecialchars($c['course_code']); ?>"><?php echo htmlspecialchars($c['course_code']); ?> — <?php echo htmlspecialchars($c['course_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="px-3 py-2 border-bottom">
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" id="teacher-comment-text" placeholder="Write a comment..." onkeydown="if(event.key==='Enter')submitTeacherComment()">
                                <button class="btn btn-primary btn-sm" onclick="submitTeacherComment()"><i class="bi bi-send"></i></button>
                            </div>
                        </div>
                        <div id="teacher-comments-container" style="max-height:350px;overflow-y:auto;">
                            <div class="text-center text-muted py-4 small">Select a course to view discussion.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="tab-pane fade" id="tab-sections">
        <div class="card border-0 shadow-none" style="border:1px solid #e2e8f0 !important;border-radius:10px;">
            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center" style="border-radius:10px 10px 0 0;">
                <strong class="small"><i class="bi bi-layers me-1"></i>Section History</strong>
                <button class="btn btn-sm btn-outline-danger" onclick="clearSectionHistory()"><i class="bi bi-trash me-1"></i>Clear All</button>
            </div>
            <div class="card-body p-0">
                <div id="section-history-container" style="max-height:500px;overflow-y:auto;">
                    <div class="text-center text-muted py-5 small"><em>No completed sections yet. Sections are saved when you end a lecture.</em></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── Manual Attendance Tab ───────────────────────────────── -->
    <div class="tab-pane fade" id="tab-manual">
        <div class="row">
            <div class="col-lg-6">
                <?php require_once 'includes/cards/admin_manual_attendance.php'; ?>
            </div>
        </div>
    </div>

    <!-- ─── Camera Tab ──────────────────────────────────────── -->
    <div class="tab-pane fade" id="tab-camera">
        <div class="row g-3">
            <div class="col-md-6">
                <?php require_once 'includes/cards/admin_webcam_attendance.php'; ?>
            </div>
            <div class="col-md-6">
                <?php require_once 'includes/cards/admin_rpi_camera.php'; ?>
            </div>
        </div>
    </div>

    <!-- ─── Voice Attendance Tab ─────────────────────────────── -->
    <div class="tab-pane fade" id="tab-voice">
        <div class="row g-3">
            <div class="col-md-7">
                <div class="card border-0 mb-3 overflow-hidden" style="border-radius:12px;box-shadow:0 4px 16px rgba(111,66,193,0.08);">
                    <div class="card-header py-2 px-3 d-flex align-items-center gap-2"
                         style="background:linear-gradient(135deg,#6f42c1,#5530a0);border-bottom:none;">
                        <i class="bi bi-mic-fill" style="color:#fff;"></i>
                        <strong class="small fw-bold" style="color:#fff;">Voice Attendance</strong>
                    </div>
                    <div class="card-body py-2 px-3 bg-white">
                        <button id="btn-start-voice-scanner"
                                class="btn btn-sm w-100 fw-bold mb-2"
                                onclick="startVoiceScanner()"
                                style="background:linear-gradient(135deg,#6f42c1,#5530a0);color:#fff;border:none;border-radius:8px;letter-spacing:0.3px;box-shadow:0 2px 8px rgba(111,66,193,0.3);">
                            <i class="bi bi-play-circle me-1"></i>Start Voice Scanner
                        </button>

                        <div id="voice-scanner-container" class="d-none">
                            <select id="voice-course" class="form-select form-select-sm mb-2" onchange="loadVoiceStudents()">
                                <option value="">Select course...</option>
                                <?php foreach ($myCourses as $c): ?>
                                <option value="<?php echo htmlspecialchars($c['course_code']); ?>"><?php echo htmlspecialchars($c['course_code']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="searchable-select-wrapper position-relative mb-2">
                                <input type="text" id="voice-student-search" class="form-control form-control-sm" placeholder="Search student in course..." autocomplete="off"
                                       oninput="filterDropdown(this,'voice-student-dropdown','voice-student')"
                                       onfocus="showDropdown('voice-student-dropdown')">
                                <input type="hidden" id="voice-student" value="">
                                <div id="voice-student-dropdown" class="searchable-dropdown d-none" style="max-height: 200px; overflow-y: auto; width: 100%;">
                                    <div class="text-center text-muted py-2 small">Select course first</div>
                                </div>
                            </div>
                            <div class="mb-2">
                                <canvas id="voice-visualizer" height="60" class="w-100 rounded" style="background:#1e293b;display:none;"></canvas>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div id="voice-status" class="small fw-bold text-muted">Select a course and student, then click record.</div>
                                <span id="voice-count-badge" class="badge d-none" style="background:#6f42c1;color:#fff;">Samples: 0</span>
                            </div>
                            <div class="d-flex gap-3 mb-2 px-1">
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="radio" name="voiceMode" id="voiceModeEnroll" value="enroll" checked>
                                    <label class="form-check-label fw-bold small" for="voiceModeEnroll" style="color:#6f42c1;"><i class="bi bi-mic me-1"></i>Enroll</label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="radio" name="voiceMode" id="voiceModeAttend" value="attend">
                                    <label class="form-check-label fw-bold small" for="voiceModeAttend" style="color:#198754;"><i class="bi bi-person-check-fill me-1"></i>Attendance</label>
                                </div>
                            </div>
                            <div class="mb-2">
                                <select id="voice-teacher-mic-select" class="form-select form-select-sm" style="font-size:0.75rem;">
                                    <option value="">Default Microphone</option>
                                </select>
                            </div>
                            <div class="d-flex gap-2 mb-2">
                                <button id="btn-record-voice" class="btn btn-sm w-100 fw-bold" onclick="toggleVoiceRecord()"
                                        style="background:linear-gradient(135deg,#6f42c1,#5530a0);color:#fff;border:none;border-radius:8px;">
                                    <i class="bi bi-mic me-1"></i><span id="voice-record-label">Start Recording</span>
                                </button>
                                <button id="btn-stop-voice" class="btn btn-sm w-100 fw-bold d-none"
                                        onclick="stopVoiceRecording()"
                                        style="background:linear-gradient(135deg,#dc3545,#b02a37);color:#fff;border:none;border-radius:8px;">
                                    <i class="bi bi-stop-circle me-1"></i>Stop Recording
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-5">
                <div class="card border-0 shadow-none" style="border:1px solid #e2e8f0 !important;border-radius:10px;">
                    <div class="card-header bg-white py-2">
                        <strong class="small"><i class="bi bi-clock-history me-1"></i>Recent Voice Attendance</strong>
                    </div>
                    <div class="card-body p-0">
                        <div id="voice-recent-list" class="px-3 py-2">
                            <div class="text-center text-muted py-3 small">No voice attendance records yet.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- ─── QR Attendance Tab ───────────────────────────────── -->
    <div class="tab-pane fade" id="tab-qr-attendance">
        <div class="row g-3">
            <div class="col-md-5">
                <div class="card border-0 shadow-none h-100" style="border:1px solid #e2e8f0 !important;border-radius:12px; background: #fff;">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-gear-fill me-1 text-primary"></i>QR Code Session Controls</h6>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted mb-4">Start a rolling QR code attendance session. The QR code automatically morphs every 5 seconds. Students must scan two consecutive QRs to confirm their presence.</p>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted" for="qr-session-course">Select Course</label>
                            <select id="qr-session-course" class="form-select">
                                <option value="">Select course...</option>
                                <?php foreach ($myCourses as $c): ?>
                                <option value="<?php echo htmlspecialchars($c['course_code']); ?>"><?php echo htmlspecialchars($c['course_code']); ?> — <?php echo htmlspecialchars($c['course_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="d-flex gap-2 mb-4">
                            <button class="btn btn-primary btn-sm w-100 py-2 fw-semibold" id="btn-qr-start" onclick="startQrSession()">
                                <i class="bi bi-play-circle-fill me-1"></i>Start QR Session
                            </button>
                            <button class="btn btn-danger btn-sm w-100 py-2 fw-semibold" id="btn-qr-stop" onclick="stopQrSession()" disabled>
                                <i class="bi bi-stop-circle-fill me-1"></i>Stop Session
                            </button>
                        </div>
                        
                        <hr class="my-4 opacity-25">
                        
                        <div id="qr-session-stats" class="d-none">
                            <h6 class="small fw-bold text-muted mb-3"><i class="bi bi-bar-chart-fill me-1 text-success"></i>Live Attendance Count</h6>
                            <div class="row text-center g-2">
                                <div class="col-6">
                                    <div class="p-3 bg-light rounded-3 border">
                                        <h3 class="fw-bold text-success mb-0" id="qr-stat-completed">0</h3>
                                        <small class="text-muted small">Completed Scans</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 bg-light rounded-3 border">
                                        <h3 class="fw-bold text-warning mb-0" id="qr-stat-pending">0</h3>
                                        <small class="text-muted small">First Scan Recorded</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-7">
                <div class="card border-0 shadow-none h-100" style="border:1px solid #e2e8f0 !important;border-radius:12px; background: #fff;">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-qr-code me-1 text-primary"></i>Rotating QR Code Display</h6>
                        <button class="btn btn-dark btn-sm rounded-pill px-3" id="btn-qr-projector" onclick="openQrProjector()" disabled>
                            <i class="bi bi-fullscreen me-1"></i>Projector Mode
                        </button>
                    </div>
                    <div class="card-body d-flex flex-column align-items-center justify-content-center py-5 position-relative">
                        <!-- Canvas overlay for start state -->
                        <div id="qr-canvas-placeholder" class="text-center text-muted py-5">
                            <i class="bi bi-qr-code fs-1 d-block mb-3 opacity-50"></i>
                            <p class="mb-0 fw-semibold">Session Inactive</p>
                            <small class="text-muted">Select a course and click "Start QR Session" to display the rolling QR code.</small>
                        </div>
                        
                        <div id="qr-canvas-container" class="d-none text-center">
                            <div class="position-relative d-inline-block">
                                <canvas id="qr-code-canvas" width="340" height="340" class="rounded shadow-sm border p-2" style="background:#fff;"></canvas>
                            </div>
                            
                            <div class="d-flex justify-content-center align-items-center gap-3 mt-2">
                                <span class="badge bg-primary rounded-pill small" id="qr-sequence-badge">#0</span>
                                <span class="fw-bold font-monospace small text-muted" id="qr-countdown-text">5.0s</span>
                            </div>
                            
                            <p class="mt-2 small text-muted"><i class="bi bi-info-circle me-1"></i>Scanning requirement: <strong>2 consecutive</strong> rotations shown on screen</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h6 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Profile</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <div class="avatar-ring mx-auto" onclick="document.getElementById('edit-photo-upload').click()">
                        <?php echo $editAvatarHtml; ?>
                        <div class="cam-overlay"><i class="bi bi-camera-fill"></i></div>
                    </div>
                    <input type="file" id="edit-photo-upload" class="d-none" accept="image/*" onchange="previewEditPhoto(this)">
                    <div class="small text-muted mt-1">Click avatar to change photo</div>
                </div>
                <div class="mb-2"><label class="form-label small fw-bold" for="edit-name">Full Name</label><input type="text" id="edit-name" class="form-control form-control-sm" value="<?php echo htmlspecialchars($teacherName); ?>"></div>
                <div class="mb-2"><label class="form-label small fw-bold" for="edit-email">Email (Login ID)</label><input type="email" id="edit-email" class="form-control form-control-sm" value="<?php echo htmlspecialchars($teacherEmail); ?>"></div>
                <div class="mb-2"><label class="form-label small fw-bold" for="edit-phone">Phone</label><input type="text" id="edit-phone" class="form-control form-control-sm" value="<?php echo htmlspecialchars($phone); ?>"></div>
                <hr>
                <div class="mb-2"><label class="form-label small fw-bold" for="edit-cur-pw">Current Password</label><input type="password" id="edit-cur-pw" class="form-control form-control-sm" placeholder="Required to change password"></div>
                <div class="mb-2"><label class="form-label small fw-bold" for="edit-new-pw">New Password</label><input type="password" id="edit-new-pw" class="form-control form-control-sm" placeholder="Leave blank to keep"></div>
                <div id="edit-msg" class="small"></div>
            </div>
            <div class="modal-footer py-2">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary btn-sm fw-semibold" onclick="saveProfile()"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
const MY_COURSES = <?php echo $courseData; ?>;
const DEVICES = <?php echo $devicesJson; ?>;

function setAvatar(src) {
    ['profile-img','edit-photo-preview'].forEach(function(id) {
        var el = document.getElementById(id);
        if (!el) return;
        if (el.tagName === 'IMG') { el.src = src; }
        else {
            var img = document.createElement('img');
            img.src = src; img.alt = ''; img.style.width = '100%'; img.style.height = '100%';
            img.style.borderRadius = '50%'; img.style.border = '2px solid #fff'; img.style.objectFit = 'cover';
            el.replaceWith(img); img.id = id;
        }
    });
}

function refreshStats(){
    var sc = document.getElementById('filter-student-course').value;
    var tc = document.getElementById('filter-today-course').value;

    if (sc) {
        fetch('/csc2052/api/teacher.php?action=get_course_students&course_code='+encodeURIComponent(sc))
            .then(function(r){return r.json();})
            .then(function(d){
                document.getElementById('stat-students').textContent = (d.students ? d.students.length : 0);
            })
            .catch(function(){ document.getElementById('stat-students').textContent = '—'; });
    } else {
        var total = 0;
        MY_COURSES.forEach(function(c){ total += (c.enrolled || 0); });
        document.getElementById('stat-students').textContent = total;
    }

    if (tc) {
        fetch('/csc2052/api/teacher.php?action=get_today_attendance&course_code='+encodeURIComponent(tc))
            .then(function(r){return r.json();})
            .then(function(d){
                document.getElementById('stat-today').textContent = (d.present ? d.present.length : 0);
            })
            .catch(function(){ document.getElementById('stat-today').textContent = '—'; });
    } else {
        fetch('/csc2052/api/teacher.php?action=today_count')
            .then(function(r){return r.json();})
            .then(function(d){
                document.getElementById('stat-today').textContent = (d.count || 0);
            })
            .catch(function(){ document.getElementById('stat-today').textContent = '—'; });
    }
}

function toggleCustomTimer(){
    var sel = document.getElementById('start-timer');
    var custom = document.getElementById('start-timer-custom');
    custom.style.display = sel.value === 'custom' ? '' : 'none';
}

function fetchLogs(){
    var search = document.getElementById('log-search').value;
    var course = document.getElementById('log-course').value;
    var date = document.getElementById('log-date').value;
    var url = '/csc2052/api/teacher.php?action=teacher_logs';
    if (course) url += '&course=' + encodeURIComponent(course);
    // Only add date filter if explicitly set
    if (date) url += '&date=' + encodeURIComponent(date);
    if (search) url += '&search=' + encodeURIComponent(search);
    console.log('Fetching logs from:', url);
    fetch(url)
        .then(function(r){ 
            console.log('Response status:', r.status);
            return r.json(); 
        })
        .then(function(data) {
            console.log('Response data:', data);
            var el = document.getElementById('log-container');
            if (!data.logs || data.logs.length === 0){
                el.innerHTML = '<div class="text-center text-muted py-5">';
                if (date || course || search) {
                    el.innerHTML += '<i class="bi bi-inbox fs-1 d-block mb-2"></i>' +
                                    '<p class="mb-0 small">No records for selected filters.</p>' +
                                    '<p class="small text-muted mt-2">Try adjusting the date, course, or search filters.</p>';
                } else {
                    el.innerHTML += '<p class="mb-0 small">No attendance records found.</p>';
                }
                el.innerHTML += '</div>';
                return;
            }
            // Use table style matching student dashboard
            var h = '<table class="table table-hover table-sm mb-0 align-middle"><thead class="table-light"><tr>' +
                        '<th>Student</th><th>Course</th><th>Time</th><th>Method</th></tr></thead><tbody>';
            data.logs.forEach(function(log) {
                var t = new Date(log.timestamp.replace(' ','T'));
                var d = t.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
                var time = t.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
                var badge = (log.modality==='fingerprint')?'bg-success':(log.modality==='rfid')?'bg-info':(log.modality==='face')?'bg-warning text-dark':'bg-secondary';
                h += '<tr><td><strong class="small" data-att-tooltip="' + esc(log.student_no) + '">' + esc(log.student_no) + '</strong> <span class="text-muted small">' + esc(log.student_name||'') + '</span></td>' +
                        '<td><span class="badge bg-light">' + esc(log.course_code||'') + '</span> <span class="text-muted small">' + esc(log.device_name || log.device_id || 'Web') + '</span></td>' +
                        '<td class="small">' + d + '</td>' +
                        '<td><span class="badge ' + badge + ' badge-pill">' + esc(log.modality||'manual') + '</span></td>' +
                    '</tr>';
            });
            h += '</tbody></table>';
            el.innerHTML = h;
        })
        .catch(function(err){ 
            console.error('Fetch error:', err);
            document.getElementById('log-container').innerHTML = '<div class="text-center text-danger py-4 small"><i class="bi bi-exclamation-triangle me-1"></i>Failed to load logs: ' + err + '</div>'; 
        });
}

function checkAbsent(){
    var course = document.getElementById('absent-course').value;
    var date = document.getElementById('absent-date').value;
    if (!course) return;
    fetch('/csc2052/api/teacher.php?action=absent_students&course_code='+encodeURIComponent(course)+'&date='+encodeURIComponent(date))
        .then(function(r){return r.json();})
        .then(function(data){
            var el = document.getElementById('absent-container');
            var btn = document.getElementById('btn-email-absent');
            if (!data.students || data.students.length===0){
                el.innerHTML = '<div class="text-center text-success py-3"><i class="bi bi-check-circle me-1"></i><strong>All students present!</strong></div>';
                btn.disabled = true;
            } else {
                var h = '<table class="table table-sm mb-0 small"><thead class="table-light"><tr><th>Student No</th><th>Name</th><th>Status</th></tr></thead><tbody>';
                data.students.forEach(function(s){ h += '<tr><td><span data-att-tooltip="'+esc(s.student_no)+'">'+esc(s.student_no)+'</span></td><td>'+esc(s.student_name||'')+'</td><td><span class="badge bg-danger badge-pill">Absent</span></td></tr>'; });
                h += '</tbody></table>';
                el.innerHTML = h;
                btn.disabled = false;
            }
        });
}

function emailAbsent(){
    var course = document.getElementById('absent-course').value;
    var date = document.getElementById('absent-date').value;
    if (!course) return;
    Swal.fire({
        title: 'Send Absentee Report?',
        text: 'This will email all absent students for ' + course + '.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3b82f6',
        confirmButtonText: 'Yes, Send Emails'
    }).then((result) => {
        if (!result.isConfirmed) return;
        var fd = new FormData(); fd.append('action','send_absent_email'); fd.append('course_code',course); fd.append('date',date);
        fetch('/csc2052/api/teacher.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(data){
            if (data.status==='success'){ alert('Sent to '+data.sent+' students.'); checkAbsent(); }
            else alert('Failed: '+(data.message||''));
        });
    });
}

function startLecture(){
    var course = document.getElementById('start-course').value;
    var device = document.getElementById('start-device').value;
    var timerSel = document.getElementById('start-timer').value;
    var timer = timerSel === 'custom' ? document.getElementById('start-timer-custom').value : timerSel;
    var autoStart = document.getElementById('auto-start-check').checked;
    if (!course){ alert('Select a course.'); return; }
    if(timerSel === 'custom' && (!timer || timer < 1)){ alert('Enter a valid custom time (min 1).'); return; }
    var msg = document.getElementById('start-msg');
    msg.innerHTML = '<span class="text-primary"><i class="bi bi-arrow-clockwise spin me-1"></i>Starting...</span>';

    var fd = new FormData();
    fd.append('action','start_course_session');
    fd.append('course_code',course);
    fd.append('device',device);
    if(timer) fd.append('timer_minutes',timer);
    if(autoStart) fd.append('auto_start','1');

    fetch('/csc2052/api/teacher.php',{method:'POST', headers: {'X-CSRF-TOKEN': getCsrf()}, body: fd })
        .then(function(r){return r.json();})
        .then(function(data){
            if(data.status==='success'){
                var timerLabel = timerSel === 'custom' ? timer+' min' : (timer == 0 ? 'no timer' : timer+' min');
                msg.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle me-1"></i>Started '+esc(course)+' on '+esc(device)+' ('+timerLabel+')</span>';
                document.getElementById('btn-start').style.display = 'none';
                document.getElementById('btn-end').style.display = '';
                if(autoStart) msg.innerHTML += ' <span class="text-info">(Auto-start enabled)</span>';
                if (typeof startLectureGlow === 'function') startLectureGlow();
                // Set activeWebCourse for camera
                window.activeWebCourse = course;
                window.activeWebDevice = device;
                localStorage.setItem('active_lecture_course', course);
                localStorage.setItem('active_lecture_device', device);
                // Auto-redirect to Camera tab and start webcam when Web Dashboard is selected
                if (device === 'WEB_DASHBOARD' || device === 'Web Dashboard') {
                    var camTab = document.querySelector('[data-bs-target="#tab-camera"]');
                    if (camTab) {
                        camTab.click();
                        setTimeout(function() {
                            if (typeof startWebFaceAttendance === 'function') {
                                startWebFaceAttendance();
                            }
                        }, 500);
                    }
                }
            } else msg.innerHTML = '<span class="text-danger">'+esc(data.message||'Failed')+'</span>';
        })
        .catch(function(){ msg.innerHTML = '<span class="text-danger">Network error.</span>'; });
}

function endLecture(){
    var course = document.getElementById('start-course').value;
    if (!course){ alert('Select the course to end.'); return; }
    Swal.fire({
        title: 'End Lecture?',
        text: 'Are you sure you want to end the lecture for ' + course + '?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'End Lecture'
    }).then((result) => {
        if (!result.isConfirmed) return;
        var msg = document.getElementById('start-msg');
        msg.innerHTML = '<span class="text-primary"><i class="bi bi-arrow-clockwise spin me-1"></i>Ending...</span>';
        var fd = new FormData(); fd.append('action','end_course_session'); fd.append('course_code',course);
        fetch('/csc2052/api/teacher.php',{method:'POST', headers: {'X-CSRF-TOKEN': getCsrf()}, body: fd })
            .then(function(r){return r.json();})
            .then(function(data){
                if(data.status==='success'){
                    msg.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle me-1"></i>Ended '+esc(course)+'</span>';
                    document.getElementById('btn-start').style.display = '';
                    document.getElementById('btn-end').style.display = 'none';
                    if (typeof stopLectureGlow === 'function') stopLectureGlow();
                } else msg.innerHTML = '<span class="text-danger">'+esc(data.message||'Failed')+'</span>';
            })
            .catch(function(){ msg.innerHTML = '<span class="text-danger">Network error.</span>'; });
    });
}

function loadCourses(){
    var el = document.getElementById('courses-container');
    var dayNames = ['','Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
    var formatTime = function(t){ if(!t) return ''; t = String(t); if(t.length>=5) return t.substring(0,5); return t; };
    fetch('/csc2052/api/teacher.php?action=get_my_courses')
        .then(function(r){return r.json();})
        .then(function(data){
            if(!data.courses||data.courses.length===0){
                el.innerHTML='<div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5"><i class="bi bi-journal-x fs-1 d-block mb-2"></i><p class="mb-0">No courses assigned yet.</p></div></div>';
                return;
            }
            var h='<div class="row g-3">';
            data.courses.forEach(function(c){
                var schedInfo = '';
                if(Array.isArray(c.schedules) && c.schedules.length > 0){
                    c.schedules.forEach(function(s){
                        var day = dayNames[parseInt(s.day_of_week)||0] || s.day_of_week;
                        var st = formatTime(s.start_time);
                        var et = formatTime(s.end_time);
                        var venue = s.venue ? ' @ '+esc(s.venue) : '';
                        schedInfo += '<span class="badge bg-light text-dark badge-pill me-1 mb-1">'+esc(day)+' '+st+'–'+et+venue+'</span>';
                    });
                }
                h+='<div class="col-md-6 col-lg-4"><div class="course-item p-3 h-100">'+
                    '<h6 class="fw-bold text-primary mb-0">'+esc(c.course_code)+'</h6>'+
                    '<div class="small text-muted">'+esc(c.course_name||'')+'</div>'+
                    (c.enrolled !== undefined && c.enrolled !== null ? '<div class="small mt-1 text-muted"><i class="bi bi-people me-1"></i>'+c.enrolled+' students</div>' : '')+
                    (schedInfo ? '<div class="mt-2">'+schedInfo+'</div>' : '')+
                '</div></div>';
            });
            h+='</div>';
            el.innerHTML=h;
        })
        .catch(function(){ el.innerHTML='<div class="text-center text-danger py-4 small"><i class="bi bi-exclamation-triangle me-1"></i>Failed to load.</div>'; });
}

function loadUpcoming(){
    var container = document.getElementById('upcoming-container');
    var dayIdx = new Date().getDay();
    var dayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    var todayName = dayNames[dayIdx];
    var dayNumMap = {'Sun':0,'Mon':1,'Tue':2,'Wed':3,'Thu':4,'Fri':5,'Sat':6};
    var targetDay = dayNumMap[todayName];

    fetch('/csc2052/api/teacher.php?action=get_my_courses')
        .then(function(r){return r.json();})
        .then(function(data){
            if(!data.courses||data.courses.length===0){
                container.innerHTML = '<div class="text-center text-muted py-2 small">No courses assigned.</div>';
                document.getElementById('upcoming-count').textContent = '0';
                return;
            }
            var upcoming = [];
            var now = new Date();
            var nowMin = now.getHours()*60+now.getMinutes();
            data.courses.forEach(function(c){
                if(Array.isArray(c.schedules)){
                    c.schedules.forEach(function(s){
                        if(parseInt(s.day_of_week) === targetDay){
                            var st = String(s.start_time||'');
                            var parts = st.split(':');
                            var startMin = parseInt(parts[0])*60 + parseInt(parts[1]||0);
                            var isPast = startMin < nowMin;
                            upcoming.push({
                                code: c.course_code,
                                name: c.course_name||'',
                                day: s.day_of_week,
                                start: st.substring(0,5),
                                end: (s.end_time||'').substring(0,5),
                                venue: s.venue||'',
                                isPast: isPast
                            });
                        }
                    });
                }
            });
            upcoming.sort(function(a,b){ return a.start.localeCompare(b.start); });

            if(upcoming.length === 0){
                container.innerHTML = '<div class="text-center text-muted py-2 small">No classes scheduled for today.</div>';
                document.getElementById('upcoming-count').textContent = '0';
                return;
            }

            document.getElementById('upcoming-count').textContent = upcoming.length;
            var h = '';
            upcoming.forEach(function(u){
                var opacity = u.isPast ? 'opacity-50' : '';
                var badge = u.isPast ? 'bg-secondary' : 'bg-success';
                var label = u.isPast ? 'Done' : 'Upcoming';
                h += '<div class="d-flex align-items-center gap-2 py-1 '+opacity+'">'+
                    '<span class="badge '+badge+' badge-pill">'+label+'</span>'+
                    '<span class="fw-bold small">'+esc(u.code)+'</span>'+
                    '<span class="text-muted small">'+esc(u.name)+'</span>'+
                    '<span class="text-muted small ms-auto">'+esc(u.start)+'–'+esc(u.end)+(u.venue?' @ '+esc(u.venue):'')+'</span>'+
                '</div>';
            });
            container.innerHTML = h;
        })
        .catch(function(){ container.innerHTML = '<div class="text-center text-danger py-2 small">Failed to load.</div>'; });
}

function searchEnrollStudents(){
    var course = document.getElementById('enroll-course').value;
    var query = document.getElementById('enroll-search').value.trim();
    if(!course){ alert('Select a course first.'); return; }
    var container = document.getElementById('enroll-search-results');
    container.innerHTML = '<div class="text-center py-2"><i class="bi bi-arrow-clockwise spin"></i></div>';

    var url = '/csc2052/api/student.php?action=get_all_students&course_code='+encodeURIComponent(course);
    if(query) url += '&search='+encodeURIComponent(query);

    fetch(url)
        .then(function(r){return r.json();})
        .then(function(data){
            var students = data.students || [];
            if(students.length === 0){
                container.innerHTML = '<div class="text-center text-muted py-2 small">No students found.</div>';
                return;
            }
            var h = '<table class="table table-sm mb-0 small"><thead class="table-light"><tr><th>Student No</th><th>Name</th><th>Action</th></tr></thead><tbody>';
            students.forEach(function(s){
                h += '<tr><td>'+esc(s.student_no)+'</td><td>'+esc(s.student_name||'')+'</td>'+
                    '<td><button class="btn btn-sm btn-outline-success py-0 px-2" onclick="enrollStudent(\''+esc(s.student_no)+'\',\''+esc(course)+'\')">Enroll</button></td></tr>';
            });
            h += '</tbody></table>';
            container.innerHTML = h;
        })
        .catch(function(){ container.innerHTML = '<div class="text-center text-danger py-2 small">Failed to search.</div>'; });
    loadEnrolledList(course);
}

function enrollStudent(studentNo, course){
    Swal.fire({
        title: 'Enroll Student?',
        text: 'Enroll ' + studentNo + ' in ' + course + '?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        confirmButtonText: 'Enroll'
    }).then((result) => {
        if (!result.isConfirmed) return;
        var fd = new FormData(); fd.append('action','enroll_student_course'); fd.append('student_no',studentNo); fd.append('course_code',course);
        fetch('/csc2052/api/student.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(data){
            if(data.status==='success'){ alert('Enrolled successfully.'); searchEnrollStudents(); }
            else alert('Failed: '+(data.message||''));
        });
    });
}

function loadEnrolledList(course){
    if(!course) course = document.getElementById('enroll-course').value;
    if(!course){ document.getElementById('enrolled-list').innerHTML = '<div class="text-center text-muted py-2 small">Select a course.</div>'; return; }
    var container = document.getElementById('enrolled-list');
    container.innerHTML = '<div class="text-center py-2"><i class="bi bi-arrow-clockwise spin"></i></div>';

    fetch('/csc2052/api/teacher.php?action=get_course_students&course_code='+encodeURIComponent(course))
        .then(function(r){return r.json();})
        .then(function(data){
            var students = data.students || [];
            if(students.length === 0){
                container.innerHTML = '<div class="text-center text-muted py-2 small">No students enrolled yet.</div>';
                return;
            }
            var h = '<table class="table table-sm mb-0 small"><thead class="table-light"><tr><th>Student No</th><th>Name</th><th>Action</th></tr></thead><tbody>';
            students.forEach(function(s){
                h += '<tr><td>'+esc(s.student_no)+'</td><td>'+esc(s.student_name||'')+'</td>'+
                    '<td><button class="btn btn-sm btn-outline-danger py-0 px-2" onclick="unEnrollStudent(\''+esc(s.student_no)+'\',\''+esc(course)+'\')">Remove</button></td></tr>';
            });
            h += '</tbody></table>';
            container.innerHTML = h;
        })
        .catch(function(){ container.innerHTML = '<div class="text-center text-danger py-2 small">Failed to load.</div>'; });
}

function unEnrollStudent(studentNo, course){
    Swal.fire({
        title: 'Remove Student?',
        text: 'Remove ' + studentNo + ' from ' + course + '?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Remove'
    }).then((result) => {
        if (!result.isConfirmed) return;
        var fd = new FormData(); fd.append('action','unenroll_student_course'); fd.append('student_no',studentNo); fd.append('course_code',course);
        fetch('/csc2052/api/student.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(data){
            if(data.status==='success'){ loadEnrolledList(course); }
            else alert('Failed: '+(data.message||''));
        });
    });
}

function loadAttendancePct(){
    var el = document.getElementById('attendance-container');
    if(MY_COURSES.length === 0){
        el.innerHTML = '<div class="text-center text-muted py-5 small">No courses assigned.</div>';
        return;
    }
    el.innerHTML = '<div class="text-center py-3 text-muted small"><i class="bi bi-arrow-clockwise spin me-1"></i>Loading...</div>';

    fetch('/csc2052/api/teacher.php?action=get_attendance_stats')
        .then(function(r){ return r.json(); })
        .then(function(data){
            if(data.status !== 'success' || !data.stats){
                el.innerHTML = '<div class="text-center text-danger py-3 small">Failed to load stats.</div>';
                return;
            }
            var allCodes = MY_COURSES.map(function(c){ return c.course_code; });
            var html = '<table class="table table-sm mb-0 align-middle"><thead class="table-light sticky-top"><tr>'+
                '<th class="ps-3">Student</th>';
            allCodes.forEach(function(c){ html += '<th class="text-center">'+esc(c)+'</th>'; });
            html += '<th class="text-center">Overall</th></tr></thead><tbody>';

            var studentMap = {};
            allCodes.forEach(function(code){
                (data.stats[code] || []).forEach(function(s){
                    if(!studentMap[s.student_no]) studentMap[s.student_no] = {name: s.student_name, courses:{}};
                    studentMap[s.student_no].courses[code] = s;
                });
            });

            var rows = '';
            Object.keys(studentMap).sort().forEach(function(sno){
                var info = studentMap[sno];
                var cells = '';
                var totalAtt = 0, totalSess = 0;
                allCodes.forEach(function(code){
                    var s = info.courses[code];
                    if(s){
                        var pct = s.percentage;
                        var color = pct >= 80 ? 'bg-success' : (pct >= 50 ? 'bg-warning' : 'bg-danger');
                        cells += '<td class="text-center"><span class="badge '+color+' badge-pill">'+s.attended+'/'+s.total_sessions+'</span></td>';
                        totalAtt += s.attended; totalSess += s.total_sessions;
                    } else {
                        cells += '<td class="text-center text-muted small">—</td>';
                    }
                });
                var overall = totalSess > 0 ? Math.round(totalAtt/totalSess*100) : 0;
                var overallColor = overall >= 80 ? 'text-success' : (overall >= 50 ? 'text-warning' : 'text-danger');
                rows += '<tr><td class="ps-3"><strong class="small">'+esc(sno)+'</strong><br><span class="text-muted small">'+esc(info.name||'')+'</span></td>'+cells+
                    '<td class="text-center fw-bold '+overallColor+'">'+overall+'%</td></tr>';
            });

            if(!rows){
                html += '<tr><td colspan="'+(allCodes.length+2)+'" class="text-center text-muted py-4 small">No students found.</td></tr>';
            } else {
                html += rows;
            }
            html += '</tbody></table>';
            el.innerHTML = html;
        })
        .catch(function(){ el.innerHTML = '<div class="text-center text-danger py-3 small">Failed to load.</div>'; });
}

/* ─── TEACHER CHARTS ──────────────────────────────────────── */
var teacherChartInstances = {};

// Reusable chart print helper
window.printChart = function(canvasId, title) {
    var canvas = document.getElementById(canvasId);
    if (!canvas) return;
    var w = window.open('', '_blank', 'width=800,height=600');
    w.document.write('<html><head><title>' + title + '</title>');
    w.document.write('<style>body{text-align:center;padding:30px;font-family:Arial,sans-serif;color:#1e293b;}h2{font-size:18px;margin-bottom:20px;}img{max-width:100%;height:auto;border:1px solid #ddd;border-radius:4px;}@media print{body{padding:10px;}h2{font-size:16px;}}</style>');
    w.document.write('</head><body>');
    w.document.write('<h2>' + title + '</h2>');
    w.document.write('<img src="' + canvas.toDataURL('image/png') + '">');
    w.document.write('</body></html>');
    w.document.close();
    setTimeout(function() { w.print(); }, 300);
};

window.loadTeacherCourseTrend = function(){
    var course = document.getElementById('teacher-trend-course').value;
    if (!course) return;
    var ctx = document.getElementById('teacherTrendChart');
    if (!ctx) return;
    if (teacherChartInstances.trend) teacherChartInstances.trend.destroy();
    var titleEl = document.getElementById('teacher-trend-title');
    if (titleEl) titleEl.textContent = course;
    fetch('/csc2052/api/charts.php?action=per_course&course='+encodeURIComponent(course)+'&days=30')
        .then(function(r){return r.json();})
        .then(function(d){
            teacherChartInstances.trend = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: d.labels || [],
                    datasets: [{ label: 'Attendance', data: d.data || [], borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,0.08)', fill: true, tension: 0.3, pointRadius: 2, pointBackgroundColor: '#0d6efd' }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(ctx) { return ctx.parsed.y + ' attendance' + (ctx.parsed.y !== 1 ? 's' : ''); } } } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } } } }
            });
        })
        .catch(function(){ console.error('[Teacher] Course trend load failed'); if (titleEl) titleEl.textContent = ''; });
};

window.loadTeacherStudentChart = function(){
    var studentNo = document.getElementById('teacher-student-input').value.trim();
    if (!studentNo || studentNo.length < 2) { document.getElementById('teacher-student-input')?.focus(); return; }
    var ctx = document.getElementById('teacherStudentChart');
    if (!ctx) return;
    if (teacherChartInstances.student) teacherChartInstances.student.destroy();
    var nameEl = document.getElementById('teacher-student-name');
    if (nameEl) nameEl.textContent = 'Student: ' + studentNo;
    fetch('/csc2052/api/charts.php?action=student_trend&student_no='+encodeURIComponent(studentNo)+'&days=30')
        .then(function(r){return r.json();})
        .then(function(d){
            teacherChartInstances.student = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: d.labels || [],
                    datasets: [{ label: 'Attendance', data: d.data || [], backgroundColor: '#a11e17', borderRadius: 3 }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(ctx) { return ctx.parsed.y + ' attendance' + (ctx.parsed.y !== 1 ? 's' : ''); } } } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } } } }
            });
        })
        .catch(function(){ console.error('[Teacher] Student chart load failed'); if (nameEl) nameEl.textContent = ''; });
};

function uploadPhoto(input){
    if(!input.files.length) return;
    var fd = new FormData(); fd.append('action','upload_photo'); fd.append('photo',input.files[0]);
    fetch('/csc2052/api/teacher.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(data){
        if(data.status==='success') setAvatar(data.photo_url+'?t='+Date.now());
        else alert('Upload failed: '+(data.message||''));
    }).catch(function(){ alert('Upload failed.'); });
}

function previewEditPhoto(input){
    if(input.files.length){
        var reader = new FileReader();
        reader.onload = function(e){
            var preview = document.getElementById('edit-photo-preview');
            if(preview.tagName === 'IMG') preview.src = e.target.result;
            else {
                var img = document.createElement('img');
                img.src = e.target.result; img.style.width='100%'; img.style.height='100%';
                img.style.borderRadius='50%'; img.style.border='2px solid #fff';
                preview.replaceWith(img); img.id = 'edit-photo-preview';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function saveProfile(){
    var msg = document.getElementById('edit-msg');
    msg.innerHTML = '<span class="text-primary"><i class="bi bi-arrow-clockwise spin me-1"></i>Saving...</span>';
    var fd = new FormData(); fd.append('action','update_my_profile');
    fd.append('teacher_name',document.getElementById('edit-name').value);
    fd.append('email',document.getElementById('edit-email').value);
    fd.append('phone',document.getElementById('edit-phone').value);
    var curPw = document.getElementById('edit-cur-pw').value;
    var newPw = document.getElementById('edit-new-pw').value;
    if(curPw) fd.append('current_password',curPw);
    if(newPw) fd.append('new_password',newPw);
    fetch('/csc2052/api/teacher.php',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(data){
        if(data.status==='success'){
            msg.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Saved! Reloading...</span>';
            setTimeout(function(){location.reload();},800);
        } else msg.innerHTML = '<span class="text-danger">'+esc(data.message||'Failed')+'</span>';
    }).catch(function(){ msg.innerHTML = '<span class="text-danger">Network error.</span>'; });
}

function loadTeacherRatings() {
    const el = document.getElementById('teacher-ratings-list');
    const courses = MY_COURSES || [];
    if (courses.length === 0) {
        el.innerHTML = '<div class="text-center text-muted py-3 small">No courses assigned.</div>';
        return;
    }
    let html = '';
    courses.forEach(c => {
        const summaryId = 'trating-summary-course-' + c.course_code;
        html += '<div class="border-bottom pb-2 mb-2">' +
            '<div class="d-flex justify-content-between align-items-center">' +
                '<div><strong class="small">' + esc(c.course_code) + '</strong><br><span class="text-muted" style="font-size:0.75rem;">' + esc(c.course_name || '') + '</span></div>' +
                '<div id="' + summaryId + '"><span class="text-muted small">—</span></div>' +
            '</div>' +
        '</div>';
    });
    el.innerHTML = html;
    courses.forEach(c => {
        loadRatingSummary('course', c.course_code);
    });
}

function switchTeacherForumCourse() {
    const sel = document.getElementById('teacher-forum-course');
    const course = sel.value;
    const container = document.getElementById('teacher-comments-container');
    if (!course) {
        container.innerHTML = '<div class="text-center text-muted py-4 small">Select a course to view discussion.</div>';
        return;
    }
    loadComments('course', course, 'teacher-comments-container');
}

function submitTeacherComment() {
    const sel = document.getElementById('teacher-forum-course');
    const course = sel.value;
    if (!course) { showToast('Select a course first', 'warning'); return; }
    const input = document.getElementById('teacher-comment-text');
    const body = input ? input.value.trim() : '';
    if (!body || body.length < 2) { showToast('Comment too short', 'warning'); return; }
    const fd = new FormData();
    fd.append('action', 'submit_comment');
    fd.append('target_type', 'course');
    fd.append('target_id', course);
    fd.append('body', body);
    fetch('/csc2052/api/ratings.php', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': getCsrf() },
        body: fd
    })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                if (input) input.value = '';
                showToast('Comment posted!', 'success');
                loadComments('course', course, 'teacher-comments-container');
            } else {
                showToast(data.message || 'Failed', 'error');
            }
        })
        .catch(() => showToast('Network error', 'error'));
}

// Override
window.loadCommentsForCurrent = function() {
    const sel = document.getElementById('teacher-forum-course');
    if (sel && sel.value) {
        loadComments('course', sel.value, 'teacher-comments-container');
    }
};

/* ─── LIVE ATTENDANCE POLLING ───────────────────────────────── */
var livePollTimer = null;
var livePollActive = true;
var lastLiveLogTime = 0;

function fetchLiveLogs() {
    fetch('/csc2052/api/teacher.php?action=teacher_logs&limit=20')
        .then(function(r){ return r.json(); })
        .then(function(data) {
            var el = document.getElementById('live-container');
            if (!data.logs || data.logs.length === 0) {
                el.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2"></i><p class="mb-0 small">No live attendance yet.</p></div>';
                document.getElementById('live-count').textContent = '0';
                return;
            }
            document.getElementById('live-count').textContent = data.logs.length;
            var h = '<table class="table table-hover table-sm mb-0 align-middle"><thead class="table-light"><tr><th>#</th><th>Student</th><th>Course</th><th>Time</th></tr></thead><tbody>';
            var latestTs = 0;
            data.logs.forEach(function(log, i) {
                var ts = (log.timestamp || '').replace(' ','T');
                var t = ts ? new Date(ts) : new Date();
                var time = t.toLocaleTimeString('en-GB', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
                var isNew = (Date.now() - t.getTime()) < 10000;
                var rowClass = isNew ? 'table-success' : '';
                // Speak new voice attendance entries
                if (isNew && log.modality === 'voice' && t.getTime() > lastLiveLogTime) {
                    var name = log.student_name || log.student_no || '';
                    if (typeof speakStudentNo === 'function') speakStudentNo(log.student_no, name);
                }
                if (t.getTime() > latestTs) latestTs = t.getTime();
                h += '<tr class="' + rowClass + '"><td class="small text-muted">' + (i+1) + '</td>' +
                    '<td><strong class="small">' + esc(log.student_no) + '</strong> <span class="text-muted small">' + esc(log.student_name||'') + '</span></td>' +
                    '<td><span class="badge bg-light">' + esc(log.course_code||'') + '</span></td>' +
                    '<td class="small text-muted">' + time + '</td></tr>';
            });
            if (latestTs > lastLiveLogTime) lastLiveLogTime = latestTs;
            h += '</tbody></table>';
            el.innerHTML = h;
        })
        .catch(function() { /* silent */ });
}

function toggleLivePolling() {
    livePollActive = !livePollActive;
    var btn = document.getElementById('btn-live-toggle');
    if (livePollActive) {
        btn.innerHTML = '<i class="bi bi-pause-fill me-1"></i>Pause';
        btn.className = 'btn btn-sm btn-outline-success';
        livePollTimer = setInterval(fetchLiveLogs, 5000);
        fetchLiveLogs();
    } else {
        btn.innerHTML = '<i class="bi bi-play-fill me-1"></i>Resume';
        btn.className = 'btn btn-sm btn-outline-warning';
        clearInterval(livePollTimer);
        livePollTimer = null;
    }
}

/* ─── CSV DOWNLOAD ──────────────────────────────────────────── */
function downloadCsv() {
    var course = document.getElementById('log-course').value;
    var date = document.getElementById('log-date').value;
    var url = '/csc2052/api/teacher.php?action=download_report';
    if (course) url += '&course_code=' + encodeURIComponent(course);
    if (date) url += '&date=' + encodeURIComponent(date);
    window.open(url, '_blank');
}

/* ─── INIT ──────────────────────────────────────────────────── */
refreshStats();
fetchLogs();
loadCourses();
loadAttendancePct();
loadUpcoming();
fetchLiveLogs();
livePollTimer = setInterval(fetchLiveLogs, 5000);

/* ─── DYNAMIC QR CODE SYSTEM ─── */
let qrSessionActive = false;
let qrSessionCourse = '';
let qrCountdownInterval = null;
let qrSessionSlot = 0;
let qrNextSlotTime = 0;
let qrCurrentData = '';
let qrProjectorWindow = null;
let isFetchingQr = false;

// Particle system variables
let qrParticles = [];
const CANVAS_SIZE = 340;
let canvasEl = null;
let ctx2d = null;
let qrMatrixSize = 25; // fallback

// Parameters
const ANIMATION_DURATION = 800; // transition time in ms
let transitionStartTime = 0;
let isTransitioning = false;

class QrParticle {
    constructor(id, r, c, startX, startY, targetX, targetY) {
        this.id = id;
        this.r = r;
        this.c = c;
        this.x = startX;
        this.y = startY;
        this.startX = startX;
        this.startY = startY;
        this.targetX = targetX;
        this.targetY = targetY;
        this.scale = 0;
        this.alpha = 0;
        this.startScale = 0;
        this.startAlpha = 0;
        this.targetScale = 1;
        this.targetAlpha = 1;
        this.isDying = false;
    }

    update(progress) {
        // Easing: easeInOutCubic
        const ease = progress < 0.5 
            ? 4 * progress * progress * progress 
            : 1 - Math.pow(-2 * progress + 2, 3) / 2;
            
        this.x = this.startX + (this.targetX - this.startX) * ease;
        this.y = this.startY + (this.targetY - this.startY) * ease;
        this.scale = this.startScale + (this.targetScale - this.startScale) * ease;
        this.alpha = this.startAlpha + (this.targetAlpha - this.startAlpha) * ease;
    }
}

function transitionToNewQr(qrMatrix, matrixSize) {
    const cellSize = CANVAS_SIZE / matrixSize;

    // Collect all dark cells in the new matrix
    const newActiveCoords = [];
    for (let r = 0; r < matrixSize; r++) {
        for (let c = 0; c < matrixSize; c++) {
            if (qrMatrix[r][c]) {
                const targetX = c * cellSize + cellSize / 2;
                const targetY = r * cellSize + cellSize / 2;
                newActiveCoords.push({ r, c, targetX, targetY });
            }
        }
    }

    // Capture starting state of current particles
    qrParticles.forEach(p => {
        p.startX = p.x;
        p.startY = p.y;
        p.startScale = p.scale;
        p.startAlpha = p.alpha;
    });

    const nextParticles = [];
    const availableOldParticles = [...qrParticles].filter(p => !p.isDying);

    // Greedy 1-to-1 matching based on cell grid distance
    newActiveCoords.forEach(newCoord => {
        let bestMatchIdx = -1;
        let minDistance = Infinity;

        for (let i = 0; i < availableOldParticles.length; i++) {
            const oldP = availableOldParticles[i];
            const dist = Math.hypot(oldP.r - newCoord.r, oldP.c - newCoord.c);
            if (dist < minDistance) {
                minDistance = dist;
                bestMatchIdx = i;
            }
        }

        // Limit jump distance for coherence
        if (bestMatchIdx !== -1 && minDistance < 12) {
            const matchedP = availableOldParticles.splice(bestMatchIdx, 1)[0];
            matchedP.r = newCoord.r;
            matchedP.c = newCoord.c;
            matchedP.targetX = newCoord.targetX;
            matchedP.targetY = newCoord.targetY;
            matchedP.targetScale = 1;
            matchedP.targetAlpha = 1;
            nextParticles.push(matchedP);
        } else {
            // Spawn new particle at destination and fade in
            const newId = Math.random().toString(36).substring(2, 9);
            const newP = new QrParticle(
                newId,
                newCoord.r,
                newCoord.c,
                newCoord.targetX,
                newCoord.targetY,
                newCoord.targetX,
                newCoord.targetY
            );
            newP.scale = 0;
            newP.alpha = 0;
            newP.startScale = 0;
            newP.startAlpha = 0;
            newP.targetScale = 1;
            newP.targetAlpha = 1;
            nextParticles.push(newP);
        }
    });

    // Old unmatched particles die out
    availableOldParticles.forEach(oldP => {
        oldP.targetScale = 0;
        oldP.targetAlpha = 0;
        oldP.isDying = true;
        nextParticles.push(oldP);
    });

    qrParticles = nextParticles;
    transitionStartTime = performance.now();
    isTransitioning = true;
}

function drawQrFrame(timestamp) {
    if (!qrSessionActive) return;
    
    ctx2d.clearRect(0, 0, CANVAS_SIZE, CANVAS_SIZE);
    
    let progress = 1;
    if (isTransitioning) {
        const elapsed = timestamp - transitionStartTime;
        progress = Math.min(elapsed / ANIMATION_DURATION, 1);
        if (progress >= 1) {
            isTransitioning = false;
            qrParticles = qrParticles.filter(p => !p.isDying);
        }
    }
    
    // Draw on lecturer's own dashboard canvas
    const cellSize = CANVAS_SIZE / qrMatrixSize;
    qrParticles.forEach(p => {
        p.update(progress);
        
        ctx2d.fillStyle = '#1e293b';
        ctx2d.globalAlpha = p.alpha;
        
        const r = cellSize * 0.5 * p.scale;
        ctx2d.beginPath();
        ctx2d.arc(p.x, p.y, Math.max(0, r), 0, Math.PI * 2);
        ctx2d.fill();
    });
    
    ctx2d.globalAlpha = 1.0;
    
    // Render on Projector Window Canvas (if active)
    if (qrProjectorWindow && !qrProjectorWindow.closed) {
        const pCanvas = qrProjectorWindow.document.getElementById('projector-canvas');
        if (pCanvas) {
            const pCtx = pCanvas.getContext('2d');
            pCtx.clearRect(0, 0, pCanvas.width, pCanvas.height);
            
            const pScale = 600 / CANVAS_SIZE;
            const pCellSize = 600 / qrMatrixSize;
            qrParticles.forEach(p => {
                pCtx.fillStyle = '#0f172a';
                pCtx.globalAlpha = p.alpha;
                const r = pCellSize * 0.5 * p.scale;
                pCtx.beginPath();
                pCtx.arc(p.x * pScale, p.y * pScale, Math.max(0, r), 0, Math.PI * 2);
                pCtx.fill();
            });
            pCtx.globalAlpha = 1.0;
        }
    }
    
    requestAnimationFrame(drawQrFrame);
}

function fetchAndRotateQr() {
    if (!qrSessionActive || isFetchingQr) return;
    
    isFetchingQr = true;
    fetch('/csc2052/api/qr_attendance.php?action=get_qr&course_code=' + encodeURIComponent(qrSessionCourse))
        .then(r => r.json())
        .then(data => {
            isFetchingQr = false;
            if (data.status === 'ok') {
                qrSessionSlot = data.slot;
                const expires = data.slot_expires_in;
                qrNextSlotTime = Date.now() + (expires * 1000);
                
                if (qrCurrentData !== data.qr_data) {
                    qrCurrentData = data.qr_data;
                    
                    // Generate QR Matrix
                    const qr = qrcode(0, 'M');
                    qr.addData(data.qr_data);
                    qr.make();
                    const size = qr.getModuleCount();
                    qrMatrixSize = size;
                    
                    const matrix = [];
                    for (let r = 0; r < size; r++) {
                        const row = [];
                        for (let c = 0; c < size; c++) {
                            row.push(qr.isDark(r, c));
                        }
                        matrix.push(row);
                    }
                    
                    transitionToNewQr(matrix, size);
                    fetchQrSessionStats();
                    
                    document.getElementById('qr-sequence-badge').textContent = '#' + qrSessionSlot;
                    if (qrProjectorWindow && !qrProjectorWindow.closed) {
                        const pSeq = qrProjectorWindow.document.getElementById('projector-sequence');
                        if (pSeq) pSeq.textContent = '#' + qrSessionSlot;
                    }
                }
            }
        })
        .catch(err => {
            isFetchingQr = false;
            console.error('Fetch QR error:', err);
        });
}

function fetchQrSessionStats() {
    if (!qrSessionActive) return;
    fetch('/csc2052/api/qr_attendance.php?action=get_session_stats&course_code=' + encodeURIComponent(qrSessionCourse))
        .then(r => r.json())
        .then(data => {
            if (data.status === 'ok') {
                document.getElementById('qr-stat-completed').textContent = data.completed;
                document.getElementById('qr-stat-pending').textContent = data.pending;
            }
        })
        .catch(err => console.error('Error fetching session stats:', err));
}

function updateLocalTimer() {
    if (!qrSessionActive) return;
    
    const now = Date.now();
    const diff = qrNextSlotTime - now;
    
    if (diff <= 0) {
        fetchAndRotateQr();
        return;
    }
    
    const seconds = (diff / 1000).toFixed(1);
    document.getElementById('qr-countdown-text').textContent = seconds + 's';
    
    if (qrProjectorWindow && !qrProjectorWindow.closed) {
        const pCount = qrProjectorWindow.document.getElementById('projector-countdown');
        if (pCount) pCount.textContent = seconds + 's';
    }
}

function startQrSession() {
    const course = document.getElementById('qr-session-course').value;
    if (!course) {
        alert('Please select a course first.');
        return;
    }
    
    qrSessionCourse = course;
    qrSessionActive = true;
    
    // Toggle UI controls
    document.getElementById('btn-qr-start').disabled = true;
    document.getElementById('btn-qr-stop').disabled = false;
    document.getElementById('btn-qr-projector').disabled = false;
    document.getElementById('qr-session-stats').classList.remove('d-none');
    document.getElementById('qr-canvas-placeholder').classList.add('d-none');
    document.getElementById('qr-canvas-container').classList.remove('d-none');
    
    if (!canvasEl) {
        canvasEl = document.getElementById('qr-code-canvas');
        ctx2d = canvasEl.getContext('2d');
    }
    
    fetchAndRotateQr();
    qrCountdownInterval = setInterval(updateLocalTimer, 100);
    requestAnimationFrame(drawQrFrame);
}

function stopQrSession() {
    qrSessionActive = false;
    qrSessionCourse = '';
    qrCurrentData = '';
    
    clearInterval(qrCountdownInterval);
    qrCountdownInterval = null;
    
    document.getElementById('btn-qr-start').disabled = false;
    document.getElementById('btn-qr-stop').disabled = true;
    document.getElementById('btn-qr-projector').disabled = true;
    document.getElementById('qr-session-stats').classList.add('d-none');
    document.getElementById('qr-canvas-placeholder').classList.remove('d-none');
    document.getElementById('qr-canvas-container').classList.add('d-none');
    
    if (ctx2d) {
        ctx2d.clearRect(0, 0, CANVAS_SIZE, CANVAS_SIZE);
    }
    qrParticles = [];
    
    if (qrProjectorWindow && !qrProjectorWindow.closed) {
        qrProjectorWindow.close();
    }
    qrProjectorWindow = null;
}

function openQrProjector() {
    if (!qrSessionActive) return;
    
    const w = 800;
    const h = 750;
    const left = (screen.width - w) / 2;
    const top = (screen.height - h) / 2;
    
    qrProjectorWindow = window.open('', 'QRProjector', `width=${w},height=${h},left=${left},top=${top},menubar=no,toolbar=no,location=no,status=no,resizable=yes`);
    
    if (!qrProjectorWindow) {
        alert('Popup blocker active. Please allow popups to open Projector Mode.');
        return;
    }
    
    qrProjectorWindow.document.write(`
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Projector View — Sentinel Swarm</title>
            <link rel="icon" type="image/svg+xml" href="/csc2052/assets/favicon.svg">
            <link rel="stylesheet" href="/csc2052/css/vendor/bootstrap.min.css">
            <link rel="stylesheet" href="/csc2052/css/vendor/bootstrap-icons.css">
            <style>
                body {
                    background-color: #090d16;
                    color: #f8fafc;
                    font-family: system-ui, -apple-system, sans-serif;
                    height: 100vh;
                    margin: 0;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    overflow: hidden;
                }
                .projector-card {
                    background: rgba(30, 41, 59, 0.4);
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    backdrop-filter: blur(12px);
                    border-radius: 24px;
                    padding: 2.5rem;
                    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
                    text-align: center;
                    max-width: 700px;
                    width: 90%;
                }
                .projector-canvas-wrapper {
                    position: relative;
                    margin: 2rem auto;
                    width: 600px;
                    height: 600px;
                    background: #fff;
                    border-radius: 16px;
                    padding: 15px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.4);
                }
                canvas {
                    display: block;
                    width: 100%;
                    height: 100%;
                }
                .info-badge {
                    font-size: 1.5rem;
                    letter-spacing: 0.05em;
                }
            </style>
        </head>
        <body>
            <div class="projector-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="badge bg-danger rounded-pill px-3 py-2 info-badge fw-bold shadow-sm">\${qrSessionCourse}</span>
                    <h3 class="fw-bold m-0 tracking-wide">SENTINEL SWARM AMS</h3>
                </div>
                
                <div class="projector-canvas-wrapper">
                    <canvas id="projector-canvas" width="600" height="600"></canvas>
                </div>
                
                <div class="d-flex justify-content-center align-items-center gap-3 mt-3">
                    <span class="badge bg-primary rounded-pill" id="projector-sequence">#\${qrSessionSlot}</span>
                    <span class="font-monospace text-warning fw-bold" id="projector-countdown">5.0s</span>
                </div>
                
                <p class="m-0 text-muted small"><i class="bi bi-shield-lock-fill text-warning me-1"></i>Secure Anti-Proxy Attendance. Rotate consecutive pairs.</p>
            </div>
            
            <script>
                window.onbeforeunload = function() {
                    if (window.opener && !window.opener.closed) {
                        window.opener.onQrProjectorClosed();
                    }
                };
            <\/script>
        </body>
        </html>
    `);
    qrProjectorWindow.document.close();
}

function onQrProjectorClosed() {
    qrProjectorWindow = null;
}

livePollTimer = setInterval(fetchLiveLogs, 5000);

document.addEventListener('DOMContentLoaded', function() {
    document.querySelector('[data-bs-target="#tab-attendance"]').addEventListener('shown.bs.tab', loadAttendancePct);
    document.querySelector('[data-bs-target="#tab-courses"]').addEventListener('shown.bs.tab', loadCourses);
    document.querySelector('[data-bs-target="#tab-logs"]').addEventListener('shown.bs.tab', fetchLogs);
    document.querySelector('[data-bs-target="#tab-forum"]').addEventListener('shown.bs.tab', function() {
        loadTeacherRatings();
        renderLangSwitcher('lang-switcher-teacher-forum');
    });
    document.querySelector('[data-bs-target="#tab-sections"]')?.addEventListener('shown.bs.tab', function() {
        renderSectionHistory();
    });
    document.querySelector('[data-bs-target="#tab-qr-attendance"]')?.addEventListener('shown.bs.tab', function() {
        if (!canvasEl) {
            canvasEl = document.getElementById('qr-code-canvas');
            if (canvasEl) ctx2d = canvasEl.getContext('2d');
        }
    });
    document.getElementById('enroll-course').addEventListener('change', function(){ loadEnrolledList(this.value); });
    // Render section history if already visible
    if (document.getElementById('section-history-container') && document.getElementById('tab-sections')?.classList.contains('show')) {
        renderSectionHistory();
    }
});
</script>
<?php require 'includes/footer.php'; ?>

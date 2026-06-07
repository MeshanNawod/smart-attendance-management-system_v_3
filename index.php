<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Role guard: non-admins go to their own dashboards
if (!isAdmin()) {
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    if (isTeacher()) { header('Location: teacher_dashboard.php'); exit; }
    if (isStudent())  { header('Location: student_dashboard.php'); exit; }
    header('Location: login.php'); exit;
}

$page = basename($_SERVER['PHP_SELF']);

$is_esp = false;
$is_rpi = false;
$status_file_esp = __DIR__ . '/sys_status_esp32.txt';
$status_file_rpi = __DIR__ . '/sys_status_rpi.txt';
if (file_exists($status_file_esp)) { $last_ping = (int)file_get_contents($status_file_esp); if ((time() - $last_ping) <= 30) $is_esp = true; }
if (file_exists($status_file_rpi)) { $last_ping = (int)file_get_contents($status_file_rpi); if ((time() - $last_ping) <= 30) $is_rpi = true; }

$total_students    = 0;
$today_attendance  = 0;
$courses           = [];
$allStudents       = [];
$discovered_devices = [];

try {
    $total_students    = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn() ?: 0;
    $today = date('Y-m-d');
    $stmt  = $pdo->prepare("SELECT COUNT(*) FROM attendance_logs WHERE DATE(timestamp) = ?");
    $stmt->execute([$today]);
    $today_attendance = $stmt->fetchColumn() ?: 0;
    $courses = $pdo->query("SELECT course_code, course_name FROM courses ORDER BY course_code ASC")->fetchAll(PDO::FETCH_ASSOC);
    $stmtStu = $pdo->query("SELECT student_no, student_name FROM students ORDER BY student_no ASC");
    $allStudents = $stmtStu->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $registry_file = __DIR__ . '/devices_registry.json';
    if (file_exists($registry_file)) {
        $registry = json_decode(file_get_contents($registry_file), true) ?: [];
        $now = time();
        foreach ($registry as $ip => $info) {
            if (empty($info['blocked'])) {
                $online = ($now - (int)$info['last_seen']) <= 30;
                $discovered_devices[] = [
                    'ip'     => $ip,
                    'name'   => $info['name'] ?? 'Unknown Node',
                    'type'   => $info['type'] ?? 'esp32',
                    'online' => $online,
                ];
            }
        }
    }
} catch (PDOException $e) {
    error_log('[Dashboard] DB error: ' . $e->getMessage());
}
?>
<?php require_once 'includes/header.php'; ?>

<div class="fade-in">

<?php require_once 'includes/cards/admin_stat_cards.php'; ?>

<div class="row g-3">

    <!-- ─── MAIN CONTENT: Live Logs ─────────────────────────── -->
    <div class="col-lg-8">
        <div class="logs-card">

            <!-- Header -->
            <div class="logs-card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:30px;height:30px;border-radius:8px;background:linear-gradient(135deg,#a11e17,#8b1510);display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-clock-history text-white" style="font-size:0.85rem;"></i>
                    </div>
                    <strong style="font-size:0.9rem;color:#1e293b;" data-i18n="live_logs">Live Attendance Logs</strong>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="global-voice-mute">
                        <label class="form-check-label small fw-bold text-muted" for="global-voice-mute">
                            <i class="bi bi-volume-mute me-1"></i><span data-i18n="mute">Mute</span>
                        </label>
                    </div>
                    <button class="btn btn-sm btn-outline-maroon fw-semibold"
                            onclick="exportVisibleLogsCsv()" title="Download current view as CSV">
                        <i class="bi bi-download me-1"></i><span data-i18n="export_csv">Export CSV</span>
                    </button>
                    <button class="btn btn-sm btn-outline-secondary fw-semibold"
                            onclick="exportLogsCsv()" title="Advanced export options">
                        <i class="bi bi-sliders me-1"></i>
                    </button>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="logs-filter-bar">
                <div class="row g-2 align-items-center">
                    <div class="col-md-3">
                        <input type="text" id="filter-student"
                               class="form-control form-control-sm"
                               placeholder="🔍 Search student..." data-i18n-placeholder="search_student"
                               oninput="fetchLogs()">
                    </div>
                    <div class="col-md-2">
                        <div class="searchable-select-wrapper position-relative">
                            <input type="text" id="filter-course-search" class="form-control form-control-sm"
                                   placeholder="Course" autocomplete="off"
                                   data-i18n-placeholder="course"
                                   oninput="filterDropdown(this, 'filter-course-dropdown', 'filter-course-input'); fetchLogs()"
                                   onfocus="showDropdown('filter-course-dropdown')">
                            <input type="hidden" id="filter-course-input" value="">
                            <div id="filter-course-dropdown" class="searchable-dropdown d-none"></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select id="filter-device" class="form-select form-select-sm" onchange="fetchLogs()">
                            <option value="" data-i18n="all_devices">All Devices</option>
                            <option value="WEB_DASHBOARD" data-i18n="web_dashboard">Web Dashboard</option>
                            <?php foreach ($discovered_devices as $dev): ?>
                            <option value="<?php echo htmlspecialchars($dev['name']); ?>">
                                <?php echo htmlspecialchars($dev['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" id="filter-date-from" class="form-control form-control-sm" onchange="fetchLogs()">
                    </div>
                    <div class="col-md-2">
                        <input type="date" id="filter-date-to" class="form-control form-control-sm" onchange="fetchLogs()">
                    </div>
                </div>
            </div>

            <!-- Logs Body -->
            <div class="logs-body">
                <div id="logs-accordion-container">
                    <div class="text-center text-muted py-4 small">
                        <i class="bi bi-hourglass-split me-1" style="color:#a11e17;"></i>
                        <em data-i18n="loading_live_logs">Loading live logs...</em>
                    </div>
                </div>
            </div>

        </div><!-- /.logs-card -->
    </div>

    <!-- ─── SIDEBAR ───────────────────────────────────────────── -->
    <div class="col-lg-4">
        <?php require_once 'includes/cards/admin_lecture_actions.php'; ?>
        <?php require_once 'includes/cards/admin_manual_attendance.php'; ?>
        <?php require_once 'includes/cards/admin_webcam_attendance.php'; ?>
        <?php require_once 'includes/cards/admin_voice_attendance.php'; ?>
        <?php require_once 'includes/cards/admin_qr_attendance.php'; ?>
        <?php require_once 'includes/cards/admin_rfid_attendance.php'; ?>
        <?php require_once 'includes/cards/admin_rpi_camera.php'; ?>
    </div>

</div><!-- /.row -->

<!-- ─── FULL-WIDTH CARDS ─────────────────────────────────────── -->
<div class="row g-3 mt-2">
    <div class="col-12">
        <?php require_once 'includes/cards/admin_logs_viewer.php'; ?>
    </div>
</div>
<div class="row g-3 mt-2">
    <div class="col-12">
        <?php require_once 'includes/cards/admin_fingerprint_templates.php'; ?>
    </div>
</div>

<!-- ─── CHARTS + RATINGS + FORUM (Tabs) ─────────────────────── -->
<div class="mt-4">
    <ul class="nav nav-tabs nav-tabs-premium" id="adminSecondaryTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-charts-btn" data-bs-toggle="tab" data-bs-target="#tab-charts" type="button" role="tab">
                <i class="bi bi-bar-chart me-1"></i> <span data-i18n="attendance_charts">Attendance Charts</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-ratings-btn" data-bs-toggle="tab" data-bs-target="#tab-ratings" type="button" role="tab">
                <i class="bi bi-star me-1"></i> <span data-i18n="ratings_feedback">Ratings & Feedback</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-forum-btn" data-bs-toggle="tab" data-bs-target="#tab-forum" type="button" role="tab">
                <i class="bi bi-chat-dots me-1"></i> <span data-i18n="forum_discussion">Forum Discussion</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-voice-btn" data-bs-toggle="tab" data-bs-target="#tab-voice" type="button" role="tab">
                <i class="bi bi-mic me-1"></i> Voice
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-logs-btn" data-bs-toggle="tab" data-bs-target="#tab-logs" type="button" role="tab">
                <i class="bi bi-journal-code me-1"></i> System Logs
            </button>
        </li>
    </ul>
    <div class="tab-content border border-top-0 rounded-bottom p-3 bg-white" style="border-color:#e2e8f0 !important;">

        <!-- ── Charts Tab ─────────────────────────────────────── -->
        <?php require_once 'includes/cards/admin_charts.php'; ?>

        <!-- ── Ratings Tab ────────────────────────────────────── -->
        <div class="tab-pane fade" id="tab-ratings" role="tabpanel">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card border-0 shadow-none h-100" style="border:1px solid #e2e8f0 !important;border-radius:10px;">
                        <div class="card-header bg-white py-2" style="border-radius:10px 10px 0 0;">
                            <strong class="small"><i class="bi bi-star me-1"></i> <span data-i18n="course_ratings">Course Ratings</span></strong>
                        </div>
                        <div class="card-body" style="max-height:450px;overflow-y:auto;" id="admin-ratings-board">
                            <div class="text-center text-muted py-4 small"><i class="bi bi-arrow-clockwise spin me-1"></i><span data-i18n="loading_ratings">Loading ratings...</span></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-none h-100" style="border:1px solid #e2e8f0 !important;border-radius:10px;">
                        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center" style="border-radius:10px 10px 0 0;">
                            <strong class="small"><i class="bi bi-chat-square-quote me-1"></i> <span data-i18n="recent_comments">Recent Comments</span></strong>
                        </div>
                        <div class="card-body" style="max-height:450px;overflow-y:auto;" id="admin-recent-comments">
                                <div class="text-center text-muted py-4 small"><i class="bi bi-arrow-clockwise spin me-1"></i><span data-i18n="loading_comments">Loading comments...</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Forum Tab ──────────────────────────────────────── -->
        <div class="tab-pane fade" id="tab-forum" role="tabpanel">
            <div class="row g-3">
                <div class="col-12">
                    <div class="card border-0 shadow-none" style="border:1px solid #e2e8f0 !important;border-radius:10px;">
                        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center" style="border-radius:10px 10px 0 0;">
                            <strong class="small"><i class="bi bi-chat-dots me-1"></i> <span data-i18n="course_discussion">Course Discussion</span></strong>
                            <div id="lang-switcher-admin-forum"></div>
                        </div>
                        <div class="card-body p-0">
                            <div class="p-3 border-bottom">
                                <select id="admin-forum-course" class="form-select form-select-sm" onchange="switchAdminForumCourse()">
                                    <option value="" data-i18n="select_course">Select a course...</option>
                                    <?php foreach ($courses as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c['course_code']); ?>">
                                        <?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="px-3 py-2 border-bottom">
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" id="admin-comment-input" data-i18n-placeholder="write_comment" placeholder="Write a comment..." onkeydown="if(event.key==='Enter')submitAdminForumComment()">
                                    <button class="btn btn-primary btn-sm" onclick="submitAdminForumComment()"><i class="bi bi-send"></i></button>
                                </div>
                            </div>
                            <div id="admin-forum-comments" style="max-height:400px;overflow-y:auto;">
                                <div class="text-center text-muted py-4 small" data-i18n="select_course">Select a course to view discussion.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Voice Tab ──────────────────────────────────────── -->
        <div class="tab-pane fade" id="tab-voice" role="tabpanel">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card border-0 shadow-none" style="border:1px solid #e2e8f0 !important;border-radius:10px;">
                        <div class="card-header bg-white py-2">
                            <strong class="small"><i class="bi bi-mic me-1" style="color:#6f42c1;"></i>Voice Enrollment</strong>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted">Record a student's voice sample to use for voice-based attendance.</p>
                            <div class="mb-2">
                                <div class="searchable-select-wrapper position-relative mb-2" style="z-index: 1050;">
                                    <input type="text" id="voice-admin-enroll-student-search" class="form-control form-control-sm" placeholder="Search student..." autocomplete="off"
                                           oninput="filterDropdown(this,'voice-admin-enroll-student-dropdown','voice-admin-enroll-student')"
                                           onfocus="showDropdown('voice-admin-enroll-student-dropdown')">
                                    <input type="hidden" id="voice-admin-enroll-student" value="">
                                    <div id="voice-admin-enroll-student-dropdown" class="searchable-dropdown d-none" style="max-height: 200px; overflow-y: auto; width: 100%;">
                                        <?php foreach ($allStudents as $s): ?>
                                            <div class="searchable-dropdown-item" data-value="<?php echo htmlspecialchars($s['student_no']); ?>" onclick="selectDropdownItem(this, 'voice-admin-enroll-student-search', 'voice-admin-enroll-student', 'voice-admin-enroll-student-dropdown')">
                                                <?php echo htmlspecialchars($s['student_no'] . ' - ' . $s['student_name']); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <select id="voice-admin-enroll-course" class="form-select form-select-sm mb-2">
                                    <option value="">Select course...</option>
                                    <?php foreach ($courses as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c['course_code']); ?>"><?php echo htmlspecialchars($c['course_code']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select id="voice-admin-enroll-mic-select" class="form-select form-select-sm mb-2" style="font-size:0.75rem;">
                                    <option value="">Default Microphone</option>
                                </select>
                            </div>
                            <div class="d-flex gap-2 mb-2">
                                <button class="btn btn-sm w-100 fw-bold" onclick="adminToggleVoiceRecord('enroll')"
                                        style="background:#6f42c1;color:#fff;border:none;border-radius:8px;">
                                    <i class="bi bi-mic me-1"></i><span id="voice-admin-record-label">Start Recording</span>
                                </button>
                                <button class="btn btn-sm w-100 fw-bold" onclick="adminPlayVoiceSample()" disabled id="voice-admin-playback"
                                        style="border:1.5px solid #6f42c1;color:#6f42c1;background:transparent;border-radius:8px;">
                                    <i class="bi bi-play-circle me-1"></i>Play
                                </button>
                            </div>
                            <div id="voice-admin-enroll-status" class="small text-muted text-center">Enter student no. and click record.</div>
                            <div class="mt-2">
                                <canvas id="voice-admin-enroll-visualizer" height="60" class="w-100 rounded" style="background:#1e293b;display:none;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-none" style="border:1px solid #e2e8f0 !important;border-radius:10px;">
                        <div class="card-header bg-white py-2">
                            <strong class="small"><i class="bi bi-person-check me-1" style="color:#198754;"></i>Voice Attendance</strong>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted">Enter student no. and record voice to mark attendance (uses active lecture course).</p>
                            <div class="mb-2">
                                <div class="searchable-select-wrapper position-relative mb-2" style="z-index: 1040;">
                                    <input type="text" id="voice-admin-attend-student-search" class="form-control form-control-sm" placeholder="Search student..." autocomplete="off"
                                           oninput="filterDropdown(this,'voice-admin-attend-student-dropdown','voice-admin-attend-student')"
                                           onfocus="showDropdown('voice-admin-attend-student-dropdown')">
                                    <input type="hidden" id="voice-admin-attend-student" value="">
                                    <div id="voice-admin-attend-student-dropdown" class="searchable-dropdown d-none" style="max-height: 200px; overflow-y: auto; width: 100%;">
                                        <?php foreach ($allStudents as $s): ?>
                                            <div class="searchable-dropdown-item" data-value="<?php echo htmlspecialchars($s['student_no']); ?>" onclick="selectDropdownItem(this, 'voice-admin-attend-student-search', 'voice-admin-attend-student', 'voice-admin-attend-student-dropdown')">
                                                <?php echo htmlspecialchars($s['student_no'] . ' - ' . $s['student_name']); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="small fw-bold text-center py-1 px-2 rounded mb-2" style="background:#f0fdf4;border:1px solid #bbf7d0;">
                                    <i class="bi bi-broadcast me-1 text-success"></i>Active Lecture: <span id="voice-admin-attend-course-display">None</span>
                                </div>
                                <input type="hidden" id="voice-admin-attend-course" value="">
                                <select id="voice-admin-attend-mic-select" class="form-select form-select-sm mb-2" style="font-size:0.75rem;">
                                    <option value="">Default Microphone</option>
                                </select>
                            </div>
                            <button class="btn btn-sm w-100 fw-bold mb-2" onclick="adminToggleVoiceRecord('attend')"
                                    style="background:#198754;color:#fff;border:none;border-radius:8px;">
                                <i class="bi bi-mic me-1"></i><span id="voice-admin-attend-label">Record & Mark</span>
                            </button>
                            <div id="voice-admin-attend-status" class="small text-center">Ready.</div>
                            <div class="mt-2">
                                <canvas id="voice-admin-attend-visualizer" height="60" class="w-100 rounded" style="background:#1e293b;display:none;"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="card border-0 shadow-none mt-3" style="border:1px solid #e2e8f0 !important;border-radius:10px;">
                        <div class="card-header bg-white py-2">
                            <strong class="small"><i class="bi bi-clock-history me-1"></i>Recent Voice Attendance</strong>
                        </div>
                        <div class="card-body p-0">
                            <div id="voice-admin-recent-list" class="px-3 py-2">
                                <div class="text-center text-muted py-3 small">No voice attendance records yet.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── System Logs Tab ─────────────────────────────────── -->
        <div class="tab-pane fade" id="tab-logs" role="tabpanel">
            <div class="row g-3">
                <div class="col-12">
                    <div class="card border-0 shadow-none" style="border:1px solid #e2e8f0 !important;border-radius:10px;">
                        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center" style="border-radius:10px 10px 0 0;">
                            <strong class="small"><i class="bi bi-journal-code me-1"></i> Full System Logs</strong>
                            <div class="d-flex gap-2 align-items-center">
                                <span class="small text-muted" id="tab-logs-count">0 entries</span>
                                <select id="tab-logs-type" class="form-select form-select-sm" style="width:auto;" onchange="fetchTabLogs()">
                                    <option value="">All Types</option>
                                    <option value="info">Info</option>
                                    <option value="warning">Warning</option>
                                    <option value="error">Error</option>
                                    <option value="power">Power</option>
                                    <option value="debug">Debug</option>
                                </select>
                                <button class="btn btn-sm btn-outline-secondary" onclick="fetchTabLogs()"><i class="bi bi-arrow-clockwise"></i></button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="p-2 border-bottom" style="background:#f8fafc;">
                                <div class="row g-1 align-items-center">
                                    <div class="col-auto">
                                        <input type="text" id="tab-logs-search" class="form-control form-control-sm" placeholder="Search..." style="width:180px;" oninput="fetchTabLogs()">
                                    </div>
                                    <div class="col-auto">
                                        <input type="date" id="tab-logs-date-from" class="form-control form-control-sm" onchange="fetchTabLogs()">
                                    </div>
                                    <div class="col-auto">
                                        <input type="date" id="tab-logs-date-to" class="form-control form-control-sm" onchange="fetchTabLogs()">
                                    </div>
                                    <div class="col-auto ms-auto">
                                        <button class="btn btn-sm btn-outline-danger" onclick="clearTabLogs()"><i class="bi bi-trash me-1"></i>Clear</button>
                                    </div>
                                </div>
                            </div>
                            <div style="max-height:500px;overflow-y:auto;">
                                <table class="table table-sm table-hover mb-0" style="font-size:0.78rem;">
                                    <thead class="table-dark small" style="position:sticky;top:0;z-index:2;">
                                        <tr>
                                            <th style="width:160px;">Timestamp</th>
                                            <th style="width:70px;">Type</th>
                                            <th style="width:70px;">Source</th>
                                            <th>Message</th>
                                            <th style="width:120px;">Details</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tab-logs-tbody">
                                        <tr><td colspan="5" class="text-center text-muted py-4 small">Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Courses Overview Tab ──────────────────────────────── -->
        <div class="tab-pane fade" id="tab-courses-overview" role="tabpanel">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="admin-courses-overview-table">
                            <thead class="table-dark small">
                                <tr>
                                    <th>Course Code</th>
                                    <th>Course Name</th>
                                    <th>Lecturer</th>
                                    <th>Enrolled Students</th>
                                    <th>Total Sessions</th>
                                    <th>Avg Attendance</th>
                                </tr>
                            </thead>
                            <tbody id="admin-courses-overview-body">
                                <tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-arrow-clockwise spin me-2"></i>Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

</div><!-- /.fade-in -->

<!-- CSV Export Modal -->
<div class="modal fade" id="exportCsvModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:14px;">
            <div class="modal-header bg-dark text-white" style="border-radius:14px 14px 0 0;">
                <h6 class="mb-0 fw-bold"><i class="bi bi-download me-2"></i><span data-i18n="export_csv">Export Attendance CSV</span></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold" data-i18n="date_from">Date From</label>
                        <input type="date" id="export-date-from" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold" data-i18n="date_to">Date To</label>
                        <input type="date" id="export-date-to" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold" data-i18n="time_from">Time From</label>
                        <input type="time" id="export-time-from" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold" data-i18n="time_to">Time To</label>
                        <input type="time" id="export-time-to" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold" data-i18n="course">Course</label>
                        <div class="searchable-select-wrapper position-relative">
                            <input type="text" id="export-course-search" class="form-control form-control-sm"
                                   placeholder="Leave blank for all" autocomplete="off"
                                   data-i18n-placeholder="leave_blank_all"
                                   oninput="filterDropdown(this, 'export-course-dropdown', 'export-course-input')"
                                   onfocus="showDropdown('export-course-dropdown')">
                            <input type="hidden" id="export-course-input" value="">
                            <div id="export-course-dropdown" class="searchable-dropdown d-none"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold" data-i18n="device">Device</label>
                        <select id="export-device" class="form-select form-select-sm">
                            <option value="" data-i18n="all_devices">All Devices</option>
                            <option value="WEB_DASHBOARD" data-i18n="web_dashboard">Web Dashboard</option>
                            <?php foreach ($discovered_devices as $dev): ?>
                            <option value="<?php echo htmlspecialchars($dev['name']); ?>">
                                <?php echo htmlspecialchars($dev['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <hr class="my-2">
                        <div class="small text-muted mb-2 fw-semibold" data-i18n="include_columns">Include Columns</div>
                        <div class="row g-2">
                            <div class="col-4"><div class="form-check"><input class="form-check-input" type="checkbox" value="student_no" id="col-student_no" checked><label class="form-check-label small" for="col-student_no" data-i18n="student_no">Student No</label></div></div>
                            <div class="col-4"><div class="form-check"><input class="form-check-input" type="checkbox" value="student_name" id="col-student_name" checked><label class="form-check-label small" for="col-student_name" data-i18n="student_name">Name</label></div></div>
                            <div class="col-4"><div class="form-check"><input class="form-check-input" type="checkbox" value="course_code" id="col-course_code" checked><label class="form-check-label small" for="col-course_code" data-i18n="course">Course</label></div></div>
                            <div class="col-4"><div class="form-check"><input class="form-check-input" type="checkbox" value="timestamp" id="col-timestamp" checked><label class="form-check-label small" for="col-timestamp" data-i18n="timestamp">Timestamp</label></div></div>
                            <div class="col-4"><div class="form-check"><input class="form-check-input" type="checkbox" value="modality" id="col-modality" checked><label class="form-check-label small" for="col-modality" data-i18n="modality">Modality</label></div></div>
                            <div class="col-4"><div class="form-check"><input class="form-check-input" type="checkbox" value="device" id="col-device" checked><label class="form-check-label small" for="col-device" data-i18n="device">Device</label></div></div>
                            <div class="col-4"><div class="form-check"><input class="form-check-input" type="checkbox" value="ip_address" id="col-ip_address"><label class="form-check-label small" for="col-ip_address" data-i18n="ip_address">IP</label></div></div>
                            <div class="col-4"><div class="form-check"><input class="form-check-input" type="checkbox" value="is_offline" id="col-is_offline"><label class="form-check-label small" for="col-is_offline" data-i18n="offline_sync">Offline Sync</label></div></div>
                            <div class="col-4"><div class="form-check"><input class="form-check-input" type="checkbox" value="id" id="col-id"><label class="form-check-label small" for="col-id" data-i18n="log_id">Log ID</label></div></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 px-4 pb-4">
                <button class="btn btn-sm btn-secondary" data-bs-dismiss="modal" data-i18n="cancel">Cancel</button>
                <button class="btn btn-sm fw-semibold btn-hover-lift" onclick="doExportCsv()"
                        style="background:#a11e17;color:#fff;border:none;border-radius:7px;padding:6px 18px;">
                    <i class="bi bi-download me-1"></i><span data-i18n="export">Export</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ─── Admin Voice Card (Attendance + Enroll) ─────────────────────────
window.adminVoiceCardToggle = function() {
    var studentEl = document.getElementById('voice-card-student');
    var courseEl = document.getElementById('voice-card-course');
    var statusEl = document.getElementById('voice-card-status');
    var labelEl = document.getElementById('voice-card-label');
    var visEl = document.getElementById('voice-card-visualizer');
    var muteEl = document.getElementById('voice-card-mute');

    if (!studentEl.value.trim()) {
        statusEl.innerHTML = '<span class="text-danger">Please enter or select a student number.</span>';
        return;
    }
    var activeCourse = courseEl.value;
    if (!activeCourse) {
        statusEl.innerHTML = '<span class="text-danger">No active lecture found. Start one under "Lecture & Quick Actions".</span>';
        return;
    }
    if (window._adminVoiceCardRecorder && window._adminVoiceCardRecorder.state === 'recording') {
        window._adminVoiceCardRecorder.stop();
        return;
    }
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        statusEl.innerHTML = '<span class="text-danger">Voice recording not supported in this browser.</span>';
        return;
    }
    // Secure context check — HTTPS or localhost required for getUserMedia
    if (!window.isSecureContext) {
        statusEl.innerHTML = '<span class="text-danger fw-bold"><i class="bi bi-shield-lock me-1"></i>HTTPS required. Access via <b>https://</b> or <b>http://localhost</b> for mic access.</span>';
        return;
    }
    statusEl.innerHTML = '<span class="text-muted"><i class="bi bi-mic me-1"></i>Requesting microphone access...</span>';
    window._adminVoiceCardChunks = [];
    var micSelect = document.getElementById('voice-card-mic-select');
    var audioConstraints = { audio: true };
    if (micSelect && micSelect.value) {
        audioConstraints = { audio: { deviceId: { exact: micSelect.value } } };
    }

    function initiateCapture(constraints) {
        navigator.mediaDevices.getUserMedia(constraints)
            .then(function(stream) {
                window._adminVoiceCardStream = stream;
                var m = 'audio/webm;codecs=opus';
                if (!MediaRecorder.isTypeSupported(m)) m = 'audio/webm';
                if (!MediaRecorder.isTypeSupported(m)) m = 'audio/ogg;codecs=opus';
                if (!MediaRecorder.isTypeSupported(m)) m = 'audio/mp4';
                window._adminVoiceCardRecorder = new MediaRecorder(stream, { mimeType: m });
                window._adminVoiceCardRecorder.ondataavailable = function(e) {
                    if (e.data.size > 0) window._adminVoiceCardChunks.push(e.data);
                };
                window._adminVoiceCardRecorder.onstop = function() {
                    var blob = new Blob(window._adminVoiceCardChunks, { type: window._adminVoiceCardRecorder.mimeType || 'audio/webm' });
                    window._adminVoiceCardChunks = [];
                    if (window._adminVoiceCardStream) {
                        window._adminVoiceCardStream.getTracks().forEach(function(t) { t.stop(); });
                        window._adminVoiceCardStream = null;
                    }
                    if (window._adminVoiceCardAnim) { cancelAnimationFrame(window._adminVoiceCardAnim); window._adminVoiceCardAnim = null; }
                    if (visEl) visEl.style.display = 'none';
                    var recordBtn = document.getElementById('voice-card-record-btn');
                    if (recordBtn) recordBtn.style.background = 'linear-gradient(135deg,#198754,#157347)';
                    var modeEl = document.querySelector('input[name="voiceCardMode"]:checked');
                    if (!modeEl) { if (labelEl) labelEl.textContent = 'Start Recording'; return; }
                    var mode = modeEl.value;
                    if (mode === 'enroll') {
                        statusEl.innerHTML = '<span class="text-success"><i class="bi bi-upload me-1"></i>Enrolling voice sample...</span>';
                        var fd = new FormData();
                        fd.append('action', 'enroll_voice');
                        fd.append('student_no', studentEl.value.trim());
                        fd.append('course_code', courseEl.value);
                        fd.append('audio', blob, studentEl.value.trim() + '_voice.webm');
                        fetch('/csc2052/api/voice.php', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
                            body: fd
                        })
                        .then(function(r) { return r.json(); })
                        .then(function(d) {
                            if (d.status === 'success') {
                                statusEl.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle me-1"></i>Voice enrolled!</span>';
                                if (!muteEl || !muteEl.checked) speakStudentNo(studentEl.value.trim(), 'Voice sample enrolled');
                            } else {
                                statusEl.innerHTML = '<span class="text-danger">' + (d.message || 'Failed.') + '</span>';
                            }
                            if (labelEl) labelEl.textContent = 'Start Recording';
                        })
                        .catch(function() {
                            statusEl.innerHTML = '<span class="text-danger">Network error.</span>';
                            if (labelEl) labelEl.textContent = 'Start Recording';
                        });
                    } else {
                        statusEl.innerHTML = '<span class="text-success"><i class="bi bi-upload me-1"></i>Marking attendance...</span>';
                        var fd = new FormData();
                        fd.append('action', 'voice_attendance');
                        fd.append('student_no', studentEl.value.trim());
                        fd.append('course_code', courseEl.value);
                        fd.append('audio', blob, studentEl.value.trim() + '_voice.webm');
                        fetch('/csc2052/api/voice.php', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
                            body: fd
                        })
                        .then(function(r) { return r.json(); })
                        .then(function(d) {
                            if (d.status === 'success') {
                                statusEl.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle me-1"></i>Attendance marked!</span>';
                                if (!muteEl || !muteEl.checked) speakStudentNo(studentEl.value.trim(), d.student_name || 'Attendance marked');
                                if (typeof fetchLogs === 'function') fetchLogs();
                                loadVoiceCardRecent();
                            } else if (d.status === 'duplicate') {
                                statusEl.innerHTML = '<span class="text-warning fw-bold"><i class="bi bi-exclamation-circle me-1"></i>Already marked today.</span>';
                            } else {
                                statusEl.innerHTML = '<span class="text-danger">' + (d.message || 'Failed.') + '</span>';
                            }
                            if (labelEl) labelEl.textContent = 'Start Recording';
                        })
                        .catch(function() {
                            statusEl.innerHTML = '<span class="text-danger">Network error.</span>';
                            if (labelEl) labelEl.textContent = 'Start Recording';
                        });
                    }
                };
                window._adminVoiceCardRecorder.start();
                if (labelEl) labelEl.textContent = '■■■ Recording...';
                statusEl.innerHTML = '<span class="text-success"><i class="bi bi-mic-fill me-1"></i>Recording... Click again to stop.</span>';
                var recordBtn = document.getElementById('voice-card-record-btn');
                if (recordBtn) recordBtn.style.background = '#dc3545';
                if (visEl) {
                    visEl.style.display = 'block';
                    var audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                    var src = audioCtx.createMediaStreamSource(stream);
                    var analyser = audioCtx.createAnalyser();
                    analyser.fftSize = 128;
                    src.connect(analyser);
                    var canvas = visEl;
                    var ctx = canvas.getContext('2d');
                    var bufferLength = analyser.frequencyBinCount;
                    var dataArray = new Uint8Array(bufferLength);
                    var W = canvas.width, H = canvas.height;
                    function draw() {
                        window._adminVoiceCardAnim = requestAnimationFrame(draw);
                        analyser.getByteFrequencyData(dataArray);
                        ctx.fillStyle = '#1e293b';
                        ctx.fillRect(0, 0, W, H);
                        var barWidth = (W / bufferLength) * 2.5;
                        var x = 0;
                        for (var i = 0; i < bufferLength; i++) {
                            var barH = (dataArray[i] / 255) * H;
                            ctx.fillStyle = 'hsl(' + (i * 3) + ', 80%, 60%)';
                            ctx.fillRect(x, H - barH, barWidth, barH);
                            x += barWidth + 1;
                        }
                    }
                    draw();
                }
            })
            .catch(function(err) {
                if (constraints.audio && constraints.audio.deviceId) {
                    console.warn('[Voice Card] Failed with selected mic, retrying default microphone...', err);
                    initiateCapture({ audio: true });
                } else {
                    var msg = 'Microphone access denied';
                    if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                        if (err.message && err.message.indexOf('context') !== -1) {
                            msg = 'HTTPS required. Access via <b>https://</b> or <b>http://localhost</b>.';
                        } else {
                            msg = 'Microphone blocked. Click the <b>🔒 lock icon</b> in the address bar → <b>Site settings</b> → set Microphone to <b>Allow</b>, then refresh.';
                        }
                    } else if (err.name === 'NotFoundError') {
                        msg = 'No microphone found. Connect a mic and try again.';
                    } else {
                        msg = 'Microphone error: ' + err.message;
                    }
                    statusEl.innerHTML = '<span class="text-danger fw-bold"><i class="bi bi-mic-mute-fill me-1"></i>' + msg + '</span>';
                    if (labelEl) labelEl.textContent = 'Start Recording';
                }
            });
    }

    initiateCapture(audioConstraints);
};

function loadVoiceCardStudents() {
    var el = document.getElementById('voice-card-student-select');
    if (!el) return;
    fetch('/csc2052/api/voice.php?action=get_course_students&course_code=' + encodeURIComponent(document.getElementById('voice-card-course').value || ''))
        .then(function(r) { return r.json(); })
        .then(function(d) {
            el.innerHTML = '<option value="">Quick-pick student...</option>';
            if (d.students) {
                d.students.forEach(function(s) {
                    el.innerHTML += '<option value="' + s.student_no + '">' + s.student_no + ' — ' + s.student_name + (s.voice_sample ? ' 🎤' : '') + '</option>';
                });
            }
        })
        .catch(function() {});
}

function loadAudioDevices() {
    if (typeof window.initVoiceMicrophones === 'function') {
        window.initVoiceMicrophones();
    }
}

function loadVoiceCardRecent() {
    var el = document.getElementById('voice-card-recent');
    if (!el) return;
    fetch('/csc2052/api/voice.php?action=get_voice_logs')
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.logs || d.logs.length === 0) {
                el.innerHTML = '<div class="text-center text-muted py-2 small">No recent voice attendance.</div>';
                return;
            }
            var h = '<table class="table table-sm mb-0 align-middle"><thead class="table-light"><tr><th>Student</th><th>Time</th></tr></thead><tbody>';
            d.logs.slice(0, 5).forEach(function(l) {
                h += '<tr><td><strong>' + esc(l.student_no) + '</strong> <span class="text-muted">' + (l.student_name || '') + '</span></td>' +
                    '<td class="small text-muted">' + (l.timestamp || '').slice(11, 19) + '</td></tr>';
            });
            h += '</tbody></table>';
            el.innerHTML = h;
        })
        .catch(function() {});
}

// Update active course display from localStorage
function updateActiveCourseDisplay() {
    var active = window.activeWebCourse || localStorage.getItem('active_lecture_course') || '';
    var displayEl = document.getElementById('voice-card-course-display');
    var hiddenEl = document.getElementById('voice-card-course');
    if (displayEl) displayEl.textContent = active || 'None';
    if (hiddenEl) hiddenEl.value = active || '';
    // Also update QR card
    var qrCourseDisplay = document.getElementById('admin-qr-course-display');
    var qrCourseHidden = document.getElementById('admin-qr-course');
    if (qrCourseDisplay) qrCourseDisplay.textContent = active || 'None';
    if (qrCourseHidden) qrCourseHidden.value = active || '';
    // Also update old voice tab attendance
    var tabAttendDisplay = document.getElementById('voice-admin-attend-course-display');
    var tabAttendHidden = document.getElementById('voice-admin-attend-course');
    if (tabAttendDisplay) tabAttendDisplay.textContent = active || 'None';
    if (tabAttendHidden) tabAttendHidden.value = active || '';
    // Also update RFID card
    var rfidCourseDisplay = document.getElementById('rfid-course-display');
    var rfidCourseHidden = document.getElementById('rfid-course');
    if (rfidCourseDisplay) rfidCourseDisplay.textContent = active || 'None';
    if (rfidCourseHidden) rfidCourseHidden.value = active || '';
}

document.addEventListener('DOMContentLoaded', function() {
    updateActiveCourseDisplay();
    loadVoiceCardRecent();
    // Re-check active course every 5 seconds
    setInterval(updateActiveCourseDisplay, 5000);
    // Reload student list when course changes
    var courseSelectors = [
        document.getElementById('voice-card-course'),
        document.querySelector('[id^=activation-course]'),
    ];
    courseSelectors.forEach(function(el) {
        if (el) el.addEventListener('change', function() { setTimeout(loadVoiceCardStudents, 500); });
    });
    // Quick-pick student fills input
    document.getElementById('voice-card-student-select')?.addEventListener('change', function() {
        var input = document.getElementById('voice-card-student');
        if (input && this.value) input.value = this.value;
    });
});

// ─── Admin QR Card ──────────────────────────────────────────────────────
let adminQrSessionActive = false;
window.adminQrToggleSession = function() {
    var courseEl = document.getElementById('admin-qr-course');
    if (!courseEl.value) { alert('No active lecture found. Start one under "Lecture & Quick Actions".'); return; }
    if (adminQrSessionActive) {
        adminQrSessionActive = false;
        document.getElementById('btn-admin-qr-start').textContent = 'Start QR Session';
        document.getElementById('admin-qr-stats').classList.add('d-none');
        document.getElementById('btn-admin-qr-start').disabled = false;
        return;
    }
    adminQrSessionActive = true;
    document.getElementById('btn-admin-qr-start').textContent = 'Starting...';
    document.getElementById('btn-admin-qr-start').disabled = true;
    fetch('/csc2052/api/qr_attendance.php?action=get_session_stats&course_code=' + encodeURIComponent(courseEl.value))
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.status === 'ok') {
                document.getElementById('admin-qr-completed').textContent = d.completed;
                document.getElementById('admin-qr-pending').textContent = d.pending;
                document.getElementById('admin-qr-stats').classList.remove('d-none');
                document.getElementById('btn-admin-qr-start').textContent = 'Refresh Stats';
                document.getElementById('btn-admin-qr-start').disabled = false;
            } else {
                alert('Failed to get QR session stats: ' + (d.message || 'Unknown error'));
                adminQrSessionActive = false;
                document.getElementById('btn-admin-qr-start').textContent = 'Start QR Session';
                document.getElementById('btn-admin-qr-start').disabled = false;
            }
        })
        .catch(function(e) {
            alert('Network error: ' + e.message);
            adminQrSessionActive = false;
            document.getElementById('btn-admin-qr-start').textContent = 'Start QR Session';
            document.getElementById('btn-admin-qr-start').disabled = false;
        });
};

// ─── Admin RFID Card ──────────────────────────────────────────────────────
let rfidSessionActive = false;
let rfidPollTimer = null;
let rfidEmptyPolls = 0;

window.toggleRfidSession = function() {
    var courseEl = document.getElementById('rfid-course');
    if (!courseEl.value) { alert('No active lecture found. Start one under "Lecture & Quick Actions".'); return; }
    if (rfidSessionActive) {
        // Stop
        rfidSessionActive = false;
        if (rfidPollTimer) { clearTimeout(rfidPollTimer); rfidPollTimer = null; }
        document.getElementById('btn-rfid-start').classList.remove('d-none');
        document.getElementById('rfid-session-container').classList.add('d-none');
        document.getElementById('btn-rfid-start').innerHTML = '<i class="bi bi-play-circle me-1"></i>Start RFID Scanner';
        return;
    }
    // Start
    rfidSessionActive = true;
    rfidEmptyPolls = 0;
    document.getElementById('btn-rfid-start').classList.add('d-none');
    document.getElementById('rfid-session-container').classList.remove('d-none');
    document.getElementById('rfid-session-status').innerHTML = '<i class="bi bi-credit-card-2-front me-1"></i>Waiting for RFID card...';
    document.getElementById('rfid-log-area').innerHTML = '';
    // Run a quick diagnostic to check endpoint reachability
    fetch('/csc2052/api/rfid_scanned.php?action=status')
        .then(function(r){ return r.json(); })
        .then(function(s){
            console.log('[RFID] Buffer status:', s);
            var logArea = document.getElementById('rfid-log-area');
            var line = document.createElement('div');
            line.style.padding = '2px 0';
            line.style.fontSize = '0.75rem';
            line.style.color = s.session_valid ? '#666' : '#dc3545';
            line.textContent = (s.session_valid ? '[OK] Session valid' : '[WARN] Session may be expired') + ' | Buffer: ' + (s.buffer_exists && s.buffer_content ? 'has UID' : 'empty') + ' | Writable: ' + s.buffer_writable;
            logArea.appendChild(line);
        })
        .catch(function(e){ console.error('[RFID] Status check failed:', e); });
    pollRfidCard();
};

function pollRfidCard() {
    if (!rfidSessionActive) return;
    fetch('/csc2052/api/rfid_scanned.php?action=consume&_=' + Date.now())
        .then(function(r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(function(d) {
            if (d.status === 'error') {
                rfidEmptyPolls++;
                var statusEl = document.getElementById('rfid-session-status');
                statusEl.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>' + (d.message || 'Unknown error') + '</span>';
                if (rfidSessionActive) { rfidPollTimer = setTimeout(pollRfidCard, 5000); }
                return;
            }
            if (d.status === 'success' && d.uid) {
                rfidEmptyPolls = 0;
                var uid = d.uid;
                var mode = document.querySelector('input[name="rfidMode"]:checked').value;
                var logArea = document.getElementById('rfid-log-area');
                var statusEl = document.getElementById('rfid-session-status');
                if (mode === 'attendance') {
                    statusEl.innerHTML = '<span class="text-primary"><i class="bi bi-credit-card-2-front me-1"></i>' + uid + ' — marking attendance...</span>';
                    markRfidAttendance(uid, function(result) {
                        var entry = document.createElement('div');
                        entry.style.padding = '2px 0';
                        entry.style.borderBottom = '1px solid #eee';
                        entry.textContent = new Date().toLocaleTimeString() + ' [' + uid + '] ' + result;
                        logArea.appendChild(entry);
                        logArea.scrollTop = logArea.scrollHeight;
                        statusEl.innerHTML = '<i class="bi bi-credit-card-2-front me-1"></i>Waiting for next card...';
                    });
                } else {
                    var entry = document.createElement('div');
                    entry.style.padding = '2px 0';
                    entry.style.borderBottom = '1px solid #eee';
                    entry.style.color = '#6f42c1';
                    entry.innerHTML = new Date().toLocaleTimeString() + ' [ENROLL] <strong>' + uid + '</strong> — scan complete, link via <a href="students.php" target="_blank">Hardware Enrollment Link</a>';
                    logArea.appendChild(entry);
                    logArea.scrollTop = logArea.scrollHeight;
                    statusEl.innerHTML = '<i class="bi bi-credit-card-2-front me-1"></i>UID captured. Go to Hardware Enrollment Link to connect to student.';
                }
            } else {
                rfidEmptyPolls++;
                if (rfidEmptyPolls === 20) {
                    var statusEl = document.getElementById('rfid-session-status');
                    statusEl.innerHTML = '<span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>No card detected for 30s — ensure device is online and tap card on reader.</span>';
                } else if (rfidEmptyPolls > 60) {
                    var statusEl = document.getElementById('rfid-session-status');
                    statusEl.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Timed out after 90s. Stop and start again, or check device.</span>';
                }
            }
            if (rfidSessionActive) {
                rfidPollTimer = setTimeout(pollRfidCard, 1500);
            }
        })
        .catch(function(e) {
            rfidEmptyPolls++;
            var statusEl = document.getElementById('rfid-session-status');
            if (rfidEmptyPolls === 3) {
                statusEl.innerHTML = '<span class="text-danger"><i class="bi bi-wifi-off me-1"></i>Cannot reach consume endpoint (' + e.message + '). Check login session.</span>';
            } else if (rfidEmptyPolls === 10) {
                statusEl.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Still failing after 10 retries. Try refreshing the page and starting again.</span>';
            }
            console.error('[RFID] Poll error:', e);
            if (rfidSessionActive) {
                rfidPollTimer = setTimeout(pollRfidCard, 3000);
            }
        });
}

// Test RFID buffer by pushing a dummy UID
window.testRfidBuffer = function() {
    var testUid = 'TEST' + Date.now().toString(36).toUpperCase().slice(-4);
    fetch('/csc2052/api/rfid_scanned.php?uid=' + testUid + '&hardware_key=ss_hw_api_key_2052')
        .then(function(r) { return r.json(); })
        .then(function(d) {
            var statusEl = document.getElementById('rfid-session-status');
            var logArea = document.getElementById('rfid-log-area');
            if (d.status === 'ok') {
                if (statusEl) statusEl.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Test UID pushed: ' + testUid + ' — polling should pick it up within 1.5s.</span>';
                var line = document.createElement('div');
                line.style.padding = '2px 0';
                line.style.fontSize = '0.75rem';
                line.style.color = '#666';
                line.textContent = new Date().toLocaleTimeString() + ' [TEST] Pushed ' + testUid + ' — ' + (d.message || 'ok');
                if (logArea) logArea.appendChild(line);
            } else {
                if (statusEl) statusEl.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Push failed: ' + (d.message || 'unknown') + '</span>';
            }
        })
        .catch(function(e) {
            var statusEl = document.getElementById('rfid-session-status');
            if (statusEl) statusEl.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Test failed: ' + e.message + '</span>';
        });
};

function markRfidAttendance(uid, callback) {
    // First lookup UID → student number
    fetch('/csc2052/api/esp_courses.php?action=lookup_rfid&uid=' + uid + '&hardware_key=ss_hw_api_key_2052')
        .then(function(r) { return r.text(); })
        .then(function(resp) {
            resp = resp.trim();
            var pipeIdx = resp.indexOf('|');
            if (pipeIdx > 0) {
                var studentNo = resp.substring(0, pipeIdx);
                var studentName = resp.substring(pipeIdx + 1);
                // Mark attendance
                var formData = new FormData();
                formData.append('action', 'mark_attendance');
                formData.append('student_no', studentNo);
                formData.append('course_code', document.getElementById('rfid-course').value);
                return fetch('/csc2052/api/teacher.php', { method: 'POST', body: formData })
                    .then(function(r2) { return r2.json(); })
                    .then(function(d2) {
                        if (d2.status === 'success') callback('Present: ' + studentName);
                        else callback('Error: ' + (d2.message || 'unknown'));
                    });
            } else {
                callback('Unknown card (not linked)');
            }
        })
        .catch(function(e) { callback('Error: ' + e.message); });
}

function linkRfidToStudent(uid, studentNo, callback) {
    var formData = new FormData();
    formData.append('action', 'link_rfid');
    formData.append('rfid_uid', uid);
    formData.append('student_no', studentNo);
    fetch('/csc2052/api/student.php', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            callback(d.status === 'success' ? 'Linked' : (d.message || 'failed'));
        })
        .catch(function(e) { callback('Error: ' + e.message); });
}
// ─── Admin Courses Overview Table ───────────────────────────────────────
function loadAdminCoursesOverview() {
    var tbody = document.getElementById('admin-courses-overview-body');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-arrow-clockwise spin me-2"></i>Loading...</td></tr>';
    fetch('/csc2052/api/teacher.php?action=get_courses_overview&_=' + Date.now())
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.status === 'ok' && d.courses) {
                var h = '';
                d.courses.forEach(function(c) {
                    h += '<tr>' +
                        '<td><strong class="text-primary">' + esc(c.course_code) + '</strong></td>' +
                        '<td>' + esc(c.course_name || '-') + '</td>' +
                        '<td>' + esc(c.lecturer_name || '-') + '</td>' +
                        '<td><span class="badge bg-info text-dark">' + (c.enrolled_count || 0) + '</span></td>' +
                        '<td>' + (c.total_sessions || 0) + '</td>' +
                        '<td>' + (c.avg_attendance != null ? c.avg_attendance + '%' : '-') + '</td>' +
                        '</tr>';
                });
                tbody.innerHTML = h || '<tr><td colspan="6" class="text-center text-muted py-3">No courses found.</td></tr>';
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-3">' + esc(d.message || 'Failed to load data.') + '</td></tr>';
            }
        })
        .catch(function(e) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-3">Network error: ' + esc(e.message) + '</td></tr>';
        });
}

// ─── Tab System Logs ──────────────────────────────────────────────────
function fetchTabLogs() {
    var tbody = document.getElementById('tab-logs-tbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3 small"><i class="bi bi-arrow-clockwise spin me-1"></i>Loading...</td></tr>';

    var type = document.getElementById('tab-logs-type').value;
    var search = document.getElementById('tab-logs-search').value;
    var dateFrom = document.getElementById('tab-logs-date-from').value;
    var dateTo = document.getElementById('tab-logs-date-to').value;

    var params = new URLSearchParams({ action: 'fetch', limit: '500' });
    if (type) params.set('log_type', type);
    if (search) params.set('search', search);
    if (dateFrom) params.set('date_from', dateFrom);
    if (dateTo) params.set('date_to', dateTo);

    fetch('/csc2052/api/system_logs.php?' + params.toString())
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.status !== 'ok') {
                tbody.innerHTML = '<tr><td colspan="5" class="text-danger text-center py-3 small">' + (d.message || 'Error') + '</td></tr>';
                return;
            }
            var countEl = document.getElementById('tab-logs-count');
            if (countEl) countEl.textContent = (d.total || d.data.length) + ' entries';
            if (!d.data || d.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3 small">No logs found.</td></tr>';
                return;
            }
            var html = '';
            for (var i = 0; i < d.data.length; i++) {
                var row = d.data[i];
                var ts = row.created_at || '';
                var typeBadge = row.log_type === 'error' ? 'danger' : row.log_type === 'warning' ? 'warning text-dark' : row.log_type === 'power' ? 'info' : row.log_type === 'debug' ? 'dark' : 'secondary';
                html += '<tr>' +
                    '<td class="text-nowrap small text-muted">' + htmlspecialchars(ts) + '</td>' +
                    '<td><span class="badge bg-' + typeBadge + '" style="font-size:0.65rem;">' + htmlspecialchars(row.log_type) + '</span></td>' +
                    '<td class="small">' + htmlspecialchars(row.source) + '</td>' +
                    '<td class="small">' + htmlspecialchars(row.message) + '</td>' +
                    '<td class="small text-muted" style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + htmlspecialchars(row.details || '') + '">' + htmlspecialchars((row.details || '').substring(0, 40)) + '</td>' +
                    '</tr>';
            }
            tbody.innerHTML = html;
        })
        .catch(function(e) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-danger text-center py-3 small">Network error: ' + e.message + '</td></tr>';
        });
}

function clearTabLogs() {
    if (!confirm('Clear all system logs?')) return;
    var fd = new FormData();
    fd.append('action', 'clear');
    fetch('/csc2052/api/system_logs.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.status === 'ok') { fetchTabLogs(); }
            else { alert('Error: ' + (d.message || 'Failed')); }
        })
        .catch(function(e) { alert('Network error'); });
}

function htmlspecialchars(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// Wire tab event
document.addEventListener('DOMContentLoaded', function() {
    var tabsList = document.getElementById('adminSecondaryTabs');
    if (tabsList && !document.getElementById('tab-courses-overview-btn')) {
        var li = document.createElement('li');
        li.className = 'nav-item';
        li.innerHTML = '<button class="nav-link" id="tab-courses-overview-btn" data-bs-toggle="tab" data-bs-target="#tab-courses-overview" type="button" role="tab"><i class="bi bi-table me-1"></i> Courses Overview</button>';
        tabsList.appendChild(li);
        document.getElementById('tab-courses-overview-btn').addEventListener('shown.bs.tab', loadAdminCoursesOverview);
    }
    var logsTabBtn = document.getElementById('tab-logs-btn');
    if (logsTabBtn) {
        logsTabBtn.addEventListener('shown.bs.tab', function() { setTimeout(fetchTabLogs, 100); });
    }
    // Preload sidebar logs
    setTimeout(function() {
        if (typeof fetchSystemLogs === 'function') fetchSystemLogs();
    }, 1000);
    // Enumerate audio input devices for mic select
    loadAudioDevices();
    if (navigator.mediaDevices && navigator.mediaDevices.addEventListener) {
        navigator.mediaDevices.addEventListener('devicechange', loadAudioDevices);
    }
});
</script>
<?php require 'includes/footer.php'; ?>

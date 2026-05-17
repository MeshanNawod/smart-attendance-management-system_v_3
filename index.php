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
$discovered_devices = [];

try {
    $total_students    = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn() ?: 0;
    $today = date('Y-m-d');
    $stmt  = $pdo->prepare("SELECT COUNT(*) FROM attendance_logs WHERE DATE(timestamp) = ?");
    $stmt->execute([$today]);
    $today_attendance = $stmt->fetchColumn() ?: 0;
    $courses = $pdo->query("SELECT course_code, course_name FROM courses ORDER BY course_code ASC")->fetchAll(PDO::FETCH_ASSOC);
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
        <?php require_once 'includes/cards/admin_rpi_camera.php'; ?>
    </div>

</div><!-- /.row -->

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
    </ul>
    <div class="tab-content border border-top-0 rounded-bottom p-3 bg-white" style="border-color:#e2e8f0 !important;">

        <!-- ── Charts Tab ─────────────────────────────────────── -->
        <div class="tab-pane fade show active" id="tab-charts" role="tabpanel">
            <!-- Overall Summary -->
            <div class="row g-2 mb-3" id="overall-summary-row">
                <div class="col-3">
                    <div class="border rounded p-2 text-center" style="border-color:#e2e8f0 !important;">
                        <div class="text-muted small" data-i18n="total_attendance">Total Attendance</div>
                        <div class="fw-bold h4 mb-0" id="summary-total" style="color:#a11e17;">-</div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="border rounded p-2 text-center" style="border-color:#e2e8f0 !important;">
                        <div class="text-muted small" data-i18n="today">Today</div>
                        <div class="fw-bold h4 mb-0" id="summary-today" style="color:#0d6efd;">-</div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="border rounded p-2 text-center" style="border-color:#e2e8f0 !important;">
                        <div class="text-muted small" data-i18n="students">Students</div>
                        <div class="fw-bold h4 mb-0" id="summary-students" style="color:#198754;">-</div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="border rounded p-2 text-center" style="border-color:#e2e8f0 !important;">
                        <div class="text-muted small" data-i18n="courses">Courses</div>
                        <div class="fw-bold h4 mb-0" id="summary-courses" style="color:#6f42c1;">-</div>
                    </div>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-8">
                    <div class="card border-0 shadow-none h-100" style="border:1px solid #e2e8f0 !important;border-radius:10px;">
                        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center" style="border-radius:10px 10px 0 0;">
                            <strong class="small"><i class="bi bi-graph-up me-1"></i> <span data-i18n="daily_trend">Daily Attendance Trend</span></strong>
                            <select id="chart-days" class="form-select form-select-sm" style="width:auto;" onchange="loadCharts()">
                                <option value="7">7 days</option>
                                <option value="14">14 days</option>
                                <option value="30" selected>30 days</option>
                                <option value="90">90 days</option>
                            </select>
                        </div>
                        <div class="card-body">
                            <canvas id="dailyTrendChart" height="220"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-none h-100" style="border:1px solid #e2e8f0 !important;border-radius:10px;">
                        <div class="card-header bg-white py-2" style="border-radius:10px 10px 0 0;">
                            <strong class="small"><i class="bi bi-pie-chart me-1"></i> <span data-i18n="modality_split">Modality Split</span></strong>
                        </div>
                        <div class="card-body d-flex align-items-center justify-content-center">
                            <canvas id="modalityChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-none" style="border:1px solid #e2e8f0 !important;border-radius:10px;">
                        <div class="card-header bg-white py-2" style="border-radius:10px 10px 0 0;">
                            <strong class="small"><i class="bi bi-bar-chart me-1"></i> <span data-i18n="course_distribution">Per-Course Attendance</span></strong>
                        </div>
                        <div class="card-body">
                            <canvas id="courseChart" height="180"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-none" style="border:1px solid #e2e8f0 !important;border-radius:10px;">
                        <div class="card-header bg-white py-2" style="border-radius:10px 10px 0 0;">
                            <strong class="small"><i class="bi bi-clock me-1"></i> <span data-i18n="hourly_pattern">Hourly Pattern</span></strong>
                        </div>
                        <div class="card-body">
                            <canvas id="hourlyChart" height="180"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Individual Analysis Section -->
            <div class="row g-3 mt-3">
                <div class="col-12">
                    <hr class="my-1" style="border-color:#e2e8f0;">
                    <h6 class="fw-bold small text-muted mt-2 mb-3"><i class="bi bi-person-lines-fill me-1"></i> <span data-i18n="individual_analysis">Individual Analysis</span></h6>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-none" style="border:1px solid #e2e8f0 !important;border-radius:10px;">
                        <div class="card-header bg-white py-2" style="border-radius:10px 10px 0 0;">
                            <strong class="small"><i class="bi bi-person me-1"></i> <span data-i18n="per_student">Per Student</span></strong>
                        </div>
                        <div class="card-body">
                            <input type="text" id="chart-student-no" class="form-control form-control-sm mb-2" placeholder="Student No..." oninput="loadStudentChart()">
                            <canvas id="studentChart" height="140"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-none" style="border:1px solid #e2e8f0 !important;border-radius:10px;">
                        <div class="card-header bg-white py-2" style="border-radius:10px 10px 0 0;">
                            <strong class="small"><i class="bi bi-book me-1"></i> <span data-i18n="per_course">Per Course</span></strong>
                        </div>
                        <div class="card-body">
                            <div class="searchable-select-wrapper position-relative mb-2">
                                <input type="text" id="chart-course-search" class="form-control form-control-sm" placeholder="Course..." autocomplete="off"
                                       oninput="filterDropdown(this,'chart-course-dropdown','chart-course-input')"
                                       onfocus="showDropdown('chart-course-dropdown')">
                                <input type="hidden" id="chart-course-input" value="">
                                <div id="chart-course-dropdown" class="searchable-dropdown d-none"></div>
                            </div>
                            <canvas id="courseDetailChart" height="140"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-none" style="border:1px solid #e2e8f0 !important;border-radius:10px;">
                        <div class="card-header bg-white py-2" style="border-radius:10px 10px 0 0;">
                            <strong class="small"><i class="bi bi-people me-1"></i> <span data-i18n="per_teacher">Per Teacher</span></strong>
                        </div>
                        <div class="card-body">
                            <canvas id="teacherChart" height="160"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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
                            <div class="col-4"><div class="form-check"><input class="form-check-input" type="checkbox" value="student_no" checked><label class="form-check-label small" data-i18n="student_no">Student No</label></div></div>
                            <div class="col-4"><div class="form-check"><input class="form-check-input" type="checkbox" value="student_name" checked><label class="form-check-label small" data-i18n="student_name">Name</label></div></div>
                            <div class="col-4"><div class="form-check"><input class="form-check-input" type="checkbox" value="course_code" checked><label class="form-check-label small" data-i18n="course">Course</label></div></div>
                            <div class="col-4"><div class="form-check"><input class="form-check-input" type="checkbox" value="timestamp" checked><label class="form-check-label small" data-i18n="timestamp">Timestamp</label></div></div>
                            <div class="col-4"><div class="form-check"><input class="form-check-input" type="checkbox" value="modality" checked><label class="form-check-label small" data-i18n="modality">Modality</label></div></div>
                            <div class="col-4"><div class="form-check"><input class="form-check-input" type="checkbox" value="device" checked><label class="form-check-label small" data-i18n="device">Device</label></div></div>
                            <div class="col-4"><div class="form-check"><input class="form-check-input" type="checkbox" value="ip_address"><label class="form-check-label small" data-i18n="ip_address">IP</label></div></div>
                            <div class="col-4"><div class="form-check"><input class="form-check-input" type="checkbox" value="is_offline"><label class="form-check-label small" data-i18n="offline_sync">Offline Sync</label></div></div>
                            <div class="col-4"><div class="form-check"><input class="form-check-input" type="checkbox" value="id"><label class="form-check-label small" data-i18n="log_id">Log ID</label></div></div>
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

<?php require 'includes/footer.php'; ?>

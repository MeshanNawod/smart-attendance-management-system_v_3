<?php // admin_charts.php — Chart tab content with premium modern redesign
$stmtStu = $pdo->query("SELECT student_no, student_name FROM students ORDER BY student_no ASC");
$allStudents = $stmtStu->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<!-- ── Premium Redesigned Charts Tab ─────────────────────────────────────── -->
<div class="tab-pane fade show active" id="tab-charts" role="tabpanel">

    <!-- Premium Custom Styles -->
    <style>
        /* Typography & Variables */
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        
        .tab-pane#tab-charts {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #1e293b;
        }

        /* Glassmorphic Stats Cards */
        .premium-stat-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.02), 0 4px 6px -4px rgba(0, 0, 0, 0.02);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            height: 100%;
        }
        .premium-stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            border-color: rgba(161, 30, 23, 0.2);
        }
        .premium-stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, transparent, #a11e17, transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .premium-stat-card:hover::before {
            opacity: 1;
        }
        .premium-stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            font-size: 1.25rem;
            transition: transform 0.3s ease;
        }
        .premium-stat-card:hover .premium-stat-icon {
            transform: scale(1.1) rotate(5deg);
        }

        /* Glassmorphic Chart Container Cards */
        .premium-chart-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.01);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .premium-chart-card:hover {
            box-shadow: 0 12px 20px -3px rgba(0, 0, 0, 0.04), 0 4px 6px -2px rgba(0, 0, 0, 0.02);
            border-color: #cbd5e1;
        }
        .premium-chart-header {
            padding: 16px 20px;
            background: #ffffff;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .premium-chart-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 8px;
            letter-spacing: -0.01em;
        }
        .premium-chart-body {
            padding: 20px;
            flex-grow: 1;
            position: relative;
        }

        /* Controls Redesign */
        .premium-select {
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 5px 12px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #475569;
            outline: none;
            cursor: pointer;
            background-color: #ffffff;
            transition: all 0.2s ease;
        }
        .premium-select:focus {
            border-color: #a11e17;
            box-shadow: 0 0 0 3px rgba(161, 30, 23, 0.15);
        }

        .btn-premium-action {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: #64748b;
            transition: all 0.2s ease;
            padding: 0;
        }
        .btn-premium-action:hover {
            background: #f8fafc;
            color: #a11e17;
            border-color: rgba(161, 30, 23, 0.2);
            transform: scale(1.05);
        }

        /* Forms in Sidebar */
        .premium-input-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .premium-input {
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 0.85rem;
            font-weight: 500;
            color: #334155;
            transition: all 0.2s ease;
        }
        .premium-input:focus {
            border-color: #a11e17;
            box-shadow: 0 0 0 3px rgba(161, 30, 23, 0.15);
            outline: none;
        }
        .premium-btn-search {
            background: linear-gradient(135deg, #a11e17, #85130d);
            color: #ffffff;
            border: none;
            border-radius: 10px;
            padding: 8px 16px;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s ease;
            box-shadow: 0 4px 6px -1px rgba(161, 30, 23, 0.2);
        }
        .premium-btn-search:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 12px -1px rgba(161, 30, 23, 0.3);
        }
        .premium-btn-search:active {
            transform: translateY(0);
        }

        /* Divider & Section Title */
        .premium-section-title {
            font-size: 1.05rem;
            font-weight: 800;
            color: #1e293b;
            letter-spacing: -0.02em;
            margin-top: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .premium-section-title i {
            color: #a11e17;
        }
        .premium-divider {
            border-top: 1px dashed #cbd5e1;
            margin: 25px 0;
            opacity: 0.8;
        }

        /* Advanced Analyzer Widget Styles */
        .analyzer-stat-value {
            font-size: 1.8rem;
            font-weight: 800;
            margin-top: 5px;
            letter-spacing: -0.02em;
        }
        .analyzer-zbadge-positive {
            background-color: rgba(25, 135, 84, 0.1);
            color: #198754;
            border: 1px solid rgba(25, 135, 84, 0.2);
            border-radius: 8px;
            padding: 2px 8px;
            font-size: 0.72rem;
            font-weight: 700;
            display: inline-block;
        }
        .analyzer-zbadge-negative {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.2);
            border-radius: 8px;
            padding: 2px 8px;
            font-size: 0.72rem;
            font-weight: 700;
            display: inline-block;
        }
        .btn-toggle-group {
            background: #f1f5f9;
            border-radius: 10px;
            padding: 3px;
            display: inline-flex;
            gap: 2px;
            border: 1px solid #e2e8f0;
        }
        .btn-toggle-item {
            border: none;
            background: transparent;
            color: #64748b;
            font-size: 0.8rem;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .btn-toggle-item.active {
            background: #ffffff;
            color: #a11e17;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .insight-card {
            background: rgba(161, 30, 23, 0.03);
            border-left: 4px solid #a11e17;
            border-radius: 0 12px 12px 0;
            padding: 15px 20px;
            font-size: 0.85rem;
            color: #475569;
            line-height: 1.6;
        }
    </style>

    <!-- Overall Summary Widgets Row -->
    <div class="row g-3 mb-4" id="overall-summary-row">
        <div class="col-6 col-md-3">
            <div class="premium-stat-card">
                <div class="premium-stat-icon" style="background: rgba(161, 30, 23, 0.08); color: #a11e17;">
                    <i class="bi bi-calendar-check-fill"></i>
                </div>
                <div class="text-muted small fw-semibold uppercase tracking-wider" data-i18n="total_attendance" style="font-size: 0.72rem;">TOTAL ATTENDANCE</div>
                <div class="fw-extrabold h3 mb-0 mt-1" id="summary-total" style="color: #a11e17; letter-spacing: -0.03em;">-</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="premium-stat-card">
                <div class="premium-stat-icon" style="background: rgba(13, 110, 253, 0.08); color: #0d6efd;">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div class="text-muted small fw-semibold uppercase tracking-wider" data-i18n="today" style="font-size: 0.72rem;">TODAY'S COUNT</div>
                <div class="fw-extrabold h3 mb-0 mt-1" id="summary-today" style="color: #0d6efd; letter-spacing: -0.03em;">-</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="premium-stat-card">
                <div class="premium-stat-icon" style="background: rgba(25, 135, 84, 0.08); color: #198754;">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="text-muted small fw-semibold uppercase tracking-wider" data-i18n="students" style="font-size: 0.72rem;">ACTIVE STUDENTS</div>
                <div class="fw-extrabold h3 mb-0 mt-1" id="summary-students" style="color: #198754; letter-spacing: -0.03em;">-</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="premium-stat-card">
                <div class="premium-stat-icon" style="background: rgba(111, 66, 193, 0.08); color: #6f42c1;">
                    <i class="bi bi-journal-bookmark-fill"></i>
                </div>
                <div class="text-muted small fw-semibold uppercase tracking-wider" data-i18n="courses" style="font-size: 0.72rem;">ACTIVE COURSES</div>
                <div class="fw-extrabold h3 mb-0 mt-1" id="summary-courses" style="color: #6f42c1; letter-spacing: -0.03em;">-</div>
            </div>
        </div>
    </div>

    <!-- Main Charts Section -->
    <div class="row g-3">
        <!-- Daily Trend (Line Chart) -->
        <div class="col-lg-8">
            <div class="premium-chart-card h-100">
                <div class="premium-chart-header">
                    <span class="premium-chart-title">
                        <i class="bi bi-activity text-danger" style="font-size: 1.15rem;"></i>
                        <span data-i18n="daily_trend">Daily Attendance Trend</span>
                    </span>
                    <div class="d-flex align-items-center gap-2">
                        <select id="chart-days" class="premium-select" onchange="loadCharts()">
                            <option value="7">7 Days</option>
                            <option value="14">14 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="90">90 Days</option>
                        </select>
                        <button class="btn-premium-action" onclick="printChart('dailyTrendChart', 'Daily Attendance Trend')" title="Print Trend"><i class="bi bi-printer"></i></button>
                    </div>
                </div>
                <div class="premium-chart-body">
                    <canvas id="dailyTrendChart" height="230"></canvas>
                </div>
            </div>
        </div>

        <!-- Modality Split (Doughnut) -->
        <div class="col-lg-4">
            <div class="premium-chart-card h-100">
                <div class="premium-chart-header">
                    <span class="premium-chart-title">
                        <i class="bi bi-circle-half text-primary" style="font-size: 1.15rem;"></i>
                        <span data-i18n="modality_split">Modality Split</span>
                    </span>
                    <button class="btn-premium-action" onclick="printChart('modalityChart', 'Attendance Modality Split')" title="Print Modality"><i class="bi bi-printer"></i></button>
                </div>
                <div class="premium-chart-body d-flex align-items-center justify-content-center">
                    <div class="w-100" style="height: 200px; position: relative;">
                        <canvas id="modalityChart" height="190"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Place-wise Attendance (New overall place-wise chart) -->
        <div class="col-md-6">
            <div class="premium-chart-card">
                <div class="premium-chart-header">
                    <span class="premium-chart-title">
                        <i class="bi bi-geo-alt-fill text-success" style="font-size: 1.15rem;"></i>
                        <span>Overall Place-Wise Attendance</span>
                    </span>
                    <button class="btn-premium-action" onclick="printChart('placeChart', 'Overall Place-Wise Attendance')" title="Print Place Distribution"><i class="bi bi-printer"></i></button>
                </div>
                <div class="premium-chart-body">
                    <canvas id="placeChart" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Course Attendance Distribution -->
        <div class="col-md-6">
            <div class="premium-chart-card">
                <div class="premium-chart-header">
                    <span class="premium-chart-title">
                        <i class="bi bi-mortarboard-fill text-warning" style="font-size: 1.15rem;"></i>
                        <span data-i18n="course_distribution">Course-Wise Attendance</span>
                    </span>
                    <button class="btn-premium-action" onclick="printChart('courseChart', 'Course Attendance Distribution')" title="Print Course Distribution"><i class="bi bi-printer"></i></button>
                </div>
                <div class="premium-chart-body">
                    <canvas id="courseChart" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Hourly Peak Activity Pattern -->
        <div class="col-md-12">
            <div class="premium-chart-card">
                <div class="premium-chart-header">
                    <span class="premium-chart-title">
                        <i class="bi bi-alarm-fill text-info" style="font-size: 1.15rem;"></i>
                        <span data-i18n="hourly_pattern">Hourly Pattern Analysis</span>
                    </span>
                    <button class="btn-premium-action" onclick="printChart('hourlyChart', 'Hourly Peak Attendance Pattern')" title="Print Hourly Peaks"><i class="bi bi-printer"></i></button>
                </div>
                <div class="premium-chart-body">
                    <canvas id="hourlyChart" height="210"></canvas>
                </div>
            </div>
        </div>

        <!-- Lecture Hall Occupancy Timeline & Peak Utilization -->
        <div class="col-md-12">
            <div class="premium-chart-card">
                <div class="premium-chart-header">
                    <span class="premium-chart-title">
                        <i class="bi bi-building-fill-gear text-success" style="font-size: 1.15rem;"></i>
                        <span>Lecture Hall Occupancy & Peak Time Analysis</span>
                    </span>
                    <div class="d-flex align-items-center gap-2">
                        <select id="occupancy-hall-select" class="premium-select" onchange="loadHallOccupancyChart()">
                            <option value="">Loading halls...</option>
                        </select>
                        <button class="btn-premium-action" onclick="printChart('hallOccupancyChart', 'Lecture Hall Occupancy Analysis')" title="Print Occupancy"><i class="bi bi-printer"></i></button>
                    </div>
                </div>
                <div class="premium-chart-body">
                    <canvas id="hallOccupancyChart" height="210"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Section Divider -->
    <div class="premium-divider"></div>

    <!-- Individual & Granular Specific Analysis Section -->
    <div class="premium-section-title">
        <i class="bi bi-person-bounding-box"></i>
        <span data-i18n="individual_analysis">Granular Attendance Analysis</span>
    </div>

    <div class="row g-3">
        <!-- Student-Wise Attendance Analysis -->
        <div class="col-lg-4">
            <div class="premium-chart-card h-100">
                <div class="premium-chart-header">
                    <span class="premium-chart-title">
                        <i class="bi bi-person-fill text-dark" style="font-size: 1.1rem;"></i>
                        <span data-i18n="per_student">Student-Wise Trend</span>
                    </span>
                    <button class="btn-premium-action" onclick="printChart('studentChart', 'Attendance Report - ' + (document.getElementById('chart-student-no')?.value || 'Student'))" title="Print Student Chart"><i class="bi bi-printer"></i></button>
                </div>
                <div class="premium-chart-body">
                    <div class="d-flex gap-2 mb-3">
                        <div class="searchable-select-wrapper position-relative w-100">
                            <input type="text" id="chart-student-search" class="premium-input w-100" placeholder="Search student..." autocomplete="off"
                                   oninput="filterDropdown(this,'chart-student-dropdown','chart-student-no')"
                                   onfocus="showDropdown('chart-student-dropdown')">
                            <input type="hidden" id="chart-student-no" value="">
                            <div id="chart-student-dropdown" class="searchable-dropdown d-none" style="max-height:200px; overflow-y:auto; z-index: 1000;">
                                <?php foreach ($allStudents as $s): ?>
                                    <div class="searchable-dropdown-item" data-value="<?php echo htmlspecialchars($s['student_no']); ?>" onclick="selectDropdownItem(this, 'chart-student-search', 'chart-student-no', 'chart-student-dropdown')">
                                        <?php echo htmlspecialchars($s['student_no'] . ' - ' . $s['student_name']); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button class="premium-btn-search" onclick="loadStudentChart()"><i class="bi bi-search"></i></button>
                    </div>
                    <div id="student-chart-name" class="small fw-bold text-center text-muted mb-2"></div>
                    <div style="height: 150px; position: relative;">
                        <canvas id="studentChart" height="150"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Course-Wise Attendance Detail -->
        <div class="col-lg-4">
            <div class="premium-chart-card h-100">
                <div class="premium-chart-header">
                    <span class="premium-chart-title">
                        <i class="bi bi-book-half text-dark" style="font-size: 1.1rem;"></i>
                        <span data-i18n="per_course">Course-Wise Trend</span>
                    </span>
                    <button class="btn-premium-action" onclick="printChart('courseDetailChart', 'Attendance Report - ' + (document.getElementById('chart-course-input')?.value || 'Course'))" title="Print Course Chart"><i class="bi bi-printer"></i></button>
                </div>
                <div class="premium-chart-body">
                    <div class="d-flex gap-2 mb-3">
                        <div class="searchable-select-wrapper position-relative w-100">
                            <input type="text" id="chart-course-search" class="premium-input w-100" placeholder="Search course..." autocomplete="off"
                                   oninput="filterDropdown(this,'chart-course-dropdown','chart-course-input')"
                                   onfocus="showDropdown('chart-course-dropdown')">
                            <input type="hidden" id="chart-course-input" value="">
                            <div id="chart-course-dropdown" class="searchable-dropdown d-none">
                                <?php foreach ($courses as $c): ?>
                                    <div class="searchable-dropdown-item" data-value="<?php echo htmlspecialchars($c['course_code']); ?>" onclick="selectDropdownItem(this, 'chart-course-search', 'chart-course-input', 'chart-course-dropdown')">
                                        <?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button class="premium-btn-search" onclick="loadCourseDetailChart()"><i class="bi bi-search"></i></button>
                    </div>
                    <div id="course-detail-title" class="small fw-bold text-center text-muted mb-2"></div>
                    <div style="height: 150px; position: relative;">
                        <canvas id="courseDetailChart" height="150"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Teacher-Wise Attendance Chart -->
        <div class="col-lg-4">
            <div class="premium-chart-card h-100">
                <div class="premium-chart-header">
                    <span class="premium-chart-title">
                        <i class="bi bi-person-video3 text-dark" style="font-size: 1.1rem;"></i>
                        <span data-i18n="per_teacher">Teacher-Wise Count</span>
                    </span>
                    <button class="btn-premium-action" onclick="printChart('teacherChart', 'Lecturer Performance Summary')" title="Print Lecturer Summary"><i class="bi bi-printer"></i></button>
                </div>
                <div class="premium-chart-body">
                    <canvas id="teacherChart" height="190"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Advanced Comparative Statistical Analyzer -->
    <div class="premium-divider"></div>
    
    <div class="premium-section-title">
        <i class="bi bi-bar-chart-steps"></i>
        <span>Advanced Peer-Comparative Attendance Analyzer</span>
    </div>

    <!-- Inputs Row -->
    <div class="premium-chart-card mb-4 p-4" style="overflow: visible;">
        <div class="row g-3 align-items-center">
            <div class="col-md-5">
                <label class="form-label small fw-bold text-secondary mb-1">Target Student</label>
                <div class="searchable-select-wrapper position-relative w-100">
                    <input type="text" id="analyzer-student-search" class="premium-input w-100" placeholder="Search student by ID or name..." autocomplete="off"
                           oninput="filterDropdown(this,'analyzer-student-dropdown','analyzer-student-input')"
                           onfocus="showDropdown('analyzer-student-dropdown')">
                    <input type="hidden" id="analyzer-student-input" value="">
                    <div id="analyzer-student-dropdown" class="searchable-dropdown d-none">
                        <?php foreach ($allStudents as $s): ?>
                            <div class="searchable-dropdown-item" data-value="<?php echo htmlspecialchars($s['student_no']); ?>" onclick="selectDropdownItem(this, 'analyzer-student-search', 'analyzer-student-input', 'analyzer-student-dropdown')">
                                <?php echo htmlspecialchars($s['student_no'] . ' - ' . $s['student_name']); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-5">
                <label class="form-label small fw-bold text-secondary mb-1">Target Course</label>
                <div class="searchable-select-wrapper position-relative w-100">
                    <input type="text" id="analyzer-course-search" class="premium-input w-100" placeholder="Search course by code or name..." autocomplete="off"
                           oninput="filterDropdown(this,'analyzer-course-dropdown','analyzer-course-input')"
                           onfocus="showDropdown('analyzer-course-dropdown')">
                    <input type="hidden" id="analyzer-course-input" value="">
                    <div id="analyzer-course-dropdown" class="searchable-dropdown d-none">
                        <?php foreach ($courses as $c): ?>
                            <div class="searchable-dropdown-item" data-value="<?php echo htmlspecialchars($c['course_code']); ?>" onclick="selectDropdownItem(this, 'analyzer-course-search', 'analyzer-course-input', 'analyzer-course-dropdown')">
                                <?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_name']); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-2 d-grid mt-md-4 pt-1">
                <button class="premium-btn-search" style="padding: 10px;" onclick="loadAdvancedAnalytics()">
                    <i class="bi bi-cpu me-1"></i>Analyze
                </button>
            </div>
        </div>
    </div>

    <!-- Advanced Analysis Output (Hidden until click) -->
    <div id="analyzer-output-container" class="d-none">
        
        <!-- Statistical Overview Grid -->
        <div class="row g-3 mb-4">
            <!-- Student Attendance Rate -->
            <div class="col-6 col-lg-3">
                <div class="premium-stat-card">
                    <div class="text-muted small fw-semibold uppercase tracking-wider" style="font-size: 0.68rem;">STUDENT ATTENDANCE RATE</div>
                    <div class="analyzer-stat-value text-dark" id="analyzer-val-rate">-</div>
                    <div class="text-muted small mt-1" id="analyzer-val-sessions">-</div>
                </div>
            </div>
            <!-- Peer Average (Mean) -->
            <div class="col-6 col-lg-3">
                <div class="premium-stat-card">
                    <div class="text-muted small fw-semibold uppercase tracking-wider" style="font-size: 0.68rem;">CLASS AVERAGE (MEAN)</div>
                    <div class="analyzer-stat-value text-primary" id="analyzer-val-mean">-</div>
                    <div class="text-muted small mt-1" id="analyzer-val-median">Median: -</div>
                </div>
            </div>
            <!-- Standard Deviation -->
            <div class="col-6 col-lg-3">
                <div class="premium-stat-card">
                    <div class="text-muted small fw-semibold uppercase tracking-wider" style="font-size: 0.68rem;">STANDARD DEVIATION (&sigma;)</div>
                    <div class="analyzer-stat-value text-warning" id="analyzer-val-std">-</div>
                    <div class="text-muted small mt-1" id="analyzer-val-minmax">Min: -% | Max: -%</div>
                </div>
            </div>
            <!-- Peer Rank & Z-Score -->
            <div class="col-6 col-lg-3">
                <div class="premium-stat-card">
                    <div class="text-muted small fw-semibold uppercase tracking-wider" style="font-size: 0.68rem;">PEER RANK & STATUS</div>
                    <div class="analyzer-stat-value text-success" id="analyzer-val-rank" style="font-size: 1.45rem; margin-top: 10px;">-</div>
                    <div class="mt-1" id="analyzer-val-zscore">-</div>
                </div>
            </div>
        </div>

        <!-- Analyzer Interactive Workspace -->
        <div class="premium-chart-card mb-4">
            <div class="premium-chart-header">
                <span class="premium-chart-title">
                    <i class="bi bi-graph-up-arrow text-danger" style="font-size: 1.15rem;"></i>
                    <span id="analyzer-chart-title">Peer-Comparative Attendance Visualizer</span>
                </span>
                
                <!-- Chart Type Toggles -->
                <div class="d-flex align-items-center gap-3">
                    <div class="btn-toggle-group">
                        <button class="btn-toggle-item active" id="btn-toggle-bar" onclick="setAnalyzerChartType('bar')"><i class="bi bi-bar-chart-steps me-1"></i>Bar</button>
                        <button class="btn-toggle-item" id="btn-toggle-line" onclick="setAnalyzerChartType('line')"><i class="bi bi-graph-up me-1"></i>Line</button>
                        <button class="btn-toggle-item" id="btn-toggle-radar" onclick="setAnalyzerChartType('radar')"><i class="bi bi-hexagon me-1"></i>Radar</button>
                        <button class="btn-toggle-item" id="btn-toggle-distribution" onclick="setAnalyzerChartType('distribution')"><i class="bi bi-reception-4 me-1"></i>Distribution</button>
                    </div>
                    <button class="btn-premium-action" onclick="printChart('advancedAnalyticsChart', document.getElementById('analyzer-chart-title').textContent)" title="Print Comparative Visual"><i class="bi bi-printer"></i></button>
                </div>
            </div>
            <div class="premium-chart-body">
                <div style="height: 280px; position: relative;">
                    <canvas id="advancedAnalyticsChart" height="280"></canvas>
                </div>
            </div>
        </div>

        <!-- Automated Statistical Insight & Remarks -->
        <div class="insight-card">
            <h6 class="fw-bold mb-1 text-dark" style="font-size: 0.85rem;"><i class="bi bi-lightbulb-fill text-warning me-1"></i>Statistical Assessment Insight</h6>
            <p class="mb-0" id="analyzer-assessment-text">Please select a student and course to generate comparative statistics analysis.</p>
        </div>

    </div>
</div>

<?php
require_once 'includes/header.php';

$enrolled_students = [];
$admins = [];
$courses = [];
$next_id = 1;
try {
    $enrolled_students = $pdo->query("SELECT student_no, student_name, fingerprint_id, rfid_uid, face_id, face_descriptor FROM students ORDER BY student_no ASC")->fetchAll(PDO::FETCH_ASSOC);
    $used_ids = array_column($enrolled_students, 'fingerprint_id');
    for ($i = 1; $i <= 127; $i++) {
        if (!in_array($i, $used_ids)) {
            $next_id = $i; break;
        }
    }
    $admins = $pdo->query("SELECT admin_name, fingerprint_id, rfid_uid, face_id, face_descriptor FROM admins ORDER BY admin_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $courses = $pdo->query("SELECT course_code, course_name FROM courses ORDER BY course_code ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[Students] DB error: ' . $e->getMessage());
}
?>

<div class="row mt-2">
    <div class="col-lg-10 mx-auto">
        
        <?php require_once 'includes/cards/admin_course_management.php'; ?>

        <?php require_once 'includes/cards/admin_student_database.php'; ?>

        <?php require_once 'includes/cards/admin_database_card.php'; ?>

    </div>
</div>

<!-- Add/Edit Schedule Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Add Weekly Schedule</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="sched-edit-id" value="">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Course Code</label>
                    <select id="sched-course" class="form-select">
                        <option value="">Select Course...</option>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Day</label>
                        <select id="sched-day" class="form-select">
                            <option value="1">Monday</option>
                            <option value="2">Tuesday</option>
                            <option value="3">Wednesday</option>
                            <option value="4">Thursday</option>
                            <option value="5">Friday</option>
                            <option value="6">Saturday</option>
                        </select>
                    </div>
                    <div class="col-3">
                        <label class="form-label fw-semibold">Start</label>
                        <input type="time" id="sched-start" class="form-control" value="08:00">
                    </div>
                    <div class="col-3">
                        <label class="form-label fw-semibold">End</label>
                        <input type="time" id="sched-end" class="form-control" value="10:00">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Venue (Optional)</label>
                    <input type="text" id="sched-venue" class="form-control" placeholder="e.g. Lab 204">
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold">Target Device</label>
                    <select id="sched-device" class="form-select">
                        <option value="WEB_DASHBOARD">Web Dashboard</option>
                    </select>
                    <div class="form-text">Device that will auto-start this schedule.</div>
                </div>
                <div id="sched-msg" class="small mb-2 min-h-xs"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success fw-semibold" onclick="saveSchedule()"><i class="bi bi-check-lg me-1"></i>Save Schedule</button>
            </div>
        </div>
    </div>
</div>

<script>
// Populate schedule course dropdown
function populateSchedCourseDropdown() {
    fetch('/csc2052/api/student.php?action=get_all_courses')
        .then(r => r.json())
        .then(data => {
            if (!data.courses) return;
            const sel = document.getElementById('sched-course');
            if (!sel) return;
            sel.innerHTML = '<option value="">Select Course...</option>';
            data.courses.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.course_code;
                opt.textContent = c.course_code + (c.course_name ? ' - ' + c.course_name : '');
                sel.appendChild(opt);
            });
        })
        .catch(() => {});
}

function populateSchedDeviceDropdown() {
    fetch('/csc2052/api/devices.php?action=list')
        .then(r => r.json())
        .then(data => {
            const sel = document.getElementById('sched-device');
            if (!sel) return;
            sel.innerHTML = '<option value="WEB_DASHBOARD">Web Dashboard</option>';
            if (data.devices && Array.isArray(data.devices)) {
                data.devices.forEach(d => {
                    const opt = document.createElement('option');
                    opt.value = d.ip_address || d.id || '';
                    opt.textContent = (d.device_name || d.ip_address || 'Unknown') + ' (' + (d.ip_address || 'no IP') + ')';
                    if (opt.value) sel.appendChild(opt);
                });
            }
        })
        .catch(() => {});
}

function loadSchedules() {
    const tbody = document.getElementById('timetable-body');
    if (!tbody) return;
    
    fetch('/csc2052/api/schedule.php?action=list')
        .then(r => r.json())
        .then(data => {
            if (!data.schedules) return;
            
            const dayColors = {
                1: 'table-info', 2: 'table-warning', 3: 'table-success',
                4: 'table-danger', 5: 'table-primary', 6: 'table-secondary'
            };
            const days = {1: 'mon', 2: 'tue', 3: 'wed', 4: 'thu', 5: 'fri', 6: 'sat'};
            const timeSlots = {};
            
            // Group schedules by time slot
            data.schedules.forEach(s => {
                const key = s.start_time + '|' + s.end_time;
                if (!timeSlots[key]) timeSlots[key] = { start: s.start_time, end: s.end_time, days: {} };
                timeSlots[key].days[s.day_of_week] = s;
            });
            
            // Sort time slots
            const sortedSlots = Object.values(timeSlots).sort((a, b) => a.start.localeCompare(b.start));
            
            let html = '';
            sortedSlots.forEach(slot => {
                const startDisplay = slot.start.substring(0, 5);
                const endDisplay = slot.end.substring(0, 5);
                html += '<tr><td class="fw-bold bg-light align-middle">' + startDisplay + '<br><span class="text-muted small">' + endDisplay + '</span></td>';
                
                for (let d = 1; d <= 6; d++) {
                    const s = slot.days[d];
                    if (s) {
                        const courseName = (s.course_name || s.course_code);
                        const venue = s.venue ? '<br><small class="text-muted"><i class="bi bi-geo-alt"></i> ' + escapeHtml(s.venue) + '</small>' : '';
                        const deviceBadge = s.device_id && s.device_id !== 'WEB_DASHBOARD' 
                            ? '<br><span class="badge bg-info text-dark" style="font-size:10px;"><i class="bi bi-cpu"></i> ' + escapeHtml(s.device_id) + '</span>' 
                            : '<br><span class="badge bg-light text-muted" style="font-size:10px;">Web</span>';
                        const autoBadge = s.auto_start == 1 
                            ? '<span class="badge bg-success" style="font-size:10px;margin-left:2px;">Auto</span>' 
                            : '';
                        html += '<td class="' + dayColors[d] + ' align-middle" style="vertical-align:middle;">' +
                            '<div class="fw-bold small">' + escapeHtml(courseName) + '</div>' +
                            venue + deviceBadge + ' ' + autoBadge +
                            '<div class="mt-1">' +
                            '<button class="btn btn-xs btn-outline-danger py-0 px-1" onclick="deleteSchedule(' + s.id + ')" title="Delete"><i class="bi bi-x"></i></button>' +
                            '</div>' +
                            '</td>';
                    } else {
                        html += '<td class="text-muted small align-middle">—</td>';
                    }
                }
                html += '</tr>';
            });
            
            if (sortedSlots.length === 0) {
                html = '<tr><td colspan="7" class="text-muted text-center py-4"><em>No schedules yet. Click "Add Schedule" to create one.</em></td></tr>';
            }
            
            tbody.innerHTML = html;
        })
        .catch(() => {
            tbody.innerHTML = '<tr><td colspan="7" class="text-danger text-center py-3"><em>Failed to load schedules.</em></td></tr>';
        });
}

function saveSchedule() {
    const id = document.getElementById('sched-edit-id').value;
    const course = document.getElementById('sched-course').value;
    const day = document.getElementById('sched-day').value;
    const start = document.getElementById('sched-start').value;
    const end = document.getElementById('sched-end').value;
    const venue = document.getElementById('sched-venue').value;
    const deviceId = document.getElementById('sched-device').value;
    const msg = document.getElementById('sched-msg');
    
    if (!course) { msg.innerHTML = '<span class="text-danger">Select a course.</span>'; return; }
    if (start >= end) { msg.innerHTML = '<span class="text-danger">End time must be after start time.</span>'; return; }
    
    const fd = new FormData();
    fd.append('action', 'save');
    if (id) fd.append('id', id);
    fd.append('course_code', course);
    fd.append('day_of_week', day);
    fd.append('start_time', start);
    fd.append('end_time', end);
    fd.append('venue', venue);
    fd.append('device_id', deviceId);
    
    fetch('/csc2052/api/schedule.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                msg.innerHTML = '<span class="text-success">Saved!</span>';
                setTimeout(() => {
                    bootstrap.Modal.getInstance(document.getElementById('scheduleModal')).hide();
                    loadSchedules();
                    loadCourseManager();
                    document.getElementById('sched-edit-id').value = '';
                    document.getElementById('sched-venue').value = '';
                    document.getElementById('sched-device').value = 'WEB_DASHBOARD';
                    msg.innerHTML = '';
                }, 800);
            } else if (data.status === 'conflict') {
                msg.innerHTML = '<span class="text-danger">' + escapeHtml(data.message) + '</span>';
            } else {
                msg.innerHTML = '<span class="text-danger">' + escapeHtml(data.message) + '</span>';
            }
        })
        .catch(() => { msg.innerHTML = '<span class="text-danger">Network error.</span>'; });
}

function deleteSchedule(id) {
    Swal.fire({
        title: 'Delete Schedule?',
        text: 'Are you sure you want to delete this schedule?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Delete'
    }).then((result) => {
        if (!result.isConfirmed) return;
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        
        fetch('/csc2052/api/schedule.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') loadSchedules();
            });
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

// ─── COURSE ENROLLMENT ────────────────────────────────────────────────────
function populateEnrollCourseSelect() {
    fetch('/csc2052/api/student.php?action=get_all_courses')
        .then(r => r.json())
        .then(data => {
            if (!data.courses) return;
            const sel = document.getElementById('enroll-stu-course-select');
            if (!sel) return;
            sel.innerHTML = '<option value="">Select Course...</option>';
            data.courses.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.course_code;
                opt.textContent = c.course_code + (c.course_name ? ' - ' + c.course_name : '');
                sel.appendChild(opt);
            });
        })
        .catch(() => {});
}

function addCourse() {
    const code = document.getElementById('new-course-code').value.trim();
    const name = document.getElementById('new-course-name').value.trim();
    const msg = document.getElementById('course-add-msg');
    if (!code) { msg.innerHTML = '<span class="text-danger">Course code is required.</span>'; return; }
    const fd = new FormData();
    fd.append('action', 'add_course');
    fd.append('course_code', code);
    if (name) fd.append('course_name', name);
    fetch('/csc2052/api/student.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                msg.innerHTML = '<span class="text-success">Course added!</span>';
                document.getElementById('new-course-code').value = '';
                document.getElementById('new-course-name').value = '';
                populateEnrollCourseSelect();
                populateSchedCourseDropdown();
                loadCourseManager();
                setTimeout(() => { msg.innerHTML = ''; }, 2000);
            } else {
                msg.innerHTML = '<span class="text-danger">' + escapeHtml(data.message || 'Failed to add course') + '</span>';
            }
        })
        .catch(() => { msg.innerHTML = '<span class="text-danger">Network error.</span>'; });
}

function deleteCourse(code) {
    Swal.fire({
        title: 'Delete Course?',
        text: 'Delete course ' + code + '?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Delete'
    }).then((result) => {
        if (!result.isConfirmed) return;
        const fd = new FormData();
        fd.append('action', 'delete_course');
        fd.append('course_code', code);
        fetch('/csc2052/api/student.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    populateEnrollCourseSelect();
                    populateSchedCourseDropdown();
                    loadCourseManager();
                    location.reload();
                }
            });
    });
}

function enrollStudentCourse() {
    const stuNo = document.getElementById('enroll-stu-course-no').value.trim();
    const course = document.getElementById('enroll-stu-course-select').value;
    const msg = document.getElementById('course-enroll-msg');
    if (!stuNo || !course) { msg.innerHTML = '<span class="text-danger">Student No and Course required.</span>'; return; }
    const fd = new FormData();
    fd.append('action', 'enroll_student_course');
    fd.append('student_no', stuNo);
    fd.append('course_code', course);
    fetch('/csc2052/api/student.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                msg.innerHTML = '<span class="text-success">Enrolled!</span>';
                document.getElementById('enroll-stu-course-no').value = '';
                setTimeout(() => { msg.innerHTML = ''; }, 2000);
            } else {
                msg.innerHTML = '<span class="text-danger">' + escapeHtml(data.message || 'Failed to enroll') + '</span>';
            }
        })
        .catch(() => { msg.innerHTML = '<span class="text-danger">Network error.</span>'; });
}

function bulkEnrollCourseCSV() {
    const fileInput = document.getElementById('course-bulk-csv');
    const log = document.getElementById('course-bulk-log');
    if (!fileInput.files.length) { log.innerHTML = '<span class="text-danger">Select a CSV file.</span>'; return; }
    const file = fileInput.files[0];
    const fd = new FormData();
    fd.append('action', 'bulk_enroll_course_csv');
    fd.append('csv_file', file);
    log.innerHTML = '<span class="text-primary">Processing...</span>';
    fetch('/csc2052/api/student.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                let html = '<span class="text-success">Done! ' + (data.enrolled || 0) + ' enrolled.</span>';
                if (data.errors && data.errors.length) {
                    html += '<ul class="mb-0 mt-1">';
                    data.errors.forEach(e => { html += '<li class="text-danger small">' + escapeHtml(e) + '</li>'; });
                    html += '</ul>';
                }
                log.innerHTML = html;
            } else {
                log.innerHTML = '<span class="text-danger">' + escapeHtml(data.message || 'Failed') + '</span>';
            }
        })
        .catch(() => { log.innerHTML = '<span class="text-danger">Network error.</span>'; });
}

function lookupStudentCourses() {
    const stuNo = document.getElementById('lookup-stu-course').value.trim();
    const result = document.getElementById('lookup-courses-result');
    if (!stuNo) { result.innerHTML = '<span class="text-muted">Enter a Student No.</span>'; return; }
    fetch('/csc2052/api/student.php?action=get_student_courses&student_no=' + encodeURIComponent(stuNo))
        .then(r => r.json())
        .then(data => {
            if (data.courses && data.courses.length > 0) {
                let html = '<strong>' + escapeHtml(data.student_name || stuNo) + '</strong><ul class="mb-0 mt-1">';
                data.courses.forEach(c => { html += '<li>' + escapeHtml(c.course_code) + (c.course_name ? ' - ' + escapeHtml(c.course_name) : '') + '</li>'; });
                html += '</ul>';
                result.innerHTML = html;
            } else {
                result.innerHTML = '<span class="text-muted">No courses found for this student.</span>';
            }
        })
        .catch(() => { result.innerHTML = '<span class="text-danger">Network error.</span>'; });
}

// ─── COURSE MANAGER ────────────────────────────────────────────────────
let courseManagerData = {};
let allTeachers = [];

function loadCourseManager() {
    const container = document.getElementById('course-manager-container');
    if (!container) return;
    
    // Load schedules and teachers in parallel
    Promise.all([
        fetch('/csc2052/api/schedule.php?action=list').then(r => r.json()),
        fetch('/csc2052/api/teacher.php?action=list').then(r => r.json()).catch(() => ({status:'error', teachers:[]}))
    ]).then(([data, tData]) => {
        if (!data.schedules) return;
        
        // Store teachers list
        allTeachers = tData.teachers || [];
        
        // Group schedules by course
        const courses = {};
        data.schedules.forEach(s => {
            if (!courses[s.course_code]) {
                courses[s.course_code] = {
                    course_code: s.course_code,
                    course_name: s.course_name || s.course_code,
                    auto_start: s.auto_start == 1,
                    email_threshold: parseInt(s.email_threshold) || 80,
                    email_on_end: s.email_on_end == 1,
                    teachers: [],
                    slots: []
                };
            }
            courses[s.course_code].slots.push(s);
        });
        
        // Attach teachers to courses
        if (tData.course_teachers) {
            tData.course_teachers.forEach(ct => {
                if (courses[ct.course_code]) {
                    courses[ct.course_code].teachers.push(ct);
                }
            });
        }
        
        courseManagerData = courses;
        renderCourseManager();
    }).catch(() => {
        container.innerHTML = '<div class="text-center py-3 text-danger"><em>Failed to load course manager.</em></div>';
    });
}

function renderCourseManager() {
    const container = document.getElementById('course-manager-container');
    if (!container) return;
    
    const courses = Object.values(courseManagerData);
    if (courses.length === 0) {
        container.innerHTML = '<div class="text-center py-4 text-muted"><em>No scheduled courses. Add a schedule to get started.</em></div>';
        return;
    }
    
    // Sort by course code
    courses.sort((a, b) => a.course_code.localeCompare(b.course_code));
    
    let html = '';
    courses.forEach(course => {
        const slotCount = course.slots.length;
        const days = {1: 'Mon', 2: 'Tue', 3: 'Wed', 4: 'Thu', 5: 'Fri', 6: 'Sat'};
        
        html += `<div class="border-bottom last:border-0">
            <div class="d-flex align-items-center p-3 bg-white" style="cursor:pointer;" onclick="toggleCourseSlots('${escapeHtml(course.course_code)}')">
                <i class="bi bi-chevron-right me-2 text-muted transition-icon" id="icon-${escapeHtml(course.course_code)}" style="transition:transform 0.2s;"></i>
                <div class="flex-grow-1">
                    <div class="fw-bold">${escapeHtml(course.course_code)}</div>
                    <div class="small text-muted">${escapeHtml(course.course_name)} &middot; ${slotCount} slot(s)`;
        
        // Show assigned teachers
        if (course.teachers && course.teachers.length > 0) {
            html += ` &middot; <i class="bi bi-person-workspace me-1"></i>`;
            course.teachers.forEach(t => {
                html += `<span class="badge bg-info me-1">${escapeHtml(t.teacher_name)}</span>`;
            });
        } else {
            html += ` &middot; <span class="text-warning small"><i class="bi bi-person-x me-1"></i>No teacher assigned</span>`;
        }
        
        html += `</div>
                </div>
                <div class="d-flex align-items-center gap-2 me-2">
                    <span class="badge ${course.auto_start ? 'bg-success' : 'bg-secondary'}">${course.auto_start ? 'Auto' : 'Manual'}</span>
                    <span class="badge bg-info">Threshold: ${course.email_threshold}%</span>
                    <span class="badge ${course.email_on_end ? 'bg-primary' : 'bg-light text-muted'}">${course.email_on_end ? 'Email On End' : 'No Email'}</span>
                </div>
            </div>
            
            <div id="slots-${escapeHtml(course.course_code)}" class="d-none bg-light p-3 border-top">
                <!-- Teacher Assignment -->
                <div class="mb-3 p-2 bg-white rounded border">
                    <label class="small fw-semibold mb-1"><i class="bi bi-person-workspace me-1"></i>Assigned Teachers</label>
                    <div class="d-flex gap-2 mb-2 flex-wrap">`;
        
        if (course.teachers && course.teachers.length > 0) {
            course.teachers.forEach(t => {
                html += `<span class="badge bg-info d-flex align-items-center gap-1">
                    ${escapeHtml(t.teacher_name)}
                    <button class="btn btn-sm btn-outline-light py-0 px-1 lh-1" onclick="removeTeacherFromCourse(${t.tc_id}, '${escapeHtml(course.course_code)}')" title="Remove">&times;</button>
                </span>`;
            });
        } else {
            html += `<span class="text-muted small fst-italic">No teachers assigned</span>`;
        }
        
        html += `</div>
                    <div class="input-group input-group-sm">
                        <select class="form-select" id="teacher-select-${escapeHtml(course.course_code)}">
                            <option value="">Select teacher to assign...</option>`;
        
        allTeachers.forEach(t => {
            const alreadyAssigned = course.teachers && course.teachers.some(ct => ct.teacher_id == t.id);
            if (!alreadyAssigned) {
                html += `<option value="${t.id}">${escapeHtml(t.teacher_name)} (${escapeHtml(t.email)})</option>`;
            }
        });
        
        html += `</select>
                        <button class="btn btn-sm btn-success" onclick="assignTeacherToCourse('${escapeHtml(course.course_code)}')"><i class="bi bi-plus-lg"></i>Assign</button>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="auto-start-${escapeHtml(course.course_code)}" 
                                ${course.auto_start ? 'checked' : ''} 
                                onchange="updateCourseSetting('${escapeHtml(course.course_code)}', 'auto_start', this.checked ? 1 : 0)">
                            <label class="form-check-label small fw-semibold" for="auto-start-${escapeHtml(course.course_code)}">
                                Auto-start at scheduled time
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="small fw-semibold mb-0">Email Alert Threshold (%)</label>
                        <input type="number" class="form-control form-control-sm mt-1" 
                            id="threshold-${escapeHtml(course.course_code)}" value="${course.email_threshold}" min="0" max="100"
                            onchange="updateCourseSetting('${escapeHtml(course.course_code)}', 'email_threshold', this.value)">
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" id="email-on-end-${escapeHtml(course.course_code)}" 
                                ${course.email_on_end ? 'checked' : ''} 
                                onchange="updateCourseSetting('${escapeHtml(course.course_code)}', 'email_on_end', this.checked ? 1 : 0)">
                            <label class="form-check-label small fw-semibold" for="email-on-end-${escapeHtml(course.course_code)}">
                                Send absent report when schedule ends
                            </label>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered text-center mb-0 small">
                        <thead class="table-light">
                            <tr><th>Day</th><th>Start</th><th>End</th><th>Venue</th><th>Device</th><th>Actions</th></tr>
                        </thead>
                        <tbody>`;
        
        course.slots.forEach(slot => {
            html += `<tr>
                <td class="fw-semibold">${days[slot.day_of_week] || slot.day_of_week}</td>
                <td>${slot.start_time ? slot.start_time.substring(0,5) : ''}</td>
                <td>${slot.end_time ? slot.end_time.substring(0,5) : ''}</td>
                <td>${escapeHtml(slot.venue || '—')}</td>
                <td><span class="badge bg-light text-dark">${escapeHtml(slot.device_id || 'Web')}</span></td>
                <td>
                    <button class="btn btn-xs btn-outline-danger py-0 px-2" onclick="deleteSchedule(${slot.id})" title="Delete slot">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>`;
        });
        
        html += `</tbody></table></div>
            </div>
        </div>`;
    });
    
    container.innerHTML = html;
}

function toggleCourseSlots(courseCode) {
    const slotsDiv = document.getElementById('slots-' + courseCode);
    const icon = document.getElementById('icon-' + courseCode);
    if (!slotsDiv) return;
    
    if (slotsDiv.classList.contains('d-none')) {
        slotsDiv.classList.remove('d-none');
        icon.style.transform = 'rotate(90deg)';
    } else {
        slotsDiv.classList.add('d-none');
        icon.style.transform = 'rotate(0deg)';
    }
}

function updateCourseSetting(courseCode, setting, value) {
    const course = courseManagerData[courseCode];
    if (!course) return;
    
    course[setting] = value;
    
    const fd = new FormData();
    fd.append('action', 'update_course_settings');
    fd.append('course_code', courseCode);
    fd.append(setting, value);
    
    fetch('/csc2052/api/schedule.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                loadSchedules();
            }
        })
        .catch(() => {});
}

function assignTeacherToCourse(courseCode) {
    const select = document.getElementById('teacher-select-' + courseCode);
    if (!select || !select.value) return;
    
    const teacherId = select.value;
    const fd = new FormData();
    fd.append('action', 'assign_teacher');
    fd.append('teacher_id', teacherId);
    fd.append('course_code', courseCode);
    
    fetch('/csc2052/api/teacher.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                loadCourseManager();
            } else {
                alert('Error: ' + (data.message || 'Failed to assign teacher'));
            }
        })
        .catch(() => alert('Network error'));
}

function removeTeacherFromCourse(tcId, courseCode) {
    Swal.fire({
        title: 'Remove Teacher?',
        text: 'Remove teacher from this course?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Remove'
    }).then((result) => {
        if (!result.isConfirmed) return;
        const fd = new FormData();
        fd.append('action', 'remove_teacher');
        fd.append('tc_id', tcId);
        fd.append('course_code', courseCode);
        
        fetch('/csc2052/api/teacher.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    loadCourseManager();
                } else {
                    alert('Error: ' + (data.message || 'Failed to remove teacher'));
                }
            })
            .catch(() => alert('Network error'));
    });
}

document.addEventListener('DOMContentLoaded', () => {
    populateEnrollCourseSelect();
    populateSchedCourseDropdown();
    populateSchedDeviceDropdown();
});
</script>

<?php require 'includes/footer.php'; ?>

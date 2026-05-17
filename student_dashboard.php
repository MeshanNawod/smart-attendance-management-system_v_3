<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

if (!isStudent()) {
    if (isAdmin()) { header('Location: index.php'); exit; }
    if (isTeacher()) { header('Location: teacher_dashboard.php'); exit; }
    header('Location: login.php'); exit;
}

$studentNo = $_SESSION['student_no'] ?? $_SESSION['username'] ?? '';
$studentName = $_SESSION['full_name'] ?? 'Student';
$mustChange = $_SESSION['must_change_password'] ?? false;

$profile = null;
$profilePhoto = '';
$fingerId = '';
$rfidUid = '';
$faceId = '';
$initials = '';

try {
    $stmt = $pdo->prepare("SELECT student_no, student_name, fingerprint_id, rfid_uid, face_id, profile_photo FROM students WHERE student_no = ?");
    $stmt->execute([$studentNo]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($profile) {
        $studentName = $profile['student_name'] ?? $studentName;
        $_SESSION['full_name'] = $studentName;
        $fingerId = $profile['fingerprint_id'] ?? '';
        $rfidUid = $profile['rfid_uid'] ?? '';
        $faceId = $profile['face_id'] ?? '';
        $initials = getInitials($studentName);
        if (!empty($profile['profile_photo']) && file_exists(__DIR__ . '/' . $profile['profile_photo'])) {
            $profilePhoto = '/' . $profile['profile_photo'];
        }
    } else {
        $initials = getInitials($studentName);
    }
} catch (PDOException $e) {
    $initials = getInitials($studentName);
}

$csrf = $_SESSION['csrf_token'] ?? '';
$avatarSrc = $profilePhoto
    ? htmlspecialchars($profilePhoto)
    : 'https://ui-avatars.com/api/?name=' . urlencode($studentName) . '&size=80&background=ffd30a&color=1e293b&bold=true';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="csrf-token" content="<?php echo h($_SESSION['csrf_token']); ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal — Sentinel Swarm</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('css/tables.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('css/cards.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <style>
        body { background: #fdfdfd; }
        .student-hero { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; color: #1e293b; }
        .avatar-ring { width: 70px; height: 70px; border-radius: 50% !important; padding: 2px; background: #e2e8f0; position: relative; cursor: pointer; overflow: hidden; }
        .avatar-ring img { width: 100% !important; height: 100% !important; border-radius: 50% !important; object-fit: cover; border: 2px solid #fff; }
        .avatar-ring .initials-avatar { width: 100%; height: 100%; border-radius: 50% !important; background: #f8fafc; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; font-weight: 600; color: #a11e17; border: 2px solid #fff; }
        .avatar-ring .cam-overlay { position: absolute; bottom: 0; right: 0; width: 22px; height: 22px; background: #fff; border-radius: 50% !important; display: flex; align-items: center; justify-content: center; border: 1px solid #e2e8f0; }
        .avatar-ring .cam-overlay i { font-size: 10px; color: #64748b; }
        .stat-card { border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; background: #fff; }
        .stat-select { border-radius: 4px; }
        .ls-wide { letter-spacing: 0.02em; }
        .course-card { border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; background: #fff; }
        .course-card .progress { border-radius: 4px; overflow: hidden; }
        .course-card .progress-bar { border-radius: 4px; }
        .nav-tabs .nav-link { border: none; color: #64748b; font-weight: 500; padding: 0.75rem 1rem; border-radius: 0; }
        .nav-tabs .nav-link.active { color: #a11e17; border-bottom: 2px solid #a11e17; background: transparent; }
        .hist-table th { position: sticky; top: 0; z-index: 1; background: #fff; border-bottom: 1px solid #e2e8f0; }
        .badge-pill { border-radius: 4px !important; }
        #ai-assistant-container { display: none !important; }
    </style>
</head>
<body>
<?php require 'includes/header_student.php'; ?>

<?php if ($mustChange): ?>
<div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,0.6)" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h6 class="modal-title"><i class="bi bi-shield-lock me-2"></i>First-Time Password</h6>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Set a new password to secure your account.</p>
                <div id="pw-msg" class="small mb-2"></div>
                <input type="hidden" id="pw-cur" value="<?php echo htmlspecialchars($studentNo); ?>">
                <div class="mb-2"><label class="form-label small fw-bold">New Password</label><input type="password" id="pw-new" class="form-control form-control-sm" minlength="6"></div>
                <div class="mb-2"><label class="form-label small fw-bold">Confirm</label><input type="password" id="pw-conf" class="form-control form-control-sm" minlength="6"></div>
                <button class="btn btn-warning btn-sm w-100 fw-bold btn-hover-lift btn-hover-ripple" onclick="changePassword()"><i class="bi bi-key me-1"></i>Set Password</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="container pb-5 mt-3">

<div class="student-hero p-4 mb-4">
    <div class="row align-items-center position-relative" style="z-index:1;">
        <div class="col-auto">
            <div class="avatar-ring" onclick="document.getElementById('photo-upload').click()" title="Upload photo">
                <?php if ($profilePhoto): ?>
                <img id="profile-img" src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Photo">
                <?php else: ?>
                <div id="profile-img" class="initials-avatar"><?php echo htmlspecialchars($initials ?: '?'); ?></div>
                <?php endif; ?>
                <div class="cam-overlay"><i class="bi bi-camera-fill"></i></div>
            </div>
            <input type="file" id="photo-upload" class="d-none" accept="image/*" onchange="uploadPhoto(this)">
        </div>
        <div class="col">
            <h3 class="fw-bold mb-1"><?php echo htmlspecialchars($studentName); ?></h3>
            <span id="tier-badge" class="badge bg-secondary ms-2">Tier: —</span>
            <p class="mb-0 opacity-75"><i class="bi bi-person-badge me-1"></i><?php echo htmlspecialchars($studentNo); ?>
            <?php if ($fingerId): ?> &bull; <i class="bi bi-fingerprint me-1"></i>FP #<?php echo htmlspecialchars($fingerId); ?><?php endif; ?>
            <?php if ($rfidUid): ?> &bull; <i class="bi bi-credit-card me-1"></i>RFID: <?php echo htmlspecialchars($rfidUid); ?><?php endif; ?>
            <?php if ($faceId): ?> &bull; <i class="bi bi-person-bounding-box me-1"></i>Face Enrolled<?php endif; ?></p>
        </div>
        <div class="col-auto">
            <button class="btn btn-sm btn-outline-secondary rounded px-3 btn-hover-lift" data-bs-toggle="modal" data-bs-target="#editModal"><i class="bi bi-pencil me-1"></i>Edit Profile</button>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card bg-gradient-primary h-100 shadow p-4 d-flex flex-column justify-content-center position-relative">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 64px; height: 64px; background: rgba(161,30,23,0.1);">
                    <i class="bi bi-journal-bookmark-fill fs-2" style="color:#a11e17;"></i>
                </div>
                <div>
                    <h6 class="text-uppercase fw-semibold mb-1 text-secondary tracking-wide ls-wide" style="font-size: 0.75rem;">Enrolled Courses</h6>
                    <h2 class="display-5 fw-bolder mb-0 text-dark" id="stat-courses">—</h2>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card bg-white h-100 shadow border-0 p-4 d-flex flex-column justify-content-center position-relative">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 64px; height: 64px; background: rgba(255,211,10,0.15);">
                    <i class="bi bi-calendar-check-fill fs-2" style="color:#ffd30a;"></i>
                </div>
                <div>
                    <h6 class="text-uppercase fw-semibold mb-1 text-secondary tracking-wide ls-wide" style="font-size: 0.75rem;">Sessions Attended</h6>
                    <h2 class="display-5 fw-bolder mb-0 text-dark" id="stat-total">—</h2>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card bg-white h-100 shadow border-0 p-4 d-flex flex-column justify-content-center position-relative">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" id="rate-icon" style="width: 64px; height: 64px;">
                    <i class="bi bi-percent fs-2" id="rate-icon-i"></i>
                </div>
                <div>
                    <h6 class="text-uppercase fw-semibold mb-1 text-secondary tracking-wide ls-wide" style="font-size: 0.75rem;">Attendance Rate</h6>
                    <h2 class="display-5 fw-bolder mb-0" id="stat-rate">—</h2>
                    <small class="text-muted fw-semibold d-block mt-1" id="rate-detail" style="font-size: 0.65rem;">Select a course above</small>
                </div>
            </div>
        </div>
    </div>
</div>

<ul class="nav nav-tabs mb-3" id="stuTabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-courses"><i class="bi bi-book me-1"></i>My Courses</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-history"><i class="bi bi-clock-history me-1"></i>Attendance History</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-forum"><i class="bi bi-star me-1"></i>Ratings & Forum</button></li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="tab-courses">
        <div id="courses-container">
            <div class="text-center text-muted py-5"><i class="bi bi-arrow-clockwise spin fs-4"></i><br>Loading courses...</div>
        </div>
    </div>
    <div class="tab-pane fade" id="tab-history">
        <div class="card border-0 shadow-none mb-3" style="border: 1px solid #e2e8f0 !important;">
            <div class="card-body py-2">
                <div class="row g-2">
                    <div class="col-md-4"><input type="text" id="h-search" class="form-control form-control-sm" placeholder="Search course..." oninput="loadHistory()"></div>
                    <div class="col-md-3"><select id="h-course" class="form-select form-select-sm" onchange="loadHistory()"><option value="">All Courses</option></select></div>
                    <div class="col-md-2"><input type="date" id="h-from" class="form-control form-control-sm" onchange="loadHistory()"></div>
                    <div class="col-md-2"><input type="date" id="h-to" class="form-control form-control-sm" onchange="loadHistory()"></div>
                    <div class="col-md-1"><select id="h-limit" class="form-select form-select-sm" onchange="loadHistory()"><option value="30">30</option><option value="50" selected>50</option><option value="100">100</option></select></div>
                </div>
            </div>
        </div>
        <div class="card border-0 shadow-none" style="border: 1px solid #e2e8f0 !important;">
            <div class="card-body p-0" style="max-height:450px;overflow-y:auto;">
                <div id="hist-container">
                    <div class="text-center text-muted py-5 small">Switch to this tab to load history.</div>
                </div>
            </div>
        </div>
    </div>
    <div class="tab-pane fade" id="tab-forum">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card border-0 shadow-none h-100" style="border: 1px solid #e2e8f0 !important;">
                    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                        <strong class="small"><i class="bi bi-star me-1"></i>Rate Your Courses</strong>
                    </div>
                    <div class="card-body" style="max-height:400px;overflow-y:auto;" id="student-ratings-list">
                        <div class="text-center text-muted py-3 small"><i class="bi bi-arrow-clockwise spin me-1"></i>Loading courses...</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-none h-100" style="border: 1px solid #e2e8f0 !important;">
                    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                        <strong class="small"><i class="bi bi-chat-dots me-1"></i>Course Discussion</strong>
                        <div id="lang-switcher-forum"></div>
                    </div>
                    <div class="card-body p-0">
                        <div class="p-3 border-bottom">
                            <select id="forum-course-select" class="form-select form-select-sm" onchange="switchForumCourse()">
                                <option value="">Select a course...</option>
                            </select>
                        </div>
                        <div class="px-3 py-2 border-bottom">
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" id="comment-text-course-" placeholder="Write a comment..." onkeydown="if(event.key==='Enter')submitComment('course', document.getElementById('forum-course-select').value, 'comments-course-')">
                                <button class="btn btn-primary btn-sm" onclick="submitComment('course', document.getElementById('forum-course-select').value, 'comments-course-')"><i class="bi bi-send"></i></button>
                            </div>
                        </div>
                        <div id="comments-course-" style="max-height:350px;overflow-y:auto;">
                            <div class="text-center text-muted py-4 small">Select a course to view discussion.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#ffd30a,#e6b800);">
                <h6 class="modal-title" style="color:#1e293b;"><i class="bi bi-pencil me-2"></i>Edit Profile</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <div class="avatar-ring mx-auto" onclick="document.getElementById('edit-photo-upload').click()">
                        <?php if ($profilePhoto): ?>
                        <img id="edit-photo-preview" src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Preview">
                        <?php else: ?>
                        <div id="edit-photo-preview" class="initials-avatar"><?php echo htmlspecialchars($initials ?: '?'); ?></div>
                        <?php endif; ?>
                        <div class="cam-overlay"><i class="bi bi-camera-fill"></i></div>
            <div class="modal-footer">
                <button class="btn btn-warning btn-sm w-100 fw-bold btn-hover-lift btn-hover-ripple" onclick="changePassword()"><i class="bi bi-key me-1"></i>Set Password</button>
                <button class="btn btn-link text-decoration-underline text-reset ps-0" onclick="window.location.href='/csc2052/logout.php'">Cancel and Logout</button>
            </div>
                    <input type="file" id="edit-photo-upload" class="d-none" accept="image/*" onchange="previewEditPhoto(this)">
                    <div class="small text-muted mt-1">Click to change photo</div>
                </div>
                <div class="mb-2"><label class="form-label small fw-bold">Full Name</label><input type="text" id="edit-name" class="form-control form-control-sm" value="<?php echo htmlspecialchars($studentName); ?>"></div>
                <hr>
                <div class="mb-2"><label class="form-label small fw-bold">Current Password</label><input type="password" id="edit-cur" class="form-control form-control-sm" placeholder="Required to change password"></div>
                <div class="mb-2"><label class="form-label small fw-bold">New Password</label><input type="password" id="edit-new" class="form-control form-control-sm" placeholder="Leave blank to keep"></div>
                <div id="edit-msg" class="small"></div>
            </div>
            <div class="modal-footer py-2">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success btn-sm fw-semibold" onclick="saveProfile()"><i class="bi bi-check-lg me-1"></i>Save</button>
            </div>
        </div>
    </div>
</div>

<script>
let courseData = [];

function setAvatar(src) {
    const avatar = document.getElementById('profile-img');
    const editAvatar = document.getElementById('edit-photo-preview');
    if (avatar.tagName === 'IMG') {
        avatar.src = src;
    } else {
        const img = document.createElement('img');
        img.src = src; img.alt = 'Photo'; img.style.width = '100%'; img.style.height = '100%'; img.style.objectFit = 'cover';
        img.style.borderRadius = '50%'; img.style.border = '2px solid #fff';
        avatar.replaceWith(img); img.id = 'profile-img';
    }
    if (editAvatar) {
        if (editAvatar.tagName === 'IMG') { editAvatar.src = src; }
        else { const img2 = document.createElement('img'); img2.src = src; img2.style.width='100%'; img2.style.height='100%'; img2.style.borderRadius='50%'; img2.style.border='2px solid #fff'; editAvatar.replaceWith(img2); img2.id='edit-photo-preview'; }
    }
}

function refreshStats() {
    const selected = document.getElementById('stat-course').value;
    let totalAtt = 0, totalSess = 0, n = 0;
    courseData.forEach(c => {
        if (!selected || selected === c.course_code) {
            totalAtt += c.attended || 0;
            totalSess += c.total_sessions || 0;
            n++;
        }
    });
    document.getElementById('stat-total').textContent = totalAtt;
    const overall = totalSess > 0 ? Math.round(totalAtt / totalSess * 1000) / 10 : 0;
    const rateEl = document.getElementById('stat-rate');
    rateEl.textContent = overall + '%';
    rateEl.className = 'display-5 fw-bolder mb-0 text-dark';
    const ri = document.getElementById('rate-icon');
    ri.className = 'rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 ' + (overall >= 80 ? 'bg-success bg-opacity-10' : (overall >= 50 ? 'bg-warning bg-opacity-10' : 'bg-danger bg-opacity-10'));
    document.getElementById('rate-icon-i').className = 'bi bi-percent fs-2 ' + (overall >= 80 ? 'text-success' : (overall >= 50 ? 'text-warning' : 'text-danger'));
    document.getElementById('rate-detail').textContent = selected ? selected + ' — ' + totalAtt + '/' + totalSess + ' sessions' : 'All courses — ' + totalAtt + '/' + totalSess + ' sessions';
}

function loadCourses() {
    fetch('/csc2052/api/student.php?action=get_my_courses')
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('courses-container');
            if (!data.courses || data.courses.length === 0) {
                // Check if there are attendance logs but no enrollments
                fetch('/csc2052/api/student.php?action=get_my_attendance&limit=5')
                    .then(r => r.json())
                    .then(attData => {
                        if (attData.logs && attData.logs.length > 0) {
                            el.innerHTML = '<div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">' +
                                '<i class="bi bi-exclamation-circle fs-1 d-block mb-2"></i>' +
                                '<p class="mb-0">You have attendance records but are not enrolled in any courses.</p>' +
                                '<p class="small">Contact your teacher or admin to enroll you in courses.</p>' +
                                '<hr><p class="small text-muted">Recent activity:</p>' +
                                '<p class="small">' + attData.logs.length + ' recent attendance record(s) found.</p></div></div>';
                        } else {
                            el.innerHTML = '<div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5"><i class="bi bi-journal-x fs-1 d-block mb-2"></i><p class="mb-0">Not enrolled in any courses yet.</p></div></div>';
                        }
                    })
                    .catch(() => {
                        el.innerHTML = '<div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5"><i class="bi bi-journal-x fs-1 d-block mb-2"></i><p class="mb-0">Not enrolled in any courses yet.</p></div></div>';
                    });
                document.getElementById('stat-courses').textContent = '0';
                document.getElementById('stat-total').textContent = '0';
                document.getElementById('stat-rate').textContent = '0%';
                return;
            }
            courseData = data.courses;
            if (data.tier) {
                const tierEl = document.getElementById('tier-badge');
                if (tierEl) tierEl.textContent = 'Tier: ' + data.tier;
            }
            let total = 0, n = 0;
            data.courses.forEach(c => { total += c.attended || 0; if (c.total_sessions > 0) n++; });
            let h = '<div class="row g-3">';
            data.courses.forEach(c => {
                const pct = c.percentage || 0;
                const bar = pct >= 80 ? 'bg-success' : (pct >= 50 ? 'bg-warning' : 'bg-danger');
                const pctColor = pct >= 80 ? 'text-success' : (pct >= 50 ? 'text-warning' : 'text-danger');
                h += '<div class="col-md-6 col-lg-4"><div class="course-card p-3 h-100">' +
                    '<div class="d-flex justify-content-between align-items-start mb-2">' +
                        '<div><h6 class="fw-bold text-primary mb-0">' + esc(c.course_code) + '</h6><div class="small text-muted">' + esc(c.course_name || '') + '</div></div>' +
                        '<span class="badge bg-light ' + pctColor + ' fw-bold badge-pill">' + pct + '%</span>' +
                    '</div>' +
                    '<div class="small text-muted mb-2"><i class="bi bi-calendar3 me-1"></i>Attended: <strong class="text-dark">' + c.attended + '</strong> of ' + c.total_sessions + ' sessions</div>' +
                    '<div class="progress" style="height:10px;background:#e2e8f0;">' +
                        '<div class="progress-bar ' + bar + '" style="width:' + pct + '%;"></div>' +
                    '</div>' +
                '</div></div>';
            });
            h += '</div>';
            el.innerHTML = h;
            document.getElementById('stat-courses').textContent = data.courses.length;
            document.getElementById('stat-total').textContent = total;
            const overall = n > 0 ? Math.round(data.courses.reduce((s, c) => s + (c.percentage || 0), 0) / n * 10) / 10 : 0;
            const rateEl = document.getElementById('stat-rate');
            rateEl.textContent = overall + '%';
            rateEl.className = 'display-5 fw-bolder mb-0 ' + (overall >= 80 ? 'text-success' : (overall >= 50 ? 'text-warning' : 'text-danger'));
            document.getElementById('rate-detail').textContent = 'Average across ' + data.courses.length + ' courses';
            populateCourseSelect(data.courses);
            refreshStats();
        })
        .catch(() => { document.getElementById('courses-container').innerHTML = '<div class="text-center text-danger py-5"><i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i><p class="mb-0 small">Failed to load courses.</p></div>'; });
}

function populateCourseSelect(courses) {
    const sel = document.getElementById('stat-course');
    const histSel = document.getElementById('h-course');
    sel.innerHTML = '<option value="">All Courses</option>';
    histSel.innerHTML = '<option value="">All Courses</option>';
    courses.forEach(c => {
        sel.innerHTML += '<option value="' + esc(c.course_code) + '">' + esc(c.course_code) + '</option>';
        histSel.innerHTML += '<option value="' + esc(c.course_code) + '">' + esc(c.course_code) + '</option>';
    });
}

function loadHistory() {
    const el = document.getElementById('hist-container');
    el.innerHTML = '<div class="text-center py-4"><i class="bi bi-arrow-clockwise spin fs-4"></i></div>';
    let url = '/csc2052/api/student.php?action=get_my_attendance&limit=' + document.getElementById('h-limit').value;
    if (document.getElementById('h-course').value) url += '&course_code=' + encodeURIComponent(document.getElementById('h-course').value);
    if (document.getElementById('h-from').value) url += '&date_from=' + encodeURIComponent(document.getElementById('h-from').value);
    if (document.getElementById('h-to').value) url += '&date_to=' + encodeURIComponent(document.getElementById('h-to').value);
    if (document.getElementById('h-search').value) url += '&search=' + encodeURIComponent(document.getElementById('h-search').value);
    fetch(url).then(r => r.json()).then(data => {
    if (!data.logs || data.logs.length === 0) {
        el.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2"></i><p class="mb-0 small">No attendance records found.</p></div>';
        return;
    }
        let h = '<table class="table table-hover table-sm mb-0 align-middle hist-table"><thead class="table-light"><tr><th class="ps-3">Course</th><th>Date</th><th>Time</th><th>Method</th></tr></thead><tbody>';
        data.logs.forEach(log => {
            const ts = new Date(log.timestamp.replace(' ', 'T'));
            const d = ts.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
            const t = ts.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
            const mb = (log.modality === 'fingerprint') ? 'bg-success' : (log.modality === 'rfid') ? 'bg-info' : (log.modality === 'face') ? 'bg-warning text-dark' : 'bg-secondary';
            h += '<tr><td class="ps-3"><span class="badge bg-dark">' + esc(log.course_code) + '</span> <span class="small text-muted">' + esc(log.course_name || '') + '</span></td><td class="small">' + d + '</td><td class="small">' + t + '</td><td><span class="badge ' + mb + ' badge-pill">' + esc(log.modality || 'manual') + '</span></td></tr>';
        });
        h += '</tbody></table>';
        el.innerHTML = h;
    }).catch(() => { el.innerHTML = '<div class="text-center text-danger py-3 small"><i class="bi bi-exclamation-triangle me-1"></i>Failed to load.</div>'; });
}

function uploadPhoto(input) {
    if (!input.files.length) return;
    const fd = new FormData(); fd.append('action', 'upload_my_photo'); fd.append('photo', input.files[0]);
    fetch('/csc2052/api/student.php', { method: 'POST', headers: {'X-CSRF-TOKEN': getCsrf()}, body: fd }).then(r => r.json()).then(data => {
        if (data.status === 'success') setAvatar(data.photo_url + '?t=' + Date.now());
        else alert('Upload failed: ' + (data.message || ''));
    }).catch(() => alert('Upload failed.'));
}

function previewEditPhoto(input) {
    if (input.files.length) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('edit-photo-preview');
            if (preview.tagName === 'IMG') preview.src = e.target.result;
            else {
                const img = document.createElement('img'); img.src = e.target.result; img.style.width = '100%'; img.style.height = '100%'; img.style.borderRadius = '50%'; img.style.border = '2px solid #fff';
                preview.replaceWith(img); img.id = 'edit-photo-preview';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function saveProfile() {
    const msg = document.getElementById('edit-msg');
    msg.innerHTML = '<span class="text-primary"><i class="bi bi-arrow-clockwise spin me-1"></i>Saving...</span>';
    const fd = new FormData(); fd.append('action', 'update_my_profile');
    fd.append('student_name', document.getElementById('edit-name').value);
    const cur = document.getElementById('edit-cur').value;
    const nw = document.getElementById('edit-new').value;
    if (cur) fd.append('current_password', cur);
    if (nw) fd.append('new_password', nw);
    fetch('/csc2052/api/student.php', { method: 'POST', headers: {'X-CSRF-TOKEN': getCsrf()}, body: fd }).then(r => r.json()).then(data => {
        if (data.status === 'success') { msg.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Saved! Reloading...</span>'; setTimeout(() => location.reload(), 800); }
        else msg.innerHTML = '<span class="text-danger">' + esc(data.message || 'Failed') + '</span>';
    }).catch(() => { msg.innerHTML = '<span class="text-danger">Network error.</span>'; });
}

function changePassword() {
    const msg = document.getElementById('pw-msg');
    const nw = document.getElementById('pw-new').value;
    const conf = document.getElementById('pw-conf').value;
    if (nw.length < 6) { msg.innerHTML = '<span class="text-danger">Minimum 6 characters.</span>'; return; }
    if (nw !== conf) { msg.innerHTML = '<span class="text-danger">Passwords do not match.</span>'; return; }
    msg.innerHTML = '<span class="text-primary"><i class="bi bi-arrow-clockwise spin me-1"></i>Updating...</span>';
    const fd = new FormData(); fd.append('action', 'update_my_profile');
    fd.append('student_name', '<?php echo addslashes($studentName); ?>');
    // For first-time setup, don't send current_password (it contains student_no as placeholder)
    // Only send current_password if user is changing an existing password
    const cur = document.getElementById('pw-cur').value;
    if (cur && cur !== '<?php echo $studentNo; ?>') {
        fd.append('current_password', cur);
    }
    fd.append('new_password', nw);
    fetch('/csc2052/api/student.php', { method: 'POST', headers: {'X-CSRF-TOKEN': getCsrf()}, body: fd }).then(r => r.json()).then(data => {
        if (data.status === 'success') { msg.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Updated! Reloading...</span>'; setTimeout(() => location.reload(), 800); }
        else msg.innerHTML = '<span class="text-danger">' + esc(data.message || 'Failed') + '</span>';
    });
}

function loadStudentRatings() {
    const el = document.getElementById('student-ratings-list');
    const courses = courseData || [];
    if (courses.length === 0) {
        el.innerHTML = '<div class="text-center text-muted py-3 small">Enroll in courses first to rate them.</div>';
        return;
    }
    let html = '';
    courses.forEach(c => {
        const summaryId = 'rating-summary-course-' + c.course_code;
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

function switchForumCourse() {
    const sel = document.getElementById('forum-course-select');
    const course = sel.value;
    const commentContainer = 'comments-course-';
    const container = document.getElementById(commentContainer);
    if (!course) {
        container.innerHTML = '<div class="text-center text-muted py-4 small">Select a course to view discussion.</div>';
        return;
    }
    // Update comment input target
    const input = document.getElementById('comment-text-course-');
    if (input) {
        input.placeholder = 'Discuss ' + course + '...';
        input.onkeydown = function(e) {
            if (e.key === 'Enter') submitComment('course', course, commentContainer);
        };
    }
    loadComments('course', course, commentContainer);
}

function initStudentForum() {
    const sel = document.getElementById('forum-course-select');
    const courses = courseData || [];
    sel.innerHTML = '<option value="">Select a course...</option>';
    courses.forEach(c => {
        sel.innerHTML += '<option value="' + esc(c.course_code) + '">' + esc(c.course_code) + ' — ' + esc(c.course_name || '') + '</option>';
    });
    renderLangSwitcher('lang-switcher-forum');
}

// Override the placeholder loadCommentsForCurrent
window.loadCommentsForCurrent = function() {
    const sel = document.getElementById('forum-course-select');
    if (sel && sel.value) {
        loadComments('course', sel.value, 'comments-course-');
    }
};

document.addEventListener('DOMContentLoaded', () => {
    loadCourses();
    document.querySelector('[data-bs-target="#tab-history"]').addEventListener('shown.bs.tab', loadHistory);
    document.querySelector('[data-bs-target="#tab-forum"]').addEventListener('shown.bs.tab', () => {
        loadStudentRatings();
        initStudentForum();
    });
});
</script>
<?php require 'includes/footer.php'; ?>

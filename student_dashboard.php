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
            $profilePhoto = $profile['profile_photo'];
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
    <link rel="icon" type="image/svg+xml" href="<?php echo htmlspecialchars(asset_url('assets/favicon.svg'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('css/vendor/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('css/vendor/bootstrap-icons.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('css/tables.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('css/cards.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <script src="<?php echo htmlspecialchars(asset_url('js/html5-qrcode.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(asset_url('js/qrcode.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <style>
        body { background: #f0f4f9; }
        .student-hero { background: #ffffff; border: 1px solid #dadce0; border-radius: 12px; color: #202124; box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 4px 16px rgba(0,0,0,0.04); }
        .avatar-ring { width: 70px; height: 70px; border-radius: 50% !important; padding: 2px; background: #dadce0; position: relative; cursor: pointer; overflow: hidden; }
        .avatar-ring img { width: 100% !important; height: 100% !important; border-radius: 50% !important; object-fit: cover; border: 2px solid #fff; }
        .avatar-ring .initials-avatar { width: 100%; height: 100%; border-radius: 50% !important; background: #e8f0fe; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; font-weight: 600; color: #1a73e8; border: 2px solid #fff; }
        .avatar-ring .cam-overlay { position: absolute; bottom: 0; right: 0; width: 22px; height: 22px; background: #fff; border-radius: 50% !important; display: flex; align-items: center; justify-content: center; border: 1px solid #dadce0; box-shadow: 0 1px 2px rgba(0,0,0,0.08); }
        .avatar-ring .cam-overlay i { font-size: 10px; color: #5f6368; }
        .stat-card { border-radius: 12px; overflow: hidden; border: 1px solid #dadce0; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .stat-select { border-radius: 8px; }
        .ls-wide { letter-spacing: 0.02em; }
        .course-card { border-radius: 12px; overflow: hidden; border: 1px solid #dadce0; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.04); transition: box-shadow 0.2s; }
        .course-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .course-card .progress { border-radius: 8px; overflow: hidden; }
        .course-card .progress-bar { border-radius: 8px; }
        .nav-tabs { border-bottom: 1px solid #dadce0; }
        .nav-tabs .nav-link { border: none; color: #5f6368; font-weight: 500; padding: 0.75rem 1rem; border-radius: 0; margin-bottom: -1px; }
        .nav-tabs .nav-link:hover { color: #202124; background: #f8f9fa; }
        .nav-tabs .nav-link.active { color: #1a73e8; border-bottom: 2px solid #1a73e8; background: transparent; }
        .hist-table th { position: sticky; top: 0; z-index: 1; background: #fff; border-bottom: 1px solid #dadce0; }
        .badge-pill { border-radius: 8px !important; }
        #ai-assistant-container { display: none !important; }
        .btn-outline-secondary { border-color: #dadce0; color: #3c4043; border-radius: 100px; }
        .btn-outline-secondary:hover { background: #f8f9fa; border-color: #c4c7cc; }
        
        /* Dynamic QR Attendance Scanner Styles */
        .stepper-container {
            padding: 0.5rem 1.5rem;
        }
        .step-circle {
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            z-index: 3;
        }
        .step-circle.active {
            border-color: var(--clr-primary) !important;
            color: var(--clr-primary) !important;
            background-color: rgba(66, 133, 244, 0.08) !important;
            transform: scale(1.15);
            box-shadow: 0 0 12px rgba(66, 133, 244, 0.3);
        }
        .step-circle.completed {
            border-color: var(--clr-success) !important;
            color: #fff !important;
            background-color: var(--clr-success) !important;
            transform: scale(1.05);
            box-shadow: 0 0 10px rgba(52, 168, 83, 0.2);
        }
        .step-circle.completed::after {
            content: "\F26B";
            font-family: "bootstrap-icons";
            font-size: 1.2rem;
            position: absolute;
            color: #fff;
        }
        .step-circle.completed span {
            display: none;
        }
        .scanner-window-wrapper {
            position: relative;
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.08);
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            box-shadow: var(--shadow-md);
        }
        .scanner-viewport-mount {
            position: relative;
            width: 100%;
            aspect-ratio: 4 / 3;
            background: #0f172a;
            border-radius: var(--radius-md);
            overflow: hidden;
        }
        .scanner-laser-line {
            position: absolute;
            left: 5%;
            right: 5%;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--clr-primary), transparent);
            box-shadow: 0 0 8px var(--clr-primary);
            animation: laserMove 2.5s ease-in-out infinite;
            z-index: 10;
            pointer-events: none;
            display: none;
        }
        @keyframes laserMove {
            0% { top: 10%; }
            50% { top: 90%; }
            100% { top: 10%; }
        }
        .scanner-corner {
            position: absolute;
            width: 28px;
            height: 28px;
            border: 4px solid transparent;
            z-index: 11;
            pointer-events: none;
            display: none;
        }
        .scanner-corner-tr { top: 16px; right: 16px; border-top-color: var(--clr-primary); border-right-color: var(--clr-primary); }
        .scanner-corner-tl { top: 16px; left: 16px; border-top-color: var(--clr-primary); border-left-color: var(--clr-primary); }
        .scanner-corner-br { bottom: 16px; right: 16px; border-bottom-color: var(--clr-primary); border-right-color: var(--clr-primary); }
        .scanner-corner-bl { bottom: 16px; left: 16px; border-bottom-color: var(--clr-primary); border-left-color: var(--clr-primary); }
        
        #qr-reader {
            width: 100% !important;
            height: 100% !important;
            border: none !important;
            background: transparent !important;
        }
        #qr-reader video {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
        }
        #qr-reader__dashboard {
            display: none !important;
        }
        .status-badge-glow {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 8px currentColor;
        }
        .status-card-feedback {
            border-radius: var(--radius-md);
            border: 1px solid rgba(0, 0, 0, 0.05);
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(8px);
            transition: all 0.3s ease;
        }
        .success-checkmark-bounce {
            animation: bounceIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }
        @keyframes bounceIn {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.1); opacity: 0.9; }
            70% { transform: scale(0.9); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }
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
                <div class="mb-2"><label class="form-label small fw-bold" for="pw-new">New Password</label><input type="password" id="pw-new" class="form-control form-control-sm" minlength="6"></div>
                <div class="mb-2"><label class="form-label small fw-bold" for="pw-conf">Confirm</label><input type="password" id="pw-conf" class="form-control form-control-sm" minlength="6"></div>
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

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-graph-up me-2 text-primary"></i>Overview</h5>
    <div style="width: 250px;">
        <select id="stat-course" class="form-select form-select-sm shadow-sm" style="border-radius:8px; border-color: #e2e8f0;" onchange="refreshStats()">
            <option value="">All Courses</option>
        </select>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card h-100 p-4 d-flex flex-column justify-content-center position-relative">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 56px; height: 56px; background: #e8f0fe;">
                    <i class="bi bi-journal-bookmark-fill fs-4" style="color:#1a73e8;"></i>
                </div>
                <div>
                    <h6 class="text-uppercase fw-semibold mb-1 text-secondary tracking-wide ls-wide" style="font-size: 0.75rem;">Enrolled Courses</h6>
                    <h2 class="display-5 fw-bolder mb-0 text-dark" id="stat-courses">—</h2>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card h-100 p-4 d-flex flex-column justify-content-center position-relative">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 56px; height: 56px; background: #fce8e6;">
                    <i class="bi bi-calendar-check-fill fs-4" style="color:#d93025;"></i>
                </div>
                <div>
                    <h6 class="text-uppercase fw-semibold mb-1 text-secondary tracking-wide ls-wide" style="font-size: 0.75rem;">Sessions Attended</h6>
                    <h2 class="display-5 fw-bolder mb-0 text-dark" id="stat-total">—</h2>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card h-100 p-4 d-flex flex-column justify-content-center position-relative">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" id="rate-icon" style="width: 56px; height: 56px;">
                    <i class="bi bi-percent fs-4" id="rate-icon-i"></i>
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
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-qr-scan"><i class="bi bi-qr-code-scan me-1"></i>Scan QR</button></li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="tab-courses">
        <div id="courses-container">
            <div class="text-center text-muted py-5"><i class="bi bi-arrow-clockwise spin fs-4"></i><br>Loading courses...</div>
        </div>
    </div>
    <div class="tab-pane fade" id="tab-history">
        <div class="card border-0 shadow-none mb-3" style="border: 1px solid #dadce0 !important; border-radius:12px;">
            <div class="card-body py-2">
                <div class="row g-2">
                    <div class="col-md-4"><input type="text" id="h-search" class="form-control form-control-sm" placeholder="Search course..." style="border-radius:8px;" oninput="loadHistory()"></div>
                    <div class="col-md-3"><select id="h-course" class="form-select form-select-sm" style="border-radius:8px;" onchange="loadHistory()"><option value="">All Courses</option></select></div>
                    <div class="col-md-2"><input type="date" id="h-from" class="form-control form-control-sm" style="border-radius:8px;" onchange="loadHistory()"></div>
                    <div class="col-md-2"><input type="date" id="h-to" class="form-control form-control-sm" style="border-radius:8px;" onchange="loadHistory()"></div>
                    <div class="col-md-1"><select id="h-limit" class="form-select form-select-sm" style="border-radius:8px;" onchange="loadHistory()"><option value="30">30</option><option value="50" selected>50</option><option value="100">100</option></select></div>
                </div>
            </div>
        </div>
        <div class="card border-0 shadow-none" style="border: 1px solid #dadce0 !important; border-radius:12px;">
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
                <div class="card border-0 shadow-none h-100" style="border: 1px solid #dadce0 !important; border-radius:12px;">
                    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center" style="border-radius:12px 12px 0 0;">
                        <strong class="small"><i class="bi bi-star me-1"></i>Rate Your Courses</strong>
                    </div>
                    <div class="card-body" style="max-height:400px;overflow-y:auto;" id="student-ratings-list">
                        <div class="text-center text-muted py-3 small"><i class="bi bi-arrow-clockwise spin me-1"></i>Loading courses...</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-none h-100" style="border: 1px solid #dadce0 !important; border-radius:12px;">
                    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center" style="border-radius:12px 12px 0 0;">
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

    <!-- Scan QR Code Tab Pane -->
    <div class="tab-pane fade" id="tab-qr-scan">
        <!-- Dual-Step Progress Stepper -->
        <div class="card border-0 mb-4 p-4" style="border: 1px solid rgba(255, 255, 255, 0.45) !important; border-radius:12px; background: rgba(255, 255, 255, 0.65); backdrop-filter: blur(24px);">
            <div class="row align-items-center justify-content-center">
                <div class="col-md-10 col-lg-8">
                    <div class="d-flex align-items-center justify-content-between position-relative stepper-container">
                        <!-- Background connection line -->
                        <div class="stepper-line position-absolute top-50 start-0 translate-middle-y w-100" style="height: 4px; background: #e2e8f0; z-index: 1;">
                            <div id="stepper-progress-line" class="h-100" style="width: 0%; background: var(--clr-primary); transition: width 0.4s ease;"></div>
                        </div>
                        <!-- Step 1 -->
                        <div class="step-item d-flex flex-column align-items-center position-relative" style="z-index: 2;">
                            <div id="step-1-indicator" class="step-circle rounded-circle bg-white d-flex align-items-center justify-content-center border border-3 border-secondary" style="width: 48px; height: 48px; font-weight: 700; color: var(--clr-muted); font-size: 1.1rem; box-shadow: var(--shadow-sm); transition: all 0.3s ease;">
                                <span id="step-1-text">1</span>
                            </div>
                            <div class="step-label mt-2 small fw-bold text-secondary" id="step-1-label">Scan First QR</div>
                        </div>
                        <!-- Step 2 -->
                        <div class="step-item d-flex flex-column align-items-center position-relative" style="z-index: 2;">
                            <div id="step-2-indicator" class="step-circle rounded-circle bg-white d-flex align-items-center justify-content-center border border-3 border-secondary" style="width: 48px; height: 48px; font-weight: 700; color: var(--clr-muted); font-size: 1.1rem; box-shadow: var(--shadow-sm); transition: all 0.3s ease;">
                                <span id="step-2-text">2</span>
                            </div>
                            <div class="step-label mt-2 small fw-bold text-secondary" id="step-2-label">Scan Next QR</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Column: Settings and Instructions -->
            <div class="col-lg-5 col-md-6">
                <!-- Status Panel -->
                <div class="card border-0 mb-4 status-card-feedback p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-shield-lock me-2 text-primary"></i>Verification Status</h6>
                        <span class="badge bg-secondary d-flex align-items-center gap-1 py-1.5 px-2.5" id="scanner-state-badge">
                            <span class="status-badge-glow bg-secondary me-1" id="scanner-state-glow"></span>
                            <span id="scanner-state-text">Inactive</span>
                        </span>
                    </div>
                    <div id="scanner-feedback-box" class="py-3 px-3 rounded text-center small bg-light text-muted">
                        <i class="bi bi-camera-video-off fs-2 d-block mb-2 text-secondary"></i>
                        <p class="mb-0 fw-semibold">Scanner is currently offline.</p>
                        <p class="mb-0 text-muted mt-1" style="font-size:0.75rem;">Select your camera and click "Start Scanner" to begin the secure 2-step verification process.</p>
                    </div>
                </div>

                <!-- Controls -->
                <div class="card border-0 mb-4 p-4">
                    <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-sliders me-2 text-primary"></i>Scanner Configuration</h6>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary" for="qr-camera-select">Capture Source</label>
                        <select id="qr-camera-select" class="form-select form-select-sm" style="border-radius:8px;">
                            <option value="">Detecting cameras...</option>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <button id="btn-start-scanner" class="btn btn-primary btn-sm w-100 fw-bold btn-hover-lift" onclick="startScanner()">
                                <i class="bi bi-camera-video me-1"></i>Start Scanner
                            </button>
                        </div>
                        <div class="col-6">
                            <button id="btn-stop-scanner" class="btn btn-outline-secondary btn-sm w-100 fw-bold btn-hover-lift" onclick="stopScanner()" disabled>
                                <i class="bi bi-camera-video-off me-1"></i>Stop
                            </button>
                        </div>
                    </div>
                    <div class="mt-2">
                        <button class="btn btn-outline-info btn-sm w-100 fw-bold" onclick="generateTestQr()">
                            <i class="bi bi-qr-code me-1"></i>Show Test QR (for troubleshooting)
                        </button>
                        <div id="test-qr-container" class="text-center mt-2" style="display:none;">
                            <canvas id="test-qr-canvas" width="200" height="200" style="border:2px solid #ddd;border-radius:8px;"></canvas>
                            
                        </div>
                    </div>
                </div>

                <!-- Instructions -->
                <div class="card border-0 p-4">
                    <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-info-circle me-2 text-primary"></i>Anti-Proxy Protocol</h6>
                    <ul class="list-unstyled mb-0 text-secondary" style="font-size:0.8rem; line-height:1.6;">
                        <li class="mb-2 d-flex align-items-start gap-2">
                            <i class="bi bi-1-circle text-primary mt-0.5"></i>
                            <span>Align and scan the live QR code shown on the lecturer's multimedia display.</span>
                        </li>
                        <li class="mb-2 d-flex align-items-start gap-2">
                            <i class="bi bi-2-circle text-primary mt-0.5"></i>
                            <span>Wait 5 seconds for the QR code to <strong>morph organically</strong> into the next slot pattern.</span>
                        </li>
                        <li class="mb-2 d-flex align-items-start gap-2">
                            <i class="bi bi-3-circle text-primary mt-0.5"></i>
                            <span>Scan the second consecutive QR code pattern to finalize verification.</span>
                        </li>
                        <li class="d-flex align-items-start gap-2 text-muted">
                            <i class="bi bi-exclamation-triangle-fill text-warning mt-0.5"></i>
                            <span>Scanning non-consecutive frames (e.g. sharing pictures with peers) will invalidate step 1 progress and restart your session.</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Right Column: Live Viewfinder -->
            <div class="col-lg-7 col-md-6">
                <div class="scanner-window-wrapper p-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <strong class="small text-muted"><i class="bi bi-viewfinder me-1"></i>Live Viewfinder</strong>
                        <small class="text-primary fw-bold" id="scanner-fps-label" style="display:none; font-size:0.75rem;"><i class="bi bi-activity me-1"></i>Scanning active</small>
                    </div>
                    <div class="scanner-viewport-mount">
                        <!-- Custom high-tech visual corner markers -->
                        <div class="scanner-corner scanner-corner-tl"></div>
                        <div class="scanner-corner scanner-corner-tr"></div>
                        <div class="scanner-corner scanner-corner-bl"></div>
                        <div class="scanner-corner scanner-corner-br"></div>
                        <div class="scanner-laser-line"></div>
                        
                        <!-- html5-qrcode target -->
                        <div id="qr-reader"></div>
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
                <div class="mb-2"><label class="form-label small fw-bold" for="edit-name">Full Name</label><input type="text" id="edit-name" class="form-control form-control-sm" value="<?php echo htmlspecialchars($studentName); ?>"></div>
                <div class="mb-2"><label class="form-label small fw-bold" for="edit-email">Email (Login ID)</label><input type="email" id="edit-email" class="form-control form-control-sm" value="<?php echo htmlspecialchars($studentNo); ?>" disabled></div>
                <div class="mb-2"><label class="form-label small fw-bold" for="edit-cur">Current Password</label><input type="password" id="edit-cur" class="form-control form-control-sm" placeholder="Required to change password"></div>
                <div class="mb-2"><label class="form-label small fw-bold" for="edit-new">New Password</label><input type="password" id="edit-new" class="form-control form-control-sm" placeholder="Leave blank to keep"></div>
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
    if (!avatar) return;
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
    fetch('/csc2052/api/student.php?action=get_my_courses&_=' + Date.now())
        .then(function(r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(data => {
            const el = document.getElementById('courses-container');
            if (data.status === 'error') {
                console.error('[Courses] API error:', data.message);
                el.innerHTML = '<div class="text-center text-danger py-5"><i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i><p class="mb-0 small">' + esc(data.message) + '</p></div>';
                return;
            }
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
                            el.innerHTML = '<div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">' +
                                '<i class="bi bi-journal-x fs-1 d-block mb-2"></i><p class="mb-0">Not enrolled in any courses yet.</p>' +
                                '<p class="small text-muted">If you were expecting courses, contact your teacher or admin.</p></div></div>';
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
            courseData = data.courses || [];
            if (data.tier) {
                const tierEl = document.getElementById('tier-badge');
                if (tierEl) tierEl.textContent = 'Tier: ' + data.tier;
            }
            let total = 0, n = 0;
            courseData.forEach(c => { total += c.attended || 0; if (c.total_sessions > 0) n++; });
            let h = '<div class="row g-3">';
            courseData.forEach(c => {
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
            document.getElementById('stat-courses').textContent = courseData.length;
            document.getElementById('stat-total').textContent = total;
            const overall = n > 0 ? Math.round(courseData.reduce((s, c) => s + (c.percentage || 0), 0) / n * 10) / 10 : 0;
            const rateEl = document.getElementById('stat-rate');
            rateEl.textContent = overall + '%';
            rateEl.className = 'display-5 fw-bolder mb-0 ' + (overall >= 80 ? 'text-success' : (overall >= 50 ? 'text-warning' : 'text-danger'));
            document.getElementById('rate-detail').textContent = 'Average across ' + courseData.length + ' courses';
            populateCourseSelect(courseData);
            refreshStats();
        })
        .catch(function(e) { console.error('[Courses]', e); var el = document.getElementById('courses-container'); if (el) el.innerHTML = '<div class="text-center text-danger py-5"><i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i><p class="mb-0 small">Failed to load courses. <span class="text-muted">(' + e.message + ')</span></p></div>'; });
}

function populateCourseSelect(courses) {
    const seen = {};
    const sel = document.getElementById('stat-course');
    const histSel = document.getElementById('h-course');
    sel.innerHTML = '<option value="">All Courses</option>';
    histSel.innerHTML = '<option value="">All Courses</option>';
    courses.forEach(c => {
        const code = c.course_code;
        if (seen[code]) return;
        seen[code] = true;
        sel.innerHTML += '<option value="' + esc(code) + '">' + esc(code) + '</option>';
        histSel.innerHTML += '<option value="' + esc(code) + '">' + esc(code) + '</option>';
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
    fetch(url).then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    }).then(data => {
        if (!data.logs || data.logs.length === 0) {
            el.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2"></i><p class="mb-0 small">No attendance records found.</p></div>';
            return;
        }
        let h = '<table class="table table-hover table-sm mb-0 align-middle hist-table"><thead class="table-light"><tr><th class="ps-3">Course</th><th>Date</th><th>Time</th><th>Method</th><th>Rating</th><th>Comment</th></tr></thead><tbody>';
        data.logs.forEach(log => {
            const ts = new Date(log.timestamp.replace(' ', 'T'));
            const d = ts.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
            const t = ts.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
            const mb = (log.modality === 'fingerprint') ? 'bg-success' : (log.modality === 'rfid') ? 'bg-info' : (log.modality === 'face') ? 'bg-warning text-dark' : 'bg-secondary';
            const stars = log.rating ? '★'.repeat(log.rating) + '☆'.repeat(5 - log.rating) : '';
            const aid = log.id;
            h += '<tr><td class="ps-3"><span class="badge bg-dark">' + esc(log.course_code) + '</span> <span class="small text-muted">' + esc(log.course_name || '') + '</span></td><td class="small">' + d + '</td><td class="small">' + t + '</td><td><span class="badge ' + mb + ' badge-pill">' + esc(log.modality || 'manual') + '</span></td>' +
                '<td class="small" style="cursor:pointer;white-space:nowrap;" onclick="rateAttendance(' + aid + ', this)">' +
                (stars ? '<span class="text-warning">' + stars + '</span>' : '<span class="text-muted small">—</span>') +
                '</td>' +
                '<td class="small" style="max-width:160px;">' +
                (log.comment ? '<span>' + esc(log.comment) + '</span>' : '<span class="text-muted">—</span>') +
                ' <i class="bi bi-pencil text-primary" style="cursor:pointer;font-size:0.7rem;" onclick="rateAttendance(' + aid + ', null)" title="Add comment"></i>' +
                '</td></tr>';
        });
        h += '</tbody></table>';
        el.innerHTML = h;
    }).catch(() => { el.innerHTML = '<div class="text-center text-danger py-3 small"><i class="bi bi-exclamation-triangle me-1"></i>Failed to load.</div>'; });
}

function rateAttendance(attId, starCell) {
    const rating = prompt('Rate this session (1-5 stars):', starCell ? '5' : '');
    if (rating === null) return;
    const r = parseInt(rating);
    if (isNaN(r) || r < 1 || r > 5) { alert('Please enter 1-5.'); return; }
    const comment = prompt('Comment (optional):', '');
    const fd = new FormData();
    fd.append('action', 'rate_attendance');
    fd.append('attendance_id', attId);
    fd.append('rating', r);
    fd.append('comment', comment || '');
    fetch('/csc2052/api/student.php', { method: 'POST', credentials: 'include', headers: {'X-CSRF-TOKEN': getCsrf()}, body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') { loadHistory(); alert('Rating saved!'); }
            else alert('Error: ' + (data.message || 'Unknown'));
        })
        .catch(e => alert('Network error: ' + e.message));
}

function getCsrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.content : '';
}

function uploadPhoto(input) {
    if (!input.files.length) return;
    const fd = new FormData(); fd.append('action', 'upload_my_photo'); fd.append('photo', input.files[0]);
    fetch('/csc2052/api/student.php', { method: 'POST', credentials: 'include', headers: {'X-CSRF-TOKEN': getCsrf()}, body: fd })
        .then(r => r.json().catch(() => { throw new Error('HTTP ' + r.status + ': ' + r.statusText); }))
        .then(data => {
            if (data.status === 'success') setAvatar(data.photo_url + '?t=' + Date.now());
            else alert('Upload failed: ' + (data.message || 'Unknown error'));
        })
        .catch(e => { alert('Upload error: ' + e.message); });
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
    const seen = {};
    const unique = [];
    courses.forEach(c => {
        const code = c.course_code;
        if (seen[code]) return;
        seen[code] = true;
        unique.push(c);
    });
    if (unique.length === 0) {
        el.innerHTML = '<div class="text-center text-muted py-3 small">Enroll in courses first to rate them.</div>';
        return;
    }
    let html = '';
    unique.forEach(c => {
        const summaryId = 'rating-summary-course-' + c.course_code;
        html += '<div class="border-bottom pb-2 mb-2">' +
            '<div class="d-flex justify-content-between align-items-center">' +
                '<div><strong class="small">' + esc(c.course_code) + '</strong><br><span class="text-muted" style="font-size:0.75rem;">' + esc(c.course_name || '') + '</span></div>' +
                '<div id="' + summaryId + '"><span class="text-muted small">—</span></div>' +
            '</div>' +
        '</div>';
    });
    el.innerHTML = html;
    unique.forEach(c => {
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
    const seen = {};
    sel.innerHTML = '<option value="">Select a course...</option>';
    courses.forEach(c => {
        const code = c.course_code;
        if (seen[code]) return;
        seen[code] = true;
        sel.innerHTML += '<option value="' + esc(code) + '">' + esc(code) + ' — ' + esc(c.course_name || '') + '</option>';
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

// ─── QR Attendance Scanner Controller Logic ───────────────────────────────

// Generate a test QR code on this page so student can verify the scanner works
window.generateTestQr = function() {
    var container = document.getElementById('test-qr-container');
    if (container.style.display !== 'none') {
        container.style.display = 'none';
        return;
    }
    container.style.display = 'block';
    var testData = 'SS-QR|TEST|' + Date.now().toString(36) + '|0';
    var qr;
    if (typeof qrcode !== 'undefined') {
        qr = qrcode(0, 'M');
    } else {
        // Load qrcode library dynamically if not already loaded
        var s = document.createElement('script');
        s.src = '/csc2052/js/qrcode.min.js';
        s.onload = function() {
            generateTestQrCanvas(testData);
        };
        document.head.appendChild(s);
        return;
    }
    generateTestQrCanvas(testData);
    function generateTestQrCanvas(data) {
        var q = qrcode(0, 'M');
        q.addData(data);
        q.make();
        var size = q.getModuleCount();
        var canvas = document.getElementById('test-qr-canvas');
        var ctx = canvas.getContext('2d');
        var scale = canvas.width / size;
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = '#1e293b';
        for (var r = 0; r < size; r++) {
            for (var c = 0; c < size; c++) {
                if (q.isDark(r, c)) {
                    ctx.fillRect(c * scale, r * scale, scale, scale);
                }
            }
        }
        console.log('[QR Scanner] Test QR generated with data:', data);
    }
};

let html5QrScanner = null;
let currentCameraId = null;
let isScanningActive = false;
let lastScannedData = '';
let firstScanSlot = null;
let scanErrorCount = 0;

function initScannerDevices() {
    const selectEl = document.getElementById('qr-camera-select');
    if (!selectEl) return;
    
    // Clear and pre-populate immediately with standard facing modes
    selectEl.innerHTML = `
        <option value="environment">Standard Back Camera (Default)</option>
        <option value="user">Front / Selfie Camera</option>
    `;
    currentCameraId = 'environment';
    
    selectEl.onchange = function() {
        currentCameraId = this.value;
        if (isScanningActive) {
            stopScanner().then(() => {
                startScanner();
            });
        }
    };
    
    // Asynchronously scan for physical cameras and append them under a divider
    Html5Qrcode.getCameras().then(devices => {
        if (devices && devices.length > 0) {
            // Append a divider option
            const divider = document.createElement('option');
            divider.disabled = true;
            divider.textContent = '─── Available Cameras ───';
            selectEl.appendChild(divider);
            
            devices.forEach((device, index) => {
                const label = device.label || `Camera ${index + 1}`;
                const option = document.createElement('option');
                option.value = device.id;
                option.textContent = label;
                selectEl.appendChild(option);
            });
        }
    }).catch(err => {
        console.warn('Physical cameras search deferred or permission not granted yet:', err);
    });
}

function startScanner() {
    const selectEl = document.getElementById('qr-camera-select');
    const deviceId = selectEl ? selectEl.value : null;
    if (!deviceId) {
        showFeedback('danger', 'Please select a camera to start scanning.');
        return;
    }
    
    const laser = document.querySelector('.scanner-laser-line');
    const corners = document.querySelectorAll('.scanner-corner');
    
    if (!html5QrScanner) {
        html5QrScanner = new Html5Qrcode("qr-reader");
    }
    
    isScanningActive = true;
    scanErrorCount = 0;
    document.getElementById('btn-start-scanner').disabled = true;
    document.getElementById('btn-stop-scanner').disabled = false;
    document.getElementById('scanner-fps-label').style.display = 'inline-block';
    if (laser) laser.style.display = 'block';
    if (corners) corners.forEach(c => c.style.display = 'block');
    
    updateScannerState('Active', 'success');
    showFeedback('info', 'Align the QR code on screen inside the scanner window.');
    
    // If it is 'environment' or 'user', pass facingMode constraints instead of raw deviceId
    const cameraIdOrConfig = (deviceId === 'environment' || deviceId === 'user') 
        ? { facingMode: deviceId } 
        : deviceId;
    
    html5QrScanner.start(
        cameraIdOrConfig,
        {
            fps: 10,
            qrbox: function(width, height) {
                const min = Math.min(width, height);
                return { width: min * 0.7, height: min * 0.7 };
            }
        },
        function onDetect(decodedText) {
            if (decodedText === lastScannedData) return;
            lastScannedData = decodedText;
            const parts = decodedText.split('|');
            if (parts.length !== 4 || parts[0] !== 'SS-QR') {
                showFeedback('danger', 'Invalid QR code scanned.', 'This is not a valid attendance QR. Scan the QR from your lecturer\'s display.');
                return;
            }
            submitQrScan(decodedText);
        },
        function onError(errMsg) {
            scanErrorCount++;
            if (scanErrorCount <= 3 || scanErrorCount % 20 === 0) {
                console.warn('[QR Scanner] Error #' + scanErrorCount + ':', errMsg);
            }
            if (scanErrorCount === 10) {
                showFeedback('warning', 'Scanner active but not detecting QR.', 'Make sure the QR code is well-lit, in focus, and fully inside the scanner frame.');
            } else if (scanErrorCount === 50) {
                showFeedback('warning', 'Still having trouble reading the QR.', 'Try cleaning your camera lens, increasing screen brightness, or moving closer.');
            } else if (scanErrorCount === 100) {
                showFeedback('danger', 'Unable to detect QR code after many attempts.', 'Please stop and restart the scanner, or try a different camera.');
            }
        }
    ).then(() => {
        console.log('[QR Scanner] Camera started successfully.');
        showFeedback('info', 'Scanner is active — point your camera at the QR on screen.');
    }).catch(err => {
        console.error("Scanner start error:", err);
        showFeedback('danger', 'Failed to start camera: ' + err);
        stopScanner();
    });
}

function stopScanner() {
    return new Promise((resolve) => {
        const laser = document.querySelector('.scanner-laser-line');
        const corners = document.querySelectorAll('.scanner-corner');
        
        isScanningActive = false;
        lastScannedData = '';
        const startBtn = document.getElementById('btn-start-scanner');
        const stopBtn = document.getElementById('btn-stop-scanner');
        const fpsLabel = document.getElementById('scanner-fps-label');
        
        if (startBtn) startBtn.disabled = false;
        if (stopBtn) stopBtn.disabled = true;
        if (fpsLabel) fpsLabel.style.display = 'none';
        if (laser) laser.style.display = 'none';
        if (corners) corners.forEach(c => c.style.display = 'none');
        
        updateScannerState('Inactive', 'secondary');
        
        if (html5QrScanner && html5QrScanner.isScanning) {
            html5QrScanner.stop().then(() => {
                resolve();
            }).catch(err => {
                console.error('Stop scanner error:', err);
                resolve();
            });
        } else {
            resolve();
        }
    });
}

function updateScannerState(text, type) {
    const badge = document.getElementById('scanner-state-badge');
    const glow = document.getElementById('scanner-state-glow');
    const stateText = document.getElementById('scanner-state-text');
    
    if (badge) badge.className = `badge bg-${type} d-flex align-items-center gap-1 py-1.5 px-2.5`;
    if (glow) glow.className = `status-badge-glow bg-${type} me-1`;
    if (stateText) stateText.textContent = text;
}

function showFeedback(type, message, details = '') {
    const box = document.getElementById('scanner-feedback-box');
    if (!box) return;
    
    let icon = 'info-circle';
    let classText = 'text-primary bg-primary bg-opacity-10 border border-primary';
    
    if (type === 'danger') {
        icon = 'exclamation-triangle';
        classText = 'text-danger bg-danger bg-opacity-10 border border-danger';
    } else if (type === 'success') {
        icon = 'check-circle';
        classText = 'text-success bg-success bg-opacity-10 border border-success';
    } else if (type === 'warning') {
        icon = 'exclamation-circle';
        classText = 'text-warning bg-warning bg-opacity-10 border border-warning';
    } else if (type === 'info') {
        icon = 'camera-video';
        classText = 'text-info bg-info bg-opacity-10 border border-info';
    }
    
    box.className = `py-3 px-3 rounded text-center small ${classText} success-checkmark-bounce`;
    box.innerHTML = `
        <i class="bi bi-${icon} fs-2 d-block mb-2"></i>
        <p class="mb-0 fw-semibold">${message}</p>
        ${details ? `<p class="mb-0 mt-1 opacity-75" style="font-size: 0.75rem;">${details}</p>` : ''}
    `;
}

function submitQrScan(qrData) {
    showFeedback('info', 'Processing scanned QR token...', 'Verifying attendance sequence securely...');
    
    const fd = new FormData();
    fd.append('action', 'scan_qr');
    fd.append('qr_data', qrData);
    
    fetch('/csc2052/api/qr_attendance.php', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': getCsrf()
        },
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        handleScanApiResponse(data);
    })
    .catch(err => {
        console.error('Scan submission error:', err);
        showFeedback('danger', 'Network communication error.', 'Please check your connection and try again.');
    });
}

function handleScanApiResponse(data) {
    const step1Ind = document.getElementById('step-1-indicator');
    const step2Ind = document.getElementById('step-2-indicator');
    const step1Lbl = document.getElementById('step-1-label');
    const step2Lbl = document.getElementById('step-2-label');
    const progressLine = document.getElementById('stepper-progress-line');
    
    if (data.status === 'first_scan') {
        firstScanSlot = data.slot;
        
        if (step1Ind) step1Ind.className = 'step-circle rounded-circle d-flex align-items-center justify-content-center completed';
        if (step2Ind) step2Ind.className = 'step-circle rounded-circle d-flex align-items-center justify-content-center active';
        
        if (step1Lbl) step1Lbl.className = 'step-label mt-2 small fw-bold text-success';
        if (step2Lbl) step2Lbl.className = 'step-label mt-2 small fw-bold text-primary';
        if (progressLine) progressLine.style.width = '50%';
        
        showFeedback('success', 
            `Step 1/2 Completed! (QR #${data.slot})`, 
            `Visual sequence recorded. Wait for the QR code to morph into <strong>#${data.need_slot}</strong>, then scan the new pattern immediately.`
        );
        
    } else if (data.status === 'success') {
        if (step1Ind) step1Ind.className = 'step-circle rounded-circle d-flex align-items-center justify-content-center completed';
        if (step2Ind) step2Ind.className = 'step-circle rounded-circle d-flex align-items-center justify-content-center completed';
        
        if (step1Lbl) step1Lbl.className = 'step-label mt-2 small fw-bold text-success';
        if (step2Lbl) step2Lbl.className = 'step-label mt-2 small fw-bold text-success';
        if (progressLine) progressLine.style.width = '100%';
        
        showFeedback('success', 
            '🎉 Attendance Recorded Successfully!', 
            `Verified verification chain: ${data.pair}. Thank you, ${data.student_name}!`
        );
        
        loadCourses();
        loadHistory();
        
        setTimeout(() => {
            stopScanner();
            resetStepper();
        }, 3500);
        
    } else if (data.status === 'same_qr') {
        showFeedback('warning', 
            'Wait for QR to update', 
            `${data.message}`
        );
        
    } else if (data.status === 'not_consecutive') {
        firstScanSlot = data.new_slot1;
        
        if (step1Ind) step1Ind.className = 'step-circle rounded-circle d-flex align-items-center justify-content-center completed';
        if (step2Ind) step2Ind.className = 'step-circle rounded-circle d-flex align-items-center justify-content-center active';
        
        if (step1Lbl) step1Lbl.className = 'step-label mt-2 small fw-bold text-success';
        if (step2Lbl) step2Lbl.className = 'step-label mt-2 small fw-bold text-primary';
        if (progressLine) progressLine.style.width = '50%';
        
        showFeedback('warning', 
            'Sequence Reset — Scan Registered as First!', 
            `Scan was not consecutive. This new scan (QR #${data.new_slot1}) is now your first scan. Wait for the QR to morph, then scan slot #${data.need_slot}.`
        );
        
    } else {
        showFeedback('danger', 
            data.status.toUpperCase().replace('_', ' '), 
            data.message || 'Verification rejected by backend security agent.'
        );
        
        if (data.status === 'already_marked') {
            setTimeout(() => {
                stopScanner();
                resetStepper();
            }, 2500);
        }
    }
}

function resetStepper() {
    const step1Ind = document.getElementById('step-1-indicator');
    const step2Ind = document.getElementById('step-2-indicator');
    const step1Lbl = document.getElementById('step-1-label');
    const step2Lbl = document.getElementById('step-2-label');
    const progressLine = document.getElementById('stepper-progress-line');
    
    if (step1Ind) step1Ind.className = 'step-circle rounded-circle bg-white d-flex align-items-center justify-content-center border border-3 border-secondary';
    if (step2Ind) step2Ind.className = 'step-circle rounded-circle bg-white d-flex align-items-center justify-content-center border border-3 border-secondary';
    
    if (step1Lbl) step1Lbl.className = 'step-label mt-2 small fw-bold text-secondary';
    if (step2Lbl) step2Lbl.className = 'step-label mt-2 small fw-bold text-secondary';
    if (progressLine) progressLine.style.width = '0%';
    
    firstScanSlot = null;
}

document.addEventListener('DOMContentLoaded', () => {
    loadCourses();
    document.querySelector('[data-bs-target="#tab-history"]').addEventListener('shown.bs.tab', loadHistory);
    document.querySelector('[data-bs-target="#tab-forum"]').addEventListener('shown.bs.tab', () => {
        loadStudentRatings();
        initStudentForum();
    });
    
    // Tab event bindings for QR Scanner lifecycle
    const qrTab = document.querySelector('[data-bs-target="#tab-qr-scan"]');
    if (qrTab) {
        qrTab.addEventListener('shown.bs.tab', () => {
            initScannerDevices();
            const box = document.getElementById('scanner-feedback-box');
            if (box) {
                box.className = 'py-3 px-3 rounded text-center small bg-light text-muted';
                box.innerHTML = `
                    <i class="bi bi-camera fs-2 d-block mb-2 text-primary"></i>
                    <p class="mb-0 fw-semibold">Scanner ready to activate.</p>
                    <p class="mb-0 text-muted mt-1" style="font-size:0.75rem;">Select your camera and click "Start Scanner" to begin the secure 2-step verification process.</p>
                `;
            }
        });
        qrTab.addEventListener('hidden.bs.tab', () => {
            stopScanner().then(() => {
                resetStepper();
                const box = document.getElementById('scanner-feedback-box');
                if (box) {
                    box.className = 'py-3 px-3 rounded text-center small bg-light text-muted';
                    box.innerHTML = `
                        <i class="bi bi-camera-video-off fs-2 d-block mb-2 text-secondary"></i>
                        <p class="mb-0 fw-semibold">Scanner is currently offline.</p>
                        <p class="mb-0 text-muted mt-1" style="font-size:0.75rem;">Select your camera and click "Start Scanner" to begin the secure 2-step verification process.</p>
                    `;
                }
            });
        });
    }
});
</script>
<?php require 'includes/footer.php'; ?>

<?php // admin_qr_attendance.php ?>
<div class="card border-0 mb-3 overflow-hidden" style="border-radius:12px;box-shadow:0 4px 16px rgba(161,30,23,0.08);">
    <div class="card-header py-2 px-3 d-flex align-items-center gap-2"
         style="background:linear-gradient(135deg,#0d6efd,#0a58ca);border-bottom:none;">
        <i class="bi bi-qr-code text-white"></i>
        <strong class="small fw-bold text-white">QR Attendance</strong>
    </div>
    <div class="card-body py-2 px-3 bg-white">
        <div id="admin-qr-active-course" class="small fw-bold text-center py-1 px-2 rounded mb-2" style="background:#fff7ed;border:1px solid #fed7aa;">
            <i class="bi bi-broadcast me-1 text-warning"></i>Active Lecture: <span id="admin-qr-course-display">None</span>
            <input type="hidden" id="admin-qr-course" value="">
        </div>
        <button id="btn-admin-qr-start" class="btn btn-sm w-100 fw-bold mb-2"
                onclick="adminQrToggleSession()"
                style="background:linear-gradient(135deg,#0d6efd,#0a58ca);color:#fff;border:none;border-radius:8px;box-shadow:0 2px 8px rgba(13,110,253,0.3);">
            <i class="bi bi-play-circle me-1"></i>View QR Session Stats
        </button>
        <div id="admin-qr-stats" class="d-none text-center small">
            <div class="d-flex justify-content-around mt-2">
                <div><span class="badge bg-success" id="admin-qr-completed">0</span> <span class="text-muted">Completed</span></div>
                <div><span class="badge bg-warning text-dark" id="admin-qr-pending">0</span> <span class="text-muted">Pending</span></div>
            </div>
            <button id="btn-admin-qr-stop" class="btn btn-sm btn-danger w-100 fw-bold mt-2"
                    onclick="adminQrToggleSession()">
                <i class="bi bi-stop-circle me-1"></i>Stop Session
            </button>
        </div>
    </div>
</div>

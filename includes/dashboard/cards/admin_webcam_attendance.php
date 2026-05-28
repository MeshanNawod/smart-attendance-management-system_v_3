<?php
// admin_webcam_attendance.php
?>
<div class="card border-0 mb-3 overflow-hidden" style="border-radius:12px;box-shadow:0 4px 16px rgba(161,30,23,0.08);">
    <div class="card-header py-2 px-3 d-flex align-items-center gap-2"
         style="background:linear-gradient(135deg,#fcd40d,#e8c00c);border-bottom:none;">
        <i class="bi bi-camera-video-fill" style="color:#1e293b;"></i>
        <strong class="small fw-bold" style="color:#1e293b;">Webcam Attendance</strong>
    </div>
    <div class="card-body py-2 px-3 bg-white">
        <button id="btn-start-attendance"
                class="btn btn-sm w-100 fw-bold mb-2"
                onclick="startWebFaceAttendance()"
                style="background:linear-gradient(135deg,#fcd40d,#e8c00c);color:#1e293b;border:none;border-radius:8px;letter-spacing:0.3px;box-shadow:0 2px 8px rgba(252,212,13,0.3);">
            <i class="bi bi-play-circle me-1"></i>Start Camera Scanner
        </button>

        <div id="web-attendance-container" class="d-none rounded overflow-hidden border mt-1" style="border-color:#f0e8e8!important;">
            <select id="attendance-camera-select" class="form-select form-select-sm mb-2 d-none rounded-0 border-0" onchange="switchAttendanceCamera()"></select>
            <div class="position-relative w-100 bg-dark" style="line-height:0;">
                <video id="web-attendance-video" autoplay muted playsinline class="w-100" style="height:auto;display:block;"></video>
                <canvas id="web-attendance-overlay" style="position:absolute;top:0;left:0;pointer-events:none;width:100%;height:100%;"></canvas>
            </div>
            <div class="px-2 pt-2">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div id="web-attendance-status" class="small fw-bold" style="color:#a11e17;"></div>
                    <span id="web-head-count-badge" class="badge d-none" style="background:#fcd40d;color:#1e293b;">Faces: 0</span>
                </div>
                <div class="d-flex gap-3 mb-2 px-1">
                    <div class="form-check form-check-inline mb-0">
                        <input class="form-check-input" type="radio" name="webFaceMode" id="modeAttendance" value="attendance" checked>
                        <label class="form-check-label fw-bold small" for="modeAttendance" style="color:#ffd30a;"><i class="bi bi-person-check-fill me-1"></i>Mark</label>
                    </div>
                    <div class="form-check form-check-inline mb-0">
                        <input class="form-check-input" type="radio" name="webFaceMode" id="modeIdentify" value="identify">
                        <label class="form-check-label fw-bold small" for="modeIdentify" style="color:#a11e17;"><i class="bi bi-search me-1"></i>Identify</label>
                    </div>
                </div>
                <div class="d-flex gap-3 justify-content-center mb-2">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="web-attendance-auto">
                        <label class="form-check-label small fw-bold text-muted" for="web-attendance-auto"><i class="bi bi-lightning-charge me-1"></i>Auto</label>
                    </div>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="web-attendance-mute">
                        <label class="form-check-label small fw-bold text-muted" for="web-attendance-mute"><i class="bi bi-volume-mute me-1"></i>Mute</label>
                    </div>
                </div>
                <button id="btn-stop-attendance" class="btn btn-sm w-100 fw-bold d-none mb-2"
                        style="background:linear-gradient(135deg,#dc3545,#b02a37);color:#fff;border:none;border-radius:8px;">
                    <i class="bi bi-stop-circle me-1"></i>Stop Scanner
                </button>
            </div>
        </div>
    </div>
</div>

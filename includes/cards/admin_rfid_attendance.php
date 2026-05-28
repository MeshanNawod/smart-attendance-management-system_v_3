<?php // admin_rfid_attendance.php ?>
<div class="card border-0 mb-3 overflow-hidden" style="border-radius:12px;box-shadow:0 4px 16px rgba(161,30,23,0.08);">
    <div class="card-header py-2 px-3 d-flex align-items-center gap-2"
         style="background:linear-gradient(135deg,#6f42c1,#5a32a3);border-bottom:none;">
        <i class="bi bi-credit-card-2-front text-white"></i>
        <strong class="small fw-bold text-white">RFID Scan Session</strong>
    </div>
    <div class="card-body py-2 px-3 bg-white">
        <button id="btn-rfid-start"
                class="btn btn-sm w-100 fw-bold mb-2"
                onclick="toggleRfidSession()"
                style="background:linear-gradient(135deg,#6f42c1,#5a32a3);color:#fff;border:none;border-radius:8px;box-shadow:0 2px 8px rgba(111,66,193,0.3);">
            <i class="bi bi-play-circle me-1"></i>Start RFID Scanner
        </button>

        <div id="rfid-session-container" class="d-none">
            <div class="small fw-bold text-center py-1 px-2 rounded mb-2"
                 style="background:#f3f0ff;border:1px solid #d9c9ff;">
                <i class="bi bi-broadcast me-1" style="color:#6f42c1;"></i>Active Lecture: <span id="rfid-course-display">None</span>
                <input type="hidden" id="rfid-course" value="">
            </div>
            <div class="d-flex gap-3 mb-2 px-1">
                <div class="form-check form-check-inline mb-0">
                    <input class="form-check-input" type="radio" name="rfidMode" id="rfidModeAttendance" value="attendance" checked>
                    <label class="form-check-label fw-bold small" for="rfidModeAttendance" style="color:#6f42c1;"><i class="bi bi-person-check-fill me-1"></i>Attendance</label>
                </div>
                <div class="form-check form-check-inline mb-0">
                    <input class="form-check-input" type="radio" name="rfidMode" id="rfidModeEnroll" value="enroll">
                    <label class="form-check-label fw-bold small" for="rfidModeEnroll" style="color:#5a32a3;"><i class="bi bi-person-plus-fill me-1"></i>Enroll</label>
                </div>
            </div>
            <div id="rfid-session-status" class="small text-center py-1 rounded" style="background:#f8f9fa;">
                <i class="bi bi-credit-card-2-front me-1"></i>Waiting for RFID card...
            </div>
            <div id="rfid-log-area" class="mt-2 small" style="max-height:150px;overflow-y:auto;font-family:monospace;"></div>
            <div class="d-flex gap-2 mt-2">
                <button id="btn-rfid-stop" class="btn btn-sm w-100 fw-bold"
                        onclick="toggleRfidSession()"
                        style="background:linear-gradient(135deg,#dc3545,#b02a37);color:#fff;border:none;border-radius:8px;">
                    <i class="bi bi-stop-circle me-1"></i>Stop Scanner
                </button>
                <button class="btn btn-sm fw-bold"
                        onclick="testRfidBuffer()" title="Push a test UID to verify the dashboard can receive it"
                        style="background:linear-gradient(135deg,#6c757d,#495057);color:#fff;border:none;border-radius:8px;white-space:nowrap;">
                    <i class="bi bi-bug me-1"></i>Test
                </button>
            </div>
        </div>
    </div>
</div>

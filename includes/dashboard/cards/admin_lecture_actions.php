<?php
// admin_lecture_actions.php
?>
<div class="card border-0 mb-3" style="border-radius:12px;box-shadow:0 4px 16px rgba(161,30,23,0.08);">
    <div class="card-header py-2 px-3 d-flex align-items-center gap-2"
         style="background:linear-gradient(135deg,#a11e17,#8b1510);border-bottom:none;border-radius:12px 12px 0 0;">
        <i class="bi bi-broadcast text-white"></i>
        <strong class="small fw-bold text-white">Lecture &amp; Quick Actions</strong>
    </div>
    <div class="card-body py-2 px-3 bg-white">

        <!-- Device Selector -->
        <div class="mb-3 p-2 rounded-3" style="background:#fdf8f8;border:1px solid #f0e8e8;">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <label class="form-label small fw-bold mb-0" style="color:#a11e17;font-size:0.72rem;letter-spacing:0.5px;">
                    <i class="bi bi-bullseye me-1"></i>Target Device
                </label>
                <div class="d-flex gap-1">
                    <button class="btn btn-xs rounded py-0 px-2" style="border:1px solid #e2e8f0;font-size:0.7rem;" onclick="refreshDeviceList(); refreshDeviceDropdown();" title="Scan">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                    <button class="btn btn-xs rounded py-0 px-2" style="border:1px solid #a11e17;color:#a11e17;font-size:0.7rem;" onclick="toggleManageDevices()" title="Manage">
                        <i class="bi bi-gear"></i>
                    </button>
                </div>
            </div>
            <div class="searchable-select-wrapper position-relative">
                <input type="text" id="device-monitor-search" class="form-control form-control-sm"
                       style="border-color:#e0d0d0;font-size:0.82rem;"
                       placeholder="Search devices..." autocomplete="off"
                       oninput="filterDropdown(this,'device-monitor-dropdown','action-target-device')"
                       onfocus="showDropdown('device-monitor-dropdown')">
                <input type="hidden" id="action-target-device" value="WEB_DASHBOARD">
                <div id="device-monitor-dropdown" class="searchable-dropdown d-none"></div>
            </div>
            <div id="device-manage-panel" class="d-none mt-2">
                <div id="device-list-table" class="small text-muted"><em>Loading...</em></div>
            </div>
        </div>

        <!-- Start Lecture -->
        <div class="fw-bold mb-1" style="font-size:0.72rem;color:#64748b;text-transform:uppercase;letter-spacing:0.8px;">
            <i class="bi bi-play-circle me-1" style="color:#a11e17;"></i>Start Active Lecture
        </div>
        <div class="input-group input-group-sm mb-2">
            <div class="searchable-select-wrapper flex-grow-1 position-relative">
                <input type="text" id="start-course-search" class="form-control" style="border-color:#e0d0d0;font-size:0.82rem;"
                       placeholder="Search courses..." autocomplete="off"
                       oninput="filterDropdown(this,'start-course-dropdown','start-course-input')"
                       onfocus="showDropdown('start-course-dropdown')"
                       onkeydown="filterDropdown(this,'start-course-dropdown','start-course-input')">
                <input type="hidden" id="start-course-input" value="">
                <div id="start-course-dropdown" class="searchable-dropdown d-none"></div>
            </div>
            <select id="lecture-timer-preset" class="form-select form-select-sm" style="width:90px;border-color:#e0d0d0;font-size:0.78rem;" onchange="applyTimerPreset(this.value)">
                <option value="0">No Timer</option>
                <option value="30">30 min</option>
                <option value="60">1 hour</option>
                <option value="90">1h 30m</option>
                <option value="120">2 hours</option>
                <option value="custom">Custom</option>
            </select>
            <input type="number" id="lecture-timer-custom" class="form-control form-control-sm" style="width:65px;display:none;border-color:#e0d0d0;" placeholder="Min" min="1" max="480">
            <button class="btn btn-sm btn-maroon fw-bold" id="btn-start-course" onclick="startCourseToDevice()"
                    style="border-radius:0 4px 4px 0;padding:0 10px;">
                <i class="bi bi-play-fill"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger fw-bold ms-1" id="btn-end-course" onclick="endCourseToDevice()" disabled
                    style="border-radius:4px;padding:0 8px;">
                <i class="bi bi-stop-fill"></i>
            </button>
        </div>
        <div class="d-flex gap-2 mt-2">
            <div class="flex-grow-1">
                <input type="text" id="lecture-section-input" class="form-control form-control-sm"
                       placeholder="Section (e.g. Lab A)" style="border-color:#e0d0d0;font-size:0.78rem;">
            </div>
            <button class="btn btn-sm btn-hover-lift" id="btn-next-section" onclick="nextLectureSection()"
                    style="border:1.5px solid #0d6efd;color:#0d6efd;background:transparent;border-radius:4px;padding:0 8px;display:none;">
                <i class="bi bi-forward-fill me-1"></i>Next Section
            </button>
        </div>
        <div id="lecture-timer-display" class="d-none mb-2 p-1 rounded" style="background:#fff8f8;border:1px dashed #f0c0c0;">
            <div class="d-flex align-items-center justify-content-between small">
                <span class="text-muted"><i class="bi bi-clock me-1"></i>Auto-end in:</span>
                <span id="lecture-timer-countdown" class="fw-bold" style="color:#a11e17;"></span>
                <button class="btn btn-xs py-0 px-2 rounded" style="border:1px solid #ddd;font-size:0.7rem;" onclick="cancelLectureTimer()">Cancel</button>
            </div>
        </div>

        <!-- Sync Courses -->
        <div class="fw-bold mb-1" style="font-size:0.72rem;color:#64748b;text-transform:uppercase;letter-spacing:0.8px;">
            <i class="bi bi-sd-card me-1" style="color:#a11e17;"></i>Sync Courses to SD
        </div>
        <div class="input-group input-group-sm mb-3">
            <div class="searchable-select-wrapper flex-grow-1 position-relative">
                <input type="text" id="sync-courses-input" class="form-control" style="border-color:#e0d0d0;font-size:0.82rem;"
                       placeholder="Search &amp; add courses..." autocomplete="off"
                       oninput="filterDropdown(this,'sync-course-dropdown',null)"
                       onfocus="showDropdown('sync-course-dropdown')"
                       onkeydown="filterDropdown(this,'sync-course-dropdown',null)">
                <div id="sync-course-dropdown" class="searchable-dropdown d-none"></div>
            </div>
            <button class="btn btn-sm btn-outline-secondary fw-bold" onclick="syncCoursesToScanner()"
                    style="border-radius:0 4px 4px 0;padding:0 10px;">
                <i class="bi bi-sd-card"></i>
            </button>
        </div>

        <!-- Hardware Mode -->
        <div class="fw-bold mb-1" style="font-size:0.72rem;color:#64748b;text-transform:uppercase;letter-spacing:0.8px;">
            <i class="bi bi-cpu me-1" style="color:#a11e17;"></i>Hardware Mode
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-sm w-100 btn-outline-warning fw-semibold"
                    onclick="sendOtaCommand('ATTENDANCE_MODE')"
                    style="border-radius:8px;font-size:0.78rem;">
                <i class="bi bi-person-check me-1"></i>Attendance
            </button>
            <button class="btn btn-sm w-100 btn-outline-warning fw-semibold"
                    onclick="sendOtaCommand('ENROLL_MODE')"
                    style="border-radius:8px;font-size:0.78rem;">
                <i class="bi bi-fingerprint me-1"></i>Enroll
            </button>
        </div>
    </div>
</div>

<!-- Active Lectures -->
<div class="card border-0 mb-3 overflow-hidden" style="border-radius:12px;box-shadow:0 4px 16px rgba(161,30,23,0.08);">
    <div class="card-header py-2 px-3 d-flex align-items-center justify-content-between"
         style="background:linear-gradient(135deg,#1e293b,#2d3a4a);border-bottom:none;">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-collection-play text-white"></i>
            <strong class="small fw-bold text-white">Active Lectures</strong>
        </div>
        <span id="active-lecture-count" class="badge"
              style="background:#fcd40d;color:#1e293b;font-size:0.7rem;border-radius:20px;">0</span>
    </div>
    <div class="card-body py-2 px-3 bg-white" style="max-height:180px;overflow-y:auto;">
        <div id="active-lectures-container">
            <div class="text-muted small text-center py-2"><i class="bi bi-inbox me-1"></i>No active lectures</div>
        </div>
    </div>
</div>

<?php
// admin_manual_attendance.php
?>
<div class="card border-0 mb-3" style="border-radius:12px;box-shadow:0 4px 16px rgba(161,30,23,0.08);">
    <div class="card-header py-2 px-3 d-flex align-items-center gap-2"
         style="background:linear-gradient(135deg,#ffd30a,#e6b800);border-bottom:none;border-radius:12px 12px 0 0;">
        <i class="bi bi-person-check-fill" style="color:#1e293b;"></i>
        <strong class="small fw-bold" style="color:#1e293b;">Mark Attendance Manually</strong>
    </div>
    <div class="card-body py-3 px-3 bg-white">
        <div class="row g-2 mb-2">
            <div class="col-6">
                <label class="form-label small mb-1 fw-semibold" style="color:#64748b;font-size:0.72rem;text-transform:uppercase;letter-spacing:0.5px;">Course (Optional)</label>
                <div class="searchable-select-wrapper position-relative">
                    <input type="text" id="manual-course-search"
                           class="form-control form-control-sm"
                           style="border-color:#e0d0d0;font-size:0.82rem;"
                           placeholder="Search..."
                           autocomplete="off"
                           oninput="filterDropdown(this,'manual-course-dropdown','manual-course-code')"
                           onfocus="showDropdown('manual-course-dropdown')">
                    <input type="hidden" id="manual-course-code" value="">
                    <div id="manual-course-dropdown" class="searchable-dropdown d-none"></div>
                </div>
            </div>
            <div class="col-6">
                <label class="form-label small mb-1 fw-semibold" style="color:#64748b;font-size:0.72rem;text-transform:uppercase;letter-spacing:0.5px;">Time (Optional)</label>
                <input type="datetime-local" id="manual-timestamp" class="form-control form-control-sm" style="border-color:#e0d0d0;font-size:0.82rem;">
            </div>
        </div>

        <div class="input-group shadow-sm" style="border-radius:8px;overflow:hidden;">
            <span class="input-group-text bg-white" style="border-color:#e0d0d0;">
                <i class="bi bi-person-check" style="color:#ffd30a;"></i>
            </span>
            <input type="text" id="manual-student-no"
                   class="form-control"
                   style="border-color:#e0d0d0;font-size:0.88rem;"
                   placeholder="e.g. S/20/123"
                   oninput="searchStudentName(this.value)">
            <button class="btn btn-gold fw-semibold"
                    onclick="markManualAttendance()"
                    style="border-radius:0 8px 8px 0;font-size:0.85rem;padding:0 14px;">
                <i class="bi bi-check-lg me-1"></i>Present
            </button>
        </div>
        <div id="manual-student-name-display" class="form-text px-1 min-h-xs" style="color:#64748b;font-size:0.78rem;"></div>
    </div>
</div>

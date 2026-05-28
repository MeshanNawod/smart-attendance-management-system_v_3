<?php // admin_voice_attendance.php ?>
<div class="card border-0 mb-3 overflow-hidden" style="border-radius:12px;box-shadow:0 4px 16px rgba(161,30,23,0.08);">
    <div class="card-header py-2 px-3 d-flex align-items-center gap-2"
         style="background:linear-gradient(135deg,#198754,#157347);border-bottom:none;">
        <i class="bi bi-mic text-white"></i>
        <strong class="small fw-bold text-white">Voice Attendance</strong>
    </div>
    <div class="card-body py-2 px-3 bg-white">
        <button id="voice-card-record-btn" class="btn btn-sm w-100 fw-bold mb-2"
                onclick="adminVoiceCardToggle()"
                style="background:linear-gradient(135deg,#198754,#157347);color:#fff;border:none;border-radius:8px;box-shadow:0 2px 8px rgba(25,135,84,0.3);">
            <i class="bi bi-mic me-1"></i><span id="voice-card-label">Start Recording</span>
        </button>

        <div id="voice-card-active">

            <div class="small fw-bold text-center py-1 px-2 rounded mb-2"
                 style="background:#f0fdf4;border:1px solid #bbf7d0;">
                <i class="bi bi-broadcast me-1 text-success"></i>Active Lecture: <span id="voice-card-course-display">None</span>
                <input type="hidden" id="voice-card-course" value="">
            </div>
            <div class="searchable-select-wrapper position-relative mb-2" style="z-index: 1060;">
                <input type="text" id="voice-card-student-search" class="form-control form-control-sm" placeholder="Search student..." autocomplete="off"
                       oninput="filterDropdown(this,'voice-card-student-dropdown','voice-card-student')"
                       onfocus="showDropdown('voice-card-student-dropdown')">
                <input type="hidden" id="voice-card-student" value="">
                <div id="voice-card-student-dropdown" class="searchable-dropdown d-none" style="max-height:200px; overflow-y:auto; width: 100%;">
                    <?php foreach ($allStudents as $s): ?>
                        <div class="searchable-dropdown-item" data-value="<?php echo htmlspecialchars($s['student_no']); ?>" onclick="selectDropdownItem(this, 'voice-card-student-search', 'voice-card-student', 'voice-card-student-dropdown')">
                            <?php echo htmlspecialchars($s['student_no'] . ' - ' . $s['student_name']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="d-flex gap-3 mb-2 px-1">
                <div class="form-check form-check-inline mb-0">
                    <input class="form-check-input" type="radio" name="voiceCardMode" id="voiceCardAttend" value="attendance" checked>
                    <label class="form-check-label fw-bold small" for="voiceCardAttend" style="color:#198754;"><i class="bi bi-person-check-fill me-1"></i>Mark</label>
                </div>
                <div class="form-check form-check-inline mb-0">
                    <input class="form-check-input" type="radio" name="voiceCardMode" id="voiceCardEnroll" value="enroll">
                    <label class="form-check-label fw-bold small" for="voiceCardEnroll" style="color:#0d6efd;"><i class="bi bi-person-plus-fill me-1"></i>Enroll</label>
                </div>
            </div>
            <div class="d-flex gap-3 justify-content-center mb-2">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="voice-card-mute">
                    <label class="form-check-label small fw-bold text-muted" for="voice-card-mute"><i class="bi bi-volume-mute me-1"></i>Mute</label>
                </div>
            </div>
            <div class="mb-2">
                <select id="voice-card-mic-select" class="form-select form-select-sm" style="font-size:0.75rem;">
                    <option value="">Default Microphone</option>
                </select>
            </div>
            <div id="voice-card-status" class="small text-center py-1 rounded mb-2" style="background:#f8f9fa;">
                <i class="bi bi-mic me-1"></i>Select student and click Record.
            </div>
            <div class="mt-2">
                <canvas id="voice-card-visualizer" height="60" class="w-100 rounded" style="background:#1e293b;display:none;"></canvas>
            </div>
            <div id="voice-card-recent" class="mt-2 small" style="max-height:120px;overflow-y:auto;">
                <div class="text-muted text-center py-1">No recent voice logs.</div>
            </div>
        </div>
    </div>
</div>

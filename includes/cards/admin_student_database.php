<?php
// admin_student_database.php
?>
<div class="card card-clean shadow-none mb-4 border-0">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <strong class="text-dark"><i class="bi bi-people-fill me-2"></i>Student Database &amp; Templates</strong>
        <div class="d-flex align-items-center gap-2">
            <label class="form-label small text-muted mb-0 fw-bold"><i class="bi bi-cpu me-1"></i>Target Device:</label>
            <select id="students-device-select" class="form-select form-select-sm" style="min-width:180px;max-width:260px;" onchange="onStudentsDeviceChange(this.value)">
                <option value="">⚠️ Select device first…</option>
            </select>
            <span id="students-device-status" class="badge bg-secondary">None</span>
        </div>
    </div>
    <div id="students-device-required-alert" class="alert alert-warning border-0 rounded-0 mb-0 py-2 px-3 d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
        <span class="small"><strong>No hardware device selected.</strong> Choose an active ESP32 node above before using <em>Enroll Finger</em> or <em>Push Template</em>. Name/RFID/CSV operations work without a device.</span>
    </div>
    <div class="card-body bg-light">
        <div class="row">
            <div class="col-md-6 border-end pe-md-4">
                <h6 class="text-muted fw-bold mb-2">Hardware Enrollment Link</h6>
                <div class="input-group mb-2 shadow-sm rounded">
                    <span class="input-group-text bg-white"><i class="bi bi-person-badge text-primary"></i></span>
                    <input type="text" id="enroll-student-no" class="form-control border-end-0" placeholder="Student No">
                    <input type="text" id="enroll-student-name" class="form-control w-50" placeholder="Student Name (Optional)">
                    <button class="btn btn-outline-primary fw-semibold" onclick="updateStudentProfile()"><i class="bi bi-save me-1"></i>Update</button>
                </div>
                <div class="input-group mb-2">
                    <span class="input-group-text bg-white"><i class="bi bi-fingerprint text-primary"></i></span>
                    <input type="number" id="enroll-id" class="form-control bg-light text-muted" value="<?php echo $next_id; ?>" readonly title="Auto-assigned ID">
                    <button class="btn btn-primary fw-semibold" onclick="if(requireDeviceForOTA()) triggerEnroll()" title="Requires a hardware device to be selected above">
                        <i class="bi bi-plus-circle me-1"></i>Enroll Finger
                    </button>
                </div>
                <div class="input-group mb-2">
                    <span class="input-group-text bg-white"><i class="bi bi-credit-card text-info"></i></span>
                    <input type="text" id="enroll-rfid" class="form-control" placeholder="RFID Tag UID">
                    <button class="btn btn-outline-info fw-semibold px-2" onclick="autoFindRfid('enroll-rfid')" title="Auto Find Latest Scan"><i class="bi bi-search"></i></button>
                    <button class="btn btn-info text-white fw-semibold" onclick="triggerEnrollRfid()">
                        <i class="bi bi-link me-1"></i>Link RFID
                    </button>
                </div>
                <div class="input-group mb-2">
                    <span class="input-group-text bg-white"><i class="bi bi-person-bounding-box text-warning"></i></span>
                    <input type="text" id="enroll-face" class="form-control" placeholder="Face Profile ID">
                    <button class="btn btn-outline-warning fw-semibold px-2" onclick="autoFindFace('enroll-face')" title="Auto Find Latest Scan"><i class="bi bi-search"></i></button>
                    <button class="btn btn-warning text-white fw-semibold" onclick="triggerEnrollFace()">
                        <i class="bi bi-link me-1"></i>Link Face
                    </button>
                </div>
                <div class="input-group mb-2 mt-3">
                    <span class="input-group-text bg-white border-danger-subtle"><i class="bi bi-images text-danger"></i></span>
                    <input type="file" id="face-bulk-input" class="form-control border-danger-subtle" multiple accept="image/*" title="Name files as student numbers: S-20-123.jpg">
                    <button class="btn btn-danger text-white fw-semibold" onclick="bulkEnrollFromImages(document.getElementById('face-bulk-input'))">
                        <i class="bi bi-upload me-1"></i>Bulk Enroll
                    </button>
                </div>
                <div class="text-muted small mb-1 ps-1">
                    <i class="bi bi-info-circle text-primary me-1"></i>
                    Name each image as the student number, e.g. <code>S-20-123.jpg</code> or <code>S_20_123.png</code>. Hyphens/underscores are auto-converted to slashes.
                </div>
                <div class="progress mb-1" style="height:6px" id="bulk-upload-progress">
                    <div id="bulk-upload-bar" class="progress-bar bg-danger" style="width:0%"></div>
                </div>
                <div id="bulk-upload-log" class="d-none rounded mb-3 bulk-log" style="display:none!important"></div>

                <h6 class="text-muted fw-bold mb-2 border-top pt-3"><i class="bi bi-camera-video me-1"></i>Web Face Enrollment</h6>
                <div class="mb-4">
                    <div class="d-flex align-items-center gap-2 mb-2 p-2 bg-white border rounded shadow-sm">
                        <i class="bi bi-images text-primary"></i>
                        <label class="form-label small fw-bold mb-0 text-muted flex-shrink-0">Capture Count</label>
                        <input type="range" class="form-range flex-grow-1" min="3" max="15" value="7" id="enroll-capture-count" oninput="document.getElementById('enroll-count-val').textContent=this.value">
                        <span class="badge bg-primary enroll-count-badge" id="enroll-count-val">7</span>
                    </div>
                    <button id="btn-start-enroll" class="btn btn-outline-primary fw-semibold w-100" onclick="startWebFaceEnrollment(document.getElementById('enroll-student-no').value, document.getElementById('enroll-student-name').value, parseInt(document.getElementById('enroll-capture-count').value))">
                        <i class="bi bi-person-bounding-box me-1"></i>Start Webcam Enrollment
                    </button>
                    <div class="mt-2 mb-2 text-center">
                        <span class="badge bg-secondary mb-1">OR UPLOAD PHOTO</span>
                        <input type="file" id="web-enroll-image" accept="image/*" class="form-control form-control-sm" onchange="enrollUploadedImage(this)">
                    </div>
                    <div id="web-enroll-container" class="d-none border rounded p-2 mt-2 bg-white text-center shadow-sm">
                        <select id="enroll-camera-select" class="form-select form-select-sm mb-2 d-none" onchange="switchEnrollCamera()"></select>
                        <video id="web-enroll-video" autoplay muted playsinline class="w-100 rounded bg-dark mb-2" style="height: auto; display: block;"></video>
                        <div id="web-enroll-status" class="small fw-bold mb-2"></div>
                        <div class="d-flex gap-2">
                            <button id="btn-capture-enroll" class="btn btn-success flex-grow-1 fw-bold d-none"><i class="bi bi-camera me-1"></i>Capture Face</button>
                            <button id="btn-cancel-enroll" class="btn btn-secondary flex-grow-1 fw-bold d-none">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 ps-md-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="text-muted fw-bold mb-0">Mapped Templates</h6>
                    <div class="d-flex gap-2 align-items-center">
                        <select id="template-attendance-course" class="form-select form-select-sm w-auto" onchange="loadTemplateAttendance()">
                            <option value="">Overall Attendance</option>
                        </select>
                        <input type="text" id="template-search" class="form-control form-control-sm border-secondary w-25" placeholder="Search student..." oninput="filterTemplates()">
                    </div>
                </div>
                <div class="scroll-y-lg border rounded bg-white mb-3 shadow-sm w-100">
                    <table class="table table-hover table-borderless align-middle mb-0" id="templates-table">
                        <thead class="table-light sticky-top border-bottom">
                            <tr>
                                <th class="ps-3 text-secondary small fw-bold">Student No</th>
                                <th class="text-secondary small fw-bold">Slot ID</th>
                                <th class="text-secondary small fw-bold">RFID UID</th>
                                <th class="text-secondary small fw-bold">HW Face ID</th>
                                <th class="text-secondary small fw-bold">Web Face</th>
                                <th class="text-secondary small fw-bold">Attendance %</th>
                                <th class="text-end pe-3 text-secondary small fw-bold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $attendance_pcts = [];
                            $attendance_counts = [];
                            $total_days = 0;
                            try {
                                $stmt = $pdo->query(
                                    "SELECT student_no, COUNT(DISTINCT DATE(timestamp)) as days_present
                                     FROM attendance_logs
                                     GROUP BY student_no"
                                );
                                while ($row = $stmt->fetch()) {
                                    $attendance_pcts[$row['student_no']] = (int)$row['days_present'];
                                }
                                $totalDaysStmt = $pdo->query("SELECT COUNT(DISTINCT DATE(timestamp)) FROM attendance_logs");
                                $total_days = (int)$totalDaysStmt->fetchColumn();
                            } catch (PDOException $e) {
                                $total_days = 0;
                            }
                            $course_attendance = [];
                            try {
                                $stmt = $pdo->query(
                                    "SELECT student_no, course_code, COUNT(DISTINCT DATE(timestamp)) as days_present
                                     FROM attendance_logs
                                     WHERE course_code != '' AND course_code != 'MANUAL_ENTRY'
                                     GROUP BY student_no, course_code"
                                );
                                while ($row = $stmt->fetch()) {
                                    if (!isset($course_attendance[$row['course_code']])) {
                                        $course_attendance[$row['course_code']] = ['students' => [], 'total_days' => 0];
                                    }
                                    $course_attendance[$row['course_code']]['students'][$row['student_no']] = (int)$row['days_present'];
                                }
                                foreach ($course_attendance as $cc => $data) {
                                    $daysStmt = $pdo->prepare("SELECT COUNT(DISTINCT DATE(timestamp)) FROM attendance_logs WHERE course_code = ?");
                                    $daysStmt->execute([$cc]);
                                    $course_attendance[$cc]['total_days'] = (int)$daysStmt->fetchColumn();
                                }
                            } catch (PDOException $e) {}
                            ?>
                            <?php if(count($enrolled_students) > 0): ?>
                                <?php foreach($enrolled_students as $st): ?>
                                <?php
                                    $overall_days = $attendance_pcts[$st['student_no']] ?? 0;
                                    $overall_pct = $total_days > 0 ? round(($overall_days / $total_days) * 100) : 0;
                                    $badgeClass = $overall_pct >= 80 ? 'bg-success' : ($overall_pct >= 50 ? 'bg-warning text-dark' : ($overall_days > 0 ? 'bg-danger' : 'bg-secondary'));
                                    
                                    $course_data = [];
                                    foreach ($course_attendance as $cc => $data) {
                                        $stu_days = $data['students'][$st['student_no']] ?? 0;
                                        $course_total = $data['total_days'];
                                        $course_pct = $course_total > 0 ? round(($stu_days / $course_total) * 100) : 0;
                                        $course_data[$cc] = ['days' => $stu_days, 'total' => $course_total, 'pct' => $course_pct];
                                    }

                                    $wf = 0;
                                    if (!empty($st['face_descriptor'])) {
                                        $fd = json_decode($st['face_descriptor'], true);
                                        if (is_array($fd) && count($fd) > 0) {
                                            $wf = is_array($fd[0]) ? count($fd) : 1;
                                        }
                                    }
                                ?>
                                <tr class="template-row border-bottom" style="transition: all 0.2s;">
                                    <td class="ps-3 fw-bold text-dark template-student-id" style="font-size: 0.9rem;">
                                        <?php echo htmlspecialchars($st['student_no']); ?>
                                        <?php if(!empty($st['student_name'])): ?>
                                            <div class="text-muted fw-normal template-student-name" style="font-size: 0.8rem;"><?php echo htmlspecialchars($st['student_name']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($st['fingerprint_id']): ?>
                                        <span class="badge rounded-pill bg-primary bg-opacity-10 text-primary border border-primary-subtle px-2 py-1">
                                            <i class="bi bi-fingerprint me-1"></i><?php echo htmlspecialchars($st['fingerprint_id']); ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($st['rfid_uid']): ?>
                                        <span class="badge rounded-pill bg-info bg-opacity-10 text-info border border-info-subtle px-2 py-1">
                                            <i class="bi bi-credit-card me-1"></i><?php echo htmlspecialchars($st['rfid_uid']); ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($st['face_id']): ?>
                                        <span class="badge rounded-pill bg-warning bg-opacity-10 text-warning border border-warning-subtle px-2 py-1">
                                            <i class="bi bi-person-bounding-box me-1"></i><?php echo htmlspecialchars($st['face_id']); ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($wf > 0): ?>
                                        <span class="badge rounded-pill bg-success bg-opacity-10 text-success border border-success-subtle px-2 py-1">
                                            <i class="bi bi-camera me-1"></i><?php echo $wf; ?> angle(s)
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($overall_days > 0): ?>
                                        <span class="badge rounded-pill <?php echo $badgeClass; ?> bg-opacity-10 border border-<?php echo $overall_pct >= 80 ? 'success' : ($overall_pct >= 50 ? 'warning' : 'danger'); ?>-subtle px-2 py-1 attendance-badge" data-student="<?php echo htmlspecialchars($st['student_no']); ?>" data-overall="<?php echo $overall_pct; ?>"
<?php foreach ($course_data as $cc => $cd): ?>data-course-<?php echo htmlspecialchars($cc); ?>="<?php echo $cd['pct']; ?>|<?php echo $cd['days']; ?>/<?php echo $cd['total']; ?>"<?php endforeach; ?>>
                                            <?php echo $overall_pct; ?>%
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted small attendance-badge" data-student="<?php echo htmlspecialchars($st['student_no']); ?>" data-overall="0"
<?php foreach ($course_data as $cc => $cd): ?>data-course-<?php echo htmlspecialchars($cc); ?>="<?php echo $cd['pct']; ?>|<?php echo $cd['days']; ?>/<?php echo $cd['total']; ?>"<?php endforeach; ?>>0%</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="btn-group shadow-sm">
                                            <button onclick="editStudentMap('<?php echo htmlspecialchars($st['student_no'], ENT_QUOTES); ?>','<?php echo htmlspecialchars($st['student_name'], ENT_QUOTES); ?>','<?php echo htmlspecialchars($st['fingerprint_id'], ENT_QUOTES); ?>','<?php echo htmlspecialchars($st['rfid_uid'], ENT_QUOTES); ?>','<?php echo htmlspecialchars($st['face_id'], ENT_QUOTES); ?>')" class="btn btn-sm btn-light border text-info" title="Edit Student Details"><i class="bi bi-pencil-square"></i></button>
                                            <button onclick="if(requireDeviceForOTA()) pushTemplateToDevice('<?php echo htmlspecialchars($st['student_no'], ENT_QUOTES); ?>',<?php echo ($st['fingerprint_id'] ? (int)$st['fingerprint_id'] : 0); ?>)" class="btn btn-sm btn-light border text-primary" title="Push Finger to ESP (requires device selection)" <?php echo empty($st['fingerprint_id']) ? 'disabled' : ''; ?>><i class="bi bi-cloud-arrow-up"></i></button>
                                            <a href="download_template.php?student_no=<?php echo urlencode($st['student_no']); ?>" class="btn btn-sm btn-light border text-success" title="Download Fingerprint" <?php echo empty($st['fingerprint_id']) ? 'style="pointer-events: none; opacity: 0.5;"' : ''; ?>><i class="bi bi-download"></i></a>
                                            <button onclick="deleteStudentMap('<?php echo htmlspecialchars($st['student_no'], ENT_QUOTES); ?>')" class="btn btn-sm btn-light border text-danger" title="Delete Match"><i class="bi bi-trash"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center text-muted py-4 small"><em>No fingerprints enrolled yet</em></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex gap-2">
                    <a href="download_template.php?bulk=true" class="btn btn-sm w-100 fw-semibold btn-success"><i class="bi bi-box-arrow-down me-1"></i>Backup All Records</a>
                    <button onclick="bulkDeleteTemplates()" class="btn btn-sm w-100 fw-semibold btn-danger"><i class="bi bi-exclamation-triangle me-1"></i>Wipe Database</button>
                </div>
            </div>
        </div>
    </div>
</div>

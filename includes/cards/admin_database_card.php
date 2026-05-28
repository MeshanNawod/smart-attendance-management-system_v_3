<?php
// admin_database_card.php
?>
<div class="card card-clean shadow-none mb-4 border-0">
    <div class="card-header bg-danger text-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <strong><i class="bi bi-shield-lock-fill me-2"></i>Admin Database</strong>
        <div class="d-flex align-items-center gap-2">
            <label class="form-label small text-white mb-0 fw-bold"><i class="bi bi-cpu me-1"></i>Target Device:</label>
            <select id="admin-device-select" class="form-select form-select-sm bg-dark text-white border-light" style="min-width:180px;max-width:260px;" onchange="onAdminDeviceChange(this.value)">
                <option value="">&#x26A0;&#xFE0F; Select device first…</option>
            </select>
            <span id="admin-device-status" class="badge bg-light text-dark">None</span>
        </div>
    </div>
    <div id="admin-device-required-alert" class="alert alert-warning border-0 rounded-0 mb-0 py-2 px-3 d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
        <span class="small"><strong>No hardware device selected.</strong> Choose an active ESP32 node above before using <em>Enroll Finger</em> or <em>Scan RFID</em>. Name/face/voice operations work without a device.</span>
    </div>
    <div class="card-body bg-light">
        <div class="row">
            <div class="col-md-6 border-end pe-md-4">
                <h6 class="text-danger fw-bold mb-2"><i class="bi bi-person-badge me-1"></i>Admin Hardware Enrollment</h6>
                <p class="small text-muted mb-2">Enroll an admin to perform special hardware actions.</p>
                <div class="input-group mb-2 shadow-sm rounded">
                    <span class="input-group-text bg-danger text-white"><i class="bi bi-person-badge"></i></span>
                    <input type="text" id="enroll-admin-name" class="form-control" placeholder="Admin Name (e.g. Dr. Smith)">
                </div>
                <div class="input-group mb-2">
                    <span class="input-group-text bg-white border-danger-subtle"><i class="bi bi-fingerprint text-danger"></i></span>
                    <input type="number" id="enroll-admin-id" class="form-control bg-light text-muted border-danger-subtle" value="<?php echo $admin_next_id ?? 1; ?>" readonly title="Auto-assigned ID">
                    <button class="btn btn-outline-danger fw-semibold" onclick="if(requireAdminDevice()) triggerAdminEnroll()">
                        <i class="bi bi-plus-circle me-1"></i>Enroll Finger
                    </button>
                </div>
                <div class="input-group mb-2">
                    <span class="input-group-text bg-white border-danger-subtle"><i class="bi bi-credit-card text-danger"></i></span>
                    <input type="text" id="enroll-admin-rfid" class="form-control border-danger-subtle" placeholder="Scan RFID on device or type UID">
                    <button class="btn btn-outline-danger fw-semibold" onclick="if(requireAdminDevice()) triggerRfidScan('enroll-admin-rfid')" title="Trigger device to scan RFID card">
                        <i class="bi bi-credit-card-2-front me-1"></i>Scan RFID
                    </button>
                    <button class="btn btn-danger text-white fw-semibold" onclick="triggerAdminEnrollRfid()">
                        <i class="bi bi-link me-1"></i>Link RFID
                    </button>
                </div>
                <div class="input-group mb-3">
                    <span class="input-group-text bg-white border-danger-subtle"><i class="bi bi-person-bounding-box text-danger"></i></span>
                    <input type="text" id="enroll-admin-face" class="form-control border-danger-subtle" placeholder="Raspberry Pi Face ID (e.g. face_101)">
                    <button class="btn btn-outline-danger fw-semibold px-2" onclick="autoFindFace('enroll-admin-face')" title="Auto Find Latest Scan"><i class="bi bi-search"></i></button>
                    <button class="btn btn-danger text-white fw-semibold" onclick="triggerAdminEnrollFace()">
                        <i class="bi bi-link me-1"></i>Link Face
                    </button>
                </div>
                <div class="input-group mb-3">
                    <span class="input-group-text bg-white border-danger-subtle"><i class="bi bi-mic text-danger"></i></span>
                    <input type="text" id="enroll-admin-voice" class="form-control border-danger-subtle" placeholder="Admin Name">
                    <button class="btn btn-outline-danger fw-semibold" onclick="window.goToVoiceEnroll('enroll-admin-voice')" title="Go to Voice tab">
                        <i class="bi bi-mic me-1"></i>Enroll Voice
                    </button>
                </div>

                <h6 class="text-danger fw-bold mb-2 border-top pt-3"><i class="bi bi-camera-video me-1"></i>Admin Web Face Enrollment</h6>
                <p class="small text-muted mb-2">Enroll admin face via webcam for RPi-based recognition.</p>
                <div class="input-group mb-2 shadow-sm rounded">
                    <span class="input-group-text bg-danger text-white"><i class="bi bi-person-badge"></i></span>
                    <input type="text" id="admin-webface-name" class="form-control" placeholder="Admin Name">
                </div>
                <div class="d-flex align-items-center gap-2 mb-2 p-2 bg-white border rounded shadow-sm">
                    <i class="bi bi-images text-danger"></i>
                    <label class="form-label small fw-bold mb-0 text-muted flex-shrink-0">Capture Count</label>
                    <input type="range" class="form-range flex-grow-1" min="3" max="15" value="7" id="admin-enroll-capture-count" oninput="document.getElementById('admin-enroll-count-val').textContent=this.value">
                    <span class="badge bg-danger enroll-count-badge" id="admin-enroll-count-val">7</span>
                </div>
                <button id="btn-start-admin-enroll" class="btn btn-outline-danger fw-semibold w-100" onclick="startAdminWebFaceEnrollment(document.getElementById('admin-webface-name').value, parseInt(document.getElementById('admin-enroll-capture-count').value))">
                    <i class="bi bi-person-bounding-box me-1"></i>Start Admin Webcam Enrollment
                </button>
                <div class="mt-2 mb-2 text-center">
                    <span class="badge bg-secondary mb-1">OR UPLOAD PHOTO</span>
                    <input type="file" id="admin-web-enroll-image" accept="image/*" class="form-control form-control-sm" onchange="adminEnrollUploadedImage(this)">
                </div>
                <div id="admin-web-enroll-container" class="d-none border rounded p-2 mt-2 bg-white text-center shadow-sm">
                    <select id="admin-enroll-camera-select" class="form-select form-select-sm mb-2 d-none" onchange="switchAdminEnrollCamera()"></select>
                    <video id="admin-web-enroll-video" autoplay muted playsinline class="w-100 rounded bg-dark mb-2" style="height: auto; display: block;"></video>
                    <div id="admin-web-enroll-status" class="small fw-bold mb-2"></div>
                    <div class="d-flex gap-2">
                        <button id="btn-capture-admin-enroll" class="btn btn-success flex-grow-1 fw-bold d-none"><i class="bi bi-camera me-1"></i>Capture Face</button>
                        <button id="btn-cancel-admin-enroll" class="btn btn-secondary flex-grow-1 fw-bold d-none">Cancel</button>
                    </div>
                </div>
            </div>

            <div class="col-md-6 ps-md-4">
                <h6 class="text-muted fw-bold mb-2"><i class="bi bi-list-check me-1"></i>Registered Admins</h6>
                <div class="scroll-y-lg border rounded bg-white shadow-sm">
                    <table class="table table-hover table-sm mb-0 table-xs">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th class="ps-3 text-secondary small fw-bold">Admin Name</th>
                                <th class="text-secondary small fw-bold">Finger</th>
                                <th class="text-secondary small fw-bold">RFID</th>
                                <th class="text-secondary small fw-bold">Face ID</th>
                                <th class="text-secondary small fw-bold">Web Face</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($admins) > 0): ?>
                                <?php foreach ($admins as $a): ?>
                                <tr>
                                    <td class="ps-3 fw-bold"><?php echo htmlspecialchars($a['admin_name']); ?></td>
                                    <td><?php if ($a['fingerprint_id']): ?><span class="badge bg-primary text-white"><i class="bi bi-fingerprint me-1"></i><?php echo htmlspecialchars($a['fingerprint_id']); ?></span><?php else: ?><span class="text-muted small">-</span><?php endif; ?></td>
                                    <td><?php if ($a['rfid_uid']): ?><span class="badge bg-info text-dark"><i class="bi bi-credit-card me-1"></i><?php echo htmlspecialchars($a['rfid_uid']); ?></span><?php else: ?><span class="text-muted small">-</span><?php endif; ?></td>
                                    <td><?php if ($a['face_id']): ?><span class="badge bg-warning text-dark"><i class="bi bi-person-bounding-box me-1"></i><?php echo htmlspecialchars($a['face_id']); ?></span><?php else: ?><span class="text-muted small">-</span><?php endif; ?></td>
                                    <td>
                                        <?php
                                            $awf = 0;
                                            if (!empty($a['face_descriptor'])) {
                                                $ad = json_decode($a['face_descriptor'], true);
                                                if (is_array($ad) && count($ad) > 0) {
                                                    $awf = is_array($ad[0]) ? count($ad) : 1;
                                                }
                                            }
                                        ?>
                                        <?php if ($awf > 0): ?><span class="badge bg-success text-white"><i class="bi bi-camera me-1"></i><?php echo $awf; ?></span><?php else: ?><span class="text-muted small">-</span><?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center text-muted py-3 small"><em>No admins registered</em></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.requireAdminDevice = function() {
    const sel = document.getElementById('admin-device-select');
    if (!sel || !sel.value || sel.value === '' || sel.value === 'WEB_DASHBOARD') {
        showToast('&#x26A0;&#xFE0F; Please select an active ESP32 hardware device first.', 'warning');
        return false;
    }
    return true;
};
window.onAdminDeviceChange = function(val) {
    const status = document.getElementById('admin-device-status');
    const alert = document.getElementById('admin-device-required-alert');
    if (val && val !== '') {
        status.textContent = val === 'WEB_DASHBOARD' ? 'Web Only' : val;
        status.className = 'badge bg-success text-white';
        if (alert) alert.style.display = 'none';
    } else {
        status.textContent = 'None';
        status.className = 'badge bg-light text-dark';
        if (alert) alert.style.display = 'flex';
    }
};
// Populate admin device select from students-device-select
(function() {
    const stuSel = document.getElementById('students-device-select');
    const adminSel = document.getElementById('admin-device-select');
    if (stuSel && adminSel) {
        function syncAdminDevices() {
            const val = stuSel.value;
            adminSel.innerHTML = stuSel.innerHTML;
            adminSel.value = val;
            onAdminDeviceChange(val);
        }
        syncAdminDevices();
        stuSel.addEventListener('change', syncAdminDevices);
    }
})();
</script>

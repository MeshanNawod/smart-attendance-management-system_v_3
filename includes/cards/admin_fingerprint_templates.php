<?php // admin_fingerprint_templates.php — Fingerprint Template Manager Card ?>
<div class="card border-0 mb-3 overflow-hidden" style="border-radius:12px;box-shadow:0 4px 16px rgba(161,30,23,0.08);">
    <div class="card-header py-2 px-3 d-flex align-items-center gap-2"
         style="background:linear-gradient(135deg,#0d6efd,#0a58ca);border-bottom:none;">
        <i class="bi bi-fingerprint text-white"></i>
        <strong class="small fw-bold text-white">Fingerprint Templates</strong>
        <span class="badge bg-light text-primary ms-auto small" id="fp-tpl-count">0</span>
    </div>
    <div class="card-body py-2 px-3 bg-white">
        <!-- Action buttons -->
        <div class="d-flex gap-2 mb-2">
            <button class="btn btn-sm btn-outline-primary fw-bold" onclick="refreshFpTplList()">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
            <button class="btn btn-sm btn-outline-success fw-bold" onclick="backupAllFpTpls()">
                <i class="bi bi-download me-1"></i>Backup All
            </button>
            <button class="btn btn-sm btn-outline-warning fw-bold" onclick="document.getElementById('fp-restore-input').click()">
                <i class="bi bi-upload me-1"></i>Restore
            </button>
            <input type="file" id="fp-restore-input" accept=".json" style="display:none;" onchange="restoreFpTpls(this)">
        </div>

        <!-- Template list -->
        <div id="fp-tpl-table-wrapper" style="max-height:260px;overflow-y:auto;">
            <table class="table table-sm table-hover mb-0" style="font-size:0.72rem;">
                <thead class="table-dark small" style="position:sticky;top:0;z-index:2;">
                    <tr>
                        <th>Slot</th>
                        <th>Student No</th>
                        <th>Name</th>
                        <th>Template</th>
                        <th style="width:50px;"></th>
                    </tr>
                </thead>
                <tbody id="fp-tpl-tbody">
                    <tr><td colspan="5" class="text-center text-muted py-3 small">Loading...</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Single upload form -->
        <div class="mt-2 p-2 rounded" style="background:#f8fafc;border:1px dashed #dee2e6;">
            <div class="small fw-bold text-muted mb-1"><i class="bi bi-plus-circle me-1"></i>Upload Single Template</div>
            <div class="row g-1">
                <div class="col-4">
                    <input type="text" id="fp-upload-student" class="form-control form-control-sm" placeholder="Student No">
                </div>
                <div class="col-4">
                    <input type="file" id="fp-upload-file" accept=".dat,.txt,.hex" class="form-control form-control-sm" style="font-size:0.7rem;">
                </div>
                <div class="col-4">
                    <button class="btn btn-sm btn-primary w-100 fw-bold" onclick="uploadSingleFpTpl()">
                        <i class="bi bi-upload me-1"></i>Upload
                    </button>
                </div>
            </div>
            <div id="fp-upload-status" class="small text-muted mt-1"></div>
        </div>
    </div>
</div>

<script>
function refreshFpTplList() {
    var tbody = document.getElementById('fp-tpl-tbody');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-2 small"><i class="bi bi-arrow-clockwise spin me-1"></i>Loading...</td></tr>';

    fetch('/csc2052/api/student.php?action=get_all_students&_=' + Date.now())
        .then(function(r) { return r.json(); })
        .then(function(d) {
            var students = d.students || [];
            var filtered = students.filter(function(s) { return s.fp_template_hex && s.fp_template_hex.length > 10; });
            var badge = document.getElementById('fp-tpl-count');
            if (badge) badge.textContent = filtered.length;

            if (filtered.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-2 small">No templates stored.</td></tr>';
                return;
            }
            var html = '';
            for (var i = 0; i < filtered.length; i++) {
                var s = filtered[i];
                var slot = s.fingerprint_id || '-';
                html += '<tr>' +
                    '<td class="small">' + htmlspecialchars(String(slot)) + '</td>' +
                    '<td class="small">' + htmlspecialchars(s.student_no) + '</td>' +
                    '<td class="small">' + htmlspecialchars(s.student_name || '') + '</td>' +
                    '<td class="small text-muted" style="max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + htmlspecialchars(s.fp_template_hex.substring(0, 200)) + '">' + htmlspecialchars(s.fp_template_hex.substring(0, 20)) + '...</td>' +
                    '<td>' +
                        '<button class="btn btn-sm btn-link text-danger p-0 px-1" onclick="deleteFpTpl(\'' + htmlspecialchars(s.student_no) + '\')" title="Delete template"><i class="bi bi-trash"></i></button>' +
                        '<button class="btn btn-sm btn-link text-primary p-0 px-1" onclick="downloadSingleFpTpl(\'' + htmlspecialchars(s.student_no) + '\')" title="Download"><i class="bi bi-download"></i></button>' +
                    '</td>' +
                    '</tr>';
            }
            tbody.innerHTML = html;
        })
        .catch(function(e) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-danger text-center py-2 small">Error: ' + e.message + '</td></tr>';
        });
}

function deleteFpTpl(studentNo) {
    if (!confirm('Delete fingerprint template for ' + studentNo + '?')) return;
    var fd = new FormData();
    fd.append('action', 'delete_fp_template');
    fd.append('student_no', studentNo);
    fetch('/csc2052/api/esp_courses.php', { method: 'POST', body: fd })
        .then(function(r) { return r.text(); })
        .then(function(resp) {
            if (resp.indexOf('OK:DELETED') === 0) {
                refreshFpTplList();
            } else {
                alert('Error: ' + resp);
            }
        })
        .catch(function(e) { alert('Network error'); });
}

function downloadSingleFpTpl(studentNo) {
    window.open('/csc2052/download_template.php?student_no=' + encodeURIComponent(studentNo), '_blank');
}

function backupAllFpTpls() {
    window.open('/csc2052/api/esp_courses.php?action=backup_all', '_blank');
}

function restoreFpTpls(input) {
    var file = input.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        var fd = new FormData();
        fd.append('action', 'restore_all');
        fd.append('json', e.target.result);
        fetch('/csc2052/api/esp_courses.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.status === 'ok') {
                    alert('Restored ' + d.imported + ' templates.' + (d.errors && d.errors.length ? '\nErrors: ' + d.errors.join(', ') : ''));
                    refreshFpTplList();
                } else {
                    alert('Error: ' + (d.message || 'Unknown'));
                }
            })
            .catch(function(e) { alert('Network error'); });
    };
    reader.readAsText(file);
    input.value = '';
}

function uploadSingleFpTpl() {
    var studentNo = document.getElementById('fp-upload-student').value.trim();
    var fileInput = document.getElementById('fp-upload-file');
    var statusEl = document.getElementById('fp-upload-status');
    if (!studentNo) { statusEl.innerHTML = '<span class="text-danger">Enter student number.</span>'; return; }
    if (!fileInput.files || !fileInput.files[0]) { statusEl.innerHTML = '<span class="text-danger">Select a template file.</span>'; return; }

    var reader = new FileReader();
    reader.onload = function(e) {
        var hex = e.target.result.trim();
        var fd = new FormData();
        fd.append('action', 'upload_fp_template');
        fd.append('student_no', studentNo);
        fd.append('hex', hex);
        fetch('/csc2052/api/esp_courses.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.status === 'ok') {
                    statusEl.innerHTML = '<span class="text-success">Uploaded to slot ' + d.slot + '</span>';
                    refreshFpTplList();
                } else {
                    statusEl.innerHTML = '<span class="text-danger">' + (d.message || 'Error') + '</span>';
                }
            })
            .catch(function(e) { statusEl.innerHTML = '<span class="text-danger">Network error</span>'; });
    };
    reader.readAsText(fileInput.files[0]);
    fileInput.value = '';
}

// Initial load
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(refreshFpTplList, 800);
});
</script>

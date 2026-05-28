<?php // admin_logs_viewer.php — System Logs Viewer Card ?>
<div class="card border-0 mb-3 overflow-hidden" style="border-radius:12px;box-shadow:0 4px 16px rgba(161,30,23,0.08);">
    <div class="card-header py-2 px-3 d-flex align-items-center gap-2"
         style="background:linear-gradient(135deg,#1e293b,#334155);border-bottom:none;">
        <i class="bi bi-journal-code text-white"></i>
        <strong class="small fw-bold text-white">System Logs Viewer</strong>
        <span class="badge bg-light text-dark ms-auto small" id="logs-count-badge">0</span>
    </div>
    <div class="card-body py-2 px-3 bg-white">
        <!-- Filter row -->
        <div class="row g-1 mb-2">
            <div class="col-4">
                <select id="syslog-type" class="form-select form-select-sm" onchange="fetchSystemLogs()">
                    <option value="">All Types</option>
                    <option value="info">Info</option>
                    <option value="warning">Warning</option>
                    <option value="error">Error</option>
                    <option value="power">Power</option>
                    <option value="debug">Debug</option>
                </select>
            </div>
            <div class="col-4">
                <select id="syslog-source" class="form-select form-select-sm" onchange="fetchSystemLogs()">
                    <option value="">All Sources</option>
                    <option value="esp32">ESP32</option>
                    <option value="fingerprint">Fingerprint</option>
                    <option value="rfid">RFID</option>
                    <option value="web">Web</option>
                    <option value="system">System</option>
                </select>
            </div>
            <div class="col-4">
                <input type="text" id="syslog-search" class="form-control form-control-sm" placeholder="Search logs..."
                       oninput="fetchSystemLogs()">
            </div>
        </div>

        <!-- ESP32 SD fetch row -->
        <div class="row g-1 mb-2 align-items-center">
            <div class="col-5">
                <input type="text" id="esp-sd-ip" class="form-control form-control-sm" placeholder="ESP32 IP (e.g. 192.168.1.100)">
            </div>
            <div class="col-3">
                <select id="esp-sd-file" class="form-select form-select-sm">
                    <option value="attendance.csv">Attendance</option>
                    <option value="error.csv">Errors</option>
                    <option value="power.csv">Power</option>
                </select>
            </div>
            <div class="col-4">
                <button class="btn btn-sm btn-outline-maroon w-100 fw-bold" onclick="fetchEspSdLog()">
                    <i class="bi bi-hdd-network me-1"></i>Fetch SD
                </button>
            </div>
        </div>

        <!-- Logs table -->
        <div id="syslog-table-wrapper" style="max-height:220px;overflow-y:auto;">
            <table class="table table-sm table-hover mb-0" style="font-size:0.72rem;">
                <thead class="table-dark small" style="position:sticky;top:0;z-index:2;">
                    <tr>
                        <th style="width:70px;">Time</th>
                        <th style="width:55px;">Type</th>
                        <th style="width:45px;">Src</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody id="syslog-tbody">
                    <tr><td colspan="4" class="text-center text-muted py-3 small">Loading...</td></tr>
                </tbody>
            </table>
        </div>

        <!-- ESP SD raw log area (hidden by default) -->
        <div id="esp-sd-result" class="d-none mt-2">
            <div class="d-flex justify-content-between align-items-center">
                <strong class="small text-muted">SD Card: <span id="esp-sd-filename"></span></strong>
                <button class="btn btn-sm btn-link p-0 text-decoration-none" onclick="toggleEspSdResult()">
                    <i class="bi bi-x-circle"></i>
                </button>
            </div>
            <pre id="esp-sd-content" style="max-height:180px;overflow-y:auto;font-size:0.65rem;background:#1e293b;color:#a5d6ff;padding:6px;border-radius:6px;margin-top:4px;"></pre>
        </div>

        <div class="d-flex gap-2 mt-2">
            <button class="btn btn-sm btn-outline-secondary" onclick="fetchSystemLogs()">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
            <button class="btn btn-sm btn-outline-danger ms-auto" onclick="clearSystemLogs()">
                <i class="bi bi-trash me-1"></i>Clear
            </button>
        </div>
    </div>
</div>

<script>
function fetchSystemLogs() {
    var type = document.getElementById('syslog-type').value;
    var source = document.getElementById('syslog-source').value;
    var search = document.getElementById('syslog-search').value;
    var tbody = document.getElementById('syslog-tbody');
    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-2 small"><i class="bi bi-arrow-clockwise spin me-1"></i>Loading...</td></tr>';

    var params = new URLSearchParams({ action: 'fetch', limit: '100' });
    if (type) params.set('log_type', type);
    if (source) params.set('source', source);
    if (search) params.set('search', search);

    fetch('/csc2052/api/system_logs.php?' + params.toString())
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.status !== 'ok') { tbody.innerHTML = '<tr><td colspan="4" class="text-danger text-center py-2 small">' + (d.message || 'Error') + '</td></tr>'; return; }
            var badge = document.getElementById('logs-count-badge');
            if (badge) badge.textContent = d.total || d.data.length;
            if (!d.data || d.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-2 small">No logs found.</td></tr>';
                return;
            }
            var html = '';
            for (var i = 0; i < d.data.length; i++) {
                var row = d.data[i];
                var ts = row.created_at || '';
                if (ts.length > 16) ts = ts.substring(11, 19);
                var color = row.log_type === 'error' ? '#dc3545' : row.log_type === 'warning' ? '#ffc107' : row.log_type === 'power' ? '#0dcaf0' : '#6c757d';
                html += '<tr>' +
                    '<td class="text-nowrap small text-muted">' + htmlspecialchars(ts) + '</td>' +
                    '<td><span class="badge bg-' + (row.log_type === 'error' ? 'danger' : row.log_type === 'warning' ? 'warning text-dark' : row.log_type === 'power' ? 'info' : 'secondary') + '" style="font-size:0.6rem;">' + htmlspecialchars(row.log_type) + '</span></td>' +
                    '<td class="small">' + htmlspecialchars(row.source) + '</td>' +
                    '<td class="small" title="' + htmlspecialchars(row.details || '') + '">' + htmlspecialchars(row.message.length > 100 ? row.message.substring(0, 100) + '...' : row.message) + '</td>' +
                    '</tr>';
            }
            tbody.innerHTML = html;
        })
        .catch(function(e) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-danger text-center py-2 small">Network error</td></tr>';
        });
}

function fetchEspSdLog() {
    var ip = document.getElementById('esp-sd-ip').value.trim();
    var file = document.getElementById('esp-sd-file').value;
    var resultDiv = document.getElementById('esp-sd-result');
    var contentEl = document.getElementById('esp-sd-content');
    var filenameEl = document.getElementById('esp-sd-filename');

    if (!ip) { alert('Enter ESP32 IP address.'); return; }

    contentEl.textContent = 'Fetching...';
    resultDiv.classList.remove('d-none');
    filenameEl.textContent = file;

    fetch('/csc2052/api/system_logs.php?action=esp_sd_log&esp_ip=' + encodeURIComponent(ip) + '&file=' + encodeURIComponent(file))
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.status !== 'ok') {
                contentEl.textContent = 'Error: ' + (d.message || 'Failed');
                return;
            }
            // Format as table
            var text = '';
            if (d.rows && d.rows.length > 0) {
                for (var i = 0; i < d.rows.length; i++) {
                    text += d.rows[i].join(' | ') + '\n';
                }
            } else {
                text = d.raw || '(empty)';
            }
            contentEl.textContent = text;
        })
        .catch(function(e) {
            contentEl.textContent = 'Network error: ' + e.message;
        });
}

function toggleEspSdResult() {
    var el = document.getElementById('esp-sd-result');
    el.classList.toggle('d-none');
}

function clearSystemLogs() {
    if (!confirm('Clear all system logs?')) return;
    var fd = new FormData();
    fd.append('action', 'clear');
    fetch('/csc2052/api/system_logs.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.status === 'ok') {
                fetchSystemLogs();
            } else {
                alert('Error: ' + (d.message || 'Failed'));
            }
        })
        .catch(function(e) { alert('Network error'); });
}

// Initial load
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(fetchSystemLogs, 500);
});
</script>

let isCommandActive = false;

// ─── OVERRIDE NATIVE ALERT TO USE SWEETALERT2 ──────────────────────
window.alert = function(message) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'warning',
            title: 'Attention',
            text: message,
            confirmButtonColor: '#6366f1'
        });
    } else {
        // Fallback to original or toast if SweetAlert2 is not loaded
        showToast(message, 'warning');
    }
};

window.highlightInvalidInput = function(elementId) {
    const el = document.getElementById(elementId);
    if (!el) return;
    el.classList.add('is-invalid');
    el.focus({ preventScroll: true }); // Prevent jumping to top before smooth scroll
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    
    // Add a pulse animation class if defined in CSS, or just rely on Bootstrap's is-invalid
    setTimeout(() => {
        el.classList.remove('is-invalid');
    }, 4000);
};

// ─── TOAST NOTIFICATION SYSTEM ─────────────────────────────────────
window.showToast = function(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = 'position:fixed;top:80px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;max-width:350px;';
        document.body.appendChild(container);
    }
    
    const safeMessage = window.escapeHtml ? window.escapeHtml(message) : String(message ?? '');
    const toast = document.createElement('div');
    const bgClass = type === 'success' ? 'bg-success' : type === 'error' || type === 'danger' ? 'bg-danger' : type === 'warning' ? 'bg-warning text-dark' : 'bg-primary';
    toast.className = `toast show ${bgClass} text-white shadow`;
    toast.style.cssText = 'border-radius:10px;padding:12px 16px;font-size:0.85rem;animation:slideIn 0.3s ease;';
    toast.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' || type === 'danger' ? 'exclamation-triangle' : type === 'warning' ? 'exclamation-circle' : 'info-circle'} me-2"></i>${safeMessage}`;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
};

// ─── DEVICE REGISTRY & AUTO-DISCOVERY ─────────────────────────────
let _discoveredDevices = []; // cache of {ip, name, type, online, blocked}

// Get CSRF token from meta tag
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

// Auto-attach CSRF token to all POST requests using FormData (covers all API calls)
(function() {
    const origFetch = window.fetch;
    window.fetch = function(url, opts) {
        if (opts && opts.method && opts.method.toUpperCase() === 'POST' && opts.body instanceof FormData) {
            if (!opts.body.has('csrf_token') && !opts.body.has('hardware_key')) {
                opts.body.append('csrf_token', getCsrfToken());
            }
        }
        return origFetch.call(this, url, opts);
    };
})();

const OTA_API_KEY = 'ss_hw_api_key_2052';

function getEspIp() {
    const sel = document.getElementById('action-target-device');
    if (sel && sel.value && sel.value !== 'WEB_DASHBOARD' && sel.value !== '__loading__') {
        return sel.value; // value is the IP stored in the option
    }
    // fallback to navbar input
    const input = document.getElementById('esp-ip');
    if (input && input.value.trim()) {
        localStorage.setItem('esp_ip_cache', input.value.trim());
        return input.value.trim();
    }
    return localStorage.getItem('esp_ip_cache') || null;
}

function getDeviceName() {
    const sel = document.getElementById('action-target-device');
    if (sel && sel.value && sel.value !== 'WEB_DASHBOARD' && sel.value !== '__loading__') {
        return sel.value;
    }
    return null;
}

async function refreshDeviceList() {
    const sel = document.getElementById('action-target-device');
    const refreshBtn = document.querySelector('[onclick="refreshDeviceList()"]');
    if (refreshBtn) refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i>';

    try {
        const res = await fetch('/csc2052/api/devices.php?action=list');
        const data = await res.json();
        _discoveredDevices = data.devices || [];
        populateDeviceSelector();
        populateStudentsDeviceSelector();
        renderDeviceManageTable();
    } catch(e) {
        console.warn('[Devices] Could not fetch device list:', e);
    } finally {
        if (refreshBtn) refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i>';
    }
}

function populateDeviceSelector() {
    const sel = document.getElementById('action-target-device');
    if (!sel || sel.tagName !== 'SELECT') return;

    const currentVal = sel.value;

    sel.innerHTML = '<option value="WEB_DASHBOARD">Web Dashboard (This Computer)</option>';

    _discoveredDevices.forEach(dev => {
        if (dev.blocked) return;
        const opt = document.createElement('option');
        opt.value = dev.ip;
        opt.dataset.type = dev.type;
        opt.textContent = `${dev.online ? 'Online' : 'Offline'} - ${dev.name} (${dev.ip})`;
        sel.appendChild(opt);
    });

    if (_discoveredDevices.length === 0) {
        const opt = document.createElement('option');
        opt.disabled = true;
        opt.textContent = '— No hardware nodes detected —';
        sel.appendChild(opt);
    }

    if (currentVal) {
        for (let i = 0; i < sel.options.length; i++) {
            if (sel.options[i].value === currentVal) { sel.value = currentVal; break; }
        }
    }
}

function renderDeviceManageTable() {
    const el = document.getElementById('device-list-table');
    if (!el) return;

    if (_discoveredDevices.length === 0) {
        el.innerHTML = '<em class="text-muted">No hardware nodes discovered yet.</em>';
        return;
    }

    let html = '<table class="table table-sm table-bordered mb-0" style="font-size:0.85rem">';
    html += '<thead class="table-dark"><tr><th>Device</th><th>IP</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
    
    _discoveredDevices.forEach(dev => {
        const icon  = dev.type === 'rpi' ? '<i class="bi bi-server text-danger me-1"></i>' : '<i class="bi bi-cpu text-primary me-1"></i>';
        const badge = dev.online  ? '<span class="badge bg-success">Online</span>'  : '<span class="badge bg-secondary">Offline</span>';
        const block = dev.blocked ? '<span class="badge bg-danger ms-1">Blocked</span>' : '';
        const ipArg = JSON.stringify(dev.ip || '');
        const nameArg = JSON.stringify(dev.name || '');
        const blockBtn = dev.blocked
            ? `<button class="btn btn-xs btn-outline-success py-0 px-1" onclick='deviceAction("unblock", ${ipArg})'>Unblock</button>`
            : `<button class="btn btn-xs btn-outline-danger py-0 px-1" onclick='deviceAction("block", ${ipArg})'>Block</button>`;
        
        html += `<tr>
            <td>${icon}<strong>${escapeHtml(dev.name)}</strong></td>
            <td><code>${escapeHtml(dev.ip)}</code></td>
            <td>${badge}${block}</td>
            <td class="d-flex gap-1">
                ${blockBtn}
                <button class="btn btn-xs btn-outline-secondary py-0 px-1" onclick='renameDevice(${ipArg}, ${nameArg})'><i class="bi bi-pencil"></i></button>
                <button class="btn btn-xs btn-outline-dark py-0 px-1" onclick='deviceAction("forget", ${ipArg})'><i class="bi bi-trash"></i></button>
            </td>
        </tr>`;
    });
    html += '</tbody></table>';
    el.innerHTML = html;
}

window.toggleManageDevices = function() {
    const panel = document.getElementById('device-manage-panel');
    if (!panel) return;
    panel.classList.toggle('d-none');
    if (!panel.classList.contains('d-none')) renderDeviceManageTable();
};

window.onTargetDeviceChange = function() {
    const hidden = document.getElementById('action-target-device');
    const input = document.getElementById('device-monitor-search');
    if (!hidden) return;
    const ip = hidden.value;
    
    // Update navbar IP to keep LCD polling in sync
    const navIp = document.getElementById('esp-ip');
    if (navIp && ip !== 'WEB_DASHBOARD' && ip !== '__loading__') {
        navIp.value = ip;
        localStorage.setItem('esp_ip_cache', ip);
    }
    if (input) input.value = ip === 'WEB_DASHBOARD' ? 'Web Dashboard' : (input.value || 'Web Dashboard');
    logToTerminal('[TARGET] Switched to: ' + (ip === 'WEB_DASHBOARD' ? 'Web Dashboard' : ip));
};

window.deviceAction = async function(action, ip) {
    const fd = new FormData();
    fd.append('ip', ip);
    try {
        const res = await fetch(`api/devices.php?action=${action}`, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'ok') {
            await refreshDeviceList();
        } else {
            alert('Error: ' + data.message);
        }
    } catch(e) { alert('Network error.'); }
};

window.renameDevice = async function(ip, currentName) {
    const name = prompt('Enter new name for device ' + ip + ':', currentName);
    if (!name || !name.trim()) return;
    const fd = new FormData();
    fd.append('ip', ip);
    fd.append('name', name.trim());
    try {
        const res = await fetch('/csc2052/api/devices.php?action=rename', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'ok') await refreshDeviceList();
        else alert('Error: ' + data.message);
    } catch(e) { alert('Network error.'); }
};

// kept for backward compat - no longer needed but guard against errors
window.toggleActionTargetIp = function() {};

// ─── STUDENTS PAGE: DEVICE SELECTION GUARD ─────────────────────────
/**
 * requireDeviceForOTA() — must be called before any fingerprint OTA operation
 * (Enroll Finger, Push Template).
 * Returns true if a valid ESP32 is selected, false + shows toast if not.
 */
window.requireDeviceForOTA = function() {
    // Check students-page selector first, then fall back to global selector
    const stuSel = document.getElementById('students-device-select');
    const globalSel = document.getElementById('action-target-device');

    const stuVal = stuSel ? stuSel.value : '';
    const globalVal = globalSel ? globalSel.value : '';

    const hasDevice = (stuVal && stuVal !== '' && stuVal !== 'WEB_DASHBOARD')
                   || (globalVal && globalVal !== '' && globalVal !== 'WEB_DASHBOARD' && globalVal !== '__loading__');

    if (!hasDevice) {
        showToast('⚠️ Please select an active ESP32 hardware device first (in the "Target Device" selector above).', 'warning');
        // Flash the warning alert
        const alertBanner = document.getElementById('students-device-required-alert');
        if (alertBanner) {
            alertBanner.style.transition = 'background 0.2s';
            alertBanner.style.background = '#fff3cd';
            setTimeout(() => alertBanner.style.background = '', 600);
            alertBanner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        // Flash the device select
        if (stuSel) {
            stuSel.style.boxShadow = '0 0 0 3px rgba(255,193,7,0.5)';
            setTimeout(() => stuSel.style.boxShadow = '', 1200);
        }
        return false;
    }
    return true;
};

/**
 * onStudentsDeviceChange() — called when user picks a device in the students page selector.
 * Syncs to the global action-target-device and hides the warning banner.
 */
window.onStudentsDeviceChange = function(val) {
    const statusBadge = document.getElementById('students-device-status');
    const alertBanner = document.getElementById('students-device-required-alert');

    if (val && val !== '') {
        // Sync to global selector if available
        const globalSel = document.getElementById('action-target-device');
        if (globalSel) {
            // Try to find matching option
            const opts = [...globalSel.options];
            const match = opts.find(o => o.value === val || o.value.includes(val));
            if (match) globalSel.value = match.value;
        }
        // Update nav IP cache
        if (val !== 'WEB_DASHBOARD') {
            localStorage.setItem('esp_ip_cache', val);
            const navIp = document.getElementById('esp-ip');
            if (navIp) navIp.value = val;
        }
        if (statusBadge) {
            statusBadge.className = 'badge bg-success';
            statusBadge.textContent = val === 'WEB_DASHBOARD' ? 'Web Only' : 'Device Set ✓';
        }
        if (alertBanner) alertBanner.style.display = 'none';
    } else {
        if (statusBadge) {
            statusBadge.className = 'badge bg-secondary';
            statusBadge.textContent = 'None';
        }
        if (alertBanner) alertBanner.style.display = '';
    }
};

/**
 * populateStudentsDeviceSelector() — fills the students page device selector
 * with discovered ESP32 nodes (excludes RPi).
 */
function populateStudentsDeviceSelector() {
    const sel = document.getElementById('students-device-select');
    if (!sel) return;

    const currentVal = sel.value;
    sel.innerHTML = '<option value="">⚠️ Select device first…</option>';

    _discoveredDevices.forEach(dev => {
        if (dev.type === 'rpi' || dev.blocked) return;
        const opt = document.createElement('option');
        opt.value = dev.ip;
        opt.textContent = `${dev.online ? '🟢' : '🔴'} ${dev.name} (${dev.ip})`;
        sel.appendChild(opt);
    });

    if (_discoveredDevices.filter(d => d.type !== 'rpi' && !d.blocked).length === 0) {
        const opt = document.createElement('option');
        opt.disabled = true;
        opt.textContent = '— No ESP32 nodes detected —';
        sel.appendChild(opt);
    }

    if (currentVal) {
        for (let i = 0; i < sel.options.length; i++) {
            if (sel.options[i].value === currentVal) { sel.value = currentVal; onStudentsDeviceChange(currentVal); break; }
        }
    }
}

// ─── INITIALIZATION ────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const cachedIp = localStorage.getItem('esp_ip_cache');
    const ipInput = document.getElementById('esp-ip');
    if (ipInput) {
        if (cachedIp) ipInput.value = cachedIp;
        ipInput.addEventListener('input', (e) => {
            localStorage.setItem('esp_ip_cache', e.target.value.trim());
        });
    }

    // Live clock for dashboards
    function tickClock() {
        var n = new Date();
        var c = document.getElementById('live-clock');
        var d = document.getElementById('live-date');
        if(c) c.textContent = n.toLocaleTimeString('en-GB', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
        if(d) d.textContent = n.toLocaleDateString('en-GB', {weekday:'long', year:'numeric', month:'short', day:'numeric'});
    }
    if (document.getElementById('live-clock')) {
        tickClock();
        setInterval(tickClock, 1000);
    }

    if (typeof window.bulkEnrollFromImages === 'function' && !window._bulkEnrollWrapped) {
        const _origBulk = window.bulkEnrollFromImages;
        window.bulkEnrollFromImages = async function(fi) {
            const log = document.getElementById('bulk-upload-log');
            if (log) { log.style.display = ''; log.classList.remove('d-none'); }
            await _origBulk(fi);
        };
        window._bulkEnrollWrapped = true;
    }

    loadCourseDatalist();
    refreshAllCourseDropdowns();

    // Auto-discover hardware nodes immediately and every 10s
    if (document.getElementById('action-target-device') || document.getElementById('students-device-select')) {
        refreshDeviceList();
        refreshDeviceDropdown();
        refreshLectureButtons();
        if (typeof window.loadDeviceSettingsEsp === 'function') {
            setTimeout(() => window.loadDeviceSettingsEsp(), 1500);
        }
        setInterval(() => { refreshDeviceList(); refreshDeviceDropdown(); refreshLectureButtons(); }, 10000);
    }

    // Restore active lecture state
    renderActiveLectures();
    refreshLectureButtons();
    restoreLectureTimer();
    // Only start polling logs if we are on a page that displays them (like index.php)
    if (document.getElementById('logs-accordion-container')) {
        console.log('[Init] Starting logs polling...');
        fetchLogs();
        if (typeof fetchTodayAttendance === 'function') fetchTodayAttendance();
        setInterval(() => {
            fetchLogs();
            if (typeof fetchTodayAttendance === 'function') fetchTodayAttendance();
        }, 5000);
    }

    // Admin secondary tabs (charts / ratings / forum)
    if (document.getElementById('adminSecondaryTabs')) {
        // Load charts on tab show
        document.querySelector('[data-bs-target="#tab-charts"]')?.addEventListener('shown.bs.tab', () => {
            loadCharts();
            // Also initialize language switcher for admin forum if not done yet
            if (document.getElementById('lang-switcher-admin-forum') && typeof renderLangSwitcher === 'function' && !document.getElementById('lang-switcher-admin-forum').hasChildNodes()) {
                renderLangSwitcher('lang-switcher-admin-forum');
            }
        });
        document.querySelector('[data-bs-target="#tab-ratings"]')?.addEventListener('shown.bs.tab', () => {
            loadAdminRatingsBoard();
            loadAdminRecentComments();
        });
        document.querySelector('[data-bs-target="#tab-forum"]')?.addEventListener('shown.bs.tab', () => {
            if (document.getElementById('lang-switcher-admin-forum') && typeof renderLangSwitcher === 'function' && !document.getElementById('lang-switcher-admin-forum').hasChildNodes()) {
                renderLangSwitcher('lang-switcher-admin-forum');
            }
        });
        // Load charts immediately if the charts tab is active by default
        if (document.getElementById('tab-charts')?.classList.contains('show')) {
            loadCharts();
        }
    }

    // Only load device settings if the elements exist
    if (document.getElementById('set_fp_power')) {
        fetchDeviceSettings();
    }

    // Start polling ESP32 logs if on the device page
    if (document.getElementById('serial-monitor')) {
        fetchESPLogs();
        setInterval(fetchESPLogs, 2000);
    }

    // Start polling LCD if on dashboard
    if (document.getElementById('lcd0')) {
        setInterval(pollLcdMirror, 2000);
    }

    // Start RFID auto-fill polling if on students page
    if (document.getElementById('enroll-rfid')) {
        setInterval(pollRfidFill, 1500);
    }

    // Start schedule auto-start polling if on dashboard
    if (document.getElementById('btn-start-course')) {
        setInterval(checkScheduleAutoStart, 30000);
        setInterval(checkLowAttendanceEmail, 60000);
        checkScheduleAutoStart();
        checkLowAttendanceEmail();
    }

    // Email sender config
    const smtpHostSelect = document.getElementById('user-smtp-host');
    if (smtpHostSelect) {
        smtpHostSelect.addEventListener('change', function() {
            const customCol = document.getElementById('custom-smtp-col');
            if (customCol) {
                customCol.classList.toggle('d-none', this.value !== 'custom');
            }
        });
    }
    loadEmailConfig();

    // Load email logs if on lecture page
    if (document.getElementById('email-logs-container')) {
        loadEmailLogs();
    }
});


// ─── SEARCHABLE DROPDOWN HELPERS ───────────────────────────────────
window.filterDropdown = function(inputEl, dropdownId, hiddenId) {
    const q = inputEl.value.toLowerCase().trim();
    const dd = document.getElementById(dropdownId);
    if (!dd) return;
    const items = dd.querySelectorAll('.searchable-dropdown-item');
    if (items.length === 0) { dd.classList.add('d-none'); return; }
    let visible = 0;
    items.forEach(item => {
        const val = (item.getAttribute('data-value') || '').toLowerCase();
        const txt = (item.textContent || '').toLowerCase();
        if (q === '' || val.indexOf(q) !== -1 || txt.indexOf(q) !== -1) {
            item.style.display = '';
            visible++;
        } else {
            item.style.display = 'none';
        }
    });
    dd.classList.toggle('d-none', visible === 0);
    if (hiddenId && q === '') {
        const h = document.getElementById(hiddenId);
        if (h) h.value = '';
    }
};

window.showDropdown = function(dropdownId) {
    const dd = document.getElementById(dropdownId);
    if (!dd) return;
    
    // If dropdown is empty, populate from associated select
    if (dd.children.length === 0) {
        const input = document.querySelector(`[data-list="${dropdownId}"]`);
        const sel = document.getElementById(input?.dataset.target);
        if (sel) {
            const opts = sel.querySelectorAll('option');
            opts.forEach(opt => {
                if (!opt.value) return;
                const item = document.createElement('div');
                item.className = 'searchable-dropdown-item';
                item.textContent = opt.textContent;
                item.setAttribute('data-value', opt.value);
                item.setAttribute('data-name', opt.dataset.name || '');
                if (input && input.dataset.target && input.dataset.target.includes('action-target')) {
                    item.setAttribute('onclick', `selectDropdownItem(this, '${input.id}', 'action-target-device', '${dropdownId}')`);
                } else if (input) {
                    item.setAttribute('onclick', `selectDropdownItem(this, '${input.id}', '${input.id.replace('-search', '-input')}', '${dropdownId}')`);
                }
                dd.appendChild(item);
            });
        }
    }
    dd.classList.remove('d-none');
};

window.selectDropdownItem = function(itemEl, inputId, hiddenId, dropdownId) {
    const val = itemEl.getAttribute('data-value');
    const inputEl = document.getElementById(inputId);
    const hiddenEl = document.getElementById(hiddenId);
    // Use data-value for display so multi-line items show only course code
    if (inputEl) inputEl.value = val;
    if (hiddenEl) hiddenEl.value = val;
    const dd = document.getElementById(dropdownId);
    if (dd) dd.classList.add('d-none');
    if (inputEl) inputEl.dispatchEvent(new Event('change'));
    if (dropdownId === 'device-monitor-dropdown' && typeof window.onDeviceTargetChange === 'function') window.onDeviceTargetChange();
};

window.appendDropdownItem = function(itemEl, inputId, dropdownId) {
    const val = itemEl.getAttribute('data-value');
    const inputEl = document.getElementById(inputId);
    if (inputEl) {
        const current = inputEl.value.trim();
        if (current && !current.endsWith(',')) {
            inputEl.value = current + ', ' + val;
        } else if (current) {
            inputEl.value = current + ' ' + val;
        } else {
            inputEl.value = val;
        }
    }
    const dd = document.getElementById(dropdownId);
    if (dd) dd.classList.add('d-none');
};

document.addEventListener('click', function(e) {
    if (!e.target.closest('.searchable-select-wrapper')) {
        document.querySelectorAll('.searchable-dropdown').forEach(dd => dd.classList.add('d-none'));
    }
});

// ─── UTILITIES ─────────────────────────────────────────────────────
function escapeHtml(unsafe) {
    if (!unsafe) return '-';
    return unsafe.toString()
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}
window.escapeHtml = escapeHtml;

// Debounce function for search inputs
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Safe fetch wrapper with error handling
async function safeFetch(url, options = {}) {
    try {
        const response = await fetch(url, {
            ...options,
            signal: options.signal || AbortSignal.timeout(10000)
        });
        return response;
    } catch (e) {
        console.error('[Fetch Error]', url, e.message);
        throw e;
    }
}

function flashTxIndicator() {
    const txIndicator = document.getElementById('tx-indicator');
    if (txIndicator) {
        txIndicator.classList.add('ota-blue-flash');
        setTimeout(() => txIndicator.classList.remove('ota-blue-flash'), 200);
    }
}

function logToTerminal(text) {
    const terminal = document.getElementById('serial-monitor');
    if (terminal) {
        terminal.textContent += text + "\n";
        terminal.scrollTop = terminal.scrollHeight;
    } else {
        console.log(text);
    }
}

// ─── OTA / ESP32 COMM ──────────────────────────────────────────────
async function fetchESPLogs() {
    const sel = document.getElementById('action-target-device');
    const ip = sel?.value && sel.value !== 'WEB_DASHBOARD' ? sel.value : getEspIp();
    if (!ip) return;
    try {
        const res = await fetch(`http://${ip}/logs`, { signal: AbortSignal.timeout(3000) });
        if (res.ok) {
            const text = await res.text();
            if (text.trim() !== '') {
                logToTerminal(text.trim());
            }
        }
    } catch (e) { /* Silent fail if offline */ }
}

async function pollLcdMirror() {
    const targetIP = getEspIp();
    if (!targetIP) return;
    try {
        const response = await fetch(`http://${targetIP}/cmd?command=GETLCD`, { signal: AbortSignal.timeout(1200) });
        const data = await response.text();
        if (data.includes("|")) {
            const lines = data.split("|");
            lines.forEach((lineText, index) => {
                const el = document.getElementById('lcd' + index);
                if (el) el.textContent = lineText;
            });
        }
    } catch (error) { /* Silently fail */ }
}

async function sendOtaCommand(command) {
    isCommandActive = true; 
    flashTxIndicator();
    
    const sel = document.getElementById('action-target-device');
    const devType = 'esp32';
    const deviceName = sel?.value || 'WEB_DASHBOARD';
    const targetIP = deviceName !== 'WEB_DASHBOARD' && deviceName !== '__loading__' ? deviceName : getEspIp();
    
    logToTerminal("\n> TX: " + command);
    
    // RPi devices use server-side OTA queue
    if (devType === 'rpi') {
        try {
            const fd = new FormData();
            fd.append('action', 'send');
            fd.append('device', deviceName);
            fd.append('command', command);
            fd.append('key', OTA_API_KEY);
            const response = await fetch('/csc2052/api/ota.php', { method: 'POST', body: fd });
            const data = await response.json();
            logToTerminal("[RX] " + (data.message || data.command || 'Queued'));
        } catch (error) {
            logToTerminal("[ERR] OTA queue failed — " + error.message);
        }
        setTimeout(() => { isCommandActive = false; }, 5000);
        return;
    }
    
    // ESP32: proxy through PHP to avoid CORS/network issues
    try {
        const response = await fetch(`/csc2052/api/esp_ota_proxy.php?ip=${encodeURIComponent(targetIP)}&command=${encodeURIComponent(command)}`, { signal: AbortSignal.timeout(8000) });
        const data = await response.text();
        
        if (data.startsWith("WIFILIST:")) {
            populateWifiDropdown(data.replace("WIFILIST:", ""));
            logToTerminal("[RX] Scan Complete. Check Dropdown.");
        } else {
            logToTerminal("[RX] " + data.trim());
        }
    } catch (error) {
        logToTerminal("[ERR] Node unresponsive at " + targetIP + " — " + error.message);
    }
    setTimeout(() => { isCommandActive = false; }, 5000); 
}

async function sendOtaCommandToIp(ip, command) {
    try {
        await fetch(`/csc2052/api/esp_ota_proxy.php?ip=${encodeURIComponent(ip)}&command=${encodeURIComponent(command)}`, { signal: AbortSignal.timeout(8000) });
    } catch (e) {
        console.warn('[OTA] Failed to reach ' + ip + ':', e.message);
    }
}

window.sendCustomOta = function() {
    const cmdInput = document.getElementById('custom-ota-cmd');
    if (cmdInput && cmdInput.value.trim() !== '') {
        sendOtaCommand(cmdInput.value.trim());
        cmdInput.value = '';
    }
};

window.activeLectures = JSON.parse(localStorage.getItem('active_lectures') || '{}');
window.activeWebCourse = localStorage.getItem('active_lecture_course') || '';
window.activeWebDevice = localStorage.getItem('active_lecture_device') || '';

function renderActiveLectures() {
    const container = document.getElementById('active-lectures-container');
    const countBadge = document.getElementById('active-lecture-count');
    if (!container) return;
    const keys = Object.keys(window.activeLectures);
    if (countBadge) countBadge.textContent = keys.length;
    if (keys.length === 0) {
        container.innerHTML = '<div class="text-muted small text-center py-2"><i class="bi bi-inbox me-1"></i>No active lectures</div>';
        return;
    }
    let html = '';
    keys.forEach(ip => {
        const lec = window.activeLectures[ip];
        const duration = lec.startTime ? Math.floor((Date.now() - lec.startTime) / 60000) + 'm ago' : '';
        const isDash = ip === 'WEB_DASHBOARD';
        const sectionHtml = lec.section ? ` <span class="badge bg-info text-dark">${escapeHtml(lec.section)}</span>` : '';
        html += `<div class="d-flex justify-content-between align-items-center border-bottom py-1 small">
            <span class="fw-bold text-truncate" style="max-width:100px;">${isDash ? 'Web Dashboard' : escapeHtml(lec.deviceName || ip)}</span>
            <span class="badge bg-success mx-1">${escapeHtml(lec.course)}</span>
            ${sectionHtml}
            <span class="text-muted small">${duration}</span>
            <button class="btn btn-xs btn-outline-danger py-0 px-1 ms-1" onclick="endLectureByDevice('${ip}')"><i class="bi bi-x"></i></button>
        </div>`;
    });
    container.innerHTML = html;
}

function refreshLectureButtons() {
    const device = document.getElementById('action-target-device')?.value || 'WEB_DASHBOARD';
    const lec = window.activeLectures[device];
    const btnStart = document.getElementById('btn-start-course');
    const btnEnd = document.getElementById('btn-end-course');
    const searchInput = document.getElementById('start-course-search');
    const hiddenInput = document.getElementById('start-course-input');

    if (lec) {
        if (btnStart) {
            btnStart.disabled = true;
            btnStart.className = 'btn btn-success fw-semibold';
            btnStart.innerHTML = `<i class="bi bi-broadcast"></i> Live: ${escapeHtml(lec.course)}`;
        }
        if (btnEnd) btnEnd.disabled = false;
        if (searchInput) { searchInput.disabled = true; searchInput.value = lec.course; }
        if (hiddenInput) { hiddenInput.disabled = true; hiddenInput.value = lec.course; }
    } else {
        if (btnStart) {
            btnStart.disabled = false;
            btnStart.className = 'btn btn-primary fw-semibold';
            btnStart.innerHTML = `<i class="bi bi-broadcast me-1"></i>Start`;
        }
        if (btnEnd) btnEnd.disabled = true;
        if (searchInput) searchInput.disabled = false;
        if (hiddenInput) hiddenInput.disabled = false;
    }
}

window.onDeviceTargetChange = function() {
    refreshLectureButtons();
    if (typeof window.loadDeviceSettingsEsp === 'function') window.loadDeviceSettingsEsp();
};

window.startCourseToDevice = function() {
    const hiddenInput = document.getElementById('start-course-input');
    const searchInput = document.getElementById('start-course-search');
    const code = (hiddenInput?.value || searchInput?.value || '').trim();
    if (!code) { alert('Please select a Course Code to start the lecture.'); return; }

    const device = document.getElementById('action-target-device')?.value || 'WEB_DASHBOARD';
    const isHardware = device !== 'WEB_DASHBOARD' && device !== '__loading__';
    const deviceName = document.getElementById('action-target-device')?.selectedOptions?.[0]?.dataset?.name || 'Web Dashboard';

    // Create lecture session via API
    var fd = new FormData();
    fd.append('action', 'start_course_session');
    fd.append('course_code', code);
    fd.append('device', device);
    const timerEl = document.getElementById('lecture-timer-preset');
    let timerMinutes = 0;
    if (timerEl) {
        const preset = timerEl.value;
        if (preset === 'custom') {
            timerMinutes = parseInt(document.getElementById('lecture-timer-custom')?.value || '0');
        } else {
            timerMinutes = parseInt(preset);
        }
    }
    if (timerMinutes > 0) fd.append('timer_minutes', timerMinutes);
    fetch('/csc2052/api/teacher.php', { method: 'POST', body: fd }).catch(function(e) { console.error('[Lecture] Failed to create session:', e); });

    if (isHardware) {
        sendOtaCommandToIp(device, 'START_COURSE ' + code);
        logToTerminal('[CMD] Pushed course to ' + deviceName + ' (' + device + '): ' + code);
    } else {
        logToTerminal('[CMD] Started Web Dashboard lecture: ' + code);
    }

    const endTime = timerMinutes > 0 ? Date.now() + timerMinutes * 60000 : null;

    const section = document.getElementById('lecture-section-input')?.value?.trim() || '';
    window.activeLectures[device] = { course: code, startTime: Date.now(), endTime: endTime, deviceName: deviceName, section: section };
    localStorage.setItem('active_lectures', JSON.stringify(window.activeLectures));

    window.activeWebCourse = code;
    window.activeWebDevice = device;
    localStorage.setItem('active_lecture_course', code);
    localStorage.setItem('active_lecture_device', device);

    if (endTime) {
        startLectureTimer(endTime, device);
    }

    refreshLectureButtons();
    renderActiveLectures();
    if (typeof fetchLogs === 'function') fetchLogs();
};

window.applyTimerPreset = function(val) {
    const customInput = document.getElementById('lecture-timer-custom');
    if (!customInput) return;
    if (val === 'custom') {
        customInput.style.display = '';
        customInput.focus();
    } else {
        customInput.style.display = 'none';
    }
};

window.startLectureTimer = function(endTime, deviceIp) {
    cancelLectureTimer();
    const display = document.getElementById('lecture-timer-display');
    const countdown = document.getElementById('lecture-timer-countdown');
    if (!display || !countdown) return;
    display.classList.remove('d-none');

    window.lectureTimerInterval = setInterval(() => {
        const remaining = endTime - Date.now();
        if (remaining <= 0) {
            clearInterval(window.lectureTimerInterval);
            window.lectureTimerInterval = null;
            display.classList.add('d-none');
            const target = deviceIp || window.activeWebDevice || 'WEB_DASHBOARD';
            if (window.activeLectures[target]) {
                logToTerminal('[TIMER] Auto-ending lecture: ' + window.activeLectures[target].course);
                window.endLectureByDevice(target);
            }
            return;
        }
        const mins = Math.floor(remaining / 60000);
        const secs = Math.floor((remaining % 60000) / 1000);
        countdown.textContent = mins.toString().padStart(2, '0') + ':' + secs.toString().padStart(2, '0');
    }, 1000);
};

window.cancelLectureTimer = function() {
    if (window.lectureTimerInterval) {
        clearInterval(window.lectureTimerInterval);
        window.lectureTimerInterval = null;
    }
    const display = document.getElementById('lecture-timer-display');
    if (display) display.classList.add('d-none');
};

window.restoreLectureTimer = function() {
    const deviceIp = window.activeWebDevice || 'WEB_DASHBOARD';
    const lec = window.activeLectures[deviceIp];
    if (lec && lec.endTime) {
        const remaining = lec.endTime - Date.now();
        if (remaining > 0) {
            startLectureTimer(lec.endTime, deviceIp);
        } else {
            logToTerminal('[TIMER] Restored expired lecture — ending: ' + (lec.course || 'unknown'));
            window.endLectureByDevice(deviceIp);
        }
    }
};

// ─── SCHEDULE AUTO-START ────────────────────────────────────────────────
window.checkScheduleAutoStart = async function() {
    try {
        const res = await fetch('/csc2052/api/schedule.php?action=auto_start_check');
        const data = await res.json();
        if (data.status === 'success' && data.schedules && data.schedules.length > 0) {
            const started = JSON.parse(localStorage.getItem('auto_started_schedules') || '{}');
            const today = new Date().toDateString();
            
            for (const sched of data.schedules) {
                const key = sched.id + '_' + today;
                if (started[key]) continue;
                
                const targetDevice = sched.device_id || 'WEB_DASHBOARD';
                const currentDevice = document.getElementById('action-target-device')?.value || 'WEB_DASHBOARD';
                
                if (targetDevice === currentDevice || targetDevice === 'WEB_DASHBOARD') {
                    const hiddenInput = document.getElementById('start-course-input');
                    const searchInput = document.getElementById('start-course-search');
                    
                    if (hiddenInput) hiddenInput.value = sched.course_code;
                    if (searchInput) searchInput.value = sched.course_code + ' (Auto-Start)';
                    
                    logToTerminal('[SCHEDULE] Auto-starting: ' + sched.course_code + ' on ' + (sched.device_name || targetDevice));
                    if (typeof showToast === 'function') {
                        showToast('Auto-starting: ' + sched.course_code, 'info');
                    }
                    
                    window.startCourseToDevice();
                    started[key] = true;
                    localStorage.setItem('auto_started_schedules', JSON.stringify(started));
                }
            }
        }
    } catch (e) {
    }
};

// ─── DOWNLOAD ABSENT REPORT ────────────────────────────────────────────
window.downloadAbsentReport = function() {
    const course = document.getElementById('absent-course-input')?.value.trim();
    const date = document.getElementById('absent-date')?.value;
    const tbody = document.getElementById('absent-table-body');
    if (!course || !tbody) return;
    
    const rows = tbody.querySelectorAll('tr');
    if (rows.length === 0) return;
    
    let csv = 'Student No,Name,Course\n';
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length >= 4) {
            const stuNo = cells[1].textContent.trim();
            const name = cells[2].textContent.trim();
            const courseCode = cells[3].textContent.trim();
            csv += `"${stuNo}","${name}","${courseCode}"\n`;
        }
    });
    
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `absent_report_${course}_${date || 'today'}.csv`;
    a.click();
    URL.revokeObjectURL(url);
    if (typeof showToast === 'function') showToast('Absent report downloaded!', 'success');
};

// ─── DOWNLOAD ANALYTICS REPORT ─────────────────────────────────────────
window.downloadAnalyticsReport = function() {
    const course = document.getElementById('analytics-course-input')?.value.trim();
    const tbody = document.getElementById('analytics-table-body');
    if (!course || !tbody) return;
    
    const rows = tbody.querySelectorAll('tr');
    if (rows.length === 0) return;
    
    let csv = 'Student No,Name,Attended,Total Sessions,Percentage\n';
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length >= 5) {
            const stuNo = cells[1].textContent.trim();
            const name = cells[2].textContent.trim();
            const attended = cells[3].textContent.trim();
            const total = document.getElementById('analytics-total-sessions')?.textContent || '0';
            const pctCell = cells[4].querySelector('.badge');
            const percentage = pctCell ? pctCell.textContent.trim() : '0%';
            csv += `"${stuNo}","${name}","${attended}","${total}","${percentage}"\n`;
        }
    });
    
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `analytics_${course}_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    URL.revokeObjectURL(url);
    if (typeof showToast === 'function') showToast('Analytics report downloaded!', 'success');
};

// ─── ANALYTICS FILTER ──────────────────────────────────────────────────
window.filterAnalyticsByAttendance = function() {
    const filter = document.getElementById('analytics-filter')?.value;
    const tbody = document.getElementById('analytics-table-body');
    if (!tbody || !filter) return;
    
    const rows = tbody.querySelectorAll('tr');
    rows.forEach(row => {
        const badge = row.querySelector('.badge');
        if (!badge) return;
        const pct = parseInt(badge.textContent);
        
        let show = true;
        switch (filter) {
            case 'all': show = true; break;
            case 'critical': show = pct < 50; break;
            case 'warning': show = pct >= 50 && pct < 80; break;
            case 'good': show = pct >= 80; break;
            case 'below_80': show = pct < 80; break;
            case 'below_50': show = pct < 50; break;
        }
        row.style.display = show ? '' : 'none';
    });
    
    const filterBtn = document.getElementById('btn-filter-analytics');
    if (filterBtn) {
        filterBtn.classList.add('btn-primary');
        filterBtn.classList.remove('btn-outline-primary');
        setTimeout(() => {
            filterBtn.classList.remove('btn-primary');
            filterBtn.classList.add('btn-outline-primary');
        }, 500);
    }
};

window.endLectureByDevice = function(deviceIp) {
    const lec = window.activeLectures[deviceIp];
    if (!lec) return;
    const isHardware = deviceIp !== 'WEB_DASHBOARD';
    if (isHardware) {
        sendOtaCommandToIp(deviceIp, 'START_COURSE Unassigned Session');
    }
    
    const endedCourse = lec.course;
    const endedSection = lec.section || '';

    // End lecture session via API
    var fd = new FormData();
    fd.append('action', 'end_course_session');
    fd.append('course_code', endedCourse);
    fetch('/csc2052/api/teacher.php', { method: 'POST', body: fd }).catch(function(e) { console.error('[Lecture] Failed to end session:', e); });
    
    delete window.activeLectures[deviceIp];
    localStorage.setItem('active_lectures', JSON.stringify(window.activeLectures));

    if (window.activeWebDevice === deviceIp) {
        window.activeWebCourse = '';
        window.activeWebDevice = '';
        localStorage.removeItem('active_lecture_course');
        localStorage.removeItem('active_lecture_device');
    }
    logToTerminal('[CMD] Ended lecture on ' + (lec.deviceName || deviceIp) + ': ' + endedCourse + (endedSection ? ' [' + endedSection + ']' : ''));
    refreshLectureButtons();
    renderActiveLectures();
    if (typeof fetchLogs === 'function') fetchLogs();
    
    if (typeof triggerEndOfCourseEmail === 'function') {
        triggerEndOfCourseEmail(endedCourse);
    }

    // Save completed section to history
    saveSectionHistory(endedCourse, endedSection, lec.startTime, Date.now(), lec.deviceName || deviceIp);

    // Offer to start next section
    if (endedSection) {
        const nextBtn = document.getElementById('btn-next-section');
        const sectionInput = document.getElementById('lecture-section-input');
        if (nextBtn && sectionInput) {
            sectionInput.value = endedSection;
            nextBtn.style.display = '';
            setTimeout(() => { if (nextBtn) nextBtn.style.display = 'none'; }, 10000);
        }
    }
};

/* ─── SECTION HISTORY ─────────────────────────────────────── */
function getSectionHistory() {
    try { return JSON.parse(localStorage.getItem('section_history') || '[]'); } catch (e) { return []; }
}

function saveSectionHistory(course, section, startTime, endTime, device) {
    const history = getSectionHistory();
    history.unshift({
        id: Date.now() + '_' + Math.random().toString(36).substr(2, 4),
        course: course,
        section: section || '',
        startTime: startTime,
        endTime: endTime,
        device: device || 'WEB_DASHBOARD',
        date: new Date().toISOString().split('T')[0]
    });
    // Keep last 200 entries
    if (history.length > 200) history.length = 200;
    localStorage.setItem('section_history', JSON.stringify(history));
    renderSectionHistory();
}

function renderSectionHistory() {
    const container = document.getElementById('section-history-container');
    if (!container) return;
    const history = getSectionHistory();
    if (history.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-5 small"><em>No completed sections yet. Sections are saved when you end a lecture.</em></div>';
        return;
    }
    let html = '<table class="table table-hover table-sm mb-0"><thead class="table-light"><tr><th style="width:30px;"><input type="checkbox" id="select-all-sections" onchange="toggleAllSections(this)"></th><th>Course</th><th>Section</th><th>Start</th><th>End</th><th>Duration</th><th>Device</th><th></th></tr></thead><tbody>';
    history.forEach((s, i) => {
        const start = s.startTime ? new Date(s.startTime).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'}) : '-';
        const end = s.endTime ? new Date(s.endTime).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'}) : '-';
        const dur = s.startTime && s.endTime ? Math.round((s.endTime - s.startTime) / 60000) + 'm' : '-';
        html += `<tr>
            <td><input type="checkbox" class="section-checkbox" value="${s.id}"></td>
            <td class="fw-bold small">${escapeHtml(s.course)}</td>
            <td>${s.section ? '<span class="badge bg-info text-dark">' + escapeHtml(s.section) + '</span>' : '<span class="text-muted">-</span>'}</td>
            <td class="small text-muted">${start}</td>
            <td class="small text-muted">${end}</td>
            <td class="small">${dur}</td>
            <td class="small text-muted">${escapeHtml(s.device)}</td>
            <td><button class="btn btn-xs btn-outline-primary py-0 px-1" onclick="downloadSectionCsv('${s.id}')" title="Download CSV"><i class="bi bi-download"></i></button></td>
        </tr>`;
    });
    html += '</tbody></table>';
    if (history.length > 0) {
        html += '<div class="p-2 border-top d-flex justify-content-between align-items-center bg-light">';
        html += '<span class="small text-muted"><span id="section-selected-count">0</span> selected</span>';
        html += '<button class="btn btn-sm btn-outline-primary" onclick="downloadSelectedSections()"><i class="bi bi-download me-1"></i>Download Selected</button>';
        html += '</div>';
    }
    container.innerHTML = html;
}

window.toggleAllSections = function(cb) {
    document.querySelectorAll('.section-checkbox').forEach(c => c.checked = cb.checked);
    updateSectionCount();
};

function updateSectionCount() {
    const count = document.querySelectorAll('.section-checkbox:checked').length;
    const el = document.getElementById('section-selected-count');
    if (el) el.textContent = count;
}

// Listen for checkbox changes
document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('section-checkbox')) updateSectionCount();
    });
});

window.downloadSectionCsv = function(id) {
    const history = getSectionHistory();
    const s = history.find(h => h.id === id);
    if (!s) return;
    const params = new URLSearchParams();
    const startDate = s.startTime ? new Date(s.startTime).toISOString().replace('T', ' ').substring(0, 19) : '';
    const endDate = s.endTime ? new Date(s.endTime).toISOString().replace('T', ' ').substring(0, 19) : '';
    if (startDate) params.set('start_time', startDate);
    if (endDate) params.set('end_time', endDate);
    if (s.course) params.set('course_code', s.course);
    params.set('columns', 'student_no,student_name,course_code,timestamp,modality,device');
    window.location.href = '/csc2052/export_csv.php?' + params.toString();
};

window.downloadSelectedSections = function() {
    const checked = document.querySelectorAll('.section-checkbox:checked');
    if (checked.length === 0) { alert('Select at least one section to download.'); return; }
    const history = getSectionHistory();
    const selected = [];
    checked.forEach(cb => {
        const s = history.find(h => h.id === cb.value);
        if (s) selected.push(s);
    });
    if (selected.length === 0) return;
    // Merge time ranges
    let minStart = Infinity, maxEnd = 0;
    const courses = new Set();
    selected.forEach(s => {
        if (s.startTime && s.startTime < minStart) minStart = s.startTime;
        if (s.endTime && s.endTime > maxEnd) maxEnd = s.endTime;
        if (s.course) courses.add(s.course);
    });
    const params = new URLSearchParams();
    if (minStart !== Infinity) params.set('start_time', new Date(minStart).toISOString().replace('T', ' ').substring(0, 19));
    if (maxEnd !== 0) params.set('end_time', new Date(maxEnd).toISOString().replace('T', ' ').substring(0, 19));
    if (courses.size === 1) params.set('course_code', [...courses][0]);
    params.set('columns', 'student_no,student_name,course_code,timestamp,modality,device');
    window.location.href = '/csc2052/export_csv.php?' + params.toString();
};

window.clearSectionHistory = function() {
    if (!confirm('Clear all section history?')) return;
    localStorage.removeItem('section_history');
    renderSectionHistory();
};

// ─── AUTO EMAIL ON COURSE END ────────────────────────────────────────────
window.triggerEndOfCourseEmail = async function(courseCode) {
    try {
        const res = await fetch(`/csc2052/api/schedule.php?action=list`);
        const data = await res.json();
        if (!data.schedules) return;
        
        const today = new Date().getDay();
        const todayNum = today === 0 ? 6 : today - 1;
        const now = new Date();
        const timeStr = now.toTimeString().substring(0, 5);
        
        for (const s of data.schedules) {
            if (s.course_code === courseCode && s.day_of_week == todayNum && s.email_on_end == 1) {
                const recipient = document.getElementById('absent-email-recipient')?.value?.trim();
                if (!recipient) continue;
                
                const todayDate = now.toISOString().split('T')[0];
                const emailData = new URLSearchParams();
                emailData.append('action', 'send_absent_report_email');
                emailData.append('course_code', courseCode);
                emailData.append('date', todayDate);
                emailData.append('recipient', recipient);
                
                const emailRes = await fetch('/csc2052/api/student.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: emailData.toString()
                });
                const emailResult = await emailRes.json();
                if (emailResult.status === 'success') {
                    logToTerminal('[AUTO-EMAIL] Absent report sent for ' + courseCode + ' to ' + recipient);
                    if (typeof showToast === 'function') {
                        showToast('Absent report auto-emailed for ' + courseCode, 'info');
                    }
                }
                break;
            }
        }
    } catch (e) {
    }
};

// ─── AUTO EMAIL FOR LOW ATTENDANCE (< threshold) ─────────────────────────
window.checkLowAttendanceEmail = async function() {
    try {
        const res = await fetch(`/csc2052/api/schedule.php?action=list`);
        const data = await res.json();
        if (!data.schedules) return;
        
        const sent = JSON.parse(localStorage.getItem('low_attendance_sent') || '{}');
        const today = new Date().toDateString();
        
        for (const s of data.schedules) {
            const key = s.course_code + '_' + today;
            if (sent[key]) continue;
            if (s.auto_start != 1 || s.email_threshold < 1) continue;
            
            const statsRes = await fetch(`/csc2052/api/analytics.php?action=course_stats&course_code=${encodeURIComponent(s.course_code)}`);
            const stats = await statsRes.json();
            if (!stats.students) continue;
            
            const threshold = parseInt(s.email_threshold) || 80;
            const lowStudents = stats.students.filter(st => parseInt(st.percentage) < threshold);
            const nearThreshold = stats.students.filter(st => {
                const pct = parseInt(st.percentage);
                return pct >= threshold && pct < (threshold + 10);
            });
            
            if (lowStudents.length > 0 || nearThreshold.length > 0) {
                const recipient = document.getElementById('absent-email-recipient')?.value?.trim();
                if (!recipient) continue;
                
                let alertMsg = '';
                if (lowStudents.length > 0) {
                    alertMsg += `${lowStudents.length} student(s) below ${threshold}% in ${s.course_code}. `;
                }
                if (nearThreshold.length > 0) {
                    alertMsg += `${nearThreshold.length} student(s) close to threshold.`;
                }
                
                logToTerminal('[ATTENDANCE ALERT] ' + alertMsg);
                if (typeof showToast === 'function') {
                    showToast(alertMsg, 'warning');
                }
                
                sent[key] = true;
                localStorage.setItem('low_attendance_sent', JSON.stringify(sent));
            }
        }
    } catch (e) {
    }
};

window.startCourseWeb = function() {
    const sel = document.getElementById('start-course-input');
    const code = sel?.value?.trim();
    if (!code) { alert('Please select a Course Code to start the lecture.'); return; }

    window.activeLectures['WEB_DASHBOARD'] = { course: code, startTime: Date.now(), deviceName: 'Web Dashboard' };
    localStorage.setItem('active_lectures', JSON.stringify(window.activeLectures));

    window.activeWebCourse = code;
    window.activeWebDevice = 'WEB_DASHBOARD';
    localStorage.setItem('active_lecture_course', code);
    localStorage.setItem('active_lecture_device', 'WEB_DASHBOARD');

    refreshLectureButtons();
    renderActiveLectures();
    if (typeof fetchLogs === 'function') fetchLogs();
};

window.endCourseToDevice = function() {
    const device = document.getElementById('action-target-device')?.value || 'WEB_DASHBOARD';
    if (window.activeLectures[device]) {
        window.endLectureByDevice(device);
    }
};

window.syncCoursesToScanner = function() {
    const list = document.getElementById('sync-courses-input')?.value.trim();
    if (!list) { alert('Please enter course codes to sync.'); return; }
    
    const sel = document.getElementById('action-target-device');
    const devType = 'esp32';
    const isHardware = sel?.value !== 'WEB_DASHBOARD' && sel?.value !== '__loading__';
    
    if (isHardware) {
        if (devType === 'rpi') {
            sendOtaCommand('SETCOURSES ' + list);
        } else {
            sendOtaCommand('SETCOURSES ' + list);
        }
        logToTerminal("[CMD] Pushed courses to hardware: " + list);
    } else {
        logToTerminal("[CMD] Web Dashboard doesn't support course push to SD.");
        alert('Select a hardware node as target to sync courses.');
    }
};

// ─── HARDWARE SETTINGS (WIFI & DEVICE) ─────────────────────────────
window.scanNetworks = async function() {
    logToTerminal("> Starting Wi-Fi Air Scan...");
    const select = document.getElementById('new-ssid');
    if (select) select.innerHTML = '<option value="">Scanning Wi-Fi... Please wait.</option>';
    try {
        const sel = document.getElementById('action-target-device');
        const devType = 'esp32';
        const ip = sel?.value && sel.value !== 'WEB_DASHBOARD' ? sel.value : getEspIp();
        const res = await fetch(`/csc2052/api/esp_ota_proxy.php?ip=${encodeURIComponent(ip)}&command=${encodeURIComponent('SCANWIFI')}`, { signal: AbortSignal.timeout(15000) });
        if (!res.ok) throw new Error("HTTP " + res.status);
        const networks = await res.json();
        if (select) {
            select.innerHTML = '<option value="">Select a Wi-Fi Network</option>';
            networks.forEach(ssid => {
                const opt = document.createElement('option');
                opt.value = escapeHtml(ssid); opt.textContent = escapeHtml(ssid);
                select.appendChild(opt);
            });
        }
        logToTerminal(`[SCANWIFI] Found ${networks.length} networks.`);
    } catch (e) {
        logToTerminal("[SCANWIFI] Network Error: " + e.message);
        if (select) select.innerHTML = '<option value="">Scan failed. Node offline?</option>';
    }
};

function populateWifiDropdown(csvList) {
    const select = document.getElementById('new-ssid');
    if (!select) return;
    select.innerHTML = '<option value="">Select a network...</option>';
    if (csvList === "NO_NETWORKS_FOUND") return;
    csvList.split(',').forEach(net => {
        if (net.trim()) {
            const opt = document.createElement('option');
            opt.value = escapeHtml(net.trim()); opt.innerHTML = escapeHtml(net.trim());
            select.appendChild(opt);
        }
    });
}

window.updateWifi = function() {
    const ssidDrop = document.getElementById('new-ssid');
    const ssidMan  = document.getElementById('manual-ssid');
    const pass     = document.getElementById('new-pass');
    
    let ssid = ssidMan && ssidMan.value.trim() ? ssidMan.value.trim() : (ssidDrop ? ssidDrop.value : '');
    let p = pass ? pass.value.trim() : '';
    let identity = document.getElementById('new-identity')?.value.trim() || '';
    
    if (!ssid) { alert("Please provide an SSID"); return; }
    if (!confirm(`This will update Wi-Fi credentials and reboot the node. Continue?`)) return;
    
    let cmd = 'SETWIFI ' + ssid;
    if (identity) cmd += '|' + identity;
    cmd += '|' + p;
    
    sendOtaCommand(cmd);
    
    if (ssidMan) ssidMan.value = '';
    if (document.getElementById('new-identity')) document.getElementById('new-identity').value = '';
    if (pass) pass.value = '';
    if (ssidDrop) ssidDrop.selectedIndex = 0;
};

window.scanBluetooth = async function() {
    logToTerminal("> Starting Bluetooth LE Scan (Takes 5 seconds)...");
    const select = document.getElementById('bt-devices-list');
    if (select) select.innerHTML = '<option value="">Scanning BLE... Please wait.</option>';
    try {
        const sel = document.getElementById('action-target-device');
        const ip = sel?.value && sel.value !== 'WEB_DASHBOARD' ? sel.value : getEspIp();
        const res = await fetch(`/csc2052/api/esp_ota_proxy.php?ip=${encodeURIComponent(ip)}&command=${encodeURIComponent('SCANBT')}`, { signal: AbortSignal.timeout(15000) });
        if (!res.ok) throw new Error("HTTP " + res.status);
        const devices = await res.json();
        if (select) {
            select.innerHTML = '<option value="">Select a BLE Device</option>';
            devices.forEach(d => {
                const opt = document.createElement('option');
                opt.value = escapeHtml(d.mac); opt.textContent = `${escapeHtml(d.name)} (${escapeHtml(d.mac)})`;
                select.appendChild(opt);
            });
        }
        logToTerminal(`[SCANBT] Found ${devices.length} BLE devices.`);
    } catch (e) {
        logToTerminal("[SCANBT] Network Error: " + e.message);
        if (select) select.innerHTML = '<option value="">Scan failed. Node offline?</option>';
    }
};

async function fetchDeviceSettings() {
    try {
        const res = await fetch('/csc2052/api/settings.php?device_id=DEFAULT');
        const data = await res.json();
        if (data && !data.error) {
            document.getElementById('set_fp_power').checked = (data.fp_power == 1);
            document.getElementById('set_display_power').checked = (data.display_power == 1);
            document.getElementById('set_backlight_power').checked = (data.backlight_power == 1);
            document.getElementById('set_bluetooth_on').checked = (data.bluetooth_on == 1);
            if (document.getElementById('set_enable_fingerprint')) document.getElementById('set_enable_fingerprint').checked = (data.enable_fingerprint == 1);
            if (document.getElementById('set_enable_rfid')) document.getElementById('set_enable_rfid').checked = (data.enable_rfid == 1);
            if (document.getElementById('set_enable_face')) document.getElementById('set_enable_face').checked = (data.enable_face == 1);
            if (document.getElementById('set_require_multi_factor')) document.getElementById('set_require_multi_factor').checked = (data.require_multi_factor == 1);
            document.getElementById('set_enroll_fingers').value = data.enroll_fingers || 3;
            document.getElementById('enrollCountVal').innerText = data.enroll_fingers || 3;
        }
    } catch (e) { console.error(e); }
}

window.saveDeviceSettings = async function() {
    const fp_power       = document.getElementById('set_fp_power').checked ? 1 : 0;
    const display_power  = document.getElementById('set_display_power').checked ? 1 : 0;
    const backlight      = document.getElementById('set_backlight_power').checked ? 1 : 0;
    const bt_on          = document.getElementById('set_bluetooth_on').checked ? 1 : 0;
    const en_fp          = document.getElementById('set_enable_fingerprint')?.checked ? 1 : 0;
    const en_rfid        = document.getElementById('set_enable_rfid')?.checked ? 1 : 0;
    const en_face        = document.getElementById('set_enable_face')?.checked ? 1 : 0;
    const mfa            = document.getElementById('set_require_multi_factor')?.checked ? 1 : 0;
    const enroll_cnt     = document.getElementById('set_enroll_fingers')?.value || 3;

    const fd = new FormData();
    fd.append('device_id', 'DEFAULT');
    fd.append('fp_power', fp_power);
    fd.append('display_power', display_power);
    fd.append('backlight_power', backlight);
    fd.append('bluetooth_on', bt_on);
    fd.append('enable_fingerprint', en_fp);
    fd.append('enable_rfid', en_rfid);
    fd.append('enable_face', en_face);
    fd.append('require_multi_factor', mfa);
    fd.append('enroll_fingers', enroll_cnt);

    try {
        logToTerminal('\n> TX: Saving Device Settings to DB...');
        const res = await fetch('/csc2052/api/settings.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'success' || data.status === 'warning') {
            logToTerminal('[DB] ' + (data.status === 'success' ? 'Settings saved.' : 'Warning: ' + data.message));

            // Also push config OTA to the selected hardware node
            const sel = document.getElementById('action-target-device');
            const devIp = sel?.value;
            const devType = 'esp32';
            if (devIp && devIp !== 'WEB_DASHBOARD' && devIp !== '__loading__') {
                const otaCmd = `SETCONFIG fp_power=${fp_power},display_power=${display_power},backlight=${backlight},` +
                               `bt=${bt_on},en_fp=${en_fp},en_rfid=${en_rfid},en_face=${en_face},` +
                               `mfa=${mfa},enroll_cnt=${enroll_cnt}`;
                logToTerminal('> OTA: Pushing config to hardware...');
                if (devType === 'rpi') {
                    if (typeof sendOtaCommandRpi === 'function') sendOtaCommandRpi(otaCmd);
                } else {
                    await sendOtaCommand(otaCmd);
                }
            } else {
                logToTerminal('[INFO] No hardware target selected — config saved to DB only.');
            }
            alert('Settings saved successfully!');
        } else {
            logToTerminal('[DB ERR] ' + data.message);
            alert('Error: ' + data.message);
        }
    } catch (e) { logToTerminal('[DB ERR] Network error: ' + e.message); }
};

// ─── FINGERPRINT TEMPLATE TRANSFER ─────────────────────────────────
window.downloadFingerprintTemplate = async function() {
    const slot = prompt('Enter fingerprint slot number to download (1-127):');
    const slotNum = Number(slot);
    if (!slot || isNaN(slotNum) || slotNum < 1 || slotNum > 127) return;
    logToTerminal(`\n> TX: Requesting template for slot #${slot}...`);
    try {
        const sel = document.getElementById('action-target-device');
        const ip = sel?.value && sel.value !== 'WEB_DASHBOARD' ? sel.value : getEspIp();
        const res = await fetch(`/csc2052/api/esp_ota_proxy.php?ip=${encodeURIComponent(ip)}&command=${encodeURIComponent('GETTEMPLATE ' + slot)}`, { signal: AbortSignal.timeout(10000) });
        const text = await res.text();
        if (text.startsWith('TEMPLATE:')) {
            const b64 = text.replace('TEMPLATE:', '').trim();
            const blob = new Blob([atob(b64)], { type: 'application/octet-stream' });
            const a = document.createElement('a'); a.href = URL.createObjectURL(blob);
            a.download = `fingerprint_slot${slot}.bin`; a.click();
            logToTerminal(`[OK] Template slot #${slot} downloaded.`);
        } else {
            logToTerminal('[ERR] ' + text);
            alert('Failed to download template: ' + text);
        }
    } catch (e) { logToTerminal('[ERR] ' + e.message); alert('Network error: ' + e.message); }
};

window.uploadFingerprintTemplate = async function(fileInput) {
    if (!fileInput?.files?.length) return;
    const slot = prompt('Enter target slot number to upload into (1-127):');
    const slotNum = Number(slot);
    if (!slot || isNaN(slotNum) || slotNum < 1 || slotNum > 127) { fileInput.value = ''; return; }
    const file = fileInput.files[0];
    const reader = new FileReader();
    reader.onload = async function(e) {
        const bytes = new Uint8Array(e.target.result);
        const b64 = btoa(String.fromCharCode(...bytes));
        logToTerminal(`\n> TX: Uploading template to slot #${slot}...`);
        try {
            const sel = document.getElementById('action-target-device');
            const ip = sel?.value && sel.value !== 'WEB_DASHBOARD' ? sel.value : getEspIp();
            const res = await fetch(`/csc2052/api/esp_ota_proxy.php?ip=${encodeURIComponent(ip)}&command=${encodeURIComponent('SETTEMPLATE ' + slot + ' ' + b64)}`, { signal: AbortSignal.timeout(10000) });
            const text = await res.text();
            logToTerminal('[RX] ' + text);
            alert(text.includes('ACK') ? `Template uploaded to slot #${slot} successfully!` : 'Upload result: ' + text);
        } catch (err) { logToTerminal('[ERR] ' + err.message); alert('Network error: ' + err.message); }
    };
    reader.readAsArrayBuffer(file);
    fileInput.value = '';
};

// ─── ATTENDANCE LOGIC ──────────────────────────────────────────────
window.markManualAttendance = async function() {
    const studentNo = document.getElementById('manual-student-no').value.trim();
    const courseCode = document.getElementById('manual-course-code')?.value.trim() || '';
    const timestamp = document.getElementById('manual-timestamp')?.value;
    
    const stuNoInput = document.getElementById('manual-student-no');
    stuNoInput.classList.remove('is-invalid');
    if (!studentNo) { 
        showToast('Please enter a Student No.', 'error');
        stuNoInput.classList.add('is-invalid');
        stuNoInput.focus();
        return; 
    }
    
    const fd = new FormData();
    fd.append('action', 'manual_attendance');
    fd.append('student_no', studentNo);
    if (courseCode) fd.append('course_code', courseCode);
    if (timestamp) {
        fd.append('timestamp', timestamp.replace('T', ' ') + ':00');
    }
    
    try {
        const res = await fetch('/csc2052/api/student.php', { method: 'POST', body: fd });
        const result = await res.json();
        if (result.status === 'success') {
            document.getElementById('manual-student-no').value = '';
            if (typeof fetchLogs === 'function') fetchLogs();
            if (typeof fetchTodayAttendance === 'function') fetchTodayAttendance();
            alert('Attendance marked successfully for ' + studentNo);
        } else {
            alert('Error: ' + result.message);
        }
    } catch (e) { alert('Network Error: ' + e.message); }
};

let searchTimer = null;
window.searchStudentName = debounce(function(val) {
    const display = document.getElementById('manual-student-name-display');
    if (!display) return;
    const courseInput = document.getElementById('manual-course-code')?.value.trim() || '';
    const qrVal = val.trim();
    if (!qrVal) { display.innerHTML = ''; return; }
    
    display.innerHTML = '<span class="text-muted"><i class="bi bi-hourglass-split"></i> Searching...</span>';
    
    (async () => {
        try {
            const formData = new FormData();
            formData.append('action', 'search_student');
            formData.append('query', qrVal);
            if (courseInput) formData.append('course_code', courseInput);
            const res = await safeFetch('api/student.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data && data.student_name) {
                let text = `<i class="bi bi-person-fill text-primary"></i> <strong class="text-dark">${escapeHtml(data.student_name)}</strong>`;
                if (data.stats) {
                    let color = data.stats.percentage > 75 ? 'success' : (data.stats.percentage >= 50 ? 'warning text-dark' : 'danger');
                    text += `<div class="mt-1 small border-top pt-1 text-muted"><i class="bi bi-bar-chart-fill"></i> Past Attendance: <strong>${data.stats.attended} / ${data.stats.total}</strong> (<span class="text-${color} fw-bold">${data.stats.percentage}%</span>)</div>`;
                }
                display.innerHTML = text;
            } else {
                display.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle"></i> Not Found</span>`;
            }
        } catch (e) { display.innerHTML = '<span class="text-danger">Search error</span>'; }
    })();
}, 300);

// ─── LOGS AND ANALYTICS ────────────────────────────────────────────
async function loadCourseDatalist() {
    try {
        const res = await fetch('/csc2052/api/logs.php?action=get_courses');
        const courses = await res.json();
        if (courses && courses.length > 0) {
            let html = '<datalist id="course-datalist">';
            courses.forEach(c => html += `<option value="${escapeHtml(c)}">`);
            html += '</datalist>';
            document.body.insertAdjacentHTML('beforeend', html);
            
            const inputs = ['export-course', 'manual-course-code', 'filter-course', 'start-course-input', 'analytics-course-input'];
            inputs.forEach(id => {
                const el = document.getElementById(id);
                if (el) { el.setAttribute('list', 'course-datalist'); el.setAttribute('autocomplete', 'off'); }
            });
        }
    } catch (e) {}
}

async function refreshAllCourseDropdowns() {
    try {
        const res = await fetch('/csc2052/api/student.php?action=get_all_courses');
        if (!res.ok) { console.warn('[Courses] API returned', res.status); return; }
        const ct = res.headers.get('content-type') || '';
        if (!ct.includes('application/json')) { console.warn('[Courses] Non-JSON response'); return; }
        const data = await res.json();
        if (data.status !== 'success' || !data.courses) return;

        // ── Regular <select> dropdowns ──
        const courseSelects = [
            'absent-course-select',
            'analytics-course-select',
            'export-course',
            'enroll-stu-course-select',
            'template-attendance-course',
            'assign-course-select',
        ];

        courseSelects.forEach(id => {
            const sel = document.getElementById(id);
            if (!sel) return;
            const currentVal = sel.value;

            let defaultText = 'Select Course...', defaultVal = '';
            if (sel.options.length > 0) {
                defaultText = sel.options[0].textContent;
                defaultVal = sel.options[0].value;
            }

            sel.innerHTML = '';
            const def = document.createElement('option');
            def.value = defaultVal;
            def.textContent = defaultText;
            sel.appendChild(def);

            data.courses.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.course_code;
                let label = c.course_code + (c.course_name ? ' - ' + c.course_name : '');
                if (c.teachers) label += ' [' + c.teachers + ']';
                if (c.avg_rating) label += ' (' + c.avg_rating + '\u2605)';
                opt.textContent = label;
                sel.appendChild(opt);
            });

            if (currentVal) sel.value = currentVal;
        });

        // ── Searchable course dropdowns ──
        const courseDropdowns = [
            { id: 'start-course-dropdown', inputId: 'start-course-search', hiddenId: 'start-course-input', mode: 'select' },
            { id: 'sync-course-dropdown', inputId: 'sync-courses-input', hiddenId: null, mode: 'append' },
            { id: 'manual-course-dropdown', inputId: 'manual-course-search', hiddenId: 'manual-course-code', mode: 'select' },
            { id: 'absent-course-dropdown', inputId: 'absent-course-search', hiddenId: 'absent-course-input', mode: 'select' },
            { id: 'analytics-course-dropdown', inputId: 'analytics-course-search', hiddenId: 'analytics-course-input', mode: 'select' },
            { id: 'export-course-dropdown', inputId: 'export-course-search', hiddenId: 'export-course-input', mode: 'select' },
            { id: 'filter-course-dropdown', inputId: 'filter-course-search', hiddenId: 'filter-course-input', mode: 'select' },
            { id: 'chart-course-dropdown', inputId: 'chart-course-search', hiddenId: 'chart-course-input', mode: 'select' },
        ];
        courseDropdowns.forEach(cfg => {
            const dd = document.getElementById(cfg.id);
            if (!dd) return;
            dd.innerHTML = '';
            if (data.courses.length === 0) {
                dd.innerHTML = '<div class="searchable-dropdown-item text-muted" style="pointer-events:none;">No courses found</div>';
                return;
            }
            data.courses.forEach(c => {
                const item = document.createElement('div');
                item.className = 'searchable-dropdown-item';
                item.setAttribute('data-value', c.course_code);
                let sub = '';
                if (c.teachers) sub += '<span class="text-muted" style="font-size:0.75rem;">' + escapeHtml(c.teachers) + '</span>';
                if (c.avg_rating) sub += ' <span class="badge bg-warning text-dark" style="font-size:0.65rem;"><i class="bi bi-star-fill me-1"></i>' + c.avg_rating + '</span>';
                item.innerHTML = '<div><strong>' + escapeHtml(c.course_code) + '</strong>' + (c.course_name ? ' <span class="text-muted">- ' + escapeHtml(c.course_name) + '</span>' : '') + '</div><div style="line-height:1.2;">' + sub + '</div>';
                if (cfg.mode === 'select') {
                    item.setAttribute('onclick', "selectDropdownItem(this, '" + cfg.inputId + "', '" + cfg.hiddenId + "', '" + cfg.id + "')");
                } else {
                    item.setAttribute('onclick', "appendDropdownItem(this, '" + cfg.inputId + "', '" + cfg.id + "')");
                }
                dd.appendChild(item);
            });
        });
    } catch (e) { console.error('[Courses] Error:', e); }
}

async function refreshDeviceDropdown() {
    try {
        const res = await fetch('/csc2052/api/devices.php?action=list');
        const data = await res.json();
        if (!data || !data.devices) return;

        const dd = document.getElementById('device-monitor-dropdown');
        if (!dd) return;

        const dashLec = window.activeLectures['WEB_DASHBOARD'];
        const dashBadge = dashLec ? `<span class="badge bg-success ms-1">LIVE: ${escapeHtml(dashLec.course)}</span>` : '';
        dd.innerHTML = '<div class="searchable-dropdown-item" data-value="WEB_DASHBOARD" onclick="selectDropdownItem(this, \'device-monitor-search\', \'action-target-device\', \'device-monitor-dropdown\')"><strong><i class="bi bi-laptop me-2"></i>Web Dashboard</strong> <span class="text-muted">(This Computer)</span> ' + dashBadge + '</div>';

        data.devices.forEach(dev => {
            const item = document.createElement('div');
            item.className = 'searchable-dropdown-item';
            item.setAttribute('data-value', dev.ip);
            const onlineBadge = dev.online ? '<span class="badge bg-success ms-1">ON</span>' : '<span class="badge bg-danger ms-1">OFF</span>';
            const lec = window.activeLectures[dev.ip];
            const lecBadge = lec ? `<span class="badge bg-success ms-1">LIVE: ${escapeHtml(lec.course)}</span>` : '';
            item.innerHTML = '<strong>' + escapeHtml(dev.name || dev.ip) + '</strong>' + onlineBadge + ' <span class="text-muted ms-1">' + escapeHtml(dev.ip) + '</span> ' + lecBadge;
            item.setAttribute('onclick', "selectDropdownItem(this, 'device-monitor-search', 'action-target-device', 'device-monitor-dropdown')");
            dd.appendChild(item);
        });
    } catch (e) {}
}

async function fetchTodayAttendance() {
    const device = document.getElementById('stat-device-select')?.value || '';
    try {
        const response = await fetch(`api/logs.php?action=today_count&device=${encodeURIComponent(device)}`);
        if (response.ok) {
            const data = await response.json();
            const countEl = document.getElementById('today-attendance-count');
            if (countEl) countEl.innerText = data.count;
        }
    } catch (e) {
        console.error("Failed to fetch today's attendance count", e);
    }
}

async function fetchLogs() {
    const container = document.getElementById('logs-accordion-container');
    if (!container) return;
    const studentNo = document.getElementById('filter-student')?.value.trim() || '';
    const dateFrom = document.getElementById('filter-date-from')?.value || '';
    const dateTo = document.getElementById('filter-date-to')?.value || '';
    const course = document.getElementById('filter-course-input')?.value || document.getElementById('filter-course-search')?.value || '';
    const device = document.getElementById('filter-device')?.value || '';
    
    try {
        const url = `/csc2052/api/logs.php?action=fetch_logs&student_no=${encodeURIComponent(studentNo)}&course_code=${encodeURIComponent(course)}&date_from=${encodeURIComponent(dateFrom)}&date_to=${encodeURIComponent(dateTo)}&device=${encodeURIComponent(device)}`;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 10000);
        const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', signal: controller.signal });
        clearTimeout(timeout);
        
        if (!response.ok) {
            console.error('[Logs] HTTP ' + response.status);
            if (response.status === 401 || response.status === 403 || response.status === 302) {
                container.innerHTML = '<div class="text-center text-danger py-4"><em>Session expired. Please <a href="/csc2052/login.php">login again</a>.</em></div>';
            } else if (response.status === 500) {
                const errText = await response.text().catch(() => '');
                console.error('[Logs] Server error:', errText);
                container.innerHTML = '<div class="text-center text-danger py-4"><em>Server error. Check browser console (F12) for details.</em></div>';
            } else {
                container.innerHTML = '<div class="text-center text-danger py-4"><em>Failed to load logs (HTTP ' + response.status + ').</em></div>';
            }
            return;
        }
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const body = await response.text().catch(() => '');
            console.error('[Logs] Non-JSON response (', response.status, '):', body.substring(0, 300));
            if (body.toLowerCase().includes('login') || body.toLowerCase().includes('session') || response.redirected) {
                container.innerHTML = '<div class="text-center text-danger py-4"><em>Session expired. Please <a href="/csc2052/login.php">login again</a>.</em></div>';
            } else {
                container.innerHTML = '<div class="text-center text-danger py-4"><em>Invalid server response. Press F12 and check Console for errors.</em></div>';
            }
            return;
        }
        const logs = await response.json();
        
        // Track max ID for new log detection
        let maxId = 0;
        let newLogs = [];
        logs.forEach(log => {
            const logId = parseInt(log.id);
            if (logId > maxId) maxId = logId;
            if (typeof window.lastReportedLogId !== 'undefined' && window.lastReportedLogId !== null && logId > window.lastReportedLogId) {
                newLogs.push(log);
            }
        });
        
        // Voice announcement for new logs (only one at a time)
        if (newLogs.length > 0) {
            newLogs.sort((a, b) => a.id - b.id);
            // Only speak the most recent one
            const last = newLogs[newLogs.length - 1];
            const mod = last.modality ? last.modality.toLowerCase() : '';
            if (mod.includes('fingerprint') || mod.includes('rfid')) {
                const stName = (last.student_name && last.student_name !== 'Unknown') ? last.student_name : last.student_no;
                speakVoice(stName + ' present');
            }
        }
        if (typeof window.lastReportedLogId === 'undefined' || window.lastReportedLogId === null || maxId > window.lastReportedLogId) {
            window.lastReportedLogId = maxId;
        }

        const getModalityBadge = (m, isOffline) => {
            if (!m) return `<span class="badge bg-light text-dark border rounded-pill px-2 py-1 shadow-sm" style="font-size: 0.85rem;">Unknown</span>`;
            m = m.toLowerCase();
            let badge = '';
            if (m.includes('fingerprint') && m.includes('face')) badge = `<span class="badge bg-success text-white shadow px-2 py-1 rounded-pill" style="font-size: 0.85rem;"><i class="bi bi-shield-lock-fill me-1"></i>2FA Verified</span>`;
            else if (m.includes('fingerprint')) badge = `<span class="badge bg-success text-white bg-gradient shadow-sm px-2 py-1 rounded-pill" style="font-size: 0.85rem;"><i class="bi bi-fingerprint me-1"></i>Fingerprint</span>`;
            else if (m.includes('rfid')) badge = `<span class="badge bg-info text-dark bg-gradient shadow-sm px-2 py-1 rounded-pill" style="font-size: 0.85rem;"><i class="bi bi-credit-card me-1"></i>RFID</span>`;
            else if (m.includes('face') || m.includes('web_face') || m.includes('rpi_face')) badge = `<span class="badge bg-warning text-dark bg-gradient shadow-sm px-2 py-1 rounded-pill" style="font-size: 0.85rem;"><i class="bi bi-person-bounding-box me-1"></i>Face</span>`;
            else if (m === 'manual') badge = `<span class="badge bg-primary text-white bg-gradient shadow-sm px-2 py-1 rounded-pill" style="font-size: 0.85rem;"><i class="bi bi-pencil-square me-1"></i>Manual</span>`;
            else badge = `<span class="badge bg-secondary text-white bg-gradient shadow-sm px-2 py-1 rounded-pill" style="font-size: 0.85rem;"><i class="bi bi-cpu me-1"></i>${escapeHtml(m)}</span>`;
            
            if (isOffline) badge += ` <span class="badge bg-secondary px-1 py-0" style="font-size: 0.7rem;" title="Synced offline">OFF</span>`;
            return badge;
        };

        // Helper: create safe HTML ID from any string
        const safeId = (s) => s.replace(/[^a-zA-Z0-9]/g, '_');

        // Group: course -> date -> timeBlock (2-hour windows)
        const groups = {};
        if (window.activeWebCourse) {
            groups[window.activeWebCourse] = {};
        }

        logs.forEach(log => {
            const c = log.course_code || 'Unassigned Session';
            if (!groups[c]) groups[c] = {};
            const ts = log.timestamp || '';
            const d = ts ? ts.split(' ')[0] : 'Unknown';
            if (!groups[c][d]) groups[c][d] = {};

            const timePart = ts ? ts.split(' ')[1] || '' : '';
            const hour = timePart ? parseInt(timePart.split(':')[0]) : -1;
            let timeBlock;
            if (hour >= 0) {
                const blockStart = Math.floor(hour / 2) * 2;
                const blockEnd = blockStart + 2;
                const fmtStart = blockStart.toString().padStart(2, '0') + ':00';
                const fmtEnd = blockEnd.toString().padStart(2, '0') + ':00';
                timeBlock = fmtStart + ' - ' + fmtEnd;
            } else {
                timeBlock = 'Unknown';
            }

            if (!groups[c][d][timeBlock]) groups[c][d][timeBlock] = [];
            groups[c][d][timeBlock].push(log);
        });

        let html = '<div class="accordion accordion-flush" id="logsAccordion">';
        const courseKeys = Object.keys(groups);
        if (courseKeys.length === 0) {
            container.innerHTML = '<div class="text-center text-muted py-4"><em>No logs found based on current filters.</em></div>';
            return;
        }
        
        let courseIdx = 0;
        courseKeys.forEach(courseName => {
            const cId = 'course' + courseIdx;
            const hId = 'courseHead' + courseIdx;
            const dateKeys = Object.keys(groups[courseName]).sort().reverse();
            const totalEntries = dateKeys.reduce((sum, d) => {
                const tbKeys = Object.keys(groups[courseName][d]);
                return sum + tbKeys.reduce((s2, tb) => s2 + groups[courseName][d][tb].length, 0);
            }, 0);
            
            const displayCourseName = courseName.length > 35 ? courseName.substring(0, 35) + '...' : courseName;

            html += `
            <div class="accordion-item mb-2 border-0 overflow-hidden" style="border-radius:10px;box-shadow:0 2px 12px rgba(161,30,23,0.08);">
                <h2 class="accordion-header d-flex align-items-center" id="${hId}">
                    <button class="accordion-button flex-grow-1 ${courseIdx !== 0 ? 'collapsed' : ''} py-2" type="button" data-bs-toggle="collapse" data-bs-target="#${cId}"
                            style="background:#fdf8f8;color:#1e293b;font-weight:700;font-size:0.9rem;">
                        <i class="bi bi-collection-fill me-2" style="color:#a11e17;"></i>
                        <span title="${escapeHtml(courseName)}">${escapeHtml(displayCourseName)}</span>
                        <span class="badge rounded-pill ms-3" style="background:#a11e17;color:#fff;font-size:0.7rem;">${totalEntries} entries</span>
                    </button>
                </h2>
                <div id="${cId}" class="accordion-collapse collapse ${courseIdx === 0 ? 'show' : ''}" data-bs-parent="#logsAccordion">
                    <div class="accordion-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-xs mb-0">
                                 <thead class="table-light">
                                     <tr>
                                         <th>#</th>
                                         <th>Student</th>
                                         <th>Name</th>
                                         <th>Time</th>
                                         <th>Modality</th>
                                         <th>Device</th>
                                     </tr>
                                 </thead>
                                 <tbody>`;
            dateKeys.forEach(dateStr => {
                const tbKeys = Object.keys(groups[courseName][dateStr]);
                tbKeys.forEach(tb => {
                    const logs = groups[courseName][dateStr][tb];
                    logs.forEach((log, idx) => {
                        const stName = log.student_name && log.student_name !== 'Unknown' ? escapeHtml(log.student_name) : '<span class="text-muted fst-italic">-</span>';
                        const timeStr = log.timestamp ? log.timestamp.split(' ')[1] || '' : '';
                        const isOffline = log.is_offline_sync == 1 || log.is_offline == 1;
                        html += `
                            <tr style="border-bottom:1px solid #f0e8e8;">
                                <td class="ps-3 py-2 small text-muted">${idx + 1}</td>
                                <td class="py-2 fw-bold" style="color:#a11e17;font-size:0.82rem;" data-att-tooltip="${escapeHtml(log.student_no)}">${escapeHtml(log.student_no)}</td>
                                <td class="py-2 fw-semibold text-dark" style="font-size:0.82rem;">${stName}</td>
                                <td class="py-2"><span class="badge rounded-pill" style="background:#1e293b;color:#fff;font-size:0.72rem;padding:4px 10px;">${escapeHtml(timeStr)}</span></td>
                                <td class="py-2"><span>${getModalityBadge(log.modality, isOffline)}</span></td>
                                <td class="pe-3 py-2 text-muted" style="font-size:0.78rem;">${escapeHtml(log.device_name)}</td>
                            </tr>`;
                    });
                });
            });

            html += `</tbody></table></div>
                    </div>
                </div>
            </div>`;
            courseIdx++;
        });
        html += `</div>`;
        // Preserve scroll position so refresh doesn't jump to top
        const logsBody = container.closest('.logs-body');
        const savedScroll = logsBody ? logsBody.scrollTop : 0;
        container.innerHTML = html;
        if (logsBody) requestAnimationFrame(() => { logsBody.scrollTop = savedScroll; });
        // Re-init tooltips so student hovers work on fresh content
        if (typeof attachAttendanceTooltips === 'function') {
            setTimeout(attachAttendanceTooltips, 100);
        }
    } catch (e) {
        console.error("[Logs] Failed to fetch logs:", e);
        container.innerHTML = '<div class="text-center text-danger py-4"><em>Error loading logs.</em></div>';
    }
};

/* ─── ADMIN RATINGS BOARD ─────────────────────────────────── */
async function loadAdminRatingsBoard() {
    const container = document.getElementById('admin-ratings-board');
    if (!container) return;
    try {
        const r = await fetch('/csc2052/api/ratings.php?action=get_board');
        if (!r.ok) throw new Error('HTTP ' + r.status);
        const data = await r.json();
        if (!data || !data.length) {
            container.innerHTML = '<div class="text-center text-muted py-4 small"><em>No ratings yet.</em></div>';
            return;
        }
        let html = '';
        data.forEach(course => {
            const avg = parseFloat(course.avg_rating) || 0;
            const full = Math.floor(avg);
            const half = avg - full >= 0.5 ? 1 : 0;
            const empty = 5 - full - half;
            let stars = '';
            for (let i = 0; i < full; i++) stars += '<i class="bi bi-star-fill text-warning" style="font-size:0.8rem;"></i>';
            if (half) stars += '<i class="bi bi-star-half text-warning" style="font-size:0.8rem;"></i>';
            for (let i = 0; i < empty; i++) stars += '<i class="bi bi-star text-warning" style="font-size:0.8rem;"></i>';
            html += `<div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                <div><strong class="small">${escapeHtml(course.course_code || '')}</strong>
                <span class="text-muted small"> - ${escapeHtml(course.course_name || '')}</span></div>
                <div class="text-end"><span class="me-2 small fw-bold">${avg.toFixed(1)}</span>${stars}
                <span class="ms-2 text-muted small">(${course.count || 0})</span></div>
            </div>`;
        });
        container.innerHTML = html;
    } catch (e) {
        container.innerHTML = '<div class="text-center text-danger py-3 small">Failed to load ratings.</div>';
    }
}

/* ─── ADMIN RECENT COMMENTS ───────────────────────────────── */
async function loadAdminRecentComments() {
    const container = document.getElementById('admin-recent-comments');
    if (!container) return;
    try {
        const r = await fetch('/csc2052/api/ratings.php?action=get_comments&target_type=course&limit=20');
        if (!r.ok) throw new Error('HTTP ' + r.status);
        const data = await r.json();
        const comments = data.comments || (Array.isArray(data) ? data : []);
        if (!comments.length) {
            container.innerHTML = '<div class="text-center text-muted py-4 small"><em>No comments yet.</em></div>';
            return;
        }
        let html = '';
        comments.slice(0, 15).forEach(c => {
            const date = c.created_at ? c.created_at.split(' ')[0] : '';
            html += `<div class="py-2 px-1 border-bottom">
                <div class="d-flex justify-content-between align-items-start">
                    <strong class="small text-primary">${escapeHtml(c.target_id || '')}</strong>
                    <span class="text-muted" style="font-size:0.7rem;">${date}</span>
                </div>
                <p class="mb-0 small text-dark mt-1">${escapeHtml(c.body || c.comment_text || '')}</p>
                <small class="text-muted" style="font-size:0.7rem;">by ${escapeHtml(c.author_name || c.commenter_name || 'Unknown')}</small>
            </div>`;
        });
        container.innerHTML = html;
    } catch (e) {
        container.innerHTML = '<div class="text-center text-danger py-3 small">Failed to load comments.</div>';
    }
}

/* ─── ADMIN FORUM ─────────────────────────────────────────── */
window.switchAdminForumCourse = async function() {
    const sel = document.getElementById('admin-forum-course');
    const course = sel?.value || '';
    const container = document.getElementById('admin-forum-comments');
    if (!container) return;
    if (!course) { container.innerHTML = '<div class="text-center text-muted py-4 small">Select a course to view discussion.</div>'; return; }
    container.innerHTML = '<div class="text-center text-muted py-3 small"><i class="bi bi-arrow-clockwise spin me-1"></i>Loading...</div>';
    try {
        const r = await fetch(`/csc2052/api/ratings.php?action=get_comments&target_type=course&target_id=${encodeURIComponent(course)}&limit=50`);
        const data = await r.json();
        const comments = data.comments || (Array.isArray(data) ? data : []);
        if (!comments.length) {
            container.innerHTML = '<div class="text-center text-muted py-4 small">No discussion yet for this course.</div>';
            return;
        }
        let html = '';
        comments.forEach(c => {
            const date = c.created_at ? c.created_at.split(' ')[0] : '';
            html += `<div class="py-2 px-3 border-bottom">
                <div class="d-flex justify-content-between align-items-start">
                    <strong class="small">${escapeHtml(c.author_name || c.commenter_name || 'Unknown')}</strong>
                    <span class="text-muted" style="font-size:0.7rem;">${date}</span>
                </div>
                <p class="mb-0 small text-dark mt-1">${escapeHtml(c.body || c.comment_text || '')}</p>
                <div class="mt-1">${c.like_count ? '<span class="badge bg-light text-dark fw-normal me-2" style="font-size:0.7rem;"><i class="bi bi-hand-thumbs-up me-1"></i>' + c.like_count + '</span>' : ''}${c.is_pinned ? '<span class="badge bg-warning text-dark fw-normal" style="font-size:0.7rem;"><i class="bi bi-pin me-1"></i>Pinned</span>' : ''}</div>
            </div>`;
        });
        container.innerHTML = html;
    } catch (e) {
        container.innerHTML = '<div class="text-center text-danger py-3 small">Failed to load discussion.</div>';
    }
};

window.submitAdminForumComment = async function() {
    const course = document.getElementById('admin-forum-course')?.value;
    const input = document.getElementById('admin-comment-input');
    const comment = input?.value?.trim();
    if (!course || !comment) return;
    try {
        const r = await fetch('/csc2052/api/ratings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'submit_comment', target_type: 'course', target_id: course, body: comment })
        });
        const data = await r.json();
        if (data.status === 'success' || data.status === 'ok' || data.success) {
            input.value = '';
            switchAdminForumCourse();
        } else {
            alert('Failed to submit comment: ' + (data.message || 'Unknown error'));
        }
    } catch (e) {
        alert('Failed to submit comment.');
    }
};

window.getAbsentStudents = async function() {
    const course = document.getElementById('absent-course-input')?.value.trim() || document.getElementById('absent-course-select')?.value.trim();
    const date = document.getElementById('absent-date')?.value;
    if (!course) return;
    
    const results = document.getElementById('absent-results');
    const tbody = document.getElementById('absent-table-body');
    const absentCount = document.getElementById('absent-count');
    const presentCount = document.getElementById('present-count');
    const courseLabel = document.getElementById('absent-course-label');
    if (!results || !tbody) return;
    
    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading...</td></tr>';
    results.classList.remove('d-none');
    
    try {
        const res = await fetch(`/csc2052/api/student.php?action=get_absent_students&course_code=${encodeURIComponent(course)}&date=${encodeURIComponent(date)}`);
        const data = await res.json();
        
        if (data.status === 'success') {
            absentCount.textContent = data.absent_count;
            presentCount.textContent = data.present_count;
            courseLabel.textContent = data.course_code + ' | ' + data.date;
            
            const recipientInput = document.getElementById('absent-email-recipient');
            if (recipientInput && !recipientInput.value) {
                const defaultRec = document.getElementById('default-recipient')?.value;
                if (defaultRec) recipientInput.value = defaultRec;
            }
            
            const emailAllBtn = document.getElementById('btn-email-absent-all');
            const customEmailBtn = document.getElementById('btn-custom-absent-email');
            const downloadBtn = document.getElementById('btn-download-absent');
            if (emailAllBtn) emailAllBtn.disabled = false;
            if (customEmailBtn) customEmailBtn.disabled = false;
            if (downloadBtn) downloadBtn.disabled = false;
            
            if (data.absent_students.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-success fw-bold"><i class="bi bi-check-circle me-1"></i>All enrolled students were present!</td></tr>';
            } else {
                let html = '';
                data.absent_students.forEach(s => {
                    html += `
                        <tr data-student-no="${escapeHtml(s.student_no)}" data-student-name="${escapeHtml(s.student_name || 'Unknown')}" data-course="${escapeHtml(s.course_code)}">
                            <td><input class="form-check-input absent-row-check" type="checkbox" value="${escapeHtml(s.student_no)}" onchange="updateSelectedCount('absent')"></td>
                            <td class="ps-3 fw-bold text-danger">${escapeHtml(s.student_no)}</td>
                            <td class="text-dark">${escapeHtml(s.student_name || 'Unknown')}</td>
                            <td><span class="badge bg-secondary">${escapeHtml(s.course_code)}</span></td>
                            <td>
                                <button class="btn btn-xs btn-outline-info py-0 px-2" onclick="sendSingleAbsentEmail('${escapeHtml(s.student_no)}', '${escapeHtml(s.student_name || 'Unknown')}', '${escapeHtml(s.course_code)}')" title="Email this student">
                                    <i class="bi bi-envelope"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            }
            updateSelectedCount('absent');
        } else {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-danger">' + escapeHtml(data.message) + '</td></tr>';
        }
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-danger">Error loading absent report.</td></tr>';
    }
};

// ─── SELECT ALL / UPDATE COUNTS ──────────────────────────────────────────────
window.toggleSelectAll = function(section, checked) {
    const checks = document.querySelectorAll('.' + section + '-row-check');
    checks.forEach(cb => cb.checked = checked);
    updateSelectedCount(section);
};

window.updateSelectedCount = function(section) {
    const checks = document.querySelectorAll('.' + section + '-row-check:checked');
    const countEl = document.getElementById(section + '-selected-count');
    if (countEl) countEl.textContent = checks.length;
    const selectedBtn = document.getElementById('btn-email-' + section + '-selected');
    if (selectedBtn) selectedBtn.disabled = checks.length === 0;
};

window.loadEmailConfig = async function() {
    try {
        const res = await fetch('/csc2052/api/student.php?action=get_email_config');
        const data = await res.json();
        if (data.status === 'success') {
            const emailInput = document.getElementById('user-sender-email');
            if (emailInput && data.email) emailInput.value = data.email;
            
            const smtpSelect = document.getElementById('user-smtp-host');
            if (smtpSelect) {
                const knownHosts = ['smtp.gmail.com', 'smtp-mail.outlook.com', 'smtp.yahoo.com'];
                if (knownHosts.includes(data.smtp_host)) {
                    smtpSelect.value = data.smtp_host;
                } else {
                    smtpSelect.value = 'custom';
                    const customCol = document.getElementById('custom-smtp-col');
                    if (customCol) customCol.classList.remove('d-none');
                    const customInput = document.getElementById('custom-smtp-host');
                    if (customInput) customInput.value = data.smtp_host;
                }
            }
            if (data.has_password) {
                const badge = document.getElementById('email-status-badge');
                if (badge) { badge.className = 'badge fs-6 bg-success'; badge.textContent = 'Email Active'; }
            }
        }
    } catch (e) {}
};

window.togglePasswordVisibility = function() {
    const pwdInput = document.getElementById('user-sender-password');
    const eyeIcon = document.getElementById('pwd-eye-icon');
    if (pwdInput) {
        if (pwdInput.type === 'password') {
            pwdInput.type = 'text';
            eyeIcon.className = 'bi bi-eye-slash';
        } else {
            pwdInput.type = 'password';
            eyeIcon.className = 'bi bi-eye';
        }
    }
};

window.saveEmailConfig = async function(evt) {
    const email = document.getElementById('user-sender-email')?.value.trim();
    const password = document.getElementById('user-sender-password')?.value.trim();
    const smtpSelect = document.getElementById('user-smtp-host');
    let smtpHost = smtpSelect?.value || 'smtp.gmail.com';
    if (smtpHost === 'custom') {
        smtpHost = document.getElementById('custom-smtp-host')?.value.trim() || 'smtp.gmail.com';
    }
    const recipient = document.getElementById('default-recipient')?.value.trim();
    
    if (!email) { showToast('Enter your email address.', 'error'); return; }
    if (!password) { showToast('Enter your app password.', 'error'); return; }
    
    const btn = (evt?.target || document.activeElement)?.closest('button');
    if (!btn) return;
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
    
    try {
        const fd = new URLSearchParams();
        fd.append('action', 'save_email_config');
        fd.append('email', email);
        fd.append('password', password);
        fd.append('smtp_host', smtpHost);
        fd.append('recipient', recipient || '');
        
        const res = await fetch('/csc2052/api/student.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: fd.toString() });
        const data = await res.json();
        
        if (data.status === 'success') {
            showToast('Email configuration saved!', 'success');
            const badge = document.getElementById('email-status-badge');
            if (badge) { badge.className = 'badge fs-6 bg-success'; badge.textContent = 'Email Active'; }
        } else {
            showToast('Failed: ' + data.message, 'error');
        }
    } catch (e) {
        showToast('Error saving config.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = origHtml;
    }
};

window.sendAbsentEmailReport = async function() {
    const course = document.getElementById('absent-course-input')?.value.trim() || document.getElementById('absent-course-select')?.value.trim();
    const date = document.getElementById('absent-date')?.value;
    const recipient = document.getElementById('absent-email-recipient')?.value.trim() || document.getElementById('default-recipient')?.value.trim();
    const emailBtn = document.getElementById('btn-email-absent-all');
    const senderEmail = document.getElementById('user-sender-email')?.value.trim() || '';
    const senderPassword = document.getElementById('user-sender-password')?.value.trim() || '';
    const smtpSelect = document.getElementById('user-smtp-host');
    let smtpHost = smtpSelect?.value || 'smtp.gmail.com';
    if (smtpHost === 'custom') smtpHost = document.getElementById('custom-smtp-host')?.value.trim() || 'smtp.gmail.com';
    
    if (!course) { showToast('Please select a course first.', 'error'); return; }
    if (!recipient) { showToast('Please enter a recipient email.', 'error'); return; }
    if (!emailBtn) return;
    
    const originalHtml = emailBtn.innerHTML;
    emailBtn.disabled = true;
    emailBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending...';
    
    try {
        const formData = new URLSearchParams();
        formData.append('action', 'send_absent_report_email');
        formData.append('course_code', course);
        formData.append('date', date);
        formData.append('recipient', recipient);
        if (senderEmail) formData.append('sender_email', senderEmail);
        if (senderPassword) formData.append('sender_password', senderPassword);
        if (smtpHost) formData.append('smtp_host', smtpHost);
        
        const res = await fetch('/csc2052/api/student.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });
        const data = await res.json();
        
        if (data.status === 'success') {
            showToast('Email report sent to ' + recipient + '!', 'success');
            loadEmailLogs();
        } else {
            showToast('Failed to send email: ' + data.message, 'error');
        }
    } catch (e) {
        showToast('Error sending email report.', 'error');
    } finally {
        emailBtn.disabled = false;
        emailBtn.innerHTML = originalHtml;
    }
};

// ─── SINGLE STUDENT EMAIL (ABSENT) ────────────────────────────────────
window.sendSingleAbsentEmail = async function(stuNo, stuName, course) {
    const recipient = document.getElementById('absent-email-recipient')?.value?.trim() || document.getElementById('default-recipient')?.value?.trim();
    if (!recipient) { showToast('Enter recipient email first.', 'error'); return; }
    
    const senderEmail = document.getElementById('user-sender-email')?.value.trim() || '';
    const senderPassword = document.getElementById('user-sender-password')?.value.trim() || '';
    const smtpSelect = document.getElementById('user-smtp-host');
    let smtpHost = smtpSelect?.value || 'smtp.gmail.com';
    if (smtpHost === 'custom') smtpHost = document.getElementById('custom-smtp-host')?.value.trim() || 'smtp.gmail.com';
    
    const date = document.getElementById('absent-date')?.value || new Date().toISOString().split('T')[0];
    const body = `Dear ${stuName},\n\nThis is to inform you that you were marked absent for course ${course} on ${date}.\n\nPlease contact your instructor if you have any concerns.\n\n— Sentinel AMS`;
    
    const formData = new URLSearchParams();
    formData.append('action', 'send_custom_email');
    formData.append('recipient', recipient);
    formData.append('subject', `Absent Notice - ${course} (${date})`);
    formData.append('body', body);
    formData.append('message_type', 'absent_notice');
    formData.append('course_code', course);
    formData.append('student_no', stuNo);
    formData.append('student_name', stuName);
    if (senderEmail) formData.append('sender', senderEmail);
    if (senderPassword) formData.append('sender_password', senderPassword);
    if (smtpHost) formData.append('smtp_host', smtpHost);
    
    try {
        const res = await fetch('/csc2052/api/student.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast('Email sent for ' + stuName, 'success');
            loadEmailLogs();
        } else {
            showToast('Failed: ' + data.message, 'error');
        }
    } catch (e) { showToast('Error sending email.', 'error'); }
};

// ─── SINGLE STUDENT EMAIL (ANALYTICS) ─────────────────────────────────
window.sendSingleAnalyticsEmail = async function(stuNo, stuName, pct, course) {
    const recipient = document.getElementById('default-recipient')?.value?.trim();
    if (!recipient) { showToast('Set default recipient email first.', 'error'); return; }
    
    const senderEmail = document.getElementById('user-sender-email')?.value.trim() || '';
    const senderPassword = document.getElementById('user-sender-password')?.value.trim() || '';
    const smtpSelect = document.getElementById('user-smtp-host');
    let smtpHost = smtpSelect?.value || 'smtp.gmail.com';
    if (smtpHost === 'custom') smtpHost = document.getElementById('custom-smtp-host')?.value.trim() || 'smtp.gmail.com';
    
    let msg = '';
    if (pct < 50) msg = `Your attendance in ${course} is critically low at ${pct}%. Immediate action required.`;
    else if (pct < 80) msg = `Your attendance in ${course} is ${pct}%, below the 80% requirement. Please attend upcoming sessions.`;
    else msg = `Your attendance in ${course} is ${pct}%. Keep up the good work!`;
    
    const formData = new URLSearchParams();
    formData.append('action', 'send_custom_email');
    formData.append('recipient', recipient);
    formData.append('subject', `Attendance Alert - ${course} (${pct}%)`);
    formData.append('body', `Dear ${stuName},\n\n${msg}\n\n— Sentinel AMS`);
    formData.append('message_type', 'attendance_alert');
    formData.append('course_code', course);
    formData.append('student_no', stuNo);
    formData.append('student_name', stuName);
    if (senderEmail) formData.append('sender', senderEmail);
    if (senderPassword) formData.append('sender_password', senderPassword);
    if (smtpHost) formData.append('smtp_host', smtpHost);
    
    try {
        const res = await fetch('/csc2052/api/student.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });
        const data = await res.json();
        if (data.status === 'success') {
            showToast('Email sent for ' + stuName, 'success');
            loadEmailLogs();
        } else {
            showToast('Failed: ' + data.message, 'error');
        }
    } catch (e) { showToast('Error sending email.', 'error'); }
};

// ─── SELECTED EMAILS (ABSENT) ─────────────────────────────────────────
window.sendSelectedAbsentEmails = async function() {
    const checks = document.querySelectorAll('.absent-row-check:checked');
    if (checks.length === 0) return;
    
    const recipient = document.getElementById('absent-email-recipient')?.value?.trim();
    if (!recipient) { showToast('Enter recipient email first.', 'error'); return; }
    
    const senderEmail = document.getElementById('user-sender-email')?.value.trim() || '';
    const senderPassword = document.getElementById('user-sender-password')?.value.trim() || '';
    const smtpSelect = document.getElementById('user-smtp-host');
    let smtpHost = smtpSelect?.value || 'smtp.gmail.com';
    if (smtpHost === 'custom') smtpHost = document.getElementById('custom-smtp-host')?.value.trim() || 'smtp.gmail.com';
    
    const date = document.getElementById('absent-date')?.value || new Date().toISOString().split('T')[0];
    let sent = 0;
    const btn = document.getElementById('btn-email-absent-selected');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending...'; }
    
    for (const cb of checks) {
        const row = cb.closest('tr');
        const stuNo = row.dataset.studentNo;
        const stuName = row.dataset.studentName;
        const course = row.dataset.course;
        
        const body = `Dear ${stuName},\n\nYou were marked absent for ${course} on ${date}.\n\n— Sentinel AMS`;
        const formData = new URLSearchParams();
        formData.append('action', 'send_custom_email');
        formData.append('recipient', recipient);
        formData.append('subject', `Absent Notice - ${course}`);
        formData.append('body', body);
        formData.append('message_type', 'absent_notice');
        formData.append('course_code', course);
        formData.append('student_no', stuNo);
        formData.append('student_name', stuName);
        if (senderEmail) formData.append('sender', senderEmail);
        if (senderPassword) formData.append('sender_password', senderPassword);
        if (smtpHost) formData.append('smtp_host', smtpHost);
        
        try {
            const res = await fetch('/csc2052/api/student.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: formData.toString() });
            const data = await res.json();
            if (data.status === 'success') sent++;
        } catch (e) {}
    }
    
    showToast(`${sent} email(s) sent.`, 'success');
    loadEmailLogs();
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-envelope-check me-1"></i>Selected (<span id="absent-selected-count">0</span>)'; }
    updateSelectedCount('absent');
};

// ─── SELECTED EMAILS (ANALYTICS) ──────────────────────────────────────
window.sendSelectedAnalyticsEmails = async function() {
    const checks = document.querySelectorAll('.analytics-row-check:checked');
    if (checks.length === 0) return;
    
    const recipient = document.getElementById('default-recipient')?.value?.trim();
    if (!recipient) { showToast('Set default recipient first.', 'error'); return; }
    
    const senderEmail = document.getElementById('user-sender-email')?.value.trim() || '';
    const senderPassword = document.getElementById('user-sender-password')?.value.trim() || '';
    const smtpSelect = document.getElementById('user-smtp-host');
    let smtpHost = smtpSelect?.value || 'smtp.gmail.com';
    if (smtpHost === 'custom') smtpHost = document.getElementById('custom-smtp-host')?.value.trim() || 'smtp.gmail.com';
    
    let sent = 0;
    const btn = document.getElementById('btn-email-analytics-selected');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending...'; }
    
    for (const cb of checks) {
        const row = cb.closest('tr');
        const stuNo = row.dataset.studentNo;
        const stuName = row.dataset.studentName;
        const pct = row.dataset.percentage;
        const course = row.dataset.course;
        
        const formData = new URLSearchParams();
        formData.append('action', 'send_custom_email');
        formData.append('recipient', recipient);
        formData.append('subject', `Attendance Update - ${course} (${pct}%)`);
        formData.append('body', `Dear ${stuName},\n\nYour attendance in ${course} is currently ${pct}%.\n\n— Sentinel AMS`);
        formData.append('message_type', 'attendance_alert');
        formData.append('course_code', course);
        formData.append('student_no', stuNo);
        formData.append('student_name', stuName);
        if (senderEmail) formData.append('sender', senderEmail);
        if (senderPassword) formData.append('sender_password', senderPassword);
        if (smtpHost) formData.append('smtp_host', smtpHost);
        
        try {
            const res = await fetch('/csc2052/api/student.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: formData.toString() });
            const data = await res.json();
            if (data.status === 'success') sent++;
        } catch (e) {}
    }
    
    showToast(`${sent} email(s) sent.`, 'success');
    loadEmailLogs();
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-envelope-check me-1"></i>Selected (<span id="analytics-selected-count">0</span>)'; }
    updateSelectedCount('analytics');
};

// ─── BULK ANALYTICS EMAIL (ALL VISIBLE) ───────────────────────────────
window.sendAnalyticsBulkEmail = async function() {
    const rows = document.querySelectorAll('#analytics-table-body tr');
    const recipient = document.getElementById('default-recipient')?.value?.trim();
    if (!recipient) { showToast('Set default recipient first.', 'error'); return; }
    
    const senderEmail = document.getElementById('user-sender-email')?.value.trim() || '';
    const senderPassword = document.getElementById('user-sender-password')?.value.trim() || '';
    const smtpSelect = document.getElementById('user-smtp-host');
    let smtpHost = smtpSelect?.value || 'smtp.gmail.com';
    if (smtpHost === 'custom') smtpHost = document.getElementById('custom-smtp-host')?.value.trim() || 'smtp.gmail.com';
    
    let sent = 0;
    const btn = document.getElementById('btn-email-analytics-all');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending...'; }
    
    for (const row of rows) {
        if (row.style.display === 'none') continue;
        const stuNo = row.dataset.studentNo;
        const stuName = row.dataset.studentName;
        const pct = row.dataset.percentage;
        const course = row.dataset.course;
        
        const formData = new URLSearchParams();
        formData.append('action', 'send_custom_email');
        formData.append('recipient', recipient);
        formData.append('subject', `Attendance Report - ${course}`);
        formData.append('body', `Dear ${stuName},\n\nYour attendance in ${course} is ${pct}%.\n\n— Sentinel AMS`);
        formData.append('message_type', 'attendance_alert');
        formData.append('course_code', course);
        formData.append('student_no', stuNo);
        formData.append('student_name', stuName);
        if (senderEmail) formData.append('sender', senderEmail);
        if (senderPassword) formData.append('sender_password', senderPassword);
        if (smtpHost) formData.append('smtp_host', smtpHost);
        
        try {
            const res = await fetch('/csc2052/api/student.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: formData.toString() });
            const data = await res.json();
            if (data.status === 'success') sent++;
        } catch (e) {}
    }
    
    showToast(`${sent} email(s) sent.`, 'success');
    loadEmailLogs();
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-envelope me-1"></i>Send All'; }
};

// ─── CUSTOM EMAIL MODAL ───────────────────────────────────────────────
window.openCustomEmailModal = function(source) {
    const modal = new bootstrap.Modal(document.getElementById('customEmailModal'));
    document.getElementById('custom-email-source').value = source;
    
    // Refresh the preset dropdown in modal
    renderModalPresetSelect();
    
    const senderInput = document.getElementById('custom-email-sender');
    const userEmail = document.getElementById('user-sender-email')?.value || '';
    if (senderInput) {
        senderInput.innerHTML = '';
        if (userEmail) {
            const opt = document.createElement('option');
            opt.value = userEmail;
            opt.textContent = userEmail;
            opt.selected = true;
            senderInput.appendChild(opt);
        }
    }
    
    // 'blank' = new blank template, don't pre-fill from row selections
    if (source === 'blank') {
        const recDiv = document.getElementById('custom-email-recipients');
        if (recDiv) recDiv.innerHTML = '<span class="text-muted">Composing a new template — no recipients pre-selected.</span>';
        document.getElementById('custom-email-subject').value = '';
        document.getElementById('custom-email-body').value = '';
        document.getElementById('custom-email-recipient').value = '';
        modal.show();
        return;
    }
    
    const recipients = [];
    const checks = document.querySelectorAll('.' + source + '-row-check:checked');
    checks.forEach(cb => {
        const row = cb.closest('tr');
        recipients.push({
            no: row.dataset.studentNo,
            name: row.dataset.studentName,
            pct: row.dataset.percentage || 'N/A',
            course: row.dataset.course
        });
    });
    
    const recDiv = document.getElementById('custom-email-recipients');
    if (recipients.length > 0) {
        recDiv.innerHTML = recipients.map(r => `<span class="badge bg-primary me-1 mb-1">${r.name} (${r.no})</span>`).join('');
        document.getElementById('custom-email-recipient').value = document.getElementById('default-recipient')?.value || '';
        
        if (source === 'analytics') {
            document.getElementById('custom-email-subject').value = `Attendance Update - ${recipients[0].course}`;
            document.getElementById('custom-email-body').value = `Dear {student_name},\n\nYour attendance in {course} is currently {percentage}%.\n\nPlease ensure you meet the minimum requirement.\n\n— Sentinel AMS`;
        } else {
            const date = document.getElementById('absent-date')?.value || new Date().toISOString().split('T')[0];
            document.getElementById('custom-email-subject').value = `Absent Notice - ${recipients[0].course}`;
            document.getElementById('custom-email-body').value = `Dear {student_name},\n\nYou were marked absent for {course} on ${date}.\n\n— Sentinel AMS`;
        }
    } else {
        recDiv.innerHTML = '<span class="text-muted">No recipients selected. Email will be sent to the recipient address below.</span>';
        document.getElementById('custom-email-subject').value = '';
        document.getElementById('custom-email-body').value = '';
    }
    
    modal.show();
};

window.sendCustomEmails = async function() {
    const source = document.getElementById('custom-email-source').value;
    const sender = document.getElementById('custom-email-sender')?.value || document.getElementById('user-sender-email')?.value.trim() || '';
    const senderPassword = document.getElementById('user-sender-password')?.value.trim() || '';
    const smtpSelect = document.getElementById('user-smtp-host');
    let smtpHost = smtpSelect?.value || 'smtp.gmail.com';
    if (smtpHost === 'custom') smtpHost = document.getElementById('custom-smtp-host')?.value.trim() || 'smtp.gmail.com';
    const recipient = document.getElementById('custom-email-recipient')?.value?.trim();
    const subject = document.getElementById('custom-email-subject')?.value?.trim();
    const body = document.getElementById('custom-email-body')?.value?.trim();
    const btn = document.getElementById('btn-send-custom-email');
    
    if (!recipient || !body) { showToast('Recipient and message are required.', 'error'); return; }
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending...'; }
    
    const checks = document.querySelectorAll('.' + source + '-row-check:checked');
    let sent = 0;
    let total = checks.length > 0 ? checks.length : 1;
    
    if (checks.length > 0) {
        for (const cb of checks) {
            const row = cb.closest('tr');
            let processedBody = body
                .replace(/{student_name}/g, row.dataset.studentName || 'Student')
                .replace(/{student_no}/g, row.dataset.studentNo || '')
                .replace(/{percentage}/g, row.dataset.percentage || 'N/A')
                .replace(/{course}/g, row.dataset.course || '');
            
            const formData = new URLSearchParams();
            formData.append('action', 'send_custom_email');
            formData.append('sender', sender);
            formData.append('recipient', recipient);
            formData.append('subject', subject);
            formData.append('body', processedBody);
            formData.append('message_type', 'custom');
            formData.append('course_code', row.dataset.course || '');
            formData.append('student_no', row.dataset.studentNo || '');
            formData.append('student_name', row.dataset.studentName || '');
            if (senderPassword) formData.append('sender_password', senderPassword);
            if (smtpHost) formData.append('smtp_host', smtpHost);
            
            try {
                const res = await fetch('/csc2052/api/student.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: formData.toString() });
                const data = await res.json();
                if (data.status === 'success') sent++;
            } catch (e) {}
        }
    } else {
        const formData = new URLSearchParams();
        formData.append('action', 'send_custom_email');
        formData.append('sender', sender);
        formData.append('recipient', recipient);
        formData.append('subject', subject);
        formData.append('body', body);
        formData.append('message_type', 'custom');
        if (senderPassword) formData.append('sender_password', senderPassword);
        if (smtpHost) formData.append('smtp_host', smtpHost);
        
        try {
            const res = await fetch('/csc2052/api/student.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: formData.toString() });
            const data = await res.json();
            if (data.status === 'success') sent = 1;
        } catch (e) {}
    }
    
    showToast(`${sent}/${total} email(s) sent.`, 'success');
    loadEmailLogs();
    bootstrap.Modal.getInstance(document.getElementById('customEmailModal')).hide();
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-send me-1"></i>Send Emails'; }
};

// ─── EMAIL LOGS ────────────────────────────────────────────────────────
window.loadEmailLogs = async function() {
    const container = document.getElementById('email-logs-container');
    if (!container) return;
    
    const filter = document.getElementById('email-log-filter')?.value || 'all';
    const url = filter !== 'all' ? `/csc2052/api/student.php?action=get_email_logs&message_type=${filter}` : '/csc2052/api/student.php?action=get_email_logs';
    
    try {
        const res = await fetch(url);
        const data = await res.json();
        
        if (data.status === 'success' && data.logs && data.logs.length > 0) {
            let html = '<table class="table table-sm table-hover mb-0 align-middle small"><thead class="table-light"><tr><th>Date</th><th>Type</th><th>Course</th><th>Student</th><th>To</th><th>Subject</th></tr></thead><tbody>';
            
            data.logs.forEach(log => {
                const typeColors = { absent_report: 'warning', attendance_alert: 'danger', custom: 'info', absent_notice: 'secondary' };
                const typeColor = typeColors[log.message_type] || 'secondary';
                const studentInfo = (log.student_name && log.student_no) ? `${log.student_name}<br><small class="text-muted">${log.student_no}</small>` : (log.student_no || '—');
                
                html += `<tr>
                    <td class="text-muted small">${log.sent_at ? log.sent_at.substring(0, 16).replace('T', ' ') : ''}</td>
                    <td><span class="badge bg-${typeColor}">${log.message_type || 'general'}</span></td>
                    <td class="fw-semibold">${log.course_code || '—'}</td>
                    <td>${studentInfo}</td>
                    <td><small>${log.recipient_email || ''}</small></td>
                    <td class="text-truncate" style="max-width:200px;" title="${log.subject || ''}">${log.subject || '—'}</td>
                </tr>`;
            });
            
            html += '</tbody></table>';
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div class="text-center py-3 text-muted"><em>No email logs found.</em></div>';
        }
    } catch (e) {
        container.innerHTML = '<div class="text-center py-3 text-danger"><em>Failed to load email logs.</em></div>';
    }
};

// ─── ENROLLMENT AND PROFILES ───────────────────────────────────────
window.addStudentName = async function() {
    const stuNoInput = document.getElementById('add-name-stuno');
    const fullInput = document.getElementById('add-name-full');
    const stuNo = stuNoInput.value.trim();
    const full = fullInput.value.trim();
    stuNoInput.classList.remove('is-invalid');
    fullInput.classList.remove('is-invalid');
    if (!stuNo) { showToast('Student No is required.', 'error'); stuNoInput.classList.add('is-invalid'); stuNoInput.focus(); return; }
    if (!full) { showToast('Student Name is required.', 'error'); fullInput.classList.add('is-invalid'); fullInput.focus(); return; }
    
    const fd = new FormData();
    fd.append('action', 'add_name');
    fd.append('student_no', stuNo);
    fd.append('student_name', full);
    try {
        const res = await fetch('/csc2052/api/student.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'success') {
            document.getElementById('add-name-stuno').value = '';
            document.getElementById('add-name-full').value = '';
            alert(data.message);
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (e) { alert("Network Error: " + e.message); }
};

window.uploadStudentCSV = async function() {
    const fileInput = document.getElementById('csv-upload-file');
    if (!fileInput?.files?.length) { alert('Please select a CSV file.'); return; }
    
    const fd = new FormData();
    fd.append('action', 'upload_csv');
    fd.append('csv_file', fileInput.files[0]);
    
    try {
        const res = await fetch('/csc2052/api/student.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'success') {
            alert(data.message);
            fileInput.value = '';
            window.location.reload();
        } else {
            alert("Upload Failed: " + data.message);
        }
    } catch (e) { alert("Network Error: " + e.message); }
};

window.triggerEnroll = async function() {
    const stuNoInput = document.getElementById('enroll-student-no');
    const fIdInput = document.getElementById('enroll-id');
    const studentNo = stuNoInput.value.trim();
    const studentName = document.getElementById('enroll-student-name')?.value.trim() || '';
    const fId = fIdInput.value;
    
    stuNoInput.classList.remove('is-invalid');
    fIdInput.classList.remove('is-invalid');
    
    if (!studentNo) { showToast("Student No is required.", 'error'); stuNoInput.classList.add('is-invalid'); stuNoInput.focus(); return; }
    if (fId < 1 || fId > 127) { showToast("Invalid Slot ID (must be 1-127).", 'error'); fIdInput.classList.add('is-invalid'); fIdInput.focus(); return; }
    
    try {
        const formData = new FormData();
        formData.append('action', 'link_student');
        formData.append('student_no', studentNo);
        formData.append('student_name', studentName);
        formData.append('finger_id', fId);
        formData.append('csrf_token', getCsrfToken());
        const res = await fetch('/csc2052/api/student.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.status === 'success') {
            sendOtaCommand('ENROLL ' + fId);
            const displayName = studentName || studentNo;
            speakVoice(displayName + ' fingerprint enrolled');
            setTimeout(() => window.location.reload(), 1500);
        } else alert("Failed: " + data.message);
    } catch (e) { alert("Network Error: " + e.message); }
};

window.triggerEnrollRfid = async function() {
    const stuNoInput = document.getElementById('enroll-student-no');
    const rfidInput = document.getElementById('enroll-rfid');
    const studentNo = stuNoInput.value.trim();
    const studentName = document.getElementById('enroll-student-name')?.value.trim() || '';
    const rfidUid = rfidInput?.value.trim();
    
    stuNoInput.classList.remove('is-invalid');
    if (rfidInput) rfidInput.classList.remove('is-invalid');
    
    if (!studentNo) { showToast("Student No is required.", 'error'); stuNoInput.classList.add('is-invalid'); stuNoInput.focus(); return; }
    if (!rfidUid) { showToast("RFID UID is required. Click 'Scan RFID' and scan a card on the device.", 'error'); if(rfidInput) { rfidInput.classList.add('is-invalid'); rfidInput.focus(); } return; }
    
    try {
        const formData = new FormData();
        formData.append('action', 'link_rfid');
        formData.append('student_no', studentNo);
        formData.append('student_name', studentName);
        formData.append('rfid_uid', rfidUid);
        const res = await fetch('/csc2052/api/student.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.status === 'success') {
            const displayName = studentName || studentNo;
            speakVoice(displayName + ' RFID linked');
            document.getElementById('enroll-rfid').value = '';
        } else alert("Failed: " + data.message);
    } catch (e) { alert("Network Error: " + e.message); }
};

window.triggerEnrollFace = async function() {
    const stuNoInput = document.getElementById('enroll-student-no');
    const faceInput = document.getElementById('enroll-face');
    const studentNo = stuNoInput.value.trim();
    const studentName = document.getElementById('enroll-student-name')?.value.trim() || '';
    const faceId = faceInput?.value.trim();
    
    stuNoInput.classList.remove('is-invalid');
    if (faceInput) faceInput.classList.remove('is-invalid');
    
    if (!studentNo) { showToast("Student No is required.", 'error'); stuNoInput.classList.add('is-invalid'); stuNoInput.focus(); return; }
    if (!faceId) { showToast("Face ID is required. Use 'Auto Find' or enter manually.", 'error'); if (faceInput) { faceInput.classList.add('is-invalid'); faceInput.focus(); } return; }
    
    try {
        const formData = new FormData();
        formData.append('action', 'link_face');
        formData.append('student_no', studentNo);
        formData.append('student_name', studentName);
        formData.append('face_id', faceId);
        const res = await fetch('/csc2052/api/student.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.status === 'success') {
            const displayName = studentName || studentNo;
            speakVoice(displayName + ' face mapped');
            document.getElementById('enroll-face').value = '';
        } else alert("Failed: " + data.message);
    } catch (e) { alert("Network Error: " + e.message); }
};

window.editStudentMap = function(studentNo, studentName, slotId, rfidUid, faceId) {
    document.getElementById('enroll-student-no').value = studentNo;
    document.getElementById('enroll-student-name').value = studentName;
    if (slotId) document.getElementById('enroll-id').value = slotId;
    if (rfidUid) document.getElementById('enroll-rfid').value = rfidUid;
    if (faceId) document.getElementById('enroll-face').value = faceId;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    
    // Briefly highlight the enrollment section to show where to edit
    const formContainer = document.getElementById('enroll-student-no').closest('.card');
    if (formContainer) {
        formContainer.style.transition = 'box-shadow 0.3s ease-in-out';
        formContainer.style.boxShadow = '0 0 15px rgba(13, 110, 253, 0.5)';
        setTimeout(() => { formContainer.style.boxShadow = ''; }, 1500);
    }
};

window.updateStudentProfile = async function() {
    const stuNoInput = document.getElementById('enroll-student-no');
    const stuNo   = stuNoInput.value.trim();
    const name    = document.getElementById('enroll-student-name').value.trim();
    const fId     = document.getElementById('enroll-id').value.trim();
    const rfid    = document.getElementById('enroll-rfid').value.trim();
    const faceId  = document.getElementById('enroll-face').value.trim();
    
    stuNoInput.classList.remove('is-invalid');
    if (!stuNo) { showToast('Student number is required to update.', 'error'); stuNoInput.classList.add('is-invalid'); stuNoInput.focus(); return; }
    
    const fd = new FormData();
    fd.append('action', 'update_student');
    fd.append('student_no', stuNo);
    fd.append('student_name', name);
    if (fId)   fd.append('fingerprint_id', parseInt(fId));
    if (rfid)  fd.append('rfid_uid', rfid);
    if (faceId) fd.append('face_id', faceId);
    
    try {
        const res = await fetch('/csc2052/api/student.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'success') {
            showToast(data.message || 'Profile updated', 'success');
            speakVoice((name || stuNo) + ' updated');
            setTimeout(() => window.location.reload(), 800);
        } else {
            alert('Error: ' + data.message);
        }
    } catch (e) { alert('Network Error: ' + e.message); }
};

function speakVoice(text) {
    if ('speechSynthesis' in window) {
        const globalMute = document.getElementById('global-voice-mute')?.checked;
        if (globalMute) return;
        const utter = new SpeechSynthesisUtterance(text);
        utter.rate = 1;
        utter.pitch = 1;
        window.speechSynthesis.speak(utter);
    }
}

// ─── Voice settings cache (loaded from API) ─────────────────────────
window.voiceSettingsCache = null;
window.getVoiceSettings = async function() {
    if (window.voiceSettingsCache) return window.voiceSettingsCache;
    try {
        const r = await fetch('/csc2052/api/voice_settings.php');
        const d = await r.json();
        if (d.status === 'success') window.voiceSettingsCache = d.settings;
        return window.voiceSettingsCache || {};
    } catch(e) { return {}; }
};

window.autoFindRfid = async function(inputId) {
    try {
        const res = await fetch('/csc2052/api/rfid_scanned.php?action=consume');
        const data = await res.json();
        if (data.status === 'success') {
            document.getElementById(inputId).value = data.uid;
        } else {
            alert("No recent unlinked RFID scan found. Please scan a new RFID tag on the hardware device first.");
        }
    } catch (e) {
        alert("Network Error while finding RFID.");
    }
};

window.autoFindFace = async function(inputId) {
    try {
        const res = await fetch('/csc2052/api/face_scanned.php?action=consume');
        const data = await res.json();
        if (data.status === 'success') {
            document.getElementById(inputId).value = data.face_id;
        } else {
            alert("No recent unlinked Face scan found. Please scan a new face on the hardware device first.");
        }
    } catch (e) {
        alert("Network Error while finding Face ID.");
    }
};

window.triggerAdminEnroll = async function() {
    const adminNameInput = document.getElementById('enroll-admin-name');
    const fIdInput = document.getElementById('enroll-admin-id');
    const adminName = adminNameInput.value.trim();
    const fId = fIdInput.value;
    
    adminNameInput.classList.remove('is-invalid');
    fIdInput.classList.remove('is-invalid');
    
    if (!adminName) { showToast("Admin Name is required.", 'error'); adminNameInput.classList.add('is-invalid'); adminNameInput.focus(); return; }
    if (!fId || fId < 1 || fId > 127) { showToast("Valid Slot ID (1-127) is required.", 'error'); fIdInput.classList.add('is-invalid'); fIdInput.focus(); return; }
    
    try {
        const formData = new FormData();
        formData.append('action', 'link_admin');
        formData.append('admin_name', adminName);
        formData.append('finger_id', fId);
        const res = await fetch('/csc2052/api/student.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.status === 'success') {
            sendOtaCommand('ENROLL ' + fId);
            alert("Admin saved! Triggering FM10A Sensor on device...");
        } else alert("Failed: " + data.message);
    } catch (e) { alert("Network Error: " + e.message); }
};

window.triggerAdminEnrollRfid = async function() {
    const adminNameInput = document.getElementById('enroll-admin-name');
    const rfidInput = document.getElementById('enroll-admin-rfid');
    const adminName = adminNameInput.value.trim();
    const rfidUid = rfidInput?.value.trim();
    
    adminNameInput.classList.remove('is-invalid');
    if (rfidInput) rfidInput.classList.remove('is-invalid');
    
    if (!adminName) { showToast("Admin Name is required.", 'error'); adminNameInput.classList.add('is-invalid'); adminNameInput.focus(); return; }
    if (!rfidUid) { showToast("RFID UID is required. Click 'Scan RFID' and scan a card on the device.", 'error'); if (rfidInput) { rfidInput.classList.add('is-invalid'); rfidInput.focus(); } return; }
    
    try {
        const formData = new FormData();
        formData.append('action', 'link_admin_rfid');
        formData.append('admin_name', adminName);
        formData.append('rfid_uid', rfidUid);
        const res = await fetch('/csc2052/api/student.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.status === 'success') {
            showToast(data.message || "Admin RFID mapped successfully!", 'success');
            speakVoice(adminName + ' RFID linked');
            document.getElementById('enroll-admin-rfid').value = '';
        } else showToast("Failed: " + data.message, 'error');
    } catch (e) { showToast("Network Error: " + e.message, 'error'); }
};

window.triggerAdminEnrollFace = async function() {
    const adminNameInput = document.getElementById('enroll-admin-name');
    const faceInput = document.getElementById('enroll-admin-face');
    const adminName = adminNameInput.value.trim();
    const faceId = faceInput?.value.trim();
    
    adminNameInput.classList.remove('is-invalid');
    if (faceInput) faceInput.classList.remove('is-invalid');
    
    if (!adminName) { showToast("Admin Name is required.", 'error'); adminNameInput.classList.add('is-invalid'); adminNameInput.focus(); return; }
    if (!faceId) { showToast("Face ID is required.", 'error'); if (faceInput) { faceInput.classList.add('is-invalid'); faceInput.focus(); } return; }
    
    try {
        const formData = new FormData();
        formData.append('action', 'link_admin_face');
        formData.append('admin_name', adminName);
        formData.append('face_id', faceId);
        const res = await fetch('/csc2052/api/student.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.status === 'success') {
            alert("Admin Face mapped successfully!");
            document.getElementById('enroll-admin-face').value = '';
        } else alert("Failed: " + data.message);
    } catch (e) { alert("Network Error: " + e.message); }
};

window.uploadFaceDataset = async function() {
    const studentNo = document.getElementById('enroll-student-no').value.trim();
    const studentName = document.getElementById('enroll-student-name')?.value.trim() || 'Unknown';
    const fileInput = document.getElementById('face-dataset-upload');
    const files = fileInput?.files;
    
    if (!studentNo) { alert("Student No is required."); return; }
    if (!files || files.length === 0) { alert("Please select at least one image file."); return; }
    
    const formData = new FormData();
    formData.append('student_no', studentNo);
    formData.append('student_name', studentName);
    for (let i = 0; i < files.length; i++) {
        formData.append('face_images[]', files[i]);
    }
    
    try {
        const res = await fetch('/csc2052/api/upload_faces.php', { method: 'POST', body: formData });
        const result = await res.json();
        if (result.status === 'success') {
            alert(`Uploaded ${result.count} images successfully.\n${result.message}`);
            fileInput.value = '';
        } else {
            alert("Upload failed: " + result.message);
        }
    } catch (e) { alert("Network Error: " + e.message); }
};

window.deleteStudentMap = async function(studentNo) {
    if (!confirm(`Delete all data for student ${studentNo}?\n\nThis will remove fingerprint, RFID, face, and web face records.\nThis action cannot be undone.`)) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'delete_student');
        formData.append('student_no', studentNo);
        const res = await fetch('/csc2052/api/student.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.status === 'success') {
            alert(data.message);
            window.location.reload();
        } else alert("Failed: " + data.message);
    } catch (e) { alert("Network Error: " + e.message); }
};

window.bulkDeleteTemplates = async function() {
    if (!confirm("WARNING! This will WIPE ALL enrolled student mappings permanently. Are you sure?")) return;
    if (!confirm("FINAL WARNING: This cannot be undone. All student data, biometric templates, and face descriptors will be deleted. Continue?")) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'bulk_delete_students');
        const res = await fetch('/csc2052/api/student.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.status === 'success') {
            alert(data.message);
            window.location.reload();
        } else alert("Failed: " + data.message);
    } catch (e) { alert("Network Error: " + e.message); }
};

window.pushTemplateToDevice = async function(studentNo, slotId) {
    logToTerminal(`\n> Fetching DB template for ${studentNo}...`);
    try {
        const res = await fetch(`download_template.php?student_no=${encodeURIComponent(studentNo)}`);
        if (!res.ok) {
            logToTerminal("[ERR] HTTP Exception downloading template bytes.");
            return;
        }
        const hexTpl = await res.text();
        if (!hexTpl || hexTpl.length < 10) {
            logToTerminal("[ERR] Template data invalid or missing from DB!");
            return;
        }
        logToTerminal(`> TX: Pushing Template HEX to ESP (Slot ${slotId})...`);
        sendOtaCommand(`LOAD_TEMPLATE ${slotId} ${hexTpl}`);
    } catch (e) {
        logToTerminal("[ERR] Network fetch failed.");
    }
};

window.filterTemplates = function() {
    const query = document.getElementById('template-search')?.value.toLowerCase() || '';
    const rows = document.querySelectorAll('.template-row');
    rows.forEach(row => {
        const studentId = row.querySelector('.template-student-id').textContent.toLowerCase();
        const studentName = row.querySelector('.template-student-name')?.textContent.toLowerCase() || '';
        if (studentId.includes(query) || studentName.includes(query)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
};

window.loadTemplateAttendance = function() {
    const course = document.getElementById('template-attendance-course')?.value || '';
    const badges = document.querySelectorAll('.attendance-badge');
    badges.forEach(badge => {
        const overall = parseInt(badge.dataset.overall) || 0;
        let pct, detail;
        if (course) {
            const key = 'course' + course.replace(/[^a-zA-Z0-9]/g, '');
            const val = badge.dataset[key] || '0|0/0';
            const parts = val.split('|');
            pct = parseInt(parts[0]) || 0;
            detail = parts[1] || '0/0';
        } else {
            pct = overall;
            detail = 'overall';
        }
        const badgeClass = pct >= 80 ? 'bg-success' : (pct >= 50 ? 'bg-warning text-dark' : (pct > 0 ? 'bg-danger' : 'bg-secondary'));
        const borderClass = pct >= 80 ? 'success' : (pct >= 50 ? 'warning' : 'danger');
        if (pct > 0) {
            badge.className = `badge rounded-pill ${badgeClass} bg-opacity-10 border border-${borderClass}-subtle px-2 py-1 attendance-badge`;
            badge.dataset.overall = overall;
            badge.innerHTML = `${pct}% <small class="opacity-75">(${detail})</small>`;
        } else {
            badge.className = 'text-muted small attendance-badge';
            badge.dataset.overall = overall;
            badge.innerHTML = '0%';
        }
    });
};

async function pollRfidFill() {
    const targets = ['enroll-rfid', 'enroll-admin-rfid'];
    let activeInput = null;
    for (const id of targets) {
        const el = document.getElementById(id);
        if (el && el.offsetParent !== null) { activeInput = el; break; }
    }
    // RFID auto-fill runs even during OTA commands — scanning is independent
    if (activeInput) { 
        try {
            const res = await fetch('/csc2052/api/rfid_scanned.php?action=consume');
            if (!res.ok) return;
            const data = await res.json();
            if (data && data.status === 'success' && data.uid) {
                activeInput.value = data.uid;
                activeInput.style.backgroundColor = '#d1e7dd';
                setTimeout(() => activeInput.style.backgroundColor = '', 1000);
                speakVoice('RFID scanned: ' + data.uid);
            }
        } catch (e) {}
    }
}

// ─── Trigger RFID scan on hardware device ──────────────────────────
window.triggerRfidScan = function(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    // Get device IP from the students selector or global selector
    const stuSel = document.getElementById('students-device-select');
    const globalSel = document.getElementById('action-target-device');
    const targetIP = (stuSel && stuSel.value && stuSel.value !== '' && stuSel.value !== 'WEB_DASHBOARD') ? stuSel.value
                   : (globalSel && globalSel.value && globalSel.value !== '' && globalSel.value !== 'WEB_DASHBOARD' && globalSel.value !== '__loading__') ? globalSel.value
                   : null;

    if (!targetIP) {
        showToast('⚠️ Please select an active ESP32 hardware device first.', 'warning');
        return;
    }

    input.value = '';
    input.placeholder = 'Scanning...';
    input.style.backgroundColor = '#fff3cd';
    showToast('📱 Scan an RFID card on the hardware device...', 'info');

    // Send SCAN_RFID command to device
    fetch(`/csc2052/api/esp_ota_proxy.php?ip=${encodeURIComponent(targetIP)}&command=${encodeURIComponent('SCAN_RFID')}`, { signal: AbortSignal.timeout(5000) })
        .catch(function(e) { /* ignore — SCAN_RFID is best-effort */ });

    // pollRfidFill will auto-populate when the card is scanned
};

// ─── RASPBERRY PI ──────────────────────────────────────────────────
window.connectRpiCamera = function() {
    const url = document.getElementById('rpi-stream-url').value.trim();
    const img = document.getElementById('rpi-camera-feed');
    const overlay = document.getElementById('rpi-camera-overlay');
    
    if (!url) {
        alert("Please enter a valid stream URL");
        return;
    }
    
    overlay.innerHTML = '<div class="spinner-border text-danger" role="status"></div><br><small>Connecting...</small>';
    
    img.onload = function() {
        overlay.style.display = 'none';
        img.style.display = 'block';
        logToTerminalRpi("[CAMERA] Stream connected successfully.");
    };
    
    img.onerror = function() {
        overlay.innerHTML = '<i class="bi bi-exclamation-triangle fs-1 text-danger"></i><br><small class="text-danger">Connection Failed</small>';
        img.style.display = 'none';
        overlay.style.display = 'block';
        logToTerminalRpi("[CAMERA] Error: Failed to connect to stream at " + url);
    };
    
    img.src = url;
    logToTerminalRpi("\n> Attempting connection to Camera Stream...");
};

function getRpiIp() {
    // Find the first online RPi device in the discovered registry
    const rpi = _discoveredDevices.find(d => d.type === 'rpi' && !d.blocked);
    if (rpi) return rpi.ip;
    // Fallback: use navbar IP if nothing discovered
    return localStorage.getItem('rpi_ip_cache') || null;
}

window.sendOtaCommandRpi = async function(cmd) {
    const ip = getRpiIp();
    const deviceName = getDeviceName() || 'WEB_DASHBOARD';
    logToTerminalRpi('\n> TX (RPi): ' + cmd);
    if (!ip && deviceName === 'WEB_DASHBOARD') {
        logToTerminalRpi('[RPi] ERROR: No Raspberry Pi detected. Check heartbeat / network.');
        return;
    }
    // Use server-side OTA queue for RPi
    try {
        const fd = new FormData();
        fd.append('action', 'send');
        fd.append('device', deviceName);
        fd.append('command', cmd);
        fd.append('key', 'ss_hw_api_key_2052');
        const res = await fetch('/csc2052/api/ota.php', { method: 'POST', body: fd });
        const data = await res.json();
        logToTerminalRpi('[RPi RX] ' + (data.message || data.command || 'Queued'));
    } catch(e) {
        logToTerminalRpi('[RPi] ERROR: OTA queue failed — ' + e.message);
    }
};

window.sendCustomOtaRpi = function() {
    const cmdInput = document.getElementById('custom-ota-cmd-rpi');
    if (cmdInput && cmdInput.value.trim() !== '') {
        sendOtaCommandRpi(cmdInput.value.trim());
        cmdInput.value = '';
    }
};

window.scanNetworksRpi = async function() {
    logToTerminalRpi("> Starting Wi-Fi Air Scan (RPi)...");
    const select = document.getElementById('new-ssid-rpi');
    if (select) select.innerHTML = '<option value="">Scanning Wi-Fi... Please wait.</option>';
    try {
        const ip = getRpiIp();
        if (!ip) { logToTerminalRpi("[SCANWIFI] No RPi detected."); return; }
        const res = await fetch(`http://${ip}/cmd?command=SCANWIFI`, { signal: AbortSignal.timeout(15000) });
        if (!res.ok) throw new Error("HTTP " + res.status);
        const networks = await res.json();
        if (select) {
            select.innerHTML = '<option value="">Select a Wi-Fi Network</option>';
            networks.forEach(ssid => {
                const opt = document.createElement('option');
                opt.value = escapeHtml(ssid); opt.textContent = escapeHtml(ssid);
                select.appendChild(opt);
            });
        }
        logToTerminalRpi(`[SCANWIFI] Found ${networks.length} networks.`);
    } catch (e) {
        logToTerminalRpi("[SCANWIFI] RPi Network Error: " + e.message);
        if (select) select.innerHTML = '<option value="">Scan failed. Node offline?</option>';
    }
};

window.scanBluetoothRpi = async function() {
    logToTerminalRpi("> Starting Bluetooth LE Scan (RPi)...");
    const select = document.getElementById('bt-devices-list-rpi');
    if (select) select.innerHTML = '<option value="">Scanning BLE... Please wait.</option>';
    try {
        const ip = getRpiIp();
        if (!ip) { logToTerminalRpi("[SCANBT] No RPi detected."); return; }
        const res = await fetch(`http://${ip}/cmd?command=SCANBT`, { signal: AbortSignal.timeout(15000) });
        if (!res.ok) throw new Error("HTTP " + res.status);
        const devices = await res.json();
        if (select) {
            select.innerHTML = '<option value="">Select a BLE Device</option>';
            devices.forEach(d => {
                const opt = document.createElement('option');
                opt.value = escapeHtml(d.mac); opt.textContent = `${escapeHtml(d.name)} (${escapeHtml(d.mac)})`;
                select.appendChild(opt);
            });
        }
        logToTerminalRpi(`[SCANBT] Found ${devices.length} BLE devices.`);
    } catch (e) {
        logToTerminalRpi("[SCANBT] RPi Network Error: " + e.message);
        if (select) select.innerHTML = '<option value="">Scan failed. Node offline?</option>';
    }
};

window.updateWifiRpi = function() {
    const ssidDrop = document.getElementById('new-ssid-rpi');
    const ssidMan  = document.getElementById('manual-ssid-rpi');
    const pass     = document.getElementById('new-pass-rpi');
    
    let ssid = ssidMan && ssidMan.value.trim() ? ssidMan.value.trim() : (ssidDrop ? ssidDrop.value : '');
    let p = pass ? pass.value.trim() : '';
    let identity = document.getElementById('new-identity-rpi')?.value.trim() || '';
    
    if (!ssid) { alert("Please provide an SSID"); return; }
    if (!confirm(`This will update RPi Wi-Fi credentials and reboot the node. Continue?`)) return;
    
    let cmd = 'SETWIFI ' + ssid;
    if (identity) cmd += '|' + identity;
    cmd += '|' + p;
    
    sendOtaCommandRpi(cmd);
    
    if (ssidMan) ssidMan.value = '';
    if (document.getElementById('new-identity-rpi')) document.getElementById('new-identity-rpi').value = '';
    if (pass) pass.value = '';
    if (ssidDrop) ssidDrop.selectedIndex = 0;
};

window.saveDeviceSettingsRpi = async function() {
    const fp_power       = document.getElementById('set_fp_power_rpi')?.checked ? 1 : 0;
    const display_power  = document.getElementById('set_display_power_rpi')?.checked ? 1 : 0;
    const backlight      = document.getElementById('set_backlight_power_rpi')?.checked ? 1 : 0;
    const bt_on          = document.getElementById('set_bluetooth_on_rpi')?.checked ? 1 : 0;
    const en_face        = document.getElementById('set_enable_face_rpi')?.checked ? 1 : 0;
    const mfa            = document.getElementById('set_require_multi_factor_rpi')?.checked ? 1 : 0;
    const threshold      = document.getElementById('set_enroll_fingers_rpi')?.value || 0.6;

    logToTerminalRpi("\n> Saving RPi Hardware Settings...");
    
    const otaCmd = `SETCONFIG fp_power=${fp_power},display_power=${display_power},backlight=${backlight},` +
                   `bt=${bt_on},en_face=${en_face},mfa=${mfa},threshold=${threshold}`;
    
    sendOtaCommandRpi(otaCmd);
    logToTerminalRpi('[RPi] Settings pushed to hardware.');
    alert('RPi settings sent to hardware node!');
};

function logToTerminalRpi(text) {
    const terminal = document.getElementById('rpi-serial-output');
    if (terminal) {
        terminal.textContent += text + "\n";
        terminal.scrollTop = terminal.scrollHeight;
    }
}

window.downloadSdConfigFromForm = function() {
    const name = document.getElementById('sd-device-name')?.value?.trim() || 'ESP32-Node';
    const ssid = document.getElementById('sd-wifi-ssid')?.value?.trim();
    if (!ssid) { alert('Enter WiFi SSID.'); return; }
    const pass = document.getElementById('sd-wifi-pass')?.value?.trim() || '';
    const serverUrl = document.getElementById('sd-server-url')?.value?.trim() || 'http://10.0.0.5/csc2052';
    const method = document.getElementById('sd-enc-method')?.value || 'plaintext';
    const hwKey = document.getElementById('sd-hw-key')?.value?.trim() || 'ss_hw_api_key_2052';
    const token = document.getElementById('sd-enc-token')?.value?.trim() || '';
    if ((method === 'hmac' || method === 'aes') && !token) { alert('Enter a Secret Token for ' + method + ' mode.'); return; }
    // Extract IP from server URL
    let serverIp = '10.0.0.5';
    try {
        const urlObj = new URL(serverUrl);
        serverIp = urlObj.hostname;
    } catch(e) { /* keep default */ }
    // Static IP
    const sip   = document.getElementById('sd-static-ip')?.value?.trim() || '';
    const sgw   = document.getElementById('sd-static-gateway')?.value?.trim() || '';
    const ssn   = document.getElementById('sd-static-subnet')?.value?.trim() || '';
    const sdns  = document.getElementById('sd-static-dns')?.value?.trim() || '';
    let staticBlock = '';
    if (sip) {
        staticBlock = `STATIC_IP=${sip}\n`;
        if (sgw) staticBlock += `STATIC_GATEWAY=${sgw}\n`;
        if (ssn) staticBlock += `STATIC_SUBNET=${ssn}\n`;
        if (sdns) staticBlock += `STATIC_DNS=${sdns}\n`;
    }
    let extraKeys = `HARDWARE_KEY=${hwKey}\n`;
    if (token) extraKeys += `SECRET_KEY=${token}\n`;
    const content = `# Sentinel Swarm ESP32 Config File\n# Place this file as config.txt on SD card root\nDEVICE_NAME=${name}\nWIFI_SSID=${ssid}\nWIFI_PASS=${pass}\nSERVER_IP=${serverIp}\nSERVER_URL=${serverUrl}\nENCRYPT_METHOD=${method}\n${extraKeys}${staticBlock}`;
    const blob = new Blob([content], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'config.txt';
    a.click();
    URL.revokeObjectURL(url);
};

window.downloadSdConfig = function() {
    const ip = getEspIp() || '192.168.1.100';
    const name = prompt('Enter device name (e.g. ESP32-Lab1):', 'ESP32-Node');
    if (!name) return;
    const ssid = prompt('Enter WiFi SSID:', 'Card ekak daganin bn');
    if (!ssid) return;
    const pass = prompt('Enter WiFi Password:', '');
    const serverUrl = prompt('Enter Server URL (full path):', 'http://' + ip + '/csc2052');
    if (!serverUrl) return;
    const method = prompt('Encryption Method (plaintext/hmac/aes):', 'hmac');
    if (!method) return;
    const token = prompt('Enter Secret Token (leave empty for legacy):', '');
    const hwKey = 'ss_hw_api_key_2052';
    let extraKeys = `HARDWARE_KEY=${hwKey}\n`;
    if (token) extraKeys += `SECRET_KEY=${token}\n`;
    const content = `# Sentinel Swarm ESP32 Config File\n# Place this file as config.txt on SD card root\nDEVICE_NAME=${name}\nWIFI_SSID=${ssid}\nWIFI_PASS=${pass}\nSERVER_URL=${serverUrl}\nENCRYPT_METHOD=${method}\n${extraKeys}`;
    const blob = new Blob([content], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'config.txt';
    a.click();
    URL.revokeObjectURL(url);
};

window.downloadFirmwareSource = async function() {
    try {
        const res = await fetch('esp32_firmware/esp32_firmware.ino');
        if (!res.ok) { alert('Firmware source not found on server.'); return; }
        const text = await res.text();
        const blob = new Blob([text], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'esp32_firmware.ino';
        a.click();
        URL.revokeObjectURL(url);
    } catch (e) { alert('Failed to download firmware: ' + e.message); }
};

window.downloadSetupGuide = function() {
    const guide = `==================================================
SENTINEL SWARM - ESP32 Node Setup Guide
==================================================

1. HARDWARE REQUIREMENTS
   - ESP32 DevKit V1 (or compatible)
   - Micro USB cable (for programming)
   - Breadboard and jumper wires
   - Optional: SD Card module + microSD card
   - Optional: LCD 16x2/20x4 (I2C)
   - Optional: Fingerprint sensor (R307/AS608/FM10A)
    - Optional: RFID reader (MFRC522 / RC522)
    - Optional: HW-084 sound sensor / clap detector

2. ARDUINO IDE SETUP
   a. Install Arduino IDE 2.x from arduino.cc
   b. Go to File > Preferences
   c. Add this URL to "Additional Board Manager URLs":
      https://espressif.github.io/arduino-esp32/package_esp32_index.json
   d. Go to Tools > Board > Board Manager
   e. Search "esp32" and install version 3.x (by Espressif)

3. REQUIRED LIBRARIES
   Install via Sketch > Include Library > Manage Libraries:
   - Adafruit GFX Library (for LCD)
   - Adafruit SSD1306 (for OLED) or LiquidCrystal I2C
   - Adafruit Fingerprint Sensor (for AS608/FM10A)
    - MFRC522 (for RFID / RC522)
    - RTClib (for DS3231 RTC)
    - ArduinoJson (by Benoit Blanchon)
    - HW-084: no library needed (analog read only)
    NOTE: BLE, SD, Fingerprint, RFID, LCD, HW-084 are optional!

4. FLASH SPACE MANAGEMENT (IMPORTANT!)
   The ESP32 has limited flash. The firmware uses FEATURE FLAGS
   at the top of esp32_firmware.ino to save space:

    #define USE_FINGERPRINT     // Enable FM10A fingerprint sensor (~40KB)
    #define USE_RFID            // Enable MFRC522 RFID-RC522 reader (~15KB)
    #define USE_LCD             // Enable I2C LCD display 16x2/20x4 (~10KB)
    #define USE_HW084           // Enable HW-084 sound sensor module (~2KB)
    #define USE_SD              // Enable SD card boot (~8KB)
    // #define USE_BLE          // BLE scanner DISABLED by default (~200KB!)

   If you get "Sketch too big" error:
   a. Comment out features you don't need with //
   b. Keep USE_BLE commented out (saves 200KB!)
   c. Recompile

5. FLASHING THE FIRMWARE
   a. Open esp32_firmware.ino in Arduino IDE
   b. Edit FEATURE FLAGS at top of file (disable unused features)
   c. Select Board: "ESP32 Dev Module"
   d. Select Port: (your ESP32 COM port)
   e. Upload Speed: 921600
   f. Click Upload button

6. SD CARD BOOT CONFIGURATION (OPTIONAL)
   a. Requires USE_SD flag enabled in firmware
   b. Download the SD Config File from dashboard
   c. You will be prompted for:
      - Device Name (e.g. ESP32-Lab1)
      - WiFi SSID and Password
      - Server URL (full path like http://10.0.0.5/csc2052)
      - Encryption Method: plaintext, hmac, or aes
      - Secret Token (for HMAC/AES authentication)
   d. Save as config.txt on root of microSD card (FAT32)
   e. Insert SD card into ESP32 module and power on

7. ENCRYPTION METHODS
   - plaintext: No encryption (default, for testing)
   - hmac: Hash-based Message Authentication Code
     Prevents tampering - server verifies token matches
   - aes: AES-128 encrypted payload
     Full encryption - hacker cannot read or modify data
   NOTE: Set the same method/token in config.php HEARTBEAT_SECRET

8. WIFI CONNECTION
   The firmware supports:
   - Standard WPA/WPA2 Personal (SSID + Password)
   - WPA2-Enterprise (PEAP/MSCHAPv2 with Identity)
   Configure via the dashboard WiFi section or SD config file.

9. ESP-NOW MESH NETWORKING
   After WiFi connection, ESP32 will:
   - Auto-discover other nodes on the network
   - Register with the web dashboard
   - Sync attendance data via HTTP API
   - Forward messages via ESP-NOW to offline nodes

10. TROUBLESHOOTING
   - "Sketch too big" error: Comment out unused feature flags
   - LiquidCrystal_I2C warning: Normal for ESP32, can be ignored
   - "Heartbeat FAIL: connection refused":
     1. Check SERVER_URL in SD config matches your PC's IP
     2. Verify XAMPP Apache is running
     3. Check Windows Firewall allows port 80
     4. Ping your PC's IP from another device
   - LED not blinking: Check power and USB connection
   - Not connecting to WiFi: Verify SSID/password, check Enterprise settings
   - Not appearing in dashboard: Check SERVER_URL, ensure firewall allows port 80
   - SD card not reading: Format as FAT32, ensure config.txt is in root

==================================================
`;
    const blob = new Blob([guide], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'ESP32_Setup_Guide.txt';
    a.click();
    URL.revokeObjectURL(url);
};

// ─── MULTI-DEVICE COURSE ASSIGNMENT ────────────────────────────────

window.assignCourseToDevice = async function() {
    const device = document.getElementById('assign-device-select')?.value;
    const code = document.getElementById('assign-course-select')?.value;
    if (!device) { alert('Select a device.'); return; }
    if (!code) { alert('Select a course.'); return; }

    const fd = new FormData();
    fd.append('action', 'assign_course_to_device');
    fd.append('device_id', device);
    fd.append('course_code', code);

    try {
        const res = await fetch('/csc2052/api/student.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'success') {
            showToast(data.message, 'success');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showToast(data.message, 'danger');
        }
    } catch (e) { showToast('Network error.', 'danger'); }
};

window.removeDeviceCourse = async function(device, code) {
    if (!confirm('Remove ' + code + ' from ' + device + '?')) return;

    const fd = new FormData();
    fd.append('action', 'remove_course_from_device');
    fd.append('device_id', device);
    fd.append('course_code', code);

    try {
        const res = await fetch('/csc2052/api/student.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'success') {
            showToast(data.message, 'success');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showToast(data.message, 'danger');
        }
    } catch (e) { showToast('Network error.', 'danger'); }
};

// ─── COURSE MANAGEMENT ─────────────────────────────────────────────

window.addCourse = async function() {
    const code = document.getElementById('new-course-code').value.trim().toUpperCase();
    const name = document.getElementById('new-course-name').value.trim();
    const msg = document.getElementById('course-add-msg');
    if (!code) { msg.innerHTML = '<span class="text-danger">Course code is required.</span>'; return; }
    if (!/^[A-Z]{2,5}\d{4}$/.test(code)) { msg.innerHTML = '<span class="text-danger">Invalid format. Use e.g. PHY1911.</span>'; return; }

    const fd = new FormData();
    fd.append('action', 'add_course');
    fd.append('course_code', code);
    fd.append('course_name', name);

    try {
        const res = await fetch('/csc2052/api/student.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'success') {
            msg.innerHTML = '<span class="text-success">' + escapeHtml(data.message) + '</span>';
            document.getElementById('new-course-code').value = '';
            document.getElementById('new-course-name').value = '';
            setTimeout(() => { msg.innerHTML = ''; }, 1200);
            refreshAllCourseDropdowns();
        } else {
            msg.innerHTML = '<span class="text-danger">' + escapeHtml(data.message) + '</span>';
        }
    } catch (e) { msg.innerHTML = '<span class="text-danger">Network error.</span>'; }
};

window.deleteCourse = async function(code) {
    if (!confirm('Delete course ' + code + '? This will also remove all student enrollments for this course.')) return;
    const fd = new FormData();
    fd.append('action', 'delete_course');
    fd.append('course_code', code);

    try {
        const res = await fetch('/csc2052/api/student.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'success') {
            showToast(data.message, 'success');
            refreshAllCourseDropdowns();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (e) { alert('Network error.'); }
};

window.enrollStudentCourse = async function() {
    const stuNo = document.getElementById('enroll-stu-course-no').value.trim();
    const code = document.getElementById('enroll-stu-course-select').value;
    const msg = document.getElementById('course-enroll-msg');

    if (!stuNo) { msg.innerHTML = '<span class="text-danger">Student number is required.</span>'; return; }
    if (!code) { msg.innerHTML = '<span class="text-danger">Select a course.</span>'; return; }

    const fd = new FormData();
    fd.append('action', 'enroll_student_course');
    fd.append('student_no', stuNo);
    fd.append('course_code', code);

    try {
        const res = await fetch('/csc2052/api/student.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'success') {
            msg.innerHTML = '<span class="text-success">' + escapeHtml(data.message) + '</span>';
            document.getElementById('enroll-stu-course-no').value = '';
            document.getElementById('enroll-stu-course-select').selectedIndex = 0;
            setTimeout(() => { msg.innerHTML = ''; }, 2500);
        } else {
            msg.innerHTML = '<span class="text-danger">' + escapeHtml(data.message) + '</span>';
        }
    } catch (e) { msg.innerHTML = '<span class="text-danger">Network error.</span>'; }
};

window.bulkEnrollCourseCSV = async function() {
    const fileInput = document.getElementById('course-bulk-csv');
    const log = document.getElementById('course-bulk-log');

    if (!fileInput.files || fileInput.files.length === 0) {
        log.innerHTML = '<span class="text-danger">Select a CSV file first.</span>';
        return;
    }

    const file = fileInput.files[0];
    const filename = file.name.replace('.csv', '').toUpperCase();
    log.innerHTML = '<span class="text-muted"><i class="bi bi-arrow-repeat spin me-1"></i>Processing ' + escapeHtml(file.name) + '...</span>';

    const fd = new FormData();
    fd.append('action', 'bulk_enroll_course_csv');
    fd.append('csv_file', file);

    try {
        const res = await fetch('/csc2052/api/student.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'success') {
            log.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>' + escapeHtml(data.message) + '</span>';
            fileInput.value = '';
            setTimeout(() => { window.location.reload(); }, 2000);
        } else {
            log.innerHTML = '<span class="text-danger">' + escapeHtml(data.message) + '</span>';
        }
    } catch (e) { log.innerHTML = '<span class="text-danger">Network error.</span>'; }
};

window.lookupStudentCourses = async function() {
    const stuNo = document.getElementById('lookup-stu-course').value.trim();
    const result = document.getElementById('lookup-courses-result');
    if (!stuNo) { result.innerHTML = '<span class="text-muted">Enter a student number.</span>'; return; }

    result.innerHTML = '<span class="text-muted"><i class="bi bi-arrow-repeat spin me-1"></i>Loading...</span>';
    try {
        const res = await fetch('/csc2052/api/student.php?action=get_student_courses&student_no=' + encodeURIComponent(stuNo));
        const data = await res.json();
        if (data.status === 'success' && data.courses.length > 0) {
            let html = '<strong>Enrolled Courses for ' + escapeHtml(stuNo) + ':</strong><ul class="mb-0 mt-1">';
            data.courses.forEach(c => {
                html += '<li><strong>' + escapeHtml(c.course_code) + '</strong>';
                if (c.attendance_pct !== null) html += ' — Attendance: <strong>' + c.attendance_pct + '%</strong> (' + c.attended + '/' + c.total + ')';
                html += '</li>';
            });
            html += '</ul>';
            result.innerHTML = html;
        } else if (data.status === 'success') {
            result.innerHTML = '<span class="text-muted">No course enrollments found for ' + escapeHtml(stuNo) + '.</span>';
        } else {
            result.innerHTML = '<span class="text-danger">' + escapeHtml(data.message) + '</span>';
        }
    } catch (e) { result.innerHTML = '<span class="text-danger">Network error.</span>'; }
};

window.unenrollStudentCourse = async function(stuNo, code) {
    if (!confirm('Remove ' + stuNo + ' from ' + code + '?')) return;
    const fd = new FormData();
    fd.append('action', 'unenroll_student_course');
    fd.append('student_no', stuNo);
    fd.append('course_code', code);

    try {
        const res = await fetch('/csc2052/api/student.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'success') {
            showToast(data.message, 'success');
            if (document.getElementById('lookup-courses-result').innerHTML !== '') lookupStudentCourses();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (e) { alert('Network error.'); }
};

// ─── Raspberry Pi Camera Attendance ────────────────────────────────
let rpiPollInterval = null;

// getRpiIp() is already declared above — this section uses it for camera attendance

window.refreshRpiStatus = async function() {
    const ip = getRpiIp();
    const dot = document.getElementById('rpi-status-dot');
    const ipDisplay = document.getElementById('rpi-ip-display');
    if (!ip) return;

    try {
        const res = await fetch(`http://${ip}:5000/api/status`, { signal: AbortSignal.timeout(3000) });
        if (res.ok) {
            const data = await res.json();
            dot.className = 'badge bg-success';
            dot.innerHTML = '<i class="bi bi-check-circle me-1"></i>Online';
            ipDisplay.textContent = ip;
            return data;
        }
    } catch (e) {}
    dot.className = 'badge bg-secondary';
    dot.innerHTML = '<i class="bi bi-circle me-1"></i>Offline';
    ipDisplay.textContent = 'Not connected';
    return null;
};

window.saveRpiConfig = function() {
    const ip = getRpiIp();
    if (!ip) { alert('Enter Pi IP address.'); return; }
    localStorage.setItem('rpi_node_ip', ip);
    showToast('Pi config saved.', 'success');
    refreshRpiStatus();
};

window.triggerRpiOta = async function() {
    const ip = getRpiIp();
    if (!ip) { alert('Enter Pi IP address.'); return; }

    try {
        const res = await fetch('/csc2052/api/ota.php?action=poll&device=rpi');
        const data = await res.json();
        if (data.status === 'success' && data.commands) {
            const postRes = await fetch(`http://${ip}:5000/api/ota`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data.commands)
            });
            if (postRes.ok) showToast('Commands sent to Pi.', 'success');
            else showToast('Pi rejected commands.', 'warning');
        } else {
            showToast('No pending commands for Pi.', 'info');
        }
    } catch (e) {
        showToast('Could not reach Pi.', 'danger');
    }
};

window.startRpiAttendance = async function() {
    const ip = getRpiIp();
    if (!ip) { alert('Enter Pi IP address in config first.'); return; }

    document.getElementById('btn-start-rpi-attendance').classList.add('d-none');
    document.getElementById('btn-stop-rpi-attendance').classList.remove('d-none');
    document.getElementById('rpi-attendance-status').innerHTML = '<span class="text-primary">Connecting to Pi Camera...</span>';

    const img = document.getElementById('rpi-cam-stream');
    img.src = `http://${ip}:5000/stream`;
    img.style.display = 'block';
    document.getElementById('rpi-cam-placeholder').style.display = 'none';

    rpiPollInterval = setInterval(async () => {
        try {
            const res = await fetch(`http://${ip}:5000/api/face-status`, { signal: AbortSignal.timeout(3000) });
            if (!res.ok) return;
            const data = await res.json();

            if (data.face_detected) {
                const auto = document.getElementById('rpi-attendance-auto')?.checked;
                if (auto && data.student_no && !window.rpiLastMarked) {
                    window.rpiLastMarked = data.student_no;
                    setTimeout(() => { window.rpiLastMarked = null; }, 5000);

                    const fd = new FormData();
                    fd.append('action', 'manual_attendance');
                    fd.append('student_no', data.student_no);
                    fd.append('modality', 'rpi_face');
                    const course = window.activeWebCourse || document.getElementById('start-course-input')?.value.trim();
                    if (course) fd.append('course_code', course);
                    await fetch('/csc2052/api/student.php', { method: 'POST', body: fd });
                }

                const mute = document.getElementById('rpi-attendance-mute')?.checked;
                const globalMute = document.getElementById('global-voice-mute')?.checked;
                
                if (!mute && !globalMute && data.student_name && 'speechSynthesis' in window) {
                    const speechText = auto ? data.student_name + ' marked present' : data.student_name + ' detected';
                    window.speechSynthesis.speak(new SpeechSynthesisUtterance(speechText));
                }

                document.getElementById('rpi-attendance-status').innerHTML =
                    `<span class="text-success fw-bolder fs-5"><i class="bi bi-person-check-fill me-2"></i>${escapeHtml(data.student_name || data.student_no)} ${auto ? '<span class="badge bg-success shadow-sm ms-2 px-3 py-2 rounded-pill">Auto Marked</span>' : ''}</span>`;
            } else {
                document.getElementById('rpi-attendance-status').innerHTML =
                    '<span class="text-muted"><i class="bi bi-camera-video me-1"></i>Scanning...</span>';
            }
        } catch (e) {}
    }, 1500);
};

window.stopRpiAttendance = function() {
    if (rpiPollInterval) clearInterval(rpiPollInterval);
    rpiPollInterval = null;
    window.rpiLastMarked = null;
    const img = document.getElementById('rpi-cam-stream');
    img.src = '';
    img.style.display = 'none';
    document.getElementById('rpi-cam-placeholder').style.display = 'flex';
    document.getElementById('rpi-attendance-status').innerHTML = '';
    document.getElementById('btn-start-rpi-attendance').classList.remove('d-none');
    document.getElementById('btn-stop-rpi-attendance').classList.add('d-none');
};

(function() {
    const savedIp = localStorage.getItem('rpi_node_ip');
    if (savedIp) {
        const ipInput = document.getElementById('rpi-ip-search') || document.getElementById('rpi-node-ip');
        if (ipInput) ipInput.value = savedIp;
    }
})();

// ─── RPi Direct Commands ─────────────────────────────────────────
window.sendRpiCommand = async function(cmd) {
    const ip = getRpiIp();
    if (!ip) { alert('Set Pi IP address first.'); return; }
    try {
        const res = await fetch(`http://${ip}:5000/api/command`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ command: cmd }),
            signal: AbortSignal.timeout(10000)
        });
        const data = await res.json();
        const out = document.getElementById('rpi-serial-output');
        if (out) {
            const line = document.createElement('div');
            line.textContent = `[${new Date().toLocaleTimeString()}] ${data.message || data.output || JSON.stringify(data)}`;
            out.appendChild(line);
            out.scrollTop = out.scrollHeight;
        }
        if (data.status === 'error') alert('Pi Error: ' + (data.message || 'Unknown'));
    } catch (e) {
        alert('Cannot reach Pi at ' + ip + ': ' + e.message);
    }
};

window.fetchRpiSysInfo = async function() {
    const ip = getRpiIp();
    if (!ip) { alert('Set Pi IP address first.'); return; }
    try {
        const res = await fetch(`http://${ip}:5000/api/sysinfo`, { signal: AbortSignal.timeout(5000) });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const d = await res.json();
        document.getElementById('rpi-cpu-temp').textContent = d.cpu_temp ?? '—';
        document.getElementById('rpi-cpu-usage').textContent = d.cpu_usage ?? '—';
        document.getElementById('rpi-ram-free').textContent = d.ram_free ?? '—';
        document.getElementById('rpi-uptime').textContent = d.uptime ?? '—';
        document.getElementById('rpi-face-count').textContent = d.face_count ?? '—';
        const svc = document.getElementById('rpi-service-status');
        svc.textContent = d.service ?? '—';
        svc.className = 'badge ' + (d.service === 'running' ? 'bg-success' : 'bg-danger');
    } catch (e) { alert('Cannot reach Pi: ' + e.message); }
};

// ─── Device Registration ──────────────────────────────────────────
window.registerNewDevice = async function() {
    const ip = document.getElementById('new-device-ip')?.value?.trim();
    const name = document.getElementById('new-device-name')?.value?.trim();
    const type = document.getElementById('new-device-type')?.value || 'esp32';
    if (!ip || !name) { alert('Please fill in IP and Name.'); return; }
    try {
        const fd = new FormData();
        fd.append('ip', ip);
        fd.append('name', name);
        fd.append('type', type);
        const res = await fetch('/csc2052/api/devices.php?action=register', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.status === 'ok') {
            showToast('Device registered!', 'success');
            document.getElementById('new-device-ip').value = '';
            document.getElementById('new-device-name').value = '';
            await refreshDeviceList();
        } else {
            alert('Error: ' + (data.message || 'Unknown'));
        }
    } catch(e) { alert('Could not reach server.'); }
};

// ─── EMAIL PRESETS (localStorage) ──────────────────────────────────────

const EMAIL_PRESETS_KEY = 'sentinel_email_presets';

function getEmailPresets() {
    try { return JSON.parse(localStorage.getItem(EMAIL_PRESETS_KEY) || '[]'); }
    catch (e) { return []; }
}

function saveEmailPresets(presets) {
    localStorage.setItem(EMAIL_PRESETS_KEY, JSON.stringify(presets));
}

window.saveEmailPreset = function() {
    const subject = document.getElementById('custom-email-subject')?.value.trim();
    const body = document.getElementById('custom-email-body')?.value.trim();
    if (!subject && !body) { showToast('Write a subject or message first.', 'warning'); return; }
    const name = prompt('Enter a name for this preset:', subject || 'My Preset');
    if (!name || !name.trim()) return;
    const presets = getEmailPresets();
    const idx = presets.findIndex(p => p.name === name.trim());
    const preset = { name: name.trim(), subject: subject || '', body: body || '', savedAt: new Date().toISOString() };
    if (idx >= 0) {
        if (!confirm(`Overwrite existing preset "${name.trim()}"?`)) return;
        presets[idx] = preset;
    } else { presets.push(preset); }
    saveEmailPresets(presets);
    showToast(`Preset "${name.trim()}" saved!`, 'success');
    renderEmailPresetsCard();
    renderModalPresetSelect();
};

window.loadEmailPreset = function(name) {
    if (!name) return;
    const preset = getEmailPresets().find(p => p.name === name);
    if (!preset) return;
    const subjectEl = document.getElementById('custom-email-subject');
    const bodyEl = document.getElementById('custom-email-body');
    const charEl = document.getElementById('email-body-chars');
    if (subjectEl) subjectEl.value = preset.subject;
    if (bodyEl) { bodyEl.value = preset.body; if (charEl) charEl.textContent = preset.body.length; }
    showToast(`Preset "${name}" loaded.`, 'info');
};

window.deleteEmailPreset = function(name) {
    if (!name) { showToast('Select a preset to delete.', 'warning'); return; }
    if (!confirm(`Delete preset "${name}"?`)) return;
    saveEmailPresets(getEmailPresets().filter(p => p.name !== name));
    showToast(`Preset "${name}" deleted.`, 'success');
    renderEmailPresetsCard();
    renderModalPresetSelect();
    const sel = document.getElementById('modal-preset-select');
    if (sel) sel.value = '';
};

function renderEmailPresetsCard() {
    const container = document.getElementById('email-presets-container');
    if (!container) return;
    const presets = getEmailPresets();
    if (presets.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-2"><em>No presets saved yet. Compose an email and click "Save as Preset".</em></div>';
        return;
    }
    let html = '<div class="row g-2">';
    presets.forEach(p => {
        const savedDate = p.savedAt ? new Date(p.savedAt).toLocaleDateString() : '';
        html += `
        <div class="col-md-6 col-lg-4">
            <div class="card border shadow-sm h-100">
                <div class="card-body py-2 px-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong class="text-primary small">${escapeHtml(p.name)}</strong>
                            <div class="text-muted small text-truncate" style="max-width:200px;">${escapeHtml(p.subject || '(no subject)')}</div>
                        </div>
                        <div class="d-flex gap-1">
                            <button class="btn btn-xs btn-outline-primary py-0 px-1" title="Load into composer"
                                onclick="openCustomEmailModal('blank'); setTimeout(() => loadEmailPreset(${JSON.stringify(p.name)}), 350)">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="btn btn-xs btn-outline-danger py-0 px-1" title="Delete preset"
                                onclick="deleteEmailPreset(${JSON.stringify(p.name)})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="text-muted" style="font-size:0.75rem;">${escapeHtml((p.body || '').substring(0, 60))}${(p.body?.length > 60) ? '…' : ''}</div>
                    ${savedDate ? `<small class="text-muted" style="font-size:0.7rem;">Saved: ${savedDate}</small>` : ''}
                </div>
            </div>
        </div>`;
    });
    html += '</div>';
    container.innerHTML = html;
}

function renderModalPresetSelect() {
    const sel = document.getElementById('modal-preset-select');
    if (!sel) return;
    const presets = getEmailPresets();
    sel.innerHTML = '<option value="">— Load a preset —</option>';
    presets.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.name;
        opt.textContent = p.name + (p.subject ? ` — ${p.subject.substring(0, 30)}` : '');
        sel.appendChild(opt);
    });
}

window.insertPlaceholder = function(text) {
    const ta = document.getElementById('custom-email-body');
    if (!ta) return;
    const start = ta.selectionStart;
    const end = ta.selectionEnd;
    ta.value = ta.value.substring(0, start) + text + ta.value.substring(end);
    ta.selectionStart = ta.selectionEnd = start + text.length;
    ta.focus();
    const charEl = document.getElementById('email-body-chars');
    if (charEl) charEl.textContent = ta.value.length;
};

/* ─── NEXT LECTURE SECTION ──────────────────────────────────── */
window.nextLectureSection = function() {
    const sectionInput = document.getElementById('lecture-section-input');
    const nextBtn = document.getElementById('btn-next-section');
    if (!sectionInput) return;
    const val = sectionInput.value.trim();
    // Auto-increment section letter/number
    const match = val.match(/^(.+?)\s*([A-Z])$/i);
    if (match) {
        const nextChar = String.fromCharCode(match[2].charCodeAt(0) + 1);
        sectionInput.value = match[1] + ' ' + nextChar;
    } else {
        sectionInput.value = val + ' (next)';
    }
    if (nextBtn) nextBtn.style.display = 'none';
    sectionInput.focus();
};

/* ─── CSV EXPORT ──────────────────────────────────────────────── */
/* ─── EXPORT VISIBLE LOGS (immediate, no modal) ───────────── */
window.exportVisibleLogsCsv = function() {
    const dateFrom = document.getElementById('filter-date-from')?.value || '';
    const dateTo = document.getElementById('filter-date-to')?.value || '';
    const course = document.getElementById('filter-course-input')?.value || document.getElementById('filter-course-search')?.value || '';
    const device = document.getElementById('filter-device')?.value || '';
    const studentNo = document.getElementById('filter-student')?.value.trim() || '';

    let startTime = dateFrom ? dateFrom + ' 00:00:00' : '';
    let endTime = dateTo ? dateTo + ' 23:59:59' : '';

    const params = new URLSearchParams();
    if (startTime) params.set('start_time', startTime);
    if (endTime) params.set('end_time', endTime);
    if (course) params.set('course_code', course);
    if (device) params.set('device', device);
    if (studentNo) params.set('student_no', studentNo);
    params.set('columns', 'student_no,student_name,course_code,timestamp,modality,device,is_offline');

    const url = '/csc2052/export_csv.php?' + params.toString();
    const a = document.createElement('a');
    a.href = url;
    a.download = '';
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
};

/* ─── EXPORT FROM LECTURE PAGE FORM (lecture.php) ──────────── */
window.exportLectureFilteredCSV = function() {
    const startTime = document.getElementById('export-start')?.value?.replace('T', ' ') || '';
    const endTime = document.getElementById('export-end')?.value?.replace('T', ' ') || '';
    const course = document.getElementById('export-course-input')?.value || document.getElementById('export-course-search')?.value || '';
    const device = document.getElementById('export-device')?.value || '';

    // Collect selected columns from csv-col-* checkboxes
    const cols = [];
    document.querySelectorAll('[id^="csv-col-"]:checked').forEach(cb => {
        if (cb.value && cb.value !== 'on') cols.push(cb.value);
    });

    const params = new URLSearchParams();
    if (startTime) params.set('start_time', startTime);
    if (endTime) params.set('end_time', endTime);
    if (course) params.set('course_code', course);
    if (device) params.set('device', device);
    if (cols.length) params.set('columns', cols.join(','));

    const url = '/csc2052/export_csv.php?' + params.toString();
    window.location.href = url;
};

window.exportLogsCsv = function() {
    const modal = document.getElementById('exportCsvModal');
    if (!modal) return;
    // Pre-fill from current filters
    const from = document.getElementById('filter-date-from');
    const to = document.getElementById('filter-date-to');
    const device = document.getElementById('filter-device');
    if (from) document.getElementById('export-date-from').value = from.value;
    if (to) document.getElementById('export-date-to').value = to.value;
    const filterCourseSearch = document.getElementById('filter-course-search');
    const exportCourseSearch = document.getElementById('export-course-search');
    const exportCourseInput = document.getElementById('export-course-input');
    if (filterCourseSearch && exportCourseSearch) {
        exportCourseSearch.value = filterCourseSearch.value;
    }
    const filterCourseInput = document.getElementById('filter-course-input');
    if (filterCourseInput && exportCourseInput) {
        exportCourseInput.value = filterCourseInput.value;
    }
    if (device) document.getElementById('export-device').value = device.value;
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
};

window.doExportCsv = function() {
    const dateFrom = document.getElementById('export-date-from')?.value || '';
    const dateTo = document.getElementById('export-date-to')?.value || '';
    const timeFrom = document.getElementById('export-time-from')?.value || '';
    const timeTo = document.getElementById('export-time-to')?.value || '';
    const course = document.getElementById('export-course-input')?.value || document.getElementById('export-course-search')?.value || '';
    const device = document.getElementById('export-device')?.value || '';

    // Build start/end datetime
    let startTime = dateFrom;
    if (dateFrom && timeFrom) startTime = dateFrom + ' ' + timeFrom + ':00';
    else if (dateFrom) startTime = dateFrom + ' 00:00:00';

    let endTime = dateTo;
    if (dateTo && timeTo) endTime = dateTo + ' ' + timeTo + ':59';
    else if (dateTo) endTime = dateTo + ' 23:59:59';

    // Collect selected columns
    const cols = [];
    document.querySelectorAll('#exportCsvModal .form-check-input:checked').forEach(cb => {
        cols.push(cb.value);
    });

    const params = new URLSearchParams();
    if (startTime) params.set('start_time', startTime);
    if (endTime) params.set('end_time', endTime);
    if (course) params.set('course_code', course);
    if (device) params.set('device', device);
    if (cols.length) params.set('columns', cols.join(','));

    const url = '/csc2052/export_csv.php?' + params.toString();
    window.location.href = url;
};

/* ─── TEACHER LECTURE GLOW ────────────────────────────────── */
window._lectureGlowInterval = null;
window.startLectureGlow = function(el) {
    if (window._lectureGlowInterval) clearInterval(window._lectureGlowInterval);
    if (!el) el = document.querySelector('.start-hero');
    if (!el) return;
    el.classList.add('glow-card');

    function update() {
        const x = Math.floor(Math.random() * 40) - 20;
        const y = Math.floor(Math.random() * 40) - 20;
        const blur = Math.floor(Math.random() * 60) + 30;
        const spread = Math.floor(Math.random() * 6) + 1;
        const hue = Math.floor(Math.random() * 360);
        const opacity = (Math.random() * 0.3 + 0.12).toFixed(2);
        el.style.setProperty('--glow-x', x + 'px');
        el.style.setProperty('--glow-y', y + 'px');
        el.style.setProperty('--glow-blur', blur + 'px');
        el.style.setProperty('--glow-spread', spread + 'px');
        el.style.setProperty('--glow-color', 'hsla(' + hue + ', 85%, 60%, ' + opacity + ')');
    }
    update();
    window._lectureGlowInterval = setInterval(update, 800);
};

window.stopLectureGlow = function() {
    if (window._lectureGlowInterval) {
        clearInterval(window._lectureGlowInterval);
        window._lectureGlowInterval = null;
    }
    const el = document.querySelector('.start-hero');
    if (el) {
        el.classList.remove('glow-card');
        el.style.removeProperty('--glow-x');
        el.style.removeProperty('--glow-y');
        el.style.removeProperty('--glow-blur');
        el.style.removeProperty('--glow-spread');
        el.style.removeProperty('--glow-color');
    }
};

// On DOMContentLoaded — render preset cards
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('email-presets-container')) {
        renderEmailPresetsCard();
        renderModalPresetSelect();
    }
});


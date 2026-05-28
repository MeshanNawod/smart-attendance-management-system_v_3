<?php
// admin_device_password_card.php
?>
<div class="card card-clean shadow-none mb-4 border-0">
    <div class="card-header bg-dark text-white py-3">
        <strong><i class="bi bi-lock-fill me-2"></i>Device Password (SYSTEM_PIN)</strong>
    </div>
    <div class="card-body bg-light">
        <p class="small text-muted mb-2">Change the ESP32 keypad lock PIN. This PIN is used to unlock the physical device.</p>
        <div class="input-group mb-2" style="max-width:400px;">
            <span class="input-group-text bg-dark text-white"><i class="bi bi-key"></i></span>
            <input type="password" id="device-system-pin" class="form-control" placeholder="Enter new PIN (4-10 digits)" maxlength="10" inputmode="numeric" pattern="[0-9]*">
            <button class="btn btn-outline-dark" onclick="togglePinVisibility()"><i class="bi bi-eye" id="pin-eye-icon"></i></button>
        </div>
        <div class="input-group mb-2" style="max-width:400px;">
            <span class="input-group-text bg-dark text-white"><i class="bi bi-cpu"></i></span>
            <select id="pin-device-select" class="form-select">
                <option value="">Select device...</option>
            </select>
            <button class="btn btn-dark fw-semibold" onclick="saveAndSendDevicePin()"><i class="bi bi-save me-1"></i>Save &amp; Send to Device</button>
        </div>
        <span id="pin-save-msg" class="small ms-2"></span>
    </div>
</div>

<script>
(function() {
    const stuSel = document.getElementById('students-device-select');
    const pinSel = document.getElementById('pin-device-select');
    if (stuSel && pinSel) {
        function sync() {
            pinSel.innerHTML = stuSel.innerHTML;
            pinSel.value = stuSel.value;
        }
        sync();
        stuSel.addEventListener('change', sync);
    }
})();
window.togglePinVisibility = function() {
    const inp = document.getElementById('device-system-pin');
    const icon = document.getElementById('pin-eye-icon');
    inp.type = inp.type === 'password' ? 'text' : 'password';
    icon.className = inp.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
};
window.saveAndSendDevicePin = function() {
    const pin = document.getElementById('device-system-pin').value.trim();
    const sel = document.getElementById('pin-device-select');
    const ip = sel ? sel.value : '';
    if (!pin || pin.length < 4 || pin.length > 10 || !/^\d+$/.test(pin)) {
        showToast('PIN must be 4-10 digits.', 'warning');
        return;
    }
    if (!ip || ip === '' || ip === 'WEB_DASHBOARD') {
        showToast('Please select a valid ESP32 device.', 'warning');
        return;
    }
    const msg = document.getElementById('pin-save-msg');
    msg.textContent = 'Saving...';
    msg.className = 'small text-info ms-2';
    const fd = new FormData();
    fd.append('system_pin', pin);
    fetch('/csc2052/api/device_password.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(d => {
            if (d.status !== 'success') {
                msg.textContent = 'DB error: ' + d.message;
                msg.className = 'small text-danger ms-2';
                return;
            }
            msg.textContent = 'Sending to device...';
            msg.className = 'small text-info ms-2';
            return fetch('/csc2052/api/esp_ota_proxy.php?ip=' + encodeURIComponent(ip) + '&command=' + encodeURIComponent('SETCONFIG SYSTEM_PIN=' + pin), { signal: AbortSignal.timeout(10000) });
        })
        .then(r => r ? r.text() : null)
        .then(t => {
            if (t !== null) {
                msg.textContent = 'PIN saved & sent to device.';
                msg.className = 'small text-success ms-2';
                showToast('Device PIN updated: ' + t, 'success');
            }
            setTimeout(() => msg.textContent = '', 4000);
        })
        .catch(e => {
            msg.textContent = 'Operation failed: ' + e.message;
            msg.className = 'small text-danger ms-2';
            showToast('Failed to update device PIN.', 'warning');
        });
};
</script>

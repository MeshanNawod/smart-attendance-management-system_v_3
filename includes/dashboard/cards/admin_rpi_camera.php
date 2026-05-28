<?php
// admin_rpi_camera.php
?>
<div class="card border-0 mb-3 overflow-hidden" style="border-radius:12px;box-shadow:0 4px 16px rgba(30,41,59,0.10);">
    <div class="card-header py-2 px-3 d-flex align-items-center justify-content-between"
         style="background:linear-gradient(135deg,#1e293b,#2d3a4a);border-bottom:none;">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-camera-reels text-white"></i>
            <strong class="small fw-bold text-white">Raspberry Pi Camera</strong>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span id="rpi-status-dot" class="badge"
                  style="background:rgba(255,255,255,0.15);color:#fff;font-size:0.65rem;border-radius:20px;">
                <i class="bi bi-circle me-1"></i>Offline
            </span>
            <button class="btn btn-xs rounded py-0 px-2"
                    style="background:rgba(255,255,255,0.12);color:#fff;border:1px solid rgba(255,255,255,0.2);font-size:0.7rem;"
                    onclick="refreshRpiStatus()" title="Refresh">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
            <button class="btn btn-xs rounded py-0 px-2"
                    style="background:rgba(255,255,255,0.12);color:#fff;border:1px solid rgba(255,255,255,0.2);font-size:0.7rem;"
                    onclick="toggleRpiPanel()" id="rpi-toggle-btn" title="Expand / Collapse">
                <i class="bi bi-chevron-down" id="rpi-toggle-icon"></i>
            </button>
        </div>
    </div>

    <div class="card-body py-2 px-3 bg-white">
        <small id="rpi-ip-display" class="text-muted d-block mb-2" style="font-size:0.72rem;">Not connected</small>

        <!-- Camera Feed -->
        <div id="rpi-face-container" class="rounded overflow-hidden mb-2" style="border:1px solid #e8ecf0;">
            <div class="position-relative bg-dark" style="aspect-ratio:4/3;line-height:0;">
                <img id="rpi-cam-stream" src="" alt="Pi Camera Feed" class="w-100"
                     style="display:none;object-fit:cover;height:100%;">
                <div id="rpi-cam-placeholder" class="d-flex flex-column align-items-center justify-content-center text-muted h-100"
                     style="position:absolute;top:0;left:0;width:100%;">
                    <i class="bi bi-camera-reels" style="font-size:2.5rem;color:#64748b;"></i>
                    <p class="small mt-1 mb-0" style="color:#94a3b8;">Pi Camera Offline</p>
                </div>
                <canvas id="rpi-attendance-overlay"
                        style="position:absolute;top:0;left:0;pointer-events:none;width:100%;height:100%;"></canvas>
            </div>
        </div>

        <!-- Status + Toggles -->
        <div id="rpi-attendance-status" class="small fw-bold mb-2" style="min-height:20px;color:#a11e17;"></div>
        <div class="d-flex justify-content-center gap-4 mb-2">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="rpi-attendance-auto">
                <label class="form-check-label small fw-bold text-muted" for="rpi-attendance-auto">
                    <i class="bi bi-lightning-charge me-1"></i>Auto
                </label>
            </div>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="rpi-attendance-mute" checked>
                <label class="form-check-label small fw-bold text-muted" for="rpi-attendance-mute">
                    <i class="bi bi-volume-mute me-1"></i>Mute
                </label>
            </div>
        </div>

        <!-- Controls -->
        <div class="d-flex gap-2 mb-3">
            <button id="btn-start-rpi-attendance" class="btn btn-sm w-100 fw-bold"
                    onclick="startRpiAttendance()"
                    style="background:linear-gradient(135deg,#1e293b,#2d3a4a);color:#fff;border:none;border-radius:8px;">
                <i class="bi bi-play-circle me-1"></i>Start Pi
            </button>
            <button id="btn-stop-rpi-attendance" class="btn btn-sm w-100 fw-bold d-none"
                    onclick="stopRpiAttendance()"
                    style="background:linear-gradient(135deg,#dc3545,#b02a37);color:#fff;border:none;border-radius:8px;">
                <i class="bi bi-stop-circle me-1"></i>Stop
            </button>
        </div>

        <!-- Pi Config -->
        <div class="p-2 rounded-3 mb-0" style="background:#f8fafc;border:1px solid #e8ecf0;">
            <div class="fw-bold mb-2" style="font-size:0.7rem;color:#64748b;text-transform:uppercase;letter-spacing:0.8px;">
                <i class="bi bi-terminal me-1" style="color:#a11e17;"></i>Pi Configuration
            </div>
            <div class="input-group input-group-sm mb-2">
                <span class="input-group-text bg-white" style="border-color:#e0e8f0;">
                    <i class="bi bi-hdd-network" style="color:#a11e17;"></i>
                </span>
                <input type="text" id="rpi-node-ip" class="form-control"
                       style="border-color:#e0e8f0;font-size:0.82rem;"
                       placeholder="Pi IP (e.g. 192.168.1.100)">
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-sm w-100 fw-semibold"
                        onclick="saveRpiConfig()"
                        style="border:1.5px solid #1e293b;color:#1e293b;background:transparent;border-radius:8px;font-size:0.78rem;">
                    <i class="bi bi-save me-1"></i>Save
                </button>
                <button class="btn btn-sm w-100 fw-semibold"
                        onclick="triggerRpiOta()"
                        style="border:1.5px solid #a11e17;color:#a11e17;background:transparent;border-radius:8px;font-size:0.78rem;">
                    <i class="bi bi-cloud-arrow-down me-1"></i>Pull Queue
                </button>
            </div>
        </div>
    </div>
</div>

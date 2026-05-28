<?php
// Ensure variables are available
$total_students    = $total_students    ?? 0;
$today_attendance  = $today_attendance  ?? 0;
$discovered_devices = $discovered_devices ?? [];
?>
<div class="row mb-4 mt-2 g-3 align-items-stretch">

    <!-- ─── Card 1: Total Students (Maroon) ─── -->
    <div class="col-md-4">
        <div class="stat-card stat-card-maroon h-100 p-4 d-flex align-items-center gap-3">
            <div class="stat-icon-circle" style="background:rgba(255,255,255,0.15);">
                <i class="bi bi-people-fill" style="font-size:1.6rem;color:#fcd40d;"></i>
            </div>
            <div class="flex-grow-1 min-width-0">
                <div class="stat-label" style="color:#64748b;" data-i18n="total_enrolled">Total Enrolled</div>
                <div class="stat-value" style="color:#a11e17;"><?php echo $total_students; ?></div>
                <div class="stat-sub" style="color:#64748b;" data-i18n="registered_students">Registered students</div>
            </div>
            <div class="stat-blob"></div>
        </div>
    </div>

    <!-- ─── Card 2: Live Clock (White) ─── -->
    <div class="col-md-4">
        <div class="stat-card stat-card-white h-100 p-4 d-flex align-items-center gap-3">
            <div class="stat-icon-circle" style="background:rgba(252,212,13,0.12);border:1.5px solid rgba(252,212,13,0.3);">
                <i class="bi bi-clock-fill" style="font-size:1.6rem;color:#fcd40d;"></i>
            </div>
            <div class="flex-grow-1 min-width-0">
                <div class="stat-label" style="color:#a11e17;" data-i18n="system_time">System Time</div>
                <div class="stat-value" id="live-clock" style="color:#1e293b;font-size:1.85rem;">--:--:--</div>
                <small class="d-block fw-semibold text-uppercase mt-1" id="live-date"
                       style="font-size:0.6rem;color:#64748b;letter-spacing:0.8px;">Loading...</small>
            </div>
        </div>
    </div>

    <!-- ─── Card 3: Today Attendance (Gold) ─── -->
    <div class="col-md-4">
        <div class="stat-card stat-card-gold h-100 p-4 d-flex align-items-center gap-3 position-relative">

            <!-- Device filter (top-right) -->
            <div class="position-absolute top-0 end-0 mt-2 me-2">
                <select id="stat-device-select"
                        class="form-select form-select-sm fw-bold"
                        style="font-size:0.65rem;padding:0.15rem 0.5rem 0.15rem 0.4rem;border-radius:7px;border:1px solid rgba(0,0,0,0.12);background:rgba(255,255,255,0.75);color:#1e293b;min-width:110px;cursor:pointer;">
                    <option value="" class="text-dark">All Devices</option>
                    <option value="WEB_DASHBOARD" class="text-dark">Web Dashboard</option>
                    <?php foreach ($discovered_devices as $dev): ?>
                    <option value="<?php echo htmlspecialchars($dev['name']); ?>" class="text-dark">
                        <?php echo htmlspecialchars($dev['name']); ?><?php echo $dev['online'] ? '' : ' (Offline)'; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="stat-icon-circle" style="background:rgba(0,0,0,0.08);">
                <i class="bi bi-person-check-fill" style="font-size:1.6rem;color:#1e293b;"></i>
            </div>
            <div class="flex-grow-1 min-width-0">
                <div class="stat-label" style="color:rgba(0,0,0,0.5);" data-i18n="today_attendance">Today's Attendance</div>
                <div class="stat-value" id="today-attendance-count" style="color:#1e293b;"><?php echo $today_attendance; ?></div>
                <div class="stat-sub" style="color:rgba(0,0,0,0.4);" data-i18n="checkins_today">Check-ins today</div>
            </div>
            <div class="stat-blob" style="background:#1e293b;"></div>
        </div>
    </div>

</div>

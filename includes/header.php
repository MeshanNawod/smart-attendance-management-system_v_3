<?php
require_once 'auth.php';
require_once 'db.php';

// Role locking
if (isset($_SESSION['role_locked'])) {
    $checkFn = 'is' . ucfirst($_SESSION['role_locked']);
    if (function_exists($checkFn) && !$checkFn()) {
        session_unset(); session_destroy();
        header('Location: /csc2052/login.php?reason=hijack'); exit;
    }
}
$role = $_SESSION['user_role'] ?? '';
if (in_array($role, ['admin','teacher','student']) && !isset($_SESSION['role_locked'])) {
    $_SESSION['role_locked'] = $role;
}

$is_esp = false; $is_rpi = false;
$status_file_esp = __DIR__ . '/../sys_status_esp32.txt';
$status_file_rpi = __DIR__ . '/../sys_status_rpi.txt';
if (file_exists($status_file_esp)) {
    $last_ping = (int)file_get_contents($status_file_esp);
    if ((time() - $last_ping) <= 30) $is_esp = true;
}
if (file_exists($status_file_rpi)) {
    $last_ping = (int)file_get_contents($status_file_rpi);
    if ((time() - $last_ping) <= 30) $is_rpi = true;
}

$page = basename($_SERVER['PHP_SELF']);
$showHardware = isAdmin();
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UoP AMS | Sentinel Swarm</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo htmlspecialchars(asset_url('assets/favicon.svg'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('css/vendor/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('css/vendor/bootstrap.min.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('css/vendor/bootstrap-icons.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('css/style.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('css/tables.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('css/cards.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <script src="<?php echo htmlspecialchars(asset_url('js/vendor/chart.umd.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</head>
<body>

<div class="floating-navbar-wrapper">
    <nav class="navbar navbar-expand-lg premium-navbar">
        <div class="container-fluid px-3">
            <a class="navbar-brand mb-0 h1 d-flex align-items-center gap-2 text-decoration-none" href="<?php echo $role === 'admin' ? 'index.php' : ($role === 'teacher' ? 'teacher_dashboard.php' : 'student_dashboard.php'); ?>">
                <div class="nav-brand-box">
                    <i class="bi <?php echo $role === 'student' ? 'bi-mortarboard-fill' : 'bi-shield-lock-fill'; ?>"></i>
                </div>
                <div class="d-flex flex-column lh-1">
                    <span class="nav-brand-title" data-i18n="sentinel_swarm">Sentinel Swarm</span>
                    <span class="nav-brand-sub" data-i18n="brand_sub">AMS &middot; University of Peradeniya</span>
                </div>
            </a>
            <button class="navbar-toggler border-0 shadow-none px-2" type="button" data-bs-toggle="collapse" data-bs-target="#navBar">
                <i class="bi bi-list fs-2 navbar-toggler-icon-custom"></i>
            </button>
            <div class="collapse navbar-collapse" id="navBar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
                    <?php if (isAdmin()): ?>
                    <li class="nav-item"><a class="nav-link nav-link-premium <?php echo $page=='index.php'?'active':''; ?>" href="index.php"><i class="bi bi-display me-1"></i><span data-i18n="dashboard">Dashboard</span></a></li>
                    <li class="nav-item"><a class="nav-link nav-link-premium <?php echo $page=='students.php'?'active':''; ?>" href="students.php"><i class="bi bi-people me-1"></i><span data-i18n="students_base">Students Base</span></a></li>
                    <li class="nav-item"><a class="nav-link nav-link-premium <?php echo $page=='teachers.php'?'active':''; ?>" href="teachers.php"><i class="bi bi-person-workspace me-1"></i><span data-i18n="teachers">Teachers</span></a></li>
                    <li class="nav-item"><a class="nav-link nav-link-premium <?php echo $page=='lecture.php'?'active':''; ?>" href="lecture.php"><i class="bi bi-journal-album me-1"></i><span data-i18n="lectures_export">Lectures & Export</span></a></li>
                    <li class="nav-item"><a class="nav-link nav-link-premium <?php echo $page=='device.php'?'active':''; ?>" href="device.php"><i class="bi bi-motherboard me-1"></i><span data-i18n="hardware_nodes">Hardware Nodes</span></a></li>
                    <?php elseif (isTeacher()): ?>
                    <li class="nav-item"><a class="nav-link nav-link-premium <?php echo $page=='teacher_dashboard.php'?'active':''; ?>" href="teacher_dashboard.php"><i class="bi bi-display me-1"></i><span data-i18n="dashboard">Dashboard</span></a></li>
                    <?php elseif (isStudent()): ?>
                    <li class="nav-item"><a class="nav-link nav-link-premium <?php echo $page=='student_dashboard.php'?'active':''; ?>" href="student_dashboard.php"><i class="bi bi-display me-1"></i><span data-i18n="dashboard">Dashboard</span></a></li>
                    <?php endif; ?>
                    <li class="nav-item ms-lg-2"><a class="nav-link nav-link-premium <?php echo $page=='instructions.php'?'active':''; ?>" href="instructions.php"><i class="bi bi-info-circle me-1"></i><span data-i18n="help">Help</span></a></li>
                </ul>
                <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2 mt-3 mt-lg-0">
                    <div id="lang-switcher-header"></div>
                    <?php if ($showHardware): ?>
                    <div class="input-group node-ip-group">
                        <span class="input-group-text bg-transparent border-0 text-secondary pe-1"><i class="bi bi-hdd-network"></i></span>
                        <input type="text" id="esp-ip" class="node-ip-input" data-i18n-placeholder="node_ip" placeholder="Node IP">
                    </div>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <div class="hw-pill <?php echo $is_esp?'hw-online':'hw-offline'; ?>">
                            <span class="hw-dot <?php echo $is_esp?'hw-dot-on':''; ?>"></span>
                            <span data-i18n="<?php echo $is_esp?'esp32_online':'esp32_offline'; ?>"><?php echo $is_esp?'ESP32 ONLINE':'ESP32 OFFLINE'; ?></span>
                        </div>
                        <div class="hw-pill <?php echo $is_rpi?'hw-online':'hw-offline'; ?>">
                            <span class="hw-dot <?php echo $is_rpi?'hw-dot-on':''; ?>"></span>
                            <span data-i18n="<?php echo $is_rpi?'rpi_online':'rpi_offline'; ?>"><?php echo $is_rpi?'RPi ONLINE':'RPi OFFLINE'; ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <a href="logout.php" class="btn btn-sm btn-danger px-3 py-2 fw-bold shadow-sm btn-hover-lift btn-hover-ripple text-uppercase">
                        <i class="bi bi-box-arrow-right me-1"></i><span data-i18n="exit">Exit</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>
</div>
<div class="container pb-5 mt-3">
<meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token']??'', ENT_QUOTES, 'UTF-8'); ?>">

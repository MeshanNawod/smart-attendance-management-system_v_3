<?php
/**
 * Student Header — Sentinel Swarm AMS v3
 * Role-isolated navigation for student pages only.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

if (isset($_SESSION['role_locked']) && $_SESSION['role_locked'] === 'student' && !isStudent()) {
    session_unset(); session_destroy();
    header('Location: /csc2052/login.php?reason=hijack'); exit;
}
if (isStudent() && !isset($_SESSION['role_locked'])) {
    $_SESSION['role_locked'] = 'student';
}

$page = basename($_SERVER['PHP_SELF']);
?><div class="floating-navbar-wrapper">
    <nav class="navbar navbar-expand-lg premium-navbar">
        <div class="container-fluid px-3">
            <a class="navbar-brand mb-0 h1 d-flex align-items-center gap-2 text-decoration-none" href="student_dashboard.php">
                <div class="nav-brand-box">
                    <i class="bi bi-mortarboard-fill"></i>
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
                    <li class="nav-item"><a class="nav-link nav-link-premium <?php echo $page=='student_dashboard.php'?'active':''; ?>" href="student_dashboard.php"><i class="bi bi-display me-1"></i><span data-i18n="dashboard">Dashboard</span></a></li>
                </ul>
                <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2 mt-3 mt-lg-0">
                    <div id="lang-switcher-header"></div>
                    <span class="text-muted small"><i class="bi bi-person-badge me-1"></i><?php echo htmlspecialchars($_SESSION['username']??'Student'); ?></span>
                    <a href="logout.php" class="btn btn-sm btn-danger px-3 py-2 fw-bold shadow-sm btn-hover-lift btn-hover-ripple text-uppercase">
                        <i class="bi bi-box-arrow-right me-1"></i><span data-i18n="exit">Exit</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>
</div>
<div class="container pb-5 mt-3">

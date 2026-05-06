<?php
/**
 * Sentinel Swarm AMS — Central Configuration
 * 
 * This file stores all shared secrets and application constants.
 * It is required by endpoints that need token-based authentication
 * for hardware devices (ESP32, RPi).
 * 
 * IMPORTANT: Keep this file outside the web root in a production environment.
 */

// ─────────────────────────────────────────────
// APPLICATION SETTINGS
// ─────────────────────────────────────────────
define('APP_NAME', 'Sentinel Swarm AMS');
define('APP_VERSION', '3.0.0');

// ─────────────────────────────────────────────
// ROLE CONSTANTS
// ─────────────────────────────────────────────
define('ROLE_ADMIN', 'admin');
define('ROLE_TEACHER', 'teacher');
define('ROLE_STUDENT', 'student');

// ─────────────────────────────────────────────
// SESSION CONFIGURATION
// ─────────────────────────────────────────────
define('SESSION_LIFETIME', 3600);       // 1 hour in seconds
define('SESSION_COOKIE_SECURE', false); // Set to true if using HTTPS
define('STUDENT_DEFAULT_PASSWORD_PREFIX', true); // Student default password = student_no

// ─────────────────────────────────────────────
// FALLBACK ADMIN CREDENTIALS (DB-first, config fallback)
// Default: admin / admin123
// To change, run: php -r "echo password_hash('your_new_password', PASSWORD_BCRYPT);"
// and replace the hash below. Used only if users table not yet migrated.
// ─────────────────────────────────────────────
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', '$2y$10$Ojnr0litp0LS8GroYINH4uci0DBEMYuKknw4vatxuYqVDSPvuuIbG'); // Default: admin123

// ─────────────────────────────────────────────
// HARDWARE AUTHENTICATION SECRETS
// ─────────────────────────────────────────────
// CHANGE THESE in production! Generate new ones with:
// php -r "echo bin2hex(random_bytes(16));"
define('HEARTBEAT_SECRET', 'ss_heartbeat_key_2052');
define('HARDWARE_API_KEY', 'ss_hw_api_key_2052');

// ─────────────────────────────────────────────
// LOGIN RATE LIMITING
// ─────────────────────────────────────────────
define('MAX_LOGIN_ATTEMPTS', 5);       // Max failed attempts before lockout
define('LOGIN_LOCKOUT_SECONDS', 15);   // 15 second lockout duration

// ─────────────────────────────────────────────
// EMAIL NOTIFICATION SETTINGS
// ─────────────────────────────────────────────
// Configure these to enable email reports for absent students.
// Uses PHP's mail() function (XAMPP sendmail) by default.
define('EMAIL_ENABLED', true);                      // Set to false to disable email features
define('EMAIL_FROM', 'sentinel-ams@localhost');    // Sender address
define('EMAIL_FROM_NAME', 'Sentinel AMS');          // Sender display name
define('EMAIL_ADMIN_RECIPIENT', 'admin@localhost'); // Default recipient for absent reports

if (!function_exists('asset_url')) {
    function asset_url(string $path): string {
        $publicPath = ltrim($path, '/');
        $filePath = __DIR__ . '/../' . $publicPath;
        $version = is_file($filePath) ? (string) filemtime($filePath) : APP_VERSION;

        return $publicPath . '?v=' . rawurlencode($version);
    }
}

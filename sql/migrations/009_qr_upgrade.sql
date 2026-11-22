-- ─────────────────────────────────────────────────────────────────────────
-- QR Attendance upgrade — Sentinel Swarm AMS
-- Optional: api/attendance/qr/record.php also applies these on first use.
--
--   • QR tokens belong to the running lecture session (session_id)
--   • One phone can complete QR attendance for one student per lecture
--     (device_hash = SHA-256 of an anonymous per-browser ID)
--   • Suspicious attempts are logged in qr_flags for the lecturer
-- ─────────────────────────────────────────────────────────────────────────

ALTER TABLE qr_tokens
    ADD COLUMN session_id INT NULL AFTER teacher_id;

ALTER TABLE qr_scan_attempts
    ADD COLUMN session_id  INT      NULL AFTER course_code,
    ADD COLUMN device_hash CHAR(64) NULL DEFAULT NULL,
    ADD INDEX idx_session_student (session_id, student_no),
    ADD INDEX idx_session_device  (session_id, device_hash);

CREATE TABLE IF NOT EXISTS qr_flags (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    session_id  INT          NULL,
    course_code VARCHAR(50)  NOT NULL,
    student_no  VARCHAR(50)  NOT NULL,
    reason      VARCHAR(40)  NOT NULL COMMENT 'e.g. shared_device',
    detail      VARCHAR(255) NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Suspicious QR attempts shown to the lecturer';

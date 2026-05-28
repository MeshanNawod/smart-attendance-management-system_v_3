-- ─────────────────────────────────────────────────────────────────────────
-- QR Attendance Support — Sentinel Swarm AMS
-- Run this once to enable QR-based attendance with anti-proxy protection.
--
-- Anti-proxy mechanism:
--   • QR token rotates every 5 seconds (slot = floor(UNIX_TIMESTAMP / 5))
--   • Student must scan 2 *consecutive* QR tokens (slot N then N+1)
--   • Sharing a photo fails: the friend needs the *next* token which only
--     exists on the live projector screen.
-- ─────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS qr_tokens (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(50)  NOT NULL,
    teacher_id  INT          NOT NULL,
    token       VARCHAR(64)  NOT NULL,
    slot        BIGINT       NOT NULL COMMENT 'floor(UNIX_TIMESTAMP / 5) — changes every 5 s',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY  uk_course_slot (course_code, slot),
    INDEX       idx_token      (token),
    INDEX       idx_course     (course_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Rotating QR tokens for attendance — one token per (course, 5-second window)';

CREATE TABLE IF NOT EXISTS qr_scan_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    student_no   VARCHAR(50)  NOT NULL,
    course_code  VARCHAR(50)  NOT NULL,
    token_1      VARCHAR(64)  NOT NULL                 COMMENT 'First QR token scanned',
    slot_1       BIGINT       NOT NULL                 COMMENT 'Time-slot of first scan',
    scanned_at_1 DATETIME     NOT NULL,
    token_2      VARCHAR(64)  NULL DEFAULT NULL        COMMENT 'Second QR token scanned (consecutive)',
    slot_2       BIGINT       NULL DEFAULT NULL,
    scanned_at_2 DATETIME     NULL DEFAULT NULL,
    completed    TINYINT(1)   NOT NULL DEFAULT 0       COMMENT '1 = both scans done, attendance recorded',
    INDEX        idx_student_course (student_no, course_code),
    INDEX        idx_token1         (token_1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Tracks 2-scan QR verification per student per session';

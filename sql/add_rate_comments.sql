-- Add rating and comment columns to attendance_logs for per-session feedback
ALTER TABLE attendance_logs
  ADD COLUMN IF NOT EXISTS rating TINYINT(1) DEFAULT NULL COMMENT 'Student rating 1-5',
  ADD COLUMN IF NOT EXISTS comment TEXT DEFAULT NULL COMMENT 'Student comment on session';

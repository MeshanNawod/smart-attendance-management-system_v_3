-- ═══════════════════════════════════════════════════════════════
-- Sentinel Swarm AMS — Ratings & Forum Comments Migration
-- Run this in phpMyAdmin or MySQL CLI against the `csc2052` DB
-- ═══════════════════════════════════════════════════════════════

-- 1. Ratings table (stars 1–5 for teacher, course, or session)
CREATE TABLE IF NOT EXISTS ratings (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT         NOT NULL,          -- FK → users.id
    user_role     VARCHAR(20) NOT NULL,           -- 'student' | 'teacher' | 'admin'
    target_type   ENUM('teacher','course','session') NOT NULL,
    target_id     VARCHAR(50) NOT NULL,           -- teacher.id | course_code | session_id
    stars         TINYINT(1)  NOT NULL CHECK (stars BETWEEN 1 AND 5),
    created_at    TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rating (user_id, target_type, target_id)   -- one rating per user per target
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Forum comments table (threaded: parent_id = NULL for top-level)
CREATE TABLE IF NOT EXISTS forum_comments (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT          NOT NULL,
    user_role     VARCHAR(20)  NOT NULL,
    target_type   ENUM('teacher','course','session') NOT NULL,
    target_id     VARCHAR(50)  NOT NULL,
    parent_id     INT          NULL DEFAULT NULL,  -- NULL = top-level; set to another id for reply
    body          TEXT         NOT NULL,
    is_pinned     TINYINT(1)   DEFAULT 0,
    is_deleted    TINYINT(1)   DEFAULT 0,           -- soft-delete
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES forum_comments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Comment likes (thumbs-up per user per comment)
CREATE TABLE IF NOT EXISTS comment_likes (
    user_id    INT NOT NULL,
    comment_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, comment_id),
    FOREIGN KEY (comment_id) REFERENCES forum_comments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Index for fast lookups
CREATE INDEX IF NOT EXISTS idx_ratings_target   ON ratings        (target_type, target_id);
CREATE INDEX IF NOT EXISTS idx_comments_target  ON forum_comments (target_type, target_id, parent_id);
CREATE INDEX IF NOT EXISTS idx_comments_user    ON forum_comments (user_id);

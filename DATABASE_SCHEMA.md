# Sentinel Swarm AMS — Database Schema

```mermaid
erDiagram
    users {
        int id PK
        varchar_50 username UK
        varchar_255 password_hash
        enum role "admin | teacher | student"
        varchar_100 email
        varchar_100 full_name
        tinyint must_change_password
        tinyint is_active
        timestamp last_login
        timestamp created_at
    }

    teachers {
        int id PK
        int user_id FK
        varchar_100 teacher_name
        varchar_100 department
        varchar_20 phone
        timestamp created_at
    }

    students {
        varchar_50 student_no PK
        int user_id FK "nullable"
        varchar_100 student_name
        int fingerprint_id
        varchar_50 rfid_uid
        varchar_100 face_id
        text face_descriptor "JSON"
        text voice_sample
        text fp_template_hex
        text profile_photo
        tinyint must_change_password
        timestamp created_at
    }

    admins {
        int id PK
        varchar_100 admin_name
        int fingerprint_id
        varchar_50 rfid_uid
        varchar_100 face_id
        text face_descriptor
    }

    courses {
        varchar_50 course_code PK
        varchar_100 course_name
    }

    teacher_courses {
        int id PK
        int teacher_id FK
        varchar_50 course_code FK
        timestamp assigned_at
    }

    student_courses {
        varchar_50 student_no FK
        varchar_50 course_code FK
    }

    course_schedules {
        int id PK
        varchar_50 course_code
        tinyint day_of_week "1=Mon..6=Sat"
        time start_time
        time end_time
        varchar_100 venue
        varchar_50 device_id "default WEB_DASHBOARD"
        tinyint auto_start "default 1"
        tinyint email_threshold "default 80"
        tinyint email_on_end "default 1"
        timestamp created_at
    }

    lecture_sessions {
        int id PK
        varchar_50 course_code
        int teacher_id "nullable"
        varchar_50 started_by
        datetime started_at
        datetime ended_at "nullable"
        varchar_100 device_name "default WEB_DASHBOARD"
        tinyint is_active "default 1"
    }

    attendance_logs {
        int id PK
        int session_id FK
        int scan_count "default 1"
        varchar_50 student_no
        varchar_50 course_code
        datetime timestamp
        varchar_100 device_name
        varchar_20 modality "rfid|fingerprint|face|qr|voice|manual"
        tinyint is_offline_sync
        tinyint rating "1-5, nullable"
        text comment "nullable"
    }

    qr_tokens {
        int id PK
        varchar_50 course_code
        int teacher_id
        varchar_64 token
        bigint slot "floor(UNIX/5)"
        datetime created_at
    }

    qr_scan_attempts {
        int id PK
        varchar_50 student_no
        varchar_50 course_code
        varchar_64 token_1
        bigint slot_1
        datetime scanned_at_1
        varchar_64 token_2 "nullable"
        bigint slot_2 "nullable"
        datetime scanned_at_2 "nullable"
        tinyint completed "default 0"
    }

    ratings {
        int id PK
        int user_id
        varchar_20 user_role
        enum target_type "teacher|course|session"
        varchar_50 target_id
        tinyint stars "1-5"
        timestamp created_at
        timestamp updated_at
    }

    forum_comments {
        int id PK
        int user_id
        varchar_20 user_role
        enum target_type "teacher|course|session"
        varchar_50 target_id
        int parent_id FK "self-ref, nullable"
        text body
        tinyint is_pinned
        tinyint is_deleted "soft-delete"
        timestamp created_at
        timestamp updated_at
    }

    comment_likes {
        int user_id PK
        int comment_id PK FK
        timestamp created_at
    }

    system_logs {
        int id PK
        enum log_type "info|warning|error|power|debug"
        varchar_64 source
        text message
        text details "nullable"
        datetime created_at
    }

    voice_settings {
        int id PK
        decimal rate "default 1.0"
        decimal pitch "default 1.0"
        varchar_100 voice_name "nullable"
        tinyint announce_student_name "default 1"
        tinyint announce_student_no "default 0"
        tinyint announce_modality "default 1"
        tinyint announce_course "default 0"
        tinyint announce_timestamp "default 0"
        tinyint announce_unknown_card "default 1"
        varchar_255 logs_columns
        timestamp updated_at
    }

    device_password {
        int id PK
        varchar_50 device_id UK
        varchar_10 system_pin "default 123456"
        timestamp updated_at
    }

    email_sent_logs {
        int id PK
        varchar_255 sender_email
        varchar_255 recipient_email
        varchar_255 subject
        varchar_50 message_type "absent_report|attendance_alert|custom"
        varchar_50 course_code
        varchar_50 student_no "nullable"
        varchar_100 student_name "nullable"
        text body "HTML"
        timestamp sent_at
        varchar_50 status
    }

    users ||--o| teachers : "user_id"
    users ||--o| students : "user_id"
    teachers ||--o{ teacher_courses : "teacher_id"
    courses ||--o{ teacher_courses : "course_code"
    courses ||--o{ student_courses : "course_code"
    students ||--o{ student_courses : "student_no"
    courses ||--o{ course_schedules : "course_code"
    courses ||--o{ lecture_sessions : "course_code"
    courses ||--o{ attendance_logs : "course_code"
    courses ||--o{ qr_tokens : "course_code"
    lecture_sessions ||--o{ attendance_logs : "session_id"
    forum_comments ||--o{ forum_comments : "parent_id (self-ref)"
    forum_comments ||--o{ comment_likes : "comment_id"
```

## Table Details

### `users` — Unified authentication
- **PK:** `id` (INT AUTO_INCREMENT)
- **Key:** `username` UNIQUE
- **Indexes:** `idx_username`, `idx_role`
- Seeded with default admin: `admin / admin123` (bcrypt)

### `teachers` — Teacher profiles
- **PK:** `id` (INT AUTO_INCREMENT)
- **FK:** `user_id` → `users.id` (ON DELETE CASCADE, UNIQUE — one user per teacher)

### `students` — Student records
- **PK:** `student_no` (VARCHAR(50))
- **FK:** `user_id` → `users.id` (ON DELETE SET NULL, nullable)
- Biometric fields: `fingerprint_id` (1–127), `rfid_uid`, `face_id`, `face_descriptor` (JSON), `voice_sample`, `fp_template_hex`

### `admins` — Hardware admin credentials
- **PK:** `id` (INT AUTO_INCREMENT)
- Standalone table for ESP32/RPi login without `users` entry

### `courses` — Course catalog
- **PK:** `course_code` (VARCHAR(50))

### `teacher_courses` — Teacher→Course assignment (M:N)
- **PK:** `id` (INT AUTO_INCREMENT)
- **FK1:** `teacher_id` → `teachers.id` (CASCADE)
- **FK2:** `course_code` → `courses.course_code` (CASCADE)
- **UK:** `(teacher_id, course_code)`

### `student_courses` — Enrollment (M:N)
- **FK1:** `student_no` → `students.student_no`
- **FK2:** `course_code` → `courses.course_code`

### `course_schedules` — Timetable slots
- **PK:** `id` (INT AUTO_INCREMENT)
- **UK:** `(day_of_week, start_time, end_time)`
- **device_id:** Controls which ESP32 auto-starts this schedule
- **email_threshold:** Percentage below which auto-email alerts trigger

### `lecture_sessions` — Active lecture tracking
- **PK:** `id` (INT AUTO_INCREMENT)
- Auto-expired after 8 hours (`MAX_SESSION_DURATION_HOURS`)
- `is_active=1` marks running sessions

### `attendance_logs` — Attendance records
- **PK:** `id` (INT AUTO_INCREMENT)
- **FK:** `session_id` → `lecture_sessions.id`
- `modality` options: `rfid`, `fingerprint`, `face`, `qr`, `voice`, `manual`
- `scan_count` increments on re-scans (same student, same session)
- `rating` (1–5) and `comment` added by migration 003

### `qr_tokens` — Rotating QR tokens (5-second window)
- **PK:** `id` (INT AUTO_INCREMENT)
- **UK:** `(course_code, slot)`
- `slot = floor(UNIX_TIMESTAMP / 5)` — changes every 5 seconds

### `qr_scan_attempts` — Two-scan anti-proxy verification
- **PK:** `id` (INT AUTO_INCREMENT)
- Student must scan two consecutive tokens (slot N, then slot N+1)

### `ratings` — Star ratings (1–5)
- **PK:** `id` (INT AUTO_INCREMENT)
- **UK:** `(user_id, target_type, target_id)` — one rating per user per target
- Polymorphic: `target_type` = `teacher`, `course`, or `session`

### `forum_comments` — Threaded forum
- **PK:** `id` (INT AUTO_INCREMENT)
- **FK:** `parent_id` → `forum_comments.id` (self-referential, ON DELETE SET NULL)
- Polymorphic targeting via `target_type` + `target_id`
- Soft-delete via `is_deleted`

### `comment_likes` — Thumbs-up
- **PK:** `(user_id, comment_id)` composite
- **FK:** `comment_id` → `forum_comments.id` (CASCADE)

### `system_logs` — Unified audit log
- **PK:** `id` (INT UNSIGNED AUTO_INCREMENT)
- `log_type`: `info`, `warning`, `error`, `power`, `debug`
- Written by ESP32 and web backend

### `voice_settings` — TTS configuration
- **PK:** `id` (INT AUTO_INCREMENT)
- Singleton row (id=1), inserted via migration 007

### `device_password` — Hardware device PINs
- **PK:** `id` (INT AUTO_INCREMENT)
- **UK:** `device_id`
- Default PIN: `123456`

### `email_sent_logs` — Email audit trail
- **PK:** `id` (INT AUTO_INCREMENT)
- `message_type`: `absent_report`, `attendance_alert`, `custom`

## Connection Summary

```
users ──→ teachers       (1:1 via user_id FK)
users ──→ students       (1:1 via user_id FK, ON DELETE SET NULL)
teachers ──→ teacher_courses ──→ courses   (M:N)
students ──→ student_courses ──→ courses  (M:N)
courses ──→ lecture_sessions    (course_code)
courses ──→ course_schedules    (course_code)
lecture_sessions ──→ attendance_logs (session_id FK)
```

**Database:** `csc2052` — MySQL — `includes/database/connection.php`
**Connection:** PDO — `host=localhost, dbname=csc2052, user=root, pass=""`

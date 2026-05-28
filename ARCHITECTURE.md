# Sentinel Swarm AMS — Complete System Architecture

> **Version:** 3.0.0  
> **University of Peradeniya**  
> **Attendance Management System** with multi-modal biometric support, real-time hardware integration, and web-based administration.

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Directory Structure](#2-directory-structure)
3. [Data Flow](#3-data-flow)
4. [PHP Backend (Server)](#4-php-backend)
5. [JavaScript Frontend (Browser)](#5-javascript-frontend)
6. [Raspberry Pi Face Recognition Service](#6-raspberry-pi-face-recognition-service)
7. [ESP32 Firmware](#7-esp32-firmware)
8. [React SPA + Tauri Desktop App](#8-react-spa--tauri-desktop-app)
9. [Database Schema](#9-database-schema)
10. [Security & Authentication](#10-security--authentication)
11. [Configuration Files](#11-configuration-files)
12. [Communication Protocols](#12-communication-protocols)
13. [Attendance Modalities](#13-attendance-modalities)
14. [Key Flows](#14-key-flows)
15. [Troubleshooting Guide](#15-troubleshooting-guide)

---

## 1. System Overview

Sentinel Swarm is a **multi-modal attendance management system** that replaces manual roll-call with autonomous hardware nodes deployed at classroom entrances. It supports **four attendance modalities** — face recognition, RFID cards, QR codes, and voice verification — all converging into a centralized attendance database.

### Core Components

```
┌──────────────────────────────────────────────────────────────────────┐
│                           WEB BROWSER                                │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────────────┐    │
│  │ Admin    │  │ Teacher  │  │ Student  │  │ React SPA        │    │
│  │ Dashboard│  │ Dashboard│  │ Dashboard│  │ (Desktop/Mobile) │    │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘  └────────┬─────────┘    │
│       │              │              │                 │              │
└───────┼──────────────┼──────────────┼─────────────────┼──────────────┘
        │              │              │                 │
        ▼              ▼              ▼                 ▼
┌──────────────────────────────────────────────────────────────────────┐
│                        PHP SERVER (XAMPP)                            │
│  ┌────────────────────────────────────────────────────────────────┐  │
│  │  API Endpoints (35 files in api/)                              │  │
│  │  student.php attendance.php heartbeat.php teacher.php          │  │
│  │  face_scanned.php rfid_scanned.php qr_attendance.php          │  │
│  │  voice.php device_password.php esp_ota_proxy.php ...           │  │
│  └────────────────────────────────────────────────────────────────┘  │
│  ┌────────────────────────────────────────────────────────────────┐  │
│  │  Includes (auth, config, helpers, cards, database layer)       │  │
│  └────────────────────────────────────────────────────────────────┘  │
│  ┌────────────────────────────────────────────────────────────────┐  │
│  │  MySQL Database                                               │  │
│  │  students | courses | lecture_sessions | attendance_logs       │  │
│  │  users | teachers | ratings | forum_comments | voice_settings  │  │
│  └────────────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────┘
        ▲                            ▲
        │ HTTP POST (attendance)     │ HTTP GET (heartbeat, OTA poll)
        │                            │
┌───────┴──────────────┐  ┌─────────┴──────────────────┐
│ RASPBERRY PI (Flask)  │  │ ESP32 NODES (Arduino C++)  │
│ ─────────────────────  │  │ ────────────────────────  │
│ Face recognition      │  │ Fingerprint (FM10A)       │
│ PiCamera / USB camera │  │ RFID (MFRC522)            │
│ MJPEG video stream    │  │ 4x4 Keypad                │
│ Auto attendance POST  │  │ I2C LCD Display           │
│ Heartbeat every 30s   │  │ OTA firmware updates      │
│ OTA command polling   │  │ SD Card logging           │
│ ─────────────────────  │  │ Offline attendance queue  │
│ Python 3 + OpenCV     │  │ Heartbeat every 10s       │
│ face_recognition lib  │  │ ────────────────────────  │
│ Flask web server      │  │ Arduino ESP32             │
└───────────────────────┘  │ WebServer + WiFi          │
                           └──────────────────────────┘
```

---

## 2. Directory Structure

```
csc2052/
├── api/                          # PHP REST API endpoints (35 files)
│   ├── attendance.php            # Attendance router (dispatches by modality)
│   ├── attendance/               # Modality-specific attendance handlers
│   │   ├── face/record.php       # Face recognition attendance
│   │   ├── fingerprint/record.php# Fingerprint attendance
│   │   ├── manual/record.php     # Manual web dashboard attendance
│   │   ├── qr/record.php         # QR code attendance
│   │   ├── rfid/buffer.php       # RFID UID buffer from ESP32
│   │   ├── rfid/record.php       # RFID attendance recording
│   │   └── voice/record.php      # Voice attendance recording
│   │   └── voice/enroll.php      # Voice enrollment
│   ├── charts.php                # Chart data (daily trend, modality pie, etc.)
│   ├── device_password.php       # ESP32 device PIN management
│   ├── devices.php               # Device registry CRUD
│   ├── esp_courses.php           # Lightweight ESP32 course API
│   ├── esp_ota_proxy.php         # OTA firmware proxy to ESP32
│   ├── face_scanned.php          # Face scan router
│   ├── heartbeat.php             # ESP32/RPi heartbeat receiver
│   ├── logs.php                  # Attendance log viewer
│   ├── ota.php                   # OTA command queue
│   ├── qr_attendance.php         # QR attendance router
│   ├── ratings.php               # Ratings & forum comments
│   ├── rfid_scanned.php          # RFID scan router
│   ├── schedule.php              # Course schedule CRUD
│   ├── settings.php              # Hardware settings read/write
│   ├── sse_monitor.php           # Server-Sent Events for serial monitor
│   ├── student.php               # Student management (1771 lines)
│   ├── system_logs.php           # System-wide log management
│   ├── teacher.php               # Teacher management (929 lines)
│   ├── upload_faces.php          # Face photo upload
│   ├── voice.php                 # Voice attendance router
│   ├── voice_settings.php        # TTS voice configuration
│   ├── analytics.php             # Per-student analytics
│   └── ota.php                   # OTA command queue
│
├── assets/                       # Logos, icons
│   ├── favicon.svg               # 32x32 shield favicon
│   └── sentinel_swarm_logo.svg   # Full 512x512 logo
│
├── css/                          # Stylesheets
│   ├── style.css                 # Main CSS (1749 lines) — design system
│   ├── cards.css                 # Card-specific styles (308 lines)
│   ├── tables.css                # Legacy (consolidated into style.css)
│   └── vendor/                   # Bootstrap 5.3, Bootstrap Icons
│
├── frontend/                     # React SPA + Tauri desktop app
│   ├── src/                      # React source (Login, TeacherDashboard)
│   ├── src-tauri/                # Tauri v2 Rust desktop wrapper
│   └── public/                   # PWA manifest, service worker, icon
│
├── includes/                     # PHP includes (helpers, configs, cards)
│   ├── auth/session.php          # Authentication functions
│   ├── config/app.php            # App constants (roles, keys, limits)
│   ├── database/connection.php   # PDO MySQL connection
│   ├── helpers/general.php       # Utility functions
│   ├── attendance/helper.php     # Lecture session management
│   ├── cards/                    # Dashboard card components (15 cards)
│   └── header*.php               # Role-specific HTML headers
│
├── js/                           # JavaScript (frontend logic)
│   ├── main.js                   # Core dashboard (4294 lines)
│   ├── face_recognition.js       # Browser face recognition (1069 lines)
│   ├── ratings.js                # Ratings, comments, i18n (1150 lines)
│   ├── voice.js                  # Voice recording/playback (794 lines)
│   ├── cards.js                  # Card UI helpers (215 lines)
│   ├── admin-charts.js           # Chart rendering (1059 lines)
│   └── vendor/                   # face-api, SweetAlert2, Chart.js
│
├── rpi_flask/                    # Raspberry Pi Python service
│   ├── app.py                    # Flask server (1086 lines)
│   ├── config.json               # Runtime config
│   ├── requirements.txt          # Python dependencies
│   ├── setup.sh                  # One-command Pi setup
│   └── face_data/                # Enrolled face encodings
│
├── esp32_firmware/               # ESP32 Arduino sketches
│   ├── esp32_firmware.ino        # Main firmware (3982 lines)
│   ├── config.txt                # SD card config template
│   └── Test_*/                   # Test sketches for each module
│
├── sql/                          # Database schema
│   ├── migrations/               # Incremental migration files (8 files)
│   └── standalone/               # Legacy SQL files (duplicates)
│
├── models/                       # face-api.js ML models
│   └── weights/                  # SSD Mobilenet V1 + face landmarks
│
├── uploads/students/             # Student face photos
│
├── index.php                     # Admin dashboard (1150 lines)
├── login.php                     # Login page (546 lines)
├── student_dashboard.php         # Student portal (1284 lines)
├── teacher_dashboard.php         # Teacher portal (2025 lines)
├── device.php                    # Hardware management (1768 lines)
├── students.php                  # Student CRUD (707 lines)
├── teachers.php                  # Teacher CRUD (316 lines)
├── lecture.php                   # Lecture session manager (389 lines)
├── instructions.php              # User documentation (513 lines)
├── export_csv.php                # CSV attendance export
├── download_template.php         # Fingerprint template backup
│
├── .htaccess                     # Security rules, caching, gzip
├── devices_registry.json         # Auto-discovered hardware nodes
├── sys_status_esp32.txt          # ESP32 heartbeat buffer
├── sys_latest_rfid.txt           # Latest RFID UID buffer
└── README.md                     # Project overview
```

---

## 3. Data Flow

### 3.1 Attendance Recording Flow (all modalities)

```
Student presents credential
        │
        ▼
┌─────────────────┐
│ Hardware Node    │  ESP32 (fingerprint/RFID) or RPi (face) or Browser (QR/voice/manual)
│ detects student  │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ POST to PHP API  │  HTTP POST with: student_no/uid, course_code, modality, device_name,
│                  │  timestamp, hardware_key
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ attendance.php   │  Router → dispatches to modality-specific record.php
│ or modality-     │  → Validates student enrollment
│ specific endpoint│  → Checks for active lecture session
└────────┬────────┘  → Records attendance_logs row (or updates scan_count)
         │
         ▼
┌─────────────────┐
│ Response to      │  { status: "ok"/"updated"/"duplicate"/"not_enrolled"/"no_session" }
│ hardware         │
└─────────────────┘
```

### 3.2 Heartbeat Flow

```
ESP32 / RPi
    │
    │ every 10s (ESP32) or 30s (RPi)
    ▼
GET /api/heartbeat.php?device=esp32&name=ESP32-Lab1&hardware_key=...
    │
    ▼
PHP:
  1. Updates sys_status_esp32.txt (or sys_status_rpi.txt) with current timestamp
  2. Registers device in devices_registry.json if new
  3. Stores session variables for web UI
  4. Returns { status: "ok" }
```

### 3.3 OTA Command Flow

```
Web Dashboard
    │
    │ Admin sends command (e.g. RESTART_SERVICE, REBOOT, ENROLL:S12345)
    ▼
POST /api/ota.php?action=send  →  command queued in database/registry
    │
    │
ESP32/RPi polls every 10s:
    │
    ▼
GET /api/ota.php?action=poll&device=esp32&name=...
    │
    ▼
PHP returns queued command → ESP32/RPi executes it → POST /api/ota.php?action=ack
```

### 3.4 Lecture Session Flow

```
Teacher clicks "Start Lecture" on dashboard
    │
    ▼
POST /api/teacher.php (action=start_course_session)
  → Ends any previous active session for this course
  → INSERT new lecture_sessions row (is_active=1)
  → Client starts countdown timer (browser localStorage)
    │
    │ Hardware scans come in: checked against is_active=1
    ▼
recordLectureAttendance() → getActiveSession() → finds session_id → records attendance
    │
    │ Timer expires OR teacher clicks "End"
    ▼
POST /api/teacher.php (action=end_course_session)
  → UPDATE lecture_sessions SET is_active=0, ended_at=NOW()
```

---

## 4. PHP Backend

### 4.1 Entry Points & Routing

| File | Role | Entry Method |
|------|------|-------------|
| `index.php` | Admin Dashboard | Direct HTTP |
| `login.php` | Authentication | POST form login, session create |
| `student.php` | Student API | POST with `action` parameter |
| `attendance.php` | Attendance router | POST/GET with `modality` + `finger_id` |
| `heartbeat.php` | Device heartbeat | GET with query params |
| `ota.php` | OTA command queue | GET poll, POST send/ack |

### 4.2 Authentication System

**File:** `includes/auth/session.php` (135 lines)

The authentication system uses PHP sessions with the following mechanisms:

- **Session Initialization:** Started via `session_start()` with secure flags (HttpOnly, SameSite)
- **Role System:** Three roles — `admin`, `teacher`, `student` — stored in `$_SESSION['user_role']`
- **Role Locking:** Once a role is assigned, it's locked (`$_SESSION['role_locked']`) to prevent session hijacking across roles
- **Fingerprinting:** Each session is fingerprinted with user-agent + IP address. If they change mid-session, the session is destroyed
- **Helper Functions:** `isAdmin()`, `isTeacher()`, `isStudent()` — check role + fingerprint
- **CSRF Protection:** `generateCsrfToken()` creates a token stored in session; `requireCsrfIfMutating()` validates it on POST/PUT/DELETE requests
- **Rate Limiting:** `MAX_LOGIN_ATTEMPTS=5`, `LOGIN_LOCKOUT_SECONDS=15` — failed attempts tracked in session, lockout enforced server-side

### 4.3 Configuration Constants

**File:** `includes/config/app.php`

```php
define('APP_NAME', 'Sentinel Swarm AMS');
define('APP_VERSION', '3.0.0');
define('SESSION_LIFETIME', 3600);        // 1 hour
define('HEARTBEAT_SECRET', 'ss_secret_key_2052');
define('HARDWARE_API_KEY', 'ss_hw_api_key_2052');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_SECONDS', 15);
```

### 4.4 Database Connection

**File:** `includes/database/connection.php`

```php
$pdo = new PDO(
    'mysql:host=localhost;dbname=csc2052;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
```

### 4.5 API Pattern

Every API endpoint follows the same pattern:

```php
// 1. Session check
if (!isAdmin()) { respond('error', 'Unauthorized', 401); }

// 2. Parse action
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// 3. Route
if ($action === 'list') { ... }
elseif ($action === 'add') { ... }

// 4. Respond
function respond($status, $message, $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}
```

### 4.6 Key API Endpoints Detail

#### `api/student.php` (1771 lines) — Student Management
The largest API file. Handles all student CRUD plus biometric linking.

| Action | Description | Input |
|--------|-------------|-------|
| `list` | List all students with pagination | `page`, `limit`, `search`, `course` |
| `add` | Create new student | `student_no`, `student_name`, `course_code` |
| `update` | Edit student details | `student_no`, `student_name`, `email` |
| `delete` | Remove student | `student_no` |
| `link_student` | Bind biometric ID to student | `student_no`, `slot_id` (fingerprint), `modality` |
| `get_next_slot` | Find next free fingerprint slot | — |
| `search_student` | Search by name/no | `query` |
| `manual_attendance` | Mark attendance manually | `student_no`, `course_code`, `timestamp`, `modality` |
| `bulk_upload` | CSV import of students | CSV file with headers |

#### `api/heartbeat.php` — Device Registration

Receives heartbeats from both ESP32 and Raspberry Pi:

```
GET /api/heartbeat.php
  ?device=esp32|rpi
  &name=ESP32-Lab1
  &hardware_key=ss_hw_api_key_2052
  &ip=10.193.54.x
```

Auth modes supported:
- **plaintext:** Validate `hardware_key` against `HARDWARE_API_KEY` constant
- **hmac:** Validate `X-HMAC-Signature` header using `HEARTBEAT_SECRET`
- **aes:** Decrypt payload with AES-128-CBC using `HEARTBEAT_SECRET`

On successful auth:
1. Writes current timestamp to `sys_status_esp32.txt` (or `sys_status_rpi.txt`)
2. Registers/updates device in `devices_registry.json`
3. Stores device info in PHP session for web UI consumption

#### `api/attendance.php` — Attendance Router

Dispatches to modality-specific handlers based on the `modality` parameter:

```php
$modality = $_POST['modality'] ?? $_GET['modality'] ?? 'fingerprint';

switch ($modality) {
    case 'rfid': require 'attendance/rfid/record.php'; break;
    case 'face': require 'attendance/face/record.php'; break;
    case 'qr':   require 'attendance/qr/record.php'; break;
    case 'voice': require 'attendance/voice/record.php'; break;
    default:     require 'attendance/fingerprint/record.php'; break;
}
```

#### `api/attendance/manual/record.php` — Manual Attendance

Called from the web dashboard when a teacher/admin manually marks a student present.

```php
$stu_no      = sanitizeStudentNo($_POST['student_no'] ?? '');
$course_code = substr(preg_replace('/[^a-zA-Z0-9_\- ]/', '', trim($_POST['course_code'] ?? '')), 0, 30);
$timestamp   = trim($_POST['timestamp'] ?? '');
$modality    = trim($_POST['modality'] ?? 'manual');

// Validates enrollment
// Checks for active lecture session via getActiveSession()
// Records or updates attendance_logs
```

#### `api/charts.php` (633 lines) — Analytics Data

Generates JSON data for Chart.js frontend:

| Action | Chart Type | SQL Pattern |
|--------|-----------|-------------|
| `daily_trend` | Line | `GROUP BY DATE(timestamp)` last 30 days |
| `modality_pie` | Pie | `GROUP BY modality` all time |
| `course_bar` | Bar | `GROUP BY course_code` with student count |
| `hourly_heatmap` | Heatmap | `GROUP BY HOUR(timestamp)` per hour |
| `weekly_trend` | Line | `GROUP BY WEEK(timestamp)` current year |
| `student_timeline` | Line | Per-student daily attendance streak |

---

## 5. JavaScript Frontend

### 5.1 `js/main.js` (4294 lines) — Core Dashboard

This is the largest JS file and handles ALL dashboard operations across admin, teacher, and device pages.

**Key Sections:**

| Lines | Function | Description |
|-------|----------|-------------|
| 1-50 | SweetAlert2 override | Replaces `window.alert()` with styled SweetAlert2 dialogs |
| 50-200 | Toast system | `showToast()` for non-intrusive notifications (success/error/info/warning) |
| 200-400 | OTA commands | `sendOtaCommand()`, `sendOtaCommandToIp()` — sends commands to ESP32 via OTA proxy |
| 400-600 | Device management | Device scanning, registration, status polling |
| 600-900 | Lecture sessions | `startCourseToDevice()`, `endLectureByDevice()`, `startLectureTimer()`, `restoreLectureTimer()` |
| 900-1100 | Schedule auto-start | `checkScheduleAutoStart()` polls `api/schedule.php` every 30s and auto-starts matching lectures |
| 1100-1400 | RFID buffer | Consumes latest RFID UID from `sys_latest_rfid.txt` via `rfid_scanned.php` |
| 1400-1700 | Serial monitor | SSE connection to `sse_monitor.php` for real-time ESP32 logs |
| 1700-2000 | Boot mode selection | UI for selecting ESP32 boot modes (normal, flash, deep-sleep) |
| 2000-2400 | WiFi scanning | Scans WiFi networks via ESP32, populates dropdown |
| 2400-2800 | Firmware upload | Modal for uploading .bin firmware to ESP32 via OTA |
| 2800-3200 | Network scanner | `scanNetwork()` — pings subnet for discoverable devices |
| 3200-3600 | Config download | `downloadSdConfigFromForm()`, `downloadSdConfig()`, `downloadFirmwareSource()` |
| 3600-4000 | Setup guide | `downloadSetupGuide()` — generates detailed text guide for hardware setup |
| 4000-4294 | AI Assistant | `window.SentinelAI` — toggleable chatbot panel with predefined Q&A, hardware status display |

### 5.2 `js/face_recognition.js` (1069 lines) — Browser Face Recognition

Uses **face-api.js** (TensorFlow.js-based) to run face recognition directly in the browser using the webcam.

**How it works:**

1. **Model Loading** — Downloads SSD Mobilenet V1 model from `models/` or CDN fallback
   ```javascript
   await faceapi.nets.ssdMobilenetv1.loadFromUri('/csc2052/models');
   await faceapi.nets.faceLandmark68Net.loadFromUri('/csc2052/models');
   await faceapi.nets.faceRecognitionNet.loadFromUri('/csc2052/models');
   ```

2. **Video Stream** — Access webcam via `navigator.mediaDevices.getUserMedia()`

3. **Detection Loop** — Every ~500ms:
   - Detect all faces with landmarks
   - Compute face descriptors
   - Compare against enrolled student descriptors (fetched from server)
   - Display bounding boxes with names on a canvas overlay

4. **Attendance Posting** — When a known face is detected:
   - Check cooldown (default 5s per student)
   - POST to `student.php` with `action=manual_attendance`, `modality=face`

**Difference from RPi:** The browser version uses the local computer's CPU/GPU and face-api.js. The RPi version uses Python's `face_recognition` library (dlib backend) which is more accurate but slower on Pi 3B.

### 5.3 `js/ratings.js` (1150 lines) — Ratings, Comments & Internationalization

**Multi-language support:**
```javascript
const I18N = {
    en: { dashboard: 'Dashboard', attendance: 'Attendance', ... },
    si: { dashboard: 'උපකරණ පුවරුව', attendance: 'පැමිණීම', ... },  // Sinhala
    ta: { dashboard: 'டாஷ்போர்டு', attendance: 'வருகை', ... },      // Tamil
};
```

Provides:
- Star rating UI (1-5 stars) for courses/sessions
- Threaded forum comments with reply nesting
- Like/unlike, pin/unpin, edit/delete comments
- Language switcher (Sinhala/Tamil/English)

### 5.4 `js/voice.js` (794 lines) — Voice Attendance

Uses the Web Audio API for:
- **Voice Recording:** Captures microphone input via `MediaRecorder` API
- **Audio Visualization:** Real-time waveform display on `<canvas>`
- **Voice Enrollment:** Records 3 samples of the student speaking their name
- **TTS Playback:** Uses `SpeechSynthesis` API to announce "Welcome [Student Name]"
- **Voice Match:** Sends recorded audio to server for comparison against enrolled voice prints

### 5.5 `js/admin-charts.js` (1059 lines) — Chart Rendering

Builds Chart.js visualizations for the admin dashboard:

| Chart | Type | Data Source |
|-------|------|-------------|
| Daily Trend | Line chart | `api/charts.php?action=daily_trend` |
| Modality Breakdown | Doughnut pie | `api/charts.php?action=modality_pie` |
| Course Comparison | Bar chart | `api/charts.php?action=course_bar` |
| Hourly Heatmap | Matrix | `api/charts.php?action=hourly_heatmap` |
| Weekly Trend | Line chart | `api/charts.php?action=weekly_trend` |

Each chart has custom gradient fills, animations, and responsive sizing.

---

## 6. Raspberry Pi Face Recognition Service

**Code:** `rpi_flask/app.py` (1086 lines)  
**Language:** Python 3 + Flask  
**Key Libraries:** OpenCV, face_recognition (dlib), numpy, picamera2

### 6.1 Module Overview

```
app.py
├── Configuration Loading (config.json + CLI args)
├── Logging System (circular buffer with SSE streaming)
├── Face Data Management
│   ├── _rebuild_encodings()     — Reload all .npy files into memory
│   ├── _recognize_faces(frame)  — Detect + match faces
│   └── _post_attendance(no)     — POST recognized student to PHP server
├── Camera Management
│   ├── _camera_open()           — Initialize PiCamera or USB camera
│   ├── _camera_read()           — Capture single frame
│   ├── _camera_close()          — Release camera resources
│   └── _camera_health_check()   — Auto-recovery on failure
├── Background Threads
│   ├── _heartbeat_loop()        — Every 30s POST to PHP server
│   ├── _ota_poll_loop()         — Every 10s poll for commands
│   └── Recognition Cache        — 500ms cache to avoid redundant processing
├── Flask Routes
│   ├── /                        — Web dashboard (status, stream, faces)
│   ├── /stream                  — MJPEG video feed with annotations
│   ├── /api/face-status         — Current frame recognition results (JSON)
│   ├── /api/snapshot            — Single JPEG frame
│   ├── /api/status              — Health info (uptime, camera, face count)
│   ├── /api/sysinfo             — CPU temp, RAM, disk, uptime
│   ├── /api/faces               — List enrolled faces
│   ├── /api/faces/<no>          — Delete enrolled face
│   ├── /api/config              — Read/write runtime config
│   ├── /api/command             — Execute commands (POST JSON)
│   └── /api/logs                — Recent log lines
└── Main Entry Point
    ├── Flask app.run()          — Start web server on port 5000
    └── Threading                 — Camera read + background loops
```

### 6.2 Face Recognition Engine

**Threshold-based matching:**

```python
# Match a detected face against all known encodings
distances = np.linalg.norm(known_encodings - new_encoding, axis=1)
min_idx = np.argmin(distances)
min_dist = float(distances[min_idx])
confidence = 1.0 - min_dist
known = min_dist < (1.0 - THRESHOLD)  # default THRESHOLD = 0.55
```

- **HOG model** (default): Fast, good for Pi 3B (~1-2 fps)
- **CNN model**: More accurate but slower (use on Pi 4+)

**Recognition Cache:** Results are cached for `RECOGNITION_CACHE_MS` (default 500ms). Both the MJPEG stream and `/api/face-status` endpoint share the same cache, avoiding redundant computation.

### 6.3 Auto Attendance Posting

When a known face is detected and `server_post_attendance` is `true`:

```python
def _post_attendance(student_no):
    data = {
        "action": "manual_attendance",
        "student_no": student_no,
        "modality": "rpi_face",
        "timestamp": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
    }
    requests.post(f"{server}/csc2052/api/student.php", data=data)
```

A cooldown (`_last_attendance_post` dict) prevents duplicate posts within `ATTENDANCE_COOLDOWN` seconds (default 5s).

### 6.4 Heartbeat Loop

```python
def _heartbeat_loop():
    while True:
        url = f"{server}/csc2052/api/heartbeat.php?device=rpi&name=rpi-face&hardware_key={key}"
        requests.get(url, timeout=8)
        time.sleep(HEARTBEAT_INTERVAL)  # default 30s
```

This keeps the Pi registered as "Online" on the admin dashboard.

### 6.5 Camera Types

| Type | `camera_type` config | Implementation |
|------|---------------------|----------------|
| Pi Camera Module | `picamera` | Uses `picamera2` library (CSI interface) |
| USB Webcam | `usb` | Uses `cv2.VideoCapture` (UVC standard) |

### 6.6 Configuration

```json
{
  "ota_server_url": "http://10.193.54.212",
  "heartbeat_interval": 30,
  "server_post_attendance": true,
  "recognition_cache_ms": 500,
  "attendance_cooldown": 5,
  "face_model": "hog",
  "threshold": 0.55,
  "camera_type": "picamera"
}
```

### 6.7 Setup on Pi

```bash
# Install system deps
sudo apt-get install -y python3-pip python3-venv python3-dlib python3-opencv python3-picamera2

# Create venv
cd rpi_flask
python3 -m venv venv --system-site-packages
source venv/bin/activate
pip install flask flask-cors numpy psutil

# Edit config
nano config.json   # Set ota_server_url

# Run
python app.py
```

---

## 7. ESP32 Firmware

**Code:** `esp32_firmware/esp32_firmware.ino` (3982 lines)  
**Language:** Arduino C++ (C++17)  
**Target:** ESP32 (ESP32-WROOM-32)  
**IDE:** Arduino IDE / PlatformIO

### 7.1 Hardware Pin Mapping

```
FM10A Fingerprint:
  TX → D16 (UART2 RX)
  RX → D17 (UART2 TX)
  TouchOut → D35 (interrupt)
  MOSFET gate → D5 (power control)

MFRC522 RFID (SPI):
  SDA(SS) → D15
  SCK     → D18
  MOSI    → D23
  MISO    → D19
  RST     → D22

4×4 Keypad:
  Rows: D13, D12, D14, D27
  Cols: D26, D25, D33, D32

I2C LCD:
  SDA → D21
  SCL → D22

SD Card (SPI, shared with RFID):
  CS → D4
```

### 7.2 Feature Flags

```cpp
#define USE_FINGERPRINT   // FM10A fingerprint sensor
#define USE_RFID          // MFRC522 RFID reader
#define USE_LCD           // I2C LCD display (16x2 or 20x4)
#define USE_HW084         // Sound sensor (disabled)
#define USE_KEYPAD        // 4x4 matrix keypad
```

Comment out flags for hardware NOT connected to save program space and avoid initialization errors.

### 7.3 Module Details

#### FM10A Fingerprint Sensor (`USE_FINGERPRINT`)

- **Communication:** UART2 (RX=16, TX=17) at auto-detected baud rate (57600-115200)
- **Power:** MOSFET-controlled via D5 (HIGH = on) — sensor draws ~100mA
- **Touch Detection:** FINGER_VOUT (D35) goes HIGH when finger is placed on sensor
- **Enrollment:** Captures 2 prints of same finger, stores in sensor's internal memory (slots 0-999)
- **1:N Matching:** Scans finger → sensor returns slot ID → lookup in PHP server → attendance
- **Template Upload/Download:** Templates can be saved to SD card as `.dat` files and restored

**Enrollment Flow:**
```
1. Admin sends ENROLL:5 (slot 5) via web dashboard or keypad
2. Firmware runs Adafruit_Fingerprint.enroll(slot)
3. Sensor captures finger 2 times
4. Template saved to sensor memory
5. Template also saved to SD /fingerprints/5.dat
6. Admin then links slot 5 to a student via web (link_student API)
```

#### MFRC522 RFID Reader (`USE_RFID`)

- **Communication:** SPI (SDA=15, SCK=18, MOSI=23, MISO=19, RST=22)
- **Cards Supported:** MIFARE Classic 1K/4K, MIFARE Ultralight, NTAG
- **Reading:** Continuously polls for card presence, reads UID (4-7 bytes)
- **UID Format:** Hex string (e.g., `A3B4C5D6`)
- **Post-Processing:** Sends UID to `rfid_scanned.php` on PHP server, which looks up the student

#### 4×4 Matrix Keypad (`USE_KEYPAD`)

- **Scan Method:** Manual row-column scanning (no external library)
- **Key Map:**
  ```
  1  2  3  A
  4  5  6  B
  7  8  9  C
  *  0  #  D
  ```
- **PIN Entry:** Used for device unlock (SYSTEM_PIN), admin functions
- **Debouncing:** 500µs hardware debounce + software timing filter

#### I2C LCD Display (`USE_LCD`)

- **Display:** 16×2 or 20×4 character LCD with I2C backpack (address 0x27 or 0x3F)
- **Content:** Device status, scan confirmation, student name, error messages
- **I2C Bus:** SDA=21, SCL=22 (shared address recovery with RFID's RST pin)

### 7.4 WebServer

The ESP32 runs a web server on **port 80** with the following endpoints:

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/` | GET | Status page (JSON) — device info, uptime, WiFi, queue count |
| `/cmd?command=...` | GET | Execute command (backward-compatible with ESP32 convention) |
| `/wifi-scan` | GET | Returns JSON list of available WiFi networks |
| `/wifi-connect` | POST | Connect to WiFi (SSID, password, optional static IP) |
| `/config` | GET/POST | Read/write NVRAM configuration |
| `/list` | GET | List files on SD card |
| `/download` | GET | Download file from SD card |
| `/upload` | POST | Upload file to SD card |
| `/remove` | POST | Delete file from SD card |
| `/format` | POST | Format SD card |
| `/update` | POST | OTA firmware update (upload .bin) |

### 7.5 OTA Firmware Update Flow

```
1. Admin uploads new firmware.bin via device.php web UI
2. PHP stores the binary temporarily
3. ESP32 polls GET /api/ota.php?action=poll&device=esp32&name=...
4. PHP responds with firmware binary URL or direct binary
5. ESP32 downloads the binary via HTTP
6. ESP32 calls Update.begin() + Update.write() + Update.end()
7. ESP32 reboots into new firmware
```

### 7.6 Offline Queue

When the PHP server is unreachable:

```
Attendance scan
    │
    ▼
WiFi connected? ──NO──→ saveToOfflineQueue(student_no, modality, timestamp)
    │                      │
    │                      ▼
    │                 LittleFS file: /offline_queue.csv
    │                 Line format: student_no,modality,timestamp
    │
    ▼
POST to server
    │
    ▼
Success? ──YES──→ Done
    │
    NO
    │
    ▼
saveToOfflineQueue() → retry later
```

Background sync runs every 30 seconds when online via `syncOfflineQueue()`.

### 7.7 SD Card Config

**File:** `config.txt` on SD card root

```
DEVICE_NAME=ESP32-Lab1
WIFI_SSID=Card ekak daganin bn
WIFI_PASS=meshan1234
SERVER_URL=http://10.0.0.5/csc2052
STATIC_IP=10.193.54.212
STATIC_GATEWAY=10.193.54.1
STATIC_SUBNET=255.255.255.0
STATIC_DNS=8.8.8.8
HARDWARE_KEY=ss_hw_api_key_2052
SECRET_KEY=ss_secret_key_2052
```

Config is parsed by `readSDConfig()` → saved to NVRAM Preferences → loaded on boot.

---

## 8. React SPA + Tauri Desktop App

### 8.1 React App (`frontend/`)

A React 19 SPA built with Vite 8, currently with mock API data:

**Pages:**
- `/login` — Login form with animated particle background
- `/teacher_dashboard` — Teacher stats, courses, attendance data (skeleton loaders)
- `/` — Redirects to `/login`

**MVC-like Architecture:**
```
src/
├── components/       # UI components (ProtectedRoute)
├── controllers/      # State management (useLoginController, useTeacherController)
├── models/           # API calls (AuthModel, TeacherModel)
├── utils/            # HTTP client wrapper (apiClient)
└── assets/           # Images, icons
```

### 8.2 Progressive Web App (PWA)

The React app is installable as a PWA:

- **`manifest.json`** — Defines app name, icons (SVG), theme color (#0f172a), standalone display
- **`sw.js`** — Service worker with cache-first for static assets, network-first for API calls
- **Offline Fallback** — API calls return `{ offline: true }` when network is unavailable

### 8.3 Tauri Desktop App (`frontend/src-tauri/`)

Wraps the React SPA in a native OS window using Tauri v2 (Rust):

**Build Output:**
- Windows: `.exe` + NSIS installer (`target/release/bundle/nsis/`)
- macOS: `.dmg`
- Linux: `.deb` / `.AppImage`

**Configuration:**
```json
{
  "productName": "Sentinel Swarm AMS",
  "version": "0.1.0",
  "build": {
    "beforeBuildCommand": "npm run build",
    "frontendDist": "../dist"
  },
  "app": {
    "windows": [{ "title": "Sentinel Swarm AMS", "width": 1280, "height": 800 }]
  },
  "bundle": {
    "targets": "all",
    "icon": ["icons/32x32.png", "icons/128x128.png", "icons/icon.ico"]
  }
}
```

**Build Commands:**
```bash
cd frontend
npm install
npm run build
npm run tauri:icon       # Generate icons from SVG
npm run tauri:build      # Produce .exe installer
```

---

## 9. Database Schema

### 9.1 Core Tables

#### `users`
| Column | Type | Description |
|--------|------|-------------|
| `id` | INT AUTO_INCREMENT PK | Primary key |
| `username` | VARCHAR(50) UNIQUE | Login ID (same as student_no for students) |
| `password` | VARCHAR(255) | Bcrypt hash |
| `role` | ENUM('admin','teacher','student') | User role |
| `full_name` | VARCHAR(100) | Display name |
| `must_change_password` | TINYINT(1) DEFAULT 0 | Force password change on next login |
| `created_at` | DATETIME | Account creation timestamp |

#### `students`
| Column | Type | Description |
|--------|------|-------------|
| `student_no` | VARCHAR(50) PK | Student ID (e.g., S_22_314) |
| `student_name` | VARCHAR(100) | Full name |
| `fingerprint_id` | INT | FM10A fingerprint slot number |
| `rfid_uid` | VARCHAR(20) | RFID card UID (hex) |
| `face_id` | VARCHAR(100) | Face encoding filename |
| `profile_photo` | VARCHAR(255) | Photo file path |
| `finger_data` | TEXT | Raw fingerprint template (hex) |

#### `courses`
| Column | Type | Description |
|--------|------|-------------|
| `course_code` | VARCHAR(50) PK | Short code (e.g., CS2052) |
| `course_name` | VARCHAR(100) | Full name |
| `credits` | INT | Credit hours |
| `department` | VARCHAR(100) | Faculty/Department |

#### `student_courses`
| Column | Type | Description |
|--------|------|-------------|
| `id` | INT AUTO_INCREMENT PK | |
| `student_no` | VARCHAR(50) FK → students | |
| `course_code` | VARCHAR(50) FK → courses | |
| `enrolled_at` | DATETIME | Enrollment date |

#### `lecture_sessions`
| Column | Type | Description |
|--------|------|-------------|
| `id` | INT AUTO_INCREMENT PK | |
| `course_code` | VARCHAR(50) FK → courses | Course being taught |
| `teacher_id` | INT | Teacher who started it |
| `started_by` | VARCHAR(50) | 'admin' or 'teacher' |
| `started_at` | DATETIME | Session start time |
| `ended_at` | DATETIME NULL | Session end time |
| `device_name` | VARCHAR(100) | Device that started it |
| `is_active` | TINYINT(1) DEFAULT 1 | Currently live |

Sessions older than `MAX_SESSION_DURATION_HOURS` (8 hours) are auto-expired by `cleanupStaleSessions()`.

#### `attendance_logs`
| Column | Type | Description |
|--------|------|-------------|
| `id` | INT AUTO_INCREMENT PK | |
| `session_id` | INT FK → lecture_sessions | Links to lecture session |
| `scan_count` | INT DEFAULT 1 | How many times scanned in this session |
| `student_no` | VARCHAR(50) FK → students | |
| `course_code` | VARCHAR(50) FK → courses | |
| `timestamp` | DATETIME | Scan time |
| `device_name` | VARCHAR(100) | Hardware node name |
| `modality` | VARCHAR(20) | fingerprint/rfid/face/qr/voice/manual/rpi_face |
| `is_offline_sync` | TINYINT(1) DEFAULT 0 | Was recorded offline and synced later |

### 9.2 Feature Tables

#### `course_schedules`
Weekly recurring schedule for auto-start feature. Columns: `course_code`, `day_of_week`, `start_time`, `end_time`, `venue`, `device_id`, `auto_start`, `email_threshold`, `email_on_end`.

#### `ratings`
Student course ratings (1-5 stars). Unique per student+course+session.

#### `forum_comments`
Threaded comments with likes, pinning, soft-delete. Columns: `comment_id`, `parent_id`, `student_no`, `course_code`, `comment_text`, `likes`, `pinned`, `deleted`.

#### `qr_tokens`
5-second rotating QR tokens for anti-proxy QR attendance.

#### `qr_scan_attempts`
Logs every QR scan with timestamp for fraud detection.

#### `voice_settings`
TTS configuration: rate, pitch, voice name, announcement toggles.

#### `device_password`
Per-device PIN codes for ESP32 access control.

---

## 10. Security & Authentication

### 10.1 Web Authentication

| Mechanism | Implementation | Location |
|-----------|---------------|----------|
| Session-based auth | PHP `$_SESSION` with HttpOnly cookies | `includes/auth/session.php` |
| Role isolation | `isAdmin()`, `isTeacher()`, `isStudent()` | `includes/auth/session.php` |
| Role locking | `$_SESSION['role_locked']` prevents multi-role hijacking | `includes/auth/session.php` |
| Session fingerprint | User-Agent + IP check on every request | `includes/auth/session.php` |
| CSRF tokens | `generateCsrfToken()` / `requireCsrfIfMutating()` | `includes/helpers/general.php` |
| Login rate limiting | 5 attempts → 15s lockout per session | `login.php` |
| Password hashing | PHP `password_hash(PASSWORD_BCRYPT)` | `login.php` |
| `.htaccess` blocking | Dot-files, SQL, config files blocked | `.htaccess` |

### 10.2 Hardware Authentication

| Method | Description | Used By |
|--------|-------------|---------|
| **Plaintext** | `hardware_key` query parameter compared against `HARDWARE_API_KEY` | ESP32 heartbeat, ESP32 attendance |
| **HMAC** | `X-HMAC-Signature` header = `HMAC-SHA256(payload, HEARTBEAT_SECRET)` | Optional ESP32/RPi |
| **AES-128-CBC** | Encrypted payload with `HEARTBEAT_SECRET` as key | Optional ESP32 |

### 10.3 File Security (`.htaccess`)

```apache
# Deny access to sensitive files
RewriteRule \.(md|sql|txt)$ - [F,L]
RewriteRule (config\.php|db\.php) - [F,L]

# Security headers
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "DENY"
Header set Referrer-Policy "strict-origin-when-cross-origin"
```

---

## 11. Configuration Files

### 11.1 PHP Server Config

**File:** `includes/config/app.php`

| Constant | Default | Purpose |
|----------|---------|---------|
| `APP_NAME` | `'Sentinel Swarm AMS'` | Used in page titles and emails |
| `APP_VERSION` | `'3.0.0'` | Displayed in footer |
| `SESSION_LIFETIME` | `3600` | Session timeout in seconds (1 hour) |
| `HEARTBEAT_SECRET` | env or `'ss_secret_key_2052'` | HMAC/AES shared secret |
| `HARDWARE_API_KEY` | env or `'ss_hw_api_key_2052'` | Plaintext hardware auth key |
| `MAX_LOGIN_ATTEMPTS` | `5` | Before lockout |
| `LOGIN_LOCKOUT_SECONDS` | `15` | Lockout duration |

### 11.2 RPi Config

**File:** `rpi_flask/config.json`

| Key | Default | Purpose |
|-----|---------|---------|
| `threshold` | `0.55` | Face match confidence (0-1) |
| `camera_type` | `picamera` | `picamera` or `usb` |
| `ota_server_url` | `null` | PHP server base URL |
| `heartbeat_interval` | `30` | Seconds between heartbeats |
| `server_post_attendance` | `true` | Auto-post recognized faces |
| `recognition_cache_ms` | `500` | Cache recognition results |
| `attendance_cooldown` | `5` | Seconds before re-scan allowed |
| `face_model` | `hog` | `hog` (fast) or `cnn` (accurate) |

### 11.3 ESP32 Config

**File:** `config.txt` on SD card (or NVRAM defaults)

| Key | Example | Purpose |
|-----|---------|---------|
| `DEVICE_NAME` | `ESP32-Lab1` | Device identity for dashboard |
| `WIFI_SSID` | `Card ekak daganin bn` | WiFi network name |
| `WIFI_PASS` | `meshan1234` | WiFi password |
| `SERVER_URL` | `http://10.0.0.5/csc2052` | PHP server URL |
| `STATIC_IP` | `10.193.54.212` | Static IP (leave blank for DHCP) |
| `HARDWARE_KEY` | `ss_hw_api_key_2052` | API auth key |
| `SECRET_KEY` | `ss_secret_key_2052` | HMAC/AES shared secret |

---

## 12. Communication Protocols

### 12.1 PHP → ESP32 (OTA Commands)

```
Web Dashboard → POST /api/ota.php (action=send, command=RESTART_SERVICE)
    ↓
PHP stores command in queue (database or registry file)
    ↓
ESP32 GET /api/ota.php?action=poll&device=esp32&name=ESP32-Lab1&hardware_key=...
    ↓
PHP returns: {"status": "ok", "command": "RESTART_SERVICE", "id": 42}
    ↓
ESP32 executes command, then:
POST /api/ota.php (action=ack, id=42)
    ↓
PHP marks command as completed
```

### 12.2 ESP32 → PHP (Attendance)

```
POST /api/attendance.php
Content-Type: application/x-www-form-urlencoded

finger_id=5
&device_name=ESP32-Lab1
&course_code=CS2052
&timestamp=2026-05-28+10%3A30%3A00
&modality=fingerprint
&hardware_key=ss_hw_api_key_2052
```

Response:
```json
{"status": "ok", "message": "Attendance recorded.", "session_id": 128}
```

Possible statuses:
- `ok` — New attendance record created
- `updated` — Re-scan within same session (scan_count incremented)
- `duplicate` — Already recorded today
- `not_enrolled` — Student not in this course
- `no_session` — No active lecture for this course

### 12.3 RPi → PHP (Heartbeat)

```
GET /api/heartbeat.php?device=rpi&name=rpi-face&hardware_key=ss_hw_api_key_2052
```

Response:
```json
{"status": "ok"}
```

### 12.4 RPi → PHP (Face Attendance)

```
POST /api/student.php
Content-Type: application/x-www-form-urlencoded

action=manual_attendance
&student_no=S_22_314
&modality=rpi_face
&timestamp=2026-05-28+10%3A30%3A00
```

Response:
```json
{"status": "success", "message": "Manual attendance recorded for S_22_314."}
```

---

## 13. Attendance Modalities

### 13.1 Fingerprint (ESP32 + FM10A)

| Aspect | Detail |
|--------|--------|
| Hardware | FM10A optical fingerprint sensor |
| Library | Adafruit_Fingerprint |
| Storage | Sensor internal memory (slots 0-999) + SD backup (.dat files) |
| Enrollment | Admin sends `ENROLL:5` → captures 2 prints → stored in slot 5 |
| Matching | 1:N scan → sensor returns slot ID → lookup via PHP |
| Strengths | Highly accurate, hard to spoof |
| Weaknesses | Slower than RFID, needs physical contact |

### 13.2 RFID (ESP32 + MRC522)

| Aspect | Detail |
|--------|--------|
| Hardware | MFRC522 RFID-RC522 module |
| Frequency | 13.56 MHz |
| Cards | MIFARE Classic, Ultralight, NTAG |
| Range | ~5cm |
| Flow | Card tap → UID read → POST to `rfid_scanned.php` → web consumes |
| Strengths | Fast, no contact needed, students already carry ID cards |
| Weaknesses | Cards can be lost/shared, shorter range |

### 13.3 Face Recognition (RPi / Browser)

| Aspect | RPi Version | Browser Version |
|--------|-------------|-----------------|
| Library | face_recognition (dlib) | face-api.js (TensorFlow.js) |
| Model | HOG (fast) or CNN (accurate) | SSD Mobilenet V1 |
| Hardware | Pi Camera Module / USB webcam | Any webcam |
| Performance | ~1-2 fps (Pi 3B HOG) | ~5-10 fps (modern laptop) |
| Strengths | Hands-free, fastest throughput | No server load, privacy-preserving |

### 13.4 QR Code (Browser / ESP32)

| Aspect | Detail |
|--------|--------|
| Generation | qrcode.min.js in browser |
| Scanning | html5-qrcode.min.js (camera) or ESP32 camera |
| Anti-fraud | 5-second rotating tokens, consecutive scan validation |
| Use case | Backup method — works with any smartphone |

### 13.5 Voice (Browser + ESP32)

| Aspect | Detail |
|--------|--------|
| Enrollment | Record 3 samples via Web Audio API |
| Matching | Server-side comparison of audio features |
| TTS | Announces student name on recognition |
| Use case | Alternative for visually impaired or without tokens |

### 13.6 Manual (Web Dashboard)

| Aspect | Detail |
|--------|--------|
| Who | Admin or Teacher |
| Where | Dashboard → Manual Attendance card |
| Input | Student number + course code |
| Modality | `manual` |

---

## 14. Key Flows

### 14.1 Full Day-in-the-Life Flow

```
08:00 — Teacher arrives, opens teacher_dashboard.php
08:01 — Teacher selects course "CS2052" and clicks "Start Lecture"
         → POST /api/teacher.php (start_course_session)
         → Server: ends previous CS2052 session, inserts new one (is_active=1)
         → Browser: localStorage timer starts (default 1 hour)
08:05 — Student enters classroom
         ┌── face: RPi detects face → POST attendance → server records
         ├── RFID: ESP32 reads card → POST → server records
         ├── QR: Student shows QR on phone → ESP32/browser scans → server records
         └── fingerprint: ESP32 scans finger → server records
08:30 — Dashboard shows 15 students present out of 20 enrolled
09:00 — Timer expires → auto-end lecture → is_active=0
         → Server: UPDATE lecture_sessions SET is_active=0
         → Email sent to teacher with attendance summary (if configured)
```

### 14.2 New Device Setup Flow

```
1. Flash ESP32 with firmware (Arduino IDE or PlatformIO)
2. Create config.txt with WiFi + server details
3. Insert SD card into ESP32
4. Power on ESP32
   → readSDConfig() parses config.txt
   → Values saved to NVRAM Preferences
   → WiFi connects to configured SSID
   → First heartbeat: POST to heartbeat.php
   → PHP registers device in devices_registry.json
5. Web dashboard shows "ESP32-Lab1 — Online"
6. Admin configures device (assign to course, set PIN)
```

### 14.3 Student Enrollment Flow

```
New student arrives:
1. Admin adds student via students.php (or CSV bulk upload)
   → INSERT into students table
2. Enroll biometrics:
   ├── Fingerprint: Admin sends command → ESP32 captures → slot linked via link_student API
   ├── RFID: Tap card → UID stored in student record
   ├── Face: Upload photo → OR RPi captures face → encoding saved
   └── Voice: Student speaks name 3x → samples saved
3. Student enrolled in courses via student_courses table
```

### 14.4 Offline Recovery Flow

```
ESP32 loses WiFi:
1. Attendance scans → saveToOfflineQueue() → LittleFS /offline_queue.csv
2. Heartbeat fails → isOnline = false
3. Every 30 seconds: try syncOfflineQueue()
4. When WiFi restored:
   → syncOfflineQueue() POSTs each queued record
   → Server processes each record (checks enrollment + active session)
   → Successful records removed from queue
   → Failed records (not_enrolled, no_session) dropped
```

---

## 15. Troubleshooting Guide

### 15.1 Common Issues

#### "No active lecture session" error
**Cause:** A lecture session must be started before attendance can be recorded.  
**Fix:** Teacher/admin starts the session via the dashboard, or enable auto-start in schedule settings.  
**Server-side:** Sessions older than 8 hours auto-expire via `cleanupStaleSessions()`.

#### ESP32 shows "Offline" on dashboard
**Cause:** No heartbeat received in last 30 seconds.  
**Check:**
1. Is ESP32 powered on?
2. Is WiFi connected? Check via serial monitor
3. Can ESP32 reach the PHP server? Check `SERVER_URL` in config
4. Is heartbeat.php responding? Check Apache logs

#### Face recognition slow on Pi 3B
**Cause:** HOG model processes ~1-2 fps on Pi 3B.  
**Fix:**
- Reduce camera resolution: set `camera_width: 320, camera_height: 240`
- Lower cache: `recognition_cache_ms: 1000` (more caching = fewer frames processed)
- Use Pi 4 or Pi 5 for better performance

#### QR code not scanning
**Cause:** Camera access blocked, or QR code too small/dim.  
**Fix:**
- Allow camera permission in browser
- Increase QR code size on screen
- Reduce ambient light reflection

### 15.2 Log Locations

| Component | Log Location | How to View |
|-----------|-------------|-------------|
| PHP Server | XAMPP Apache error log | `xampp/apache/logs/error.log` |
| PHP API | `error_log()` calls | Apache error log |
| MySQL | XAMPP MySQL log | `xampp/mysql/data/mysql.log` |
| RPi Flask | stdout/stderr (journald) | `journalctl -u rpi-face -f` |
| RPi Flask (web) | `/api/logs?n=50` | Browser → Pi web dashboard |
| ESP32 | Serial output | Arduino IDE Serial Monitor or web dashboard SSE |
| ESP32 SD | `/logs/error.csv` | Fetch via `/sdfile?path=/logs/error.csv` |
| Browser | Browser DevTools Console | F12 → Console |

### 15.3 Database Reset

```sql
-- Reset attendance for testing
TRUNCATE attendance_logs;
UPDATE lecture_sessions SET is_active = 0, ended_at = NOW();

-- Reset all lecture sessions
TRUNCATE lecture_sessions;

-- Clear device registry
-- (delete contents of devices_registry.json, keep empty object {})
```

### 15.4 File Permissions

| Path | Permission | Reason |
|------|-----------|--------|
| `devices_registry.json` | 666 (writable) | PHP writes device heartbeats |
| `sys_status_esp32.txt` | 666 | PHP writes heartbeat timestamps |
| `sys_latest_rfid.txt` | 666 | ESP32 writes RFID UID, PHP reads |
| `uploads/students/` | 755 | Stores uploaded student photos |
| `rpi_flask/face_data/` | 755 | RPi stores face encodings |
| `esp32_firmware/` | 755 | Read-only firmware source |

---

## Quick Reference: File Purposes

### Root PHP Pages
```
index.php               → Admin dashboard (stats, charts, device status)
login.php               → Login page (multi-role, rate-limited)
device.php              → Hardware management (ESP32 + RPi control center)
student_dashboard.php   → Student portal (attendance history, biometrics)
teacher_dashboard.php   → Teacher portal (lecture control, attendance, QR, ratings)
students.php            → Student CRUD (admin)
teachers.php            → Teacher CRUD (admin)
lecture.php             → Lecture session manager + email config
instructions.php        → User documentation
export_csv.php          → CSV attendance export
```

### API Endpoints (by function)
```
Attendance:      attendance.php, face_scanned.php, rfid_scanned.php,
                 qr_attendance.php, voice.php
Student:         student.php
Teacher:         teacher.php
Devices:         heartbeat.php, devices.php, device_password.php, esp_ota_proxy.php
Analytics:       charts.php, analytics.php
Config:          settings.php, schedule.php, voice_settings.php
System:          system_logs.php, sse_monitor.php, ota.php
```

### Hardware
```
rpi_flask/app.py                    → Raspberry Pi face recognition service
esp32_firmware/esp32_firmware.ino   → ESP32 biometric node firmware
frontend/                           → React SPA + Tauri desktop app
```

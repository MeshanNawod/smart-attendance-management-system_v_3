# Sentinel Swarm AMS — Technology Stack

## Languages

| Language | Used For |
|---|---|
| **PHP 8.x** | Web backend — all attendance logic, API endpoints, dashboard rendering |
| **JavaScript (ES6+)** | Browser frontend — real-time UI, face-api.js, QR scanning, charts |
| **Python 3.9+** | Raspberry Pi face recognition Flask server |
| **C++ (Arduino)** | ESP32 firmware — hardware control, sensors, networking |
| **Rust** | Tauri v2 desktop app shell |
| **SQL** | MySQL schema, queries, migrations |
| **HTML5 / CSS3** | Web UI structure and styling |
| **JSX** | React frontend (Tauri desktop app) |

---

## Frontend Stack

### Legacy Web App (main UI — served via XAMPP)

| Layer | Technology |
|---|---|
| **CSS Framework** | Bootstrap 5.3.0 (CDN) |
| **Icons** | Bootstrap Icons 1.11.3 (CDN) |
| **Font** | Inter (Google Fonts) |
| **Charts** | Chart.js (UMD bundle) |
| **Alerts / Modals** | SweetAlert2 11.x |
| **QR Generation** | `qrcode.min.js` |
| **QR Scanning** | `html5-qrcode.min.js` |
| **Face Detection** | `face-api.min.js` (face-api.js — TensorFlow.js based) |
| **Service Worker** | Custom `sw.js` for PWA offline support |
| **Manifest** | `manifest.json` — installable PWA |

**PHP pages** (15+): `index.php`, `login.php`, `students.php`, `teachers.php`, `lecture.php`, `device.php`, `student_dashboard.php`, `teacher_dashboard.php`, `attendance.php`, `ratings.php`, `reports.php`, etc.

**JS modules:** `js/main.js` (4294 lines — core logic), `js/face_recognition.js`, `js/voice.js`, `js/ratings.js`, `js/cards.js`, `js/admin-charts.js`

### React / Tauri Desktop App (frontend/)

| Layer | Technology |
|---|---|
| **Framework** | React 19 (Vite 8) |
| **Bundler** | Vite 8 |
| **Desktop Shell** | Tauri v2 (Rust) |
| **Linting** | ESLint 10 |
| **Routing** | react-router-dom 7 |
| **HTTP** | `apiClient.js` — fetch-based |
| **PWA** | `manifest.json` + `sw.js` |

**Components:** `Login.jsx`, `TeacherDashboard.jsx`, `App.jsx`, `ProtectedRoute.jsx`
**Models:** `AuthModel.js`, `TeacherModel.js`
**Controllers:** `useLoginController.js`, `useTeacherController.js`

---

## Backend Stack

### PHP Web Backend

| Component | Detail |
|---|---|
| **Server** | Apache via XAMPP |
| **PHP Version** | 8.x |
| **DB Driver** | PDO (MySQL) |
| **Auth** | Session-based (`$_SESSION`) with role locking |
| **Password Hashing** | bcrypt (`password_hash` / `password_verify`) |
| **No Composer** | Zero PHP dependencies — hand-rolled PDO, sessions, SMTP (fsockopen) |
| **SMTP Email** | Raw `fsockopen()` TLS on port 587 — no PHPMailer |

**Key files:**
- `includes/database/connection.php` — PDO singleton
- `includes/config/app.php` — constants, secrets, env vars
- `includes/auth.php` — session auth, role checks
- `includes/attendance/helper.php` — lecture sessions, recording logic

**API endpoints** (all under `api/`):
- `api/student.php` — student CRUD, enrollment, linking biometrics, email
- `api/teacher.php` — teacher CRUD, attendance reports
- `api/charts.php` — analytics data
- `api/attendance/rfid/record.php` — RFID attendance
- `api/attendance/fingerprint/record.php` — fingerprint attendance
- `api/attendance/face/` — face attendance
- `api/attendance/qr/record.php` — QR attendance
- `api/attendance/voice/record.php` — voice attendance
- `api/attendance/manual/record.php` — manual attendance
- `api/system_logs.php` — unified logging
- `api/esp_courses.php` — ESP32 course/student data sync
- `api/analytics.php` — per-student analytics

### Python Flask Backend (Raspberry Pi 3B)

| Component | Detail |
|---|---|
| **Framework** | Flask 3.x |
| **CORS** | flask-cors 4.x |
| **Face Recognition** | `face_recognition` (dlib HOG) |
| **Computer Vision** | OpenCV 4.x (via `opencv-python`) |
| **Numerical** | NumPy 1.24+ |
| **Camera (Pi)** | picamera2 (RPi OS only) |
| **System** | psutil |
| **Port** | 5000 |

**File:** `rpi_flask/app.py` (1086 lines)
- Face enrollment, recognition, attendance posting
- Video stream with annotation
- Heartbeat to web server
- OTA auto-update polling
- Camera health checks

---

## Firmware — ESP32

| Component | Detail |
|---|---|
| **Board** | ESP32 (devkit) |
| **Language** | C++ (Arduino framework 3.x) |
| **IDE** | Arduino IDE |
| **Filesystem** | LittleFS + SD card |
| **WiFi** | `WiFi.h` + `HTTPClient.h` |
| **Web Server** | Embedded `WebServer.h` (OTA commands, config download) |
| **NVRAM** | `Preferences.h` |

**File:** `esp32_firmware/esp32_firmware.ino` (3982 lines)

### Hardware Peripherals

| Component | Interface | Library |
|---|---|---|
| **FM10A Fingerprint** | UART (Serial2) | `Adafruit_Fingerprint.h` |
| **MFRC522 RFID** | SPI | `MFRC522.h` |
| **I2C LCD (16x2/20x4)** | I2C | `LiquidCrystal_I2C.h` |
| **HW-084 Sound** | Analog GPIO | none (raw ADC) |
| **4x4 Matrix Keypad** | GPIO matrix | none (manual scan) |
| **SD Card** | SPI | `SD.h` |
| **DS3231 RTC** | — | replaced by ESP32 onboard clock + NTP |

### Feature Flags

```cpp
#define USE_FINGERPRINT    // FM10A sensor
#define USE_RFID           // MFRC522 reader
#define USE_LCD            // I2C display
#define USE_HW084          // Sound sensor (clap detection)
#define USE_KEYPAD         // 4x4 matrix keypad
```

---

## AI / ML Models

### Client-side (face-api.js — TensorFlow.js)

Located in `models/` and `weights/` directories:

| Model | File(s) | Purpose |
|---|---|---|
| **SSD MobileNet v1** | `ssd_mobilenetv1_model-*` | Face detection (bounding boxes) |
| **Face Landmark 68** | `face_landmark_68_model-*` | Facial landmark alignment |
| **Face Recognition** | `face_recognition_model-*` (2 shards) | Face descriptor extraction |

Loaded by `js/face_recognition.js` via `faceapi.nets.ssdMobilenetv1.loadFromUri()`.

### Server-side (RPi — dlib)

| Model | Parameters | Purpose |
|---|---|---|
| **dlib HOG** | `face_model: "hog"` (config.json) | Face detection + 128-d encoding |
| HOG is ~1-2 FPS on Pi 3B; CNN option exists but is not usable |

---

## Infrastructure & Build Tools

| Tool | Purpose |
|---|---|
| **XAMPP** | Local dev server (Apache + MySQL + PHP) |
| **Vite 8** | React app bundler + dev server |
| **Tauri CLI v2** | Desktop app build (`cargo tauri build`) |
| **Rust / Cargo** | Tauri backend compilation |
| **Arduino IDE** | ESP32 firmware compilation + upload |
| **MySQL (MariaDB)** | Database — `csc2052` schema |
| **pip** | Python dependency management |
| **npm** | Node.js dependency management |

---

## Dependencies

### PHP (zero external — no Composer)
- **PDO** (built-in) — database
- **fsockopen** (built-in) — SMTP email

### Node.js (root package.json)
```json
{
  "react-router-dom": "^7.15.1",
  "sweetalert2": "^11.26.24"
}
```

### Node.js (frontend/package.json — React + Tauri)
```json
{
  "dependencies": {
    "react": "^19.2.6",
    "react-dom": "^19.2.6"
  },
  "devDependencies": {
    "@tauri-apps/cli": "^2",
    "vite": "^8.0.12",
    "@vitejs/plugin-react": "^6.0.1",
    "eslint": "^10.3.0"
  }
}
```

### Rust / Cargo (frontend/src-tauri/Cargo.toml)
```toml
tauri = "2"
tauri-plugin-opener = "2"
serde = "1" (with derive)
serde_json = "1"
```

### Python (rpi_flask/requirements.txt)
```
flask>=2.3
flask-cors>=4.0
opencv-python>=4.8
numpy>=1.24
face-recognition>=1.3
psutil>=5.9
picamera2  # RPi OS only: sudo apt install python3-picamera2
```

### ESP32 Arduino libraries
| Library | Source |
|---|---|
| `WiFi.h` | built-in ESP32 core |
| `HTTPClient.h` | built-in ESP32 core |
| `WebServer.h` | built-in ESP32 core |
| `Preferences.h` | built-in ESP32 core |
| `LittleFS.h` | built-in ESP32 core |
| `SD.h` | built-in ESP32 core |
| `Adafruit_Fingerprint.h` | Adafruit library |
| `MFRC522.h` | miguelbalboa library |
| `LiquidCrystal_I2C.h` | fdebrabander library |

### CDN (loaded at runtime by PHP pages)
| Library | Source |
|---|---|
| Bootstrap 5.3.0 (CSS + JS) | cdn.jsdelivr.net |
| Bootstrap Icons 1.11.3 | cdn.jsdelivr.net |
| Chart.js (UMD) | vendor file |
| SweetAlert2 11.x | vendor file |
| face-api.js | vendor file |
| Google Fonts (Inter) | fonts.googleapis.com |

---

## Architecture Overview

```
┌──────────────────────────────────────────────────┐
│                  Browser (User)                   │
│   PHP pages + JS + face-api.js + Chart.js        │
│   PWA (manifest.json + sw.js)                    │
└──────────┬──────────────┬──────────────┬──────────┘
           │ HTTP         │ WebSocket    │ HTTP
           ▼              │              ▼
┌──────────────────────┐  │  ┌────────────────────────────┐
│   XAMPP / Apache     │  │  │  Tauri v2 (React+Vite)     │
│   PHP 8 + PDO        │  │  │  Windows desktop app       │
│   MySQL (csc2052)    │  │  └────────────────────────────┘
└──────┬───────┬───────┘  │
       │       │          │
       │ HTTP  │ HTTP     │
       ▼       ▼          │
┌──────────┐ ┌────────────────────┐
│ ESP32    │ │ RPi 3B (Flask)     │
│ C++      │ │ Python + face_reco │
│ FM10A    │ │ OpenCV + picamera2 │
│ MFRC522  │ │ HOG @ 1-2 FPS      │
│ LCD+KP   │ └────────────────────┘
│ SD card  │
└──────────┘
```

## Config Files

| File | Purpose |
|---|---|
| `includes/config/app.php` | PHP constants, env vars, secrets |
| `rpi_flask/config.json` | RPi camera/threshold/OTA config |
| `esp32_firmware/esp32_firmware.ino` | ESP32 firmware (feature flags at top) |
| `frontend/src-tauri/tauri.conf.json` | Tauri desktop app config |
| `devices_registry.json` | Auto-generated — registered ESP32 devices |
| `sys_status_esp32.txt` | Auto-generated — ESP32 heartbeat timestamp |
| `sys_status_rpi.txt` | Auto-generated — RPi heartbeat timestamp |

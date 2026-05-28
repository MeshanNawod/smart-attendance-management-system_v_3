<img width="1536" height="365" alt="ChatGPT Image May 28, 2026, 09_05_45 PM" src="https://github.com/user-attachments/assets/06bff2bd-a05a-48f5-9c81-0df9cbaa22d5" />

# Sentinel Swarm AMS

A full-stack, multi-modal **Attendance Management System** that fuses IoT hardware (ESP32 + Raspberry Pi) with a PHP/React web dashboard.

## About

Sentinel Swarm is an academic project developed at **University of Peradeniya** that solves a real classroom problem: **taking attendance manually is slow, error-prone, and eats into lecture time.**

The system replaces manual roll-call with **autonomous hardware nodes** that detect students and record attendance in real time — using **face recognition** (Raspberry Pi with camera), **RFID cards** (ESP32 + RC522), **QR codes**, and **voice verification**. All devices report back to a central PHP server that manages courses, schedules, and generates reports. Administrators and teachers monitor everything through a web dashboard with live device status, attendance stats, and analytics charts.

### How It Works

1. A **lecture session** is started from the web dashboard — this activates attendance recording for a specific course.
2. **Hardware nodes** (RPi cameras, ESP32 RFID/QR scanners) deployed at classroom entrances detect students as they enter.
3. Each detection is posted to the PHP server's API, which validates the student's enrollment in the active session and records the timestamp.
4. Devices send a **heartbeat every 30 seconds** — the admin dashboard shows live online/offline status for every node.
5. **ESP32 nodes** can also receive **OTA firmware updates** pushed from the server, so firmware can be updated remotely without physical access.

### Why Three Detection Methods?

| Method          | Best for                                         |
|-----------------|--------------------------------------------------|
| Face Recognition | Hands-free, fastest throughput, no physical token needed |
| RFID            | Students already carry ID cards; simple tap      |
| QR Code         | Backup method — works with any smartphone screen |
| Voice           | Alternative for students without cards or visibility to camera |

All methods converge into the same attendance records, so a student can be marked present via any modality.

### Who It's For

- **Students** — View their own attendance record and punctuality
- **Teachers** — Start/stop lecture sessions, see who's present, rate courses
- **Administrators** — Manage users, courses, devices; monitor system health; push firmware updates; view analytics

---

## Architecture Overview

```
┌──────────────────────────────────────────────────────┐
│                    Web Browser                       │
│  (Admin / Teacher / Student dashboards)              │
└──────────┬──────────────────────────┬────────────────┘
           │ HTTP/JSON                │ HTTP/JSON
┌──────────▼───────────┐   ┌──────────▼─────────────────┐
│   PHP Server (API)   │   │   React SPA (frontend/)    │
│  ├─ student.php      │   │  (Login, Teacher Dashboard)│
│  ├─ attendance.php   │   └────────────────────────────┘
│  ├─ heartbeat.php    │
│  └─ 30+ more APIs    │
└──────────┬───────────┘
           │
    ┌──────┴──────┐
    │  MySQL DB   │
    └─────────────┘
           ▲ (heartbeat / attendance)
           │
┌──────────┴───────────────────┐     ┌─────────────────────┐
│   Raspberry Pi (Flask)       │     │   ESP32 Nodes       │
│  ├─ Face recognition         │     │  ├─ RFID scan       │
│  ├─ MJPEG stream             │     │  ├─ QR scan         │
│  ├─ Heartbeat → heartbeat.php│     │  ├─ Voice capture   │
│  └─ Attendance POST          │     │  └─ Heartbeat       │
└──────────────────────────────┘     └─────────────────────┘
```

---

## Features

### Attendance Capture
- **Face Recognition** – Raspberry Pi + `face_recognition` library detects known students and posts attendance automatically
- **RFID** – ESP32 nodes with RC522 readers scan RFID cards
- **QR Code** – Generate and scan QR codes via ESP32 or browser
- **Voice** – Voice module integration on ESP32
- **Manual** – Web dashboard with student/course selector
- **Cooldown** – Per-student, per-cooldown deduplication (configurable)

### Dashboards
- **Admin** – Device monitoring (SSE live updates), attendance stats, charts, student/teacher management, database viewer, logs, OTA firmware uploads
- **Teacher** – Course attendance, ratings/comments, per-session reports
- **Student** – Personal attendance record

### Device Management
- ESP32 nodes auto-register on first heartbeat
- Real-time online/offline status via `/api/heartbeat.php`
- OTA firmware updates proxied through the PHP server
- Device-to-course bindings (each device can be assigned to a course)

### Security
- Session-based auth with CSRF tokens
- Account lockout after failed attempts
- Password hashing (PHP `password_hash`)
- API key validation (`hardware_key`) for hardware endpoints

---

## Tech Stack

| Layer    | Technology                                      |
|----------|-------------------------------------------------|
| Backend  | PHP 8.x, PDO, MySQL/MariaDB                     |
| Frontend | React 19, Vite 8, React Router                  |
| IoT      | Flask (Python 3), OpenCV, face_recognition, picamera2 |
| Hardware | Raspberry Pi 3B+, ESP32, RC522, QR scanners, voice modules |

---

## Quick Start

### Requirements
- PHP 8.1+
- MySQL / MariaDB
- Node.js 20+ (for frontend)
- Composer (for PHP deps)
- A web server (Apache / Nginx)

### Setup

```bash
# 1. Clone the repository
git clone https://github.com/MeshanNawod/csc2052.git
cd csc2052

# 2. Import the database schema
mysql -u root -p csc2052 < sql/schema.sql
# Run any pending migrations from sql/migrations/

# 3. Configure the database connection
cp includes/config.example.php includes/config.php
# Edit includes/config.php with your DB credentials

# 4. (Optional) Build the React frontend
cd frontend
npm install
npm run build
cd ..

# 5. Serve the PHP app
# Point your web server document root to this directory
```

### Raspberry Pi Setup

```bash
# On your Raspberry Pi
pip install -r rpi_flask/requirements.txt

# Edit config
nano rpi_flask/config.json
# Set ota_server_url to your PHP server's base URL
# Adjust camera_type ("picamera" or "usb")

# Run the face-recognition service
python rpi_flask/app.py
```

### Configuration

Key settings in `rpi_flask/config.json`:

| Key                  | Default   | Description                              |
|----------------------|-----------|------------------------------------------|
| `ota_server_url`     | `null`    | PHP server base URL                      |
| `heartbeat_interval` | `30`      | Seconds between heartbeats               |
| `server_post_attendance` | `true` | Auto-post recognized faces to PHP server |
| `recognition_cache_ms` | `500`   | Cache recognition results (ms)           |
| `attendance_cooldown` | `5`      | Seconds before same student can re-scan  |
| `face_model`         | `hog`     | Recognition model: `hog` or `cnn`        |
| `threshold`          | `0.55`    | Face match confidence threshold          |

---

## API Endpoints

| Endpoint                      | Method | Description                              |
|-------------------------------|--------|------------------------------------------|
| `/api/heartbeat.php`          | GET    | Device heartbeat + registration          |
| `/api/student.php`            | POST   | Manual attendance, student search        |
| `/api/attendance.php`         | GET/POST | Manage lecture sessions, attendance records |
| `/api/face_scanned.php`       | POST   | Attendance via face recognition          |
| `/api/rfid_scanned.php`       | POST   | Attendance via RFID scan                 |
| `/api/qr_attendance.php`      | POST   | Attendance via QR code                   |
| `/api/voice.php`              | POST   | Attendance via voice capture             |
| `/api/device_password.php`    | POST   | Verify device passwords                  |
| `/api/esp_ota_proxy.php`      | GET    | Proxy OTA firmware to ESP32              |
| `/api/system_logs.php`        | POST   | Receive logs from ESP32 nodes            |
| `/api/voice_settings.php`     | POST   | Save voice settings                      |
| `/api/teacher.php`            | POST   | Teacher CRUD operations                  |
| `/api/charts.php`             | GET    | Attendance chart data                    |
| `/api/ratings.php`            | POST   | Course ratings & comments                |

---

## Directory Structure

```
├── api/                    # PHP REST API endpoints
├── css/                    # Stylesheets
├── includes/               # PHP helpers, configs, dashboard cards
│   ├── attendance_helper.php
│   ├── config/
│   ├── dashboard/
│   ├── database/
│   └── helpers/
├── js/                     # Frontend JavaScript
│   ├── main.js
│   ├── face_recognition.js
│   ├── attendance/
│   └── admin/
├── frontend/               # React SPA
├── rpi_flask/              # Flask face-recognition microservice
│   ├── app.py              # Main service (~1110 lines)
│   └── config.json         # RPi configuration
├── esp32_firmware/         # Arduino sketches for ESP32 nodes
├── sql/
│   ├── schema.sql
│   └── migrations/         # Incremental schema changes
└── uploads/students/       # Student face images
```

---

## License

This project is developed for academic purposes at University of Peradeniya.

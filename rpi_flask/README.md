# RPi Face Recognition Flask Service v2

Part of the Sentinel Swarm attendance system. Runs on Raspberry Pi 3B,
providing face recognition, MJPEG streaming, system management, and OTA
command polling.

## Quick Start

```bash
cd rpi_flask
bash setup.sh
sudo systemctl start rpi-face
```

## Manual Start

```bash
cd rpi_flask
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
python app.py
```

## API Endpoints

### Core
| Route | Method | Description |
|---|---|---|
| `/` | GET | Web status dashboard |
| `/api/status` | GET | Health check (uptime, camera, face count, threshold) |
| `/stream` | GET | Annotated MJPEG camera feed |
| `/api/snapshot` | GET | Single JPEG frame |
| `/api/face-status` | GET | Current face detection (multi-face, cooldown) |
| `/api/command` | POST | Execute command (`{"command": "..."}`) |
| `/api/sysinfo` | GET | CPU temp/usage, RAM, disk, uptime, face count |
| `/api/ota` | POST | Receive queued OTA commands |

### Management
| Route | Method | Description |
|---|---|---|
| `/api/faces` | GET | List all enrolled faces |
| `/api/faces/<student_no>` | DELETE | Delete a face |
| `/api/config` | GET/POST | Read/update configuration |
| `/api/reload` | POST | Reload config from file |
| `/api/restart` | POST | Restart Flask service |
| `/api/reboot` | POST | Reboot Pi |
| `/api/logs?n=50` | GET | Recent log lines |

### Legacy (ESP32-compatible `/cmd`)
| Route | Method | Description |
|---|---|---|
| `/cmd?command=SCANWIFI` | GET | Scan Wi-Fi networks |
| `/cmd?command=SCANBT` | GET | Scan Bluetooth devices |
| `/cmd?command=PING` | GET | Returns `PONG` |

## Commands (via `/api/command`)

| Command | Description |
|---|---|
| `ENROLL:S12345` | Capture & enroll face (averages multiple frames) |
| `ENROLL:S12345\|John Doe` | Enroll with display name |
| `DELETE_FACE:S12345` | Delete enrolled face |
| `SETCONFIG:THRESHOLD:55` | Set match threshold (40-90) |
| `SETCONFIG THRESHOLD=0.55,ENROLL_CAPTURES=5` | Multiple config SETCONFIG |
| `SETWIFI:MySSID\|mypassword\|identity` | Configure Wi-Fi |
| `RESTART_SERVICE` | Restart Flask service |
| `REBOOT` | Reboot Pi |
| `RELOAD_CONFIG` | Reload config.json |
| `REBUILD_ENCODINGS` | Rebuild face encoding cache |

## Stream Annotations

The MJPEG stream draws green rectangles around recognized faces and orange
for unknown faces, with names and confidence percentages.

## Attendance Cooldown

The `attendance_cooldown` (default 5s) prevents repeated marking of the
same student. The `face-status` endpoint returns `cooldown_remaining` and
`on_cooldown` fields.

## Face Enrollment

Enrollment captures multiple frames (configurable via `enroll_captures`),
checks that exactly one face is visible, and averages the encodings for
better accuracy.

## Configuration

Edit `config.json`:

```json
{
  "threshold": 0.55,
  "camera_id": 0,
  "camera_width": 640,
  "camera_height": 480,
  "ota_server_url": "http://YOUR_SERVER_IP",
  "ota_device_name": "rpi",
  "ota_api_key": "ss_hw_api_key_2052",
  "enroll_captures": 3,
  "attendance_cooldown": 5,
  "stream_annotate": true,
  "stream_show_confidence": true,
  "log_max_lines": 500,
  "face_model": "hog"
}
```

### `face_model`
- `hog` — faster, good for Pi 3B (default)
- `cnn` — more accurate but slower (use on Pi 4+)

Changes take effect after `POST /api/reload` or service restart.

## File Structure

```
rpi_flask/
├── app.py              # Flask service
├── config.json         # Configuration
├── requirements.txt    # Dependencies
├── setup.sh            # One-command install + systemd
├── README.md
├── .gitignore
└── face_data/
    ├── _index.json     # Face index
    └── *.npy           # Face encodings
```

## Service Management

```bash
sudo systemctl start/stop/restart rpi-face
sudo systemctl status rpi-face
journalctl -u rpi-face -f     # tail logs
```

## Dependencies

- Python 3.7+
- OpenCV 4.x
- face_recognition (dlib)
- Flask + flask-cors
- numpy
- psutil

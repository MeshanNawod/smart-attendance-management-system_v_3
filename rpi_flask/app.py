import io, json, os, subprocess, sys, time, uuid, warnings, logging, threading, re, signal
from datetime import datetime, timedelta
from pathlib import Path
from collections import OrderedDict

warnings.filterwarnings("ignore")
logging.basicConfig(level=logging.INFO, format="[%(asctime)s] %(levelname)s: %(message)s")
log = logging.getLogger(__name__)

try:
    import cv2
    import numpy as np
    from flask import Flask, jsonify, request, Response, render_template_string, stream_with_context
    from flask_cors import CORS
except ImportError as e:
    log.error("Missing dependency: %s. Run: pip install flask flask-cors opencv-python numpy", e)
    sys.exit(1)

FACE_W, FACE_H = 64, 64

BASE_DIR = Path(__file__).resolve().parent
FACE_DIR = BASE_DIR / "face_data"
FACE_INDEX = FACE_DIR / "_index.json"
CONFIG_FILE = BASE_DIR / "config.json"
FACE_DIR.mkdir(parents=True, exist_ok=True)

try:
    _cv2_data_path = cv2.data.haarcascades
except AttributeError:
    _cv2_data_path = None
_haar_paths = [
    _cv2_data_path + "haarcascade_frontalface_default.xml" if _cv2_data_path else None,
    "/usr/share/opencv4/haarcascades/haarcascade_frontalface_default.xml",
    "/usr/local/share/opencv4/haarcascades/haarcascade_frontalface_default.xml",
]
face_cascade = cv2.CascadeClassifier()
for p in _haar_paths:
    if p is None:
        continue
    cc = cv2.CascadeClassifier(p)
    if not cc.empty():
        face_cascade = cc
        break
if face_cascade.empty():
    log.error("Haar Cascade not found — searched: %s", [p for p in _haar_paths if p])
    sys.exit(1)

DEFAULT_CONFIG = OrderedDict([
    ("threshold", 0.45),
    ("camera_type", "usb"),
    ("camera_id", 0),
    ("camera_width", 640),
    ("camera_height", 480),
    ("camera_fps", 30),
    ("flask_port", 5000),
    ("flask_host", "0.0.0.0"),
    ("ota_server_url", None),
    ("ota_device_name", "rpi"),
    ("ota_poll_interval", 10),
    ("ota_api_key", None),
    ("enroll_captures", 3),
    ("attendance_cooldown", 5),
    ("stream_annotate", True),
    ("stream_show_confidence", True),
    ("log_max_lines", 500),
])

config = DEFAULT_CONFIG.copy()
if CONFIG_FILE.exists():
    try:
        with open(CONFIG_FILE) as f:
            user = json.load(f)
            for k in user:
                if k in config:
                    config[k] = user[k]
                elif k not in ("__comment",):
                    config[k] = user[k]
    except Exception as e:
        log.warning("Config load failed: %s", e)

THRESHOLD = float(config["threshold"])
CAMERA_TYPE = config.get("camera_type", "usb")
CAMERA_ID = int(config["camera_id"])
CAMERA_W = int(config["camera_width"])
CAMERA_H = int(config["camera_height"])
FLASK_PORT = int(config["flask_port"])
FLASK_HOST = config["flask_host"]
OTA_SERVER = config.get("ota_server_url")
OTA_DEVICE = config.get("ota_device_name", "rpi")
OTA_KEY = config.get("ota_api_key")
OTA_INTERVAL = int(config.get("ota_poll_interval", 10))
ENROLL_CAPTURES = int(config.get("enroll_captures", 3))
ATTENDANCE_COOLDOWN = int(config.get("attendance_cooldown", 5))
STREAM_ANNOTATE = bool(config.get("stream_annotate", True))
STREAM_CONFIDENCE = bool(config.get("stream_show_confidence", True))
LOG_MAX = int(config.get("log_max_lines", 500))
HEARTBEAT_INTERVAL = int(config.get("heartbeat_interval", 30))
SERVER_POST_ATTENDANCE = bool(config.get("server_post_attendance", True))
RECOGNITION_CACHE_MS = int(config.get("recognition_cache_ms", 500))

RESTART_PENDING = False
REBOOT_PENDING = False
SERVICE_START_TIME = time.time()

_last_recognition = {"results": [], "time": 0, "frame": None}
_last_attendance_post = {}

_log_buf = []
_log_lock = threading.Lock()

class BufHandler(logging.Handler):
    def emit(self, record):
        msg = self.format(record)
        with _log_lock:
            _log_buf.append(msg)
            if len(_log_buf) > LOG_MAX:
                _log_buf.pop(0)

_buf_handler = BufHandler()
_buf_handler.setFormatter(logging.Formatter("[%(asctime)s] %(levelname)s: %(message)s"))
logging.getLogger().addHandler(_buf_handler)

_index_lock = threading.Lock()
_known_vectors = []
_known_names = []
_known_student_nos = []
_encodings_dirty = True
_attendance_cooldowns = {}

def _load_index():
    if FACE_INDEX.exists():
        with open(FACE_INDEX) as f:
            return json.load(f)
    return {}

def _save_index(idx):
    tmp = FACE_INDEX.with_suffix(".tmp")
    with open(tmp, "w") as f:
        json.dump(idx, f, indent=2)
    tmp.replace(FACE_INDEX)

def _face_path(student_no):
    safe = re.sub(r"[^a-zA-Z0-9_\-]", "_", str(student_no))
    return FACE_DIR / f"{safe}.npy"

def _rebuild_encodings():
    global _known_vectors, _known_names, _known_student_nos, _encodings_dirty
    idx = _load_index()
    vectors, names, student_nos = [], [], []
    for sid, entry in idx.items():
        fp = _face_path(sid)
        if fp.exists():
            try:
                arr = np.load(fp)
                vectors.append(arr)
                names.append(entry.get("name", sid))
                student_nos.append(sid)
            except Exception as e:
                log.warning("Failed to load %s: %s", fp, e)
        else:
            log.warning("Index entry %s has no file, removing", sid)
            del idx[sid]
    if list(idx.keys()) != list(_load_index().keys()):
        _save_index(idx)
    with _index_lock:
        _known_vectors = vectors
        _known_names = names
        _known_student_nos = student_nos
        _encodings_dirty = False
    log.info("Loaded %d face(s) for %d student(s)", len(vectors), len(student_nos))

def _mark_dirty():
    global _encodings_dirty
    _encodings_dirty = True

def _get_face_count():
    return len(_load_index())

def _check_cooldown(student_no):
    now = time.time()
    last = _attendance_cooldowns.get(student_no, 0)
    if now - last < ATTENDANCE_COOLDOWN:
        return last + ATTENDANCE_COOLDOWN - now
    _attendance_cooldowns[student_no] = now
    return 0

def _detect_faces(gray):
    faces = face_cascade.detectMultiScale(gray, scaleFactor=1.1, minNeighbors=5, minSize=(80, 80))
    return faces

def _extract_face_vector(gray, face_rect):
    x, y, w, h = face_rect
    face = gray[y:y+h, x:x+w]
    face = cv2.resize(face, (FACE_W, FACE_H))
    return face.flatten().astype(np.float32)

def _normalize_vec(v):
    norm = np.linalg.norm(v)
    if norm > 0:
        return v / norm
    return v

def _compare_faces(vec, known_vecs):
    if not known_vecs:
        return None, 0.0
    kn = np.array(known_vecs)
    dots = np.dot(kn, vec)
    best_idx = int(np.argmax(dots))
    score = float(dots[best_idx])
    score = max(0.0, min(1.0, score))
    return best_idx, score

_camera = None
_camera_lock = threading.Lock()
_camera_ok = False
_camera_alt_tried = False
_picam2 = None

def _camera_open():
    global _camera, _camera_ok, _camera_alt_tried, _picam2
    with _camera_lock:
        if _camera is not None:
            return True
        if CAMERA_TYPE == "picamera":
            try:
                from picamera2 import Picamera2
                _picam2 = Picamera2()
                video_config = _picam2.create_video_configuration(
                    main={"size": (CAMERA_W, CAMERA_H)},
                    controls={"FrameRate": int(config.get("camera_fps", 30))} if int(config.get("camera_fps", 30)) <= 30 else {},
                )
                _picam2.configure(video_config)
                _picam2.start()
                for _ in range(5):
                    _picam2.capture_array()
                    time.sleep(0.05)
                _camera = True
                _camera_ok = True
                log.info("Pi Camera (CSI) opened (%dx%d)", CAMERA_W, CAMERA_H)
                return True
            except ImportError:
                log.error("picamera2 not installed. Run: sudo apt install python3-picamera2")
                _camera = None
                _camera_ok = False
                return False
            except Exception as e:
                log.error("Pi Camera error: %s", e)
                _camera = None
                _camera_ok = False
                return False
        for attempt in range(3):
            cid = CAMERA_ID
            if attempt > 0 and not _camera_alt_tried:
                cid = 0 if CAMERA_ID != 0 else 1
                _camera_alt_tried = True
                log.info("Trying alternate camera %d", cid)
            try:
                cap = cv2.VideoCapture(cid, cv2.CAP_V4L2 if os.name != "nt" else cv2.CAP_DSHOW)
                cap.set(cv2.CAP_PROP_FRAME_WIDTH, CAMERA_W)
                cap.set(cv2.CAP_PROP_FRAME_HEIGHT, CAMERA_H)
                cap.set(cv2.CAP_PROP_FPS, int(config.get("camera_fps", 30)))
                cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
                if not cap.isOpened():
                    cap.release()
                    cap = None
                    continue
                _camera = cap
                _camera_ok = True
                log.info("USB camera %d opened (%dx%d)", cid, CAMERA_W, CAMERA_H)
                return True
            except Exception as e:
                log.warning("Camera %d attempt %d: %s", cid, attempt, e)
                if cap:
                    cap.release()
        log.error("All camera attempts failed")
        return False

def _camera_close():
    global _camera, _camera_ok, _picam2
    with _camera_lock:
        if CAMERA_TYPE == "picamera":
            if _picam2 is not None:
                try:
                    _picam2.stop()
                except:
                    pass
                _picam2 = None
            _camera = None
            _camera_ok = False
        else:
            if _camera is not None:
                try:
                    _camera.release()
                except:
                    pass
                _camera = None
                _camera_ok = False

def _camera_read():
    global _camera_ok
    with _camera_lock:
        if _camera is None:
            if not _camera_open():
                return None
        try:
            if CAMERA_TYPE == "picamera":
                if _picam2 is None:
                    return None
                frame = _picam2.capture_array()
                if frame is None or frame.size == 0:
                    _camera_ok = False
                    _camera_close()
                    return None
                frame = cv2.cvtColor(frame, cv2.COLOR_RGB2BGR)
                _camera_ok = True
                return frame
            else:
                ret, frame = _camera.read()
                if not ret:
                    _camera_ok = False
                    _camera_close()
                    return None
                _camera_ok = True
                return frame
        except Exception as e:
            log.warning("Camera read error: %s", e)
            _camera_ok = False
            _camera_close()
            return None

_last_camera_check = 0
def _camera_health_check():
    global _last_camera_check, _camera_ok, _camera
    now = time.time()
    if now - _last_camera_check < 15:
        return _camera_ok
    _last_camera_check = now
    if _camera is None and not _camera_ok:
        log.info("Camera health: attempting reopen...")
        _camera_open()
    elif _camera is None and _camera_ok:
        _camera_ok = False
    return _camera_ok

def _camera_set_prop(prop, val):
    with _camera_lock:
        if _camera is not None:
            try:
                _camera.set(prop, val)
            except:
                pass

def _face_rect_to_tblr(x, y, w, h):
    return (y, x + w, y + h, x)

def _recognize_faces(frame, force=False):
    global _last_recognition, _encodings_dirty
    now_ms = time.time() * 1000
    if not force and (now_ms - _last_recognition["time"]) < RECOGNITION_CACHE_MS:
        return _last_recognition["results"]
    if _encodings_dirty:
        _rebuild_encodings()
    gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
    faces = _detect_faces(gray)
    results = []
    for (x, y, w, h) in faces:
        vec = _extract_face_vector(gray, (x, y, w, h))
        vec = _normalize_vec(vec)
        best_idx, score = _compare_faces(vec, _known_vectors)
        known = score >= THRESHOLD and best_idx is not None
        tblr = _face_rect_to_tblr(x, y, w, h)
        if known:
            results.append({
                "face_detected": True,
                "student_no": _known_student_nos[best_idx],
                "student_name": _known_names[best_idx],
                "confidence": round(score, 4),
                "bbox": list(map(int, tblr)),
            })
        else:
            results.append({
                "face_detected": True,
                "student_no": None,
                "student_name": "Unknown",
                "confidence": round(score, 4),
                "bbox": list(map(int, tblr)),
            })
    _last_recognition = {"results": results, "time": now_ms, "frame": frame}
    if SERVER_POST_ATTENDANCE and OTA_SERVER:
        for r in results:
            if r["student_no"] and r["student_no"] not in _last_attendance_post:
                _last_attendance_post[r["student_no"]] = time.time()
                _post_attendance(r["student_no"])
            elif r["student_no"] and time.time() - _last_attendance_post.get(r["student_no"], 0) > ATTENDANCE_COOLDOWN:
                _last_attendance_post[r["student_no"]] = time.time()
                _post_attendance(r["student_no"])
    return results

def _annotate_frame(frame, results):
    if not STREAM_ANNOTATE:
        return frame
    for r in results:
        top, right, bottom, left = r["bbox"]
        name = r["student_name"] or "Unknown"
        known = r["student_no"] is not None
        color = (0, 255, 0) if known else (0, 165, 255)
        cv2.rectangle(frame, (left, top), (right, bottom), color, 2)
        label = name
        if STREAM_CONFIDENCE and r["confidence"] > 0:
            pct = int(r["confidence"] * 100)
            label += f" ({pct}%)"
        (tw, th), _ = cv2.getTextSize(label, cv2.FONT_HERSHEY_SIMPLEX, 0.5, 1)
        cv2.rectangle(frame, (left, top - th - 6), (left + tw + 6, top), color, -1)
        cv2.putText(frame, label, (left + 3, top - 3), cv2.FONT_HERSHEY_SIMPLEX,
                    0.5, (0, 0, 0), 1, cv2.LINE_AA)
    return frame

app = Flask(__name__)
CORS(app)

def json_ok(data=None, message="ok"):
    resp = {"status": "success", "message": message}
    if data:
        resp.update(data)
    return jsonify(resp)

def json_error(message, code=400):
    return jsonify({"status": "error", "message": str(message)}), code

@app.route("/api/status", methods=["GET"])
def api_status():
    uptime_s = int(time.time() - SERVICE_START_TIME)
    d, r = divmod(uptime_s, 86400)
    h, m = divmod(r, 3600)
    m //= 60
    return jsonify({
        "status": "running",
        "service": "running",
        "uptime": f"{d}d {h}h {m}m" if d else f"{h}h {m}m",
        "uptime_seconds": uptime_s,
        "camera_ok": _camera_ok,
        "faces_enrolled": _get_face_count(),
        "threshold": round(THRESHOLD, 2),
        "threshold_pct": int(THRESHOLD * 100),
        "face_model": "haar_cascade",
        "timestamp": datetime.now().isoformat(),
        "version": "2.1",
    })

def _mjpeg_generator():
    while True:
        _camera_health_check()
        frame = _camera_read()
        if frame is None:
            time.sleep(0.2)
            continue
        results = _recognize_faces(frame)
        if STREAM_ANNOTATE:
            frame = _annotate_frame(frame, results)
        ret, jpeg = cv2.imencode(".jpg", frame, [cv2.IMWRITE_JPEG_QUALITY, 75])
        if not ret:
            continue
        yield (b"--frame\r\n"
               b"Content-Type: image/jpeg\r\n\r\n" + jpeg.tobytes() + b"\r\n")
        time.sleep(0.03)

@app.route("/stream")
def stream():
    return Response(
        _mjpeg_generator(),
        mimetype="multipart/x-mixed-replace; boundary=frame",
        headers={"Cache-Control": "no-cache, no-store, must-revalidate",
                 "Pragma": "no-cache", "Expires": "0"},
    )

@app.route("/api/snapshot", methods=["GET"])
def api_snapshot():
    frame = _camera_read()
    if frame is None:
        return json_error("Camera unavailable", 503)
    results = _recognize_faces(frame)
    if STREAM_ANNOTATE:
        frame = _annotate_frame(frame, results)
    ret, jpeg = cv2.imencode(".jpg", frame, [cv2.IMWRITE_JPEG_QUALITY, 85])
    if not ret:
        return json_error("JPEG encode failed", 500)
    return Response(jpeg.tobytes(), mimetype="image/jpeg")

@app.route("/api/face-status", methods=["GET"])
def api_face_status():
    _camera_health_check()
    frame = _camera_read()
    if frame is None:
        return jsonify({"face_detected": False, "faces": [], "camera_ok": False})
    results = _recognize_faces(frame)
    if not results:
        return jsonify({"face_detected": False, "faces": [], "camera_ok": True})
    primary = results[0]
    primary["cooldown_remaining"] = 0
    if primary["student_no"]:
        cd = _check_cooldown(primary["student_no"])
        primary["cooldown_remaining"] = cd
        if cd > 0:
            primary["on_cooldown"] = True
        else:
            primary["on_cooldown"] = False
    return jsonify({
        "face_detected": True,
        "faces": results,
        "student_no": primary["student_no"],
        "student_name": primary["student_name"],
        "confidence": primary["confidence"],
        "bbox": primary["bbox"],
        "cooldown_remaining": primary.get("cooldown_remaining", 0),
        "on_cooldown": primary.get("on_cooldown", False),
        "camera_ok": True,
    })

def _enroll_face(student_no):
    captures = []
    log.info("Enrolling %s — capturing %d frame(s)", student_no, ENROLL_CAPTURES)
    for i in range(ENROLL_CAPTURES):
        for _ in range(10):
            frame = _camera_read()
            if frame is not None:
                break
            time.sleep(0.1)
        if frame is None:
            return "Camera unavailable during capture"
        gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
        faces = _detect_faces(gray)
        if len(faces) == 0:
            if i == 0:
                return "No face detected — look at the camera"
            continue
        if len(faces) > 1:
            return "Multiple faces detected — only one person at a time"
        vec = _extract_face_vector(gray, faces[0])
        captures.append(vec)
        log.info("  capture %d/%d: face found", i + 1, ENROLL_CAPTURES)
        time.sleep(0.3)
    if not captures:
        return "Could not capture any valid face"
    avg_vec = np.mean(captures, axis=0)
    avg_vec = _normalize_vec(avg_vec)
    fp = _face_path(student_no)
    np.save(fp, avg_vec)
    idx = _load_index()
    idx[student_no] = {
        "name": student_no,
        "enrolled_at": datetime.now().isoformat(),
        "captures": len(captures),
        "vec_dim": int(avg_vec.shape[0]),
    }
    _save_index(idx)
    _mark_dirty()
    log.info("Enrolled %s (%d captures averaged)", student_no, len(captures))
    return f"Enrolled {student_no} ({len(captures)} captures)"

def _enroll_face_with_name(args):
    parts = args.split("|", 1)
    sid = parts[0].strip()
    name = parts[1].strip() if len(parts) > 1 else sid
    result = _enroll_face(sid)
    idx = _load_index()
    if sid in idx:
        idx[sid]["name"] = name
        _save_index(idx)
    return result

def _delete_face(student_no):
    fp = _face_path(student_no)
    deleted_file = False
    if fp.exists():
        fp.unlink()
        deleted_file = True
    idx = _load_index()
    existed = idx.pop(student_no, None)
    if existed or deleted_file:
        _save_index(idx)
        _mark_dirty()
        log.info("Deleted face for %s", student_no)
        return f"Deleted face for {student_no}"
    return f"No face found for {student_no}"

def _set_threshold(val):
    global THRESHOLD
    try:
        v = float(val)
        if ":" in str(val):
            v = float(str(val).split(":")[-1])
    except (ValueError, TypeError):
        try:
            v = float(val)
        except:
            return f"Invalid threshold: {val}"
    v = max(0.1, min(0.99, v / 100.0 if v > 1 else v))
    THRESHOLD = v
    config["threshold"] = v
    _save_config()
    log.info("Threshold set to %.2f", v)
    return f"Threshold set to {int(v * 100)}%"

def _restart_service():
    global RESTART_PENDING
    RESTART_PENDING = True
    threading.Thread(target=lambda: (time.sleep(1), os.execv(sys.executable, [sys.executable] + sys.argv)),
                     daemon=True).start()
    return "Restarting service..."

def _reboot_pi():
    global REBOOT_PENDING
    REBOOT_PENDING = True
    threading.Thread(target=lambda: (time.sleep(2), os.system("sudo reboot")), daemon=True).start()
    return "Rebooting Pi in 2s..."

def _set_wifi(args):
    parts = args.split("|", 2)
    ssid = parts[0].strip()
    psk = parts[1].strip() if len(parts) > 1 else ""
    identity = parts[2].strip() if len(parts) > 2 else ""
    if identity:
        return "Enterprise Wi-Fi: update /etc/wpa_supplicant/wpa_supplicant.conf manually"
    try:
        if psk:
            r = subprocess.run(["sudo", "nmcli", "dev", "wifi", "connect", ssid,
                                "password", psk], capture_output=True, text=True, timeout=30)
        else:
            r = subprocess.run(["sudo", "nmcli", "dev", "wifi", "connect", ssid],
                               capture_output=True, text=True, timeout=30)
        if r.returncode == 0:
            log.info("Wi-Fi connected to %s", ssid)
            return f"Connected to {ssid}"
        else:
            return f"Wi-Fi failed: {r.stderr.strip()}"
    except Exception as e:
        return f"Wi-Fi error: {e}"

def _save_config():
    with open(CONFIG_FILE, "w") as f:
        json.dump(config, f, indent=2)

def _reload_config():
    global THRESHOLD, ENROLL_CAPTURES, ATTENDANCE_COOLDOWN, STREAM_ANNOTATE, STREAM_CONFIDENCE
    if not CONFIG_FILE.exists():
        return "No config file"
    with open(CONFIG_FILE) as f:
        user = json.load(f)
    for k in user:
        config[k] = user[k]
    THRESHOLD = float(config["threshold"])
    ENROLL_CAPTURES = int(config.get("enroll_captures", 3))
    ATTENDANCE_COOLDOWN = int(config.get("attendance_cooldown", 5))
    STREAM_ANNOTATE = bool(config.get("stream_annotate", True))
    STREAM_CONFIDENCE = bool(config.get("stream_show_confidence", True))
    return "Config reloaded"

_COMMANDS = OrderedDict([
    ("ENROLL:", lambda a: _enroll_face_with_name(a) if "|" in a else _enroll_face(a)),
    ("DELETE_FACE:", _delete_face),
    ("SETCONFIG:THRESHOLD:", _set_threshold),
    ("SETCONFIG:THRESHOLD", _set_threshold),
    ("SETCONFIG", lambda a: _handle_setconfig(a)),
    ("RESTART_SERVICE", lambda _: _restart_service()),
    ("REBOOT", lambda _: _reboot_pi()),
    ("SETWIFI:", _set_wifi),
    ("SETWIFI ", _set_wifi),
    ("RELOAD_CONFIG", lambda _: _reload_config()),
    ("REBUILD_ENCODINGS", lambda _: (_rebuild_encodings(), "Encodings rebuilt")),
])

def _handle_setconfig(args):
    if not args:
        return "No SETCONFIG params"
    parts = re.split(r"[,;]", args)
    results = []
    for part in parts:
        part = part.strip()
        if "=" in part:
            k, v = part.split("=", 1)
            k, v = k.strip().upper(), v.strip()
            if k == "THRESHOLD":
                results.append(_set_threshold(v))
            elif k == "ENROLL_CAPTURES":
                ENROLL_CAPTURES = max(1, int(v))
                config["enroll_captures"] = ENROLL_CAPTURES
                _save_config()
                results.append(f"enroll_captures={ENROLL_CAPTURES}")
            elif k == "ATTENDANCE_COOLDOWN":
                ATTENDANCE_COOLDOWN = max(1, int(v))
                config["attendance_cooldown"] = ATTENDANCE_COOLDOWN
                _save_config()
                results.append(f"attendance_cooldown={ATTENDANCE_COOLDOWN}s")
            elif k == "STREAM_ANNOTATE":
                STREAM_ANNOTATE = v in ("1", "true", "yes")
                config["stream_annotate"] = STREAM_ANNOTATE
                results.append(f"annotate={STREAM_ANNOTATE}")
            elif k == "STREAM_CONFIDENCE":
                STREAM_CONFIDENCE = v in ("1", "true", "yes")
                config["stream_show_confidence"] = STREAM_CONFIDENCE
                results.append(f"show_conf={STREAM_CONFIDENCE}")
            else:
                results.append(f"Unknown key: {k}")
        else:
            results.append(f"Bad format: {part}")
    return "; ".join(results)

@app.route("/api/command", methods=["POST"])
def api_command():
    data = request.get_json(silent=True) or request.form or {}
    cmd_str = (data.get("command") or "").strip()
    if not cmd_str:
        return json_error("Missing 'command' field")
    output, status = "", "ok"
    matched = False
    for prefix, handler in _COMMANDS.items():
        if cmd_str == prefix or cmd_str.startswith(prefix):
            arg = cmd_str[len(prefix):].strip() if cmd_str.startswith(prefix) else ""
            try:
                output = handler(arg)
            except Exception as e:
                output = f"Error: {e}"
                status = "error"
            matched = True
            break
    if not matched:
        output = f"Unknown command: {cmd_str}"
        status = "error"
    return jsonify({"status": status, "message": output, "output": output})

_sysinfo_cache = {}
_sysinfo_ts = 0

def _update_sysinfo():
    global _sysinfo_cache, _sysinfo_ts
    now = time.time()
    if now - _sysinfo_ts < 3:
        return _sysinfo_cache
    info = {}
    try:
        import psutil
        info["cpu_usage"] = f"{psutil.cpu_percent(interval=0.3)}%"
        mem = psutil.virtual_memory()
        info["ram_free"] = f"{mem.available // (1024*1024)}MB"
        info["ram_total"] = f"{mem.total // (1024*1024)}MB"
        info["ram_pct"] = mem.percent
        uptime_s = int(time.time() - psutil.boot_time())
        d, r = divmod(uptime_s, 86400)
        h, m = divmod(r, 3600)
        m //= 60
        info["uptime"] = f"{d}d {h}h {m}m" if d else f"{h}h {m}m"
        info["uptime_raw"] = uptime_s
        disk = psutil.disk_usage("/")
        info["disk_free"] = f"{disk.free // (1024*1024)}MB"
        info["disk_pct"] = disk.percent
    except ImportError:
        info["cpu_usage"] = info.get("cpu_usage", "—")
        info["ram_free"] = info.get("ram_free", "—")
        info["uptime"] = info.get("uptime", "—")
    info["cpu_temp"] = "—"
    try:
        t = subprocess.run(["vcgencmd", "measure_temp"], capture_output=True, text=True, timeout=3)
        if t.returncode == 0:
            info["cpu_temp"] = t.stdout.strip().replace("temp=", "")
    except:
        try:
            for p in ["/sys/class/thermal/thermal_zone0/temp"]:
                if Path(p).exists():
                    raw = int(Path(p).read_text().strip()) / 1000.0
                    info["cpu_temp"] = f"{raw:.1f}'C"
                    break
        except:
            pass
    info["face_count"] = str(_get_face_count())
    info["service"] = "running"
    uptime_svc = int(time.time() - SERVICE_START_TIME)
    d, r = divmod(uptime_svc, 86400)
    h, m = divmod(r, 3600)
    m //= 60
    info["service_uptime"] = f"{d}d {h}h {m}m" if d else f"{h}h {m}m"
    info["threshold_pct"] = int(THRESHOLD * 100)
    info["camera_ok"] = _camera_ok
    info["faces_loaded"] = len(_known_student_nos)
    info["timestamp"] = datetime.now().isoformat()
    _sysinfo_cache = info
    _sysinfo_ts = now
    return info

@app.route("/api/sysinfo", methods=["GET"])
def api_sysinfo():
    return jsonify(_update_sysinfo())

@app.route("/api/ota", methods=["POST"])
def api_ota():
    data = request.get_json(silent=True) or request.form or {}
    commands = data.get("commands") or data.get("command") or ""
    if not commands:
        return json_error("No commands")
    count = 0
    for cmdline in commands.replace("\\n", "\n").split("\n"):
        cmdline = cmdline.strip()
        if cmdline:
            log.info("OTA received: %s", cmdline)
            threading.Thread(target=_handle_ota_cmd, args=(cmdline,), daemon=True).start()
            count += 1
    return json_ok(message=f"Queued {count} command(s)")

def _handle_ota_cmd(cmdline):
    if cmdline.startswith("SETCONFIG "):
        _handle_setconfig(cmdline[10:].strip())
    elif cmdline.startswith("SETWIFI "):
        _set_wifi(cmdline[8:].strip())
    elif cmdline.startswith("START_COURSE "):
        log.info("Course started: %s", cmdline[13:].strip())
    elif cmdline.startswith("SETCOURSES "):
        log.info("Courses set: %s", cmdline[11:].strip())
    elif cmdline == "RESTART_SERVICE":
        _restart_service()
    elif cmdline == "REBOOT":
        _reboot_pi()
    elif cmdline == "RELOAD_CONFIG":
        _reload_config()
    elif cmdline == "REBUILD_ENCODINGS":
        _rebuild_encodings()
    else:
        log.info("Unhandled OTA cmd: %s", cmdline)

@app.route("/cmd", methods=["GET"])
def cmd_legacy():
    cmd = (request.args.get("command") or "").strip()
    if cmd == "SCANWIFI":
        try:
            out = subprocess.run(["sudo", "iwlist", "scan"], capture_output=True, text=True, timeout=20)
            essids = []
            seen = set()
            for line in out.stdout.split("\n"):
                m = re.search(r'ESSID:"(.+?)"', line)
                if m and m.group(1) not in seen:
                    seen.add(m.group(1))
                    essids.append(m.group(1))
            return jsonify({"networks": essids, "count": len(essids)})
        except subprocess.TimeoutExpired:
            return jsonify({"error": "Wi-Fi scan timed out"}), 500
        except Exception as e:
            return jsonify({"error": str(e)}), 500
    if cmd == "SCANBT":
        try:
            out = subprocess.run(["hcitool", "scan"], capture_output=True, text=True, timeout=10)
            devices = []
            for line in out.stdout.split("\n")[1:]:
                parts = line.strip().split("\t", 1)
                if len(parts) == 2:
                    devices.append({"name": parts[1].strip(), "mac": parts[0].strip(), "type": "BLE"})
            return jsonify({"devices": devices, "count": len(devices)})
        except subprocess.TimeoutExpired:
            return jsonify({"error": "BT scan timed out"}), 500
        except Exception as e:
            return jsonify({"error": str(e)}), 500
    if cmd == "PING":
        return "PONG"
    if cmd.startswith("SETCONFIG "):
        out = _handle_setconfig(cmd[10:].strip())
        return jsonify({"status": "success", "output": out})
    return jsonify({"error": f"Unknown command: {cmd}"}), 400

@app.route("/api/logs", methods=["GET"])
def api_logs():
    n = request.args.get("n", 50, type=int)
    n = max(1, min(n, LOG_MAX))
    with _log_lock:
        lines = _log_buf[-n:]
    return jsonify({"logs": lines, "count": len(lines)})

@app.route("/api/faces", methods=["GET"])
def api_faces():
    idx = _load_index()
    faces = []
    for sid, entry in idx.items():
        faces.append({
            "student_no": sid,
            "name": entry.get("name", sid),
            "enrolled_at": entry.get("enrolled_at", "unknown"),
            "vec_dim": entry.get("vec_dim", "unknown"),
        })
    return jsonify({"faces": faces, "count": len(faces)})

@app.route("/api/faces/<student_no>", methods=["DELETE"])
def api_delete_face(student_no):
    out = _delete_face(student_no)
    return jsonify({"status": "success" if "Deleted" in out else "error", "message": out})

@app.route("/api/config", methods=["GET"])
def api_get_config():
    return jsonify(dict(config))

@app.route("/api/config", methods=["POST"])
def api_set_config():
    data = request.get_json(silent=True) or {}
    changed = []
    for k, v in data.items():
        if k in config and k not in ("ota_api_key", "flask_port", "flask_host"):
            config[k] = v
            changed.append(k)
    if changed:
        _save_config()
        _reload_config()
    return jsonify({"status": "success", "changed": changed, "config": dict(config)})

@app.route("/api/reload", methods=["POST"])
def api_reload():
    msg = _reload_config()
    return jsonify({"status": "success", "message": msg})

@app.route("/api/restart", methods=["POST"])
def api_restart_service():
    msg = _restart_service()
    return jsonify({"status": "success", "message": msg})

@app.route("/api/reboot", methods=["POST"])
def api_reboot_pi():
    msg = _reboot_pi()
    return jsonify({"status": "success", "message": msg})

@app.route("/")
def web_index():
    idx = _load_index()
    info = _update_sysinfo()
    return render_template_string("""<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>RPi Face Recognition</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
body{background:#1a1a2e;color:#eee;padding:20px}
h1{color:#e94560;margin-bottom:20px}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:1200px}
.card{background:#16213e;border-radius:12px;padding:20px;box-shadow:0 4px 15px rgba(0,0,0,.3)}
.card h2{color:#0f3460;font-size:1.1rem;margin-bottom:12px;text-transform:uppercase;letter-spacing:1px;color:#e94560}
.card p{margin:6px 0;font-size:.9rem;color:#ccc}
.card .val{color:#fff;font-weight:600}
.stream-wrap{grid-column:1/-1;text-align:center}
.stream-wrap img{max-width:100%;border-radius:8px;border:2px solid #0f3460}
.badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:.8rem}
.badge-green{background:#00b894;color:#fff}
.badge-red{background:#e17055;color:#fff}
.badge-yellow{background:#fdcb6e;color:#333}
.faces-list{max-height:200px;overflow-y:auto}
.faces-list li{padding:4px 0;border-bottom:1px solid #0f3460;font-size:.85rem}
.log-box{grid-column:1/-1;background:#0d0d1a;border-radius:8px;padding:12px;max-height:300px;overflow-y:auto;font-family:monospace;font-size:.8rem;line-height:1.4}
.log-box div{color:#55efc4}
</style>
</head>
<body>
<h1>RPi Face Recognition</h1>
<div class="grid">
<div class="stream-wrap">
<div class="card">
<h2>Live Stream</h2>
<img src="/stream" alt="camera feed" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22300%22><rect fill=%22%2316213e%22 width=%22400%22 height=%22300%22/><text fill=%22%23e94560%22 x=%2250%%22 y=%2250%%22 text-anchor=%22middle%22 dominant-baseline=%22middle%22 font-size=%2220%22>Camera Offline</text></svg>'">
</div>
</div>
<div class="card">
<h2>System</h2>
<p>CPU Temp: <span class="val">{{info.cpu_temp}}</span></p>
<p>CPU Usage: <span class="val">{{info.cpu_usage}}</span></p>
<p>RAM Free: <span class="val">{{info.ram_free}}</span></p>
<p>Uptime: <span class="val">{{info.uptime}}</span></p>
<p>Threshold: <span class="val">{{info.threshold_pct}}%</span></p>
</div>
<div class="card">
<h2>Faces ({{info.face_count}})</h2>
<ul class="faces-list">
{% for sid, entry in faces %}
<li>{{sid}} — {{entry.get("name", sid)}}</li>
{% else %}
<li style="color:#888">No faces enrolled</li>
{% endfor %}
</ul>
</div>
<div class="card">
<h2>Service</h2>
<p>Status: <span class="badge badge-green">running</span></p>
<p>Uptime: <span class="val">{{info.service_uptime}}</span></p>
<p>Camera: <span class="badge {{'badge-green' if info.camera_ok else 'badge-red'}}">{{"OK" if info.camera_ok else "OFFLINE"}}</span></p>
<p>Faces Loaded: <span class="val">{{info.faces_loaded}}</span></p>
<p>Model: <span class="val">Haar + Cosine</span></p>
</div>
<div class="log-box" id="log-box">
{% for line in logs %}
<div>{{line}}</div>
{% endfor %}
</div>
</div>
<script>
setInterval(()=>fetch('/api/logs?n=30').then(r=>r.json()).then(d=>{
  const box=document.getElementById('log-box');
  box.innerHTML=d.logs.map(l=>'<div>'+l.replace(/</g,'&lt;')+'</div>').join('');
  box.scrollTop=box.scrollHeight;
}),3000);
</script>
</body>
</html>
""", info=info, faces=sorted(_load_index().items()), config=config, logs=_log_buf[-50:])

def _post_attendance(student_no):
    if not OTA_SERVER:
        return
    try:
        import urllib.request, urllib.parse
        data = urllib.parse.urlencode({
            "action": "manual_attendance",
            "student_no": student_no,
            "modality": "rpi_face",
            "timestamp": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
            # Without the key the server treats this as a browser and redirects to the login page,
            # so no face attendance from the Pi was ever recorded. The server picks the course
            # from the student's lecture that is running now.
            "hardware_key": OTA_KEY or "",
        }).encode()
        url = f"{OTA_SERVER}/csc2052/api/student.php"
        req = urllib.request.Request(url, data=data, method="POST")
        with urllib.request.urlopen(req, timeout=8) as resp:
            body = resp.read().decode()
            log.info("Posted attendance for %s: %s", student_no, body[:100])
            if not OTA_KEY:
                log.warning("ota_api_key is not set in the config, so the server will refuse attendance posts.")
    except Exception as e:
        log.debug("Attendance post failed for %s: %s", student_no, e)

def _heartbeat_loop():
    if not OTA_SERVER:
        return
    import urllib.request, urllib.parse
    log.info("Heartbeat started -> %s", OTA_SERVER)
    while True:
        try:
            params = urllib.parse.urlencode({
                "device": OTA_DEVICE,
                "name": "rpi-face",
                "hardware_key": OTA_KEY or "",
            })
            url = f"{OTA_SERVER}/csc2052/api/heartbeat.php?{params}"
            req = urllib.request.Request(url)
            with urllib.request.urlopen(req, timeout=8) as resp:
                body = json.loads(resp.read())
                if body.get("status") == "ok":
                    pass
        except Exception as e:
            if "timed out" not in str(e).lower():
                log.debug("Heartbeat: %s", e)
        time.sleep(HEARTBEAT_INTERVAL)

def _ota_poll_loop():
    if not OTA_SERVER:
        log.info("OTA polling disabled (ota_server_url not configured)")
        return
    import urllib.request, urllib.parse
    log.info("OTA polling started -> %s", OTA_SERVER)
    while True:
        try:
            params = urllib.parse.urlencode({"action": "poll", "device": OTA_DEVICE, "key": OTA_KEY or ""})
            url = f"{OTA_SERVER}/csc2052/api/ota.php?{params}"
            req = urllib.request.Request(url)
            with urllib.request.urlopen(req, timeout=10) as resp:
                data = json.loads(resp.read())
            command = data.get("command") or data.get("commands") or ""
            if command:
                log.info("OTA poll received: %s", command)
                _handle_ota_cmd(command)
        except Exception as e:
            if "timed out" not in str(e).lower():
                log.debug("OTA poll: %s", e)
        time.sleep(OTA_INTERVAL)

def _graceful_shutdown(sig=None, frame=None):
    log.info("Shutting down...")
    _camera_close()
    sys.exit(0)

signal.signal(signal.SIGINT, _graceful_shutdown)
signal.signal(signal.SIGTERM, _graceful_shutdown)

if __name__ == "__main__":
    log.info("=" * 50)
    log.info("RPi Face Recognition Service v2.1 (OpenCV)")
    log.info("=" * 50)
    log.info("  Camera:     %s (%dx%d)", CAMERA_ID, CAMERA_W, CAMERA_H)
    log.info("  Threshold:  %d%%", int(THRESHOLD * 100))
    log.info("  Face size:  %dx%d", FACE_W, FACE_H)
    log.info("  Faces dir:  %s", FACE_DIR)
    log.info("  Port:       %d", FLASK_PORT)
    log.info("  Enroll cap: %d frame(s)", ENROLL_CAPTURES)
    log.info("  Cooldown:   %ds", ATTENDANCE_COOLDOWN)
    log.info("  Engine:     Haar Cascade + Cosine Similarity")
    if OTA_SERVER:
        log.info("  OTA:        %s (device=%s, %ds interval)", OTA_SERVER, OTA_DEVICE, OTA_INTERVAL)
    else:
        log.info("  OTA:        not configured")
    _rebuild_encodings()
    _camera_open()
    if OTA_SERVER:
        threading.Thread(target=_heartbeat_loop, daemon=True).start()
        threading.Thread(target=_ota_poll_loop, daemon=True).start()
    try:
        app.run(host=FLASK_HOST, port=FLASK_PORT, threaded=True, use_reloader=False)
    except KeyboardInterrupt:
        _graceful_shutdown()

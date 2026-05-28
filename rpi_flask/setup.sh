#!/usr/bin/env bash
# setup.sh — One-command install for Raspberry Pi 3B + Camera
set -euo pipefail

cd "$(dirname "$0")"
SERVICE_DIR="$(pwd)"
PI_USER="${PI_USER:-pi}"

echo "================================================================================"
echo "  RPi Face Recognition Service — Automated Setup"
echo "  Raspberry Pi 3B + Pi Camera"
echo "================================================================================"

# ── 1. System packages ─────────────────────────────────────────────────
echo ""
echo "[1/6] Updating system packages..."
sudo apt-get update -qq
sudo apt-get upgrade -y -qq

echo "[2/6] Installing system dependencies..."
sudo apt-get install -y -qq \
  python3-pip python3-venv python3-dev \
  python3-picamera2 \
  cmake build-essential \
  libatlas-base-dev libhdf5-dev libhdf5-serial-dev \
  libjasper-dev libqtgui4 libqt4-test \
  libilmbase-dev libopenexr-dev libgstreamer1.0-dev \
  libjpeg-dev libtiff-dev libpng-dev \
  fswebcam v4l-utils network-manager \
  iw bluetooth bluez pi-bluetooth

# ── 2. Enable Camera ──────────────────────────────────────────────────
echo ""
echo "[3/6] Enabling Pi Camera interface..."
# raspi-config non-interactive: enable camera
sudo raspi-config nonint do_camera 0 2>/dev/null || true
# Also ensure legacy camera support is available
if grep -q "start_x=0" /boot/config.txt 2>/dev/null; then
  sudo sed -i 's/start_x=0/start_x=1/g' /boot/config.txt
elif ! grep -q "start_x=1" /boot/config.txt 2>/dev/null; then
  echo "start_x=1" | sudo tee -a /boot/config.txt
fi
if ! grep -q "gpu_mem" /boot/config.txt 2>/dev/null; then
  echo "gpu_mem=128" | sudo tee -a /boot/config.txt
fi
echo "  Pi Camera enabled in /boot/config.txt"

# ── 3. Python virtual environment ──────────────────────────────────────
echo ""
echo "[4/6] Creating Python virtual environment..."
python3 -m venv venv
source venv/bin/activate

echo "[5/6] Installing Python packages (this takes 5-15 minutes on Pi 3B)..."
pip install --upgrade pip setuptools wheel

# Install face_recognition deps first (dlib takes the longest)
pip install numpy==1.24.4
pip install opencv-python==4.8.1.78
pip install face-recognition==1.3.0
pip install flask flask-cors psutil

# ── 4. Systemd service ────────────────────────────────────────────────
echo ""
echo "[6/6] Installing systemd service..."
SERVICE_FILE="/etc/systemd/system/rpi-face.service"

sudo tee "$SERVICE_FILE" > /dev/null <<SERVICEEOF
[Unit]
Description=RPi Face Recognition Service
After=network.target multi-user.target

[Service]
Type=simple
User=$PI_USER
WorkingDirectory=$SERVICE_DIR
ExecStart=$SERVICE_DIR/venv/bin/python $SERVICE_DIR/app.py
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
SERVICEEOF

sudo systemctl daemon-reload
sudo systemctl enable rpi-face.service
echo "  Service installed: rpi-face.service"

# ── 5. Done ────────────────────────────────────────────────────────────
IP=$(hostname -I 2>/dev/null | awk '{print $1}')
echo ""
echo "================================================================================"
echo "  SETUP COMPLETE!"
echo "================================================================================"
echo ""
echo "  ⚠  REBOOT REQUIRED to activate the camera"
echo "     Run:  sudo reboot"
echo ""
echo "  After reboot, start the service:"
echo "     sudo systemctl start rpi-face"
echo "     sudo systemctl status rpi-face"
echo ""
echo "  Then open in browser:"
echo "     http://$IP:5000"
echo "     http://$IP:5000/stream"
echo ""
echo "  View logs:"
echo "     journalctl -u rpi-face -f"
echo ""
echo "  Configure your PHP server IP in config.json, then reload:"
echo "     curl -X POST http://$IP:5000/api/reload"
echo ""
echo "================================================================================"

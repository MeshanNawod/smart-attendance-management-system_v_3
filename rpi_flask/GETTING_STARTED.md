# Beginner's Guide: Setting Up Raspberry Pi 3B + Camera

This guide assumes you know NOTHING about Raspberry Pi. Follow it step by step.

---

## What You Need

- **Raspberry Pi 3B** (or 3B+, 4B)
- **MicroSD card** (16GB or larger, Class 10 recommended)
- **5V 2.5A power supply** (USB-C for Pi 4, Micro-USB for Pi 3B)
- **Raspberry Pi Camera Module** (v1, v2, or v3 — any works)
- **Ribbon cable** (comes with the camera — 15-pin, wider end goes to Pi)
- **Computer** (Windows/Mac/Linux) to flash the SD card
- **SD card reader** (if your computer doesn't have one)
- **Ethernet cable** (optional, for wired network) or **Wi-Fi**

---

## Step 1: Flash Raspberry Pi OS onto the SD Card

1. Download **Raspberry Pi Imager** from https://www.raspberrypi.com/software/
2. Install and open it
3. Insert your SD card into your computer
4. In Raspberry Pi Imager:
   - Click **CHOOSE DEVICE** → Raspberry Pi 3
   - Click **CHOOSE OS** → Raspberry Pi OS (64-bit) WITH desktop (recommended)
   - Click **CHOOSE STORAGE** → select your SD card
   - Click the **gear icon** (⚙) to pre-configure:
     - ☑ Set hostname: `attendance-pi`
     - ☑ Enable SSH: `Use password authentication`
     - ☑ Set username & password: `pi` / `raspberry`
     - ☑ Configure Wi-Fi: enter your Wi-Fi name & password
     - ☑ Set locale: your timezone
   - Click **SAVE**, then **WRITE**
5. Wait for it to finish, then remove the SD card

---

## Step 2: Connect the Pi Camera

The ribbon cable has **metal contacts on one side only**.

1. **On the Pi 3B:** locate the **CSI camera port** (between HDMI and audio jack).
   It's a long narrow black plastic slot with a blue tab.
2. Gently **lift the blue tab** (pull UP on both ends).
3. Insert the ribbon cable:
   - **Metal contacts face AWAY from the Ethernet port** (toward the HDMI port)
   - Push the cable fully in
4. **Press the blue tab back down** to lock

If using Pi Camera Module v3:
- The cable is already attached
- Just plug the other end into the Pi

---

## Step 3: Boot the Pi

1. Insert the SD card into the Pi
2. Connect the Pi Camera ribbon cable
3. Plug in power (USB)
4. Wait 1–2 minutes for first boot

---

## Step 4: Find the Pi on Your Network

### Option A: Check your router
Log into your router's admin page (usually http://192.168.1.1 or http://192.168.0.1)
and look for a device named `attendance-pi` or check the DHCP client list.

### Option B: Use the IP your phone hotspot shows
If using phone hotspot, check "Connected devices" in hotspot settings.

### Option C: Ping the hostname
```bash
ping attendance-pi.local
```
If this works, the IP in the response IS your Pi's IP.

Write down the IP address — you'll need it.

---

## Step 5: SSH into the Pi

**Windows:** Open PowerShell or Command Prompt
```powershell
ssh pi@<PI_IP_ADDRESS>
```
Type `yes` when asked about the fingerprint, then enter password `raspberry`.

**Mac/Linux:**
```bash
ssh pi@<PI_IP_ADDRESS>
```

Once connected, you'll see a command prompt like `pi@attendance-pi:~ $`

---

## Step 6: Enable the Camera Interface

Run:
```bash
sudo raspi-config
```

Navigate:
1. **Interface Options** (press Enter)
2. **I1 Legacy Camera** (press Enter)
3. **Yes** → **Enable**
4. **Finish** → **Yes** (reboot)

Wait for the Pi to reboot, then SSH back in.

---

## Step 7: Test the Camera

After reboot, SSH in and test:
```bash
# Take a test photo (saves as test.jpg)
raspistill -o test.jpg

# If you get "Command not found", try:
libcamera-still -o test.jpg

# View the photo (if SSH with X forwarding) or copy to your computer:
ls -lh test.jpg
```
If it says "preview completed" and a jpg file appears, the camera works.

---

## Step 8: Copy the Flask App to the Pi

From your computer (where this project is), copy the `rpi_flask` folder to the Pi:

**On your computer** (PowerShell/terminal):
```bash
# Replace PI_IP with your Pi's actual IP
scp -r C:\xampp\htdocs\csc2052\rpi_flask pi@<PI_IP>:/home/pi/
```

Enter the password `raspberry` when prompted.

---

## Step 9: Configure the Server IP

SSH into the Pi and edit the config:
```bash
nano /home/pi/rpi_flask/config.json
```

Change `ota_server_url` to your PHP server's IP:
```json
{
  "ota_server_url": "http://10.193.54.212",
  "camera_type": "picamera"
}
```
- `camera_type` should be `"picamera"` for the Pi Camera module
- `camera_type` should be `"usb"` for a USB webcam

Save: **Ctrl+X → Y → Enter**

---

## Step 10: Run Setup

```bash
cd /home/pi/rpi_flask
chmod +x setup.sh
./setup.sh
```

This takes 10–20 minutes on a Pi 3B (dlib compiles from source).

**When it finishes, it will tell you to reboot. DO IT:**
```bash
sudo reboot
```

---

## Step 11: Start the Service

Wait for Pi to reboot, then SSH back in:
```bash
sudo systemctl start rpi-face
sudo systemctl status rpi-face
```

Check for:
- `Active: active (running)` — green text = it's running
- No errors in the log

If it shows errors, view the full log:
```bash
journalctl -u rpi-face -n 50 --no-pager
```

---

## Step 12: Open in Browser

Open your computer's browser and go to:
```
http://<PI_IP>:5000
```

You should see the **web dashboard** showing:
- Live camera feed (green border = face recognized)
- System info (CPU temp, RAM, etc.)
- Enrolled faces list
- Log viewer

If the camera stream doesn't load, check:
```bash
journalctl -u rpi-face -n 20 --no-pager
```
Look for `Pi Camera (CSI) opened` or error messages.

---

## Connecting to Your PHP Server

From your PHP server's dashboard (`device.php` or `students.php`):
1. Set the Pi's IP address in the "Raspberry Pi 3B Control Center" section
2. Click **Test Ping** — should show green "Online"
3. Click **Start Scanner** — should show the camera feed

The Pi automatically polls `api/ota.php` on your PHP server every 10 seconds
for queued commands.

---

## Face Enrollment

### From the Web Dashboard:
```
http://<PI_IP>:5000
```
→ Not the main way, use the PHP frontend instead.

### From your PHP Server (device.php):
1. Go to **Raspberry Pi 3B Control Center**
2. Enter a student number in **Face Enrollment**
3. Click **Enroll**
4. Look at the Pi Camera — it will capture 3 frames and average them

### From SSH (direct command):
```bash
curl -X POST http://localhost:5000/api/command \
  -H "Content-Type: application/json" \
  -d '{"command": "ENROLL:S12345|John Doe"}'
```

---

## Common Problems

### "Camera not detected"
```bash
# Check if camera is enabled in config
grep start_x /boot/config.txt
# Should show: start_x=1

# Check GPU memory
grep gpu_mem /boot/config.txt
# Should be at least 128

# List video devices
ls -la /dev/video*

# If no /dev/video0, try:
sudo modprobe bcm2835-v4l2
```

### "picamera2 not installed"
```bash
sudo apt-get install python3-picamera2
```

### "dlib installation fails"
The Pi 3B is slow. Setup takes 15-20 mins. If it fails:
```bash
# Increase swap space
sudo dphys-swapfile swapoff
sudo sed -i 's/CONF_SWAPSIZE=100/CONF_SWAPSIZE=1024/' /etc/dphys-swapfile
sudo dphys-swapfile setup
sudo dphys-swapfile swapon
# Then re-run setup.sh
```

### Service won't start
```bash
journalctl -u rpi-face -n 50 --no-pager
```

### Face recognition is slow
- Pi 3B processes about 1-2 frames per second with HOG model
- This is normal — use a Pi 4B for better performance
- Or reduce camera resolution in config.json to 320x240 for speed

### MJPEG stream lags in browser
Open the stream URL directly: `http://<PI_IP>:5000/stream`
If it works there, the issue is the dashboard page, not the stream.

---

## Need Help?

Check the logs:
```bash
journalctl -u rpi-face -f
```

The web dashboard at `http://<PI_IP>:5000` also shows live logs.

---

## File Locations on the Pi

| Path | Purpose |
|---|---|
| `/home/pi/rpi_flask/app.py` | Main Flask service |
| `/home/pi/rpi_flask/config.json` | Configuration |
| `/home/pi/rpi_flask/face_data/` | Enrolled face encodings |
| `/home/pi/rpi_flask/face_data/_index.json` | Face index |
| `/home/pi/rpi_flask/setup.sh` | Setup script |
| `/etc/systemd/system/rpi-face.service` | Systemd service file |

## Service Commands

```bash
sudo systemctl start rpi-face      # Start
sudo systemctl stop rpi-face       # Stop
sudo systemctl restart rpi-face    # Restart
sudo systemctl status rpi-face     # Check status
sudo systemctl enable rpi-face     # Auto-start on boot
sudo systemctl disable rpi-face    # Disable auto-start
journalctl -u rpi-face -f          # Live log tail
```

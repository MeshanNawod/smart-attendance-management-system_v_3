# Sentinel Swarm AMS — Frontend

React SPA with **PWA** support and a **Tauri desktop app** wrapper.

---

## Development (Web)

```bash
npm install
npm run dev
```

## Build for Production (Web)

```bash
npm run build
# Output goes to dist/
```

---

## Desktop App (.exe installer)

The React frontend is wrapped with **Tauri** (v2) — a Rust-based framework that produces a small, fast `.exe` (~5 MB) with an optional NSIS installer.

### Prerequisites

- **Rust** — Install from https://rustup.rs
- **Windows:** Microsoft Visual Studio Build Tools (or Visual Studio with "Desktop development with C++")
- **Linux:** `sudo apt install libwebkit2gtk-4.1-dev build-essential curl wget file libxdo-dev libssl-dev libayatana-appindicator3-dev librsvg2-dev`

### Build

```bash
# 1. Generate app icons from SVG
npm run tauri:icon

# 2. Build the .exe + installer
npm run tauri:build
```

The installer `.exe` will be in `src-tauri/target/release/bundle/nsis/`.

### Dev Mode (hot-reload desktop window)

```bash
npm run tauri:dev
```

---

## PWA (Install from Browser)

The app is also a Progressive Web App. Open it in Chrome/Edge and click the install icon in the address bar to add it to your home screen — no build tools needed.

---

## Project Structure

```
public/
├── icon.svg            # App icon (SVG)
├── manifest.json       # PWA manifest
├── sw.js               # Service worker
src/                    # React source
src-tauri/              # Tauri desktop wrapper
├── Cargo.toml
├── tauri.conf.json
├── src/main.rs
├── src/lib.rs
└── capabilities/
```

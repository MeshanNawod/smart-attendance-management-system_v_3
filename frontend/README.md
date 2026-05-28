# Sentinel Swarm AMS — Frontend (PWA)

React SPA with **Progressive Web App** support. Can be installed on mobile/desktop home screen.

## PWA Features

- **Installable** — Add to home screen on Android (Chrome), iOS (Safari), and desktop (Chrome/Edge)
- **Offline fallback** — Service worker caches static assets; API calls gracefully degrade
- **Standalone mode** — Runs without browser chrome when installed

## Development

```bash
npm install
npm run dev
```

## Build for Production

```bash
npm run build
```

The output goes to `dist/`. Point your web server to serve this directory (or copy contents into the PHP project root).

## Icon

The app icon is at `public/icon.svg`.  
For iOS, create a PNG version named `icon-192.png` and `icon-512.png` in `public/` using any SVG-to-PNG converter (e.g. `rsvg-convert`, Inkscape, or online tools).

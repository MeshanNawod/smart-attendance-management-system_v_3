/**
 * cards.js — Dashboard Card UI Logic
 * Handles: stat card animations, button states, live feedback,
 * card ripples, sidebar card interactions, and status updates.
 * Palette: Maroon #a11e17 | Gold #fcd40d | White #ffffff
 */

'use strict';

/* ─── CONSTANTS ────────────────────────────────────────────────── */
const BRAND = {
  maroon: '#a11e17',
  gold:   '#fcd40d',
  dark:   '#1e293b',
  white:  '#ffffff',
};

/* ─── ON DOM READY ─────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  initStatCardAnimations();
  initButtonFeedback();
  initSidebarCardCollapse();
  wireStopButtons();
  wireHardwareModeBtns();
  initFormInputHighlights();
  syncDeviceSelectorsUI();
});

/* ─── STAT CARD HOVER ANIMATIONS ───────────────────────────────── */
function initStatCardAnimations() {
  const statCards = document.querySelectorAll('.stat-card');
  statCards.forEach(card => {
    card.addEventListener('mouseenter', () => {
      card.style.transition = 'transform 200ms ease, box-shadow 200ms ease';
      card.style.transform  = 'translateY(-3px)';
    });
    card.addEventListener('mouseleave', () => {
      card.style.transform = '';
      card.style.boxShadow = '';
    });
  });
}

/* ─── GENERIC BUTTON LOADING FEEDBACK ──────────────────────────── */
function initButtonFeedback() {
  // Any btn with data-action gets a loading spinner on click
  document.querySelectorAll('[data-action]').forEach(btn => {
    btn.addEventListener('click', function () {
      const originalHTML = this.innerHTML;
      this.disabled = true;
      this.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status"></span>${this.dataset.loadingText || 'Loading...'}`;
      // Re-enable after 4s as a safety fallback
      setTimeout(() => {
        this.disabled = false;
        this.innerHTML = originalHTML;
      }, 4000);
    });
  });
}

/* ─── SIDEBAR CARD COLLAPSE TOGGLE ─────────────────────────────── */
function initSidebarCardCollapse() {
  document.querySelectorAll('.sidebar-card-toggle').forEach(btn => {
    btn.addEventListener('click', function () {
      const target = document.getElementById(this.dataset.target);
      if (!target) return;
      const isHidden = target.classList.contains('d-none');
      target.classList.toggle('d-none', !isHidden);
      const icon = this.querySelector('i');
      if (icon) {
        icon.className = isHidden ? 'bi bi-chevron-up' : 'bi bi-chevron-down';
      }
    });
  });
}

/* ─── STOP BUTTONS (webcam / rpi) ───────────────────────────────── */
function wireStopButtons() {
  const btnStop = document.getElementById('btn-stop-attendance');
  if (btnStop) {
    btnStop.addEventListener('click', () => {
      const container = document.getElementById('web-attendance-container');
      const btnStart  = document.getElementById('btn-start-attendance');
      if (container) container.classList.add('d-none');
      if (btnStart)  btnStart.classList.remove('d-none');
      btnStop.classList.add('d-none');
    });
  }
}

/* ─── HARDWARE MODE BUTTONS ─────────────────────────────────────── */
function wireHardwareModeBtns() {
  const modeMap = {
    'ATTENDANCE_MODE': { label: 'Attendance', icon: 'bi-person-check', color: BRAND.maroon },
    'ENROLL_MODE':     { label: 'Enroll',      icon: 'bi-fingerprint',  color: '#8b6b00' },
  };

  document.querySelectorAll('[onclick*="sendOtaCommand"]').forEach(btn => {
    const match = btn.getAttribute('onclick').match(/'([^']+)'/);
    if (!match) return;
    const mode = match[1];
    const info = modeMap[mode];
    if (!info) return;

    btn.addEventListener('click', function () {
      // Visual feedback: pulse the clicked btn
      this.style.background = info.color;
      this.style.color = '#fff';
      setTimeout(() => {
        this.style.background = '';
        this.style.color = '';
      }, 1200);
    });
  });
}

/* ─── FORM INPUT HIGHLIGHTS ─────────────────────────────────────── */
function initFormInputHighlights() {
  document.querySelectorAll('.form-control, .form-select').forEach(el => {
    el.addEventListener('focus', () => {
      el.style.borderColor = 'rgba(161,30,23,0.5)';
      el.style.boxShadow   = '0 0 0 3px rgba(161,30,23,0.08)';
    });
    el.addEventListener('blur', () => {
      el.style.borderColor = '';
      el.style.boxShadow   = '';
    });
  });
}

/* ─── SYNC DEVICE SELECTORS UI STATE ───────────────────────────── */
function syncDeviceSelectorsUI() {
  const studentsSel = document.getElementById('students-device-select');
  const statSel     = document.getElementById('stat-device-select');

  if (studentsSel) {
    studentsSel.addEventListener('change', function () {
      updateDeviceStatusBadge('students-device-status', this.value);
    });
  }

  if (statSel) {
    statSel.addEventListener('change', function () {
      if (typeof fetchTodayAttendance === 'function') fetchTodayAttendance();
    });
  }
}

function updateDeviceStatusBadge(badgeId, value) {
  const badge = document.getElementById(badgeId);
  if (!badge) return;
  if (!value) {
    badge.textContent = 'None';
    badge.style.background = '#94a3b8';
    badge.style.color = '#fff';
  } else {
    badge.textContent = value.length > 14 ? value.substring(0, 14) + '…' : value;
    badge.style.background = BRAND.maroon;
    badge.style.color = '#fff';
  }
}

/* ─── EXPORTED: update stat card values ─────────────────────────── */
window.updateStatCard = function(id, value, options = {}) {
  const el = document.getElementById(id);
  if (!el) return;

  // Animate count up
  if (options.animate && typeof value === 'number') {
    const start = parseInt(el.textContent) || 0;
    const duration = options.duration || 600;
    const step = Math.ceil(Math.abs(value - start) / (duration / 16));
    let current = start;
    const interval = setInterval(() => {
      current += (value > start ? step : -step);
      if ((value > start && current >= value) || (value <= start && current <= value)) {
        current = value;
        clearInterval(interval);
      }
      el.textContent = current;
    }, 16);
  } else {
    el.textContent = value;
  }
};

/* ─── EXPORTED: flash a card to indicate update ─────────────────── */
window.flashStatCard = function(cardSelector, color = BRAND.gold) {
  const card = document.querySelector(cardSelector);
  if (!card) return;
  card.style.transition = 'box-shadow 200ms ease';
  card.style.boxShadow = `0 0 0 3px ${color}`;
  setTimeout(() => { card.style.boxShadow = ''; }, 700);
};

/* ─── EXPORTED: show inline toast inside a card ─────────────────── */
window.cardToast = function(containerId, message, type = 'success') {
  const container = document.getElementById(containerId);
  if (!container) return;
  const colors = {
    success: { bg: '#d1fae5', border: '#34d399', text: '#065f46' },
    error:   { bg: '#fee2e2', border: '#f87171', text: '#991b1b' },
    info:    { bg: '#fdf8f8', border: '#a11e17', text: '#a11e17' },
  };
  const c = colors[type] || colors.info;
  const toast = document.createElement('div');
  toast.style.cssText = `background:${c.bg};border-left:3px solid ${c.border};color:${c.text};border-radius:7px;padding:6px 10px;font-size:0.78rem;font-weight:600;margin-top:6px;animation:fadeIn 200ms ease;`;
  toast.textContent = message;
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transition = 'opacity 300ms';
    setTimeout(() => toast.remove(), 300);
  }, 2500);
};

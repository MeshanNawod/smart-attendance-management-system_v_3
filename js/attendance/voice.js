// ─── Voice Recording & Attendance ────────────────────────────────

function esc(t) { if (t == null || t === undefined) return ''; return String(t).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }

let voiceRecorder = null;
let voiceChunks = [];
let voiceStream = null;
let voiceAnalyser = null;
let voiceAnimFrame = null;

function getCsrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.content : '';
}

// Load students for voice dropdown
window.loadVoiceStudents = function () {
    var course = document.getElementById('voice-course').value;
    var sel = document.getElementById('voice-student');
    var searchEl = document.getElementById('voice-student-search');
    var dropdownEl = document.getElementById('voice-student-dropdown');

    if (!course) {
        if (sel) sel.value = '';
        if (searchEl) searchEl.value = '';
        if (dropdownEl) dropdownEl.innerHTML = '<div class="text-center text-muted py-2 small">Select course first</div>';
        return;
    }

    if (dropdownEl) dropdownEl.innerHTML = '<div class="text-center text-muted py-2 small"><i class="bi bi-arrow-clockwise spin me-1"></i>Loading...</div>';

    fetch('/csc2052/api/voice.php?action=get_course_students&course_code=' + encodeURIComponent(course))
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (dropdownEl) dropdownEl.innerHTML = '';
            if (sel) sel.value = '';
            if (searchEl) searchEl.value = '';

            if (d.students && d.students.length > 0) {
                d.students.forEach(function (s) {
                    var hasVoice = s.voice_sample ? ' 🎤' : '';
                    var item = document.createElement('div');
                    item.className = 'searchable-dropdown-item';
                    item.setAttribute('data-value', s.student_no);
                    item.textContent = s.student_no + ' — ' + s.student_name + hasVoice;
                    item.onclick = function() {
                        selectDropdownItem(this, 'voice-student-search', 'voice-student', 'voice-student-dropdown');
                    };
                    if (dropdownEl) dropdownEl.appendChild(item);
                });
            } else {
                if (dropdownEl) dropdownEl.innerHTML = '<div class="text-center text-muted py-2 small">No students enrolled</div>';
            }
        })
        .catch(function () {
            if (dropdownEl) dropdownEl.innerHTML = '<div class="text-center text-danger py-2 small">Error loading</div>';
        });
};

// Show the voice scanner panel (mirrors face card UX)
window.startVoiceScanner = function () {
    var container = document.getElementById('voice-scanner-container');
    var btn = document.getElementById('btn-start-voice-scanner');
    if (container) container.classList.remove('d-none');
    if (btn) btn.style.display = 'none';
    var statusEl = document.getElementById('voice-status');
    if (statusEl) statusEl.innerHTML = 'Select a course and student, then click record.';
};

// Start/stop voice recording
window.toggleVoiceRecord = function () {
    if (voiceRecorder && voiceRecorder.state === 'recording') {
        stopVoiceRecording();
        return;
    }
    startVoiceRecording();
};

function getVoiceMode() {
    var enroll = document.getElementById('voiceModeEnroll');
    return enroll && enroll.checked ? 'enroll' : 'attend';
}

function startVoiceRecording() {
    var mode = getVoiceMode();
    var studentEl = document.getElementById('voice-student');
    var courseEl = document.getElementById('voice-course');
    var statusEl = document.getElementById('voice-status');
    var visEl = document.getElementById('voice-visualizer');

    if (!studentEl.value || !courseEl.value) {
        statusEl.innerHTML = '<span class="text-danger">Please select a course and student first.</span>';
        return;
    }

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        statusEl.innerHTML = '<span class="text-danger">Voice recording not supported in this browser.</span>';
        return;
    }

    voiceChunks = [];
    navigator.mediaDevices.getUserMedia({ audio: true })
        .then(function (stream) {
            voiceStream = stream;
            var mimeType = 'audio/webm;codecs=opus';
            if (!MediaRecorder.isTypeSupported(mimeType)) mimeType = 'audio/webm';
            if (!MediaRecorder.isTypeSupported(mimeType)) mimeType = 'audio/ogg;codecs=opus';
            if (!MediaRecorder.isTypeSupported(mimeType)) mimeType = 'audio/mp4';

            voiceRecorder = new MediaRecorder(stream, { mimeType: mimeType });

            voiceRecorder.ondataavailable = function (e) {
                if (e.data.size > 0) voiceChunks.push(e.data);
            };

            voiceRecorder.onstop = function () {
                var blob = new Blob(voiceChunks, { type: voiceRecorder.mimeType || 'audio/webm' });
                voiceChunks = [];
                if (voiceStream) { voiceStream.getTracks().forEach(function (t) { t.stop(); }); voiceStream = null; }
                if (voiceAnimFrame) { cancelAnimationFrame(voiceAnimFrame); voiceAnimFrame = null; }
                if (visEl) visEl.style.display = 'none';

                if (mode === 'enroll') {
                    uploadVoiceSample(blob, studentEl.value, courseEl.value);
                } else {
                    submitVoiceAttendance(blob, studentEl.value, courseEl.value);
                }

                var btn = document.getElementById('btn-stop-voice');
                if (btn) btn.classList.add('d-none');
            };

            voiceRecorder.start();
            statusEl.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-mic-fill me-1"></i>Recording... Click Stop to finish.</span>';

            var btn = document.getElementById('btn-stop-voice');
            if (btn) btn.classList.remove('d-none');

            // Audio visualizer
            if (visEl) {
                visEl.style.display = 'block';
                var audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                var src = audioCtx.createMediaStreamSource(stream);
                voiceAnalyser = audioCtx.createAnalyser();
                voiceAnalyser.fftSize = 128;
                src.connect(voiceAnalyser);
                var canvas = visEl;
                var ctx = canvas.getContext('2d');
                var bufferLength = voiceAnalyser.frequencyBinCount;
                var dataArray = new Uint8Array(bufferLength);
                var W = canvas.width, H = canvas.height;
                function draw() {
                    voiceAnimFrame = requestAnimationFrame(draw);
                    voiceAnalyser.getByteFrequencyData(dataArray);
                    ctx.fillStyle = '#1e293b';
                    ctx.fillRect(0, 0, W, H);
                    var barWidth = (W / bufferLength) * 2.5;
                    var x = 0;
                    for (var i = 0; i < bufferLength; i++) {
                        var barH = (dataArray[i] / 255) * H;
                        ctx.fillStyle = 'hsl(' + (i * 3) + ', 80%, 60%)';
                        ctx.fillRect(x, H - barH, barWidth, barH);
                        x += barWidth + 1;
                    }
                }
                draw();
            }
        })
        .catch(function (err) {
            statusEl.innerHTML = '<span class="text-danger">Microphone access denied: ' + err.message + '</span>';
        });
}

function stopVoiceRecording() {
    if (voiceRecorder && voiceRecorder.state === 'recording') {
        voiceRecorder.stop();
    }
}

function uploadVoiceSample(blob, studentNo, courseCode) {
    var statusEl = document.getElementById('voice-status');
    var fd = new FormData();
    fd.append('action', 'enroll_voice');
    fd.append('student_no', studentNo);
    fd.append('course_code', courseCode);
    fd.append('audio', blob, studentNo + '_voice.webm');

    fetch('/csc2052/api/voice.php', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': getCsrf() },
        body: fd
    })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.status === 'success') {
                statusEl.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle me-1"></i>Voice sample enrolled for ' + studentNo + '!</span>';
                var badge = document.getElementById('voice-count-badge');
                if (badge) {
                    var cur = parseInt(badge.textContent.replace('Samples: ', '')) || 0;
                    badge.textContent = 'Samples: ' + (cur + 1);
                    badge.classList.remove('d-none');
                }
                loadVoiceStudents();
            } else {
                statusEl.innerHTML = '<span class="text-danger">' + (d.message || 'Upload failed.') + '</span>';
            }
        })
        .catch(function () {
            statusEl.innerHTML = '<span class="text-danger">Network error.</span>';
        });
}

function submitVoiceAttendance(blob, studentNo, courseCode) {
    var statusEl = document.getElementById('voice-status');
    var ts = new Date().toISOString().slice(0, 19).replace('T', ' ');

    var fd = new FormData();
    fd.append('action', 'voice_attendance');
    fd.append('student_no', studentNo);
    fd.append('course_code', courseCode);
    fd.append('timestamp', ts);
    fd.append('audio', blob, studentNo + '_voice.webm');

    fetch('/csc2052/api/voice.php', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': getCsrf() },
        body: fd
    })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.status === 'success') {
                statusEl.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle me-1"></i>Attendance marked for ' + studentNo + '!</span>';
                loadVoiceRecentLogs();
                if (typeof fetchLiveLogs === 'function') fetchLiveLogs();
            } else if (d.status === 'duplicate') {
                statusEl.innerHTML = '<span class="text-warning fw-bold"><i class="bi bi-exclamation-circle me-1"></i>Already marked today for ' + studentNo + '.</span>';
            } else {
                statusEl.innerHTML = '<span class="text-danger">' + (d.message || 'Failed.') + '</span>';
            }
        })
        .catch(function () {
            statusEl.innerHTML = '<span class="text-danger">Network error.</span>';
        });
}

function loadVoiceRecentLogs() {
    var el = document.getElementById('voice-recent-list');
    if (!el) return;
    fetch('/csc2052/api/voice.php?action=get_voice_logs')
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.logs || d.logs.length === 0) {
                el.innerHTML = '<div class="text-center text-muted py-3 small">No voice attendance records yet.</div>';
                return;
            }
            var h = '<table class="table table-sm mb-0 align-middle"><thead class="table-light"><tr><th>Student</th><th>Course</th><th>Time</th></tr></thead><tbody>';
            d.logs.forEach(function (l) {
                h += '<tr><td><strong>' + esc(l.student_no) + '</strong> <span class="text-muted small">' + (l.student_name || '') + '</span></td>' +
                    '<td><span class="badge bg-light">' + (l.course_code || '') + '</span></td>' +
                    '<td class="small text-muted">' + (l.timestamp || '') + '</td></tr>';
            });
            h += '</tbody></table>';
            el.innerHTML = h;
        })
        .catch(function () { /* silent */ });
}

// Load recent voice logs when tab is shown
document.addEventListener('DOMContentLoaded', function () {
    document.querySelector('[data-bs-target="#tab-voice"]')?.addEventListener('shown.bs.tab', function () {
        loadVoiceRecentLogs();
    });
    document.querySelector('[data-bs-target="#tab-voice-admin"]')?.addEventListener('shown.bs.tab', function () {
        adminLoadVoiceRecentLogs();
    });
});

// Bind course dropdown to student loader
document.addEventListener('DOMContentLoaded', function () {
    var courseEl = document.getElementById('voice-course');
    if (courseEl) {
        courseEl.addEventListener('change', loadVoiceStudents);
    }
});

// ═══════════════════════════════════════════════════════════════════
// ADMIN VOICE FUNCTIONS (separate IDs)
// ═══════════════════════════════════════════════════════════════════

let adminVoiceRecorder = null;
let adminVoiceChunks = [];
let adminVoiceStream = null;
let adminVoiceMode = '';
let adminVoiceAnalyser = null;
let adminVoiceAnimFrame = null;

window.adminToggleVoiceRecord = function (mode) {
    if (adminVoiceRecorder && adminVoiceRecorder.state === 'recording') {
        adminStopVoiceRecording(mode);
        return;
    }
    adminVoiceMode = mode;
    adminStartVoiceRecording(mode);
};

function adminStartVoiceRecording(mode) {
    var studentEl = mode === 'enroll'
        ? document.getElementById('voice-admin-enroll-student')
        : document.getElementById('voice-admin-attend-student');
    var courseEl = mode === 'enroll'
        ? document.getElementById('voice-admin-enroll-course')
        : document.getElementById('voice-admin-attend-course');
    var statusEl = mode === 'enroll'
        ? document.getElementById('voice-admin-enroll-status')
        : document.getElementById('voice-admin-attend-status');
    var labelEl = mode === 'enroll'
        ? document.getElementById('voice-admin-record-label')
        : document.getElementById('voice-admin-attend-label');
    var btnEl = mode === 'enroll'
        ? document.getElementById('voice-admin-record-label').parentElement
        : document.getElementById('voice-admin-attend-label').parentElement;
    var visEl = mode === 'enroll'
        ? document.getElementById('voice-admin-enroll-visualizer')
        : document.getElementById('voice-admin-attend-visualizer');

    if (!studentEl.value.trim() || !courseEl.value) {
        statusEl.innerHTML = '<span class="text-danger">Please enter student no. and select a course.</span>';
        return;
    }

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        statusEl.innerHTML = '<span class="text-danger">Voice recording not supported in this browser.</span>';
        return;
    }

    adminVoiceChunks = [];
    navigator.mediaDevices.getUserMedia({ audio: true })
        .then(function (stream) {
            adminVoiceStream = stream;
            var mimeType = 'audio/webm;codecs=opus';
            if (!MediaRecorder.isTypeSupported(mimeType)) mimeType = 'audio/webm';
            if (!MediaRecorder.isTypeSupported(mimeType)) mimeType = 'audio/ogg;codecs=opus';
            if (!MediaRecorder.isTypeSupported(mimeType)) mimeType = 'audio/mp4';

            adminVoiceRecorder = new MediaRecorder(stream, { mimeType: mimeType });

            adminVoiceRecorder.ondataavailable = function (e) {
                if (e.data.size > 0) adminVoiceChunks.push(e.data);
            };

            adminVoiceRecorder.onstop = function () {
                var blob = new Blob(adminVoiceChunks, { type: adminVoiceRecorder.mimeType || 'audio/webm' });
                adminVoiceChunks = [];
                if (adminVoiceStream) { adminVoiceStream.getTracks().forEach(function (t) { t.stop(); }); adminVoiceStream = null; }
                if (adminVoiceAnimFrame) { cancelAnimationFrame(adminVoiceAnimFrame); adminVoiceAnimFrame = null; }
                if (visEl) visEl.style.display = 'none';

                if (mode === 'enroll') {
                    adminUploadVoiceSample(blob, studentEl.value.trim(), courseEl.value);
                } else {
                    adminSubmitVoiceAttendance(blob, studentEl.value.trim(), courseEl.value);
                }
            };

            adminVoiceRecorder.start();
            if (labelEl) labelEl.textContent = '■■■ Recording...';
            if (btnEl) btnEl.style.background = '#dc3545';
            statusEl.innerHTML = '<span class="text-success"><i class="bi bi-mic-fill me-1"></i>Recording... Click again to stop.</span>';

            if (visEl) {
                visEl.style.display = 'block';
                var audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                var src = audioCtx.createMediaStreamSource(stream);
                adminVoiceAnalyser = audioCtx.createAnalyser();
                adminVoiceAnalyser.fftSize = 128;
                src.connect(adminVoiceAnalyser);
                var canvas = visEl;
                var ctx = canvas.getContext('2d');
                var bufferLength = adminVoiceAnalyser.frequencyBinCount;
                var dataArray = new Uint8Array(bufferLength);
                var W = canvas.width, H = canvas.height;
                function draw() {
                    adminVoiceAnimFrame = requestAnimationFrame(draw);
                    adminVoiceAnalyser.getByteFrequencyData(dataArray);
                    ctx.fillStyle = '#1e293b';
                    ctx.fillRect(0, 0, W, H);
                    var barWidth = (W / bufferLength) * 2.5;
                    var x = 0;
                    for (var i = 0; i < bufferLength; i++) {
                        var barH = (dataArray[i] / 255) * H;
                        ctx.fillStyle = 'hsl(' + (i * 3) + ', 80%, 60%)';
                        ctx.fillRect(x, H - barH, barWidth, barH);
                        x += barWidth + 1;
                    }
                }
                draw();
            }
        })
        .catch(function (err) {
            statusEl.innerHTML = '<span class="text-danger">Microphone access denied: ' + err.message + '</span>';
        });
}

function adminStopVoiceRecording(mode) {
    if (adminVoiceRecorder && adminVoiceRecorder.state === 'recording') {
        adminVoiceRecorder.stop();
    }
    var labelEl = mode === 'enroll'
        ? document.getElementById('voice-admin-record-label')
        : document.getElementById('voice-admin-attend-label');
    if (labelEl) labelEl.textContent = mode === 'enroll' ? 'Start Recording' : 'Record & Mark';
}

function adminUploadVoiceSample(blob, studentNo, courseCode) {
    var statusEl = document.getElementById('voice-admin-enroll-status');
    var labelEl = document.getElementById('voice-admin-record-label');
    var fd = new FormData();
    fd.append('action', 'enroll_voice');
    fd.append('student_no', studentNo);
    fd.append('course_code', courseCode);
    fd.append('audio', blob, studentNo + '_voice.webm');

    fetch('/csc2052/api/voice.php', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': getCsrf() },
        body: fd
    })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.status === 'success') {
                statusEl.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle me-1"></i>Voice sample enrolled!</span>';
                if (labelEl) labelEl.textContent = 'Start Recording';
                var playBtn = document.getElementById('voice-admin-playback');
                if (playBtn) { playBtn.disabled = false; playBtn.dataset.url = d.data ? d.data.voice_url : ''; }
            } else {
                statusEl.innerHTML = '<span class="text-danger">' + (d.message || 'Upload failed.') + '</span>';
                if (labelEl) labelEl.textContent = 'Start Recording';
            }
        })
        .catch(function () {
            statusEl.innerHTML = '<span class="text-danger">Network error.</span>';
            if (labelEl) labelEl.textContent = 'Start Recording';
        });
}

function adminSubmitVoiceAttendance(blob, studentNo, courseCode) {
    var statusEl = document.getElementById('voice-admin-attend-status');
    var labelEl = document.getElementById('voice-admin-attend-label');

    var fd = new FormData();
    fd.append('action', 'voice_attendance');
    fd.append('student_no', studentNo);
    fd.append('course_code', courseCode);
    fd.append('audio', blob, studentNo + '_voice.webm');

    fetch('/csc2052/api/voice.php', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': getCsrf() },
        body: fd
    })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.status === 'success') {
                statusEl.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle me-1"></i>Attendance marked for ' + studentNo + '!</span>';
                if (labelEl) labelEl.textContent = 'Record & Mark';
                adminLoadVoiceRecentLogs();
                if (typeof fetchLogs === 'function') fetchLogs();
            } else if (d.status === 'duplicate') {
                statusEl.innerHTML = '<span class="text-warning fw-bold"><i class="bi bi-exclamation-circle me-1"></i>Already marked today.</span>';
                if (labelEl) labelEl.textContent = 'Record & Mark';
            } else {
                statusEl.innerHTML = '<span class="text-danger">' + (d.message || 'Failed.') + '</span>';
                if (labelEl) labelEl.textContent = 'Record & Mark';
            }
        })
        .catch(function () {
            statusEl.innerHTML = '<span class="text-danger">Network error.</span>';
            if (labelEl) labelEl.textContent = 'Record & Mark';
        });
}

window.adminPlayVoiceSample = function () {
    var url = document.getElementById('voice-admin-playback')?.dataset.url;
    if (!url) return;
    var a = new Audio(url);
    a.play().catch(function (e) { console.error('[Voice] Playback error:', e); });
};

function adminLoadVoiceRecentLogs() {
    var el = document.getElementById('voice-admin-recent-list');
    if (!el) return;
    fetch('/csc2052/api/voice.php?action=get_voice_logs')
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.logs || d.logs.length === 0) {
                el.innerHTML = '<div class="text-center text-muted py-3 small">No voice attendance records yet.</div>';
                return;
            }
            var h = '<table class="table table-sm mb-0 align-middle"><thead class="table-light"><tr><th>Student</th><th>Course</th><th>Time</th></tr></thead><tbody>';
            d.logs.forEach(function (l) {
                h += '<tr><td><strong>' + esc(l.student_no) + '</strong> <span class="text-muted small">' + (l.student_name || '') + '</span></td>' +
                    '<td><span class="badge bg-light">' + (l.course_code || '') + '</span></td>' +
                    '<td class="small text-muted">' + (l.timestamp || '') + '</td></tr>';
            });
            h += '</tbody></table>';
            el.innerHTML = h;
        })
        .catch(function () { /* silent */ });
}

// Voice student number spoken — plays in Live Attendance Logs
window.speakStudentNo = function (studentNo, studentName) {
    if ('speechSynthesis' in window) {
        var msg = new SpeechSynthesisUtterance(studentName || studentNo);
        msg.lang = 'en-US';
        msg.rate = 0.85;
        msg.pitch = 1.0;
        window.speechSynthesis.speak(msg);
    }
};

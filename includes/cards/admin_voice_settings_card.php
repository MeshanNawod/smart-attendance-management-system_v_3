<?php
$vs = [];
try {
    $st = $pdo->query("SELECT * FROM voice_settings WHERE id = 1 LIMIT 1");
    $vs = $st->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $vs = []; }
$vsRate = $vs['rate'] ?? 1.0;
$vsPitch = $vs['pitch'] ?? 1.0;
$vsVoice = $vs['voice_name'] ?? '';
$vsName  = (int)($vs['announce_student_name'] ?? 1);
$vsNo    = (int)($vs['announce_student_no'] ?? 0);
$vsMod   = (int)($vs['announce_modality'] ?? 1);
$vsCourse= (int)($vs['announce_course'] ?? 0);
$vsTs    = (int)($vs['announce_timestamp'] ?? 0);
$vsUnk   = (int)($vs['announce_unknown_card'] ?? 1);
$vsCols  = $vs['logs_columns'] ?? 'student_no,student_name,modality,course_code,timestamp';
?>
<div class="card card-clean shadow-none mb-4 border-0">
    <div class="card-header bg-white py-3 border-bottom">
        <strong class="text-dark"><i class="bi bi-megaphone me-2"></i>Voice Announcement Settings</strong>
    </div>
    <div class="card-body bg-light">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="mb-2">
                    <label class="form-label small fw-bold">Speech Rate (0.5 – 2.0)</label>
                    <input type="range" class="form-range" min="0.5" max="2.0" step="0.1" value="<?php echo $vsRate; ?>" id="vs-rate" oninput="document.getElementById('vs-rate-val').textContent=this.value">
                    <span class="badge bg-primary" id="vs-rate-val"><?php echo $vsRate; ?></span>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">Pitch (0.5 – 2.0)</label>
                    <input type="range" class="form-range" min="0.5" max="2.0" step="0.1" value="<?php echo $vsPitch; ?>" id="vs-pitch" oninput="document.getElementById('vs-pitch-val').textContent=this.value">
                    <span class="badge bg-primary" id="vs-pitch-val"><?php echo $vsPitch; ?></span>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">Voice Name (leave empty for default)</label>
                    <input type="text" class="form-control" id="vs-voice" value="<?php echo htmlspecialchars($vsVoice); ?>" placeholder="e.g. Google US English">
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-bold">Announce When Card Scanned:</label>
                <div class="form-check"><input class="form-check-input" type="checkbox" id="vs-ann-name" <?php echo $vsName?'checked':''; ?>><label class="form-check-label small">Student Name</label></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" id="vs-ann-no" <?php echo $vsNo?'checked':''; ?>><label class="form-check-label small">Student Number</label></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" id="vs-ann-mod" <?php echo $vsMod?'checked':''; ?>><label class="form-check-label small">Modality (fingerprint/RFID/face)</label></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" id="vs-ann-course" <?php echo $vsCourse?'checked':''; ?>><label class="form-check-label small">Course Code</label></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" id="vs-ann-ts" <?php echo $vsTs?'checked':''; ?>><label class="form-check-label small">Timestamp</label></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" id="vs-ann-unk" <?php echo $vsUnk?'checked':''; ?>><label class="form-check-label small">Unknown Card Warning</label></div>
            </div>
        </div>
        <div class="mt-2">
            <label class="form-label small fw-bold">Logs Table Columns (comma-separated)</label>
            <input type="text" class="form-control form-control-sm" id="vs-cols" value="<?php echo htmlspecialchars($vsCols); ?>">
        </div>
        <button class="btn btn-primary mt-2" onclick="saveVoiceSettings()"><i class="bi bi-save me-1"></i>Save Voice Settings</button>
        <span id="vs-save-msg" class="small text-success ms-2"></span>
    </div>
</div>

<script>
function saveVoiceSettings() {
    const fd = new FormData();
    fd.append('rate', document.getElementById('vs-rate').value);
    fd.append('pitch', document.getElementById('vs-pitch').value);
    fd.append('voice_name', document.getElementById('vs-voice').value);
    fd.append('announce_student_name', document.getElementById('vs-ann-name').checked ? 1 : 0);
    fd.append('announce_student_no', document.getElementById('vs-ann-no').checked ? 1 : 0);
    fd.append('announce_modality', document.getElementById('vs-ann-mod').checked ? 1 : 0);
    fd.append('announce_course', document.getElementById('vs-ann-course').checked ? 1 : 0);
    fd.append('announce_timestamp', document.getElementById('vs-ann-ts').checked ? 1 : 0);
    fd.append('announce_unknown_card', document.getElementById('vs-ann-unk').checked ? 1 : 0);
    fd.append('logs_columns', document.getElementById('vs-cols').value);
    fetch('/csc2052/api/voice_settings.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(d => {
            document.getElementById('vs-save-msg').textContent = d.status === 'success' ? 'Saved!' : 'Error: ' + d.message;
            setTimeout(() => document.getElementById('vs-save-msg').textContent = '', 3000);
        }).catch(e => console.error(e));
}
window.voiceSettingsCache = null;
async function getVoiceSettings() {
    if (window.voiceSettingsCache) return window.voiceSettingsCache;
    try {
        const r = await fetch('/csc2052/api/voice_settings.php');
        const d = await r.json();
        if (d.status === 'success') window.voiceSettingsCache = d.settings;
        return window.voiceSettingsCache || {};
    } catch(e) { return {}; }
}
// Patch speakVoice to use settings
const _origSpeakVoice = window.speakVoice || function(){};
window.speakVoice = async function(text) {
    if ('speechSynthesis' in window) {
        const globalMute = document.getElementById('global-voice-mute')?.checked;
        if (globalMute) return;
        const s = await getVoiceSettings();
        const utter = new SpeechSynthesisUtterance(text);
        utter.rate = parseFloat(s.rate) || 1;
        utter.pitch = parseFloat(s.pitch) || 1;
        if (s.voice_name) {
            const voices = window.speechSynthesis.getVoices();
            const found = voices.find(v => v.name === s.voice_name);
            if (found) utter.voice = found;
        }
        window.speechSynthesis.speak(utter);
    }
};
</script>

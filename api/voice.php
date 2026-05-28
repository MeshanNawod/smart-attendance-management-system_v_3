<?php
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
    case 'enroll_voice':
    case 'get_course_students':
    case 'get_voice_logs':
        require __DIR__ . '/attendance/voice/enroll.php';
        break;
    case 'voice_attendance':
        require __DIR__ . '/attendance/voice/record.php';
        break;
    default:
        require __DIR__ . '/attendance/voice/enroll.php';
        break;
}

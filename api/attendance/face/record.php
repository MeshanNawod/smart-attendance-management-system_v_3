<?php
require_once __DIR__ . '/../../../includes/config/app.php';

header('Content-Type: application/json; charset=utf-8');

$buffer_file = __DIR__ . '/../../../sys_latest_face.txt';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'consume') {
    require __DIR__ . '/../../../includes/auth/session.php';

    if (!file_exists($buffer_file)) {
        echo json_encode(['status' => 'empty']);
        exit;
    }

    $face_id = trim(file_get_contents($buffer_file));
    if (!empty($face_id)) {
        file_put_contents($buffer_file, '', LOCK_EX);
        echo json_encode(['status' => 'success', 'face_id' => $face_id]);
    } else {
        echo json_encode(['status' => 'empty']);
    }
    exit;
}

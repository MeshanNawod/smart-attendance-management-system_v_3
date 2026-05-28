<?php
$modality = trim($_POST['modality'] ?? $_GET['modality'] ?? 'fingerprint');

switch ($modality) {
    case 'rfid':
        require __DIR__ . '/attendance/rfid/record.php';
        break;
    case 'fingerprint':
    default:
        require __DIR__ . '/attendance/fingerprint/record.php';
        break;
}

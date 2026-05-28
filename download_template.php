<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Only admin/teacher can download templates
if (!isAdmin() && !isTeacher()) {
    http_response_code(403);
    die('Access denied.');
}

$bulk = isset($_GET['bulk']) && $_GET['bulk'] === 'true';
$studentNo = trim($_GET['student_no'] ?? '');

if ($bulk) {
    // Bulk download all students with fp_template_hex
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="fp_templates_backup_' . date('Ymd_His') . '.txt"');
    
    try {
        $stmt = $pdo->query("SELECT student_no, student_name, fingerprint_id, rfid_uid, fp_template_hex FROM students WHERE fp_template_hex IS NOT NULL AND fp_template_hex != ''");
        $count = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $count++;
            echo "=== STUDENT: " . $row['student_no'] . " ===\n";
            echo "Name: " . $row['student_name'] . "\n";
            echo "FP_ID: " . ($row['fingerprint_id'] ?? '') . "\n";
            echo "RFID_UID: " . ($row['rfid_uid'] ?? '') . "\n";
            echo "Template:\n" . chunk_split($row['fp_template_hex'], 64, "\n") . "\n\n";
        }
        if ($count === 0) {
            echo "No fingerprint templates found in database.\n";
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    exit;
}

if (empty($studentNo)) {
    http_response_code(400);
    die('student_no parameter required.');
}

try {
    $stmt = $pdo->prepare("SELECT student_name, fingerprint_id, rfid_uid, fp_template_hex FROM students WHERE student_no = ?");
    $stmt->execute([$studentNo]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        http_response_code(404);
        die('Student not found.');
    }

    if (empty($student['fp_template_hex'])) {
        // No template stored – fallback: create a placeholder file
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $studentNo . '_fp_template.txt"');
        echo "Student: " . $studentNo . "\n";
        echo "Name: " . ($student['student_name'] ?? '') . "\n";
        echo "FP_ID: " . ($student['fingerprint_id'] ?? 'N/A') . "\n";
        echo "RFID_UID: " . ($student['rfid_uid'] ?? 'N/A') . "\n";
        echo "Template: (none stored)\n";
        exit;
    }

    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $studentNo . '_fp_template.txt"');
    echo "Student: " . $studentNo . "\n";
    echo "Name: " . ($student['student_name'] ?? '') . "\n";
    echo "FP_ID: " . ($student['fingerprint_id'] ?? '') . "\n";
    echo "RFID_UID: " . ($student['rfid_uid'] ?? '') . "\n";
    echo "Template:\n";
    echo chunk_split($student['fp_template_hex'], 64, "\n");

} catch (PDOException $e) {
    http_response_code(500);
    die('Database error: ' . $e->getMessage());
}

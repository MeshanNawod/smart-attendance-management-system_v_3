<?php
require_once __DIR__ . '/../../../includes/config/app.php';
require_once __DIR__ . '/../../../includes/auth/session.php';
require_once __DIR__ . '/../../../includes/database/connection.php';
require_once __DIR__ . '/../../../includes/helpers/general.php';
require_once __DIR__ . '/../../../includes/attendance/helper.php';

requireCsrfIfMutating();

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

switch ($action) {

    case 'manual_attendance':
        $stu_no      = substr(trim($_POST['student_no'] ?? ''), 0, 50);
        $course_code = substr(preg_replace('/[^a-zA-Z0-9_\- ]/', '', trim($_POST['course_code'] ?? '')), 0, 30);
        $timestamp   = trim($_POST['timestamp'] ?? '');
        $modality    = trim($_POST['modality'] ?? 'manual');

        if (empty($stu_no)) {
            respond('error', 'Student number is required.', 422);
        }
        if (empty($course_code)) {
            respond('error', 'Course code is required.', 422);
        }

        if (!empty($timestamp) && strtotime($timestamp) !== false) {
            $timestamp = date('Y-m-d H:i:s', strtotime($timestamp));
        } else {
            $timestamp = date('Y-m-d H:i:s');
        }

        try {
            $result = recordLectureAttendance($pdo, $stu_no, $course_code, 'WEB_DASHBOARD', $modality, $timestamp);

            if ($result['status'] === 'not_enrolled') {
                respond('error', 'Student not enrolled in this course.', 403);
            }
            if ($result['status'] === 'no_session') {
                respond('error', 'No active lecture session for this course. Start a lecture first.', 403);
            }

            $msg = $result['status'] === 'updated' ? "Manual attendance updated for {$stu_no} (re-scan)." : "Manual attendance recorded for {$stu_no}.";
            respond('success', $msg);
        } catch (PDOException $e) {
            error_log('[Manual Attendance] ' . $e->getMessage());
            respond('error', 'Database error while recording attendance.', 500);
        }
        break;

    case 'mark_attendance':
        $studentNo  = strtoupper(trim($_POST['student_no'] ?? ''));
        $courseCode = strtoupper(trim($_POST['course_code'] ?? ''));

        if (!$studentNo) {
            respond('error', 'Student number required.', 422);
        }
        if (!$courseCode) {
            respond('error', 'Course code required.', 422);
        }

        try {
            $stmt = $pdo->prepare("SELECT student_no FROM students WHERE student_no = ?");
            $stmt->execute([$studentNo]);
            if (!$stmt->fetch()) {
                respond('error', 'Student not found.', 404);
            }

            $result = recordLectureAttendance($pdo, $studentNo, $courseCode, 'Teacher Portal', 'manual', date('Y-m-d H:i:s'));

            if ($result['status'] === 'not_enrolled') {
                respond('error', 'Student is not enrolled in this course.', 403);
            } elseif ($result['status'] === 'no_session') {
                respond('error', 'No active lecture session for this course. Start a lecture first.', 403);
            } elseif ($result['status'] === 'updated') {
                respond('success', 'Attendance re-scanned — record updated.');
            } else {
                respond('success', 'Attendance marked.');
            }
        } catch (PDOException $e) {
            respond('error', 'Database error.', 500);
        }
        break;

    default:
        respond('error', 'Unknown action.', 400);
}

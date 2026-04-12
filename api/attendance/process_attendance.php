<?php
header('Content-Type: application/json');
require_once '../../includes/db.php';

// Set timezone to user's local time
date_default_timezone_set('Asia/Kabul');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id = $_SESSION['user_id'];

try {
    // Get attendance settings
    $stmt = $pdo->prepare("
        SELECT * FROM attendance_settings
        WHERE tenant_id = ? AND branch_id = ?
    ");
    $stmt->execute([$tenant_id, $branch_id]);
    $attendance_settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attendance_settings) {
        // Create default settings if not exist
        $stmt = $pdo->prepare("
            INSERT INTO attendance_settings (tenant_id, branch_id, office_start_time, office_end_time, late_after_minutes, half_day_minutes, working_days)
            VALUES (?, ?, '09:00:00', '17:00:00', 15, 240, 'Mon-Fri')
        ");
        $stmt->execute([$tenant_id, $branch_id]);

        $attendance_settings = [
            'office_start_time' => '09:00:00',
            'office_end_time' => '17:00:00',
            'late_after_minutes' => 15,
            'half_day_minutes' => 240,
            'working_days' => 'Mon-Fri'
        ];
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'check_in') {
        // Check if already checked in today
        $stmt = $pdo->prepare("
            SELECT id FROM attendance
            WHERE tenant_id = ? AND branch_id = ? AND user_id = ? AND date = CURDATE()
        ");
        $stmt->execute([$tenant_id, $branch_id, $user_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            echo json_encode(['success' => false, 'message' => 'Already checked in today']);
            exit();
        }

        // Check in
        $now = date('H:i:s');
        $stmt = $pdo->prepare("
            INSERT INTO attendance (tenant_id, branch_id, user_id, date, check_in_time, working_minutes, status)
            VALUES (?, ?, ?, CURDATE(), ?, 0, 'present')
        ");
        $stmt->execute([$tenant_id, $branch_id, $user_id, $now]);

        echo json_encode(['success' => true, 'message' => 'Checked in successfully']);

    } elseif ($action === 'check_out') {
        // Get today's attendance
        $stmt = $pdo->prepare("
            SELECT * FROM attendance
            WHERE tenant_id = ? AND branch_id = ? AND user_id = ? AND date = CURDATE()
        ");
        $stmt->execute([$tenant_id, $branch_id, $user_id]);
        $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attendance) {
            echo json_encode(['success' => false, 'message' => 'No check-in record found for today']);
            exit();
        }

        if ($attendance['check_out_time']) {
            echo json_encode(['success' => false, 'message' => 'Already checked out today']);
            exit();
        }

        // Check out and calculate working minutes and status
        $check_out_time = date('H:i:s');
        $check_in_time = $attendance['check_in_time'];

        // Calculate working minutes
        $check_in_timestamp = strtotime($check_in_time);
        $check_out_timestamp = strtotime($check_out_time);
        $working_minutes = round(($check_out_timestamp - $check_in_timestamp) / 60);

        // Determine status
        $office_start = strtotime($attendance_settings['office_start_time']);
        $office_end = strtotime($attendance_settings['office_end_time']);
        $expected_minutes = round(($office_end - $office_start) / 60);
        $half_day_minutes = $attendance_settings['half_day_minutes'];
        $late_after_minutes = $attendance_settings['late_after_minutes'];

        // Check if late
        $late_threshold = $office_start + ($late_after_minutes * 60);
        $is_late = $check_in_timestamp > $late_threshold;

        // Determine final status based on working minutes
        if ($working_minutes >= $expected_minutes) {
            $status = $is_late ? 'late' : 'present';
        } elseif ($working_minutes >= $half_day_minutes) {
            $status = 'half_day';
        } else {
            $status = 'absent';
        }

        // Update attendance record
        $stmt = $pdo->prepare("
            UPDATE attendance
            SET check_out_time = ?, working_minutes = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([$check_out_time, $working_minutes, $status, $attendance['id']]);

        echo json_encode(['success' => true, 'message' => 'Checked out successfully']);

    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing attendance']);
}
?>
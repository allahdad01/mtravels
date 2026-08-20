<?php
// Initialize the session
session_start();

require_once __DIR__ . '/../includes/permissions.php';
require_permission('hr.attendance');

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$user_id_session = $_SESSION['user_id'] ?? null;

if (!$user_id_session) {
    echo json_encode(['success' => false, 'message' => 'User session invalid']);
    exit;
}

// Include config file
require_once "../includes/db.php";

// Get POST data
$user_id = $_POST['user_id'] ?? null;
$payment_for_month = $_POST['payment_for_month'] ?? null;
$currency = $_POST['currency'] ?? null;

if (!$user_id || !$payment_for_month || !$currency) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Fetch allowed features from plans table
$query = "
    SELECT p.features
    FROM tenant_subscriptions ts
    JOIN plans p ON ts.plan_id = p.id
    WHERE ts.tenant_id = ? AND ts.status IN ('active', 'trial')
    ORDER BY ts.start_date DESC
    LIMIT 1
";
$stmt = $pdo->prepare($query);
$stmt->execute([$tenant_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$allowed_features = $row ? json_decode($row['features'], true) : [];

$has_attendance_feature = in_array('attendance', $allowed_features);

if (!$has_attendance_feature) {
    echo json_encode(['success' => false, 'message' => 'Attendance feature not enabled']);
    exit;
}

// Get base salary
$sql = "SELECT base_salary FROM salary_management WHERE user_id = ? AND currency = ? AND status = 'active' AND tenant_id = ? AND branch_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(1, $user_id, PDO::PARAM_INT);
$stmt->bindParam(2, $currency, PDO::PARAM_STR);
$stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$salary = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$salary) {
    echo json_encode(['success' => false, 'message' => 'Salary record not found']);
    exit;
}

$base_salary = $salary['base_salary'];

// Get absent days for the month
$sql = "SELECT COUNT(*) as absent_days FROM attendance WHERE user_id = ? AND DATE_FORMAT(date, '%Y-%m') = ? AND status = 'absent' AND tenant_id = ? AND branch_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(1, $user_id, PDO::PARAM_INT);
$stmt->bindParam(2, $payment_for_month, PDO::PARAM_STR);
$stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$attendance = $stmt->fetch(PDO::FETCH_ASSOC);

$absent_days = $attendance['absent_days'] ?? 0;

if ($absent_days == 0) {
    echo json_encode(['success' => false, 'message' => 'No absent days found']);
    exit;
}

// Check if already deducted
$sql = "SELECT id FROM salary_deductions WHERE user_id = ? AND type = 'absence' AND DATE_FORMAT(deduction_date, '%Y-%m') = ? AND tenant_id = ? AND branch_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(1, $user_id, PDO::PARAM_INT);
$stmt->bindParam(2, $payment_for_month, PDO::PARAM_STR);
$stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$existing_deduction = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing_deduction) {
    echo json_encode(['success' => false, 'message' => 'Absence already deducted for this month']);
    exit;
}

// Calculate deduction amount
$deduction_per_day = $base_salary / 30;
$total_deduction = $deduction_per_day * $absent_days;

// Insert deduction
$description = "Absence deduction for $absent_days days in $payment_for_month";
$deduction_date = date('Y-m-d');

$sql = "INSERT INTO salary_deductions (tenant_id, branch_id, user_id, amount, description, deduction_date, type, created_by)
        VALUES (?, ?, ?, ?, ?, ?, 'absence', ?)";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->bindParam(3, $user_id, PDO::PARAM_INT);
$stmt->bindParam(4, $total_deduction, PDO::PARAM_STR);
$stmt->bindParam(5, $description, PDO::PARAM_STR);
$stmt->bindParam(6, $deduction_date, PDO::PARAM_STR);
$stmt->bindParam(7, $user_id_session, PDO::PARAM_INT);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Absence deduction added successfully', 'deducted_amount' => $total_deduction]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to add deduction']);
}
?>
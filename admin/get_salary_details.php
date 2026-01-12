<?php
// Initialize the session
session_start();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Prevent any unwanted output
error_reporting(0);
ini_set('display_errors', 0);

// Set JSON header
header('Content-Type: application/json');


// Include config file
require_once "../includes/db.php";

// Fetch allowed features
$query = "
    SELECT p.features
    FROM tenant_subscriptions ts
    JOIN plans p ON ts.plan_id = p.id
    WHERE ts.tenant_id = ? AND ts.status = 'active'
    ORDER BY ts.start_date DESC
    LIMIT 1
";
$stmt = $pdo->prepare($query);
$stmt->execute([$tenant_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$allowed_features = $row ? json_decode($row['features'], true) : [];

// Helper function
function hasFeature($feature, $allowed_features) {
    return in_array($feature, $allowed_features);
}

// Get POST data
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$currency = isset($_POST['currency']) ? $_POST['currency'] : '';
$payment_for_month = isset($_POST['payment_for_month']) ? $_POST['payment_for_month'] : date('Y-m');

// Validate input
if (!$user_id || !$currency) {
    echo json_encode(['error' => 'Invalid input parameters']);
    exit;
}

try {
    // First check if salary has already been paid for this month
    $payment_check_sql = "SELECT id, amount, payment_date
                         FROM salary_payments
                         WHERE user_id = ? AND tenant_id = ? AND branch_id = ?
                         AND currency = ?
                         AND payment_type = 'regular'
                         AND DATE_FORMAT(payment_for_month, '%Y-%m') = ?";

    $payment_check_stmt = $pdo->prepare($payment_check_sql);
    $payment_check_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $payment_check_stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $payment_check_stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $payment_check_stmt->bindParam(4, $currency, PDO::PARAM_STR);
    $payment_check_stmt->bindParam(5, $payment_for_month, PDO::PARAM_STR);

    if (!$payment_check_stmt->execute()) {
        throw new Exception("Execute failed for payment check");
    }

    $payment_check_result = $payment_check_stmt->fetchAll();
    $existing_payment = count($payment_check_result) > 0 ? $payment_check_result[0] : null;

    // Get total advances for this month
    $advance_sql = "SELECT COALESCE(SUM(amount), 0) as total_advances
                   FROM salary_advances
                   WHERE user_id = ? AND tenant_id = ? AND branch_id = ?
                   AND currency = ?
                   AND DATE_FORMAT(created_at, '%Y-%m') = ?";

    $advance_stmt = $pdo->prepare($advance_sql);
    $advance_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $advance_stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $advance_stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $advance_stmt->bindParam(4, $currency, PDO::PARAM_STR);
    $advance_stmt->bindParam(5, $payment_for_month, PDO::PARAM_STR);

    if (!$advance_stmt->execute()) {
        throw new Exception("Execute failed for advances");
    }

    $advance_result = $advance_stmt->fetch();
    $totalAdvances = floatval($advance_result['total_advances']);

    // Get total deductions for this month
    $deduction_sql = "SELECT COALESCE(SUM(amount), 0) as total_deductions
                     FROM salary_deductions
                     WHERE user_id = ? AND tenant_id = ? AND branch_id = ?
                     AND DATE_FORMAT(deduction_date, '%Y-%m') = ?";

    $deduction_stmt = $pdo->prepare($deduction_sql);
    $deduction_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $deduction_stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $deduction_stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $deduction_stmt->bindParam(4, $payment_for_month, PDO::PARAM_STR);

    if (!$deduction_stmt->execute()) {
        throw new Exception("Execute failed for deductions");
    }

    $deduction_result = $deduction_stmt->fetch();
    $totalDeductions = floatval($deduction_result['total_deductions']);

    // Get total bonuses for this month
    $bonus_sql = "SELECT COALESCE(SUM(amount), 0) as total_bonuses
                 FROM salary_bonuses
                 WHERE user_id = ? AND tenant_id = ? AND branch_id = ?
                 AND DATE_FORMAT(bonus_date, '%Y-%m') = ?";

    $bonus_stmt = $pdo->prepare($bonus_sql);
    $bonus_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $bonus_stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $bonus_stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $bonus_stmt->bindParam(4, $payment_for_month, PDO::PARAM_STR);

    if (!$bonus_stmt->execute()) {
        throw new Exception("Execute failed for bonuses");
    }

    $bonus_result = $bonus_stmt->fetch();
    $totalBonuses = floatval($bonus_result['total_bonuses']);

    // Check if attendance feature is enabled
    $has_attendance_feature = hasFeature('attendance', $allowed_features);

    $absent_days = 0;
    $absence_already_deducted = false;
    if ($has_attendance_feature) {
        // Get absent days for this month
        $attendance_sql = "SELECT COUNT(*) as absent_days
                          FROM attendance
                          WHERE user_id = ? AND tenant_id = ? AND branch_id = ?
                          AND status = 'absent'
                          AND DATE_FORMAT(date, '%Y-%m') = ?";

        $attendance_stmt = $pdo->prepare($attendance_sql);
        $attendance_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $attendance_stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $attendance_stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $attendance_stmt->bindParam(4, $payment_for_month, PDO::PARAM_STR);

        if (!$attendance_stmt->execute()) {
            throw new Exception("Execute failed for attendance");
        }

        $attendance_result = $attendance_stmt->fetch();
        $absent_days = intval($attendance_result['absent_days']);

        // Check if absence has already been deducted
        $absence_deduction_sql = "SELECT COUNT(*) as deduction_count
                                 FROM salary_deductions
                                 WHERE user_id = ? AND tenant_id = ? AND branch_id = ?
                                 AND type = 'absence'
                                 AND DATE_FORMAT(deduction_date, '%Y-%m') = ?";

        $absence_deduction_stmt = $pdo->prepare($absence_deduction_sql);
        $absence_deduction_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $absence_deduction_stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $absence_deduction_stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $absence_deduction_stmt->bindParam(4, $payment_for_month, PDO::PARAM_STR);

        if (!$absence_deduction_stmt->execute()) {
            throw new Exception("Execute failed for absence deduction check");
        }

        $absence_deduction_result = $absence_deduction_stmt->fetch();
        $absence_already_deducted = intval($absence_deduction_result['deduction_count']) > 0;
    }

    // Return the results
    $response = [
        'totalAdvances' => $totalAdvances,
        'totalDeductions' => $totalDeductions,
        'totalBonuses' => $totalBonuses,
        'salaryAlreadyPaid' => !empty($existing_payment),
        'existingPayment' => $existing_payment,
        'has_attendance_feature' => $has_attendance_feature,
        'absent_days' => $absent_days,
        'absence_already_deducted' => $absence_already_deducted
    ];

    echo json_encode($response);
    exit;

} catch (Exception $e) {
    // Log the detailed error
    error_log("Error in get_salary_details.php: " . $e->getMessage());
    
    // Return a more specific error message for debugging
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage(),
        'user_id' => $user_id,
        'currency' => $currency,
        'payment_for_month' => $payment_for_month
    ]);
    exit;
}

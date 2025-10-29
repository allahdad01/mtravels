<?php
// Initialize the session
session_start();
$tenant_id = $_SESSION['tenant_id'];
// Prevent any unwanted output
error_reporting(0);
ini_set('display_errors', 0);

// Set JSON header
header('Content-Type: application/json');


// Include config file
require_once "../includes/db.php";

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
                         WHERE user_id = ? AND tenant_id = ?
                         AND currency = ? 
                         AND payment_type = 'regular'
                         AND DATE_FORMAT(payment_for_month, '%Y-%m') = ?";
                    
    if (!($payment_check_stmt = mysqli_prepare($conection_db, $payment_check_sql))) {
        throw new Exception("Prepare failed for payment check: " . mysqli_error($conection_db));
    }
    
    if (!mysqli_stmt_bind_param($payment_check_stmt, "iiss", $user_id, $tenant_id, $currency, $payment_for_month)) {
        throw new Exception("Bind param failed for payment check: " . mysqli_stmt_error($payment_check_stmt));
    }
    
    if (!mysqli_stmt_execute($payment_check_stmt)) {
        throw new Exception("Execute failed for payment check: " . mysqli_stmt_error($payment_check_stmt));
    }
    
    $payment_check_result = mysqli_stmt_get_result($payment_check_stmt);
    if (!$payment_check_result) {
        throw new Exception("Get result failed for payment check: " . mysqli_error($conection_db));
    }
    
    $existing_payment = mysqli_fetch_assoc($payment_check_result);

    // Get total advances for this month
    $advance_sql = "SELECT COALESCE(SUM(amount), 0) as total_advances 
                    FROM salary_advances 
                    WHERE user_id = ? AND tenant_id = ?
                    AND currency = ? 
                    AND DATE_FORMAT(created_at, '%Y-%m') = ?";
                    
    if (!($advance_stmt = mysqli_prepare($conection_db, $advance_sql))) {
        throw new Exception("Prepare failed for advances: " . mysqli_error($conection_db));
    }
    
    if (!mysqli_stmt_bind_param($advance_stmt, "iiss", $user_id, $tenant_id, $currency, $payment_for_month)) {
        throw new Exception("Bind param failed for advances: " . mysqli_stmt_error($advance_stmt));
    }
    
    if (!mysqli_stmt_execute($advance_stmt)) {
        throw new Exception("Execute failed for advances: " . mysqli_stmt_error($advance_stmt));
    }
    
    $advance_result = mysqli_stmt_get_result($advance_stmt);
    if (!$advance_result) {
        throw new Exception("Get result failed for advances: " . mysqli_error($conection_db));
    }
    
    $advance_row = mysqli_fetch_assoc($advance_result);
    $totalAdvances = floatval($advance_row['total_advances']);

    // Get total deductions for this month
    $deduction_sql = "SELECT COALESCE(SUM(amount), 0) as total_deductions 
                      FROM salary_deductions 
                      WHERE user_id = ? AND tenant_id = ?
                      AND DATE_FORMAT(deduction_date, '%Y-%m') = ?";
                      
    if (!($deduction_stmt = mysqli_prepare($conection_db, $deduction_sql))) {
        throw new Exception("Prepare failed for deductions: " . mysqli_error($conection_db));
    }
    
    if (!mysqli_stmt_bind_param($deduction_stmt, "iis", $user_id, $tenant_id, $payment_for_month)) {
        throw new Exception("Bind param failed for deductions: " . mysqli_stmt_error($deduction_stmt));
    }
    
    if (!mysqli_stmt_execute($deduction_stmt)) {
        throw new Exception("Execute failed for deductions: " . mysqli_stmt_error($deduction_stmt));
    }
    
    $deduction_result = mysqli_stmt_get_result($deduction_stmt);
    if (!$deduction_result) {
        throw new Exception("Get result failed for deductions: " . mysqli_error($conection_db));
    }
    
    $deduction_row = mysqli_fetch_assoc($deduction_result);
    $totalDeductions = floatval($deduction_row['total_deductions']);

    // Get total bonuses for this month
    $bonus_sql = "SELECT COALESCE(SUM(amount), 0) as total_bonuses 
                  FROM salary_bonuses 
                  WHERE user_id = ? AND tenant_id = ?
                  AND DATE_FORMAT(bonus_date, '%Y-%m') = ?";
                  
    if (!($bonus_stmt = mysqli_prepare($conection_db, $bonus_sql))) {
        throw new Exception("Prepare failed for bonuses: " . mysqli_error($conection_db));
    }
    
    if (!mysqli_stmt_bind_param($bonus_stmt, "iis", $user_id, $tenant_id, $payment_for_month)) {
        throw new Exception("Bind param failed for bonuses: " . mysqli_stmt_error($bonus_stmt));
    }
    
    if (!mysqli_stmt_execute($bonus_stmt)) {
        throw new Exception("Execute failed for bonuses: " . mysqli_stmt_error($bonus_stmt));
    }
    
    $bonus_result = mysqli_stmt_get_result($bonus_stmt);
    if (!$bonus_result) {
        throw new Exception("Get result failed for bonuses: " . mysqli_error($conection_db));
    }
    
    $bonus_row = mysqli_fetch_assoc($bonus_result);
    $totalBonuses = floatval($bonus_row['total_bonuses']);

    // Return the results
    $response = [
        'totalAdvances' => $totalAdvances,
        'totalDeductions' => $totalDeductions,
        'totalBonuses' => $totalBonuses,
        'salaryAlreadyPaid' => !empty($existing_payment),
        'existingPayment' => $existing_payment
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

// Close database connection
if (isset($conection_db)) {
    mysqli_close($conection_db);
} 
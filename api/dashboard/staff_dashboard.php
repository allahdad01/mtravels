<?php
/**
 * Staff Dashboard Data Handler
 * Fetches attendance and payment data for staff members
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/db.php';

$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];

// Get user's first name
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ? AND tenant_id = ?");
$stmt->execute([$user_id, $tenant_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$first_name = $user_data['name'] ?? 'Staff';
// Get first word if name has spaces
$first_name = explode(' ', $first_name)[0];

// Get current month attendance data
$current_month = date('m');
$current_year = date('Y');

$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status IN ('late', 'half_day') THEN 1 ELSE 0 END) as `leave`
    FROM attendance
    WHERE user_id = ? 
    AND tenant_id = ?
    AND MONTH(`date`) = ?
    AND YEAR(`date`) = ?
");
$stmt->execute([$user_id, $tenant_id, $current_month, $current_year]);
$attendance = $stmt->fetch(PDO::FETCH_ASSOC);

$total = $attendance['total'] ?? 0;
$present = $attendance['present'] ?? 0;
$absent = $attendance['absent'] ?? 0;
$leave = $attendance['leave'] ?? 0;
$rate = $total > 0 ? round(($present / $total) * 100, 1) : 0;

// Get recent payments (last 5)
$stmt = $pdo->prepare("
    SELECT 
        id,
        description,
        amount,
        payment_date,
        payment_type as status
    FROM salary_payments
    WHERE user_id = ?
    AND tenant_id = ?
    ORDER BY payment_date DESC
    LIMIT 5
");
$stmt->execute([$user_id, $tenant_id]);
$recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total paid this month
$stmt = $pdo->prepare("
    SELECT SUM(amount) as total_paid
    FROM salary_payments
    WHERE user_id = ?
    AND tenant_id = ?
    AND MONTH(payment_date) = ?
    AND YEAR(payment_date) = ?
");
$stmt->execute([$user_id, $tenant_id, $current_month, $current_year]);
$paid_data = $stmt->fetch(PDO::FETCH_ASSOC);
$total_paid_month = $paid_data['total_paid'] ?? 0;
?>

<?php
// Initialize the session
session_start();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Include config file
require_once "../includes/db.php";

require_once __DIR__ . '/../includes/permissions.php';
require_permission('hr.salary');

// Fetch allowed features
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

// Helper function
function hasFeature($feature, $allowed_features) {
    return in_array($feature, $allowed_features);
}


// Fetch settings data
try {
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->execute([$tenant_id]);
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $settings = ['agency_name' => 'Default Name'];
}

// Fetch branch info
$branchQuery = "SELECT name FROM branches WHERE id = ? AND tenant_id = ?";
$branchStmt = $pdo->prepare($branchQuery);
$branchStmt->execute([$branch_id, $tenant_id]);
$branch = $branchStmt->fetch(PDO::FETCH_ASSOC);

// Define variables
$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;

// Get month name
$monthName = date('F', mktime(0, 0, 0, $month, 1, $year));

// Create a title
$title = $user_id ? "Individual Payroll Report" : "Group Payroll Report";
$subtitle = "For $monthName $year";

// Function to calculate total earnings
function calculateTotalEarnings($base_salary, $bonuses, $deductions, $advances) {
    $totalBonuses = 0;
    foreach ($bonuses as $bonus) {
        $totalBonuses += $bonus['amount'];
    }
    
    $totalDeductions = 0;
    foreach ($deductions as $deduction) {
        $totalDeductions += $deduction['amount'];
    }
    
    $totalAdvances = 0;
    foreach ($advances as $advance) {
        $totalAdvances += $advance['amount'];
    }
    
    return $base_salary + $totalBonuses - $totalDeductions - $totalAdvances;
}

// Prepare SQL query based on whether user_id is provided
if ($user_id) {
    // Query for individual employee
    $employeeQuery = "SELECT sm.*, u.name as employee_name, u.hire_date, u.email, u.phone
                      FROM salary_management sm
                      JOIN users u ON sm.user_id = u.id
                      WHERE sm.user_id = ? AND sm.tenant_id = ? AND sm.branch_id = ?";
    $stmt = $pdo->prepare($employeeQuery);
    $stmt->execute([$user_id, $tenant_id, $branch_id]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Query for all active employees
    $employeeQuery = "SELECT sm.*, u.name as employee_name, u.hire_date, u.email, u.phone
                      FROM salary_management sm
                      JOIN users u ON sm.user_id = u.id
                      WHERE sm.status = 'active' AND sm.tenant_id = ? AND sm.branch_id = ?
                      ORDER BY u.name ASC";
    $stmt = $pdo->prepare($employeeQuery);
    $stmt->execute([$tenant_id, $branch_id]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch salary bonuses for the month
$bonusesQuery = "SELECT * FROM salary_bonuses
                WHERE user_id = ? AND MONTH(bonus_date) = ? AND YEAR(bonus_date) = ? AND tenant_id = ? AND branch_id = ?";
$bonusStmt = $pdo->prepare($bonusesQuery);

// Fetch salary deductions for the month
$deductionsQuery = "SELECT * FROM salary_deductions
                   WHERE user_id = ? AND MONTH(deduction_date) = ? AND YEAR(deduction_date) = ? AND tenant_id = ? AND branch_id = ?";
$deductionStmt = $pdo->prepare($deductionsQuery);

// Fetch salary adjustments for the month (legacy)
$adjustmentsQuery = "SELECT sa.*
                    FROM salary_adjustments sa
                    WHERE sa.user_id = ? AND MONTH(sa.effective_date) = ? AND YEAR(sa.effective_date) = ? AND tenant_id = ? AND branch_id = ?";
$adjustmentStmt = $pdo->prepare($adjustmentsQuery);

// Fetch salary advances for the month
$advancesQuery = "SELECT sad.*
                 FROM salary_advances sad
                 WHERE sad.user_id = ? AND MONTH(sad.advance_date) = ? AND YEAR(sad.advance_date) = ? AND tenant_id = ? AND branch_id = ?";
$advanceStmt = $pdo->prepare($advancesQuery);

// Get employee adjustments and advances
foreach ($employees as &$employee) {
    // Get bonuses
    $bonusStmt->execute([$employee['user_id'], $month, $year, $tenant_id, $branch_id]);
    $employee['bonuses'] = $bonusStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get deductions
    $deductionStmt->execute([$employee['user_id'], $month, $year, $tenant_id, $branch_id]);
    $employee['deductions'] = $deductionStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get legacy adjustments
    $adjustmentStmt->execute([$employee['user_id'], $month, $year, $tenant_id, $branch_id]);
    $adjustments = $adjustmentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get advances
    $advanceStmt->execute([$employee['user_id'], $month, $year, $tenant_id, $branch_id]);
    $employee['advances'] = $advanceStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total earnings (for display in summary only)
    $employee['total_earnings'] = calculateTotalEarnings(
        $employee['base_salary'], 
        $employee['bonuses'],
        $employee['deductions'],
        $employee['advances']
    );

    // Calculate total bonuses
    $totalBonuses = 0;
    foreach ($employee['bonuses'] as $bonus) {
        $totalBonuses += $bonus['amount'];
    }

    // Calculate total deductions
    $totalDeductions = 0;
    foreach ($employee['deductions'] as $deduction) {
        $totalDeductions += $deduction['amount'];
    }
    foreach ($employee['advances'] as $advance) {
        $totalDeductions += $advance['amount'];
    }

    // Check payment status from existing salary_payments table
    $paymentQuery = "SELECT SUM(amount) as total_paid
                    FROM salary_payments
                    WHERE user_id = ?
                    AND DATE_FORMAT(payment_for_month, '%Y-%m') = ? AND tenant_id = ? AND branch_id = ?";
    $paymentStmt = $pdo->prepare($paymentQuery);
    // Format month and year to match payment_for_month format (YYYY-MM)
    $paymentForMonth = sprintf('%04d-%02d', $year, $month);
    $paymentStmt->execute([$employee['user_id'], $paymentForMonth, $tenant_id, $branch_id]);
    $paymentStatus = $paymentStmt->fetch(PDO::FETCH_ASSOC);
    
    // Compare total paid amount with (base salary + bonuses - deductions)
    $totalPaid = $paymentStatus['total_paid'] ?? 0;
    $requiredAmount = ($employee['base_salary'] + $totalBonuses) - $totalDeductions;
    $employee['payment_status'] = ($totalPaid >= $requiredAmount) ? 'paid' : 'pending';
    $employee['amount_paid'] = $totalPaid;
    $employee['amount_remaining'] = max(0, $requiredAmount - $totalPaid);
    $employee['required_amount'] = $requiredAmount;
    $employee['total_bonuses'] = $totalBonuses;

    // Get payment details for display
    $paymentDetailsQuery = "SELECT payment_date, payment_type, description, amount, receipt, main_account_id
                          FROM salary_payments
                          WHERE user_id = ?
                          AND DATE_FORMAT(payment_for_month, '%Y-%m') = ?
                          AND tenant_id = ? AND branch_id = ?
                          ORDER BY payment_date DESC";
    $paymentDetailsStmt = $pdo->prepare($paymentDetailsQuery);
    $paymentDetailsStmt->execute([$employee['user_id'], $paymentForMonth, $tenant_id, $branch_id]);
    $employee['payment_details'] = $paymentDetailsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get attendance summary if feature is enabled
    $employee['attendance_summary'] = null;
    if (hasFeature('attendance', $allowed_features)) {
        $attendanceQuery = "SELECT
                            COUNT(*) as total_days,
                            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
                            SUM(CASE WHEN status = 'half_day' THEN 1 ELSE 0 END) as half_day_days,
                            SUM(working_minutes) as total_working_minutes
                           FROM attendance
                           WHERE user_id = ? AND tenant_id = ? AND branch_id = ?
                           AND MONTH(date) = ? AND YEAR(date) = ?";
        $attendanceStmt = $pdo->prepare($attendanceQuery);
        $attendanceStmt->execute([$employee['user_id'], $tenant_id, $branch_id, $month, $year]);
        $employee['attendance_summary'] = $attendanceStmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo $title; ?> - <?php echo htmlspecialchars($settings['agency_name']); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>

/* ══════════════════════════════════════════════════════════
   DESIGN TOKENS
══════════════════════════════════════════════════════════ */
:root {
    --blue-50:    #e6f2ff;
    --blue-100:   #cce5ff;
    --blue-200:   #99cbff;
    --blue-400:   #4099ff;
    --blue-600:   #0066cc;
    --blue-700:   #004d99;
    --blue-900:   #001a4d;
    
    --teal-500:   #2ed8b6;
    --teal-600:   #1fbf9e;

    --brand:        var(--blue-400);
    --brand-light:  var(--blue-100);
    --brand-mid:    var(--blue-200);
    --brand-dark:   var(--blue-700);
    --brand-accent: var(--teal-500);

    --success:    #15803d;
    --success-bg: #f0fdf4;
    --success-bd: #bbf7d0;
    --danger:     #b91c1c;
    --danger-bg:  #fff1f2;
    --danger-bd:  #fecdd3;
    --warning:    #b45309;

    --text:       #0f172a;
    --text-muted: #64748b;
    --border:     #e2e8f0;
    --border-dk:  #cbd5e1;
    --surface:    #f8fafc;
    --white:      #ffffff;

    --radius-sm: 4px;
    --radius:    7px;
    --radius-lg: 12px;
}

/* ══════════════════════════════════════════════════════════
   RESET & BASE
══════════════════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 10pt;
    line-height: 1.55;
    color: var(--text);
    background: #e2e8f0;
}

/* ══════════════════════════════════════════════════════════
   PAGE WRAPPER
══════════════════════════════════════════════════════════ */
.page-wrapper {
    max-width: 860px;
    margin: 0 auto;
    padding: 28px 18px 40px;
}

/* ══════════════════════════════════════════════════════════
   CONTROLS BAR
══════════════════════════════════════════════════════════ */
.controls {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 14px 20px;
    margin-bottom: 22px;
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    box-shadow: 0 1px 4px rgba(0,0,0,.07);
}

.controls form {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    flex: 1;
}

.controls label {
    font-size: 12.5px;
    font-weight: 600;
    color: var(--text-muted);
}

.controls select {
    font-family: inherit;
    font-size: 13px;
    padding: 6px 11px;
    border: 1px solid var(--border-dk);
    border-radius: var(--radius-sm);
    background: var(--surface);
    color: var(--text);
    cursor: pointer;
    outline: none;
    transition: border-color .15s;
}
.controls select:focus { border-color: var(--brand); }

.btn {
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    padding: 7px 18px;
    border: none;
    border-radius: var(--radius-sm);
    cursor: pointer;
    white-space: nowrap;
    transition: filter .15s, transform .1s;
}
.btn:hover  { filter: brightness(.91); }
.btn:active { transform: scale(.97); }

.btn-apply { background: var(--brand);      color: #fff; }
.btn-print { background: var(--brand-dark); color: #fff; }
.btn-back  { background: var(--surface); color: var(--text); border: 1px solid var(--border-dk); }

/* ══════════════════════════════════════════════════════════
   PAYSLIP CARD
══════════════════════════════════════════════════════════ */
.payslip {
    background: var(--white);
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,.07), 0 0 0 1px var(--border);
    position: relative;
    margin-bottom: 28px;
}

/* ── Header ─────────────────────────────────────────── */
.payslip-header {
    background: linear-gradient(135deg, var(--brand) 0%, var(--brand-accent) 100%);
    color: #fff;
    padding: 24px 30px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    border-bottom: 4px solid var(--brand-accent);
}

.company-name {
    font-size: 18px;
    font-weight: 800;
    letter-spacing: -.4px;
    margin-bottom: 5px;
    line-height: 1.2;
}

.company-meta {
    font-size: 9pt;
    opacity: .68;
    line-height: 1.7;
}

.title-block { text-align: right; flex-shrink: 0; }

.doc-label {
    font-size: 8.5pt;
    font-weight: 600;
    letter-spacing: 2px;
    text-transform: uppercase;
    opacity: .6;
    margin-bottom: 3px;
}

.doc-title {
    font-size: 22px;
    font-weight: 800;
    letter-spacing: -.5px;
    line-height: 1.1;
}

.doc-period {
    font-size: 11px;
    opacity: .6;
    margin-top: 4px;
    font-weight: 500;
}

/* ── PAID watermark ──────────────────────────────────────── */
.paid-stamp {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(-25deg);
    font-family: 'JetBrains Mono', monospace;
    font-size: 58px;
    font-weight: 500;
    color: var(--success);
    border: 6px solid var(--success);
    padding: 2px 18px;
    border-radius: var(--radius-sm);
    opacity: .12;
    pointer-events: none;
    z-index: 10;
    letter-spacing: 6px;
    user-select: none;
}

/* ── Employee band ───────────────────────────────────────── */
.employee-band {
    background: var(--teal-50);
    border-bottom: 1px solid var(--teal-100);
    padding: 16px 30px;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px 20px;
}

.info-cell { display: flex; flex-direction: column; gap: 2px; }

.ic-label {
    font-size: 7.5pt;
    font-weight: 700;
    color: var(--brand);
    text-transform: uppercase;
    letter-spacing: .7px;
}

.ic-value {
    font-size: 9.5pt;
    font-weight: 600;
    color: var(--text);
}

/* Status badge */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 2px 10px;
    border-radius: 99px;
    font-size: 8.5pt;
    font-weight: 700;
    letter-spacing: .3px;
}
.badge::before {
    content: '';
    width: 6px; height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}
.badge-paid    { background: var(--success-bg); color: var(--success); border: 1px solid var(--success-bd); }
.badge-paid::before    { background: var(--success); }
.badge-pending { background: var(--danger-bg);  color: var(--danger);  border: 1px solid var(--danger-bd); }
.badge-pending::before { background: var(--danger); }

/* ══════════════════════════════════════════════════════════
   BODY
══════════════════════════════════════════════════════════ */
.payslip-body { padding: 26px 30px; }

/* ── Summary cards ───────────────────────────────────────── */
.summary-strip {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-bottom: 24px;
}

.scard {
    background: var(--surface);
    border: 1px solid var(--border);
    border-top: 3px solid var(--border);
    border-radius: var(--radius);
    padding: 12px 14px;
}

.scard.sc-base   { border-top-color: var(--brand); }
.scard.sc-bonus  { border-top-color: var(--success); }
.scard.sc-deduct { border-top-color: var(--danger); }
.scard.sc-net    { border-top-color: var(--brand-accent); background: linear-gradient(135deg, rgba(64, 153, 255, 0.08) 0%, rgba(46, 216, 182, 0.08) 100%); border: 1px solid rgba(46, 216, 182, 0.3); }

.sc-label {
    font-size: 8pt;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 5px;
}

.sc-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 14px;
    font-weight: 500;
    color: var(--text);
    line-height: 1.2;
}

.scard.sc-net .sc-value { font-size: 16px; color: var(--brand); font-weight: 700; }

.sc-cur {
    font-size: 9pt;
    color: var(--text-muted);
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-weight: 500;
    margin-left: 2px;
}

/* ── Payment progress ────────────────────────────────────── */
.progress-block {
    margin-bottom: 22px;
    padding: 13px 16px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
}

.progress-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 9px;
    font-size: 9.5pt;
}

.pm-title { font-weight: 700; }
.pm-info  { display: flex; gap: 16px; }
.pm-info span { color: var(--text-muted); }
.pm-info strong { font-weight: 700; }

.progress-track {
    height: 7px;
    background: var(--border);
    border-radius: 99px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--teal-500), var(--teal-700));
    border-radius: 99px;
}

/* ── Section title — left accent bar ─────────────────────── */
.section-title {
    display: flex;
    align-items: center;
    gap: 9px;
    font-size: 10pt;
    font-weight: 800;
    color: var(--brand-dark);
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border);
    letter-spacing: -.2px;
}

.section-title::before {
    content: '';
    display: block;
    width: 4px;
    height: 18px;
    background: var(--brand);
    border-radius: 99px;
    flex-shrink: 0;
}

.section-title.s-danger         { color: var(--danger); }
.section-title.s-danger::before { background: var(--danger); }

/* ── Two-column grid ─────────────────────────────────────── */
.two-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 22px;
    margin-bottom: 24px;
}

/* ── Data tables ─────────────────────────────────────────── */
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 9.5pt;
}

.data-table thead th {
    font-size: 7.5pt;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .6px;
    color: var(--text-muted);
    background: var(--surface);
    padding: 7px 10px;
    border: 1px solid var(--border);
    text-align: left;
    white-space: nowrap;
}

.data-table tbody td {
    padding: 7px 10px;
    border: 1px solid var(--border);
    vertical-align: middle;
    line-height: 1.4;
}

.data-table tbody tr:hover td { background: var(--surface); }

.data-table .total-row td {
    background: var(--brand-light) !important;
    font-weight: 700;
    color: var(--brand);
    border-color: var(--brand-mid);
}

.data-table .bonus-row td  { background: var(--success-bg); }
.data-table .deduct-row td { background: var(--danger-bg); }

.amount-col {
    text-align: right;
    font-family: 'JetBrains Mono', monospace;
    font-size: 9pt;
    white-space: nowrap;
}

.text-success { color: var(--success); font-weight: 600; }
.text-danger  { color: var(--danger);  font-weight: 600; }
.text-muted   { color: var(--text-muted); }

/* ── Attendance grid ─────────────────────────────────────── */
.section-gap { margin-bottom: 24px; }

.attendance-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 8px;
}

.att-card {
    border: 1px solid var(--border);
    border-top: 3px solid var(--border);
    border-radius: var(--radius);
    padding: 10px 8px;
    text-align: center;
    background: var(--surface);
}

.att-num {
    font-family: 'JetBrains Mono', monospace;
    font-size: 18px;
    font-weight: 500;
    line-height: 1.1;
    margin-bottom: 3px;
}

.att-lbl {
    font-size: 7.5pt;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: var(--text-muted);
}

.att-card.att-total   { border-top-color: var(--brand); }
.att-card.att-total   .att-num { color: var(--brand); }
.att-card.att-present { border-top-color: var(--success); }
.att-card.att-present .att-num { color: var(--success); }
.att-card.att-absent  { border-top-color: var(--danger); }
.att-card.att-absent  .att-num { color: var(--danger); }
.att-card.att-late    { border-top-color: var(--warning); }
.att-card.att-late    .att-num { color: var(--warning); }
.att-card.att-half    { border-top-color: #ea580c; }
.att-card.att-half    .att-num { color: #ea580c; }

/* ── History section ─────────────────────────────────────── */
.history-section { margin-bottom: 24px; }

/* ── Net payable bar ─────────────────────────────────────── */
.net-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: linear-gradient(135deg, var(--brand) 0%, var(--brand-accent) 100%);
    color: #fff;
    border-radius: var(--radius);
    padding: 16px 22px;
    margin-bottom: 24px;
}

.nb-label  { font-size: 11pt; font-weight: 700; opacity: .82; }
.nb-amount { font-family: 'JetBrains Mono', monospace; font-size: 22px; font-weight: 500; }
.nb-cur    { font-size: 11pt; opacity: .65; margin-left: 5px; }

/* ── Signatures ──────────────────────────────────────────── */
.signature-section {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 20px;
    align-items: end;
    padding-top: 22px;
    border-top: 1px dashed var(--border-dk);
}

.sig-box { display: flex; flex-direction: column; }

.sig-line {
    height: 42px;
    border-bottom: 1.5px solid var(--border-dk);
    margin-bottom: 7px;
}

.sig-label {
    font-size: 8pt;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .6px;
    color: var(--text-muted);
    text-align: center;
}

.sig-date-box { text-align: center; }

.date-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10pt;
    color: var(--text-muted);
    border-bottom: 1.5px solid var(--border-dk);
    padding: 4px 20px 8px;
    margin-bottom: 7px;
    display: inline-block;
}

/* ══════════════════════════════════════════════════════════
   EMPTY STATE
══════════════════════════════════════════════════════════ */
.no-data {
    text-align: center;
    padding: 60px 24px;
    color: var(--text-muted);
    font-size: 14px;
}

/* ══════════════════════════════════════════════════════════
   PRINT
══════════════════════════════════════════════════════════ */
@media print {

    @page {
        size: A4 portrait;
        margin: 0;
    }

    body {
        background: #fff;
        font-size: 8.5pt;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .no-print { display: none !important; }

    .page-wrapper { padding: 0; max-width: 100%; }

    /* One slip = one page */
    .payslip {
        box-shadow: none;
        border-radius: 0;
        border: none;
        margin-bottom: 0;
        page-break-after: always;
        break-after: page;
    }
    .payslip:last-of-type {
        page-break-after: auto;
        break-after: auto;
    }

    /* Never orphan these blocks */
    .summary-strip,
    .two-col,
    .attendance-grid,
    .net-bar,
    .signature-section,
    .history-section {
        break-inside: avoid;
        page-break-inside: avoid;
    }

    /* Force colour printing */
    .payslip-header,
    .employee-band,
    .scard.sc-net,
    .net-bar,
    .data-table thead th,
    .data-table .total-row td,
    .data-table .bonus-row td,
    .data-table .deduct-row td,
    .att-card {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* Tighten spacing */
    .payslip-header  { padding: 14px 22px; }
    .employee-band   { padding: 10px 22px; grid-template-columns: repeat(3, 1fr); }
    .payslip-body    { padding: 14px 22px; }
    .summary-strip   { margin-bottom: 14px; }
    .scard           { padding: 9px 11px; }
    .sc-value        { font-size: 12px; }
    .scard.sc-net .sc-value { font-size: 14px; }
    .two-col         { gap: 16px; margin-bottom: 14px; }
    .section-gap     { margin-bottom: 14px; }
    .attendance-grid { grid-template-columns: repeat(3, 1fr); }
    .net-bar         { padding: 12px 18px; margin-bottom: 16px; }
    .nb-amount       { font-size: 18px; }
    .history-section { margin-bottom: 16px; }

    /* Hide progress bar on print (saves ink) */
    .progress-block { display: none; }

    .controls { display: none; }
}

    </style>
</head>
<body>
<div class="page-wrapper">

<!-- Controls -->
<div class="controls no-print">
    <form method="get" action="">
        <?php if ($user_id): ?>
            <input type="hidden" name="user_id" value="<?php echo (int)$user_id; ?>">
        <?php endif; ?>

        <label for="month">Month</label>
        <select name="month" id="month">
            <?php for ($i = 1; $i <= 12; $i++): ?>
                <option value="<?php echo $i; ?>" <?php echo $i == $month ? 'selected' : ''; ?>>
                    <?php echo date('F', mktime(0,0,0,$i,1)); ?>
                </option>
            <?php endfor; ?>
        </select>

        <label for="year">Year</label>
        <select name="year" id="year">
            <?php for ($i = date('Y') - 5; $i <= date('Y') + 1; $i++): ?>
                <option value="<?php echo $i; ?>" <?php echo $i == $year ? 'selected' : ''; ?>>
                    <?php echo $i; ?>
                </option>
            <?php endfor; ?>
        </select>

        <button type="submit" class="btn btn-apply">Apply</button>
    </form>

    <button onclick="window.print();" class="btn btn-print">🖨&nbsp; Print Payslip</button>
    <button onclick="window.history.back();" class="btn btn-back">← Back</button>
</div>


<!-- Employee loop -->
<?php foreach ($employees as $index => $employee):

    $totalBonuses    = array_sum(array_column($employee['bonuses'],    'amount'));
    $totalDeductions = array_sum(array_column($employee['deductions'], 'amount'))
                     + array_sum(array_column($employee['advances'],   'amount'));
    $netSalary       = $employee['base_salary'] + $totalBonuses - $totalDeductions;
    $requiredAmt     = $employee['required_amount'] ?? $netSalary;
    $paidAmt         = $employee['amount_paid'] ?? 0;
    $remainingAmt    = max(0, $requiredAmt - $paidAmt);
    $paidPct         = $requiredAmt > 0 ? min(100, round(($paidAmt / $requiredAmt) * 100)) : 0;
    $statusKey       = strtolower($employee['payment_status']);
    $cur             = htmlspecialchars($employee['currency']);
?>

<div class="payslip">

    <?php if ($statusKey === 'paid'): ?>
        <div class="paid-stamp">PAID</div>
    <?php endif; ?>

    <!-- Header -->
    <div class="payslip-header">
        <div>
            <div class="company-name"><?php echo htmlspecialchars($settings['agency_name']); ?></div>
            <div class="company-meta">
                <?php if (!empty($branch['name'])): ?>Branch: <?php echo htmlspecialchars($branch['name']); ?><br><?php endif; ?>
                <?php if (!empty($settings['address'])): echo htmlspecialchars($settings['address']) . '<br>'; endif; ?>
                <?php if (!empty($settings['phone'])): ?>Tel: <?php echo htmlspecialchars($settings['phone']); ?><?php endif; ?>
                <?php if (!empty($settings['email'])): ?>&nbsp;·&nbsp;<?php echo htmlspecialchars($settings['email']); ?><?php endif; ?>
            </div>
        </div>
        <div class="title-block">
            <div class="doc-label">Document</div>
            <div class="doc-title"><?php echo htmlspecialchars($title); ?></div>
            <div class="doc-period"><?php echo htmlspecialchars($subtitle); ?></div>
        </div>
    </div>

    <!-- Employee band -->
    <div class="employee-band">
        <div class="info-cell">
            <span class="ic-label">Employee</span>
            <span class="ic-value"><?php echo htmlspecialchars($employee['employee_name']); ?></span>
        </div>
        <div class="info-cell">
            <span class="ic-label">Employee ID</span>
            <span class="ic-value"><?php echo htmlspecialchars($employee['user_id']); ?></span>
        </div>
        <div class="info-cell">
            <span class="ic-label">Joining Date</span>
            <span class="ic-value"><?php echo htmlspecialchars($employee['hire_date']); ?></span>
        </div>
        <div class="info-cell">
            <span class="ic-label">Payment Date</span>
            <span class="ic-value"><?php echo date('M', mktime(0,0,0,$month,1)) . ' ' . $employee['payment_day'] . ', ' . $year; ?></span>
        </div>
        <div class="info-cell">
            <span class="ic-label">Email</span>
            <span class="ic-value"><?php echo htmlspecialchars($employee['email']); ?></span>
        </div>
        <div class="info-cell">
            <span class="ic-label">Phone</span>
            <span class="ic-value"><?php echo htmlspecialchars($employee['phone']); ?></span>
        </div>
        <div class="info-cell" style="grid-column:span 2">
            <span class="ic-label">Payment Status</span>
            <span class="ic-value">
                <span class="badge badge-<?php echo $statusKey; ?>">
                    <?php echo strtoupper($statusKey); ?>
                </span>
            </span>
        </div>
    </div>

    <!-- Body -->
    <div class="payslip-body">

        <!-- Summary cards -->
        <div class="summary-strip">
            <div class="scard sc-base">
                <div class="sc-label">Base Salary</div>
                <div class="sc-value"><?php echo number_format($employee['base_salary'], 2); ?><span class="sc-cur"><?php echo $cur; ?></span></div>
            </div>
            <div class="scard sc-bonus">
                <div class="sc-label">Bonuses</div>
                <div class="sc-value" style="color:var(--success)"><?php echo number_format($totalBonuses, 2); ?><span class="sc-cur"><?php echo $cur; ?></span></div>
            </div>
            <div class="scard sc-deduct">
                <div class="sc-label">Deductions</div>
                <div class="sc-value" style="color:var(--danger)"><?php echo number_format($totalDeductions, 2); ?><span class="sc-cur"><?php echo $cur; ?></span></div>
            </div>
            <div class="scard sc-net">
                <div class="sc-label">Net Salary</div>
                <div class="sc-value"><?php echo number_format($netSalary, 2); ?><span class="sc-cur"><?php echo $cur; ?></span></div>
            </div>
        </div>

        <!-- Payment progress (hidden on print) -->
        <?php if ($requiredAmt > 0): ?>
        <div class="progress-block no-print">
            <div class="progress-meta">
                <span class="pm-title">Payment Progress</span>
                <span class="pm-info">
                    <span>Paid: <strong class="text-success"><?php echo number_format($paidAmt, 2) . ' ' . $cur; ?></strong></span>
                    <?php if ($remainingAmt > 0): ?>
                        <span>Remaining: <strong class="text-danger"><?php echo number_format($remainingAmt, 2) . ' ' . $cur; ?></strong></span>
                    <?php else: ?>
                        <span><strong class="text-success">&#10003; Fully Paid</strong></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="progress-track">
                <div class="progress-fill" style="width:<?php echo $paidPct; ?>%"></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Earnings + Deductions -->
        <div class="two-col">

            <div>
                <div class="section-title">Earnings</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="amount-col"><?php echo $cur; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Base Salary</td>
                            <td class="amount-col"><?php echo number_format($employee['base_salary'], 2); ?></td>
                        </tr>
                        <?php foreach ($employee['bonuses'] as $bonus): ?>
                        <tr class="bonus-row">
                            <td><?php echo htmlspecialchars($bonus['description'] ?: ucfirst($bonus['type'])); ?></td>
                            <td class="amount-col text-success">+ <?php echo number_format($bonus['amount'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if ($totalBonuses > 0): ?>
                        <tr class="total-row">
                            <td>Gross Earnings</td>
                            <td class="amount-col"><?php echo number_format($employee['base_salary'] + $totalBonuses, 2); ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div>
                <div class="section-title s-danger">Deductions</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="amount-col"><?php echo $cur; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employee['deductions'] as $deduction): ?>
                        <tr class="deduct-row">
                            <td><?php echo htmlspecialchars($deduction['description'] ?: ucfirst($deduction['type'])); ?></td>
                            <td class="amount-col text-danger">- <?php echo number_format($deduction['amount'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php foreach ($employee['advances'] as $advance): ?>
                        <tr class="deduct-row">
                            <td>Salary Advance <span class="text-muted" style="font-size:8.5pt">(<?php echo date('M j, Y', strtotime($advance['advance_date'])); ?>)</span></td>
                            <td class="amount-col text-danger">- <?php echo number_format($advance['amount'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if ($totalDeductions > 0): ?>
                        <tr class="total-row">
                            <td>Total Deductions</td>
                            <td class="amount-col">- <?php echo number_format($totalDeductions, 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (empty($employee['deductions']) && empty($employee['advances'])): ?>
                        <tr>
                            <td colspan="2" style="text-align:center;color:var(--text-muted);font-style:italic;padding:14px 10px">
                                No deductions this period
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div><!-- /two-col -->

        <!-- Attendance -->
        <?php if (!empty($employee['attendance_summary']) && $employee['attendance_summary']['total_days'] > 0):
            $att = $employee['attendance_summary']; ?>
        <div class="section-gap">
            <div class="section-title">Attendance &mdash; <?php echo htmlspecialchars($monthName . ' ' . $year); ?></div>
            <div class="attendance-grid">
                <div class="att-card att-total">
                    <div class="att-num"><?php echo $att['total_days']; ?></div>
                    <div class="att-lbl">Working</div>
                </div>
                <div class="att-card att-present">
                    <div class="att-num"><?php echo $att['present_days']; ?></div>
                    <div class="att-lbl">Present</div>
                </div>
                <div class="att-card att-absent">
                    <div class="att-num"><?php echo $att['absent_days']; ?></div>
                    <div class="att-lbl">Absent</div>
                </div>
                <div class="att-card att-late">
                    <div class="att-num"><?php echo $att['late_days']; ?></div>
                    <div class="att-lbl">Late</div>
                </div>
                <div class="att-card att-half">
                    <div class="att-num"><?php echo $att['half_day_days']; ?></div>
                    <div class="att-lbl">Half Day</div>
                </div>
                <div class="att-card">
                    <div class="att-num" style="font-size:14px"><?php echo number_format($att['total_working_minutes']); ?></div>
                    <div class="att-lbl">Minutes</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Payment history -->
        <?php if (!empty($employee['payment_details']) || !empty($employee['bonuses']) || !empty($employee['deductions'])): ?>
        <div class="history-section">
            <div class="section-title">Payment History</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Account / Channel</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Reference</th>
                        <th class="amount-col">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employee['payment_details'] as $payment):
                        // TODO: pre-load accounts above loop to avoid N+1 queries
                        $acctStmt = $pdo->prepare("SELECT name FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ? LIMIT 1");
                        $acctStmt->execute([$payment['main_account_id'], $tenant_id, $branch_id]);
                        $acct = $acctStmt->fetch(PDO::FETCH_ASSOC);
                    ?>
                    <tr>
                        <td><?php echo date('Y-m-d', strtotime($payment['payment_date'])); ?></td>
                        <td><?php echo htmlspecialchars($acct['name'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($payment['payment_type'])); ?></td>
                        <td><?php echo htmlspecialchars($payment['description']); ?></td>
                        <td style="font-family:'JetBrains Mono',monospace;font-size:8pt"><?php echo htmlspecialchars($payment['receipt'] ?: '—'); ?></td>
                        <td class="amount-col text-success">+ <?php echo number_format($payment['amount'], 2) . ' ' . $cur; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php foreach ($employee['bonuses'] as $bonus): ?>
                    <tr class="bonus-row">
                        <td><?php echo date('Y-m-d', strtotime($bonus['bonus_date'])); ?></td>
                        <td>Bonus</td>
                        <td><?php echo htmlspecialchars(ucfirst($bonus['type'])); ?></td>
                        <td><?php echo htmlspecialchars($bonus['description']); ?></td>
                        <td>—</td>
                        <td class="amount-col text-success">+ <?php echo number_format($bonus['amount'], 2) . ' ' . $cur; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php foreach ($employee['deductions'] as $deduction): ?>
                    <tr class="deduct-row">
                        <td><?php echo date('Y-m-d', strtotime($deduction['deduction_date'])); ?></td>
                        <td>Deduction</td>
                        <td><?php echo htmlspecialchars(ucfirst($deduction['type'])); ?></td>
                        <td><?php echo htmlspecialchars($deduction['description']); ?></td>
                        <td>—</td>
                        <td class="amount-col text-danger">- <?php echo number_format($deduction['amount'], 2) . ' ' . $cur; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Net payable -->
        <div class="net-bar">
            <span class="nb-label">Net Payable Amount</span>
            <span>
                <span class="nb-amount"><?php echo number_format($netSalary, 2); ?></span>
                <span class="nb-cur"><?php echo $cur; ?></span>
            </span>
        </div>

        <!-- Signatures -->
        <div class="signature-section">
            <div class="sig-box">
                <div class="sig-line"></div>
                <div class="sig-label">Employee Signature</div>
            </div>
            <div class="sig-date-box">
                <div class="date-value"><?php echo date('F j, Y'); ?></div>
                <div class="sig-label">Date Issued</div>
            </div>
            <div class="sig-box" style="text-align:right">
                <div class="sig-line"></div>
                <div class="sig-label">Authorized Signature</div>
            </div>
        </div>

    </div><!-- /payslip-body -->
</div><!-- /payslip -->

<?php endforeach; ?>

<?php if (empty($employees)): ?>
<div class="payslip no-data">
    <p>No payroll data found for the selected period.</p>
</div>
<?php endif; ?>

</div><!-- /page-wrapper -->
</body>
</html>
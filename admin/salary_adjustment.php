<?php
// Initialize the session
session_start();

// Check if user is logged in with proper role
$allowed_roles = ['admin', 'finance'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    // Log unauthorized access attempt
    error_log("Unauthorized access attempt to dashboard: " . ($_SESSION['user_id'] ?? 'unknown') . " - Role: " . ($_SESSION['role'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Include config file
require_once "../includes/db.php";

// Define variables and initialize with empty values
$adjustment_type = $amount = $percentage = $effective_date = $reason = "";
$amount_err = $percentage_err = $effective_date_err = $reason_err = "";
$error_message = "";

// Check if user_id is passed in the URL
if (isset($_GET["adjustment_user_id"]) && !empty(trim($_GET["adjustment_user_id"]))) {
    $adjustment_user_id = trim($_GET["adjustment_user_id"]);
    
    // Get user information
    $sql = "SELECT u.name, sm.base_salary, sm.currency
            FROM users u
            JOIN salary_management sm ON u.id = sm.user_id
            WHERE u.id = ? AND u.tenant_id = ? AND u.branch_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(1, $adjustment_user_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $employee_name = $result["name"];
            $current_salary = $result["base_salary"];
            $currency = $result["currency"];
        } else {
            // URL doesn't contain valid id parameter
            header("location: salary_management.php");
            exit();
        }
    } else {
        $error_message = "Oops! Something went wrong. Please try again later.";
    }
} else {
    // URL doesn't contain id parameter
    header("location: salary_management.php");
    exit();
}

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate adjustment type
    $adjustment_type = $_POST["adjustment_type"];
    
    // Validate amount
    if (empty($_POST["amount"]) && empty($_POST["percentage"])) {
        $amount_err = "Please enter either an amount or percentage.";
        $percentage_err = "Please enter either an amount or percentage.";
    } else {
        if (!empty($_POST["amount"])) {
            $amount = $_POST["amount"];
        }
        
        if (!empty($_POST["percentage"])) {
            $percentage = $_POST["percentage"];
        }
    }
    
    // Validate effective date
    if (empty($_POST["effective_date"])) {
        $effective_date_err = "Please enter the effective date.";
    } else {
        $effective_date = $_POST["effective_date"];
    }
    
    // Validate reason
    if (empty($_POST["reason"])) {
        $reason_err = "Please enter the reason for adjustment.";
    } else {
        $reason = $_POST["reason"];
    }
    
    // Calculate new salary
    if (!empty($amount)) {
        if ($adjustment_type == "increment") {
            $new_salary = $current_salary + $amount;
        } else {
            $new_salary = $current_salary - $amount;
        }
    } else if (!empty($percentage)) {
        $adjustment_value = $current_salary * ($percentage / 100);
        if ($adjustment_type == "increment") {
            $new_salary = $current_salary + $adjustment_value;
        } else {
            $new_salary = $current_salary - $adjustment_value;
        }
    }
    
    // Ensure new salary is not negative
    if (isset($new_salary) && $new_salary < 0) {
        $amount_err = "The adjustment would result in a negative salary.";
        $percentage_err = "The adjustment would result in a negative salary.";
    }
    
    // Check input errors before inserting in database
    if (empty($amount_err) && empty($percentage_err) && empty($effective_date_err) && empty($reason_err)) {
        // Start transaction
        $pdo->beginTransaction();

        try {
            // First, insert into salary_adjustments table
            $sql = "INSERT INTO salary_adjustments (user_id, adjustment_type, amount, percentage, effective_date,
                   previous_salary, new_salary, reason, approved_by, tenant_id, branch_id)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql);
            // Get approved_by (current user ID)
            $approved_by = $_SESSION["user_id"];

            // Bind variables to the statement
            $amount_bind = !empty($amount) ? $amount : null;
            $percentage_bind = !empty($percentage) ? $percentage : null;
            
            $stmt->bindParam(1, $adjustment_user_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $adjustment_type, PDO::PARAM_STR);
            $stmt->bindParam(3, $amount_bind, PDO::PARAM_STR);
            $stmt->bindParam(4, $percentage_bind, PDO::PARAM_STR);
            $stmt->bindParam(5, $effective_date, PDO::PARAM_STR);
            $stmt->bindParam(6, $current_salary, PDO::PARAM_STR);
            $stmt->bindParam(7, $new_salary, PDO::PARAM_STR);
            $stmt->bindParam(8, $reason, PDO::PARAM_STR);
            $stmt->bindParam(9, $approved_by, PDO::PARAM_INT);
            $stmt->bindParam(10, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(11, $branch_id, PDO::PARAM_INT);

            // Execute the statement
            $stmt->execute();

            // Update the base salary in salary_management table
            $update_sql = "UPDATE salary_management SET base_salary = ? WHERE user_id = ? AND tenant_id = ? AND branch_id = ?";

            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->bindParam(1, $new_salary, PDO::PARAM_STR);
            $update_stmt->bindParam(2, $adjustment_user_id, PDO::PARAM_INT);
            $update_stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
            $update_stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
            $update_stmt->execute();

            // Commit transaction
            $pdo->commit();

            // Redirect to success page
            header("location: salary_adjustment.php?adjustment_user_id=$adjustment_user_id&success=1");
            exit();
        } catch (Exception $e) {
            // Roll back transaction on error
            $pdo->rollBack();
            $error_message = "Error: " . $e->getMessage();
        }
    }

    // PDO connection will be closed automatically when script ends
}

// Fetch adjustment history
try {
    $sql = "SELECT sa.*, u.name as approved_by_name
            FROM salary_adjustments sa
            JOIN users u ON sa.approved_by = u.id
            WHERE sa.user_id = ? AND sa.tenant_id = ? AND sa.branch_id = ?
            ORDER BY sa.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(1, $adjustment_user_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $adjustments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $adjustments = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Salary Adjustment</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap & Feather Icons -->
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">

<style>
:root {
    --ink:       #0f1117;
    --surface:   #ffffff;
    --muted:     #f4f5f7;
    --border:    #e8eaed;
    --accent:    #3d6cff;
    --accent2:   #00d9a6;
    --warn:      #ff9f43;
    --danger:    #ff4757;
    --text-sub:  #6b7280;
    --radius:    12px;
    --shadow-sm: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
    --shadow-md: 0 4px 16px rgba(0,0,0,.08);
    --shadow-lg: 0 12px 40px rgba(0,0,0,.12);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'DM Sans', sans-serif;
    background: #f0f2f5;
    color: var(--ink);
}

/* ── Page wrapper ───────────────────────────────── */
.sm-page {
    padding: 28px 32px;
    max-width: 1400px;
}

/* ── Page header ────────────────────────────────── */
.page-hero {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 28px;
    flex-wrap: wrap;
    gap: 16px;
}

.page-hero-title {
    font-family: 'Syne', sans-serif;
    font-size: 26px;
    font-weight: 800;
    color: var(--ink);
    letter-spacing: -.5px;
    line-height: 1.1;
}

.page-hero-subtitle {
    font-size: 13px;
    color: var(--text-sub);
    margin-top: 4px;
    font-weight: 400;
}

.hero-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* ── Employee Info ──────────────────────────────── */
.info-box {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 18px 20px;
    margin-bottom: 28px;
    box-shadow: var(--shadow-sm);
}

.info-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 12px;
}

.info-row:last-child {
    margin-bottom: 0;
}

.info-item {
    display: flex;
    flex-direction: column;
}

.info-label {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--text-sub);
    margin-bottom: 6px;
}

.info-value {
    font-size: 15px;
    font-weight: 600;
    color: var(--ink);
}

/* ── Form Card ────────────────────────────────── */
.form-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    margin-bottom: 28px;
}

.form-card-header {
    padding: 20px;
    border-bottom: 1px solid var(--border);
    background: linear-gradient(90deg, var(--muted) 0%, var(--muted) 100%);
}

.form-card-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: var(--ink);
}

.form-card-body {
    padding: 28px;
}

/* ── Form elements ───────────────────────────────── */
.field-group {
    margin-bottom: 20px;
}

.field-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--ink);
    margin-bottom: 8px;
}

.field-control, .field-textarea {
    display: block;
    width: 100%;
    padding: 11px 14px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-family: inherit;
    font-size: 13px;
    color: var(--ink);
    background: var(--surface);
    transition: border-color .2s, box-shadow .2s;
}

.field-control:focus, .field-textarea:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(61, 108, 255, .1);
}

.field-control.is-invalid {
    border-color: var(--danger);
    background: rgba(255, 71, 87, .05);
}

.field-error {
    font-size: 12px;
    color: var(--danger);
    margin-top: 6px;
    display: block;
}

.field-hint {
    font-size: 12px;
    color: var(--text-sub);
    margin-top: 4px;
    display: block;
}

/* ── Salary Preview ────────────────────────────── */
.preview-box {
    background: rgba(61, 108, 255, .05);
    border: 1px solid rgba(61, 108, 255, .2);
    border-radius: 6px;
    padding: 18px 16px;
    margin-bottom: 20px;
}

.preview-label {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--text-sub);
    margin-bottom: 8px;
}

.preview-value {
    font-family: 'Syne', sans-serif;
    font-size: 24px;
    font-weight: 700;
    color: var(--accent);
}

/* ── Buttons ─────────────────────────────────────── */
.btn-primary, .btn-secondary {
    border: none;
    border-radius: 6px;
    padding: 11px 20px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-primary {
    background: var(--accent);
    color: white;
}

.btn-primary:hover {
    background: #2654e3;
    box-shadow: 0 4px 14px rgba(61, 108, 255, .3);
}

.btn-secondary {
    background: var(--muted);
    color: var(--ink);
    border: 1px solid var(--border);
}

.btn-secondary:hover {
    background: #e8eaed;
}

/* ── Data table ──────────────────────────────────── */
.data-table {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}

.table-wrap {
    overflow-x: auto;
}

.table-wrap table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.table-wrap thead {
    background: var(--muted);
    border-bottom: 1px solid var(--border);
}

.table-wrap th {
    padding: 14px 16px;
    text-align: left;
    font-weight: 600;
    color: var(--ink);
    text-transform: none;
    letter-spacing: 0;
}

.table-wrap tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background .15s;
}

.table-wrap tbody tr:hover {
    background: var(--muted);
}

.table-wrap td {
    padding: 14px 16px;
    color: var(--ink);
}

.table-wrap td.muted {
    color: var(--text-sub);
}

.table-empty {
    text-align: center;
    padding: 48px 20px;
    color: var(--text-sub);
}

.table-empty-icon {
    font-size: 32px;
    margin-bottom: 8px;
}

.table-empty-title {
    font-weight: 600;
    margin-bottom: 4px;
}

.table-empty-text {
    font-size: 13px;
}

.table-footer {
    padding: 16px 20px;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    color: var(--text-sub);
    flex-wrap: wrap;
    gap: 8px;
}

/* ── Alert ───────────────────────────────────────– */
.alert {
    padding: 14px 16px;
    border-radius: 6px;
    border-left: 4px solid;
    margin-bottom: 20px;
    font-size: 13px;
}

.alert-success {
    background: rgba(0, 217, 166, .1);
    border-left-color: var(--accent2);
    color: #118b67;
}

.alert-danger {
    background: rgba(255, 71, 87, .1);
    border-left-color: var(--danger);
    color: #c41e3a;
}

/* ── Toast ────────────────────────────────────────– */
#toastWrap {
    display: none;
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1001;
}

.toast-msg {
    background: var(--accent2);
    color: white;
    padding: 14px 18px;
    border-radius: 6px;
    box-shadow: var(--shadow-lg);
    font-size: 13px;
    font-weight: 500;
}

.toast-msg.error {
    background: var(--danger);
}

/* ── Responsive ───────────────────────────────────– */
@media (max-width: 768px) {
    .sm-page {
        padding: 16px 16px;
    }

    .page-hero {
        flex-direction: column;
        align-items: flex-start;
    }

    .hero-actions {
        width: 100%;
    }

    .info-row {
        grid-template-columns: 1fr;
    }

    .form-card-body {
        padding: 20px;
    }

    .table-wrap {
        font-size: 12px;
    }

    .table-wrap th, .table-wrap td {
        padding: 10px 12px;
    }
}
</style>
</head>
<body>

<!-- [ Header ] start -->
<?php include("../includes/header.php"); ?>
<!-- [ Header ] end -->

<!-- Main Content -->
<div class="pcoded-main-container">
    <div class="pcoded-content">
        <div class="sm-page">

            <!-- Page header -->
            <div class="page-hero">
                <div>
                    <div class="page-hero-title"><?= __('salary_adjustment') ?></div>
                    <div class="page-hero-subtitle">Adjust employee salary record</div>
                </div>
                <div class="hero-actions">
                    <a href="salary_management.php" class="btn-secondary">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                        <?= __('back') ?>
                    </a>
                </div>
            </div>

            <!-- Employee Info Box -->
            <div class="info-box">
                <div class="info-row">
                    <div class="info-item">
                        <span class="info-label"><?= __('employee') ?></span>
                        <span class="info-value"><?= $employee_name ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><?= __('current_salary') ?></span>
                        <span class="info-value"><?= number_format($current_salary, 2) . " " . $currency ?></span>
                    </div>
                </div>
            </div>

            <!-- Adjustment Form Card -->
            <div class="form-card">
                <div class="form-card-header">
                    <h3><?= __('process_salary_adjustment') ?></h3>
                </div>
                <div class="form-card-body">
                    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                    <div class="alert alert-success">
                        <strong><?= __('success') ?></strong> <?= __('salary_adjustment_processed_successfully') ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <strong><?= __('error') ?></strong> <?= $error_message ?>
                    </div>
                    <?php endif; ?>

                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?adjustment_user_id=" . $adjustment_user_id; ?>" method="post">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px">
                            <div class="field-group">
                                <label class="field-label"><?= __('adjustment_type') ?></label>
                                <select class="field-control" name="adjustment_type">
                                    <option value="increment" <?php echo ($adjustment_type == "increment") ? "selected" : ""; ?>><?= __('increment') ?></option>
                                    <option value="decrement" <?php echo ($adjustment_type == "decrement") ? "selected" : ""; ?>><?= __('decrement') ?></option>
                                </select>
                            </div>
                            <div class="field-group">
                                <label class="field-label"><?= __('effective_date') ?></label>
                                <input type="date" class="field-control <?php echo (!empty($effective_date_err)) ? 'is-invalid' : ''; ?>" name="effective_date" value="<?php echo $effective_date; ?>">
                                <?php if (!empty($effective_date_err)): ?>
                                <span class="field-error"><?= $effective_date_err ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="field-group">
                                <label class="field-label"><?= __('amount') ?> (<?= __('fixed') ?>)</label>
                                <input type="number" class="field-control <?php echo (!empty($amount_err)) ? 'is-invalid' : ''; ?>" name="amount" step="0.01" value="<?php echo $amount; ?>">
                                <?php if (!empty($amount_err)): ?>
                                <span class="field-error"><?= $amount_err ?></span>
                                <?php else: ?>
                                <span class="field-hint"><?= __('enter_either_amount_or_percentage') ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="field-group">
                                <label class="field-label"><?= __('percentage') ?></label>
                                <input type="number" class="field-control <?php echo (!empty($percentage_err)) ? 'is-invalid' : ''; ?>" name="percentage" step="0.01" value="<?php echo $percentage; ?>">
                                <?php if (!empty($percentage_err)): ?>
                                <span class="field-error"><?= $percentage_err ?></span>
                                <?php else: ?>
                                <span class="field-hint"><?= __('enter_either_amount_or_percentage') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="field-group">
                            <label class="field-label"><?= __('reason_for_adjustment') ?></label>
                            <textarea class="field-textarea <?php echo (!empty($reason_err)) ? 'is-invalid' : ''; ?>" name="reason" rows="3"><?php echo $reason; ?></textarea>
                            <?php if (!empty($reason_err)): ?>
                            <span class="field-error"><?= $reason_err ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="preview-box">
                            <div class="preview-label"><?= __('new_salary_preview') ?></div>
                            <div class="preview-value" id="salary-preview"><?php echo number_format($current_salary, 2) . " " . $currency; ?></div>
                        </div>
                        <div style="display:flex; gap:10px; margin-top:28px">
                            <button type="submit" class="btn-primary">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
                                <?= __('process_adjustment') ?>
                            </button>
                            <a href="salary_management.php" class="btn-secondary"><?= __('cancel') ?></a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Adjustment History Table -->
            <div class="data-table">
                <div style="padding:16px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px">
                    <div style="font-weight:600; color:var(--ink); font-size:14px"><?= __('salary_adjustment_history') ?></div>
                </div>
                <div class="table-wrap">
                    <table id="adjustmentTable">
                        <thead>
                            <tr>
                                <th><?= __('id') ?></th>
                                <th><?= __('type') ?></th>
                                <th><?= __('amount') ?></th>
                                <th><?= __('percentage') ?></th>
                                <th><?= __('previous_salary') ?></th>
                                <th><?= __('new_salary') ?></th>
                                <th><?= __('effective_date') ?></th>
                                <th><?= __('reason') ?></th>
                                <th><?= __('approved_by') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($adjustments)): ?>
                            <tr>
                                <td colspan="9" class="table-empty">
                                    <div class="table-empty-icon">📊</div>
                                    <div class="table-empty-title"><?= __('no_adjustments') ?></div>
                                    <div class="table-empty-text"><?= __('no_salary_adjustments_yet') ?></div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($adjustments as $row): ?>
                                <tr>
                                    <td><?= $row['id'] ?></td>
                                    <td><span style="background:<?php echo ($row['adjustment_type'] == 'increment') ? 'rgba(0,217,166,.1)' : 'rgba(255,71,87,.1)'; ?>; color:<?php echo ($row['adjustment_type'] == 'increment') ? 'var(--accent2)' : 'var(--danger)'; ?>; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:600; text-transform:capitalize"><?= ucfirst($row['adjustment_type']) ?></span></td>
                                    <td class="muted"><?= $row['amount'] ? number_format($row['amount'], 2) : "-" ?></td>
                                    <td class="muted"><?= $row['percentage'] ? $row['percentage'] . "%" : "-" ?></td>
                                    <td><strong><?= number_format($row['previous_salary'], 2) ?></strong></td>
                                    <td><strong><?= number_format($row['new_salary'], 2) ?></strong></td>
                                    <td class="muted"><?= date('Y-m-d', strtotime($row['effective_date'])) ?></td>
                                    <td class="muted"><?= substr($row['reason'], 0, 40) . (strlen($row['reason']) > 40 ? '...' : '') ?></td>
                                    <td class="muted"><?= $row['approved_by_name'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-footer">
                    <span id="tableCount"><?= count($adjustments) ?> record<?= count($adjustments) !== 1 ? 's' : '' ?></span>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Toast wrapper -->
<div id="toastWrap">
    <div class="toast-msg" id="toastMsg">
        <span id="toastText"></span>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>

<script>
$(document).ready(function() {
    // Current salary value
    var currentSalary = <?php echo $current_salary; ?>;
    var currency = "<?php echo $currency; ?>";
    
    // Function to calculate new salary preview
    function calculateNewSalary() {
        var adjustmentType = $('select[name="adjustment_type"]').val();
        var amount = parseFloat($('input[name="amount"]').val()) || 0;
        var percentage = parseFloat($('input[name="percentage"]').val()) || 0;
        var newSalary = currentSalary;
        
        if (amount > 0) {
            // Using fixed amount
            if (adjustmentType === 'increment') {
                newSalary = currentSalary + amount;
            } else {
                newSalary = currentSalary - amount;
            }
        } else if (percentage > 0) {
            // Using percentage
            var adjustmentValue = currentSalary * (percentage / 100);
            if (adjustmentType === 'increment') {
                newSalary = currentSalary + adjustmentValue;
            } else {
                newSalary = currentSalary - adjustmentValue;
            }
        }
        
        // Ensure new salary is not negative
        newSalary = Math.max(0, newSalary);
        
        // Update preview
        $('#salary-preview').text(newSalary.toFixed(2) + " " + currency);
    }
    
    // Handle input changes
    $('select[name="adjustment_type"], input[name="amount"], input[name="percentage"]').on('change keyup', function() {
        // If both amount and percentage are filled, clear the other one
        if ($(this).attr('name') === 'amount' && $(this).val() !== '') {
            $('input[name="percentage"]').val('');
        } else if ($(this).attr('name') === 'percentage' && $(this).val() !== '') {
            $('input[name="amount"]').val('');
        }
        
        calculateNewSalary();
    });
});
</script>

</body>
</html>

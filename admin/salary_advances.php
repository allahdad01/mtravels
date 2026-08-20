<?php
// Initialize the session
session_start();

require_once 'security.php';
enforce_auth();
require_permission('hr.salary');
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Include config file
require_once "../includes/db.php";

$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
function advance_finish_ajax($is_ajax, $success, $message) {
    if ($is_ajax) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => (bool)$success, 'message' => $message]);
        exit;
    }
}

// Define variables and initialize with empty values
$main_account_id = $amount = $currency = $description = "";
$main_account_id_err = $amount_err = $currency_err = $description_err = "";
$error_message = "";

// Generate receipt number
function generateReceiptNumber() {
    return "SA" . date("YmdHis");
}

// Resolve user_id from URL parameter or POST (AJAX)
$advance_user_id = trim($_GET["advance_user_id"] ?? "");
if ($advance_user_id === "" && isset($_POST["user_id"])) {
    $advance_user_id = trim($_POST["user_id"]);
}

// Check if user_id is provided
if ($advance_user_id !== "") {
    
    // Get user information
    $sql = "SELECT u.name, sm.base_salary, sm.currency
            FROM users u
            JOIN salary_management sm ON u.id = sm.user_id
            WHERE u.id = ? AND u.tenant_id = ? AND u.branch_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(1, $advance_user_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $employee_name = $result["name"];
            $current_salary = $result["base_salary"];
            $default_currency = $result["currency"];
        } else {
            // URL doesn't contain valid id parameter
            advance_finish_ajax($is_ajax, false, "Employee not found.");
            header("location: salary_management.php");
            exit();
        }
    } else {
        $error_message = "Oops! Something went wrong. Please try again later.";
    }
} else {
    // No user id provided
    advance_finish_ajax($is_ajax, false, "Please select an employee.");
    header("location: salary_management.php");
    exit();
}

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && $advance_user_id !== "") {
    
    // Validate main account
    if (empty($_POST["main_account_id"])) {
        $main_account_id_err = "Please select a main account.";
    } else {
        $main_account_id = $_POST["main_account_id"];
    }
    
    // Validate amount
    if (empty($_POST["amount"])) {
        $amount_err = "Please enter the advance amount.";
    } else if (!is_numeric($_POST["amount"]) || floatval($_POST["amount"]) <= 0) {
        $amount_err = "Advance amount must be a positive number.";
    } else {
        $amount = floatval($_POST["amount"]);
        
        // Check if amount is reasonable (not more than 3x monthly salary)
        if ($amount > ($current_salary * 3)) {
            $amount_err = "Advance amount exceeds 3 times monthly salary.";
        }
    }
    
    // Set other values
    $currency = $_POST["currency"];
    $description = $_POST["description"];
    $advance_date = date("Y-m-d");
    $receipt = generateReceiptNumber();
    
    // Check input errors before inserting in database
    if (empty($main_account_id_err) && empty($amount_err)) {
        // Start transaction
        $pdo->beginTransaction();

        try {
            // Get current main account balance
            $sql = "SELECT usd_balance, afs_balance FROM main_account WHERE id = ? AND tenant_id = ? AND branch_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(1, $main_account_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $usd_balance = $result['usd_balance'];
                $afs_balance = $result['afs_balance'];
                
                // Calculate new balance based on currency
                $balance = ($currency == "USD") ? $usd_balance : $afs_balance;
                $new_balance = $balance - $amount;
                
                // Check if account has enough balance
                if ($new_balance < 0) {
                    throw new Exception("Account does not have enough balance.");
                }
                
                // Update main account balance
                $update_sql = ($currency == "USD")
                    ? "UPDATE main_account SET usd_balance = usd_balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?"
                    : "UPDATE main_account SET afs_balance = afs_balance - ? WHERE id = ? AND tenant_id = ? AND branch_id = ?";

                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->bindParam(1, $amount, PDO::PARAM_STR);
                $update_stmt->bindParam(2, $main_account_id, PDO::PARAM_INT);
                $update_stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
                $update_stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
                $update_stmt->execute();
                
                // Insert into salary_advances
                $insert_sql = "INSERT INTO salary_advances (user_id, main_account_id, amount, currency, advance_date,
                              description, receipt, tenant_id, branch_id)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $insert_stmt = $pdo->prepare($insert_sql);
                $insert_stmt->bindParam(1, $advance_user_id, PDO::PARAM_INT);
                $insert_stmt->bindParam(2, $main_account_id, PDO::PARAM_INT);
                $insert_stmt->bindParam(3, $amount, PDO::PARAM_STR);
                $insert_stmt->bindParam(4, $currency, PDO::PARAM_STR);
                $insert_stmt->bindParam(5, $advance_date, PDO::PARAM_STR);
                $insert_stmt->bindParam(6, $description, PDO::PARAM_STR);
                $insert_stmt->bindParam(7, $receipt, PDO::PARAM_STR);
                $insert_stmt->bindParam(8, $tenant_id, PDO::PARAM_INT);
                $insert_stmt->bindParam(9, $branch_id, PDO::PARAM_INT);
                $insert_stmt->execute();

                // Get the inserted advance ID
                $advance_id = $pdo->lastInsertId();

                // Also insert into salary_payments as an advance payment
                $payment_sql = "INSERT INTO salary_payments (user_id, main_account_id, amount, currency, payment_date,
                              payment_for_month, payment_type, description, receipt, tenant_id, branch_id)
                              VALUES (?, ?, ?, ?, ?, ?, 'advance', ?, ?, ?, ?)";

                $payment_stmt = $pdo->prepare($payment_sql);
                $payment_for_month = date("Y-m-01"); // Current month
                $payment_stmt->bindParam(1, $advance_user_id, PDO::PARAM_INT);
                $payment_stmt->bindParam(2, $main_account_id, PDO::PARAM_INT);
                $payment_stmt->bindParam(3, $amount, PDO::PARAM_STR);
                $payment_stmt->bindParam(4, $currency, PDO::PARAM_STR);
                $payment_stmt->bindParam(5, $advance_date, PDO::PARAM_STR);
                $payment_stmt->bindParam(6, $payment_for_month, PDO::PARAM_STR);
                $payment_stmt->bindParam(7, $description, PDO::PARAM_STR);
                $payment_stmt->bindParam(8, $receipt, PDO::PARAM_STR);
                $payment_stmt->bindParam(9, $tenant_id, PDO::PARAM_INT);
                $payment_stmt->bindParam(10, $branch_id, PDO::PARAM_INT);
                $payment_stmt->execute();

                // Get the inserted payment ID
                $payment_id = $pdo->lastInsertId();
                
                // Insert into main_account_transactions
                $transaction_sql = "INSERT INTO main_account_transactions (main_account_id, type, amount, balance, currency,
                                   description, transaction_of, reference_id, receipt, tenant_id, branch_id, created_by)
                                   VALUES (?, 'debit', ?, ?, ?, ?, 'salary_payment', ?, ?, ?, ?, ?)";

                $transaction_stmt = $pdo->prepare($transaction_sql);
                $transaction_stmt->bindParam(1, $main_account_id, PDO::PARAM_INT);
                $transaction_stmt->bindParam(2, $amount, PDO::PARAM_STR);
                $transaction_stmt->bindParam(3, $new_balance, PDO::PARAM_STR);
                $transaction_stmt->bindParam(4, $currency, PDO::PARAM_STR);
                $transaction_stmt->bindParam(5, $description, PDO::PARAM_STR);
                $transaction_stmt->bindParam(6, $payment_id, PDO::PARAM_INT);
                $transaction_stmt->bindParam(7, $receipt, PDO::PARAM_STR);
                $transaction_stmt->bindParam(8, $tenant_id, PDO::PARAM_INT);
                $transaction_stmt->bindParam(9, $branch_id, PDO::PARAM_INT);
                $transaction_stmt->bindValue(10, $_SESSION['user_id'] ?? null, PDO::PARAM_INT);
                $transaction_stmt->execute();

                // Commit transaction
                $pdo->commit();
                
                // Send email notification to employee
                require_once '../includes/functions.php';

                // Get employee email
                $email_sql = "SELECT email FROM users WHERE id = ? AND tenant_id = ? AND branch_id = ?";
                $email_stmt = $pdo->prepare($email_sql);
                $email_stmt->bindParam(1, $advance_user_id, PDO::PARAM_INT);
                $email_stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                $email_stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                $email_stmt->execute();
                $email_result = $email_stmt->fetch(PDO::FETCH_ASSOC);

                if ($email_result) {
                    $employee_email = $email_result['email'];
                    
                    if (!empty($employee_email)) {
                        sendSalaryAdvanceNotification(
                            $employee_email,
                            $employee_name,
                            $advance_id,
                            $amount,
                            $currency,
                            $advance_date,
                            $description,
                            $receipt
                        );
                    }
                }

                // Redirect back to the same employee's page with success message
                advance_finish_ajax($is_ajax, true, 'Salary advance recorded successfully.');
                header("location: salary_advances.php?advance_user_id=" . $advance_user_id . "&success=1");
                exit();
            } else {
                throw new Exception("Main account not found.");
            }
        } catch (Exception $e) {
            // Roll back transaction on error
            $pdo->rollBack();
            $error_message = "Error: " . $e->getMessage();
        }
    }

    if ($is_ajax && (isset($error_message) || isset($main_account_id_err) || isset($amount_err))) {
        $first_err = '';
        foreach ([$main_account_id_err, $amount_err, $error_message] as $e) {
            if (!empty($e)) { $first_err = $e; break; }
        }
        advance_finish_ajax($is_ajax, false, $first_err ?: 'Please fix the highlighted fields.');
    }

    // PDO connection will be closed automatically when script ends
}

// Fetch advances for this user
try {
    $sql = "SELECT * FROM salary_advances WHERE user_id = ? AND tenant_id = ? AND branch_id = ? ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(1, $advance_user_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $advances = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $advances = [];
}

// Get main accounts
try {
    $sql = "SELECT id, name, usd_balance, afs_balance FROM main_account WHERE tenant_id = ? AND branch_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
    $stmt->execute();
    $main_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $main_accounts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Salary Advances</title>

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

.btn-info {
    background: var(--accent);
    color: white;
    border: none;
    border-radius: 4px;
    padding: 6px 10px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.btn-info:hover {
    background: #2654e3;
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
                    <div class="page-hero-title"><?= __('salary_advance') ?></div>
                    <div class="page-hero-subtitle">For: <strong><?= $employee_name ?></strong></div>
                </div>
                <div class="hero-actions">
                    <a href="salary_management.php" class="btn-secondary">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                        <?= __('back') ?>
                    </a>
                </div>
            </div>

            <!-- Advance Form Card -->
            <div class="form-card">
                <div class="form-card-header">
                    <h3><?= __('process_salary_advance') ?></h3>
                </div>
                <div class="form-card-body">
                    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                    <div class="alert alert-success">
                        <strong><?= __('success') ?></strong> <?= __('salary_advance_processed_successfully') ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <strong><?= __('error') ?></strong> <?= $error_message ?>
                    </div>
                    <?php endif; ?>

                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?advance_user_id=" . $advance_user_id; ?>" method="post">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px">
                            <div class="field-group">
                                <label class="field-label"><?= __('select_account') ?></label>
                                <select class="field-control <?php echo (!empty($main_account_id_err)) ? 'is-invalid' : ''; ?>" name="main_account_id" required>
                                    <option value=""><?= __('select_account') ?></option>
                                    <?php foreach ($main_accounts as $account): ?>
                                    <option value="<?= $account['id'] ?>" data-usd="<?= $account['usd_balance'] ?>" data-afs="<?= $account['afs_balance'] ?>" <?php echo ($main_account_id == $account['id']) ? 'selected' : ''; ?>>
                                        <?= $account['name'] ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (!empty($main_account_id_err)): ?>
                                <span class="field-error"><?= $main_account_id_err ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="field-group">
                                <label class="field-label"><?= __('advance_amount') ?></label>
                                <input type="number" class="field-control <?php echo (!empty($amount_err)) ? 'is-invalid' : ''; ?>" name="amount" step="0.01" value="<?php echo $amount; ?>" required>
                                <?php if (!empty($amount_err)): ?>
                                <span class="field-error"><?= $amount_err ?></span>
                                <?php else: ?>
                                <span class="field-hint"><?= __('max') ?>: <span id="max-advance"><?php echo number_format($current_salary * 3, 2); ?></span></span>
                                <?php endif; ?>
                            </div>
                            <div class="field-group">
                                <label class="field-label"><?= __('currency') ?></label>
                                <select class="field-control" name="currency">
                                    <option value="USD" <?php echo ($default_currency == "USD") ? "selected" : ""; ?>>USD</option>
                                    <option value="AFS" <?php echo ($default_currency == "AFS") ? "selected" : ""; ?>>AFS</option>
                                </select>
                            </div>
                        </div>
                        <div class="field-group">
                            <label class="field-label"><?= __('description') ?></label>
                            <textarea class="field-textarea" name="description" rows="3"><?php echo $description; ?></textarea>
                        </div>
                        <div style="display:flex; gap:10px; margin-top:28px">
                            <button type="submit" class="btn-primary">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 11l3 3L22 4"/><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <?= __('process_advance') ?>
                            </button>
                            <a href="salary_management.php" class="btn-secondary"><?= __('cancel') ?></a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Advances History Table -->
            <div class="data-table">
                <div style="padding:16px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px">
                    <div style="font-weight:600; color:var(--ink); font-size:14px"><?= __('salary_advances_history') ?></div>
                </div>
                <div class="table-wrap">
                    <table id="advancesTable">
                        <thead>
                            <tr>
                                <th><?= __('id') ?></th>
                                <th><?= __('amount') ?></th>
                                <th><?= __('currency') ?></th>
                                <th><?= __('advance_date') ?></th>
                                <th><?= __('description') ?></th>
                                <th><?= __('receipt') ?></th>
                                <th><?= __('actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($advances)): ?>
                            <tr>
                                <td colspan="7" class="table-empty">
                                    <div class="table-empty-icon">💰</div>
                                    <div class="table-empty-title"><?= __('no_advances') ?></div>
                                    <div class="table-empty-text"><?= __('no_salary_advances_processed_yet') ?></div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($advances as $advance): ?>
                                <tr data-date="<?= date('Y-m', strtotime($advance['advance_date'])) ?>">
                                    <td><?= $advance['id'] ?></td>
                                    <td><strong><?= number_format($advance['amount'], 2) ?></strong></td>
                                    <td><?= $advance['currency'] ?></td>
                                    <td class="muted"><?= date('Y-m-d', strtotime($advance['advance_date'])) ?></td>
                                    <td class="muted"><?= substr($advance['description'], 0, 50) . (strlen($advance['description']) > 50 ? '...' : '') ?></td>
                                    <td class="muted"><code><?= $advance['receipt'] ?></code></td>
                                    <td>
                                        <a href="print_salary_advance_receipt.php?advance_id=<?= $advance['id'] ?>" target="_blank" class="btn-info">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/></svg>
                                            <?= __('print') ?>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-footer">
                    <span id="tableCount"><?= count($advances) ?> record<?= count($advances) !== 1 ? 's' : '' ?></span>
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
// ── Toast ─────────────────────────────────────────
function showToast(msg, isError = false) {
    const wrap = document.getElementById('toastWrap');
    const toastMsg = document.getElementById('toastMsg');
    const text = document.getElementById('toastText');
    text.textContent = msg;
    toastMsg.className = 'toast-msg' + (isError ? ' error' : '');
    wrap.style.display = 'block';
    setTimeout(() => { wrap.style.display = 'none'; }, 3500);
}

$(document).ready(function() {
    // Current salary value
    var currentSalary = <?php echo $current_salary; ?>;
    
    // Validate advance amount
    $('#amount').on('input', function() {
        var amount = parseFloat($(this).val()) || 0;
        var maxAdvance = currentSalary * 3;
        
        if (amount > maxAdvance) {
            $(this).addClass('is-invalid');
            $(this).parent().find('.field-error').text('<?= __('advance_amount_exceeds_3_times_monthly_salary') ?>');
        } else {
            $(this).removeClass('is-invalid');
        }
    });
    
    // Validate if account has enough balance
    $('#main_account_id, #amount, select[name="currency"]').change(function() {
        var selectedAccount = $('#main_account_id').find('option:selected');
        var amount = parseFloat($('#amount').val()) || 0;
        var currency = $('select[name="currency"]').val();
        
        if (selectedAccount.val() && amount > 0) {
            var accountBalance = (currency == 'USD') ? 
                parseFloat(selectedAccount.data('usd')) : 
                parseFloat(selectedAccount.data('afs'));
            
            if (amount > accountBalance) {
                alert('<?= __('warning_the_selected_account_does_not_have_enough_balance_for_this_advance') ?>');
            }
        }
    });
});
</script>

</body>
</html>

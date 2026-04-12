<?php
// Initialize the session
session_start();

$allowed_roles = ['admin', 'finance'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../login.php');
    exit();
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

require_once "../includes/db.php";

// Define variables and initialize with empty values
$user_id = $amount = $description = $deduction_date = $type = "";
$user_id_err = $amount_err = $description_err = $deduction_date_err = "";
$success_message = $error_message = "";

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate user ID
    if (empty($_POST["user_id"])) {
        $user_id_err = "Please select an employee.";
    } else {
        $user_id = $_POST["user_id"];
    }
    
    // Validate amount
    if (empty($_POST["amount"])) {
        $amount_err = "Please enter the deduction amount.";
    } else {
        $amount = $_POST["amount"];
        // Check if amount is a positive number
        if (!is_numeric($amount) || $amount <= 0) {
            $amount_err = "Please enter a positive number for the deduction amount.";
        }
    }
    
    // Validate description
    if (empty($_POST["description"])) {
        $description_err = "Please enter a description.";
    } else {
        $description = $_POST["description"];
    }
    
    // Validate deduction date
    if (empty($_POST["deduction_date"])) {
        $deduction_date_err = "Please enter the deduction date.";
    } else {
        $deduction_date = $_POST["deduction_date"];
    }
    
    // Set deduction type
    $type = $_POST["type"] ?? "absence";
    
    // Check input errors before inserting in database
    if (empty($user_id_err) && empty($amount_err) && empty($description_err) && empty($deduction_date_err)) {
        // Prepare an insert statement
        $sql = "INSERT INTO salary_deductions (tenant_id, branch_id, user_id, amount, description, deduction_date, type, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        // Get current user ID as created_by
        $created_by = $_SESSION["user_id"];

        // Bind variables to the prepared statement as parameters
        $stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
        $stmt->bindParam(3, $user_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $amount, PDO::PARAM_STR);
        $stmt->bindParam(5, $description, PDO::PARAM_STR);
        $stmt->bindParam(6, $deduction_date, PDO::PARAM_STR);
        $stmt->bindParam(7, $type, PDO::PARAM_STR);
        $stmt->bindParam(8, $created_by, PDO::PARAM_INT);

        // Attempt to execute the prepared statement
        if ($stmt->execute()) {
            // Records created successfully. Redirect to landing page
            header("location: manage_deductions.php?success=1");
            exit();
        } else {
            $error_message = "Oops! Something went wrong. Please try again later.";
        }
    }
}

// Fetch deduction records
try {
    $stmt = $pdo->prepare("SELECT sd.*, u.name as employee_name, a.name as added_by_name FROM salary_deductions sd JOIN users u ON sd.user_id=u.id JOIN users a ON sd.created_by=a.id WHERE sd.tenant_id=? AND sd.branch_id=? ORDER BY sd.deduction_date DESC");
    $stmt->execute([$tenant_id, $branch_id]);
    $deductions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $deductions = [];
}

// Fetch users with salary records
try {
    $stmt = $pdo->prepare("SELECT u.id, u.name FROM users u JOIN salary_management sm ON u.id=sm.user_id WHERE sm.status='active' AND u.tenant_id=? AND u.branch_id=? ORDER BY u.name ASC");
    $stmt->execute([$tenant_id, $branch_id]);
    $users_with_salary = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users_with_salary = [];
}

// Stats
$total_deductions = count($deductions);
$total_deduction_amount = array_sum(array_map(fn($r) => floatval($r['amount']), $deductions));
$active_employees = count($users_with_salary);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Deductions</title>

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

/* ── Stat cards ─────────────────────────────────── */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 28px;
}

.stat-card {
    background: var(--surface);
    border-radius: var(--radius);
    padding: 20px 22px;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border);
    position: relative;
    overflow: hidden;
    transition: box-shadow .2s;
}

.stat-card:hover { box-shadow: var(--shadow-md); }

.stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
}

.stat-card.blue::before  { background: var(--accent); }
.stat-card.green::before { background: var(--accent2); }
.stat-card.red::before   { background: var(--danger); }
.stat-card.yellow::before{ background: var(--warn); }

.stat-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .8px;
    color: var(--text-sub);
    margin-bottom: 8px;
}

.stat-value {
    font-family: 'Syne', sans-serif;
    font-size: 28px;
    font-weight: 700;
    color: var(--ink);
    line-height: 1;
}

.stat-icon {
    position: absolute;
    right: 18px;
    top: 50%;
    transform: translateY(-50%);
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.stat-card.blue .stat-icon { background: rgba(61, 108, 255, .1); color: var(--accent); }
.stat-card.green .stat-icon { background: rgba(0, 217, 166, .1); color: var(--accent2); }
.stat-card.red .stat-icon { background: rgba(255, 71, 87, .1); color: var(--danger); }
.stat-card.yellow .stat-icon { background: rgba(255, 159, 67, .1); color: var(--warn); }

/* ── Add Panel ───────────────────────────────────── */
.add-panel-wrapper {
    max-height: 0;
    overflow: hidden;
    transition: max-height .3s ease-out;
    margin-bottom: 0;
}

.add-panel-wrapper.open {
    max-height: 800px;
    margin-bottom: 28px;
}

.add-panel {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}

.add-panel-header {
    padding: 16px 20px;
    background: linear-gradient(90deg, var(--muted) 0%, var(--muted) 100%);
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.add-panel-header h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    color: var(--ink);
}

.add-panel-body {
    padding: 24px;
}

/* ── Form elements ───────────────────────────────── */
.field-group {
    margin-bottom: 18px;
}

.field-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--ink);
    margin-bottom: 6px;
}

.field-control, .field-textarea {
    display: block;
    width: 100%;
    padding: 10px 12px;
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
    margin-top: 4px;
}

/* ── Buttons ─────────────────────────────────────── */
.btn-primary, .btn-sm-primary {
    background: var(--accent);
    color: white;
    border: none;
    border-radius: 6px;
    padding: 10px 18px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s, box-shadow .2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-primary:hover, .btn-sm-primary:hover {
    background: #2654e3;
    box-shadow: 0 4px 14px rgba(61, 108, 255, .3);
}

.btn-secondary, .btn-sm-ghost {
    background: var(--muted);
    color: var(--ink);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 10px 18px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s;
}

.btn-secondary:hover, .btn-sm-ghost:hover {
    background: #e8eaed;
}

.btn-success {
    background: var(--accent2);
    color: white;
    border: none;
    border-radius: 6px;
    padding: 8px 14px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s;
}

.btn-success:hover {
    background: #00b89e;
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
}

.btn-info:hover {
    background: #2654e3;
}

.btn-danger {
    background: var(--danger);
    color: white;
    border: none;
    border-radius: 4px;
    padding: 6px 10px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s;
}

.btn-danger:hover {
    background: #ff2d47;
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
    padding: 12px 16px;
    border-radius: 6px;
    border-left: 4px solid;
    margin-bottom: 16px;
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

/* ── Modal ───────────────────────────────────────– */
.sm-modal-backdrop {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.sm-modal-backdrop.open {
    display: flex;
}

.sm-modal {
    background: var(--surface);
    border-radius: var(--radius);
    max-width: 500px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: var(--shadow-lg);
}

.sm-modal-header {
    padding: 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.sm-modal-title {
    font-family: 'Syne', sans-serif;
    font-size: 17px;
    font-weight: 700;
    color: var(--ink);
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    color: var(--text-sub);
    cursor: pointer;
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color .2s;
}

.modal-close:hover {
    color: var(--ink);
}

.sm-modal-body {
    padding: 24px;
}

.sm-modal-footer {
    padding: 16px 20px;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 10px;
    justify-content: flex-end;
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

    .stat-grid {
        grid-template-columns: 1fr;
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
                    <div class="page-hero-title"><?= __('manage_employee_deductions') ?></div>
                    <div class="page-hero-subtitle">Manage and track employee deduction records</div>
                </div>
                <div class="hero-actions">
                    <button type="button" class="btn-primary" onclick="toggleAddPanel()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <?= __('add_deduction') ?>
                    </button>
                    <a href="salary_management.php" class="btn-secondary">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                        <?= __('back') ?>
                    </a>
                </div>
            </div>

            <!-- Stats -->
            <div class="stat-grid">
                <div class="stat-card blue">
                    <div class="stat-label"><?= __('total_deductions') ?></div>
                    <div class="stat-value"><?= $total_deductions ?></div>
                    <div class="stat-icon">📊</div>
                </div>
                <div class="stat-card red">
                    <div class="stat-label"><?= __('total_amount') ?></div>
                    <div class="stat-value"><?= number_format($total_deduction_amount, 0) ?></div>
                    <div class="stat-icon">💸</div>
                </div>
                <div class="stat-card yellow">
                    <div class="stat-label"><?= __('active_employees') ?></div>
                    <div class="stat-value"><?= $active_employees ?></div>
                    <div class="stat-icon">👥</div>
                </div>
            </div>

            <!-- Add Deduction Panel -->
            <div class="add-panel-wrapper" id="addPanelWrapper">
                <div class="add-panel">
                    <div class="add-panel-header">
                        <h3><?= __('add_new_deduction') ?></h3>
                    </div>
                    <div class="add-panel-body">
                        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                        <div class="alert alert-success">
                            <strong><?= __('success') ?></strong> <?= __('deduction_has_been_added_successfully') ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger">
                            <strong><?= __('error') ?></strong> <?= $error_message ?>
                        </div>
                        <?php endif; ?>

                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-bottom:18px">
                                <div class="field-group">
                                    <label class="field-label"><?= __('employee') ?></label>
                                    <select class="field-control <?php echo (!empty($user_id_err)) ? 'is-invalid' : ''; ?>" name="user_id">
                                        <option value=""><?= __('select_employee') ?></option>
                                        <?php foreach ($users_with_salary as $emp): ?>
                                        <option value="<?= $emp['id'] ?>" <?php echo ($user_id == $emp['id']) ? 'selected' : ''; ?>><?= $emp['name'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (!empty($user_id_err)): ?>
                                    <div class="field-error"><?= $user_id_err ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="field-group">
                                    <label class="field-label"><?= __('deduction_amount') ?></label>
                                    <input type="number" class="field-control <?php echo (!empty($amount_err)) ? 'is-invalid' : ''; ?>" name="amount" step="0.01" min="0" value="<?php echo $amount; ?>">
                                    <?php if (!empty($amount_err)): ?>
                                    <div class="field-error"><?= $amount_err ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="field-group">
                                    <label class="field-label"><?= __('deduction_type') ?></label>
                                    <select class="field-control" name="type">
                                        <option value="absence"><?= __('absence') ?></option>
                                        <option value="penalty"><?= __('penalty') ?></option>
                                        <option value="tax"><?= __('tax') ?></option>
                                        <option value="other"><?= __('other') ?></option>
                                    </select>
                                </div>
                                <div class="field-group">
                                    <label class="field-label"><?= __('deduction_date') ?></label>
                                    <input type="date" class="field-control <?php echo (!empty($deduction_date_err)) ? 'is-invalid' : ''; ?>" name="deduction_date" value="<?php echo empty($deduction_date) ? date('Y-m-d') : $deduction_date; ?>">
                                    <?php if (!empty($deduction_date_err)): ?>
                                    <div class="field-error"><?= $deduction_date_err ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="field-group">
                                <label class="field-label"><?= __('description') ?></label>
                                <textarea class="field-textarea <?php echo (!empty($description_err)) ? 'is-invalid' : ''; ?>" name="description" rows="3"><?php echo $description; ?></textarea>
                                <?php if (!empty($description_err)): ?>
                                <div class="field-error"><?= $description_err ?></div>
                                <?php endif; ?>
                            </div>
                            <div style="display:flex; gap:10px">
                                <button type="submit" class="btn-primary"><?= __('add_deduction') ?></button>
                                <button type="button" class="btn-secondary" onclick="toggleAddPanel()"><?= __('cancel') ?></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Deduction Records Table -->
            <div class="data-table">
                <div style="padding:16px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px">
                    <div style="font-weight:600; color:var(--ink); font-size:14px"><?= __('deduction_records') ?></div>
                    <a href="manage_bonuses.php" class="btn-success">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <?= __('manage_bonuses') ?>
                    </a>
                </div>
                <div class="table-wrap">
                    <table id="deductionTable">
                        <thead>
                            <tr>
                                <th><?= __('id') ?></th>
                                <th><?= __('employee') ?></th>
                                <th><?= __('amount') ?></th>
                                <th><?= __('type') ?></th>
                                <th><?= __('deduction_date') ?></th>
                                <th><?= __('description') ?></th>
                                <th><?= __('added_by') ?></th>
                                <th><?= __('actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($deductions)): ?>
                            <tr>
                                <td colspan="8" class="table-empty">
                                    <div class="table-empty-icon">💸</div>
                                    <div class="table-empty-title"><?= __('no_deduction_records') ?></div>
                                    <div class="table-empty-text"><?= __('click_add_deduction_to_get_started') ?></div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($deductions as $deduction): ?>
                                <tr data-id="<?= $deduction['id'] ?>">
                                    <td><?= $deduction['id'] ?></td>
                                    <td><?= $deduction['employee_name'] ?></td>
                                    <td><strong><?= number_format($deduction['amount'], 2) ?></strong></td>
                                    <td><span style="background:rgba(255,71,87,.1); color:var(--danger); padding:4px 8px; border-radius:4px; font-size:11px; font-weight:600; text-transform:capitalize"><?= ucfirst($deduction['type']) ?></span></td>
                                    <td class="muted"><?= date('Y-m-d', strtotime($deduction['deduction_date'])) ?></td>
                                    <td class="muted"><?= substr($deduction['description'], 0, 40) . (strlen($deduction['description']) > 40 ? '...' : '') ?></td>
                                    <td class="muted"><?= $deduction['added_by_name'] ?></td>
                                    <td>
                                        <a href="edit_deduction.php?id=<?= $deduction['id'] ?>" class="btn-info" style="text-decoration:none; display:inline-block">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </a>
                                        <a href="delete_deduction.php?id=<?= $deduction['id'] ?>" class="btn-danger" style="text-decoration:none; display:inline-block" onclick="return confirm('<?= __('are_you_sure') ?>')">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-footer">
                    <span id="tableCount"><?= count($deductions) ?> record<?= count($deductions) !== 1 ? 's' : '' ?></span>
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
// ── Add Panel Toggle ──────────────────────────────
function toggleAddPanel() {
    const wrapper = document.getElementById('addPanelWrapper');
    const isOpen  = wrapper.classList.contains('open');
    wrapper.classList.toggle('open');
    if (!isOpen) wrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// Auto-open panel if there were validation errors
<?php if (!empty($user_id_err) || !empty($amount_err) || !empty($description_err) || !empty($deduction_date_err)): ?>
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('addPanelWrapper').classList.add('open');
});
<?php endif; ?>

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

<?php if (isset($_GET['success'])): ?>
document.addEventListener('DOMContentLoaded', () => showToast('<?= __('deduction_has_been_added_successfully') ?>'));
<?php elseif (!empty($error_message)): ?>
document.addEventListener('DOMContentLoaded', () => showToast('<?= addslashes($error_message) ?>', true));
<?php endif; ?>
</script>
</body>
</html>

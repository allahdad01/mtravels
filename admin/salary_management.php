<?php
// Initialize the session
session_start();

require_once 'security.php';
enforce_auth();
require_permission('hr.salary');
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

require_once "../includes/db.php";

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->execute([$_SESSION['user_id'], $tenant_id, $branch_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Error in dashboard.php: " . $e->getMessage());
}

$user_id = $base_salary = $currency = $joining_date = $payment_day = "";
$user_id_err = $base_salary_err = $currency_err = $joining_date_err = $payment_day_err = "";
$success_message = $error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["update_salary"])) {
        if (empty($_POST["base_salary"])) {
            $base_salary_err = "Please enter the base salary.";
        } else {
            $base_salary = $_POST["base_salary"];
        }
        $user_id   = $_POST["user_id"];
        $currency  = $_POST["currency"];
        $payment_day = $_POST["payment_day"];
        $status    = $_POST["status"] ?? 'active';
        $fired     = isset($_POST["fired"]) ? intval($_POST["fired"]) : 0;

        if (empty($base_salary_err)) {
            $sql_salary = "UPDATE salary_management SET base_salary=?, currency=?, payment_day=?, status=?, updated_at=CURRENT_TIMESTAMP WHERE user_id=? AND tenant_id=? AND branch_id=?";
            $sql_user   = "UPDATE users SET fired=?, fired_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=? AND branch_id=?";
            try {
                $pdo->beginTransaction();
                $stmt_salary = $pdo->prepare($sql_salary);
                $stmt_salary->execute([$base_salary, $currency, $payment_day, $status, $user_id, $tenant_id, $branch_id]);
                $stmt_user = $pdo->prepare($sql_user);
                $stmt_user->execute([$fired, $user_id, $tenant_id, $branch_id]);
                $pdo->commit();
                header("location: salary_management.php?updated=1");
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_message = "Something went wrong. Please try again.";
            }
        }
    } else {
        if (empty($_POST["user_id"])) {
            $user_id_err = "Please select an employee.";
        } else {
            $user_id = $_POST["user_id"];
            $chk = $pdo->prepare("SELECT id FROM salary_management WHERE user_id=? AND tenant_id=? AND branch_id=?");
            $chk->execute([$user_id, $tenant_id, $branch_id]);
            if ($chk->rowCount() == 1) $user_id_err = "This employee already has a salary record.";
        }
        if (empty($_POST["base_salary"])) { $base_salary_err = "Please enter the base salary."; }
        else { $base_salary = $_POST["base_salary"]; }
        if (empty($_POST["joining_date"])) { $joining_date_err = "Please enter the joining date."; }
        else { $joining_date = $_POST["joining_date"]; }
        $currency    = $_POST["currency"];
        $payment_day = $_POST["payment_day"];

        if (empty($user_id_err) && empty($base_salary_err) && empty($joining_date_err)) {
            $sql = "INSERT INTO salary_management (user_id,base_salary,currency,joining_date,payment_day,tenant_id,branch_id) VALUES (?,?,?,?,?,?,?)";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$user_id, $base_salary, $currency, $joining_date, $payment_day, $tenant_id, $branch_id])) {
                header("location: salary_management.php?added=1");
                exit();
            } else {
                $error_message = "Something went wrong. Please try again.";
            }
        }
    }
}

// Fetch salary records
try {
    $stmt = $pdo->prepare("SELECT sm.*, u.name as employee_name, u.fired as is_fired FROM salary_management sm JOIN users u ON sm.user_id=u.id WHERE u.tenant_id=? AND u.branch_id=? ORDER BY sm.id DESC");
    $stmt->execute([$tenant_id, $branch_id]);
    $salaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $salaries = [];
}

// Fetch users without salary record
try {
    $stmt = $pdo->prepare("SELECT u.id, u.name FROM users u LEFT JOIN salary_management sm ON u.id=sm.user_id WHERE sm.id IS NULL AND u.tenant_id=? AND u.branch_id=? ORDER BY u.name ASC");
    $stmt->execute([$tenant_id, $branch_id]);
    $users_no_salary = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users_no_salary = [];
}

// Stats
$total_active  = count(array_filter($salaries, fn($r) => $r['status'] === 'active' && !$r['is_fired']));
$total_fired   = count(array_filter($salaries, fn($r) => $r['is_fired']));
$total_payroll = array_sum(array_map(fn($r) => !$r['is_fired'] ? floatval($r['base_salary']) : 0, $salaries));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Salary Management</title>

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
    opacity: .12;
}

/* ── Card shell ─────────────────────────────────── */
.sm-card {
    background: var(--surface);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    margin-bottom: 24px;
}

.sm-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 24px;
    border-bottom: 1px solid var(--border);
    background: var(--surface);
    flex-wrap: wrap;
    gap: 12px;
}

.sm-card-title {
    font-family: 'Syne', sans-serif;
    font-size: 15px;
    font-weight: 700;
    color: var(--ink);
    display: flex;
    align-items: center;
    gap: 8px;
}

.sm-card-title svg {
    color: var(--accent);
    flex-shrink: 0;
}

.sm-card-body { padding: 24px; }

/* ── Buttons ────────────────────────────────────── */
.btn-sm-primary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 9px 16px;
    font-size: 13px;
    font-weight: 500;
    font-family: 'DM Sans', sans-serif;
    cursor: pointer;
    transition: background .18s, transform .12s;
    text-decoration: none;
    white-space: nowrap;
}
.btn-sm-primary:hover { background: #2d5be0; color: #fff; transform: translateY(-1px); }

.btn-sm-ghost {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: transparent;
    color: var(--text-sub);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 9px 16px;
    font-size: 13px;
    font-weight: 500;
    font-family: 'DM Sans', sans-serif;
    cursor: pointer;
    transition: all .18s;
    text-decoration: none;
    white-space: nowrap;
}
.btn-sm-ghost:hover { background: var(--muted); color: var(--ink); border-color: #d0d5dd; }

.btn-sm-success {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(0,217,166,.12);
    color: #00a880;
    border: 1px solid rgba(0,217,166,.25);
    border-radius: 8px;
    padding: 9px 16px;
    font-size: 13px;
    font-weight: 500;
    font-family: 'DM Sans', sans-serif;
    cursor: pointer;
    transition: all .18s;
    text-decoration: none;
    white-space: nowrap;
}
.btn-sm-success:hover { background: rgba(0,217,166,.22); color: #00a880; }

.btn-sm-warn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255,159,67,.12);
    color: #cc7a00;
    border: 1px solid rgba(255,159,67,.25);
    border-radius: 8px;
    padding: 9px 16px;
    font-size: 13px;
    font-weight: 500;
    font-family: 'DM Sans', sans-serif;
    cursor: pointer;
    transition: all .18s;
    text-decoration: none;
    white-space: nowrap;
}
.btn-sm-warn:hover { background: rgba(255,159,67,.22); color: #cc7a00; }

.btn-sm-info {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(61,108,255,.1);
    color: var(--accent);
    border: 1px solid rgba(61,108,255,.2);
    border-radius: 8px;
    padding: 9px 16px;
    font-size: 13px;
    font-weight: 500;
    font-family: 'DM Sans', sans-serif;
    cursor: pointer;
    transition: all .18s;
    text-decoration: none;
    white-space: nowrap;
}
.btn-sm-info:hover { background: rgba(61,108,255,.18); color: var(--accent); }

/* Open Add Panel button — pill */
.btn-add-record {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: linear-gradient(135deg, var(--accent), #5b85ff);
    color: #fff;
    border: none;
    border-radius: 50px;
    padding: 10px 20px;
    font-size: 13px;
    font-weight: 600;
    font-family: 'DM Sans', sans-serif;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(61,108,255,.35);
    transition: box-shadow .2s, transform .15s;
    text-decoration: none;
}
.btn-add-record:hover { color: #fff; box-shadow: 0 6px 20px rgba(61,108,255,.45); transform: translateY(-1px); }

/* ── Slide-down Add Form Panel ──────────────────── */
.add-panel-wrapper {
    overflow: hidden;
    max-height: 0;
    transition: max-height .4s cubic-bezier(.4,0,.2,1), opacity .3s;
    opacity: 0;
}
.add-panel-wrapper.open {
    max-height: 600px;
    opacity: 1;
}

.add-panel {
    background: linear-gradient(135deg, #eef2ff 0%, #f0fdf9 100%);
    border: 1px solid #dbe4ff;
    border-radius: var(--radius);
    padding: 28px;
    margin-bottom: 24px;
}

.add-panel-title {
    font-family: 'Syne', sans-serif;
    font-size: 14px;
    font-weight: 700;
    color: var(--accent);
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 7px;
}

/* ── Form fields ────────────────────────────────── */
.field-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 0;
}

.field-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-sub);
    text-transform: uppercase;
    letter-spacing: .5px;
}

.field-control {
    height: 42px;
    padding: 0 12px;
    font-size: 14px;
    font-family: 'DM Sans', sans-serif;
    color: var(--ink);
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: 8px;
    outline: none;
    transition: border-color .18s, box-shadow .18s;
    width: 100%;
}
.field-control:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(61,108,255,.12);
}
.field-control.is-invalid { border-color: var(--danger); }
.field-error {
    font-size: 12px;
    color: var(--danger);
    margin-top: 2px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.form-grid-wide {
    grid-column: 1 / -1;
}

/* ── Table ──────────────────────────────────────── */
.sm-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 13.5px;
}

.sm-table thead th {
    background: var(--muted);
    color: var(--text-sub);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .7px;
    padding: 11px 16px;
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
}

.sm-table thead th:first-child { border-radius: 8px 0 0 0; }
.sm-table thead th:last-child  { border-radius: 0 8px 0 0; }

.sm-table tbody tr {
    transition: background .15s;
}
.sm-table tbody tr:hover td { background: #f8f9ff; }

.sm-table tbody td {
    padding: 13px 16px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
    color: var(--ink);
}

.sm-table tbody tr.fired-row td {
    background: #fff9f9;
    color: #888;
}
.sm-table tbody tr.fired-row:hover td { background: #fff3f3; }

.employee-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.emp-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--accent), #5b85ff);
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-family: 'Syne', sans-serif;
}

.emp-avatar.fired {
    background: linear-gradient(135deg, #ccc, #aaa);
}

.emp-name {
    font-weight: 500;
    color: var(--ink);
    font-size: 13.5px;
}

/* ── Custom Status Badges ──────────────────────── */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    position: relative;
    transition: all 0.2s ease;
}

.status-badge::before {
    content: '';
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

.status-active {
    background: linear-gradient(135deg, #d4f8f0, #e8fdf9);
    color: #008866;
    border: 1.5px solid #00d9a6;
}

.status-active::before {
    background: #00d9a6;
}

.status-inactive {
    background: linear-gradient(135deg, #fff5e6, #fffaf0);
    color: #cc7a00;
    border: 1.5px solid #ff9f43;
}

.status-inactive::before {
    background: #ff9f43;
}

.status-fired {
    background: linear-gradient(135deg, #ffe8e8, #fff4f4);
    color: #cc2233;
    border: 1.5px solid #ff4757;
}

.status-fired::before {
    background: #ff4757;
}

/* ── Row action menu ────────────────────────────── */
.row-actions {
    display: flex;
    gap: 8px;
    justify-content: center;
    flex-wrap: wrap;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: var(--surface);
    cursor: pointer;
    transition: all .2s;
    text-decoration: none;
    color: var(--text-sub);
    font-size: 12px;
    font-weight: 500;
    white-space: nowrap;
}

.action-btn svg {
    width: 14px;
    height: 14px;
    flex-shrink: 0;
}

.action-btn:hover { 
    color: var(--ink); 
    border-color: #aaa; 
    background: var(--muted); 
}

.action-btn.edit:hover { 
    color: var(--accent); 
    border-color: var(--accent); 
    background: rgba(61,108,255,.08);
}

.action-btn.adj:hover { 
    color: #00a880; 
    border-color: #00a880; 
    background: rgba(0,168,128,.08);
}

.action-btn.adv:hover { 
    color: #cc7a00; 
    border-color: var(--warn); 
    background: rgba(255,159,67,.08);
}

.action-btn.print:hover { 
    color: #555; 
    border-color: #888; 
    background: var(--muted);
}

.action-btn.fire:hover {
    color: var(--danger);
    border-color: var(--danger);
    background: rgba(255,71,87,.08);
}

/* ── Modal ──────────────────────────────────────── */
.sm-modal-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15,17,23,.5);
    backdrop-filter: blur(4px);
    z-index: 1050;
    align-items: center;
    justify-content: center;
}
.sm-modal-backdrop.open { display: flex; }

.sm-modal {
    background: var(--surface);
    border-radius: 16px;
    width: 100%;
    max-width: 520px;
    margin: 16px;
    box-shadow: var(--shadow-lg);
    animation: modalIn .25s cubic-bezier(.34,1.56,.64,1);
}

@keyframes modalIn {
    from { transform: scale(.95) translateY(8px); opacity: 0; }
    to   { transform: scale(1)   translateY(0);   opacity: 1; }
}

.sm-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px 16px;
    border-bottom: 1px solid var(--border);
}

.sm-modal-title {
    font-family: 'Syne', sans-serif;
    font-size: 17px;
    font-weight: 700;
    color: var(--ink);
}

.modal-close {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-sub);
    transition: all .15s;
}
.modal-close:hover { background: var(--muted); color: var(--ink); }

.sm-modal-body { padding: 24px; }
.sm-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 16px 24px;
    border-top: 1px solid var(--border);
    background: var(--muted);
    border-radius: 0 0 16px 16px;
}

/* ── Confirm modal ──────────────────────────────── */
.confirm-icon {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: rgba(255,71,87,.12);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    color: var(--danger);
    font-size: 22px;
}

/* ── Toast notification ─────────────────────────── */
.toast-wrap {
    position: fixed;
    top: 24px;
    right: 24px;
    z-index: 9999;
}

.toast-msg {
    background: var(--surface);
    border-radius: 10px;
    padding: 14px 18px;
    box-shadow: var(--shadow-lg);
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13.5px;
    font-weight: 500;
    border-left: 3px solid var(--accent2);
    animation: slideIn .3s ease;
    min-width: 240px;
}
.toast-msg.error { border-left-color: var(--danger); }

@keyframes slideIn {
    from { transform: translateX(30px); opacity: 0; }
    to   { transform: translateX(0);    opacity: 1; }
}

/* ── Search / Filter bar ────────────────────────── */
.table-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    gap: 12px;
    flex-wrap: wrap;
}

.search-wrap {
    position: relative;
    flex: 1;
    min-width: 220px;
    max-width: 320px;
}

.search-wrap svg {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-sub);
    pointer-events: none;
}

.search-input {
    height: 38px;
    padding: 0 12px 0 34px;
    font-size: 13.5px;
    font-family: 'DM Sans', sans-serif;
    color: var(--ink);
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: 8px;
    outline: none;
    transition: border-color .18s;
    width: 100%;
}
.search-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(61,108,255,.1); }

.filter-select {
    height: 38px;
    padding: 0 10px;
    font-size: 13px;
    font-family: 'DM Sans', sans-serif;
    color: var(--ink);
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: 8px;
    outline: none;
    cursor: pointer;
    transition: border-color .18s;
}
.filter-select:focus { border-color: var(--accent); }

/* DataTables overrides */
.dataTables_wrapper .dataTables_info,
.dataTables_wrapper .dataTables_paginate {
    padding-top: 14px;
    font-size: 13px;
    color: var(--text-sub);
}
.dataTables_wrapper .dataTables_paginate .paginate_button {
    border-radius: 6px !important;
    font-size: 12.5px !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: var(--accent) !important;
    color: #fff !important;
    border-color: var(--accent) !important;
}
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter { display: none; }

/* Salary value */
.salary-val {
    font-family: 'Syne', sans-serif;
    font-weight: 600;
    font-size: 14px;
}

/* ── Responsive ─────────────────────────────────── */
@media (max-width: 768px) {
    .sm-page { padding: 16px; }
    .page-hero { flex-direction: column; align-items: flex-start; }
    .hero-actions { width: 100%; }
    .btn-add-record { width: 100%; justify-content: center; }
    .form-grid { grid-template-columns: 1fr; }
    .stat-grid { grid-template-columns: 1fr 1fr; }
    .row-actions { gap: 2px; }
    .action-btn { width: 26px; height: 26px; }
}
</style>

<?php include '../includes/header.php'; ?>

<div class="pcoded-main-container">
<div class="pcoded-content">

<!-- Page wrapper -->
<div class="sm-page">

    <!-- ── Toast ── -->
    <div class="toast-wrap" id="toastWrap" style="display:none">
        <div class="toast-msg" id="toastMsg">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            <span id="toastText"></span>
        </div>
    </div>

    <!-- ── Page Hero ── -->
    <div class="page-hero">
        <div>
            <div class="page-hero-title">Salary Management</div>
            <div class="page-hero-subtitle">Manage employee compensation, status, and payroll records</div>
        </div>
        <div class="hero-actions">
            <a href="manage_bonuses.php" class="btn-sm-success">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                Bonuses
            </a>
            <a href="manage_deductions.php" class="btn-sm-warn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                Deductions
            </a>
            <a href="print_payroll.php" target="_blank" class="btn-sm-ghost">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Group Payroll
            </a>
            <button class="btn-add-record" onclick="toggleAddPanel()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Record
            </button>
        </div>
    </div>

    <!-- ── Stat Cards ── -->
    <div class="stat-grid">
        <div class="stat-card blue">
            <div class="stat-label">Total Records</div>
            <div class="stat-value"><?= count($salaries) ?></div>
            <div class="stat-icon">👥</div>
        </div>
        <div class="stat-card green">
            <div class="stat-label">Active Employees</div>
            <div class="stat-value"><?= $total_active ?></div>
            <div class="stat-icon">✅</div>
        </div>
        <div class="stat-card red">
            <div class="stat-label">Fired / Inactive</div>
            <div class="stat-value"><?= $total_fired ?></div>
            <div class="stat-icon">🚫</div>
        </div>
        <div class="stat-card yellow">
            <div class="stat-label">Monthly Payroll</div>
            <div class="stat-value" style="font-size:20px">$<?= number_format($total_payroll, 0) ?></div>
            <div class="stat-icon">💰</div>
        </div>
    </div>

    <!-- ── Add Record Slide Panel ── -->
    <div class="add-panel-wrapper" id="addPanelWrapper">
        <div class="add-panel">
            <div class="add-panel-title">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Salary Record
            </div>
            <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]) ?>" method="post" id="addSalaryForm">
                <div class="form-grid">
                    <!-- Employee -->
                    <div class="field-group" style="grid-column: span 2">
                        <label class="field-label">Employee</label>
                        <select class="field-control <?= !empty($user_id_err) ? 'is-invalid' : '' ?>" name="user_id" required>
                            <option value="">Select employee…</option>
                            <?php foreach ($users_no_salary as $row): ?>
                            <option value="<?= $row['id'] ?>" <?= $user_id == $row['id'] ? 'selected' : '' ?>><?= htmlspecialchars($row['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($user_id_err)): ?><div class="field-error"><?= $user_id_err ?></div><?php endif; ?>
                    </div>

                    <!-- Base Salary -->
                    <div class="field-group">
                        <label class="field-label">Base Salary</label>
                        <input type="number" class="field-control <?= !empty($base_salary_err) ? 'is-invalid' : '' ?>" name="base_salary" step="0.01" value="<?= $base_salary ?>" placeholder="0.00" required>
                        <?php if (!empty($base_salary_err)): ?><div class="field-error"><?= $base_salary_err ?></div><?php endif; ?>
                    </div>

                    <!-- Currency -->
                    <div class="field-group">
                        <label class="field-label">Currency</label>
                        <select class="field-control" name="currency">
                            <option value="USD" <?= $currency == 'USD' ? 'selected' : '' ?>>USD — US Dollar</option>
                            <option value="AFS" <?= $currency == 'AFS' ? 'selected' : '' ?>>AFS — Afghan Afghani</option>
                        </select>
                    </div>

                    <!-- Joining Date -->
                    <div class="field-group">
                        <label class="field-label">Joining Date</label>
                        <input type="date" class="field-control <?= !empty($joining_date_err) ? 'is-invalid' : '' ?>" name="joining_date" value="<?= $joining_date ?>" required>
                        <?php if (!empty($joining_date_err)): ?><div class="field-error"><?= $joining_date_err ?></div><?php endif; ?>
                    </div>

                    <!-- Payment Day -->
                    <div class="field-group">
                        <label class="field-label">Payment Day of Month</label>
                        <input type="number" class="field-control" name="payment_day" min="1" max="31" value="<?= empty($payment_day) ? 1 : $payment_day ?>">
                    </div>
                </div>

                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="submit" class="btn-sm-primary">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Save Record
                    </button>
                    <button type="button" class="btn-sm-ghost" onclick="toggleAddPanel()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Main Table Card ── -->
    <div class="sm-card">
        <div class="sm-card-header">
            <div class="sm-card-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Employee Salaries
            </div>
            <div class="table-toolbar" style="margin-bottom:0">
                <div class="search-wrap">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" class="search-input" id="tableSearch" placeholder="Search employees…">
                </div>
                <select class="filter-select" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="fired">Fired</option>
                </select>
            </div>
        </div>
        <div class="sm-card-body" style="padding: 0">
            <div style="overflow-x:auto">
                <table id="salaryTable" class="sm-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Employee</th>
                            <th>Base Salary</th>
                            <th>Currency</th>
                            <th>Joined</th>
                            <th style="text-align:center">Pay Day</th>
                            <th>Status</th>
                            <th style="text-align:center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($salaries as $i => $row):
                            $isFired   = (bool)$row['is_fired'];
                            $isInactive= $row['status'] === 'inactive';
                            $initials  = strtoupper(substr($row['employee_name'], 0, 1));
                            if ($isFired) {
                                $badge = '<span class="status-badge status-fired">Fired</span>';
                                $statusFilter = 'fired';
                            } elseif ($isInactive) {
                                $badge = '<span class="status-badge status-inactive">Inactive</span>';
                                $statusFilter = 'inactive';
                            } else {
                                $badge = '<span class="status-badge status-active">Active</span>';
                                $statusFilter = 'active';
                            }
                        ?>
                        <tr class="<?= $isFired ? 'fired-row' : '' ?>" data-status="<?= $statusFilter ?>" data-name="<?= strtolower($row['employee_name']) ?>">
                            <td style="color:var(--text-sub); font-size:12px; width:40px"><?= $row['id'] ?></td>
                            <td>
                                <div class="employee-cell">
                                    <div class="emp-avatar <?= $isFired ? 'fired' : '' ?>"><?= $initials ?></div>
                                    <div class="emp-name"><?= htmlspecialchars($row['employee_name']) ?></div>
                                </div>
                            </td>
                            <td><span class="salary-val"><?= number_format($row['base_salary'], 2) ?></span></td>
                            <td style="font-size:12.5px; color:var(--text-sub); font-weight:600"><?= $row['currency'] ?></td>
                            <td style="color:var(--text-sub); font-size:13px"><?= date('M d, Y', strtotime($row['joining_date'])) ?></td>
                            <td style="text-align:center; font-weight:600"><?= $row['payment_day'] ?></td>
                            <td><?= $badge ?></td>
                            <td>
                                <div class="row-actions">
                                    <button class="action-btn edit open-edit-modal" title="Edit Salary"
                                        data-user-id="<?= $row['user_id'] ?>"
                                        data-name="<?= htmlspecialchars($row['employee_name']) ?>"
                                        data-base-salary="<?= $row['base_salary'] ?>"
                                        data-currency="<?= $row['currency'] ?>"
                                        data-payment-day="<?= $row['payment_day'] ?>"
                                        data-status="<?= $row['status'] ?>"
                                        data-fired="<?= $row['is_fired'] ?>">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        <span>Edit</span>
                                    </button>
                                    <a href="salary_adjustment.php?adjustment_user_id=<?= $row['user_id'] ?>" class="action-btn adj" title="Adjustments">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                        <span>Adjust</span>
                                    </a>
                                    <a href="salary_advances.php?advance_user_id=<?= $row['user_id'] ?>" class="action-btn adv" title="Salary Advances">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                                        <span>Advance</span>
                                    </a>
                                    <a href="print_payroll.php?user_id=<?= $row['user_id'] ?>" target="_blank" class="action-btn print" title="Print Payroll">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                        <span>Print</span>
                                    </a>
                                    <?php if (!$isFired): ?>
                                    <button class="action-btn fire" title="Mark as Fired"
                                        onclick="confirmFire(<?= $row['user_id'] ?>, '<?= htmlspecialchars($row['employee_name'], ENT_QUOTES) ?>')">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                                        <span>Fire</span>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($salaries)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center; padding:48px; color:var(--text-sub)">
                                <div style="font-size:32px; margin-bottom:8px">📋</div>
                                <div style="font-weight:600; margin-bottom:4px">No salary records yet</div>
                                <div style="font-size:13px">Click "Add Record" to get started</div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div style="padding:16px 20px; border-top:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; font-size:13px; color:var(--text-sub); flex-wrap:wrap; gap:8px">
                <span id="tableCount"><?= count($salaries) ?> records</span>
            </div>
        </div>
    </div>

</div><!-- end sm-page -->
</div>
</div>

<!-- ══════════════════════════════════════
     EDIT SALARY MODAL
══════════════════════════════════════ -->
<div class="sm-modal-backdrop" id="editModalBackdrop">
    <div class="sm-modal">
        <div class="sm-modal-header">
            <div class="sm-modal-title">Edit Salary Record</div>
            <button class="modal-close" onclick="closeEditModal()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]) ?>" method="post" id="editSalaryForm">
            <input type="hidden" name="user_id"       id="edit_user_id">
            <input type="hidden" name="update_salary" value="1">
            <div class="sm-modal-body">
                <div style="margin-bottom:18px">
                    <div class="field-label" style="margin-bottom:4px">Employee</div>
                    <input type="text" class="field-control" id="edit_employee_name" readonly style="background:var(--muted); color:var(--text-sub)">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px">
                    <div class="field-group">
                        <label class="field-label">Base Salary</label>
                        <input type="number" class="field-control" id="edit_base_salary" name="base_salary" step="0.01" required>
                    </div>
                    <div class="field-group">
                        <label class="field-label">Currency</label>
                        <select class="field-control" id="edit_currency" name="currency">
                            <option value="USD">USD</option>
                            <option value="AFS">AFS</option>
                        </select>
                    </div>
                    <div class="field-group">
                        <label class="field-label">Payment Day</label>
                        <input type="number" class="field-control" id="edit_payment_day" name="payment_day" min="1" max="31">
                    </div>
                    <div class="field-group">
                        <label class="field-label">Status</label>
                        <select class="field-control" id="edit_status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="field-group">
                    <label class="field-label">Employment Status</label>
                    <select class="field-control" id="edit_fired" name="fired">
                        <option value="0">Employed</option>
                        <option value="1">Fired</option>
                    </select>
                </div>
            </div>
            <div class="sm-modal-footer">
                <button type="button" class="btn-sm-ghost" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-sm-primary">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
                    Update Record
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════
     FIRE CONFIRM MODAL
══════════════════════════════════════ -->
<div class="sm-modal-backdrop" id="fireModalBackdrop">
    <div class="sm-modal" style="max-width:420px">
        <div class="sm-modal-body" style="padding:32px 28px; text-align:center">
            <div class="confirm-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div style="font-family:'Syne',sans-serif; font-size:17px; font-weight:700; margin-bottom:8px">Mark as Fired?</div>
            <div style="font-size:13.5px; color:var(--text-sub); margin-bottom:24px" id="fireConfirmText">Are you sure you want to mark <strong></strong> as fired?</div>
            <div style="display:flex; gap:10px; justify-content:center">
                <button class="btn-sm-ghost" onclick="closeFireModal()">Cancel</button>
                <button class="btn-sm-primary" style="background:var(--danger); box-shadow:0 4px 14px rgba(255,71,87,.3)" onclick="submitFire()">
                    Yes, Mark as Fired
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden fire form -->
<form id="fireForm" action="<?= htmlspecialchars($_SERVER["PHP_SELF"]) ?>" method="post" style="display:none">
    <input type="hidden" name="user_id"       id="fire_user_id">
    <input type="hidden" name="update_salary" value="1">
    <input type="hidden" name="fired"         value="1">
    <input type="hidden" name="base_salary"   id="fire_base_salary">
    <input type="hidden" name="currency"      id="fire_currency">
    <input type="hidden" name="payment_day"   id="fire_payment_day">
    <input type="hidden" name="status"        value="inactive">
</form>

<?php include '../includes/admin_footer.php'; ?>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
// ── Panel toggle ──────────────────────────────────
function toggleAddPanel() {
    const wrapper = document.getElementById('addPanelWrapper');
    const isOpen  = wrapper.classList.contains('open');
    wrapper.classList.toggle('open');
    if (!isOpen) wrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// Auto-open panel if there were validation errors
<?php if (!empty($user_id_err) || !empty($base_salary_err) || !empty($joining_date_err)): ?>
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('addPanelWrapper').classList.add('open');
});
<?php endif; ?>

// ── Edit Modal ────────────────────────────────────
document.querySelectorAll('.open-edit-modal').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('edit_user_id').value       = btn.dataset.userId;
        document.getElementById('edit_employee_name').value = btn.dataset.name;
        document.getElementById('edit_base_salary').value   = btn.dataset.baseSalary;
        document.getElementById('edit_currency').value      = btn.dataset.currency;
        document.getElementById('edit_payment_day').value   = btn.dataset.paymentDay;
        document.getElementById('edit_status').value        = btn.dataset.status;
        document.getElementById('edit_fired').value         = btn.dataset.fired;
        document.getElementById('editModalBackdrop').classList.add('open');
    });
});

function closeEditModal() {
    document.getElementById('editModalBackdrop').classList.remove('open');
}

// Close modal on backdrop click
document.getElementById('editModalBackdrop').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

// ── Fire Confirm ──────────────────────────────────
let fireUserId = null;

// We need to pull salary info to fill hidden fields
const salaryData = <?php
    $map = [];
    foreach ($salaries as $r) {
        $map[$r['user_id']] = ['base_salary' => $r['base_salary'], 'currency' => $r['currency'], 'payment_day' => $r['payment_day']];
    }
    echo json_encode($map);
?>;

function confirmFire(userId, name) {
    fireUserId = userId;
    document.getElementById('fireConfirmText').innerHTML = `Are you sure you want to mark <strong>${name}</strong> as fired? This action will update their employment status.`;
    document.getElementById('fireModalBackdrop').classList.add('open');
}

function closeFireModal() {
    document.getElementById('fireModalBackdrop').classList.remove('open');
    fireUserId = null;
}

function submitFire() {
    if (!fireUserId) return;
    const data = salaryData[fireUserId] || {};
    document.getElementById('fire_user_id').value     = fireUserId;
    document.getElementById('fire_base_salary').value = data.base_salary || 0;
    document.getElementById('fire_currency').value    = data.currency    || 'USD';
    document.getElementById('fire_payment_day').value = data.payment_day || 1;
    document.getElementById('fireForm').submit();
}

document.getElementById('fireModalBackdrop').addEventListener('click', function(e) {
    if (e.target === this) closeFireModal();
});

// ── Live Search & Filter ──────────────────────────
const searchInput  = document.getElementById('tableSearch');
const statusFilter = document.getElementById('statusFilter');
const rows         = document.querySelectorAll('#salaryTable tbody tr[data-name]');
const tableCount   = document.getElementById('tableCount');

function filterTable() {
    const q      = searchInput.value.toLowerCase().trim();
    const status = statusFilter.value;
    let visible  = 0;

    rows.forEach(row => {
        const nameMatch   = !q      || row.dataset.name.includes(q);
        const statusMatch = !status || row.dataset.status === status;
        const show        = nameMatch && statusMatch;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    tableCount.textContent = `${visible} record${visible !== 1 ? 's' : ''}`;
}

searchInput.addEventListener('input',  filterTable);
statusFilter.addEventListener('change', filterTable);

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

<?php if (isset($_GET['updated'])): ?>
document.addEventListener('DOMContentLoaded', () => showToast('Salary record updated successfully.'));
<?php elseif (isset($_GET['added'])): ?>
document.addEventListener('DOMContentLoaded', () => showToast('New salary record added successfully.'));
<?php elseif (!empty($error_message)): ?>
document.addEventListener('DOMContentLoaded', () => showToast('<?= addslashes($error_message) ?>', true));
<?php endif; ?>
</script>
</body>
</html>
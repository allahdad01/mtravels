<?php
session_start();

require_once 'security.php';
enforce_auth();
require_permission('hr.salary_pay');
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

require_once "../includes/db.php";

$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
function salary_payment_finish_ajax($is_ajax, $success, $message) {
    if ($is_ajax) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => (bool)$success, 'message' => $message]);
        exit;
    }
}

$user_id = $main_account_id = $amount = $currency = $payment_type = $description = $payment_for_month = "";
$user_id_err = $main_account_id_err = $amount_err = $payment_for_month_err = "";

function generateReceiptNumber() {
    return "SP" . date("YmdHis");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty($_POST["user_id"]))          { $user_id_err = "Please select an employee."; }
    else                                   { $user_id = $_POST["user_id"]; }

    if (empty($_POST["main_account_id"])) { $main_account_id_err = "Please select an account."; }
    else                                  { $main_account_id = $_POST["main_account_id"]; }

    if (empty($_POST["amount"]) || !is_numeric($_POST["amount"]) || floatval($_POST["amount"]) <= 0) {
        $amount_err = "Please enter a valid positive amount.";
    } else {
        $amount = floatval($_POST["amount"]);
    }

    if (empty($_POST["payment_for_month"])) { $payment_for_month_err = "Please select the payment month."; }
    else                                    { $payment_for_month = $_POST["payment_for_month"] . "-01"; }

    $currency      = $_POST["currency"];
    $payment_type  = $_POST["payment_type"];
    $description   = $_POST["description"];
    $payment_date  = date("Y-m-d");
    $receipt       = generateReceiptNumber();
    $months_to_pay = max(1, (int)($_POST['months_to_pay'] ?? 1));

    if (empty($user_id_err) && empty($main_account_id_err) && empty($amount_err) && empty($payment_for_month_err)) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT usd_balance, afs_balance FROM main_account WHERE id=? AND tenant_id=? AND branch_id=?");
            $stmt->execute([$main_account_id, $tenant_id, $branch_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $starting_balance = ($currency == "USD") ? $result['usd_balance'] : $result['afs_balance'];
                $total_deduction  = $amount * $months_to_pay;

                for ($i = 0; $i < $months_to_pay; $i++) {
                    $this_month_for = date('Y-m-01', strtotime("+{$i} month", strtotime($payment_for_month)));
                    $this_receipt   = $receipt . '-' . ($i + 1);

                    $ins = $pdo->prepare("INSERT INTO salary_payments (user_id,main_account_id,amount,currency,payment_date,payment_for_month,payment_type,description,receipt,tenant_id,branch_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                    $ins->execute([$user_id,$main_account_id,$amount,$currency,$payment_date,$this_month_for,$payment_type,$description,$this_receipt,$tenant_id,$branch_id]);
                    $payment_id = $pdo->lastInsertId();

                    $running_balance = $starting_balance - ($amount * ($i + 1));
                    $trx = $pdo->prepare("INSERT INTO main_account_transactions (main_account_id,type,amount,balance,currency,description,transaction_of,reference_id,receipt,tenant_id,branch_id,created_by) VALUES (?,'debit',?,?,?,?,'salary_payment',?,?,?,?,?)");
                    $trx->execute([$main_account_id,$amount,$running_balance,$currency,$description,$payment_id,$this_receipt,$tenant_id,$branch_id,$_SESSION['user_id'] ?? null]);

                    if ($payment_type == 'regular') {
                        $adv = $pdo->prepare("SELECT id,amount,amount_paid FROM salary_advances WHERE user_id=? AND currency=? AND repayment_status!='paid' AND tenant_id=? AND branch_id=?");
                        $adv->execute([$user_id,$currency,$tenant_id,$branch_id]);
                        foreach ($adv->fetchAll() as $ar) {
                            $remaining = $ar['amount'] - $ar['amount_paid'];
                            $deduction = min($amount, $remaining);
                            if ($deduction > 0) {
                                $new_paid = $ar['amount_paid'] + $deduction;
                                $status_adv = ($new_paid >= $ar['amount']) ? 'paid' : 'partially_paid';
                                $pdo->prepare("UPDATE salary_advances SET amount_paid=?,repayment_status=? WHERE id=? AND tenant_id=? AND branch_id=?")->execute([$new_paid,$status_adv,$ar['id'],$tenant_id,$branch_id]);
                            }
                        }
                    }
                }

                $upd = ($currency == "USD")
                    ? "UPDATE main_account SET usd_balance=usd_balance-? WHERE id=? AND tenant_id=? AND branch_id=?"
                    : "UPDATE main_account SET afs_balance=afs_balance-? WHERE id=? AND tenant_id=? AND branch_id=?";
                $pdo->prepare($upd)->execute([$total_deduction,$main_account_id,$tenant_id,$branch_id]);
                $pdo->commit();

                require_once '../includes/functions.php';
                $er = $pdo->prepare("SELECT email,name FROM users WHERE id=? AND tenant_id=? AND branch_id=?");
                $er->execute([$user_id,$tenant_id,$branch_id]);
                $emp = $er->fetch(PDO::FETCH_ASSOC);
                if ($emp && !empty($emp['email'])) {
                    sendSalaryPaymentNotification($emp['email'],$emp['name'],$payment_id,$amount,$currency,$payment_date,date('Y-m',strtotime($payment_for_month)),$payment_type,$description,$receipt);
                }

                salary_payment_finish_ajax($is_ajax, true, 'Salary payment recorded successfully.');
                header("location: salary_payment.php?success=1");
                exit();
            } else {
                throw new Exception("Main account not found.");
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

if ($is_ajax && (isset($error_message) || isset($user_id_err) || isset($main_account_id_err) || isset($amount_err) || isset($payment_for_month_err))) {
    $first_err = '';
    foreach ([$user_id_err, $main_account_id_err, $amount_err, $payment_for_month_err, $error_message] as $e) {
        if (!empty($e)) { $first_err = $e; break; }
    }
    salary_payment_finish_ajax($is_ajax, false, $first_err ?: 'Please fix the highlighted fields.');
}

// Fetch active employees with salary records
try {
    $emp_stmt = $pdo->query("SELECT u.id, u.name, sm.base_salary, sm.currency FROM users u JOIN salary_management sm ON u.id=sm.user_id WHERE sm.status='active' AND u.fired=0 AND u.tenant_id={$tenant_id} AND u.branch_id={$branch_id} ORDER BY u.name");
    $employees = $emp_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $employees = []; }

// Fetch accounts
try {
    $acc_stmt = $pdo->prepare("SELECT id,name,usd_balance,afs_balance FROM main_account WHERE status='active' AND tenant_id=? AND branch_id=?");
    $acc_stmt->execute([$tenant_id, $branch_id]);
    $accounts = $acc_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $accounts = []; }

// Fetch payment history (paginated)
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset   = ($page - 1) * $per_page;

try {
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM salary_payments WHERE tenant_id=? AND branch_id=?");
    $cnt->execute([$tenant_id,$branch_id]);
    $total_rows  = $cnt->fetchColumn();
    $total_pages = ceil($total_rows / $per_page);

    $hist = $pdo->prepare("SELECT sp.*,u.name as employee_name,ma.name as account_name FROM salary_payments sp JOIN users u ON sp.user_id=u.id JOIN main_account ma ON sp.main_account_id=ma.id WHERE sp.tenant_id=? AND sp.branch_id=? ORDER BY sp.created_at DESC LIMIT ? OFFSET ?");
    $hist->execute([$tenant_id,$branch_id,$per_page,$offset]);
    $payments = $hist->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $payments = [];
    $total_pages = 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Salary Payment</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">

<style>
/* ── Design tokens (shared with salary_management.php) ── */
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
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:#f0f2f5;color:var(--ink)}

/* ── Page wrapper ── */
.sp-page{padding:28px 32px;max-width:1400px}

/* ── Page hero ── */
.page-hero{display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:28px;flex-wrap:wrap;gap:16px}
.page-hero-title{font-family:'Syne',sans-serif;font-size:26px;font-weight:800;color:var(--ink);letter-spacing:-.5px;line-height:1.1}
.page-hero-subtitle{font-size:13px;color:var(--text-sub);margin-top:4px;font-weight:400}
.hero-actions{display:flex;gap:10px;flex-wrap:wrap}

/* ── Two-column layout ── */
.sp-grid{display:grid;grid-template-columns:420px 1fr;gap:24px;align-items:start}
@media(max-width:1100px){.sp-grid{grid-template-columns:1fr}}

/* ── Card ── */
.sm-card{background:var(--surface);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow-sm);overflow:hidden;margin-bottom:24px}
.sm-card-header{display:flex;justify-content:space-between;align-items:center;padding:18px 24px;border-bottom:1px solid var(--border);background:var(--surface);flex-wrap:wrap;gap:12px}
.sm-card-title{font-family:'Syne',sans-serif;font-size:15px;font-weight:700;color:var(--ink);display:flex;align-items:center;gap:8px}
.sm-card-title svg{color:var(--accent);flex-shrink:0}
.sm-card-body{padding:24px}

/* ── Buttons ── */
.btn-sm-primary{display:inline-flex;align-items:center;gap:6px;background:var(--accent);color:#fff;border:none;border-radius:8px;padding:9px 16px;font-size:13px;font-weight:500;font-family:'DM Sans',sans-serif;cursor:pointer;transition:background .18s,transform .12s;text-decoration:none;white-space:nowrap}
.btn-sm-primary:hover{background:#2d5be0;color:#fff;transform:translateY(-1px)}
.btn-sm-ghost{display:inline-flex;align-items:center;gap:6px;background:transparent;color:var(--text-sub);border:1px solid var(--border);border-radius:8px;padding:9px 16px;font-size:13px;font-weight:500;font-family:'DM Sans',sans-serif;cursor:pointer;transition:all .18s;text-decoration:none;white-space:nowrap}
.btn-sm-ghost:hover{background:var(--muted);color:var(--ink);border-color:#d0d5dd}
.btn-sm-danger{display:inline-flex;align-items:center;gap:6px;background:rgba(255,71,87,.1);color:var(--danger);border:1px solid rgba(255,71,87,.2);border-radius:8px;padding:9px 16px;font-size:13px;font-weight:500;font-family:'DM Sans',sans-serif;cursor:pointer;transition:all .18s;text-decoration:none;white-space:nowrap}
.btn-sm-danger:hover{background:rgba(255,71,87,.18);color:var(--danger)}
.btn-process{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;background:linear-gradient(135deg,var(--accent),#5b85ff);color:#fff;border:none;border-radius:10px;padding:13px;font-size:14px;font-weight:600;font-family:'DM Sans',sans-serif;cursor:pointer;box-shadow:0 4px 14px rgba(61,108,255,.35);transition:box-shadow .2s,transform .15s;margin-top:4px}
.btn-process:hover{box-shadow:0 6px 20px rgba(61,108,255,.45);transform:translateY(-1px)}
.btn-process:disabled{opacity:.6;cursor:not-allowed;transform:none}

/* ── Form fields ── */
.field-group{display:flex;flex-direction:column;gap:4px;margin-bottom:16px}
.field-label{font-size:12px;font-weight:600;color:var(--text-sub);text-transform:uppercase;letter-spacing:.5px}
.field-control{height:42px;padding:0 12px;font-size:14px;font-family:'DM Sans',sans-serif;color:var(--ink);background:var(--surface);border:1.5px solid var(--border);border-radius:8px;outline:none;transition:border-color .18s,box-shadow .18s;width:100%}
.field-control:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(61,108,255,.12)}
.field-control.is-invalid{border-color:var(--danger)}
textarea.field-control{height:auto;padding:10px 12px;resize:vertical}
.field-error{font-size:12px;color:var(--danger);margin-top:2px}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.field-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}

/* ── Salary breakdown panel ── */
.breakdown-panel{background:linear-gradient(135deg,#eef2ff,#f0fdf9);border:1px solid #dbe4ff;border-radius:10px;padding:16px;margin-top:-4px;margin-bottom:16px;animation:fadeIn .25s ease}
@keyframes fadeIn{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:translateY(0)}}
.breakdown-title{font-family:'Syne',sans-serif;font-size:12px;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;display:flex;align-items:center;gap:6px}
.breakdown-row{display:flex;justify-content:space-between;align-items:center;padding:5px 0;font-size:13px;border-bottom:1px solid rgba(0,0,0,.05)}
.breakdown-row:last-child{border-bottom:none;font-weight:700;font-size:13.5px;padding-top:8px;margin-top:4px}
.breakdown-row.credit{color:#00a880}
.breakdown-row.debit{color:#cc2233}
.breakdown-row.warn{color:#cc7a00}
.total-hint{font-size:12px;color:var(--text-sub);margin-top:6px;padding:7px 10px;background:var(--muted);border-radius:6px;display:none}
.total-hint.visible{display:block}

/* ── Alert banners ── */
.alert-banner{display:flex;align-items:flex-start;gap:12px;padding:14px 16px;border-radius:10px;font-size:13.5px;margin-bottom:16px}
.alert-banner svg{flex-shrink:0;margin-top:1px}
.alert-success{background:rgba(0,217,166,.1);border:1px solid rgba(0,217,166,.25);color:#00816d}
.alert-warning{background:rgba(255,159,67,.1);border:1px solid rgba(255,159,67,.25);color:#7a4b00}
.alert-danger{background:rgba(255,71,87,.1);border:1px solid rgba(255,71,87,.25);color:#8b0011}

/* ── Table ── */
.sm-table{width:100%;border-collapse:separate;border-spacing:0;font-size:13.5px}
.sm-table thead th{background:var(--muted);color:var(--text-sub);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;padding:11px 16px;border-bottom:1px solid var(--border);white-space:nowrap}
.sm-table thead th:first-child{border-radius:8px 0 0 0}
.sm-table thead th:last-child{border-radius:0 8px 0 0}
.sm-table tbody tr{transition:background .15s}
.sm-table tbody tr:hover td{background:#f8f9ff}
.sm-table tbody td{padding:12px 16px;border-bottom:1px solid var(--border);vertical-align:middle;color:var(--ink)}
.receipt-code{font-size:11px;color:var(--text-sub);font-family:monospace;margin-top:2px}

/* ── Employee cell ── */
.employee-cell{display:flex;align-items:center;gap:10px}
.emp-avatar{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#5b85ff);color:#fff;font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-family:'Syne',sans-serif}
.emp-name{font-weight:500;font-size:13.5px}

/* ── Badges ── */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:50px;font-size:11.5px;font-weight:600;white-space:nowrap}
.badge-regular{background:rgba(61,108,255,.1);color:var(--accent)}
.badge-bonus{background:rgba(0,217,166,.13);color:#00a880}
.badge-advance{background:rgba(255,159,67,.13);color:#cc7a00}
.badge-other{background:#f0f0f0;color:#555}

/* ── Row action buttons ── */
.row-actions{display:flex;gap:4px;justify-content:center}
.action-btn{width:30px;height:30px;border-radius:7px;border:1px solid var(--border);background:var(--surface);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s;text-decoration:none;color:var(--text-sub)}
.action-btn:hover{color:var(--ink);border-color:#aaa;background:var(--muted)}
.action-btn.edit:hover{color:var(--accent);border-color:var(--accent);background:rgba(61,108,255,.07)}
.action-btn.del:hover{color:var(--danger);border-color:var(--danger);background:rgba(255,71,87,.07)}

/* ── Month filter bar ── */
.table-toolbar{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:0}
.search-wrap{position:relative;flex:1;min-width:200px;max-width:280px}
.search-wrap svg{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-sub);pointer-events:none}
.search-input{height:38px;padding:0 12px 0 34px;font-size:13.5px;font-family:'DM Sans',sans-serif;color:var(--ink);background:var(--surface);border:1.5px solid var(--border);border-radius:8px;outline:none;transition:border-color .18s;width:100%}
.search-input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(61,108,255,.1)}
.filter-select{height:38px;padding:0 10px;font-size:13px;font-family:'DM Sans',sans-serif;color:var(--ink);background:var(--surface);border:1.5px solid var(--border);border-radius:8px;outline:none;cursor:pointer;transition:border-color .18s}
.filter-select:focus{border-color:var(--accent)}

/* ── Pagination ── */
.pagination-wrap{display:flex;justify-content:space-between;align-items:center;padding:14px 20px;border-top:1px solid var(--border);font-size:13px;color:var(--text-sub);flex-wrap:wrap;gap:8px}
.pagination{display:flex;gap:4px;list-style:none;margin:0;padding:0}
.page-item a,.page-item span{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:7px;font-size:13px;text-decoration:none;color:var(--text-sub);border:1px solid var(--border);background:var(--surface);transition:all .15s}
.page-item.active a,.page-item.active span{background:var(--accent);color:#fff;border-color:var(--accent)}
.page-item a:hover{background:var(--muted);color:var(--ink)}
.page-item.disabled span{opacity:.4;cursor:default}

/* ── Modal ── */
.sm-modal-backdrop{display:none;position:fixed;inset:0;background:rgba(15,17,23,.5);backdrop-filter:blur(4px);z-index:1050;align-items:center;justify-content:center}
.sm-modal-backdrop.open{display:flex}
.sm-modal{background:var(--surface);border-radius:16px;width:100%;max-width:500px;margin:16px;box-shadow:var(--shadow-lg);animation:modalIn .25s cubic-bezier(.34,1.56,.64,1)}
@keyframes modalIn{from{transform:scale(.95) translateY(8px);opacity:0}to{transform:scale(1) translateY(0);opacity:1}}
.sm-modal-header{display:flex;justify-content:space-between;align-items:center;padding:20px 24px 16px;border-bottom:1px solid var(--border)}
.sm-modal-title{font-family:'Syne',sans-serif;font-size:17px;font-weight:700;color:var(--ink)}
.modal-close{width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:transparent;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-sub);transition:all .15s}
.modal-close:hover{background:var(--muted);color:var(--ink)}
.sm-modal-body{padding:24px}
.sm-modal-footer{display:flex;justify-content:flex-end;gap:10px;padding:16px 24px;border-top:1px solid var(--border);background:var(--muted);border-radius:0 0 16px 16px}

/* ── Confirm modal ── */
.confirm-icon{width:52px;height:52px;border-radius:50%;background:rgba(255,71,87,.12);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:var(--danger);font-size:22px}

/* ── Toast ── */
.toast-wrap{position:fixed;top:24px;right:24px;z-index:9999}
.toast-msg{background:var(--surface);border-radius:10px;padding:14px 18px;box-shadow:var(--shadow-lg);display:flex;align-items:center;gap:10px;font-size:13.5px;font-weight:500;border-left:3px solid var(--accent2);animation:slideIn .3s ease;min-width:240px}
.toast-msg.error{border-left-color:var(--danger)}
@keyframes slideIn{from{transform:translateX(30px);opacity:0}to{transform:translateX(0);opacity:1}}

/* ── Salary already paid warn ── */
.already-paid-banner{background:rgba(255,159,67,.08);border:1px solid rgba(255,159,67,.3);border-radius:10px;padding:14px 16px;margin-bottom:16px;font-size:13px;color:#7a4b00;display:none}
.already-paid-banner.visible{display:block}

@media(max-width:768px){
    .sp-page{padding:16px}
    .field-row,.field-row-3{grid-template-columns:1fr}
    .page-hero{flex-direction:column;align-items:flex-start}
}
</style>

<?php include("../includes/header.php"); ?>

<div class="pcoded-main-container">
<div class="pcoded-content">
<div class="sp-page">

    <!-- Toast -->
    <div class="toast-wrap" id="toastWrap" style="display:none">
        <div class="toast-msg" id="toastMsg">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            <span id="toastText"></span>
        </div>
    </div>

    <!-- Page Hero -->
    <div class="page-hero">
        <div>
            <div class="page-hero-title">Process Salary Payment</div>
            <div class="page-hero-subtitle">Pay individual employees or batch process multiple months</div>
        </div>
        <div class="hero-actions">
            <a href="salary_management.php" class="btn-sm-ghost">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                Back to Management
            </a>
        </div>
    </div>

    <!-- Two column layout -->
    <div class="sp-grid">

        <!-- LEFT: Payment Form -->
        <div>
            <div class="sm-card">
                <div class="sm-card-header">
                    <div class="sm-card-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        New Payment
                    </div>
                </div>
                <div class="sm-card-body">

                    <?php if (isset($_GET['success'])): ?>
                    <div class="alert-banner alert-success">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Payment processed successfully. Email notification sent.</span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($error_message)): ?>
                    <div class="alert-banner alert-danger">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span><?= htmlspecialchars($error_message) ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="already-paid-banner" id="alreadyPaidBanner"></div>

                    <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]) ?>" method="post" id="paymentForm">

                        <!-- Employee -->
                        <div class="field-group">
                            <label class="field-label">Employee</label>
                            <select class="field-control <?= !empty($user_id_err) ? 'is-invalid' : '' ?>" id="user_id" name="user_id" required>
                                <option value="">Select employee…</option>
                                <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['id'] ?>"
                                    data-base-salary="<?= $emp['base_salary'] ?>"
                                    data-currency="<?= $emp['currency'] ?>"
                                    <?= $user_id == $emp['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($emp['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($user_id_err)): ?><div class="field-error"><?= $user_id_err ?></div><?php endif; ?>
                        </div>

                        <!-- Account -->
                        <div class="field-group">
                            <label class="field-label">Account</label>
                            <select class="field-control <?= !empty($main_account_id_err) ? 'is-invalid' : '' ?>" id="main_account_id" name="main_account_id" required>
                                <option value="">Select account…</option>
                                <?php foreach ($accounts as $acc): ?>
                                <option value="<?= $acc['id'] ?>"
                                    data-usd="<?= $acc['usd_balance'] ?>"
                                    data-afs="<?= $acc['afs_balance'] ?>"
                                    <?= $main_account_id == $acc['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($acc['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($main_account_id_err)): ?><div class="field-error"><?= $main_account_id_err ?></div><?php endif; ?>
                        </div>

                        <!-- Month + Months to pay -->
                        <div class="field-row">
                            <div class="field-group">
                                <label class="field-label">Payment For Month</label>
                                <input type="month" class="field-control <?= !empty($payment_for_month_err) ? 'is-invalid' : '' ?>"
                                       id="payment_for_month" name="payment_for_month" value="<?= date('Y-m') ?>" required>
                                <?php if (!empty($payment_for_month_err)): ?><div class="field-error"><?= $payment_for_month_err ?></div><?php endif; ?>
                            </div>
                            <div class="field-group">
                                <label class="field-label">Months to Pay</label>
                                <input type="number" class="field-control" id="months_to_pay" name="months_to_pay" min="1" step="1" value="1">
                            </div>
                        </div>

                        <!-- Amount + Currency -->
                        <div class="field-row">
                            <div class="field-group">
                                <label class="field-label">Amount</label>
                                <input type="number" class="field-control <?= !empty($amount_err) ? 'is-invalid' : '' ?>"
                                       id="amount" name="amount" step="0.01" value="<?= $amount ?>" required>
                                <?php if (!empty($amount_err)): ?><div class="field-error"><?= $amount_err ?></div><?php endif; ?>
                            </div>
                            <div class="field-group">
                                <label class="field-label">Currency</label>
                                <input type="hidden" id="currency" name="currency" value="<?= $currency ?>">
                                <input type="text" class="field-control" id="currencyDisplay" readonly
                                       style="background:var(--muted);color:var(--text-sub)"
                                       placeholder="Set from employee's salary record">
                            </div>
                        </div>

                        <!-- Total hint -->
                        <div class="total-hint" id="totalHint"></div>

                        <!-- Salary Breakdown (populated via JS) -->
                        <div id="breakdownPanel" style="display:none" class="breakdown-panel">
                            <div class="breakdown-title">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                                Salary Breakdown
                            </div>
                            <div id="breakdownRows"></div>
                        </div>

                        <!-- Absence warning (populated via JS) -->
                        <div id="absenceWarning" style="display:none" class="alert-banner alert-warning">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            <span id="absenceText"></span>
                        </div>

                        <!-- Payment Type -->
                        <div class="field-group">
                            <label class="field-label">Payment Type</label>
                            <select class="field-control" id="payment_type" name="payment_type">
                                <option value="regular" <?= $payment_type == 'regular' ? 'selected' : '' ?>>Regular Salary</option>
                                <option value="bonus"   <?= $payment_type == 'bonus'   ? 'selected' : '' ?>>Bonus</option>
                                <option value="advance" <?= $payment_type == 'advance' ? 'selected' : '' ?>>Advance</option>
                                <option value="other"   <?= $payment_type == 'other'   ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>

                        <!-- Description -->
                        <div class="field-group">
                            <label class="field-label">Description</label>
                            <input type="text" class="field-control" id="description" name="description" value="<?= htmlspecialchars($description) ?>" placeholder="Optional note…">
                        </div>

                        <button type="submit" class="btn-process" id="submitBtn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            Process Payment
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- RIGHT: Payment History -->
        <div>
            <div class="sm-card">
                <div class="sm-card-header">
                    <div class="sm-card-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        Payment History
                    </div>
                    <div class="table-toolbar">
                        <div class="search-wrap">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="text" class="search-input" id="histSearch" placeholder="Search employee…">
                        </div>
                        <select class="filter-select" id="monthFilter">
                            <option value="">All months</option>
                            <?php for ($i = 0; $i < 12; $i++):
                                $mv = date('Y-m', strtotime("-$i months"));
                                $ml = date('F Y', strtotime("-$i months"));
                                $sel = $i === 0 ? 'selected' : '';
                            ?>
                            <option value="<?= $mv ?>" <?= $sel ?>><?= $ml ?></option>
                            <?php endfor; ?>
                        </select>
                        <select class="filter-select" id="typeFilter">
                            <option value="">All types</option>
                            <option value="regular">Regular</option>
                            <option value="bonus">Bonus</option>
                            <option value="advance">Advance</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div style="overflow-x:auto">
                    <table class="sm-table" id="histTable">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Amount</th>
                                <th>Type</th>
                                <th>Account</th>
                                <th>For Month</th>
                                <th>Paid On</th>
                                <th style="text-align:center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($payments)): ?>
                            <tr><td colspan="7" style="text-align:center;padding:48px;color:var(--text-sub)">
                                <div style="font-size:28px;margin-bottom:8px">💳</div>
                                <div style="font-weight:600">No payment records yet</div>
                            </td></tr>
                        <?php else: ?>
                        <?php foreach ($payments as $row):
                            $initials = strtoupper(substr($row['employee_name'], 0, 1));
                            $typeMap = ['regular' => 'badge-regular', 'bonus' => 'badge-bonus', 'advance' => 'badge-advance', 'other' => 'badge-other'];
                            $badgeClass = $typeMap[$row['payment_type']] ?? 'badge-other';
                        ?>
                        <tr data-name="<?= strtolower($row['employee_name']) ?>"
                            data-month="<?= date('Y-m', strtotime($row['payment_for_month'])) ?>"
                            data-type="<?= $row['payment_type'] ?>">
                            <td>
                                <div class="employee-cell">
                                    <div class="emp-avatar"><?= $initials ?></div>
                                    <div>
                                        <div class="emp-name"><?= htmlspecialchars($row['employee_name']) ?></div>
                                        <div class="receipt-code"><?= htmlspecialchars($row['receipt']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span style="font-family:'Syne',sans-serif;font-weight:600;font-size:14px"><?= number_format($row['amount'], 2) ?></span>
                                <span style="font-size:11px;color:var(--text-sub);margin-left:3px"><?= $row['currency'] ?></span>
                            </td>
                            <td>
                                <span style="<?php
                                    $styleMap = [
                                        'regular' => 'background:rgba(61,108,255,.1);color:#3d6cff',
                                        'bonus' => 'background:rgba(0,217,166,.13);color:#00a880',
                                        'advance' => 'background:rgba(255,159,67,.13);color:#cc7a00',
                                        'other' => 'background:#f0f0f0;color:#555'
                                    ];
                                    echo $styleMap[$row['payment_type']] ?? $styleMap['other'];
                                ?>; display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:50px;font-size:11.5px;font-weight:600;white-space:nowrap">
                                    <?= ucfirst($row['payment_type']) ?>
                                </span>
                            </td>
                            <td style="font-size:12.5px;color:var(--text-sub)"><?= htmlspecialchars($row['account_name']) ?></td>
                            <td style="font-size:13px;color:var(--text-sub)"><?= date('M Y', strtotime($row['payment_for_month'])) ?></td>
                            <td style="font-size:13px;color:var(--text-sub)"><?= date('M d, Y', strtotime($row['payment_date'])) ?></td>
                            <td>
                                <div class="row-actions">
                                    <button class="action-btn edit open-edit"
                                        title="Edit"
                                        data-id="<?= $row['id'] ?>"
                                        data-amount="<?= $row['amount'] ?>"
                                        data-currency="<?= $row['currency'] ?>"
                                        data-date="<?= date('Y-m-d', strtotime($row['payment_date'])) ?>"
                                        data-description="<?= htmlspecialchars($row['description'], ENT_QUOTES) ?>"
                                        data-type="<?= $row['payment_type'] ?>"
                                        data-user-id="<?= $row['user_id'] ?>"
                                        data-account-id="<?= $row['main_account_id'] ?>">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    <button class="action-btn del open-delete"
                                        title="Delete"
                                        data-id="<?= $row['id'] ?>"
                                        data-amount="<?= $row['amount'] ?>"
                                        data-account-id="<?= $row['main_account_id'] ?>"
                                        data-name="<?= htmlspecialchars($row['employee_name'], ENT_QUOTES) ?>">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination-wrap">
                    <span><?= $total_rows ?> total records</span>
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                        <li class="page-item"><a href="?page=<?= $page - 1 ?>">‹</a></li>
                        <?php else: ?>
                        <li class="page-item disabled"><span>‹</span></li>
                        <?php endif; ?>
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                        <li class="page-item"><a href="?page=<?= $page + 1 ?>">›</a></li>
                        <?php else: ?>
                        <li class="page-item disabled"><span>›</span></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- end sp-grid -->

</div><!-- end sp-page -->
</div>
</div>

<!-- ════════════════════════════════
     EDIT PAYMENT MODAL
════════════════════════════════ -->
<div class="sm-modal-backdrop" id="editModalBackdrop">
    <div class="sm-modal">
        <div class="sm-modal-header">
            <div class="sm-modal-title">Edit Payment</div>
            <button class="modal-close" onclick="closeModal('editModalBackdrop')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="editPaymentForm">
            <input type="hidden" id="edit_payment_id"      name="payment_id">
            <input type="hidden" id="edit_user_id"         name="user_id">
            <input type="hidden" id="edit_original_amount" name="original_amount">
            <input type="hidden" id="edit_main_account_id" name="main_account_id">
            <input type="hidden" id="edit_currency"        name="currency">
            <div class="sm-modal-body">
                <div class="field-row" style="margin-bottom:14px">
                    <div class="field-group" style="margin-bottom:0">
                        <label class="field-label">Amount</label>
                        <input type="number" class="field-control" id="edit_payment_amount" name="payment_amount" step="0.01" required>
                    </div>
                    <div class="field-group" style="margin-bottom:0">
                        <label class="field-label">Currency</label>
                        <input type="text" class="field-control" id="edit_currency_display" readonly style="background:var(--muted);color:var(--text-sub)">
                    </div>
                </div>
                <div class="field-row" style="margin-bottom:14px">
                    <div class="field-group" style="margin-bottom:0">
                        <label class="field-label">Payment Date</label>
                        <input type="date" class="field-control" id="edit_payment_date" name="payment_date" required>
                    </div>
                    <div class="field-group" style="margin-bottom:0">
                        <label class="field-label">Payment Type</label>
                        <select class="field-control" id="edit_payment_type" name="payment_type" disabled>
                            <option value="regular">Regular Salary</option>
                            <option value="bonus">Bonus</option>
                            <option value="advance">Advance</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="field-group" style="margin-bottom:0">
                    <label class="field-label">Description</label>
                    <textarea class="field-control" id="edit_payment_description" name="payment_description" rows="3"></textarea>
                </div>
            </div>
            <div class="sm-modal-footer">
                <button type="button" class="btn-sm-ghost" onclick="closeModal('editModalBackdrop')">Cancel</button>
                <button type="button" class="btn-sm-primary" id="saveEditBtn">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ════════════════════════════════
     DELETE CONFIRM MODAL
════════════════════════════════ -->
<div class="sm-modal-backdrop" id="deleteModalBackdrop">
    <div class="sm-modal" style="max-width:420px">
        <div class="sm-modal-body" style="padding:32px 28px;text-align:center">
            <div class="confirm-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M9 6V4h6v2"/></svg>
            </div>
            <div style="font-family:'Syne',sans-serif;font-size:17px;font-weight:700;margin-bottom:8px">Delete Payment?</div>
            <div style="font-size:13.5px;color:var(--text-sub);margin-bottom:24px" id="deleteConfirmText">
                This will reverse the deduction from the account and cannot be undone.
            </div>
            <div style="display:flex;gap:10px;justify-content:center">
                <button class="btn-sm-ghost" onclick="closeModal('deleteModalBackdrop')">Cancel</button>
                <button class="btn-sm-danger" id="confirmDeleteBtn" style="background:var(--danger);color:#fff;border-color:var(--danger);box-shadow:0 4px 14px rgba(255,71,87,.3)">
                    Yes, Delete
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
// ── Modal helpers ─────────────────────────────────
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}
document.querySelectorAll('.sm-modal-backdrop').forEach(backdrop => {
    backdrop.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('open');
    });
});

// ── Toast ─────────────────────────────────────────
function showToast(msg, isError = false) {
    const wrap = document.getElementById('toastWrap');
    const box  = document.getElementById('toastMsg');
    document.getElementById('toastText').textContent = msg;
    box.className = 'toast-msg' + (isError ? ' error' : '');
    wrap.style.display = 'block';
    setTimeout(() => { wrap.style.display = 'none'; }, 3500);
}
<?php if (isset($_GET['success'])): ?>
document.addEventListener('DOMContentLoaded', () => showToast('Payment processed successfully!'));
<?php endif; ?>

// ── Table live filter ─────────────────────────────
const histRows    = document.querySelectorAll('#histTable tbody tr[data-name]');
const histSearch  = document.getElementById('histSearch');
const monthFilter = document.getElementById('monthFilter');
const typeFilter  = document.getElementById('typeFilter');

function filterHistory() {
    const q  = histSearch.value.toLowerCase().trim();
    const mo = monthFilter.value;
    const ty = typeFilter.value;
    histRows.forEach(row => {
        const nm = !q  || row.dataset.name.includes(q);
        const mm = !mo || row.dataset.month === mo;
        const tt = !ty || row.dataset.type  === ty;
        row.style.display = nm && mm && tt ? '' : 'none';
    });
}
histSearch.addEventListener('input',   filterHistory);
monthFilter.addEventListener('change', filterHistory);
typeFilter.addEventListener('change',  filterHistory);
filterHistory(); // apply initial (current month selected)

// ── Total hint ────────────────────────────────────
function updateTotalHint() {
    const amount  = parseFloat(document.getElementById('amount').value) || 0;
    const months  = parseInt(document.getElementById('months_to_pay').value) || 1;
    const cur     = document.getElementById('currency').value;
    const hint    = document.getElementById('totalHint');
    if (amount > 0 && months > 1) {
        hint.textContent = `Total: ${(amount * months).toFixed(2)} ${cur} across ${months} months`;
        hint.classList.add('visible');
    } else {
        hint.classList.remove('visible');
    }
}
['amount','months_to_pay','currency'].forEach(id => {
    document.getElementById(id).addEventListener('input', updateTotalHint);
    document.getElementById(id).addEventListener('change', updateTotalHint);
});

// ── Auto description + submit state on payment type change ─
document.getElementById('payment_type').addEventListener('change', function() {
    const map = { regular: 'Regular Salary Payment', bonus: 'Bonus Payment', advance: 'Salary Advance' };
    document.getElementById('description').value = map[this.value] || '';
    updateSubmitState();
});

// ── Employee select → fetch salary details ────────
let alreadyPaidMonthly = false;

function updateSubmitState() {
    document.getElementById('submitBtn').disabled =
        alreadyPaidMonthly && document.getElementById('payment_type').value === 'regular';
}

document.getElementById('user_id').addEventListener('change', fetchSalaryDetails);
document.getElementById('payment_for_month').addEventListener('change', fetchSalaryDetails);

// Sync currency display on load (e.g. after validation error re-render)
document.addEventListener('DOMContentLoaded', () => {
    const sel  = document.getElementById('user_id');
    const opt  = sel.options[sel.selectedIndex];
    if (opt && opt.value) {
        document.getElementById('currencyDisplay').value     = opt.dataset.currency || '';
        document.getElementById('currency').value            = opt.dataset.currency || '';
        fetchSalaryDetails();
    }
});

function fetchSalaryDetails() {
    const sel      = document.getElementById('user_id');
    const opt      = sel.options[sel.selectedIndex];
    const userId   = opt.value;
    const baseSal  = parseFloat(opt.dataset.baseSalary) || 0;
    const currency = opt.dataset.currency || 'USD';
    const month    = document.getElementById('payment_for_month').value;

    // Hide breakdown
    alreadyPaidMonthly = false;
    updateSubmitState();
    document.getElementById('breakdownPanel').style.display = 'none';
    document.getElementById('absenceWarning').style.display = 'none';
    document.getElementById('alreadyPaidBanner').classList.remove('visible');

    if (!userId || !baseSal) return;

    document.getElementById('currency').value = currency;
    document.getElementById('currencyDisplay').value = currency;

    $.ajax({
        url: 'get_salary_details.php',
        type: 'POST',
        dataType: 'json',
        data: { user_id: userId, currency: currency, payment_for_month: month },
        success: function(data) {
            if (data.error) { console.error(data.error); return; }

            const advances   = parseFloat(data.totalAdvances)   || 0;
            const deductions = parseFloat(data.totalDeductions)  || 0;
            const bonuses    = parseFloat(data.totalBonuses)     || 0;
            const net        = Math.max(0, baseSal - advances - deductions + bonuses);

            // Already paid banner
            if (data.salaryAlreadyPaid) {
                const banner = document.getElementById('alreadyPaidBanner');
                banner.innerHTML = `⚠️ Salary already processed for this month — <strong>${data.existingPayment.amount} ${currency}</strong> on ${data.existingPayment.payment_date}.`;
                banner.classList.add('visible');
                alreadyPaidMonthly = true;
            } else {
                alreadyPaidMonthly = false;
            }
            updateSubmitState();

            // Set amount
            document.getElementById('amount').value = net.toFixed(2);
            document.getElementById('amount').dataset.maxAmount = net;

            // Build breakdown
            let rows = '';
            rows += `<div class="breakdown-row"><span>Base Salary</span><span>+ ${baseSal.toFixed(2)} ${currency}</span></div>`;
            if (bonuses > 0)    rows += `<div class="breakdown-row credit"><span>Bonuses</span><span>+ ${bonuses.toFixed(2)} ${currency}</span></div>`;
            if (deductions > 0) rows += `<div class="breakdown-row debit"><span>Deductions</span><span>− ${deductions.toFixed(2)} ${currency}</span></div>`;
            if (advances > 0)   rows += `<div class="breakdown-row warn"><span>Advance Deductions</span><span>− ${advances.toFixed(2)} ${currency}</span></div>`;
            rows += `<div class="breakdown-row"><span>Net Payable</span><span>${net.toFixed(2)} ${currency}</span></div>`;
            document.getElementById('breakdownRows').innerHTML = rows;
            document.getElementById('breakdownPanel').style.display = 'block';

            // Absence warning
            if (data.has_attendance_feature && data.absent_days > 0 && !data.absence_already_deducted) {
                const dedAmt = ((baseSal / 30) * data.absent_days).toFixed(2);
                document.getElementById('absenceText').innerHTML =
                    `Employee has <strong>${data.absent_days} absent days</strong> this month. Potential absence deduction: <strong>${dedAmt} ${currency}</strong>.
                    <button type="button" onclick="deductAbsence(${userId},'${currency}',${baseSal},${data.absent_days},'${month}')"
                        style="margin-left:8px;background:var(--danger);color:#fff;border:none;border-radius:6px;padding:4px 10px;font-size:12px;cursor:pointer;font-family:'DM Sans',sans-serif">
                        Apply Deduction
                    </button>`;
                document.getElementById('absenceWarning').style.display = 'flex';
            }

            updateTotalHint();
        },
        error: function() { showToast('Failed to load salary details.', true); }
    });
}

// ── Absence deduction ─────────────────────────────
let isDeductingAbsence = false;
function deductAbsence(userId, currency, baseSalary, absentDays, paymentMonth, btnElement) {
    if (isDeductingAbsence) return;
    isDeductingAbsence = true;
    
    const btn = btnElement || document.activeElement;
    const originalHtml = btn ? btn.innerHTML : '';
    
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    }
    
    $.ajax({
        url: 'deduct_absence.php',
        type: 'POST',
        data: { user_id: userId, payment_for_month: paymentMonth, absent_days: absentDays, base_salary: baseSalary, currency: currency },
        success: function(res) {
            isDeductingAbsence = false;
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
            try {
                const r = JSON.parse(res);
                if (r.success) {
                    showToast(`Absence deduction of ${r.deducted_amount} ${currency} applied.`);
                    fetchSalaryDetails();
                } else {
                    showToast(r.message || 'Failed to create deduction.', true);
                }
            } catch(e) { showToast('Error processing response.', true); }
        },
        error: function() {
            isDeductingAbsence = false;
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
            showToast('Network error.', true);
        }
    });
}

// ── Account balance warning ───────────────────────
['main_account_id','amount','currency'].forEach(id => {
    document.getElementById(id).addEventListener('change', function() {
        const sel = document.getElementById('main_account_id');
        const opt = sel.options[sel.selectedIndex];
        if (!opt.value) return;
        const amount  = parseFloat(document.getElementById('amount').value) || 0;
        const cur     = document.getElementById('currency').value;
        const balance = cur === 'USD' ? parseFloat(opt.dataset.usd) : parseFloat(opt.dataset.afs);
        if (amount > balance) {
            showToast(`⚠️ Account balance (${balance.toFixed(2)} ${cur}) is less than payment amount.`, true);
        }
    });
});

// ── Edit modal ────────────────────────────────────
document.querySelectorAll('.open-edit').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('edit_payment_id').value      = this.dataset.id;
        document.getElementById('edit_user_id').value         = this.dataset.userId;
        document.getElementById('edit_payment_amount').value  = this.dataset.amount;
        document.getElementById('edit_original_amount').value = this.dataset.amount;
        document.getElementById('edit_currency').value        = this.dataset.currency;
        document.getElementById('edit_currency_display').value= this.dataset.currency;
        document.getElementById('edit_payment_date').value    = this.dataset.date;
        document.getElementById('edit_payment_description').value = this.dataset.description;
        document.getElementById('edit_payment_type').value    = this.dataset.type;
        document.getElementById('edit_main_account_id').value = this.dataset.accountId;
        document.getElementById('editModalBackdrop').classList.add('open');
    });
});

document.getElementById('saveEditBtn').addEventListener('click', function() {
    const form    = document.getElementById('editPaymentForm');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '...Saving';
    $.ajax({
        url: 'update_salary_payment.php',
        type: 'POST',
        data: $(form).serialize(),
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                showToast('Payment updated successfully.');
                closeModal('editModalBackdrop');
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(res.message || 'Failed to update.', true);
                btn.disabled = false;
                btn.innerHTML = 'Save Changes';
            }
        },
        error: function() {
            showToast('Network error.', true);
            btn.disabled = false;
            btn.innerHTML = 'Save Changes';
        }
    });
});

// ── Delete modal ──────────────────────────────────
let deleteData = null;
document.querySelectorAll('.open-delete').forEach(btn => {
    btn.addEventListener('click', function() {
        deleteData = { id: this.dataset.id, amount: this.dataset.amount, accountId: this.dataset.accountId };
        document.getElementById('deleteConfirmText').innerHTML =
            `Delete payment for <strong>${this.dataset.name}</strong>? This will reverse the account deduction.`;
        document.getElementById('deleteModalBackdrop').classList.add('open');
    });
});

document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    if (!deleteData) return;
    const btn = this;
    btn.disabled = true;
    $.ajax({
        url: 'delete_salary_payment.php',
        type: 'POST',
        data: { payment_id: deleteData.id, amount: deleteData.amount, main_account_id: deleteData.accountId },
        success: function(res) {
            try {
                const r = JSON.parse(res);
                if (r.success) {
                    showToast('Payment deleted successfully.');
                    closeModal('deleteModalBackdrop');
                    setTimeout(() => location.reload(), 800);
                } else {
                    showToast(r.message || 'Failed to delete.', true);
                    btn.disabled = false;
                }
            } catch(e) { showToast('Error.', true); btn.disabled = false; }
        },
        error: function() { showToast('Network error.', true); btn.disabled = false; }
    });
});
</script>
</body>
</html>
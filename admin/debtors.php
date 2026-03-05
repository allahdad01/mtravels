<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Include secure headers helper
require_once 'includes/set_secure_headers.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in with proper role
$allowed_roles = ['admin', 'finance'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    // Log unauthorized access attempt
    error_log("Unauthorized access attempt to dashboard: " . ($_SESSION['user_id'] ?? 'unknown') . " - Role: " . ($_SESSION['role'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Generate CSRF token if it doesn't exist
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
require_once '../includes/db.php';
include '../api/debtor/debtors_handler.php';

// Check if user is admin
$isAdmin = $_SESSION['role'] === 'admin';

// Fetch debtors list
$status_filter = isset($_GET['status']) && $_GET['status'] === 'inactive' ? 'inactive' : 'active';

try {
     // Get total count for current status
     $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM debtors WHERE status = ? AND tenant_id = ? AND branch_id = ?");
     $countStmt->execute([$status_filter, $tenant_id, $branch_id]);
     $total_count = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
     
     // Get counts for both active and inactive debtors
     $activeCountStmt = $pdo->prepare("SELECT COUNT(*) as count FROM debtors WHERE status = 'active' AND tenant_id = ? AND branch_id = ?");
     $activeCountStmt->execute([$tenant_id, $branch_id]);
     $active_count = $activeCountStmt->fetch(PDO::FETCH_ASSOC)['count'];
     
     $inactiveCountStmt = $pdo->prepare("SELECT COUNT(*) as count FROM debtors WHERE status = 'inactive' AND tenant_id = ? AND branch_id = ?");
     $inactiveCountStmt->execute([$tenant_id, $branch_id]);
     $inactive_count = $inactiveCountStmt->fetch(PDO::FETCH_ASSOC)['count'];
     
     // Pagination
     $items_per_page = 10;
     $current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
     $offset = ($current_page - 1) * $items_per_page;
     $total_pages = ceil($total_count / $items_per_page);
     
     // Fetch debtors with pagination
     $stmt = $pdo->prepare("SELECT * FROM debtors WHERE status = ? AND tenant_id = ? AND branch_id = ? ORDER BY name ASC LIMIT ? OFFSET ?");
     $stmt->execute([$status_filter, $tenant_id, $branch_id, $items_per_page, $offset]);
     $debtors = $stmt->fetchAll(PDO::FETCH_ASSOC);
     
     // Fetch total debts by currency
     $currencyStmt = $pdo->prepare("SELECT currency, SUM(balance) as total FROM debtors WHERE status = ? AND tenant_id = ? AND branch_id = ? GROUP BY currency");
     $currencyStmt->execute([$status_filter, $tenant_id, $branch_id]);
     $currency_results = $currencyStmt->fetchAll(PDO::FETCH_ASSOC);
     $currency_totals = [];
     foreach ($currency_results as $row) {
         $currency_totals[$row['currency']] = $row['total'];
     }
     
     // Fetch main accounts for the dropdown
     $mainAcctStmt = $pdo->prepare("SELECT id, name FROM main_account WHERE tenant_id = ? AND branch_id = ? ORDER BY name ASC");
     $mainAcctStmt->execute([$tenant_id, $branch_id]);
     $main_accounts = $mainAcctStmt->fetchAll(PDO::FETCH_ASSOC);
 } catch (PDOException $e) {
     error_log("Error fetching debtors: " . $e->getMessage());
     $debtors = [];
     $total_count = 0;
     $total_pages = 0;
     $main_accounts = [];
     $currency_totals = [];
     $active_count = 0;
     $inactive_count = 0;
 }
?>


    <?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../css/general/modal-styles.css">
<link rel="stylesheet" href="../css/debtors/styles.css">

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">

<style>
:root {
  --primary:#4099ff;--primary-dark:#2563eb;--primary-light:#60a5fa;
  --accent:#2ed8b6;--accent-dark:#14b8a6;--accent-light:#5eead4;
  --violet:#7c3aed;--violet-light:#a78bfa;--indigo:#4f46e5;
  --sky:#0ea5e9;--emerald:#10b981;--amber:#f59e0b;
  --rose:#f43f5e;--orange:#f97316;--pink:#ec4899;--teal:#14b8a6;
  --bg:#f8fafc;--surface:#ffffff;--surface2:#f1f5f9;--surface3:#e2e8f0;
  --border:rgba(0,0,0,0.08);
  --text:#1e293b;--text-muted:#64748b;
  --grad-start:#4099ff;--grad-end:#2ed8b6;--grad:linear-gradient(135deg,var(--grad-start) 0%,var(--grad-end) 100%);
}
.pcoded-main-container{background:var(--bg)!important;display:flex;flex-direction:column;min-height:100vh;}
.pcoded-wrapper{display:flex;flex-direction:column;flex:1;}
.pcoded-content,.pcoded-inner-content{background:transparent!important;flex:1;display:flex;flex-direction:column;}
.main-body{flex:1;display:flex;flex-direction:column;}
.page-wrapper{flex:1;display:flex;flex-direction:column;}
.container{padding-left:20px;padding-right:20px;}
.dash-wrap{font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);padding:28px 20px;position:relative;}

@media (max-width: 768px) {
  .container {
    padding-left: 16px;
    padding-right: 16px;
  }
  .dash-wrap {
    padding: 20px 16px;
  }
}

@media (max-width: 480px) {
  .container {
    padding-left: 12px;
    padding-right: 12px;
  }
  .dash-wrap {
    padding: 16px 12px;
  }
}
.dash-wrap::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background:radial-gradient(ellipse 80% 60% at 10% 0%,rgba(124,58,237,.15) 0%,transparent 60%),radial-gradient(ellipse 60% 50% at 90% 10%,rgba(14,165,233,.12) 0%,transparent 55%),radial-gradient(ellipse 50% 40% at 50% 100%,rgba(16,185,129,.08) 0%,transparent 50%);}
.dash-inner{position:relative;z-index:1;}
.sec-label{font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--text-muted);margin-bottom:14px;display:flex;align-items:center;gap:8px;}
.sec-label::after{content:'';flex:1;height:1px;background:var(--border);}
.d-card{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:24px;margin-bottom:22px;}
.d-card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:12px;}
.d-card-title{font-size:15px;font-weight:800;display:flex;align-items:center;gap:10px;color:var(--text);}
.ci{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:13px;}
.ci-violet{background:rgba(124,58,237,.2);color:var(--violet-light);}
.ci-sky{background:rgba(14,165,233,.2);color:var(--sky);}
.ci-emerald{background:rgba(16,185,129,.2);color:var(--emerald);}
.ci-amber{background:rgba(245,158,11,.2);color:var(--amber);}
.ci-rose{background:rgba(244,63,94,.2);color:var(--rose);}
.dbtn{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:10px;font-size:13px;font-weight:600;font-family:inherit;cursor:pointer;border:none;transition:all .2s;text-decoration:none;}
.dbtn-ghost{background:var(--surface2);color:var(--text);border:1px solid var(--border);}
.dbtn-ghost:hover{background:var(--surface3);transform:translateY(-1px);color:var(--text);}
.dbtn-primary{background:var(--grad);color:#fff;box-shadow:0 4px 20px rgba(64,153,255,.35);}
.dbtn-primary:hover{transform:translateY(-2px);color:#fff;}
.d-alert{border-radius:14px;padding:16px 20px;margin-bottom:12px;display:flex;align-items:flex-start;gap:14px;border:1px solid;animation:slideIn .4s ease;}
@keyframes slideIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
.d-alert-warning{background:rgba(245,158,11,.1);border-color:rgba(245,158,11,.3);}
.d-alert-danger{background:rgba(244,63,94,.1);border-color:rgba(244,63,94,.3);}

/* Debtor cards grid */
.debtor-grid{display:flex;flex-direction:column;gap:12px;margin-bottom:22px;}
.debtor-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:16px;position:relative;overflow:hidden;transition:transform .2s,border-color .2s,box-shadow .2s;cursor:pointer;display:grid;grid-template-columns:auto 1fr auto auto auto 1fr auto;align-items:center;gap:16px;}
.debtor-card:hover{transform:translateY(-2px);border-color:rgba(64,153,255,.4);box-shadow:0 8px 30px rgba(64,153,255,.12);}
.debtor-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--grad);}

/* Mobile Responsive */
@media (max-width: 768px) {
  .debtor-card{
    grid-template-columns: auto 1fr;
    gap: 12px;
    padding: 14px;
  }
  .debtor-card > div:nth-child(2),
  .debtor-card > div:nth-child(3),
  .debtor-card > div:nth-child(4),
  .debtor-card > div:nth-child(5),
  .debtor-card > div:nth-child(6) {
    grid-column: 1 / -1;
  }
  .dc-actions {
    grid-column: 1 / -1;
    margin-top: 8px;
    justify-content: flex-start;
  }
  .dc-stats {
    grid-column: 1 / -1;
    flex-wrap: wrap;
    gap: 12px;
  }
}

@media (max-width: 480px) {
  .debtor-card{
    grid-template-columns: 1fr;
    gap: 10px;
    padding: 12px;
  }
  .debtor-card > div {
    grid-column: 1 / -1;
  }
  .dc-icon {
    width: 32px;
    height: 32px;
    font-size: 14px;
  }
  .dc-label {
    font-size: 9px;
  }
  .dc-name {
    font-size: 13px;
  }
  .dc-balance {
    font-size: 14px;
  }
  .dc-btn {
    padding: 6px 10px;
    font-size: 11px;
  }
  .dc-btn span {
    display: none;
  }
  .dc-btn i {
    margin-right: 0;
  }
}
.dc-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;background:rgba(64,153,255,.15);color:var(--sky);flex-shrink:0;}
.dc-label{font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px;}
.dc-name{font-size:14px;font-weight:700;color:var(--text);word-break:break-word;}
.dc-balance{font-size:16px;font-weight:800;font-family:'JetBrains Mono',monospace;letter-spacing:-.5px;white-space:nowrap;}
.dc-currency{font-size:12px;color:var(--text-muted);font-weight:600;white-space:nowrap;}
.dc-status{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;padding:4px 12px;border-radius:20px;white-space:nowrap;}
.dc-status.active{background:rgba(16,185,129,.15);color:var(--emerald);}
.dc-status.inactive{background:rgba(244,63,94,.15);color:var(--rose);}
.dc-actions{display:flex;gap:6px;flex-wrap:wrap;flex-shrink:0;align-items:center;}
.dc-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:7px 12px;border-radius:8px;font-size:12px;font-weight:600;border:none;cursor:pointer;transition:all .2s;font-family:inherit;flex-shrink:0;white-space:nowrap;}
.dc-btn span{display:inline-block;}
.dc-btn-primary{background:var(--grad);color:#fff;}
.dc-btn-primary:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(64,153,255,.3);}
.dc-btn-info{background:rgba(14,165,233,.15);color:var(--sky);border:1px solid rgba(14,165,233,.3);}
.dc-btn-info:hover{background:rgba(14,165,233,.25);border-color:rgba(14,165,233,.5);}
.dc-btn-warning{background:rgba(245,158,11,.15);color:var(--amber);border:1px solid rgba(245,158,11,.3);}
.dc-btn-warning:hover{background:rgba(245,158,11,.25);border-color:rgba(245,158,11,.5);}
.dc-btn-danger{background:rgba(244,63,94,.15);color:var(--rose);border:1px solid rgba(244,63,94,.3);}
.dc-btn-danger:hover{background:rgba(244,63,94,.25);border-color:rgba(244,63,94,.5);}

.dc-stats{display:flex;gap:16px;font-size:12px;}
.dc-stat{white-space:nowrap;}

.dash-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:28px;gap:20px;flex-wrap:wrap;}
.dash-header h1{font-size:26px;font-weight:800;margin:0;display:flex;align-items:center;gap:12px;color:var(--text);}
.dash-header p{font-size:14px;color:var(--text-muted);margin:8px 0 0 38px;line-height:1.4;}
.header-actions{display:flex;gap:12px;flex-wrap:wrap;}

@media (max-width: 768px) {
  .dash-header{flex-direction:column;align-items:flex-start;}
  .dash-header h1{font-size:20px;}
  .header-actions{width:100%;}
  .header-actions .dbtn{width:100%;justify-content:center;}
}

.d-alert-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:18px;}
.d-alert-title{font-size:13px;font-weight:700;margin-bottom:2px;}
.dash-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px;margin-bottom:28px;}
.dash-stat-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:20px;position:relative;overflow:hidden;}
.dash-stat-card::before{content:'';position:absolute;top:0;right:0;width:80px;height:80px;border-radius:50%;background:radial-gradient(circle,rgba(64,153,255,.1) 0%,transparent 100%);pointer-events:none;}
.dsc-title{font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:12px;position:relative;z-index:1;}
.dsc-value{font-size:24px;font-weight:800;color:var(--text);margin-bottom:8px;position:relative;z-index:1;}
.dsc-change{font-size:12px;color:var(--emerald);display:flex;align-items:center;gap:4px;}
.dsc-change.negative{color:var(--rose);}

.status-tabs{display:flex;gap:0;border-bottom:2px solid var(--border);margin-bottom:24px;}
.status-tab{flex:1;padding:14px 16px;text-align:center;font-size:13px;font-weight:600;color:var(--text-muted);text-decoration:none;border-bottom:3px solid transparent;transition:all .3s;position:relative;}
.status-tab:hover{color:var(--text);background:var(--surface2);}
.status-tab.active{color:var(--primary);border-bottom-color:var(--primary);}
.status-tab i{margin-right:6px;}

.cc-stat-label,.dc-stat-label{font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--text-muted);}
.cc-stat-value,.dc-stat-value{font-size:13px;color:var(--text);margin-top:2px;word-break:break-word;}
</style>

 <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <div class="container mt-4">
                                <!-- Page Header -->
                                <div class="dash-header">
                                    <div>
                                        <h1><i class="fas fa-people-arrows mr-2"></i><?= __('debtors_management') ?></h1>
                                        <p><?= __('manage_your_debtors_and_track_payments') ?></p>
                                    </div>
                                    <div class="header-actions">
                                        <button type="button" class="dbtn dbtn-primary" data-toggle="modal" data-target="#addDebtorModal">
                                            <i class="fas fa-plus"></i><?= __('add_new_debtor') ?>
                                        </button>
                                    </div>
                                </div>
    
                                <?php if (isset($success_message)): ?>
                                     <div class="d-alert d-alert-warning" style="background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);">
                                        <div class="d-alert-icon" style="background:rgba(16,185,129,.2);color:var(--emerald);"><i class="fas fa-check-circle"></i></div>
                                        <div>
                                            <div class="d-alert-title" style="color:var(--emerald);"><?= __('success') ?></div>
                                            <div style="font-size:13px;color:var(--text);"><?php echo h($success_message); ?></div>
                                        </div>
                                     </div>
                                 <?php endif; ?>
                                 
                                 <?php if (isset($error_message)): ?>
                                      <div class="d-alert d-alert-danger" style="background:rgba(244,63,94,.1);border-color:rgba(244,63,94,.3);">
                                        <div class="d-alert-icon" style="background:rgba(244,63,94,.2);color:var(--rose);"><i class="fas fa-exclamation-circle"></i></div>
                                        <div>
                                            <div class="d-alert-title" style="color:var(--rose);"><?= __('error') ?></div>
                                            <div style="font-size:13px;color:var(--text);"><?php echo h($error_message); ?></div>
                                        </div>
                                     </div>
                                 <?php endif; ?>
                                
                                <!-- Status Toggle Tabs -->
                                <div class="dash-wrap" style="padding:0;">
                                <div class="dash-inner">
                                       <div class="status-tabs">
                                           <a href="debtors.php" class="status-tab <?php echo h($status_filter) === 'active' ? 'active' : ''; ?>">
                                               <i class="fas fa-user-check mr-2"></i><?= __('active_debtors') ?> <span style="margin-left:6px;font-weight:700;"><?php echo $active_count; ?></span>
                                           </a>
                                           <a href="debtors.php?status=inactive" class="status-tab <?php echo h($status_filter) === 'inactive' ? 'active' : ''; ?>">
                                               <i class="fas fa-user-minus mr-2"></i><?= __('inactive_debtors') ?> <span style="margin-left:6px;font-weight:700;"><?php echo $inactive_count; ?></span>
                                           </a>
                                       </div>
                                
                                <!-- Debtors Cards Grid -->
                                <div class="sec-label"><i class="fas fa-people-arrows"></i> <?= __($status_filter . '_debtors') ?></div>
                                
                                <div class="debtor-grid">
                                                    <?php if (!empty($debtors) && count($debtors) > 0): ?>
                                                        <?php foreach ($debtors as $debtor): ?>
                                                            <div class="debtor-card">
                                                                <div class="dc-icon"><i class="fas fa-user-tie"></i></div>
                                                                <div style="min-width:150px;">
                                                                    <div class="dc-label"><?= __('name') ?></div>
                                                                    <div class="dc-name"><?php echo htmlspecialchars($debtor['name']); ?></div>
                                                                </div>
                                                                <div style="min-width:120px;">
                                                                    <div class="dc-label"><?= __('balance') ?></div>
                                                                    <div class="dc-balance"><?php echo number_format($debtor['balance'], 2); ?></div>
                                                                    <div class="dc-currency"><?php echo htmlspecialchars($debtor['currency']); ?></div>
                                                                </div>
                                                                <div style="min-width:110px;">
                                                                    <div class="dc-label"><?= __('status') ?></div>
                                                                    <div class="dc-status <?php echo h($debtor['status']); ?>">
                                                                        <i class="fas <?php echo h($debtor['status']) === 'active' ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                                                        <?= ucfirst(__($debtor['status'])) ?>
                                                                    </div>
                                                                </div>
                                                                <div class="dc-stats">
                                                                    <?php if (!empty($debtor['email'])): ?>
                                                                    <div class="dc-stat">
                                                                        <div class="dc-stat-label"><i class="fas fa-envelope"></i> <?= __('email') ?></div>
                                                                        <div class="dc-stat-value"><?php echo htmlspecialchars($debtor['email']); ?></div>
                                                                    </div>
                                                                    <?php endif; ?>
                                                                    <?php if (!empty($debtor['phone'])): ?>
                                                                    <div class="dc-stat">
                                                                        <div class="dc-stat-label"><i class="fas fa-phone"></i> <?= __('phone') ?></div>
                                                                        <div class="dc-stat-value"><?php echo htmlspecialchars($debtor['phone']); ?></div>
                                                                    </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="dc-actions">
                                                                    <button class="dc-btn dc-btn-primary" data-toggle="modal" data-target="#paymentModal<?php echo h($debtor['id']); ?>" title="<?= __('process_payment') ?>" data-bs-toggle="tooltip" data-placement="top">
                                                                        <i class="fas fa-credit-card"></i>
                                                                        <span><?= __('pay') ?></span>
                                                                    </button>
                                                                    <button class="dc-btn dc-btn-info" data-toggle="modal" data-target="#transactionsModal<?php echo h($debtor['id']); ?>" title="<?= __('view_transactions') ?>" data-bs-toggle="tooltip" data-placement="top">
                                                                        <i class="fas fa-list"></i>
                                                                        <span><?= __('transactions') ?></span>
                                                                    </button>
                                                                    <a href="../api/debtor/print_debtor_statement.php?id=<?php echo h($debtor['id']); ?>" class="dc-btn dc-btn-warning" target="_blank" title="<?= __('print_statement') ?>" data-bs-toggle="tooltip" data-placement="top">
                                                                        <i class="fas fa-print"></i>
                                                                        <span><?= __('print') ?></span>
                                                                    </a>
                                                                    <button class="dc-btn dc-btn-info" data-toggle="modal" data-target="#editDebtorModal<?php echo h($debtor['id']); ?>" title="<?= __('edit_debtor') ?>" data-bs-toggle="tooltip" data-placement="top">
                                                                        <i class="fas fa-edit"></i>
                                                                        <span><?= __('edit') ?></span>
                                                                    </button>
                                                                    <?php if ($isAdmin): ?>
                                                                    <button class="dc-btn dc-btn-danger" onclick="if(confirm('<?= __('are_you_sure') ?>')){ document.querySelector('form.delete-form-<?php echo h($debtor['id']); ?>').submit(); }" title="<?= __('delete_debtor') ?>" data-bs-toggle="tooltip" data-placement="top">
                                                                        <i class="fas fa-trash"></i>
                                                                        <span><?= __('delete') ?></span>
                                                                    </button>
                                                                    <form method="POST" class="d-none delete-form-<?php echo h($debtor['id']); ?>">
                                                                        <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                                                                        <input type="hidden" name="delete_debtor" value="1">
                                                                        <input type="hidden" name="debtor_id" value="<?php echo h($debtor['id']); ?>">
                                                                    </form>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <div style="grid-column:1/-1;text-align:center;padding:40px 20px;">
                                                            <i class="fas fa-inbox" style="font-size:48px;color:var(--text-muted);margin-bottom:16px;display:block;"></i>
                                                            <p style="color:var(--text-muted);font-size:14px;">
                                                                <?php if ($status_filter === 'active'): ?>
                                                                    <?= __("add_new_debtors_to_start_tracking_your_debts") ?>
                                                                <?php else: ?>
                                                                    <?= __("deactivated_debtors_will_appear_here") ?>
                                                                <?php endif; ?>
                                                            </p>
                                                        </div>
                                                    <?php endif; ?>
                                                    </div>
                                                    
                                                    <!-- Pagination -->
                                                    <div class="mt-3 mt-md-4">
                                            <nav aria-label="Page navigation">
                                                <ul class="pagination pagination-sm justify-content-center flex-wrap">
                                                    <?php
                                                    // Previous button
                                                    if ($current_page > 1): ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="?page=<?= $current_page - 1 ?><?= $status_filter === 'inactive' ? '&status=inactive' : '' ?>" aria-label="Previous">
                                                                <span aria-hidden="true">&laquo;</span>
                                                            </a>
                                                        </li>
                                                    <?php else: ?>
                                                        <li class="page-item disabled">
                                                            <a class="page-link" href="#" aria-label="Previous">
                                                                <span aria-hidden="true">&laquo;</span>
                                                            </a>
                                                        </li>
                                                    <?php endif;
                                                    
                                                    // Page numbers
                                                    $start_page = max(1, $current_page - 2);
                                                    $end_page = min($total_pages, $current_page + 2);
                                                    
                                                    if ($start_page > 1): ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="?page=1<?= $status_filter === 'inactive' ? '&status=inactive' : '' ?>">1</a>
                                                        </li>
                                                        <?php if ($start_page > 2): ?>
                                                            <li class="page-item disabled">
                                                                <a class="page-link" href="#">...</a>
                                                            </li>
                                                        <?php endif;
                                                    endif;
                                                    
                                                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                                                        <li class="page-item <?= $i === $current_page ? 'active' : '' ?>">
                                                            <a class="page-link" href="?page=<?= $i ?><?= $status_filter === 'inactive' ? '&status=inactive' : '' ?>"><?= $i ?></a>
                                                        </li>
                                                    <?php endfor;
                                                    
                                                    if ($end_page < $total_pages): 
                                                        if ($end_page < $total_pages - 1): ?>
                                                            <li class="page-item disabled">
                                                                <a class="page-link" href="#">...</a>
                                                            </li>
                                                        <?php endif; ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="?page=<?= $total_pages ?><?= $status_filter === 'inactive' ? '&status=inactive' : '' ?>"><?= $total_pages ?></a>
                                                        </li>
                                                    <?php endif;
                                                    
                                                    // Next button
                                                    if ($current_page < $total_pages): ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="?page=<?= $current_page + 1 ?><?= $status_filter === 'inactive' ? '&status=inactive' : '' ?>" aria-label="Next">
                                                                <span aria-hidden="true">&raquo;</span>
                                                            </a>
                                                        </li>
                                                    <?php else: ?>
                                                        <li class="page-item disabled">
                                                            <a class="page-link" href="#" aria-label="Next">
                                                                <span aria-hidden="true">&raquo;</span>
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </nav>
                                            <div class="text-center mt-2">
                                                <small class="text-muted">
                                                    <?= __('showing') ?> <?= count($debtors) ?> <?= __('of') ?> <?= $total_count ?> <?= __('debtors') ?> |
                                                    <?= __('page') ?> <?= $current_page ?> <?= __('of') ?> <?= $total_pages ?>
                                                </small>
                                                </div>
                                                </div>
                                                </div>
                                                </div>
                                                </div>
                                                </div>
                                                </div>
                                                </div>
                                                </div>
                                                
                                                <!-- Add Debtor Modal -->
                                <div class="modal fade" id="addDebtorModal" tabindex="-1" role="dialog" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                        <div class="modal-content">
                                            
                                            <!-- Header styled like creditor -->
                                            <div class="modal-header bg-gradient-success text-white border-0">
                                                <h5 class="modal-title">
                                                    <i class="feather icon-user-plus mr-2"></i><?= __("add_new_debtor") ?>
                                                </h5>
                                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            
                                            <form method="POST">
                                                <!-- CSRF Protection (if needed like creditor) -->
                                                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                                                
                                                <div class="modal-body">
                                                    <div class="form-row">
                                                        <div class="form-group col-md-12">
                                                            <label for="debtor_name" class="small text-muted mb-1"><?= __("name") ?> *</label>
                                                            <div class="input-group">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="feather icon-user"></i></span>
                                                                </div>
                                                                <input type="text" class="form-control" id="debtor_name" name="name" placeholder="<?= __("enter_name") ?>" required>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="form-row">
                                                        <div class="form-group col-md-6">
                                                            <label for="debtor_email" class="small text-muted mb-1"><?= __("email") ?></label>
                                                            <div class="input-group">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="feather icon-mail"></i></span>
                                                                </div>
                                                                <input type="email" class="form-control" id="debtor_email" name="email" placeholder="<?= __("enter_email") ?>">
                                                            </div>
                                                        </div>
                                                        <div class="form-group col-md-6">
                                                            <label for="debtor_phone" class="small text-muted mb-1"><?= __("phone") ?></label>
                                                            <div class="input-group">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="feather icon-phone"></i></span>
                                                                </div>
                                                                <input type="tel" class="form-control" id="debtor_phone" name="phone" placeholder="<?= __("enter_phone") ?>">
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="form-group">
                                                        <label for="debtor_address" class="small text-muted mb-1"><?= __("address") ?></label>
                                                        <div class="input-group">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text"><i class="feather icon-map-pin"></i></span>
                                                            </div>
                                                            <textarea class="form-control" id="debtor_address" name="address" rows="2" placeholder="<?= __("enter_address") ?>"></textarea>
                                                        </div>
                                                    </div>

                                                    <div class="form-row">
                                                        <div class="form-group col-md-6">
                                                            <label for="debtor_balance" class="small text-muted mb-1"><?= __("initial_balance") ?> *</label>
                                                            <div class="input-group">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="feather icon-dollar-sign"></i></span>
                                                                </div>
                                                                <input type="number" class="form-control" id="debtor_balance" name="balance" step="0.01" placeholder="0.00" required>
                                                            </div>
                                                        </div>
                                                        <div class="form-group col-md-6">
                                                            <label for="debtor_currency" class="small text-muted mb-1"><?= __("currency") ?> *</label>
                                                            <div class="input-group">
                                                                <div class="input-group-prepend">
                                                                    <span class="input-group-text"><i class="feather icon-credit-card"></i></span>
                                                                </div>
                                                                <select class="form-control" id="debtor_currency" name="currency" required>
                                                                    <option value=""><?= __("select_currency") ?></option>
                                                                    <option value="USD"><?= __("usd") ?></option>
                                                                    <option value="AFS"><?= __("afs") ?></option>
                                                                    <option value="EUR"><?= __("eur") ?></option>
                                                                    <option value="DARHAM"><?= __("darham") ?></option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="form-group">
                                                        <label for="debtor_main_account" class="small text-muted mb-1"><?= __("main_account") ?> *</label>
                                                        <div class="input-group">
                                                            <div class="input-group-prepend">
                                                                <span class="input-group-text"><i class="feather icon-briefcase"></i></span>
                                                            </div>
                                                            <select class="form-control" id="debtor_main_account" name="main_account_id" required>
                                                                <option value=""><?= __("select_main_account") ?></option>
                                                                <?php foreach ($main_accounts as $account): ?>
                                                                    <option value="<?php echo h($account['id']); ?>"><?php echo h($account['name']); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <small class="form-text text-muted"><?= __('main_account_debit_notice') ?></small>
                                                    </div>

                                                    <div class="custom-control custom-switch">
                                                        <input type="checkbox" class="custom-control-input" id="skipDeduction" name="skip_deduction">
                                                        <label class="custom-control-label small" for="skipDeduction">
                                                            <?= __('skip_deduction_from_main_account') ?>
                                                        </label>
                                                        <small class="form-text text-muted"><?= __('skip_deduction_notice') ?></small>
                                                    </div>

                                                    <div class="form-group mt-3">
                                                        <label for="debtor_agreement" class="small text-muted mb-1"><?= __('agreement_terms') ?></label>
                                                        <textarea class="form-control" id="debtor_agreement" name="agreement_terms" rows="3" placeholder="<?= __('enter_agreement_terms_placeholder') ?>"></textarea>
                                                    </div>
                                                </div>

                                                <!-- Footer styled like creditor -->
                                                <div class="modal-footer bg-light border-0">
                                                    <button type="button" class="btn btn-link" data-dismiss="modal">
                                                        <i class="feather icon-x mr-2"></i><?= __("cancel") ?>
                                                    </button>
                                                    <button type="submit" name="add_debtor" class="btn btn-success">
                                                        <i class="feather icon-check-circle mr-2"></i><?= __("add_debtor") ?>
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <?php foreach ($debtors as $debtor): ?>
                                    <!-- Payment Modal -->
                                    <div class="modal fade" id="paymentModal<?php echo h($debtor['id']); ?>" tabindex="-1" role="dialog" aria-labelledby="paymentModalLabel<?php echo h($debtor['id']); ?>" aria-hidden="true">
                                        <div class="modal-dialog" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header bg-gradient-primary text-white border-0">
                                                    <h5 class="modal-title" id="paymentModalLabel<?php echo h($debtor['id']); ?>"><?= __('process_payment') ?></h5>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <form method="POST">
                                                     <!-- CSRF Protection -->
                                                     <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                                                     
                                                     <div class="modal-body">
                                                         <input type="hidden" name="pay" value="1">
                                                         <input type="hidden" name="debtor_id" value="<?php echo h($debtor['id']); ?>">
                                                         <input type="hidden" name="debtor_currency" value="<?php echo h($debtor['currency']); ?>">
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('debtor_name') ?></label>
                                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($debtor['name']); ?>" readonly>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('current_balance') ?></label>
                                                            <input type="text" class="form-control" value="<?php echo number_format($debtor['balance'], 2) . ' ' . $debtor['currency']; ?>" readonly>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('payment_amount') ?></label>
                                                            <input type="number" class="form-control" name="amount" step="0.00001" required>
                                                        </div>

                                                        <!-- Payment Currency -->
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('payment_currency') ?></label>
                                                            <select class="form-control" name="currency" required onchange="checkCurrency(this, '<?php echo h($debtor['currency']); ?>', '<?php echo h($debtor['id']); ?>')">
                                                                <option value="USD" <?php echo h($debtor['currency']) == 'USD' ? 'selected' : ''; ?>><?= __('usd') ?></option>
                                                                <option value="AFS" <?php echo h($debtor['currency']) == 'AFS' ? 'selected' : ''; ?>><?= __('afs') ?></option>
                                                                <option value="EUR" <?php echo h($debtor['currency']) == 'EUR' ? 'selected' : ''; ?>><?= __('eur') ?></option>
                                                                <option value="DARHAM" <?php echo h($debtor['currency']) == 'DARHAM' ? 'selected' : ''; ?>><?= __('darham') ?></option>
                                                            </select>
                                                        </div>
                                                        
                                                        <!-- Exchange Rate Field - Initially Hidden -->
                                                        <div class="form-group" id="exchangeRateDiv<?php echo h($debtor['id']); ?>" style="display: none;">
                                                            <label class="form-label"><?= __('exchange_rate') ?> (1 <span id="selectedCurrency<?php echo h($debtor['id']); ?>"><?php echo h($debtor['currency']); ?></span> = ? <span id="debtorCurrency<?php echo h($debtor['id']); ?>"><?php echo h($debtor['currency']); ?></span>)</label>
                                                            <input type="number" class="form-control" name="exchange_rate" id="exchangeRate<?php echo h($debtor['id']); ?>" step="0.000001" placeholder="<?= __('enter_exchange_rate') ?>">
                                                            <small class="form-text text-muted"><?= __('enter_the_exchange_rate_to_convert_from_payment_currency_to_debtor_s_currency') ?></small>
                                                        </div>

                                                        <!-- Payment Date -->
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('payment_date') ?></label>
                                                            <input type="date" class="form-control" name="payment_date" required>
                                                        </div>
                                                        
                                                        <!-- Description -->
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('description') ?></label>
                                                            <input type="text" class="form-control" name="description">
                                                        </div>
                                                        
                                                        <!-- Paid To -->
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('paid_to') ?></label>   
                                                            <select class="form-control" name="paid_to" required>
                                                                <option value=""><?= __('select_main_account') ?></option>
                                                                <?php foreach ($main_accounts as $account): ?>
                                                                    <option value="<?php echo h($account['id']); ?>"><?php echo h($account['name']); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer bg-light border-0">
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                                                        <button type="submit" name="pay" class="btn btn-primary"><?= __('process_payment') ?></button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Transactions Modal -->
                                    <div class="modal fade" id="transactionsModal<?php echo h($debtor['id']); ?>" tabindex="-1" role="dialog" aria-hidden="true">
                                        <div class="modal-dialog modal-lg" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header bg-gradient-info text-white border-0">
                                                    <h5 class="modal-title"><?= __('transactions') ?> - <?php echo htmlspecialchars($debtor['name']); ?></h5>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="table-responsive">
                                                        <table class="table table-bordered table-striped">
                                                            <thead>
                                                                <tr>
                                                                    <th><?= __('date') ?></th>
                                                                    <th><?= __('amount') ?></th>
                                                                    <th><?= __('type') ?></th>
                                                                    <th><?= __('description') ?></th>
                                                                    <th><?= __('receipt') ?></th>
                                                                    <th><?= __('actions') ?></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php
                                                                // Fetch transactions for this debtor
                                                                $transStmt = $pdo->prepare("SELECT * FROM debtor_transactions WHERE debtor_id = ? AND tenant_id = ? AND branch_id = ? ORDER BY payment_date DESC");
                                                                $transStmt->bindParam(1, $debtor['id'], PDO::PARAM_INT);
                                                                $transStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                                                                $transStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                                                                $transStmt->execute();
                                                                $transResult = $transStmt->fetchAll();

                                                                if (count($transResult) > 0) {
                                                                    foreach ($transResult as $transaction) {
                                                                        echo '<tr>';
                                                                        echo '<td>' . date('M d, Y H:i:s', strtotime($transaction['created_at'])) . '</td>';
                                                                        echo '<td>' . number_format($transaction['amount'], 2) . ' ' . $transaction['currency'] . '</td>';
                                                                        echo '<td>' . ($transaction['transaction_type'] == 'credit' ? '<span class="badge-success">Payment</span>' : '<span class="badge-danger">Debt</span>') . '</td>';
                                                                        echo '<td>' . htmlspecialchars($transaction['description']) . '</td>';
                                                                        echo '<td>' . htmlspecialchars($transaction['reference_number']) . '</td>';
                                                                        echo '<td>';
                                                                        echo '<div class="btn-group" role="group">';
                                                                        // Edit button
                                                                        echo '<button type="button" class="btn btn-warning btn-sm mr-1 edit-transaction-btn" 
                                                                            data-transaction-id="' . $transaction['id'] . '"
                                                                            data-debtor-id="' . $debtor['id'] . '"
                                                                            data-amount="' . $transaction['amount'] . '"
                                                                            data-currency="' . $transaction['currency'] . '"
                                                                            data-description="' . htmlspecialchars($transaction['description'], ENT_QUOTES) . '"
                                                                            data-payment-date="' . date('Y-m-d', strtotime($transaction['payment_date'])) . '"
                                                                            data-created-at="' . date('Y-m-d\TH:i', strtotime($transaction['created_at'])) . '">
                                                                            <i class="feather icon-edit-2"></i> ' . __('edit') . '
                                                                        </button>';
                                                                        echo '<button class="btn btn-info btn-sm mr-1" title="Print Receipt"
                                                                        onclick="printDebtorReceipt('.$transaction['id'].')">
                                                                        <i class="feather icon-printer"></i>
                                                                        </button>';
                                                                         // Delete button (admin only) with toast notification
                                                                         if ($isAdmin) {
                                                                             echo '<button type="button" class="btn btn-danger btn-sm delete-transaction-btn" 
                                                                                 data-transaction-id="' . $transaction['id'] . '"
                                                                                 data-debtor-id="' . $debtor['id'] . '"
                                                                                 data-amount="' . $transaction['amount'] . '"
                                                                                 data-currency="' . $transaction['currency'] . '">
                                                                                 <i class="feather icon-trash"></i> ' . __('delete') . '
                                                                             </button>';
                                                                         }
                                                                        echo '</div>';
                                                                        echo '</td>';
                                                                        echo '</tr>';
                                                                    }
                                                                } else {
                                                                    echo '<tr><td colspan="6" class="text-center">' . __('no_transactions_found') . '</td></tr>';
                                                                }
                                                                ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light border-0">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Edit Debtor Modal -->
                                    <div class="modal fade" id="editDebtorModal<?php echo h($debtor['id']); ?>" tabindex="-1" role="dialog" aria-hidden="true">
                                        <div class="modal-dialog" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header bg-gradient-warning text-white border-0">
                                                    <h5 class="modal-title"><?= __('edit_debtor') ?> - <?php echo htmlspecialchars($debtor['name']); ?></h5>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <form method="POST">
                                                     <!-- CSRF Protection -->
                                                     <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                                                     
                                                     <div class="modal-body">
                                                         <input type="hidden" name="edit_debtor" value="1">
                                                         <input type="hidden" name="debtor_id" value="<?php echo h($debtor['id']); ?>">
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('name') ?> *</label>
                                                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($debtor['name']); ?>" required>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('email') ?></label>
                                                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($debtor['email']); ?>">
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('phone') ?></label>
                                                            <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($debtor['phone']); ?>">
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('address') ?></label>
                                                            <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($debtor['address']); ?></textarea>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('balance') ?> *</label>
                                                                <input type="number" class="form-control" name="balance" step="0.01" value="<?php echo h($debtor['balance']); ?>" required>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('currency') ?> *</label>
                                                            <select class="form-control" name="currency" required>
                                                                <option value="USD" <?php echo h($debtor['currency']) == 'USD' ? 'selected' : ''; ?>><?= __('usd') ?></option>
                                                                <option value="AFS" <?php echo h($debtor['currency']) == 'AFS' ? 'selected' : ''; ?>><?= __('afs') ?></option>
                                                                <option value="EUR" <?php echo h($debtor['currency']) == 'EUR' ? 'selected' : ''; ?>><?= __('eur') ?></option>
                                                                <option value="DARHAM" <?php echo h($debtor['currency']) == 'DARHAM' ? 'selected' : ''; ?>><?= __('darham') ?></option>
                                                            </select>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('main_account') ?> *</label>
                                                            <select class="form-control" name="main_account_id" required>
                                                                <option value=""><?= __('select_main_account') ?></option>
                                                                <?php foreach ($main_accounts as $account): ?>
                                                                    <option value="<?php echo h($account['id']); ?>" <?php echo isset($debtor['main_account_id']) && $debtor['main_account_id'] == $account['id'] ? 'selected' : ''; ?>>
                                                                        <?php echo h($account['name']); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('agreement_terms') ?></label>
                                                            <textarea class="form-control" name="agreement_terms" rows="4"><?php echo htmlspecialchars($debtor['agreement_terms'] ?? ''); ?></textarea>
                                                            <small class="text-muted"><?= __('these_terms_will_appear_on_the_printed_agreement') ?></small>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer bg-light border-0">
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                                                        <button type="submit" name="edit_debtor" class="btn btn-warning"><?= __('update_debtor') ?></button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <!-- [ Main Content ] end -->
                        </div>
                    </div>
                </div>
            
        </div>
    </div>



<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

<!-- Required Js -->

    <script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<!-- SweetAlert2 JS -->
<script src="../assets/plugins/sweetalert2/sweetalert2.min.js"></script>

<!-- Custom JS for Debtors Page -->

<script src="../js/debtor/debtors-interactions.js"></script>
<script src="../js/debtor/currency-check.js"></script>
<script src="../js/debtor/form-protection.js"></script>

<!-- Toast Container -->
<div class="toast-container"></div>

<!-- Edit Transaction Modal - Moved to root level -->
<div class="modal fade" id="editTransactionModal" tabindex="-1" role="dialog" aria-labelledby="editTransactionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-gradient-warning text-white border-0">
                <h5 class="modal-title" id="editTransactionModalLabel">
                    <i class="feather icon-edit-2 mr-2"></i><?= __('edit_transaction') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editTransactionForm">
                    <input type="hidden" id="edit_transaction_id" name="transaction_id">
                    <input type="hidden" id="edit_debtor_id" name="debtor_id">
                    <input type="hidden" id="edit_original_amount" name="original_amount">
                    <input type="hidden" id="edit_currency" name="currency">
                    
                    <div class="form-group">
                        <div class="d-flex align-items-center mb-2">
                            
                            <label for="edit_amount" class="mb-0"><?= __('amount') ?></label>
                        </div>
                        <input type="number" class="form-control" id="edit_amount" name="amount" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <div class="d-flex align-items-center mb-2">
                            
                            <label for="edit_description" class="mb-0"><?= __('description') ?></label>
                        </div>
                        <input type="text" class="form-control" id="edit_description" name="description" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="d-flex align-items-center mb-2">
                                    
                                    <label for="edit_payment_date" class="mb-0"><?= __('payment_date') ?></label>
                                </div>
                                <input type="date" class="form-control" id="edit_payment_date" name="payment_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <div class="d-flex align-items-center mb-2">
                                    
                                    <label for="edit_created_at_time" class="mb-0"><?= __('transaction_time') ?></label>
                                </div>
                                <input type="time" class="form-control" id="edit_created_at_time" name="created_at_time">
                                <small class="form-text text-muted mt-1">
                                    <i class="feather icon-info mr-1"></i><?= __('time_the_transaction_was_created') ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <input type="hidden" id="edit_created_at_date" name="created_at_date">
                   
                </form>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                </button>
                <button type="button" class="btn btn-primary" id="saveTransactionBtn">
                    <i class="feather icon-save mr-1"></i><?= __('save_changes') ?>
                </button>
            </div>
        </div>
    </div>
</div>

    <style>
        /* Enhanced custom styles for better layout and design */
        .page-header.card {
            background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
            color: #ffffff;
            border: none;
            margin-bottom: 20px;
            padding: 20px !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-radius: 10px;
        }

        .page-header.card .row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header.card h5 {
            color: #ffffff;
            margin: 0;
            font-weight: 600;
        }

        .page-header.card .text-end {
            text-align: right;
        }

        .page-header.card .btn {
            background: rgba(255,255,255,0.2);
            color: #ffffff;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 25px;
            transition: all 0.3s ease;
        }

        .page-header.card .btn:hover {
            background: rgba(255,255,255,0.3);
            border-color: rgba(255,255,255,0.5);
            transform: translateY(-1px);
        }

        .card {
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            border: none;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 10px 0 0;
            padding: 1rem 1.5rem;
            border: none;
        }

        .card-header h5 {
            margin: 0;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .progress {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
        }

        .progress-bar {
            transition: width 0.6s ease;
        }

        .badge {
            font-size: 0.85em;
            padding: 0.5em 0.75em;
            border-radius: 20px;
            font-weight: 500;
        }

        .badge-success {
            background-color: #28a745;
        }

        .bg-gradient-success {
            background: linear-gradient(135deg, #28a745 0%, #2ed8b6 100%);
        }

        .bg-gradient-primary {
            background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        }

        .bg-gradient-info {
            background: linear-gradient(135deg, #17a2b8 0%, #2ed8b6 100%);
        }

        .bg-gradient-warning {
            background: linear-gradient(135deg, #ffc107 0%, #2ed8b6 100%);
        }

        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }

        .badge-info {
            background-color: #17a2b8;
        }

        .table-responsive {
            border-radius: 10px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table-responsive table {
            min-width: 100%;
            table-layout: auto;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
            padding: 1rem;
        }

        .table tbody tr:hover {
            background-color: #f1f3f4;
        }

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid #ced4da;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            padding: 0.75rem;
        }

        .form-control:focus {
            border-color: #4099ff;
            box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
        }

        .btn-primary {
            background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
            border: none;
            border-radius: 25px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(64, 153, 255, 0.3);
        }

        .btn-secondary {
            border-radius: 25px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .alert {
            border-radius: 10px;
            border: none;
            padding: 1rem 1.5rem;
        }

        .alert-info {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            color: #0c5460;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }

        #estimated_cost {
            color: #28a745;
            font-weight: bold;
        }

        .h2 {
            font-size: 2.5rem;
        }

        .h4 {
            font-size: 1.5rem;
        }

        .h5 {
            font-size: 1.25rem;
        }

        .h6 {
            font-size: 1rem;
        }
    </style>

</body>
</html>
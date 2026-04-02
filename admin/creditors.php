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
include '../api/creditor/creditor_handler.php';

// Check if user is admin
$isAdmin = $_SESSION['role'] === 'admin';
?>
 
<?php
// Fetch creditors list
$status_filter = isset($_GET['status']) && $_GET['status'] === 'inactive' ? 'inactive' : 'active';

try {
     // Get total count for current status
     $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM creditors WHERE status = ? AND tenant_id = ? AND branch_id = ?");
     $countStmt->execute([$status_filter, $tenant_id, $branch_id]);
     $total_count = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
     
     // Get counts for both active and inactive creditors
     $activeCountStmt = $pdo->prepare("SELECT COUNT(*) as count FROM creditors WHERE status = 'active' AND tenant_id = ? AND branch_id = ?");
     $activeCountStmt->execute([$tenant_id, $branch_id]);
     $active_count = $activeCountStmt->fetch(PDO::FETCH_ASSOC)['count'];
     
     $inactiveCountStmt = $pdo->prepare("SELECT COUNT(*) as count FROM creditors WHERE status = 'inactive' AND tenant_id = ? AND branch_id = ?");
     $inactiveCountStmt->execute([$tenant_id, $branch_id]);
     $inactive_count = $inactiveCountStmt->fetch(PDO::FETCH_ASSOC)['count'];
     
     // Pagination
     $items_per_page = 10;
     $current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
     $offset = ($current_page - 1) * $items_per_page;
     $total_pages = ceil($total_count / $items_per_page);
     
     // Fetch creditors with pagination
     $stmt = $pdo->prepare("SELECT * FROM creditors WHERE status = ? AND tenant_id = ? AND branch_id = ? ORDER BY name ASC LIMIT ? OFFSET ?");
     $stmt->execute([$status_filter, $tenant_id, $branch_id, $items_per_page, $offset]);
     $creditors = $stmt->fetchAll(PDO::FETCH_ASSOC);
     
     // Fetch total credits by currency
     $currencyStmt = $pdo->prepare("SELECT currency, SUM(balance) as total FROM creditors WHERE status = ? AND tenant_id = ? AND branch_id = ? GROUP BY currency");
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
     error_log("Error fetching creditors: " . $e->getMessage());
     $creditors = [];
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
<link rel="stylesheet" href="../css/creditors/styles.css">

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

/* Creditor cards grid */
.creditor-grid{display:flex;flex-direction:column;gap:12px;margin-bottom:22px;}
.creditor-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:16px;position:relative;overflow:hidden;transition:transform .2s,border-color .2s,box-shadow .2s;cursor:pointer;display:grid;grid-template-columns:auto 1fr auto auto auto 1fr auto;align-items:center;gap:16px;}
.creditor-card:hover{transform:translateY(-2px);border-color:rgba(64,153,255,.4);box-shadow:0 8px 30px rgba(64,153,255,.12);}
.creditor-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--grad);}

/* Mobile Responsive */
@media (max-width: 768px) {
  .creditor-card{
    grid-template-columns: auto 1fr;
    gap: 12px;
    padding: 14px;
  }
  .creditor-card > div:nth-child(2),
  .creditor-card > div:nth-child(3),
  .creditor-card > div:nth-child(4),
  .creditor-card > div:nth-child(5),
  .creditor-card > div:nth-child(6) {
    grid-column: 1 / -1;
  }
  .cc-actions {
    grid-column: 1 / -1;
    margin-top: 8px;
    justify-content: flex-start;
  }
  .cc-stats {
    grid-column: 1 / -1;
    flex-wrap: wrap;
    gap: 12px;
  }
}

@media (max-width: 480px) {
  .creditor-card{
    grid-template-columns: 1fr;
    gap: 10px;
    padding: 12px;
  }
  .creditor-card > div {
    grid-column: 1 / -1;
  }
  .cc-icon {
    width: 32px;
    height: 32px;
    font-size: 14px;
  }
  .cc-label {
    font-size: 9px;
  }
  .cc-name {
    font-size: 13px;
  }
  .cc-balance {
    font-size: 14px;
  }
  .cc-btn {
    padding: 6px 10px;
    font-size: 11px;
  }
  .cc-btn span {
    display: none;
  }
  .cc-btn i {
    margin-right: 0;
  }
}
.cc-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;background:rgba(64,153,255,.15);color:var(--sky);flex-shrink:0;}
.cc-label{font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px;}
.cc-name{font-size:14px;font-weight:700;color:var(--text);word-break:break-word;}
.cc-balance{font-size:16px;font-weight:800;font-family:'JetBrains Mono',monospace;letter-spacing:-.5px;white-space:nowrap;}
.cc-currency{font-size:12px;color:var(--text-muted);font-weight:600;white-space:nowrap;}
.cc-status{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;padding:4px 12px;border-radius:20px;white-space:nowrap;}
.cc-status.active{background:rgba(16,185,129,.15);color:var(--emerald);}
.cc-status.inactive{background:rgba(244,63,94,.15);color:var(--rose);}
.cc-actions{display:flex;gap:6px;flex-wrap:wrap;flex-shrink:0;align-items:center;}
.cc-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:7px 12px;border-radius:8px;font-size:12px;font-weight:600;border:none;cursor:pointer;transition:all .2s;font-family:inherit;flex-shrink:0;white-space:nowrap;}
.cc-btn span{display:inline-block;}
.cc-btn-primary{background:var(--grad);color:#fff;}
.cc-btn-primary:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(64,153,255,.3);}
.cc-btn-info{background:rgba(14,165,233,.15);color:var(--sky);border:1px solid rgba(14,165,233,.3);}
.cc-btn-info:hover{background:rgba(14,165,233,.25);border-color:rgba(14,165,233,.5);}
.cc-btn-warning{background:rgba(245,158,11,.15);color:var(--amber);border:1px solid rgba(245,158,11,.3);}
.cc-btn-warning:hover{background:rgba(245,158,11,.25);border-color:rgba(245,158,11,.5);}
.cc-btn-danger{background:rgba(244,63,94,.15);color:var(--rose);border:1px solid rgba(244,63,94,.3);}
.cc-btn-danger:hover{background:rgba(244,63,94,.25);border-color:rgba(244,63,94,.5);}

.cc-stats{display:flex;gap:16px;font-size:12px;}
.cc-stat{white-space:nowrap;}
.cc-stat-label{color:var(--text-muted);font-size:11px;margin-bottom:2px;display:flex;align-items:center;gap:5px;}
.cc-stat-value{font-weight:600;color:var(--text);font-size:12px;overflow:hidden;text-overflow:ellipsis;max-width:150px;}

.status-tabs{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;}
.status-tab{padding:8px 16px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;border:1px solid var(--border);background:var(--surface2);color:var(--text-muted);transition:all .2s;flex:1;min-width:150px;text-align:center;}
.status-tab.active{background:var(--grad);color:#fff;border-color:transparent;}

@media (max-width: 480px) {
  .status-tabs {
    gap: 6px;
    margin-bottom: 16px;
  }
  .status-tab {
    padding: 6px 12px;
    font-size: 12px;
    min-width: auto;
    flex: 1;
  }
}

.dash-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:26px;flex-wrap:wrap;gap:16px;}
.dash-header h1{font-size:24px;font-weight:800;letter-spacing:-.5px;background:var(--grad);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.dash-header p{color:var(--text-muted);font-size:14px;margin-top:3px;}
.header-actions{display:flex;gap:10px;flex-wrap:wrap;}

@media (max-width: 768px) {
  .dash-header {
    flex-direction: column;
    align-items: flex-start;
    margin-bottom: 20px;
    gap: 12px;
  }
  .dash-header h1 {
    font-size: 20px;
  }
  .dash-header p {
    font-size: 13px;
  }
  .header-actions {
    width: 100%;
  }
  .dbtn {
    font-size: 12px;
    padding: 8px 14px;
  }
}

@media (max-width: 480px) {
  .dash-header h1 {
    font-size: 18px;
  }
  .dash-header {
    gap: 10px;
    margin-bottom: 16px;
  }
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

.nav-pills .nav-link {
    border-radius: 25px;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    transition: all 0.3s ease;
    border: 1px solid transparent;
}

.nav-pills .nav-link.active {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    color: white;
    border: none;
    box-shadow: 0 4px 12px rgba(64, 153, 255, 0.3);
}

.nav-pills .nav-link:hover {
    background-color: #f8f9fa;
    border-color: #dee2e6;
}

.bg-gradient-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #007bff 0%, #6610f2 100%);
}

.bg-gradient-info {
    background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
}

.bg-gradient-warning {
    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
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
<!-- Add this right before the closing </body> tag -->
<!-- Toast Container -->
<div class="toast-container"></div>

<!-- Toast JavaScript -->
<script src="../js/creditor/toast.js"></script>
<script>
    // Show toasts if there are any messages
    <?php if (isset($success_message)): ?>
        toast.show('<?php echo addslashes($success_message); ?>', 'success');
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        toast.show('<?php echo addslashes($error_message); ?>', 'error');
    <?php endif; ?>
</script>


                <!-- [ Main Content ] start -->
                <div class="pcoded-main-container">
                    <div class="main-body">
                            <div class="container mt-4">
                                <!-- Page Header -->
                                <div class="dash-header">
                                    <div>
                                        <h1><i class="fas fa-handshake mr-2"></i><?= __('creditors_management') ?></h1>
                                        <p><?= __('manage_your_creditors_and_track_payments') ?></p>
                                    </div>
                                    <div class="header-actions">
                                        <button type="button" class="dbtn dbtn-primary" data-toggle="modal" data-target="#addCreditorModal">
                                            <i class="fas fa-plus"></i><?= __('add_new_creditor') ?>
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
                                     <div class="d-alert d-alert-danger">
                                        <div class="d-alert-icon"><i class="fas fa-exclamation-circle"></i></div>
                                        <div>
                                            <div class="d-alert-title"><?= __('error') ?></div>
                                            <div style="font-size:13px;color:var(--text);"><?php echo h($error_message); ?></div>
                                        </div>
                                     </div>
                                 <?php endif; ?>
                                
                                <!-- Status Toggle Tabs -->
                                <div class="dash-wrap" style="padding:0;">
                                    <div class="dash-inner">
                                       <div class="status-tabs">
                                           <a href="creditors.php" class="status-tab <?php echo h($status_filter) === 'active' ? 'active' : ''; ?>">
                                               <i class="fas fa-user-check mr-2"></i><?= __('active_creditors') ?> <span style="margin-left:6px;font-weight:700;"><?php echo $active_count; ?></span>
                                           </a>
                                           <a href="creditors.php?status=inactive" class="status-tab <?php echo h($status_filter) === 'inactive' ? 'active' : ''; ?>">
                                               <i class="fas fa-user-minus mr-2"></i><?= __('inactive_creditors') ?> <span style="margin-left:6px;font-weight:700;"><?php echo $inactive_count; ?></span>
                                           </a>
                                       </div>
                                
                                        <!-- Creditors Cards Grid -->
                                        <div class="sec-label"><i class="fas fa-building"></i> <?= __($status_filter . '_creditors') ?></div>
                                    
                                            <div class="creditor-grid">
                                            <?php if (count($creditors) > 0): ?>
                                                <?php foreach ($creditors as $creditor): ?>
                                                <div class="creditor-card">
                                                    <div class="cc-icon"><i class="fas fa-handshake"></i></div>
                                                    <div style="min-width:150px;">
                                                        <div class="cc-label"><?= __('name') ?></div>
                                                        <div class="cc-name"><?php echo htmlspecialchars($creditor['name']); ?></div>
                                                    </div>
                                                    <div style="min-width:120px;">
                                                        <div class="cc-label"><?= __('balance') ?></div>
                                                        <div class="cc-balance"><?php echo number_format($creditor['balance'], 2); ?></div>
                                                        <div class="cc-currency"><?php echo htmlspecialchars($creditor['currency']); ?></div>
                                                    </div>
                                                    <div style="min-width:110px;">
                                                        <div class="cc-label"><?= __('status') ?></div>
                                                        <div class="cc-status <?php echo h($creditor['status']); ?>">
                                                            <i class="fas <?php echo h($creditor['status']) === 'active' ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                                            <?= ucfirst(__($creditor['status'])) ?>
                                                        </div>
                                                    </div>
                                                    <div class="cc-stats">
                                                        <?php if (!empty($creditor['email'])): ?>
                                                        <div class="cc-stat">
                                                            <div class="cc-stat-label"><i class="fas fa-envelope"></i> <?= __('email') ?></div>
                                                            <div class="cc-stat-value"><?php echo htmlspecialchars($creditor['email']); ?></div>
                                                        </div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($creditor['phone'])): ?>
                                                        <div class="cc-stat">
                                                            <div class="cc-stat-label"><i class="fas fa-phone"></i> <?= __('phone') ?></div>
                                                            <div class="cc-stat-value"><?php echo htmlspecialchars($creditor['phone']); ?></div>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="cc-actions">
                                                        <button class="cc-btn cc-btn-primary" data-toggle="modal" data-target="#paymentModal_<?php echo h($creditor['id']); ?>" title="<?= __('process_payment') ?>" data-bs-toggle="tooltip" data-placement="top">
                                                            <i class="fas fa-credit-card"></i>
                                                            <span><?= __('pay') ?></span>
                                                        </button>
                                                        <button class="cc-btn cc-btn-info" data-toggle="modal" data-target="#transactionsModal_<?php echo h($creditor['id']); ?>" title="<?= __('view_transactions') ?>" data-bs-toggle="tooltip" data-placement="top">
                                                            <i class="fas fa-list"></i>
                                                            <span><?= __('transactions') ?></span>
                                                        </button>
                                                        <a href="../api/creditor/print_creditor_statement.php?id=<?php echo h($creditor['id']); ?>" class="cc-btn cc-btn-warning" target="_blank" title="<?= __('print_statement') ?>" data-bs-toggle="tooltip" data-placement="top">
                                                            <i class="fas fa-print"></i>
                                                            <span><?= __('print') ?></span>
                                                        </a>
                                                        <button class="cc-btn cc-btn-info" data-toggle="modal" data-target="#editCreditorModal_<?php echo h($creditor['id']); ?>" title="<?= __('edit_creditor') ?>" data-bs-toggle="tooltip" data-placement="top">
                                                            <i class="fas fa-edit"></i>
                                                            <span><?= __('edit') ?></span>
                                                        </button>
                                                        <?php if ($isAdmin): ?>
                                                        <button class="cc-btn cc-btn-danger" data-toggle="modal" data-target="#deleteCreditorModal_<?php echo h($creditor['id']); ?>" title="<?= __('delete_creditor') ?>" data-bs-toggle="tooltip" data-placement="top">
                                                            <i class="fas fa-trash"></i>
                                                            <span><?= __('delete') ?></span>
                                                        </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div style="grid-column:1/-1;text-align:center;padding:40px 20px;">
                                                    <i class="fas fa-inbox" style="font-size:48px;color:var(--text-muted);margin-bottom:16px;display:block;"></i>
                                                    <p style="color:var(--text-muted);font-size:14px;">
                                                        <?php if ($status_filter === 'active'): ?>
                                                            <?= __("add_new_creditors_to_start_tracking_your_credits") ?>
                                                        <?php else: ?>
                                                            <?= __("deactivated_creditors_will_appear_here") ?>
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
                                                                    <span aria-hidden="true">«</span>
                                                                </a>
                                                            </li>
                                                        <?php else: ?>
                                                            <li class="page-item disabled">
                                                                <a class="page-link" href="#" aria-label="Previous">
                                                                    <span aria-hidden="true">«</span>
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
                                                                    <span aria-hidden="true">»</span>
                                                                </a>
                                                            </li>
                                                        <?php else: ?>
                                                            <li class="page-item disabled">
                                                                <a class="page-link" href="#" aria-label="Next">
                                                                    <span aria-hidden="true">»</span>
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </nav>
                                                <div class="text-center mt-2">
                                                    <small class="text-muted">
                                                        <?= __('showing') ?> <?= count($creditors) ?> <?= __('of') ?> <?= $total_count ?> <?= __('creditors') ?> |
                                                        <?= __('page') ?> <?= $current_page ?> <?= __('of') ?> <?= $total_pages ?>
                                                    </small>
                                                </div>
                                            </div>
                                    </div>
                                </div>
                            </div>                      
                        
                    </div>
                </div>
                                            
                                            <!-- Add Creditor Modal -->
    <div class="modal fade" id="addCreditorModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="feather icon-user-plus mr-2"></i><?= __("add_new_creditor") ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                    
                    <div class="modal-body">
                        <div class="form-row">
                            <div class="form-group col-md-12">
                                <label class="small text-muted mb-1"><?= __("name") ?> *</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="feather icon-user"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="name" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label class="small text-muted mb-1"><?= __("email") ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="feather icon-mail"></i></span>
                                    </div>
                                    <input type="email" class="form-control" name="email">
                                </div>
                            </div>
                            <div class="form-group col-md-6">
                                <label class="small text-muted mb-1"><?= __("phone") ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="feather icon-phone"></i></span>
                                    </div>
                                    <input type="tel" class="form-control" name="phone">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="small text-muted mb-1"><?= __("address") ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="feather icon-map-pin"></i></span>
                                </div>
                                <textarea class="form-control" name="address" rows="2"></textarea>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label class="small text-muted mb-1"><?= __("initial_balance") ?> *</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="feather icon-dollar-sign"></i></span>
                                    </div>
                                    <input type="number" class="form-control" name="balance" step="0.01" required>
                                </div>
                            </div>
                            <div class="form-group col-md-6">
                                <label class="small text-muted mb-1"><?= __("currency") ?> *</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="feather icon-credit-card"></i></span>
                                    </div>
                                    <select class="form-control" name="currency" required>
                                        <option value="USD"><?= __("usd") ?></option>
                                        <option value="AFS"><?= __("afs") ?></option>
                                        <option value="EUR"><?= __("eur") ?></option>
                                        <option value="DARHAM"><?= __("darham") ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="small text-muted mb-1"><?= __("main_account") ?> *</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="feather icon-briefcase"></i></span>
                                </div>
                                <select class="form-control" name="main_account_id" id="mainAccountSelect" required>
                                    <?php foreach ($main_accounts as $account): ?>
                                        <option value="<?php echo h($account['id']); ?>"><?php echo htmlspecialchars($account['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="skipMainAccount" name="skip_main_account">
                            <label class="custom-control-label small" for="skipMainAccount">
                                <?= __("skip_adding_to_main_account_balance_and_transaction_record") ?>
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-link" data-dismiss="modal">
                            <i class="feather icon-x mr-2"></i><?= __("cancel") ?>
                        </button>
                        <button type="submit" name="add_creditor" class="btn btn-success">
                            <i class="feather icon-check-circle mr-2"></i><?= __("add_creditor") ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    
    <!-- Required Js -->
    
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    


    <script src="../js/creditor/modal_init.js"></script>

    <script src="../js/creditor/currency_check.js"></script>

<?php foreach ($creditors as $creditor): ?>
    <!-- Transactions Modal -->
    <div class="modal fade" id="transactionsModal_<?php echo h($creditor['id']); ?>" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __("transactions") ?> - <?php echo htmlspecialchars($creditor['name']); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th><?= __("date") ?></th>
                                    <th><?= __("amount") ?></th>
                                    <th><?= __("type") ?></th>
                                    <th><?= __("description") ?></th>
                                    <th><?= __("receipt") ?></th>
                                    <th><?= __("actions") ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Fetch transactions for this creditor
                                $transStmt = $pdo->prepare("SELECT * FROM creditor_transactions WHERE creditor_id = ? AND tenant_id = ? AND branch_id = ? ORDER BY payment_date DESC");
                                $transStmt->bindParam(1, $creditor['id'], PDO::PARAM_INT);
                                $transStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
                                $transStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
                                $transStmt->execute();
                                $transResult = $transStmt->fetchAll();
                                
                                if (count($transResult) > 0) {
                                    foreach ($transResult as $transaction) {
                                        echo '<tr>';
                                        // Ensure we display the exact date and time as stored in the database
                                        $dateTime = new DateTime($transaction['created_at']);
                                        echo '<td>' . $dateTime->format('Y-m-d H:i:s') . '</td>';
                                        echo '<td>' . number_format($transaction['amount'], 2) . ' ' . $transaction['currency'] . '</td>';
                                        echo '<td>' . ($transaction['transaction_type'] == 'debit' ? '<span class="badge-success">' . __("payment") . '</span>' : '<span class="badge-danger">' . __("credit") . '</span>') . '</td>';
                                        echo '<td>' . htmlspecialchars($transaction['description'] ?? '') . '</td>';
                                        echo '<td>' . htmlspecialchars($transaction['reference_number'] ?? '') . '</td>';
                                        echo '<td>';
                                        echo '<button class="btn btn-info btn-sm mr-1" title="Print Receipt" onclick="printReceipt(\'' . $transaction['id'] . '\')"><i class="feather icon-printer"></i></button>';
                                        // Add edit button
                                        echo '<button type="button" class="btn btn-primary btn-sm mr-1" data-toggle="modal" data-target="#editTransactionModal_' . $transaction['id'] . '"><i class="feather icon-edit"></i> ' . __("edit") . '</button>';
                                        // Delete button (admin only)
                                        if ($isAdmin) {
                                            echo '<form method="POST" onsubmit="return confirm(\'' . __("are_you_sure_you_want_to_delete_this_transaction_this_will_reverse_the_payment") . '\');">';
                                            echo '<input type="hidden" name="csrf_token" value="' . h($_SESSION['csrf_token']) . '">';
                                            echo '<input type="hidden" name="transaction_id" value="' . $transaction['id'] . '">';
                                            echo '<input type="hidden" name="creditor_id" value="' . $creditor['id'] . '">';
                                            echo '<input type="hidden" name="amount" value="' . $transaction['amount'] . '">';
                                            echo '<input type="hidden" name="currency" value="' . $transaction['currency'] . '">';
                                            echo '<button type="submit" name="delete_transaction" class="btn btn-danger btn-sm"><i class="feather icon-trash"></i> ' . __("delete") . '</button>';
                                            echo '</form>';
                                        }
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="6" class="text-center">' . __("no_transactions_found") . '</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __("close") ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal_<?php echo h($creditor['id']); ?>" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __("process_payment") ?> - <?php echo htmlspecialchars($creditor['name']); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                    
                    <div class="modal-body">
                        <input type="hidden" name="creditor_id" value="<?php echo h($creditor['id']); ?>">
                        <input type="hidden" name="creditor_currency" value="<?php echo h($creditor['currency']); ?>">
                        <div class="form-group">
                            <label><?= __("amount") ?> *</label>
                            <input type="number" class="form-control" name="amount" step="0.000001" required>
                        </div>
                        <div class="form-group">
                            <label><?= __("payment_currency") ?> *</label>
                            <select class="form-control" name="currency" required onchange="checkCreditorCurrency(this, '<?php echo h($creditor['currency']); ?>', '<?php echo h($creditor['id']); ?>')">
                                <option value="USD" <?php echo h($creditor['currency']) == 'USD' ? 'selected' : ''; ?>>USD</option>
                                <option value="AFS" <?php echo h($creditor['currency']) == 'AFS' ? 'selected' : ''; ?>>AFS</option>
                                <option value="EUR" <?php echo h($creditor['currency']) == 'EUR' ? 'selected' : ''; ?>>EUR</option>
                                <option value="DARHAM" <?php echo h($creditor['currency']) == 'DARHAM' ? 'selected' : ''; ?>>DARHAM</option>
                            </select>
                        </div>
                        <!-- Exchange Rate Field - Initially Hidden -->
                        <div class="form-group" id="exchangeRateDiv_<?php echo h($creditor['id']); ?>" style="display: none;">
                            <label>Exchange Rate (1 <span id="selectedCreditorCurrency_<?php echo h($creditor['id']); ?>"><?php echo h($creditor['currency']); ?></span> = ? <span id="creditorCurrency_<?php echo h($creditor['id']); ?>"><?php echo h($creditor['currency']); ?></span>)</label>
                            <input type="number" class="form-control" name="exchange_rate" id="exchangeRate_<?php echo h($creditor['id']); ?>" step="0.000001" placeholder="Enter exchange rate">
                            <small class="form-text text-muted">Enter the exchange rate to convert from payment currency to creditor's currency</small>
                        </div>
                        <div class="form-group">
                            <label><?= __("payment_date") ?> *</label>
                            <input type="date" class="form-control" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label><?= __("description") ?></label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label><?= __("paid_from") ?> *</label>
                            <select class="form-control" name="paid_to" required>
                                <?php foreach ($main_accounts as $account): ?>
                                    <option value="<?php echo h($account['id']); ?>"><?php echo htmlspecialchars($account['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __("cancel") ?></button>
                        <button type="submit" name="pay" class="btn btn-primary"><?= __("process_payment") ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Creditor Modal -->
    <div class="modal fade" id="editCreditorModal_<?php echo h($creditor['id']); ?>" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __("edit_creditor") ?> - <?php echo htmlspecialchars($creditor['name']); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                    
                    <div class="modal-body">
                        <input type="hidden" name="creditor_id" value="<?php echo h($creditor['id']); ?>">
                        <div class="form-group">
                            <label><?= __("name") ?> *</label>
                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($creditor['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label><?= __("email") ?></label>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($creditor['email']); ?>">
                        </div>
                        <div class="form-group">
                            <label><?= __("phone") ?></label>
                            <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($creditor['phone']); ?>">
                        </div>
                        <div class="form-group">
                            <label><?= __("address") ?></label>
                            <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($creditor['address']); ?></textarea>
                        </div>
                        <div class="form-group">
                             <label><?= __("balance") ?> *</label>
                             <input type="number" class="form-control" name="balance" step="0.01" value="<?php echo h($creditor['balance']); ?>" required disabled>
                             <small class="form-text text-muted"><?= __("balance_cannot_be_edited_directly") ?></small>
                         </div>
                         <div class="form-group">
                             <label><?= __("currency") ?> *</label>
                             <select class="form-control" name="currency" required disabled>
                                 <option value="USD" <?php echo h($creditor['currency']) == 'USD' ? 'selected' : ''; ?>>USD</option>
                                 <option value="AFS" <?php echo h($creditor['currency']) == 'AFS' ? 'selected' : ''; ?>>AFS</option>
                                 <option value="EUR" <?php echo h($creditor['currency']) == 'EUR' ? 'selected' : ''; ?>>EUR</option>
                                 <option value="DARHAM" <?php echo h($creditor['currency']) == 'DARHAM' ? 'selected' : ''; ?>>DARHAM</option>
                             </select>
                             <small class="form-text text-muted"><?= __("currency_cannot_be_changed") ?></small>
                         </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __("cancel") ?></button>
                        <button type="submit" name="edit_creditor" class="btn btn-primary"><?= __("save_changes") ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- Add Delete Creditor Modal for each creditor -->
<?php foreach ($creditors as $creditor): ?>
    <div class="modal fade" id="deleteCreditorModal_<?php echo h($creditor['id']); ?>" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><?= __("delete_creditor") ?> - <?php echo htmlspecialchars($creditor['name']); ?></h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" onsubmit="return confirm('<?= __("are_you_sure_you_want_to_delete_this_creditor_this_action_cannot_be_undone") ?>');">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                    
                    <div class="modal-body">
                        <input type="hidden" name="creditor_id" value="<?php echo h($creditor['id']); ?>">
                        <input type="hidden" name="creditor_balance" value="<?php echo h($creditor['balance']); ?>">
                        <input type="hidden" name="creditor_currency" value="<?php echo h($creditor['currency']); ?>">
                        <p><?= __("are_you_sure_you_want_to_delete_this_creditor") ?> <strong><?php echo htmlspecialchars($creditor['name']); ?></strong>?</p>
                        <p><?= __("current_balance") ?>: <strong><?php echo number_format($creditor['balance'], 2) . ' ' . h($creditor['currency']); ?></strong></p>
                        <?php if ($creditor['balance'] > 0): ?>
                            <div class="alert alert-warning">
                                <i class="feather icon-alert-triangle mr-2"></i>
                                <?= __("warning") ?>: <?= __("this_creditor_has_a_non_zero_balance_deleting_will_affect_main_account_balances_if_transactions_exist") ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __("cancel") ?></button>
                        <button type="submit" name="delete_creditor" class="btn btn-danger"><?= __("delete_creditor") ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php
// Validate edit_creditor
$edit_creditor = isset($_POST['edit_creditor']) ? DbSecurity::validateInput($_POST['edit_creditor'], 'string', ['maxlength' => 255]) : null;

// Add Edit Transaction Modals for each transaction
foreach ($creditors as $creditor): 
    // Fetch transactions for this creditor
    $transStmt = $pdo->prepare("SELECT * FROM creditor_transactions WHERE creditor_id = ? AND tenant_id = ? AND branch_id = ? ORDER BY payment_date DESC");
    $transStmt->bindParam(1, $creditor['id'], PDO::PARAM_INT);
    $transStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $transStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $transStmt->execute();
    $transResult = $transStmt->fetchAll();
    
    foreach ($transResult as $transaction):
?>
    <!-- Edit Transaction Modal -->
    <div class="modal fade" id="editTransactionModal_<?php echo $transaction['id']; ?>" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __("edit_transaction") ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="editTransactionForm_<?php echo $transaction['id']; ?>" class="edit-transaction-form">
                        <input type="hidden" name="transaction_id" value="<?php echo $transaction['id']; ?>">
                        <input type="hidden" name="creditor_id" value="<?php echo $creditor['id']; ?>">
                        <input type="hidden" name="original_amount" value="<?php echo $transaction['amount']; ?>">
                        <input type="hidden" name="original_currency" value="<?php echo $transaction['currency']; ?>">
                        
                        <div class="form-group">
                            <label><?= __("amount") ?> *</label>
                            <input type="number" class="form-control" name="payment_amount" value="<?php echo $transaction['amount']; ?>" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label><?= __("payment_date_and_time") ?> *</label>
                            <div class="row">
                                <div class="col-md-7">
                                    <?php 
                                    // Ensure we get the proper date
                                    $datetime = new DateTime($transaction['created_at']);
                                    $formattedDate = $datetime->format('d/m/Y');
                                    ?>
                                    <input type="text" class="form-control" name="payment_date" 
                                           placeholder="DD/MM/YYYY" value="<?php echo $formattedDate; ?>" required>
                                    <small class="form-text text-muted"><?= __("format") ?>: DD/MM/YYYY</small>
                                </div>
                                <div class="col-md-5">
                                    <?php 
                                    // Get the time part
                                    $formattedTime = $datetime->format('H:i:s');
                                    ?>
                                    <input type="text" class="form-control" name="payment_time" 
                                           placeholder="HH:MM:SS" value="<?php echo $formattedTime; ?>" required>
                                    <small class="form-text text-muted"><?= __("format") ?>: HH:MM:SS</small>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><?= __("reference_number") ?></label>
                            <input type="text" class="form-control" name="reference_number" value="<?php echo htmlspecialchars($transaction['reference_number'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label><?= __("description") ?></label>
                            <textarea class="form-control" name="payment_description" rows="3"><?php echo htmlspecialchars($transaction['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="feather icon-alert-triangle mr-2"></i>
                            <?= __("warning") ?>: <?= __("editing_a_transaction_will_recalculate_balances") ?>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __("cancel") ?></button>
                    <button type="button" class="btn btn-primary" onclick="updateCreditorTransaction(<?php echo $transaction['id']; ?>)"><?= __("save_changes") ?></button>
                </div>
            </div>
        </div>
    </div>
<?php
    endforeach;
endforeach;
?>



<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

<script src="../js/creditor/transaction_update.js"></script>
<script src="../js/creditor/print_receipt.js"></script>
</body>
</html> 

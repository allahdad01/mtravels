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
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page   = isset($_GET['page'])   ? intval($_GET['page']) : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

try {
      // Get counts for both active and inactive debtors
     $activeCountStmt = $pdo->prepare("SELECT COUNT(*) as count FROM debtors WHERE status = 'active' AND tenant_id = ? AND branch_id = ?");
     $activeCountStmt->execute([$tenant_id, $branch_id]);
     $active_count = $activeCountStmt->fetch(PDO::FETCH_ASSOC)['count'];
     
     $inactiveCountStmt = $pdo->prepare("SELECT COUNT(*) as count FROM debtors WHERE status = 'inactive' AND tenant_id = ? AND branch_id = ?");
     $inactiveCountStmt->execute([$tenant_id, $branch_id]);
     $inactive_count = $inactiveCountStmt->fetch(PDO::FETCH_ASSOC)['count'];
     
     // Count with search
     $searchCondition = "WHERE status = ? AND tenant_id = ? AND branch_id = ?";
     $countParams = [$status_filter, $tenant_id, $branch_id];
     if (!empty($search)) {
         $searchCondition .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
         for ($i = 0; $i < 3; $i++) { $countParams[] = "%$search%"; }
     }
     $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM debtors $searchCondition");
     $countStmt->execute($countParams);
     $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
     $totalPages   = ceil($totalRecords / $recordsPerPage);
     
     // Fetch debtors with search + pagination
     $query = "SELECT * FROM debtors $searchCondition ORDER BY id DESC LIMIT ? OFFSET ?";
     $queryParams = $countParams;
     $queryParams[] = $recordsPerPage;
     $queryParams[] = $offset;
     $stmt = $pdo->prepare($query);
     $stmt->execute($queryParams);
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
    $debtors = [];
    $totalRecords = 0;
    $totalPages = 0;
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
    --bg:        #f0f2f7;
    --surface:   #ffffff;
    --border:    #e4e8f0;
    --text-1:    #111827;
    --text-2:    #4b5563;
    --text-3:    #9ca3af;
    --blue:      #3b82f6;
    --blue-lt:   #eff6ff;
    --green:     #10b981;
    --green-lt:  #ecfdf5;
    --amber:     #f59e0b;
    --amber-lt:  #fffbeb;
    --red:       #ef4444;
    --red-lt:    #fef2f2;
    --grad:      linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);
    --shadow-sm: 0 1px 3px rgba(0,0,0,.07),0 1px 2px rgba(0,0,0,.04);
    --shadow-md: 0 4px 16px rgba(0,0,0,.08),0 2px 6px rgba(0,0,0,.05);
    --r:         14px;
    --r-sm:      8px;
}
.pcoded-main-container{background:var(--bg)!important;}
.main-body .page-wrapper{flex:1;display:flex;flex-direction:column;}

/* Page header */
.debtor-page-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 22px; flex-wrap: wrap; gap: 14px;
}
.debtor-page-title { display: flex; align-items: center; gap: 12px; }
.debtor-page-title-icon {
    width: 44px; height: 44px;
    background: var(--grad); border-radius: var(--r-sm);
    display: grid; place-items: center; color: #fff; font-size: 18px;
    box-shadow: 0 4px 12px rgba(64,153,255,.35);
}
.debtor-page-title h1 { font-size: 20px; font-weight: 700; color: var(--text-1); margin: 0; line-height: 1.2; }
.debtor-page-title p { font-size: 13px; color: var(--text-3); margin: 2px 0 0; }
.debtor-header-actions { display: flex; gap: 10px; align-items: center; }

/* Search bar */
.debtor-search-bar {
    background: var(--surface); border-radius: var(--r);
    padding: 14px 18px; display: flex; align-items: center; gap: 12px;
    box-shadow: var(--shadow-sm); border: 1px solid var(--border);
    margin-bottom: 18px; flex-wrap: wrap;
}
.debtor-search-input-wrap { position: relative; flex: 1; min-width: 220px; }
.debtor-search-input-wrap i {
    position: absolute; left: 13px; top: 50%;
    transform: translateY(-50%); color: var(--text-3); font-size: 13px;
}
.debtor-search-input {
    width: 100%; padding: 9px 13px 9px 36px;
    border: 1.5px solid var(--border); border-radius: var(--r-sm);
    font-size: 14px; color: var(--text-1); background: var(--bg);
    outline: none; transition: border-color .2s, box-shadow .2s; font-family: inherit;
}
.debtor-search-input:focus {
    border-color: var(--blue); box-shadow: 0 0 0 3px rgba(59,130,246,.12);
    background: var(--surface);
}
.debtor-search-input::placeholder { color: var(--text-3); }

/* Results bar */
.debtor-results-bar {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 14px; flex-wrap: wrap; gap: 8px;
}
.debtor-results-count { font-size: 13px; color: var(--text-3); }
.debtor-results-count strong { color: var(--text-2); font-weight: 600; }

/* Buttons */
.btn-debtor-primary {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 10px 18px; border-radius: var(--r-sm);
    font-size: 14px; font-weight: 600; cursor: pointer; border: none;
    background: var(--grad); color: #fff;
    box-shadow: 0 4px 12px rgba(64,153,255,.3);
    transition: all .18s; font-family: inherit; white-space: nowrap;
}
.btn-debtor-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(64,153,255,.4); }

/* Debtor card */
.debtor-card-list { display: flex; flex-direction: column; gap: 11px; }
.debtor-card {
    background: var(--surface); border-radius: var(--r);
    border: 1.5px solid var(--border); box-shadow: var(--shadow-sm);
    overflow: hidden; display: grid;
    grid-template-columns: 5px 1fr;
    transition: box-shadow .2s, transform .2s, border-color .2s;
    animation: fadeUp .3s ease both;
}
.debtor-card:hover { box-shadow: var(--shadow-md); transform: translateY(-1px); border-color: #d1d5e8; }
.debtor-card__stripe { width: 5px; }
.stripe--active   { background: var(--green); }
.stripe--inactive { background: var(--red); }
.debtor-card__body {
    padding: 16px 18px;
    display: grid; grid-template-columns: 1fr auto; gap: 12px; align-items: start;
}
.debtor-card__left { min-width: 0; }
.debtor-card__top {
    display: flex; align-items: center; gap: 9px; margin-bottom: 9px; flex-wrap: wrap;
}
.debtor-card__counter {
    font-size: 11px; font-weight: 700; color: var(--text-3);
    font-family: 'DM Mono','Courier New',monospace; letter-spacing: .5px;
}
.debtor-card__name {
    font-size: 15px; font-weight: 700; color: var(--text-1);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 280px;
}
.dc-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 99px; font-size: 11.5px; font-weight: 600;
}
.dc-badge-dot { width: 6px; height: 6px; border-radius: 50%; }
.badge--active   { background: var(--green-lt); color: var(--green); }
.badge--inactive { background: var(--red-lt);   color: var(--red); }

/* Meta pills */
.debtor-card__meta {
    display: flex; gap: 7px; flex-wrap: wrap; margin-bottom: 9px; align-items: center;
}
.dc-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px; background: var(--bg); border: 1px solid var(--border);
    border-radius: 6px; font-size: 12.5px; color: var(--text-2); font-weight: 500;
}
.dc-pill i { font-size: 11px; color: var(--text-3); }

/* Right side */
.debtor-card__right { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; }
.dc-amount { text-align: right; }
.dc-amount__label {
    font-size: 10.5px; font-weight: 600; text-transform: uppercase;
    letter-spacing: .6px; color: var(--text-3);
}
.dc-amount__value {
    font-size: 18px; font-weight: 700; color: var(--text-1);
    font-family: 'DM Mono','Courier New',monospace; letter-spacing: -.5px; line-height: 1.2;
}
.dc-amount__currency { font-size: 12px; color: var(--text-3); font-weight: 500; }

/* Action buttons */
.debtor-card__actions { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
.dc-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 11px; border-radius: 7px; font-family: inherit;
    font-size: 12px; font-weight: 600; cursor: pointer;
    border: 1.5px solid var(--border); background: var(--surface);
    color: var(--text-2); transition: all .15s; white-space: nowrap;
}
.dc-btn:hover          { border-color: var(--blue);  color: var(--blue);  background: var(--blue-lt); }
.dc-btn--warn:hover    { border-color: var(--amber); color: #b45309;      background: var(--amber-lt); }
.dc-btn--danger:hover  { border-color: var(--red);   color: var(--red);   background: var(--red-lt); }
.dc-btn--success:hover { border-color: var(--green); color: var(--green); background: var(--green-lt); }

/* Alerts */
.debtor-alert {
    border-radius: 14px; padding: 14px 18px; margin-bottom: 16px;
    display: flex; align-items: flex-start; gap: 12px; border: 1px solid;
    animation: fadeUp .3s ease both;
}
.debtor-alert--success { background: var(--green-lt); border-color: rgba(16,185,129,.3); }
.debtor-alert--error   { background: var(--red-lt);   border-color: rgba(244,63,94,.3); }
.debtor-alert-icon {
    width: 36px; height: 36px; border-radius: 8px;
    display: grid; place-items: center; font-size: 16px; flex-shrink: 0;
}
.debtor-alert-title { font-size: 13px; font-weight: 700; margin-bottom: 2px; }

/* Pagination */
.debtor-pagination-bar {
    display: flex; align-items: center; justify-content: space-between;
    margin-top: 22px; flex-wrap: wrap; gap: 12px;
}
.debtor-pagination-info { font-size: 13px; color: var(--text-3); }
.debtor-pagination { display: flex; gap: 4px; list-style: none; margin: 0; padding: 0; }
.debtor-pagination li a,
.debtor-pagination li span {
    display: grid; place-items: center; width: 36px; height: 36px;
    border-radius: 8px; font-size: 13px; font-weight: 600;
    text-decoration: none; border: 1.5px solid var(--border);
    background: var(--surface); color: var(--text-2);
    transition: all .15s; cursor: pointer;
}
.debtor-pagination li a:hover {
    border-color: var(--blue); color: var(--blue); background: var(--blue-lt);
}
.debtor-pagination li.active a {
    background: var(--grad); color: #fff; border-color: transparent;
    box-shadow: 0 3px 8px rgba(64,153,255,.3);
}
.debtor-pagination li.disabled span { color: var(--text-3); cursor: not-allowed; }

/* Empty state */
.debtor-empty {
    background: var(--surface); border: 1.5px dashed var(--border);
    border-radius: var(--r); text-align: center; padding: 60px 20px; color: var(--text-3);
}
.debtor-empty i { font-size: 40px; margin-bottom: 14px; opacity: .35; display: block; }
.debtor-empty p { font-size: 14px; }

/* Animations */
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}
<?php for ($i = 1; $i <= 10; $i++): ?>
.debtor-card:nth-child(<?= $i ?>) { animation-delay: <?= ($i * 0.035) ?>s; }
<?php endfor; ?>

@media (max-width: 640px) {
    .debtor-card__body { grid-template-columns: 1fr; }
    .debtor-card__right { align-items: flex-start; flex-direction: row; flex-wrap: wrap; }
    .dc-amount { text-align: left; }
    .dc-amount__value { font-size: 16px; }
    .debtor-card__name { max-width: 200px; }
}
</style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">

                        <!-- PAGE HEADER -->
                        <div class="debtor-page-header">
                            <div class="debtor-page-title">
                                <div class="debtor-page-title-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div>
                                    <h1><?= __('debtors_management') ?></h1>
                                    <p><?= __('manage_your_debtors_and_track_payments') ?></p>
                                </div>
                            </div>
                            <div class="debtor-header-actions">
                                <button class="btn-debtor-primary" data-toggle="modal" data-target="#addDebtorModal">
                                    <i class="fas fa-plus"></i> <?= __('add_new_debtor') ?>
                                </button>
                            </div>
                        </div>

                        <?php if (isset($success_message)): ?>
                        <div class="debtor-alert debtor-alert--success">
                            <div class="debtor-alert-icon" style="background:rgba(16,185,129,.2);color:var(--green);"><i class="fas fa-check-circle"></i></div>
                            <div>
                                <div class="debtor-alert-title" style="color:var(--green);"><?= __('success') ?></div>
                                <div style="font-size:13px;color:var(--text-1);"><?php echo h($success_message); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($error_message)): ?>
                        <div class="debtor-alert debtor-alert--error">
                            <div class="debtor-alert-icon" style="background:rgba(244,63,94,.2);color:var(--red);"><i class="fas fa-exclamation-circle"></i></div>
                            <div>
                                <div class="debtor-alert-title" style="color:var(--red);"><?= __('error') ?></div>
                                <div style="font-size:13px;color:var(--text-1);"><?php echo h($error_message); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- SEARCH BAR -->
                        <div class="debtor-search-bar">
                            <div class="debtor-search-input-wrap">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchInput" class="debtor-search-input"
                                       placeholder="<?= __('search_by_name_email_or_phone') ?>"
                                       value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>

                        <!-- RESULTS BAR -->
                        <div class="debtor-results-bar">
                            <p class="debtor-results-count">
                                <?php
                                $start_record = min(($page - 1) * $recordsPerPage + 1, $totalRecords);
                                $end_record = min($page * $recordsPerPage, $totalRecords);
                                ?>
                                <?= __('showing') ?>
                                <strong><?= $totalRecords > 0 ? $start_record : 0 ?>–<?= $end_record ?></strong>
                                <?= __('of') ?> <strong><?= $totalRecords ?></strong> <?= __('entries') ?>
                            </p>
                            <div style="display:flex;gap:8px;">
                                <a href="debtors.php?status=active" class="dc-btn <?= $status_filter === 'active' ? '' : '' ?>" style="<?= $status_filter === 'active' ? 'border-color:var(--green);color:var(--green);background:var(--green-lt);' : '' ?>">
                                    <i class="fas fa-user-check"></i> <?= __('active') ?> (<?= $active_count ?>)
                                </a>
                                <a href="debtors.php?status=inactive" class="dc-btn <?= $status_filter === 'inactive' ? '' : '' ?>" style="<?= $status_filter === 'inactive' ? 'border-color:var(--red);color:var(--red);background:var(--red-lt);' : '' ?>">
                                    <i class="fas fa-user-minus"></i> <?= __('inactive') ?> (<?= $inactive_count ?>)
                                </a>
                            </div>
                        </div>

                        <!-- DEBTOR CARDS -->
                        <?php if (empty($debtors)): ?>
                        <div class="debtor-empty">
                            <i class="fas fa-inbox"></i>
                            <p><?= $status_filter === 'active' ? __('add_new_debtors_to_start_tracking_your_debts') : __('deactivated_debtors_will_appear_here') ?></p>
                        </div>
                        <?php else: ?>

                        <div class="debtor-card-list" id="debtorList">
                        <?php
                        $counter = $start_record;
                        foreach ($debtors as $debtor):
                            $stripeClass = $debtor['status'] === 'active' ? 'stripe--active' : 'stripe--inactive';
                            $badgeClass  = $debtor['status'] === 'active' ? 'badge--active' : 'badge--inactive';
                            $statusIcon  = $debtor['status'] === 'active' ? 'fa-check-circle' : 'fa-times-circle';
                        ?>
                        <div class="debtor-card">
                            <div class="debtor-card__stripe <?= $stripeClass ?>"></div>
                            <div class="debtor-card__body">
                                <div class="debtor-card__left">
                                    <div class="debtor-card__top">
                                        <span class="debtor-card__counter">#<?= str_pad($counter, 3, '0', STR_PAD_LEFT) ?></span>
                                        <span class="debtor-card__name"><?= htmlspecialchars($debtor['name']) ?></span>
                                        <span class="dc-badge <?= $badgeClass ?>">
                                            <span class="dc-badge-dot" style="background:<?= $debtor['status'] === 'active' ? 'var(--green)' : 'var(--red)' ?>"></span>
                                            <?= ucfirst(__($debtor['status'])) ?>
                                        </span>
                                    </div>
                                    <div class="debtor-card__meta">
                                        <?php if (!empty($debtor['email'])): ?>
                                        <span class="dc-pill">
                                            <i class="fas fa-envelope"></i>
                                            <?= htmlspecialchars($debtor['email']) ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if (!empty($debtor['phone'])): ?>
                                        <span class="dc-pill">
                                            <i class="fas fa-phone"></i>
                                            <?= htmlspecialchars($debtor['phone']) ?>
                                        </span>
                                        <?php endif; ?>
                                        <span class="dc-pill">
                                            <i class="fas fa-money-bill-wave"></i>
                                            <?= htmlspecialchars($debtor['currency']) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="debtor-card__right">
                                    <div class="dc-amount">
                                        <div class="dc-amount__label"><?= __('balance') ?></div>
                                        <div class="dc-amount__value">
                                            <span class="dc-amount__currency"><?= htmlspecialchars($debtor['currency']) ?></span>
                                            <?= number_format($debtor['balance'], 2) ?>
                                        </div>
                                    </div>
                                    <div class="debtor-card__actions">
                                        <button class="dc-btn dc-btn--success" data-toggle="modal" data-target="#paymentModal<?= h($debtor['id']) ?>" title="<?= __('process_payment') ?>">
                                            <i class="fas fa-credit-card"></i> <?= __('pay') ?>
                                        </button>
                                        <button class="dc-btn" data-toggle="modal" data-target="#transactionsModal<?= h($debtor['id']) ?>" title="<?= __('view_transactions') ?>">
                                            <i class="fas fa-list"></i> <?= __('transactions') ?>
                                        </button>
                                        <a href="../api/debtor/print_debtor_statement.php?id=<?= h($debtor['id']) ?>" class="dc-btn" target="_blank" title="<?= __('print_statement') ?>">
                                            <i class="fas fa-print"></i>
                                        </a>
<a href="../api/debtor/print_agreement.php?id=<?= h($debtor['id']) ?>" class="dc-btn" target="_blank" title="<?= __('print_agreement') ?>">
    <i class="fas fa-file-alt"></i>
</a>
                                        <button class="dc-btn" data-toggle="modal" data-target="#editDebtorModal<?= h($debtor['id']) ?>" title="<?= __('edit_debtor') ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($debtor['status'] === 'active'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="debtor_id" value="<?= h($debtor['id']) ?>">
                                            <input type="hidden" name="action" value="deactivate_debtor">
<button type="submit" class="dc-btn dc-btn--danger" title="<?= __('deactivate_debtor') ?>" onclick="return confirm('<?= __('are_you_sure_you_want_to_deactivate_this_debtor') ?>');">
    <i class="fas fa-ban"></i>
</button>
                                        </form>
                                        <?php else: ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="debtor_id" value="<?= h($debtor['id']) ?>">
                                            <input type="hidden" name="action" value="activate_debtor">
                                            <button type="submit" class="dc-btn dc-btn--success" title="<?= __('activate_debtor') ?>">
                                                <i class="fas fa-check-circle"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        <?php if ($isAdmin): ?>
                                        <button class="dc-btn dc-btn--danger" onclick="if(confirm('<?= __('are_you_sure') ?>')){ document.querySelector('form.delete-form-<?= h($debtor['id']) ?>').submit(); }" title="<?= __('delete_debtor') ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <form method="POST" action="../api/debtor/delete_debtor.php" class="d-none delete-form-<?= h($debtor['id']) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="debtor_id" value="<?= h($debtor['id']) ?>">
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
                            $counter++;
                        endforeach;
                        ?>
                        </div>
                        <?php endif; ?>

                        <!-- PAGINATION -->
                        <div class="debtor-pagination-bar">
                            <p class="debtor-pagination-info">
                                <?= __('showing') ?>
                                <?= $totalRecords > 0 ? $start_record : 0 ?>
                                <?= __('to') ?>
                                <?= $end_record ?>
                                <?= __('of') ?>
                                <?= $totalRecords ?> <?= __('entries') ?>
                            </p>
                            <ul class="debtor-pagination">
                                <li class="<?= $page <= 1 ? 'disabled' : '' ?>">
                                    <?php if ($page <= 1): ?>
                                    <span>&laquo;</span>
                                    <?php else: ?>
                                    <a href="?page=<?= $page - 1 ?>&status=<?= $status_filter ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">&laquo;</a>
                                    <?php endif; ?>
                                </li>
                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage   = min($totalPages, $page + 2);
                                for ($i = $startPage; $i <= $endPage; $i++):
                                ?>
                                <li class="<?= $i == $page ? 'active' : '' ?>">
                                    <a href="?page=<?= $i ?>&status=<?= $status_filter ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"><?= $i ?></a>
                                </li>
                                <?php endfor; ?>
                                <li class="<?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <?php if ($page >= $totalPages): ?>
                                    <span>&raquo;</span>
                                    <?php else: ?>
                                    <a href="?page=<?= $page + 1 ?>&status=<?= $status_filter ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">&raquo;</a>
                                    <?php endif; ?>
                                </li>
                            </ul>
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
                                                         
                                                         <div class="form-row">
                                                             <div class="form-group col-md-6">
                                                                 <label class="form-label"><?= __('debtor_name') ?></label>
                                                                 <input type="text" class="form-control" value="<?php echo htmlspecialchars($debtor['name']); ?>" readonly>
                                                             </div>
                                                             <div class="form-group col-md-6">
                                                                 <label class="form-label"><?= __('current_balance') ?></label>
                                                                 <input type="text" class="form-control" value="<?php echo number_format($debtor['balance'], 2) . ' ' . $debtor['currency']; ?>" readonly>
                                                             </div>
                                                         </div>
                                                         
                                                         <div class="form-row">
                                                             <div class="form-group col-md-6">
                                                                 <label class="form-label"><?= __('payment_amount') ?></label>
                                                                 <input type="number" class="form-control" name="amount" step="0.00001" required>
                                                             </div>
                                                             <div class="form-group col-md-6">
                                                                 <label class="form-label"><?= __('payment_currency') ?></label>
                                                                 <select class="form-control" name="currency" required onchange="checkCurrency(this, '<?php echo h($debtor['currency']); ?>', '<?php echo h($debtor['id']); ?>')">
                                                                     <option value="USD" <?php echo h($debtor['currency']) == 'USD' ? 'selected' : ''; ?>><?= __('usd') ?></option>
                                                                     <option value="AFS" <?php echo h($debtor['currency']) == 'AFS' ? 'selected' : ''; ?>><?= __('afs') ?></option>
                                                                     <option value="EUR" <?php echo h($debtor['currency']) == 'EUR' ? 'selected' : ''; ?>><?= __('eur') ?></option>
                                                                     <option value="DARHAM" <?php echo h($debtor['currency']) == 'DARHAM' ? 'selected' : ''; ?>><?= __('darham') ?></option>
                                                                 </select>
                                                             </div>
                                                         </div>
                                                         
                                                         <!-- Exchange Rate Field - Initially Hidden -->
                                                         <div class="form-group" id="exchangeRateDiv<?php echo h($debtor['id']); ?>" style="display: none;">
                                                             <label class="form-label"><?= __('exchange_rate') ?> (1 <span id="selectedCurrency<?php echo h($debtor['id']); ?>"><?php echo h($debtor['currency']); ?></span> = ? <span id="debtorCurrency<?php echo h($debtor['id']); ?>"><?php echo h($debtor['currency']); ?></span>)</label>
                                                             <input type="number" class="form-control" name="exchange_rate" id="exchangeRate<?php echo h($debtor['id']); ?>" step="0.000001" placeholder="<?= __('enter_exchange_rate') ?>">
                                                               <small class="form-text text-muted" id="exchangeRateHelp<?php echo h($debtor['id']); ?>"><?= __('enter_the_exchange_rate_to_convert_between_currencies') ?></small>
                                                         </div>
                                                         
                                                          <div class="form-row">
                                                              <div class="form-group col-md-4">
                                                                  <label class="form-label"><?= __('description') ?></label>
                                                                  <input type="text" class="form-control" name="description">
                                                              </div>
                                                              <div class="form-group col-md-4">
                                                                  <label class="form-label"><?= __('receipt') ?></label>
                                                                  <input type="text" class="form-control" name="receipt" placeholder="<?= __('receipt_number') ?>">
                                                              </div>
                                                              <div class="form-group col-md-4">
                                                                  <label class="form-label"><?= __('payment_date') ?></label>
                                                                  <input type="date" class="form-control" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                                                              </div>
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
                                        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                            <div class="modal-content shadow-lg border-0">
                                                <div class="modal-header bg-gradient-info text-white border-0">
                                                    <h5 class="modal-title"><?= __('transactions') ?> - <?php echo htmlspecialchars($debtor['name']); ?></h5>
                                                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body p-0">
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-hover mb-0">
                                                            <thead class="thead-light">
                                                                <tr>
                                                                    <th><?= __('date') ?></th>
                                                                    <th><?= __('description') ?></th>
                                                                    <th><?= __('receipt') ?></th>
                                                                    <th><?= __('amount') ?></th>
                                                                    <th class="text-center"><?= __('actions') ?></th>
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
                                                                        echo '<td>' . date('M d, Y', strtotime($transaction['payment_date'])) . '</td>';
                                                                        $displayAmount = number_format($transaction['amount'], 2) . ' ' . $transaction['currency'];
                                                                        if ($transaction['transaction_type'] == 'credit') {
                                                                            echo '<td>' . htmlspecialchars($transaction['description']) . '</td>';
                                                                            echo '<td>' . htmlspecialchars($transaction['reference_number']) . '</td>';
                                                                            echo '<td>Received ' . $displayAmount . '</td>';
                                                                        } else {
                                                                            echo '<td>' . htmlspecialchars($transaction['description']) . '</td>';
                                                                            echo '<td>' . htmlspecialchars($transaction['reference_number']) . '</td>';
                                                                            echo '<td>Paid ' . $displayAmount . '</td>';
                                                                        }
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
                                                                            data-reference-number="' . htmlspecialchars($transaction['reference_number'], ENT_QUOTES) . '">
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
                                                                    echo '<tr><td colspan="5" class="text-center">' . __('no_transactions_found') . '</td></tr>';
                                                                }
                                                                ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
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
                                                            <label class="form-label"><?= __('balance') ?></label>
                                                            <input type="text" class="form-control" value="<?php echo h($debtor['balance']); ?>" disabled>
                                                            <small class="text-muted">Balance is managed through transactions only</small>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('currency') ?></label>
                                                            <input type="text" class="form-control" value="<?php echo h($debtor['currency']); ?>" disabled>
                                                            <small class="text-muted">Currency cannot be changed after creation</small>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><?= __('main_account') ?></label>
                                                            <input type="text" class="form-control" value="<?php 
                                                                $account_name = '';
                                                                foreach ($main_accounts as $account) {
                                                                    if ($account['id'] == $debtor['main_account_id']) {
                                                                        $account_name = $account['name'];
                                                                        break;
                                                                    }
                                                                }
                                                                echo h($account_name);
                                                            ?>" disabled>
                                                            <small class="text-muted">Main account is set during creation and cannot be changed</small>
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



<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

<!-- Required Js -->

    <script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<!-- SweetAlert2 JS -->
<script src="../assets/plugins/sweetalert2/sweetalert2.min.js"></script>

<script>
// Search on Enter
document.getElementById('searchInput').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
        const q = this.value.trim();
        window.location.href = 'debtors.php?status=<?= $status_filter ?>&search=' + encodeURIComponent(q) + '&page=1';
    }
});
</script>

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
                    
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="edit_amount"><?= __('amount') ?></label>
                            <input type="number" class="form-control" id="edit_amount" name="amount" step="0.01" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="edit_reference_number"><?= __('receipt') ?></label>
                            <input type="text" class="form-control" id="edit_reference_number" name="reference_number">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_description"><?= __('description') ?></label>
                        <input type="text" class="form-control" id="edit_description" name="description" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_payment_date"><?= __('payment_date') ?></label>
                        <input type="date" class="form-control" id="edit_payment_date" name="payment_date" required>
                    </div>
                    
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
        /* Dropdown action styles */
        .dropdown-icon {
            width: 18px;
            text-align: center;
            margin-right: 8px;
        }
        .dropdown-item-form {
            margin: 0;
        }
        .dropdown-item-btn {
            display: block;
            width: 100%;
            padding: 4px 24px;
            clear: both;
            font-weight: 400;
            color: #212529;
            text-align: inherit;
            white-space: nowrap;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            line-height: 1.5;
        }
        .dropdown-item-btn:hover {
            background-color: #f8f9fa;
        }
        .dropdown-item-btn.text-danger {
            color: #dc3545;
        }
        .dropdown-item-btn.text-danger:hover {
            background-color: #f8d7da;
        }
        #debtorsTable .btn-group .btn-sm {
            padding: 3px 8px;
            font-size: 12px;
        }
        #debtorsTable td {
            vertical-align: middle;
        }
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
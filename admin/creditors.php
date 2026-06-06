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
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$items_per_page = 10;
$offset = ($current_page - 1) * $items_per_page;

try {
     // Get counts for both active and inactive creditors
     $activeCountStmt = $pdo->prepare("SELECT COUNT(*) as count FROM creditors WHERE status = 'active' AND tenant_id = ? AND branch_id = ?");
     $activeCountStmt->execute([$tenant_id, $branch_id]);
     $active_count = $activeCountStmt->fetch(PDO::FETCH_ASSOC)['count'];
     
     $inactiveCountStmt = $pdo->prepare("SELECT COUNT(*) as count FROM creditors WHERE status = 'inactive' AND tenant_id = ? AND branch_id = ?");
     $inactiveCountStmt->execute([$tenant_id, $branch_id]);
     $inactive_count = $inactiveCountStmt->fetch(PDO::FETCH_ASSOC)['count'];
     
     // Build search condition
     $searchCondition = "WHERE status = ? AND tenant_id = ? AND branch_id = ?";
     $countParams = [$status_filter, $tenant_id, $branch_id];
     if (!empty($search)) {
         $searchCondition .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
         for ($i = 0; $i < 3; $i++) { $countParams[] = "%$search%"; }
     }
     
     // Get total count with search
     $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM creditors $searchCondition");
     $countStmt->execute($countParams);
     $total_count = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
     $total_pages = ceil($total_count / $items_per_page);
     
     // Fetch creditors with search + pagination
     $query = "SELECT * FROM creditors $searchCondition ORDER BY name ASC LIMIT ? OFFSET ?";
     $queryParams = $countParams;
     $queryParams[] = $items_per_page;
     $queryParams[] = $offset;
     $stmt = $pdo->prepare($query);
     $stmt->execute($queryParams);
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
.creditor-page-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 22px; flex-wrap: wrap; gap: 14px;
}
.creditor-page-title { display: flex; align-items: center; gap: 12px; }
.creditor-page-title-icon {
    width: 44px; height: 44px;
    background: var(--grad); border-radius: var(--r-sm);
    display: grid; place-items: center; color: #fff; font-size: 18px;
    box-shadow: 0 4px 12px rgba(64,153,255,.35);
}
.creditor-page-title h1 { font-size: 20px; font-weight: 700; color: var(--text-1); margin: 0; line-height: 1.2; }
.creditor-page-title p { font-size: 13px; color: var(--text-3); margin: 2px 0 0; }
.creditor-header-actions { display: flex; gap: 10px; align-items: center; }

/* Search bar */
.creditor-search-bar {
    background: var(--surface); border-radius: var(--r);
    padding: 14px 18px; display: flex; align-items: center; gap: 12px;
    box-shadow: var(--shadow-sm); border: 1px solid var(--border);
    margin-bottom: 18px; flex-wrap: wrap;
}
.creditor-search-input-wrap { position: relative; flex: 1; min-width: 220px; }
.creditor-search-input-wrap i {
    position: absolute; left: 13px; top: 50%;
    transform: translateY(-50%); color: var(--text-3); font-size: 13px;
}
.creditor-search-input {
    width: 100%; padding: 9px 13px 9px 36px;
    border: 1.5px solid var(--border); border-radius: var(--r-sm);
    font-size: 14px; color: var(--text-1); background: var(--bg);
    outline: none; transition: border-color .2s, box-shadow .2s; font-family: inherit;
}
.creditor-search-input:focus {
    border-color: var(--blue); box-shadow: 0 0 0 3px rgba(59,130,246,.12);
    background: var(--surface);
}
.creditor-search-input::placeholder { color: var(--text-3); }

/* Results bar */
.creditor-results-bar {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 14px; flex-wrap: wrap; gap: 8px;
}
.creditor-results-count { font-size: 13px; color: var(--text-3); }
.creditor-results-count strong { color: var(--text-2); font-weight: 600; }

/* Buttons */
.btn-creditor-primary {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 10px 18px; border-radius: var(--r-sm);
    font-size: 14px; font-weight: 600; cursor: pointer; border: none;
    background: var(--grad); color: #fff;
    box-shadow: 0 4px 12px rgba(64,153,255,.3);
    transition: all .18s; font-family: inherit; white-space: nowrap;
}
.btn-creditor-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(64,153,255,.4); }

/* Creditor card */
.creditor-card-list { display: flex; flex-direction: column; gap: 11px; }
.creditor-card {
    background: var(--surface); border-radius: var(--r);
    border: 1.5px solid var(--border); box-shadow: var(--shadow-sm);
    overflow: hidden; display: grid;
    grid-template-columns: 5px 1fr;
    transition: box-shadow .2s, transform .2s, border-color .2s;
    animation: fadeUp .3s ease both;
}
.creditor-card:hover { box-shadow: var(--shadow-md); transform: translateY(-1px); border-color: #d1d5e8; }
.creditor-card__stripe { width: 5px; }
.stripe--active   { background: var(--green); }
.stripe--inactive { background: var(--red); }
.creditor-card__body {
    padding: 16px 18px;
    display: grid; grid-template-columns: 1fr auto; gap: 12px; align-items: start;
}
.creditor-card__left { min-width: 0; }
.creditor-card__top {
    display: flex; align-items: center; gap: 9px; margin-bottom: 9px; flex-wrap: wrap;
}
.creditor-card__counter {
    font-size: 11px; font-weight: 700; color: var(--text-3);
    font-family: 'DM Mono','Courier New',monospace; letter-spacing: .5px;
}
.creditor-card__name {
    font-size: 15px; font-weight: 700; color: var(--text-1);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 280px;
}
.cc-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 99px; font-size: 11.5px; font-weight: 600;
}
.cc-badge-dot { width: 6px; height: 6px; border-radius: 50%; }
.badge--active   { background: var(--green-lt); color: var(--green); }
.badge--inactive { background: var(--red-lt);   color: var(--red); }

/* Meta pills */
.creditor-card__meta {
    display: flex; gap: 7px; flex-wrap: wrap; margin-bottom: 9px; align-items: center;
}
.cc-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px; background: var(--bg); border: 1px solid var(--border);
    border-radius: 6px; font-size: 12.5px; color: var(--text-2); font-weight: 500;
}
.cc-pill i { font-size: 11px; color: var(--text-3); }

/* Right side */
.creditor-card__right { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; }
.cc-amount { text-align: right; }
.cc-amount__label {
    font-size: 10.5px; font-weight: 600; text-transform: uppercase;
    letter-spacing: .6px; color: var(--text-3);
}
.cc-amount__value {
    font-size: 18px; font-weight: 700; color: var(--text-1);
    font-family: 'DM Mono','Courier New',monospace; letter-spacing: -.5px; line-height: 1.2;
}
.cc-amount__currency { font-size: 12px; color: var(--text-3); font-weight: 500; }

/* Action buttons */
.creditor-card__actions { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
.cc-action-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 11px; border-radius: 7px; font-family: inherit;
    font-size: 12px; font-weight: 600; cursor: pointer;
    border: 1.5px solid var(--border); background: var(--surface);
    color: var(--text-2); transition: all .15s; white-space: nowrap;
}
.cc-action-btn:hover          { border-color: var(--blue);  color: var(--blue);  background: var(--blue-lt); }
.cc-action-btn--warn:hover    { border-color: var(--amber); color: #b45309;      background: var(--amber-lt); }
.cc-action-btn--danger:hover  { border-color: var(--red);   color: var(--red);   background: var(--red-lt); }
.cc-action-btn--success:hover { border-color: var(--green); color: var(--green); background: var(--green-lt); }

/* Alerts */
.creditor-alert {
    border-radius: 14px; padding: 14px 18px; margin-bottom: 16px;
    display: flex; align-items: flex-start; gap: 12px; border: 1px solid;
    animation: fadeUp .3s ease both;
}
.creditor-alert--success { background: var(--green-lt); border-color: rgba(16,185,129,.3); }
.creditor-alert--error   { background: var(--red-lt);   border-color: rgba(244,63,94,.3); }
.creditor-alert-icon {
    width: 36px; height: 36px; border-radius: 8px;
    display: grid; place-items: center; font-size: 16px; flex-shrink: 0;
}
.creditor-alert-title { font-size: 13px; font-weight: 700; margin-bottom: 2px; }

/* Pagination */
.creditor-pagination-bar {
    display: flex; align-items: center; justify-content: space-between;
    margin-top: 22px; flex-wrap: wrap; gap: 12px;
}
.creditor-pagination-info { font-size: 13px; color: var(--text-3); }
.creditor-pagination { display: flex; gap: 4px; list-style: none; margin: 0; padding: 0; }
.creditor-pagination li a,
.creditor-pagination li span {
    display: grid; place-items: center; width: 36px; height: 36px;
    border-radius: 8px; font-size: 13px; font-weight: 600;
    text-decoration: none; border: 1.5px solid var(--border);
    background: var(--surface); color: var(--text-2);
    transition: all .15s; cursor: pointer;
}
.creditor-pagination li a:hover {
    border-color: var(--blue); color: var(--blue); background: var(--blue-lt);
}
.creditor-pagination li.active a {
    background: var(--grad); color: #fff; border-color: transparent;
    box-shadow: 0 3px 8px rgba(64,153,255,.3);
}
.creditor-pagination li.disabled span { color: var(--text-3); cursor: not-allowed; }

/* Empty state */
.creditor-empty {
    background: var(--surface); border: 1.5px dashed var(--border);
    border-radius: var(--r); text-align: center; padding: 60px 20px; color: var(--text-3);
}
.creditor-empty i { font-size: 40px; margin-bottom: 14px; opacity: .35; display: block; }
.creditor-empty p { font-size: 14px; }

/* Animations */
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}
<?php for ($i = 1; $i <= 10; $i++): ?>
.creditor-card:nth-child(<?= $i ?>) { animation-delay: <?= ($i * 0.035) ?>s; }
<?php endfor; ?>

@media (max-width: 640px) {
    .creditor-card__body { grid-template-columns: 1fr; }
    .creditor-card__right { align-items: flex-start; flex-direction: row; flex-wrap: wrap; }
    .cc-amount { text-align: left; }
    .cc-amount__value { font-size: 16px; }
    .creditor-card__name { max-width: 200px; }
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
                        <div class="creditor-page-header">
                            <div class="creditor-page-title">
                                <div class="creditor-page-title-icon">
                                    <i class="fas fa-handshake"></i>
                                </div>
                                <div>
                                    <h1><?= __('creditors_management') ?></h1>
                                    <p><?= __('manage_your_creditors_and_track_payments') ?></p>
                                </div>
                            </div>
                            <div class="creditor-header-actions">
                                <button class="btn-creditor-primary" data-toggle="modal" data-target="#addCreditorModal">
                                    <i class="fas fa-plus"></i> <?= __('add_new_creditor') ?>
                                </button>
                            </div>
                        </div>
                                
                        <?php if (isset($success_message)): ?>
                        <div class="creditor-alert creditor-alert--success">
                            <div class="creditor-alert-icon" style="background:rgba(16,185,129,.2);color:var(--green);"><i class="fas fa-check-circle"></i></div>
                            <div>
                                <div class="creditor-alert-title" style="color:var(--green);"><?= __('success') ?></div>
                                <div style="font-size:13px;color:var(--text-1);"><?php echo h($success_message); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($error_message)): ?>
                        <div class="creditor-alert creditor-alert--error">
                            <div class="creditor-alert-icon" style="background:rgba(244,63,94,.2);color:var(--red);"><i class="fas fa-exclamation-circle"></i></div>
                            <div>
                                <div class="creditor-alert-title" style="color:var(--red);"><?= __('error') ?></div>
                                <div style="font-size:13px;color:var(--text-1);"><?php echo h($error_message); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                                
                        <!-- SEARCH BAR -->
                        <div class="creditor-search-bar">
                            <div class="creditor-search-input-wrap">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchInput" class="creditor-search-input"
                                       placeholder="<?= __('search_by_name_email_or_phone') ?>"
                                       value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>

                        <!-- RESULTS BAR -->
                        <div class="creditor-results-bar">
                            <p class="creditor-results-count">
                                <?php
                                $start_record = min(($current_page - 1) * $items_per_page + 1, $total_count);
                                $end_record = min($current_page * $items_per_page, $total_count);
                                ?>
                                <?= __('showing') ?>
                                <strong><?= $total_count > 0 ? $start_record : 0 ?>–<?= $end_record ?></strong>
                                <?= __('of') ?> <strong><?= $total_count ?></strong> <?= __('entries') ?>
                            </p>
                            <div style="display:flex;gap:8px;">
                                <a href="creditors.php?status=active<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="cc-action-btn" style="<?= $status_filter === 'active' ? 'border-color:var(--green);color:var(--green);background:var(--green-lt);' : '' ?>">
                                    <i class="fas fa-user-check"></i> <?= __('active') ?> (<?= $active_count ?>)
                                </a>
                                <a href="creditors.php?status=inactive<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" class="cc-action-btn" style="<?= $status_filter === 'inactive' ? 'border-color:var(--red);color:var(--red);background:var(--red-lt);' : '' ?>">
                                    <i class="fas fa-user-minus"></i> <?= __('inactive') ?> (<?= $inactive_count ?>)
                                </a>
                            </div>
                        </div>

                        <!-- CREDITOR CARDS -->
                        <?php if (empty($creditors)): ?>
                        <div class="creditor-empty">
                            <i class="fas fa-inbox"></i>
                            <p><?= $status_filter === 'active' ? __('add_new_creditors_to_start_tracking_your_credits') : __('deactivated_creditors_will_appear_here') ?></p>
                        </div>
                        <?php else: ?>

                        <div class="creditor-card-list" id="creditorList">
                        <?php
                        $counter = $start_record;
                        foreach ($creditors as $creditor):
                            $stripeClass = $creditor['status'] === 'active' ? 'stripe--active' : 'stripe--inactive';
                            $badgeClass  = $creditor['status'] === 'active' ? 'badge--active' : 'badge--inactive';
                            $statusIcon  = $creditor['status'] === 'active' ? 'fa-check-circle' : 'fa-times-circle';
                        ?>
                        <div class="creditor-card">
                            <div class="creditor-card__stripe <?= $stripeClass ?>"></div>
                            <div class="creditor-card__body">
                                <div class="creditor-card__left">
                                    <div class="creditor-card__top">
                                        <span class="creditor-card__counter">#<?= str_pad($counter, 3, '0', STR_PAD_LEFT) ?></span>
                                        <span class="creditor-card__name"><?= htmlspecialchars($creditor['name']) ?></span>
                                        <span class="cc-badge <?= $badgeClass ?>">
                                            <span class="cc-badge-dot" style="background:<?= $creditor['status'] === 'active' ? 'var(--green)' : 'var(--red)' ?>"></span>
                                            <?= ucfirst(__($creditor['status'])) ?>
                                        </span>
                                    </div>
                                    <div class="creditor-card__meta">
                                        <?php if (!empty($creditor['email'])): ?>
                                        <span class="cc-pill">
                                            <i class="fas fa-envelope"></i>
                                            <?= htmlspecialchars($creditor['email']) ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if (!empty($creditor['phone'])): ?>
                                        <span class="cc-pill">
                                            <i class="fas fa-phone"></i>
                                            <?= htmlspecialchars($creditor['phone']) ?>
                                        </span>
                                        <?php endif; ?>
                                        <span class="cc-pill">
                                            <i class="fas fa-money-bill-wave"></i>
                                            <?= htmlspecialchars($creditor['currency']) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="creditor-card__right">
                                    <div class="cc-amount">
                                        <div class="cc-amount__label"><?= __('balance') ?></div>
                                        <div class="cc-amount__value">
                                            <span class="cc-amount__currency"><?= htmlspecialchars($creditor['currency']) ?></span>
                                            <?= number_format($creditor['balance'], 2) ?>
                                        </div>
                                    </div>
                                    <div class="creditor-card__actions">
                                        <button class="cc-action-btn cc-action-btn--success" data-toggle="modal" data-target="#paymentModal_<?= h($creditor['id']) ?>" title="<?= __('process_payment') ?>">
                                            <i class="fas fa-credit-card"></i> <?= __('pay') ?>
                                        </button>
                                        <button class="cc-action-btn" data-toggle="modal" data-target="#transactionsModal_<?= h($creditor['id']) ?>" title="<?= __('view_transactions') ?>">
                                            <i class="fas fa-list"></i> <?= __('transactions') ?>
                                        </button>
                                        <a href="../api/creditor/print_creditor_statement.php?id=<?= h($creditor['id']) ?>" class="cc-action-btn" target="_blank" title="<?= __('print_statement') ?>">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <button class="cc-action-btn" data-toggle="modal" data-target="#editCreditorModal_<?= h($creditor['id']) ?>" title="<?= __('edit_creditor') ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($creditor['status'] === 'active'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="creditor_id" value="<?= h($creditor['id']) ?>">
                                            <button type="submit" name="deactivate_creditor" value="1" class="cc-action-btn cc-action-btn--danger" title="<?= __('deactivate_creditor') ?>" onclick="return confirm('<?= __('are_you_sure_you_want_to_deactivate_this_creditor') ?>');">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="creditor_id" value="<?= h($creditor['id']) ?>">
                                            <button type="submit" name="activate_creditor" value="1" class="cc-action-btn cc-action-btn--success" title="<?= __('activate_creditor') ?>">
                                                <i class="fas fa-check-circle"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        <?php if ($isAdmin): ?>
                                        <button class="cc-action-btn cc-action-btn--danger" data-toggle="modal" data-target="#deleteCreditorModal_<?= h($creditor['id']) ?>" title="<?= __('delete_creditor') ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
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
                        <div class="creditor-pagination-bar">
                            <p class="creditor-pagination-info">
                                <?= __('showing') ?>
                                <?= $total_count > 0 ? $start_record : 0 ?>
                                <?= __('to') ?>
                                <?= $end_record ?>
                                <?= __('of') ?>
                                <?= $total_count ?> <?= __('entries') ?>
                            </p>
                            <ul class="creditor-pagination">
                                <li class="<?= $current_page <= 1 ? 'disabled' : '' ?>">
                                    <?php if ($current_page <= 1): ?>
                                    <span>&laquo;</span>
                                    <?php else: ?>
                                    <a href="?page=<?= $current_page - 1 ?>&status=<?= $status_filter ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">&laquo;</a>
                                    <?php endif; ?>
                                </li>
                                <?php
                                $startPage = max(1, $current_page - 2);
                                $endPage   = min($total_pages, $current_page + 2);
                                for ($i = $startPage; $i <= $endPage; $i++):
                                ?>
                                <li class="<?= $i == $current_page ? 'active' : '' ?>">
                                    <a href="?page=<?= $i ?>&status=<?= $status_filter ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"><?= $i ?></a>
                                </li>
                                <?php endfor; ?>
                                <li class="<?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                                    <?php if ($current_page >= $total_pages): ?>
                                    <span>&raquo;</span>
                                    <?php else: ?>
                                    <a href="?page=<?= $current_page + 1 ?>&status=<?= $status_filter ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">&raquo;</a>
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

    


    <script src="../js/creditor/modal_init.js"></script>

    <script src="../js/creditor/currency_check.js"></script>

<?php foreach ($creditors as $creditor): ?>
    <!-- Transactions Modal -->
    <div class="modal fade" id="transactionsModal_<?php echo h($creditor['id']); ?>" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-gradient-info text-white border-0">
                    <h5 class="modal-title"><?= __('transactions') ?> - <?php echo htmlspecialchars($creditor['name']); ?></h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th><?= __("date") ?></th>
                                    <th><?= __("description") ?></th>
                                    <th><?= __("receipt") ?></th>
                                    <th><?= __("amount") ?></th>
                                    <th class="text-center"><?= __("actions") ?></th>
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
                                        $displayAmount = number_format($transaction['amount'], 2) . ' ' . $transaction['currency'];
                                        echo '<tr>';
                                        echo '<td>' . date('M d, Y', strtotime($transaction['payment_date'])) . '</td>';
                                        echo '<td>' . htmlspecialchars($transaction['description'] ?? '') . '</td>';
                                        echo '<td>' . htmlspecialchars($transaction['reference_number'] ?? '') . '</td>';
                                        if ($transaction['transaction_type'] == 'debit') {
                                            echo '<td>Paid ' . $displayAmount . '</td>';
                                        } else {
                                            echo '<td>Received ' . $displayAmount . '</td>';
                                        }
                                        echo '<td class="text-center text-nowrap">';
                                        echo '<button type="button" class="btn btn-primary btn-sm mr-1" data-toggle="modal" data-target="#editTransactionModal_' . $transaction['id'] . '" title="' . __("edit") . '"><i class="fas fa-edit"></i></button>';
                                        echo '<button class="btn btn-info btn-sm mr-1" title="' . __("print_receipt") . '" onclick="printReceipt(\'' . $transaction['id'] . '\')"><i class="fas fa-print"></i></button>';
                                        if ($isAdmin) {
                                            echo '<form method="POST" class="d-inline" onsubmit="return confirm(\'' . __("are_you_sure_you_want_to_delete_this_transaction_this_will_reverse_the_payment") . '\');">';
                                            echo '<input type="hidden" name="csrf_token" value="' . h($_SESSION['csrf_token']) . '">';
                                            echo '<input type="hidden" name="transaction_id" value="' . $transaction['id'] . '">';
                                            echo '<input type="hidden" name="creditor_id" value="' . $creditor['id'] . '">';
                                            echo '<input type="hidden" name="amount" value="' . $transaction['amount'] . '">';
                                            echo '<input type="hidden" name="currency" value="' . $transaction['currency'] . '">';
                                            echo '<button type="submit" name="delete_transaction" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>';
                                            echo '</form>';
                                        }
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="5" class="text-center">' . __("no_transactions_found") . '</td></tr>';
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
    <div class="modal fade" id="paymentModal_<?php echo h($creditor['id']); ?>" tabindex="-1" role="dialog" aria-labelledby="paymentModalLabel_<?php echo h($creditor['id']); ?>" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-gradient-primary text-white border-0">
                    <h5 class="modal-title" id="paymentModalLabel_<?php echo h($creditor['id']); ?>"><?= __('process_payment') ?></h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <!-- CSRF Protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                    
                    <div class="modal-body">
                        <input type="hidden" name="creditor_id" value="<?php echo h($creditor['id']); ?>">
                        <input type="hidden" name="creditor_currency" value="<?php echo h($creditor['currency']); ?>">
                        
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label class="form-label"><?= __('creditor_name') ?></label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($creditor['name']); ?>" readonly>
                            </div>
                            <div class="form-group col-md-6">
                                <label class="form-label"><?= __('current_balance') ?></label>
                                <input type="text" class="form-control" value="<?php echo number_format($creditor['balance'], 2) . ' ' . $creditor['currency']; ?>" readonly>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label class="form-label"><?= __("payment_amount") ?></label>
                                <input type="number" class="form-control" name="amount" step="0.000001" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label class="form-label"><?= __("payment_currency") ?></label>
                                <select class="form-control" name="currency" required onchange="checkCreditorCurrency(this, '<?php echo h($creditor['currency']); ?>', '<?php echo h($creditor['id']); ?>')">
                                    <option value="USD" <?php echo h($creditor['currency']) == 'USD' ? 'selected' : ''; ?>>USD</option>
                                    <option value="AFS" <?php echo h($creditor['currency']) == 'AFS' ? 'selected' : ''; ?>>AFS</option>
                                    <option value="EUR" <?php echo h($creditor['currency']) == 'EUR' ? 'selected' : ''; ?>>EUR</option>
                                    <option value="DARHAM" <?php echo h($creditor['currency']) == 'DARHAM' ? 'selected' : ''; ?>>AED</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Exchange Rate Field - Initially Hidden -->
                        <div class="form-group" id="exchangeRateDiv_<?php echo h($creditor['id']); ?>" style="display: none;">
                            <label class="form-label"><?= __('exchange_rate') ?> (1 <span id="selectedCreditorCurrency_<?php echo h($creditor['id']); ?>"><?php echo h($creditor['currency']); ?></span> = ? <span id="creditorCurrency_<?php echo h($creditor['id']); ?>"><?php echo h($creditor['currency']); ?></span>)</label>
                            <input type="number" class="form-control" name="exchange_rate" id="exchangeRate_<?php echo h($creditor['id']); ?>" step="0.000001" placeholder="<?= __('enter_exchange_rate') ?>">
                             <small class="form-text text-muted" id="exchangeRateHelp_<?php echo h($creditor['id']); ?>"><?= __('enter_the_exchange_rate_to_convert_from_payment_currency_to_creditor_s_currency') ?></small>
                         </div>
                        
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label class="form-label"><?= __("description") ?></label>
                                <textarea class="form-control" name="description" rows="2"></textarea>
                            </div>
                            <div class="form-group col-md-6">
                                <label class="form-label"><?= __("receipt") ?></label>
                                <input type="text" class="form-control" name="receipt" placeholder="<?= __('receipt_number') ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label class="form-label"><?= __("payment_date") ?></label>
                                <input type="date" class="form-control" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label"><?= __("paid_from") ?></label>
                            <select class="form-control" name="paid_to" required>
                                <?php foreach ($main_accounts as $account): ?>
                                    <option value="<?php echo h($account['id']); ?>"><?php echo htmlspecialchars($account['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-0">
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
                                 <option value="DARHAM" <?php echo h($creditor['currency']) == 'DARHAM' ? 'selected' : ''; ?>>AED</option>
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
    // Fetch both main account initial transaction and creditor transactions for this creditor
    $transStmt = $pdo->prepare("
        SELECT 
            'initial' as type,
            mt.id,
            mt.created_at as payment_date,
            mt.amount,
            mt.currency,
            mt.description,
            NULL as reference_number
        FROM main_account_transactions mt
        WHERE mt.transaction_of = 'creditor'
        AND mt.reference_id = ?
        AND mt.type = 'credit'
        AND mt.tenant_id = ?
        AND mt.branch_id = ?
        
        UNION ALL
        
        SELECT 
            'payment' as type,
            ct.id,
            ct.payment_date,
            ct.amount,
            ct.currency,
            ct.description,
            ct.reference_number
        FROM creditor_transactions ct
        WHERE ct.creditor_id = ?
        AND ct.tenant_id = ?
        AND ct.branch_id = ?
        
        ORDER BY payment_date DESC
    ");
    $transStmt->bindParam(1, $creditor['id'], PDO::PARAM_INT);
    $transStmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
    $transStmt->bindParam(3, $branch_id, PDO::PARAM_INT);
    $transStmt->bindParam(4, $creditor['id'], PDO::PARAM_INT);
    $transStmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
    $transStmt->bindParam(6, $branch_id, PDO::PARAM_INT);
    $transStmt->execute();
    $transResult = $transStmt->fetchAll();
    
    foreach ($transResult as $transaction):
?>
    <!-- Edit Transaction Modal -->
    <div class="modal fade" id="editTransactionModal_<?php echo $transaction['id']; ?>" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <?php if ($transaction['type'] === 'initial'): ?>
                            <?= __("view_initial_transaction") ?>
                        <?php else: ?>
                            <?= __("edit_transaction") ?>
                        <?php endif; ?>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <?php if ($transaction['type'] === 'initial'): ?>
                        <!-- Initial Transaction Display -->
                        <div class="alert alert-info">
                            <i class="feather icon-info mr-2"></i>
                            This is the initial credit transaction created when the creditor was added.
                        </div>
                        <div class="form-group">
                            <label><?= __("amount") ?></label>
                            <input type="text" class="form-control" value="<?php echo $transaction['amount']; ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label><?= __("currency") ?></label>
                            <input type="text" class="form-control" value="<?php echo $transaction['currency']; ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label><?= __("created_date") ?></label>
                            <input type="text" class="form-control" value="<?php echo (new DateTime($transaction['payment_date']))->format('d/m/Y H:i:s'); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label><?= __("description") ?></label>
                            <textarea class="form-control" rows="3" disabled><?php echo htmlspecialchars($transaction['description'] ?? ''); ?></textarea>
                        </div>
                    <?php else: ?>
                        <!-- Payment Transaction Edit -->
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
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __("cancel") ?></button>
                    <?php if ($transaction['type'] === 'initial'): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure? This will delete the initial creditor transaction.');">
                            <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="delete_initial_transaction" value="1">
                            <input type="hidden" name="transaction_id" value="<?php echo $transaction['id']; ?>">
                            <input type="hidden" name="creditor_id" value="<?php echo $creditor['id']; ?>">
                            <button type="submit" class="btn btn-danger"><?= __("delete_initial_transaction") ?></button>
                        </form>
                    <?php else: ?>
                        <button type="button" class="btn btn-primary" onclick="updateCreditorTransaction(<?php echo $transaction['id']; ?>)"><?= __("save_changes") ?></button>
                    <?php endif; ?>
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
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="../js/creditor/transaction_update.js"></script>
<script src="../js/creditor/print_receipt.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    var query = this.value.trim();
                    var url = new URL(window.location.href);
                    if (query) {
                        url.searchParams.set('search', query);
                    } else {
                        url.searchParams.delete('search');
                    }
                    url.searchParams.set('page', '1');
                    window.location.href = url.toString();
                }
            });
        }
    });
</script>
</body>
</html> 

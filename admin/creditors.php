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

// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
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
        <div class="pcoded-wrapper">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <div class="container mt-4">
                                <!-- Page Header -->
                                <div class="page-header card">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h5 class="mb-0"><i class="feather icon-users mr-2"></i><?= __('creditors_management') ?></h5>
                                            <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;">Manage your creditors and track payments</p>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <button type="button" class="btn btn-success" data-toggle="modal" data-target="#addCreditorModal">
                                                <i class="feather icon-plus mr-1"></i><?= __('add_new_creditor') ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (isset($success_message)): ?>
                                    <div class="alert alert-success"><?php echo h($success_message); ?></div>
                                <?php endif; ?>
                                
                                <?php if (isset($error_message)): ?>
                                    <div class="alert alert-danger"><?php echo h($error_message); ?></div>
                                <?php endif; ?>
                                
                                <!-- Creditors Summary Section -->
                                <?php if (!empty($currency_totals)): ?>
                                <div class="row mb-4">
                                    <div class="col-md-4">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5><i class="feather icon-bar-chart-2 mr-2"></i><?= __('creditors_summary') ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="text-center mb-4">
                                                    <div class="h2 font-weight-bold text-primary">
                                                        <i class="feather icon-users mr-2"></i><?php echo $active_count; ?>
                                                        <span class="text-muted h4">/ <?php echo $active_count + $inactive_count; ?></span>
                                                    </div>
                                                    <p class="text-muted mb-0"><?= __('creditors_count') ?></p>
                                                </div>

                                                <div class="progress mb-4" style="height: 30px; border-radius: 15px;">
                                                    <div class="progress-bar <?php echo $active_count >= ($active_count + $inactive_count) * 0.9 ? 'bg-danger' : ($active_count >= ($active_count + $inactive_count) * 0.75 ? 'bg-warning' : 'bg-success'); ?>"
                                                         role="progressbar"
                                                         style="width: <?php echo ($active_count + $inactive_count) > 0 ? min(100, ($active_count / ($active_count + $inactive_count)) * 100) : 0; ?>%; border-radius: 15px;">
                                                        <span class="font-weight-bold"><?php echo ($active_count + $inactive_count) > 0 ? round(($active_count / ($active_count + $inactive_count)) * 100) : 0; ?>%</span>
                                                    </div>
                                                </div>

                                                <hr class="my-4">

                                                <div class="row text-center">
                                                    <div class="col-6">
                                                        <div class="h4 mb-1 font-weight-bold text-info"><?php echo $active_count; ?></div>
                                                        <small class="text-muted"><i class="feather icon-user-check mr-1"></i><?= __('active_creditors') ?></small>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="h4 mb-1 font-weight-bold text-success"><?php echo $inactive_count; ?></div>
                                                        <small class="text-muted"><i class="feather icon-user-minus mr-1"></i><?= __('inactive_creditors') ?></small>
                                                    </div>
                                                </div>

                                                <hr class="my-4">

                                                <div class="text-center">
                                                    <span class="badge badge-info badge-pill px-3 py-2 h6">
                                                        <i class="feather icon-package mr-1"></i><?= __('status') ?>: <?= ucfirst($status_filter) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-8">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5><i class="feather icon-dollar-sign mr-2"></i><?= __('total_credits_by_currency') ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <table class="table table-borderless">
                                                    <tbody>
                                                        <?php foreach ($currency_totals as $currency => $total): ?>
                                                        <tr>
                                                            <td class="py-3"><i class="feather icon-credit-card mr-2 text-primary"></i><?php echo htmlspecialchars($currency); ?></td>
                                                            <td class="text-right py-3 font-weight-bold text-success h5"><?php echo number_format($total, 2); ?> <?php echo htmlspecialchars($currency); ?></td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                                <div class="alert alert-light border mt-3">
                                                    <p class="text-muted mb-0 text-center">
                                                        <i class="feather icon-info mr-2"></i><?= __('total_credits_across_all_creditors') ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Status Toggle Tabs -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5><i class="feather icon-filter mr-2"></i><?= __('status_filter') ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <ul class="nav nav-pills card-header-pills flex-column flex-sm-row" id="creditorTabs" role="tablist">
                                                    <li class="nav-item flex-fill text-center">
                                                        <a class="nav-link <?php echo h($status_filter) === 'active' ? 'active' : ''; ?> rounded-pill" href="creditors.php">
                                                            <i class="feather icon-user-check mr-2"></i>
                                                            <span><?= __('active_creditors') ?></span>
                                                            <span class="badge badge-light ml-2"><?php echo $active_count; ?></span>
                                                        </a>
                                                    </li>
                                                    <li class="nav-item flex-fill text-center">
                                                        <a class="nav-link <?php echo h($status_filter) === 'inactive' ? 'active' : ''; ?> rounded-pill" href="creditors.php?status=inactive">
                                                            <i class="feather icon-user-minus mr-2"></i>
                                                            <span><?= __('inactive_creditors') ?></span>
                                                            <span class="badge badge-light ml-2"><?php echo $inactive_count; ?></span>
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Creditors Table -->
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">
                                            <i class="feather icon-users mr-2"></i><?= __($status_filter . '_creditors') ?>
                                        </h5>
                                        <button type="button" class="btn btn-success d-flex align-items-center" data-toggle="modal" data-target="#addCreditorModal">
                                            <i class="feather icon-plus-circle mr-2"></i> <?= __("add_new_creditor") ?>
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th><?= __("name") ?></th>
                                                        <th><?= __("email") ?></th>
                                                        <th><?= __("phone") ?></th>
                                                        <th><?= __("address") ?></th>
                                                        <th><?= __("balance") ?></th>
                                                        <th><?= __("currency") ?></th>
                                                        <th class="text-center"><?= __("actions") ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (count($creditors) > 0): ?>
                                                        <?php foreach ($creditors as $creditor): ?>
                                                            <tr>
                                                                <td>
                                                                    <div class="d-flex align-items-center">

                                                                        <div class="ml-3">
                                                                            <h6 class="mb-0"><?php echo htmlspecialchars($creditor['name']); ?></h6>
                                                                            <small class="text-muted">ID: <?php echo h($creditor['id']); ?></small>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <?php if (!empty($creditor['email'])): ?>
                                                                        <div class="d-flex align-items-center">
                                                                            <i class="feather icon-mail text-muted mr-2"></i>
                                                                            <?php echo htmlspecialchars($creditor['email']); ?>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">-</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php if (!empty($creditor['phone'])): ?>
                                                                        <div class="d-flex align-items-center">
                                                                            <i class="feather icon-phone text-muted mr-2"></i>
                                                                            <?php echo htmlspecialchars($creditor['phone']); ?>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">-</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php if (!empty($creditor['address'])): ?>
                                                                        <div class="d-flex align-items-center">
                                                                            <i class="feather icon-map-pin text-muted mr-2"></i>
                                                                            <?php echo htmlspecialchars($creditor['address']); ?>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">-</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <div class="d-flex align-items-center">
                                                                        <span class="font-weight-medium <?php echo $creditor['balance'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                                                            <?php echo number_format($creditor['balance'], 2); ?>
                                                                        </span>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <span class="">
                                                                        <?php echo htmlspecialchars($creditor['currency']); ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <div class="d-flex justify-content-center">
                                                                        <div class="dropdown">
                                                                            <button class="btn btn-icon btn-primary dropdown-toggle" 
                                                                                    type="button" 
                                                                                    id="dropdownMenu_<?php echo h($creditor['id']); ?>" 
                                                                                    data-toggle="dropdown" 
                                                                                    aria-haspopup="true" 
                                                                                    aria-expanded="false"
                                                                                    title="<?= __("actions") ?>">
                                                                                <i class="feather icon-more-vertical"></i>
                                                                            </button>
                                                                            <div class="dropdown-menu dropdown-menu-right" 
                                                                                 aria-labelledby="dropdownMenu_<?php echo h($creditor['id']); ?>">
                                                                                <button type="button" class="dropdown-item" 
                                                                                        data-toggle="modal" 
                                                                                        data-target="#paymentModal_<?php echo h($creditor['id']); ?>">
                                                                                    <i class="feather icon-credit-card"></i> <?= __("process_payment") ?>
                                                                                </button>
                                                                                <button type="button" class="dropdown-item" 
                                                                                        data-toggle="modal" 
                                                                                        data-target="#transactionsModal_<?php echo h($creditor['id']); ?>">
                                                                                    <i class="feather icon-list"></i> <?= __("view_transactions") ?>
                                                                                </button>
                                                                                <a href="../api/creditor/print_creditor_statement.php?id=<?php echo h($creditor['id']); ?>" 
                                                                                   class="dropdown-item"
                                                                                   target="_blank">
                                                                                    <i class="feather icon-printer"></i> <?= __("print_statement") ?>
                                                                                </a>
                                                                                <div class="dropdown-divider"></div>
                                                                                <button type="button" class="dropdown-item" 
                                                                                        data-toggle="modal" 
                                                                                        data-target="#editCreditorModal_<?php echo h($creditor['id']); ?>">
                                                                                    <i class="feather icon-edit-2"></i> <?= __("edit_creditor") ?>
                                                                                </button>
                                                                                <button type="button" class="dropdown-item text-danger" 
                                                                                        data-toggle="modal" 
                                                                                        data-target="#deleteCreditorModal_<?php echo h($creditor['id']); ?>">
                                                                                    <i class="feather icon-trash-2"></i> <?= __("delete_creditor") ?>
                                                                                </button>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="7">
                                                                <div class="empty-state">
                                                                    <i class="feather icon-users"></i>
                                                                    <h5 class="mt-3"><?= __("no_creditors_found") ?></h5>
                                                                    <p class="text-muted">
                                                                        <?php if ($status_filter === 'active'): ?>
                                                                            <?= __("add_new_creditors_to_start_tracking_your_credits") ?>
                                                                        <?php else: ?>
                                                                            <?= __("deactivated_creditors_will_appear_here") ?>
                                                                        <?php endif; ?>
                                                                    </p>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
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
                                        echo '<form method="POST" onsubmit="return confirm(\'' . __("are_you_sure_you_want_to_delete_this_transaction_this_will_reverse_the_payment") . '\');">';
                                        echo '<input type="hidden" name="csrf_token" value="' . h($_SESSION['csrf_token']) . '">';
                                        echo '<input type="hidden" name="transaction_id" value="' . $transaction['id'] . '">';
                                        echo '<input type="hidden" name="creditor_id" value="' . $creditor['id'] . '">';
                                        echo '<input type="hidden" name="amount" value="' . $transaction['amount'] . '">';
                                        echo '<input type="hidden" name="currency" value="' . $transaction['currency'] . '">';
                                        echo '<button type="submit" name="delete_transaction" class="btn btn-danger btn-sm"><i class="feather icon-trash"></i> ' . __("delete") . '</button>';
                                        echo '</form>';
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
                            <input type="number" class="form-control" name="balance" step="0.01" value="<?php echo h($creditor['balance']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label><?= __("currency") ?> *</label>
                            <select class="form-control" name="currency" required>
                                <option value="USD" <?php echo h($creditor['currency']) == 'USD' ? 'selected' : ''; ?>>USD</option>
                                <option value="AFS" <?php echo h($creditor['currency']) == 'AFS' ? 'selected' : ''; ?>>AFS</option>
                                <option value="EUR" <?php echo h($creditor['currency']) == 'EUR' ? 'selected' : ''; ?>>EUR</option>
                                <option value="DARHAM" <?php echo h($creditor['currency']) == 'DARHAM' ? 'selected' : ''; ?>>DARHAM</option>
                            </select>
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

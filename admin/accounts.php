<?php
// Include security module
require_once 'security.php';

// Include secure headers helper
require_once 'includes/set_secure_headers.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');



// Note: Client accounts are fetched later with a more detailed query

// Fetch main account balances
$mainAccountQuery = "SELECT * FROM main_account WHERE tenant_id = ? And branch_id = ?";
$stmt = $pdo->prepare($mainAccountQuery);
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$mainAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch client accounts balances
$clientAccountQuery = "SELECT * FROM clients where status = 'active' AND tenant_id = ? And branch_id = ?";
$stmt = $pdo->prepare($clientAccountQuery);
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$clientAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch supplier accounts with their balances
    $supplierQuery = "
    SELECT sa.id, sa.name AS supplier_name, sa.currency, sa.balance, sa.updated_at, sa.status
    FROM suppliers sa where status = 'active' AND tenant_id = ? And branch_id = ?";
$supplierStmt = $pdo->prepare($supplierQuery);
$supplierStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$supplierStmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$supplierStmt->execute();
$supplier = $supplierStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch client accounts with their balances
$clientQuery = "
SELECT cl.id, cl.name, cl.usd_balance, cl.afs_balance, cl.updated_at, cl.status
FROM clients cl where status = 'active' AND tenant_id = ? And branch_id = ?";
$clientStmt = $pdo->prepare($clientQuery);
$clientStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$clientStmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$clientStmt->execute();
$clientAccounts = $clientStmt->fetchAll(PDO::FETCH_ASSOC);

// Include dashboard handler for low balance alerts
require_once '../api/dashboard/supplier_notification.php';
require_once '../api/dashboard/client_notification.php';
?>

<?php include '../includes/header.php'; ?>
<link href="../css/account/styles.css" rel="stylesheet">
<!-- Date Range Picker -->
<link rel="stylesheet" type="text/css" href="../assets/plugins/daterangepicker/daterangepicker.css" />

    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <!-- [ breadcrumb ] start -->
                    <div class="page-header">
                        <div class="page-block">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <div class="page-header-title">
                                        <h5 class="m-b-10"><?= __('accounts_management') ?></h5>
                                    </div>
                                    <ul class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                        <li class="breadcrumb-item"><?= __('accounts') ?></li>
                                    </ul>
                                </div>
                                <div class="col-md-6 text-right">
                                    <button class="btn btn-info" type="button" id="watchTutorialsBtn" data-toggle="modal" data-target="#accountsTutorialsModal">
                                        <i class="feather icon-play-circle mr-1"></i>Watch Tutorials
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [ breadcrumb ] end -->

                    <!-- [ Search & Filter Section ] start -->
                    <div class="filter-container animated-item">
                        <div class="row align-items-center filter-row">
                            <div class="col-md-6 mb-3 mb-md-0 filter-col">
                                <div class="search-container">
                                    <i class="feather icon-search search-icon"></i>
                                    <input type="text" id="accountSearchInput" class="search-input" placeholder="<?= __('search_accounts') ?>...">
                                </div>
                            </div>
                            <div class="col-md-3 mb-3 mb-md-0 filter-col">
                                <select class="form-control filter-control" id="accountTypeFilter">
                                    <option value="all"><?= __('all_account_types') ?></option>
                                    <option value="main"><?= __('main_accounts') ?></option>
                                    <option value="supplier"><?= __('supplier_accounts') ?></option>
                                    <option value="client"><?= __('client_accounts') ?></option>
                                </select>
                            </div>
                            <div class="col-md-3 filter-col">
                                <select class="form-control filter-control" id="statusFilter">
                                    <option value="all"><?= __('all_statuses') ?></option>
                                    <option value="active"><?= __('active') ?></option>
                                    <option value="inactive"><?= __('inactive') ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <!-- [ Search & Filter Section ] end -->

                    <div class="main-body">
                         <div class="page-wrapper">

                             <!-- Low Supplier Balance Alert -->
                             <?php if (!empty($suppliersWithLowBalance)): ?>
                             <div class="row mb-4">
                                 <div class="col-md-12">
                                     <div class="alert alert-warning alert-dismissible fade show border-left-warning" role="alert">
                                         <div class="d-flex align-items-start">
                                             <i class="feather icon-alert-triangle mr-3" style="font-size: 20px; margin-top: 2px;"></i>
                                             <div class="flex-grow-1">
                                                 <h5 class="alert-heading mb-2">Low Supplier Balance Alert</h5>
                                                 <p class="mb-2">The following suppliers have low account balances:</p>
                                                 <div class="supplier-alerts">
                                                     <?php foreach ($suppliersWithLowBalance as $lowBalanceSupplier): ?>
                                                         <div class="alert alert-sm mb-2" style="background-color: rgba(255,193,7,0.1); border-left: 3px solid #ffc107; padding: 8px 12px;">
                                                             <strong><?= htmlspecialchars($lowBalanceSupplier['name']) ?></strong><br>
                                                             <small class="text-muted">
                                                                 <?php
                                                                 $currency_symbol = ($lowBalanceSupplier['currency'] === 'USD') ? '$' : '؋';
                                                                 $threshold = ($lowBalanceSupplier['currency'] === 'USD') ? 500 : 20000;
                                                                 $threshold_display = ($lowBalanceSupplier['currency'] === 'USD') ? '$500' : '؋20,000';
                                                                 echo $lowBalanceSupplier['currency'] . ": " . $currency_symbol . number_format($lowBalanceSupplier['balance'], 2) . " (Threshold: " . $threshold_display . ")";
                                                                 ?>
                                                             </small>
                                                         </div>
                                                     <?php endforeach; ?>
                                                 </div>
                                             </div>
                                         </div>
                                         <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                             <span aria-hidden="true">&times;</span>
                                         </button>
                                     </div>
                                 </div>
                             </div>
                             <?php endif; ?>

                             <!-- Client Low/Negative Balance Alert -->
                             <?php if (!empty($clientsWithLowBalance)): ?>
                             <div class="row mb-4">
                                 <div class="col-md-12">
                                     <div class="alert alert-danger alert-dismissible fade show border-left-danger" role="alert">
                                         <div class="d-flex align-items-start">
                                             <i class="feather icon-alert-circle mr-3" style="font-size: 20px; margin-top: 2px;"></i>
                                             <div class="flex-grow-1">
                                                 <h5 class="alert-heading mb-2">Client Balance Due Alert</h5>
                                                 <p class="mb-2">The following clients have reached the balance due thresholds:</p>
                                                 <div class="client-alerts">
                                                     <?php foreach ($clientsWithLowBalance as $lowBalanceClient): ?>
                                                         <div class="alert alert-sm mb-2" style="background-color: rgba(220,53,69,0.1); border-left: 3px solid #dc3545; padding: 8px 12px;">
                                                             <strong><?= htmlspecialchars($lowBalanceClient['name']) ?></strong><br>
                                                             <small class="text-muted">
                                                                 <?php
                                                                 $usd_threshold = -1000;
                                                                 $afs_threshold = -20000;
                                                                 $display_items = [];
                                                                 
                                                                 if ($lowBalanceClient['usd_balance'] < $usd_threshold) {
                                                                     $display_items[] = "USD: $" . number_format($lowBalanceClient['usd_balance'], 2) . " (Threshold: $-1,000)";
                                                                 }
                                                                 if ($lowBalanceClient['afs_balance'] < $afs_threshold) {
                                                                     $display_items[] = "AFS: ؋" . number_format($lowBalanceClient['afs_balance'], 2) . " (Threshold: ؋-20,000)";
                                                                 }
                                                                 
                                                                 echo implode(" | ", $display_items);
                                                                 ?>
                                                             </small>
                                                         </div>
                                                     <?php endforeach; ?>
                                                 </div>
                                             </div>
                                         </div>
                                         <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                             <span aria-hidden="true">&times;</span>
                                         </button>
                                     </div>
                                 </div>
                             </div>
                             <?php endif; ?>

                             <!-- Main Accounts Section -->
                            <div class="row">
                                <div class="col-md-12 mb-4">
                                    <div class="card shadow-lg border-0">
                                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                            <h4 class="mb-0"><i class="feather icon-briefcase mr-2"></i><?= __('internal_accounts') ?></h4>
                                            <div>
                                                <button type="button" class="btn btn-light btn-sm mr-2" data-toggle="modal" data-target="#transferModal">
                                                    <i class="feather icon-exchange"></i> <?= __('transfer_balance') ?>
                                                </button>
                                                <button id="addMainAccountBtn" class="btn btn-light btn-sm">
                                                    <i class="feather icon-plus"></i> <?= __('add_account') ?>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="row p-4">
                                                <?php foreach ($mainAccounts as $account): ?>
                                                    <div class="col-md-4 mb-4">
                                                        <div class="account-card <?= isset($account['status']) && $account['status'] === 'inactive' ? 'border-left border-danger' : '' ?>">
                                                            <div class="card-header bg-light border-bottom-0 d-flex justify-content-between align-items-start">
                                                                <div>
                                                                    <h5 class="mb-0 text-primary">
                                                                        <i class="feather icon-box mr-2"></i>
                                                                        <?= htmlspecialchars($account['name']) ?>
                                                                    </h5>
                                                                    <?php if (isset($account['account_type'])): ?>
                                                                    <span class="account-type-badge bg-<?= $account['account_type'] === 'bank' ? 'info' : 'danger' ?> mt-1" style="color: #ffffff;">
                                                                        <?= ucfirst(htmlspecialchars($account['account_type'])) ?> <?= __('account') ?>
                                                                    </span>
                                                                    <?php endif; ?>
                                                                    <?php if (isset($account['account_type']) && $account['account_type'] === 'bank' && !empty($account['bank_account_number'])): ?>
                                                                    <div class="small text-muted mt-1">
                                                                        <?php if (!empty($account['bank_account_number'])): ?>
                                                                        <span class="ml-2">Acct USD #: <?= htmlspecialchars($account['bank_account_number']) ?></span>
                                                                        <?php endif; ?>
                                                                        <?php if (!empty($account['bank_account_afs_number'])): ?>
                                                                        <span class="ml-2">Acct AFS #: <?= htmlspecialchars($account['bank_account_afs_number']) ?></span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <?php if (isset($account['status'])): ?>
                                                                <span class="status-badge bg-<?= isset($account['status']) && $account['status'] === 'inactive' ? 'danger' : 'success' ?>" style="color: #ffffff;">
                                                                    <?= isset($account['status']) ? ucfirst($account['status']) : 'Active' ?>
                                                                </span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="card-body">
                                                                <div class="row balance-row">
                                                                    <div class="col-6">
                                                                        <div class="balance-item d-flex align-items-center">
                                                                            <div class="currency-icon bg-success-light">
                                                                                <i class="fas fa-dollar-sign text-success"></i>
                                                                            </div>
                                                                            <div>
                                                                                <div class="balance-label"><?= __('usd_balance') ?></div>
                                                                                <div class="balance-value text-success">$<?= number_format($account['usd_balance'], 2) ?></div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-6">
                                                                        <div class="balance-item d-flex align-items-center">
                                                                            <div class="currency-icon bg-info-light">
                                                                                <i class="fas fa-money-bill-wave text-info"></i>
                                                                            </div>
                                                                            <div>
                                                                                <div class="balance-label"><?= __('afs_balance') ?></div>
                                                                                <div class="balance-value text-info">؋<?= number_format($account['afs_balance'], 2) ?></div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-6 mt-3">
                                                                        <div class="balance-item d-flex align-items-center">
                                                                            <div class="currency-icon bg-info-light">
                                                                                <i class="fas fa-euro-sign text-info"></i>
                                                                            </div>
                                                                            <div>
                                                                                <div class="balance-label"><?= __('euro_balance') ?></div>
                                                                                <div class="balance-value text-info">€<?= number_format($account['euro_balance'], 2) ?></div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-6 mt-3">
                                                                        <div class="balance-item d-flex align-items-center">
                                                                            <div class="currency-icon bg-warning-light">
                                                                                <i class="fas fa-money-bill-wave text-warning"></i>
                                                                            </div>
                                                                            <div>
                                                                                <div class="balance-label"><?= __('aed_balance') ?></div>
                                                                                <div class="balance-value text-warning">AED <?= number_format($account['darham_balance'], 2) ?></div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="form-group">
                                                                    <select class="form-control filter-control mb-2" id="currency-<?= $account['id'] ?>">
                                                                        <option value="USD"><?= __('usd') ?></option>
                                                                        <option value="AFS"><?= __('afs') ?></option>
                                                                        <option value="EUR"><?= __('eur') ?></option>
                                                                        <option value="DARHAM"><?= __('darham') ?></option>
                                                                    </select>
                                                                    <div class="input-group">
                                                                        <input type="number" class="form-control filter-control" id="amount-<?= $account['id'] ?>" placeholder="Enter amount">
                                                                        <div class="input-group-append">
                                                                            <button class="btn btn-primary fund-account-btn" data-account-id="<?= $account['id'] ?>">
                                                                                <i class="feather icon-plus-circle"></i> <?= __('fund') ?>
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <small class="text-muted d-block mb-3"><?= __('last_updated') ?>: <?= htmlspecialchars($account['last_updated']) ?></small>
                                                                <div class="d-flex flex-column">
                                                                    <button class="btn btn-outline-primary btn-sm mb-2 action-btn view-transactions-btn" 
                                                                            data-account-id="<?= $account['id'] ?>"
                                                                            data-account-name="<?= htmlspecialchars($account['name']) ?>">
                                                                        <i class="feather icon-list mr-1"></i> <?= __('view_transactions') ?>
                                                                    </button>
                                                                    <button class="btn btn-outline-info btn-sm mb-2 action-btn edit-main-account-btn" 
                                                                            data-account-id="<?= $account['id'] ?>"
                                                                            data-account-name="<?= htmlspecialchars($account['name']) ?>">
                                                                        <i class="feather icon-edit mr-1"></i> <?= __('edit_account') ?>
                                                                    </button>
                                                                    <button class="btn btn-outline-<?= isset($account['status']) && $account['status'] === 'active' ? 'danger' : 'success' ?> btn-sm action-btn toggle-status-btn" 
                                                                            data-account-id="<?= $account['id'] ?>"
                                                                            data-current-status="<?= isset($account['status']) ? $account['status'] : 'active' ?>">
                                                                        <i class="feather icon-<?= isset($account['status']) && $account['status'] === 'active' ? 'power' : 'check-circle' ?> mr-1"></i> 
                                                                        <?= isset($account['status']) && $account['status'] === 'active' ? __('deactivate') : __('activate') ?> <?= __('account') ?>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Supplier Accounts Section -->
                            <div class="row">
                                <div class="col-12 mb-4">
                                    <div class="modern-card">
                                        <!-- Card Header with Totals -->
                                        <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                                            <h4 class="mb-0">
                                                <i class="feather icon-users me-2"></i><?= __('supplier_accounts') ?>
                                            </h4>
                                            <!-- Add local search for supplier section -->
                                            <div class="col-md-4">
                                                <div class="search-container my-1">
                                                    <i class="feather icon-search search-icon"></i>
                                                    <input type="text" id="supplierSearchInput" class="search-input bg-white" placeholder="<?= __('search_suppliers') ?>...">
                                                </div>
                                            </div>
                                        </div>
                                        <?php
                                        $totalSupplierUSD = 0;
                                        $totalSupplierAFS = 0;
                                        $totalSupplierDueUSD = 0;
                                        $totalSupplierDueAFS = 0;
                                        foreach ($supplier as $sup) {
                                            if ($sup['currency'] === 'USD' && $sup['balance'] > 0) {
                                                $totalSupplierUSD += $sup['balance'];
                                            } else if ($sup['currency'] === 'AFS' && $sup['balance'] > 0) {
                                                $totalSupplierAFS += $sup['balance'];
                                            } else if ($sup['currency'] === 'USD' && $sup['balance'] < 0) {
                                                $totalSupplierDueUSD += $sup['balance'];
                                            } else if ($sup['currency'] === 'AFS' && $sup['balance'] < 0) {
                                                $totalSupplierDueAFS += $sup['balance'];
                                            }
                                        }
                                        ?>
                                        <!-- Totals -->
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-3 mb-3">
                                                    <div class="stat-card bg-light">
                                                        <div class="stat-icon bg-success-light mb-2">
                                                            <i class="fas fa-dollar-sign text-success"></i>
                                                        </div>
                                                        <div class="text-muted"><?= __('total_usd') ?></div>
                                                        <div class="stat-value text-success">$<?= number_format($totalSupplierUSD, 2) ?></div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <div class="stat-card bg-light">
                                                        <div class="stat-icon bg-info-light mb-2">
                                                            <i class="fas fa-money-bill-wave text-info"></i>
                                                        </div>
                                                        <div class="text-muted"><?= __('total_afs') ?></div>
                                                        <div class="stat-value text-info">؋<?= number_format($totalSupplierAFS, 2) ?></div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <div class="stat-card bg-light">
                                                        <div class="stat-icon bg-danger-light mb-2">
                                                            <i class="fas fa-dollar-sign text-danger"></i>
                                                        </div>
                                                        <div class="text-muted"><?= __('total_usd_due') ?></div>
                                                        <div class="stat-value text-danger">$<?= number_format($totalSupplierDueUSD, 2) ?></div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <div class="stat-card bg-light">
                                                        <div class="stat-icon bg-danger-light mb-2">
                                                            <i class="fas fa-money-bill-wave text-danger"></i>
                                                        </div>
                                                        <div class="text-muted"><?= __('total_afs_due') ?></div>
                                                        <div class="stat-value text-danger">؋<?= number_format($totalSupplierDueAFS, 2) ?></div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Supplier currency filter -->
                                            <div class="row mt-3 mb-2">
                                                <div class="col-md-3">
                                                    <select class="form-control filter-control" id="supplierCurrencyFilter">
                                                        <option value="all"><?= __('all_currencies') ?></option>
                                                        <option value="USD">USD ($)</option>
                                                        <option value="AFS">AFS (؋)</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <select class="form-control filter-control" id="supplierBalanceFilter">
                                                        <option value="all"><?= __('all_balances') ?></option>
                                                        <option value="positive"><?= __('positive_balance') ?></option>
                                                        <option value="negative"><?= __('negative_balance') ?></option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Card Body with Table -->
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table modern-table table-hover" id="supplierTable">
                                                    <thead>
                                                        <tr>
                                                            <th class="px-4"><?= __('supplier_name') ?></th>
                                                            <th><?= __('currency') ?></th>
                                                            <th><?= __('balance') ?></th>
                                                            <th><?= __('status') ?></th>
                                                            <th><?= __('last_updated') ?></th>
                                                            <th class="text-center"><?= __('actions') ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (empty($supplier)): ?>
                                                            <tr>
                                                                <td colspan="6" class="text-center py-4"><?= __('no_supplier_accounts_found') ?></td>
                                                            </tr>
                                                        <?php else: ?>
                                                            <?php foreach ($supplier as $row): ?>
                                                                <tr class="supplier-row" data-supplier-name="<?= htmlspecialchars($row['supplier_name']) ?>" data-supplier-currency="<?= htmlspecialchars($row['currency']) ?>" data-supplier-balance="<?= $row['balance'] ?>">
                                                                    <td class="px-4">
                                                                        <div class="d-flex align-items-center">
                                                                            <div class="currency-icon bg-info-light">
                                                                                <i class="feather icon-user-check text-info"></i>
                                                                            </div>
                                                                            <span class="fw-medium">
                                                                                <?= htmlspecialchars($row['supplier_name']) ?>
                                                                            </span>
                                                                        </div>
                                                                    </td>
                                                                    <td>
                                                                        <span class="status-badge bg-<?= $row['currency'] === 'USD' ? 'success' : 'info' ?>" style="color: #ffffff;">
                                                                            <?= htmlspecialchars($row['currency']) ?>
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        <div class="d-flex align-items-center">
                                                                            <span class="fw-medium <?= $row['balance'] >= 0 ? ($row['currency'] === 'USD' ? 'text-success' : 'text-info') : 'text-danger' ?>">
                                                                                <?= $row['currency'] === 'USD' ? '$' : '؋' ?><?= number_format($row['balance'], 2) ?>
                                                                            </span>
                                                                        </div>
                                                                    </td>
                                                                    <td>
                                                                        <span class="status-badge bg-<?= isset($row['status']) && $row['status'] === 'inactive' ? 'danger' : 'success' ?>" style="color: #ffffff;">
                                                                            <?= isset($row['status']) ? ucfirst($row['status']) : 'Active' ?>
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        <small class="text-muted">
                                                                            <?= date('M d, Y H:i', strtotime($row['updated_at'])) ?>
                                                                        </small>
                                                                    </td>
                                                                    <td class="text-center">
                                                                        <div class="dropdown">
                                                                            <button class="btn btn-primary btn-sm dropdown-toggle" type="button" id="supplierActions<?= $row['id'] ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                                                <i class="feather icon-settings"></i> <?= __('actions') ?>
                                                                            </button>
                                                                            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="supplierActions<?= $row['id'] ?>">
                                                                                <a class="dropdown-item" href="javascript:void(0);" onclick="setupFundingModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['supplier_name']) ?>', '<?= htmlspecialchars($row['currency']) ?>')">
                                                                                    <i class="feather icon-credit-card mr-2"></i> <?= __('fund') ?>
                                                                                </a>
                                                                                <a class="dropdown-item" href="javascript:void(0);" onclick="setupBonusModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['supplier_name']) ?>', '<?= htmlspecialchars($row['currency']) ?>')">
                                                                                    <i class="fas fa-gift mr-2"></i> <?= __('bonus') ?>
                                                                                </a>
                                                                                <!-- Add Withdraw Button for Suppliers -->
                                                                                <a class="dropdown-item" href="javascript:void(0);" onclick="setupWithdrawModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['supplier_name']) ?>', '<?= htmlspecialchars($row['currency']) ?>')">
                                                                                    <i class="feather icon-arrow-down me-1"></i> <?= __('withdraw') ?>
                                                                                </a>
                                                                                <a class="dropdown-item view-supplier-transactions-btn" href="javascript:void(0);" 
                                                                                    data-supplier-id="<?= $row['id'] ?>"
                                                                                    data-supplier-name="<?= htmlspecialchars($row['supplier_name']) ?>">
                                                                                    <i class="feather icon-list mr-2"></i> <?= __('transactions') ?>
                                                                                </a>
                                                                                <div class="dropdown-divider"></div>
                                                                                <a class="dropdown-item toggle-supplier-status-btn <?= isset($row['status']) && $row['status'] === 'active' ? 'text-danger' : 'text-success' ?>" href="javascript:void(0);" 
                                                                                    data-supplier-id="<?= $row['id'] ?>"
                                                                                    data-current-status="<?= isset($row['status']) ? $row['status'] : 'active' ?>">
                                                                                    <i class="feather icon-<?= isset($row['status']) && $row['status'] === 'active' ? 'power' : 'check-circle' ?> mr-2"></i> 
                                                                                    <?= isset($row['status']) && $row['status'] === 'active' ? __('deactivate') : __('activate') ?>
                                                                                </a>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Client Accounts Section -->
                            <div class="row">
                                <div class="col-md-12 mb-4">
                                    <div class="modern-card">
                                        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                                            <h4 class="mb-0"><i class="feather icon-users mr-2"></i><?= __('client_accounts') ?></h4>
                                            <!-- Add local search for client section -->
                                            <div class="col-md-4">
                                                <div class="search-container my-1">
                                                    <i class="feather icon-search search-icon"></i>
                                                    <input type="text" id="clientSearchInput" class="search-input bg-white" placeholder="<?= __('search_clients') ?>...">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <!-- Client currency filter -->
                                            <div class="row mb-3">
                                                <div class="col-md-3">
                                                    <select class="form-control filter-control" id="clientBalanceFilter">
                                                        <option value="all"><?= __('all_balances') ?></option>
                                                        <option value="positive"><?= __('positive_balance') ?></option>
                                                        <option value="negative"><?= __('negative_balance') ?></option>
                                                        <option value="zero"><?= __('zero_balance') ?></option>
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <select class="form-control filter-control" id="clientCurrencyType">
                                                        <option value="all"><?= __('all_currencies') ?></option>
                                                        <option value="USD"><?= __('usd') ?></option>
                                                        <option value="AFS"><?= __('afs') ?></option>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <div class="row" id="clientAccountsContainer">
                                                <?php foreach ($clientAccounts as $client): ?>
                                                    <div class="col-md-4 mb-4">
                                                        <div class="account-card client-card <?= isset($client['status']) && $client['status'] === 'inactive' ? 'border-left border-danger' : '' ?>"
                                                             data-client-name="<?= htmlspecialchars($client['name']) ?>"
                                                             data-client-status="<?= isset($client['status']) ? $client['status'] : 'active' ?>"
                                                             data-usd-balance="<?= $client['usd_balance'] ?>"
                                                             data-afs-balance="<?= $client['afs_balance'] ?>">
                                                            <div class="card-header bg-light border-bottom-0 d-flex justify-content-between align-items-start">
                                                                <div>
                                                                    <h5 class="mb-0 text-success">
                                                                        <i class="feather icon-user mr-2"></i>
                                                                        <?= htmlspecialchars($client['name']) ?>
                                                                    </h5>
                                                                </div>
                                                                <?php if (isset($client['status'])): ?>
                                                                <span class="status-badge bg-<?= $client['status'] === 'active' ? 'success' : 'danger' ?>" style="color: #ffffff;">
                                                                    <?= ucfirst(htmlspecialchars($client['status'])) ?>
                                                                </span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="card-body">
                                                                <div class="row balance-row">
                                                                    <div class="col-6">
                                                                        <div class="balance-item d-flex align-items-center">
                                                                            <div class="currency-icon bg-success-light">
                                                                                <i class="fas fa-dollar-sign text-success"></i>
                                                                            </div>
                                                                            <div>
                                                                                <div class="balance-label"><?= __('usd_balance') ?></div>
                                                                                <div class="balance-value <?= $client['usd_balance'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                                                                    $<?= number_format($client['usd_balance'], 2) ?>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-6">
                                                                        <div class="balance-item d-flex align-items-center">
                                                                            <div class="currency-icon bg-info-light">
                                                                                <i class="fas fa-money-bill-wave text-info"></i>
                                                                            </div>
                                                                            <div>
                                                                                <div class="balance-label"><?= __('afs_balance') ?></div>
                                                                                <div class="balance-value <?= $client['afs_balance'] >= 0 ? 'text-info' : 'text-danger' ?>">
                                                                                    ؋<?= number_format($client['afs_balance'], 2) ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                </div>
                                                                
                                                                <div class="mt-3">
                                                                    <button class="btn btn-primary btn-sm btn-block action-btn mb-2 make-payment-btn" 
                                                                            data-client-id="<?= $client['id'] ?>"
                                                                            data-client-name="<?= htmlspecialchars($client['name']) ?>"
                                                                            data-usd-balance="<?= $client['usd_balance'] ?>"
                                                                            data-afs-balance="<?= $client['afs_balance'] ?>">
                                                                        <i class="feather icon-credit-card mr-1"></i> <?= __('make_payment') ?>
                                                                    </button>
                                                                
                                                                    <button class="btn btn-outline-primary btn-sm btn-block action-btn mb-2 view-client-transactions-btn" 
                                                                            data-client-id="<?= $client['id'] ?>"
                                                                            data-client-name="<?= htmlspecialchars($client['name']) ?>">
                                                                        <i class="feather icon-list mr-1"></i> <?= __('view_transactions') ?>
                                                                    </button>
                                                                    
                                                                    <button class="btn btn-outline-<?= isset($client['status']) && $client['status'] === 'active' ? 'danger' : 'success' ?> btn-sm btn-block action-btn toggle-client-status-btn" 
                                                                            data-client-id="<?= $client['id'] ?>"
                                                                            data-current-status="<?= isset($client['status']) ? $client['status'] : 'active' ?>">
                                                                        <i class="feather icon-<?= isset($client['status']) && $client['status'] === 'active' ? 'power' : 'check-circle' ?> mr-1"></i> 
                                                                        <?= isset($client['status']) && $client['status'] === 'active' ? __('deactivate') : __('activate') ?> <?= __('client') ?>
                                                                    </button>
                                                                </div>
                                                                
                                                                <small class="text-muted d-block mt-3">
                                                                    <?= __('last_updated') ?>: <?= date('M d, Y H:i', strtotime($client['updated_at'])) ?>
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>

                                            <!-- No clients found message -->
                                            <div id="noClientsMessage" class="text-center py-4 d-none">
                                                <i class="feather icon-users text-muted mb-2" style="font-size: 2rem;"></i>
                                                <p class="text-muted"><?= __('no_clients_match_your_criteria') ?></p>
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
    </div>
</div>

  <!-- Include Modals -->
  <?php include '../modals/accounts/add_main_account_modal.php'; ?>
  <?php include '../modals/accounts/edit_main_account_modal.php'; ?>
  <?php include '../modals/accounts/fund_supplier_modal.php'; ?>
  <?php include '../modals/accounts/withdraw_supplier_modal.php'; ?>
  <?php include '../modals/accounts/bonus_supplier_modal.php'; ?>
  <?php include '../modals/accounts/client_payment_modal.php'; ?>
  <?php include '../modals/accounts/client_transaction_history_modal.php'; ?>
  <?php include '../modals/accounts/supplier_transaction_history_modal.php'; ?>
  <?php include '../modals/accounts/transfer_modal.php'; ?>
  <?php include '../modals/accounts/main_account_transaction_history_modal.php'; ?>
  <?php include '../modals/accounts/remarks_modal.php'; ?>
  <?php include '../modals/accounts/edit_transaction_modal.php'; ?>
  <?php include '../modals/accounts/edit_receipt_modal.php'; ?>
  <!-- Hidden form for transaction deletion -->
<form id="deleteTransactionForm" class="d-none">
    <input type="hidden" id="deleteTransactionId" name="transaction_id">
    <input type="hidden" id="deleteTransactionType" name="transaction_type">
</form>



<style>
.transfer-separator {
    text-align: center;
}

.transfer-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}
</style>











    <!-- Enhanced Button Protection Script -->
    <script src="../js/accounts/button_protection.js"></script>

    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    <script src="../assets/js/client-search.js"></script>


    
    <!-- Date Range Picker -->
    <script type="text/javascript" src="../assets/plugins/daterangepicker/moment.min.js"></script>
    <script type="text/javascript" src="../assets/plugins/daterangepicker/daterangepicker.js"></script>

    <!-- Toast Container -->
    <div class="toast-container"></div>

    <!-- Account filters scripts -->
    <script src="../js/accounts/filters.js"></script>
    <script src="../js/accounts/toast-notifications.js"></script>
    <script src="../js/accounts/printing.js"></script>
    <script src="../js/accounts/account-management.js"></script>
    <script src="../js/accounts/account-funding.js"></script>
    <script src="../js/accounts/account-withdrawal.js"></script>
    <script src="../js/accounts/transaction-management.js"></script>
    <script src="../js/accounts/status-management.js?v=1.1"></script>

    <!-- Accounts Tutorials Modal -->
    <div class="modal fade" id="accountsTutorialsModal" tabindex="-1" role="dialog" aria-labelledby="accountsTutorialsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="accountsTutorialsModalLabel">
                        <i class="feather icon-play-circle mr-2"></i>Accounts Management Tutorials
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0">
                    <div class="row h-100 no-gutters">
                        <!-- Video Player Column -->
                        <div class="col-md-8">
                            <div style="background: #000; position: relative;">
                                <iframe id="tutorialVideoPlayer" style="width: 100%; height: 500px; border: none;" 
                                        src="" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                        allowfullscreen>
                                </iframe>
                            </div>
                            <div class="p-3">
                                <h6 id="tutorialTitle" class="mb-2">Select a tutorial to watch</h6>
                                <p id="tutorialDescription" class="text-muted small mb-0"></p>
                                <div class="mt-2">
                                    <small class="badge-primary" id="tutorialLevel">Beginner</small>
                                    <small class="badge-secondary" id="tutorialDuration">5:00</small>
                                </div>
                            </div>
                        </div>
                        <!-- Tutorials List Column -->
                        <div class="col-md-4" style="max-height: 600px; overflow-y: auto; border-left: 1px solid #e9ecef;">
                            <div class="p-3">
                                <h6 class="mb-3">Available Tutorials</h6>
                                <div id="tutorialsListContainer">
                                    <p class="text-muted text-center py-3">Loading tutorials...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Accounts Tutorials Data
    const accountsTutorials = [
        {id: 1, title: 'View Account Balances', description: 'Viewing comprehensive account overview, understanding main account balances, supplier account balances, and client account balances with USD and AFS currency support.', duration: '4:30', level: 'Beginner', vimeo_id: ''},
        {id: 2, title: 'Search & Filter Accounts', description: 'Using search functionality to find accounts, filtering by account type (main, supplier, client), filtering by status (active, inactive), managing large account lists.', duration: '5:00', level: 'Beginner', vimeo_id: ''},
        {id: 3, title: 'Add Account Funds', description: 'Adding funds to account balances, selecting currency (USD/AFS), entering amount, recording transactions, understanding debit and credit operations, updating account status.', duration: '6:00', level: 'Intermediate', vimeo_id: ''},
        {id: 4, title: 'Withdraw Account Funds', description: 'Withdrawing funds from accounts, verifying withdrawal requests, processing withdrawals with proper documentation, managing withdrawal transactions, updating balances.', duration: '6:00', level: 'Intermediate', vimeo_id: ''},
        {id: 5, title: 'View Transaction History', description: 'Accessing account transaction history, reviewing all account transactions, understanding transaction types and amounts, tracking account changes over time, exporting transaction records.', duration: '5:30', level: 'Intermediate', vimeo_id: ''},
        {id: 6, title: 'Manage Account Status', description: 'Changing account status (active/inactive), understanding status impact on operations, deactivating accounts, reactivating accounts, managing account lifecycle.', duration: '4:30', level: 'Advanced', vimeo_id: ''}
    ];

    $(document).ready(function() {
        // Initialize tutorials list
        function loadTutorialsInModal() {
            let html = '';
            accountsTutorials.forEach(function(tutorial) {
                html += `
                    <div class="tutorial-item p-2 mb-2 border rounded cursor-pointer tutorial-selectable" 
                         data-tutorial-id="${tutorial.id}" 
                         style="background: #f8f9fa; transition: all 0.3s ease;">
                        <div class="d-flex align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-1 small font-weight-bold">${tutorial.title}</h6>
                                <small class="text-muted d-block mb-1">${tutorial.duration}</small>
                                <small class="badge-light">${tutorial.level}</small>
                            </div>
                            <i class="feather icon-play text-muted" style="margin-top: 2px;"></i>
                        </div>
                    </div>
                `;
            });
            $('#tutorialsListContainer').html(html);

            // Add click handlers
            $('.tutorial-selectable').click(function() {
                const tutorialId = $(this).data('tutorial-id');
                const tutorial = accountsTutorials.find(t => t.id == tutorialId);
                
                if (tutorial) {
                    // Update active state
                    $('.tutorial-selectable').css('background', '#f8f9fa');
                    $(this).css('background', '#e7f3ff').css('border-color', '#4099ff');
                    
                    // Update video player and info
                    if (tutorial.vimeo_id) {
                        const videoUrl = `https://player.vimeo.com/video/${tutorial.vimeo_id}`;
                        $('#tutorialVideoPlayer').attr('src', videoUrl);
                    } else {
                        $('#tutorialVideoPlayer').attr('src', '');
                    }
                    
                    $('#tutorialTitle').text(tutorial.title);
                    $('#tutorialDescription').text(tutorial.description);
                    $('#tutorialLevel').text(tutorial.level);
                    $('#tutorialDuration').text(tutorial.duration);
                }
            });
        }

        // Load tutorials when modal is shown
        $('#accountsTutorialsModal').on('show.bs.modal', function() {
            loadTutorialsInModal();
            // Select first tutorial by default
            if (accountsTutorials.length > 0) {
                $('.tutorial-selectable').first().click();
            }
        });

        // Add CSS for cursor pointer
        $('<style>').text('.cursor-pointer { cursor: pointer; }').appendTo('head');
    });
    </script>

    <!-- Include Admin Footer -->
    <?php include '../includes/admin_footer.php'; ?>
    </body>
    </html>

                                               



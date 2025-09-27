<?php
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');
require_once('../includes/conn.php');

// Note: Client accounts are fetched later with a more detailed query

// Fetch main account balances
$mainAccountQuery = "SELECT * FROM main_account WHERE tenant_id = ?";
$stmt = $conn->prepare($mainAccountQuery);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$result = $stmt->get_result(); // Use get_result() instead of another query

if ($result && $result->num_rows > 0) {
    $mainAccounts = $result->fetch_all(MYSQLI_ASSOC); // Fetch all rows as an array of associative arrays
} else {
    $mainAccounts = [];
}

// Fetch client accounts balances
$clientAccountQuery = "SELECT * FROM clients where status = 'active' AND tenant_id = ?";
$stmt = $conn->prepare($clientAccountQuery);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $clientAccounts = $result->fetch_all(MYSQLI_ASSOC); // Fetch all rows as an array of associative arrays
} else {
    $clientAccounts = [];
}

// Fetch supplier accounts with their balances
    $supplierQuery = "
    SELECT sa.id, sa.name AS supplier_name, sa.currency, sa.balance, sa.updated_at, sa.status
    FROM suppliers sa where status = 'active' AND tenant_id = ?";
$supplier = $conn->prepare($supplierQuery);
$supplier->bind_param("i", $tenant_id);
$supplier->execute();
$supplier = $supplier->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch client accounts with their balances
$clientQuery = "
SELECT cl.id, cl.name, cl.usd_balance, cl.afs_balance, cl.updated_at, cl.status
FROM clients cl where status = 'active' AND tenant_id = ?";
$clientAccounts = $conn->prepare($clientQuery);
$clientAccounts->bind_param("i", $tenant_id);
$clientAccounts->execute();
$clientAccounts = $clientAccounts->get_result()->fetch_all(MYSQLI_ASSOC);


?>
<style>
/* Apply gradient background to card headers matching the sidebar */
.card-header {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
    color: #ffffff !important;
    border-bottom: none !important;
}

.card-header h5 {
    color: #ffffff !important;
    margin-bottom: 0 !important;
}

.card-header .card-header-right {
    color: #ffffff !important;
}

.card-header .card-header-right .btn {
    color: #ffffff !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
}

.card-header .card-header-right .btn:hover {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(255, 255, 255, 0.5) !important;
}
</style>
<?php include '../includes/header.php'; ?>
<link href="css/account-styles.css" rel="stylesheet">
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
                                <div class="col-md-12">
                                    <div class="page-header-title">
                                        <h5 class="m-b-10"><?= __('accounts_management') ?></h5>
                                    </div>
                                    <ul class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                        <li class="breadcrumb-item"><?= __('accounts') ?></li>
                                    </ul>
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
                                                                                <div class="balance-label"><?= __('darham_balance') ?></div>
                                                                                <div class="balance-value text-warning">د.أ <?= number_format($account['darham_balance'], 2) ?></div>
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

  

<!-- Add Main Account Modal -->
<div class="modal fade modern-modal" id="addMainAccountModal" tabindex="-1" aria-labelledby="addMainAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addMainAccountModalLabel">
                    <i class="feather icon-plus-circle mr-2"></i><?= __('add_new_main_account') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="addMainAccountForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="account_name" class="form-label"><?= __('account_name') ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-light"><i class="feather icon-briefcase"></i></span>
                            </div>
                            <input type="text" id="account_name" name="account_name" class="form-control" placeholder="<?= __('enter_account_name') ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="account_type" class="form-label"><?= __('account_type') ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-light"><i class="feather icon-tag"></i></span>
                            </div>
                        <select id="account_type" name="account_type" class="form-control" required>
                            <option value="internal"><?= __('internal_account') ?></option>
                            <option value="bank"><?= __('bank_account') ?></option>
                        </select>
                        </div>
                    </div>
                    <!-- Bank account specific fields - shown/hidden based on account type -->
                    <div id="bankFields" style="display: none;">
                        <div class="mb-3">
                            <label for="bank_account_usd_number" class="form-label"><?= __('bank_account_usd_number') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="feather icon-hash"></i></span>
                                </div>
                                <input type="text" id="bank_account_number" name="bank_account_usd_number" class="form-control" placeholder="<?= __('enter_bank_account_usd_number') ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="bank_account_afs_number" class="form-label"><?= __('bank_account_afs_number') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="feather icon-hash"></i></span>
                                </div>
                                <input type="text" id="bank_account_afs_number" name="bank_account_afs_number" class="form-control" placeholder="<?= __('enter_bank_account_afs_number') ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="bank_name" class="form-label"><?= __('bank_name') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="feather icon-home"></i></span>
                                </div>
                                <input type="text" id="bank_name" name="bank_name" class="form-control" placeholder="<?= __('enter_bank_name') ?>">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label"><?= __('status') ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-light"><i class="feather icon-toggle-right"></i></span>
                            </div>
                        <select id="status" name="status" class="form-control" required>
                            <option value="active"><?= __('active') ?></option>
                            <option value="inactive"><?= __('inactive') ?></option>
                        </select>
                    </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                    <div class="mb-3">
                        <label for="usd_balance" class="form-label"><?= __('usd_balance') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light"><i class="fas fa-dollar-sign"></i></span>
                                    </div>
                                    <input type="number" id="usd_balance" name="usd_balance" class="form-control" step="0.01" placeholder="<?= __('enter_usd_balance') ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                    <div class="mb-3">
                        <label for="afs_balance" class="form-label"><?= __('afs_balance') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light"><i class="fas fa-money-bill-wave"></i></span>
                                    </div>
                                    <input type="number" id="afs_balance" name="afs_balance" class="form-control" step="0.01" placeholder="<?= __('enter_afs_balance') ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                        <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather icon-save mr-1"></i><?= __('add_account') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
       
<!-- Main Account Transaction History Modal -->
<div class="modal fade modern-modal" id="transactionHistoryModal" tabindex="-1" aria-labelledby="transactionHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="transactionHistoryModalLabel">
                    <i class="feather icon-list mr-2"></i><?= __('account_transactions') ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <h5 id="accountNameDisplay" class="font-weight-bold text-primary mb-0"></h5>
                    <p class="text-muted small"><?= __('transaction_history') ?></p>
                    </div>
                    
                <!-- Currency Filter -->
                <div class="row mb-4 no-gutters">
                    <div class="col-md-3 pr-md-2 mb-2 mb-md-0">
                        <div class="form-group mb-0">
                            <label for="mainAccountCurrencyFilter" class="small font-weight-bold"><?= __('filter_by_currency') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="feather icon-filter"></i></span>
                                </div>
                                <select id="mainAccountCurrencyFilter" class="form-control">
                                    <option value="all"><?= __('all_currencies') ?></option>
                                    <option value="USD"><?= __('usd') ?> ($)</option>
                                    <option value="AFS"><?= __('afs') ?> (؋)</option>
                                    <option value="EUR"><?= __('eur') ?> (€)</option>
                                    <option value="DARHAM"><?= __('darham') ?> (د.أ)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 px-md-2 mb-2 mb-md-0">
                        <div class="form-group mb-0">
                            <label for="receiptSearch" class="small font-weight-bold"><?= __('search_by_receipt') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="feather icon-search"></i></span>
                                </div>
                                <input type="text" id="receiptSearch" class="form-control" placeholder="<?= __('enter_receipt_number') ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 px-md-2 mb-2 mb-md-0">
                        <div class="form-group mb-0">
                            <label for="dateRangeFilter" class="small font-weight-bold"><?= __('date_range') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="feather icon-calendar"></i></span>
                                </div>
                                <input type="text" id="dateRangeFilter" class="form-control" placeholder="<?= __('select_date_range') ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 pl-md-2">
                        <div class="form-group mb-0 d-flex flex-column h-100">
                            <label class="small font-weight-bold d-block">&nbsp;</label>
                            <div class="d-flex align-items-center mt-auto">
                                <button type="button" class="btn btn-primary btn-sm w-100" id="printTransactionsBtn">
                                    <i class="feather icon-printer mr-1"></i><?= __('export_pdf') ?>
                                </button>
                            </div>
                        </div>
                    </div>
                

                <div class="table-responsive rounded">
                    <table class="table table-hover table-striped mb-0" id="transactionsTable">
                        <thead class="bg-light">
                            <tr>
                                <th>#</th>
                                <th><?= __('date') ?></th>
                                <th><?= __('remarks') ?></th>
                                <th><?= __('receipt') ?></th>
                                <th><?= __('debit') ?></th>
                                <th><?= __('credit') ?></th>
                                <th><?= __('balance') ?></th>
                                <th><?= __('currency') ?></th>
                                <th class="text-center"><?= __('actions') ?></th>
                            </tr>
                        </thead>
                        <tbody id="transactionsTableBody">
                            <!-- Transactions will be loaded here -->
                        </tbody>
                    </table>
                </div>
                            <div id="noTransactionsMessage" class="text-center py-5 d-none">
                                <div class="empty-state">
                                    <i class="feather icon-inbox text-muted mb-3" style="font-size: 3rem;"></i>
                                    <h6 class="mt-3"><?= __('no_transactions_found') ?></h6>
                                    <p class="text-muted small"><?= __('no_transactions_found_for_this_account') ?></p>
                                </div>
                            </div>
                            <div id="transactionsLoader" class="text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden"><?= __('loading') ?>...</span>
                                </div>
                                <p class="mt-3"><?= __('loading_transactions') ?>...</p>
                            </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Remarks Modal -->
<div class="modal fade modern-modal" id="remarksModal" tabindex="-1" aria-labelledby="remarksModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="remarksModalLabel">
                    <i class="feather icon-message-square mr-2"></i><?= __('enter_your_remarks') ?>
                                                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                                            </div>
            <div class="modal-body">
                <div class="form-section">
                    <div class="form-group mb-3">
                        <label for="user-remarks"><?= __('remarks') ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-light"><i class="feather icon-message-circle"></i></span>
                                                        </div>
                            <textarea id="user-remarks" class="form-control" rows="4" placeholder="<?= __('add_remarks_regarding_this_funding') ?>..."></textarea>
                                                            </div>
                                                        </div>

                    <div class="form-group mb-0">
                        <label for="modalReceiptNumber"><?= __('receipt_number') ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-light"><i class="feather icon-file-text"></i></span>
                                                            </div>
                            <input type="text" class="form-control" id="modalReceiptNumber" name="receiptNumber" required>
                                                                    </div>
                                                                </div>
                                                                    </div>
                                                                </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                                                    <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                                                </button>
                                                <button type="button" class="btn btn-primary" id="submit-remarks-btn">
                                                    <i class="feather icon-check-circle mr-1"></i><?= __('submit') ?>
                                                </button>
                                            </div>
                                        </div>
                                </div>
                            </div>

<!-- Edit Transaction Modal -->
<div class="modal fade modern-modal" id="editTransactionModal" tabindex="-1" aria-labelledby="editTransactionModalLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editTransactionModalLabel">
                    <i class="feather icon-edit mr-2"></i><?= __('edit_fund_transaction') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editTransactionForm">
                    <input type="hidden" id="editTransactionId" name="transaction_id">
                    <input type="hidden" id="editTransactionType" name="transaction_type">
                    <input type="hidden" id="originalAmount" name="original_amount">
                    <input type="hidden" id="originalType" name="original_type">
                    
                    <!-- Transaction Details Section -->
                    <div class="form-section">
                        <div class="form-section-title"><?= __('transaction_details') ?></div>
                        
                        <div class="form-group mb-3">
                            <label for="editTransactionDate"><?= __('transaction_date') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="feather icon-calendar"></i></span>
                                </div>
                                <input type="datetime-local" class="form-control" id="editTransactionDate" name="transaction_date" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-group mb-md-0">
                                    <label for="editTransactionAmount"><?= __('amount') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="fas fa-coins"></i></span>
                                        </div>
                                        <input type="number" step="0.01" class="form-control" id="editTransactionAmount" name="amount" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-md-0">
                                    <label for="editTransactionCurrency"><?= __('currency') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="feather icon-dollar-sign"></i></span>
                                        </div>
                                            <select class="form-control" id="editTransactionCurrency" name="currency" required>
                                                <option value="USD"><?= __('usd') ?> ($)</option>
                                                <option value="AFS"><?= __('afs') ?> (؋)</option>
                                                <option value="EUR"><?= __('eur') ?> (€)</option>
                                                <option value="DARHAM"><?= __('darham') ?> (د.أ)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        
                        <div class="form-group mb-3">
                            <label for="editTransactionTypeSelect"><?= __('type') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="feather icon-repeat"></i></span>
                                </div>
                                <select class="form-control" id="editTransactionTypeSelect" name="type" required>
                                    <option value="credit"><?= __('credit') ?> (<?= __('add_funds') ?>)</option>
                                    <option value="debit"><?= __('debit') ?> (<?= __('remove_funds') ?>)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Additional Information Section -->
                    <div class="form-section">
                        <div class="form-section-title"><?= __('additional_information') ?></div>
                        
                        <div class="form-group mb-3">
                            <label for="editTransactionReceipt"><?= __('receipt_number') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="feather icon-file-text"></i></span>
                                </div>
                                <input type="text" class="form-control" id="editTransactionReceipt" name="receipt">
                         </div>
                        </div>
                        
                        <div class="form-group mb-0">
                            <label for="editTransactionDescription"><?= __('description') ?>/<?= __('remarks') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="feather icon-message-square"></i></span>
                                </div>
                                <textarea class="form-control" id="editTransactionDescription" name="description" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                </button>
                <button type="button" class="btn btn-primary" id="saveEditTransactionBtn">
                    <i class="feather icon-save mr-1"></i><?= __('save_changes') ?>
                </button>
            </div>
        </div>
    </div>
</div>

                <!-- Client Transaction History Modal -->
                <div class="modal fade modern-modal" id="clientTransactionHistoryModal" tabindex="-1" aria-labelledby="clientTransactionHistoryModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title" id="clientTransactionHistoryModalLabel">
                                    <i class="feather icon-list mr-2"></i><?= __('client_transactions') ?>
                                </h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div class="text-center mb-4">
                                    <h5 id="clientNameDisplay" class="font-weight-bold text-success mb-0"></h5>
                                    <p class="text-muted small"><?= __('transaction_history') ?></p>
                                </div>
                                
                                                                <!-- Currency and Search Filters -->
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <div class="form-group mb-0">
                                            <label for="clientCurrencyFilter" class="small font-weight-bold"><?= __('filter_by_currency') ?></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text bg-light"><i class="feather icon-filter"></i></span>
                                                </div>
                                                <select id="clientCurrencyFilter" class="form-control">
                                                <option value="all"><?= __('all_currencies') ?></option>
                                                    <option value="USD"><?= __('usd') ?> ($)</option>
                                                    <option value="AFS"><?= __('afs') ?> (؋)</option>
                                            </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group mb-0">
                                                <label for="clientReceiptSearch" class="small font-weight-bold"><?= __('search_by_receipt') ?></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text bg-light"><i class="feather icon-search"></i></span>
                                        </div>
                                                <input type="text" id="clientReceiptSearch" class="form-control" placeholder="<?= __('enter_receipt_number') ?>">
                                    </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group mb-0">
                                            <label for="clientDateRangeFilter" class="small font-weight-bold"><?= __('date_range') ?></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text bg-light"><i class="feather icon-calendar"></i></span>
                                                </div>
                                                <input type="text" id="clientDateRangeFilter" class="form-control" placeholder="<?= __('select_date_range') ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group mb-0 d-flex flex-column h-100">
                                            <label class="small font-weight-bold d-block">&nbsp;</label>
                                            <div class="d-flex align-items-center mt-auto">
                                                <button type="button" class="btn btn-success btn-sm w-100" id="printClientTransactionsBtn">
                                                    <i class="feather icon-printer mr-1"></i><?= __('export_pdf') ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                </div>

                                <div class="table-responsive rounded">
                                    <table class="table table-hover table-striped mb-0" id="clientTransactionsTable">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>#</th>
                                                <th><?= __('date') ?></th>
                                                <th><?= __('remarks') ?></th>
                                                <th><?= __('receipt') ?></th>
                                                <th><?= __('category') ?></th>
                                                <th><?= __('reference') ?></th>
                                                <th><?= __('debit') ?></th>
                                                <th><?= __('credit') ?></th>
                                                <th><?= __('balance') ?></th>
                                                <th><?= __('currency') ?></th>
                                                <th class="text-center"><?= __('actions') ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="clientTransactionsTableBody">
                                            <!-- Transactions will be loaded here -->
                                        </tbody>
                                    </table>
                                </div>
                                <div id="noClientTransactionsMessage" class="text-center py-5 d-none">
                                    <div class="empty-state">
                                        <i class="feather icon-inbox text-muted mb-3" style="font-size: 3rem;"></i>
                                        <h6 class="mt-3"><?= __('no_transactions_found') ?></h6>
                                        <p class="text-muted small"><?= __('no_transactions_found_for_this_client') ?></p>
                                </div>
                                </div>
                                <div id="clientTransactionsLoader" class="text-center py-5">
                                    <div class="spinner-border text-success" role="status">
                                        <span class="visually-hidden"><?= __('loading') ?>...</span>
                                    </div>
                                    <p class="mt-3"><?= __('loading_transactions') ?>...</p>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Supplier Transaction History Modal -->
                <div class="modal fade modern-modal" id="supplierTransactionHistoryModal" tabindex="-1" aria-labelledby="supplierTransactionHistoryModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header bg-info text-white">
                                <h5 class="modal-title" id="supplierTransactionHistoryModalLabel">
                                    <i class="feather icon-list mr-2"></i><?= __('supplier_transactions') ?>
                                </h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div class="text-center mb-4">
                                    <h5 id="supplierNameDisplay" class="font-weight-bold text-info mb-0"></h5>
                                    <p class="text-muted small"><?= __('transaction_history') ?></p>
                                </div>
                                <!-- Currency and Search Filters -->
                                <div class="row mb-4">
                                    <div class="col-md-4">
                                        <div class="form-group mb-0">
                                            <label for="supplierReceiptSearch" class="small font-weight-bold"><?= __('search_by_receipt') ?></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text bg-light"><i class="feather icon-search"></i></span>
                                        </div>
                                                <input type="text" id="supplierReceiptSearch" class="form-control" placeholder="<?= __('enter_receipt_number') ?>">
                                    </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group mb-0">
                                            <label for="supplierDateRangeFilter" class="small font-weight-bold"><?= __('date_range') ?></label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text bg-light"><i class="feather icon-calendar"></i></span>
                                        </div>
                                                <input type="text" id="supplierDateRangeFilter" class="form-control" placeholder="<?= __('select_date_range') ?>">
                                    </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group mb-0 d-flex flex-column h-100">
                                            <label class="small font-weight-bold d-block">&nbsp;</label>
                                            <div class="d-flex align-items-center mt-auto">
                                                <button type="button" class="btn btn-info btn-sm w-100" id="printSupplierTransactionsBtn">
                                                    <i class="feather icon-printer mr-1"></i><?= __('export_pdf') ?>
                                            </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                
                                <div class="table-responsive rounded">
                                    <table class="table table-hover table-striped mb-0" id="supplierTransactionsTable">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>#</th>
                                                <th><?= __('date') ?></th>
                                                <th><?= __('remarks') ?></th>
                                                <th><?= __('receipt') ?></th>
                                                <th><?= __('category') ?></th>
                                                <th><?= __('reference') ?></th>
                                                <th><?= __('debit') ?></th>
                                                <th><?= __('credit') ?></th>
                                                <th><?= __('balance') ?></th>                               
                                                <th class="text-center"><?= __('actions') ?></th>
                                            </tr>
                                        </thead>
                                        <tbody id="supplierTransactionsTableBody">
                                            <!-- Transactions will be loaded here -->
                                        </tbody>
                                    </table>
                                </div>
                                <div id="noSupplierTransactionsMessage" class="text-center py-5 d-none">
                                    <div class="empty-state">
                                        <i class="feather icon-inbox text-muted mb-3" style="font-size: 3rem;"></i>
                                        <h6 class="mt-3"><?= __('no_transactions_found') ?></h6>
                                        <p class="text-muted small"><?= __('no_transactions_found_for_this_supplier') ?></p>
                                </div>
                                </div>
                                <div id="supplierTransactionsLoader" class="text-center py-5">
                                    <div class="spinner-border text-info" role="status">
                                        <span class="visually-hidden"><?= __('loading') ?>...</span>
                                    </div>
                                    <p class="mt-3"><?= __('loading_transactions') ?>...</p>
                                </div>
                            </div>
                            <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                            </div>
                        </div>
                    </div>
                </div>

<!-- Hidden form for transaction deletion -->
<form id="deleteTransactionForm" class="d-none">
    <input type="hidden" id="deleteTransactionId" name="transaction_id">
    <input type="hidden" id="deleteTransactionType" name="transaction_type">
</form>


<!-- Transfer Modal -->
<div class="modal fade modern-modal" id="transferModal" tabindex="-1" role="dialog" aria-labelledby="transferModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="transferModalLabel">
                    <i class="feather icon-exchange mr-2"></i><?= __('transfer_balance') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="transferForm">
                    <div class="row">
                        <div class="col-md-6">
                    <div class="form-group">
                        <label for="fromAccount"><?= __('from_account') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light"><i class="feather icon-credit-card"></i></span>
                                    </div>
                        <select class="form-control" id="fromAccount" name="fromAccount" required>
                            <option value=""><?= __('select_account') ?></option>
                            <?php foreach ($mainAccounts as $account): ?>
                            <option value="<?= $account['id'] ?>"><?= htmlspecialchars($account['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                    <div class="form-group">
                        <label for="fromCurrency"><?= __('from_currency') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light"><i class="feather icon-dollar-sign"></i></span>
                                    </div>
                        <select class="form-control" id="fromCurrency" name="fromCurrency" required>
                            <option value=""><?= __('select_currency') ?></option>
                            <option value="USD"><?= __('usd') ?></option>
                            <option value="AFS"><?= __('afs') ?></option>
                            <option value="EUR"><?= __('eur') ?></option>
                            <option value="DARHAM"><?= __('darham') ?></option>
                        </select>
                    </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="transfer-separator position-relative my-4">
                        <hr>
                        <div class="transfer-icon bg-primary text-white">
                            <i class="feather icon-arrow-down"></i>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                    <div class="form-group">
                        <label for="toAccount"><?= __('to_account') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light"><i class="feather icon-credit-card"></i></span>
                                    </div>
                        <select class="form-control" id="toAccount" name="toAccount" required>
                            <option value=""><?= __('select_account') ?></option>
                            <?php foreach ($mainAccounts as $account): ?>
                            <option value="<?= $account['id'] ?>"><?= htmlspecialchars($account['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                    <div class="form-group">
                        <label for="toCurrency"><?= __('to_currency') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light"><i class="feather icon-dollar-sign"></i></span>
                                    </div>
                        <select class="form-control" id="toCurrency" name="toCurrency" required>
                            <option value=""><?= __('select_currency') ?></option>
                            <option value="USD"><?= __('usd') ?></option>
                            <option value="AFS"><?= __('afs') ?></option>
                            <option value="EUR"><?= __('eur') ?></option>
                            <option value="DARHAM"><?= __('darham') ?></option>
                        </select>
                    </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                    <div class="form-group">
                        <label for="amount"><?= __('amount') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light"><i class="fas fa-coins"></i></span>
                                    </div>
                        <input type="number" class="form-control" id="amount" name="amount" step="0.01" required>
                    </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                    <div class="form-group">
                        <label for="exchangeRate"><?= __('exchange_rate') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light"><i class="feather icon-percent"></i></span>
                                    </div>
                        <input type="number" class="form-control" id="exchangeRate" name="exchangeRate" step="0.01" required>
                    </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group mb-0">
                        <label for="description"><?= __('description') ?></label>
                        <textarea class="form-control" id="description" name="description" rows="2" placeholder="<?= __('enter_transaction_details') ?>"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                </button>
                <button type="button" class="btn btn-primary" id="transferBtn">
                    <i class="feather icon-check mr-1"></i><?= __('transfer') ?>
                </button>
            </div>
        </div>
    </div>
</div>

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

<!-- Fund Supplier Modal -->
<div class="modal fade modern-modal" id="fundSupplierModal" tabindex="-1" role="dialog" aria-labelledby="fundSupplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="fundSupplierModalLabel">
                    <i class="feather icon-credit-card mr-2"></i><?= __('fund_supplier_account') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="fundSupplierForm">
                    <input type="hidden" id="supplierId" name="supplier_id">
                    <input type="hidden" id="supplierName" name="supplier_name">
                    
                    
                    <!-- Supplier Info Section -->
                    <div class="form-section">
                        <div class="supplier-info alert alert-info mb-4">
                            <div class="d-flex align-items-center mb-2">
                                <i class="feather icon-user mr-2"></i>
                                <h6 class="mb-0" id="supplierNameDisplay"></h6>
                            </div>
                            <p class="mb-0 small" id="supplierCurrencyDisplay"></p>
                        </div>
                    </div>
                    
                    <!-- Transaction Details Section -->
                    <div class="form-section">
                        <div class="form-section-title"><?= __('transaction_details') ?></div>
                        
                        <div class="form-group mb-3">
                            <label for="mainAccount"><?= __('select_main_account') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="feather icon-credit-card"></i></span>
                                </div>
                                <select class="form-control" id="mainAccount" name="main_account" required>
                                    <option value=""><?= __('select_account') ?></option>
                                    <!-- Options will be loaded dynamically -->
                                </select>
                            </div>
                        </div>
                        
                        
                        
                        <!-- Payment Currency -->
                    <div class="mb-3">
                        <label for="supplierCurrency" class="form-label">Supplier Currency</label>
                        <input class="form-control" type="text" id="supplierCurrency" name="supplier_currency" readonly>
                        <label for="paymentCurrency" class="form-label">Payment Currency</label>
                        <select id="paymentCurrency" name="payment_currency" class="form-control" required>
                            <option value="USD">USD</option>
                            <option value="AFS">AFS</option>
                        </select>
                    </div>
                    <!-- Exchange Rate -->
                    <div class="mb-3 d-none" id="exchangeRateGroup">
                        <label for="exchangeRate" class="form-label" id="exchangeRateLabel">Exchange rate (USD → AFS)</label>
                        <input type="number" id="exchangeRate" name="exchange_rate" class="form-control" step="0.0001" placeholder="e.g., 70" min="0">
                        <small class="form-text text-muted" id="exchangeHint">Provide USD → AFS rate only when payment currency differs from supplier currency.</small>
                    </div>
                        <div class="form-group mb-3">
                            <label for="amount"><?= __('amount') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light" id="currencySymbol">$</span>
                                </div>
                                <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required>
                            </div>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="receipt"><?= __('receipt_number') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="feather icon-file-text"></i></span>
                                </div>
                                <input type="text" class="form-control" id="receipt" name="receipt_number" required>
                            </div>
                        </div>
                        
                        <div class="form-group mb-0">
                            <label for="remarks"><?= __('remarks') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="feather icon-message-square"></i></span>
                                </div>
                                <textarea class="form-control" id="remarks" name="remarks" rows="3" placeholder="<?= __('enter_transaction_details') ?>"></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                </button>
                <button type="submit" form="fundSupplierForm" class="btn btn-info">
                    <i class="feather icon-check-circle mr-1"></i><?= __('fund_account') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Withdraw Supplier Modal -->
<div class="modal fade modern-modal" id="withdrawSupplierModal" tabindex="-1" aria-labelledby="withdrawSupplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="withdrawSupplierModalLabel"><?= __('withdraw_supplier_account') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="withdrawSupplierForm">
                    <!-- Select Main Account -->
                    <div class="mb-3">
                        <label for="withdrawMainAccount" class="form-label"><?= __('select_main_account') ?></label>
                        <select id="withdrawMainAccount" name="main_account" class="form-control" required>
                            <!-- Populated dynamically with main accounts -->
                        </select>
                    </div>
                    
                    <!-- Supplier Information -->
                    <div class="mb-3">
                        <label for="withdrawSupplierName" class="form-label"><?= __('supplier') ?></label>
                        <input type="text" id="withdrawSupplierName" class="form-control" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="withdrawSupplierCurrency" class="form-label"><?= __('currency') ?></label>
                        <input type="text" id="withdrawSupplierCurrency" class="form-control" readonly>
                    </div>
                    
                    <!-- Payment Currency -->
                    <div class="mb-3">
                        <label for="withdrawPaymentCurrency" class="form-label"><?= __('payment_currency') ?></label>
                        <select id="withdrawPaymentCurrency" name="payment_currency" class="form-control" required>
                            <option value="USD"><?= __('usd') ?></option>
                            <option value="AFS"><?= __('afs') ?></option>
                        </select>
                    </div>
                    
                    <!-- Exchange Rate -->
                    <div class="mb-3 d-none" id="withdrawExchangeRateGroup">
                        <label for="withdrawExchangeRate" class="form-label" id="withdrawExchangeRateLabel"><?= __('exchange_rate_usd_to_afs') ?></label>
                        <input type="number" id="withdrawExchangeRate" name="exchange_rate" class="form-control" step="0.0001" placeholder="e.g., 70" min="0">
                        <small class="form-text text-muted" id="withdrawExchangeHint"><?= __('exchange_rate_hint') ?></small>
                    </div>
                    
                    <!-- Withdrawal Amount -->
                    <div class="mb-3">
                        <label for="withdrawAmount" class="form-label"><?= __('amount_to_withdraw') ?></label>
                        <input type="number" id="withdrawAmount" name="amount" class="form-control" step="0.01" placeholder="<?= __('enter_amount') ?>" required>
                    </div>
                    
                    <!-- Remarks -->
                    <div class="mb-3">
                        <label for="withdrawRemarks" class="form-label"><?= __('remarks') ?></label>
                        <textarea id="withdrawRemarks" name="remarks" class="form-control" rows="3" placeholder="<?= __('enter_remarks') ?>"></textarea>
                    </div>
                    
                    <!-- Receipt Number -->
                    <div class="mb-3">
                        <label for="withdrawReceiptNumber" class="form-label"><?= __('receipt_number') ?></label>
                        <input type="text" id="withdrawReceiptNumber" name="receipt_number" class="form-control" placeholder="<?= __('enter_receipt_number') ?>" required>
                    </div>
                    
                    <input type="hidden" id="withdrawSupplierId" name="supplier_id">
                    <button type="submit" class="btn btn-danger w-100"><?= __('withdraw_account') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>

    <!-- Add Supplier Bonus Modal -->
    <div class="modal fade modern-modal" id="addBonusModal" tabindex="-1" role="dialog" aria-labelledby="addBonusModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="addBonusModalLabel">
                        <i class="feather icon-gift mr-2"></i><?= __('add_supplier_bonus') ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="addBonusForm">
                        <input type="hidden" id="bonusSupplierId" name="supplier_id">
                        <input type="hidden" id="bonusSupplierName" name="supplier_name">
                        <input type="hidden" id="bonusSupplierCurrency" name="supplier_currency">
                        
                        <div class="form-section">
                            <div class="supplier-info alert alert-success mb-4">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="feather icon-user mr-2"></i>
                                    <h6 class="mb-0" id="bonusSupplierNameDisplay"></h6>
                                </div>
                                <p class="mb-0 small" id="bonusSupplierCurrencyDisplay"></p>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="form-section-title"><?= __('bonus_details') ?></div>
                            
                            <div class="form-group mb-3">
                                <label for="bonusAmount"><?= __('amount') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light" id="bonusCurrencySymbol">$</span>
                                    </div>
                                    <input type="number" class="form-control" id="bonusAmount" name="amount" step="0.01" min="0" required>
                                </div>
                            </div>
                            
                            <div class="form-group mb-3">
                                <label for="bonusReceipt"><?= __('receipt_number') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light"><i class="feather icon-file-text"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="bonusReceipt" name="receipt_number">
                                </div>
                            </div>
                            
                            <div class="form-group mb-0">
                                <label for="bonusRemarks"><?= __('remarks') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light"><i class="feather icon-message-square"></i></span>
                                    </div>
                                    <textarea class="form-control" id="bonusRemarks" name="remarks" rows="3" placeholder="<?= __('enter_transaction_details') ?>"></textarea>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                        <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                    </button>
                    <button type="submit" form="addBonusForm" class="btn btn-success">
                        <i class="feather icon-check-circle mr-1"></i><?= __('add_bonus') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

<!-- Add Partial Payment Modal -->
<div class="modal fade modern-modal" id="partialPaymentModal" tabindex="-1" role="dialog" aria-labelledby="partialPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="partialPaymentModalLabel">
                    <i class="feather icon-credit-card mr-2"></i><?= __('make_payment') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="partialPaymentForm">
                    <input type="hidden" id="clientId" name="client_id">
                    <input type="hidden" id="clientName" name="client_name">
                    
                    <!-- Client Info Section -->
                    <div class="form-section">
                        <div class="alert alert-info mb-4">
                            <div class="d-flex align-items-center mb-3">
                                <i class="feather icon-info-circle mr-2" style="font-size: 1.5rem;"></i>
                                <h6 class="mb-0"><?= __('current_balances') ?></h6>
                            </div>
                        <div class="row">
                            <div class="col-md-6">
                                    <div class="balance-item d-flex align-items-center">
                                        <div class="currency-icon bg-success-light mr-2">
                                            <i class="fas fa-dollar-sign text-success"></i>
                                        </div>
                                        <div>
                                            <div class="balance-label small"><?= __('usd_balance') ?></div>
                                            <div class="balance-value text-success" id="currentUsdBalance">$0.00</div>
                                        </div>
                                    </div>
                            </div>
                            <div class="col-md-6">
                                    <div class="balance-item d-flex align-items-center">
                                        <div class="currency-icon bg-info-light mr-2">
                                            <i class="fas fa-money-bill-wave text-info"></i>
                                        </div>
                                        <div>
                                            <div class="balance-label small"><?= __('afs_balance') ?></div>
                                            <div class="balance-value text-info" id="currentAfsBalance">؋0.00</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Details Section -->
                    <div class="form-section">
                        <div class="form-section-title"><?= __('payment_details') ?></div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-group mb-md-0">
                        <label for="paymentCurrency"><?= __('select_currency_to_update') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="feather icon-dollar-sign"></i></span>
                                        </div>
                        <select class="form-control" id="paymentCurrency" name="payment_currency" required>
                            <option value=""><?= __('select_currency') ?></option>
                            <option value="USD"><?= __('usd') ?></option>
                            <option value="AFS"><?= __('afs') ?></option>
                        </select>
                                    </div>
                        <small class="text-muted"><?= __('select_the_currency_balance_you_want_to_update') ?></small>
                    </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-md-0">
                        <label for="totalAmount"><?= __('amount_to_pay_in_selected_currency') ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                            <span class="input-group-text bg-light" id="totalAmountCurrency">$</span>
                            </div>
                            <input type="number" class="form-control" id="totalAmount" name="total_amount" step="0.01" min="0" required>
                                    </div>
                                </div>
                        </div>
                    </div>
                    
                        <div class="form-group mb-3">
                        <label for="exchangeRate"><?= __('exchange_rate') ?> (<?= __('afs_per_usd') ?>)</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                    <span class="input-group-text bg-light">1 <?= __('usd') ?> =</span>
                            </div>
                            <input type="number" class="form-control" id="exchangeRate" name="exchange_rate" step="0.01" min="0" required>
                            <div class="input-group-append">
                                    <span class="input-group-text bg-light"><?= __('afs') ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Amounts Section -->
                    <div class="form-section">
                        <div class="form-section-title"><?= __('payment_amounts') ?></div>
                        <div class="row mb-3">
                        <div class="col-md-6">
                                <div class="form-group mb-md-0">
                                    <label for="usdAmount"><?= __('payment_in_usd') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                            <span class="input-group-text bg-light">$</span>
                                    </div>
                                    <input type="number" class="form-control" id="usdAmount" name="usd_amount" step="0.01" min="0" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                                <div class="form-group mb-md-0">
                                    <label for="afsAmount"><?= __('payment_in_afs') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                            <span class="input-group-text bg-light">؋</span>
                                    </div>
                                    <input type="number" class="form-control" id="afsAmount" name="afs_amount" step="0.01" min="0" required>
                                </div>
                                    <small class="text-info" id="afsEquivalent"></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Transaction Details Section -->
                    <div class="form-section">
                        <div class="form-section-title"><?= __('transaction_details') ?></div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-group mb-md-0">
                                    <label for="clientMainAccount"><?= __('main_account') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="feather icon-credit-card"></i></span>
                                        </div>
                                        <select class="form-control" id="clientMainAccount" name="main_account" required>
                        <option value=""><?= __('select_account') ?></option>
                            <?php foreach ($mainAccounts as $account): ?>
                            <option value="<?= $account['id'] ?>"><?= htmlspecialchars($account['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-md-0">
                        <label for="receiptNumber"><?= __('receipt_number') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text bg-light"><i class="feather icon-file-text"></i></span>
                                        </div>
                                        <input type="text" class="form-control" id="receiptNumber" name="receipt_number" placeholder="<?= __('enter_receipt_number') ?>">
                                    </div>
                                </div>
                            </div>
                    </div>
                    
                        <div class="form-group mb-0">
                        <label for="remarks"><?= __('remarks') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="feather icon-message-square"></i></span>
                                </div>
                                <textarea class="form-control" id="remarks" name="remarks" rows="2" placeholder="<?= __('enter_payment_details') ?>"></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                </button>
                <button type="button" class="btn btn-primary btn-confirm" id="processPaymentBtn">
                    <i class="feather icon-check-circle mr-1"></i><?= __('process_payment') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Main Account Modal -->
<div class="modal fade modern-modal" id="editMainAccountModal" tabindex="-1" aria-labelledby="editMainAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editMainAccountModalLabel">
                    <i class="feather icon-edit mr-2"></i><?= __('edit_main_account') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editMainAccountForm">
                <div class="modal-body">
                    <input type="hidden" id="edit_account_id" name="account_id">
                    
                    <div class="mb-3">
                        <label for="edit_account_name" class="form-label"><?= __('account_name') ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-light"><i class="feather icon-briefcase"></i></span>
                    </div>
                            <input type="text" id="edit_account_name" name="account_name" class="form-control" placeholder="<?= __('enter_account_name') ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_account_type" class="form-label"><?= __('account_type') ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-light"><i class="feather icon-tag"></i></span>
                            </div>
                        <select id="edit_account_type" name="account_type" class="form-control" required>
                            <option value="internal"><?= __('internal_account') ?></option>
                            <option value="bank"><?= __('bank_account') ?></option>
                        </select>
                    </div>
                    </div>
                    
                    <!-- Bank account specific fields - shown/hidden based on account type -->
                    <div id="edit_bankFields" style="display: none;">
                        <div class="mb-3">
                            <label for="edit_bank_account_number" class="form-label"><?= __('bank_account_usd_number') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="feather icon-hash"></i></span>
                                </div>
                                <input type="text" id="edit_bank_account_number" name="bank_account_number" class="form-control" placeholder="<?= __('enter_bank_account_number') ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="bank_account_afs_number" class="form-label"><?= __('bank_account_afs_number') ?></label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="feather icon-hash"></i></span>
                                </div>
                                <input type="text" id="bank_account_afs_number" name="bank_account_afs_number" class="form-control" placeholder="<?= __('enter_bank_account_afs_number') ?>">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_status" class="form-label"><?= __('status') ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-light"><i class="feather icon-toggle-right"></i></span>
                            </div>
                                <select id="edit_status" name="status" class="form-control" required>
                                    <option value="active"><?= __('active') ?></option>
                                    <option value="inactive"><?= __('inactive') ?></option>
                                </select>
                            </div>
                        </div>
                    
                        <div class="alert alert-warning small mb-0">
                            <div class="d-flex">
                                <i class="feather icon-alert-circle mr-2 mt-1"></i>
                                <div>
                                    <strong><?= __('note') ?>:</strong> <?= __('editing_an_account_doesnt_affect_its_transaction_history') ?>. <?= __('this_will_only_update_the_account_information') ?>.
                                </div>
                            </div>
                        </div>
                    </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                        <i class="feather icon-x mr-1"></i><?= __('cancel') ?>
                    </button>
                    <button type="submit" class="btn btn-primary" id="saveEditMainAccountBtn">
                        <i class="feather icon-save mr-1"></i><?= __('save_changes') ?>
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
    <script src="../assets/js/client-search.js"></script>


    
    <!-- Date Range Picker -->
    <script type="text/javascript" src="../assets/plugins/daterangepicker/moment.min.js"></script>
    <script type="text/javascript" src="../assets/plugins/daterangepicker/daterangepicker.js"></script>

    <!-- Toast Container -->
    <div class="toast-container"></div>

    <!-- Account filters scripts -->
    <script src="js/filters.js"></script>
    <script src="js/toast-notifications.js"></script>
    <script src="js/profile-management.js"></script>
    <script src="js/printing.js"></script>
    <script src="js/account-management.js"></script>
    <script src="js/account-funding.js"></script>
    <script src="js/account-withdrawal.js"></script>
    <script src="js/transaction-management.js"></script>
    <script src="js/status-management.js?v=1.1"></script>

    <!-- Include Admin Footer -->
    <?php include '../includes/admin_footer.php'; ?>
    </body>
</html>

                                               



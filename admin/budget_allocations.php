<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();



// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');

// Month and year filter
$currentMonth = date('m');
$currentYear = date('Y');

// Get selected month and year from filter (if provided)
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : $currentMonth;
$selectedYear = isset($_GET['year']) ? $_GET['year'] : $currentYear;

// Create date range for filtering allocations
$startDate = $selectedYear . '-' . $selectedMonth . '-01';
$endDate = date('Y-m-t', strtotime($startDate));

// Fetch main accounts for allocations
$mainAccountsQuery = "SELECT * FROM main_account WHERE tenant_id = ? ORDER BY name";
$stmt = $pdo->prepare($mainAccountsQuery);
$stmt->execute([$tenant_id]); // pass tenant_id as parameter
$mainAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch categories for allocations
$categoriesQuery = "SELECT * FROM expense_categories WHERE tenant_id = ? ORDER BY name";
$stmt = $pdo->prepare($categoriesQuery);
$stmt->execute([$tenant_id]); // pass tenant_id as parameter
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Fetch existing allocations with date filter
$allocationsQuery = "
    SELECT ba.*, ma.name as account_name, ec.name as category_name 
    FROM budget_allocations ba
    JOIN main_account ma ON ba.main_account_id = ma.id
    JOIN expense_categories ec ON ba.category_id = ec.id
    WHERE ba.allocation_date BETWEEN ? AND ? AND ba.tenant_id = ?
    ORDER BY ba.allocation_date DESC
";
$stmt = $pdo->prepare($allocationsQuery);
$stmt->execute([$startDate, $endDate, $tenant_id]);
$allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->closeCursor();

?>



    <style>
        .card {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: none;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .card-header {
            border-radius: 8px 8px 0 0;
            background-color: #f8f9fa;
        }
        .allocation-card {
            transition: all 0.3s ease;
        }
        .allocation-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .budget-progress {
            height: 10px;
            border-radius: 5px;
        }
        .budget-item {
            border-bottom: 1px solid #eee;
            padding: 10px 0;
        }
        .budget-item:last-child {
            border-bottom: none;
        }
        .category-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 50px;
        }
        .account-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 50px;
            background-color: #e3f2fd;
            color: #0d6efd;
        }
        .date-badge {
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 50px;
            background-color: #f8f9fa;
            color: #6c757d;
        }
        .currency-usd {
            color: #28a745;
        }
        .currency-afs {
            color: #dc3545;
        }
        .currency-eur {
            color: #0d6efd;
        }
        .currency-darham {
            color: #fd7e14;
        }
        .btn-allocation {
            border-radius: 50px;
            padding: 5px 15px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 767px) {
            .date-badge, .account-badge {
                font-size: 0.7rem;
                padding: 2px 6px;
            }
            .btn-allocation {
                width: 100%;
                margin-bottom: 8px;
            }
            .d-flex.flex-wrap.justify-content-between {
                flex-direction: column;
            }
        }
    </style>
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

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        
                        <!-- [ Page Content ] start -->
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><?= __('budget_allocations') ?></h5>
                                        <div class="float-right d-flex align-items-center">
                                            <form method="get" class="form-inline mr-3">
                                                <div class="input-group">
                                                    <select class="form-control" name="month" id="monthFilter">
                                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                                            <option value="<?= sprintf('%02d', $m) ?>" <?= $selectedMonth == sprintf('%02d', $m) ? 'selected' : '' ?>>
                                                                <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                                            </option>
                                                        <?php endfor; ?>
                                                    </select>
                                                    <select class="form-control ml-2" name="year" id="yearFilter">
                                                        <?php for ($y = $currentYear - 2; $y <= $currentYear + 1; $y++): ?>
                                                            <option value="<?= $y ?>" <?= $selectedYear == $y ? 'selected' : '' ?>>
                                                                <?= $y ?>
                                                            </option>
                                                        <?php endfor; ?>
                                                    </select>
                                                    <div class="input-group-append">
                                                        <button type="submit" class="btn btn-outline-secondary">
                                                            <i class="feather icon-filter"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                            <a href="budget_rollover.php" class="btn btn-outline-success mr-2">
                                                <i class="feather icon-refresh-cw"></i> <?= __('budget_rollover') ?>
                                            </a>
                                            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#allocationModal">
                                                <i class="feather icon-plus"></i> <?= __('new_allocation') ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php 
                        // Check for pending rollovers
                        $previousMonth = date('m', strtotime('-1 month'));
                        $previousYear = date('Y', strtotime('-1 month'));
                        $previousMonthStart = $previousYear . '-' . $previousMonth . '-01';
                        $previousMonthEnd = date('Y-m-t', strtotime($previousMonthStart));
                        
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*) FROM budget_allocations 
                            WHERE allocation_date BETWEEN ? AND ? 
                            AND remaining_amount > 0
                            AND tenant_id = ?
                        ");
                        $stmt->execute([$previousMonthStart, $previousMonthEnd, $tenant_id]);
                        $pendingCount = $stmt->fetchColumn();
                        
                        if ($pendingCount > 0): 
                        ?>
                        <div class="row">
                            <div class="col-12">
                                <div class="alert alert-warning">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="feather icon-alert-triangle mr-2"></i> 
                                            <strong><?= __('attention') ?>:</strong> <?= __('there_are') ?> <?= $pendingCount ?> <?= __('budget_allocations_from') ?> <?= date('F Y', strtotime($previousMonthStart)) ?> <?= __('with_remaining_funds') ?>
                                        </div>
                                        <div>
                                            <a href="budget_rollover.php" class="btn btn-sm btn-warning">
                                                <i class="feather icon-refresh-cw mr-1"></i> <?= __('process_rollover') ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Current Month Display -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="feather icon-calendar mr-2"></i> 
                                    <?= __('showing_budget_allocations_for') ?>: 
                                    <strong><?= date('F Y', strtotime($startDate)) ?></strong>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Allocation Summary Cards -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="text-c-blue mb-3"><?= __('total_allocations') ?></h5>
                                        <div class="row align-items-center">
                                            <div class="col-8">
                                                <h3 class="f-w-300 d-flex align-items-center m-b-0">
                                                    <i class="feather icon-arrow-up text-c-green f-30 m-r-10"></i>
                                                    <?php
                                                    $totalUSD = 0;
                                                    $totalAFS = 0;
                                                    $totalEUR = 0;
                                                    $totalDARHAM = 0;
                                                    foreach($allocations as $alloc) {
                                                        if($alloc['currency'] === 'USD') {
                                                            $totalUSD += $alloc['allocated_amount'];
                                                        } else if($alloc['currency'] === 'AFS') {
                                                            $totalAFS += $alloc['allocated_amount'];
                                                        } else if($alloc['currency'] === 'EUR') {
                                                            $totalEUR += $alloc['allocated_amount'];
                                                        } else if($alloc['currency'] === 'DARHAM') {
                                                            $totalDARHAM += $alloc['allocated_amount'];
                                                        }
                                                    }
                                                    echo number_format($totalUSD, 2);
                                                    ?>
                                                </h3>
                                                <p class="text-muted m-b-0"><?= __('usd') ?></p>
                                            </div>
                                            <div class="col-4 text-right">
                                                <p class="m-b-0"><?= number_format($totalAFS, 2) ?> <?= __('afs') ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="text-c-green mb-3"><?= __('available_funds') ?></h5>
                                        <div class="row align-items-center">
                                            <div class="col-8">
                                                <h3 class="f-w-300 d-flex align-items-center m-b-0">
                                                    <i class="feather icon-credit-card text-c-green f-30 m-r-10"></i>
                                                    <?php
                                                    $availableUSD = 0;
                                                    $availableAFS = 0;
                                                    $availableEUR = 0;
                                                    $availableDARHAM = 0;
                                                    foreach($allocations as $alloc) {
                                                        if($alloc['currency'] === 'USD') {
                                                            $availableUSD += $alloc['remaining_amount'];
                                                        } else if($alloc['currency'] === 'AFS') {
                                                            $availableAFS += $alloc['remaining_amount'];
                                                        } else if($alloc['currency'] === 'EUR') {
                                                            $availableEUR += $alloc['remaining_amount'];
                                                        } else if($alloc['currency'] === 'DARHAM') {
                                                            $availableDARHAM += $alloc['remaining_amount'];
                                                        }
                                                    }
                                                    echo number_format($availableUSD, 2);
                                                    ?>
                                                </h3>
                                                <p class="text-muted m-b-0"><?= __('usd') ?></p>
                                            </div>
                                            <div class="col-4 text-right">
                                                <p class="m-b-0"><?= number_format($availableAFS, 2) ?> <?= __('afs') ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="text-c-red mb-3"><?= __('used_funds') ?></h5>
                                        <div class="row align-items-center">
                                            <div class="col-8">
                                                <h3 class="f-w-300 d-flex align-items-center m-b-0">
                                                    <i class="feather icon-arrow-down text-c-red f-30 m-r-10"></i>
                                                    <?php
                                                    $usedUSD = $totalUSD - $availableUSD;
                                                    $usedAFS = $totalAFS - $availableAFS;
                                                    $usedEUR = $totalEUR - $availableEUR;
                                                    $usedDARHAM = $totalDARHAM - $availableDARHAM;
                                                    echo number_format($usedUSD, 2);
                                                    ?>
                                                </h3>
                                                    <p class="text-muted m-b-0"><?= __('usd') ?></p>
                                            </div>
                                            <div class="col-4 text-right">
                                                <p class="m-b-0"><?= number_format($usedAFS, 2) ?> <?= __('afs') ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Budget Allocation Cards -->
                        <div class="row">
                            <?php foreach($allocations as $allocation): ?>
                                <?php 
                                $usedAmount = $allocation['allocated_amount'] - $allocation['remaining_amount'];
                                $usedPercentage = ($allocation['allocated_amount'] > 0) ? 
                                    round(($usedAmount / $allocation['allocated_amount']) * 100) : 0;
                                $progressClass = ($usedPercentage < 50) ? 'bg-success' : 
                                                ($usedPercentage < 75 ? 'bg-warning' : 'bg-danger');
                                $currencyClass = 'currency-' . strtolower($allocation['currency']);
                                ?>
                                <div class="col-xl-4 col-md-6 col-sm-12 mb-3">
                                    <div class="card allocation-card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h5 class="mb-0"><?= htmlspecialchars($allocation['category_name']) ?></h5>
                                                <span class="badge badge-light date-badge">
                                                    <i class="feather icon-calendar mr-1"></i>
                                                    <?= date('d M Y', strtotime($allocation['allocation_date'])) ?>
                                                </span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span class="account-badge">
                                                    <i class="feather icon-briefcase mr-1"></i>
                                                    <?= htmlspecialchars($allocation['account_name']) ?>
                                                </span>
                                                <h5 class="mb-0 <?= $currencyClass ?>">
                                                    <?= number_format($allocation['allocated_amount'], 2) ?> 
                                                    <small><?= $allocation['currency'] ?></small>
                                                </h5>
                                            </div>
                                            <div class="progress budget-progress mb-3">
                                                <div class="progress-bar <?= $progressClass ?>" role="progressbar" 
                                                     style="width: <?= $usedPercentage ?>%" 
                                                     aria-valuenow="<?= $usedPercentage ?>" 
                                                     aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <div class="d-flex justify-content-between mb-3">
                                                <small class="text-muted">Used: <?= number_format($usedAmount, 2) ?> <?= $allocation['currency'] ?></small>
                                                <small class="text-muted">Available: <?= number_format($allocation['remaining_amount'], 2) ?> <?= $allocation['currency'] ?></small>
                                            </div>
                                            <p class="text-muted mb-3"><?= htmlspecialchars($allocation['description']) ?></p>
                                            <div class="d-flex flex-wrap justify-content-between">
                                                <button class="btn btn-sm btn-outline-success mb-2 btn-allocation fund-allocation"
                                                        data-id="<?= $allocation['id'] ?>"
                                                        data-currency="<?= $allocation['currency'] ?>">
                                                    <i class="feather icon-plus-circle mr-1"></i> Fund
                                                </button>
                                                <button class="btn btn-sm btn-outline-info mb-2 btn-allocation view-funds" 
                                                        data-id="<?= $allocation['id'] ?>"
                                                        data-currency="<?= $allocation['currency'] ?>">
                                                    <i class="feather icon-dollar-sign mr-1"></i> View Funds
                                                </button>
                                                <button class="btn btn-sm btn-outline-primary mb-2 btn-allocation view-expenses" 
                                                        data-id="<?= $allocation['id'] ?>">
                                                    <i class="feather icon-eye mr-1"></i> View Expenses
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger mb-2 btn-allocation delete-allocation" 
                                                        data-id="<?= $allocation['id'] ?>"
                                                        <?= ($usedAmount > 0) ? 'disabled' : '' ?>>
                                                    <i class="feather icon-trash-2 mr-1"></i> Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if(count($allocations) === 0): ?>
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body text-center py-5">
                                        <i class="feather icon-alert-circle text-muted" style="font-size: 48px;"></i>
                                        <h5 class="mt-3"><?= __('no_budget_allocations_found') ?></h5>
                                        <p class="text-muted"><?= __('no_budget_allocations_found_for_selected_month') ?></p>
                                        <a href="budget_allocations.php" class="btn btn-outline-primary mt-2">
                                            <i class="feather icon-refresh-cw mr-1"></i> <?= __('show_all_allocations') ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <!-- [ Page Content ] end -->
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- [ Main Content ] end -->

<!-- Allocation Modal -->
<div class="modal fade" id="allocationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('create_budget_allocation') ?></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="allocationForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label><?= __('expense_category') ?></label>
                        <select class="form-control" id="categoryId" name="categoryId" required>
                            <option value=""><?= __('select_category') ?></option>
                            <?php foreach($categories as $category): ?>
                                <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= __('main_account') ?></label>
                        <select class="form-control" id="mainAccountId" name="mainAccountId" required>
                            <option value=""><?= __('select_account') ?></option>
                            <?php foreach($mainAccounts as $account): ?>
                                <option value="<?= $account['id'] ?>"><?= htmlspecialchars($account['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?= __('amount') ?></label>
                                <input type="number" step="0.01" class="form-control" id="amount" name="amount" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?= __('currency') ?></label>
                                <select class="form-control" id="currency" name="currency" required>
                                    <option value=""><?= __('select_currency') ?></option>
                                    <option value="USD"><?= __('usd') ?></option>
                                    <option value="AFS"><?= __('afs') ?></option>
                                    <option value="EUR"><?= __('eur') ?></option>
                                    <option value="DARHAM"><?= __('darham') ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><?= __('allocation_date') ?></label>
                        <input type="date" class="form-control" id="allocationDate" name="allocationDate" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label><?= __('description') ?></label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="submit" class="btn btn-primary"><?= __('create_allocation') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Expenses Modal -->
<div class="modal fade" id="expensesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('expenses_for_allocation') ?></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="allocation-details mb-4">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h5 id="allocation-category" class="mb-2"></h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong><?= __('account') ?>:</strong> <span id="allocation-account"></span></p>
                                    <p class="mb-1"><strong><?= __('date') ?>:</strong> <span id="allocation-date"></span></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong><?= __('total_amount') ?>:</strong> <span id="allocation-amount"></span></p>
                                    <p class="mb-1"><strong><?= __('remaining') ?>:</strong> <span id="allocation-remaining"></span></p>
                                </div>
                            </div>
                            <p class="mt-2 mb-0"><strong><?= __('description') ?>:</strong> <span id="allocation-description"></span></p>
                        </div>
                    </div>
                </div>
                <div class="expenses-list">
                    <h6 class="mb-3"><?= __('related_expenses') ?></h6>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><?= __('date') ?></th>
                                    <th><?= __('description') ?></th>
                                    <th><?= __('amount') ?></th>
                                    <th><?= __('actions') ?></th>
                                </tr>
                            </thead>
                            <tbody id="expenses-table-body">
                                <!-- Expenses will be loaded dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div id="no-expenses-message" class="text-center py-4" style="display: none;">
                    <i class="feather icon-inbox text-muted" style="font-size: 36px;"></i>
                    <p class="mt-3 mb-0"><?= __('no_expenses_found_for_this_allocation') ?></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="addExpenseBtn"><?= __('add_expense') ?></button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Add Fund Modal -->
<div class="modal fade" id="fundAllocationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('add_funds_to_allocation') ?></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="fundAllocationForm">
                <input type="hidden" id="fundAllocationId" name="fundAllocationId">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="feather icon-info mr-2"></i>
                        <?= __('adding_funds_will_increase_both_the_total_allocation_amount_and_the_remaining_amount') ?>
                    </div>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label><?= __('additional_funds') ?></label>
                                <input type="number" step="0.01" class="form-control" id="additionalAmount" name="additionalAmount" required min="0.01">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?= __('currency') ?></label>
                                <input type="text" class="form-control" id="fundCurrency" name="fundCurrency" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><?= __('note') ?></label>
                        <textarea class="form-control" id="fundNote" name="fundNote" rows="2" placeholder="<?= __('reason_for_adding_funds_optional') ?>"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="submit" class="btn btn-success"><?= __('add_funds') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Fund Transactions Modal -->
<div class="modal fade" id="viewFundsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('fund_transactions_for_allocation') ?></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="allocation-funds-details mb-4">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h5 id="funds-allocation-category" class="mb-2"></h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong><?= __('account') ?>:</strong> <span id="funds-allocation-account"></span></p>
                                    <p class="mb-1"><strong><?= __('date') ?>:</strong> <span id="funds-allocation-date"></span></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong><?= __('total_amount') ?>:</strong> <span id="funds-allocation-amount"></span></p>
                                    <p class="mb-1"><strong><?= __('remaining') ?>:</strong> <span id="funds-allocation-remaining"></span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="funds-list">
                    <h6 class="mb-3"><?= __('fund_transactions') ?></h6>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><?= __('date') ?></th>
                                    <th><?= __('description') ?></th>
                                    <th><?= __('amount') ?></th>
                                    <th><?= __('type') ?></th>
                                    <th><?= __('actions') ?></th>
                                </tr>
                            </thead>
                            <tbody id="funds-table-body">
                                <!-- Fund transactions will be loaded dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div id="no-funds-message" class="text-center py-4" style="display: none;">
                    <i class="feather icon-inbox text-muted" style="font-size: 36px;"></i>
                    <p class="mt-3 mb-0"><?= __('no_fund_transactions_found_for_this_allocation') ?></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
            </div>
        </div>
    </div>
</div>

                               <!-- Profile Modal -->
                               <div class="modal fade" id="profileModal" tabindex="-1" role="dialog" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="profileModalLabel">
                    <i class="feather icon-user mr-2"></i><?= __('user_profile') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    
                    <div class="position-relative d-inline-block">
                        <img src="<?= $imagePath ?>" 
                             class="rounded-circle profile-image" 
                             alt="User Profile Image">
                        <div class="profile-status online"></div>
                    </div>
                    <h5 class="mt-3 mb-1"><?= !empty($user['name']) ? htmlspecialchars($user['name']) : 'Guest' ?></h5>
                    <p class="text-muted mb-0"><?= !empty($user['role']) ? htmlspecialchars($user['role']) : 'User' ?></p>
                </div>

                <div class="profile-info">
                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('email') ?></label>
                                <p class="mb-0"><?= !empty($user['email']) ? htmlspecialchars($user['email']) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('phone') ?></label>
                                <p class="mb-0"><?= !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('join_date') ?></label>
                                <p class="mb-0"><?= !empty($user['hire_date']) ? date('M d, Y', strtotime($user['hire_date'])) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('address') ?></label>
                                <p class="mb-0"><?= !empty($user['address']) ? htmlspecialchars($user['address']) : 'Not Set' ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-3 mt-3">
                        <h6 class="mb-3"><?= __('account_information') ?></h6>
                        <div class="activity-timeline">
                            <div class="timeline-item">
                                <i class="activity-icon fas fa-calendar-alt bg-primary"></i>
                                <div class="timeline-content">
                                    <p class="mb-0"><?= __('account_created') ?></p>
                                    <small class="text-muted"><?= !empty($user['created_at']) ? date('M d, Y H:i A', strtotime($user['created_at'])) : 'Not Available' ?></small>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= __('close') ?></button>
                
            </div>
        </div>
    </div>
</div>

<style>
        .profile-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .profile-status {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #2ed8b6;
            border: 2px solid #fff;
        }

        .profile-status.online {
            background-color: #2ed8b6;
        }

        .info-item label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-item p {
            font-weight: 500;
        }

        .activity-timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 15px;
        }

        .activity-icon {
            position: absolute;
            left: -30px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background-color: #e3f2fd;
            color: #2196f3;
            text-align: center;
            line-height: 24px;
            font-size: 12px;
        }

        .modal-content {
            border: none;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .modal-header {
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .modal-footer {
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
        }

        @media (max-width: 576px) {
            .profile-image {
                width: 100px;
                height: 100px;
            }
            
            .modal-dialog {
                margin: 0.5rem;
            }
        }
        /* Updated Modal Styles */
        .modal-lg {
            max-width: 800px;
        }

        .floating-label {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .floating-label input,
        .floating-label textarea {
            height: auto;
            padding: 0.75rem;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            width: 100%;
            font-size: 1rem;
        }

        .floating-label label {
            position: absolute;
            top: 50%;
            left: 0.75rem;
            transform: translateY(-50%);
            pointer-events: none;
            transition: all 0.2s ease;
            color: #6c757d;
            margin: 0;
            padding: 0 0.2rem;
            background-color: #fff;
            font-size: 1rem;
        }

        .floating-label textarea ~ label {
            top: 1rem;
            transform: translateY(0);
        }

        /* Active state - when input has value or is focused */
        .floating-label input:focus ~ label,
        .floating-label input:not(:placeholder-shown) ~ label,
        .floating-label textarea:focus ~ label,
        .floating-label textarea:not(:placeholder-shown) ~ label {
            top: 0;
            transform: translateY(-50%) scale(0.85);
            background-color: #fff;
            color: #4099ff;
            z-index: 1;
        }

        .floating-label input:focus,
        .floating-label textarea:focus {
            border-color: #4099ff;
            box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
            outline: none;
        }

        /* Ensure inputs have placeholder to trigger :not(:placeholder-shown) */
        .floating-label input,
        .floating-label textarea {
            placeholder: " ";
        }

        /* Rest of the styles remain the same */
        .profile-upload-preview {
            width: 150px;
            height: 150px;
            object-fit: cover;
            transition: all 0.3s ease;
        }

        .upload-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            background: rgba(64, 153, 255, 0.9);
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .upload-overlay:hover {
            transform: scale(1.1);
            background: rgba(64, 153, 255, 1);
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .modal-lg {
                max-width: 95%;
                margin: 0.5rem auto;
            }

            .profile-upload-preview {
                width: 120px;
                height: 120px;
            }

            .modal-body {
                padding: 1rem !important;
            }

            .floating-label input,
            .floating-label textarea {
                padding: 0.6rem;
                font-size: 0.95rem;
            }

            .floating-label label {
                font-size: 0.95rem;
            }
        }

        @media (max-width: 576px) {
            .profile-upload-preview {
                width: 100px;
                height: 100px;
            }

            .upload-overlay {
                width: 30px;
                height: 30px;
            }

            .modal-footer {
                flex-direction: column;
            }

            .modal-footer button {
                width: 100%;
                margin: 0.25rem 0;
            }
        }
</style>

                            <!-- Settings Modal -->
                            <div class="modal fade" id="settingsModal" tabindex="-1" role="dialog">
                                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                    <form id="updateProfileForm" enctype="multipart/form-data">
                                        <div class="modal-content shadow-lg border-0">
                                            <div class="modal-header bg-primary text-white border-0">
                                                <h5 class="modal-title">
                                                    <i class="feather icon-settings mr-2"></i><?= __('profile_settings') ?>
                                                </h5>
                                                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="row">
                                                    <!-- Left Column - Profile Picture -->
                                                    <div class="col-md-4 text-center mb-4">
                                                        <div class="position-relative d-inline-block">
                                                            <img src="<?= $imagePath ?>" alt="Profile Picture" 
                                                                 class="profile-upload-preview rounded-circle border shadow-sm"
                                                                 id="profilePreview">
                                                            <label for="profileImage" class="upload-overlay">
                                                                <i class="feather icon-camera"></i>
                                                            </label>
                                                            <input type="file" class="d-none" id="profileImage" name="image" 
                                                                   accept="image/*" onchange="previewImage(this)">
                                                        </div>
                                                        <small class="text-muted d-block mt-2"><?= __('click_to_change_profile_picture') ?></small>
                                                    </div>

                                                    <!-- Right Column - Form Fields -->
                                                    <div class="col-md-8">
                                                        <!-- Personal Info Section -->
                                                        <div class="settings-section active" id="personalInfo">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="feather icon-user mr-2"></i><?= __('personal_information') ?>
                                                            </h6>
                                                            <div class="form-group floating-label">
                                                                <input type="text" class="form-control" id="updateName" name="name" 
                                                                       value="<?= htmlspecialchars($user['name']) ?>" required>
                                                                <label for="updateName"><?= __('full_name') ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <input type="email" class="form-control" id="updateEmail" name="email" 
                                                                       value="<?= htmlspecialchars($user['email']) ?>" required>
                                                                <label for="updateEmail"><?= __('email_address') ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <input type="tel" class="form-control" id="updatePhone" name="phone" 
                                                                       value="<?= htmlspecialchars($user['phone']) ?>">
                                                                <label for="updatePhone"><?= __('phone_number') ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <textarea class="form-control" id="updateAddress" name="address" 
                                                                          rows="3"><?= htmlspecialchars($user['address']) ?></textarea>
                                                                <label for="updateAddress"><?= __('address') ?></label>
                                                            </div>
                                                        </div>

                                                        <!-- Password Section -->
                                                        <div class="settings-section mt-4">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="feather icon-lock mr-2"></i><?= __('change_password') ?>
                                                            </h6>
                                                            <div class="form-group floating-label">
                                                                <input type="password" class="form-control" id="currentPassword" 
                                                                       name="current_password">
                                                                <label for="currentPassword"><?= __('current_password') ?></label>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="form-group floating-label">
                                                                        <input type="password" class="form-control" id="newPassword" 
                                                                               name="new_password">
                                                                        <label for="newPassword"><?= __('new_password') ?></label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="form-group floating-label">
                                                                        <input type="password" class="form-control" id="confirmPassword" 
                                                                               name="confirm_password">
                                                                        <label for="confirmPassword"><?= __('confirm_password') ?></label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0 bg-light">
                                                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                                                    <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                                                </button>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="feather icon-save mr-2"></i><?= __('save_changes') ?>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
$(document).ready(function() {
    // Auto-submit the month/year filter form when selection changes
    $('#monthFilter, #yearFilter').on('change', function() {
        $(this).closest('form').submit();
    });

    // Create budget allocation
    $('#allocationForm').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: 'allocation_actions.php',
            type: 'POST',
            data: {
                action: 'create_allocation',
                category_id: $('#categoryId').val(),
                main_account_id: $('#mainAccountId').val(),
                amount: $('#amount').val(),
                currency: $('#currency').val(),
                date: $('#allocationDate').val(),
                description: $('#description').val()
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                alert('An error occurred while creating the allocation');
            }
        });
    });
    
    console.log('Setting up fund allocation button handlers');
    
    // View fund transactions for an allocation
    $(document).on('click', '.view-funds', function(e) {
        e.preventDefault();
        console.log('View funds button clicked');
        
        const allocationId = $(this).data('id');
        const currency = $(this).data('currency');
        console.log('Allocation ID:', allocationId);
        
        $.ajax({
            url: 'allocation_actions.php',
            type: 'POST',
            data: {
                action: 'get_fund_transactions',
                allocation_id: allocationId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const allocation = response.allocation;
                    const transactions = response.transactions;
                    
                    // Update allocation details
                    $('#funds-allocation-category').text(allocation.category_name);
                    $('#funds-allocation-account').text(allocation.account_name);
                    $('#funds-allocation-date').text(new Date(allocation.allocation_date).toLocaleDateString());
                    $('#funds-allocation-amount').text(`${allocation.allocated_amount} ${allocation.currency}`);
                    $('#funds-allocation-remaining').text(`${allocation.remaining_amount} ${allocation.currency}`);
                    
                    // Clear and populate transactions table
                    const tbody = $('#funds-table-body');
                    tbody.empty();
                    
                    if (transactions.length > 0) {
                        transactions.forEach(transaction => {
                            const createdAt = transaction.created_at ? new Date(transaction.created_at).toLocaleDateString() : 'N/A';
                            const typeClass = transaction.type === 'debit' ? 'text-danger' : 'text-success';
                            const typeIcon = transaction.type === 'debit' ? 'arrow-down' : 'arrow-up';
                            
                            const row = `
                                <tr>
                                    <td>${createdAt}</td>
                                    <td style="max-width: 300px; word-wrap: break-word; white-space: normal;">${transaction.description}</td>
                                    <td>${transaction.amount} ${transaction.currency}</td>
                                    <td class="${typeClass}">
                                        <i class="feather icon-${typeIcon} mr-1"></i>
                                        ${transaction.type === 'debit' ? 'Debit' : 'Credit'}
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-danger delete-fund-transaction" 
                                                data-id="${transaction.id}" 
                                                data-allocation-id="${allocationId}">
                                            <i class="feather icon-trash-2"></i>
                                        </button>
                                    </td>
                                </tr>
                            `;
                            tbody.append(row);
                        });
                        
                        $('.funds-list').show();
                        $('#no-funds-message').hide();
                    } else {
                        $('.funds-list').hide();
                        $('#no-funds-message').show();
                    }
                    
                    // Show modal
                    $('#viewFundsModal').modal('show');
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                alert('<?= __('an_error_occurred_while_fetching_fund_transactions') ?>');
            }
        });
    });
    
    // Delete fund transaction
    $(document).on('click', '.delete-fund-transaction', function() {
        if (confirm('<?= __('are_you_sure_you_want_to_delete_this_transaction_this_may_affect_the_allocation_balance') ?>')) {
            const transactionId = $(this).data('id');
            const allocationId = $(this).data('allocation-id');
            
            $.ajax({
                url: 'allocation_actions.php',
                type: 'POST',
                data: {
                    action: 'delete_fund_transaction',
                    transaction_id: transactionId,
                    allocation_id: allocationId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        // Refresh the transactions list
                        $('.view-funds[data-id="' + allocationId + '"]').trigger('click');
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    alert('<?= __('an_error_occurred_while_deleting_the_transaction') ?>');
                }
            });
        }
    });
    
    // Add funds to allocation - Needs to be delegated for dynamic content
    $(document).on('click', '.fund-allocation', function(e) {
        e.preventDefault();
        console.log('Fund button clicked');
        
        const allocationId = $(this).data('id');
        const currency = $(this).data('currency');
        console.log('Allocation ID:', allocationId, 'Currency:', currency);
        
        // Set values in modal
        $('#fundAllocationId').val(allocationId);
        $('#fundCurrency').val(currency);
        
        // Show modal
        $('#fundAllocationModal').modal('show');
    });
    
    // Handle fund allocation form submission
    $('#fundAllocationForm').on('submit', function(e) {
        e.preventDefault();
        console.log('Fund form submitted');
        
        $.ajax({
            url: 'allocation_actions.php',
            type: 'POST',
            data: {
                action: 'add_funds',
                allocation_id: $('#fundAllocationId').val(),
                amount: $('#additionalAmount').val(),
                note: $('#fundNote').val()
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                alert('<?= __('an_error_occurred_while_adding_funds_to_the_allocation') ?>');
            }
        });
    });
    
    // Delete allocation
    $('.delete-allocation').on('click', function() {
        if (confirm('<?= __('are_you_sure_you_want_to_delete_this_allocation_any_remaining_funds_will_be_returned_to_the_main_account') ?>')) {
            const allocationId = $(this).data('id');
            
            $.ajax({
                url: 'allocation_actions.php',
                type: 'POST',
                data: {
                    action: 'delete_allocation',
                    allocation_id: allocationId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                        alert('<?= __('an_error_occurred_while_deleting_the_allocation') ?>');
                }
            });
        }
    });
    
    // View expenses for an allocation
    $('.view-expenses').on('click', function() {
        const allocationId = $(this).data('id');
        
        $.ajax({
            url: 'allocation_actions.php',
            type: 'POST',
            data: {
                action: 'get_allocation_details',
                allocation_id: allocationId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const allocation = response.allocation;
                    const expenses = response.expenses;
                    
                    // Update allocation details
                    $('#allocation-category').text(allocation.category_name);
                    $('#allocation-account').text(allocation.account_name);
                    $('#allocation-date').text(new Date(allocation.allocation_date).toLocaleDateString());
                    $('#allocation-amount').text(`${allocation.allocated_amount} ${allocation.currency}`);
                    $('#allocation-remaining').text(`${allocation.remaining_amount} ${allocation.currency}`);
                    $('#allocation-description').text(allocation.description || 'No description');
                    
                    // Add allocation ID to Add Expense button for later use
                    $('#addExpenseBtn').data('allocation-id', allocation.id);
                    $('#addExpenseBtn').data('currency', allocation.currency);
                    $('#addExpenseBtn').data('category-id', allocation.category_id);
                    
                    // Clear and populate expenses table
                    const tbody = $('#expenses-table-body');
                    tbody.empty();
                    
                    if (expenses.length > 0) {
                        expenses.forEach(expense => {
                            const row = `
                                <tr>
                                    <td>${new Date(expense.date).toLocaleDateString()}</td>
                                    <td style="max-width: 300px; word-wrap: break-word; white-space: normal;">${expense.description}</td>
                                    <td>${expense.amount} ${expense.currency}</td>
                                    <td>
                                        <button class="btn btn-sm btn-info edit-expense" data-id="${expense.id}">
                                            <i class="feather icon-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger delete-expense" data-id="${expense.id}">
                                            <i class="feather icon-trash-2"></i>
                                        </button>
                                    </td>
                                </tr>
                            `;
                            tbody.append(row);
                        });
                        
                        $('.expenses-list').show();
                        $('#no-expenses-message').hide();
                    } else {
                        $('.expenses-list').hide();
                        $('#no-expenses-message').show();
                    }
                    
                    // Show modal
                    $('#expensesModal').modal('show');
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                alert('<?= __('an_error_occurred_while_fetching_allocation_details') ?>');
            }
        });
    });
    
    // Add expense from allocation
    $('#addExpenseBtn').on('click', function() {
        const allocationId = $(this).data('allocation-id');
        const currency = $(this).data('currency');
        const categoryId = $(this).data('category-id');
        
        // Close current modal
        $('#expensesModal').modal('hide');
        
        // Open expense modal from the main expense page with allocation data
        window.location.href = 'expense_management.php?allocation_id=' + allocationId + 
                               '&currency=' + currency + 
                               '&category_id=' + categoryId;
    });
    
    // Edit expense from allocation view
    $(document).on('click', '.edit-expense', function() {
        const expenseId = $(this).data('id');
        // Redirect to expense edit page with the ID
        window.location.href = 'expense_management.php?edit_expense=' + expenseId;
    });
    
    // Delete expense from allocation view
    $(document).on('click', '.delete-expense', function() {
        if (confirm('<?= __('are_you_sure_you_want_to_delete_this_expense_the_amount_will_be_returned_to_the_allocation') ?>')) {
            const expenseId = $(this).data('id');
            
            $.ajax({
                url: 'expense_actions.php',
                type: 'POST',
                data: {
                    action: 'delete_expense',
                    expenseId: expenseId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        // Close modal and refresh page to see updated allocation
                        $('#expensesModal').modal('hide');
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    alert('<?= __('an_error_occurred_while_deleting_the_expense') ?>');
                }
            });
        }
    });
});
</script>

<script>
                            document.addEventListener('DOMContentLoaded', function() {
                                // Listen for form submission (using submit event)
                                document.getElementById('updateProfileForm').addEventListener('submit', function(e) {
                                    e.preventDefault();
                                    
                                    const newPassword = document.getElementById('newPassword').value;
                                    const confirmPassword = document.getElementById('confirmPassword').value;
                                    const currentPassword = document.getElementById('currentPassword').value;

                                    // If any password field is filled, all password fields must be filled
                                    if (newPassword || confirmPassword || currentPassword) {
                                        if (!currentPassword) {
                                            alert('<?= __('please_enter_your_current_password') ?>');
                                            return;
                                        }
                                        if (!newPassword) {
                                            alert('<?= __('please_enter_a_new_password') ?>');
                                            return;
                                        }
                                        if (!confirmPassword) {
                                            alert('<?= __('please_confirm_your_new_password') ?>');
                                            return;
                                        }
                                        if (newPassword !== confirmPassword) {
                                            alert('<?= __('new_passwords_do_not_match') ?>');
                                            return;
                                        }
                                        if (newPassword.length < 6) {
                                            alert('<?= __('new_password_must_be_at_least_6_characters_long') ?>');
                                            return;
                                        }
                                    }
                                    
                                    const formData = new FormData(this);
                                    
                                    fetch('update_client_profile.php', {
                                        method: 'POST',
                                        body: formData
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            alert(data.message);
                                            // Clear password fields
                                            document.getElementById('currentPassword').value = '';
                                            document.getElementById('newPassword').value = '';
                                            document.getElementById('confirmPassword').value = '';
                                            location.reload();
                                        } else {
                                            alert(data.message || '<?= __('failed_to_update_profile') ?>');
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error:', error);
                                        alert('<?= __('an_error_occurred_while_updating_the_profile') ?>');
                                    });
                                });
                            });
                            </script>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profilePreview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>


<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

</body>
</html> 
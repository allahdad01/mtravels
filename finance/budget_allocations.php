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
if (!isset($_SESSION['user_id'])) {
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
<?php include '../includes/header_finance.php'; ?>

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


<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

</body>
</html> 
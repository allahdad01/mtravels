<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
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
$mainAccountsQuery = "SELECT * FROM main_account WHERE tenant_id = ? AND branch_id = ? ORDER BY name";
$stmt = $pdo->prepare($mainAccountsQuery);
$stmt->execute([$tenant_id, $branch_id]); // pass tenant_id and branch_id as parameters
$mainAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch categories for allocations
$categoriesQuery = "SELECT * FROM expense_categories WHERE tenant_id = ? AND branch_id = ? ORDER BY name";
$stmt = $pdo->prepare($categoriesQuery);
$stmt->execute([$tenant_id, $branch_id]); // pass tenant_id and branch_id as parameters
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Fetch existing allocations with date filter
$allocationsQuery = "
    SELECT ba.*, ma.name as account_name, ec.name as category_name
    FROM budget_allocations ba
    JOIN main_account ma ON ba.main_account_id = ma.id
    JOIN expense_categories ec ON ba.category_id = ec.id
    WHERE ba.allocation_date BETWEEN ? AND ? AND ba.tenant_id = ? AND ba.branch_id = ?
    ORDER BY ba.allocation_date DESC
";
$stmt = $pdo->prepare($allocationsQuery);
$stmt->execute([$startDate, $endDate, $tenant_id, $branch_id]);
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
                            AND tenant_id = ? AND branch_id = ?
                        ");
                        $stmt->execute([$previousMonthStart, $previousMonthEnd, $tenant_id, $branch_id]);
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
                                                <span class="badge-light date-badge">
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


<?php include '../modals/allocation/allocation_modal.php'; ?>
<?php include '../modals/allocation/expense_modal.php'; ?>
<?php include '../modals/allocation/fund_modal.php'; ?>
<?php include '../modals/allocation/view_fund_modal.php'; ?>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script src="../js/allocation/allowcation_management.js"></script>
<script src="../js/allocation/allowcation_event_handlers.js"></script>




<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

</body>
</html> 